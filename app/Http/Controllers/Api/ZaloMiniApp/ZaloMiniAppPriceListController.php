<?php

namespace App\Http\Controllers\Api\ZaloMiniApp;

use App\Http\Controllers\Api\Mobile\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\News;
use App\Models\ServicePriceList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ZaloMiniAppPriceListController extends Controller
{
    use ApiResponse;

    public function bootstrap(Request $request): JsonResponse
    {
        if (! $request->user()->can('service-prices.manage')) {
            return $this->fail('Bạn không có quyền quản lý bảng giá.', 403);
        }

        return $this->ok([
            'services' => News::query()
                ->where('type', News::TYPE_MAIN_SERVICE)
                ->orderBy('namevi')
                ->get(['id', 'namevi as name']),
            'countries' => Country::query()
                ->orderBy('name')
                ->get(['id', 'name', 'iso2']),
        ], 'OK');
    }

    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->can('service-prices.manage')) {
            return $this->fail('Bạn không có quyền quản lý bảng giá.', 403);
        }

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        $query = ServicePriceList::query()
            ->with(['service:id,namevi', 'countries:id,name,iso2'])
            ->withCount('details')
            ->latest('updated_at');

        if (filled($validated['search'] ?? null)) {
            $keyword = trim((string) $validated['search']);
            $query->where(function ($sub) use ($keyword) {
                $sub->where('name', 'like', '%'.$keyword.'%')
                    ->orWhereHas('service', fn ($service) => $service->where('namevi', 'like', '%'.$keyword.'%'))
                    ->orWhereHas('countries', fn ($country) => $country->where('name', 'like', '%'.$keyword.'%'));
            });
        }

        $page = $query->paginate(
            perPage: (int) ($validated['per_page'] ?? 15),
            page: (int) ($validated['page'] ?? 1),
        );

        return $this->ok([
            'items' => $page->getCollection()->map(fn (ServicePriceList $list) => $this->listPayload($list))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
                'has_more' => $page->hasMorePages(),
            ],
        ], 'OK');
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->can('service-prices.manage')) {
            return $this->fail('Bạn không có quyền quản lý bảng giá.', 403);
        }

        $validated = $this->validateList($request);
        $details = $this->validatedDetails($validated['details'] ?? []);

        if ($details === []) {
            return $this->fail('Vui lòng nhập ít nhất một dòng giá.', 422);
        }

        $priceList = DB::transaction(function () use ($request, $validated, $details) {
            $priceList = ServicePriceList::query()->create([
                'name' => trim($validated['name']),
                'service_id' => (int) $validated['service_id'],
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $priceList->countries()->sync(array_map('intval', $validated['country_ids']));
            $priceList->details()->createMany($details);

            return $priceList;
        });

        return $this->ok($this->detailPayload($priceList->fresh(['service', 'countries', 'details'])), 'Đã tạo bảng giá.', 201);
    }

    public function show(Request $request, ServicePriceList $priceList): JsonResponse
    {
        if (! $request->user()->can('service-prices.manage')) {
            return $this->fail('Bạn không có quyền quản lý bảng giá.', 403);
        }

        return $this->ok($this->detailPayload($priceList->load(['service', 'countries', 'details'])), 'OK');
    }

    public function update(Request $request, ServicePriceList $priceList): JsonResponse
    {
        if (! $request->user()->can('service-prices.manage')) {
            return $this->fail('Bạn không có quyền quản lý bảng giá.', 403);
        }

        $validated = $this->validateList($request, detailsRequired: false);
        $details = array_key_exists('details', $validated)
            ? $this->validatedDetails($validated['details'] ?? [])
            : null;

        DB::transaction(function () use ($request, $priceList, $validated, $details) {
            $priceList->fill([
                'name' => trim($validated['name']),
                'service_id' => (int) $validated['service_id'],
                'updated_by' => $request->user()->id,
            ])->save();

            $priceList->countries()->sync(array_map('intval', $validated['country_ids']));

            if (is_array($details)) {
                $priceList->details()->delete();
                $priceList->details()->createMany($details);
            }
        });

        return $this->ok($this->detailPayload($priceList->fresh(['service', 'countries', 'details'])), 'Đã cập nhật bảng giá.');
    }

    public function updateDetails(Request $request, ServicePriceList $priceList): JsonResponse
    {
        if (! $request->user()->can('service-prices.manage')) {
            return $this->fail('Bạn không có quyền quản lý bảng giá.', 403);
        }

        $validated = $request->validate([
            'details' => ['required', 'array', 'min:1', 'max:500'],
            'details.*.quycach' => ['required', Rule::in(['CO_DINH', 'DON_GIA'])],
            'details.*.weight_from' => ['required', 'numeric', 'min:0', 'max:100000'],
            'details.*.weight_to' => ['required', 'numeric', 'min:0', 'max:100000'],
            'details.*.sale_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'details.*.cost_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'details.*.base_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ]);

        $details = $this->validatedDetails($validated['details']);

        DB::transaction(function () use ($request, $priceList, $details) {
            $priceList->forceFill(['updated_by' => $request->user()->id])->save();
            $priceList->details()->delete();
            $priceList->details()->createMany($details);
        });

        return $this->ok($this->detailPayload($priceList->fresh(['service', 'countries', 'details'])), 'Đã cập nhật dòng giá.');
    }

    public function destroy(Request $request, ServicePriceList $priceList): JsonResponse
    {
        if (! $request->user()->can('service-prices.delete')) {
            return $this->fail('Chỉ admin được xóa bảng giá.', 403);
        }

        $priceList->delete();

        return $this->ok(null, 'Đã xóa bảng giá.');
    }

    private function validateList(Request $request, bool $detailsRequired = true): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'service_id' => ['required', 'integer', Rule::exists('news', 'id')->where('type', News::TYPE_MAIN_SERVICE)],
            'country_ids' => ['required', 'array', 'min:1'],
            'country_ids.*' => ['integer', 'exists:countries,id'],
            'details' => [$detailsRequired ? 'required' : 'sometimes', 'array', 'min:1', 'max:500'],
            'details.*.quycach' => ['required_with:details', Rule::in(['CO_DINH', 'DON_GIA'])],
            'details.*.weight_from' => ['required_with:details', 'numeric', 'min:0', 'max:100000'],
            'details.*.weight_to' => ['required_with:details', 'numeric', 'min:0', 'max:100000'],
            'details.*.sale_price' => ['required_with:details', 'numeric', 'min:0', 'max:999999999'],
            'details.*.cost_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'details.*.base_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ]);
    }

    private function validatedDetails(array $rows): array
    {
        $details = collect($rows)
            ->map(fn (array $row) => [
                'quycach' => $row['quycach'],
                'weight_from' => round((float) $row['weight_from'], 2),
                'weight_to' => round((float) $row['weight_to'], 2),
                'sale_price' => round((float) $row['sale_price'], 2),
                'cost_price' => round((float) ($row['cost_price'] ?? 0), 2),
                'base_price' => round((float) ($row['base_price'] ?? 0), 2),
            ])
            ->values()
            ->all();

        foreach ($details as $index => $row) {
            if ($row['weight_to'] < $row['weight_from']) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Cân nặng đến phải lớn hơn hoặc bằng cân nặng từ.',
                    'errors' => ['details.'.$index.'.weight_to' => ['Khoảng cân không hợp lệ.']],
                ], 422));
            }
        }

        collect($details)
            ->groupBy('quycach')
            ->each(function ($group) {
                $previous = null;

                foreach ($group->sortBy('weight_from')->values() as $row) {
                    if ($previous && $row['weight_from'] <= $previous['weight_to']) {
                        abort(response()->json([
                            'success' => false,
                            'message' => 'Khoảng cân nặng trong cùng quy cách không được giao nhau.',
                        ], 422));
                    }

                    $previous = $row;
                }
            });

        return $details;
    }

    private function listPayload(ServicePriceList $list): array
    {
        return [
            'id' => $list->id,
            'name' => $list->name,
            'service' => [
                'id' => $list->service_id,
                'name' => $list->service?->namevi,
            ],
            'countries' => $list->countries
                ->map(fn (Country $country) => [
                    'id' => $country->id,
                    'name' => $country->name,
                    'iso2' => $country->iso2,
                ])
                ->values(),
            'details_count' => (int) ($list->details_count ?? $list->details()->count()),
            'updated_at' => $list->updated_at?->toIso8601String(),
        ];
    }

    private function detailPayload(ServicePriceList $list): array
    {
        return [
            ...$this->listPayload($list),
            'details' => $list->details
                ->map(fn ($detail) => [
                    'id' => $detail->id,
                    'quycach' => $detail->quycach,
                    'weight_from' => (float) $detail->weight_from,
                    'weight_to' => (float) $detail->weight_to,
                    'sale_price' => (float) $detail->sale_price,
                    'cost_price' => (float) $detail->cost_price,
                    'base_price' => (float) $detail->base_price,
                ])
                ->values(),
        ];
    }
}
