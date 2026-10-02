<?php

use App\Models\Personil;
use App\Models\Tim;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

test('tim user can access jadwal-tim page and component renders without error', function () {
    $tim = Tim::create(['nama_tim' => 'Tim Jadwal Test', 'status' => 'active']);
    $user = User::factory()->create([
        'role' => 'tim',
        'tim_id' => $tim->id,
    ]);

    $this->actingAs($user)
        ->get(route('tim.jadwal'))
        ->assertOk();

    Livewire::actingAs($user)
        ->test('pages::tim.jadwal-tim')
        ->assertSee('Jadwal Tim')
        ->assertSee('Tidak ada tugas mendatang');
});

test('tim user can access akun page with foto preview and lightbox modal', function () {
    $tim = Tim::create(['nama_tim' => 'Tim Akun Test', 'status' => 'active']);
    $user = User::factory()->create([
        'name' => 'Tim Akun Test',
        'role' => 'tim',
        'tim_id' => $tim->id,
    ]);

    $this->actingAs($user)
        ->get(route('tim.akun'))
        ->assertOk()
        ->assertSee('Foto Bersama Tim')
        ->assertSee('Pratinjau Foto Tim')
        ->assertSee('Informasi');
});

test('tim user can upload foto bersama and it synchronizes user name', function () {
    Storage::fake('public');

    $tim = Tim::create(['nama_tim' => 'Tim Lama', 'status' => 'active']);
    $user = User::factory()->create([
        'name' => 'Tim Lama',
        'role' => 'tim',
        'tim_id' => $tim->id,
    ]);

    $file = UploadedFile::fake()->image('tim_photo.jpg', 800, 600);

    $this->actingAs($user);

    Livewire::test('pages::tim.akun')
        ->set('nama_tim', 'Tim Baru Modern')
        ->set('keterangan', 'Divisi Web Developer')
        ->set('foto_bersama', $file)
        ->call('updateProfilTim')
        ->assertHasNoErrors()
        ->assertDispatched('photo-saved');

    $tim->refresh();
    $user->refresh();

    expect($tim->nama_tim)->toBe('Tim Baru Modern');
    expect($tim->keterangan)->toBe('Divisi Web Developer');
    expect($tim->foto_bersama)->not->toBeNull();
    Storage::disk('public')->assertExists($tim->foto_bersama);

    // Verify User model name is synchronized
    expect($user->name)->toBe('Tim Baru Modern');
});

test('tim user can delete foto bersama', function () {
    Storage::fake('public');

    $path = UploadedFile::fake()->image('saved_photo.jpg')->store('tim-photos', 'public');

    $tim = Tim::create([
        'nama_tim' => 'Tim Hapus Foto',
        'status' => 'active',
        'foto_bersama' => $path,
    ]);
    $user = User::factory()->create([
        'role' => 'tim',
        'tim_id' => $tim->id,
    ]);

    Storage::disk('public')->assertExists($path);

    $this->actingAs($user);

    Livewire::test('pages::tim.akun')
        ->call('hapusFotoBersama')
        ->assertHasNoErrors()
        ->assertDispatched('photo-deleted');

    $tim->refresh();
    expect($tim->foto_bersama)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('foto bersama upload validates max size', function () {
    Storage::fake('public');

    $tim = Tim::create(['nama_tim' => 'Tim Validasi', 'status' => 'active']);
    $user = User::factory()->create(['role' => 'tim', 'tim_id' => $tim->id]);

    $this->actingAs($user);

    // Test oversize file (> 5MB = 5120KB)
    $largeFile = UploadedFile::fake()->image('huge.jpg')->size(6000);

    Livewire::test('pages::tim.akun')
        ->set('foto_bersama', $largeFile)
        ->call('updateProfilTim')
        ->assertHasErrors(['foto_bersama']);
});

test('tim user can update email in akun page', function () {
    $tim = Tim::create(['nama_tim' => 'Tim Email Test', 'status' => 'active']);
    $user = User::factory()->create([
        'role' => 'tim',
        'tim_id' => $tim->id,
        'email' => null,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::tim.akun')
        ->set('email', 'tim-baru@instansi.sch.id')
        ->call('updateEmail')
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->email)->toBe('tim-baru@instansi.sch.id');
});
