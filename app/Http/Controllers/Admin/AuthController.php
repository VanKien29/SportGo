<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Hiển thị form đăng nhập
     */
    public function login_form()
    {
        if (Auth::check()) {
            return redirect()->route('admin.permissions.index');
        }
        return view('admin.auth.login');
    }

    /**
     * Xử lý đăng nhập
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            
            // Kiểm tra xem user có role admin không
            $roles = $user->roles()->pluck('roles.name')->all() ?? [];
            if (!array_intersect($roles, ['admin', 'super_admin', 'system_staff'])) {
                Auth::logout();
                return back()->with('error', 'Bạn không có quyền truy cập admin panel.');
            }

            return redirect()->route('admin.permissions.index');
        }

        return back()->with('error', 'Email hoặc mật khẩu không đúng.');
    }

    /**
     * Xử lý đăng xuất
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
