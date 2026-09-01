<?php

use App\Models\Personil;
use App\Models\Tim;
use App\Services\TimAccountGenerator;
use Livewire\Livewire;

test('admin can generate standalone team accounts in batch', function () {
    $generator = new TimAccountGenerator;
    $accounts = $generator->createMultipleStandaloneAccounts(3);

    expect($accounts)->toHaveCount(3);
    foreach ($accounts as $acc) {
        expect($acc->role)->toBe('tim');
        expect($acc->tim_id)->toBeNull();
        expect($acc->needsOnboarding())->toBeTrue();
    }
});

test('tim user with no team is redirected to onboarding from home', function () {
    $user = (new TimAccountGenerator)->createStandaloneAccount();

    $this->actingAs($user);

    $this->get('/')
        ->assertRedirect(route('tim.onboarding'));
});

test('tim user cannot access jadwal-tim before completing onboarding', function () {
    $user = (new TimAccountGenerator)->createStandaloneAccount();

    $this->actingAs($user);

    $this->get(route('tim.jadwal'))
        ->assertRedirect(route('tim.onboarding'));
});

test('tim user can view onboarding page and complete setup with team name and personil list', function () {
    $user = (new TimAccountGenerator)->createStandaloneAccount();

    $this->actingAs($user);

    $this->get(route('tim.onboarding'))
        ->assertStatus(200)
        ->assertSee('Identitas Tim')
        ->assertSee('Daftar Anggota Tim');

    Livewire::test('pages::tim.onboarding')
        ->set('nama_tim', 'Tim Universitas Indonesia')
        ->set('keterangan', 'Fasilkom 2026')
        ->set('personil', [
            ['nama' => 'Budi Santoso', 'jenis_kelamin' => 'laki-laki', 'no_hp' => '08123456789'],
            ['nama' => 'Siti Nurhaliza', 'jenis_kelamin' => 'perempuan', 'no_hp' => '08129876543'],
        ])
        ->call('simpan')
        ->assertRedirect(route('tim.jadwal'));

    $user->refresh();
    expect($user->needsOnboarding())->toBeFalse();
    expect($user->tim)->not->toBeNull();
    expect($user->tim->nama_tim)->toContain('Tim Universitas Indonesia');

    $personil = Personil::where('tim_id', $user->tim_id)->get();
    expect($personil)->toHaveCount(2);
    expect($personil->pluck('nama')->toArray())->toContain('Budi Santoso', 'Siti Nurhaliza');
    expect($personil->where('nama', 'Budi Santoso')->first()->jenis_kelamin)->toBe('laki-laki');
    expect($personil->where('nama', 'Siti Nurhaliza')->first()->jenis_kelamin)->toBe('perempuan');
});

test('tim user with completed profile is redirected away from onboarding to jadwal-tim', function () {
    $tim = Tim::create([
        'nama_tim' => 'Tim Telkom',
        'status' => 'active',
    ]);
    $user = (new TimAccountGenerator)->createAccount($tim);

    $this->actingAs($user);

    $this->get(route('tim.onboarding'))
        ->assertRedirect(route('tim.jadwal'));
});

test('tim user can logout from onboarding page', function () {
    $user = (new TimAccountGenerator)->createStandaloneAccount();

    $this->actingAs($user);

    Livewire::test('pages::tim.onboarding')
        ->call('logout')
        ->assertRedirect('/');

    expect(auth()->check())->toBeFalse();
});
