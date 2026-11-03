<?php

namespace App\Models;

use App\Models\Concerns\HasImages;
use App\Services\SeoAutoIndexService;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasImages;

    protected static function booted(): void
    {
        static::saving(function (Category $category): void {
            SeoAutoIndexService::ensureCategorySeo($category);
        });

        static::saved(function (Category $category): void {
            SeoAutoIndexService::scheduleModelPing($category);
        });
    }

    protected $fillable = [
        'name',
        'parent_id',
        'description',
        'description_2',
        'seo_title',
        'seo_description',
        'seo_keywords',
    ];

    protected $hidden = ['images'];

    protected $appends = ['slug', 'image', 'image_url'];

    /**
     * @return Attribute<string, never>
     */
    protected function slug(): Attribute
    {
        return Attribute::get(function (): string {
            $slug = Str::slug((string) $this->name);

            return $slug !== '' ? $slug : (string) $this->id;
        });
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
     * Get the parent category.
     *
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Get the child categories.
     *
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Get products assigned to this category as a child category.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'subcategory_id');
    }
}
