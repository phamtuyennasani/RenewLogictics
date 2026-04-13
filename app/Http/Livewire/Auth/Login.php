<?php

namespace App\Http\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Component;

class Login extends Component
{
    public string $username = '';
    public string $password = '';
    public bool $remember = false;
    public bool $showPassword = false;
    public string $errorMessage = '';
    public bool $isLoading = false;

    protected $rules = [
        'username' => 'required|string|max:255',
        'password' => 'required|string|min:1',
    ];

    protected $messages = [
        'username.required' => 'Vui lòng nhập tên đăng nhập.',
        'password.required' => 'Vui lòng nhập mật khẩu.',
    ];

    public function updated($propertyName)
    {
        $this->validateOnly($propertyName);
        $this->errorMessage = '';
    }

    public function login()
    {
        $this->validate();
        $this->isLoading = true;
        $this->errorMessage = '';

        try {
            // Tìm user theo username hoặc email
            $user = User::where('username', $this->username)
                ->orWhere('email', $this->username)
                ->first();
            if (!$user) {
                $this->errorMessage = 'Tên đăng nhập không tồn tại.';
                $this->isLoading = false;
                return;
            }

            if (!Hash::check($this->password, $user->password)) {
                $this->errorMessage = 'Mật khẩu không đúng.';
                $this->isLoading = false;
                return;
            }

            // Kiểm tra tài khoản có hoạt động không (hienthi = đang hiển thị/hoạt động)
            if ($user->status !== 'hienthi') {
                $this->errorMessage = 'Tài khoản đã bị khóa. Vui lòng liên hệ quản trị viên.';
                $this->isLoading = false;
                return;
            }

            // Đăng nhập
            Auth::login($user, $this->remember);

            // Cập nhật last login (cột trong DB là lastlogin)
            $user->update(['lastlogin' => now()]);

            // Chuyển hướng về dashboard hoặc intended URL
            return redirect()->intended(route('dashboard'));
        } catch (\Exception $e) {
            $this->errorMessage = 'Đã có lỗi xảy ra. Vui lòng thử lại sau.';
            report($e);
        } finally {
            $this->isLoading = false;
        }
    }

    public function togglePassword()
    {
        $this->showPassword = !$this->showPassword;
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
