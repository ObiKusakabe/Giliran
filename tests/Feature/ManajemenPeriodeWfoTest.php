<?php

use App\Models\PeriodeWfo;
use App\Models\User;
use Livewire\Livewire;

test('admin can access periode wfo page', function () {
    $admin = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('admin.periode-wfo'))
        ->assertStatus(200)
        ->assertSee('Periode WFO')
        ->assertSee('Total Periode');
});

test('admin can create and edit periode wfo', function () {
    $admin = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    // Create
    Livewire::test('pages::admin.manajemen-periode-wfo')
        ->set('tanggal_mulai', '2026-10-01')
        ->set('tanggal_selesai', '2026-10-31')
        ->set('keterangan', 'Periode Oktober 2026')
        ->call('simpan')
        ->assertHasNoErrors();

    $periode = PeriodeWfo::where('keterangan', 'Periode Oktober 2026')->first();
    expect($periode)->not->toBeNull();
    expect($periode->status)->toBe('nonaktif');

    // Edit
    Livewire::test('pages::admin.manajemen-periode-wfo')
        ->call('bukaEdit', $periode->id)
        ->set('keterangan', 'Periode Oktober 2026 Revisi')
        ->call('simpan')
        ->assertHasNoErrors();

    $periode->refresh();
    expect($periode->keterangan)->toBe('Periode Oktober 2026 Revisi');
});
