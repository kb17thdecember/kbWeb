<?php

namespace Database\Seeders;

use App\Models\KflexPrice;
use Illuminate\Database\Seeder;

class KflexPriceSeeder extends Seeder
{
    /**
     * Seed M-Flex tube foam (không nhôm) Nett Price VND/m records.
     */
    public function run(): void
    {
        /**
         * Map of thickness (mm) => [diameter (mm) => Nett Price VND/m]
         *
         * @var array<string, array<string, int>>
         */
        $mFlexTubeFoamPrices = [
            // 3/8" (10mm)
            '10' => [
                '06' => 6179,
                '10' => 6562,
                '13' => 7609,
                '16' => 5030,
                '19' => 6123,
                '22' => 9549,
                '25' => 11771,
                '28' => 12790,
                '32' => 13812,
                '35' => 15346,
                '42' => 19302,
                '48' => 21778,
                '51' => 22315,
                '54' => 22851,
                '60' => 24101,
                '67' => 25249,
                '73' => 0,
                '76' => 0,
                '79' => 0,
                '89' => 29564,
            ],

            // 1/2" (13mm)
            '13' => [
                '06' => 7863,
                '10' => 8706,
                '13' => 11693,
                '16' => 11974,
                '19' => 14305,
                '22' => 17233,
                '25' => 18792,
                '28' => 20373,
                '32' => 22927,
                '35' => 23538,
                '42' => 28109,
                '48' => 32552,
                '51' => 34619,
                '54' => 35741,
                '60' => 37657,
                '67' => 38603,
                '73' => 0,
                '76' => 39852,
                '79' => 41588,
                '89' => 46619,
                '114' => 62166,
            ],

            // 3/4" (19mm)
            '19' => [
                '06' => 15446,
                '10' => 17820,
                '13' => 20374,
                '16' => 22774,
                '19' => 24203,
                '22' => 27140,
                '25' => 29972,
                '28' => 32756,
                '32' => 36586,
                '35' => 38348,
                '42' => 45930,
                '48' => 52798,
                '51' => 56805,
                '54' => 58159,
                '60' => 65026,
                '67' => 67761,
                '73' => 73885,
                '76' => 76873,
                '79' => 0,
                '89' => 88335,
                '114' => 129860,
            ],

            // 1" (25mm)
            '25' => [
                '06' => 20037,
                '10' => 26297,
                '13' => 30893,
                '16' => 37759,
                '19' => 42559,
                '22' => 46898,
                '25' => 48942,
                '28' => 50807,
                '32' => 56831,
                '35' => 59665,
                '42' => 67553,
                '48' => 76846,
                '51' => 0,
                '54' => 91323,
                '60' => 93671,
                '67' => 98522,
                '73' => 0,
                '76' => 109219,
                '79' => 117668,
                '89' => 128368,
                '114' => 191273,
            ],

            // 1 1/4" (32mm)
            '32' => [
                '19' => 65160,
                '22' => 71824,
                '25' => 74946,
                '28' => 77798,
                '32' => 87026,
                '35' => 91335,
                '38' => 94993,
                '42' => 100047,
                '48' => 109429,
                '54' => 117683,
                '60' => 126166,
                '67' => 136045,
                '73' => 144279,
                '76' => 145084,
                '79' => 150425,
                '89' => 162488,
                '108' => 214609,
                '114' => 223724,
                '140' => 288712,
                '168' => 381234,
            ],

            // 1 1/2" (40mm)
            '40' => [
                '16' => 79291,
                '19' => 89363,
                '22' => 98478,
                '25' => 102786,
                '28' => 106712,
                '32' => 119349,
                '35' => 125247,
                '42' => 138783,
                '48' => 150157,
                '54' => 161186,
                '60' => 171451,
                '67' => 183608,
                '73' => 191459,
                '76' => 193143,
                '79' => 199998,
                '89' => 215221,
                '108' => 247429,
                '114' => 257559,
                '140' => 342975,
                '168' => 495603,
            ],

            // 2" (50mm)
            '50' => [
                '19' => 200803,
                '22' => 208998,
                '25' => 217501,
                '28' => 225236,
                '32' => 233718,
                '35' => 240381,
                '42' => 255719,
                '48' => 260679,
                '54' => 270557,
                '60' => 283905,
                '67' => 292962,
                '76' => 298419,
                '79' => 309545,
                '89' => 340428,
                '108' => 395995,
                '114' => 446393,
                '140' => 634499,
            ],
        ];

        $defaults = KflexPrice::defaultBrandAndOrigin('m_flex');

        foreach ($mFlexTubeFoamPrices as $thickness => $diameters) {
            $thicknessStr = (string) $thickness;

            foreach ($diameters as $diameter => $pricePerMeter) {
                $diameterStr = (string) $diameter;

                KflexPrice::query()->updateOrCreate(
                    [
                        'product_line' => 'm_flex',
                        'form_type' => 'tube',
                        'variant_type' => 'foam',
                        'thickness' => $thicknessStr,
                        'diameter' => $diameterStr,
                    ],
                    [
                        'brand' => $defaults['brand'],
                        'origin' => $defaults['origin'],
                        'product_name' => KflexPrice::buildDefaultProductName(
                            'm_flex',
                            'tube',
                            $thicknessStr,
                            $diameterStr,
                        ),
                        'unit' => 'm',
                        'price' => $pricePerMeter,
                    ],
                );
            }
        }

        /**
         * Aluminium foil cost VND/m for M-Flex tube by thickness (mm) => [diameter (mm) => alu_price_per_meter].
         * Thickness 9mm (Max Thk 12mm) in the foil table maps to M-Flex 10mm (3/8").
         *
         * @var array<string, array<string, int>>
         */
        $mFlexTubeAluFoilCosts = [
            // 3/8" (9mm in foil table, Max Thk 12mm => matches 10mm M-Flex tube)
            '10' => [
                '06' => 29876,
                '10' => 31159,
                '13' => 32444,
                '16' => 35010,
                '19' => 35010,
                '22' => 37577,
                '25' => 37577,
                '28' => 41427,
                '35' => 46560,
                '42' => 46560,
                '48' => 54901,
                '54' => 54901,
                '60' => 54901,
                '76' => 68376,
                '89' => 68376,
                '114' => 95326,
            ],

            // 1/2" (13mm)
            '13' => [
                '06' => 32444,
                '10' => 34369,
                '13' => 37577,
                '16' => 37577,
                '19' => 41427,
                '22' => 41427,
                '25' => 41427,
                '28' => 46560,
                '35' => 46560,
                '42' => 54901,
                '48' => 54901,
                '54' => 54901,
                '60' => 68376,
                '76' => 68376,
                '89' => 95326,
                '114' => 95326,
            ],

            // 3/4" (19mm)
            '19' => [
                '06' => 36935,
                '10' => 41427,
                '13' => 41427,
                '16' => 46560,
                '19' => 46560,
                '22' => 46560,
                '25' => 46560,
                '28' => 46560,
                '35' => 54901,
                '42' => 54901,
                '48' => 68376,
                '54' => 68376,
                '60' => 68376,
                '76' => 95326,
                '89' => 95326,
                '114' => 95326,
            ],

            // 1" (25mm)
            '25' => [
                '06' => 46560,
                '10' => 46560,
                '13' => 46560,
                '16' => 46560,
                '19' => 54901,
                '22' => 54901,
                '25' => 54901,
                '28' => 54901,
                '35' => 68376,
                '42' => 68376,
                '48' => 68376,
                '54' => 68376,
                '60' => 68376,
                '76' => 95326,
                '89' => 95326,
                '114' => 95326,
            ],

            // 1 1/4" (32mm)
            '32' => [
                '06' => 54901,
                '10' => 46560,
                '13' => 46560,
                '16' => 46560,
                '19' => 54901,
                '22' => 68376,
                '25' => 68376,
                '28' => 68376,
                '35' => 68376,
                '42' => 68376,
                '48' => 68376,
                '54' => 95326,
                '60' => 95326,
                '76' => 95326,
                '89' => 95326,
                '114' => 95326,
            ],

            // 1 1/2" (40mm) - ALUMINIUM FOIL/ 1.83 MET (VND) / 1.83
            '40' => [
                '06' => 66748,
                '10' => 66748,
                '13' => 66748,
                '16' => 66748,
                '19' => 66748,
                '22' => 66748,
                '25' => 66748,
                '28' => 66748,
                '35' => 66748,
                '42' => 66748,
                '48' => 66748,
                '54' => 93056,
                '60' => 93056,
                '76' => 93056,
                '89' => 93056,
                '114' => 171355,
            ],

            // 2" (50mm) - ALUMINIUM FOIL/ 1.83 MET (VND) / 1.83
            '50' => [
                '06' => 66748,
                '10' => 66748,
                '13' => 66748,
                '16' => 93056,
                '19' => 93056,
                '22' => 93056,
                '25' => 93056,
                '28' => 93056,
                '35' => 93056,
                '42' => 93056,
                '48' => 93056,
                '54' => 93056,
                '60' => 93056,
                '76' => 171355,
                '89' => 171355,
                '114' => 171355,
                '140' => 201392,
            ],
        ];

        foreach ($mFlexTubeAluFoilCosts as $thickness => $aluDiameters) {
            $thicknessStr = (string) $thickness;

            if (! isset($mFlexTubeFoamPrices[$thicknessStr])) {
                continue;
            }

            foreach ($aluDiameters as $diameter => $aluPricePerMeter) {
                $diameterStr = (string) $diameter;
                $foamPricePerMeter = $mFlexTubeFoamPrices[$thicknessStr][$diameterStr] ?? null;

                // Skip sizes that do not exist in M-Flex tube foam or have 0 foam price
                if ($foamPricePerMeter === null || $foamPricePerMeter <= 0) {
                    continue;
                }

                KflexPrice::query()->updateOrCreate(
                    [
                        'product_line' => 'm_flex',
                        'form_type' => 'tube',
                        'variant_type' => 'alu',
                        'thickness' => $thicknessStr,
                        'diameter' => $diameterStr,
                    ],
                    [
                        'brand' => $defaults['brand'],
                        'origin' => $defaults['origin'],
                        'product_name' => KflexPrice::buildDefaultProductName(
                            'm_flex',
                            'tube',
                            $thicknessStr,
                            $diameterStr,
                        ),
                        'unit' => 'm',
                        'price' => $foamPricePerMeter + $aluPricePerMeter,
                    ],
                );
            }
        }

        /**
         * M-Flex Sheet (Tấm) Nett Price VND/m² by variant_type => [thickness (mm) => price].
         *
         * @var array<string, array<string, int>>
         */
        $mFlexSheetPrices = [
            // Foam (Chỉ Foam)
            'foam' => [
                '6' => 69300,
                '10' => 103950,
                '13' => 135135,
                '16' => 184571,
                '19' => 197505,
                '25' => 259875,
                '32' => 422031,
                '40' => 639089,
                '50' => 806965,
            ],

            // SA/AF (Có keo/nhôm)
            'alu_or_glue' => [
                '6' => 129491,
                '10' => 164141,
                '13' => 195326,
                '16' => 244761,
                '19' => 257696,
                '25' => 320066,
                '32' => 482222,
                '40' => 714326,
                '50' => 882202,
            ],

            // SAAF (Có cả keo và nhôm)
            'alu_and_glue' => [
                '6' => 189681,
                '10' => 224331,
                '13' => 255516,
                '16' => 304952,
                '19' => 317886,
                '25' => 380256,
                '32' => 542412,
                '40' => 789566,
                '50' => 957442,
            ],
        ];

        foreach ($mFlexSheetPrices as $variantType => $thicknessPrices) {
            foreach ($thicknessPrices as $thickness => $pricePerSquareMeter) {
                $thicknessStr = (string) $thickness;

                KflexPrice::query()
                    ->where('product_line', 'm_flex')
                    ->where('form_type', 'sheet')
                    ->where('variant_type', $variantType)
                    ->where('thickness', $thicknessStr)
                    ->whereNull('diameter')
                    ->delete();

                KflexPrice::query()->create([
                    'product_line' => 'm_flex',
                    'form_type' => 'sheet',
                    'variant_type' => $variantType,
                    'thickness' => $thicknessStr,
                    'diameter' => null,
                    'brand' => $defaults['brand'],
                    'origin' => $defaults['origin'],
                    'product_name' => KflexPrice::buildDefaultProductName(
                        'm_flex',
                        'sheet',
                        $thicknessStr,
                    ),
                    'unit' => 'm²',
                    'price' => $pricePerSquareMeter,
                ]);
            }
        }

        /**
         * K-Protect Tube Phủ nhôm (alu) Nett Price VND/m by thickness (mm) => [diameter (mm) => price].
         *
         * @var array<string, array<string, int>>
         */
        $kProtectTubeAluPrices = [
            // 3/8" (10mm)
            '10' => [
                '06' => 14769,
                '10' => 16209,
                '13' => 17650,
                '16' => 20171,
                '20' => 23053,
                '22' => 24854,
                '25' => 26295,
                '28' => 27375,
                '32' => 29897,
                '35' => 31338,
                '40' => 37461,
                '42' => 39622,
                '48' => 40342,
                '50' => 40342,
                '54' => 44305,
                '60' => 45385,
                '67' => 51869,
                '76' => 61954,
                '79' => 65196,
                '89' => 68077,
            ],

            // 1/2" (13mm)
            '13' => [
                '06' => 23000,
                '10' => 24000,
                '13' => 26000,
                '16' => 28000,
                '20' => 32000,
                '22' => 33000,
                '25' => 34000,
                '28' => 35000,
                '32' => 38000,
                '35' => 40000,
                '40' => 42000,
                '42' => 45000,
                '48' => 46000,
                '50' => 48000,
                '54' => 53000,
                '60' => 56000,
                '67' => 64000,
                '76' => 68000,
                '79' => 71000,
                '89' => 83000,
                '114' => 108000,
            ],

            // 5/8" (16mm)
            '16' => [
                '06' => 27375,
                '10' => 29176,
                '13' => 31698,
                '16' => 33859,
                '20' => 36380,
                '22' => 38181,
                '25' => 43944,
                '28' => 46466,
                '32' => 52949,
                '35' => 54030,
                '40' => 58352,
                '42' => 61594,
                '48' => 63035,
                '50' => 64836,
                '54' => 71679,
                '60' => 70959,
                '67' => 81405,
                '76' => 90770,
                '79' => 95452,
                '89' => 105898,
                '114' => 118505,
                '140' => 132913,
                '168' => 188743,
                '220' => 246735,
                '273' => 265825,
            ],

            // 3/4" (20mm)
            '20' => [
                '06' => 31698,
                '10' => 36380,
                '13' => 40342,
                '16' => 43224,
                '20' => 46826,
                '22' => 48267,
                '25' => 48987,
                '28' => 51508,
                '32' => 54750,
                '35' => 59433,
                '40' => 61594,
                '42' => 66637,
                '48' => 70599,
                '50' => 74201,
                '54' => 81405,
                '60' => 87168,
                '67' => 99775,
                '76' => 108780,
                '79' => 114543,
                '89' => 125349,
                '114' => 156686,
                '140' => 191264,
                '168' => 377846,
                '220' => 401258,
                '273' => 436558,
            ],

            // 1" (25mm)
            '25' => [
                '06' => 40342,
                '10' => 42143,
                '13' => 44305,
                '16' => 50428,
                '20' => 57272,
                '22' => 59433,
                '25' => 61954,
                '28' => 69878,
                '32' => 72040,
                '35' => 77443,
                '40' => 79604,
                '42' => 88248,
                '48' => 94732,
                '50' => 97253,
                '54' => 106979,
                '60' => 109500,
                '67' => 124988,
                '76' => 123548,
                '79' => 129671,
                '89' => 156325,
                '114' => 193786,
                '140' => 232687,
                '168' => 406661,
            ],

            // 1 1/4" (32mm)
            '32' => [
                '13' => 57992,
                '16' => 60513,
                '20' => 65556,
                '22' => 66997,
                '25' => 69518,
                '28' => 72400,
                '32' => 79604,
                '35' => 86087,
                '40' => 89329,
                '42' => 94372,
                '48' => 100855,
                '50' => 109500,
                '54' => 120306,
                '60' => 118865,
                '67' => 136154,
                '76' => 133993,
                '79' => 140837,
                '89' => 183700,
                '114' => 238810,
                '140' => 265465,
                '168' => 497431,
                '220' => 510038,
                '273' => 654836,
            ],

            // 1 1/2" (40mm)
            '40' => [
                '13' => 74201,
                '20' => 79964,
                '22' => 95092,
                '25' => 107339,
                '28' => 119225,
                '32' => 122827,
                '35' => 132552,
                '40' => 135074,
                '42' => 148761,
                '48' => 156325,
                '50' => 162089,
                '54' => 170733,
                '60' => 172894,
                '67' => 197748,
                '76' => 198468,
                '79' => 200629,
                '89' => 216118,
                '114' => 269067,
                '140' => 354073,
                '168' => 582437,
                '220' => 608371,
                '273' => 717870,
            ],

            // 2" (50mm)
            '50' => [
                '20' => 170733,
                '22' => 188023,
                '25' => 191264,
                '28' => 193786,
                '32' => 199909,
                '35' => 205672,
                '40' => 215758,
                '42' => 218999,
                '48' => 220800,
                '50' => 222601,
                '54' => 232327,
                '60' => 240251,
                '67' => 250336,
                '76' => 267986,
                '79' => 281673,
                '89' => 288517,
                '114' => 343987,
                '140' => 383249,
                '168' => 755691,
                '220' => 790270,
                '273' => 874556,
            ],
        ];

        $kProtectDefaults = KflexPrice::defaultBrandAndOrigin('k_protect');

        foreach ($kProtectTubeAluPrices as $thickness => $diameters) {
            $thicknessStr = (string) $thickness;

            foreach ($diameters as $diameter => $pricePerMeter) {
                $diameterStr = (string) $diameter;

                KflexPrice::query()->updateOrCreate(
                    [
                        'product_line' => 'k_protect',
                        'form_type' => 'tube',
                        'variant_type' => 'alu',
                        'thickness' => $thicknessStr,
                        'diameter' => $diameterStr,
                    ],
                    [
                        'brand' => $kProtectDefaults['brand'],
                        'origin' => $kProtectDefaults['origin'],
                        'product_name' => KflexPrice::buildDefaultProductName(
                            'k_protect',
                            'tube',
                            $thicknessStr,
                            $diameterStr,
                        ),
                        'unit' => 'm',
                        'price' => $pricePerMeter,
                    ],
                );
            }
        }

        /**
         * K-Protect Sheet (Tấm) Nett Price VND/m² by variant_type => [thickness (mm) => price].
         *
         * @var array<string, array<string, int>>
         */
        $kProtectSheetPrices = [
            // AF (Có keo/nhôm)
            'alu_or_glue' => [
                '6' => 107975,
                '10' => 149950,
                '13' => 180879,
                '16' => 211255,
                '20' => 252125,
                '25' => 313431,
                '32' => 404284,
                '40' => 490056,
                '50' => 597313,
            ],

            // SAAF (Có cả keo và nhôm)
            'alu_and_glue' => [
                '10' => 208218,
                '13' => 239146,
                '16' => 269523,
                '20' => 310393,
                '25' => 371698,
                '32' => 465313,
                '40' => 551196,
                '50' => 657790,
            ],
        ];

        foreach ($kProtectSheetPrices as $variantType => $thicknessPrices) {
            foreach ($thicknessPrices as $thickness => $pricePerSquareMeter) {
                $thicknessStr = (string) $thickness;

                KflexPrice::query()
                    ->where('product_line', 'k_protect')
                    ->where('form_type', 'sheet')
                    ->where('variant_type', $variantType)
                    ->where('thickness', $thicknessStr)
                    ->whereNull('diameter')
                    ->delete();

                KflexPrice::query()->create([
                    'product_line' => 'k_protect',
                    'form_type' => 'sheet',
                    'variant_type' => $variantType,
                    'thickness' => $thicknessStr,
                    'diameter' => null,
                    'brand' => $kProtectDefaults['brand'],
                    'origin' => $kProtectDefaults['origin'],
                    'product_name' => KflexPrice::buildDefaultProductName(
                        'k_protect',
                        'sheet',
                        $thicknessStr,
                    ),
                    'unit' => 'm²',
                    'price' => $pricePerSquareMeter,
                ]);
            }
        }

        /**
         * ST Sheet (Tấm) Nett Price VND/m² by variant_type => [thickness (mm) => price].
         *
         * @var array<string, array<string, int>>
         */
        $stSheetPrices = [
            // BARE Foam [1.22m width] (Chỉ Foam)
            'foam' => [
                '6' => 141228,
                '10' => 165771,
                '13' => 184001,
                '16' => 247274,
                '19' => 294090,
                '25' => 353035,
                '32' => 537887,
                '40' => 651594,
                '50' => 822827,
            ],

            // SA/AF [1.14m width] (Có keo/nhôm)
            'alu_or_glue' => [
                '6' => 217978,
                '10' => 242521,
                '13' => 260751,
                '16' => 324023,
                '19' => 370839,
                '25' => 429785,
                '32' => 614637,
                '40' => 728343,
                '50' => 899577,
            ],

            // SAAF [1.14m width] (Có cả keo và nhôm)
            'alu_and_glue' => [
                '6' => 294657,
                '10' => 319200,
                '13' => 337430,
                '16' => 400702,
                '19' => 447518,
                '25' => 506464,
                '32' => 691316,
                '40' => 805022,
                '50' => 976255,
            ],
        ];

        $stDefaults = KflexPrice::defaultBrandAndOrigin('st');

        foreach ($stSheetPrices as $variantType => $thicknessPrices) {
            foreach ($thicknessPrices as $thickness => $pricePerSquareMeter) {
                $thicknessStr = (string) $thickness;

                KflexPrice::query()
                    ->where('product_line', 'st')
                    ->where('form_type', 'sheet')
                    ->where('variant_type', $variantType)
                    ->where('thickness', $thicknessStr)
                    ->whereNull('diameter')
                    ->delete();

                KflexPrice::query()->create([
                    'product_line' => 'st',
                    'form_type' => 'sheet',
                    'variant_type' => $variantType,
                    'thickness' => $thicknessStr,
                    'diameter' => null,
                    'brand' => $stDefaults['brand'],
                    'origin' => $stDefaults['origin'],
                    'product_name' => KflexPrice::buildDefaultProductName(
                        'st',
                        'sheet',
                        $thicknessStr,
                    ),
                    'unit' => 'm²',
                    'price' => $pricePerSquareMeter,
                ]);
            }
        }

        /**
         * ST Tube (Ống) Nett Price VND/m: thickness => [diameter => [bare_foam_price, alu_foil_price]].
         *
         * @var array<string, array<string, array{0: int, 1: int}>>
         */
        $stTubePrices = [
            // 3/8" (9mm)
            '09' => [
                '06' => [11137, 42235],
                '10' => [11881, 44274],
                '13' => [13938, 47407],
                '16' => [14683, 50865],
                '19' => [16208, 52136],
                '22' => [16208, 54973],
                '25' => [18230, 56658],
                '28' => [19507, 61978],
                '32' => [20996, 63219],
                '35' => [22273, 69928],
                '42' => [27345, 74155],
                '48' => [31388, 86746],
                '51' => [33410, 88430],
                '54' => [34935, 89701],
                '60' => [40503, 94341],
                '67' => [48873, 116183],
                '73' => [0, 0],
                '76' => [55683, 121858],
                '79' => [0, 0],
                '89' => [67493, 131700],
                '114' => [86503, 177274],
            ],

            // 1/2" (13mm)
            '13' => [
                '06' => [13938, 47407],
                '10' => [15428, 50776],
                '13' => [16705, 55387],
                '16' => [18230, 56658],
                '19' => [20996, 63219],
                '22' => [22273, 64283],
                '25' => [24543, 66175],
                '28' => [27841, 74569],
                '32' => [29366, 75840],
                '35' => [30643, 76904],
                '42' => [35679, 90322],
                '48' => [43305, 96676],
                '51' => [0, 0],
                '54' => [48093, 100666],
                '60' => [53661, 120173],
                '67' => [67599, 131788],
                '73' => [0, 0],
                '76' => [77956, 140418],
                '79' => [81006, 142960],
                '89' => [94696, 184102],
                '108' => [118565, 203992],
                '114' => [125304, 209608],
            ],

            // 3/4" (19mm)
            '19' => [
                '06' => [23798, 60589],
                '10' => [26565, 67860],
                '13' => [28622, 69574],
                '16' => [31388, 77524],
                '19' => [35679, 81100],
                '22' => [38481, 83435],
                '25' => [44546, 88489],
                '28' => [50895, 93780],
                '32' => [54867, 106311],
                '35' => [60010, 110597],
                '42' => [69621, 118606],
                '48' => [80261, 142340],
                '51' => [0, 0],
                '54' => [90617, 150970],
                '60' => [97462, 156674],
                '67' => [107606, 165127],
                '73' => [0, 0],
                '76' => [122005, 206859],
                '79' => [129950, 213480],
                '89' => [146335, 227134],
                '108' => [185633, 259882],
                '114' => [195705, 268276],
                '140' => [230356, 0],
            ],

            // 1" (25mm)
            '25' => [
                '06' => [40787, 85356],
                '10' => [54441, 96735],
                '13' => [57208, 99041],
                '16' => [61251, 102410],
                '19' => [64798, 114587],
                '22' => [68344, 117542],
                '25' => [75189, 123247],
                '28' => [82283, 129158],
                '32' => [84056, 145502],
                '35' => [85829, 146980],
                '38' => [93597, 153452],
                '42' => [90617, 150970],
                '48' => [106577, 164270],
                '51' => [0, 0],
                '54' => [125304, 179875],
                '60' => [133177, 186436],
                '67' => [156940, 235972],
                '73' => [0, 0],
                '76' => [178220, 253705],
                '79' => [175950, 251813],
                '89' => [198471, 270581],
                '108' => [240358, 305486],
                '114' => [253799, 316688],
                '140' => [310121, 387500],
                '168' => [372152, 0],
            ],

            // 1 1/4" (32mm)
            '32' => [
                '19' => [101257, 144970],
                '22' => [107606, 165127],
                '25' => [110124, 167225],
                '28' => [123424, 178309],
                '32' => [125304, 179875],
                '35' => [137114, 189717],
                '42' => [155060, 204672],
                '48' => [168786, 216110],
                '54' => [183540, 258138],
                '60' => [200422, 272207],
                '67' => [213084, 282758],
                '73' => [227838, 295053],
                '76' => [236279, 302087],
                '79' => [232058, 298570],
                '89' => [261602, 323190],
                '108' => [358533, 403965],
                '114' => [390275, 430418],
                '140' => [460145, 0],
                '168' => [534837, 0],
            ],

            // 1 1/2" (40mm)
            '40' => [
                '16' => [121509, 174910],
                '19' => [153996, 201983],
                '22' => [164565, 210790],
                '25' => [162012, 208662],
                '28' => [181412, 224829],
                '35' => [178291, 222228],
                '42' => [194251, 235528],
                '48' => [226135, 262099],
                '54' => [238655, 301555],
                '60' => [248905, 310097],
                '67' => [264298, 322924],
                '73' => [359987, 402665],
                '76' => [300190, 352834],
                '79' => [284797, 340007],
                '89' => [321257, 370390],
                '108' => [397865, 520621],
                '114' => [419783, 538886],
                '140' => [515614, 0],
                '168' => [618539, 0],
            ],

            // 2" (50mm)
            '50' => [
                '19' => [193648, 264049],
                '22' => [213084, 280246],
                '25' => [242592, 304836],
                '28' => [256318, 316274],
                '32' => [254438, 314708],
                '35' => [278484, 334746],
                '42' => [283733, 339120],
                '48' => [313277, 363740],
                '54' => [352326, 396281],
                '60' => [388183, 426162],
                '67' => [401660, 523784],
                '76' => [455676, 568797],
                '79' => [439219, 555083],
                '89' => [494973, 601544],
                '108' => [608005, 695738],
                '114' => [641947, 724022],
                '140' => [701176, 806542],
            ],
        ];

        foreach ($stTubePrices as $thickness => $diameters) {
            $thicknessStr = (string) $thickness;

            foreach ($diameters as $diameter => [$bareFoamPrice, $aluFoilPrice]) {
                $diameterStr = (string) $diameter;
                $productName = KflexPrice::buildDefaultProductName(
                    'st',
                    'tube',
                    $thicknessStr,
                    $diameterStr,
                );

                // K-FLEX ST TUBE BARE FOAM (Chỉ Foam)
                KflexPrice::query()->updateOrCreate(
                    [
                        'product_line' => 'st',
                        'form_type' => 'tube',
                        'variant_type' => 'foam',
                        'thickness' => $thicknessStr,
                        'diameter' => $diameterStr,
                    ],
                    [
                        'brand' => $stDefaults['brand'],
                        'origin' => $stDefaults['origin'],
                        'product_name' => $productName,
                        'unit' => 'm',
                        'price' => $bareFoamPrice,
                    ],
                );

                // K-FLEX ST TUBE WITH ALU FOIL (Phủ nhôm)
                KflexPrice::query()->updateOrCreate(
                    [
                        'product_line' => 'st',
                        'form_type' => 'tube',
                        'variant_type' => 'alu',
                        'thickness' => $thicknessStr,
                        'diameter' => $diameterStr,
                    ],
                    [
                        'brand' => $stDefaults['brand'],
                        'origin' => $stDefaults['origin'],
                        'product_name' => $productName,
                        'unit' => 'm',
                        'price' => $aluFoilPrice,
                    ],
                );
            }
        }
    }
}
