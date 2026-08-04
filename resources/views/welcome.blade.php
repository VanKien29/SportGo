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
    <!-- Leaflet Map Library -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        #app:empty {
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #f8fafc;
        }

        #app:empty::before {
            content: "";
            width: 30px;
            height: 30px;
            border: 3px solid #d9f5e4;
            border-top-color: #16a34a;
            border-radius: 50%;
            animation: sportgo-app-spin .75s linear infinite;
        }

        @keyframes sportgo-app-spin {
            to { transform: rotate(360deg); }
        }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        (function() {
            try {
                const path = window.location.pathname;
                const storageKey = /^\/admin(\/|$)/.test(path)
                    ? 'admin-theme'
                    : (/^\/(owner|staff)(\/|$)/.test(path) ? 'owner-theme' : null);
                const savedTheme = storageKey ? (localStorage.getItem(storageKey) || 'system') : 'light';
                const resolvedTheme = savedTheme === 'system'
                    ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                    : savedTheme;

                document.documentElement.classList.toggle('dark', resolvedTheme === 'dark');
                document.documentElement.classList.toggle('light', resolvedTheme === 'light');
                document.documentElement.setAttribute('data-theme', resolvedTheme);
            } catch (e) {}
        })();
    </script>
</head>
<body class="antialiased">
    <div id="app"></div>
</body>
</html>
