<?php

use App\Mail\ContactInquiryReceived;
use App\Models\Category;
use App\Models\FeaturedProject;
use App\Models\Product;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('the user products page lists products with their categories', function () {
    $category = Category::factory()->create(['name' => 'HVAC']);
    $subcategory = Category::factory()->create([
        'name' => 'Ống gió',
        'parent_id' => $category->id,
    ]);
    $product = Product::query()->create([
        'name' => 'Ống gió mềm bảo ôn',
        'product_title' => 'Ống gió cách nhiệt',
        'category_id' => $category->id,
        'subcategory_id' => $subcategory->id,
        'description' => 'Sản phẩm thử nghiệm',
        'price' => 100000,
        'quantity' => 5,
    ]);

    $this->get(route('kbuser.products'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/Index')
            ->has('categories', 1)
            ->has('products', 1)
            ->where('products.0.id', $product->id)
            ->where('products.0.product_title', 'Ống gió cách nhiệt')
            ->where('products.0.category.name', 'HVAC')
            ->where('products.0.subcategory.name', 'Ống gió'),
        );
});

test('the user featured projects page provides project details and image URLs', function () {
    Storage::fake('public');
    Storage::disk('public')->put('featured-projects/office.png', 'test image');
    Storage::disk('public')->put('featured-projects/office-detail.png', 'test image');

    $project = FeaturedProject::query()->create([
        'name' => 'Tòa nhà văn phòng',
        'description' => 'Cung ứng vật tư cơ điện',
        'location' => 'Hà Nội',
        'category' => 'Văn phòng',
        'subcategory' => 'HVAC',
        'type' => '2',
    ]);
    $project->syncImagePaths(['featured-projects/office.png', 'featured-projects/office-detail.png']);

    $this->get(route('kbuser.featured-projects'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('featured projects/Index')
            ->has('projects', 1)
            ->where('projects.0.id', $project->id)
            ->where('projects.0.image_url', '/storage/featured-projects/office.png')
            ->where('projects.0.images_url', [
                '/storage/featured-projects/office.png',
                '/storage/featured-projects/office-detail.png',
            ]),
        );
});

test('the home page includes every featured project with an image URL', function () {
    Storage::fake('public');

    foreach (range(1, 4) as $number) {
        $project = FeaturedProject::query()->create([
            'name' => "Dự án {$number}",
            'description' => 'Cung ứng vật tư cho công trình',
            'location' => 'Hà Nội',
            'category' => 'Văn phòng',
            'subcategory' => 'HVAC',
            'type' => '2',
        ]);
        $project->syncImagePaths(["featured-projects/project-{$number}.png"]);
        $project->forceFill(['created_at' => now()->addSeconds($number)])->save();
    }

    $this->get(route('kbuser.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('featuredProjects', 4)
            ->where('featuredProjects.0.image_url', '/storage/featured-projects/project-4.png'),
        );
});

test('the user about page renders the company information page', function () {
    $this->get(route('kbuser.about'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('about us/Index'),
        );
});

test('the user contacts page renders the contact form', function () {
    $this->get(route('kbuser.contacts'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/Index'),
        );
});

test('a contact quote request is saved with its project name', function () {
    Mail::fake();

    $response = $this->post(route('kbuser.contacts.store'), [
        'name' => 'Nguyễn Văn An',
        'phone' => '0901234567',
        'email' => 'an@example.com',
        'category_id' => 'Cung ứng cho dự án',
        'project_name' => 'Tòa nhà văn phòng ABC',
        'message' => 'Cần báo giá vật tư HVAC cho dự án.',
    ]);

    $response->assertRedirect(route('kbuser.contacts'));
    $this->assertDatabaseHas('contacts', [
        'name' => 'Nguyễn Văn An',
        'phone' => '0901234567',
        'email' => 'an@example.com',
        'category_id' => 'Cung ứng cho dự án',
        'project_name' => 'Tòa nhà văn phòng ABC',
        'message' => 'Cần báo giá vật tư HVAC cho dự án.',
        'status' => 'pending',
    ]);

    Mail::assertSent(ContactInquiryReceived::class, function (ContactInquiryReceived $mail) {
        return $mail->hasTo('nguyentuankietdz@gmail.com')
            && $mail->contact->name === 'Nguyễn Văn An'
            && $mail->contact->project_name === 'Tòa nhà văn phòng ABC'
            && str_contains($mail->render(), 'Cần báo giá vật tư HVAC cho dự án.');
    });
});

test('a contact quote request from the home page returns to the home page', function () {
    Mail::fake();

    $this->post(route('kbuser.contacts.store').'?from=home', [
        'name' => 'Nguyễn Văn An',
        'phone' => '0901234567',
        'email' => 'an@example.com',
        'message' => 'Cần báo giá vật tư cho dự án.',
    ])->assertRedirect(route('kbuser.home'));

    $this->assertDatabaseCount('contacts', 1);
    Mail::assertSent(ContactInquiryReceived::class);
});

test('a contact quote request requires valid contact details and a message', function () {
    Mail::fake();

    $this->from(route('kbuser.contacts'))
        ->post(route('kbuser.contacts.store'), [
            'name' => '',
            'phone' => '',
            'email' => 'invalid-email',
            'message' => '',
        ])
        ->assertRedirect(route('kbuser.contacts'))
        ->assertSessionHasErrors(['name', 'phone', 'email', 'message']);

    $this->assertDatabaseCount('contacts', 0);
    Mail::assertNothingOutgoing();
});
