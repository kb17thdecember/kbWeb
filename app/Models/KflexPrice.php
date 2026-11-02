<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KflexPrice extends Model
{
    use HasFactory;

    public const PRODUCT_LINES = [
        'st' => 'ST',
        'k_protect' => 'K-Protect',
        'm_flex' => 'M-Flex',
    ];

    /**
     * Default brand and origin per product line.
     *
     * @var array<string, array{label: string, brand: string, origin: string}>
     */
    public const PRODUCT_LINE_META = [
        'st' => [
            'label' => 'ST',
            'brand' => 'Ý',
            'origin' => 'Malaysia',
        ],
        'k_protect' => [
            'label' => 'K-Protect',
            'brand' => 'Ý',
            'origin' => 'Malaysia',
        ],
        'm_flex' => [
            'label' => 'M-Flex',
            'brand' => 'Ý',
            'origin' => 'VN',
        ],
    ];

    public const FORM_TYPES = [
        'sheet' => 'Tấm',
        'tube' => 'Ống',
    ];

    public const TUBE_VARIANTS = [
        'foam' => 'Chỉ Foam',
        'alu' => 'Phủ nhôm',
    ];

    public const SHEET_VARIANTS = [
        'foam' => 'Chỉ Foam',
        'alu_or_glue' => 'Có keo/nhôm',
        'alu_and_glue' => 'Có cả keo và nhôm',
    ];

    protected $table = 'kflex_prices';

    protected $fillable = [
        'product_line',
        'form_type',
        'variant_type',
        'thickness',
        'diameter',
        'brand',
        'origin',
        'product_name',
        'unit',
        'price',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
        ];
    }

    /**
     * Resolve default brand and origin for a product line.
     *
     * @return array{brand: string, origin: string}
     */
    public static function defaultBrandAndOrigin(string $productLine): array
    {
        $meta = self::PRODUCT_LINE_META[$productLine] ?? null;

        return [
            'brand' => $meta['brand'] ?? 'Ý',
            'origin' => $meta['origin'] ?? 'Malaysia',
        ];
    }

    public static function buildDefaultProductName(
        string $productLine,
        string $formType,
        string $thickness,
        ?string $diameter = null,
    ): string {
        $lineLabel = self::PRODUCT_LINES[$productLine] ?? $productLine;
        $cleanThickness = preg_replace('/\s*mm$/i', '', trim($thickness)) ?: trim($thickness);
        $cleanDiameter = preg_replace('/\s*mm$/i', '', trim((string) $diameter)) ?: trim((string) $diameter);

        if ($formType === 'sheet') {
            return "Tấm bảo ôn K-Flex {$lineLabel} dày {$cleanThickness}mm";
        }

        return "Ống bảo ôn K-Flex {$lineLabel} kích thước {$cleanThickness}x{$cleanDiameter}";
    }
}
