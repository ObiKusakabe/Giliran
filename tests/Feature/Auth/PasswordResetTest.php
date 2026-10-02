<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
});

test('reset password link screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'New-password123!',
            'password_confirmation' => 'New-password123!',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login', absolute: false));

        return true;
    });
});

test('reset password link can be requested using username', function () {
    Notification::fake();

    $user = User::factory()->create([
        'username' => 'tim_test_001',
        'email' => 'tim@example.com',
    ]);

    $response = $this->post(route('password.email'), ['email' => 'tim_test_001']);

    $response->assertSessionHas('status');
    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password displays clear error if account has no email', function () {
    $user = User::factory()->create([
        'username' => 'tim_no_email_002',
        'email' => null,
    ]);

    $response = $this->post(route('password.email'), ['email' => 'tim_no_email_002']);

    $response->assertSessionHasErrors('email');
});

test('password can be reset using 6 digit otp code', function () {
    $user = User::factory()->create([
        'email' => 'user_otp@example.com',
        'password' => bcrypt('Old-password123!'),
        'password_reset_otp' => '654321',
        'password_reset_otp_expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->post(route('password.update'), [
        'email' => 'user_otp@example.com',
        'otp' => '654321',
        'password' => 'Brand-new-password123!',
        'password_confirmation' => 'Brand-new-password123!',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('login'));

    $user->refresh();
    expect(Hash::check('Brand-new-password123!', $user->password))->toBeTrue();
    expect($user->password_reset_otp)->toBeNull();
});

test('password reset fails when otp code is incorrect', function () {
    $user = User::factory()->create([
        'email' => 'user_wrong_otp@example.com',
        'password_reset_otp' => '123456',
        'password_reset_otp_expires_at' => now()->addMinutes(10),
        'password_reset_otp_attempts' => 0,
    ]);

    $response = $this->post(route('password.update'), [
        'email' => 'user_wrong_otp@example.com',
        'otp' => '999999',
        'password' => 'New-password123!',
        'password_confirmation' => 'New-password123!',
    ]);

    $response->assertSessionHasErrors('otp');

    $user->refresh();
    expect($user->password_reset_otp_attempts)->toBe(1);
});

test('password reset fails when otp code is expired', function () {
    $user = User::factory()->create([
        'email' => 'user_expired_otp@example.com',
        'password_reset_otp' => '123456',
        'password_reset_otp_expires_at' => now()->subMinutes(1),
    ]);

    $response = $this->post(route('password.update'), [
        'email' => 'user_expired_otp@example.com',
        'otp' => '123456',
        'password' => 'New-password123!',
        'password_confirmation' => 'New-password123!',
    ]);

    $response->assertSessionHasErrors('otp');
});

test('password reset otp can be resent and respects cooldown', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'resend_otp@example.com',
        'password_reset_otp' => '111111',
        // Expired so cooldown is 0
        'password_reset_otp_expires_at' => now()->subMinutes(5),
    ]);

    // First resend succeeds
    $response = $this->post(route('password.resend-otp'), [
        'email' => 'resend_otp@example.com',
    ]);

    $response->assertSessionHas('status');

    $user->refresh();
    expect($user->password_reset_otp)->not->toBe('111111');
    expect($user->password_reset_otp)->toHaveLength(6);

    // Immediate second resend is blocked by 60s cooldown
    $responseSecond = $this->post(route('password.resend-otp'), [
        'email' => 'resend_otp@example.com',
    ]);

    $responseSecond->assertSessionHasErrors('otp');
});

test('reset password page pre-fills OTP from query parameter and renders paste button', function () {
    $user = User::factory()->create([
        'email' => 'prefill_otp@example.com',
    ]);

    $response = $this->get(route('password.reset', [
        'token' => 'otp',
        'email' => $user->email,
        'otp' => '998877',
    ]));

    $response->assertOk()
        ->assertSee("otp: '998877'", false)
        ->assertSee('Tempel');
});
