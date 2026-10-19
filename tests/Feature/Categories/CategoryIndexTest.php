<?php

use App\Models\Category;
use App\Models\Product;
use Inertia\Testing\AssertableInertia as Assert;

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
            ->component('categories/Index')
            ->has('categories', 1)
            ->where('categories.0.name', 'Ariston')
            ->where('categories.0.children_count', 1)
            ->where('categories.0.children.0.name', 'Bình nước nóng trực tiếp')
            ->where('categories.0.children.0.products_count', 1),
        );
});
