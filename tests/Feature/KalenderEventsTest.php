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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('admin can fetch calendar events json with correct structure', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->getJson('/admin/kalender/events?start=2026-08-01&end=2026-08-31');

    $response->assertOk()
        ->assertJsonIsArray();
});

test('calendar events includes wfo events for active period', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $periode = PeriodeWfo::create([
        'tanggal_mulai' => '2026-08-03', // Senin
        'tanggal_selesai' => '2026-08-09', // Minggu
        'keterangan' => 'Minggu 1 Agustus 2026',
        'status' => 'aktif',
    ]);

    $tim = Tim::create(['nama_tim' => 'Tim Garuda', 'status' => 'active']);

    JadwalWfo::create([
        'periode_wfo_id' => $periode->id,
        'tim_id' => $tim->id,
        'hari' => 'senin',
    ]);

    $response = $this->actingAs($admin)->getJson('/admin/kalender/events?start=2026-08-01&end=2026-08-31&jenis=wfo');

    $response->assertOk();
    $data = $response->json();

    expect($data)->not->toBeEmpty();
    expect($data[0]['extendedProps']['jenis'])->toBe('wfo');
    expect($data[0]['title'])->toContain('Tim Garuda');
    expect($data[0]['start'])->toBe('2026-08-03');
});

test('calendar events filter jenis only returns selected event types', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $periode = PeriodeWfo::create([
        'tanggal_mulai' => '2026-08-03',
        'tanggal_selesai' => '2026-08-09',
        'status' => 'aktif',
    ]);

    $tim = Tim::create(['nama_tim' => 'Tim Elang', 'status' => 'active']);
    $personil = Personil::create([
        'tim_id' => $tim->id,
        'nama' => 'Budi',
        'jenis_kelamin' => 'laki-laki',
        'status' => 'aktif',
    ]);
    $ruangan = Ruangan::create(['nama_ruangan' => 'Ruang Melati', 'kapasitas' => 10, 'status' => 'tersedia']);

    // Create 1 of each type
    JadwalWfo::create(['periode_wfo_id' => $periode->id, 'tim_id' => $tim->id, 'hari' => 'senin']);
    AlokasiRuangan::create(['tim_id' => $tim->id, 'ruangan_id' => $ruangan->id, 'tanggal' => '2026-08-03']);
    JadwalBriefing::create(['tim_id' => $tim->id, 'personil_id' => $personil->id, 'tanggal' => '2026-08-03', 'sesi' => 'pagi']);
    JadwalAdzanKitab::create(['personil_id' => $personil->id, 'tanggal' => '2026-08-03', 'waktu_sholat' => 'dhuhr', 'jenis_tugas' => 'adzan']);

    // Filter briefing
    $resBriefing = $this->actingAs($admin)->getJson('/admin/kalender/events?start=2026-08-01&end=2026-08-31&jenis=briefing');
    $resBriefing->assertOk();
    $dataBriefing = $resBriefing->json();
    expect(collect($dataBriefing)->pluck('extendedProps.jenis')->unique()->toArray())->toBe(['briefing']);

    // Filter ruangan
    $resRuangan = $this->actingAs($admin)->getJson('/admin/kalender/events?start=2026-08-01&end=2026-08-31&jenis=ruangan');
    $resRuangan->assertOk();
    $dataRuangan = $resRuangan->json();
    expect(collect($dataRuangan)->pluck('extendedProps.jenis')->unique()->toArray())->toBe(['ruangan']);

    // Filter adzan
    $resAdzan = $this->actingAs($admin)->getJson('/admin/kalender/events?start=2026-08-01&end=2026-08-31&jenis=adzan');
    $resAdzan->assertOk();
    $dataAdzan = $resAdzan->json();
    expect(collect($dataAdzan)->pluck('extendedProps.jenis')->unique()->toArray())->toBe(['adzan']);

    // Filter wfo
    $resWfo = $this->actingAs($admin)->getJson('/admin/kalender/events?start=2026-08-01&end=2026-08-31&jenis=wfo');
    $resWfo->assertOk();
    $dataWfo = $resWfo->json();
    expect(collect($dataWfo)->pluck('extendedProps.jenis')->unique()->toArray())->toBe(['wfo']);
});

test('calendar events filter tim_id scopes events to specific team', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $tim1 = Tim::create(['nama_tim' => 'Tim 1', 'status' => 'active']);
    $tim2 = Tim::create(['nama_tim' => 'Tim 2', 'status' => 'active']);
    $ruangan = Ruangan::create(['nama_ruangan' => 'Ruang 1', 'kapasitas' => 10, 'status' => 'tersedia']);

    AlokasiRuangan::create(['tim_id' => $tim1->id, 'ruangan_id' => $ruangan->id, 'tanggal' => '2026-08-03']);
    AlokasiRuangan::create(['tim_id' => $tim2->id, 'ruangan_id' => $ruangan->id, 'tanggal' => '2026-08-04']);

    $res = $this->actingAs($admin)->getJson("/admin/kalender/events?start=2026-08-01&end=2026-08-31&jenis=ruangan&tim_id={$tim1->id}");
    $res->assertOk();
    $data = $res->json();

    expect(count($data))->toBe(1);
    expect($data[0]['extendedProps']['tim'])->toBe('Tim 1');
});

test('calendar widget and dashboard can be rendered with reactive filters', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test('pages::admin.dashboard')
        ->set('viewMode', 'kalender')
        ->assertSee('Semua Jenis')
        ->assertSee('Jadwal WFO')
        ->set('filterJenis', 'wfo')
        ->assertSet('filterJenis', 'wfo')
        ->assertDispatched('filter-changed');
});
