<?php

namespace App\Models;

use App\Models\Concerns\HasImages;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Product extends Model
{
    use HasImages;

    public $table = 'products';

    protected $fillable = [
        'name',
        'product_title',
        'category_id',
        'subcategory_id',
        'description',
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

    protected $appends = ['image'];

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
