<?php

namespace App\Http\Controllers\Api\ZaloMiniApp;

use App\Http\Controllers\Api\Mobile\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Zalo\ZaloMiniAppIdentityVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Throwable;

class ZaloMiniAppAuthController extends Controller
{
    use ApiResponse;

    private const ALLOWED_ROLES = ['admin', 'manager', 'ketoan', 'cs', 'sale', 'ops', 'ctv', 'shipper'];

    public function __construct(protected ZaloMiniAppIdentityVerifier $zaloVerifier)
    {
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'zalo_access_token' => ['nullable', 'string', 'max:2048'],
            'link_zalo' => ['nullable', 'boolean'],
        ], [
            'username.required' => 'Vui lòng nhập tên đăng nhập.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ]);

        $user = User::query()->where('username', $validated['username'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return $this->fail('Tài khoản hoặc mật khẩu không đúng.', 401);
        }

        if (! $user->isActive()) {
            return $this->fail('Tài khoản đã bị khóa.', 403);
        }

        if (! $user->hasAnyRole(self::ALLOWED_ROLES)) {
            return $this->fail('Tài khoản không có quyền dùng Zalo Mini App.', 403);
        }

        if ((bool) ($validated['link_zalo'] ?? false) && filled($validated['zalo_access_token'] ?? null)) {
            $linked = $this->linkUserWithAccessToken($user, (string) $validated['zalo_access_token']);

            if (! $linked) {
                return $this->fail('Không liên kết được tài khoản Zalo. Vui lòng đăng nhập lại hoặc bỏ chọn liên kết.', 422);
            }
        }

        $token = $user->createToken($validated['device_name'] ?? 'zalo-mini-app')->plainTextToken;

        return $this->ok([
            'token' => $token,
            ...$this->userPayload($user->fresh()),
        ], 'Đăng nhập thành công.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->ok($this->userPayload($request->user()), 'OK');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->ok(null, 'Đã đăng xuất.');
    }

    public function zalo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'zalo_access_token' => ['required', 'string', 'max:2048'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $identity = $this->zaloVerifier->verifyAccessToken($validated['zalo_access_token']);
        } catch (Throwable) {
            return $this->fail('Không xác thực được tài khoản Zalo.', 401);
        }

        $user = User::query()->where('zalo_user_id', $identity['id'])->first();

        if (! $user) {
            return $this->fail('Tài khoản Zalo này chưa được liên kết.', 404);
        }

        if (! $user->isActive()) {
            return $this->fail('Tài khoản đã bị khóa.', 403);
        }

        if (! $user->hasAnyRole(self::ALLOWED_ROLES)) {
            return $this->fail('Tài khoản không có quyền dùng Zalo Mini App.', 403);
        }

        $token = $user->createToken($validated['device_name'] ?? 'zalo-mini-app')->plainTextToken;

        return $this->ok([
            'token' => $token,
            ...$this->userPayload($user),
        ], 'Đăng nhập thành công.');
    }

    public function zaloLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'zalo_access_token' => ['required', 'string', 'max:2048'],
        ]);

        if (! $this->linkUserWithAccessToken($request->user(), $validated['zalo_access_token'])) {
            return $this->fail('Không liên kết được tài khoản Zalo. Vui lòng thử lại.', 422);
        }

        return $this->ok($this->userPayload($request->user()->fresh()), 'Đã liên kết tài khoản Zalo.');
    }

    private function linkUserWithAccessToken(User $user, string $accessToken): bool
    {
        try {
            $identity = $this->zaloVerifier->verifyAccessToken($accessToken);
        } catch (Throwable) {
            return false;
        }

        $existing = User::query()
            ->where('zalo_user_id', $identity['id'])
            ->whereKeyNot($user->id)
            ->exists();

        if ($existing) {
            return false;
        }

        $user->forceFill([
            'zalo_user_id' => $identity['id'],
            'zalo_linked_at' => now(),
        ])->save();

        return true;
    }

    private function userPayload(User $user): array
    {
        $roles = $user->getRoleNames()->values();

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
                'zalo_linked' => filled($user->zalo_user_id),
            ],
            'roles' => $roles,
            'abilities' => $this->abilities($user),
        ];
    }

    private function abilities(User $user): array
    {
        return [
            'orders_view' => $user->can('orders.index'),
            'orders_create' => $user->can('orders.create'),
            'prices_manage' => $user->can('service-prices.manage'),
            'prices_delete' => $user->can('service-prices.delete'),
            'finance_view' => $user->hasAnyRole(['admin', 'manager', 'ketoan']),
            'orders_scope' => $this->ordersScope($user),
        ];
    }

    private function ordersScope(User $user): string
    {
        return match (true) {
            $user->hasAnyRole(['admin', 'manager', 'ketoan']) => 'all',
            $user->hasRole('cs') => 'assigned_or_unassigned_cs',
            $user->hasRole('ops') => 'assigned_or_unassigned_ops',
            $user->hasRole('sale') => 'sale',
            $user->hasRole('ctv') => 'ctv',
            default => 'none',
        };
    }

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
}
