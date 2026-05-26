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
                $contact = collect([$customer->email, $customer->phone])->filter()->implode(' / ');

                return '<div class="max-w-[240px]">'
                    .'<div class="truncate font-semibold text-neutral-900">'.e($customer->fullname ?: $customer->username).'</div>'
                    .'<div class="mt-0.5 truncate text-xs text-neutral-500">'.e($contact ?: '-').'</div>'
                    .'</div>';
            })
            ->addColumn('sale_info', function (CongNoPayment $invoice) {
                $debt = $invoice->congNo;
                if (! $debt || ! $debt->sale) {
                    return '<span class="text-neutral-400">-</span>';
                }

                $sale = $debt->sale;

                return '<div class="max-w-[180px]">'
                    .'<div class="truncate font-medium text-neutral-800">'.e($sale->fullname ?: $sale->username).'</div>'
                    .'<div class="mt-0.5 truncate font-mono text-xs text-neutral-500">'.e($sale->code ?: '-').'</div>'
                    .'</div>';
            })
            ->addColumn('amount', function (CongNoPayment $invoice) {
                return '<span class="font-semibold text-neutral-950">'.$this->money($invoice->amount).'</span>';
            })
            ->addColumn('status_badge', function (CongNoPayment $invoice) {
                $status = $invoice->status ?? InvoicePaymentStatusEnum::CHO_DUYET;

                return '<span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold '.$status->color().'">'
                    .e($status->label())
                    .'</span>';
            })
            ->addColumn('date_timeline', function (CongNoPayment $invoice) {
                return '<div class="space-y-1 text-xs leading-5">'
                    .$this->dateLine('Tạo', $invoice->created_at)
                    .$this->dateLine('Duyệt', $invoice->ngay_duyet)
                    .$this->dateLine('Thanh toán', $invoice->paid_at, 'text-emerald-700')
                    .'</div>';
            })
            ->addColumn('creator', function (CongNoPayment $invoice) {
                $user = $invoice->user;

                return $user
                    ? '<div class="max-w-[160px]">'
                        .'<div class="truncate font-medium text-neutral-800">'.e($user->fullname ?: $user->username).'</div>'
                        .'<div class="mt-0.5 truncate font-mono text-xs text-neutral-500">'.e($user->code ?: '-').'</div>'
                        .'</div>'
                    : '-';
            })
            ->addColumn('actions', function (CongNoPayment $invoice) use ($request) {
                return $this->actionsHtml($invoice, $request);
            })
            ->setRowId(fn (CongNoPayment $invoice) => 'invoice-'.$invoice->id)
            ->rawColumns(['invoice_code', 'debt_code', 'customer_info', 'sale_info', 'amount', 'status_badge', 'date_timeline', 'creator', 'actions'])
            ->toJson();

        $payload = $response->getData(true);
        $payload['statusCounts'] = $this->statusCounts($request);
        $payload['summary'] = $this->summary($request);

        return response()->json($payload);
    }

    protected function actionsHtml(CongNoPayment $invoice, Request $request): string
    {
        return '<button type="button" data-invoice-detail="'.$invoice->id.'" data-invoice-code="'.e($invoice->ma_hoa_don).'" class="inline-flex items-center justify-center rounded-lg border border-primary-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-700 transition hover:bg-primary-50">Chi tiết</button>';
    }

    protected function dateLine(string $label, mixed $date, string $valueClass = 'text-neutral-700'): string
    {
        $value = $date ? $date->format('d/m/Y H:i') : '-';

        return '<div class="flex items-center justify-between gap-3">'
            .'<span class="text-neutral-500">'.e($label).'</span>'
            .'<span class="whitespace-nowrap font-medium '.$valueClass.'">'.e($value).'</span>'
            .'</div>';
    }

    protected function query(Request $request, bool $includeStatus = true): Builder
    {
        $user = $request->user();

        return CongNoPayment::query()
            ->with([
                'user:id,fullname,username,code',
                'approver:id,fullname,username',
                'paymentConfirmer:id,fullname,username',
                'congNo.customer:id,fullname,username,code,email,phone',
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

        if ($invoice->status !== InvoicePaymentStatusEnum::CHO_DUYET) {
            return response()->json(['message' => 'Hóa đơn không ở trạng thái Chờ duyệt, không thể duyệt.'], 422);
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

        $updateData = [
            'method' => 'cash',
            'photo' => $path,
            'status' => InvoicePaymentStatusEnum::DA_GUI_HOA_DON_TT->value,
            'submitted_at' => now(),
            'paid_at' => null,
        ];

        if ($fromStatus === InvoicePaymentStatusEnum::KHONG_CHAP_NHAN) {
            $updateData['payment_rejection_reason'] = null;
            $updateData['payment_rejected_at'] = null;
            $updateData['payment_rejected_by'] = null;
        }

        $invoice->forceFill($updateData)->save();

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

        $providerKey = $request->input('provider');
        if ($providerKey !== null && ! in_array($providerKey, ['sepay', 'momo', 'vnpay'], true)) {
            return response()->json(['message' => 'Cổng thanh toán không hợp lệ.'], 422);
        }

        try {
            DB::transaction(function () use ($invoice, $providerKey) {
                $locked = CongNoPayment::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

                if (! $locked->canPay(request()->user())) {
                    throw new \RuntimeException('Hóa đơn chưa thể tạo QR thanh toán.');
                }

                $fromStatus = $locked->status;
                $this->fillQrPayment($locked, true, $providerKey);
                $locked->writeStatusLog('qr_requested', $fromStatus, InvoicePaymentStatusEnum::DA_GUI_YEU_CAU_TT, request()->user()?->id, null, [
                    'qr_payment_code' => $locked->qr_payment_code,
                    'provider' => $providerKey ?: $locked->payment_provider,
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

    public function rejectCashPayment(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $invoice = CongNoPayment::whereKey($id)->firstOrFail();

        if (! $invoice->canRejectPayment($user)) {
            return response()->json(['message' => 'Không có quyền từ chối chứng từ thanh toán hóa đơn này.'], 403);
        }

        $validated = $request->validate([
            'payment_rejection_reason' => ['required', 'string', 'max:500'],
        ], [
            'payment_rejection_reason.required' => 'Vui lòng nhập ghi chú từ chối chứng từ.',
            'payment_rejection_reason.string' => 'Ghi chú từ chối không hợp lệ.',
            'payment_rejection_reason.max' => 'Ghi chú từ chối không vượt quá 500 ký tự.',
        ]);

        $invoice->forceFill([
            'status' => InvoicePaymentStatusEnum::KHONG_CHAP_NHAN->value,
            'payment_rejection_reason' => $validated['payment_rejection_reason'],
            'payment_rejected_at' => now(),
            'payment_rejected_by' => $user->id,
            'payment_confirmed_by' => null,
            'paid_at' => null,
        ])->save();

        $invoice->writeStatusLog(
            'payment_rejected',
            InvoicePaymentStatusEnum::DA_GUI_HOA_DON_TT,
            InvoicePaymentStatusEnum::KHONG_CHAP_NHAN,
            $user->id,
            $validated['payment_rejection_reason']
        );

        return response()->json(['message' => "Đã từ chối chứng từ thanh toán của hóa đơn {$invoice->ma_hoa_don}."]);
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

    protected function fillQrPayment(CongNoPayment $invoice, bool $updateStatus, ?string $providerKey = null): void
    {
        $code = $invoice->payment_reference
            ?: $invoice->qr_payment_code
            ?: app(InvoiceCodeGenerator::class)->generatePaymentCode($invoice->ma_hoa_don);
        $provider = app(PaymentProviderManager::class)->driver($providerKey ?: $invoice->payment_provider);
        $intent = $provider->createPayment(new PaymentRequestData(
            amount: (int) round((float) $invoice->amount),
            reference: $code,
            description: $code,
            expiresAt: now()->addMinutes(CongNoPayment::QR_THROTTLE_MINUTES),
            metadata: [
                'request_id' => $code.'-'.now()->format('YmdHis'),
            ],
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
