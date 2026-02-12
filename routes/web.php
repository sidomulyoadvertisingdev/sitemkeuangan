<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\InvestmentController;

/*
|--------------------------------------------------------------------------
| WEB ROUTES
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| ROOT
|--------------------------------------------------------------------------
| Arahkan user ke dashboard jika sudah login,
| atau ke login (Breeze) jika belum
*/
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES (ADMIN PANEL - ADMINLTE)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD
    |--------------------------------------------------------------------------
    */
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | TRANSAKSI
    |--------------------------------------------------------------------------
    */
    Route::resource('transactions', TransactionController::class);
    Route::resource('bank-accounts', BankAccountController::class);
    Route::resource('projects', ProjectController::class);
    Route::post('projects/{project}/allocate', [ProjectController::class, 'storeAllocation'])
        ->name('projects.allocate');
    Route::post('projects/{project}/expenses', [ProjectController::class, 'storeExpense'])
        ->name('projects.expenses.store');

    /*
    |----------------------------------------------------------------------
    | INVESTASI
    |----------------------------------------------------------------------
    */
    Route::resource('investments', InvestmentController::class)->only(['index','create','store']);

    /*
    |--------------------------------------------------------------------------
    | KATEGORI (AJAX)
    |--------------------------------------------------------------------------
    */
    Route::post('/categories', [CategoryController::class, 'store'])
        ->name('categories.store');

    Route::get('/categories/by-type/{type}', function ($type) {
        return \App\Models\Category::where('user_id', auth()->id())
            ->where('type', $type)
            ->orderBy('name')
            ->get();
    })->name('categories.byType');

    /*
    |--------------------------------------------------------------------------
    | BUDGET
    |--------------------------------------------------------------------------
    */
    Route::resource('budgets', BudgetController::class);

    /*
    |--------------------------------------------------------------------------
    | HUTANG & PIUTANG
    |--------------------------------------------------------------------------
    */
    Route::resource('debts', DebtController::class);
    Route::post('debts/{debt}/installments', [DebtController::class, 'storeInstallment'])
        ->name('debts.installments.store');

    /*
    |--------------------------------------------------------------------------
    | LOGOUT (ADMINLTE)
    |--------------------------------------------------------------------------
    */
    Route::post('/logout', function () {
        auth()->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/login');
    })->name('logout');
});

/*
|--------------------------------------------------------------------------
| AUTH ROUTES (BREEZE / TAILWIND)
|--------------------------------------------------------------------------
| ⚠️ ROUTE INI HARUS TETAP ADA
| UI Breeze boleh tidak dipakai,
| tapi route login/register tetap dibutuhkan Laravel
*/
require __DIR__ . '/auth.php';
