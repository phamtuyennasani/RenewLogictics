<?php

namespace App\Http\Controllers;

use App\Actions\Order\ResolveServicePriceAction;
use App\Models\News;
use App\Models\Setting;
use App\Models\ServicePriceList;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Trang tra cước công khai /tra-cuoc — khách tự ước tính cước KHÔNG cần
 * đăng nhập (quote calculator).
 *
 * Nguyên tắc dữ liệu (trang public):
 * - CHỈ hiện cước bán (sale_price) — tuyệt đối không lộ cước vốn/gốc.
 * - Chỉ liệt kê dịch vụ ĐANG có bảng giá (tránh khách chọn dịch vụ ra giá 0).
 * - Giá là THAM KHẢO, chưa gồm phụ phí/VAT — disclaimer bắt buộc trên UI.
 * - Tính toán dùng lại nguyên vẹn ResolveServicePriceAction +
 *   CalculateChargeableWeightAction (cùng công thức với tạo đơn).
 */
class PublicQuoteController extends Controller
{
    public function __construct(protected ResolveServicePriceAction $resolvePrice)
    {
    }

    public function __invoke(Request $request): View
    {
        $services = $this->servicesWithPriceList();
        $countries = $this->countriesForService($request->integer('service_id'));

        $quote = null;
        $error = null;

        if ($request->filled(['service_id', 'country_id'])) {
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

            [$quote, $error] = $this->calculate($validated);
        }

        return view('quote.index', [
            'services' => $services,
            'countries' => $countries,
            'input' => [
                'service_id' => $request->integer('service_id') ?: null,
                'country_id' => $request->integer('country_id') ?: null,
                'g_weight' => $request->input('g_weight', ''),
                'length' => $request->input('length', ''),
                'width' => $request->input('width', ''),
                'height' => $request->input('height', ''),
            ],
            'quote' => $quote,
            'error' => $error,
            'dim' => $this->systemDim(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: ?array<string, mixed>, 1: ?string}
     */
    protected function calculate(array $validated): array
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

        // CHỈ trả về dữ liệu an toàn cho public — không đụng cost/base price.
        return [[
            'sale_price' => $salePrice,
            'chargeable_weight' => (float) $resolved['chargeable_weight'],
            'quycach' => (string) $resolved['detail']->quycach,
            'unit_price' => (float) $resolved['detail']->sale_price,
        ], null];
    }

    /**
     * Dịch vụ chính đang có ít nhất 1 bảng giá.
     *
     * @return \Illuminate\Support\Collection<int, News>
     */
    protected function servicesWithPriceList()
    {
        return News::query()
            ->where('type', News::TYPE_MAIN_SERVICE)
            ->whereIn('id', ServicePriceList::query()->select('service_id'))
            ->orderBy('namevi')
            ->get(['id', 'namevi']);
    }

    /**
     * Quốc gia áp dụng của các bảng giá (giới hạn theo dịch vụ nếu đã chọn).
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function countriesForService(?int $serviceId)
    {
        return \Illuminate\Support\Facades\DB::table('countries')
            ->join('service_price_list_countries', 'countries.id', '=', 'service_price_list_countries.country_id')
            ->join('service_price_lists', 'service_price_lists.id', '=', 'service_price_list_countries.service_price_list_id')
            ->when($serviceId, fn ($q) => $q->where('service_price_lists.service_id', $serviceId))
            ->distinct()
            ->orderBy('countries.name')
            ->get(['countries.id', 'countries.name']);
    }

    protected function systemDim(): float
    {
        // Đọc qua cast json của model (portable MySQL/SQLite) thay vì JSON_EXTRACT raw.
        $dim = (float) data_get(Setting::query()->first()?->options, 'dim', 0);

        return $dim > 0 ? $dim : 6000;
    }
}
