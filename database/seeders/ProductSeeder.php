<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ariston = Category::query()->firstOrCreate([
            'name' => 'Ariston',
            'parent_id' => null,
        ]);
        $subcategory = Category::query()->firstOrCreate([
            'name' => 'Bình nước nóng gián tiếp',
            'parent_id' => $ariston->id,
        ]);
        $heatPumpSubcategory = Category::query()->firstOrCreate([
            'name' => 'Bơm nhiệt nước nóng',
            'parent_id' => $ariston->id,
        ]);

        $products = [
            [
                'name' => 'SLIM3 30B VN',
                'product_title' => 'Dung tích 30L, vừa vặn cho gia đình.',
                'image' => 'SLIM3_30B_VN.png',
                'description' => [
                    '- Dung tích: 30L',
                    '- Thanh đốt 100% đồng chất lượng cao',
                    '- Bình chứa tráng men Titan siêu bền',
                    '- Rơ le nhiệt điện cơ TBST độ chính xác cao',
                    '- Khuyến cáo nhiệt độ an toàn',
                    '- Hệ thống an toàn đồng bộ TSS và ELCB chống giật',
                    '- Công nghệ Flexomix thêm 10% nước nóng',
                    '- Có núm vặn điều chỉnh nhiệt độ',
                    '- Lớp cách nhiệt mật độ cao HDI, giữ nước nóng lên đến 48h',
                    '- Nhiệt độ tối đa: 80oC',
                    '- Công suất: 2.5 kW',
                    '- Kích thước: 757x348x334 WxHxD mm',
                    '- Tổng khối lượng: 15kg',
                    '- Chỉ số bảo vệ chống xâm nhập: IP2.4',
                    '- Áp suất làm việc tối đa: 8bar',
                    '- Nguồn cấp: 1/220/50 (Ph/V/Hz)',
                ],
            ],
            [
                'name' => 'SLIM3 20B VN',
                'product_title' => 'Dung tích 20L · Thiết kế gọn cho gia đình.',
                'image' => 'SLIM3_20B_VN.png',
                'description' => [
                    '- Dung tích: 20L',
                    '- Thanh đốt 100% đồng chất lượng cao',
                    '- Bình chứa tráng men Titan siêu bền',
                    '- Rơ le nhiệt điện cơ TBST độ chính xác cao',
                    '- Khuyến cáo nhiệt độ an toàn',
                    '- Hệ thống an toàn đồng bộ TSS và ELCB chống giật',
                    '- Công nghệ Flexomix thêm 10% nước nóng',
                    '- Có núm vặn điều chỉnh nhiệt độ',
                    '- Lớp cách nhiệt mật độ cao HDI, giữ nước nóng lên đến 48h',
                    '- Nhiệt độ tối đa: 80oC',
                    '- Công suất: 2.5 kW',
                    '- Thời gian gia nhiệt 25 phút',
                    '- Tổn thất nhiệt: 0.65 KWh/24hr',
                    '- Kích thước: 702x291x299 WxHxD mm',
                    '- Tổng khối lượng: 11.6kg',
                    '- Chỉ số bảo vệ chống xâm nhập: IP2.4',
                    '- Áp suất làm việc tối đa: 8bar',
                    '- Nguồn cấp: 1/220/50 (Ph/V/Hz)',
                ],
            ],
            [
                'name' => 'AN3 15 B VN',
                'product_title' => 'Model mới, sang trọng, lịch sự.',
                'image' => 'AN3_15B_VN.png',
                'description' => [
                    '- Dung tích: 15L',
                    '- Thanh đốt 100% đồng chất lượng cao',
                    '- Công nghệ bình chứa tráng men Titan',
                    '- Rơ le nhiệt điện cơ TBST độ chính xác cao',
                    '- Khuyến cáo nhiệt độ an toàn',
                    '- Hệ thống an toàn đồng bộ TSS và ELCB chống giật',
                    '- Công nghệ Flexomix thêm 10% nước nóng',
                    '- Có núm vặn điều chỉnh nhiệt độ',
                    '- Lớp cách nhiệt mật độ cao HDI, giữ nước nóng lên đến 48h',
                    '- Nhiệt độ tối đa: 75oC',
                    '- Công suất: 2.5 kW',
                    '- Kích thước: 360x360x318 WxHxD mm',
                    '- Tổng khối lượng: 10.5kg',
                    '- Chỉ số bảo vệ chống xâm nhập: IPX1',
                    '- Áp suất làm việc tối đa: 7.5 bar',
                    '- Nguồn cấp: 1/220/50 (Ph/V/Hz)',
                ],
            ],
            [
                'name' => 'AN3 30B VN',
                'product_title' => 'Sang trọng và lịch sự, dung tích 30L.',
                'image' => 'AN3_30B_VN.png',
                'description' => [
                    '- Dung tích: 30L',
                    '- Thanh đốt 100% đồng chất lượng cao',
                    '- Công nghệ bình chứa tráng men Titan',
                    '- Rơ le nhiệt điện cơ TBST độ chính xác cao',
                    '- Khuyến cáo nhiệt độ an toàn',
                    '- Hệ thống an toàn đồng bộ TSS và ELCB chống giật',
                    '- Công nghệ Flexomix thêm 10% nước nóng',
                    '- Có núm vặn điều chỉnh nhiệt độ',
                    '- Lớp cách nhiệt mật độ cao HDI, giữ nước nóng lên đến 48h',
                    '- Nhiệt độ tối đa: 75oC',
                    '- Công suất: 2.5 kW',
                    '- Kích thước: 447x447x380 WxHxD mm',
                    '- Tổng khối lượng: 16kg',
                    '- Chỉ số bảo vệ chống xâm nhập: IPX1',
                    '- Áp suất làm việc tối đa: 7.5 bar',
                    '- Nguồn cấp: 1/220/50 (Ph/V/Hz)',
                ],
            ],
            [
                'name' => 'PRO R S 30 SH 2.5 FE',
                'product_title' => 'Bình nước nóng 30L nhập khẩu.',
                'image' => 'TQN.png',
                'description' => [
                    '- Dung tích: 30L',
                    '- Phù hợp nhiều vị trí lắp đặt ngang: treo tường/trần, đặt sàn',
                    '- Thanh đốt thép không gỉ chất lượng cao',
                    '- Công nghệ bình chứa tráng men Titan',
                    '- Rơ le nhiệt điện cơ TBST độ chính xác cao',
                    '- Hệ thống an toàn đồng bộ TSS và ELCB chống giật',
                    '- Công nghệ Flexomix nâng cao hiệu suất làm nóng',
                    '- Có núm vặn điều chỉnh nhiệt độ kích thước lớn',
                    '- Lớp cách nhiệt mật độ cao HDI, giữ nước nóng lên đến 48h',
                    '- Nhiệt độ tối đa: 75oC',
                    '- Công suất: 2.5 kW',
                    '- Kích thước tổng quan: 584x229x353x373x64.5 mm',
                    '- Tổng khối lượng: 13kg',
                    '- Chỉ số bảo vệ chống xâm nhập: IPX1',
                    '- Áp suất làm việc tối đa: 8 bar',
                    '- Nguồn cấp: 1/230/50 (Ph/V/Hz)',
                ],
            ],

            [
                'name' => 'PRO R S 40 SH 2.5 FE',
                'product_title' => 'Dành cho hộ gia đình.',
                'image' => 'TQN.png',
                'description' => [
                    '- Dung tích: 40L',
                    '- Phù hợp nhiều vị trí lắp đặt ngang: treo tường/trần, đặt sàn',
                    '- Thanh đốt thép không gỉ chất lượng cao',
                    '- Công nghệ bình chứa tráng men Titan',
                    '- Rơ le nhiệt điện cơ TBST độ chính xác cao',
                    '- Hệ thống an toàn đồng bộ TSS và ELCB chống giật',
                    '- Công nghệ Flexomix nâng cao hiệu suất làm nóng',
                    '- Có núm vặn điều chỉnh nhiệt độ kích thước lớn',
                    '- Lớp cách nhiệt mật độ cao giữ nước nóng lâu hơn',
                    '- Nhiệt độ tối đa: 75oC',
                    '- Công suất: 2.5 kW',
                    '- Kích thước tổng quan: 719x373x353x373x64.5 mm',
                    '- Tổng khối lượng: 17kg',
                    '- Chỉ số bảo vệ chống xâm nhập: IPX1',
                    '- Áp suất làm việc tối đa: 8 bar',
                    '- Nguồn cấp: 1/230/50 (Ph/V/Hz)',
                ],
            ],

            [
                'name' => 'PRO R 50 V 2.5 FE',
                'product_title' => 'Đầy đủ nước nóng cho cả gia đình.',
                'image' => 'TQD.png',
                'description' => [
                    '- Dung tích: 50L',
                    '- Phù hợp nhiều vị trí lắp đặt đứng: treo tường/trần, đặt sàn',
                    '- Thanh đốt thép không gỉ chất lượng cao',
                    '- Công nghệ bình chứa tráng men Titan',
                    '- Rơ le nhiệt điện cơ TBST độ chính xác cao',
                    '- Hệ thống an toàn đồng bộ TSS và ELCB chống giật',
                    '- Công nghệ Flexomix nâng cao hiệu suất làm nóng',
                    '- Có núm vặn điều chỉnh nhiệt độ kích thước lớn',
                    '- Lớp cách nhiệt mật độ cao giữ nước nóng lâu hơn',
                    '- Nhiệt độ tối đa: 75oC',
                    '- Công suất: 2.5 kW',
                    '- Kích thước tổng quan: 553x165x450x470x163 mm',
                    '- Tổng khối lượng: 19kg',
                    '- Chỉ số bảo vệ chống xâm nhập: IPX1',
                    '- Áp suất làm việc tối đa: 8 bar',
                    '- Nguồn cấp: 1/230/50 (Ph/V/Hz)',
                ],
            ],

            [
                'name' => 'PRO R 50 SH 2.5 FE',
                'product_title' => 'Đầy đủ nước nóng cho cả gia đình.',
                'image' => 'TQN.png',
                'description' => [
                    '- Dung tích: 50L',
                    '- Phù hợp nhiều vị trí lắp đặt ngang: treo tường/trần, đặt sàn',
                    '- Thanh đốt thép không gỉ chất lượng cao',
                    '- Công nghệ bình chứa tráng men Titan',
                    '- Rơ le nhiệt điện cơ TBST độ chính xác cao',
                    '- Hệ thống an toàn đồng bộ TSS và ELCB chống giật',
                    '- Công nghệ Flexomix nâng cao hiệu suất làm nóng',
                    '- Có núm vặn điều chỉnh nhiệt độ kích thước lớn',
                    '- Lớp cách nhiệt mật độ cao giữ nước nóng lâu hơn',
                    '- Nhiệt độ tối đa: 75oC',
                    '- Công suất: 2.5 kW',
                    '- Kích thước tổng quan: 837x491x353x373x64.5 mm',
                    '- Tổng khối lượng: 19kg',
                    '- Chỉ số bảo vệ chống xâm nhập: IPX1',
                    '- Áp suất làm việc tối đa: 8 bar',
                    '- Nguồn cấp: 1/230/50 (Ph/V/Hz)',
                ],
            ],

            [
                'name' => 'PRO R 80 V 2.5 FE',
                'product_title' => 'Phù hợp cho bồn tắm.',
                'image' => 'TQD.png',
                'description' => [
                    '- Dung tích: 80L',
                    '- Phù hợp nhiều vị trí lắp đặt đứng: treo tường/trần, đặt sàn',
                    '- Thanh đốt thép không gỉ chất lượng cao',
                    '- Công nghệ bình chứa tráng men Titan',
                    '- Rơ le nhiệt điện cơ TBST độ chính xác cao',
                    '- Hệ thống an toàn đồng bộ TSS và ELCB chống giật',
                    '- Công nghệ Flexomix nâng cao hiệu suất làm nóng',
                    '- Có núm vặn điều chỉnh nhiệt độ kích thước lớn',
                    '- Lớp cách nhiệt mật độ cao giữ nước nóng lâu hơn',
                    '- Nhiệt độ tối đa: 75oC',
                    '- Công suất: 2.5 kW',
                    '- Kích thước tổng quan: 758x165x450x470x163 mm',
                    '- Tổng khối lượng: 22kg',
                    '- Chỉ số bảo vệ chống xâm nhập: IPX1',
                    '- Áp suất làm việc tối đa: 8 bar',
                    '- Nguồn cấp: 1/230/50 (Ph/V/Hz)',
                ],
            ],

            [
                'name' => 'PRO R 80 H 2.5 FE',
                'product_title' => 'Phù hợp cho bồn tắm.',
                'image' => 'TQN.png',
                'description' => [
                    '- Dung tích: 80L',
                    '- Phù hợp nhiều vị trí lắp đặt ngang: treo tường/trần, đặt sàn',
                    '- Thanh đốt thép không gỉ chất lượng cao',
                    '- Công nghệ bình chứa tráng men Titan',
                    '- Rơ le nhiệt điện cơ TBST độ chính xác cao',
                    '- Hệ thống an toàn đồng bộ TSS và ELCB chống giật',
                    '- Công nghệ Flexomix nâng cao hiệu suất làm nóng',
                    '- Có núm vặn điều chỉnh nhiệt độ kích thước lớn',
                    '- Lớp cách nhiệt mật độ cao giữ nước nóng lâu hơn',
                    '- Nhiệt độ tối đa: 75oC',
                    '- Công suất: 2.5 kW',
                    '- Kích thước tổng quan: 758x335x450x470x113 mm',
                    '- Tổng khối lượng: 22kg',
                    '- Chỉ số bảo vệ chống xâm nhập: IPX1',
                    '- Áp suất làm việc tối đa: 8 bar',
                    '- Nguồn cấp: 1/230/50 (Ph/V/Hz)',
                ],
            ],

            [
                'name' => 'PRO R 100 V 2.5 FE',
                'product_title' => 'Phù hợp cho bồn tắm cỡ lớn.',
                'image' => 'TQD.png',
                'description' => [
                    '- Dung tích: 100L',
                    '- Phù hợp nhiều vị trí lắp đặt đứng: treo tường/trần, đặt sàn',
                    '- Thanh đốt thép không gỉ chất lượng cao',
                    '- Công nghệ bình chứa tráng men Titan',
                    '- Rơ le nhiệt điện cơ TBST độ chính xác cao',
                    '- Hệ thống an toàn đồng bộ TSS và ELCB chống giật',
                    '- Công nghệ Flexomix nâng cao hiệu suất làm nóng',
                    '- Có núm vặn điều chỉnh nhiệt độ kích thước lớn',
                    '- Lớp cách nhiệt mật độ cao giữ nước nóng lâu hơn',
                    '- Nhiệt độ tối đa: 75oC',
                    '- Công suất: 2.5 kW',
                    '- Kích thước tổng quan: 913x165x450x470x166 mm',
                    '- Tổng khối lượng: 26kg',
                    '- Chỉ số bảo vệ chống xâm nhập: IPX1',
                    '- Áp suất làm việc tối đa: 8 bar',
                    '- Nguồn cấp: 1/230/50 (Ph/V/Hz)',
                ],
            ],

            [
                'name' => 'PRO R 100 H 2.5 FE',
                'product_title' => 'Phù hợp cho bồn tắm cỡ lớn.',
                'image' => 'TQN.png',
                'description' => [
                    '- Dung tích: 100L',
                    '- Phù hợp nhiều vị trí lắp đặt ngang: treo tường/trần, đặt sàn',
                    '- Thanh đốt thép không gỉ chất lượng cao',
                    '- Công nghệ bình chứa tráng men Titan',
                    '- Rơ le nhiệt điện cơ TBST độ chính xác cao',
                    '- Hệ thống an toàn đồng bộ TSS và ELCB chống giật',
                    '- Công nghệ Flexomix nâng cao hiệu suất làm nóng',
                    '- Có núm vặn điều chỉnh nhiệt độ kích thước lớn',
                    '- Lớp cách nhiệt mật độ cao giữ nước nóng lâu hơn',
                    '- Nhiệt độ tối đa: 75oC',
                    '- Công suất: 2.5 kW',
                    '- Kích thước tổng quan: 913x487x450x470x113 mm',
                    '- Tổng khối lượng: 26kg',
                    '- Chỉ số bảo vệ chống xâm nhập: IPX1',
                    '- Áp suất làm việc tối đa: 8 bar',
                    '- Nguồn cấp: 1/230/50 (Ph/V/Hz)',
                ],
            ],

            [
                'name' => 'ARI 150 HORB 560 THER MO EU',
                'product_title' => 'Bình nước nóng gián tiếp dung tích cực lớn.',
                'image' => 'TQN.png',
                'description' => [
                    '- Dung tích: 150L',
                    '- Phù hợp nhiều vị trí lắp đặt treo tường/trần, đặt sàn',
                    '- Thanh đốt 100% đồng chất lượng cao',
                    '- Công nghệ bình chứa tráng men Titan',
                    '- Rơ le nhiệt điện cơ TBST độ chính xác cao',
                    '- Hệ thống an toàn đồng bộ TSS và ELCB chống giật',
                    '- Công nghệ Flexomix nâng cao hiệu suất làm nóng',
                    '- Có núm vặn điều chỉnh nhiệt độ kích thước lớn',
                    '- Lớp cách nhiệt mật độ cao giữ nước nóng lâu hơn',
                    '- Nhiệt độ tối đa: 75oC',
                    '- Công suất: 2 kW',
                    '- Kích thước tổng quan: 565x1010mm',
                    '- Tổng khối lượng: 38kg',
                    '- Chỉ số bảo vệ chống xâm nhập: IP25',
                    '- Áp suất làm việc tối đa: 8 bar',
                    '- Nguồn cấp: 1/230/50 (Ph/V/Hz)',
                ],
            ],

            [
                'name' => 'AR-40 PTP HX',
                'subcategory' => 'Bơm nhiệt nước nóng',
                'product_title' => 'Bơm nhiệt chất lượng cao.',
                'image' => 'HP48.png',
                'description' => [
                    '- Công suất làm nóng: 48 kW',
                    '- Lưu lượng làm nóng: 1031 L/h',
                    '- Hệ số hiệu quả: 4.81 kW',
                    '- Công suất điện cực đại: 14.3 kW',
                    '- Dòng điện cực đại: 24.2 A',
                    '- Nguồn điện: 380-415V/3N-/50Hz',
                    '- Làm nóng nước lên tới 60°C',
                    '- Khoảng nhiệt độ làm việc: -15~43 °C',
                    '- Bộ trao đổi nhiệt dạng ống chùm',
                    '- Môi chất lạnh R410a thân thiện môi trường',
                    '- Số lượng quạt/Công suất mô tơ quạt: 2/200x2 W',
                    '- Cánh quạt thiết kế đặc biệt tạo tiếng ồn thấp',
                    '- Loại máy nén/thương hiệu máy nén: 2xScroll/Copeland',
                    '- Van tiết lưu điện tử',
                    '- Màn hình màu 5 inch hiển thị đa chức năng',
                    '- Cảm biến áp suất và nhiệt độ',
                    '- Hệ thống chống đông nhờ van 4 chiều được thiết kế đặc biệt',
                    '- Bảo vệ quá dòng và quá nhiệt',
                ],
            ],

            [
                'name' => 'AR-80PTP HX',
                'subcategory' => 'Bơm nhiệt nước nóng',
                'product_title' => 'Bơm nhiệt công suất lớn.',
                'image' => 'HP80.png',
                'description' => [
                    '- Công suất làm nóng: 80 kW',
                    '- Lưu lượng làm nóng: 1720 L/h',
                    '- Hệ số hiệu quả: 4.63 kW',
                    '- Công suất điện cực đại: 23.82 kW',
                    '- Dòng điện cực đại: 41.3 A',
                    '- Nguồn điện: 380-415V/3N-/50Hz',
                    '- Làm nóng nước lên tới 60°C',
                    '- Khoảng nhiệt độ làm việc: -16~48 °C',
                    '- Bộ trao đổi nhiệt dạng ống chùm',
                    '- Môi chất lạnh R410a thân thiện môi trường',
                    '- Số lượng quạt/Công suất mô tơ quạt: 2/500x2 /W',
                    '- Cánh quạt thiết kế đặc biệt tạo tiếng ồn thấp',
                    '- Loại máy nén/thương hiệu máy nén: 2xScroll/Copeland',
                    '- Van tiết lưu điện tử',
                    '- Màn hình màu 5 inch hiển thị đa chức năng',
                    '- Cảm biến áp suất và nhiệt độ',
                    '- Hệ thống chống đông nhờ van 4 chiều được thiết kế đặc biệt',
                    '- Bảo vệ quá dòng và quá nhiệt',
                ],
            ],
            [
                'name' => 'KFlex ST',
                'category' => 'K-FLEX',
                'subcategory' => 'Cao su lưu hóa NBR',
                'product_title' => 'Dòng sản phẩm bảo ôn cao cấp phù hợp cho văn phòng khách sạn .',
                'image' => 'ST.png',
                'description' => [
                    '- vật liệu: Cao su lưu hóa NBR',
                    '-Tỷ trọng: 48-70kg/m3',
                    '- Phạm vi nhiệt độ: -200°C~116°C',
                    '-Hệ số dẫn nhiệt: <0.034 W/mK tại 20°C (EN 12667, ISO 8497)',
                    '-Độ thẩm thấu hơi nước: 0.92 x10^-11 g/Pa.s.m (ASTM E96)',
                    '-Độ hấp thụ nước theo thể tích: ≤ 0.1 % (ASTM C209)',
                    '- Chứng chỉ: FM Approved; UL94 V-0, 5VA; BS 476 part 6&7, Class 0; ASTM E84',
                    '-Chỉ số lan truyền ngọn lửa FSI ≤25 (ASTM E84)',
                    '-Chỉ số sinh khói SDI ≤30 (ASTM E84)',

                ],
            ],
        ];

        foreach ($products as $productData) {
            $imagePath = base_path("Modules/KbAdmin/resources/assets/images/{$productData['image']}");
            $imageContents = file_get_contents($imagePath);

            if ($imageContents === false) {
                throw new RuntimeException("Unable to read product image at [{$imagePath}].");
            }

            $productCategory = $ariston;
            $productSubcategory = ($productData['subcategory'] ?? null) === 'Bơm nhiệt nước nóng'
                ? $heatPumpSubcategory
                : $subcategory;

            if (isset($productData['category'])) {
                $productCategory = Category::query()->firstOrCreate([
                    'name' => $productData['category'],
                    'parent_id' => null,
                ]);
                $productSubcategory = Category::query()->firstOrCreate([
                    'name' => $productData['subcategory'],
                    'parent_id' => $productCategory->id,
                ]);
            }

            $product = Product::query()->updateOrCreate(
                [
                    'name' => $productData['name'],
                    'category_id' => $productCategory->id,
                ],
                [
                    'product_title' => $productData['product_title'],
                    'category_id' => $productCategory->id,
                    'subcategory_id' => $productSubcategory->id,
                    'description' => implode("\n", $productData['description']),
                    'price' => 0,
                    'quantity' => 0,
                ],
            );

            $storedImagePath = 'products/'.Str::slug($productData['name']).'.png';

            if (! Storage::disk('public')->put($storedImagePath, $imageContents)) {
                throw new RuntimeException("Unable to store product image at [{$storedImagePath}].");
            }

            $product->syncImagePaths([$storedImagePath]);
        }
    }
}
