<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Project;
use App\Services\ProjectLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    public function __construct(private ProjectLedger $ledger)
    {
    }

    public function index()
    {
        $projects = Project::with('bankAccount', 'transactions')
            ->where('user_id', auth()->id())
            ->latest()
            ->get()
            ->map(function ($project) {
                $allocated = $project->transactions->whereIn('type', ['allocation','transfer_in'])->sum('amount');
                $expenses  = $project->transactions->where('type', 'expense')->sum('amount');
                $refunds   = $project->transactions->where('type', 'refund')->sum('amount');
                $netSpent  = $expenses - $refunds;
                $project->progress = $project->target_amount > 0
                    ? min(100, round(($netSpent / $project->target_amount) * 100))
                    : 0;
                $project->spent = $netSpent;
                return $project;
            });

        return view('projects.index', compact('projects'));
    }

    public function create()
    {
        $accounts = BankAccount::where('user_id', auth()->id())->get();
        return view('projects.create', compact('accounts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'            => 'required|string|max:150',
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'description'     => 'nullable|string',
            'target_amount'   => 'nullable|numeric|min:0',
            'start_date'      => 'nullable|date',
            'end_date'        => 'nullable|date|after_or_equal:start_date',
        ]);

        $project = Project::create([
            'user_id'        => auth()->id(),
            'bank_account_id'=> $request->bank_account_id,
            'name'           => $request->name,
            'description'    => $request->description,
            'target_amount'  => $request->target_amount ?? 0,
            'start_date'     => $request->start_date,
            'end_date'       => $request->end_date,
            'status'         => 'ongoing',
        ]);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Proyek berhasil dibuat');
    }

    public function show(Project $project)
    {
        abort_if($project->user_id !== auth()->id(), 403);

        $project->load(['bankAccount', 'transactions.category']);

        $accounts  = BankAccount::where('user_id', auth()->id())->get();
        $categories= Category::where('user_id', auth()->id())
            ->where('type', 'expense')
            ->get();

        $expenses  = $project->transactions()->where('type', 'expense')->sum('amount');
        $refunds   = $project->transactions()->where('type', 'refund')->sum('amount');
        $netSpent  = $expenses - $refunds;
        $allocated = $project->transactions()->whereIn('type', ['allocation','transfer_in'])->sum('amount');
        $balance   = $allocated - $netSpent;
        $progress  = $project->target_amount > 0
            ? min(100, round(($netSpent / $project->target_amount) * 100))
            : 0;

        return view('projects.show', compact(
            'project',
            'accounts',
            'categories',
            'netSpent',
            'allocated',
            'balance',
            'progress'
        ));
    }

    public function storeAllocation(Request $request, Project $project)
    {
        abort_if($project->user_id !== auth()->id(), 403);

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
        abort_if($project->user_id !== auth()->id(), 403);

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
        abort_if($project->user_id !== auth()->id(), 403);

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
}
