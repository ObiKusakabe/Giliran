<?php

use App\Models\AlokasiRuangan;
use App\Models\JadwalWfo;
use App\Models\PeriodeWfo;
use App\Models\Ruangan;
use App\Models\Tim;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('admin can access jadwal wfo page without error and switch periode', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $periode1 = PeriodeWfo::create([
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-31',
        'keterangan' => 'Agustus 2026',
        'status' => 'aktif',
    ]);

    $periode2 = PeriodeWfo::create([
        'tanggal_mulai' => '2026-09-01',
        'tanggal_selesai' => '2026-09-30',
        'keterangan' => 'September 2026',
        'status' => 'nonaktif',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.jadwal-wfo'))
        ->assertOk();

    Livewire::actingAs($admin)
        ->test('pages::admin.jadwal-wfo-grid')
        ->assertSet('periodeId', $periode1->id)
        ->set('periodeId', $periode2->id)
        ->assertSet('periodeId', $periode2->id);
});

test('deleting team in jadwal wfo removes room allocations across period', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $periode = PeriodeWfo::create([
        'tanggal_mulai' => '2026-08-03', // Senin
        'tanggal_selesai' => '2026-08-16', // 2 minggu
        'keterangan' => 'Agustus 2026',
        'status' => 'aktif',
    ]);

    $tim = Tim::create(['nama_tim' => 'Tim Alpha', 'status' => 'active']);
    $ruangan = Ruangan::create(['nama_ruangan' => 'Ruang 101', 'kapasitas' => 10, 'status' => 'tersedia']);

    $jadwal = JadwalWfo::create([
        'periode_wfo_id' => $periode->id,
        'tim_id' => $tim->id,
        'hari' => 'senin',
    ]);

    // Alokasikan ruangan untuk tim pada Senin minggu 1 dan Senin minggu 2
    AlokasiRuangan::create(['tim_id' => $tim->id, 'ruangan_id' => $ruangan->id, 'tanggal' => '2026-08-03']);
    AlokasiRuangan::create(['tim_id' => $tim->id, 'ruangan_id' => $ruangan->id, 'tanggal' => '2026-08-10']);

    expect(AlokasiRuangan::where('tim_id', $tim->id)->count())->toBe(2);

    Livewire::actingAs($admin)
        ->test('pages::admin.jadwal-wfo-grid')
        ->call('hapusTim', $jadwal->id);

    expect(JadwalWfo::find($jadwal->id))->toBeNull();
    expect(AlokasiRuangan::where('tim_id', $tim->id)->count())->toBe(0);
});

test('moving team in jadwal wfo clears old day allocations and auto assigns new day', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $periode = PeriodeWfo::create([
        'tanggal_mulai' => '2026-08-03', // Senin
        'tanggal_selesai' => '2026-08-09', // 1 minggu
        'keterangan' => 'Agustus 2026',
        'status' => 'aktif',
    ]);

    $tim = Tim::create(['nama_tim' => 'Tim Alpha', 'status' => 'active']);
    $ruangan = Ruangan::create(['nama_ruangan' => 'Ruang 101', 'kapasitas' => 10, 'status' => 'tersedia']);

    $jadwal = JadwalWfo::create([
        'periode_wfo_id' => $periode->id,
        'tim_id' => $tim->id,
        'hari' => 'senin',
    ]);

    AlokasiRuangan::create(['tim_id' => $tim->id, 'ruangan_id' => $ruangan->id, 'tanggal' => '2026-08-03']);

    Livewire::actingAs($admin)
        ->test('pages::admin.jadwal-wfo-grid')
        ->call('pindahTim', $jadwal->id, 'selasa');

    // Alokasi pada hari Senin harus hilang
    expect(AlokasiRuangan::where('tim_id', $tim->id)->where('tanggal', '2026-08-03')->exists())->toBeFalse();

    // Dan otomatis dialokasikan ke hari Selasa (2026-08-04) karena Ruangan 101 tersedia
    expect(AlokasiRuangan::where('tim_id', $tim->id)->where('tanggal', '2026-08-04')->exists())->toBeTrue();
});

