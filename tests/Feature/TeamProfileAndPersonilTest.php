<?php

use App\Models\Personil;
use App\Models\Tim;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('tim user can access profil page', function () {
    $tim = Tim::create(['nama_tim' => 'Tim Garuda IT', 'status' => 'active']);
    $user = User::factory()->create(['role' => 'tim', 'tim_id' => $tim->id]);

    $this->actingAs($user)
        ->get(route('tim.profil'))
        ->assertStatus(200)
        ->assertSee('Tim Garuda IT')
        ->assertSee('Daftar Anggota Personil')
        ->assertSee('Keamanan');
});

test('tim user can update team profile info', function () {
    $tim = Tim::create(['nama_tim' => 'Tim Lama', 'keterangan' => 'Lama', 'status' => 'active']);
    $user = User::factory()->create(['role' => 'tim', 'tim_id' => $tim->id]);

    $this->actingAs($user);

    Livewire::test('pages::tim.profil')
        ->set('nama_tim', 'Tim Baru Inovatif')
        ->set('keterangan', 'Jurusan Teknik Komputer')
        ->call('updateProfilTim')
        ->assertHasNoErrors();

    $tim->refresh();
    expect($tim->nama_tim)->toBe('Tim Baru Inovatif');
    expect($tim->keterangan)->toBe('Jurusan Teknik Komputer');
});

test('tim user can add new personil to team', function () {
    $tim = Tim::create(['nama_tim' => 'Tim Alpha', 'status' => 'active']);
    $user = User::factory()->create(['role' => 'tim', 'tim_id' => $tim->id]);

    $this->actingAs($user);

    Livewire::test('pages::tim.profil')
        ->set('personil_nama', 'Ahmad Dani')
        ->set('personil_no_hp', '081234567890')
        ->set('personil_status', 'aktif')
        ->call('simpanPersonil')
        ->assertHasNoErrors();

    $personil = Personil::where('tim_id', $tim->id)->where('nama', 'Ahmad Dani')->first();
    expect($personil)->not->toBeNull();
    expect($personil->no_hp)->toBe('081234567890');
    expect($personil->status)->toBe('aktif');
});

test('tim user can edit existing personil in their team', function () {
    $tim = Tim::create(['nama_tim' => 'Tim Beta', 'status' => 'active']);
    $user = User::factory()->create(['role' => 'tim', 'tim_id' => $tim->id]);
    $personil = Personil::create(['tim_id' => $tim->id, 'nama' => 'Nama Lama', 'status' => 'aktif']);

    $this->actingAs($user);

    Livewire::test('pages::tim.profil')
        ->call('bukaModalEditPersonil', $personil->id)
        ->set('personil_nama', 'Nama Diperbarui')
        ->set('personil_status', 'nonaktif')
        ->call('simpanPersonil')
        ->assertHasNoErrors();

    $personil->refresh();
    expect($personil->nama)->toBe('Nama Diperbarui');
    expect($personil->status)->toBe('nonaktif');
});

test('tim user can delete personil from their team', function () {
    $tim = Tim::create(['nama_tim' => 'Tim Gamma', 'status' => 'active']);
    $user = User::factory()->create(['role' => 'tim', 'tim_id' => $tim->id]);
    $personil = Personil::create(['tim_id' => $tim->id, 'nama' => 'Personil Hapus', 'status' => 'aktif']);

    $this->actingAs($user);

    Livewire::test('pages::tim.profil')
        ->set('hapusPersonilId', $personil->id)
        ->call('hapusPersonil')
        ->assertHasNoErrors();

    expect(Personil::find($personil->id))->toBeNull();
});

test('tim user can update their password', function () {
    $tim = Tim::create(['nama_tim' => 'Tim Delta', 'status' => 'active']);
    $user = User::factory()->create([
        'role' => 'tim',
        'tim_id' => $tim->id,
        'password' => Hash::make('inovindojaya'),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::tim.profil')
        ->set('password_baru', 'PasswordBaru123!')
        ->set('password_baru_confirmation', 'PasswordBaru123!')
        ->call('ubahPassword')
        ->assertHasNoErrors();

    $user->refresh();
    expect(Hash::check('PasswordBaru123!', $user->password))->toBeTrue();
});
