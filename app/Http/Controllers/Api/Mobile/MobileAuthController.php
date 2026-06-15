<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\Mobile\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    use ApiResponse;

    /**
     * Các role được phép dùng app mobile.
     * Shipper → module shipper; còn lại → module OPS (theo MOBILE_API_CONTRACT §2.1).
     */
    private const OPS_ROLES = ['ops', 'admin', 'manager', 'cs'];

    private const MOBILE_ROLES = ['shipper', 'ops', 'admin', 'manager', 'cs'];

    /**
     * POST /api/mobile/login — cấp Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ], [
            'username.required' => 'Vui lòng nhập tên đăng nhập.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ]);

        /** @var User|null $user */
        $user = User::query()->where('username', $validated['username'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return $this->fail('Tài khoản hoặc mật khẩu không đúng.', 401);
        }

        if (! $user->isActive()) {
            return $this->fail('Tài khoản đã bị khóa.', 403);
        }

        if (! $user->hasAnyRole(self::MOBILE_ROLES)) {
            return $this->fail('Tài khoản không có quyền dùng ứng dụng.', 403);
        }

        $deviceName = $validated['device_name'] ?? 'mobile';
        $token = $user->createToken($deviceName)->plainTextToken;

        return $this->ok([
            'token' => $token,
            ...$this->userPayload($user),
        ], 'Đăng nhập thành công.');
    }

    /**
     * GET /api/mobile/me — thông tin user hiện tại + điều hướng.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->ok($this->userPayload($request->user()), 'OK');
    }

    /**
     * POST /api/mobile/logout — thu hồi token hiện tại.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->ok(null, 'Đã đăng xuất.');
    }

    /**
     * PUT /api/mobile/profile — cập nhật thông tin cá nhân.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'fullname' => ['required', 'string', 'max:225'],
            'email'    => ['nullable', 'email', Rule::unique('user', 'email')->ignore($user->id)->whereNull('deleted_at')],
            'phone'    => ['nullable', 'string', 'max:20'],
            'address'  => ['nullable', 'string', 'max:255'],
        ], [
            'fullname.required' => 'Họ và tên không được để trống.',
            'email.email'       => 'Email không hợp lệ.',
            'email.unique'      => 'Email đã được sử dụng.',
        ]);

        $user->update([
            'fullname' => trim($validated['fullname']),
            'email'    => trim((string) ($validated['email'] ?? '')) ?: null,
            'phone'    => trim((string) ($validated['phone'] ?? '')) ?: null,
            'address'  => trim((string) ($validated['address'] ?? '')) ?: null,
        ]);

        return $this->ok($this->userPayload($user->fresh()), 'Cập nhật thông tin thành công.');
    }

    /**
     * PUT /api/mobile/profile/password — đổi mật khẩu.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password'     => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};:\'",.<>\/?\\|`~]).{8,}$/',
            ],
            'confirm_password' => ['required', 'string', 'same:new_password'],
        ], [
            'current_password.required' => 'Vui lòng nhập mật khẩu cũ.',
            'new_password.required'     => 'Mật khẩu mới không được để trống.',
            'new_password.min'          => 'Mật khẩu tối thiểu 8 ký tự.',
            'new_password.regex'        => 'Mật khẩu phải có ít nhất 1 chữ hoa, 1 số, 1 ký tự đặc biệt.',
            'confirm_password.required' => 'Vui lòng nhập lại mật khẩu mới.',
            'confirm_password.same'     => 'Mật khẩu nhập lại không khớp.',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            // Trả 422 theo field để app hiện lỗi đỏ ngay ô mật khẩu cũ.
            throw ValidationException::withMessages([
                'current_password' => ['Mật khẩu cũ không chính xác.'],
            ]);
        }

        $user->update(['password' => bcrypt($validated['new_password'])]);

        return $this->ok(null, 'Đổi mật khẩu thành công.');
    }

    /**
     * POST /api/mobile/profile/avatar — cập nhật ảnh đại diện (multipart).
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:2048'],
        ], [
            'avatar.required' => 'Vui lòng chọn ảnh.',
            'avatar.image'    => 'File không phải ảnh hợp lệ.',
            'avatar.mimes'    => 'Chỉ chấp nhận ảnh JPG, PNG, GIF, WEBP.',
            'avatar.max'      => 'Ảnh tối đa 2MB.',
        ]);

        $file = $request->file('avatar');
        $uploadDir = public_path('uploads' . DIRECTORY_SEPARATOR . 'user');

        // Xóa ảnh cũ chỉ khi là file local trong /uploads/user/.
        $old = (string) ($user->avatar ?? '');
        if (str_starts_with($old, '/uploads/user/')) {
            $oldPath = public_path(ltrim($old, '/'));
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        $filename = $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();

        try {
            if (! is_dir($uploadDir) && ! mkdir($uploadDir, 0755, true) && ! is_dir($uploadDir)) {
                throw new \RuntimeException('Không tạo được thư mục lưu ảnh.');
            }
            $file->move($uploadDir, $filename);
        } catch (\Throwable $e) {
            return $this->fail('Không lưu được ảnh. Vui lòng thử lại.', 500);
        }

        $user->update(['avatar' => '/uploads/user/' . $filename]);

        return $this->ok($this->userPayload($user->fresh()), 'Cập nhật ảnh đại diện thành công.');
    }

    /**
     * Payload user dùng chung cho login + me.
     *
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        $roles = $user->getRoleNames();

        return [
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'code' => $user->code,
                'fullname' => $user->fullname,
                'phone' => $user->phone,
                'email' => $user->email,
                'address' => $user->address,
                'avatar' => $this->avatarUrl($user->avatar),
            ],
            'roles' => $roles->values(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            'default_module' => $this->resolveDefaultModule($roles->all()),
        ];
    }

    /**
     * Resolve avatar thành URL đầy đủ cho client.
     * Rỗng → null; đã là http(s) → giữ nguyên; đường dẫn tương đối → ghép host gốc.
     */
    private function avatarUrl(?string $avatar): ?string
    {
        $avatar = trim((string) $avatar);
        if ($avatar === '') {
            return null;
        }
        if (str_starts_with($avatar, 'http://') || str_starts_with($avatar, 'https://')) {
            return $avatar;
        }

        return url($avatar);
    }

    /**
     * Tính module mặc định để app điều hướng:
     *  - chỉ shipper            → "shipper"
     *  - có role OPS-capable     → "ops"
     *  - vừa shipper vừa OPS     → "chooser"
     *
     * @param  array<int, string>  $roles
     */
    private function resolveDefaultModule(array $roles): string
    {
        $isShipper = in_array('shipper', $roles, true);
        $isOps = count(array_intersect($roles, self::OPS_ROLES)) > 0;

        return match (true) {
            $isShipper && $isOps => 'chooser',
            $isOps => 'ops',
            default => 'shipper',
        };
    }
}
