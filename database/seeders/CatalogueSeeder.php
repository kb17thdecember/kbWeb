<?php

namespace Database\Seeders;

use App\Models\Catalogue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $catalogues = [
            [
                'title' => 'Catalogue bình nước nóng Ariston',
                'description' => 'Các dòng sản phẩm của Ariston và thông số kỹ thuật.',
                'pdf' => 'ARISTON NEW CATALOGUE.pdf',
                'image' => 'products/ariston.png',
            ],
            [
                'title' => 'Catalogue bảo ôn K-Flex model ST',
                'description' => 'Thông số kỹ thuật và hướng dẫn lựa chọn vật liệu bảo ôn K-Flex model ST.',
                'pdf' => 'K-FLEX-ST.pdf',
                'image' => 'products/kfkex_logo.png',
            ],
            [
                'title' => 'Catalogue bảo ôn K-Flex model K-Protect',
                'description' => 'Thông số kỹ thuật và hướng dẫn lựa chọn vật liệu bảo ôn K-Flex model K-Protect.',
                'pdf' => 'K-FLEX K-PROTECT.pdf',
                'image' => 'products/kfkex_logo.png',
            ],
            [
                'title' => 'Catalogue bảo ôn K-Flex model M-Flex',
                'description' => 'Thông số kỹ thuật và hướng dẫn lựa chọn vật liệu bảo ôn K-Flex model M-Flex.',
                'pdf' => 'K-FLEX M-FLEX.pdf',
                'image' => 'products/kfkex_logo.png',
            ],
        ];

        foreach ($catalogues as $item) {
            $pdfSource = base_path("Modules/KbAdmin/resources/assets/catalogue/{$item['pdf']}");
            $imageSource = base_path("Modules/KbAdmin/resources/assets/images/{$item['image']}");
            $slug = str($item['title'])->slug();
            $pdfPath = "catalogues/{$slug}.pdf";

            $this->copyToPublicDisk($pdfSource, $pdfPath);

            $catalogue = Catalogue::query()->updateOrCreate(
                ['title' => $item['title']],
                [
                    'description' => $item['description'],
                    'file_path' => $pdfPath,
                    'file_name' => $item['pdf'],
                    'file_size' => filesize($pdfSource),
                    'is_published' => true,
                ],
            );

            $coverPath = "catalogues/covers/{$slug}.".pathinfo($imageSource, PATHINFO_EXTENSION);
            $this->copyToPublicDisk($imageSource, $coverPath);
            $catalogue->syncImagePaths([$coverPath]);
        }
    }

    private function copyToPublicDisk(string $source, string $destination): void
    {
        $stream = @fopen($source, 'rb');

        if ($stream === false) {
            throw new RuntimeException("Unable to read catalogue file at [{$source}].");
        }

        $stored = Storage::disk('public')->put($destination, $stream);
        fclose($stream);

        if (! $stored) {
            throw new RuntimeException("Unable to store catalogue file at [{$destination}].");
        }
    }
}
