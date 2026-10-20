<?php

use App\Models\Contact;
use Inertia\Testing\AssertableInertia as Assert;

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
