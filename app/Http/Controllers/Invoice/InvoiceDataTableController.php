<?php

namespace App\Http\Controllers\Invoice;

use App\Enums\DebtStatusEnum;
use App\Enums\InvoicePaymentStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\CongNo;
use App\Models\CongNoPayment;
use App\Models\User;
use App\Services\Payments\InvoiceCodeGenerator;
use App\Services\Payments\PaymentProviderManager;
use App\Services\Payments\Data\PaymentRequestData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class InvoiceDataTableController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $response = DataTables::eloquent($this->query($request))
            ->addColumn('invoice_code', function (CongNoPayment $invoice) {
                $debt = $invoice->congNo;
                $url = $debt ? route('congno.show', $debt->uuid) : '#';

                return '<a href="'.$url.'" class="font-mono font-semibold text-primary-700 hover:text-primary-800">'
                    .e($invoice->ma_hoa_don ?? '-')
                    .'</a>';
            })
            ->addColumn('debt_code', function (CongNoPayment $invoice) {
                $debt = $invoice->congNo;

                return $debt
                    ? '<span class="font-semibold text-neutral-800">'.e($debt->sohoadon).'</span>'
                    : '<span class="text-neutral-400">-</span>';
            })
            ->addColumn('customer_info', function (CongNoPayment $invoice) {
                $debt = $invoice->congNo;
                if (! $debt || ! $debt->customer) {
                    return '<span class="text-neutral-400">-</span>';
                }

                $customer = $debt->customer;

                return '<div class="max-w-[200px] truncate font-semibold text-neutral-900">'
                    .e($customer->fullname ?: $customer->username)
                    .'</div>';
            })
            ->addColumn('sale_info', function (CongNoPayment $invoice) {
                $debt = $invoice->congNo;
                if (! $debt || ! $debt->sale) {
                    return '<span class="text-neutral-400">-</span>';
                }

                $sale = $debt->sale;

                return '<div class="max-w-[150px] truncate text-neutral-800">'
                    .e($sale->fullname ?: $sale->username)
                    .'</div>';
            })
            ->addColumn('amount', function (CongNoPayment $invoice) {
                return '<span class="font-semibold text-neutral-950">'.$this->money($invoice->amount).'</span>';
            })
            ->addColumn('status_badge', function (CongNoPayment $invoice) {
                $status = $invoice->status ?? InvoicePaymentStatusEnum::MOI_TAO;

                return '<span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold '.$status->color().'">'
                    .e($status->label())
                    .'</span>';
            })
            ->addColumn('created_at', function (CongNoPayment $invoice) {
                return $invoice->created_at?->format('d/m/Y H:i') ?: '-';
            })
            ->addColumn('paid_at', function (CongNoPayment $invoice) {
                $status = $invoice->status ?? null;
                if ($status && $status->isPaid()) {
                    return '<span class="text-emerald-700 font-medium">'.$invoice->paid_at?->format('d/m/Y H:i').'</span>';
                }

                return '-';
            })
            ->addColumn('creator', function (CongNoPayment $invoice) {
                $user = $invoice->user;

                return $user
                    ? '<span class="text-neutral-700">'.e($user->fullname ?: $user->username).'</span>'
                    : '-';
            })
            ->addColumn('actions', function (CongNoPayment $invoice) use ($request) {
                return $this->actionsHtml($invoice, $request);
            })
            ->setRowId(fn (CongNoPayment $invoice) => 'invoice-'.$invoice->id)
            ->rawColumns(['invoice_code', 'debt_code', 'customer_info', 'sale_info', 'amount', 'status_badge', 'paid_at', 'creator', 'actions'])
            ->toJson();

        $payload = $response->getData(true);
        $payload['statusCounts'] = $this->statusCounts($request);
        $payload['summary'] = $this->summary($request);

        return response()->json($payload);
    }

    protected function actionsHtml(CongNoPayment $invoice, Request $request): string
    {
        $user = $request->user();
        $actions = [];

        if ($invoice->canApprove($user)) {
            $actions[] = '<button type="button" data-invoice-approve="'.$invoice->id.'" class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-primary-700">Duyệt</button>';
        }

        if ($invoice->canPay($user)) {
            $actions[] = '<button type="button" data-invoice-cash="'.$invoice->id.'" data-invoice-code="'.e($invoice->ma_hoa_don).'" data-invoice-amount="'.$this->money($invoice->amount).'" class="inline-flex items-center justify-center rounded-lg border border-amber-200 bg-white px-3 py-1.5 text-xs font-semibold text-amber-700 transition hover:bg-amber-50">Tiền mặt</button>';
            $actions[] = '<button type="button" data-invoice-qr="'.$invoice->id.'" class="inline-flex items-center justify-center rounded-lg border border-indigo-200 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-50">Thanh toán online</button>';
        }

        if ($invoice->canConfirmCashPayment($user)) {
            $actions[] = '<button type="button" data-invoice-confirm-cash="'.$invoice->id.'" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700">Xác nhận thu</button>';
        }

        if ($invoice->canManageQr($user)) {
            if ($invoice->payment_url || $invoice->qr_url) {
                $actions[] = '<a href="'.e($invoice->payment_url ?: $invoice->qr_url).'" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 transition hover:bg-neutral-50">Mở thanh toán</a>';
            }

            if ($invoice->canRegenerateQr()) {
                $actions[] = '<button type="button" data-invoice-regenerate-qr="'.$invoice->id.'" class="inline-flex items-center justify-center rounded-lg border border-indigo-200 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-50">Tạo lại QR</button>';
            } else {
                $nextAt = $invoice->nextQrAvailableAt();
                $title = $nextAt ? 'Có thể tạo lại sau '.$nextAt->format('H:i d/m/Y') : 'Chưa thể tạo lại QR';
                $actions[] = '<button type="button" disabled title="'.e($title).'" class="inline-flex cursor-not-allowed items-center justify-center rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-1.5 text-xs font-semibold text-neutral-400">Tạo lại QR</button>';
            }
        }

        if ($invoice->canCancel($user)) {
            $actions[] = '<button type="button" data-invoice-cancel="'.$invoice->id.'" data-invoice-code="'.e($invoice->ma_hoa_don).'" data-invoice-amount="'.$this->money($invoice->amount).'" class="inline-flex items-center justify-center rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-50">Hủy</button>';
        }

        if ($actions === []) {
            return '<span class="text-xs text-neutral-400">-</span>';
        }

        return '<div class="flex flex-wrap items-center gap-2">'.implode('', $actions).'</div>';
    }

    protected function query(Request $request, bool $includeStatus = true): Builder
    {
        $user = $request->user();

        return CongNoPayment::query()
            ->with([
                'user:id,fullname,username',
                'approver:id,fullname,username',
                'paymentConfirmer:id,fullname,username',
                'congNo.customer:id,fullname,username,code',
                'congNo.sale:id,fullname,username,code',
            ])
            ->where('loai_hoa_don', 'thu')
            ->when($user->hasRole('sale'), function ($q) use ($user) {
                $q->whereHas('congNo', fn ($d) => $d->where('id_sale', $user->id));
            })
            ->when($user->hasRole('ctv'), function ($q) use ($user) {
                $q->whereHas('congNo', fn ($d) => $d->where('id_customer', $user->id));
            })
            ->when($includeStatus && $request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('fromDate'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('fromDate')))
            ->when($request->filled('toDate'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('toDate')))
            ->when($request->filled('customerId'), function ($q) use ($request) {
                $q->whereHas('congNo', fn ($d) => $d->where('id_customer', $request->integer('customerId')));
            })
            ->when($request->filled('saleId'), function ($q) use ($request) {
                $q->whereHas('congNo', fn ($d) => $d->where('id_sale', $request->integer('saleId')));
            })
            ->when(filled($request->input('search.value')), function ($q) use ($request) {
                $keyword = '%'.trim((string) $request->input('search.value')).'%';
                $q->where(function ($sub) use ($keyword) {
                    $sub->where('ma_hoa_don', 'like', $keyword)
                        ->orWhere('reference', 'like', $keyword)
                        ->orWhereHas('congNo', fn ($d) => $d->where('sohoadon', 'like', $keyword))
                        ->orWhereHas('user', fn ($u) => $u->where('fullname', 'like', $keyword)->orWhere('username', 'like', $keyword));
                });
            })
            ->latest('id');
    }

    protected function statusCounts(Request $request): array
    {
        $counts = array_fill_keys(InvoicePaymentStatusEnum::values(), 0);

        $this->query($request, includeStatus: false)
            ->reorder()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->get()
            ->each(function ($row) use (&$counts) {
                $status = (string) $row->getRawOriginal('status');
                if (isset($counts[$status])) {
                    $counts[$status] = (int) $row->total;
                }
            });

        return ['all' => array_sum($counts), ...$counts];
    }

    protected function summary(Request $request): array
    {
        $items = $this->query($request, includeStatus: false)->get();
        $total = (float) $items->sum('amount');
        $paid = (float) $items->where('status', InvoicePaymentStatusEnum::DA_THANH_TOAN->value)->sum('amount');
        $pending = (float) $items->filter(fn ($inv) => $inv->status?->isOpen())->sum('amount');
        $cancelled = (float) $items->where('status', InvoicePaymentStatusEnum::HUY->value)->sum('amount');

        return [
            'total' => $total,
            'paid' => $paid,
            'pending' => $pending,
            'cancelled' => $cancelled,
            'paid_percent' => $this->percentOf($paid, $total),
            'pending_percent' => $this->percentOf($pending, $total),
            'cancelled_percent' => $this->percentOf($cancelled, $total),
        ];
    }

    protected function percentOf(mixed $value, mixed $total): float
    {
        $total = (float) $total;

        if ($total <= 0) {
            return 0;
        }

        return round(min(100, max(0, ((float) $value / $total) * 100)), 2);
    }

    public function sales(Request $request): JsonResponse
    {
        $users = User::role('sale')->orderBy('fullname')->get(['id', 'fullname', 'username', 'code']);

        return response()->json(['sales' => $users->map(fn (User $u) => [
            'id' => $u->id,
            'label' => trim(($u->fullname ?: $u->username).($u->code ? " ({$u->code})" : '')),
        ])->values()]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $invoice = CongNoPayment::whereKey($id)->firstOrFail();

        if (! $invoice->canApprove($user)) {
            return response()->json(['message' => 'Không có quyền duyệt hóa đơn này.'], 403);
        }

        if ($invoice->status !== InvoicePaymentStatusEnum::MOI_TAO) {
            return response()->json(['message' => 'Hóa đơn không ở trạng thái Mới tạo, không thể duyệt.'], 422);
        }

        $fromStatus = $invoice->status;

        $invoice->forceFill([
            'status' => InvoicePaymentStatusEnum::DA_DUYET->value,
            'id_ketoan' => $user->id,
            'approved_by' => $user->id,
            'ngay_duyet' => now(),
        ])->save();

        $invoice->writeStatusLog('approved', $fromStatus, InvoicePaymentStatusEnum::DA_DUYET, $user->id);

        return response()->json(['message' => "Đã duyệt hóa đơn {$invoice->ma_hoa_don}."]);
    }

    public function submitCashPayment(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $invoice = CongNoPayment::whereKey($id)->firstOrFail();

        if (! $invoice->canPay($user)) {
            return response()->json(['message' => 'Hóa đơn chưa thể gửi thanh toán tiền mặt.'], 403);
        }

        $validated = $request->validate([
            'photo' => ['required', 'image', 'max:8192'],
        ], [
            'photo.required' => 'Vui lòng upload ảnh hóa đơn thanh toán.',
            'photo.image' => 'File phải là ảnh.',
            'photo.max' => 'Ảnh không vượt quá 8MB.',
        ]);

        $path = $validated['photo']->store('customer-debt-invoices', 'public');

        $fromStatus = $invoice->status;

        $invoice->forceFill([
            'method' => 'cash',
            'photo' => $path,
            'status' => InvoicePaymentStatusEnum::DA_GUI_HOA_DON_TT->value,
            'submitted_at' => now(),
            'paid_at' => null,
        ])->save();

        $invoice->writeStatusLog('cash_submitted', $fromStatus, InvoicePaymentStatusEnum::DA_GUI_HOA_DON_TT, $user->id, null, [
            'photo' => $path,
        ]);

        return response()->json(['message' => "Đã gửi hóa đơn thanh toán {$invoice->ma_hoa_don}."]);
    }

    public function submitOnlinePayment(Request $request, int $id): JsonResponse
    {
        $invoice = CongNoPayment::whereKey($id)->firstOrFail();

        if (! $invoice->canPay($request->user())) {
            return response()->json(['message' => 'Hóa đơn chưa thể tạo QR thanh toán.'], 403);
        }

        try {
            DB::transaction(function () use ($invoice) {
                $locked = CongNoPayment::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

                if (! $locked->canPay(request()->user())) {
                    throw new \RuntimeException('Hóa đơn chưa thể tạo QR thanh toán.');
                }

                $fromStatus = $locked->status;
                $this->fillQrPayment($locked, updateStatus: true);
                $locked->writeStatusLog('qr_requested', $fromStatus, InvoicePaymentStatusEnum::DA_GUI_YEU_CAU_TT, request()->user()?->id, null, [
                    'qr_payment_code' => $locked->qr_payment_code,
                ]);
            });
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => "Đã tạo yêu cầu thanh toán online cho hóa đơn {$invoice->ma_hoa_don}.",
            'qr_url' => $invoice->fresh()->qr_url,
            'payment_url' => $invoice->fresh()->payment_url,
        ]);
    }

    public function regenerateQr(Request $request, int $id): JsonResponse
    {
        $invoice = CongNoPayment::whereKey($id)->firstOrFail();

        if (! ($invoice->canPay($request->user()) || $invoice->canManageQr($request->user()))) {
            return response()->json(['message' => 'Không có quyền tạo lại QR cho hóa đơn này.'], 403);
        }

        try {
            DB::transaction(function () use ($invoice, $request) {
                $locked = CongNoPayment::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

                if (! ($locked->canPay($request->user()) || $locked->canManageQr($request->user()))) {
                    throw new \RuntimeException('Không có quyền tạo lại QR cho hóa đơn này.');
                }

                if (! $locked->canRegenerateQr()) {
                    $nextAt = $locked->nextQrAvailableAt();

                    throw new \RuntimeException($nextAt
                        ? 'Vui lòng đợi đến '.$nextAt->format('H:i d/m/Y').' để tạo lại QR.'
                        : 'Vui lòng đợi đủ '.CongNoPayment::QR_THROTTLE_MINUTES.' phút để tạo lại QR.');
                }

                $fromStatus = $locked->status;
                $toStatus = $locked->status === InvoicePaymentStatusEnum::DA_GUI_YEU_CAU_TT
                    ? $locked->status
                    : InvoicePaymentStatusEnum::DA_GUI_YEU_CAU_TT;

                $this->fillQrPayment($locked, updateStatus: $locked->status !== InvoicePaymentStatusEnum::DA_GUI_YEU_CAU_TT);
                $locked->writeStatusLog('qr_regenerated', $fromStatus, $toStatus, $request->user()?->id, null, [
                    'qr_payment_code' => $locked->qr_payment_code,
                ]);
            });
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => "Đã tạo lại yêu cầu thanh toán online cho hóa đơn {$invoice->ma_hoa_don}.",
            'qr_url' => $invoice->fresh()->qr_url,
            'payment_url' => $invoice->fresh()->payment_url,
        ]);
    }

    public function confirmCashPayment(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $invoice = CongNoPayment::whereKey($id)->firstOrFail();

        if (! $invoice->canConfirmCashPayment($user)) {
            return response()->json(['message' => 'Không có quyền xác nhận thanh toán hóa đơn này.'], 403);
        }

        DB::transaction(function () use ($invoice, $user) {
            $debt = CongNo::query()->whereKey($invoice->id_congno)->lockForUpdate()->firstOrFail();
            $locked = CongNoPayment::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            $locked->forceFill([
                'status' => InvoicePaymentStatusEnum::DA_THANH_TOAN->value,
                'paid_at' => now(),
                'id_ketoan' => $user->id,
                'payment_confirmed_by' => $user->id,
            ])->save();

            $locked->writeStatusLog('cash_confirmed', InvoicePaymentStatusEnum::DA_GUI_HOA_DON_TT, InvoicePaymentStatusEnum::DA_THANH_TOAN, $user->id);

            $debt->syncPaidAmountFromPayments();
            $debt->refresh();

            $orderStatus = $debt->status === DebtStatusEnum::DA_THANH_TOAN
                ? DebtStatusEnum::DA_THANH_TOAN->value
                : DebtStatusEnum::DA_THANH_TOAN_MOT_PHAN->value;

            $debt->orders()->update([
                'customer_payment_status' => $orderStatus,
                'customer_paid_at' => $orderStatus === DebtStatusEnum::DA_THANH_TOAN->value ? now() : null,
            ]);
        });

        return response()->json(['message' => "Đã xác nhận thanh toán hóa đơn {$invoice->ma_hoa_don}."]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $invoice = CongNoPayment::whereKey($id)->firstOrFail();

        if (! $invoice->canCancel($user)) {
            return response()->json(['message' => 'Không có quyền hủy hóa đơn này hoặc hóa đơn đã ở trạng thái cuối.'], 403);
        }

        $validated = $request->validate([
            'cancel_reason' => ['required', 'string', 'max:500'],
        ], [
            'cancel_reason.required' => 'Vui lòng nhập ghi chú hủy hóa đơn.',
            'cancel_reason.string' => 'Ghi chú hủy không hợp lệ.',
            'cancel_reason.max' => 'Ghi chú hủy không vượt quá 500 ký tự.',
        ]);

        $fromStatus = $invoice->status;

        $invoice->forceFill([
            'status' => InvoicePaymentStatusEnum::HUY->value,
            'cancelled_at' => now(),
            'id_cancelled_by' => $user->id,
            'cancel_reason' => $validated['cancel_reason'],
            'payment_url' => null,
            'qr_url' => null,
            'qr_expires_at' => null,
        ])->save();

        $invoice->writeStatusLog('cancelled', $fromStatus, InvoicePaymentStatusEnum::HUY, $user->id, $validated['cancel_reason']);

        return response()->json(['message' => "Đã hủy hóa đơn {$invoice->ma_hoa_don}."]);
    }

    protected function fillQrPayment(CongNoPayment $invoice, bool $updateStatus): void
    {
        $code = $invoice->payment_reference
            ?: $invoice->qr_payment_code
            ?: app(InvoiceCodeGenerator::class)->generatePaymentCode($invoice->ma_hoa_don);
        $provider = app(PaymentProviderManager::class)->driver($invoice->payment_provider);
        $intent = $provider->createPayment(new PaymentRequestData(
            amount: (int) round((float) $invoice->amount),
            reference: $code,
            description: $code,
            expiresAt: now()->addMinutes(CongNoPayment::QR_THROTTLE_MINUTES),
        ));

        $attributes = [
            'method' => $intent->channel === 'qr' ? 'bank_transfer' : 'online',
            'payment_provider' => $intent->provider,
            'payment_channel' => $intent->channel,
            'payment_reference' => $intent->reference,
            'payment_url' => $intent->paymentUrl,
            'provider_intent_id' => $intent->providerIntentId,
            'provider_payload' => $intent->raw ?: null,
            'qr_payment_code' => $intent->reference,
            'qr_url' => $intent->qrUrl,
            'qr_generated_at' => now(),
            'qr_expires_at' => $intent->expiresAt,
        ];

        if ($updateStatus) {
            $attributes['status'] = InvoicePaymentStatusEnum::DA_GUI_YEU_CAU_TT->value;
        }

        $invoice->forceFill($attributes)->save();
    }

    public function customers(Request $request): JsonResponse
    {
        $user = $request->user();
        $saleId = $request->integer('saleId') ?: null;

        $customers = User::role('ctv')
            ->when($user->hasRole('sale'), fn ($q) => $q->where('id_sale', $user->id))
            ->when($user->hasRole('ctv'), fn ($q) => $q->whereKey($user->id))
            ->when(! $user->hasAnyRole(['sale', 'ctv']) && $saleId, fn ($q) => $q->where('id_sale', $saleId))
            ->orderBy('fullname')
            ->get(['id', 'fullname', 'username', 'code'])
            ->map(fn (User $c) => [
                'id' => $c->id,
                'label' => trim(($c->fullname ?: $c->username).($c->code ? " ({$c->code})" : '')),
            ])
            ->values();

        return response()->json(['customers' => $customers]);
    }

    protected function money(mixed $value): string
    {
        return number_format((float) $value, 0, ',', '.').' đ';
    }
}
