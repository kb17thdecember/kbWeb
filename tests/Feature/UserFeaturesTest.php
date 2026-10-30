<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

test('product detail page renders with reviews and technical comments', function () {
    $category = Category::query()->create([
        'name' => 'Bình nước nóng Ariston',
        'description' => 'Thiết bị nước nóng',
    ]);

    $product = Product::query()->create([
        'name' => 'Ariston Slim2 30L',
        'product_title' => 'Bình nước nóng gián tiếp Ariston Slim2 30L',
        'category_id' => $category->id,
        'description' => 'Dung tích 30L, thanh đốt đồng bền bỉ.',
        'price' => 4250000,
        'quantity' => 20,
    ]);

    $this->get(route('kbuser.products.show', $product))
        ->assertOk()
        ->assertSee('Ariston Slim2 30L');
});

test('authenticated user can rate and review a product and post a technical question', function () {
    $user = User::factory()->create();
    $category = Category::query()->create(['name' => 'Bảo ôn K-FLEX']);
    $product = Product::query()->create([
        'name' => 'Ống bảo ôn K-FLEX ST 19mm',
        'product_title' => 'Bảo ôn K-FLEX ST dày 19mm',
        'category_id' => $category->id,
        'description' => 'Cách nhiệt chống đọng sương hệ VRV.',
        'price' => 185000,
        'quantity' => 100,
    ]);

    $this->actingAs($user)
        ->post(route('kbuser.products.reviews.store', $product), [
            'rating' => 5,
            'comment' => 'Chất lượng cao su lưu hóa rất tốt, thi công nhanh.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('product_reviews', [
        'product_id' => $product->id,
        'user_id' => $user->id,
        'rating' => 5,
        'comment' => 'Chất lượng cao su lưu hóa rất tốt, thi công nhanh.',
    ]);

    $this->actingAs($user)
        ->post(route('kbuser.products.comments.store', $product), [
            'body' => 'Sản phẩm có chứng chỉ chống cháy Class 0 đi kèm không?',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('comments', [
        'commentable_type' => Product::class,
        'commentable_id' => $product->id,
        'user_id' => $user->id,
        'body' => 'Sản phẩm có chứng chỉ chống cháy Class 0 đi kèm không?',
    ]);
});

test('authenticated user can comment on a published news article', function () {
    $user = User::factory()->create();
    $article = Article::factory()->create([
        'title' => 'Hướng dẫn chọn độ dày bảo ôn K-FLEX',
        'is_published' => true,
        'published_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('kbuser.news.comments.store', $article), [
            'body' => 'Bài viết kỹ thuật rất chi tiết cho đội ngũ MEP.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('comments', [
        'commentable_type' => Article::class,
        'commentable_id' => $article->id,
        'user_id' => $user->id,
        'body' => 'Bài viết kỹ thuật rất chi tiết cho đội ngũ MEP.',
    ]);
});

test('authenticated user can toggle wishlist and view account overview with quote history and attachment', function () {
    Mail::fake();
    Storage::fake('public');

    $user = User::factory()->create([
        'name' => 'Kỹ sư Trần Hải',
        'email' => 'hai.mep@example.com',
        'phone' => '0909123456',
    ]);

    $category = Category::query()->create(['name' => 'Vật tư HVAC']);
    $product = Product::query()->create([
        'name' => 'Tấm bảo ôn K-FLEX 25mm',
        'product_title' => 'Tấm K-FLEX ST 25mm',
        'category_id' => $category->id,
        'description' => 'Bọc ống gió HVAC.',
        'price' => 650000,
        'quantity' => 50,
    ]);

    // Toggle wishlist ON
    $this->actingAs($user)
        ->postJson(route('kbuser.wishlist.toggle', $product))
        ->assertOk()
        ->assertJson([
            'wishlisted' => true,
            'wishlistIds' => [$product->id],
        ]);

    // Submit quote request with BOQ PDF attachment
    $boqFile = UploadedFile::fake()->create('bang-khoi-luong-boq.pdf', 256, 'application/pdf');

    $this->actingAs($user)
        ->post(route('kbuser.contacts.store'), [
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'category_id' => 'Bảo ôn K-FLEX',
            'project_name' => 'Tòa nhà Văn phòng Green Tower',
            'message' => 'Xin báo giá theo file BOQ đính kèm.',
            'attachment' => $boqFile,
        ])
        ->assertRedirect(route('kbuser.contacts'));

    $this->assertDatabaseHas('contacts', [
        'user_id' => $user->id,
        'project_name' => 'Tòa nhà Văn phòng Green Tower',
        'attachment_name' => 'bang-khoi-luong-boq.pdf',
    ]);

    // Fetch account overview (Quote history + Wishlist products)
    $this->actingAs($user)
        ->getJson(route('kbuser.account.overview'))
        ->assertOk()
        ->assertJsonCount(1, 'quotes')
        ->assertJsonCount(1, 'wishlistProducts')
        ->assertJsonPath('quotes.0.project_name', 'Tòa nhà Văn phòng Green Tower')
        ->assertJsonPath('wishlistProducts.0.id', $product->id);
});