test('alokasi ruangan grid validates team wfo schedule and syncs with wfo', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $periode = PeriodeWfo::create([
        'tanggal_mulai' => '2026-08-03', // Senin
        'tanggal_selesai' => '2026-08-09',
        'keterangan' => 'Agustus 2026',
        'status' => 'aktif',
    ]);

    $timA = Tim::create(['nama_tim' => 'Tim A', 'status' => 'active']);
    $timB = Tim::create(['nama_tim' => 'Tim B', 'status' => 'active']);
    $ruangan = Ruangan::create(['nama_ruangan' => 'Ruang 101', 'kapasitas' => 10, 'status' => 'tersedia']);
    $ruangan2 = Ruangan::create(['nama_ruangan' => 'Ruang 102', 'kapasitas' => 10, 'status' => 'tersedia']);

    // Tim A WFO Senin, Tim B WFO Selasa
    JadwalWfo::create(['periode_wfo_id' => $periode->id, 'tim_id' => $timA->id, 'hari' => 'senin']);
    JadwalWfo::create(['periode_wfo_id' => $periode->id, 'tim_id' => $timB->id, 'hari' => 'selasa']);

    $component = Livewire::actingAs($admin)
        ->test('pages::admin.alokasi-ruangan-grid');

    // Coba alokasikan Tim B ke hari Senin (padahal Tim B WFO hari Selasa)
    $component->call('tambahAlokasi', $timB->id, $ruangan->id, '2026-08-03');

    // Harus ditolak (tidak tersimpan)
    expect(AlokasiRuangan::where('tim_id', $timB->id)->whereDate('tanggal', '2026-08-03')->exists())->toBeFalse();

    // Sekarang alokasikan Tim A ke hari Senin (sesuai jadwal WFO)
    $component->call('tambahAlokasi', $timA->id, $ruangan->id, '2026-08-03');
    expect(AlokasiRuangan::where('tim_id', $timA->id)->whereDate('tanggal', '2026-08-03')->exists())->toBeTrue();

    // Jika ada alokasi salah yang tersisa (misal Tim B di hari Senin dimasukkan manual ke DB pada ruangan 2)
    AlokasiRuangan::create(['tim_id' => $timB->id, 'ruangan_id' => $ruangan2->id, 'tanggal' => '2026-08-03']);

    // Jalankan syncWithJadwalWfo
    $component->call('sinkronkanJadwalWfo');

    // Alokasi Tim B di hari Senin harus dibersihkan, sedangkan Tim A tetap ada
    expect(AlokasiRuangan::where('tim_id', $timB->id)->whereDate('tanggal', '2026-08-03')->exists())->toBeFalse();
    expect(AlokasiRuangan::where('tim_id', $timA->id)->whereDate('tanggal', '2026-08-03')->exists())->toBeTrue();
});

test('generating jadwal wfo automatically creates synchronized room allocations', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $periode = PeriodeWfo::create([
        'tanggal_mulai' => '2026-08-03', // Senin
        'tanggal_selesai' => '2026-08-09',
        'keterangan' => 'Agustus 2026',
        'status' => 'aktif',
    ]);

    $tim1 = Tim::create(['nama_tim' => 'Tim 1', 'status' => 'active']);
    $tim2 = Tim::create(['nama_tim' => 'Tim 2', 'status' => 'active']);
    Ruangan::create(['nama_ruangan' => 'Ruang 101', 'kapasitas' => 10, 'status' => 'tersedia']);
    Ruangan::create(['nama_ruangan' => 'Ruang 102', 'kapasitas' => 10, 'status' => 'tersedia']);

    Livewire::actingAs($admin)
        ->test('pages::admin.jadwal-wfo-grid')
        ->call('generateJadwalWfo');

    // JadwalWFO harus terbuat
    expect(JadwalWfo::where('periode_wfo_id', $periode->id)->count())->toBeGreaterThan(0);

    // AlokasiRuangan harus otomatis tersinkronisasi dan terbuat
    expect(AlokasiRuangan::count())->toBeGreaterThan(0);
});
