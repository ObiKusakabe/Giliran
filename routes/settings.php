<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    // Single settings page with all sections
    Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');

    // Redirects for old routes (backward compatibility)
    Route::redirect('settings', 'settings/profile');
    Route::redirect('settings/security', 'settings/profile')->name('security.edit');
    Route::redirect('settings/appearance', 'settings/profile')->name('appearance.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
