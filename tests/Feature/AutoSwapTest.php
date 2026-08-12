<?php

use App\Models\JadwalAdzanKitab;
use App\Models\JadwalBriefing;
use App\Models\JadwalWfo;
use App\Models\Notifikasi;
use App\Models\PeriodeWfo;
use App\Models\Personil;
use App\Models\Tim;
use App\Models\User;
use App\Services\AutoSwapService;
use App\Services\LraScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function buatSetupSwap(): array
{
    $periode = PeriodeWfo::create([
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-31',
        'status' => 'aktif',
        'keterangan' => 'Test',
    ]);

    $tim = Tim::create(['nama_tim' => 'Tim Swap']);
    $p1 = Personil::create(['tim_id' => $tim->id, 'nama' => 'Personil A', 'status' => 'aktif']);
    $p2 = Personil::create(['tim_id' => $tim->id, 'nama' => 'Personil B', 'status' => 'aktif']);
    $p3 = Personil::create(['tim_id' => $tim->id, 'nama' => 'Personil C', 'status' => 'aktif']);

    // Jadwal WFO harus ada supaya ambilPersonilWfo() bisa menemukan kandidat pengganti
    JadwalWfo::create(['periode_wfo_id' => $periode->id, 'tim_id' => $tim->id, 'hari' => 'senin']);

    // Buat akun admin untuk menerima notifikasi NOT-07
    User::create([
        'name' => 'Admin Test',
        'email' => 'admin.test@giliran.test',
        'password' => bcrypt('password'),
        'role' => 'admin',
        'username' => 'admin.test',
    ]);

    return compact('periode', 'tim', 'p1', 'p2', 'p3');
}

// ── Test: Konfirmasi Siap ─────────────────────────────────────────────────────

test('personil bisa konfirmasi siap untuk jadwal adzan', function () {
    ['p1' => $p1] = buatSetupSwap();

    $jadwal = JadwalAdzanKitab::create([
        'personil_id' => $p1->id,
        'tanggal' => '2026-08-03',
        'waktu_sholat' => 'dhuhr',
        'jenis_tugas' => 'adzan',
        'status_konfirmasi' => 'menunggu',
    ]);

    $jadwal->update(['status_konfirmasi' => 'siap']);

    expect($jadwal->fresh()->status_konfirmasi)->toBe('siap');
});

// ── Test: Auto-Swap Adzan ─────────────────────────────────────────────────────

test('berhalangan adzan: status diupdate dan pengganti ditemukan', function () {
    ['periode' => $periode, 'p1' => $p1] = buatSetupSwap();

    $jadwal = JadwalAdzanKitab::create([
        'personil_id' => $p1->id,
        'tanggal' => '2026-08-03',
        'waktu_sholat' => 'dhuhr',
        'jenis_tugas' => 'adzan',
        'status_konfirmasi' => 'menunggu',
    ]);

    $service = new AutoSwapService(new LraScheduler);
    $hasil = $service->berhalanganAdzan($jadwal->id, $p1->id);

    // Status jadwal lama harus berhalangan
    expect($jadwal->fresh()->status_konfirmasi)->toBe('berhalangan');

    // Pesan harus menyebut pengganti ditemukan
    expect($hasil)->toContain('Pengganti ditemukan');

    // Jadwal baru dibuat untuk pengganti
    $jadwalBaru = JadwalAdzanKitab::where('tanggal', '2026-08-03')
        ->where('waktu_sholat', 'dhuhr')
        ->where('jenis_tugas', 'adzan')
        ->where('status_konfirmasi', 'menunggu')
        ->where('personil_id', '!=', $p1->id)
        ->first();

    expect($jadwalBaru)->not->toBeNull();
});

test('berhalangan adzan: notifikasi dikirim ke pengganti dan admin (NOT-06, NOT-07)', function () {
    ['periode' => $periode, 'p1' => $p1] = buatSetupSwap();

    $jadwal = JadwalAdzanKitab::create([
        'personil_id' => $p1->id,
        'tanggal' => '2026-08-03',
        'waktu_sholat' => 'asr',
        'jenis_tugas' => 'kajian',
        'status_konfirmasi' => 'menunggu',
    ]);

    $service = new AutoSwapService(new LraScheduler);
    $service->berhalanganAdzan($jadwal->id, $p1->id);

    // Notifikasi pengganti (tipe: pengganti)
    $notifPengganti = Notifikasi::where('tipe', 'pengganti')
        ->whereNotNull('personil_id')
        ->count();
    expect($notifPengganti)->toBeGreaterThan(0);

    // Notifikasi admin (NOT-07)
    $notifAdmin = Notifikasi::where('tipe', 'pengganti')
        ->whereNotNull('user_id')
        ->count();
    expect($notifAdmin)->toBeGreaterThan(0);
});

