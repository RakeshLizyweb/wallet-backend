<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BankAccountController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\LimitController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PinController;
use App\Http\Controllers\Api\V1\QrCodeController;
use App\Http\Controllers\Api\V1\RewardController;
use App\Http\Controllers\Api\V1\TransferController;
use App\Http\Controllers\Api\V1\VerificationController;
use App\Http\Controllers\Api\V1\VirtualCardController;
use App\Http\Controllers\Api\V1\WalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function () {
    Route::middleware('throttle:auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('login', [AuthController::class, 'login'])->name('login');
    });

    Route::middleware('throttle:otp')->group(function () {
        Route::post('resend-otp', [AuthController::class, 'resendOtp'])->name('resend-otp');
        Route::post('forgot-pin', [AuthController::class, 'forgotPin'])->name('forgot-pin');
    });

    Route::middleware('throttle:auth')->group(function () {
        Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->name('verify-otp');
        Route::post('reset-pin', [AuthController::class, 'resetPin'])->name('reset-pin');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::put('nationality', [AuthController::class, 'updateNationality'])->name('nationality');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('logout-all', [AuthController::class, 'logoutAllDevices'])->name('logout-all');
        Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
        Route::post('deactivate', [AuthController::class, 'deactivate'])->name('deactivate');
        Route::delete('account', [AuthController::class, 'deleteAccount'])->name('delete-account');
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::post('devices/fcm-token', [DeviceController::class, 'updateFcmToken'])->name('devices.fcm-token');
    Route::delete('devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');

    Route::prefix('pin')->name('pin.')->middleware('throttle:sensitive')->group(function () {
        Route::post('/', [PinController::class, 'store'])->name('store');
        Route::put('/', [PinController::class, 'update'])->name('update');
    });

    Route::prefix('wallet')->name('wallet.')->group(function () {
        Route::get('/', [WalletController::class, 'show'])->middleware('pin')->name('show');
        Route::get('statement/mini', [WalletController::class, 'miniStatement'])->name('statement.mini');
        Route::get('statement/full', [WalletController::class, 'fullStatement'])->name('statement.full');
    });

    Route::prefix('banks')->name('banks.')->group(function () {
        Route::get('/', [BankAccountController::class, 'index'])->name('index');
        Route::post('/', [BankAccountController::class, 'store'])->name('store');
        Route::put('{bankAccount}', [BankAccountController::class, 'update'])->name('update');
        Route::post('{bankAccount}/primary', [BankAccountController::class, 'setPrimary'])->name('primary');
        Route::delete('{bankAccount}', [BankAccountController::class, 'destroy'])->name('destroy');
    });

    Route::get('limits', [LimitController::class, 'show'])->name('limits.show');

    Route::prefix('transfers')->name('transfers.')->middleware('throttle:sensitive')->group(function () {
        Route::get('/', [TransferController::class, 'index'])->name('index');
        Route::post('wallet-to-wallet', [TransferController::class, 'walletToWallet'])->name('wallet-to-wallet');
        Route::post('wallet-to-bank', [TransferController::class, 'walletToBank'])->name('wallet-to-bank');
        Route::post('bank-to-wallet', [TransferController::class, 'bankToWallet'])->name('bank-to-wallet');
        Route::get('{reference}', [TransferController::class, 'show'])->name('show');
    });

    Route::prefix('qr')->name('qr.')->group(function () {
        Route::get('/', [QrCodeController::class, 'show'])->name('show');
        Route::get('download', [QrCodeController::class, 'download'])->name('download');
        Route::post('validate', [QrCodeController::class, 'validateQr'])->name('validate');
    });

    Route::prefix('rewards')->name('rewards.')->group(function () {
        Route::get('/', [RewardController::class, 'index'])->name('index');
        Route::get('summary', [RewardController::class, 'summary'])->name('summary');
        Route::post('{scratchCard}/scratch', [RewardController::class, 'scratch'])->name('scratch');
        Route::post('{scratchCard}/redeem', [RewardController::class, 'redeem'])->name('redeem');
    });

    Route::prefix('verification')->name('verification.')->group(function () {
        Route::get('/', [VerificationController::class, 'show'])->name('show');
        Route::get('history', [VerificationController::class, 'history'])->name('history');
        Route::post('/', [VerificationController::class, 'store'])->name('store');
    });

    Route::get('virtual-card', [VirtualCardController::class, 'show'])->name('virtual-card.show');

    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
        Route::post('read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
        Route::post('{notification}/read', [NotificationController::class, 'markAsRead'])->name('read');
    });
});

require __DIR__.'/api_v1_admin.php';
