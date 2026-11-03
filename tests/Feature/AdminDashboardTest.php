<?php

use App\Models\Category;
use App\Models\Contact;
use App\Models\FeaturedProject;
use App\Models\Product;
use App\Models\SiteVisit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->actingAs(User::factory()->admin()->create()));

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

    SiteVisit::factory()->count(2)->create([
        'session_id' => 'session-a',
        'visited_date' => now()->toDateString(),
    ]);
    SiteVisit::factory()->create([
        'session_id' => 'session-b',
        'visited_date' => now()->subDays(2)->toDateString(),
    ]);

    $this->get(route('admin.dashboard.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.0.count', 1)
            ->where('stats.1.count', 1)
            ->where('stats.2.count', 1)
            ->where('stats.3.count', 3)
            ->where('stats.4.count', 3)
            ->where('stats.4.description', 'Hôm nay: 2 lượt')
            ->where('openRequestsCount', 3)
            ->where('requestStatuses.pending', 2)
            ->where('requestStatuses.processing', 1)
            ->where('requestStatuses.resolved', 1)
            ->where('recentRequests.2.count', 1)
            ->where('recentRequests.6.count', 2)
            ->where('recentVisits.4.count', 1)
            ->where('recentVisits.6.count', 2)
            ->where('visitOverview.total', 3)
            ->where('visitOverview.today', 2)
            ->where('visitOverview.lastSevenDays', 3)
            ->where('visitOverview.uniqueSessions', 2),
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
        ['label' => 'Lượt truy cập', 'count' => 0, 'description' => 'Hôm nay: 0 lượt'],
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

test('browsing multiple pages within the active window counts as one visit and returning later counts as a new visit', function () {
    $this->get(route('kbuser.home'))->assertOk();
    $this->get(route('kbuser.products'))->assertOk();
    $this->get(route('kbuser.categories'))->assertOk();
    $this->getJson(route('kbuser.chat.messages'))->assertOk();

    expect(SiteVisit::query()->count())->toBe(1);

    $this->travel(31)->minutes();

    $this->get(route('kbuser.home'))->assertOk();
    $this->get(route('kbuser.products'))->assertOk();

    expect(SiteVisit::query()->count())->toBe(2);
});

test('admin quotations page loads ariston products for pdf quotation builder', function () {
    $aristonCategory = Category::factory()->create(['name' => 'Ariston', 'parent_id' => null]);
    $subCategory = Category::factory()->create(['name' => 'Bình nước nóng gián tiếp', 'parent_id' => $aristonCategory->id]);
    $otherCategory = Category::factory()->create(['name' => 'K-FLEX', 'parent_id' => null]);

    Product::query()->create([
        'name' => 'SLIM3 20B VN',
        'category_id' => $aristonCategory->id,
        'subcategory_id' => $subCategory->id,
        'description' => '- Dung tích: 20L',
        'price' => 2386000,
        'quantity' => 10,
    ]);

    Product::query()->create([
        'name' => 'K-Flex ST',
        'category_id' => $otherCategory->id,
        'description' => 'Bảo ôn NBR',
        'price' => 100000,
        'quantity' => 5,
    ]);

    $this->get(route('admin.quotations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('quotations/Index')
            ->has('aristonProducts', 1)
            ->where('aristonProducts.0.name', 'SLIM3 20B VN'),
        );
});

test('admin visits page keeps only the 20 most recent visits and displays device details', function () {
    $now = CarbonImmutable::parse('2026-11-03 18:00:00');
    $this->travelTo($now);

    for ($i = 1; $i <= 23; $i++) {
        $visit = SiteVisit::factory()->create([
            'session_id' => "session-{$i}",
            'ip_address' => "192.168.1.{$i}",
            'path' => '/products',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
            'visited_date' => $now->toDateString(),
        ]);

        $visit->forceFill([
            'created_at' => $now->subMinutes(30 - $i),
            'updated_at' => $now->subMinutes(30 - $i),
        ])->save();
    }

    $iphoneVisit = SiteVisit::factory()->create([
        'session_id' => 'session-iphone',
        'ip_address' => '10.0.0.99',
        'path' => '/featured-projects',
        'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1',
        'visited_date' => $now->toDateString(),
    ]);
    $iphoneVisit->forceFill([
        'created_at' => $now,
        'updated_at' => $now,
    ])->save();

    $this->get(route('admin.visits.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('visits/Index')
            ->has('visits', 20)
            ->where('visitSummary.total', 20)
            ->where('visitSummary.maxLimit', 20)
            ->where('visitSummary.mobile', 1)
            ->where('visitSummary.desktop', 19)
            ->where('visits.0.id', $iphoneVisit->id)
            ->where('visits.0.device_type', 'mobile')
            ->where('visits.0.device_label', 'Điện thoại')
            ->where('visits.0.device_model', 'Apple iPhone')
            ->where('visits.0.os', 'iOS 17.4')
            ->where('visits.0.browser', 'Apple Safari')
            ->where('visits.0.page_label', 'Dự án tiêu biểu'),
        );

    expect(SiteVisit::query()->count())->toBe(20)
        ->and(SiteVisit::query()->whereIn('session_id', ['session-1', 'session-2', 'session-3', 'session-4'])->count())->toBe(0);
});

test('recording user visits automatically deletes older records beyond the 20 most recent visits', function () {
    $now = CarbonImmutable::parse('2026-11-03 18:00:00');
    $this->travelTo($now);

    for ($i = 1; $i <= 21; $i++) {
        $visit = SiteVisit::factory()->create([
            'session_id' => "old-session-{$i}",
            'ip_address' => "10.10.0.{$i}",
            'visited_date' => $now->toDateString(),
        ]);

        $visit->forceFill([
            'created_at' => $now->subHours(2)->addMinutes($i),
            'updated_at' => $now->subHours(2)->addMinutes($i),
        ])->save();
    }

    $this->get(route('kbuser.home'))->assertOk();

    expect(SiteVisit::query()->count())->toBe(20)
        ->and(SiteVisit::query()->where('session_id', 'old-session-1')->exists())->toBeFalse()
        ->and(SiteVisit::query()->where('session_id', 'old-session-2')->exists())->toBeFalse();
});
