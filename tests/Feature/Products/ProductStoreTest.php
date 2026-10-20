<?php

use App\Models\Category;
use App\Models\Product;

test('products can be created with their images', function () {
    $parent = Category::factory()->create(['name' => 'Ariston']);
    $child = Category::factory()->create([
        'name' => 'Bình nước nóng gián tiếp',
        'parent_id' => $parent->id,
    ]);
    $images = [
        'https://example.com/product.jpg',
        'data:image/webp;base64,ZmFrZQ==',
    ];
    $description = "Mô tả dòng một\nMô tả dòng hai";

    $response = $this->post(route('admin.products.store'), [
        'name' => 'Bình nước nóng',
        'product_title' => 'Bình Ariston cao cấp',
        'category_id' => $parent->id,
        'subcategory_id' => $child->id,
        'tag' => 1,
        'price' => 2190000,
        'description' => $description,
        'images' => $images,
    ]);

    $response->assertRedirect(route('admin.products.index'));

    $product = Product::query()->sole();

    expect($product->name)->toBe('Bình nước nóng')
        ->and($product->product_title)->toBe('Bình Ariston cao cấp')
        ->and($product->description)->toBe($description)
        ->and($product->category_id)->toBe($parent->id)
        ->and($product->subcategory_id)->toBe($child->id)
        ->and($product->quantity)->toBe(0)
        ->and($product->image)->toBe($images);
});

test('products cannot be created with a subcategory from another category', function () {
    $parent = Category::factory()->create(['name' => 'Ariston']);
    $otherParent = Category::factory()->create(['name' => 'K-FLEX']);
    $otherChild = Category::factory()->create([
        'name' => 'Cao su lưu hóa NBR',
        'parent_id' => $otherParent->id,
    ]);

    $response = $this
        ->from(route('admin.products.index'))
        ->post(route('admin.products.store'), [
            'name' => 'Bình nước nóng',
            'product_title' => 'Bình Ariston',
            'category_id' => $parent->id,
            'subcategory_id' => $otherChild->id,
            'tag' => 1,
            'price' => 2190000,
            'description' => 'Mô tả sản phẩm',
            'images' => [],
        ]);

    $response->assertRedirect(route('admin.products.index'))
        ->assertSessionHasErrors('subcategory_id');

    expect(Product::query()->count())->toBe(0);
});

test('products can be updated with their images', function () {
    $oldParent = Category::factory()->create(['name' => 'Ariston']);
    $oldChild = Category::factory()->create([
        'name' => 'Bình nước nóng trực tiếp',
        'parent_id' => $oldParent->id,
    ]);
    $newParent = Category::factory()->create(['name' => 'K-FLEX']);
    $newChild = Category::factory()->create([
        'name' => 'Polyolefin XLPE',
        'parent_id' => $newParent->id,
    ]);
    $product = Product::create([
        'name' => 'Sản phẩm cũ',
        'category_id' => $oldParent->id,
        'subcategory_id' => $oldChild->id,
        'description' => 'Mô tả cũ',
        'price' => 100000,
        'quantity' => 3,
        'image' => ['https://example.com/old.jpg'],
    ]);
    $images = ['https://example.com/new.jpg'];

    $response = $this->put(route('admin.products.update', $product), [
        'name' => 'Sản phẩm mới',
        'product_title' => 'Tiêu đề mới',
        'category_id' => $newParent->id,
        'subcategory_id' => $newChild->id,
        'tag' => 2,
        'price' => 250000,
        'description' => 'Mô tả mới',
        'images' => $images,
    ]);

    $response->assertRedirect(route('admin.products.index'));

    expect($product->fresh()->name)->toBe('Sản phẩm mới')
        ->and($product->fresh()->product_title)->toBe('Tiêu đề mới')
        ->and($product->fresh()->category_id)->toBe($newParent->id)
        ->and($product->fresh()->subcategory_id)->toBe($newChild->id)
        ->and($product->fresh()->quantity)->toBe(3)
        ->and($product->fresh()->image)->toBe($images);
});

test('products can be deleted', function () {
    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $parent->id]);
    $product = Product::create([
        'name' => 'Sản phẩm cần xóa',
        'category_id' => $parent->id,
        'subcategory_id' => $child->id,
        'description' => 'Mô tả sản phẩm',
        'price' => 100000,
        'quantity' => 0,
        'image' => [],
    ]);

    $response = $this->delete(route('admin.products.destroy', $product));

    $response->assertRedirect(route('admin.products.index'));
    $this->assertDatabaseMissing('products', ['id' => $product->id]);
});

test('categories have children and child categories have products', function () {
    $parent = Category::factory()->create(['name' => 'Ariston']);
    $child = Category::factory()->create([
        'name' => 'Bình nước nóng trực tiếp',
        'parent_id' => $parent->id,
    ]);
    $product = Product::create([
        'name' => 'Bình nóng lạnh',
        'category_id' => $parent->id,
        'subcategory_id' => $child->id,
        'description' => 'Mô tả',
        'price' => 100000,
        'quantity' => 0,
        'image' => [],
    ]);

    expect($parent->children()->first()->is($child))->toBeTrue()
        ->and($child->products()->first()->is($product))->toBeTrue()
        ->and($product->category()->first()->is($parent))->toBeTrue()
        ->and($product->subcategory()->first()->is($child))->toBeTrue();
});
