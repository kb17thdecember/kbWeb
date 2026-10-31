<?php

use App\Mail\AdminLoginOtp;
use App\Mail\ContactInquiryReceived;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Support\Facades\Mail;

function adminWithPassword(): User
{
    return User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'password' => 'secret-pass-1',
    ]);
}

function requestOtpCode(): string
{
    $code = null;
    Mail::assertSent(AdminLoginOtp::class, function (AdminLoginOtp $mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    return $code;
}

test('guests are redirected from admin pages to the admin login', function () {
    $this->get(route('admin.dashboard.index'))->assertRedirect(route('admin.login'));
    $this->get(route('admin.login'))->assertOk();
});

test('non admin users cannot access the admin area', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard.index'))
        ->assertRedirect(route('admin.login'));
});

test('the seeded admin can sign in with the password and the emailed code', function () {
    Mail::fake();
    $this->seed(AdminUserSeeder::class);

    $this->post(route('admin.login.store'), [
        'email' => 'kbnguyen0311@gmail.com',
        'password' => 'kbnguyen0311@',
    ])->assertRedirect(route('admin.login.otp'));

    $this->assertGuest();
    Mail::assertSent(AdminLoginOtp::class, fn ($mail) => $mail->hasTo('kbnguyen0311@gmail.com'));

    $this->post(route('admin.login.otp.verify'), ['code' => requestOtpCode()])
        ->assertRedirect(route('admin.dashboard.index'));

    $this->assertAuthenticated();
});

test('a wrong password does not send a code', function () {
    Mail::fake();
    adminWithPassword();

    $this->post(route('admin.login.store'), [
        'email' => 'admin@example.com',
        'password' => 'wrong',
    ])->assertSessionHasErrors('email');

    Mail::assertNothingSent();
    $this->assertGuest();
});

test('a wrong code does not sign in and too many wrong codes invalidate it', function () {
    Mail::fake();
    adminWithPassword();

    $this->post(route('admin.login.store'), ['email' => 'admin@example.com', 'password' => 'secret-pass-1']);
    $code = requestOtpCode();
    $wrong = $code === '000000' ? '111111' : '000000';

    foreach (range(1, 5) as $attempt) {
        $this->post(route('admin.login.otp.verify'), ['code' => $wrong])->assertSessionHasErrors('code');
    }

    $this->post(route('admin.login.otp.verify'), ['code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest();
});

test('an expired code is rejected', function () {
    Mail::fake();
    adminWithPassword();

    $this->post(route('admin.login.store'), ['email' => 'admin@example.com', 'password' => 'secret-pass-1']);
    $code = requestOtpCode();

    $this->travel(6)->minutes();

    $this->post(route('admin.login.otp.verify'), ['code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest();
});

test('admins can sign out', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.logout'))
        ->assertRedirect(route('admin.login'));

    $this->assertGuest();
});

test('a quote request from the cart stores the requested products on the contact', function () {
    Mail::fake();
    $category = Category::factory()->create();
    $pump = Product::query()->create(['name' => 'Máy bơm', 'category_id' => $category->id, 'description' => 'x', 'price' => 1000, 'quantity' => 0]);
    $pipe = Product::query()->create(['name' => 'Ống đồng', 'category_id' => $category->id, 'description' => 'x', 'price' => 500, 'quantity' => 0]);

    $this->post(route('kbuser.contacts.store'), [
        'name' => 'Khách hàng',
        'phone' => '0900000000',
        'email' => 'khach@example.com',
        'items' => [
            ['id' => $pump->id, 'quantity' => 2],
            ['id' => $pipe->id, 'quantity' => 5],
        ],
    ])->assertSessionHasNoErrors();

    $contact = Contact::query()->sole();
    expect($contact->product)->toBe("Máy bơm × 2\nỐng đồng × 5");
    expect($contact->message)->not->toBeEmpty();
    Mail::assertSent(ContactInquiryReceived::class);
});

test('a contact without cart items still requires a message and rejects unknown products', function () {
    Mail::fake();
    $payload = ['name' => 'Khách', 'phone' => '0900000000', 'email' => 'khach@example.com'];

    $this->post(route('kbuser.contacts.store'), $payload)->assertSessionHasErrors('message');
    $this->post(route('kbuser.contacts.store'), [...$payload, 'items' => [['id' => 9999, 'quantity' => 1]]])
        ->assertSessionHasErrors('items.0.id');

    expect(Contact::query()->count())->toBe(0);
});

test('a quote request is emailed to the owner address', function () {
    Mail::fake();

    $this->post(route('kbuser.contacts.store'), [
        'name' => 'Khách',
        'phone' => '0900000000',
        'email' => 'khach@example.com',
        'message' => 'Cần báo giá.',
    ]);

    Mail::assertSent(ContactInquiryReceived::class, fn ($mail) => $mail->hasTo('kbnguyen0311@gmail.com'));
});
