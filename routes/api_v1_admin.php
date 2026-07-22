<?php

use App\Http\Controllers\Api\V1\Admin\AdminAuthController;
use App\Http\Controllers\Api\V1\Admin\BankAccountController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\LogController;
use App\Http\Controllers\Api\V1\Admin\NotificationController;
use App\Http\Controllers\Api\V1\Admin\ReportController;
use App\Http\Controllers\Api\V1\Admin\RewardController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\SettingsController;
use App\Http\Controllers\Api\V1\Admin\TransactionController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Admin\VerificationController;
use App\Http\Controllers\Api\V1\Admin\WalletController;
use Illuminate\Support\Facades\Route;

Route::post('admin/auth/login', [AdminAuthController::class, 'login'])
    ->middleware('throttle:auth')
    ->name('admin.auth.login');

Route::post('admin/auth/forgot-password', [AdminAuthController::class, 'forgotPassword'])
    ->middleware('throttle:otp')
    ->name('admin.auth.forgot-password');

Route::post('admin/auth/reset-password', [AdminAuthController::class, 'resetPassword'])
    ->middleware('throttle:auth')
    ->name('admin.auth.reset-password');

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('{id}', [UserController::class, 'show'])->name('show');
        Route::put('{id}/status', [UserController::class, 'updateStatus'])->name('status');
        Route::put('{id}/tier', [UserController::class, 'updateTier'])->name('tier');
        Route::put('{id}/role', [UserController::class, 'assignRole'])->name('role');
        Route::put('{id}/credentials', [UserController::class, 'setCredentials'])->name('credentials');
    });

    Route::prefix('wallets')->name('wallets.')->group(function () {
        Route::get('/', [WalletController::class, 'index'])->name('index');
        Route::post('{wallet}/freeze', [WalletController::class, 'freeze'])->name('freeze');
        Route::post('{wallet}/unfreeze', [WalletController::class, 'unfreeze'])->name('unfreeze');
        Route::post('{wallet}/adjust', [WalletController::class, 'adjust'])->name('adjust');
    });

    Route::prefix('transactions')->name('transactions.')->group(function () {
        Route::get('/', [TransactionController::class, 'index'])->name('index');
        Route::get('{reference}', [TransactionController::class, 'show'])->name('show');
        Route::post('{reference}/reverse', [TransactionController::class, 'reverse'])->name('reverse');
    });

    Route::get('rewards', [RewardController::class, 'index'])->name('rewards.index');

    Route::prefix('verifications')->name('verifications.')->group(function () {
        Route::get('/', [VerificationController::class, 'index'])->name('index');
        Route::post('{verification}/approve', [VerificationController::class, 'approve'])->name('approve');
        Route::post('{verification}/reject', [VerificationController::class, 'reject'])->name('reject');
        Route::get('{verification}/passport-image', [VerificationController::class, 'passportImage'])->name('passport-image');
        Route::get('{verification}/selfie-image', [VerificationController::class, 'selfieImage'])->name('selfie-image');
    });

    Route::prefix('banks')->name('banks.')->group(function () {
        Route::get('/', [BankAccountController::class, 'index'])->name('index');
        Route::post('{bankAccount}/verify', [BankAccountController::class, 'verify'])->name('verify');
    });

    Route::get('reports/transactions-summary', [ReportController::class, 'transactionsSummary'])->name('reports.transactions-summary');

    Route::post('notifications/broadcast', [NotificationController::class, 'broadcast'])->name('notifications.broadcast');

    Route::get('roles', [RoleController::class, 'roles'])->name('roles.index');
    Route::get('permissions', [RoleController::class, 'permissions'])->name('permissions.index');

    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::get('limits', [SettingsController::class, 'limits'])->name('limits.index');

    Route::get('logs', [LogController::class, 'index'])->name('logs.index');
});
