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

        $request->user()->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        return response()->json([
            'message' => 'Mật khẩu đã được thiết lập thành công.',
        ]);
    }
}
