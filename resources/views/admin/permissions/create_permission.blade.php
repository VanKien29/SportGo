@extends('layouts.app')

@section('title', 'Tạo Quyền Mới')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <a href="{{ route('admin.permissions.list') }}" class="text-blue-600 hover:underline mb-4 inline-block">← Quay lại</a>

    <h1 class="text-3xl font-bold text-gray-900 mb-6">Tạo Quyền Mới</h1>

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
        <form action="{{ route('admin.permissions.store_permission') }}" method="POST" class="space-y-6">
            @csrf

            <!-- Code -->
            <div>
                <label for="code" class="block text-sm font-medium text-gray-700 mb-2">Code Quyền *</label>
                <input type="text" id="code" name="code" value="{{ old('code') }}" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Ví dụ: manage_banners (viết liền, in thường)">
                <p class="text-xs text-gray-500 mt-1">Dùng tên duy nhất, không có dấu cách</p>
            </div>

            <!-- Tên quyền -->
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Tên Quyền *</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Ví dụ: Quản lý banner">
            </div>

            <!-- Nhóm quyền -->
            <div>
                <label for="group_name" class="block text-sm font-medium text-gray-700 mb-2">Nhóm Quyền *</label>
                <div class="flex gap-2">
                    <input type="text" id="group_name" name="group_name" value="{{ old('group_name') }}" required
                        list="groups"
                        class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Ví dụ: Banner Management">
                    <datalist id="groups">
                        @foreach ($groups as $group)
                            <option value="{{ $group }}"></option>
                        @endforeach
                    </datalist>
                </div>
                <p class="text-xs text-gray-500 mt-1">Chọn nhóm có sẵn hoặc nhập nhóm mới</p>
            </div>

            <!-- Buttons -->
            <div class="flex gap-4 border-t pt-6">
                <a href="{{ route('admin.permissions.list') }}" class="flex-1 px-4 py-2 bg-gray-600 text-white text-center rounded hover:bg-gray-700 font-medium">
                    Hủy
                </a>
                <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-medium">
                    ✓ Tạo Quyền
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
