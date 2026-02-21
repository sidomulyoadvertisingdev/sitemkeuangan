<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Category;
use App\Models\IuranInstallment;
use App\Models\IuranMember;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class IuranController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $members = IuranMember::withSum('installments as paid_amount', 'amount')
            ->where('user_id', auth()->id())
            ->when($q !== '', function ($query) use ($q) {
                $query->where('name', 'like', '%' . $q . '%');
            })
            ->orderByRaw("CASE WHEN status = 'lunas' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get()
            ->map(function ($member) {
                $paid = (float) ($member->paid_amount ?? 0);
                $target = (float) $member->target_amount;
                $member->remaining_amount = max(0, $target - $paid);
                $member->progress = $target > 0
                    ? min(100, round(($paid / $target) * 100))
                    : 0;

                return $member;
            });

        return view('iuran.index', compact('members', 'q'));
    }

    public function create()
    {
        return view('iuran.create');
    }

    public function store(Request $request)
    {
        $currentYear = (int) date('Y');

        $request->validate([
            'name' => 'required|string|max:120',
            'target_amount' => 'required|numeric|min:1',
            'target_start_year' => 'required|integer|min:2000|max:' . ($currentYear + 50),
            'target_end_year' => 'required|integer|gte:target_start_year|max:' . ($currentYear + 50),
            'note' => 'nullable|string',
        ]);

        IuranMember::create([
            'user_id' => auth()->id(),
            'name' => $request->name,
            'target_amount' => $request->target_amount,
            'target_start_year' => $request->target_start_year,
            'target_end_year' => $request->target_end_year,
            'note' => $request->note,
            'status' => 'aktif',
        ]);

        return redirect()
            ->route('iuran.index')
            ->with('success', 'Data iuran anggota berhasil ditambahkan');
    }

    public function show(IuranMember $iuran)
    {
        abort_if($iuran->user_id !== auth()->id(), 403);

        $iuran->load([
            'installments' => function ($query) {
                $query->latest();
            },
            'installments.bankAccount',
            'installments.category',
        ]);

        $paid = (float) $iuran->installments->sum('amount');
        $remaining = max(0, (float) $iuran->target_amount - $paid);
        $progress = $iuran->target_amount > 0
            ? min(100, round(($paid / $iuran->target_amount) * 100))
            : 0;

        $accounts = BankAccount::where('user_id', auth()->id())->get();
        $categories = Category::where('user_id', auth()->id())
            ->where('type', 'income')
            ->orderBy('name')
            ->get();

        return view('iuran.show', compact(
            'iuran',
            'paid',
            'remaining',
            'progress',
            'accounts',
            'categories'
        ));
    }

    public function edit(IuranMember $iuran)
    {
        abort_if($iuran->user_id !== auth()->id(), 403);

        return view('iuran.edit', compact('iuran'));
    }

    public function update(Request $request, IuranMember $iuran)
    {
        abort_if($iuran->user_id !== auth()->id(), 403);

        $currentYear = (int) date('Y');

        $request->validate([
            'name' => 'required|string|max:120',
            'target_amount' => 'required|numeric|min:1',
            'target_start_year' => 'required|integer|min:2000|max:' . ($currentYear + 50),
            'target_end_year' => 'required|integer|gte:target_start_year|max:' . ($currentYear + 50),
            'note' => 'nullable|string',
        ]);

        $iuran->update([
            'name' => $request->name,
            'target_amount' => $request->target_amount,
            'target_start_year' => $request->target_start_year,
            'target_end_year' => $request->target_end_year,
            'note' => $request->note,
        ]);

        $this->syncStatus($iuran);

        return redirect()
            ->route('iuran.index')
            ->with('success', 'Data iuran anggota berhasil diperbarui');
    }

    public function storeInstallment(Request $request, IuranMember $iuran)
    {
        abort_if($iuran->user_id !== auth()->id(), 403);

        $request->validate([
            'amount' => 'required|numeric|min:1',
            'paid_at' => 'required|date',
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'category_id' => 'required|exists:categories,id',
            'note' => 'nullable|string',
        ]);

        $bank = BankAccount::where('id', $request->bank_account_id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $category = Category::where('id', $request->category_id)
            ->where('user_id', auth()->id())
            ->where('type', 'income')
            ->firstOrFail();

        $businessError = $this->recordInstallment(
            $iuran,
            $bank,
            $category,
            (float) $request->amount,
            (string) $request->paid_at,
            $request->note
        );

        if ($businessError !== null) {
            return back()->withInput()->withErrors($businessError);
        }

        return back()->with('success', 'Cicilan iuran berhasil dicatat');
    }

    public function import(Request $request)
    {
        $currentYear = (int) date('Y');

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if ($handle === false) {
            return back()->withErrors([
                'file' => 'File import tidak dapat dibaca.',
            ]);
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return back()->withErrors([
                'file' => 'File CSV kosong.',
            ]);
        }

        $normalizedHeader = [];
        foreach ($header as $column) {
            $name = strtolower(trim((string) $column));
            $name = preg_replace('/^\xEF\xBB\xBF/', '', $name);
            $normalizedHeader[] = $name;
        }

        $requiredColumns = ['name', 'target_amount', 'target_start_year', 'target_end_year'];
        foreach ($requiredColumns as $column) {
            if (!in_array($column, $normalizedHeader, true)) {
                fclose($handle);
                return back()->withErrors([
                    'file' => 'Format CSV tidak valid. Kolom wajib: name,target_amount,target_start_year,target_end_year,note',
                ]);
            }
        }

        $indexes = array_flip($normalizedHeader);
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $line = 1;
        $warnings = [];

        while (($row = fgetcsv($handle)) !== false) {
            $line++;

            if ($this->isCsvRowEmpty($row)) {
                continue;
            }

            $payload = [
                'name' => trim((string) ($row[$indexes['name']] ?? '')),
                'target_amount' => trim((string) ($row[$indexes['target_amount']] ?? '')),
                'target_start_year' => trim((string) ($row[$indexes['target_start_year']] ?? '')),
                'target_end_year' => trim((string) ($row[$indexes['target_end_year']] ?? '')),
                'note' => trim((string) ($row[$indexes['note']] ?? '')),
            ];

            $validator = Validator::make($payload, [
                'name' => 'required|string|max:120',
                'target_amount' => 'required|numeric|min:1',
                'target_start_year' => 'required|integer|min:2000|max:' . ($currentYear + 50),
                'target_end_year' => 'required|integer|gte:target_start_year|max:' . ($currentYear + 50),
                'note' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                $skipped++;
                if (count($warnings) < 10) {
                    $warnings[] = 'Baris ' . $line . ': ' . implode(' ', $validator->errors()->all());
                }
                continue;
            }

            $member = IuranMember::where('user_id', auth()->id())
                ->whereRaw('LOWER(name) = ?', [strtolower($payload['name'])])
                ->first();

            if ($member) {
                $member->update([
                    'name' => $payload['name'],
                    'target_amount' => $payload['target_amount'],
                    'target_start_year' => $payload['target_start_year'],
                    'target_end_year' => $payload['target_end_year'],
                    'note' => $payload['note'] !== '' ? $payload['note'] : null,
                ]);
                $this->syncStatus($member);
                $updated++;
                continue;
            }

            IuranMember::create([
                'user_id' => auth()->id(),
                'name' => $payload['name'],
                'target_amount' => $payload['target_amount'],
                'target_start_year' => $payload['target_start_year'],
                'target_end_year' => $payload['target_end_year'],
                'note' => $payload['note'] !== '' ? $payload['note'] : null,
                'status' => 'aktif',
            ]);
            $created++;
        }

        fclose($handle);

        $redirect = redirect()
            ->route('iuran.index')
            ->with('success', "Import selesai. Data baru: {$created}, diperbarui: {$updated}, dilewati: {$skipped}.");

        if (!empty($warnings)) {
            $redirect->with('import_warnings', $warnings);
        }

        return $redirect;
    }

    public function downloadTemplate()
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['name', 'target_amount', 'target_start_year', 'target_end_year', 'note']);
        fputcsv($stream, ['Anggota Contoh', '500000', date('Y'), date('Y'), 'Target iuran tahunan']);
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="template-import-iuran.csv"',
        ]);
    }

    public function importInstallments(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if ($handle === false) {
            return back()->withErrors([
                'file' => 'File import cicilan tidak dapat dibaca.',
            ]);
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return back()->withErrors([
                'file' => 'File CSV cicilan kosong.',
            ]);
        }

        $normalizedHeader = [];
        foreach ($header as $column) {
            $name = strtolower(trim((string) $column));
            $name = preg_replace('/^\xEF\xBB\xBF/', '', $name);
            $normalizedHeader[] = $name;
        }

        $requiredColumns = ['member_name', 'amount', 'paid_at', 'bank_account', 'category'];
        foreach ($requiredColumns as $column) {
            if (!in_array($column, $normalizedHeader, true)) {
                fclose($handle);
                return back()->withErrors([
                    'file' => 'Format CSV cicilan tidak valid. Kolom wajib: member_name,amount,paid_at,bank_account,category,note',
                ]);
            }
        }

        $indexes = array_flip($normalizedHeader);
        $imported = 0;
        $skipped = 0;
        $line = 1;
        $warnings = [];
        $userId = auth()->id();

        while (($row = fgetcsv($handle)) !== false) {
            $line++;

            if ($this->isCsvRowEmpty($row)) {
                continue;
            }

            $payload = [
                'member_name' => trim((string) ($row[$indexes['member_name']] ?? '')),
                'amount' => trim((string) ($row[$indexes['amount']] ?? '')),
                'paid_at' => trim((string) ($row[$indexes['paid_at']] ?? '')),
                'bank_account' => trim((string) ($row[$indexes['bank_account']] ?? '')),
                'category' => trim((string) ($row[$indexes['category']] ?? '')),
                'note' => trim((string) ($row[$indexes['note']] ?? '')),
            ];

            $validator = Validator::make($payload, [
                'member_name' => 'required|string|max:120',
                'amount' => 'required|numeric|min:1',
                'paid_at' => 'required|date',
                'bank_account' => 'required|string',
                'category' => 'required|string',
                'note' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                $skipped++;
                if (count($warnings) < 15) {
                    $warnings[] = 'Baris ' . $line . ': ' . implode(' ', $validator->errors()->all());
                }
                continue;
            }

            $member = IuranMember::where('user_id', $userId)
                ->whereRaw('LOWER(name) = ?', [strtolower($payload['member_name'])])
                ->first();

            if (!$member) {
                $skipped++;
                if (count($warnings) < 15) {
                    $warnings[] = 'Baris ' . $line . ': anggota "' . $payload['member_name'] . '" tidak ditemukan.';
                }
                continue;
            }

            $bankInput = $payload['bank_account'];
            $bank = is_numeric($bankInput)
                ? BankAccount::where('user_id', $userId)->where('id', (int) $bankInput)->first()
                : BankAccount::where('user_id', $userId)->whereRaw('LOWER(name) = ?', [strtolower($bankInput)])->first();

            if (!$bank) {
                $skipped++;
                if (count($warnings) < 15) {
                    $warnings[] = 'Baris ' . $line . ': rekening "' . $bankInput . '" tidak ditemukan.';
                }
                continue;
            }

            $categoryInput = $payload['category'];
            $category = is_numeric($categoryInput)
                ? Category::where('user_id', $userId)->where('type', 'income')->where('id', (int) $categoryInput)->first()
                : Category::where('user_id', $userId)->where('type', 'income')->whereRaw('LOWER(name) = ?', [strtolower($categoryInput)])->first();

            if (!$category) {
                $skipped++;
                if (count($warnings) < 15) {
                    $warnings[] = 'Baris ' . $line . ': kategori pemasukan "' . $categoryInput . '" tidak ditemukan.';
                }
                continue;
            }

            $businessError = $this->recordInstallment(
                $member,
                $bank,
                $category,
                (float) $payload['amount'],
                (string) $payload['paid_at'],
                $payload['note'] !== '' ? $payload['note'] : null
            );

            if ($businessError !== null) {
                $skipped++;
                if (count($warnings) < 15) {
                    $warnings[] = 'Baris ' . $line . ': ' . $businessError;
                }
                continue;
            }

            $imported++;
        }

        fclose($handle);

        $redirect = redirect()
            ->route('iuran.index')
            ->with('success', "Import cicilan selesai. Berhasil: {$imported}, dilewati: {$skipped}.");

        if (!empty($warnings)) {
            $redirect->with('import_installment_warnings', $warnings);
        }

        return $redirect;
    }

    public function downloadInstallmentTemplate()
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['member_name', 'amount', 'paid_at', 'bank_account', 'category', 'note']);
        fputcsv($stream, ['Anggota Contoh', '100000', date('Y-m-d'), 'BCA Utama', 'Iuran Pemuda', 'Import cicilan manual']);
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="template-import-cicilan-iuran.csv"',
        ]);
    }

    public function exportPdf()
    {
        $members = IuranMember::withSum('installments as paid_amount', 'amount')
            ->where('user_id', auth()->id())
            ->orderByRaw("CASE WHEN status = 'lunas' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get()
            ->map(function ($member) {
                $member->paid_amount = (float) ($member->paid_amount ?? 0);
                $member->remaining_amount = max(0, (float) $member->target_amount - $member->paid_amount);
                $member->status_label = $member->remaining_amount <= 0 ? 'Lunas' : 'Belum Lunas';
                return $member;
            });

        $totalTarget = (float) $members->sum('target_amount');
        $totalPaid = (float) $members->sum('paid_amount');
        $totalRemaining = max(0, $totalTarget - $totalPaid);
        $progress = $totalTarget > 0 ? round(($totalPaid / $totalTarget) * 100) : 0;
        $lunasCount = (int) $members->where('status_label', 'Lunas')->count();
        $belumLunasCount = (int) $members->where('status_label', 'Belum Lunas')->count();

        $summary = [
            'generated_at' => date('d-m-Y H:i:s'),
            'user_name' => auth()->user()->name,
            'total_target' => $totalTarget,
            'total_paid' => $totalPaid,
            'total_remaining' => $totalRemaining,
            'progress' => $progress,
            'lunas_count' => $lunasCount,
            'belum_lunas_count' => $belumLunasCount,
        ];

        $pdf = $this->buildIuranReportPdf($members, $summary);
        $filename = 'laporan-iuran-' . date('Ymd-His') . '.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function destroy(IuranMember $iuran)
    {
        abort_if($iuran->user_id !== auth()->id(), 403);

        if ($iuran->installments()->exists()) {
            return back()->withErrors([
                'delete' => 'Anggota yang sudah memiliki cicilan tidak bisa dihapus.',
            ]);
        }

        $iuran->delete();

        return redirect()
            ->route('iuran.index')
            ->with('success', 'Data anggota iuran berhasil dihapus');
    }

    private function recordInstallment(
        IuranMember $iuran,
        BankAccount $bank,
        Category $category,
        float $amount,
        string $paidAt,
        ?string $note
    ): ?string {
        $paid = (float) $iuran->installments()->sum('amount');
        $remaining = max(0, (float) $iuran->target_amount - $paid);

        if ($remaining <= 0) {
            return 'Target iuran anggota ini sudah tercapai.';
        }

        if ($amount > $remaining) {
            return 'Nominal cicilan melebihi sisa iuran (Rp ' . number_format($remaining, 0, ',', '.') . ').';
        }

        $paidYear = (int) date('Y', strtotime($paidAt));
        if ($paidYear < (int) $iuran->target_start_year || $paidYear > (int) $iuran->target_end_year) {
            return 'Tanggal bayar harus berada pada periode target ' . $iuran->target_period . '.';
        }

        $installment = IuranInstallment::create([
            'iuran_member_id' => $iuran->id,
            'bank_account_id' => $bank->id,
            'category_id' => $category->id,
            'amount' => $amount,
            'paid_at' => $paidAt,
            'note' => $note,
        ]);

        Transaction::create([
            'user_id' => auth()->id(),
            'type' => 'income',
            'category_id' => $category->id,
            'project_id' => null,
            'bank_account_id' => $bank->id,
            'amount' => $installment->amount,
            'date' => $installment->paid_at,
            'note' => $installment->note ?: ('Iuran anggota: ' . $iuran->name),
        ]);

        $this->adjustBankBalance($bank, (float) $installment->amount);
        $this->syncStatus($iuran);

        return null;
    }

    private function syncStatus(IuranMember $iuran): void
    {
        $paid = (float) $iuran->installments()->sum('amount');
        $target = (float) $iuran->target_amount;

        $iuran->update([
            'status' => $paid >= $target ? 'lunas' : 'aktif',
        ]);
    }

    private function adjustBankBalance(BankAccount $bank, float $delta): void
    {
        $bank->balance += $delta;
        $bank->save();
    }

    private function isCsvRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function truncateText(string $text, int $maxLength): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if (strlen($clean) <= $maxLength) {
            return $clean;
        }

        return substr($clean, 0, $maxLength - 3) . '...';
    }

    private function buildIuranReportPdf($members, array $summary): string
    {
        $left = 30;
        $right = 565;
        $bottom = 45;
        $rowHeight = 18;
        $columns = [30, 55, 200, 270, 350, 430, 500, 565];

        $pageStreams = [];
        $commands = [];
        $currentY = $this->appendIuranReportHeader($commands, $summary, false, $columns, $left, $right, $rowHeight);

        if ($members->isEmpty()) {
            $this->appendIuranRow($commands, $columns, $currentY, $rowHeight, [
                'no' => '',
                'name' => 'Belum ada data iuran anggota.',
                'period' => '',
                'target' => '',
                'paid' => '',
                'remaining' => '',
                'status' => '',
            ]);
        } else {
            foreach ($members->values() as $index => $member) {
                if (($currentY - $rowHeight) < $bottom) {
                    $pageStreams[] = implode("\n", $commands) . "\n";
                    $commands = [];
                    $currentY = $this->appendIuranReportHeader($commands, $summary, true, $columns, $left, $right, $rowHeight);
                }

                $this->appendIuranRow($commands, $columns, $currentY, $rowHeight, [
                    'no' => (string) ($index + 1),
                    'name' => $this->truncateText((string) $member->name, 28),
                    'period' => $this->truncateText((string) $member->target_period, 12),
                    'target' => number_format((float) $member->target_amount, 0, ',', '.'),
                    'paid' => number_format((float) $member->paid_amount, 0, ',', '.'),
                    'remaining' => number_format((float) $member->remaining_amount, 0, ',', '.'),
                    'status' => (string) $member->status_label,
                ]);

                $currentY -= $rowHeight;
            }
        }

        $pageStreams[] = implode("\n", $commands) . "\n";

        return $this->buildPdfFromPageStreams($pageStreams);
    }

    private function appendIuranReportHeader(
        array &$commands,
        array $summary,
        bool $continuation,
        array $columns,
        int $left,
        int $right,
        int $rowHeight
    ): float {
        $title = $continuation ? 'LAPORAN IURAN PEMUDA (LANJUTAN)' : 'LAPORAN IURAN PEMUDA';
        $commands[] = $this->pdfTextCommand(30, 810, $title, 14, 'F2');
        $commands[] = $this->pdfLineCommand($left, 802, $right, 802);

        if ($continuation) {
            $tableTop = 770;
        } else {
            $commands[] = $this->pdfTextCommand(30, 785, 'Tanggal Export : ' . ($summary['generated_at'] ?? '-'), 9, 'F1');
            $commands[] = $this->pdfTextCommand(30, 771, 'User          : ' . ($summary['user_name'] ?? '-'), 9, 'F1');
            $commands[] = $this->pdfTextCommand(30, 757, 'Total Target   : Rp ' . number_format((float) ($summary['total_target'] ?? 0), 0, ',', '.'), 9, 'F1');
            $commands[] = $this->pdfTextCommand(30, 743, 'Total Tercapai : Rp ' . number_format((float) ($summary['total_paid'] ?? 0), 0, ',', '.'), 9, 'F1');
            $commands[] = $this->pdfTextCommand(30, 729, 'Total Sisa     : Rp ' . number_format((float) ($summary['total_remaining'] ?? 0), 0, ',', '.'), 9, 'F1');
            $commands[] = $this->pdfTextCommand(30, 715, 'Progress       : ' . ((int) ($summary['progress'] ?? 0)) . '%', 9, 'F1');
            $commands[] = $this->pdfTextCommand(
                30,
                701,
                'Status Anggota: Lunas ' . ((int) ($summary['lunas_count'] ?? 0)) . ' | Belum Lunas ' . ((int) ($summary['belum_lunas_count'] ?? 0)),
                9,
                'F1'
            );
            $tableTop = 675;
        }

        $commands[] = $this->pdfLineCommand($left, $tableTop, $right, $tableTop);
        $commands[] = $this->pdfLineCommand($left, $tableTop - $rowHeight, $right, $tableTop - $rowHeight);

        foreach ($columns as $x) {
            $commands[] = $this->pdfLineCommand($x, $tableTop, $x, $tableTop - $rowHeight);
        }

        $headerY = $tableTop - 12;
        $commands[] = $this->pdfTextCommand($columns[0] + 2, $headerY, 'No', 8, 'F2');
        $commands[] = $this->pdfTextCommand($columns[1] + 2, $headerY, 'Nama Anggota', 8, 'F2');
        $commands[] = $this->pdfTextCommand($columns[2] + 2, $headerY, 'Periode', 8, 'F2');
        $commands[] = $this->pdfTextCommand($columns[3] + 2, $headerY, 'Target', 8, 'F2');
        $commands[] = $this->pdfTextCommand($columns[4] + 2, $headerY, 'Terbayar', 8, 'F2');
        $commands[] = $this->pdfTextCommand($columns[5] + 2, $headerY, 'Sisa', 8, 'F2');
        $commands[] = $this->pdfTextCommand($columns[6] + 2, $headerY, 'Status', 8, 'F2');

        return $tableTop - $rowHeight;
    }

    private function appendIuranRow(array &$commands, array $columns, float $topY, int $rowHeight, array $row): void
    {
        $bottomY = $topY - $rowHeight;
        $left = $columns[0];
        $right = $columns[count($columns) - 1];

        $commands[] = $this->pdfLineCommand($left, $bottomY, $right, $bottomY);
        foreach ($columns as $x) {
            $commands[] = $this->pdfLineCommand($x, $topY, $x, $bottomY);
        }

        $textY = $topY - 12;
        $commands[] = $this->pdfTextCommand($columns[0] + 2, $textY, (string) ($row['no'] ?? ''), 8, 'F1');
        $commands[] = $this->pdfTextCommand($columns[1] + 2, $textY, (string) ($row['name'] ?? ''), 8, 'F1');
        $commands[] = $this->pdfTextCommand($columns[2] + 2, $textY, (string) ($row['period'] ?? ''), 8, 'F1');
        $commands[] = $this->pdfTextCommand($columns[3] + 2, $textY, (string) ($row['target'] ?? ''), 8, 'F1');
        $commands[] = $this->pdfTextCommand($columns[4] + 2, $textY, (string) ($row['paid'] ?? ''), 8, 'F1');
        $commands[] = $this->pdfTextCommand($columns[5] + 2, $textY, (string) ($row['remaining'] ?? ''), 8, 'F1');
        $commands[] = $this->pdfTextCommand($columns[6] + 2, $textY, (string) ($row['status'] ?? ''), 8, 'F1');
    }

    private function buildPdfFromPageStreams(array $pageStreams): string
    {
        $objects = [];
        $addObject = function (string $content) use (&$objects): int {
            $objects[] = $content;
            return count($objects);
        };

        $fontRegularId = $addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
        $fontBoldId = $addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>');
        $pagesId = $addObject('');

        $pageIds = [];
        foreach ($pageStreams as $stream) {
            $contentId = $addObject("<< /Length " . strlen($stream) . " >>\nstream\n{$stream}endstream");
            $pageId = $addObject(
                "<< /Type /Page /Parent {$pagesId} 0 R /MediaBox [0 0 595 842] "
                . "/Resources << /Font << /F1 {$fontRegularId} 0 R /F2 {$fontBoldId} 0 R >> >> "
                . "/Contents {$contentId} 0 R >>"
            );
            $pageIds[] = $pageId;
        }

        $kids = implode(' ', array_map(fn ($id) => "{$id} 0 R", $pageIds));
        $objects[$pagesId - 1] = "<< /Type /Pages /Kids [{$kids}] /Count " . count($pageIds) . " >>";

        $catalogId = $addObject("<< /Type /Catalog /Pages {$pagesId} 0 R >>");

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        $count = count($objects);

        for ($i = 1; $i <= $count; $i++) {
            $offsets[$i] = strlen($pdf);
            $pdf .= $i . " 0 obj\n" . $objects[$i - 1] . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . ($count + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size " . ($count + 1) . " /Root {$catalogId} 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function pdfTextCommand(float $x, float $y, string $text, int $size = 9, string $font = 'F1'): string
    {
        return sprintf(
            "BT /%s %d Tf 1 0 0 1 %s %s Tm (%s) Tj ET",
            $font,
            $size,
            $x,
            $y,
            $this->escapePdfText($this->normalizePdfText($text))
        );
    }

    private function pdfLineCommand(float $x1, float $y1, float $x2, float $y2): string
    {
        return sprintf("%s %s m %s %s l S", $x1, $y1, $x2, $y2);
    }

    private function buildSimplePdf(array $lines): string
    {
        $linesPerPage = 48;
        $pages = array_chunk($lines, $linesPerPage);

        $objects = [];
        $addObject = function (string $content) use (&$objects): int {
            $objects[] = $content;
            return count($objects);
        };

        $fontId = $addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
        $pagesId = $addObject('');

        $pageIds = [];
        foreach ($pages as $pageLines) {
            $y = 810;
            $streamLines = ['BT', '/F1 10 Tf'];
            foreach ($pageLines as $line) {
                $streamLines[] = sprintf(
                    '1 0 0 1 30 %d Tm (%s) Tj',
                    $y,
                    $this->escapePdfText($this->normalizePdfText($line))
                );
                $y -= 16;
            }
            $streamLines[] = 'ET';
            $stream = implode("\n", $streamLines) . "\n";

            $contentId = $addObject("<< /Length " . strlen($stream) . " >>\nstream\n{$stream}endstream");
            $pageId = $addObject(
                "<< /Type /Page /Parent {$pagesId} 0 R /MediaBox [0 0 595 842] "
                . "/Resources << /Font << /F1 {$fontId} 0 R >> >> "
                . "/Contents {$contentId} 0 R >>"
            );
            $pageIds[] = $pageId;
        }

        $kids = implode(' ', array_map(fn ($id) => "{$id} 0 R", $pageIds));
        $objects[$pagesId - 1] = "<< /Type /Pages /Kids [{$kids}] /Count " . count($pageIds) . " >>";

        $catalogId = $addObject("<< /Type /Catalog /Pages {$pagesId} 0 R >>");

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        $count = count($objects);

        for ($i = 1; $i <= $count; $i++) {
            $offsets[$i] = strlen($pdf);
            $pdf .= $i . " 0 obj\n" . $objects[$i - 1] . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . ($count + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size " . ($count + 1) . " /Root {$catalogId} 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function normalizePdfText(string $text): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $clean);
            if ($converted !== false) {
                return $converted;
            }
        }

        return $clean;
    }

    private function escapePdfText(string $text): string
    {
        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\(', '\)'],
            $text
        );
    }
}
