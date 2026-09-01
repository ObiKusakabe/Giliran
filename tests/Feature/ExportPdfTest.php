<?php

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

test('admin can export pdf via POST request with parameters', function () {
    $admin = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)
        ->post(route('admin.export.pdf'), [
            'tanggal_mulai' => '2026-08-01',
            'tanggal_selesai' => '2026-08-31',
            'jenis' => 'adzan',
        ]);

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'application/pdf');
});
