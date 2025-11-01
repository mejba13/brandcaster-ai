<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('user-password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');

    Volt::route('settings/two-factor', 'settings.two-factor')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');
});

/**
 * Social Media Authentication Routes
 *
 * OAuth flows for connecting Facebook, Twitter, and LinkedIn accounts
 */
Route::middleware(['auth', 'verified'])->prefix('auth/social')->name('social.')->group(function () {
    // Facebook OAuth
    Route::get('facebook/redirect', [App\Http\Controllers\SocialAuthController::class, 'redirectToFacebook'])
        ->name('facebook.redirect');
    Route::get('facebook/callback', [App\Http\Controllers\SocialAuthController::class, 'handleFacebookCallback'])
        ->name('facebook.callback');

    // Twitter OAuth
    Route::get('twitter/redirect', [App\Http\Controllers\SocialAuthController::class, 'redirectToTwitter'])
        ->name('twitter.redirect');
    Route::get('twitter/callback', [App\Http\Controllers\SocialAuthController::class, 'handleTwitterCallback'])
        ->name('twitter.callback');

    // LinkedIn OAuth
    Route::get('linkedin/redirect', [App\Http\Controllers\SocialAuthController::class, 'redirectToLinkedIn'])
        ->name('linkedin.redirect');
    Route::get('linkedin/callback', [App\Http\Controllers\SocialAuthController::class, 'handleLinkedInCallback'])
        ->name('linkedin.callback');

    // Disconnect social account
    Route::post('disconnect/{connectorId}', [App\Http\Controllers\SocialAuthController::class, 'disconnect'])
        ->name('disconnect');
});
