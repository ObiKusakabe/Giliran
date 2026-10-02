<?php

use App\Models\Tim;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated users are redirected to their role dashboard', function () {
    // Sistem kita redirect /dashboard ke HomeController yang redirect sesuai role
    $user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($user);

    // /dashboard redirect ke HomeController → /admin/dashboard
    $response = $this->get(route('dashboard'));
    $response->assertRedirect('/admin/dashboard');
});

test('admin sees dev mode floating button on admin dashboard when debug is enabled', function () {
    config(['app.debug' => true]);

    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertStatus(200)
        ->assertSee('devModeFloating()', false)
        ->assertSee('Dev Mode Time Editor');
});

test('admin does not see dev mode floating button on other admin pages', function () {
    config(['app.debug' => true]);

    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get(route('admin.periode-wfo'));

    $response->assertStatus(200)
        ->assertDontSee('devModeFloating()', false)
        ->assertDontSee('Dev Mode Time Editor');
});

test('tim user does not see dev mode floating button on tim pages', function () {
    config(['app.debug' => true]);

    $tim = Tim::create(['nama_tim' => 'Tim Dev Check', 'status' => 'active']);
    $user = User::factory()->create(['role' => 'tim', 'tim_id' => $tim->id]);

    $response = $this->actingAs($user)->get(route('tim.profil'));

    $response->assertStatus(200)
        ->assertDontSee('devModeFloating()', false)
        ->assertDontSee('Dev Mode Time Editor');
});

test('dev mode floating button is hidden when debug is disabled in non-local environment', function () {
    config(['app.debug' => false]);

    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertStatus(200)
        ->assertDontSee('devModeFloating()', false)
        ->assertDontSee('Dev Mode Time Editor');
});
