@php
    $siteSettings = $page['props']['siteSettings'] ?? \App\Models\SiteSetting::allSettings();
    $componentName = (string) ($page['component'] ?? '');
    $pageProps = is_array($page['props'] ?? null) ? $page['props'] : [];

    $companyName = $siteSettings['company_name'] ?? 'HVAC Mart';
    $seoTitle = $siteSettings['seo_meta_title'] ?? ($companyName . ' - Bình Nước Nóng Ariston và Bảo Ôn K-Flex');
    $seoDescription = $siteSettings['seo_meta_description'] ?? 'HVAC Mart cung cấp bình nước nóng Ariston và bảo ôn K-Flex trên toàn quốc. Hỗ trợ tư vấn kỹ thuật và báo giá cho công trình.';
    $seoKeywords = $siteSettings['seo_meta_keywords'] ?? 'HVAC Mart, bình nước nóng Ariston, bảo ôn K-Flex, vật tư HVAC, vật tư cơ điện MEP';
    $seoRobots = $siteSettings['seo_robots'] ?? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
    $ogType = 'website';

    $canonicalBase = rtrim((string) ($siteSettings['seo_canonical_domain'] ?? 'https://hvacmart.io.vn'), '/') ?: 'https://hvacmart.io.vn';
    $currentPath = '/' . ltrim(request()->path(), '/');
    if ($currentPath === '//') {
        $currentPath = '/';
    }
    $canonicalUrl = $currentPath === '/' ? $canonicalBase . '/' : $canonicalBase . $currentPath;

    $rawOgImage = $siteSettings['seo_og_image'] ?? '/favicon.svg';
    $seoImage = str_starts_with($rawOgImage, 'http')
        ? $rawOgImage
        : $canonicalBase . '/' . ltrim($rawOgImage, '/');

    $extraSchemas = [];

    if ($componentName === 'Home') {
        $seoTitle = $siteSettings['seo_home_title'] ?: $seoTitle;
        $seoDescription = $siteSettings['seo_home_description'] ?: $seoDescription;
    } elseif ($componentName === 'products/Index' || $componentName === 'categories/Index') {
        $seoTitle = $siteSettings['seo_products_title'] ?: $seoTitle;
        $seoDescription = $siteSettings['seo_products_description'] ?: $seoDescription;
        $selCat = is_array($pageProps['selectedCategory'] ?? null) ? $pageProps['selectedCategory'] : null;
        $selSub = is_array($pageProps['selectedSubcategory'] ?? null) ? $pageProps['selectedSubcategory'] : null;

        if ($selCat !== null) {
            $catName = trim((string) ($selCat['name'] ?? ''));
            $catSlug = trim((string) ($selCat['slug'] ?? $selCat['id'] ?? ''));
            $catDesc = strip_tags((string) ($selCat['description'] ?? ''));

            if ($selSub !== null) {
                $subName = trim((string) ($selSub['name'] ?? ''));
                $subSlug = trim((string) ($selSub['slug'] ?? $selSub['id'] ?? ''));
                $subDesc = strip_tags((string) ($selSub['description'] ?? ''));

                if ($subName !== '') {
                    $seoTitle = $subName . ($catName !== '' ? ' - ' . $catName : '') . ' | ' . $companyName;
                }
                if ($subDesc !== '') {
                    $seoDescription = \Illuminate\Support\Str::limit($subDesc, 160);
                } elseif ($catDesc !== '') {
                    $seoDescription = \Illuminate\Support\Str::limit($catDesc, 160);
                }
                if ($catSlug !== '' && $subSlug !== '') {
                    $canonicalUrl = $canonicalBase . '/categories/' . $catSlug . '/' . $subSlug;
                }
            } else {
                if ($catName !== '') {
                    $seoTitle = 'Danh mục ' . $catName . ' | ' . $companyName;
                }
                if ($catDesc !== '') {
                    $seoDescription = \Illuminate\Support\Str::limit($catDesc, 160);
                }
                if ($catSlug !== '') {
                    $canonicalUrl = $canonicalBase . '/categories/' . $catSlug;
                }
            }
        }
    } elseif ($componentName === 'products/Show' && ! empty($pageProps['product'])) {
        $prod = (array) $pageProps['product'];
        $prodName = trim((string) ($prod['name'] ?? 'Sản phẩm HVAC'));
        $prodSub = trim((string) ($prod['product_title'] ?? ''));
        $seoTitle = $prodName . ($prodSub !== '' ? ' - ' . $prodSub : '') . ' | ' . $companyName;
        $rawDesc = strip_tags((string) ($prod['description'] ?? ''));
        $seoDescription = $rawDesc !== ''
            ? \Illuminate\Support\Str::limit($rawDesc, 160)
            : ('Thông số kỹ thuật và báo giá ' . $prodName . ' chính hãng tại ' . $companyName . '.');
        $firstImg = is_array($prod['image'] ?? null) ? ($prod['image'][0] ?? null) : null;
        if (is_string($firstImg) && $firstImg !== '') {
            $seoImage = str_starts_with($firstImg, 'http')
                ? $firstImg
                : $canonicalBase . '/' . (str_starts_with($firstImg, '/') ? ltrim($firstImg, '/') : 'storage/' . $firstImg);
        }
        $ogType = 'product';
        $extraSchemas[] = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $prodName,
            'description' => $seoDescription,
            'image' => [$seoImage],
            'brand' => [
                '@type' => 'Brand',
                'name' => data_get($prod, 'category.name', $companyName),
            ],
            'offers' => [
                '@type' => 'Offer',
                'url' => $canonicalUrl,
                'priceCurrency' => 'VND',
                'price' => (float) ($prod['price'] ?? 0),
                'availability' => 'https://schema.org/InStock',
            ],
        ];
    } elseif ($componentName === 'featured projects/Index') {
        $seoTitle = $siteSettings['seo_projects_title'] ?: $seoTitle;
        $seoDescription = $siteSettings['seo_projects_description'] ?: $seoDescription;
    } elseif ($componentName === 'featured projects/Show' && ! empty($pageProps['project'])) {
        $proj = (array) $pageProps['project'];
        $projName = trim((string) ($proj['name'] ?? 'Dự án tiêu biểu'));
        $projLocation = trim((string) ($proj['location'] ?? ''));
        $seoTitle = $projName . ($projLocation !== '' ? ' (' . $projLocation . ')' : '') . ' - Dự Án Tiêu Biểu | ' . $companyName;
        $rawDesc = strip_tags((string) ($proj['description'] ?? ''));
        $seoDescription = $rawDesc !== ''
            ? \Illuminate\Support\Str::limit($rawDesc, 160)
            : ('Hồ sơ cung ứng vật tư cơ điện MEP cho dự án ' . $projName . ' tại ' . $companyName . '.');
        $projImg = (string) ($proj['image_url'] ?? $proj['image'] ?? '');
        if ($projImg !== '') {
            $seoImage = str_starts_with($projImg, 'http')
                ? $projImg
                : $canonicalBase . '/' . (str_starts_with($projImg, '/') ? ltrim($projImg, '/') : 'storage/' . $projImg);
        }
        $ogType = 'article';
    } elseif ($componentName === 'news/Index') {
        $seoTitle = $siteSettings['seo_news_title'] ?: $seoTitle;
        $seoDescription = $siteSettings['seo_news_description'] ?: $seoDescription;
    } elseif ($componentName === 'news/Show' && ! empty($pageProps['article'])) {
        $art = (array) $pageProps['article'];
        $artTitle = trim((string) ($art['title'] ?? 'Bài viết kỹ thuật'));
        $seoTitle = $artTitle . ' | ' . $companyName;
        $rawExcerpt = strip_tags((string) ($art['excerpt'] ?? $art['content'] ?? ''));
        $seoDescription = $rawExcerpt !== ''
            ? \Illuminate\Support\Str::limit($rawExcerpt, 160)
            : $seoDescription;
        $artCover = (string) ($art['cover_image_url'] ?? '');
        if ($artCover !== '') {
            $seoImage = str_starts_with($artCover, 'http') ? $artCover : $canonicalBase . '/' . ltrim($artCover, '/');
        }
        $ogType = 'article';
        $extraSchemas[] = [
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => $artTitle,
            'description' => $seoDescription,
            'image' => [$seoImage],
            'datePublished' => (string) ($art['published_at'] ?? now()->toAtomString()),
            'author' => [
                '@type' => 'Organization',
                'name' => (string) ($art['author_name'] ?? $companyName),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $companyName,
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => $canonicalBase . '/favicon.svg',
                ],
            ],
        ];
    } elseif ($componentName === 'catalogues/Index') {
        $seoTitle = $siteSettings['seo_catalogues_title'] ?: $seoTitle;
        $seoDescription = $siteSettings['seo_catalogues_description'] ?: $seoDescription;
    } elseif ($componentName === 'contacts/Index') {
        $seoTitle = $siteSettings['seo_contacts_title'] ?: $seoTitle;
        $seoDescription = $siteSettings['seo_contacts_description'] ?: $seoDescription;
    }

    $organizationSchema = [
        '@context' => 'https://schema.org',
        '@type' => $siteSettings['seo_schema_type'] ?: 'HardwareStore',
        'name' => $companyName,
        'url' => $canonicalBase . '/',
        'logo' => $canonicalBase . '/favicon.svg',
        'image' => $seoImage,
        'description' => $siteSettings['seo_meta_description'] ?? $seoDescription,
        'telephone' => $siteSettings['hotline'] ?? '0979.807.234',
        'email' => $siteSettings['contact_email'] ?? 'kbnguyen0311@gmail.com',
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => $siteSettings['seo_geo_placename'] ?? ($siteSettings['address'] ?? 'Hà Nội, Việt Nam'),
            'addressRegion' => $siteSettings['seo_geo_region'] ?? 'VN-HN',
            'addressCountry' => 'VN',
        ],
    ];

    $websiteSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => $companyName,
        'url' => $canonicalBase . '/',
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => $canonicalBase . '/products?search={search_term_string}',
            'query-input' => 'required name=search_term_string',
        ],
    ];

    $googleVerification = trim((string) ($siteSettings['seo_google_verification'] ?? ''));
    if ($googleVerification !== '' && preg_match('/content=["\']([^"\']+)["\']/i', $googleVerification, $vMatch)) {
        $googleVerification = trim($vMatch[1]);
    }
    $googleAnalyticsId = trim((string) ($siteSettings['seo_google_analytics_id'] ?? ''));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDescription }}">
    @if($seoKeywords !== '')
        <meta name="keywords" content="{{ $seoKeywords }}">
    @endif
    <meta name="application-name" content="{{ $companyName }}">
    <meta name="robots" content="{{ $seoRobots }}">
    <meta name="googlebot" content="{{ $seoRobots }}">
    <meta name="geo.region" content="{{ $siteSettings['seo_geo_region'] ?? 'VN-HN' }}">
    <meta name="geo.placename" content="{{ $siteSettings['seo_geo_placename'] ?? ($siteSettings['address'] ?? 'Hà Nội, Việt Nam') }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <link rel="sitemap" type="application/xml" title="Sitemap" href="{{ $canonicalBase }}/sitemap.xml">
    <link rel="alternate" type="application/rss+xml" title="{{ $companyName }} RSS Feed" href="{{ $canonicalBase }}/feed.xml">

    @if($googleVerification !== '')
        <meta name="google-site-verification" content="{{ $googleVerification }}">
    @endif

    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:site_name" content="{{ $companyName }}">
    <meta property="og:locale" content="vi_VN">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:image" content="{{ $seoImage }}">
    <meta property="og:image:alt" content="{{ $seoTitle }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <meta name="twitter:image" content="{{ $seoImage }}">

    <script type="application/ld+json">
        {!! json_encode($organizationSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script type="application/ld+json">
        {!! json_encode($websiteSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    @foreach($extraSchemas as $schemaItem)
        <script type="application/ld+json">
            {!! json_encode($schemaItem, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
        </script>
    @endforeach

    @if($googleAnalyticsId !== '' && str_starts_with($googleAnalyticsId, 'G-'))
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode($googleAnalyticsId) }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', @json($googleAnalyticsId));
        </script>
    @endif

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="48x48" href="{{ asset('favicon-48x48.png') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,300;1,400;1,500;1,600&family=Playfair+Display:ital,wght@0,400;0,500;0,600;1,400;1,500&display=swap" rel="stylesheet">
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('kbuser-theme');
                var mode = (saved === 'dark' || saved === 'light')
                    ? saved
                    : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                var root = document.documentElement;
                root.style.colorScheme = mode;
                root.setAttribute('data-theme', mode);
                root.style.setProperty('--lightningcss-light', mode === 'dark' ? ' ' : 'initial');
                root.style.setProperty('--lightningcss-dark', mode === 'dark' ? 'initial' : ' ');
            } catch (e) {}
        })();
    </script>

    @vite('Modules/KbUser/resources/js/kb-user.ts')

    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
