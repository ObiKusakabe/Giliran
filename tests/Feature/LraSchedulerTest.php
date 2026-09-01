<?php

use App\Models\JadwalWfo;
use App\Models\PeriodeWfo;
use App\Models\Personil;
use App\Models\Ruangan;
use App\Models\Tim;
use App\Services\LraScheduler;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function buatTim(string $nama, array $namaPersonil = []): Tim
{
    $tim = Tim::create(['nama_tim' => $nama]);
    foreach ($namaPersonil as $n) {
        Personil::create(['tim_id' => $tim->id, 'nama' => $n, 'status' => 'aktif']);
    }

    return $tim;
}

function buatPeriode(): PeriodeWfo
{
    return PeriodeWfo::create([
        'tanggal_mulai' => '2026-08-03',
        'tanggal_selesai' => '2026-08-08',
        'keterangan' => 'Periode Test',
        'status' => 'aktif',
    ]);
}

// ── Slot adzan ────────────────────────────────────────────────────────────────

test('slot senin-kamis adalah dhuhr dan asr', function () {
    $s = new LraScheduler;
    expect($s->tentukanSlotAdzan(Carbon::parse('2026-08-03')))->toBe(['dhuhr', 'asr']); // Senin
    expect($s->tentukanSlotAdzan(Carbon::parse('2026-08-06')))->toBe(['dhuhr', 'asr']); // Kamis
});

test('slot jumat adalah asr saja', function () {
    expect((new LraScheduler)->tentukanSlotAdzan(Carbon::parse('2026-08-07')))->toBe(['asr']);
});

test('slot sabtu adalah dhuhr saja', function () {
    expect((new LraScheduler)->tentukanSlotAdzan(Carbon::parse('2026-08-08')))->toBe(['dhuhr']);
});

// ── expandTanggal ─────────────────────────────────────────────────────────────

test('expandTanggal skip hari minggu', function () {
    $list = (new LraScheduler)->expandTanggal(
        Carbon::parse('2026-08-07'), // Jumat
        Carbon::parse('2026-08-10')  // Senin
    );

    expect($list)->toHaveCount(3); // Jumat, Sabtu, Senin
    expect($list[0]->toDateString())->toBe('2026-08-07');
    expect($list[1]->toDateString())->toBe('2026-08-08');
    expect($list[2]->toDateString())->toBe('2026-08-10');
});

// ── pilihKandidat ─────────────────────────────────────────────────────────────

test('pilihKandidat memilih personil dengan counter terkecil', function () {
    $tim = buatTim('Tim Pilih', ['Alice', 'Bob', 'Charlie']);
    [$alice, $bob, $charlie] = Personil::where('tim_id', $tim->id)->orderBy('id')->get()->all();

    $counter = [$alice->id => 5, $bob->id => 1, $charlie->id => 3];
    $kandidat = Personil::where('tim_id', $tim->id)->get();
    $terpilih = (new LraScheduler)->pilihKandidat($kandidat, $counter);

    expect($terpilih->id)->toBe($bob->id);
});

test('pilihKandidat tie-breaker: tidak selalu pilih ID terkecil saat counter sama', function () {
    $tim = buatTim('Tim Tie', ['P1', 'P2', 'P3', 'P4', 'P5']);
    $personils = Personil::where('tim_id', $tim->id)->get();
    $counter = [];

    $terpilihIds = [];
    for ($i = 0; $i < 50; $i++) {
        $p = (new LraScheduler)->pilihKandidat($personils, $counter);
        $terpilihIds[$p->id] = true;
    }

    // Lebih dari 1 kandidat berbeda yang terpilih
    expect(count($terpilihIds))->toBeGreaterThan(1);
});

// ── generateAdzanKajian ───────────────────────────────────────────────────────

test('generateAdzanKajian menghasilkan jumlah baris yang tepat per hari', function () {
    $periode = buatPeriode();
    $tim = buatTim('Tim Adzan', ['A', 'B', 'C', 'D']);

    JadwalWfo::create(['periode_wfo_id' => $periode->id, 'tim_id' => $tim->id, 'hari' => 'senin']);

    // 2 Senin: 3 Agt + 10 Agt
    $hasil = (new LraScheduler)->generateAdzanKajian(
        [Carbon::parse('2026-08-03'), Carbon::parse('2026-08-10')],
        $periode->id
    );

    // Senin: 2 slot × 2 tugas = 4 per hari × 2 hari = 8
    expect($hasil)->toHaveCount(8);
    expect(collect($hasil)->pluck('status_konfirmasi')->unique()->toArray())->toBe(['menunggu']);
});

test('GEN-05: tidak ada personil yang sama dapat 2 tugas di slot yang sama', function () {
    $periode = buatPeriode();
    $tim = buatTim('Tim GEN05', ['X1', 'X2', 'X3', 'X4']);

    JadwalWfo::create(['periode_wfo_id' => $periode->id, 'tim_id' => $tim->id, 'hari' => 'senin']);

    $hasil = (new LraScheduler)->generateAdzanKajian([Carbon::parse('2026-08-03')], $periode->id);
    $perSlot = collect($hasil)->groupBy(fn ($r) => $r['tanggal'].'_'.$r['waktu_sholat']);

    foreach ($perSlot as $slot => $rows) {
        $ids = collect($rows)->pluck('personil_id')->toArray();
        expect($ids)->toBe(array_unique($ids), "Slot {$slot}: duplikat personil!");
    }
});

