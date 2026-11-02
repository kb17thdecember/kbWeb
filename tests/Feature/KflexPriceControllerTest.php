<?php

use App\Models\KflexPrice;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

test('admin can view kflex detailed prices page', function () {
    KflexPrice::query()->create([
        'product_line' => 'st',
        'form_type' => 'tube',
        'variant_type' => 'foam',
        'thickness' => '13',
        'diameter' => '22',
        'brand' => 'Ý',
        'origin' => 'Malaysia',
        'product_name' => 'Ống bảo ôn K-Flex ST kích thước 13x22',
        'unit' => 'm',
        'price' => 35000,
    ]);

    $response = $this->get(route('admin.kflex-prices.index'));

    $response->assertOk();
    expect(KflexPrice::query()->count())->toBe(1);
});

test('admin can bulk create and update multiple kflex sizes for tubes and multiple thicknesses for sheets at once', function () {
    $response = $this->post(route('admin.kflex-prices.bulk'), [
        'items' => [
            [
                'product_line' => 'st',
                'form_type' => 'tube',
                'variant_type' => 'foam',
                'thickness' => '13',
                'diameter' => '22',
                'brand' => '',
                'origin' => '',
                'product_name' => '',
                'unit' => 'm',
                'price' => 28000,
            ],
            [
                'product_line' => 'm_flex',
                'form_type' => 'tube',
                'variant_type' => 'alu',
                'thickness' => '19',
                'diameter' => '28',
                'brand' => '',
                'origin' => '',
                'product_name' => '',
                'unit' => 'm',
                'price' => 45000,
            ],
            [
                'product_line' => 'k_protect',
                'form_type' => 'sheet',
                'variant_type' => 'alu_or_glue',
                'thickness' => '19',
                'diameter' => null,
                'brand' => '',
                'origin' => '',
                'product_name' => '',
                'unit' => 'm²',
                'price' => 155000,
            ],
            [
                'product_line' => 'k_protect',
                'form_type' => 'sheet',
                'variant_type' => 'alu_and_glue',
                'thickness' => '25',
                'diameter' => null,
                'brand' => '',
                'origin' => '',
                'product_name' => '',
                'unit' => 'm²',
                'price' => 195000,
            ],
        ],
    ]);

    $response->assertRedirect(route('admin.kflex-prices.index'))
        ->assertSessionHas('toast');

    expect(KflexPrice::query()->count())->toBe(4);

    $stTube = KflexPrice::query()
        ->where('product_line', 'st')
        ->where('form_type', 'tube')
        ->where('variant_type', 'foam')
        ->sole();

    expect($stTube->product_name)->toBe('Ống bảo ôn K-Flex ST kích thước 13x22')
        ->and($stTube->brand)->toBe('Ý')
        ->and($stTube->origin)->toBe('Malaysia')
        ->and($stTube->price)->toBe(28000);

    $mFlexTube = KflexPrice::query()
        ->where('product_line', 'm_flex')
        ->where('form_type', 'tube')
        ->where('variant_type', 'alu')
        ->sole();

    expect($mFlexTube->product_name)->toBe('Ống bảo ôn K-Flex M-Flex kích thước 19x28')
        ->and($mFlexTube->brand)->toBe('Ý')
        ->and($mFlexTube->origin)->toBe('VN')
        ->and($mFlexTube->price)->toBe(45000);

    $kProtectSheet = KflexPrice::query()
        ->where('product_line', 'k_protect')
        ->where('form_type', 'sheet')
        ->where('variant_type', 'alu_and_glue')
        ->sole();

    expect($kProtectSheet->product_name)->toBe('Tấm bảo ôn K-Flex K-Protect dày 25mm')
        ->and($kProtectSheet->brand)->toBe('Ý')
        ->and($kProtectSheet->origin)->toBe('Malaysia')
        ->and($kProtectSheet->price)->toBe(195000);

    // Updating existing size via bulk endpoint updates price in-place without duplicating
    $this->post(route('admin.kflex-prices.bulk'), [
        'items' => [
            [
                'product_line' => 'st',
                'form_type' => 'tube',
                'variant_type' => 'foam',
                'thickness' => '13',
                'diameter' => '22',
                'brand' => 'Ý',
                'origin' => 'Malaysia',
                'product_name' => '',
                'unit' => 'm',
                'price' => 32000,
            ],
        ],
    ])->assertRedirect(route('admin.kflex-prices.index'));

    expect(KflexPrice::query()->count())->toBe(4)
        ->and($stTube->fresh()->price)->toBe(32000);
});

test('admin can update and delete a single kflex price record or bulk delete', function () {
    $first = KflexPrice::query()->create([
        'product_line' => 'st',
        'form_type' => 'tube',
        'variant_type' => 'foam',
        'thickness' => '9',
        'diameter' => '16',
        'brand' => 'Ý',
        'origin' => 'Malaysia',
        'product_name' => 'Ống bảo ôn K-Flex ST kích thước 9x16',
        'unit' => 'm',
        'price' => 15000,
    ]);

    $second = KflexPrice::query()->create([
        'product_line' => 'm_flex',
        'form_type' => 'sheet',
        'variant_type' => 'alu_or_glue',
        'thickness' => '19',
        'diameter' => null,
        'brand' => 'Ý',
        'origin' => 'VN',
        'product_name' => 'Tấm bảo ôn K-Flex M-Flex dày 19mm',
        'unit' => 'm²',
        'price' => 120000,
    ]);

    $this->put(route('admin.kflex-prices.update', $first), [
        'product_line' => 'st',
        'form_type' => 'tube',
        'variant_type' => 'alu',
        'thickness' => '9',
        'diameter' => '19',
        'brand' => 'Ý',
        'origin' => 'Malaysia',
        'product_name' => '',
        'unit' => 'm',
        'price' => 21000,
    ])->assertRedirect(route('admin.kflex-prices.index'));

    expect($first->fresh()->variant_type)->toBe('alu')
        ->and($first->fresh()->diameter)->toBe('19')
        ->and($first->fresh()->product_name)->toBe('Ống bảo ôn K-Flex ST kích thước 9x19')
        ->and($first->fresh()->price)->toBe(21000);

    $this->delete(route('admin.kflex-prices.bulk-destroy'), [
        'ids' => [$first->id, $second->id],
    ])->assertRedirect(route('admin.kflex-prices.index'));

    expect(KflexPrice::query()->count())->toBe(0);
});
