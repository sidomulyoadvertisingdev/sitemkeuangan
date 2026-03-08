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
        Route::get('/super-admin/projects', [MobileSuperAdminController::class, 'projects']);
        Route::post('/super-admin/projects', [MobileSuperAdminController::class, 'storeProject']);
        Route::get('/super-admin/reports', [MobileSuperAdminController::class, 'reports']);

        Route::get('/iuran/members', [MobileIuranController::class, 'members']);
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
