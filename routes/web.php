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
use App\Http\Controllers\KoperasiController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\AccountTransferController;
use App\Http\Controllers\ProfileController;

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
    if (auth()->check()) {
        return auth()->user()->isCooperativeMode()
            ? redirect()->route('koperasi.dashboard')
            : redirect()->route('dashboard');
    }

    return view('home.index');
});

/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES (ADMIN PANEL - ADMINLTE)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active_account'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware('app_mode:organization')->group(function () {
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
        Route::get('transfers', [AccountTransferController::class, 'index'])
            ->middleware('permission:transactions.manage')
            ->name('transfers.index');
        Route::post('transfers/direct', [AccountTransferController::class, 'storeDirect'])
            ->middleware('permission:transactions.manage')
            ->name('transfers.direct.store');
        Route::post('transfers/requests', [AccountTransferController::class, 'storePaymentRequest'])
            ->middleware('permission:transactions.manage')
            ->name('transfers.requests.store');
        Route::post('transfers/requests/{transfer}/pay', [AccountTransferController::class, 'payPaymentRequest'])
            ->middleware('permission:transactions.manage')
            ->name('transfers.requests.pay');
        Route::post('transfers/requests/{transfer}/reject', [AccountTransferController::class, 'rejectPaymentRequest'])
            ->middleware('permission:transactions.manage')
            ->name('transfers.requests.reject');
        Route::post('transfers/requests/{transfer}/cancel', [AccountTransferController::class, 'cancelPaymentRequest'])
            ->middleware('permission:transactions.manage')
            ->name('transfers.requests.cancel');
        Route::resource('bank-accounts', BankAccountController::class)
            ->middleware('permission:bank_accounts.manage');
        Route::post('bank-accounts/transfer-balance', [BankAccountController::class, 'transferBalance'])
            ->middleware('permission:bank_accounts.manage')
            ->name('bank-accounts.transfer-balance');
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
    });

    Route::middleware('app_mode:cooperative')->group(function () {
        Route::get('koperasi/transaksi/{menu}', [KoperasiController::class, 'transactions'])
            ->where('menu', 'simpan|pinjam|withdraw|angsuran|bagi-hasil')
            ->middleware('permission:koperasi.manage')
            ->name('koperasi.transactions');
        Route::post('koperasi/transaksi/simpan', [KoperasiController::class, 'storeSavingFromTransaction'])
            ->middleware('permission:koperasi.manage')
            ->name('koperasi.transactions.simpan.store');
        Route::post('koperasi/transaksi/pinjam', [KoperasiController::class, 'storeLoanFromTransaction'])
            ->middleware('permission:koperasi.manage')
            ->name('koperasi.transactions.pinjam.store');
        Route::post('koperasi/transaksi/withdraw', [KoperasiController::class, 'storeWithdrawFromTransaction'])
            ->middleware('permission:koperasi.manage')
            ->name('koperasi.transactions.withdraw.store');
        Route::post('koperasi/transaksi/angsuran', [KoperasiController::class, 'storeInstallmentFromTransaction'])
            ->middleware('permission:koperasi.manage')
            ->name('koperasi.transactions.angsuran.store');
        Route::get('koperasi/export/pdf', [KoperasiController::class, 'exportPdf'])
            ->middleware('permission:koperasi.manage')
            ->name('koperasi.export.pdf');
        Route::get('koperasi/export/excel', [KoperasiController::class, 'exportExcel'])
            ->middleware('permission:koperasi.manage')
            ->name('koperasi.export.excel');
        Route::get('koperasi/dashboard', [KoperasiController::class, 'dashboard'])
            ->middleware('permission:koperasi.manage')
            ->name('koperasi.dashboard');
        Route::resource('koperasi', KoperasiController::class)
            ->middleware('permission:koperasi.manage');
        Route::post('koperasi/{koperasi}/savings', [KoperasiController::class, 'storeSaving'])
            ->middleware('permission:koperasi.manage')
            ->name('koperasi.savings.store');
        Route::post('koperasi/{koperasi}/withdraws', [KoperasiController::class, 'storeWithdraw'])
            ->middleware('permission:koperasi.manage')
            ->name('koperasi.withdraws.store');
        Route::post('koperasi/{koperasi}/loans', [KoperasiController::class, 'storeLoan'])
            ->middleware('permission:koperasi.manage')
            ->name('koperasi.loans.store');
        Route::post('koperasi/loans/{loan}/installments', [KoperasiController::class, 'storeInstallment'])
            ->middleware('permission:koperasi.manage')
            ->name('koperasi.loans.installments.store');
    });

    /*
    |--------------------------------------------------------------------------
    | LOGOUT (ADMINLTE)
    |--------------------------------------------------------------------------
    */
    Route::post('/logout', function () {
        auth()->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/');
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
