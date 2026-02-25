<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        return view('reports.index', $this->buildReportData($request));
    }

    public function exportPdf(Request $request)
    {
        $data = $this->buildReportData($request);

        $summary = [
            'generated_at' => now()->format('d-m-Y H:i:s'),
            'user_name' => auth()->user()->name,
            'start_date' => $data['startDate'],
            'end_date' => $data['endDate'],
            'total_income' => (float) $data['totalIncome'],
            'total_expense' => (float) $data['totalExpense'],
            'net_balance' => (float) $data['netBalance'],
            'transaction_count' => $data['transactions']->count(),
        ];

        $pdf = $this->buildReportPdf($data, $summary);
        $filename = 'laporan-lengkap-keuangan-' . date('Ymd-His') . '.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function buildReportData(Request $request): array
    {
        $userId = auth()->user()->tenantUserId();
        $defaultStartDate = now()->startOfMonth()->toDateString();
        $defaultEndDate = now()->toDateString();
        $startDate = (string) $request->query('start_date', $defaultStartDate);
        $endDate = (string) $request->query('end_date', $defaultEndDate);

        $validated = validator(
            ['start_date' => $startDate, 'end_date' => $endDate],
            [
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]
        )->validate();

        $transactions = Transaction::with(['category', 'bankAccount', 'project'])
            ->where('user_id', $userId)
            ->whereBetween('date', [$validated['start_date'], $validated['end_date']])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->map(function (Transaction $transaction) {
                $transaction->source_label = $this->resolveSourceLabel($transaction);
                return $transaction;
            });

        $totalIncome = (float) $transactions->where('type', 'income')->sum('amount');
        $totalExpense = (float) $transactions->where('type', 'expense')->sum('amount');
        $netBalance = $totalIncome - $totalExpense;

        $incomeBySource = $this->groupAmountByLabel(
            $transactions->where('type', 'income'),
            fn (Transaction $transaction) => (string) $transaction->source_label
        );
        $expenseBySource = $this->groupAmountByLabel(
            $transactions->where('type', 'expense'),
            fn (Transaction $transaction) => (string) $transaction->source_label
        );
        $incomeByCategory = $this->groupAmountByLabel(
            $transactions->where('type', 'income'),
            fn (Transaction $transaction) => (string) ($transaction->category?->name ?? 'Tanpa Kategori')
        );
        $expenseByCategory = $this->groupAmountByLabel(
            $transactions->where('type', 'expense'),
            fn (Transaction $transaction) => (string) ($transaction->category?->name ?? 'Tanpa Kategori')
        );
        $incomeByBankAccount = $this->groupAmountByLabel(
            $transactions->where('type', 'income'),
            fn (Transaction $transaction) => (string) ($transaction->bankAccount?->name ?? 'Tanpa Rekening')
        );
        $expenseByBankAccount = $this->groupAmountByLabel(
            $transactions->where('type', 'expense'),
            fn (Transaction $transaction) => (string) ($transaction->bankAccount?->name ?? 'Tanpa Rekening')
        );

        $expenseUsage = $expenseByCategory
            ->map(function (float $amount, string $label) use ($totalExpense) {
                $percent = $totalExpense > 0 ? round(($amount / $totalExpense) * 100, 2) : 0;

                return [
                    'label' => $label,
                    'amount' => $amount,
                    'percent' => $percent,
                ];
            })
            ->values();

        return [
            'startDate' => $validated['start_date'],
            'endDate' => $validated['end_date'],
            'transactions' => $transactions,
            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
            'netBalance' => $netBalance,
            'incomeBySource' => $incomeBySource,
            'expenseBySource' => $expenseBySource,
            'incomeByCategory' => $incomeByCategory,
            'expenseByCategory' => $expenseByCategory,
            'incomeByBankAccount' => $incomeByBankAccount,
            'expenseByBankAccount' => $expenseByBankAccount,
            'expenseUsage' => $expenseUsage,
        ];
    }

    private function groupAmountByLabel(Collection $transactions, callable $labelResolver): Collection
    {
        return $transactions
            ->groupBy($labelResolver)
            ->map(fn (Collection $items) => (float) $items->sum('amount'))
            ->sortDesc();
    }

    private function resolveSourceLabel(Transaction $transaction): string
    {
        if ($transaction->project_id && $transaction->project?->name) {
            return 'Proyek: ' . $transaction->project->name;
        }

        $note = strtolower((string) $transaction->note);
        if ($note !== '' && str_contains($note, '[transfer]')) {
            return 'Transfer Antar Organisasi';
        }

        if ($note !== '' && str_contains($note, 'iuran')) {
            return 'Iuran Pemuda';
        }

        if ($note !== '' && str_contains($note, 'pembayaran')) {
            return 'Hutang / Piutang';
        }

        return 'Transaksi Umum';
    }

    private function buildReportPdf(array $data, array $summary): string
    {
        $rowHeight = 16;
        $bottom = 45;
        $columns = [30, 52, 106, 150, 250, 340, 430, 505, 565];

        $pageStreams = [];
        $commands = [];

        $tableTop = $this->appendReportFirstPageHeader($commands, $summary, $data);
        $currentY = $this->appendDetailTableHeader($commands, $columns, $tableTop, $rowHeight);

        if ($data['transactions']->isEmpty()) {
            $this->appendDetailRow($commands, $columns, $currentY, $rowHeight, [
                'no' => '',
                'date' => '',
                'type' => '',
                'source' => 'Tidak ada transaksi pada periode ini',
                'category' => '',
                'account' => '',
                'note' => '',
                'amount' => '',
            ]);
        } else {
            foreach ($data['transactions']->values() as $index => $transaction) {
                if (($currentY - $rowHeight) < $bottom) {
                    $pageStreams[] = implode("\n", $commands) . "\n";
                    $commands = [];
                    $tableTop = $this->appendReportContinuationHeader($commands, $summary);
                    $currentY = $this->appendDetailTableHeader($commands, $columns, $tableTop, $rowHeight);
                }

                $this->appendDetailRow($commands, $columns, $currentY, $rowHeight, [
                    'no' => (string) ($index + 1),
                    'date' => date('d-m-Y', strtotime((string) $transaction->date)),
                    'type' => $transaction->type === 'income' ? 'Masuk' : 'Keluar',
                    'source' => $this->truncateText((string) $transaction->source_label, 21),
                    'category' => $this->truncateText((string) ($transaction->category?->name ?? '-'), 17),
                    'account' => $this->truncateText((string) ($transaction->bankAccount?->name ?? '-'), 17),
                    'note' => $this->truncateText((string) ($transaction->note ?? '-'), 14),
                    'amount' => number_format((float) $transaction->amount, 0, ',', '.'),
                ]);

                $currentY -= $rowHeight;
            }
        }

        $pageStreams[] = implode("\n", $commands) . "\n";

        return $this->buildPdfFromPageStreams($pageStreams);
    }

    private function appendReportFirstPageHeader(array &$commands, array $summary, array $data): float
    {
        $commands[] = $this->pdfTextCommand(30, 810, 'LAPORAN LENGKAP KEUANGAN', 14, 'F2');
        $commands[] = $this->pdfLineCommand(30, 802, 565, 802);
        $commands[] = $this->pdfTextCommand(30, 785, 'Tanggal Export : ' . $summary['generated_at'], 9, 'F1');
        $commands[] = $this->pdfTextCommand(30, 771, 'User           : ' . $summary['user_name'], 9, 'F1');
        $commands[] = $this->pdfTextCommand(
            30,
            757,
            'Periode        : ' . date('d-m-Y', strtotime((string) $summary['start_date'])) . ' s/d ' . date('d-m-Y', strtotime((string) $summary['end_date'])),
            9,
            'F1'
        );
        $commands[] = $this->pdfTextCommand(30, 743, 'Total Pemasukan : Rp ' . number_format((float) $summary['total_income'], 0, ',', '.'), 9, 'F1');
        $commands[] = $this->pdfTextCommand(30, 729, 'Total Pengeluaran: Rp ' . number_format((float) $summary['total_expense'], 0, ',', '.'), 9, 'F1');
        $commands[] = $this->pdfTextCommand(30, 715, 'Saldo Bersih    : Rp ' . number_format((float) $summary['net_balance'], 0, ',', '.'), 9, 'F1');
        $commands[] = $this->pdfTextCommand(30, 701, 'Jumlah Transaksi: ' . (string) $summary['transaction_count'], 9, 'F1');

        $y = 684;
        $y = $this->appendRankingSection(
            $commands,
            $y,
            'Sumber Pemasukan Terbesar',
            $data['incomeBySource']->take(6)
        );
        $y = $this->appendRankingSection(
            $commands,
            $y - 4,
            'Sumber Pengeluaran Terbesar',
            $data['expenseBySource']->take(6)
        );

        $commands[] = $this->pdfTextCommand(30, $y - 4, 'Pengeluaran Untuk Apa Saja (Top 6)', 9, 'F2');
        $y -= 16;
        $expenseTop = collect($data['expenseUsage'])->take(6);
        if ($expenseTop->isEmpty()) {
            $commands[] = $this->pdfTextCommand(38, $y, '- Tidak ada data', 8, 'F1');
            $y -= 12;
        } else {
            foreach ($expenseTop as $item) {
                $line = '- ' . $this->truncateText((string) $item['label'], 28)
                    . ': Rp ' . number_format((float) $item['amount'], 0, ',', '.')
                    . ' (' . number_format((float) $item['percent'], 2, ',', '.') . '%)';
                $commands[] = $this->pdfTextCommand(38, $y, $line, 8, 'F1');
                $y -= 12;
            }
        }

        return max(430, $y - 6);
    }

    private function appendReportContinuationHeader(array &$commands, array $summary): float
    {
        $commands[] = $this->pdfTextCommand(30, 810, 'LAPORAN LENGKAP KEUANGAN (LANJUTAN)', 14, 'F2');
        $commands[] = $this->pdfLineCommand(30, 802, 565, 802);
        $commands[] = $this->pdfTextCommand(
            30,
            785,
            'Periode: ' . date('d-m-Y', strtotime((string) $summary['start_date'])) . ' s/d ' . date('d-m-Y', strtotime((string) $summary['end_date'])),
            9,
            'F1'
        );
        return 760;
    }

    private function appendRankingSection(array &$commands, float $startY, string $title, Collection $rows): float
    {
        $commands[] = $this->pdfTextCommand(30, $startY, $title . ' (Top 6)', 9, 'F2');
        $y = $startY - 16;

        if ($rows->isEmpty()) {
            $commands[] = $this->pdfTextCommand(38, $y, '- Tidak ada data', 8, 'F1');
            return $y - 12;
        }

        foreach ($rows as $label => $amount) {
            $line = '- ' . $this->truncateText((string) $label, 30)
                . ': Rp ' . number_format((float) $amount, 0, ',', '.');
            $commands[] = $this->pdfTextCommand(38, $y, $line, 8, 'F1');
            $y -= 12;
        }

        return $y;
    }

    private function appendDetailTableHeader(array &$commands, array $columns, float $topY, int $rowHeight): float
    {
        $commands[] = $this->pdfLineCommand($columns[0], $topY, $columns[count($columns) - 1], $topY);
        $commands[] = $this->pdfLineCommand($columns[0], $topY - $rowHeight, $columns[count($columns) - 1], $topY - $rowHeight);
        foreach ($columns as $x) {
            $commands[] = $this->pdfLineCommand($x, $topY, $x, $topY - $rowHeight);
        }

        $textY = $topY - 11;
        $commands[] = $this->pdfTextCommand($columns[0] + 2, $textY, 'No', 7, 'F2');
        $commands[] = $this->pdfTextCommand($columns[1] + 2, $textY, 'Tanggal', 7, 'F2');
        $commands[] = $this->pdfTextCommand($columns[2] + 2, $textY, 'Jenis', 7, 'F2');
        $commands[] = $this->pdfTextCommand($columns[3] + 2, $textY, 'Sumber', 7, 'F2');
        $commands[] = $this->pdfTextCommand($columns[4] + 2, $textY, 'Kategori', 7, 'F2');
        $commands[] = $this->pdfTextCommand($columns[5] + 2, $textY, 'Rekening', 7, 'F2');
        $commands[] = $this->pdfTextCommand($columns[6] + 2, $textY, 'Catatan', 7, 'F2');
        $commands[] = $this->pdfTextCommand($columns[7] + 2, $textY, 'Nominal', 7, 'F2');

        return $topY - $rowHeight;
    }

    private function appendDetailRow(array &$commands, array $columns, float $topY, int $rowHeight, array $row): void
    {
        $bottomY = $topY - $rowHeight;
        $commands[] = $this->pdfLineCommand($columns[0], $bottomY, $columns[count($columns) - 1], $bottomY);
        foreach ($columns as $x) {
            $commands[] = $this->pdfLineCommand($x, $topY, $x, $bottomY);
        }

        $textY = $topY - 11;
        $commands[] = $this->pdfTextCommand($columns[0] + 2, $textY, (string) ($row['no'] ?? ''), 7, 'F1');
        $commands[] = $this->pdfTextCommand($columns[1] + 2, $textY, (string) ($row['date'] ?? ''), 7, 'F1');
        $commands[] = $this->pdfTextCommand($columns[2] + 2, $textY, (string) ($row['type'] ?? ''), 7, 'F1');
        $commands[] = $this->pdfTextCommand($columns[3] + 2, $textY, (string) ($row['source'] ?? ''), 7, 'F1');
        $commands[] = $this->pdfTextCommand($columns[4] + 2, $textY, (string) ($row['category'] ?? ''), 7, 'F1');
        $commands[] = $this->pdfTextCommand($columns[5] + 2, $textY, (string) ($row['account'] ?? ''), 7, 'F1');
        $commands[] = $this->pdfTextCommand($columns[6] + 2, $textY, (string) ($row['note'] ?? ''), 7, 'F1');
        $commands[] = $this->pdfTextCommand($columns[7] + 2, $textY, (string) ($row['amount'] ?? ''), 7, 'F1');
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

    private function truncateText(string $text, int $maxLength): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if (strlen($clean) <= $maxLength) {
            return $clean;
        }

        return substr($clean, 0, $maxLength - 3) . '...';
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
        return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $text);
    }
}
