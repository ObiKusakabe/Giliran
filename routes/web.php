<?php

use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\KalenderController;
use App\Http\Controllers\Api\DevModeController;
use App\Http\Controllers\HomeController;
use App\Http\Middleware\EnsureTeamOnboardingCompleted;
use Illuminate\Support\Facades\Route;

// ── Halaman publik ────────────────────────────────────────────────────────────
Route::view('/welcome', 'welcome')->name('home');

// ── Redirect root ke dashboard sesuai role ────────────────────────────────────
Route::middleware('auth')->get('/', HomeController::class)->name('home.redirect');

// Alias route 'dashboard' untuk kompatibilitas starter kit settings pages
Route::middleware('auth')->get('/dashboard', HomeController::class)->name('dashboard');

// ── Admin portal ──────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('/dashboard', 'pages::admin.dashboard')->name('dashboard');
    Route::livewire('/tim', 'pages::admin.manajemen-tim')->name('tim');
    Route::livewire('/personil', 'pages::admin.manajemen-personil')->name('personil');
    Route::livewire('/ruangan', 'pages::admin.manajemen-ruangan')->name('ruangan');
    Route::livewire('/periode-wfo', 'pages::admin.manajemen-periode-wfo')->name('periode-wfo');
    Route::livewire('/jadwal-wfo', 'pages::admin.jadwal-wfo-grid')->name('jadwal-wfo');
    Route::livewire('/alokasi-ruangan', 'pages::admin.alokasi-ruangan-grid')->name('alokasi-ruangan');
    Route::livewire('/generate-jadwal', 'pages::admin.generate-jadwal')->name('generate-jadwal');

    // Notulen Briefing Management
    Route::livewire('/notulen-briefing', 'pages::admin.notulen-briefing')->name('notulen-briefing');

    // Redirect kalender to dashboard (kalender now integrated in dashboard)
    Route::redirect('/kalender', '/admin/dashboard')->name('kalender');
    Route::get('/kalender/events', [KalenderController::class, 'events'])->name('kalender.events');

    Route::get('/export', [ExportController::class, 'form'])->name('export');
    Route::match(['get', 'post'], '/export/pdf', [ExportController::class, 'pdf'])->name('export.pdf');
});

// ── Tim portal — jadwal seluruh tim + alokasi ruangan (role tim, 1 akun per instansi) ──
Route::middleware(['auth', 'role:tim', EnsureTeamOnboardingCompleted::class])->name('tim.')->group(function () {
    Route::livewire('/tim/onboarding', 'pages::tim.onboarding')->name('onboarding');
    Route::livewire('/tim/beranda', 'pages::tim.beranda')->name('beranda');
    Route::livewire('/jadwal-tim', 'pages::tim.jadwal-tim')->name('jadwal');
    Route::livewire('/tim/ruangan', 'pages::tim.ruangan')->name('ruangan');
    Route::livewire('/tim/profil', 'pages::tim.profil')->name('profil');
    Route::livewire('/tim/akun', 'pages::tim.akun')->name('akun');
    Route::livewire('/tim/tambah-email', 'tim.tambah-email')->name('tambah-email');

    // Notulen Briefing routes
    Route::middleware('web')->prefix('notulen')->name('notulen.')->group(function () {
        Route::livewire('/create', 'tim.notulen.create')->name('create');
        Route::livewire('/history', 'tim.notulen.history')->name('history');
    });
});

// ── Redirect lama /jadwal-saya → /jadwal-tim (backward compat) ────────────────
Route::middleware('auth')->get('/jadwal-saya', fn () => redirect()->route('tim.jadwal'));

// ── Notifikasi (semua role yang login) ────────────────────────────────────────
Route::middleware('auth')->name('notifikasi.')->group(function () {
    Route::livewire('/notifikasi', 'pages::notifikasi.index')->name('index');
});

// ── Dev Mode API (admin & tim role) ──────────────────────────────────────────
// COMMENTED OUT - Not for production/mentor review
// Route::middleware('auth')->prefix('api/dev-mode')->group(function () {
//     Route::post('/set-time', [DevModeController::class, 'setTime']);
//     Route::post('/reset-time', [DevModeController::class, 'resetTime']);
//     Route::get('/get-time', [DevModeController::class, 'getTime']);
// });

require __DIR__.'/settings.php';
