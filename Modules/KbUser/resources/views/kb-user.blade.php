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

    $rawSiteLogo = trim((string) ($siteSettings['site_logo'] ?? ''));
    $customLogoHref = $rawSiteLogo !== ''
        ? (str_starts_with($rawSiteLogo, 'http') || str_starts_with($rawSiteLogo, '/') ? $rawSiteLogo : '/storage/' . ltrim($rawSiteLogo, '/'))
        : null;
    $rawOgImage = ($siteSettings['seo_og_image'] ?? '') !== '' && ($siteSettings['seo_og_image'] ?? '') !== '/favicon.svg'
        ? $siteSettings['seo_og_image']
        : ($customLogoHref ?: '/favicon.svg');
    $seoImage = str_starts_with($rawOgImage, 'http')
        ? $rawOgImage
        : $canonicalBase . '/' . ltrim($rawOgImage, '/');

    $extraSchemas = [];

    $toArrayPayload = static function (mixed $item): ?array {
        if ($item instanceof \Illuminate\Contracts\Support\Arrayable) {
            return $item->toArray();
        }
        if (is_array($item)) {
            return $item;
        }
        if (is_object($item)) {
            return (array) $item;
        }

        return null;
    };

    $resolveAbsImage = static function (?string $rawPath) use ($canonicalBase): ?string {
        $trimmed = trim((string) $rawPath);
        if ($trimmed === '' || str_starts_with($trimmed, 'data:')) {
            return null;
        }
        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return $trimmed;
        }

        return $canonicalBase . '/' . (str_starts_with($trimmed, '/') ? ltrim($trimmed, '/') : 'storage/' . $trimmed);
    };

    if ($componentName === 'Home') {
        $seoTitle = $siteSettings['seo_home_title'] ?: $seoTitle;
        $seoDescription = $siteSettings['seo_home_description'] ?: $seoDescription;
    } elseif ($componentName === 'products/Index' || $componentName === 'categories/Index') {
        if ($componentName === 'categories/Index') {
            $seoTitle = ($siteSettings['seo_categories_title'] ?? '') ?: ($siteSettings['seo_products_title'] ?: $seoTitle);
            $seoDescription = ($siteSettings['seo_categories_description'] ?? '') ?: ($siteSettings['seo_products_description'] ?: $seoDescription);
        } else {
            $seoTitle = $siteSettings['seo_products_title'] ?: $seoTitle;
            $seoDescription = $siteSettings['seo_products_description'] ?: $seoDescription;
        }

        $selCat = $toArrayPayload($pageProps['selectedCategory'] ?? null);
        $selSub = $toArrayPayload($pageProps['selectedSubcategory'] ?? null);

        if ($selCat !== null) {
            $catName = trim((string) ($selCat['name'] ?? ''));
            $catSlug = trim((string) ($selCat['slug'] ?? $selCat['id'] ?? ''));
            $catDesc = strip_tags((string) ($selCat['description'] ?? ''));
            $catSeoTitle = trim((string) ($selCat['seo_title'] ?? ''));
            $catSeoDesc = trim((string) ($selCat['seo_description'] ?? ''));
            $catSeoKeywords = trim((string) ($selCat['seo_keywords'] ?? ''));
            $catImg = $resolveAbsImage((string) ($selCat['image_url'] ?? $selCat['image'] ?? ''));

            if ($selSub !== null) {
                $subName = trim((string) ($selSub['name'] ?? ''));
                $subSlug = trim((string) ($selSub['slug'] ?? $selSub['id'] ?? ''));
                $subDesc = strip_tags((string) ($selSub['description'] ?? ''));
                $subSeoTitle = trim((string) ($selSub['seo_title'] ?? ''));
                $subSeoDesc = trim((string) ($selSub['seo_description'] ?? ''));
                $subSeoKeywords = trim((string) ($selSub['seo_keywords'] ?? ''));
                $subImg = $resolveAbsImage((string) ($selSub['image_url'] ?? $selSub['image'] ?? '')) ?? $catImg;

                if ($subSeoTitle !== '') {
                    $seoTitle = $subSeoTitle;
                } elseif ($subName !== '') {
                    $seoTitle = $subName . ($catName !== '' ? ' - ' . $catName : '') . ' Chính Hãng | ' . $companyName;
                }

                if ($subSeoDesc !== '') {
                    $seoDescription = $subSeoDesc;
                } elseif ($subDesc !== '') {
                    $seoDescription = \Illuminate\Support\Str::limit($subDesc, 160);
                } elseif ($catSeoDesc !== '') {
                    $seoDescription = $catSeoDesc;
                } elseif ($catDesc !== '') {
                    $seoDescription = \Illuminate\Support\Str::limit($catDesc, 160);
                }

                if ($subSeoKeywords !== '') {
                    $seoKeywords = $subSeoKeywords;
                } elseif ($catSeoKeywords !== '') {
                    $seoKeywords = $catSeoKeywords;
                }

                if ($subImg !== null) {
                    $seoImage = $subImg;
                }

                if ($catSlug !== '' && $subSlug !== '') {
                    $canonicalUrl = $canonicalBase . '/categories/' . $catSlug . '/' . $subSlug;
                }

                $extraSchemas[] = [
                    '@context' => 'https://schema.org',
                    '@type' => 'CollectionPage',
                    'name' => $seoTitle,
                    'description' => $seoDescription,
                    'url' => $canonicalUrl,
                ];
                $extraSchemas[] = [
                    '@context' => 'https://schema.org',
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Trang chủ', 'item' => $canonicalBase . '/'],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Danh mục', 'item' => $canonicalBase . '/categories'],
                        ['@type' => 'ListItem', 'position' => 3, 'name' => $catName, 'item' => $canonicalBase . '/categories/' . $catSlug],
                        ['@type' => 'ListItem', 'position' => 4, 'name' => $subName, 'item' => $canonicalUrl],
                    ],
                ];
            } else {
                if ($catSeoTitle !== '') {
                    $seoTitle = $catSeoTitle;
                } elseif ($catName !== '') {
                    $seoTitle = 'Danh mục ' . $catName . ' Chính Hãng | ' . $companyName;
                }

                if ($catSeoDesc !== '') {
                    $seoDescription = $catSeoDesc;
                } elseif ($catDesc !== '') {
                    $seoDescription = \Illuminate\Support\Str::limit($catDesc, 160);
                }

                if ($catSeoKeywords !== '') {
                    $seoKeywords = $catSeoKeywords;
                }

                if ($catImg !== null) {
                    $seoImage = $catImg;
                }

                if ($catSlug !== '') {
                    $canonicalUrl = $canonicalBase . '/categories/' . $catSlug;
                }

                $extraSchemas[] = [
                    '@context' => 'https://schema.org',
                    '@type' => 'CollectionPage',
                    'name' => $seoTitle,
                    'description' => $seoDescription,
                    'url' => $canonicalUrl,
                ];
                $extraSchemas[] = [
                    '@context' => 'https://schema.org',
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Trang chủ', 'item' => $canonicalBase . '/'],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Danh mục', 'item' => $canonicalBase . '/categories'],
                        ['@type' => 'ListItem', 'position' => 3, 'name' => $catName, 'item' => $canonicalUrl],
                    ],
                ];
            }
        }
    } elseif ($componentName === 'products/Show' && ! empty($pageProps['product'])) {
        $prod = $toArrayPayload($pageProps['product']) ?? [];
        $prodName = trim((string) ($prod['name'] ?? 'Sản phẩm HVAC'));
        $prodSub = trim((string) ($prod['product_title'] ?? ''));
        $prodSlug = trim((string) ($prod['slug'] ?? $prod['id'] ?? ''));
        $customProdTitle = trim((string) ($prod['seo_title'] ?? ''));
        $customProdDesc = trim((string) ($prod['seo_description'] ?? ''));
        $customProdKeywords = trim((string) ($prod['seo_keywords'] ?? ''));

        $seoTitle = $customProdTitle !== ''
            ? $customProdTitle
            : ($prodName . ($prodSub !== '' ? ' - ' . $prodSub : '') . ' | ' . $companyName);

        $rawDesc = strip_tags((string) ($prod['description'] ?? ''));
        $seoDescription = $customProdDesc !== ''
            ? $customProdDesc
            : ($rawDesc !== ''
                ? \Illuminate\Support\Str::limit($rawDesc, 160)
                : ('Thông số kỹ thuật và báo giá ' . $prodName . ' chính hãng tại ' . $companyName . '.'));

        if ($customProdKeywords !== '') {
            $seoKeywords = $customProdKeywords;
        }

        if ($prodSlug !== '') {
            $canonicalUrl = $canonicalBase . '/products/' . $prodSlug;
        }

        $firstImg = is_array($prod['image'] ?? null) ? ($prod['image'][0] ?? null) : null;
        $resolvedProdImg = is_string($firstImg) ? $resolveAbsImage($firstImg) : null;
        if ($resolvedProdImg !== null) {
            $seoImage = $resolvedProdImg;
        }

        $ogType = 'product';
        $brandName = (string) data_get($prod, 'category.name', $companyName);
        $subCatName = (string) data_get($prod, 'subcategory.name', $brandName);
        $productSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $prodName,
            'sku' => 'SP-' . ($prod['id'] ?? '0'),
            'category' => $subCatName,
            'description' => $seoDescription,
            'image' => [$seoImage],
            'brand' => [
                '@type' => 'Brand',
                'name' => $brandName,
            ],
            'offers' => [
                '@type' => 'Offer',
                'url' => $canonicalUrl,
                'priceCurrency' => 'VND',
                'price' => (float) ($prod['price'] ?? 0),
                'availability' => 'https://schema.org/InStock',
                'seller' => [
                    '@type' => 'Organization',
                    'name' => $companyName,
                ],
            ],
        ];

        $reviewsCount = (int) ($pageProps['reviewsCount'] ?? 0);
        $averageRating = (float) ($pageProps['averageRating'] ?? 0);
        if ($reviewsCount > 0 && $averageRating > 0) {
            $productSchema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $averageRating,
                'reviewCount' => $reviewsCount,
                'bestRating' => 5,
                'worstRating' => 1,
            ];
        }

        $extraSchemas[] = $productSchema;
        $extraSchemas[] = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Trang chủ', 'item' => $canonicalBase . '/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Sản phẩm', 'item' => $canonicalBase . '/products'],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $prodName, 'item' => $canonicalUrl],
            ],
        ];
    } elseif ($componentName === 'featured projects/Index') {
        $seoTitle = $siteSettings['seo_projects_title'] ?: $seoTitle;
        $seoDescription = $siteSettings['seo_projects_description'] ?: $seoDescription;
    } elseif ($componentName === 'featured projects/Show' && ! empty($pageProps['project'])) {
        $proj = $toArrayPayload($pageProps['project']) ?? [];
        $projName = trim((string) ($proj['name'] ?? 'Dự án tiêu biểu'));
        $projSlug = trim((string) ($proj['slug'] ?? $proj['id'] ?? ''));
        $projLocation = trim((string) ($proj['location'] ?? ''));
        $customProjTitle = trim((string) ($proj['seo_title'] ?? ''));
        $customProjDesc = trim((string) ($proj['seo_description'] ?? ''));
        $customProjKeywords = trim((string) ($proj['seo_keywords'] ?? ''));

        $seoTitle = $customProjTitle !== ''
            ? $customProjTitle
            : ($projName . ($projLocation !== '' ? ' (' . $projLocation . ')' : '') . ' - Dự Án Tiêu Biểu | ' . $companyName);
        $rawDesc = strip_tags((string) ($proj['description'] ?? ''));
        $seoDescription = $customProjDesc !== ''
            ? $customProjDesc
            : ($rawDesc !== ''
                ? \Illuminate\Support\Str::limit($rawDesc, 160)
                : ('Hồ sơ cung ứng vật tư cơ điện MEP cho dự án ' . $projName . ' tại ' . $companyName . '.'));
        if ($customProjKeywords !== '') {
            $seoKeywords = $customProjKeywords;
        }
        if ($projSlug !== '') {
            $canonicalUrl = $canonicalBase . '/featured-projects/' . $projSlug;
        }
        $projImg = $resolveAbsImage((string) ($proj['image_url'] ?? $proj['image'] ?? ''));
        if ($projImg !== null) {
            $seoImage = $projImg;
        }
        $ogType = 'article';
        $extraSchemas[] = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $projName,
            'description' => $seoDescription,
            'keywords' => $seoKeywords,
            'image' => [$seoImage],
            'url' => $canonicalUrl,
            'author' => [
                '@type' => 'Organization',
                'name' => $companyName,
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $companyName,
            ],
        ];
        $extraSchemas[] = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Trang chủ', 'item' => $canonicalBase . '/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Dự án tiêu biểu', 'item' => $canonicalBase . '/featured-projects'],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $projName, 'item' => $canonicalUrl],
            ],
        ];
    } elseif ($componentName === 'news/Index') {
        $seoTitle = $siteSettings['seo_news_title'] ?: $seoTitle;
        $seoDescription = $siteSettings['seo_news_description'] ?: $seoDescription;
    } elseif ($componentName === 'news/Show' && ! empty($pageProps['article'])) {
        $art = $toArrayPayload($pageProps['article']) ?? [];
        $artTitle = trim((string) ($art['title'] ?? 'Bài viết kỹ thuật'));
        $artSlug = trim((string) ($art['slug'] ?? ''));
        $customArtTitle = trim((string) ($art['seo_title'] ?? ''));
        $customArtDesc = trim((string) ($art['seo_description'] ?? ''));
        $customArtKeywords = trim((string) ($art['seo_keywords'] ?? ''));

        $seoTitle = $customArtTitle !== '' ? $customArtTitle : ($artTitle . ' | ' . $companyName);
        $rawExcerpt = strip_tags((string) ($art['excerpt'] ?? $art['content'] ?? ''));
        $seoDescription = $customArtDesc !== ''
            ? $customArtDesc
            : ($rawExcerpt !== ''
                ? \Illuminate\Support\Str::limit($rawExcerpt, 160)
                : $seoDescription);

        if ($customArtKeywords !== '') {
            $seoKeywords = $customArtKeywords;
        }

        if ($artSlug !== '') {
            $canonicalUrl = $canonicalBase . '/news/' . $artSlug;
        }

        $artCover = $resolveAbsImage((string) ($art['image_url'] ?? $art['cover_image_url'] ?? ''));
        if ($artCover !== null) {
            $seoImage = $artCover;
        }
        $ogType = 'article';
        $extraSchemas[] = [
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $canonicalUrl,
            ],
            'headline' => $artTitle,
            'description' => $seoDescription,
            'keywords' => $seoKeywords,
            'image' => [$seoImage],
            'datePublished' => (string) ($art['published_at'] ?? now()->toAtomString()),
            'dateModified' => (string) ($art['updated_at'] ?? $art['published_at'] ?? now()->toAtomString()),
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
        $extraSchemas[] = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Trang chủ', 'item' => $canonicalBase . '/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Tin tức & Kỹ thuật', 'item' => $canonicalBase . '/news'],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $artTitle, 'item' => $canonicalUrl],
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

    @if($customLogoHref)
        <link rel="icon" href="{{ $customLogoHref }}">
        <link rel="apple-touch-icon" href="{{ $customLogoHref }}">
    @else
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" type="image/png" sizes="48x48" href="{{ asset('favicon-48x48.png') }}">
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    @endif
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
