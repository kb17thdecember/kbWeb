<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="KB TECH - cung cấp thiết bị và giải pháp kỹ thuật HVAC: điều hòa, thông gió, điện lạnh. Tư vấn, báo giá và thi công dự án nhanh chóng.">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="KB TECH">
    <meta property="og:locale" content="vi_VN">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="KB TECH - Thiết bị &amp; giải pháp kỹ thuật HVAC">
    <meta property="og:description" content="Thiết bị và giải pháp kỹ thuật HVAC: tư vấn, báo giá và thi công dự án.">
    <meta property="og:image" content="{{ asset('apple-touch-icon.png') }}">
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'KB TECH',
            'url' => url('/'),
            'logo' => asset('apple-touch-icon.png'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,500;0,600;1,400;1,500&display=swap" rel="stylesheet">

    @vite('Modules/KbUser/resources/js/kb-user.ts')

    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
