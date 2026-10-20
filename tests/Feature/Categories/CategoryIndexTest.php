<?php

use App\Models\Category;
use App\Models\Product;
use Database\Seeders\CategorySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('the category seeder assigns and stores the parent category logos idempotently', function () {
    Storage::fake('public');

    $this->seed(CategorySeeder::class);
    $this->seed(CategorySeeder::class);

    $categories = Category::query()->whereNull('parent_id')->orderBy('name')->get();
    $logos = [
        'Ariston' => [
            'path' => 'categories/ariston_logo.png',
            'source' => 'ariston_logo.png',
        ],
        'K-FLEX' => [
            'path' => 'categories/kflex_logo.png',
            'source' => 'kfkex_logo.png',
        ],
    ];

    expect($categories)->toHaveCount(2);

    foreach ($logos as $name => $logo) {
        $category = $categories->firstWhere('name', $name);
        $imageContents = file_get_contents(base_path("Modules/KbAdmin/resources/assets/images/{$logo['source']}"));

        expect($category)->not->toBeNull()
            ->and($category->image)->toBe($logo['path']);

        Storage::disk('public')->assertExists($logo['path']);
        expect(Storage::disk('public')->get($logo['path']))->toBe($imageContents);
    }
});

test('the categories index shows parent categories, children, and child product counts', function () {
    $parent = Category::factory()->create([
        'name' => 'Ariston',
        'description' => 'Thiết bị nước nóng',
    ]);
    $child = Category::factory()->create([
        'name' => 'Bình nước nóng trực tiếp',
        'parent_id' => $parent->id,
        'description_2' => 'Dòng trực tiếp',
    ]);
    Product::create([
        'name' => 'Sản phẩm thử',
        'category_id' => $parent->id,
        'subcategory_id' => $child->id,
        'description' => 'Mô tả sản phẩm',
        'price' => 100000,
        'quantity' => 0,
        'image' => [],
    ]);

    $this->get(route('admin.categories.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('categories', 1)
            ->where('categories.0.name', 'Ariston')
            ->where('categories.0.children_count', 1)
            ->where('categories.0.children.0.name', 'Bình nước nóng trực tiếp')
            ->where('categories.0.children.0.products_count', 1),
        );
});

test('a parent category can be created from the admin form', function () {
    Storage::fake('public');

    $response = $this->post(route('admin.categories.store'), [
        'name' => 'Ariston',
        'description' => 'Thiết bị nước nóng',
        'description_2' => 'Danh mục chính',
        'image' => UploadedFile::fake()->image('ariston.jpg'),
    ]);

    $response->assertRedirect(route('admin.categories.index'));

    $category = Category::query()->where('name', 'Ariston')->firstOrFail();

    $this->assertDatabaseHas('categories', [
        'name' => 'Ariston',
        'parent_id' => null,
        'description' => 'Thiết bị nước nóng',
        'description_2' => 'Danh mục chính',
    ]);
    expect($category->image)->toStartWith('categories/');
    Storage::disk('public')->assertExists($category->image);
});

test('a parent category name must be unique among parent categories', function () {
    Category::factory()->create([
        'name' => 'Ariston',
        'parent_id' => null,
    ]);

    $response = $this
        ->from(route('admin.categories.index'))
        ->post(route('admin.categories.store'), [
            'name' => 'Ariston',
        ]);

    $response->assertRedirect(route('admin.categories.index'))
        ->assertSessionHasErrors('name');

    expect(Category::query()->whereNull('parent_id')->count())->toBe(1);
});

test('a child category can be created under a parent category', function () {
    $parent = Category::factory()->create([
        'name' => 'Ariston',
        'parent_id' => null,
    ]);

    $response = $this->post(route('admin.categories.store'), [
        'name' => 'Bình nước nóng',
        'parent_id' => $parent->id,
        'description' => 'Dòng sản phẩm Ariston',
    ]);

    $response->assertRedirect(route('admin.categories.index'));

    $this->assertDatabaseHas('categories', [
        'name' => 'Bình nước nóng',
        'parent_id' => $parent->id,
        'description' => 'Dòng sản phẩm Ariston',
    ]);
});

test('a parent category can be updated', function () {
    $category = Category::factory()->create([
        'name' => 'Ariston cũ',
        'parent_id' => null,
    ]);

    $response = $this->put(route('admin.categories.update', $category), [
        'name' => 'Ariston',
        'description' => 'Danh mục đã cập nhật',
    ]);

    $response->assertRedirect(route('admin.categories.index'));

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => 'Ariston',
        'description' => 'Danh mục đã cập nhật',
    ]);
});

test('a parent category can be deleted when it has no children or products', function () {
    $category = Category::factory()->create([
        'parent_id' => null,
    ]);

    $response = $this->delete(route('admin.categories.destroy', $category));

    $response->assertRedirect(route('admin.categories.index'));
    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});

test('a parent category cannot be deleted while it has child categories', function () {
    $parent = Category::factory()->create([
        'parent_id' => null,
    ]);
    Category::factory()->create([
        'parent_id' => $parent->id,
    ]);

    $response = $this->from(route('admin.categories.index'))
        ->delete(route('admin.categories.destroy', $parent));

    $response->assertRedirect(route('admin.categories.index'))
        ->assertSessionHasErrors('category');

    $this->assertDatabaseHas('categories', ['id' => $parent->id]);
});

test('a child category can be updated without changing its parent', function () {
    $parent = Category::factory()->create([
        'parent_id' => null,
    ]);
    $child = Category::factory()->create([
        'name' => 'Dòng cũ',
        'parent_id' => $parent->id,
    ]);

    $response = $this->put(route('admin.categories.update', $child), [
        'name' => 'Dòng mới',
        'description' => 'Mô tả đã sửa',
    ]);

    $response->assertRedirect(route('admin.categories.index'));

    $this->assertDatabaseHas('categories', [
        'id' => $child->id,
        'name' => 'Dòng mới',
        'parent_id' => $parent->id,
        'description' => 'Mô tả đã sửa',
    ]);
});

test('a child category cannot be deleted while it has products', function () {
    $parent = Category::factory()->create([
        'parent_id' => null,
    ]);
    $child = Category::factory()->create([
        'parent_id' => $parent->id,
    ]);
    Product::create([
        'name' => 'Sản phẩm thử',
        'category_id' => $parent->id,
        'subcategory_id' => $child->id,
        'description' => 'Mô tả sản phẩm',
        'price' => 100000,
        'quantity' => 0,
        'image' => [],
    ]);

    $response = $this->from(route('admin.categories.index'))
        ->delete(route('admin.categories.destroy', $child));

    $response->assertRedirect(route('admin.categories.index'))
        ->assertSessionHasErrors('category');

    $this->assertDatabaseHas('categories', ['id' => $child->id]);
});
