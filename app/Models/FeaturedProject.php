<?php

namespace App\Models;

use App\Models\Concerns\HasImages;
use App\Services\SeoAutoIndexService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FeaturedProject extends Model
{
    use HasImages;

    protected $fillable = [
        'name',
        'description',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'location',
        'category',
        'subcategory',
        'products',
        'type',
    ];

    protected $hidden = ['images'];

    protected $appends = ['slug', 'image', 'image_url', 'images_url'];

    protected static function booted(): void
    {
        static::saving(function (FeaturedProject $project): void {
            SeoAutoIndexService::ensureProjectSeo($project);
        });

        static::saved(function (FeaturedProject $project): void {
            SeoAutoIndexService::scheduleModelPing($project);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'products' => 'array',
        ];
    }

    /**
     * @return Attribute<string, never>
     */
    protected function slug(): Attribute
    {
        return Attribute::get(fn (): string => Str::slug((string) $this->name) ?: (string) $this->id);
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function image(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->firstImageUrl());
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->firstImageUrl());
    }

    /**
     * @return Attribute<list<string>, never>
     */
    protected function imagesUrl(): Attribute
    {
        return Attribute::get(fn (): array => $this->imageUrls());
    }
}
