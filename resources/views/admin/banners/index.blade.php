@extends('layouts.app')

@section('title', 'Quản lý Banner')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Quản lý Banner</h1>
            <p class="text-gray-600 mt-2">Tạo và quản lý các banner quảng cáo trên hệ thống.</p>
        </div>
        <a href="{{ route('admin.banners.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium">
            + Thêm banner mới
        </a>
    </div>

    @if (session('success'))
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
            {{ session('error') }}
        </div>
    @endif

    <!-- Filters -->
    <div class="mb-6 flex gap-4">
        <form method="GET" class="flex gap-2 w-full">
            <select name="position" class="px-4 py-2 border border-gray-300 rounded-lg">
                <option value="">-- Tất cả vị trí --</option>
                @foreach ($positions as $value => $label)
                    <option value="{{ $value }}" {{ request('position') == $value ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            <select name="is_active" class="px-4 py-2 border border-gray-300 rounded-lg">
                <option value="">-- Tất cả trạng thái --</option>
                <option value="1" {{ request('is_active') == '1' ? 'selected' : '' }}>Hoạt động</option>
                <option value="0" {{ request('is_active') == '0' ? 'selected' : '' }}>Tạm ngưng</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">Tìm</button>
        </form>
    </div>

    <!-- Banners Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($banners as $banner)
            <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition">
                <!-- Image -->
                <div class="h-40 bg-gray-200 overflow-hidden">
                    @if ($banner->image_path)
                        <img src="{{ Storage::url($banner->image_path) }}" alt="{{ $banner->title }}" class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full flex items-center justify-center text-gray-400">Chưa có ảnh</div>
                    @endif
                </div>

                <!-- Info -->
                <div class="p-4">
                    <div class="flex justify-between items-start gap-2 mb-2">
                        <h3 class="font-semibold text-gray-900 flex-1">{{ $banner->title }}</h3>
                        <span class="px-2 py-1 text-xs font-bold rounded {{ $banner->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $banner->is_active ? 'Hoạt động' : 'Tạm ngưng' }}
                        </span>
                    </div>

                    <div class="text-sm text-gray-600 space-y-1 mb-4">
                        <p><strong>Vị trí:</strong> {{ $positions[$banner->position] ?? $banner->position }}</p>
                        <p><strong>Thứ tự:</strong> {{ $banner->sort_order }}</p>
                        <p><strong>Thời hạn:</strong> {{ $banner->starts_at->format('d/m/Y') }} - {{ $banner->ends_at->format('d/m/Y') }}</p>
                        @if ($banner->link_url)
                            <p><strong>Link:</strong> <a href="{{ $banner->link_url }}" target="_blank" class="text-blue-600 hover:underline">Mở</a></p>
                        @endif
                    </div>

                    <div class="flex gap-2 border-t pt-4">
                        <a href="{{ route('admin.banners.edit', $banner) }}" class="flex-1 px-3 py-2 bg-blue-600 text-white text-center rounded hover:bg-blue-700 text-sm font-medium">
                            Chỉnh sửa
                        </a>
                        <form method="POST" action="{{ route('admin.banners.destroy', $banner) }}" class="flex-1" onsubmit="return confirm('Xóa banner này?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-full px-3 py-2 bg-red-600 text-white rounded hover:bg-red-700 text-sm font-medium">
                                Xóa
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-12 text-gray-500">
                <p class="text-lg">Chưa có banner nào.</p>
                <a href="{{ route('admin.banners.create') }}" class="text-blue-600 hover:underline mt-2 inline-block">Tạo ngay →</a>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    <div class="mt-8">
        {{ $banners->links() }}
    </div>
</div>
@endsection
