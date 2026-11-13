<?php

namespace App\Models;

use App\Models\Concerns\HasImages;
use App\Services\SeoAutoIndexService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasImages;

    public const TAG_REGULAR = 0;

    public const TAG_FEATURED = 1;

    public $table = 'products';

    private static bool $originColumnChecked = false;

    protected $attributes = [
        'tag' => self::TAG_REGULAR,
    ];

    protected $fillable = [
        'name',
        'product_title',
        'origin',
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
            static::ensureOriginColumn();

            $product->tag = ((int) ($product->tag ?? self::TAG_REGULAR)) === self::TAG_FEATURED
                ? self::TAG_FEATURED
                : self::TAG_REGULAR;

            if (static::$originColumnChecked || Schema::hasColumn('products', 'origin')) {
                $rawOrigin = trim((string) $product->origin);

                if ($rawOrigin === '') {
                    $product->origin = static::resolveDefaultOrigin(
                        (string) $product->name,
                        $product->product_title,
                        $product->description,
                        $product->category_id ? (int) $product->category_id : null,
                        $product->subcategory_id ? (int) $product->subcategory_id : null,
                    );
                } elseif (strtoupper($rawOrigin) === 'VN') {
                    $product->origin = 'Việt Nam';
                } elseif (strtoupper($rawOrigin) === 'TQ') {
                    $product->origin = 'Trung Quốc';
                }
            }

            SeoAutoIndexService::ensureProductSeo($product);
        });

        static::saved(function (Product $product): void {
            SeoAutoIndexService::scheduleModelPing($product);
        });
    }

    public static function ensureOriginColumn(): void
    {
        if (static::$originColumnChecked && ! app()->runningUnitTests()) {
            return;
        }

        $markerFile = storage_path('framework/cache/products_origin_column.flag');

        if (! app()->runningUnitTests() && is_file($markerFile)) {
            static::$originColumnChecked = true;

            return;
        }

        if (! Schema::hasTable('products')) {
            return;
        }

        if (Schema::hasColumn('products', 'origin')) {
            static::$originColumnChecked = true;
            if (! app()->runningUnitTests()) {
                @file_put_contents($markerFile, '1');
            }

            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'origin')) {
                $table->string('origin', 100)->nullable()->after('product_title');
            }
        });

        static::$originColumnChecked = true;
        if (! app()->runningUnitTests()) {
            @file_put_contents($markerFile, '1');
        }
    }

    public static function resolveDefaultOrigin(
        string $name,
        ?string $productTitle = null,
        ?string $description = null,
        ?int $categoryId = null,
        ?int $subcategoryId = null
    ): string {
        $combined = mb_strtolower(trim("{$name} {$productTitle} {$description}"));
        $nameAndTitle = mb_strtolower(trim("{$name} {$productTitle}"));

        $isKflex = $categoryId === 2
            || in_array($subcategoryId, [3, 4, 5], true)
            || str_contains($nameAndTitle, 'k-flex')
            || str_contains($nameAndTitle, 'kflex')
            || str_contains($nameAndTitle, 'm-flex')
            || str_contains($nameAndTitle, 'mflex')
            || str_contains($nameAndTitle, 'k-protect')
            || str_contains($nameAndTitle, 'bảo ôn');

        if ($isKflex) {
            if ($subcategoryId === 5 || preg_match('/\bm-?\s*flex\b/iu', $nameAndTitle) === 1) {
                return 'Việt Nam';
            }

            return 'Malaysia';
        }

        $isHeatPump = $subcategoryId === 2
            || preg_match('/\b(bơm\s*nhiệt|heat\s*pump|stp|ptp|ar-\d+)\b/iu', $nameAndTitle) === 1;

        if ($isHeatPump) {
            return 'Trung Quốc';
        }

        if (preg_match('/dung\s*tích\s*:?\s*(\d+)\s*l\b/iu', $combined, $matches) === 1) {
            $liters = (int) $matches[1];

            return ($liters > 0 && $liters <= 30) ? 'Việt Nam' : 'Trung Quốc';
        }

        if (preg_match('/\b(10|15|20|30|40|50|56|80|100|150|200|300)\s*(?:l\b|b\b|sh\b|v\b|h\b|horb\b|hord\b|vert\b)/iu', $name, $matches) === 1) {
            $liters = (int) $matches[1];

            return ($liters > 0 && $liters <= 30) ? 'Việt Nam' : 'Trung Quốc';
        }

        return 'Trung Quốc';
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
