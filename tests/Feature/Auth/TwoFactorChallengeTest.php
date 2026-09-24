<?php

use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
});

test('two factor challenge redirects to login when not authenticated', function () {
    $response = $this->get(route('two-factor.login'));

    $response->assertRedirect(route('login'));
});

test('two factor challenge can be rendered', function () {
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('two-factor.login'));
});

test('two factor authentication sets remember cookie when remember was checked on login', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'remember' => 'on',
    ])->assertRedirect(route('two-factor.login'));

    $recoveryCode = json_decode(decrypt($user->two_factor_recovery_codes), true)[0];

    $response = $this->post(route('two-factor.login.store'), [
        'recovery_code' => $recoveryCode,
    ]);

    $response->assertRedirect();
    $this->assertAuthenticated();
    $response->assertCookie(Auth::guard()->getRecallerName());
});

test('user is automatically logged in from remember cookie without entering credentials or 2fa again', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'remember' => 'on',
    ]);

    $recoveryCode = json_decode(decrypt($user->two_factor_recovery_codes), true)[0];

    $response = $this->post(route('two-factor.login.store'), [
        'recovery_code' => $recoveryCode,
    ]);

    $recallerCookie = $response->getCookie(Auth::guard()->getRecallerName());

    // Flush session and clear current auth to simulate closing browser
    $this->flushSession();
    auth()->forgetGuards();

    $newResponse = $this->withCookie($recallerCookie->getName(), $recallerCookie->getValue())
        ->get('/');

    $newResponse->assertRedirect();
    $this->assertAuthenticatedAs($user);
});
