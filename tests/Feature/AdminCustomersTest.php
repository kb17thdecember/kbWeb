<?php

use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->actingAs(User::factory()->admin()->create()));

test('admin customers page lists non-admin customers newest first with activity counts', function () {
    $olderCustomer = User::factory()->create([
        'name' => 'Khách hàng Cũ',
        'email' => 'older@example.com',
        'phone' => '0901111111',
    ]);
    $olderCustomer->forceFill(['created_at' => now()->subDay()])->save();

    $newerCustomer = User::factory()->create([
        'name' => 'Khách hàng Mới',
        'email' => 'newer@example.com',
        'phone' => '0902222222',
        'google_id' => 'google-123',
    ]);
    $newerCustomer->forceFill(['created_at' => now()])->save();

    Contact::query()->create([
        'user_id' => $newerCustomer->id,
        'name' => $newerCustomer->name,
        'email' => $newerCustomer->email,
        'phone' => '0902222222',
        'project_name' => 'Tòa nhà A',
        'message' => 'Báo giá vật tư',
        'status' => 'pending',
    ]);

    $this->get(route('admin.customers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/Index')
            ->has('customers', 2)
            ->where('customers.0.id', $newerCustomer->id)
            ->where('customers.0.login_type', 'google')
            ->where('customers.0.contacts_count', 1)
            ->where('customers.0.recent_contacts.0.project_name', 'Tòa nhà A')
            ->where('customers.1.id', $olderCustomer->id)
            ->where('customers.1.login_type', 'account'),
        );
});

test('admin can create a new customer account', function () {
    $this->post(route('admin.customers.store'), [
        'name' => 'Trần Thị B',
        'email' => 'tranthib@example.com',
        'phone' => '0912345678',
        'password' => 'password123',
    ])
        ->assertRedirect(route('admin.customers.index'))
        ->assertSessionHas('toast', 'Đã thêm khách hàng mới thành công.');

    $customer = User::query()->where('email', 'tranthib@example.com')->firstOrFail();
    expect($customer->name)->toBe('Trần Thị B')
        ->and($customer->phone)->toBe('0912345678')
        ->and($customer->is_admin)->toBeFalse()
        ->and(Hash::check('password123', $customer->password))->toBeTrue();
});

test('admin can update a customer account and optionally change password', function () {
    $customer = User::factory()->create([
        'name' => 'Lê Văn C',
        'email' => 'levanc@example.com',
        'phone' => '0900000001',
    ]);

    $this->put(route('admin.customers.update', $customer), [
        'name' => 'Lê Văn C (Đã sửa)',
        'email' => 'levanc-new@example.com',
        'phone' => '0988888888',
        'password' => 'newpassword123',
    ])
        ->assertRedirect(route('admin.customers.index'))
        ->assertSessionHas('toast', 'Đã cập nhật thông tin khách hàng thành công.');

    $customer->refresh();
    expect($customer->name)->toBe('Lê Văn C (Đã sửa)')
        ->and($customer->email)->toBe('levanc-new@example.com')
        ->and($customer->phone)->toBe('0988888888')
        ->and(Hash::check('newpassword123', $customer->password))->toBeTrue();
});

test('admin can delete a customer account and cannot modify another admin via customer routes', function () {
    $customer = User::factory()->create();
    $otherAdmin = User::factory()->admin()->create();

    $this->delete(route('admin.customers.destroy', $customer))
        ->assertRedirect(route('admin.customers.index'))
        ->assertSessionHas('toast', 'Đã xóa tài khoản khách hàng thành công.');

    $this->assertDatabaseMissing('users', ['id' => $customer->id]);

    $this->delete(route('admin.customers.destroy', $otherAdmin))
        ->assertNotFound();

    $this->assertDatabaseHas('users', ['id' => $otherAdmin->id]);
});
