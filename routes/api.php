<?php

use App\Http\Controllers\Api\Mobile\MobileAuthController;
use App\Http\Controllers\Api\Mobile\MobileIuranController;
use App\Http\Controllers\Api\Mobile\MobileKoperasiController;
use App\Http\Controllers\Api\Mobile\MobileMemberController;
use App\Http\Controllers\Api\Mobile\MobileSuperAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile')->group(function () {
    Route::post('/login', [MobileAuthController::class, 'login']);

    Route::middleware('mobile_token')->group(function () {
        Route::get('/me', [MobileAuthController::class, 'me']);
        Route::post('/logout', [MobileAuthController::class, 'logout']);

        Route::get('/member/dashboard', [MobileMemberController::class, 'dashboard']);
        Route::get('/member/transactions', [MobileMemberController::class, 'transactions']);
        Route::get('/member/transactions/{transactionId}', [MobileMemberController::class, 'showTransaction']);

        Route::get('/super-admin/members', [MobileSuperAdminController::class, 'members']);
        Route::post('/super-admin/members', [MobileSuperAdminController::class, 'storeMember']);
        Route::put('/super-admin/members/{member}', [MobileSuperAdminController::class, 'updateMember']);
        Route::patch('/super-admin/members/{member}/status', [MobileSuperAdminController::class, 'toggleMemberStatus']);
        Route::get('/super-admin/dashboard', [MobileSuperAdminController::class, 'dashboard']);
        Route::get('/super-admin/options', [MobileSuperAdminController::class, 'options']);
        Route::get('/super-admin/projects', [MobileSuperAdminController::class, 'projects']);
        Route::post('/super-admin/projects', [MobileSuperAdminController::class, 'storeProject']);
        Route::put('/super-admin/projects/{project}', [MobileSuperAdminController::class, 'updateProject']);
        Route::delete('/super-admin/projects/{project}', [MobileSuperAdminController::class, 'destroyProject']);
        Route::get('/super-admin/transactions', [MobileSuperAdminController::class, 'transactions']);
        Route::post('/super-admin/transactions', [MobileSuperAdminController::class, 'storeTransaction']);
        Route::put('/super-admin/transactions/{transaction}', [MobileSuperAdminController::class, 'updateTransaction']);
        Route::delete('/super-admin/transactions/{transaction}', [MobileSuperAdminController::class, 'destroyTransaction']);
        Route::get('/super-admin/wallets', [MobileSuperAdminController::class, 'wallets']);
        Route::post('/super-admin/wallets', [MobileSuperAdminController::class, 'storeWallet']);
        Route::put('/super-admin/wallets/{wallet}', [MobileSuperAdminController::class, 'updateWallet']);
        Route::delete('/super-admin/wallets/{wallet}', [MobileSuperAdminController::class, 'destroyWallet']);
        Route::get('/super-admin/transfers', [MobileSuperAdminController::class, 'transfers']);
        Route::post('/super-admin/transfers', [MobileSuperAdminController::class, 'storeTransfer']);
        Route::get('/super-admin/budgets', [MobileSuperAdminController::class, 'budgets']);
        Route::post('/super-admin/budgets', [MobileSuperAdminController::class, 'storeBudget']);
        Route::put('/super-admin/budgets/{budget}', [MobileSuperAdminController::class, 'updateBudget']);
        Route::delete('/super-admin/budgets/{budget}', [MobileSuperAdminController::class, 'destroyBudget']);
        Route::get('/super-admin/debts', [MobileSuperAdminController::class, 'debts']);
        Route::post('/super-admin/debts', [MobileSuperAdminController::class, 'storeDebt']);
        Route::put('/super-admin/debts/{debt}', [MobileSuperAdminController::class, 'updateDebt']);
        Route::delete('/super-admin/debts/{debt}', [MobileSuperAdminController::class, 'destroyDebt']);
        Route::post('/super-admin/debts/{debt}/installments', [MobileSuperAdminController::class, 'storeDebtInstallment']);
        Route::get('/super-admin/reports', [MobileSuperAdminController::class, 'reports']);

        Route::get('/iuran/members', [MobileIuranController::class, 'members']);
        Route::post('/iuran/members', [MobileIuranController::class, 'storeMember']);
        Route::get('/iuran/options', [MobileIuranController::class, 'options']);
        Route::get('/iuran/history', [MobileIuranController::class, 'history']);
        Route::get('/iuran/wallet', [MobileIuranController::class, 'wallet']);
        Route::post('/iuran/wallet/transfers', [MobileIuranController::class, 'transferToAdmin']);
        Route::post('/iuran/members/{member}/installments', [MobileIuranController::class, 'storeInstallment']);

        Route::get('/koperasi/dashboard', [MobileKoperasiController::class, 'dashboard']);
        Route::get('/koperasi/members', [MobileKoperasiController::class, 'members']);
        Route::get('/koperasi/transactions', [MobileKoperasiController::class, 'history']);
        Route::post('/koperasi/members/{member}/transactions', [MobileKoperasiController::class, 'storeTransaction']);
    });
});
