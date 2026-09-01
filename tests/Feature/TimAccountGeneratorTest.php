<?php

use App\Models\Tim;
use App\Models\User;
use App\Services\TimAccountGenerator;
use Illuminate\Support\Facades\Hash;

test('tim account generator creates valid username with YYYY_MM_XXX format', function () {
    $generator = new TimAccountGenerator;
    $username = $generator->generateUsername();

    $year = now()->format('Y');
    $month = now()->format('m');

    expect($username)->toMatch("/^{$year}_{$month}_\d{3}$/");
});

test('tim account generator creates user associated with team', function () {
    $tim = Tim::create([
        'nama_tim' => 'Tim Test PKL',
        'status' => 'active',
    ]);

    $generator = new TimAccountGenerator;
    $user = $generator->createAccount($tim);

    expect($user)->toBeInstanceOf(User::class);
    expect($user->role)->toBe('tim');
    expect($user->tim_id)->toBe($tim->id);
    expect($user->name)->toBe('Tim Test PKL');
    expect(Hash::check('inovindojaya', $user->password))->toBeTrue();
    expect($tim->fresh()->hasAccount())->toBeTrue();
});

test('admin can view manajemen tim page with quick stats and generate account', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $tim = Tim::create([
        'nama_tim' => 'Tim Baru Tanpa Akun',
        'status' => 'active',
    ]);

    $this->actingAs($admin);

    $this->get(route('admin.tim'))
        ->assertStatus(200)
        ->assertSee('Total Tim')
        ->assertSee('Tim Aktif')
        ->assertSee('Memiliki Akun');

    $generator = new TimAccountGenerator;
    $user = $generator->createAccount($tim);

    expect($tim->fresh()->hasAccount())->toBeTrue();
    expect($user->username)->toMatch('/^\d{4}_\d{2}_\d{3}$/');
});
