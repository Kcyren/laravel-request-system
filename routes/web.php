<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ServiceRequestController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('requests.index'));

// Health routes are stateless (no session/cookies), so they still answer
// correctly when the database - which also stores sessions - is down.
$stateless = [
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
];

// Release health check (Lab 8): status, version, revision, database reachability.
Route::get('/health', HealthController::class)->withoutMiddleware($stateless)->name('health');

// Lab 10 - liveness: the application process is running (no database call).
Route::get('/health/live', function () {
    return response()->json(['status' => 'ok'], 200)->header('Cache-Control', 'no-store');
})->withoutMiddleware($stateless)->name('health.live');

// Lab 10 - readiness: the application can reach its database.
// Only a generic status is returned - never exception details or credentials.
Route::get('/health/ready', function () {
    try {
        DB::select('SELECT 1');

        return response()->json(['status' => 'ready'], 200)->header('Cache-Control', 'no-store');
    } catch (\Throwable $e) {
        Log::warning('health.ready_failed', ['outcome' => 'database_unavailable']);

        return response()->json(['status' => 'unavailable'], 503)->header('Cache-Control', 'no-store');
    }
})->withoutMiddleware($stateless)->name('health.ready');

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