test('counter in-memory ter-update sepanjang batch bukan hanya hari pertama', function () {
    $periode = buatPeriode();
    $tim = buatTim('Tim Counter', ['Alpha', 'Beta']);
    [$alpha, $beta] = Personil::where('tim_id', $tim->id)->orderBy('id')->get()->all();

    JadwalWfo::create(['periode_wfo_id' => $periode->id, 'tim_id' => $tim->id, 'hari' => 'jumat']);

    // 3 Jumat: 7, 14, 21 Agustus
    $hasil = (new LraScheduler)->generateAdzanKajian([
        Carbon::parse('2026-08-07'),
        Carbon::parse('2026-08-14'),
        Carbon::parse('2026-08-21'),
    ], $periode->id);

    // Jumat = 1 slot (asr) × 2 tugas = 2 per Jumat × 3 = 6
    expect($hasil)->toHaveCount(6);

    $distribusi = collect($hasil)->countBy('personil_id')->toArray();
    expect($distribusi[$alpha->id] ?? 0)->toBeGreaterThan(0);
    expect($distribusi[$beta->id] ?? 0)->toBeGreaterThan(0);
});

// ── generateBriefing ──────────────────────────────────────────────────────────

test('generateBriefing: 1 perwakilan per tim per sesi per hari', function () {
    $periode = buatPeriode();
    $tim1 = buatTim('Tim Brief A', ['P1', 'P2', 'P3']);
    $tim2 = buatTim('Tim Brief B', ['P4', 'P5']);

    JadwalWfo::create(['periode_wfo_id' => $periode->id, 'tim_id' => $tim1->id, 'hari' => 'senin']);
    JadwalWfo::create(['periode_wfo_id' => $periode->id, 'tim_id' => $tim2->id, 'hari' => 'senin']);

    $hasil = (new LraScheduler)->generateBriefing([Carbon::parse('2026-08-03')], $periode->id);

    // 2 tim × 2 sesi = 4
    expect($hasil)->toHaveCount(4);

    foreach (collect($hasil)->groupBy(fn ($r) => $r['tim_id'].'_'.$r['sesi']) as $key => $rows) {
        expect($rows)->toHaveCount(1, "{$key} seharusnya 1 perwakilan");
    }
});

// ── generateAlokasiRuangan ────────────────────────────────────────────────────

test('GEN-15: satu ruangan tidak dialokasikan ke 2 tim di hari yang sama', function () {
    $periode = buatPeriode();
    $tim1 = buatTim('Tim R1');
    $tim2 = buatTim('Tim R2');
    $tim3 = buatTim('Tim R3');

    JadwalWfo::create(['periode_wfo_id' => $periode->id, 'tim_id' => $tim1->id, 'hari' => 'senin']);
    JadwalWfo::create(['periode_wfo_id' => $periode->id, 'tim_id' => $tim2->id, 'hari' => 'senin']);
    JadwalWfo::create(['periode_wfo_id' => $periode->id, 'tim_id' => $tim3->id, 'hari' => 'senin']);

    Ruangan::create(['nama_ruangan' => 'R1', 'kapasitas' => 10, 'status' => 'tersedia']);
    Ruangan::create(['nama_ruangan' => 'R2', 'kapasitas' => 10, 'status' => 'tersedia']);
    Ruangan::create(['nama_ruangan' => 'R3', 'kapasitas' => 10, 'status' => 'tersedia']);

    $hasil = (new LraScheduler)->generateAlokasiRuangan([Carbon::parse('2026-08-03')], $periode->id);
    $ruanganIds = collect($hasil)->pluck('ruangan_id')->toArray();

    expect($hasil)->toHaveCount(3);
    expect($ruanganIds)->toBe(array_unique($ruanganIds), 'Ada ruangan dialokasikan ke 2 tim!');
});

test('generateAdzanKajian hanya menjadwalkan personil laki-laki', function () {
    $periode = buatPeriode();
    $tim = Tim::create(['nama_tim' => 'Tim Campuran', 'status' => 'active']);

    $pLaki = Personil::create(['tim_id' => $tim->id, 'nama' => 'Ahmad', 'jenis_kelamin' => 'laki-laki', 'status' => 'aktif']);
    $pPerempuan = Personil::create(['tim_id' => $tim->id, 'nama' => 'Aisyah', 'jenis_kelamin' => 'perempuan', 'status' => 'aktif']);

    JadwalWfo::create(['periode_wfo_id' => $periode->id, 'tim_id' => $tim->id, 'hari' => 'senin']);

    $hasil = (new LraScheduler)->generateAdzanKajian([Carbon::parse('2026-08-03')], $periode->id);

    expect($hasil)->not->toBeEmpty();
    $personilIds = collect($hasil)->pluck('personil_id')->unique()->toArray();

    expect($personilIds)->toContain($pLaki->id);
    expect($personilIds)->not->toContain($pPerempuan->id);
});
