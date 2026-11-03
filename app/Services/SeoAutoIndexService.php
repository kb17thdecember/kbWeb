<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Catalogue;
use App\Models\Category;
use App\Models\FeaturedProject;
use App\Models\Product;
use App\Models\SiteSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
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

    /**
     * @return array{seo_title: string, seo_description: string, seo_keywords: string}
     */
    public static function generateProductSeo(Product $product): array
    {
        $companyName = self::companyName();
        $name = self::cleanPlainText((string) $product->name) ?: 'Sản phẩm HVAC';
        $subtitle = self::cleanPlainText((string) ($product->product_title ?? ''));

        $categoryName = '';
        if ($product->relationLoaded('category') && $product->category !== null) {
            $categoryName = self::cleanPlainText((string) $product->category->name);
        } elseif ($product->category_id) {
            $categoryName = self::cleanPlainText((string) Category::query()->whereKey($product->category_id)->value('name'));
        }

        $subcategoryName = '';
        if ($product->relationLoaded('subcategory') && $product->subcategory !== null) {
            $subcategoryName = self::cleanPlainText((string) $product->subcategory->name);
        } elseif ($product->subcategory_id) {
            $subcategoryName = self::cleanPlainText((string) Category::query()->whereKey($product->subcategory_id)->value('name'));
        }

        $qualifier = $subcategoryName !== ''
            ? $subcategoryName
            : ($categoryName !== '' ? $categoryName : 'Chính Hãng');

        $baseTitle = $name;
        if ($qualifier !== '' && ! Str::contains(mb_strtolower($baseTitle), mb_strtolower($qualifier))) {
            $baseTitle .= ' - '.$qualifier;
        }
        $candidateTitle = $baseTitle.' Chính Hãng | '.$companyName;
        if (mb_strlen($candidateTitle) > 70) {
            $candidateTitle = $baseTitle.' | '.$companyName;
        }
        if (mb_strlen($candidateTitle) < 35 && $subtitle !== '') {
            $candidateTitle = $name.' - '.Str::limit($subtitle, 32, '').' | '.$companyName;
        }
        $seoTitle = Str::limit($candidateTitle, 160, '');

        $rawDesc = self::cleanSpecText((string) ($product->description ?? ''));
        $leadText = $subtitle !== '' ? rtrim($subtitle, '.').'. ' : '';
        $specSummary = $rawDesc !== '' ? $rawDesc : "Thiết bị và vật tư {$name} chất lượng cao cho công trình cơ điện MEP.";
        $descBody = trim($leadText.$specSummary);

        $fullDescription = "{$name}: {$descBody} Phân phối chính hãng tại {$companyName}, đầy đủ CO/CQ, báo giá dự án toàn quốc.";
        $seoDescription = Str::limit(preg_replace('/\s+/u', ' ', $fullDescription) ?? $fullDescription, 160, '...');

        $keywords = array_filter([
            $name,
            "báo giá {$name}",
            "thông số {$name}",
            $subcategoryName,
            $categoryName !== '' ? "{$name} {$categoryName}" : null,
            $categoryName,
            'vật tư HVAC',
            'vật tư cơ điện MEP',
            $companyName,
        ]);

        $seoKeywords = Str::limit(implode(', ', array_values(array_unique($keywords))), 500, '');

        return [
            'seo_title' => $seoTitle,
            'seo_description' => $seoDescription,
            'seo_keywords' => $seoKeywords,
        ];
    }

    /**
     * @return array{seo_title: string, seo_description: string, seo_keywords: string}
     */
    public static function generateCategorySeo(Category $category): array
    {
        $companyName = self::companyName();
        $name = self::cleanPlainText((string) $category->name) ?: 'Danh mục sản phẩm';

        $parentName = '';
        if ($category->parent_id !== null) {
            if ($category->relationLoaded('parent') && $category->parent !== null) {
                $parentName = self::cleanPlainText((string) $category->parent->name);
            } else {
                $parentName = self::cleanPlainText((string) Category::query()->whereKey($category->parent_id)->value('name'));
            }
        }

        if ($category->parent_id !== null) {
            $titlePrefix = $parentName !== '' && ! Str::contains(mb_strtolower($name), mb_strtolower($parentName))
                ? "{$name} {$parentName}"
                : $name;
            $candidateTitle = "{$titlePrefix} Chính Hãng - Thông Số & Báo Giá | {$companyName}";
            if (mb_strlen($candidateTitle) > 70) {
                $candidateTitle = "{$titlePrefix} Chính Hãng | {$companyName}";
            }
        } else {
            $candidateTitle = "Danh Mục {$name} Chính Hãng - Bảng Giá & Catalogue | {$companyName}";
            if (mb_strlen($candidateTitle) > 70) {
                $candidateTitle = "Danh Mục {$name} Chính Hãng | {$companyName}";
            }
        }

        $seoTitle = Str::limit($candidateTitle, 160, '');

        $rawDesc = self::cleanSpecText((string) ($category->description ?: $category->description_2 ?: ''));
        if ($category->parent_id !== null) {
            $brandLabel = $parentName !== '' ? " thương hiệu {$parentName}" : '';
            $fallbackDesc = "Chuyên cung cấp dòng sản phẩm {$name}{$brandLabel} chính hãng tại {$companyName}. Đầy đủ quy cách, chứng chỉ CO/CQ và báo giá chiết khấu cao cho dự án MEP.";
            $fullDesc = $rawDesc !== ''
                ? "{$name}{$brandLabel}: {$rawDesc}. Phân phối chính hãng tại {$companyName}, hỗ trợ kỹ thuật và báo giá công trình."
                : $fallbackDesc;
        } else {
            $fallbackDesc = "Phân phối chính hãng các dòng sản phẩm {$name} cho công trình dân dụng và công nghiệp tại {$companyName}. Hỗ trợ tư vấn kỹ thuật, đầy đủ CO/CQ và catalogue.";
            $fullDesc = $rawDesc !== ''
                ? "Danh mục {$name}: {$rawDesc}. Cung ứng vật tư chính hãng tại {$companyName}, đầy đủ CO/CQ và báo giá dự án toàn quốc."
                : $fallbackDesc;
        }

        $seoDescription = Str::limit(preg_replace('/\s+/u', ' ', $fullDesc) ?? $fullDesc, 160, '...');

        $keywords = array_filter([
            $name,
            $parentName !== '' ? "{$name} {$parentName}" : "danh mục {$name}",
            "bảng giá {$name}",
            "đại lý {$name}",
            "catalogue {$name}",
            $parentName ?: null,
            'vật tư HVAC',
            'vật tư MEP',
            $companyName,
        ]);

        $seoKeywords = Str::limit(implode(', ', array_values(array_unique($keywords))), 500, '');

        return [
            'seo_title' => $seoTitle,
            'seo_description' => $seoDescription,
            'seo_keywords' => $seoKeywords,
        ];
    }

    /**
     * @return array{seo_title: string, seo_description: string, seo_keywords: string}
     */
    public static function generateArticleSeo(Article $article): array
    {
        $companyName = self::companyName();
        $title = self::cleanPlainText((string) $article->title) ?: 'Bài viết kỹ thuật HVAC';

        $candidateTitle = "{$title} | {$companyName}";
        if (mb_strlen($candidateTitle) < 40) {
            $candidateTitle = "{$title} - Cẩm Nang Kỹ Thuật | {$companyName}";
        }
        $seoTitle = Str::limit($candidateTitle, 160, '');

        $rawExcerpt = self::cleanSpecText((string) ($article->excerpt ?: $article->content ?: ''));
        $fullDesc = $rawExcerpt !== ''
            ? $rawExcerpt
            : "Tìm hiểu chi tiết {$title} cùng đội ngũ kỹ sư {$companyName}. Giải pháp vật tư HVAC, bình nước nóng Ariston và bảo ôn K-Flex cho công trình.";

        if (mb_strlen($fullDesc) < 95) {
            $fullDesc = rtrim($fullDesc, '.').". Chuyên đề kỹ thuật cơ điện MEP và giải pháp vật tư công trình từ {$companyName}.";
        }

        $seoDescription = Str::limit(preg_replace('/\s+/u', ' ', $fullDesc) ?? $fullDesc, 160, '...');

        $keywords = array_filter([
            $title,
            'cẩm nang kỹ thuật HVAC',
            'giải pháp cơ điện MEP',
            'bình nước nóng Ariston',
            'bảo ôn K-Flex',
            'vật tư công trình',
            $companyName,
        ]);

        $seoKeywords = Str::limit(implode(', ', array_values(array_unique($keywords))), 500, '');

        return [
            'seo_title' => $seoTitle,
            'seo_description' => $seoDescription,
            'seo_keywords' => $seoKeywords,
        ];
    }

    /**
     * @return array{seo_title: string, seo_description: string, seo_keywords: string}
     */
    public static function generateProjectSeo(FeaturedProject $project): array
    {
        $companyName = self::companyName();
        $name = self::cleanPlainText((string) $project->name) ?: 'Dự án tiêu biểu';
        $location = self::cleanPlainText((string) ($project->location ?? ''));
        $category = self::cleanPlainText((string) ($project->category ?? ''));
        $subcategory = self::cleanPlainText((string) ($project->subcategory ?? ''));

        $candidateTitle = $location !== '' && ! Str::contains(mb_strtolower($name), mb_strtolower($location))
            ? "{$name} ({$location}) - Dự Án Tiêu Biểu | {$companyName}"
            : "{$name} - Dự Án Tiêu Biểu | {$companyName}";
        if (mb_strlen($candidateTitle) > 70) {
            $candidateTitle = "{$name} | {$companyName}";
        }
        $seoTitle = Str::limit($candidateTitle, 160, '');

        $rawDesc = self::cleanSpecText((string) ($project->description ?? ''));
        $scopeLabel = implode(' · ', array_filter([$category, $subcategory]));
        $fallbackDesc = "Hồ sơ dự án tiêu biểu {$name}".($location !== '' ? " tại {$location}" : '').($scopeLabel !== '' ? ", cung ứng hạng mục {$scopeLabel}" : '')." do {$companyName} triển khai.";
        $fullDesc = $rawDesc !== ''
            ? "{$name}: {$rawDesc} Cung ứng vật tư {$scopeLabel} chính hãng tại {$companyName}."
            : $fallbackDesc;

        $seoDescription = Str::limit(preg_replace('/\s+/u', ' ', $fullDesc) ?? $fullDesc, 160, '...');

        $productKeywords = is_array($project->products)
            ? array_map(static fn (mixed $item): string => self::cleanPlainText((string) $item), $project->products)
            : [];

        $keywords = array_filter([
            $name,
            "dự án {$name}",
            $location !== '' ? "dự án HVAC {$location}" : null,
            $category,
            $subcategory,
            ...array_slice($productKeywords, 0, 5),
            'dự án tiêu biểu HVAC',
            'cung ứng vật tư MEP',
            $companyName,
        ]);

        $seoKeywords = Str::limit(implode(', ', array_values(array_unique($keywords))), 500, '');

        return [
            'seo_title' => $seoTitle,
            'seo_description' => $seoDescription,
            'seo_keywords' => $seoKeywords,
        ];
    }

    public static function ensureProductSeo(Product $product, bool $overwrite = false): void
    {
        if (! self::tableHasSeoColumns('products')) {
            return;
        }

        $generated = self::generateProductSeo($product);

        if ($overwrite || trim((string) ($product->seo_title ?? '')) === '') {
            $product->seo_title = $generated['seo_title'];
        }
        if ($overwrite || trim((string) ($product->seo_description ?? '')) === '') {
            $product->seo_description = $generated['seo_description'];
        }
        if ($overwrite || trim((string) ($product->seo_keywords ?? '')) === '') {
            $product->seo_keywords = $generated['seo_keywords'];
        }
    }

    public static function ensureCategorySeo(Category $category, bool $overwrite = false): void
    {
        if (! self::tableHasSeoColumns('categories')) {
            return;
        }

        $generated = self::generateCategorySeo($category);

        if ($overwrite || trim((string) ($category->seo_title ?? '')) === '') {
            $category->seo_title = $generated['seo_title'];
        }
        if ($overwrite || trim((string) ($category->seo_description ?? '')) === '') {
            $category->seo_description = $generated['seo_description'];
        }
        if ($overwrite || trim((string) ($category->seo_keywords ?? '')) === '') {
            $category->seo_keywords = $generated['seo_keywords'];
        }
    }

    public static function ensureArticleSeo(Article $article, bool $overwrite = false): void
    {
        if (! self::tableHasSeoColumns('articles')) {
            return;
        }

        $generated = self::generateArticleSeo($article);

        if ($overwrite || trim((string) ($article->seo_title ?? '')) === '') {
            $article->seo_title = $generated['seo_title'];
        }
        if ($overwrite || trim((string) ($article->seo_description ?? '')) === '') {
            $article->seo_description = $generated['seo_description'];
        }
        if ($overwrite || trim((string) ($article->seo_keywords ?? '')) === '') {
            $article->seo_keywords = $generated['seo_keywords'];
        }
    }

    public static function ensureProjectSeo(FeaturedProject $project, bool $overwrite = false): void
    {
        if (! self::tableHasSeoColumns('featured_projects')) {
            return;
        }

        $generated = self::generateProjectSeo($project);

        if ($overwrite || trim((string) ($project->seo_title ?? '')) === '') {
            $project->seo_title = $generated['seo_title'];
        }
        if ($overwrite || trim((string) ($project->seo_description ?? '')) === '') {
            $project->seo_description = $generated['seo_description'];
        }
        if ($overwrite || trim((string) ($project->seo_keywords ?? '')) === '') {
            $project->seo_keywords = $generated['seo_keywords'];
        }
    }

    public static function ensureSeoSchema(): void
    {
        $tables = [
            'products' => 'description',
            'categories' => 'description_2',
            'articles' => 'content',
            'featured_projects' => 'description',
        ];

        foreach ($tables as $table => $afterColumn) {
            try {
                if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'seo_title')) {
                    Schema::table($table, function (Blueprint $blueprint) use ($afterColumn): void {
                        $blueprint->string('seo_title', 180)->nullable()->after($afterColumn);
                        $blueprint->string('seo_description', 350)->nullable()->after('seo_title');
                        $blueprint->string('seo_keywords', 500)->nullable()->after('seo_description');
                    });
                }
            } catch (Throwable) {
                // Ignore schema modification errors on read-only connections
            }
        }
    }

    /**
     * @return array{products: int, categories: int, articles: int, projects: int}
     */
    public static function syncAllEntitiesSeo(bool $overwrite = false): array
    {
        self::ensureSeoSchema();

        $categoriesUpdated = 0;
        $productsUpdated = 0;
        $articlesUpdated = 0;
        $projectsUpdated = 0;

        if (self::tableHasSeoColumns('categories')) {
            $categories = Category::query()->with('parent:id,name')->get();
            foreach ($categories as $category) {
                $generated = self::generateCategorySeo($category);
                $category->forceFill([
                    'seo_title' => ($overwrite || trim((string) $category->seo_title) === '') ? $generated['seo_title'] : $category->seo_title,
                    'seo_description' => ($overwrite || trim((string) $category->seo_description) === '') ? $generated['seo_description'] : $category->seo_description,
                    'seo_keywords' => ($overwrite || trim((string) $category->seo_keywords) === '') ? $generated['seo_keywords'] : $category->seo_keywords,
                ])->saveQuietly();
                $categoriesUpdated++;
            }
        }

        if (self::tableHasSeoColumns('products')) {
            $products = Product::query()->with(['category:id,name', 'subcategory:id,name'])->get();
            foreach ($products as $product) {
                $generated = self::generateProductSeo($product);
                $product->forceFill([
                    'seo_title' => ($overwrite || trim((string) $product->seo_title) === '') ? $generated['seo_title'] : $product->seo_title,
                    'seo_description' => ($overwrite || trim((string) $product->seo_description) === '') ? $generated['seo_description'] : $product->seo_description,
                    'seo_keywords' => ($overwrite || trim((string) $product->seo_keywords) === '') ? $generated['seo_keywords'] : $product->seo_keywords,
                ])->saveQuietly();
                $productsUpdated++;
            }
        }

        if (self::tableHasSeoColumns('articles')) {
            $articles = Article::query()->get();
            foreach ($articles as $article) {
                $generated = self::generateArticleSeo($article);
                $article->forceFill([
                    'seo_title' => ($overwrite || trim((string) $article->seo_title) === '') ? $generated['seo_title'] : $article->seo_title,
                    'seo_description' => ($overwrite || trim((string) $article->seo_description) === '') ? $generated['seo_description'] : $article->seo_description,
                    'seo_keywords' => ($overwrite || trim((string) $article->seo_keywords) === '') ? $generated['seo_keywords'] : $article->seo_keywords,
                ])->saveQuietly();
                $articlesUpdated++;
            }
        }

        if (self::tableHasSeoColumns('featured_projects')) {
            $projects = FeaturedProject::query()->get();
            foreach ($projects as $project) {
                $generated = self::generateProjectSeo($project);
                $project->forceFill([
                    'seo_title' => ($overwrite || trim((string) $project->seo_title) === '') ? $generated['seo_title'] : $project->seo_title,
                    'seo_description' => ($overwrite || trim((string) $project->seo_description) === '') ? $generated['seo_description'] : $project->seo_description,
                    'seo_keywords' => ($overwrite || trim((string) $project->seo_keywords) === '') ? $generated['seo_keywords'] : $project->seo_keywords,
                ])->saveQuietly();
                $projectsUpdated++;
            }
        }

        return [
            'products' => $productsUpdated,
            'categories' => $categoriesUpdated,
            'articles' => $articlesUpdated,
            'projects' => $projectsUpdated,
        ];
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
            return SiteSetting::canonicalUrl('/products/'.$model->slug);
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

        if ($model instanceof Category) {
            if ($model->parent_id !== null) {
                $parent = $model->relationLoaded('parent')
                    ? $model->parent
                    : Category::query()->find($model->parent_id, ['id', 'name']);
                $parentSlug = $parent !== null ? $parent->slug : (string) $model->parent_id;

                return SiteSetting::canonicalUrl('/categories/'.$parentSlug.'/'.$model->slug);
            }

            return SiteSetting::canonicalUrl('/categories/'.$model->slug);
        }

        if ($model instanceof Catalogue) {
            if (! $model->is_published) {
                return null;
            }

            return SiteSetting::canonicalUrl('/catalogues');
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

    private static function companyName(): string
    {
        try {
            $settings = SiteSetting::allSettings();

            return trim((string) ($settings['company_name'] ?? 'HVAC Mart')) ?: 'HVAC Mart';
        } catch (Throwable) {
            return 'HVAC Mart';
        }
    }

    private static function cleanPlainText(string $value): string
    {
        $stripped = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $stripped) ?? $stripped);
    }

    private static function cleanSpecText(string $value): string
    {
        $stripped = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = preg_split('/\r\n|\r|\n/u', $stripped) ?: [];
        $cleanedLines = [];

        foreach ($lines as $line) {
            $trimmed = trim(preg_replace('/^[\s\-\•\*\+]+/u', '', $line) ?? $line);
            if ($trimmed !== '') {
                $cleanedLines[] = rtrim($trimmed, '.;,');
            }
        }

        return trim(implode('; ', $cleanedLines));
    }

    private static function tableHasSeoColumns(string $table): bool
    {
        try {
            return Schema::hasColumn($table, 'seo_title');
        } catch (Throwable) {
            return false;
        }
    }
}
