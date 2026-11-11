<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Agent\AgentController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CheckoutController;
use App\Http\Controllers\Web\TwoFactorController;
use App\Http\Controllers\Web\WalletController;
use Illuminate\Support\Facades\Route;

// Public web checkout
Route::get('/', [CheckoutController::class, 'index'])->name('home');
Route::get('/pay', [CheckoutController::class, 'pay'])->name('pay');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout.store');
Route::get('/checkout/callback', [CheckoutController::class, 'callback'])->name('checkout.callback');
Route::get('/checkout/{reference}', [CheckoutController::class, 'show'])->name('checkout.show');
Route::get('/checkout/{reference}/poll', [CheckoutController::class, 'poll'])->name('checkout.poll');
Route::get('/checkout/{reference}/sandbox-pay', [CheckoutController::class, 'sandboxPay'])->name('checkout.sandbox-pay');
Route::post('/checkout/{reference}/sandbox-pay', [CheckoutController::class, 'sandboxComplete'])->name('checkout.sandbox-complete');

// JSON helpers for the Vue forms (session + CSRF)
Route::post('/web/verify-meter', [CatalogController::class, 'verifyMeter'])->middleware('throttle:verify')->name('web.verify-meter');
Route::get('/web/plans/{code}', [CatalogController::class, 'plans'])->name('web.plans');

// SMS login
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login/code', [AuthController::class, 'send'])->middleware('throttle:otp')->name('login.send');
    Route::post('/login/verify', [AuthController::class, 'verify'])->middleware('throttle:otp')->name('login.verify');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Wallet (customers and agents)
Route::middleware(['auth', 'role:customer,agent'])->prefix('app')->name('wallet.')->group(function () {
    Route::get('/', [WalletController::class, 'dashboard'])->name('dashboard');
    Route::get('/buy', [WalletController::class, 'buy'])->name('buy');
    Route::post('/buy', [WalletController::class, 'purchase'])->middleware('throttle:purchases')->name('purchase');
    Route::get('/wallet', [WalletController::class, 'wallet'])->name('wallet');
    Route::get('/history', [WalletController::class, 'history'])->name('history');
    Route::get('/history.csv', [WalletController::class, 'historyCsv'])->name('history.csv');
    Route::get('/help', [WalletController::class, 'help'])->name('help');
    Route::post('/help', [WalletController::class, 'helpStore'])->middleware('throttle:6,1')->name('help.store');
    Route::get('/receipt/{reference}', [WalletController::class, 'receipt'])->name('receipt');
    Route::post('/receipt/{reference}/requery', [WalletController::class, 'requery'])->middleware('throttle:6,1')->name('requery');
    Route::get('/profile', [WalletController::class, 'profile'])->name('profile');
    Route::patch('/profile', [WalletController::class, 'updateProfile'])->name('profile.update');
    Route::post('/profile/pin', [WalletController::class, 'setPin'])->middleware('throttle:6,1')->name('pin');
    Route::delete('/meters/{id}', [WalletController::class, 'deleteMeter'])->name('meters.delete');
    Route::post('/sandbox-fund', [WalletController::class, 'sandboxFund'])->name('sandbox-fund');
});

// Agent tools
Route::middleware(['auth', 'role:agent'])->prefix('agent')->name('agent.')->group(function () {
    Route::get('/', [AgentController::class, 'overview'])->name('overview');
    Route::get('/commission', [AgentController::class, 'commission'])->name('commission');
    Route::get('/bulk', [AgentController::class, 'bulk'])->name('bulk');
    Route::post('/bulk', [AgentController::class, 'bulkStore'])->name('bulk.store');
    Route::get('/bulk/{batch}', [AgentController::class, 'batch'])->name('batch');
});

// Admin: SMS login + TOTP every session
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/2fa', [TwoFactorController::class, 'show'])->name('2fa.show');
    Route::post('/admin/2fa', [TwoFactorController::class, 'verify'])->middleware('throttle:10,1')->name('2fa.verify');
});
Route::middleware(['auth', 'role:admin', '2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');
    Route::get('/transactions', [Admin\TransactionController::class, 'index'])->name('transactions');
    Route::get('/transactions/{transaction}', [Admin\TransactionController::class, 'show'])->name('transactions.show');
    Route::post('/transactions/{transaction}/requery', [Admin\TransactionController::class, 'requery'])->name('transactions.requery');
    Route::post('/transactions/{transaction}/resolve', [Admin\TransactionController::class, 'resolve'])->name('transactions.resolve');
    Route::get('/users', [Admin\UserController::class, 'index'])->name('users');
    Route::get('/users/{user}', [Admin\UserController::class, 'show'])->name('users.show');
    Route::patch('/users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/adjust', [Admin\UserController::class, 'adjust'])->name('users.adjust');
    Route::post('/users/{user}/unlock-pin', [Admin\UserController::class, 'unlockPin'])->name('users.unlock-pin');
    Route::get('/services', [Admin\ServiceController::class, 'index'])->name('services');
    Route::patch('/services/{service}', [Admin\ServiceController::class, 'update'])->name('services.update');
    Route::get('/reconciliation', [Admin\ReconciliationController::class, 'index'])->name('reconciliation');
    Route::post('/reconciliation', [Admin\ReconciliationController::class, 'store'])->name('reconciliation.store');
    Route::get('/audit', Admin\AuditController::class)->name('audit');
    Route::get('/support', [Admin\SupportController::class, 'index'])->name('support');
    Route::patch('/support/{ticket}', [Admin\SupportController::class, 'update'])->name('support.update');
    Route::get('/sandbox', [Admin\SandboxController::class, 'show'])->name('sandbox');
    Route::post('/sandbox/outcome', [Admin\SandboxController::class, 'outcome'])->name('sandbox.outcome');
    Route::post('/sandbox/replay', [Admin\SandboxController::class, 'replayWebhook'])->name('sandbox.replay');
    Route::post('/sandbox/run', [Admin\SandboxController::class, 'run'])->name('sandbox.run');
});
