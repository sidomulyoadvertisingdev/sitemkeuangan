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
use App\Http\Controllers\IuranController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserManagementController;

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
Route::middleware(['auth', 'active_account'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD
    |--------------------------------------------------------------------------
    */
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::get('/reports', [ReportController::class, 'index'])
        ->middleware('permission:reports.view')
        ->name('reports.index');
    Route::get('/reports/export/pdf', [ReportController::class, 'exportPdf'])
        ->middleware('permission:reports.view')
        ->name('reports.export.pdf');

    /*
    |--------------------------------------------------------------------------
    | TRANSAKSI
    |--------------------------------------------------------------------------
    */
    Route::resource('transactions', TransactionController::class)
        ->middleware('permission:transactions.manage');
    Route::resource('bank-accounts', BankAccountController::class)
        ->middleware('permission:bank_accounts.manage');
    Route::resource('projects', ProjectController::class)
        ->middleware('permission:projects.manage');
    Route::post('projects/{project}/allocate', [ProjectController::class, 'storeAllocation'])
        ->middleware('permission:projects.manage')
        ->name('projects.allocate');
    Route::post('projects/{project}/expenses', [ProjectController::class, 'storeExpense'])
        ->middleware('permission:projects.manage')
        ->name('projects.expenses.store');

    /*
    |----------------------------------------------------------------------
    | INVESTASI
    |----------------------------------------------------------------------
    */
    Route::resource('investments', InvestmentController::class)
        ->only(['index', 'create', 'store'])
        ->middleware('permission:investments.manage');

    /*
    |--------------------------------------------------------------------------
    | KATEGORI (AJAX)
    |--------------------------------------------------------------------------
    */
    Route::post('/categories', [CategoryController::class, 'store'])
        ->middleware('permission:transactions.manage')
        ->name('categories.store');

    Route::get('/categories/by-type/{type}', function ($type) {
        return \App\Models\Category::where('user_id', auth()->user()->tenantUserId())
            ->where('type', $type)
            ->orderBy('name')
            ->get();
    })->name('categories.byType');

    /*
    |--------------------------------------------------------------------------
    | BUDGET
    |--------------------------------------------------------------------------
    */
    Route::resource('budgets', BudgetController::class)
        ->middleware('permission:budgets.manage');

    /*
    |--------------------------------------------------------------------------
    | HUTANG & PIUTANG
    |--------------------------------------------------------------------------
    */
    Route::resource('debts', DebtController::class)
        ->middleware('permission:debts.manage');
    Route::post('debts/{debt}/installments', [DebtController::class, 'storeInstallment'])
        ->middleware('permission:debts.manage')
        ->name('debts.installments.store');
    Route::post('iuran/import', [IuranController::class, 'import'])
        ->middleware(['permission:iuran.manage', 'permission:iuran.import'])
        ->name('iuran.import');
    Route::get('iuran/import/template', [IuranController::class, 'downloadTemplate'])
        ->middleware(['permission:iuran.manage', 'permission:iuran.import'])
        ->name('iuran.import.template');
    Route::post('iuran/installments/import', [IuranController::class, 'importInstallments'])
        ->middleware(['permission:iuran.manage', 'permission:iuran.import'])
        ->name('iuran.installments.import');
    Route::get('iuran/installments/import/template', [IuranController::class, 'downloadInstallmentTemplate'])
        ->middleware(['permission:iuran.manage', 'permission:iuran.import'])
        ->name('iuran.installments.import.template');
    Route::get('iuran/export/pdf', [IuranController::class, 'exportPdf'])
        ->middleware(['permission:iuran.manage', 'permission:iuran.import'])
        ->name('iuran.export.pdf');
    Route::resource('iuran', IuranController::class)
        ->middleware('permission:iuran.manage');
    Route::post('iuran/{iuran}/installments', [IuranController::class, 'storeInstallment'])
        ->middleware('permission:iuran.manage')
        ->name('iuran.installments.store');
    Route::resource('users', UserManagementController::class)
        ->except(['show'])
        ->middleware('permission:users.manage');
    Route::post('users/{user}/approve', [UserManagementController::class, 'approve'])
        ->middleware('permission:users.manage')
        ->name('users.approve');
    Route::post('users/{user}/ban', [UserManagementController::class, 'ban'])
        ->middleware('permission:users.manage')
        ->name('users.ban');
    Route::post('users/{user}/unban', [UserManagementController::class, 'unban'])
        ->middleware('permission:users.manage')
        ->name('users.unban');

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
| ROUTE INI HARUS TETAP ADA
| UI Breeze boleh tidak dipakai,
| tapi route login/register tetap dibutuhkan Laravel
*/
require __DIR__ . '/auth.php';
