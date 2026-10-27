<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Ariston' => [
                'image_source' => 'ariston.png',
                'stored_image' => 'categories/ariston_logo.png',
                'children' => [
                    'Bình nước nóng trực tiếp',
                    'Bình nước nóng gián tiếp',
                    'Bơm nhiệt nước nóng',
                    'Thái dương năng',
                ],
            ],
            'K-FLEX' => [
                'image_source' => 'kfkex_logo.png',
                'stored_image' => 'categories/kflex_logo.png',
                'children' => [
                    'Cao su lưu hóa NBR',
                    'Polyolefin XLPE',
                    'Phụ kiện bảo ôn',
                ],
            ],
        ];

        foreach ($categories as $parentName => $categoryData) {
            $imagePath = base_path("Modules/KbAdmin/resources/assets/images/products/{$categoryData['image_source']}");
            $imageContents = file_get_contents($imagePath);

            if ($imageContents === false) {
                throw new RuntimeException("Unable to read category image at [{$imagePath}].");
            }

            $storedImagePath = $categoryData['stored_image'];
            if (! Storage::disk('public')->put($storedImagePath, $imageContents)) {
                throw new RuntimeException("Unable to store category image at [{$storedImagePath}].");
            }

            $parent = Category::query()->firstOrCreate([
                'name' => $parentName,
                'parent_id' => null,
            ]);
            $parent->syncImagePaths([$storedImagePath]);

            foreach ($categoryData['children'] as $childName) {
                Category::query()->firstOrCreate([
                    'name' => $childName,
                    'parent_id' => $parent->id,
                ]);
            }
        }
    }
}
