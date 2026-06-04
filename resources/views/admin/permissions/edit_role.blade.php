@extends('layouts.app')

@section('title', 'Chỉnh sửa Vai trò')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    <a href="{{ route('admin.permissions.index') }}" class="text-blue-600 hover:underline mb-4 inline-block">← Quay lại</a>

    <h1 class="text-3xl font-bold text-gray-900 mb-6">Chỉnh sửa Vai trò: {{ $role->display_name ?? $role->name }}</h1>

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
        <form action="{{ route('admin.permissions.update_role', $role) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Tên hiển thị -->
            <div>
                <label for="display_name" class="block text-sm font-medium text-gray-700 mb-2">Tên Hiển Thị *</label>
                <input type="text" id="display_name" name="display_name" value="{{ old('display_name', $role->display_name) }}" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Ví dụ: Quản trị viên">
            </div>

            <!-- Mô tả -->
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Mô tả</label>
                <textarea id="description" name="description" rows="3"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Mô tả về vai trò này...">{{ old('description', $role->description) }}</textarea>
            </div>

            <!-- Phân quyền -->
            <div>
                <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded">
                    <p class="text-blue-900 text-sm">
                        <strong>Lưu ý:</strong> Chọn các quyền mà bạn muốn gán cho vai trò này. 
                        Các quyền được tổ chức theo nhóm để dễ quản lý.
                    </p>
                </div>

                @foreach ($permissionsByGroup as $group => $groupPermissions)
                    <div class="mb-6 border rounded-lg p-4 bg-gray-50">
                        <div class="mb-3">
                            <h3 class="font-semibold text-gray-900 text-lg">{{ $group ?? 'Khác' }}</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            @foreach ($groupPermissions as $permission)
                                <label class="flex items-center">
                                    <input type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                                        {{ in_array($permission->id, $rolePermissionIds) ? 'checked' : '' }}
                                        class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    <span class="ml-2 text-gray-700">
                                        <span class="font-medium">{{ $permission->name }}</span>
                                        <span class="text-gray-500 text-sm">({{ $permission->code }})</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Buttons -->
            <div class="flex gap-4 border-t pt-6">
                <a href="{{ route('admin.permissions.index') }}" class="flex-1 px-4 py-2 bg-gray-600 text-white text-center rounded hover:bg-gray-700 font-medium">
                    Hủy
                </a>
                <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-medium">
                    💾 Lưu Thay đổi
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
