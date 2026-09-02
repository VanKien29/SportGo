<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ChangePasswordController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:50', 'confirmed', 'regex:/^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,50}$/'],
        ], [
            'password.confirmed' => 'Xác nhận mật khẩu mới không khớp.',
            'password.regex' => 'Mật khẩu mới phải có ít nhất 1 chữ hoa, 1 chữ số và 1 ký tự đặc biệt.',
        ]);

        $user = $request->user();

        if (! $user->password_set_at) {
            throw ValidationException::withMessages(['current_password' => 'Tài khoản này chưa có mật khẩu. Vui lòng thiết lập mật khẩu trước.']);
        }

        if (! Hash::check($data['current_password'], (string) $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Mật khẩu hiện tại không đúng.']);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'password_set_at' => now(),
        ])->save();
        $user->revokeAllAccess();

        return response()->json([
            'message' => 'Đã đổi mật khẩu. Vui lòng đăng nhập lại.',
            'requires_relogin' => true,
        ]);
    }
}
