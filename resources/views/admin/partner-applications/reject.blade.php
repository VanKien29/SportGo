@extends('layouts.app')

@section('title', 'Từ chối Đơn Đăng kí')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <a href="{{ route('admin.partner-applications.index') }}" class="text-blue-600 hover:underline mb-4 inline-block">← Quay lại</a>

    <h1 class="text-3xl font-bold text-gray-900 mb-6">Từ Chối Đơn Đăng kí</h1>

    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
        <p class="text-red-900"><strong>Đơn:</strong> {{ $application->venue_name }} - {{ $application->user?->full_name }}</p>
    </div>

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
        <form action="{{ route('admin.partner-applications.reject', $application) }}" method="POST" class="space-y-6">
            @csrf
            @method('POST')

            <!-- Lý do từ chối -->
            <div>
                <label for="reason" class="block text-sm font-medium text-gray-700 mb-2">Lý Do Từ Chối *</label>
                <textarea id="reason" name="reason" rows="8" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Vui lòng nhập chi tiết lý do từ chối đơn này...">{{ old('reason') }}</textarea>
                <p class="text-xs text-gray-500 mt-2">Lý do này sẽ được gửi cho người nộp đơn.</p>
            </div>

            <!-- Buttons -->
            <div class="flex gap-4 border-t pt-6">
                <a href="{{ route('admin.partner-applications.index') }}" class="flex-1 px-4 py-2 bg-gray-600 text-white text-center rounded hover:bg-gray-700 font-medium">
                    Hủy
                </a>
                <button type="submit" class="flex-1 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 font-medium"
                    onclick="return confirm('Xác nhận từ chối đơn này?')">
                    ✕ Từ Chối Đơn
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
