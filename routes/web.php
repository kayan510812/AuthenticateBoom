<?php

use App\Http\Controllers\Auth\Concerns\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GithubOAuthController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\TwoFactorSettingsController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

/*
|--------------------------------------------------------------------------
| Guest routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // GitHub OAuth
    Route::get('auth/github/redirect', [GithubOAuthController::class, 'redirect'])->name('github.redirect');
    Route::get('auth/github/callback', [GithubOAuthController::class, 'callback'])->name('github.callback');

    // Google OAuth
    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');

    // Second factor, still unauthenticated: only a pending user id in the session.
    Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
    Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store']);
    Route::post('two-factor-challenge/cancel', [TwoFactorChallengeController::class, 'destroy'])->name('two-factor.cancel');
});

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Reachable even while enrollment is still pending.
    Route::get('two-factor', [TwoFactorSettingsController::class, 'show'])->name('two-factor.setup');
    Route::post('two-factor', [TwoFactorSettingsController::class, 'confirm'])->name('two-factor.confirm');
    Route::post('two-factor/reset', [TwoFactorSettingsController::class, 'reset'])->name('two-factor.reset');
    Route::post('two-factor/recovery-codes', [TwoFactorSettingsController::class, 'regenerateRecoveryCodes'])->name('two-factor.recovery-codes');
    Route::delete('two-factor', [TwoFactorSettingsController::class, 'destroy'])->name('two-factor.disable');

    Route::middleware('mfa.enrolled')->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');
    });
});
