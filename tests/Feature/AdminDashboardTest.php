<?php

use App\Models\Category;
use App\Models\Contact;
use App\Models\FeaturedProject;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

test('admin dashboard displays database statistics, recent requests, and request statuses', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-20 10:00:00'));

    $category = Category::factory()->create();
    $subcategory = Category::factory()->create(['parent_id' => $category->id]);

    Product::query()->create([
        'name' => 'Máy bơm',
        'category_id' => $category->id,
        'subcategory_id' => $subcategory->id,
        'description' => 'Sản phẩm thử nghiệm.',
        'price' => 100000,
        'quantity' => 0,
    ]);

    FeaturedProject::query()->create([
        'name' => 'Dự án văn phòng',
        'description' => 'Dự án thử nghiệm.',
        'location' => 'Hà Nội',
        'category' => 'Văn phòng',
        'subcategory' => 'HVAC',
        'type' => '2',
        'image' => 'project.jpg',
        'images' => ['project.jpg'],
    ]);

    Contact::query()->create([
        'name' => 'Khách hàng mới',
        'email' => 'new@example.com',
        'phone' => '0900000001',
        'message' => 'Yêu cầu mới.',
        'status' => 'pending',
    ]);
    $processingRequest = Contact::query()->create([
        'name' => 'Khách hàng đang xử lý',
        'email' => 'processing@example.com',
        'phone' => '0900000002',
        'message' => 'Yêu cầu đang xử lý.',
        'status' => 'processing',
    ]);
    $processingRequest->forceFill([
        'created_at' => now()->subDays(4),
        'updated_at' => now()->subDays(4),
    ])->save();
    Contact::query()->create([
        'name' => 'Khách hàng đã xử lý',
        'email' => 'resolved@example.com',
        'phone' => '0900000003',
        'message' => 'Yêu cầu đã xử lý.',
        'status' => 'resolved',
    ]);
    $oldRequest = Contact::query()->create([
        'name' => 'Yêu cầu cũ',
        'email' => 'old@example.com',
        'phone' => '0900000004',
        'message' => 'Yêu cầu ngoài 7 ngày.',
        'status' => 'pending',
    ]);
    $oldRequest->forceFill([
        'created_at' => now()->subDays(8),
        'updated_at' => now()->subDays(8),
    ])->save();

    $this->get(route('admin.dashboard.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.0.count', 1)
            ->where('stats.1.count', 1)
            ->where('stats.2.count', 1)
            ->where('stats.3.count', 3)
            ->where('openRequestsCount', 3)
            ->where('requestStatuses.pending', 2)
            ->where('requestStatuses.processing', 1)
            ->where('requestStatuses.resolved', 1)
            ->where('recentRequests.2.count', 1)
            ->where('recentRequests.6.count', 2),
        );
});

test('all admin pages display the same current database statistics', function () {
    Category::factory()->create();
    Category::factory()->create(['parent_id' => null]);

    $expectedStats = [
        ['label' => 'Sản phẩm', 'count' => 0, 'description' => 'Tổng số mặt hàng'],
        ['label' => 'Dự án tiêu biểu', 'count' => 0, 'description' => 'Hồ sơ dự án'],
        ['label' => 'Danh mục', 'count' => 2, 'description' => 'Nhóm chính'],
        ['label' => 'Yêu cầu cần xử lý', 'count' => 0, 'description' => 'Chưa hoàn tất'],
    ];

    foreach ([
        route('admin.products.index'),
        route('admin.categories.index'),
        route('admin.case-studies.index'),
        route('admin.consultations.index'),
    ] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats', $expectedStats),
            );
    }
});
