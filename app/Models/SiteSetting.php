<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

class SiteSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'company_name' => 'HVAC Mart',
            'company_tagline' => 'HVAC & ENGINEERING SUPPLY',
            'announcement_text' => 'Giải pháp vật tư & thiết bị · Tư vấn đúng nhu cầu, đồng hành đến hoàn thiện',
            'footer_description' => 'Đầu mối cung ứng thiết bị và vật tư kỹ thuật cho các công trình dân dụng, thương mại và công nghiệp.',
            'copyright_text' => '© 2026 HVAC Mart. Bảo lưu mọi quyền.',
            'hotline' => '0979.807.234',
            'zalo_phone' => '0979807234',
            'contact_email' => 'kbnguyen0311@gmail.com',
            'address' => 'Hà Nội, Việt Nam',
            'working_hours' => 'Thứ 2 – Thứ 7 · 08:00–17:30',
            'facebook_url' => '/contacts',
            'linkedin_url' => '/contacts',
            'zalo_url' => 'https://zalo.me/0979807234',
            'seo_meta_title' => 'HVAC Mart - Bình Nước Nóng Ariston, Bảo Ôn K-Flex',
            'seo_meta_description' => 'HVAC Mart phân phối chính hãng bình nước nóng Ariston, bảo ôn cách nhiệt K-Flex và vật tư cơ điện MEP cho dự án xây dựng toàn quốc. Hỗ trợ kỹ thuật, đầy đủ CO/CQ.',
            'seo_meta_keywords' => 'HVAC Mart, bình nước nóng Ariston, bảo ôn K-Flex, vật tư HVAC, vật tư cơ điện MEP, ống bảo ôn K-Flex, bơm nhiệt Ariston, báo giá vật tư HVAC',
            'seo_og_image' => '/favicon.svg',
            'seo_robots' => 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
            'seo_canonical_domain' => 'https://hvacmart.io.vn',
            'seo_google_verification' => '',
            'seo_google_analytics_id' => '',
            'seo_schema_type' => 'HardwareStore',
            'seo_geo_region' => 'VN-HN',
            'seo_geo_placename' => 'Hà Nội, Việt Nam',
            'seo_home_title' => 'HVAC Mart - Bình Nước Nóng Ariston và Bảo Ôn K-Flex Chính Hãng',
            'seo_home_description' => 'HVAC Mart cung cấp giải pháp bình nước nóng Ariston và bảo ôn K-Flex cho dự án xây dựng trên toàn quốc. Hỗ trợ kỹ thuật, tư vấn MEP và báo giá công trình.',
            'seo_categories_title' => 'Danh Mục Thiết Bị Ariston, Bảo Ôn K-Flex & Vật Tư MEP | HVAC Mart',
            'seo_categories_description' => 'Khám phá đầy đủ các danh mục bình nước nóng Ariston, bơm nhiệt Heatpump, bảo ôn cách nhiệt K-Flex và phụ kiện HVAC chính hãng cho mọi quy mô công trình.',
            'seo_products_title' => 'Danh Mục Sản Phẩm Bình Nước Nóng Ariston & Bảo Ôn K-Flex | HVAC Mart',
            'seo_products_description' => 'Tra cứu bảng giá và thông số kỹ thuật bình nước nóng Ariston, bơm nhiệt Heatpump, ống và tấm bảo ôn cách nhiệt K-Flex chính hãng cho dự án MEP.',
            'seo_projects_title' => 'Dự Án Tiêu Biểu & Hồ Sơ Cung Ứng Vật Tư MEP | HVAC Mart',
            'seo_projects_description' => 'Khám phá các dự án chung cư, tòa nhà văn phòng, khách sạn, bệnh viện và khu công nghiệp tiêu biểu do HVAC Mart cung ứng vật tư Ariston và K-Flex.',
            'seo_news_title' => 'Tin Tức & Cẩm Nang Kỹ Thuật HVAC, Bảo Ôn, Nước Nóng | HVAC Mart',
            'seo_news_description' => 'Cập nhật kiến thức kỹ thuật cơ điện MEP, hướng dẫn tính chọn độ dày bảo ôn K-Flex, giải pháp nước nóng trung tâm Ariston và tin tức dự án.',
            'seo_catalogues_title' => 'Thư Viện Catalogue & Tài Liệu Kỹ Thuật Ariston, K-Flex PDF | HVAC Mart',
            'seo_catalogues_description' => 'Tải miễn phí Catalogue, Datasheet, chứng chỉ kỹ thuật và tài liệu Submittal chính hãng của Ariston và bảo ôn K-Flex phục vụ hồ sơ thầu MEP.',
            'seo_contacts_title' => 'Liên Hệ Tư Vấn Kỹ Thuật & Nhận Báo Giá Dự Án | HVAC Mart',
            'seo_contacts_description' => 'Gửi khối lượng BOQ hoặc yêu cầu tư vấn vật tư HVAC, bình nước nóng Ariston, bảo ôn K-Flex để nhận báo giá chiết khấu tốt nhất cho công trình.',
            'seo_indexnow_key' => 'hvacmart8f92a3c4d5e6718293a4b5c6',
            'seo_last_ping_at' => '',
            'seo_last_ping_url' => '',
            'seo_last_ping_status' => 'Tự động kích hoạt khi thêm/sửa Sản phẩm, Dự án, Bài viết',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function allSettings(): array
    {
        $defaults = self::defaults();
        $optionalEmptyKeys = [
            'seo_google_verification',
            'seo_google_analytics_id',
            'seo_last_ping_at',
            'seo_last_ping_url',
        ];

        try {
            $stored = self::query()
                ->whereIn('key', array_keys($defaults))
                ->pluck('value', 'key')
                ->all();
        } catch (Throwable) {
            return $defaults;
        }

        foreach ($defaults as $key => $defaultValue) {
            if (! array_key_exists($key, $stored)) {
                continue;
            }

            $rawValue = trim((string) ($stored[$key] ?? ''));

            if ($rawValue !== '' || in_array($key, $optionalEmptyKeys, true)) {
                $defaults[$key] = $rawValue;
            }
        }

        return $defaults;
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public static function updateSettings(array $values): void
    {
        $allowedKeys = array_keys(self::defaults());

        foreach ($values as $key => $value) {
            if (! in_array($key, $allowedKeys, true)) {
                continue;
            }

            self::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value !== null ? trim($value) : null],
            );
        }
    }

    public static function canonicalBaseUrl(): string
    {
        $settings = self::allSettings();
        $configured = rtrim(trim((string) ($settings['seo_canonical_domain'] ?? '')), '/');

        return $configured !== '' ? $configured : 'https://hvacmart.io.vn';
    }

    public static function canonicalUrl(string $path = '/'): string
    {
        $base = self::canonicalBaseUrl();
        $normalizedPath = '/'.ltrim($path, '/');

        return $normalizedPath === '/' ? $base.'/' : $base.$normalizedPath;
    }
}
