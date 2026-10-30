<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

test('a new user can register with name, email, phone, and password', function () {
    $response = $this->post(route('kbuser.register.store'), [
        'name' => 'Nguyễn Văn Minh',
        'email' => 'minh@example.com',
        'phone' => '0987654321',
        'password' => 'secret-pass-123',
        'password_confirmation' => 'secret-pass-123',
    ]);

    $response->assertRedirect(route('kbuser.home'));
    $this->assertAuthenticated();

    $this->assertDatabaseHas('users', [
        'name' => 'Nguyễn Văn Minh',
        'email' => 'minh@example.com',
        'phone' => '0987654321',
    ]);
});

test('registration validates required fields and phone format', function () {
    $this->post(route('kbuser.register.store'), [
        'name' => '',
        'email' => 'not-an-email',
        'phone' => 'abc',
        'password' => 'short',
        'password_confirmation' => 'mismatch',
    ])->assertSessionHasErrors(['name', 'email', 'phone', 'password']);

    $this->assertGuest();
});

test('a user can sign in with email and password and sign out', function () {
    $user = User::factory()->create([
        'email' => 'khachhang@example.com',
        'phone' => '0912345678',
        'password' => 'user-pass-123',
    ]);

    $this->post(route('kbuser.login.store'), [
        'email' => 'khachhang@example.com',
        'password' => 'user-pass-123',
    ])->assertRedirect(route('kbuser.home'));

    $this->assertAuthenticatedAs($user);

    $this->post(route('kbuser.logout'))
        ->assertRedirect(route('kbuser.home'));

    $this->assertGuest();
});

test('a user cannot sign in with an invalid password', function () {
    User::factory()->create([
        'email' => 'khachhang@example.com',
        'password' => 'user-pass-123',
    ]);

    $this->post(route('kbuser.login.store'), [
        'email' => 'khachhang@example.com',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('google oauth redirects and creates or signs in the user on callback', function () {
    config()->set('services.google.client_id', 'test-google-client-id');
    config()->set('services.google.client_secret', 'test-google-secret');
    config()->set('services.google.redirect', 'http://localhost/auth/google/callback');

    $redirectResponse = $this->get(route('kbuser.auth.google.redirect'));
    $redirectResponse->assertRedirect();

    $state = session('kbuser_google_oauth_state');
    expect($state)->toBeString()->not->toBeEmpty();

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'fake-google-access-token',
        ], 200),
        'https://www.googleapis.com/oauth2/v3/userinfo' => Http::response([
            'sub' => 'google-uid-999',
            'email' => 'googleuser@example.com',
            'name' => 'Google Partner',
            'picture' => 'https://example.com/avatar.png',
        ], 200),
    ]);

    $callbackResponse = $this->get(route('kbuser.auth.google.callback', [
        'code' => 'fake-auth-code',
        'state' => $state,
    ]));

    $callbackResponse->assertRedirect(route('kbuser.home'));
    $this->assertAuthenticated();

    $this->assertDatabaseHas('users', [
        'email' => 'googleuser@example.com',
        'name' => 'Google Partner',
        'google_id' => 'google-uid-999',
        'avatar' => 'https://example.com/avatar.png',
    ]);
});
