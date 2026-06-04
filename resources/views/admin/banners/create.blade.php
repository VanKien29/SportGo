@extends('layouts.app')

@section('title', 'Thêm Banner')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <h1 class="text-3xl font-bold text-gray-900 mb-6">Thêm Banner Mới</h1>

    @if ($errors->any())
        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
            <strong>Lỗi:</strong>
            <ul class="mt-2 list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-md p-6">
        <form action="{{ route('admin.banners.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <!-- Tiêu đề -->
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Tiêu đề *</label>
                <input type="text" id="title" name="title" value="{{ old('title') }}" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Nhập tiêu đề banner">
            </div>

            <!-- Ảnh -->
            <div>
                <label for="image" class="block text-sm font-medium text-gray-700 mb-2">Ảnh Banner *</label>
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-400 transition">
                    <input type="file" id="image" name="image" accept="image/*" required
                        class="hidden" onchange="previewImage(event)">
                    <label for="image" class="cursor-pointer">
                        <p class="text-gray-600">Click để upload ảnh</p>
                        <p class="text-xs text-gray-500 mt-1">Dung lượng tối đa: 5MB. Định dạng: JPEG, PNG, GIF</p>
                    </label>
                </div>
                <img id="preview" class="mt-4 max-h-48 mx-auto" style="display:none">
            </div>

            <!-- Link URL -->
            <div>
                <label for="link_url" class="block text-sm font-medium text-gray-700 mb-2">Liên kết (URL)</label>
                <input type="url" id="link_url" name="link_url" value="{{ old('link_url') }}"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="https://...">
            </div>

            <!-- Vị trí -->
            <div>
                <label for="position" class="block text-sm font-medium text-gray-700 mb-2">Vị trí Hiển Thị *</label>
                <select id="position" name="position" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">-- Chọn vị trí --</option>
                    @foreach ($positions as $value => $label)
                        <option value="{{ $value }}" {{ old('position') == $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Thời gian -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="starts_at" class="block text-sm font-medium text-gray-700 mb-2">Ngày Bắt Đầu *</label>
                    <input type="datetime-local" id="starts_at" name="starts_at" value="{{ old('starts_at') }}" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label for="ends_at" class="block text-sm font-medium text-gray-700 mb-2">Ngày Kết Thúc *</label>
                    <input type="datetime-local" id="ends_at" name="ends_at" value="{{ old('ends_at') }}" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <!-- Thứ tự -->
            <div>
                <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-2">Thứ tự Hiển thị</label>
                <input type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', 0) }}" min="0"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Kích hoạt -->
            <div class="flex items-center">
                <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active') ? 'checked' : '' }}
                    class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                <label for="is_active" class="ml-2 block text-sm text-gray-700">Kích hoạt ngay</label>
            </div>

            <!-- Buttons -->
            <div class="flex gap-4 border-t pt-6">
                <a href="{{ route('admin.banners.index') }}" class="flex-1 px-4 py-2 bg-gray-600 text-white text-center rounded hover:bg-gray-700 font-medium">
                    Hủy
                </a>
                <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-medium">
                    Tạo Banner
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function previewImage(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('preview').src = e.target.result;
            document.getElementById('preview').style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
}
</script>
@endsection
