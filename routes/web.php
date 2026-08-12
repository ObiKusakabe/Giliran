<?php

use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\KalenderController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

// ── Halaman publik ────────────────────────────────────────────────────────────
Route::view('/welcome', 'welcome')->name('home');

// ── Redirect root ke dashboard sesuai role ────────────────────────────────────
Route::middleware('auth')->get('/', HomeController::class)->name('home.redirect');

// Alias route 'dashboard' untuk kompatibilitas starter kit settings pages
Route::middleware('auth')->get('/dashboard', HomeController::class)->name('dashboard');

// ── Admin portal ──────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    // BATCH 10 — Dashboard
    Route::livewire('/dashboard', 'pages::admin.dashboard')->name('dashboard');

    // BATCH 3 — CRUD Tim & Personil
    Route::livewire('/tim', 'pages::admin.manajemen-tim')->name('tim');
    Route::livewire('/personil', 'pages::admin.manajemen-personil')->name('personil');

    // BATCH 4 — CRUD Ruangan
    Route::livewire('/ruangan', 'pages::admin.manajemen-ruangan')->name('ruangan');

    // BATCH 5 — Periode WFO
    Route::livewire('/periode-wfo', 'pages::admin.manajemen-periode-wfo')->name('periode-wfo');

    // BATCH 6 — Grid Jadwal WFO
    Route::livewire('/jadwal-wfo', 'pages::admin.jadwal-wfo-grid')->name('jadwal-wfo');

    // BATCH 8 — Generate Jadwal
    Route::livewire('/generate-jadwal', 'pages::admin.generate-jadwal')->name('generate-jadwal');

    // BATCH 10 — Kalender terpadu + endpoint JSON events
    Route::livewire('/kalender', 'pages::admin.kalender')->name('kalender');
    Route::get('/kalender/events', [KalenderController::class, 'events'])->name('kalender.events');

    // BATCH 11 — Export
    Route::get('/export', [ExportController::class, 'form'])->name('export');
    Route::post('/export/pdf', [ExportController::class, 'pdf'])->name('export.pdf');
});

// ── Personil portal ───────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:personil'])->name('personil.')->group(function () {
    Route::livewire('/jadwal-saya', 'pages::personil.jadwal-saya')->name('jadwal-saya');
});

// ── Tim portal ────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:tim'])->name('tim.')->group(function () {
    // BATCH 10 — Tim/Ruangan read-only
    Route::livewire('/tim/ruangan', 'pages::tim.ruangan')->name('ruangan');
});

// ── Notifikasi (semua role yang login) ────────────────────────────────────────
Route::middleware('auth')->name('notifikasi.')->group(function () {
    Route::livewire('/notifikasi', 'pages::notifikasi.index')->name('index');
});

require __DIR__.'/settings.php';
