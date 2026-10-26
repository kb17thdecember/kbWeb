<?php

use App\Models\Category;
use App\Models\FeaturedProject;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->actingAs(User::factory()->admin()->create()));

function fakeFeaturedProjectImage(string $name): UploadedFile
{
    $imageContents = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=',
        true,
    );

    if ($imageContents === false) {
        throw new RuntimeException('Unable to decode the test image fixture.');
    }

    return UploadedFile::fake()->createWithContent($name, $imageContents);
}

test('the featured projects page lists saved projects', function () {
    $parent = Category::factory()->create(['name' => 'Văn phòng']);
    $child = Category::factory()->create([
        'name' => 'MEP',
        'parent_id' => $parent->id,
    ]);
    $project = FeaturedProject::create([
        'name' => 'Tổ hợp văn phòng',
        'description' => 'Dự án văn phòng tại Hà Nội',
        'location' => 'Hà Nội',
        'category' => $parent->name,
        'subcategory' => $child->name,
        'type' => '1',
    ]);
    $project->syncImagePaths(['https://example.com/office.jpg']);

    $this->get(route('admin.case-studies.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects', 1)
            ->where('projects.0.id', $project->id)
            ->where('projects.0.name', 'Tổ hợp văn phòng')
            ->where('projects.0.image_url', 'https://example.com/office.jpg')
            ->where('projects.0.category', 'Văn phòng')
            ->where('projects.0.subcategory', 'MEP'),
        );
});

test('a featured project can be created with an uploaded image', function () {
    Storage::fake('public');
    $parent = Category::factory()->create(['name' => 'Văn phòng']);
    $child = Category::factory()->create([
        'name' => 'MEP',
        'parent_id' => $parent->id,
    ]);

    $response = $this->post(route('admin.case-studies.store'), [
        'name' => 'Tổ hợp văn phòng',
        'description' => 'Dự án văn phòng tại Hà Nội',
        'location' => 'Hà Nội',
        'category' => $parent->name,
        'subcategory' => $child->name,
        'type' => '2',
        'image' => fakeFeaturedProjectImage('office.png'),
    ]);

    $response->assertRedirect(route('admin.case-studies.index'));

    $project = FeaturedProject::query()->sole();

    expect($project->name)->toBe('Tổ hợp văn phòng')
        ->and($project->category)->toBe('Văn phòng')
        ->and($project->subcategory)->toBe('MEP')
        ->and($project->images)->toHaveCount(1)
        ->and($project->image)->toStartWith('/storage/featured-projects/');
    Storage::disk('public')->assertExists($project->images->first()->path);
});

test('a featured project can be created with up to three uploaded images', function () {
    Storage::fake('public');
    $parent = Category::factory()->create(['name' => 'Văn phòng']);
    $child = Category::factory()->create([
        'name' => 'MEP',
        'parent_id' => $parent->id,
    ]);

    $this->post(route('admin.case-studies.store'), [
        'name' => 'Tổ hợp văn phòng',
        'description' => 'Dự án văn phòng tại Hà Nội',
        'location' => 'Hà Nội',
        'category' => $parent->name,
        'subcategory' => $child->name,
        'type' => '2',
        'images' => [
            fakeFeaturedProjectImage('office-front.png'),
            fakeFeaturedProjectImage('office-hall.png'),
            fakeFeaturedProjectImage('office-system.png'),
        ],
    ])->assertRedirect(route('admin.case-studies.index'));

    $project = FeaturedProject::query()->sole();
    expect($project->images)->toHaveCount(3)
        ->and($project->image)->toBe($project->images_url[0]);

    foreach ($project->images as $image) {
        Storage::disk('public')->assertExists($image->path);
    }

    $this->get(route('admin.case-studies.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('projects.0.images_url', $project->images_url),
        );
});

test('a featured project cannot be created with more than three images', function () {
    Storage::fake('public');
    $parent = Category::factory()->create(['name' => 'Văn phòng']);
    $child = Category::factory()->create([
        'name' => 'MEP',
        'parent_id' => $parent->id,
    ]);

    $this->from(route('admin.case-studies.index'))
        ->post(route('admin.case-studies.store'), [
            'name' => 'Tổ hợp văn phòng',
            'description' => 'Dự án văn phòng tại Hà Nội',
            'location' => 'Hà Nội',
            'category' => $parent->name,
            'subcategory' => $child->name,
            'type' => '2',
            'images' => [
                fakeFeaturedProjectImage('office-front.png'),
                fakeFeaturedProjectImage('office-hall.png'),
                fakeFeaturedProjectImage('office-system.png'),
                fakeFeaturedProjectImage('office-extra.png'),
            ],
        ])
        ->assertRedirect(route('admin.case-studies.index'))
        ->assertSessionHasErrors('images');

    expect(FeaturedProject::query()->count())->toBe(0);
    Storage::disk('public')->assertDirectoryEmpty('featured-projects');
});

test('updating a featured project can replace its image gallery', function () {
    Storage::fake('public');
    $oldImages = [
        fakeFeaturedProjectImage('old-front.png')->store('featured-projects', 'public'),
        fakeFeaturedProjectImage('old-detail.png')->store('featured-projects', 'public'),
    ];
    $parent = Category::factory()->create(['name' => 'Văn phòng']);
    $child = Category::factory()->create([
        'name' => 'MEP',
        'parent_id' => $parent->id,
    ]);
    $project = FeaturedProject::create([
        'name' => 'Tổ hợp văn phòng',
        'description' => 'Mô tả cũ',
        'location' => 'Hà Nội',
        'category' => $parent->name,
        'subcategory' => $child->name,
        'type' => '2',
    ]);
    $project->syncImagePaths($oldImages);

    $this->post(route('admin.case-studies.update', $project), [
        '_method' => 'put',
        'name' => 'Tổ hợp văn phòng',
        'description' => 'Mô tả mới',
        'location' => 'Hà Nội',
        'category' => $parent->name,
        'subcategory' => $child->name,
        'type' => '2',
        'images' => [
            fakeFeaturedProjectImage('new-front.png'),
            fakeFeaturedProjectImage('new-detail.png'),
        ],
    ])->assertRedirect(route('admin.case-studies.index'));

    $updatedProject = $project->fresh()->load('images');
    expect($updatedProject->images)->toHaveCount(2)
        ->and($updatedProject->image)->toBe($updatedProject->images_url[0]);

    foreach ($oldImages as $imagePath) {
        Storage::disk('public')->assertMissing($imagePath);
    }
    foreach ($updatedProject->images as $image) {
        Storage::disk('public')->assertExists($image->path);
    }
});

test('a featured project requires an image when created', function () {
    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $parent->id]);

    $response = $this->from(route('admin.case-studies.index'))
        ->post(route('admin.case-studies.store'), [
            'name' => 'Tổ hợp văn phòng',
            'description' => 'Dự án văn phòng tại Hà Nội',
            'location' => 'Hà Nội',
            'category' => $parent->name,
            'subcategory' => $child->name,
            'type' => '3',
        ]);

    $response->assertRedirect(route('admin.case-studies.index'))
        ->assertSessionHasErrors('image');

    expect(FeaturedProject::query()->count())->toBe(0);
});

