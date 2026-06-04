@extends('layouts.app')

@section('title', 'Quản lý Đơn Đăng kí')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Quản lý Đơn Đăng kí</h1>
        <p class="text-gray-600 mt-2">Duyệt và quản lý các đơn đăng kí làm chủ sân.</p>
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
            <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg">
                <option value="">-- Tất cả trạng thái --</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" {{ request('status') == $value ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            <input type="text" name="search" placeholder="Tìm theo tên sân..." value="{{ request('search') }}"
                class="px-4 py-2 border border-gray-300 rounded-lg flex-1">
            <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">Tìm</button>
        </form>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-100 border-b">
                <tr>
                    <th class="px-6 py-3 text-left font-semibold text-gray-900">Tên Sân</th>
                    <th class="px-6 py-3 text-left font-semibold text-gray-900">Người Nộp</th>
                    <th class="px-6 py-3 text-left font-semibold text-gray-900">Ngày Nộp</th>
                    <th class="px-6 py-3 text-left font-semibold text-gray-900">Trạng Thái</th>
                    <th class="px-6 py-3 text-left font-semibold text-gray-900">Hành Động</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($applications as $app)
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-6 py-4 font-medium text-gray-900">{{ $app->venue_name }}</td>
                        <td class="px-6 py-4 text-gray-700">{{ $app->user?->full_name }}</td>
                        <td class="px-6 py-4 text-gray-700">{{ $app->submitted_at->format('d/m/Y H:i') }}</td>
                        <td class="px-6 py-4">
                            @if ($app->status == 'pending')
                                <span class="px-3 py-1 bg-yellow-100 text-yellow-800 text-xs font-bold rounded">Chờ duyệt</span>
                            @elseif ($app->status == 'approved')
                                <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-bold rounded">Đã duyệt</span>
                            @else
                                <span class="px-3 py-1 bg-red-100 text-red-800 text-xs font-bold rounded">Từ chối</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm space-x-2">
                            <a href="{{ route('admin.partner-applications.show', $app) }}" 
                                class="text-blue-600 hover:underline">Xem</a>
                            @if ($app->status == 'pending')
                                <a href="{{ route('admin.partner-applications.approve_form', $app) }}" 
                                    class="text-green-600 hover:underline">Duyệt</a>
                                <a href="{{ route('admin.partner-applications.reject_form', $app) }}" 
                                    class="text-red-600 hover:underline">Từ chối</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                            Không có đơn đăng kí nào.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="mt-8">
        {{ $applications->links() }}
    </div>
</div>
@endsection
