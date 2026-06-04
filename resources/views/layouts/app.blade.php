<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') - SportGo</title>
    @vite('resources/css/app.css')
    <style>
        [x-cloak] { display: none; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <nav class="bg-white shadow-sm border-b">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center gap-8">
                <h1 class="text-2xl font-bold text-gray-900">SportGo Admin</h1>
                <div class="hidden md:flex gap-6">
                    <a href="{{ route('admin.banners.index') }}" class="text-gray-600 hover:text-gray-900 font-medium">
                        Quản lý Banner
                    </a>
                    <a href="{{ route('admin.partner-applications.index') }}" class="text-gray-600 hover:text-gray-900 font-medium">
                        Quản lý Đơn Đăng kí
                    </a>
                    <a href="{{ route('admin.permissions.index') }}" class="text-gray-600 hover:text-gray-900 font-medium">
                        Quản lý Phân quyền
                    </a>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-gray-700">{{ Auth::user()->full_name }}</span>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="text-red-600 hover:text-red-700 font-medium">
                        Đăng xuất
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main>
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-400 py-8 mt-16">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2024 SportGo. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
