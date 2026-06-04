@extends('layouts.app')

@section('title', 'Danh sách Quyền')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Danh sách Quyền</h1>
            <p class="text-gray-600 mt-2">Tất cả các quyền trong hệ thống.</p>
        </div>
        <a href="{{ route('admin.permissions.create_permission') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium">
            + Tạo Quyền Mới
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

    <!-- Permissions Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-100 border-b">
                <tr>
                    <th class="px-6 py-3 text-left font-semibold text-gray-900">Tên Quyền</th>
                    <th class="px-6 py-3 text-left font-semibold text-gray-900">Code</th>
                    <th class="px-6 py-3 text-left font-semibold text-gray-900">Nhóm</th>
                    <th class="px-6 py-3 text-left font-semibold text-gray-900">Hành Động</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($permissions as $permission)
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-6 py-4 font-medium text-gray-900">{{ $permission->name }}</td>
                        <td class="px-6 py-4 text-gray-700 font-mono text-sm">{{ $permission->code }}</td>
                        <td class="px-6 py-4">
                            <span class="px-3 py-1 bg-purple-100 text-purple-800 text-sm font-bold rounded">
                                {{ $permission->group_name ?? '-' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <form action="{{ route('admin.permissions.destroy_permission', $permission) }}" method="POST" 
                                style="display:inline" onsubmit="return confirm('Xác nhận xóa quyền này?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Xóa</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                            Không có quyền nào.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="mt-8">
        {{ $permissions->links() }}
    </div>
</div>
@endsection