test('a featured project can be updated without replacing its image', function () {
    Storage::fake('public');
    $parent = Category::factory()->create(['name' => 'Khu căn hộ']);
    $child = Category::factory()->create([
        'name' => 'Cấp nước',
        'parent_id' => $parent->id,
    ]);
    $imagePath = fakeFeaturedProjectImage('office.png')->store('featured-projects', 'public');
    $project = FeaturedProject::create([
        'name' => 'Tổ hợp cũ',
        'description' => 'Mô tả cũ',
        'location' => 'Hà Nội',
        'category' => 'Văn phòng',
        'subcategory' => 'MEP',
        'type' => '1',
    ]);
    $project->syncImagePaths([$imagePath]);

    $response = $this->put(route('admin.case-studies.update', $project), [
        'name' => 'Tổ hợp mới',
        'description' => 'Mô tả mới',
        'location' => 'Hồ Chí Minh',
        'category' => $parent->name,
        'subcategory' => $child->name,
        'type' => '2',
    ]);

    $response->assertRedirect(route('admin.case-studies.index'));

    expect($project->fresh()->name)->toBe('Tổ hợp mới')
        ->and($project->fresh()->category)->toBe('Khu căn hộ')
        ->and($project->fresh()->subcategory)->toBe('Cấp nước')
        ->and($project->fresh()->load('images')->images->pluck('path')->all())->toBe([$imagePath]);
    Storage::disk('public')->assertExists($imagePath);
});

test('a featured project can be deleted together with its stored image', function () {
    Storage::fake('public');
    $imagePath = fakeFeaturedProjectImage('office.png')->store('featured-projects', 'public');
    $project = FeaturedProject::create([
        'name' => 'Tổ hợp cần xóa',
        'description' => 'Dự án cần xóa',
        'location' => 'Hà Nội',
        'category' => 'Văn phòng',
        'subcategory' => 'MEP',
        'type' => '3',
    ]);
    $project->syncImagePaths([$imagePath]);

    $response = $this->delete(route('admin.case-studies.destroy', $project));

    $response->assertRedirect(route('admin.case-studies.index'));
    $this->assertDatabaseMissing('featured_projects', ['id' => $project->id]);
    Storage::disk('public')->assertMissing($imagePath);
    $this->assertDatabaseMissing('images', ['imageable_id' => $project->id]);
});

test('a featured project cannot select a child category from a different parent', function () {
    $parent = Category::factory()->create(['name' => 'Văn phòng']);
    $otherParent = Category::factory()->create(['name' => 'Khu căn hộ']);
    $otherChild = Category::factory()->create([
        'name' => 'Cấp nước',
        'parent_id' => $otherParent->id,
    ]);

    $response = $this->from(route('admin.case-studies.index'))
        ->post(route('admin.case-studies.store'), [
            'name' => 'Tổ hợp văn phòng',
            'description' => 'Dự án văn phòng tại Hà Nội',
            'location' => 'Hà Nội',
            'category' => $parent->name,
            'subcategory' => $otherChild->name,
            'type' => '1',
            'image' => fakeFeaturedProjectImage('office.png'),
        ]);

    $response->assertRedirect(route('admin.case-studies.index'))
        ->assertSessionHasErrors('subcategory');

    expect(FeaturedProject::query()->count())->toBe(0);
});

test('a featured project rejects an unsupported project type', function () {
    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $parent->id]);

    $response = $this->from(route('admin.case-studies.index'))
        ->post(route('admin.case-studies.store'), [
            'name' => 'Tổ hợp văn phòng',
            'description' => 'Dự án văn phòng tại Hà Nội',
            'location' => 'Hà Nội',
            'category' => $parent->name,
            'subcategory' => $child->name,
            'type' => '4',
            'image' => fakeFeaturedProjectImage('office.png'),
        ]);

    $response->assertRedirect(route('admin.case-studies.index'))
        ->assertSessionHasErrors('type');

    expect(FeaturedProject::query()->count())->toBe(0);
});
