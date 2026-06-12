<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\Mobile\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Models\Ward;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API locations cho app mobile — tỉnh/thành và phường/xã phục vụ form tạo pickup.
 */
class MobileLocationController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/mobile/ops/locations/provinces
     */
    public function provinces(Request $request): JsonResponse
    {
        $provinces = Province::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Province $p) => [
                'id' => $p->id,
                'name' => $p->name,
            ])
            ->all();

        return $this->ok(['provinces' => $provinces], 'OK');
    }

    /**
     * GET /api/mobile/ops/locations/wards
     */
    public function wards(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'province_id' => ['required', 'integer', 'exists:province,id'],
        ]);

        $wards = Ward::query()
            ->where('parent_code', $validated['province_id'])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Ward $w) => [
                'id' => $w->id,
                'name' => $w->name,
            ])
            ->all();

        return $this->ok(['wards' => $wards], 'OK');
    }
}
