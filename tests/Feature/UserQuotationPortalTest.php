<?php

use App\Models\User;
use Database\Seeders\PublicQuoteUserSeeder;
use Modules\KbUser\Http\Controllers\QuotationController;

test('visitors and regular authenticated users are redirected to quotation login when visiting the quotation builder', function () {
    $this->get(route('kbuser.quotations.index'))
        ->assertRedirect(route('kbuser.quotations.login'));

    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('kbuser.quotations.index'))
        ->assertRedirect(route('kbuser.quotations.login'));

    $this->get(route('kbuser.quotations.login'))
        ->assertOk();
});

test('the seeded public quote account can log in, view the quotation builder, and log out', function () {
    $this->seed(PublicQuoteUserSeeder::class);

    $this->post(route('kbuser.quotations.login.store'), [
        'email' => 'publicquote@gmail.com',
        'password' => 'stlqoute',
    ])
        ->assertRedirect(route('kbuser.quotations.index'))
        ->assertSessionHas(QuotationController::SESSION_KEY);

    $this->get(route('kbuser.quotations.index'))
        ->assertOk();

    $this->post(route('kbuser.quotations.logout'))
        ->assertRedirect(route('kbuser.quotations.login'))
        ->assertSessionMissing(QuotationController::SESSION_KEY);
});

test('invalid credentials or non-authorized accounts cannot log in to the quotation builder', function () {
    $this->seed(PublicQuoteUserSeeder::class);
    $otherUser = User::factory()->create([
        'email' => 'customer@example.com',
        'password' => 'stlqoute',
    ]);

    $this->post(route('kbuser.quotations.login.store'), [
        'email' => 'publicquote@gmail.com',
        'password' => 'wrong-password',
    ])
        ->assertSessionHasErrors('email')
        ->assertSessionMissing(QuotationController::SESSION_KEY);

    $this->post(route('kbuser.quotations.login.store'), [
        'email' => $otherUser->email,
        'password' => 'stlqoute',
    ])
        ->assertSessionHasErrors('email')
        ->assertSessionMissing(QuotationController::SESSION_KEY);
});
