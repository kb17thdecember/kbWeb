<?php

use App\Models\Category;
use Inertia\Testing\AssertableInertia as Assert;

test('the user category page lists parent categories and selects their product lines', function () {
    $parent = Category::factory()->create([
        'name' => 'Ariston',
        'parent_id' => null,
    ]);
    $child = Category::factory()->create([
        'name' => 'Bình nước nóng trực tiếp',
        'parent_id' => $parent->id,
    ]);
    Category::factory()->create([
        'name' => 'K-FLEX',
        'parent_id' => null,
    ]);

    $this->get(route('kbuser.categories'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('categories/Index')
            ->has('categories', 2)
            ->where('selectedCategoryId', null)
            ->where('categories.0.name', 'Ariston')
            ->where('categories.0.children.0.name', 'Bình nước nóng trực tiếp'),
        );

    $this->get(route('kbuser.categories', ['category' => $parent->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('categories/Index')
            ->where('selectedCategoryId', $parent->id)
            ->where('categories.0.children.0.id', $child->id),
        );
});

test('the user category page ignores an unknown parent category filter', function () {
    $this->get(route('kbuser.categories', ['category' => 999999]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('categories/Index')
            ->where('selectedCategoryId', null),
        );
});
