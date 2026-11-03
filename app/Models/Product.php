<?php

namespace App\Models;

use App\Models\Concerns\HasImages;
use App\Services\SeoAutoIndexService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasImages;

    public const TAG_REGULAR = 0;

    public const TAG_FEATURED = 1;

    public $table = 'products';

    protected $attributes = [
        'tag' => self::TAG_REGULAR,
    ];

    protected $fillable = [
        'name',
        'product_title',
        'category_id',
        'subcategory_id',
        'description',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'display_order',
        'discount',
        'tag',
        'status',
        'price',
        'quantity',
        'created_at',
        'updated_at',
    ];

    protected $hidden = ['images'];

    protected $appends = ['slug', 'image'];

    protected function casts(): array
    {
        return [
            'tag' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            $product->tag = ((int) ($product->tag ?? self::TAG_REGULAR)) === self::TAG_FEATURED
                ? self::TAG_FEATURED
                : self::TAG_REGULAR;

            SeoAutoIndexService::ensureProductSeo($product);
        });

        static::saved(function (Product $product): void {
            SeoAutoIndexService::scheduleModelPing($product);
        });
    }

    /**
     * @return Attribute<string, never>
     */
    protected function slug(): Attribute
    {
        return Attribute::get(fn (): string => Str::slug((string) $this->name) ?: (string) $this->id);
    }

    /**
     * Danh sách URL ảnh (giữ nguyên tên `image` cho giao diện hiện có).
     *
     * @return Attribute<list<string>, never>
     */
    protected function image(): Attribute
    {
        return Attribute::get(fn (): array => $this->imageUrls());
    }

    /**
     * Get the parent category for this product.
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Get the child category for this product.
     *
     * @return BelongsTo<Category, $this>
     */
    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'subcategory_id');
    }

    /**
     * @return HasMany<ProductReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class)->latest();
    }

    /**
     * @return MorphMany<Comment, $this>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->latest();
    }
}
