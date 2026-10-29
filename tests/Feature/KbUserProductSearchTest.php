<?php

use App\Models\Category;
use App\Models\Product;

test('the navbar product search returns matching products and category details', function () {
    $category = Category::factory()->create(['name' => 'Vật tư HVAC']);
    $subcategory = Category::factory()->create([
        'name' => 'Ống gió',
        'parent_id' => $category->id,
    ]);
    $product = Product::query()->create([
        'name' => 'Ống gió mềm bảo ôn',
        'product_title' => 'Ống gió cách nhiệt',
        'category_id' => $category->id,
        'subcategory_id' => $subcategory->id,
        'description' => 'Dùng cho hệ thống thông gió',
        'price' => 125000,
        'quantity' => 8,
    ]);
    Product::query()->create([
        'name' => 'Bình nước nóng',
        'product_title' => 'Bình Ariston',
        'category_id' => $category->id,
        'description' => 'Sản phẩm không khớp từ khóa',
        'price' => 0,
        'quantity' => 4,
    ]);

    $this->getJson(route('kbuser.products.search', ['q' => 'cách nhiệt']))
        ->assertOk()
        ->assertJsonCount(1, 'products')
        ->assertJsonPath('products.0.id', $product->id)
        ->assertJsonPath('products.0.category.name', 'Vật tư HVAC')
        ->assertJsonPath('products.0.subcategory.name', 'Ống gió')
        ->assertJsonPath('products.0.image', []);
});

test('the navbar product search can retrieve products already in the cart', function () {
    $category = Category::factory()->create(['name' => 'Vật tư HVAC']);
    $products = collect(range(1, 9))
        ->map(fn (int $number): Product => Product::query()->create([
            'name' => "Ống gió mềm {$number}",
            'category_id' => $category->id,
            'description' => 'Ống gió cho công trình',
            'price' => 75000,
            'quantity' => 3,
        ]));

    $this->getJson(route('kbuser.products.search', ['ids' => $products->pluck('id')->all()]))
        ->assertOk()
        ->assertJsonCount(9, 'products')
        ->assertJsonPath('products.0.id', $products->last()->id);
});

test('the navbar product search validates cart product identifiers', function () {
    $this->getJson(route('kbuser.products.search', ['ids' => ['invalid']]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ids.0']);
});
