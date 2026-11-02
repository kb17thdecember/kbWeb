<?php

use App\Models\KflexPrice;
use App\Models\User;
use Database\Seeders\KflexPriceSeeder;

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

test('kflex price seeder seeds m-flex tube foam prices in vnd per meter', function () {
    $this->seed(KflexPriceSeeder::class);

    expect(KflexPrice::query()->where('product_line', 'm_flex')->where('form_type', 'tube')->where('variant_type', 'foam')->count())
        ->toBe(140);

    $sample10x06 = KflexPrice::query()
        ->where('product_line', 'm_flex')
        ->where('form_type', 'tube')
        ->where('variant_type', 'foam')
        ->where('thickness', '10')
        ->where('diameter', '06')
        ->sole();

    expect($sample10x06->price)->toBe(6179)
        ->and($sample10x06->unit)->toBe('m')
        ->and($sample10x06->brand)->toBe('Ý')
        ->and($sample10x06->origin)->toBe('VN')
        ->and($sample10x06->product_name)->toBe('Ống bảo ôn K-Flex M-Flex kích thước 10x06');

    $sample40x168 = KflexPrice::query()
        ->where('product_line', 'm_flex')
        ->where('form_type', 'tube')
        ->where('variant_type', 'foam')
        ->where('thickness', '40')
        ->where('diameter', '168')
        ->sole();

    expect($sample40x168->price)->toBe(495603)
        ->and($sample40x168->product_name)->toBe('Ống bảo ôn K-Flex M-Flex kích thước 40x168');

    $sample50x140 = KflexPrice::query()
        ->where('product_line', 'm_flex')
        ->where('form_type', 'tube')
        ->where('variant_type', 'foam')
        ->where('thickness', '50')
        ->where('diameter', '140')
        ->sole();

    expect($sample50x140->price)->toBe(634499)
        ->and($sample50x140->product_name)->toBe('Ống bảo ôn K-Flex M-Flex kích thước 50x140');

    expect(KflexPrice::query()->where('product_line', 'm_flex')->where('form_type', 'tube')->where('variant_type', 'alu')->count())
        ->toBe(100);

    $mFlexAlu10x06 = KflexPrice::query()
        ->where('product_line', 'm_flex')
        ->where('form_type', 'tube')
        ->where('variant_type', 'alu')
        ->where('thickness', '10')
        ->where('diameter', '06')
        ->sole();

    expect($mFlexAlu10x06->price)->toBe(6179 + 29876)
        ->and($mFlexAlu10x06->unit)->toBe('m')
        ->and($mFlexAlu10x06->brand)->toBe('Ý')
        ->and($mFlexAlu10x06->origin)->toBe('VN')
        ->and($mFlexAlu10x06->product_name)->toBe('Ống bảo ôn K-Flex M-Flex kích thước 10x06');

    $mFlexAlu50x140 = KflexPrice::query()
        ->where('product_line', 'm_flex')
        ->where('form_type', 'tube')
        ->where('variant_type', 'alu')
        ->where('thickness', '50')
        ->where('diameter', '140')
        ->sole();

    expect($mFlexAlu50x140->price)->toBe(634499 + 201392)
        ->and($mFlexAlu50x140->product_name)->toBe('Ống bảo ôn K-Flex M-Flex kích thước 50x140');

    expect(KflexPrice::query()->where('product_line', 'm_flex')->where('form_type', 'sheet')->count())
        ->toBe(27);

    $sheetFoam6 = KflexPrice::query()
        ->where('product_line', 'm_flex')
        ->where('form_type', 'sheet')
        ->where('variant_type', 'foam')
        ->where('thickness', '6')
        ->sole();

    expect($sheetFoam6->price)->toBe(69300)
        ->and($sheetFoam6->unit)->toBe('m²')
        ->and($sheetFoam6->product_name)->toBe('Tấm bảo ôn K-Flex M-Flex dày 6mm');

    $sheetSaAf25 = KflexPrice::query()
        ->where('product_line', 'm_flex')
        ->where('form_type', 'sheet')
        ->where('variant_type', 'alu_or_glue')
        ->where('thickness', '25')
        ->sole();

    expect($sheetSaAf25->price)->toBe(320066)
        ->and($sheetSaAf25->product_name)->toBe('Tấm bảo ôn K-Flex M-Flex dày 25mm');

    $sheetSaaf50 = KflexPrice::query()
        ->where('product_line', 'm_flex')
        ->where('form_type', 'sheet')
        ->where('variant_type', 'alu_and_glue')
        ->where('thickness', '50')
        ->sole();

    expect($sheetSaaf50->price)->toBe(957442)
        ->and($sheetSaaf50->product_name)->toBe('Tấm bảo ôn K-Flex M-Flex dày 50mm');

    expect(KflexPrice::query()->where('product_line', 'k_protect')->where('form_type', 'tube')->where('variant_type', 'alu')->count())
        ->toBe(180);

    $kProtect10x06 = KflexPrice::query()
        ->where('product_line', 'k_protect')
        ->where('form_type', 'tube')
        ->where('variant_type', 'alu')
        ->where('thickness', '10')
        ->where('diameter', '06')
        ->sole();

    expect($kProtect10x06->price)->toBe(14769)
        ->and($kProtect10x06->unit)->toBe('m')
        ->and($kProtect10x06->brand)->toBe('Ý')
        ->and($kProtect10x06->origin)->toBe('Malaysia')
        ->and($kProtect10x06->product_name)->toBe('Ống bảo ôn K-Flex K-Protect kích thước 10x06');

    $kProtect50x273 = KflexPrice::query()
        ->where('product_line', 'k_protect')
        ->where('form_type', 'tube')
        ->where('variant_type', 'alu')
        ->where('thickness', '50')
        ->where('diameter', '273')
        ->sole();

    expect($kProtect50x273->price)->toBe(874556)
        ->and($kProtect50x273->product_name)->toBe('Ống bảo ôn K-Flex K-Protect kích thước 50x273');

    expect(KflexPrice::query()->where('product_line', 'k_protect')->where('form_type', 'sheet')->where('variant_type', 'alu_or_glue')->count())
        ->toBe(9);

    $kProtectSheet50 = KflexPrice::query()
        ->where('product_line', 'k_protect')
        ->where('form_type', 'sheet')
        ->where('variant_type', 'alu_or_glue')
        ->where('thickness', '50')
        ->sole();

    expect($kProtectSheet50->price)->toBe(597313)
        ->and($kProtectSheet50->unit)->toBe('m²')
        ->and($kProtectSheet50->brand)->toBe('Ý')
        ->and($kProtectSheet50->origin)->toBe('Malaysia')
        ->and($kProtectSheet50->product_name)->toBe('Tấm bảo ôn K-Flex K-Protect dày 50mm');

    expect(KflexPrice::query()->where('product_line', 'k_protect')->where('form_type', 'sheet')->where('variant_type', 'alu_and_glue')->count())
        ->toBe(8);

    $kProtectSaaf50 = KflexPrice::query()
        ->where('product_line', 'k_protect')
        ->where('form_type', 'sheet')
        ->where('variant_type', 'alu_and_glue')
        ->where('thickness', '50')
        ->sole();

    expect($kProtectSaaf50->price)->toBe(657790)
        ->and($kProtectSaaf50->unit)->toBe('m²')
        ->and($kProtectSaaf50->product_name)->toBe('Tấm bảo ôn K-Flex K-Protect dày 50mm');

    expect(KflexPrice::query()->where('product_line', 'st')->where('form_type', 'sheet')->where('variant_type', 'foam')->count())
        ->toBe(9);

    $stSheet50 = KflexPrice::query()
        ->where('product_line', 'st')
        ->where('form_type', 'sheet')
        ->where('variant_type', 'foam')
        ->where('thickness', '50')
        ->sole();

    expect($stSheet50->price)->toBe(822827)
        ->and($stSheet50->unit)->toBe('m²')
        ->and($stSheet50->brand)->toBe('Ý')
        ->and($stSheet50->origin)->toBe('Malaysia')
        ->and($stSheet50->product_name)->toBe('Tấm bảo ôn K-Flex ST dày 50mm');

    expect(KflexPrice::query()->where('product_line', 'st')->where('form_type', 'sheet')->where('variant_type', 'alu_or_glue')->count())
        ->toBe(9);

    $stSheetSaAf50 = KflexPrice::query()
        ->where('product_line', 'st')
        ->where('form_type', 'sheet')
        ->where('variant_type', 'alu_or_glue')
        ->where('thickness', '50')
        ->sole();

    expect($stSheetSaAf50->price)->toBe(899577)
        ->and($stSheetSaAf50->unit)->toBe('m²')
        ->and($stSheetSaAf50->product_name)->toBe('Tấm bảo ôn K-Flex ST dày 50mm');

    expect(KflexPrice::query()->where('product_line', 'st')->where('form_type', 'sheet')->where('variant_type', 'alu_and_glue')->count())
        ->toBe(9);

    $stSheetSaaf50 = KflexPrice::query()
        ->where('product_line', 'st')
        ->where('form_type', 'sheet')
        ->where('variant_type', 'alu_and_glue')
        ->where('thickness', '50')
        ->sole();

    expect($stSheetSaaf50->price)->toBe(976255)
        ->and($stSheetSaaf50->unit)->toBe('m²')
        ->and($stSheetSaaf50->product_name)->toBe('Tấm bảo ôn K-Flex ST dày 50mm');

    expect(KflexPrice::query()->where('product_line', 'st')->where('form_type', 'tube')->where('variant_type', 'foam')->count())
        ->toBe(146)
        ->and(KflexPrice::query()->where('product_line', 'st')->where('form_type', 'tube')->where('variant_type', 'alu')->count())
        ->toBe(146);

    $stTubeFoam09x06 = KflexPrice::query()
        ->where('product_line', 'st')
        ->where('form_type', 'tube')
        ->where('variant_type', 'foam')
        ->where('thickness', '09')
        ->where('diameter', '06')
        ->sole();

    expect($stTubeFoam09x06->price)->toBe(11137)
        ->and($stTubeFoam09x06->unit)->toBe('m')
        ->and($stTubeFoam09x06->brand)->toBe('Ý')
        ->and($stTubeFoam09x06->origin)->toBe('Malaysia')
        ->and($stTubeFoam09x06->product_name)->toBe('Ống bảo ôn K-Flex ST kích thước 09x06');

    $stTubeAlu50x140 = KflexPrice::query()
        ->where('product_line', 'st')
        ->where('form_type', 'tube')
        ->where('variant_type', 'alu')
        ->where('thickness', '50')
        ->where('diameter', '140')
        ->sole();

    expect($stTubeAlu50x140->price)->toBe(806542)
        ->and($stTubeAlu50x140->unit)->toBe('m')
        ->and($stTubeAlu50x140->product_name)->toBe('Ống bảo ôn K-Flex ST kích thước 50x140');
});
