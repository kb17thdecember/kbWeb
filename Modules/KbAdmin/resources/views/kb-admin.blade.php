@php
    $siteSettings = $page['props']['siteSettings'] ?? \App\Models\SiteSetting::allSettings();
    $rawSiteLogo = trim((string) ($siteSettings['site_logo'] ?? ''));
    $customLogoHref = $rawSiteLogo !== ''
        ? (str_starts_with($rawSiteLogo, 'http') || str_starts_with($rawSiteLogo, '/') ? $rawSiteLogo : '/storage/' . ltrim($rawSiteLogo, '/'))
        : null;
@endphp
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ $customLogoHref ?: asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,300;1,400;1,500;1,600&family=Playfair+Display:ital,wght@0,400;0,500;0,600;1,400;1,500&display=swap" rel="stylesheet">

    @vite('Modules/KbAdmin/resources/js/kb-admin.ts')

    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
