<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Mobile\BuildsMobileSchema;
use Tests\TestCase;

/**
 * Trang tra cứu đơn công khai /theo-doi/{idbill} (không cần đăng nhập).
 *
 * Fix 2026-07-03: route cũ trỏ view('tracking.index') không tồn tại → 500.
 * Kiểm: trang render được, tìm theo id_bill/tracking_code, che thông tin
 * người nhận, không lộ giá/người gửi.
 */
class PublicTrackingPageTest extends TestCase
{
    use BuildsMobileSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildMobileSchema();
    }

    protected function tearDown(): void
    {
        $this->dropMobileSchema();

        parent::tearDown();
    }

    protected function makeOrder(array $attributes = []): Order
    {
        return Order::query()->create(array_merge([
            'uuid' => 'track-'.uniqid(),
            'id_bill' => 'BEE'.random_int(100000000, 999999999),
            'bill_status' => 'dang_phat_hang',
            'sender' => ['company' => 'Công ty Gửi Bí Mật', 'fullname' => 'Người Gửi', 'phone' => '0911222333', 'address' => 'HCM'],
            'receiver' => ['fullname' => 'Nguyễn Văn Đức', 'phone' => '0909123456', 'address' => 'Tokyo', 'city' => 'Tokyo', 'country' => 'JAPAN', 'postcode' => '100'],
            'service' => ['tensanpham' => 'Quần áo'],
        ], $attributes));
    }

    public function test_tracking_page_without_code_renders_search_form(): void
    {
        $response = $this->get('/theo-doi');

        $response->assertOk();
        $response->assertSee('Tra cứu đơn hàng của bạn');
    }

    public function test_tracking_page_finds_order_by_id_bill(): void
    {
        $order = $this->makeOrder();
        $order->histories()->create([
            'action' => 'tracking_status_auto',
            'thoigian' => now(),
            'trangthai' => 'Đang phát hàng',
            'diadiem' => 'Tokyo, JAPAN',
            'main' => true,
        ]);

        $response = $this->get('/theo-doi/'.$order->id_bill);

        $response->assertOk();
        $response->assertSee($order->id_bill);
        $response->assertSee('Đang phát hàng');
        $response->assertSee('Tokyo, JAPAN');
    }

    public function test_tracking_page_finds_order_by_tracking_code(): void
    {
        $order = $this->makeOrder(['tracking_code' => 'TRACKME123']);

        $response = $this->get('/theo-doi/TRACKME123');

        $response->assertOk();
        $response->assertSee($order->id_bill);
    }

    public function test_receiver_info_is_masked_and_sender_hidden(): void
    {
        $order = $this->makeOrder();

        $response = $this->get('/theo-doi/'.$order->id_bill);

        $response->assertOk();
        // Người nhận che: tên viết tắt, SĐT chỉ còn 3 số cuối.
        $response->assertSee('N*** V*** Đ***');
        $response->assertSee('*******456');
        $response->assertDontSee('Nguyễn Văn Đức');
        $response->assertDontSee('0909123456');
        // Không lộ người gửi.
        $response->assertDontSee('Công ty Gửi Bí Mật');
        $response->assertDontSee('0911222333');
    }

    public function test_unknown_code_shows_not_found_message_not_500(): void
    {
        $response = $this->get('/theo-doi/KHONGTONTAI999');

        $response->assertOk();
        $response->assertSee('Không tìm thấy đơn hàng');
    }

    public function test_malicious_code_input_is_rejected_gracefully(): void
    {
        // Payload không chứa "/" (slash trong path đã bị router chặn 404 sẵn).
        $response = $this->get('/theo-doi/'.urlencode('<script>alert(1)<x>'));

        $response->assertOk();
        // Controller loại keyword không hợp lệ → trang quay về form trống,
        // không echo lại payload.
        $response->assertSee('Tra cứu đơn hàng của bạn');
        $response->assertDontSee('<script>alert(1)', false);
    }
}
