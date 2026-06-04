@extends('layouts.app')

@section('title', 'Chi tiết đơn đăng kí')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    <a href="{{ route('admin.partner-applications.index') }}" class="text-blue-600 hover:underline mb-4 inline-block">← Quay lại</a>

    <h1 class="text-3xl font-bold text-gray-900 mb-6">Chi tiết Đơn Đăng kí</h1>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Main Info -->
        <div class="md:col-span-2 space-y-6">
            <!-- Thông tin người nộp -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Thông tin người nộp</h3>
                <div class="space-y-2 text-gray-700">
                    <p><strong>Tên:</strong> {{ $application->user?->full_name }}</p>
                    <p><strong>Email:</strong> {{ $application->user?->email }}</p>
                    <p><strong>Điện thoại:</strong> {{ $application->user?->phone }}</p>
                </div>
            </div>

            <!-- Thông tin kinh doanh -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Thông tin kinh doanh</h3>
                <div class="space-y-2 text-gray-700">
                    <p><strong>Tên doanh nghiệp:</strong> {{ $application->business_name }}</p>
                    <p><strong>Mã số thuế:</strong> {{ $application->tax_code }}</p>
                </div>
            </div>

            <!-- Thông tin sân -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Thông tin sân</h3>
                <div class="space-y-2 text-gray-700">
                    <p><strong>Tên sân:</strong> {{ $application->venue_name }}</p>
                    <p><strong>Địa chỉ:</strong> {{ $application->venue_address }}</p>
                    <p><strong>Tọa độ:</strong> {{ $application->venue_latitude }}, {{ $application->venue_longitude }}</p>
                    @if ($application->venue_map_url)
                        <p><strong>Bản đồ:</strong> <a href="{{ $application->venue_map_url }}" target="_blank" class="text-blue-600 hover:underline">Xem bản đồ</a></p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Status Info -->
        <div>
            <div class="bg-white rounded-lg shadow-md p-6 sticky top-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Trạng thái</h3>
                
                <div class="mb-4">
                    @if ($application->status == 'pending')
                        <span class="block px-4 py-2 bg-yellow-100 text-yellow-800 text-center font-bold rounded mb-2">Chờ duyệt</span>
                        <a href="{{ route('admin.partner-applications.approve_form', $application) }}" 
                            class="block w-full px-4 py-2 bg-green-600 text-white text-center rounded hover:bg-green-700 font-medium mb-2">
                            ✓ Duyệt
                        </a>
                        <a href="{{ route('admin.partner-applications.reject_form', $application) }}" 
                            class="block w-full px-4 py-2 bg-red-600 text-white text-center rounded hover:bg-red-700 font-medium">
                            ✕ Từ chối
                        </a>
                    @elseif ($application->status == 'approved')
                        <span class="block px-4 py-2 bg-green-100 text-green-800 text-center font-bold rounded">Đã duyệt</span>
                        <div class="mt-4 space-y-2 text-sm text-gray-700">
                            <p><strong>Duyệt bởi:</strong> {{ $application->reviewedBy?->full_name }}</p>
                            <p><strong>Ngày duyệt:</strong> {{ $application->reviewed_at?->format('d/m/Y H:i') }}</p>
                        </div>
                    @else
                        <span class="block px-4 py-2 bg-red-100 text-red-800 text-center font-bold rounded mb-2">Từ chối</span>
                        <div class="mt-4 space-y-2 text-sm text-gray-700">
                            <p><strong>Duyệt bởi:</strong> {{ $application->reviewedBy?->full_name }}</p>
                            <p><strong>Ngày duyệt:</strong> {{ $application->reviewed_at?->format('d/m/Y H:i') }}</p>
                            @if ($application->status_reason)
                                <p><strong>Lý do:</strong></p>
                                <p class="bg-gray-100 p-2 rounded text-gray-800">{{ $application->status_reason }}</p>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="mt-4 pt-4 border-t space-y-2 text-sm text-gray-600">
                    <p><strong>Ngày nộp:</strong> {{ $application->submitted_at->format('d/m/Y H:i') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
