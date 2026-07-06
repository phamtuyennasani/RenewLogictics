<?php

namespace App\Http\Controllers\Api;

use App\Actions\Order\ResolveServicePriceAction;
use App\Http\Controllers\Api\Mobile\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\News;
use App\Models\Setting;
use App\Models\ServicePriceList;
use App\Models\ZaloShippingRequest;
use App\Services\Zalo\ZaloMiniAppIdentityVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class ZaloMiniAppController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ResolveServicePriceAction $resolvePrice,
        protected ZaloMiniAppIdentityVerifier $zaloVerifier,
    ) {}

    public function bootstrap(Request $request): JsonResponse
    {
        $serviceId = $request->integer('service_id') ?: null;

        return $this->ok([
            'services' => $this->servicesWithPriceList(),
            'countries' => $this->countriesForService($serviceId),
            'dim' => $this->systemDim(),
        ]);
    }

    public function countries(Request $request): JsonResponse
    {
        return $this->ok([
            'items' => $this->countriesForService($request->integer('service_id') ?: null),
        ]);
    }

    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer'],
            'country_id' => ['required', 'integer'],
            'g_weight' => ['required', 'numeric', 'min:0.01', 'max:10000'],
            'length' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'width' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'height' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ], [], [
            'g_weight' => 'cân nặng',
            'length' => 'chiều dài',
            'width' => 'chiều rộng',
            'height' => 'chiều cao',
        ]);

        [$quote, $error] = $this->calculateQuote($validated);

        if ($error !== null) {
            return $this->fail($error, 422);
        }

        return $this->ok($quote, 'Đã tính cước tham khảo.');
    }

    public function storeShippingRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'requester_name' => ['required', 'string', 'max:191'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:191'],
            'company' => ['nullable', 'string', 'max:191'],
            'pickup_address' => ['required', 'string', 'max:500'],
            'pickup_city' => ['nullable', 'string', 'max:191'],
            'receiver_country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'receiver_country' => ['nullable', 'string', 'max:191'],
            'service_id' => ['nullable', 'integer', 'exists:news,id'],
            'service_name' => ['nullable', 'string', 'max:191'],
            'package_count' => ['required', 'integer', 'min:1', 'max:1000'],
            'weight_kg' => ['required', 'numeric', 'min:0.01', 'max:10000'],
            'length_cm' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'width_cm' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'height_cm' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'note' => ['nullable', 'string', 'max:1000'],
            'quote_snapshot' => ['nullable', 'array'],
            'zalo_access_token' => ['nullable', 'string', 'max:2048'],
        ]);

        $zaloUserId = null;
        $accessToken = trim((string) ($validated['zalo_access_token'] ?? ''));

        if ($accessToken !== '') {
            try {
                $zaloUserId = $this->zaloVerifier->verifyAccessToken($accessToken)['id'];
            } catch (Throwable) {
                return $this->fail('Không xác thực được tài khoản Zalo. Vui lòng thử lại.', 422);
            }
        }

        $shippingRequest = ZaloShippingRequest::query()->create([
            ...collect($validated)->except('zalo_access_token')->all(),
            'zalo_user_id' => $zaloUserId,
            'status' => 'new',
        ]);

        return $this->ok([
            'id' => $shippingRequest->id,
            'code' => 'ZMA'.str_pad((string) $shippingRequest->id, 6, '0', STR_PAD_LEFT),
            'status' => $shippingRequest->status,
        ], 'Đã ghi nhận yêu cầu gửi hàng.', 201);
    }

    protected function calculateQuote(array $validated): array
    {
        $service = ['id_dichvu' => (int) $validated['service_id']];
        $receiver = ['country_id' => (int) $validated['country_id']];
        $packages = [[
            'number_of_package' => 1,
            'length' => (float) ($validated['length'] ?? 0),
            'width' => (float) ($validated['width'] ?? 0),
            'height' => (float) ($validated['height'] ?? 0),
            'g_weight' => (float) $validated['g_weight'],
        ]];

        try {
            $resolved = $this->resolvePrice->execute($service, $receiver, $packages, $this->systemDim());
        } catch (Throwable $e) {
            return [null, $e->getMessage()];
        }

        $salePrice = (float) $resolved['sale_price'];

        if (! $resolved['detail'] || $salePrice <= 0) {
            return [null, 'Chưa có bảng giá cho tuyến này với mức cân của bạn. Vui lòng liên hệ để được báo giá trực tiếp.'];
        }

        return [[
            'sale_price' => $salePrice,
            'chargeable_weight' => (float) $resolved['chargeable_weight'],
            'quycach' => (string) $resolved['detail']->quycach,
            'unit_price' => (float) $resolved['detail']->sale_price,
        ], null];
    }

    protected function servicesWithPriceList()
    {
        return News::query()
            ->where('type', News::TYPE_MAIN_SERVICE)
            ->whereIn('id', ServicePriceList::query()->select('service_id'))
            ->orderBy('namevi')
            ->get(['id', 'namevi as name']);
    }

    protected function countriesForService(?int $serviceId)
    {
        return DB::table('countries')
            ->join('service_price_list_countries', 'countries.id', '=', 'service_price_list_countries.country_id')
            ->join('service_price_lists', 'service_price_lists.id', '=', 'service_price_list_countries.service_price_list_id')
            ->when($serviceId, fn ($query) => $query->where('service_price_lists.service_id', $serviceId))
            ->distinct()
            ->orderBy('countries.name')
            ->get(['countries.id', 'countries.name']);
    }

    protected function systemDim(): float
    {
        $dim = (float) data_get(Setting::query()->first()?->options, 'dim', 0);

        return $dim > 0 ? $dim : 6000;
    }
}