// ── Test: Auto-Swap Briefing ──────────────────────────────────────────────────

test('berhalangan briefing: pengganti dari tim yang sama ditemukan', function () {
    ['periode' => $periode, 'tim' => $tim, 'p1' => $p1] = buatSetupSwap();

    $jadwal = JadwalBriefing::create([
        'tim_id' => $tim->id,
        'personil_id' => $p1->id,
        'tanggal' => '2026-08-03',
        'sesi' => 'pagi',
        'status_konfirmasi' => 'menunggu',
    ]);

    $service = new AutoSwapService(new LraScheduler);
    $hasil = $service->berhalanganBriefing($jadwal->id, $p1->id);

    expect($jadwal->fresh()->status_konfirmasi)->toBe('berhalangan');
    expect($hasil)->toContain('Pengganti ditemukan');

    // Pengganti harus dari tim yang sama
    $jadwalBaru = JadwalBriefing::where('tanggal', '2026-08-03')
        ->where('sesi', 'pagi')
        ->where('tim_id', $tim->id)
        ->where('status_konfirmasi', 'menunggu')
        ->where('personil_id', '!=', $p1->id)
        ->first();

    expect($jadwalBaru)->not->toBeNull();
    expect($jadwalBaru->personil->tim_id)->toBe($tim->id);
});

// ── Test: Tidak ada pengganti ─────────────────────────────────────────────────

test('kalau tidak ada pengganti, admin dinotifikasi untuk assign manual', function () {
    ['periode' => $periode, 'tim' => $tim, 'p1' => $p1, 'p2' => $p2, 'p3' => $p3] = buatSetupSwap();

    // Buat semua personil lain sudah bertugas di slot yang sama
    JadwalAdzanKitab::create(['personil_id' => $p2->id, 'tanggal' => '2026-08-03', 'waktu_sholat' => 'dhuhr', 'jenis_tugas' => 'adzan', 'status_konfirmasi' => 'siap']);
    JadwalAdzanKitab::create(['personil_id' => $p3->id, 'tanggal' => '2026-08-03', 'waktu_sholat' => 'dhuhr', 'jenis_tugas' => 'kajian', 'status_konfirmasi' => 'siap']);

    $jadwal = JadwalAdzanKitab::create([
        'personil_id' => $p1->id,
        'tanggal' => '2026-08-03',
        'waktu_sholat' => 'dhuhr',
        'jenis_tugas' => 'adzan',
        'status_konfirmasi' => 'menunggu',
    ]);

    $service = new AutoSwapService(new LraScheduler);
    $hasil = $service->berhalanganAdzan($jadwal->id, $p1->id);

    // Pesan harus bilang tidak ada pengganti
    expect($hasil)->toContain('Tidak ada pengganti');

    // Admin tetap dinotifikasi
    $notifAdmin = Notifikasi::where('tipe', 'pengganti')->whereNotNull('user_id')->count();
    expect($notifAdmin)->toBeGreaterThan(0);
});

// ── Test: Scoping notifikasi per role ─────────────────────────────────────────

test('notifikasi personil tidak bisa dilihat oleh role lain', function () {
    $tim = Tim::create(['nama_tim' => 'Tim Notif']);
    $p = Personil::create(['tim_id' => $tim->id, 'nama' => 'Test Personil', 'status' => 'aktif']);
    $admin = User::create(['name' => 'Admin', 'email' => 'a@test.com', 'password' => bcrypt('pw'), 'role' => 'admin', 'username' => 'a_test']);

    Notifikasi::create(['personil_id' => $p->id, 'user_id' => null, 'tipe' => 'jadwal', 'pesan' => 'Notif personil', 'dibaca' => false, 'terkirim_pada' => now()]);
    Notifikasi::create(['personil_id' => null, 'user_id' => $admin->id, 'tipe' => 'pengganti', 'pesan' => 'Notif admin', 'dibaca' => false, 'terkirim_pada' => now()]);

    // Scoping personil
    $qPersonil = Notifikasi::where('personil_id', $p->id)->count();
    expect($qPersonil)->toBe(1);

    // Scoping admin (personal + broadcast)
    $qAdmin = Notifikasi::where(function ($q) use ($admin) {
        $q->where('user_id', $admin->id)
            ->orWhere(fn ($q2) => $q2->whereNull('personil_id')->whereNull('user_id'));
    })->count();
    expect($qAdmin)->toBe(1);

    // Cross-leak: 0
    $leak = Notifikasi::where('personil_id', $p->id)->where('user_id', $admin->id)->count();
    expect($leak)->toBe(0);
});
