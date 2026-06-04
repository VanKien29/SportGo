@extends('layouts.app')

@section('title', 'Duyệt Đơn Đăng kí')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <a href="{{ route('admin.partner-applications.index') }}" class="text-blue-600 hover:underline mb-4 inline-block">← Quay lại</a>

    <h1 class="text-3xl font-bold text-gray-900 mb-6">Duyệt Đơn Đăng kí</h1>

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <p class="text-blue-900"><strong>Đơn:</strong> {{ $application->venue_name }} - {{ $application->user?->full_name }}</p>
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
        <form action="{{ route('admin.partner-applications.approve', $application) }}" method="POST" class="space-y-6">
            @csrf
            @method('POST')

            <!-- Tên sân con ban đầu -->
            <div>
                <label for="initial_court_name" class="block text-sm font-medium text-gray-700 mb-2">Tên Sân Con Ban Đầu *</label>
                <input type="text" id="initial_court_name" name="initial_court_name" value="{{ old('initial_court_name') }}" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Ví dụ: Sân bóng 1">
            </div>

            <!-- Loại sân -->
            <div>
                <label for="court_type_id" class="block text-sm font-medium text-gray-700 mb-2">Loại Sân *</label>
                <select id="court_type_id" name="court_type_id" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">-- Chọn loại sân --</option>
                    @foreach ($courtTypes as $type)
                        <option value="{{ $type->id }}" {{ old('court_type_id') == $type->id ? 'selected' : '' }}>
                            {{ $type->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Bank Info -->
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                <h3 class="font-semibold text-gray-900 mb-4">Thông tin Tài khoản Ngân hàng</h3>
                
                <div class="space-y-4">
                    <div>
                        <label for="bank_account_name" class="block text-sm font-medium text-gray-700 mb-2">Tên Tài Khoản *</label>
                        <input type="text" id="bank_account_name" name="bank_account_name" value="{{ old('bank_account_name') }}" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="bank_account_number" class="block text-sm font-medium text-gray-700 mb-2">Số Tài Khoản *</label>
                        <input type="text" id="bank_account_number" name="bank_account_number" value="{{ old('bank_account_number') }}" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="bank_name" class="block text-sm font-medium text-gray-700 mb-2">Tên Ngân Hàng *</label>
                        <input type="text" id="bank_name" name="bank_name" value="{{ old('bank_name') }}" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
            </div>

            <!-- Buttons -->
            <div class="flex gap-4 border-t pt-6">
                <a href="{{ route('admin.partner-applications.index') }}" class="flex-1 px-4 py-2 bg-gray-600 text-white text-center rounded hover:bg-gray-700 font-medium">
                    Hủy
                </a>
                <button type="submit" class="flex-1 px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 font-medium">
                    ✓ Duyệt Đơn
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
