<?php

use App\Models\PeriodeWfo;
use App\Models\User;

test('admin can export pdf via GET request with parameters', function () {
    $admin = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)
        ->get(route('admin.export.pdf', [
            'tanggal_mulai' => '2026-08-01',
            'tanggal_selesai' => '2026-08-31',
            'jenis' => 'semua',
        ]));

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'application/pdf');
});

test('admin can export pdf with granular include options', function () {
    $admin = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)
        ->post(route('admin.export.pdf'), [
            'tanggal_mulai' => '2026-08-01',
            'tanggal_selesai' => '2026-08-31',
            'include_surat' => 1,
            'include_wfo' => 1,
            'include_kelompok' => 0,
            'include_ruangan' => 1,
            'include_adzan' => 0,
            'include_briefing' => 1,
        ]);

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'application/pdf');
});

test('admin can export pdf with only ruangan option', function () {
    $admin = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)
        ->post(route('admin.export.pdf'), [
            'tanggal_mulai' => '2026-08-01',
            'tanggal_selesai' => '2026-08-31',
            'include_surat' => 0,
            'include_wfo' => 0,
            'include_kelompok' => 0,
            'include_ruangan' => 1,
            'include_adzan' => 0,
            'include_briefing' => 0,
        ]);

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'application/pdf');
});

test('admin can export pdf by selecting periode_wfo_id', function () {
    $admin = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin']);
    $periode = PeriodeWfo::first() ?? PeriodeWfo::create([
        'nama' => 'Periode Test Export',
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-31',
        'status' => 'aktif',
    ]);

    $response = $this->actingAs($admin)
        ->post(route('admin.export.pdf'), [
            'periode_wfo_id' => $periode->id,
            'include_wfo' => 1,
            'include_ruangan' => 1,
        ]);

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'application/pdf');
});

test('admin can export pdf with only briefing option from modal', function () {
    $admin = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)
        ->post(route('admin.export.pdf'), [
            'from_modal' => '1',
            'tanggal_mulai' => '2026-08-01',
            'tanggal_selesai' => '2026-08-31',
            'include_briefing' => '1',
        ]);

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'application/pdf');
});
