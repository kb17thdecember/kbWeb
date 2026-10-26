<?php

namespace App\Models\Concerns;

use App\Models\Image;
use App\Support\ImageStorage;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gắn danh sách ảnh lưu ở bảng `images`; xóa model sẽ xóa luôn dòng ảnh và file.
 */
trait HasImages
{
    public static function bootHasImages(): void
    {
        static::deleting(function ($model): void {
            $model->images()->get()->each->delete();
        });
    }

    /**
     * @return MorphMany<Image, $this>
     */
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Chỉ đọc từ relation đã eager load để tránh N+1 khi model được serialize lồng nhau.
     *
     * @return list<string>
     */
    public function imageUrls(): array
    {
        if (! $this->relationLoaded('images')) {
            return [];
        }

        return $this->getRelation('images')->map(fn (Image $image): string => $image->url)->values()->all();
    }

    public function firstImageUrl(): ?string
    {
        return $this->imageUrls()[0] ?? null;
    }

    /**
     * Đồng bộ ảnh theo danh sách đường dẫn: giữ dòng cũ còn trong danh sách, xóa dòng (và file) không còn.
     *
     * @param  list<string>  $paths
     */
    public function syncImagePaths(array $paths): void
    {
        $paths = array_values(array_unique($paths));
        $existing = $this->images()->get()->keyBy('path');

        $existing->reject(fn (Image $image): bool => in_array($image->path, $paths, true))
            ->each->delete();

        foreach ($paths as $order => $path) {
            $image = $existing->get($path);

            if ($image !== null) {
                $image->update(['sort_order' => $order]);

                continue;
            }

            $this->images()->create(['path' => $path, 'sort_order' => $order]);
        }

        $this->unsetRelation('images');
        $this->load('images');
    }

    /**
     * Đồng bộ ảnh từ dữ liệu gửi lên: URL ảnh đã có, URL ngoài, hoặc ảnh mới dạng data URI.
     *
     * @param  list<string>  $inputs
     */
    public function syncImageInputs(array $inputs, string $directory): void
    {
        $existing = $this->images()->get()->keyBy(fn (Image $image): string => $image->url);
        $paths = [];

        foreach ($inputs as $input) {
            $paths[] = match (true) {
                $existing->has($input) => $existing->get($input)->path,
                ImageStorage::isDataUri($input) => ImageStorage::storeDataUri($input, $directory),
                default => $input,
            };
        }

        $this->syncImagePaths($paths);
    }
}
