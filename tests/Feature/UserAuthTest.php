<?php

use App\Mail\UserPasswordResetOtp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

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
    ])->assertRedirect(route('kbuser.home'))
        ->assertSessionHas('kbuser_login_method', 'account');

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

    $callbackResponse->assertRedirect(route('kbuser.home'))
        ->assertSessionHas('kbuser_login_method', 'google');
    $this->assertAuthenticated();

    $this->assertDatabaseHas('users', [
        'email' => 'googleuser@example.com',
        'name' => 'Google Partner',
        'google_id' => 'google-uid-999',
        'avatar' => 'https://example.com/avatar.png',
    ]);
});

test('a user can request a password reset code via email and reset their password', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'resetme@example.com',
        'password' => 'old-password-123',
    ]);

    $this->post(route('kbuser.password.email'), [
        'email' => 'resetme@example.com',
    ])->assertSessionHasNoErrors();

    $sentCode = null;
    Mail::assertSent(UserPasswordResetOtp::class, function (UserPasswordResetOtp $mail) use (&$sentCode) {
        $sentCode = $mail->code;

        return $mail->hasTo('resetme@example.com');
    });

    expect($sentCode)->toBeString()->toHaveLength(6);

    $this->post(route('kbuser.password.update'), [
        'email' => 'resetme@example.com',
        'code' => $sentCode,
        'password' => 'new-password-456',
        'password_confirmation' => 'new-password-456',
    ])->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($user);
    expect(Hash::check('new-password-456', $user->fresh()->password))->toBeTrue();
});

test('an authenticated user can update profile info and change password', function () {
    $user = User::factory()->create([
        'name' => 'Tên Cũ',
        'phone' => '0900000001',
        'password' => 'current-pass-123',
    ]);

    $this->actingAs($user)
        ->put(route('kbuser.account.profile.update'), [
            'name' => 'Nguyễn Tuấn Kiệt',
            'phone' => '0979807234',
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->name)->toBe('Nguyễn Tuấn Kiệt');
    expect($user->fresh()->phone)->toBe('0979807234');

    $this->actingAs($user)
        ->put(route('kbuser.account.password.update'), [
            'current_password' => 'current-pass-123',
            'password' => 'updated-pass-789',
            'password_confirmation' => 'updated-pass-789',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('updated-pass-789', $user->fresh()->password))->toBeTrue();
});
