<?php

namespace App\Models;

use Database\Factories\SiteVisitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteVisit extends Model
{
    /** @use HasFactory<SiteVisitFactory> */
    use HasFactory;

    public const MAX_RECENT_VISITS = 20;

    protected $fillable = [
        'session_id',
        'user_id',
        'ip_address',
        'user_agent',
        'path',
        'visited_date',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function pruneOldVisits(int $limit = self::MAX_RECENT_VISITS): int
    {
        if ($limit < 1) {
            return 0;
        }

        $keepIds = self::query()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('id');

        if ($keepIds->count() < $limit) {
            return 0;
        }

        return self::query()
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    /**
     * @return array{
     *     device_type: 'mobile'|'tablet'|'desktop'|'bot',
     *     device_label: string,
     *     device_model: string,
     *     os: string,
     *     browser: string,
     *     device_summary: string
     * }
     */
    public function deviceInfo(): array
    {
        return self::parseUserAgent($this->user_agent);
    }

    /**
     * @return array{
     *     device_type: 'mobile'|'tablet'|'desktop'|'bot',
     *     device_label: string,
     *     device_model: string,
     *     os: string,
     *     browser: string,
     *     device_summary: string
     * }
     */
    public static function parseUserAgent(?string $userAgent): array
    {
        $ua = trim((string) $userAgent);

        if ($ua === '') {
            return [
                'device_type' => 'desktop',
                'device_label' => 'Không xác định',
                'device_model' => 'Thiết bị không rõ',
                'os' => 'Không rõ HĐH',
                'browser' => 'Không rõ trình duyệt',
                'device_summary' => 'Thiết bị không rõ',
            ];
        }

        if (preg_match('/bot|crawl|spider|slurp|facebookexternalhit|mediapartners|adsbot|bingpreview|lighthouse|headlesschrome/i', $ua)) {
            $botName = 'Bot thu thập dữ liệu';
            if (stripos($ua, 'Googlebot') !== false) {
                $botName = 'Googlebot';
            } elseif (stripos($ua, 'bingbot') !== false) {
                $botName = 'Bingbot';
            } elseif (stripos($ua, 'facebookexternalhit') !== false) {
                $botName = 'Facebook Bot';
            } elseif (stripos($ua, 'AhrefsBot') !== false) {
                $botName = 'AhrefsBot';
            }

            return [
                'device_type' => 'bot',
                'device_label' => 'Bot / Tự động',
                'device_model' => $botName,
                'os' => 'Hệ thống tự động',
                'browser' => $botName,
                'device_summary' => $botName,
            ];
        }

        $isTablet = (bool) preg_match('/ipad|tablet|playbook|silk|(android(?!.*mobile))/i', $ua);
        $isMobile = ! $isTablet && (bool) preg_match('/mobile|iphone|ipod|android.*mobile|windows phone|blackberry|opera mini|iemobile/i', $ua);

        $deviceType = $isTablet ? 'tablet' : ($isMobile ? 'mobile' : 'desktop');
        $deviceLabel = match ($deviceType) {
            'mobile' => 'Điện thoại',
            'tablet' => 'Máy tính bảng',
            default => 'Máy tính',
        };

        $os = 'Hệ điều hành khác';
        if (preg_match('/iphone|ipod/i', $ua)) {
            $os = 'iOS';
            if (preg_match('/OS (\d+[._]\d+)/i', $ua, $matches)) {
                $os = 'iOS '.str_replace('_', '.', $matches[1]);
            }
        } elseif (preg_match('/ipad/i', $ua)) {
            $os = 'iPadOS';
            if (preg_match('/OS (\d+[._]\d+)/i', $ua, $matches)) {
                $os = 'iPadOS '.str_replace('_', '.', $matches[1]);
            }
        } elseif (preg_match('/android/i', $ua)) {
            $os = 'Android';
            if (preg_match('/Android\s+([0-9.]+)/i', $ua, $matches)) {
                $os = 'Android '.$matches[1];
            }
        } elseif (preg_match('/windows nt 10\.0/i', $ua)) {
            $os = 'Windows 10/11';
        } elseif (preg_match('/windows nt 6\.3/i', $ua)) {
            $os = 'Windows 8.1';
        } elseif (preg_match('/windows nt 6\.1/i', $ua)) {
            $os = 'Windows 7';
        } elseif (preg_match('/windows/i', $ua)) {
            $os = 'Windows';
        } elseif (preg_match('/mac os x|macintosh/i', $ua)) {
            $os = 'macOS';
        } elseif (preg_match('/cros/i', $ua)) {
            $os = 'ChromeOS';
        } elseif (preg_match('/linux/i', $ua)) {
            $os = 'Linux';
        }

        $deviceModel = match ($deviceType) {
            'mobile' => 'Điện thoại',
            'tablet' => 'Máy tính bảng',
            default => 'Máy tính (PC/Laptop)',
        };

        if (stripos($ua, 'iPhone') !== false) {
            $deviceModel = 'Apple iPhone';
        } elseif (stripos($ua, 'iPad') !== false) {
            $deviceModel = 'Apple iPad';
        } elseif (stripos($ua, 'Macintosh') !== false || stripos($ua, 'Mac OS X') !== false) {
            $deviceModel = 'Apple Mac';
        } elseif (preg_match('/SM-[A-Z0-9]+|Samsung/i', $ua)) {
            $deviceModel = $isTablet ? 'Máy tính bảng Samsung' : 'Điện thoại Samsung';
        } elseif (preg_match('/Xiaomi|Redmi|POCO|Mi\s/i', $ua)) {
            $deviceModel = 'Điện thoại Xiaomi';
        } elseif (preg_match('/OPPO|CPH\d+/i', $ua)) {
            $deviceModel = 'Điện thoại OPPO';
        } elseif (preg_match('/Vivo/i', $ua)) {
            $deviceModel = 'Điện thoại Vivo';
        } elseif (preg_match('/Pixel/i', $ua)) {
            $deviceModel = 'Google Pixel';
        } elseif (stripos($ua, 'Android') !== false) {
            $deviceModel = $isTablet ? 'Máy tính bảng Android' : 'Điện thoại Android';
        } elseif (stripos($ua, 'Windows') !== false) {
            $deviceModel = 'Máy tính Windows';
        } elseif (stripos($ua, 'Linux') !== false) {
            $deviceModel = 'Máy tính Linux';
        }

        $browser = 'Trình duyệt khác';
        if (stripos($ua, 'Zalo') !== false) {
            $browser = 'Zalo In-App';
        } elseif (preg_match('/FBAN|FBAV|FB_IAB/i', $ua)) {
            $browser = 'Facebook In-App';
        } elseif (stripos($ua, 'coc_coc_browser') !== false) {
            $browser = 'Cốc Cốc';
        } elseif (preg_match('/EdgA?\/|EdgiOS\//i', $ua)) {
            $browser = 'Microsoft Edge';
        } elseif (stripos($ua, 'SamsungBrowser') !== false) {
            $browser = 'Samsung Internet';
        } elseif (preg_match('/OPR\/|Opera/i', $ua)) {
            $browser = 'Opera';
        } elseif (preg_match('/Chrome\/|CriOS\//i', $ua)) {
            $browser = 'Google Chrome';
        } elseif (preg_match('/Firefox\/|FxiOS\//i', $ua)) {
            $browser = 'Mozilla Firefox';
        } elseif (stripos($ua, 'Safari/') !== false) {
            $browser = 'Apple Safari';
        }

        return [
            'device_type' => $deviceType,
            'device_label' => $deviceLabel,
            'device_model' => $deviceModel,
            'os' => $os,
            'browser' => $browser,
            'device_summary' => "{$deviceModel} · {$os} · {$browser}",
        ];
    }

    public static function formatPageLabel(?string $path): string
    {
        $normalized = '/'.ltrim(trim((string) $path), '/');

        return match (true) {
            $normalized === '/' => 'Trang chủ',
            $normalized === '/products' => 'Danh sách sản phẩm',
            $normalized === '/products/search' => 'Tìm kiếm sản phẩm',
            str_starts_with($normalized, '/products/') => 'Chi tiết sản phẩm',
            $normalized === '/categories' => 'Danh mục sản phẩm',
            str_starts_with($normalized, '/categories/') => 'Chi tiết danh mục',
            $normalized === '/featured-projects' => 'Dự án tiêu biểu',
            str_starts_with($normalized, '/featured-projects/') => 'Chi tiết dự án',
            $normalized === '/news' => 'Tin tức & Bài viết',
            str_starts_with($normalized, '/news/') => 'Chi tiết bài viết',
            str_starts_with($normalized, '/catalogues') => 'Catalogue & Tài liệu',
            str_starts_with($normalized, '/about-us') => 'Giới thiệu',
            str_starts_with($normalized, '/contacts') => 'Liên hệ & Tư vấn',
            str_starts_with($normalized, '/quotations') => 'Báo giá trực tuyến',
            str_starts_with($normalized, '/my-account') => 'Tài khoản khách hàng',
            default => 'Trang nội dung',
        };
    }
}
