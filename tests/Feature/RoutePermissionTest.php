<?php

namespace Tests\Feature;

use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Mobile\BuildsMobileSchema;
use Tests\TestCase;

/**
 * Chốt chặn hồi quy cho lỗi group middleware vô hiệu:
 * `Route::prefix()->group(...)->middleware('can:x')` KHÔNG áp middleware
 * vào các route trong group (group đã đăng ký xong trước khi middleware
 * được set). Phải viết `Route::middleware('can:x')->prefix()->group(...)`.
 *
 * Test này fail ngay nếu ai đó thêm group mới theo pattern sai.
 */
class RoutePermissionTest extends TestCase
{
    use BuildsMobileSchema;

    /**
     * Mọi route web nhạy cảm phải có Authorize (can:) trong middleware stack.
     */
    public function test_sensitive_route_groups_have_authorize_middleware(): void
    {
        $prefixes = [
            'orders.', 'pickups.', 'congno.', 'invoice.', 'packages.',
            'customers.', 'sender.', 'receiver.', 'ctv.', 'nhansu.',
            'dichvu.', 'donvi.', 'phanloai.', 'doitac.', 'phuphi.',
            'place.', 'chinhsach.', 'settings.',
        ];

        $missing = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || str_starts_with($name, 'api.')) {
                continue;
            }

            if (! collect($prefixes)->contains(fn ($p) => str_starts_with($name, $p))) {
                continue;
            }

            $hasAuthorize = collect($route->gatherMiddleware())
                ->contains(fn ($middleware) => is_string($middleware)
                    && (str_starts_with($middleware, 'can:') || str_starts_with($middleware, Authorize::class)));

            if (! $hasAuthorize) {
                $missing[] = $name;
            }
        }

        $this->assertSame([], $missing, 'Các route sau thiếu Authorize middleware (kiểm tra pattern group->middleware): '.implode(', ', $missing));
    }

    /**
     * Role ngoài gate bị chặn 403 ngay tại middleware (không đụng tới controller/DB).
     */
    public function test_forbidden_roles_get_403_on_sensitive_endpoints(): void
    {
        $this->buildMobileSchema();

        // CTV (tài khoản khách hàng) không được xem công nợ đại lý (chứa giá vốn).
        $ctv = $this->makeUser(['ctv']);
        $this->actingAs($ctv)->get(route('congno.daily.datatable'))->assertForbidden();
        $this->actingAs($ctv)->get(route('congno.daily.export'))->assertForbidden();

        // Sale cũng không được xem công nợ đại lý.
        $sale = $this->makeUser(['sale']);
        $this->actingAs($sale)->get(route('congno.daily.datatable'))->assertForbidden();

        // CS và OPS không có gate congno.index.
        $cs = $this->makeUser(['cs']);
        $this->actingAs($cs)->get(route('congno.datatable'))->assertForbidden();
        $this->actingAs($cs)->get(route('congno.export'))->assertForbidden();

        // Shipper bị RedirectShipperToMobile đẩy về giao diện mobile trước khi tới gate.
        $shipper = $this->makeUser(['shipper']);
        $this->actingAs($shipper)->get(route('orders.datatable'))
            ->assertRedirect(route('shipper.pickups'));

        // Sale không được vào nhân sự.
        $this->actingAs($sale)->get(route('nhansu.index', ['type' => 'sale']))->assertForbidden();

        $this->dropMobileSchema();
    }

    /**
     * Gate definitions vẫn cấp quyền đúng cho các role hợp lệ
     * (đảm bảo việc bật middleware không chặn nhầm người đang dùng).
     */
    public function test_allowed_roles_still_pass_gates(): void
    {
        $this->buildMobileSchema();

        $cases = [
            ['ctv', 'orders.index', true],
            ['ctv', 'congno.index', true],
            ['ctv', 'congno_daily.view', false],
            ['sale', 'orders.index', true],
            ['sale', 'congno.index', true],
            ['sale', 'congno_daily.view', false],
            ['cs', 'orders.index', true],
            ['cs', 'congno.index', false],
            ['ketoan', 'congno_daily.view', true],
            ['manager', 'congno_daily.view', true],
            ['admin', 'congno_daily.view', true],
            ['ops', 'orders.index', true],
            ['ops', 'congno.index', false],
            ['shipper', 'orders.index', false],
        ];

        foreach ($cases as [$role, $gate, $expected]) {
            $user = $this->makeUser([$role]);
            $this->assertSame(
                $expected,
                $user->can($gate),
                "Role [{$role}] gate [{$gate}] kỳ vọng ".($expected ? 'PASS' : 'DENY'),
            );
        }

        $this->dropMobileSchema();
    }
}
