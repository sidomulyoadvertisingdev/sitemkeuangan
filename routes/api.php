<?php

use App\Http\Controllers\Api\Mobile\MobileAuthController;
use App\Http\Controllers\Api\Mobile\MobileIuranController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile')->group(function () {
    Route::post('/login', [MobileAuthController::class, 'login']);

    Route::middleware('mobile_token')->group(function () {
        Route::get('/me', [MobileAuthController::class, 'me']);
        Route::post('/logout', [MobileAuthController::class, 'logout']);

        Route::get('/iuran/members', [MobileIuranController::class, 'members']);
        Route::get('/iuran/options', [MobileIuranController::class, 'options']);
        Route::get('/iuran/history', [MobileIuranController::class, 'history']);
        Route::get('/iuran/wallet', [MobileIuranController::class, 'wallet']);
        Route::post('/iuran/wallet/transfers', [MobileIuranController::class, 'transferToAdmin']);
        Route::post('/iuran/members/{member}/installments', [MobileIuranController::class, 'storeInstallment']);
    });
});
