<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Contact;
use App\Models\Product;
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
        ->assertJsonPath('totalCount', 2)
        ->assertJsonCount(2, 'items');

    $this->postJson(route('admin.notifications.read-chats'))
        ->assertOk()
        ->assertJsonPath('chatUnreadCount', 0)
        ->assertJsonPath('totalCount', 1);
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
        'site_logo' => '/storage/settings/custom-logo.png',
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
            'site_logo' => '/storage/settings/custom-logo.png',
            'company_name' => 'HVAC Mart Việt Nam',
            'hotline' => '0988.999.888',
            'contact_email' => 'contact@hvacmart.vn',
            'address' => 'Quận Cầu Giấy, Hà Nội',
        ]);

    $this->get(route('kbuser.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('siteSettings.site_logo', '/storage/settings/custom-logo.png')
            ->where('siteSettings.company_name', 'HVAC Mart Việt Nam')
            ->where('siteSettings.hotline', '0988.999.888')
            ->where('siteSettings.contact_email', 'contact@hvacmart.vn'),
        );
});

test('products categories and articles automatically generate seo metadata and can be managed from admin seo', function () {
    $parentCategory = Category::query()->create([
        'name' => 'Ariston',
        'description' => 'Thiết bị nước nóng thương hiệu Ý',
    ]);

    $childCategory = Category::query()->create([
        'name' => 'Bình nước nóng gián tiếp',
        'parent_id' => $parentCategory->id,
        'description' => 'Dòng bình nước nóng gián tiếp tiết kiệm điện',
    ]);

    $product = Product::query()->create([
        'name' => 'Ariston AN2 30 RS',
        'product_title' => 'Bình nước nóng gián tiếp 30L tráng men Titan',
        'category_id' => $parentCategory->id,
        'subcategory_id' => $childCategory->id,
        'description' => 'Dung tích 30 lít, thanh đốt đồng, vi mạch kiểm soát an toàn.',
        'price' => 3250000,
        'quantity' => 10,
    ]);

    $article = Article::query()->create([
        'title' => 'Hướng dẫn chọn độ dày bảo ôn K-Flex cho ống đồng',
        'excerpt' => 'Cách tra cứu bảng độ dày ống bảo ôn K-Flex chống đọng sương hiệu quả.',
        'content' => '<p>Nội dung kỹ thuật chi tiết về hệ số dẫn nhiệt và độ dày bảo ôn.</p>',
        'is_published' => true,
        'published_at' => now(),
    ]);

    expect($parentCategory->seo_title)->toContain('Ariston')
        ->and($parentCategory->seo_description)->not->toBeEmpty()
        ->and($parentCategory->seo_keywords)->toContain('Ariston')
        ->and($childCategory->seo_title)->toContain('Bình nước nóng gián tiếp')
        ->and($product->seo_title)->toContain('Ariston AN2 30 RS')
        ->and($product->seo_description)->not->toBeEmpty()
        ->and($product->seo_keywords)->toContain('Ariston AN2 30 RS')
        ->and($article->seo_title)->toContain('Hướng dẫn chọn độ dày bảo ôn K-Flex')
        ->and($article->seo_description)->not->toBeEmpty()
        ->and($article->seo_keywords)->not->toBeEmpty();

    $this->get(route('admin.seo.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('seo/Index')
            ->has('seoEntities.products', 1)
            ->has('seoEntities.categories', 2)
            ->has('seoEntities.articles', 1)
            ->where('seoAudit.productsWithSeoCount', 1)
            ->where('seoAudit.categoriesWithSeoCount', 2)
            ->where('seoAudit.articlesWithSeoCount', 1),
        );

    $this->put(route('admin.seo.entities.update', ['type' => 'product', 'id' => $product->id]), [
        'seo_title' => 'Mua Bình Nước Nóng Ariston AN2 30 RS Chính Hãng | HVAC Mart',
        'seo_description' => 'Báo giá bình nước nóng Ariston AN2 30 RS 30L chính hãng chiết khấu cao cho dự án.',
        'seo_keywords' => 'Ariston AN2 30 RS, bình nóng lạnh 30L, báo giá Ariston',
    ])
        ->assertRedirect(route('admin.seo.index'));

    expect($product->fresh()->seo_title)->toBe('Mua Bình Nước Nóng Ariston AN2 30 RS Chính Hãng | HVAC Mart');

    $this->get('/products/'.$product->slug)
        ->assertOk()
        ->assertSee('Mua Bình Nước Nóng Ariston AN2 30 RS Chính Hãng | HVAC Mart', false)
        ->assertSee('Báo giá bình nước nóng Ariston AN2 30 RS 30L chính hãng chiết khấu cao cho dự án.', false);

    $this->get('/categories/'.$parentCategory->slug)
        ->assertOk()
        ->assertSee($parentCategory->seo_title, false);

    $this->get('/categories/'.$parentCategory->slug.'/'.$childCategory->slug)
        ->assertOk()
        ->assertSee($childCategory->seo_title, false);

    $this->get('/news/'.$article->slug)
        ->assertOk()
        ->assertSee($article->seo_title, false);

    $this->post(route('admin.seo.generate-all'))
        ->assertRedirect(route('admin.seo.index'));

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private')
        ->assertSee('/products/'.$product->slug, false)
        ->assertSee('/news/'.$article->slug, false);

    $this->get('/feed.xml')
        ->assertOk()
        ->assertSee('/products/'.$product->slug, false)
        ->assertSee('/news/'.$article->slug, false)
        ->assertSee('/categories/'.$parentCategory->slug, false);

    $this->post(route('admin.seo.ping'))
        ->assertRedirect(route('admin.seo.index'));
});

test('admin global search endpoint returns matching products projects consultations and customers', function () {
    $category = Category::query()->create([
        'name' => 'Ariston Heat Pump',
        'description' => 'Máy bơm nhiệt Ariston',
    ]);

    Product::query()->create([
        'name' => 'Bơm nhiệt Ariston NIMBUS 80',
        'product_title' => 'Máy bơm nhiệt nước nóng trung tâm',
        'category_id' => $category->id,
        'subcategory_id' => null,
        'description' => 'Công suất lớn cho khách sạn',
        'price' => 45000000,
        'quantity' => 5,
    ]);

    ProjectReport::query()->create([
        'project_name' => 'Khu đô thị NIMBUS Riverside',
        'project_code' => 'NTK-NIMBUS',
        'project_area' => 'Hà Nội',
        'project_status' => '2',
        'project_update' => 'Đang đàm phán hợp đồng',
    ]);

    Contact::query()->create([
        'name' => 'Ban Quản Lý NIMBUS',
        'email' => 'nimbus@example.com',
        'phone' => '0933445566',
        'project_name' => 'NIMBUS Tower',
        'message' => 'Báo giá hệ thống nước nóng trung tâm.',
        'status' => 'pending',
    ]);

    $this->getJson(route('admin.search', ['q' => 'NIMBUS']))
        ->assertOk()
        ->assertJsonPath('query', 'NIMBUS')
        ->assertJsonPath('total', 3)
        ->assertJsonCount(3, 'items');
});
