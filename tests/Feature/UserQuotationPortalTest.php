<?php

use App\Models\Product;
use App\Models\User;
use Database\Seeders\ProductSeeder;
use Database\Seeders\PublicQuoteUserSeeder;
use Inertia\Testing\AssertableInertia as Assert;
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
    $this->seed([
        PublicQuoteUserSeeder::class,
        ProductSeeder::class,
    ]);

    $this->post(route('kbuser.quotations.login.store'), [
        'email' => 'publicquote@gmail.com',
        'password' => 'stlquote',
    ])
        ->assertRedirect(route('kbuser.quotations.index'))
        ->assertSessionHas(QuotationController::SESSION_KEY);

    $slim30Id = Product::query()->where('name', 'SLIM3 30B VN')->value('id');
    $proR40Id = Product::query()->where('name', 'PRO R S 40 SH 2.5 FE')->value('id');

    $this->get(route('kbuser.quotations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('quotations/Index')
            ->where('aristonProducts', fn ($products) => collect($products)->firstWhere('id', $slim30Id)['origin'] === 'Việt Nam'
                && collect($products)->firstWhere('id', $proR40Id)['origin'] === 'Trung Quốc'),
        );

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
