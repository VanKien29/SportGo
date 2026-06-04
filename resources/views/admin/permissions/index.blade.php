@extends('layouts.app')

@section('title', 'Quản lý Phân quyền')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Quản lý Phân quyền</h1>
            <p class="text-gray-600 mt-2">Quản lý vai trò, quyền và phân quyền cho người dùng.</p>
        </div>
        <div class="flex gap-4">
            <a href="{{ route('admin.permissions.list') }}" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg font-medium">
                📋 Danh sách Quyền
            </a>
            <a href="{{ route('admin.permissions.create_role') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium">
                + Tạo Vai trò Mới
            </a>
        </div>
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

    <!-- Roles Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b font-semibold text-gray-900">
            Các Vai trò ({{ $roles->total() }})
        </div>
        <table class="w-full">
            <thead class="bg-gray-100 border-b">
                <tr>
                    <th class="px-6 py-3 text-left font-semibold text-gray-900">Tên Vai trò</th>
                    <th class="px-6 py-3 text-left font-semibold text-gray-900">Mô tả</th>
                    <th class="px-6 py-3 text-left font-semibold text-gray-900">Số Quyền</th>
                    <th class="px-6 py-3 text-left font-semibold text-gray-900">Loại</th>
                    <th class="px-6 py-3 text-left font-semibold text-gray-900">Hành Động</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($roles as $role)
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-6 py-4 font-medium text-gray-900">{{ $role->display_name ?? $role->name }}</td>
                        <td class="px-6 py-4 text-gray-700 text-sm">{{ $role->description ?? '-' }}</td>
                        <td class="px-6 py-4">
                            <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm font-bold rounded">
                                {{ $role->permissions()->count() }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            @if ($role->is_system)
                                <span class="px-3 py-1 bg-yellow-100 text-yellow-800 text-xs font-bold rounded">Hệ thống</span>
                            @else
                                <span class="px-3 py-1 bg-gray-100 text-gray-800 text-xs font-bold rounded">Tùy chỉnh</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm space-x-2">
                            <a href="{{ route('admin.permissions.edit_role', $role) }}" 
                                class="text-blue-600 hover:underline">Chỉnh sửa</a>
                            @if (!$role->is_system)
                                <form action="{{ route('admin.permissions.destroy_role', $role) }}" method="POST" 
                                    style="display:inline" onsubmit="return confirm('Xác nhận xóa vai trò này?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">Xóa</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                            Không có vai trò nào.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="mt-8">
        {{ $roles->links() }}
    </div>
</div>
@endsection
