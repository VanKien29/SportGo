<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @php
        $systemProfile = \App\Models\SystemSetting::profilePayload();
        $systemName = $systemProfile['system_name'] ?: 'SportGo';
        $systemFavicon = $systemProfile['favicon_url'] ?: $systemProfile['logo_url'];
        $systemFaviconUrl = $systemFavicon
            ? (\Illuminate\Support\Str::startsWith($systemFavicon, ['http://', 'https://', '//', 'data:'])
                ? $systemFavicon
                : asset(ltrim($systemFavicon, '/')))
            : null;
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $systemName }} - Đặt sân thể thao online</title>
    <meta name="description" content="{{ $systemName }} - Nền tảng đặt sân thể thao online">
    @if($systemFaviconUrl)
        <link rel="icon" href="{{ $systemFaviconUrl }}">
        <link rel="shortcut icon" href="{{ $systemFaviconUrl }}">
        <link rel="apple-touch-icon" href="{{ $systemFaviconUrl }}">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Outfit:wght@500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Leaflet Map Library -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        (function() {
            try {
                const theme = localStorage.getItem('theme');
                if (theme === 'dark') {
                    document.documentElement.classList.add('dark');
                    document.documentElement.classList.remove('light');
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else if (theme === 'light') {
                    document.documentElement.classList.add('light');
                    document.documentElement.classList.remove('dark');
                    document.documentElement.removeAttribute('data-theme');
                }
            } catch (e) {}
        })();
    </script>
</head>
<body class="antialiased">
    <div id="app"></div>
</body>
</html>
