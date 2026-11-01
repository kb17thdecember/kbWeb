<?php

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Contact;
use App\Models\ProjectReport;
use App\Models\SiteSetting;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->actingAs(User::factory()->admin()->create()));

test('admin notifications endpoint and shared props return unhandled counts and items', function () {
    Contact::query()->create([
        'name' => 'Công ty Cơ Điện Minh Phát',
        'email' => 'minhphat@example.com',
        'phone' => '0912345678',
        'project_name' => 'Khách sạn 5 sao Hạ Long',
        'message' => 'Cần báo giá hệ thống Heat Pump.',
        'status' => 'pending',
    ]);

    $conversation = ChatConversation::query()->create([
        'token' => 'test-token-notif',
        'visitor_name' => 'Trần Văn Nam',
        'status' => 'open',
        'last_message_at' => now(),
    ]);
    $conversation->messages()->create([
        'sender' => ChatMessage::SENDER_VISITOR,
        'body' => 'Cho mình hỏi giá bình nước nóng 300L.',
        'read_at' => null,
    ]);

    ProjectReport::query()->create([
        'project_name' => 'Tổ hợp thương mại Sài Gòn',
        'project_code' => 'NTK99',
        'project_area' => 'Hồ Chí Minh',
        'project_status' => '3',
        'project_update' => 'Cần hỗ trợ catalog kỹ thuật gấp',
    ]);

    $this->getJson(route('admin.notifications.index'))
        ->assertOk()
        ->assertJsonPath('openRequestsCount', 1)
        ->assertJsonPath('pendingRequestsCount', 1)
        ->assertJsonPath('chatUnreadCount', 1)
        ->assertJsonPath('supportProjectsCount', 1)
        ->assertJsonPath('totalCount', 3)
        ->assertJsonCount(3, 'items');

    $this->postJson(route('admin.notifications.read-chats'))
        ->assertOk()
        ->assertJsonPath('chatUnreadCount', 0)
        ->assertJsonPath('totalCount', 2);
});

test('admin can view and update site settings and changes are shared with user pages', function () {
    $this->get(route('admin.settings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Index')
            ->where('settings.company_name', 'HVAC Mart')
            ->where('settings.hotline', '0979.807.234'),
        );

    $payload = [
        ...SiteSetting::defaults(),
        'company_name' => 'HVAC Mart Việt Nam',
        'hotline' => '0988.999.888',
        'contact_email' => 'contact@hvacmart.vn',
        'address' => 'Quận Cầu Giấy, Hà Nội',
    ];

    $this->put(route('admin.settings.update'), $payload)
        ->assertRedirect(route('admin.settings.index'))
        ->assertSessionHas('toast', 'Đã cập nhật cài đặt hệ thống thành công.');

    expect(SiteSetting::allSettings())
        ->toMatchArray([
            'company_name' => 'HVAC Mart Việt Nam',
            'hotline' => '0988.999.888',
            'contact_email' => 'contact@hvacmart.vn',
            'address' => 'Quận Cầu Giấy, Hà Nội',
        ]);

    $this->get(route('kbuser.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('siteSettings.company_name', 'HVAC Mart Việt Nam')
            ->where('siteSettings.hotline', '0988.999.888')
            ->where('siteSettings.contact_email', 'contact@hvacmart.vn'),
        );
});
