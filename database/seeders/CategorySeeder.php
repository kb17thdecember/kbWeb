<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Ariston' => [
                'Bình nước nóng trực tiếp',
                'Bình nước nóng gián tiếp',
                'Bơm nhiệt nước nóng',
                'Thái dương năng',
            ],
            'K-FLEX' => [
                'Cao su lưu hóa NBR',
                'Polyolefin XLPE',
                'Phụ kiện bảo ôn',
            ],
        ];

        foreach ($categories as $parentName => $children) {
            $parent = Category::query()->firstOrCreate([
                'name' => $parentName,
                'parent_id' => null,
            ]);

            foreach ($children as $childName) {
                Category::query()->firstOrCreate([
                    'name' => $childName,
                    'parent_id' => $parent->id,
                ]);
            }
        }
    }
}
