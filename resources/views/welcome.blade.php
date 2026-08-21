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

    <!-- Primary On-Page SEO Meta Tags -->
    <title>{{ $systemName }} - Nền tảng Đặt lịch & Quản lý Sân Thể Thao Trực Tuyến</title>
    <meta name="description" content="{{ $systemName }} là ứng dụng đặt lịch sân thể thao trực tuyến hàng đầu. Tìm kiếm sân Pickleball, Cầu Lông, Bóng Đá, Tennis gần bạn và giữ chỗ nhanh chóng 24/7.">
    <meta name="keywords" content="đặt sân thể thao, đặt lịch sân cầu lông, đặt sân pickleball, đặt sân bóng đá, thuê sân tennis, giữ chỗ sân trực tuyến, sportgo">
    <meta name="author" content="{{ $systemName }} Platform">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#15803d">
    <link rel="canonical" href="{{ url()->current() }}">

    <!-- Open Graph / Facebook Meta Tags -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="{{ $systemName }} - Nền tảng Đặt lịch & Quản lý Sân Thể Thao Trực Tuyến">
    <meta property="og:description" content="Tìm kiếm cụm sân gần bạn, xem ma trận giờ trống realtime và đặt chỗ nhanh chóng chỉ trong 3 bước.">
    <meta property="og:site_name" content="{{ $systemName }}">

    <!-- Twitter Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $systemName }} - Nền tảng Đặt lịch & Quản lý Sân Thể Thao">
    <meta name="twitter:description" content="Đặt lịch sân Pickleball, Cầu Lông, Bóng Đá & Tennis trực tuyến 24/7.">

    @if($systemFaviconUrl)
        <link rel="icon" href="{{ $systemFaviconUrl }}">
        <link rel="shortcut icon" href="{{ $systemFaviconUrl }}">
        <link rel="apple-touch-icon" href="{{ $systemFaviconUrl }}">
    @endif

    <!-- Google Fonts: Noto Sans (Vietnamese & Latin) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:ital,wght@0,300..900;1,300..900&display=swap" rel="stylesheet">

    <!-- Leaflet Map Library -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- Schema.org JSON-LD Structured Data for On-Page SEO -->
    <script type="application/ld+json">
    {
      "{{ '@' }}context": "https://schema.org",
      "{{ '@' }}type": "WebSite",
      "name": "{{ $systemName }}",
      "url": "{{ url('/') }}",
      "potentialAction": {
        "{{ '@' }}type": "SearchAction",
        "target": "{{ url('/venues') }}?q={search_term_string}",
        "query-input": "required name=search_term_string"
      }
    }
    </script>
    <script type="application/ld+json">
    {
      "{{ '@' }}context": "https://schema.org",
      "{{ '@' }}type": "SportsActivityLocation",
      "name": "{{ $systemName }} Sports Platform",
      "description": "Nền tảng đặt sân thể thao trực tuyến chuyên nghiệp tại Việt Nam.",
      "url": "{{ url('/') }}"
    }
    </script>

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
