<?php

use App\Models\Contact;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->actingAs(User::factory()->admin()->create()));

test('admin consultations page lists quote requests newest first', function () {
    $olderContact = Contact::query()->create([
        'name' => 'Khách hàng cũ',
        'email' => 'old@example.com',
        'phone' => '0900000001',
        'message' => 'Cần tư vấn vật tư.',
        'status' => 'pending',
    ]);
    $newerContact = Contact::query()->create([
        'name' => 'Khách hàng mới',
        'email' => 'new@example.com',
        'phone' => '0900000002',
        'message' => 'Cần báo giá dự án.',
        'status' => 'processing',
        'project_name' => 'Tòa nhà văn phòng',
        'category_id' => 'Vật tư HVAC',
    ]);
    $olderContact->forceFill(['created_at' => now()->subDay()])->save();
    $newerContact->forceFill(['created_at' => now()])->save();

    $this->get(route('admin.consultations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/Index')
            ->has('contacts', 2)
            ->where('contacts.0.id', $newerContact->id)
            ->where('contacts.0.project_name', 'Tòa nhà văn phòng')
            ->where('contacts.1.id', $olderContact->id)
            ->where('openRequestsCount', 2)
            ->where('stats.3.count', 2),
        );
});

test('admin can update a consultation request status', function () {
    $contact = Contact::query()->create([
        'name' => 'Nguyễn Văn An',
        'email' => 'an@example.com',
        'phone' => '0901234567',
        'message' => 'Cần tư vấn thiết bị.',
        'status' => 'pending',
    ]);

    $this->patch(route('admin.consultations.update-status', $contact), [
        'status' => 'processing',
    ])->assertRedirect(route('admin.consultations.index'));

    $this->assertDatabaseHas('contacts', [
        'id' => $contact->id,
        'status' => 'processing',
    ]);
});

test('admin cannot set an unsupported consultation status', function () {
    $contact = Contact::query()->create([
        'name' => 'Nguyễn Văn An',
        'email' => 'an@example.com',
        'phone' => '0901234567',
        'message' => 'Cần tư vấn thiết bị.',
        'status' => 'pending',
    ]);

    $this->from(route('admin.consultations.index'))
        ->patch(route('admin.consultations.update-status', $contact), [
            'status' => 'not-a-status',
        ])
        ->assertRedirect(route('admin.consultations.index'))
        ->assertSessionHasErrors('status');

    $this->assertDatabaseHas('contacts', [
        'id' => $contact->id,
        'status' => 'pending',
    ]);
});

test('admin can delete a consultation request', function () {
    $contact = Contact::query()->create([
        'name' => 'Nguyễn Văn An',
        'email' => 'an@example.com',
        'phone' => '0901234567',
        'message' => 'Cần tư vấn thiết bị.',
        'status' => 'pending',
    ]);

    $this->delete(route('admin.consultations.destroy', $contact))
        ->assertRedirect(route('admin.consultations.index'));

    $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
});

test('admin can open quotation builder prefilled from a consultation and send quotation pdf response to customer', function () {
    Storage::fake('public');

    $customer = User::factory()->create([
        'email' => 'customer-quote@example.com',
    ]);

    $contact = Contact::query()->create([
        'user_id' => $customer->id,
        'name' => 'Công ty Cổ phần Kỹ thuật Minh Tâm',
        'email' => $customer->email,
        'phone' => '0987654321',
        'project_name' => 'Chung cư Sunrise City',
        'category_id' => 'Ariston',
        'product' => 'SLIM3 20 LUX × 15',
        'message' => 'Cần báo giá gấp trong ngày.',
        'status' => 'pending',
    ]);

    $this->get(route('admin.quotations.index', ['contact_id' => $contact->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('quotations/Index')
            ->where('initialContact.id', $contact->id)
            ->where('initialContact.name', 'Công ty Cổ phần Kỹ thuật Minh Tâm')
            ->where('initialContact.project_name', 'Chung cư Sunrise City'),
        );

    $pdfFile = UploadedFile::fake()->create('Bao-Gia-Sunrise-City.pdf', 240, 'application/pdf');

    $this->from(route('admin.consultations.index'))
        ->post(route('admin.consultations.quotation', $contact), [
            'quotation_file' => $pdfFile,
            'admin_note' => 'Gửi quý khách bảng báo giá chiết khấu dự án Sunrise City.',
            'status' => 'resolved',
        ])
        ->assertRedirect(route('admin.consultations.index'));

    $contact->refresh();
    expect($contact->status)->toBe('resolved')
        ->and($contact->quotation_name)->toBe('Bao-Gia-Sunrise-City.pdf')
        ->and($contact->quotation_path)->not->toBeNull()
        ->and($contact->quotation_url)->toContain('/storage/quotations/responses/')
        ->and($contact->admin_note)->toBe('Gửi quý khách bảng báo giá chiết khấu dự án Sunrise City.')
        ->and($contact->quoted_at)->not->toBeNull();

    Storage::disk('public')->assertExists($contact->quotation_path);

    $this->actingAs($customer)
        ->getJson(route('kbuser.account.overview'))
        ->assertOk()
        ->assertJsonPath('quotes.0.id', $contact->id)
        ->assertJsonPath('quotes.0.quotation_name', 'Bao-Gia-Sunrise-City.pdf')
        ->assertJsonPath('quotes.0.admin_note', 'Gửi quý khách bảng báo giá chiết khấu dự án Sunrise City.');
});
