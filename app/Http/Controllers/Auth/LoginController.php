<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use App\Models\User;

class LoginController extends Controller
{
    /**
     * Display login page
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->intended(route('dashboard'));
        }
        return view('auth.login');
    }

    /**
     * Handle login submission via traditional form (fallback)
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)
            ->orWhere('email', $request->username)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return back()->withErrors([
                'username' => 'Tên đăng nhập hoặc mật khẩu không đúng.',
            ])->onlyInput('username');
        }

        if ($user->status != 1) {
            return back()->withErrors([
                'username' => 'Tài khoản đã bị khóa. Vui lòng liên hệ quản trị viên.',
            ])->onlyInput('username');
        }

        Auth::login($user, $request->boolean('remember'));

        // Cập nhật last login
        $user->update(['last_login' => now()]);

        // Chuyển hướng về dashboard hoặc intended URL
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Handle logout
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}