<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HVAC Mart - Bình Nước Nóng Ariston và Bảo Ôn K-Flex</title>
    <meta name="description" content="HVAC Mart cung cấp BÌNH NƯỚC NÓNG ARISTON và BẢO ÔN K-FLEX trên toàn quốc. Hỗ trợ tư vấn kỹ thuật và báo giá cho công trình.">
    <meta name="application-name" content="HVAC Mart">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="HVAC Mart">
    <meta property="og:locale" content="vi_VN">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="HVAC Mart - Bình Nước Nóng Ariston và Bảo Ôn K-Flex">
    <meta property="og:description" content="HVAC Mart cung cấp BÌNH NƯỚC NÓNG ARISTON và BẢO ÔN K-FLEX cho dự án xây dựng trên toàn quốc. Hỗ trợ kỹ thuật, tư vấn kỹ thuật và báo giá cho công trình.">
    <meta property="og:image" content="{{ asset('favicon.svg') }}">
    <meta property="og:image:alt" content="HVAC Mart - thiết bị và vật tư kỹ thuật MEP">
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'HVAC Mart',
            'url' => url('/'),
            'logo' => asset('favicon.svg'),
            'description' => 'Bình nước nóng Ariston, bảo ôn K-Flex và thiết bị HVAC cho công trình.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => 'HVAC Mart',
            'url' => url('/'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
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
