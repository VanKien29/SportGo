<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SetPasswordController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password' => [
                'required',
                'string',
                'min:8',
                'max:50',
                'confirmed',
                'regex:/^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,50}$/',
            ],
        ], [
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.max' => 'Mật khẩu không được vượt quá 50 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
            'password.regex' => 'Mật khẩu phải có ít nhất 1 chữ hoa, 1 chữ số và 1 ký tự đặc biệt.',
        ]);

        $user = $request->user();

        if ($user->password_set_at) {
            return response()->json([
                'message' => 'Tài khoản đã có mật khẩu. Vui lòng dùng chức năng đổi mật khẩu.',
            ], 422);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'password_set_at' => now(),
        ])->save();
        $user->revokeAllAccess();

        return response()->json([
            'message' => 'Mật khẩu đã được thiết lập thành công.',
            'requires_relogin' => true,
        ]);
    }
}
