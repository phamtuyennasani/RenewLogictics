<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\Mobile\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

/**
 * Cấu hình công khai cho app mobile (không nhạy cảm).
 *
 * Trả các giá trị client BẮT BUỘC phải biết để render, ví dụ VietMap Tile API
 * Key dùng tải tile/style trực tiếp từ maps.vietmap.vn (không qua proxy server).
 *
 * LƯU Ý: chỉ trả tile key (giới hạn quyền render tile). Geocode/Route key có
 * quyền cao hơn, KHÔNG bao giờ xuống client — luôn đi qua VietmapProxyController.
 */
class MobileConfigController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/mobile/config — cấu hình public cho app.
     */
    public function index(): JsonResponse
    {
        $options = Setting::query()->value('options') ?? [];

        return $this->ok([
            'vietmap' => [
                'tile_api_key' => (string) data_get($options, 'vietmap_tile_api_key', ''),
            ],
        ]);
    }
}
