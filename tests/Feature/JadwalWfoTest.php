<?php

use App\Models\PeriodeWfo;
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
