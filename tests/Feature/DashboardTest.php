<?php

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
