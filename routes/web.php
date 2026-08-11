<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

// ── Halaman publik ────────────────────────────────────────────────────────────
Route::view('/welcome', 'welcome')->name('home');

// ── Redirect root ke dashboard sesuai role ────────────────────────────────────
Route::middleware('auth')->get('/', HomeController::class)->name('home.redirect');

// ── Admin portal ──────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::view('/dashboard', 'admin.dashboard')->name('dashboard');

    // BATCH 3 — CRUD Tim & Personil (Livewire SFC)
    Route::livewire('/tim', 'pages::admin.manajemen-tim')->name('tim');
    Route::livewire('/personil', 'pages::admin.manajemen-personil')->name('personil');

    // BATCH 4 — CRUD Ruangan
    Route::livewire('/ruangan', 'pages::admin.manajemen-ruangan')->name('ruangan');
    // BATCH 5 — Periode WFO
    Route::livewire('/periode-wfo', 'pages::admin.manajemen-periode-wfo')->name('periode-wfo');
    // BATCH 6 — Grid Jadwal WFO (Signature Element)
    Route::livewire('/jadwal-wfo', 'pages::admin.jadwal-wfo-grid')->name('jadwal-wfo');
    // BATCH 8 — Generate Jadwal
    Route::livewire('/generate-jadwal', 'pages::admin.generate-jadwal')->name('generate-jadwal');
    Route::view('/kalender', 'admin.kalender')->name('kalender');
    Route::view('/export', 'admin.export')->name('export');
});

// ── Personil portal ───────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:personil'])->name('personil.')->group(function () {
    Route::view('/jadwal-saya', 'personil.jadwal-saya')->name('jadwal-saya');
});

// ── Tim portal ────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:tim'])->name('tim.')->group(function () {
    Route::view('/tim/ruangan', 'tim.ruangan')->name('ruangan');
});

// ── Notifikasi (semua role yang login) ────────────────────────────────────────
Route::middleware('auth')->name('notifikasi.')->group(function () {
    Route::view('/notifikasi', 'notifikasi.index')->name('index');
});

require __DIR__.'/settings.php';
