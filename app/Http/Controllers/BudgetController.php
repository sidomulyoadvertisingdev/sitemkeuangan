<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    public function index()
    {
        $month = request('month', now()->month);
        $year  = request('year', now()->year);

        $budgets = Budget::with('category')
            ->where('user_id', auth()->user()->tenantUserId())
            ->where('month', $month)
            ->where('year', $year)
            ->get()
            ->map(function ($budget) use ($month, $year) {

                // Hitung total pengeluaran per kategori
                $used = Transaction::where('user_id', auth()->user()->tenantUserId())
                    ->where('type', 'expense')
                    ->where('category_id', $budget->category_id)
                    ->whereMonth('date', $month)
                    ->whereYear('date', $year)
                    ->sum('amount');

                $budget->used = $used;
                $budget->remaining = max(0, $budget->limit - $used);
                $budget->percentage = $budget->limit > 0
                    ? round(($used / $budget->limit) * 100)
                    : 0;

                return $budget;
            });

        return view('budgets.index', compact('budgets', 'month', 'year'));
    }

    public function create()
    {
        // HANYA kategori pengeluaran yang boleh dibudget
        $categories = Category::where('user_id', auth()->user()->tenantUserId())
            ->where('type', 'expense')
            ->orderBy('name')
            ->get();

        return view('budgets.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'limit'       => 'required|numeric|min:1',
            'month'       => 'required|integer|min:1|max:12',
            'year'        => 'required|integer|min:2000',
        ]);

        // Pastikan kategori milik user & bertipe expense
        $category = Category::where('id', $request->category_id)
            ->where('user_id', auth()->user()->tenantUserId())
            ->where('type', 'expense')
            ->firstOrFail();

        // Cegah duplikasi budget kategori di bulan yang sama
        $exists = Budget::where('user_id', auth()->user()->tenantUserId())
            ->where('category_id', $category->id)
            ->where('month', $request->month)
            ->where('year', $request->year)
            ->exists();

        if ($exists) {
            return back()
                ->withInput()
                ->withErrors([
                    'category_id' => 'Budget untuk kategori ini sudah ada di periode tersebut'
                ]);
        }

        Budget::create([
            'user_id'     => auth()->user()->tenantUserId(),
            'category_id' => $category->id,
            'limit'       => $request->limit,
            'month'       => $request->month,
            'year'        => $request->year,
        ]);

        return redirect()
            ->route('budgets.index')
            ->with('success', 'Budget berhasil ditambahkan');
    }

    public function destroy(Budget $budget)
    {
        abort_if($budget->user_id !== auth()->user()->tenantUserId(), 403);

        $budget->delete();

        return redirect()
            ->route('budgets.index')
            ->with('success', 'Budget berhasil dihapus');
    }
}
