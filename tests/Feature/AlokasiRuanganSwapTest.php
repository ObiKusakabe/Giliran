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

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->admin);
});

test('pindahRuangan successfully swaps two teams in different rooms on the same date without unique constraint violation', function () {
    $periode = PeriodeWfo::create([
        'nama_periode' => 'September 2026',
        'tanggal_mulai' => '2026-09-01',
        'tanggal_selesai' => '2026-09-30',
        'status' => 'aktif',
    ]);

    $timA = Tim::create(['nama_tim' => 'Tim Alpha', 'status' => 'active']);
    $timB = Tim::create(['nama_tim' => 'Tim Beta', 'status' => 'active']);

    $ruangan1 = Ruangan::create(['nama_ruangan' => 'Ruang 1', 'kapasitas' => 10, 'status' => 'tersedia']);
    $ruangan2 = Ruangan::create(['nama_ruangan' => 'Ruang 2', 'kapasitas' => 10, 'status' => 'tersedia']);

    // Kedua tim terjadwal WFO di hari Senin
    JadwalWfo::create(['periode_wfo_id' => $periode->id, 'tim_id' => $timA->id, 'hari' => 'senin']);
    JadwalWfo::create(['periode_wfo_id' => $periode->id, 'tim_id' => $timB->id, 'hari' => 'senin']);

    // Senin pertama: 2026-09-07
    $tanggal = '2026-09-07';
    $alokasiA = AlokasiRuangan::create([
        'tim_id' => $timA->id,
        'ruangan_id' => $ruangan1->id,
        'tanggal' => $tanggal,
        'expected_attendance' => 5,
    ]);

    $alokasiB = AlokasiRuangan::create([
        'tim_id' => $timB->id,
        'ruangan_id' => $ruangan2->id,
        'tanggal' => $tanggal,
        'expected_attendance' => 5,
    ]);

    // Test drag Team A to Ruangan 2 (which is occupied by Team B)
    $component = Livewire::test('pages::admin.alokasi-ruangan-grid')
        ->call('pindahRuangan', $alokasiA->id, $ruangan2->id, $tanggal);

    // Verify swap result on 2026-09-07
    $newAlokasiA = AlokasiRuangan::where('tim_id', $timA->id)->whereDate('tanggal', $tanggal)->first();
    $newAlokasiB = AlokasiRuangan::where('tim_id', $timB->id)->whereDate('tanggal', $tanggal)->first();

    expect($newAlokasiA)->not->toBeNull()
        ->and($newAlokasiA->ruangan_id)->toBe($ruangan2->id)
        ->and($newAlokasiB)->not->toBeNull()
        ->and($newAlokasiB->ruangan_id)->toBe($ruangan1->id);
});

test('pindahRuangan blocks moving team to a day without WFO schedule', function () {
    $periode = PeriodeWfo::create([
        'nama_periode' => 'September 2026',
        'tanggal_mulai' => '2026-09-01',
        'tanggal_selesai' => '2026-09-30',
        'status' => 'aktif',
    ]);

    $timA = Tim::create(['nama_tim' => 'Tim Gamma', 'status' => 'active']);
    $ruangan = Ruangan::create(['nama_ruangan' => 'Ruang 3', 'kapasitas' => 10, 'status' => 'tersedia']);

    // Hanya terjadwal WFO hari Senin
    JadwalWfo::create(['periode_wfo_id' => $periode->id, 'tim_id' => $timA->id, 'hari' => 'senin']);

    $alokasiA = AlokasiRuangan::create([
        'tim_id' => $timA->id,
        'ruangan_id' => $ruangan->id,
        'tanggal' => '2026-09-07', // Senin
        'expected_attendance' => 4,
    ]);

    // Coba pindah ke Selasa (2026-09-08) di mana tim tidak WFO
    Livewire::test('pages::admin.alokasi-ruangan-grid')
        ->call('pindahRuangan', $alokasiA->id, $ruangan->id, '2026-09-08')
        ->assertHasNoErrors();

    // Tim tetap di hari Senin, tidak pindah ke Selasa
    expect(AlokasiRuangan::where('tim_id', $timA->id)->whereDate('tanggal', '2026-09-08')->exists())->toBeFalse();
    expect(AlokasiRuangan::where('tim_id', $timA->id)->whereDate('tanggal', '2026-09-07')->exists())->toBeTrue();
});

test('pindahRuangan warns and does not duplicate if dragged to room it already occupies', function () {
    $periode = PeriodeWfo::create([
        'nama_periode' => 'September 2026',
        'tanggal_mulai' => '2026-09-01',
        'tanggal_selesai' => '2026-09-30',
        'status' => 'aktif',
    ]);

    $timA = Tim::create(['nama_tim' => 'Tim Delta', 'status' => 'active']);
    $ruangan = Ruangan::create(['nama_ruangan' => 'Ruang 4', 'kapasitas' => 10, 'status' => 'tersedia']);

    JadwalWfo::create(['periode_wfo_id' => $periode->id, 'tim_id' => $timA->id, 'hari' => 'senin']);

    $alokasi = AlokasiRuangan::create([
        'tim_id' => $timA->id,
        'ruangan_id' => $ruangan->id,
        'tanggal' => '2026-09-07',
        'expected_attendance' => 3,
    ]);

    Livewire::test('pages::admin.alokasi-ruangan-grid')
        ->call('pindahRuangan', $alokasi->id, $ruangan->id, '2026-09-07');

    expect(AlokasiRuangan::where('tim_id', $timA->id)->whereDate('tanggal', '2026-09-07')->count())->toBe(1);
});
