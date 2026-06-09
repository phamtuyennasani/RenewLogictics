<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\Mobile\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
                'fullname' => $user->fullname,
                'phone' => $user->phone,
                'email' => $user->email,
                'avatar' => $user->avatar,
            ],
            'roles' => $roles->values(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            'default_module' => $this->resolveDefaultModule($roles->all()),
        ];
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
