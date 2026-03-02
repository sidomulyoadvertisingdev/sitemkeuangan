<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Category;
use App\Models\IuranInstallment;
use App\Models\IuranMember;
use App\Models\Project;
use App\Models\ProjectIuranAssignment;
use App\Models\User;
use App\Services\IuranTargetSynchronizer;
use App\Services\ProjectLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    public function __construct(
        private ProjectLedger $ledger,
        private IuranTargetSynchronizer $iuranTargetSynchronizer
    )
    {
    }

    public function index()
    {
        $tenantId = auth()->user()->tenantUserId();
        $iuranCollectedByProject = IuranInstallment::query()
            ->join('iuran_members', 'iuran_members.id', '=', 'iuran_installments.iuran_member_id')
            ->where('iuran_members.user_id', $tenantId)
            ->whereNotNull('iuran_installments.project_id')
            ->select('iuran_installments.project_id', DB::raw('SUM(iuran_installments.amount) as total_collected'))
            ->groupBy('iuran_installments.project_id')
            ->pluck('total_collected', 'iuran_installments.project_id');

        $projects = Project::with('bankAccount', 'transactions')
            ->where('user_id', $tenantId)
            ->latest()
            ->get()
            ->map(function ($project) use ($iuranCollectedByProject) {
                $allocated = $project->transactions->whereIn('type', ['allocation','transfer_in'])->sum('amount');
                $expenses  = $project->transactions->where('type', 'expense')->sum('amount');
                $refunds   = $project->transactions->where('type', 'refund')->sum('amount');
                $netSpent  = $expenses - $refunds;

                $iuranCollected = (float) ($iuranCollectedByProject[(int) $project->id] ?? 0);
                $incomingTarget = (float) $project->target_amount;
                $incomingGap = $incomingTarget - $iuranCollected;
                $incomingProgress = $incomingTarget > 0
                    ? min(100, round(($iuranCollected / $incomingTarget) * 100))
                    : 0;
                $spendingProgress = $project->target_amount > 0
                    ? min(100, round(($netSpent / $project->target_amount) * 100))
                    : 0;

                $project->incoming_target = $incomingTarget;
                $project->iuran_collected = $iuranCollected;
                $project->incoming_gap = $incomingGap;
                $project->incoming_progress = $incomingProgress;
                $project->spent = $netSpent;
                $project->progress = $spendingProgress;
                $project->cashflow_gap = $iuranCollected - $netSpent;
                $project->allocated = (float) $allocated;
                return $project;
            });

        return view('projects.index', compact('projects'));
    }

    public function create()
    {
        $accounts = BankAccount::where('user_id', auth()->user()->tenantUserId())->get();
        return view('projects.create', compact('accounts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'            => 'required|string|max:150',
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'description'     => 'nullable|string',
            'target_amount'   => 'required|numeric|min:1',
            'iuran_allocation_mode' => 'nullable|in:default,kelas',
            'iuran_class_a_percent' => 'nullable|numeric|min:1|max:1000',
            'iuran_class_b_percent' => 'nullable|numeric|min:1|max:1000',
            'iuran_class_c_percent' => 'nullable|numeric|min:1|max:1000',
            'start_date'      => 'nullable|date',
            'end_date'        => 'nullable|date|after_or_equal:start_date',
        ]);

        $project = Project::create([
            'user_id'        => auth()->user()->tenantUserId(),
            'bank_account_id'=> $request->bank_account_id,
            'name'           => $request->name,
            'description'    => $request->description,
            'target_amount'  => (float) $request->target_amount,
            'iuran_allocation_mode' => $request->input('iuran_allocation_mode', Project::IURAN_MODE_DEFAULT),
            'iuran_class_a_percent' => (float) $request->input('iuran_class_a_percent', 130),
            'iuran_class_b_percent' => (float) $request->input('iuran_class_b_percent', 110),
            'iuran_class_c_percent' => (float) $request->input('iuran_class_c_percent', 100),
            'start_date'     => $request->start_date,
            'end_date'       => $request->end_date,
            'status'         => 'ongoing',
        ]);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Proyek berhasil dibuat');
    }

    public function show(Project $project)
    {
        abort_if($project->user_id !== auth()->user()->tenantUserId(), 403);

        $project->load(['bankAccount', 'transactions.category']);
        $tenantId = auth()->user()->tenantUserId();

        $accounts = BankAccount::where('user_id', $tenantId)->get();
        $categories = Category::where('user_id', $tenantId)
            ->where('type', 'expense')
            ->get();

        $iuranMembers = IuranMember::where('user_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'target_amount', 'status']);

        $iuranOfficers = User::query()
            ->where('data_owner_user_id', $tenantId)
            ->where('account_mode', User::MODE_ORGANIZATION)
            ->where('account_status', User::STATUS_APPROVED)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_admin', 'is_platform_admin', 'permissions', 'account_status'])
            ->filter(function (User $user) {
                return $user->is_admin || $user->hasPermission('iuran.manage');
            })
            ->values();

        $iuranAssignments = $project->iuranAssignments()
            ->with([
                'member:id,name,target_amount,status',
                'officer:id,name,email',
                'assignedBy:id,name',
            ])
            ->latest()
            ->get();
        $plannedTotal = (float) $iuranAssignments->sum('planned_amount');
        $plannedGap = (float) $project->target_amount - $plannedTotal;

        $expenses  = $project->transactions()->where('type', 'expense')->sum('amount');
        $refunds   = $project->transactions()->where('type', 'refund')->sum('amount');
        $netSpent  = $expenses - $refunds;
        $allocated = $project->transactions()->whereIn('type', ['allocation','transfer_in'])->sum('amount');
        $balance   = $allocated - $netSpent;
        $progress  = $project->target_amount > 0
            ? min(100, round(($netSpent / $project->target_amount) * 100))
            : 0;

        $iuranCollected = (float) IuranInstallment::query()
            ->join('iuran_members', 'iuran_members.id', '=', 'iuran_installments.iuran_member_id')
            ->where('iuran_members.user_id', $tenantId)
            ->where('iuran_installments.project_id', $project->id)
            ->sum('iuran_installments.amount');
        $iuranInstallmentCount = (int) IuranInstallment::query()
            ->join('iuran_members', 'iuran_members.id', '=', 'iuran_installments.iuran_member_id')
            ->where('iuran_members.user_id', $tenantId)
            ->where('iuran_installments.project_id', $project->id)
            ->count('iuran_installments.id');
        $iuranMemberCount = (int) IuranInstallment::query()
            ->join('iuran_members', 'iuran_members.id', '=', 'iuran_installments.iuran_member_id')
            ->where('iuran_members.user_id', $tenantId)
            ->where('iuran_installments.project_id', $project->id)
            ->distinct('iuran_installments.iuran_member_id')
            ->count('iuran_installments.iuran_member_id');

        $incomingTarget = (float) $project->target_amount;
        $incomingGap = $incomingTarget - $iuranCollected;
        $incomingProgress = $incomingTarget > 0
            ? min(100, round(($iuranCollected / $incomingTarget) * 100))
            : 0;
        $cashflowGap = $iuranCollected - (float) $netSpent;

        return view('projects.show', compact(
            'project',
            'accounts',
            'categories',
            'netSpent',
            'allocated',
            'balance',
            'progress',
            'iuranMembers',
            'iuranOfficers',
            'iuranAssignments',
            'plannedTotal',
            'plannedGap',
            'incomingTarget',
            'iuranCollected',
            'incomingGap',
            'incomingProgress',
            'iuranInstallmentCount',
            'iuranMemberCount',
            'cashflowGap'
        ));
    }

    public function updateIuranPlan(Request $request, Project $project)
    {
        abort_if($project->user_id !== auth()->user()->tenantUserId(), 403);

        $validated = $request->validate([
            'target_amount' => 'required|numeric|min:1',
            'iuran_allocation_mode' => 'required|in:default,kelas',
            'iuran_class_a_percent' => 'required|numeric|min:1|max:1000',
            'iuran_class_b_percent' => 'required|numeric|min:1|max:1000',
            'iuran_class_c_percent' => 'required|numeric|min:1|max:1000',
        ]);

        DB::transaction(function () use ($project, $validated) {
            $project->update([
                'target_amount' => (float) $validated['target_amount'],
                'iuran_allocation_mode' => $validated['iuran_allocation_mode'],
                'iuran_class_a_percent' => (float) $validated['iuran_class_a_percent'],
                'iuran_class_b_percent' => (float) $validated['iuran_class_b_percent'],
                'iuran_class_c_percent' => (float) $validated['iuran_class_c_percent'],
            ]);

            $freshProject = $project->fresh();
            $this->recalculateIuranAllocations($freshProject);
            $this->syncIuranMembersFromProjectAllocations($freshProject, $this->projectAssignmentMemberIds($freshProject));
        });

        return back()->with('success', 'Skema iuran proyek berhasil diperbarui dan jatah anggota dihitung ulang.');
    }

    public function storeIuranAssignment(Request $request, Project $project)
    {
        abort_if($project->user_id !== auth()->user()->tenantUserId(), 403);

        $request->validate([
            'iuran_member_id' => 'nullable|integer|exists:iuran_members,id',
            'iuran_member_ids' => 'nullable|array|min:1',
            'iuran_member_ids.*' => 'integer|exists:iuran_members,id',
            'officer_user_id' => 'required|integer|exists:users,id',
            'member_class' => 'nullable|in:A,B,C',
            'note' => 'nullable|string|max:255',
        ]);

        $tenantId = auth()->user()->tenantUserId();

        $requestedMemberIds = collect((array) $request->input('iuran_member_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($requestedMemberIds->isEmpty() && $request->filled('iuran_member_id')) {
            $requestedMemberIds = collect([(int) $request->input('iuran_member_id')]);
        }

        if ($requestedMemberIds->isEmpty()) {
            return back()->withErrors([
                'iuran_member_ids' => 'Pilih minimal satu anggota iuran untuk ditugaskan.',
            ])->withInput();
        }

        $members = IuranMember::query()
            ->where('user_id', $tenantId)
            ->whereIn('id', $requestedMemberIds->all())
            ->get(['id']);

        if ($members->count() !== $requestedMemberIds->count()) {
            return back()->withErrors([
                'iuran_member_ids' => 'Sebagian anggota tidak valid untuk organisasi ini.',
            ])->withInput();
        }

        $officer = User::query()
            ->where('id', (int) $request->officer_user_id)
            ->where('data_owner_user_id', $tenantId)
            ->where('account_mode', User::MODE_ORGANIZATION)
            ->where('account_status', User::STATUS_APPROVED)
            ->first();
        if (!$officer) {
            return back()->withErrors([
                'officer_user_id' => 'Petugas tidak valid untuk organisasi ini.',
            ])->withInput();
        }

        if (!$officer->is_admin && !$officer->hasPermission('iuran.manage')) {
            return back()->withErrors([
                'officer_user_id' => 'User terpilih belum memiliki hak akses petugas iuran.',
            ])->withInput();
        }

        DB::transaction(function () use ($project, $members, $officer, $request) {
            $memberClass = $this->resolveMemberClass($request->input('member_class'));
            $note = $request->filled('note') ? trim((string) $request->note) : null;

            foreach ($members as $member) {
                ProjectIuranAssignment::updateOrCreate(
                    [
                        'project_id' => $project->id,
                        'iuran_member_id' => $member->id,
                    ],
                    [
                        'officer_user_id' => $officer->id,
                        'allocation_mode' => $project->iuran_allocation_mode ?: Project::IURAN_MODE_DEFAULT,
                        'member_class' => $memberClass,
                        'assigned_by' => auth()->id(),
                        'note' => $note,
                    ]
                );
            }

            $freshProject = $project->fresh();
            $this->recalculateIuranAllocations($freshProject);

            $affectedMemberIds = collect($this->projectAssignmentMemberIds($freshProject))
                ->merge($members->pluck('id')->all())
                ->unique()
                ->values()
                ->all();

            $this->syncIuranMembersFromProjectAllocations($freshProject, $affectedMemberIds);
        });

        return back()->with('success', 'Penugasan berhasil untuk ' . $members->count() . ' anggota dan jatah iuran diperbarui.');
    }

    public function destroyIuranAssignment(Project $project, ProjectIuranAssignment $assignment)
    {
        abort_if($project->user_id !== auth()->user()->tenantUserId(), 403);
        abort_if((int) $assignment->project_id !== (int) $project->id, 404);

        $removedMemberId = (int) $assignment->iuran_member_id;

        DB::transaction(function () use ($assignment, $project, $removedMemberId) {
            $assignment->delete();
            $freshProject = $project->fresh();
            $this->recalculateIuranAllocations($freshProject);

            $affectedMemberIds = collect($this->projectAssignmentMemberIds($freshProject))
                ->push($removedMemberId)
                ->unique()
                ->values()
                ->all();

            $this->syncIuranMembersFromProjectAllocations($freshProject, $affectedMemberIds);
        });

        return back()->with('success', 'Penugasan petugas iuran berhasil dihapus dan jatah iuran diperbarui.');
    }

    public function storeAllocation(Request $request, Project $project)
    {
        abort_if($project->user_id !== auth()->user()->tenantUserId(), 403);

        $request->validate([
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'amount'          => 'required|numeric|min:1',
            'date'            => 'required|date',
            'note'            => 'nullable|string',
            'type'            => 'required|in:allocation,transfer_in',
        ]);

        $this->ledger->record([
            'project_id'      => $project->id,
            'bank_account_id' => $request->bank_account_id,
            'type'            => $request->type,
            'amount'          => $request->amount,
            'date'            => $request->date,
            'note'            => $request->note,
        ]);

        return back()->with('success', 'Alokasi dana berhasil dicatat');
    }

    public function storeExpense(Request $request, Project $project)
    {
        abort_if($project->user_id !== auth()->user()->tenantUserId(), 403);

        $request->validate([
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'category_id'     => 'nullable|exists:categories,id',
            'amount'          => 'required|numeric|min:1',
            'date'            => 'required|date',
            'note'            => 'nullable|string',
        ]);

        $allocated = $project->transactions()->whereIn('type', ['allocation','transfer_in'])->sum('amount');
        $spent     = $project->transactions()->where('type', 'expense')->sum('amount');
        $balance   = $allocated - $spent;

        if ($request->amount > $balance) {
            return back()->withInput()->withErrors([
                'amount' => 'Saldo proyek tidak cukup untuk pengeluaran ini'
            ]);
        }

        $this->ledger->record([
            'project_id'      => $project->id,
            'bank_account_id' => $request->bank_account_id,
            'category_id'     => $request->category_id,
            'type'            => 'expense',
            'amount'          => $request->amount,
            'date'            => $request->date,
            'note'            => $request->note,
        ]);

        return back()->with('success', 'Pengeluaran proyek berhasil dicatat');
    }

    public function destroy(Project $project)
    {
        abort_if($project->user_id !== auth()->user()->tenantUserId(), 403);

        // Hitung dampak saldo sebelum data dihapus (cascade)
        $txnGroups = \App\Models\Transaction::where('project_id', $project->id)
            ->get()
            ->groupBy('bank_account_id');

        DB::transaction(function () use ($project, $txnGroups) {
            foreach ($txnGroups as $bankId => $txns) {
                $bank = BankAccount::find($bankId);
                if (!$bank) continue;
                $income = $txns->where('type', 'income')->sum('amount');
                $expense= $txns->where('type', 'expense')->sum('amount');
                $delta  = $expense - $income; // remove effect: add back expenses, subtract incomes
                $bank->balance += $delta;
                $bank->save();
            }

            $project->delete(); // cascade removes project_transactions & transactions
        });

        return redirect()->route('projects.index')->with('success', 'Proyek berhasil dihapus');
    }

    private function resolveMemberClass(?string $value): string
    {
        $normalized = strtoupper(trim((string) $value));
        return in_array($normalized, ['A', 'B', 'C'], true) ? $normalized : 'C';
    }

    private function projectAssignmentMemberIds(Project $project): array
    {
        return $project->iuranAssignments()
            ->pluck('iuran_member_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function syncIuranMembersFromProjectAllocations(Project $project, array $memberIds): void
    {
        $this->iuranTargetSynchronizer->syncForTenant(
            (int) $project->user_id,
            $memberIds,
            true
        );
    }

    private function recalculateIuranAllocations(Project $project): void
    {
        $assignments = $project->iuranAssignments()
            ->orderBy('id')
            ->get();

        if ($assignments->isEmpty()) {
            return;
        }

        $targetCents = max(0, (int) round(((float) $project->target_amount) * 100));
        $mode = $project->iuran_allocation_mode === Project::IURAN_MODE_KELAS
            ? Project::IURAN_MODE_KELAS
            : Project::IURAN_MODE_DEFAULT;

        $weightsByAssignmentId = [];
        foreach ($assignments as $assignment) {
            $weightsByAssignmentId[$assignment->id] = $mode === Project::IURAN_MODE_KELAS
                ? $project->classPercent((string) $assignment->member_class)
                : 100.0;
        }

        $totalWeight = (float) array_sum($weightsByAssignmentId);
        if ($totalWeight <= 0) {
            $equalWeight = 100.0;
            foreach ($assignments as $assignment) {
                $weightsByAssignmentId[$assignment->id] = $equalWeight;
            }
            $totalWeight = $equalWeight * $assignments->count();
        }

        $allocatedCents = 0;
        $plannedCents = [];
        $remainders = [];

        foreach ($assignments as $assignment) {
            $weight = (float) $weightsByAssignmentId[$assignment->id];
            $raw = $targetCents > 0
                ? ($targetCents * $weight / $totalWeight)
                : 0.0;

            $floor = (int) floor($raw);
            $plannedCents[$assignment->id] = $floor;
            $allocatedCents += $floor;
            $remainders[] = [
                'assignment_id' => (int) $assignment->id,
                'remainder' => $raw - $floor,
            ];
        }

        $remainingCents = $targetCents - $allocatedCents;
        if ($remainingCents > 0) {
            usort($remainders, function (array $a, array $b) {
                if ($a['remainder'] === $b['remainder']) {
                    return $a['assignment_id'] <=> $b['assignment_id'];
                }

                return $a['remainder'] < $b['remainder'] ? 1 : -1;
            });

            $index = 0;
            while ($remainingCents > 0 && $index < count($remainders)) {
                $assignmentId = $remainders[$index]['assignment_id'];
                $plannedCents[$assignmentId]++;
                $remainingCents--;
                $index++;
                if ($index >= count($remainders)) {
                    $index = 0;
                }
            }
        }

        foreach ($assignments as $assignment) {
            $weight = (float) $weightsByAssignmentId[$assignment->id];
            $amountCents = (int) ($plannedCents[$assignment->id] ?? 0);

            $assignment->update([
                'allocation_mode' => $mode,
                'member_class' => $this->resolveMemberClass((string) $assignment->member_class),
                'class_percent' => $weight,
                'planned_amount' => $amountCents / 100,
            ]);
        }
    }
}
