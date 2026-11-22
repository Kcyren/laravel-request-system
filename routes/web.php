<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ServiceRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('requests.index'));

// Release health check (Lab 8): status, version, revision, database reachability.
// Stateless: no session/cookies, so it reports "fail" (503) instead of crashing
// when the database or the sessions table is unavailable.
Route::get('/health', HealthController::class)
    ->withoutMiddleware([
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
    ])
    ->name('health');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/requests', [ServiceRequestController::class, 'index'])->name('requests.index');
    Route::get('/requests/create', [ServiceRequestController::class, 'create'])->name('requests.create');
    Route::post('/requests', [ServiceRequestController::class, 'store'])->name('requests.store');
    Route::get('/requests/{serviceRequest}', [ServiceRequestController::class, 'show'])
        ->whereNumber('serviceRequest')
        ->name('requests.show');
    Route::patch('/requests/{serviceRequest}/status', [ServiceRequestController::class, 'updateStatus'])
        ->whereNumber('serviceRequest')
        ->name('requests.updateStatus');

    Route::get('/account', [AccountController::class, 'show'])->name('account.show');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])
        ->middleware('throttle:6,1')
        ->name('account.password');
    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
});
