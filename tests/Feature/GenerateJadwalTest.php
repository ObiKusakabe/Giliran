<?php

use App\Models\AlokasiRuangan;
use App\Models\JadwalAdzanKitab;
use App\Models\JadwalBriefing;
use App\Models\JadwalWfo;
use App\Models\PeriodeWfo;
use App\Models\Personil;
use App\Models\Ruangan;
use App\Models\Tim;
use App\Models\User;
use App\Services\LraScheduler;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function setupPeriodeWfo(): PeriodeWfo
{
    return PeriodeWfo::create([
        'tanggal_mulai' => '2026-08-03',
        'tanggal_selesai' => '2026-08-31',
        'keterangan' => 'Test Periode',
        'status' => 'aktif',
    ]);
}

function setupTimPersonil(string $namaTim, array $namaPersonil): Tim
{
    $tim = Tim::create(['nama_tim' => $namaTim]);
    foreach ($namaPersonil as $nama) {
        Personil::create(['tim_id' => $tim->id, 'nama' => $nama, 'status' => 'aktif']);
    }

    return $tim;
}

function setupRuangan(int $jumlah = 3): void
{
    for ($i = 1; $i <= $jumlah; $i++) {
        Ruangan::create(['nama_ruangan' => "Ruang {$i}", 'kapasitas' => 10, 'status' => 'tersedia']);
    }
}

// ── GenerateJadwalTest ────────────────────────────────────────────────────────

test('generate adzan/kajian menghasilkan data dan dapat disimpan ke DB', function () {
    $periode = setupPeriodeWfo();
    $tim = setupTimPersonil('Tim A', ['P1', 'P2', 'P3', 'P4']);
    JadwalWfo::create(['periode_wfo_id' => $periode->id, 'tim_id' => $tim->id, 'hari' => 'senin']);

    $scheduler = new LraScheduler;
    $tanggalList = [$scheduler->expandTanggal(Carbon::parse('2026-08-03'), Carbon::parse('2026-08-03'))];
    $tanggalList = $tanggalList[0]; // flatten

    $hasil = $scheduler->generateAdzanKajian($tanggalList, $periode->id);

    expect($hasil)->not->toBeEmpty();
    expect($hasil[0])->toHaveKeys(['personil_id', 'tanggal', 'waktu_sholat', 'jenis_tugas', 'status_konfirmasi']);

    // Simpan ke DB
    DB::transaction(fn () => JadwalAdzanKitab::insert($hasil));

    expect(JadwalAdzanKitab::count())->toBe(count($hasil));
});

test('generate briefing menghasilkan 1 perwakilan per tim per sesi', function () {
    $periode = setupPeriodeWfo();
    $tim1 = setupTimPersonil('Tim B1', ['X1', 'X2']);
    $tim2 = setupTimPersonil('Tim B2', ['X3', 'X4']);

    JadwalWfo::create(['periode_wfo_id' => $periode->id, 'tim_id' => $tim1->id, 'hari' => 'rabu']);
    JadwalWfo::create(['periode_wfo_id' => $periode->id, 'tim_id' => $tim2->id, 'hari' => 'rabu']);

    $scheduler = new LraScheduler;
    $hasil = $scheduler->generateBriefing([Carbon::parse('2026-08-05')], $periode->id); // Rabu

    // 2 tim × 2 sesi = 4
    expect($hasil)->toHaveCount(4);

    DB::transaction(fn () => JadwalBriefing::insert($hasil));
    expect(JadwalBriefing::count())->toBe(4);
});

test('generate alokasi ruangan: tiap tim dapat 1 ruangan unik per hari', function () {
    $periode = setupPeriodeWfo();
    $tim1 = setupTimPersonil('Tim C1', []);
    $tim2 = setupTimPersonil('Tim C2', []);
    setupRuangan(3);

    JadwalWfo::create(['periode_wfo_id' => $periode->id, 'tim_id' => $tim1->id, 'hari' => 'senin']);
    JadwalWfo::create(['periode_wfo_id' => $periode->id, 'tim_id' => $tim2->id, 'hari' => 'senin']);

    $scheduler = new LraScheduler;
    $hasil = $scheduler->generateAlokasiRuangan([Carbon::parse('2026-08-03')], $periode->id);

    expect($hasil)->toHaveCount(2);

    $ruanganIds = collect($hasil)->pluck('ruangan_id')->toArray();
    expect($ruanganIds)->toBe(array_unique($ruanganIds));

    DB::transaction(fn () => AlokasiRuangan::insert($hasil));
    expect(AlokasiRuangan::count())->toBe(2);
});

test('generate 1 minggu penuh: semua tabel terisi dan GEN-05 terpenuhi', function () {
    $periode = setupPeriodeWfo();
    $tim = setupTimPersonil('Tim D', ['D1', 'D2', 'D3', 'D4', 'D5', 'D6']);
    setupRuangan(2);

    // Tim WFO Senin & Rabu
    JadwalWfo::create(['periode_wfo_id' => $periode->id, 'tim_id' => $tim->id, 'hari' => 'senin']);
    JadwalWfo::create(['periode_wfo_id' => $periode->id, 'tim_id' => $tim->id, 'hari' => 'rabu']);

    $scheduler = new LraScheduler;
    $tanggalList = $scheduler->expandTanggal(Carbon::parse('2026-08-03'), Carbon::parse('2026-08-08'));

    $adzan = $scheduler->generateAdzanKajian($tanggalList, $periode->id);
    $briefing = $scheduler->generateBriefing($tanggalList, $periode->id);
    $ruangan = $scheduler->generateAlokasiRuangan($tanggalList, $periode->id);

    DB::transaction(function () use ($adzan, $briefing, $ruangan) {
        JadwalAdzanKitab::insert($adzan);
        JadwalBriefing::insert($briefing);
        AlokasiRuangan::insert($ruangan);
    });

    // GEN-05: tidak ada personil yang sama dapat 2 tugas di slot yang sama
    $duplikat = JadwalAdzanKitab::selectRaw('tanggal, waktu_sholat, personil_id, COUNT(*) as c')
        ->groupBy('tanggal', 'waktu_sholat', 'personil_id')
        ->having('c', '>', 1)
        ->count();

    expect($duplikat)->toBe(0);

    // GEN-15: tidak ada ruangan dobel per hari
    $duplikatRuangan = AlokasiRuangan::selectRaw('tanggal, ruangan_id, COUNT(*) as c')
        ->groupBy('tanggal', 'ruangan_id')
        ->having('c', '>', 1)
        ->count();

    expect($duplikatRuangan)->toBe(0);

    expect(JadwalAdzanKitab::count())->toBeGreaterThan(0);
    expect(JadwalBriefing::count())->toBeGreaterThan(0);
    expect(AlokasiRuangan::count())->toBeGreaterThan(0);
});

test('halaman admin generate-jadwal dapat diakses dan dirender dengan 200 OK', function () {
    $user = User::factory()->create(['role' => 'admin']);
    setupPeriodeWfo();

    $this->actingAs($user)
        ->get(route('admin.generate-jadwal'))
        ->assertOk()
        ->assertSee('Generate Jadwal')
        ->assertSee('Pilih Mode Generate');
});
