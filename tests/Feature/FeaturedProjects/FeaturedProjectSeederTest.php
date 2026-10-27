<?php

use App\Models\FeaturedProject;
use Database\Seeders\FeaturedProjectSeeder;
use Illuminate\Support\Facades\Storage;

test('featured project seeder creates representative projects idempotently', function () {
    Storage::fake('public');

    $this->seed(FeaturedProjectSeeder::class);
    $this->seed(FeaturedProjectSeeder::class);

    expect(FeaturedProject::query()->count())->toBe(1)
        ->and(FeaturedProject::query()->distinct()->pluck('type')->sort()->values()->all())->toBe(['2']);

    $project = FeaturedProject::query()
        ->with('images')
        ->where('name', 'Tổ Hợp Giáo Dục FPT Tại Hà Tĩnh.')
        ->sole();

    expect($project->description)
        ->toBeString()
        ->toStartWith('Trường Tiểu học, THCS và THPT FPT Hà Tĩnh')
        ->toContain('khu vực Bắc Trung Bộ.')
        ->and(strlen($project->description))->toBeGreaterThan(255)
        ->and($project->images)->toHaveCount(2)
        ->and($project->images->pluck('path')->all())->toBe([
            'featured-projects/to-hop-giao-duc-fpt-tai-ha-tinh/FPTS.jpg',
            'featured-projects/to-hop-giao-duc-fpt-tai-ha-tinh/FPTS1.jpg',
        ]);

    Storage::disk('public')->assertExists('featured-projects/to-hop-giao-duc-fpt-tai-ha-tinh/FPTS.jpg');
    Storage::disk('public')->assertExists('featured-projects/to-hop-giao-duc-fpt-tai-ha-tinh/FPTS1.jpg');
});
