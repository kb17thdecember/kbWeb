<?php

namespace App\Services;

use App\Models\Article;
use App\Models\FeaturedProject;
use App\Models\Product;
use App\Models\SiteSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class SeoAutoIndexService
{
    public static function indexNowKey(): string
    {
        $settings = SiteSetting::allSettings();
        $existing = trim((string) ($settings['seo_indexnow_key'] ?? ''));

        if ($existing !== '') {
            return $existing;
        }

        $generated = Str::lower(Str::random(32));
        SiteSetting::updateSettings(['seo_indexnow_key' => $generated]);

        return $generated;
    }

    public static function scheduleModelPing(Model $model): void
    {
        app()->terminating(function () use ($model): void {
            self::pingModel($model);
        });
    }

    public static function pingModel(Model $model): void
    {
        $url = self::resolveModelUrl($model);

        if ($url === null) {
            return;
        }

        self::pingUrls([$url]);
    }

    public static function resolveModelUrl(Model $model): ?string
    {
        if ($model instanceof Product) {
            return SiteSetting::canonicalUrl('/products/'.$model->id);
        }

        if ($model instanceof FeaturedProject) {
            return SiteSetting::canonicalUrl('/featured-projects/'.$model->slug);
        }

        if ($model instanceof Article) {
            if (! $model->is_published) {
                return null;
            }

            return SiteSetting::canonicalUrl('/news/'.$model->slug);
        }

        return null;
    }

    /**
     * @param  list<string>  $urls
     * @return array{success: bool, urls_count: int, message: string}
     */
    public static function pingUrls(array $urls): array
    {
        $cleanUrls = array_values(array_unique(array_filter($urls)));

        if ($cleanUrls === []) {
            $cleanUrls = [SiteSetting::canonicalUrl('/')];
        }

        $feedUrl = SiteSetting::canonicalUrl('/feed.xml');
        $sitemapUrl = SiteSetting::canonicalUrl('/sitemap.xml');
        $baseUrl = SiteSetting::canonicalBaseUrl();
        $host = (string) (parse_url($baseUrl, PHP_URL_HOST) ?: 'hvacmart.io.vn');
        $indexNowKey = self::indexNowKey();

        $googleHubSuccess = false;
        $indexNowSuccess = false;

        try {
            $hubResponse = Http::timeout(4)
                ->asForm()
                ->post('https://pubsubhubbub.appspot.com/', [
                    'hub.mode' => 'publish',
                    'hub.url' => [
                        $feedUrl,
                        $sitemapUrl,
                    ],
                ]);

            $googleHubSuccess = $hubResponse->successful() || $hubResponse->status() === 204;
        } catch (Throwable) {
            $googleHubSuccess = false;
        }

        try {
            $indexNowResponse = Http::timeout(4)
                ->acceptJson()
                ->post('https://api.indexnow.org/indexnow', [
                    'host' => $host,
                    'key' => $indexNowKey,
                    'keyLocation' => SiteSetting::canonicalUrl('/indexnow-'.$indexNowKey.'.txt'),
                    'urlList' => array_slice($cleanUrls, 0, 100),
                ]);

            $indexNowSuccess = $indexNowResponse->successful() || in_array($indexNowResponse->status(), [200, 202], true);
        } catch (Throwable) {
            $indexNowSuccess = false;
        }

        $statusText = ($googleHubSuccess || $indexNowSuccess)
            ? 'Đã gửi tín hiệu tự động tới Google WebSub Hub & IndexNow'
            : 'Đã ghi nhận vào Sitemap XML & RSS Feed (chờ mạng máy chủ kết nối)';

        SiteSetting::updateSettings([
            'seo_last_ping_at' => now()->format('d/m/Y H:i:s'),
            'seo_last_ping_url' => $cleanUrls[0],
            'seo_last_ping_status' => $statusText,
        ]);

        return [
            'success' => $googleHubSuccess || $indexNowSuccess,
            'urls_count' => count($cleanUrls),
            'message' => $statusText,
        ];
    }
}
