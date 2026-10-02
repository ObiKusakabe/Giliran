<?php

use App\Livewire\NotifikasiDropdown;
use App\Models\Notifikasi;
use App\Models\User;
use Livewire\Livewire;

test('notifikasi dropdown component renders successfully', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(NotifikasiDropdown::class)
        ->assertStatus(200)
        ->assertSee('Notifikasi');
});

test('notifikasi dropdown shows unread count and messages', function () {
    $user = User::factory()->create();

    Notifikasi::create([
        'user_id' => $user->id,
        'tipe' => 'jadwal',
        'pesan' => 'Jadwal adzan besok siang',
        'dibaca' => false,
        'terkirim_pada' => now(),
    ]);

    Notifikasi::create([
        'user_id' => $user->id,
        'tipe' => 'reminder',
        'pesan' => 'Pengingat briefing pagi',
        'dibaca' => false,
        'terkirim_pada' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test(NotifikasiDropdown::class)
        ->assertSet('unreadCount', 2)
        ->assertSee('Jadwal adzan besok siang')
        ->assertSee('Pengingat briefing pagi');
});

test('notifikasi dropdown can mark single notification as read', function () {
    $user = User::factory()->create();

    $notif = Notifikasi::create([
        'user_id' => $user->id,
        'tipe' => 'jadwal',
        'pesan' => 'Jadwal adzan besok siang',
        'dibaca' => false,
        'terkirim_pada' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test(NotifikasiDropdown::class)
        ->assertSet('unreadCount', 1)
        ->call('tandaiDibaca', $notif->id)
        ->assertSet('unreadCount', 0);

    expect($notif->fresh()->dibaca)->toBeTrue();
});

test('notifikasi dropdown can mark all notifications as read', function () {
    $user = User::factory()->create();

    Notifikasi::create([
        'user_id' => $user->id,
        'tipe' => 'jadwal',
        'pesan' => 'Notif 1',
        'dibaca' => false,
        'terkirim_pada' => now(),
    ]);

    Notifikasi::create([
        'user_id' => $user->id,
        'tipe' => 'jadwal',
        'pesan' => 'Notif 2',
        'dibaca' => false,
        'terkirim_pada' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test(NotifikasiDropdown::class)
        ->assertSet('unreadCount', 2)
        ->call('markAllRead')
        ->assertSet('unreadCount', 0);

    expect(Notifikasi::where('user_id', $user->id)->where('dibaca', false)->count())->toBe(0);
});

test('notifikasi dropdown renders mobile responsive classes and backdrop', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(NotifikasiDropdown::class)
        ->assertSee('fixed inset-x-3 top-16', false)
        ->assertSee('sm:absolute', false)
        ->assertSee('sm:hidden', false);
});

test('authenticated user can access notifikasi index page with app layout and navigation', function () {
    $user = User::factory()->create(['role' => 'tim']);

    $this->actingAs($user)
        ->get(route('notifikasi.index'))
        ->assertOk()
        ->assertSee('Semua Notifikasi')
        ->assertSee('Kembali');
});

test('notifikasi index page allows marking notifications as read', function () {
    $user = User::factory()->create(['role' => 'tim']);

    $notif = Notifikasi::create([
        'user_id' => $user->id,
        'tipe' => 'jadwal',
        'pesan' => 'Jadwal baru tersedia di sistem',
        'dibaca' => false,
        'terkirim_pada' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::notifikasi.index')
        ->assertSee('Jadwal baru tersedia di sistem')
        ->call('tandaiDibaca', $notif->id)
        ->assertHasNoErrors();

    expect($notif->fresh()->dibaca)->toBeTrue();
});

test('notifikasi index page allows marking all notifications as read', function () {
    $user = User::factory()->create(['role' => 'tim']);

    Notifikasi::create([
        'user_id' => $user->id,
        'tipe' => 'jadwal',
        'pesan' => 'Notif A',
        'dibaca' => false,
        'terkirim_pada' => now(),
    ]);

    Notifikasi::create([
        'user_id' => $user->id,
        'tipe' => 'reminder',
        'pesan' => 'Notif B',
        'dibaca' => false,
        'terkirim_pada' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::notifikasi.index')
        ->call('tandaiSemuaDibaca')
        ->assertHasNoErrors();

    expect(Notifikasi::where('user_id', $user->id)->where('dibaca', false)->count())->toBe(0);
});
