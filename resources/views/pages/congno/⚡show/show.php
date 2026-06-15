<?php
use App\Actions\Order\RecordOrderEditHistoryAction;
use App\Enums\DebtStatusEnum;
use App\Enums\InvoicePaymentStatusEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\OrderStatusEnum;
use App\Models\CongNo;
use App\Models\CongNoDetail;
use App\Models\CongNoEInvoice;
use App\Models\CongNoPayment;
use App\Models\News;
use App\Models\Order;
use App\Models\Setting;
use App\Mail\EInvoiceMail;
use App\Services\EInvoices\Data\EInvoiceRequestData;
use App\Services\EInvoices\EInvoiceProviderManager;
use App\Services\Payments\InvoiceCodeGenerator;
use App\Services\Payments\PaymentProviderManager;
use App\Services\Payments\Data\PaymentRequestData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Flux\Flux;

new #[Layout('layouts.app')] #[Title('Chi tiết công nợ')] class extends Component {
    use WithFileUploads;

    public CongNo $debt;

    public string $invoiceAmount = '';
    public string $invoiceNote = '';

    public ?int $payingInvoiceId = null;
    public ?string $selectedMethod = null;
    public string $selectedProvider = 'sepay';
    public array $enabledProviders = [];
    public $cashInvoicePhoto = null;
    public bool $showPayModal = false;

    // E-Invoice state
    public bool $showEInvoiceModal = false;
    public string $einvoiceProvider = '';
    public string $einvoiceNotes = '';
    public array $enabledEInvoiceProviders = [];
    public ?int $pendingEmailEInvoiceId = null;

    public ?int $editingSaleChargeDetailId = null;
    public ?string $editingSaleChargeOrderCode = null;
    public ?string $editingSaleChargeTotal = null;
    public array $editingSaleCharge = [];
    public array $feeOptions = [];
    public array $expenseOptions = [];

    public function mount(string $id): void
    {
        $this->debt = $this->loadDebt($id);

        abort_unless($this->canView(), 403);
        $this->feeOptions = $this->loadFeeOptions();
        $this->expenseOptions = $this->loadExpenseOptions();
        $this->loadEnabledProviders();
        $this->loadEnabledEInvoiceProviders();
    }

    protected function loadEnabledEInvoiceProviders(): void
    {
        $this->enabledEInvoiceProviders = EInvoiceProviderManager::enabledProviders();

        if (!($this->enabledEInvoiceProviders[$this->einvoiceProvider] ?? false)) {
            $this->einvoiceProvider = EInvoiceProviderManager::defaultProvider();
        }
    }

    protected function loadEnabledProviders(): void
    {
        $this->enabledProviders = \App\Services\Payments\PaymentProviderManager::enabledProviders();

        if (!($this->enabledProviders[$this->selectedProvider] ?? false)) {
            $this->selectedProvider = \App\Services\Payments\PaymentProviderManager::defaultProvider();
        }
    }

    protected function loadDebt(string $id): CongNo
    {
        return CongNo::query()
            ->with([
                'sale:id,fullname,username,code',
                'customer:id,fullname,username,code,phone,email,options',
                'creator:id,fullname,username,code',
                'ketoan:id,fullname,username,code',
                'details.order.dichvu:id,namevi',
                'details.order.chiNhanhNhanHang:id,namevi',
                'details.order.receiverCountry:id,name,iso2,iso3',
                'details.order.receiverCountryLegacy:id,name,iso2,iso3',
                'details.order.packages',
                'payments.user:id,fullname,username,code',
                'payments.ketoan:id,fullname,username,code',
                'payments.approver:id,fullname,username,code',
                'payments.paymentConfirmer:id,fullname,username,code',
                'payments.cancelledBy:id,fullname,username,code',
                'einvoices.user:id,fullname,username,code',
            ])
            ->where(function ($query) use ($id) {
                $query->where('uuid', $id);

                if (ctype_digit($id)) {
                    $query->orWhere('id', (int) $id);
                }
            })
            ->firstOrFail();
    }

    protected function loadFeeOptions(): array
    {
        return Cache::remember('payment_page_phuphidonhang', now()->addDay(), function () {
            return News::query()
                ->select([
                    'id',
                    'namevi',
                    DB::raw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(options2, '$.price')), 0) as price"),
                ])
                ->whereType('phuphidonhang')
                ->orderBy('numb')
                ->get()
                ->map(fn (News $item) => [
                    'id' => $item->id,
                    'name' => $item->namevi,
                    'price' => (float) $item->price,
                ])
                ->toArray();
        });
    }

    protected function loadExpenseOptions(): array
    {
        return Cache::remember('payment_page_loai_chi_hhkh', now()->addDay(), function () {
            return News::query()
                ->select(['id', 'namevi'])
                ->whereType('loai-chi-hhkh')
                ->orderBy('numb')
                ->get()
                ->map(fn (News $item) => [
                    'id' => $item->id,
                    'name' => $item->namevi,
                ])
                ->toArray();
        });
    }

    public function updated($property): void
    {
        if (! str_starts_with((string) $property, 'editingSaleCharge.')) {
            return;
        }

        if (str_ends_with((string) $property, '.id_loaiphuphi')) {
            $this->syncEditingSelectedFee((string) $property);
            return;
        }

        if (str_ends_with((string) $property, '.id_loaichi')) {
            $this->syncEditingSelectedExpense((string) $property);
            return;
        }

        if (! $this->isEditingComputedProperty((string) $property)) {
            $this->editingSaleCharge = $this->recalculateGroup($this->editingSaleCharge, 'dongiaban', ['phuphi', 'hh_khachhang']);
        }
    }

    public function confirmDebt(): void
    {
        abort_unless($this->canManage(), 403);
        $this->claimAccountantIfNeeded();

        if ($this->debt->status !== DebtStatusEnum::MOI_TAO) {
            Flux::toast(heading: 'Không hợp lệ', text: 'Chỉ công nợ mới tạo mới có thể chốt cước.', variant: 'warning');
            return;
        }

        DB::transaction(function () {
            $fromStatus = $this->debt->status;

            $this->debt->forceFill([
                'status' => DebtStatusEnum::DA_CHOT_CUOC,
                'id_success' => auth()->id(),
                'id_ketoan' => auth()->user()->hasRole('ketoan') ? auth()->id() : $this->debt->id_ketoan,
                'ngaychothoadon' => now(),
                'hanthanhtoan' => now()->addDays((int) $this->debt->songaythanhtoan)->startOfDay(),
            ])->save();

            $this->debt->writeActivityLog(
                action: 'confirmed',
                title: 'Chốt cước công nợ khách hàng',
                fromStatus: $fromStatus,
                toStatus: DebtStatusEnum::DA_CHOT_CUOC,
                metadata: array_filter([
                    'total_orders' => (int) $this->debt->total_orders,
                    'total_amount' => (float) $this->debt->total_cuocban,
                ], fn ($v) => $v !== null),
            );

            $this->debt->orders()->update(['customer_payment_status' => DebtStatusEnum::DA_CHOT_CUOC->value]);
        });

        $this->reloadDebt();
        Flux::toast(heading: 'Đã chốt cước', text: 'Công nợ đã được chuyển sang trạng thái đã chốt cước.', variant: 'success');
    }

    public function createPaymentInvoice(): void
    {
        abort_unless($this->canCreatePaymentInvoice(), 403);
        $this->claimAccountantIfNeeded();

        if (! $this->debt->canCreatePaymentInvoice()) {
            Flux::toast(heading: 'Chưa chốt cước', text: 'Cần chốt cước công nợ trước khi tạo hóa đơn thu.', variant: 'warning');
            return;
        }

        $data = $this->validate([
            'invoiceAmount' => ['required', 'regex:/^[0-9.,]+$/'],
            'invoiceNote' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'invoiceAmount' => 'Số tiền hóa đơn',
        ]);

        $amount = round($this->number($data['invoiceAmount']), 2);
        if ($amount <= 0) {
            Flux::toast(heading: 'Số tiền không hợp lệ', text: 'Vui lòng nhập số tiền lớn hơn 0.', variant: 'warning');
            return;
        }

        try {
            DB::transaction(function () use ($amount, $data) {
                $debt = CongNo::query()->whereKey($this->debt->id)->lockForUpdate()->firstOrFail();
                $available = $debt->availableForNewInvoice();

                if ($amount > $available + 0.5) {
                    throw new \RuntimeException(sprintf('Số tiền vượt mức cho phép. Tối đa còn lại: %s đ.', number_format($available, 0, ',', '.')));
                }

                $payment = CongNoPayment::create([
                    'id_congno' => $debt->id,
                    'id_user' => auth()->id(),
                    'amount' => $amount,
                    'due_at' => $debt->hanthanhtoan,
                    'note' => $data['invoiceNote'] ?: 'Hóa đơn thu công nợ khách hàng ' . ($debt->customer?->fullname ?: $debt->customer?->username ?: ''),
                    'status' => InvoicePaymentStatusEnum::CHO_DUYET->value,
                    'loai_hoa_don' => InvoiceTypeEnum::THU->value,
                ]);

                $debt->writeActivityLog(
                    action: 'payment_invoice_created',
                    title: 'Tạo hóa đơn thu',
                    metadata: array_filter([
                        'invoice_id' => $payment->id,
                        'invoice_code' => $payment->ma_hoa_don,
                        'amount' => $amount,
                        'note' => $data['invoiceNote'] ?: null,
                    ], fn ($v) => $v !== null && $v !== ''),
                );
            });
        } catch (\RuntimeException $exception) {
            Flux::toast(heading: 'Không thể tạo', text: $exception->getMessage(), variant: 'warning');
            return;
        }

        $this->invoiceAmount = '';
        $this->invoiceNote = '';
        $this->reloadDebt();

        Flux::toast(heading: 'Đã tạo hóa đơn thu', text: 'Hóa đơn đã được lưu ở trạng thái Mới tạo, chờ duyệt.', variant: 'success');
    }

    public function approveInvoice(int $invoiceId): void
    {
        $invoice = CongNoPayment::query()->whereKey($invoiceId)->firstOrFail();

        abort_unless($this->canManage(), 403);
        abort_unless($invoice->canApprove(auth()->user()), 403);
        $this->assertAccountantOwnership();
        $this->claimAccountantIfNeeded();

        $fromStatus = $invoice->status;

        $invoice->forceFill([
            'status' => InvoicePaymentStatusEnum::DA_DUYET->value,
            'id_ketoan' => auth()->id(),
            'approved_by' => auth()->id(),
            'ngay_duyet' => now(),
        ])->save();

        $invoice->writeStatusLog('approved', $fromStatus, InvoicePaymentStatusEnum::DA_DUYET, auth()->id());
        $this->debt->writeActivityLog(
            action: 'payment_invoice_approved',
            title: 'Duyệt hóa đơn thu',
            metadata: array_filter([
                'invoice_id' => $invoice->id,
                'invoice_code' => $invoice->ma_hoa_don,
                'amount' => (float) $invoice->amount,
            ], fn ($v) => $v !== null && $v !== ''),
        );

        $this->reloadDebt();
        Flux::toast(heading: 'Đã duyệt', text: 'Hóa đơn ' . $invoice->ma_hoa_don . ' đã được duyệt.', variant: 'success');
    }

    public ?int $cancellingInvoiceId = null;
    public string $cancelReason = '';

    public function openCancelInvoiceModal(int $invoiceId): void
    {
        $invoice = CongNoPayment::query()->whereKey($invoiceId)->firstOrFail();

        abort_unless($this->canManage(), 403);
        abort_unless($invoice->canCancel(auth()->user()), 403);
        $this->assertAccountantOwnership();
        $this->claimAccountantIfNeeded();

        $this->cancellingInvoiceId = $invoice->id;
        $this->cancelReason = '';

        Flux::modal('cancel-invoice')->show();
    }

    public function closeCancelInvoiceModal(): void
    {
        $this->resetErrorBag('cancelReason');
        $this->cancellingInvoiceId = null;
        $this->cancelReason = '';

        Flux::modal('cancel-invoice')->close();
    }

    public function submitCancelInvoice(): void
    {
        $invoice = $this->cancellingInvoiceId
            ? CongNoPayment::query()->whereKey($this->cancellingInvoiceId)->first()
            : null;

        if (! $invoice) {
            Flux::toast(heading: 'Không tìm thấy', text: 'Hóa đơn không tồn tại.', variant: 'danger');
            return;
        }

        abort_unless($this->canManage(), 403);
        abort_unless($invoice->canCancel(auth()->user()), 403);
        $this->assertAccountantOwnership();
        $this->claimAccountantIfNeeded();

        $this->validate([
            'cancelReason' => ['required', 'string', 'max:500'],
        ], [
            'cancelReason.required' => 'Vui lòng nhập lý do hủy hóa đơn.',
            'cancelReason.max' => 'Lý do hủy không vượt quá 500 ký tự.',
        ]);

        $fromStatus = $invoice->status;

        $invoice->forceFill([
            'status' => InvoicePaymentStatusEnum::HUY->value,
            'cancelled_at' => now(),
            'id_cancelled_by' => auth()->id(),
            'cancel_reason' => $this->cancelReason,
            'payment_url' => null,
            'qr_url' => null,
            'qr_expires_at' => null,
        ])->save();

        $invoice->writeStatusLog('cancelled', $fromStatus, InvoicePaymentStatusEnum::HUY, auth()->id(), $this->cancelReason);
        $this->debt->writeActivityLog(
            action: 'payment_invoice_cancelled',
            title: 'Hủy hóa đơn thu',
            note: $this->cancelReason,
            metadata: array_filter([
                'invoice_id' => $invoice->id,
                'invoice_code' => $invoice->ma_hoa_don,
                'amount' => (float) $invoice->amount,
            ], fn ($v) => $v !== null && $v !== ''),
        );

        $this->closeCancelInvoiceModal();
        $this->reloadDebt();

        Flux::toast(heading: 'Đã hủy hóa đơn', text: 'Hóa đơn ' . $invoice->ma_hoa_don . ' đã chuyển sang trạng thái hủy.', variant: 'success');
    }

    public function cancelInvoice(int $invoiceId): void
    {
        $this->openCancelInvoiceModal($invoiceId);
    }

    public function openPayModal(int $invoiceId): void
    {
        $invoice = CongNoPayment::query()->whereKey($invoiceId)->firstOrFail();

        abort_unless($this->canActOnInvoicePayment(), 403);
        abort_unless($invoice->canPay(auth()->user()), 403);
        $this->assertAccountantOwnership();
        $this->claimAccountantIfNeeded();

        $this->payingInvoiceId = $invoice->id;
        $this->selectedMethod = null;
        $this->selectedProvider = $invoice->payment_provider ?: 'sepay';
        $this->cashInvoicePhoto = null;
        $this->showPayModal = true;

        Flux::modal('pay-invoice')->show();
    }

    public function closePayModal(): void
    {
        $this->payingInvoiceId = null;
        $this->selectedMethod = null;
        $this->selectedProvider = \App\Services\Payments\PaymentProviderManager::defaultProvider();
        $this->cashInvoicePhoto = null;
        $this->showPayModal = false;

        Flux::modal('pay-invoice')->close();
    }

    public function submitCashPayment(): void
    {
        $invoice = $this->payingInvoiceId
            ? CongNoPayment::query()->whereKey($this->payingInvoiceId)->first()
            : null;

        if (! $invoice) {
            Flux::toast(heading: 'Không tìm thấy', text: 'Hóa đơn không tồn tại.', variant: 'danger');
            return;
        }

        abort_unless($this->canActOnInvoicePayment(), 403);
        abort_unless($invoice->canPay(auth()->user()), 403);
        $this->assertAccountantOwnership();
        $this->claimAccountantIfNeeded();

        $this->validate([
            'cashInvoicePhoto' => ['required', 'image', 'max:8192'],
        ], [
            'cashInvoicePhoto.required' => 'Vui lòng upload ảnh hóa đơn đã thanh toán.',
            'cashInvoicePhoto.image' => 'File phải là ảnh.',
            'cashInvoicePhoto.max' => 'Ảnh không vượt quá 8MB.',
        ]);

        $path = $this->cashInvoicePhoto->store('customer-debt-invoices', 'public');

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

        $invoice->writeStatusLog('cash_submitted', $fromStatus, InvoicePaymentStatusEnum::DA_GUI_HOA_DON_TT, auth()->id(), null, [
            'photo' => $path,
        ]);
        $this->debt->writeActivityLog(
            action: 'cash_payment_submitted',
            title: 'Gửi chứng từ thanh toán',
            metadata: array_filter([
                'invoice_id' => $invoice->id,
                'invoice_code' => $invoice->ma_hoa_don,
                'amount' => (float) $invoice->amount,
                'photo' => $path,
            ], fn ($v) => $v !== null && $v !== ''),
        );

        $this->closePayModal();
        $this->reloadDebt();

        Flux::toast(heading: 'Đã gửi hóa đơn thanh toán', text: 'Kế toán sẽ kiểm tra và xác nhận thanh toán.', variant: 'success');
    }

    public function submitOnlinePayment(): void
    {
        $invoice = $this->payingInvoiceId
            ? CongNoPayment::query()->whereKey($this->payingInvoiceId)->first()
            : null;

        if (! $invoice) {
            Flux::toast(heading: 'Không tìm thấy', text: 'Hóa đơn không tồn tại.', variant: 'danger');
            return;
        }

        abort_unless($this->canActOnInvoicePayment(), 403);
        abort_unless($invoice->canPay(auth()->user()), 403);
        $this->assertAccountantOwnership();
        $this->claimAccountantIfNeeded();

        $providerKey = in_array($this->selectedProvider, PaymentProviderManager::allProviders(), true)
            ? $this->selectedProvider
            : PaymentProviderManager::defaultProvider();

        try {
            DB::transaction(function () use ($invoice, $providerKey) {
                $locked = CongNoPayment::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

                abort_unless($locked->canPay(auth()->user()), 403);

                $code = $locked->payment_reference
                    ?: $locked->qr_payment_code
                    ?: app(InvoiceCodeGenerator::class)->generatePaymentCode($locked->ma_hoa_don);
                $intent = app(PaymentProviderManager::class)
                    ->driver($providerKey)
                    ->createPayment(new PaymentRequestData(
                        amount: (int) round((float) $locked->amount),
                        reference: $code,
                        description: $code,
                        expiresAt: now()->addMinutes(CongNoPayment::QR_THROTTLE_MINUTES),
                        metadata: [
                            'request_id' => $code.'-'.now()->format('YmdHis'),
                        ],
                    ));

                $fromStatus = $locked->status;

                $locked->forceFill([
                    'method' => $intent->channel === 'qr' ? 'bank_transfer' : 'online',
                    'status' => InvoicePaymentStatusEnum::DA_GUI_YEU_CAU_TT->value,
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
                ])->save();

                $locked->writeStatusLog('qr_requested', $fromStatus, InvoicePaymentStatusEnum::DA_GUI_YEU_CAU_TT, auth()->id(), null, [
                    'qr_payment_code' => $code,
                    'provider' => $intent->provider,
                ]);

                $this->debt->writeActivityLog(
                    action: 'online_payment_requested',
                    title: 'Tạo link thanh toán online',
                    metadata: array_filter([
                        'invoice_id' => $locked->id,
                        'invoice_code' => $locked->ma_hoa_don,
                        'amount' => (float) $locked->amount,
                        'provider' => $intent->provider,
                        'channel' => $intent->channel,
                        'qr_payment_code' => $code,
                    ], fn ($v) => $v !== null && $v !== ''),
                );
            });
        } catch (\Throwable $exception) {
            Flux::toast(heading: 'Không thể tạo thanh toán', text: $exception->getMessage(), variant: 'danger');
            return;
        }

        $this->closePayModal();
        $this->reloadDebt();

        Flux::toast(heading: 'Đã tạo yêu cầu thanh toán', text: 'Đã tạo link thanh toán cho khách hàng.', variant: 'success');
    }

    public function regenerateQr(int $invoiceId): void
    {
        $invoice = CongNoPayment::query()->whereKey($invoiceId)->firstOrFail();

        abort_unless($this->canActOnInvoicePayment(), 403);
        abort_unless($invoice->canPay(auth()->user()) || $invoice->canManageQr(auth()->user()), 403);
        $this->assertAccountantOwnership();
        $this->claimAccountantIfNeeded();

        if (! $invoice->canRegenerateQr()) {
            $nextAt = $invoice->nextQrAvailableAt();
            Flux::toast(
                heading: 'Chưa thể tạo lại QR',
                text: $nextAt ? 'Vui lòng đợi đến ' . $nextAt->format('H:i d/m/Y') . ' để tạo lại.' : 'Vui lòng đợi đủ ' . CongNoPayment::QR_THROTTLE_MINUTES . ' phút.',
                variant: 'warning'
            );
            return;
        }

        try {
            DB::transaction(function () use ($invoice) {
                $locked = CongNoPayment::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

                abort_unless($locked->canPay(auth()->user()) || $locked->canManageQr(auth()->user()), 403);

                if (! $locked->canRegenerateQr()) {
                    $nextAt = $locked->nextQrAvailableAt();
                    throw new \RuntimeException(
                        $nextAt
                            ? 'Vui lòng đợi đến ' . $nextAt->format('H:i d/m/Y') . ' để tạo lại.'
                            : 'Vui lòng đợi đủ ' . CongNoPayment::QR_THROTTLE_MINUTES . ' phút.'
                    );
                }

                $code = $locked->payment_reference
                    ?: $locked->qr_payment_code
                    ?: app(InvoiceCodeGenerator::class)->generatePaymentCode($locked->ma_hoa_don);
                $providerKey = in_array($this->selectedProvider, PaymentProviderManager::allProviders(), true)
                    ? $this->selectedProvider
                    : ($locked->payment_provider ?: PaymentProviderManager::defaultProvider());
                $intent = app(PaymentProviderManager::class)
                    ->driver($providerKey)
                    ->createPayment(new PaymentRequestData(
                        amount: (int) round((float) $locked->amount),
                        reference: $code,
                        description: $code,
                        expiresAt: now()->addMinutes(CongNoPayment::QR_THROTTLE_MINUTES),
                    ));

                $fromStatus = $locked->status;

                $locked->forceFill([
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
                ])->save();

                $locked->writeStatusLog('qr_regenerated', $fromStatus, $locked->status, auth()->id(), null, [
                    'qr_payment_code' => $code,
                ]);

                $this->debt->writeActivityLog(
                    action: 'qr_regenerated',
                    title: 'Tạo lại QR thanh toán',
                    metadata: array_filter([
                        'invoice_id' => $locked->id,
                        'invoice_code' => $locked->ma_hoa_don,
                        'amount' => (float) $locked->amount,
                        'provider' => $intent->provider,
                        'qr_payment_code' => $code,
                    ], fn ($v) => $v !== null && $v !== ''),
                );
            });
        } catch (\Throwable $exception) {
            Flux::toast(heading: 'Lỗi tạo QR', text: $exception->getMessage(), variant: 'danger');
            return;
        }

        $this->reloadDebt();
        Flux::toast(heading: 'Đã tạo lại QR', text: 'Mã QR mới đã được tạo.', variant: 'success');
    }

    public function confirmCashPayment(int $invoiceId): void
    {
        $invoice = CongNoPayment::query()->whereKey($invoiceId)->firstOrFail();

        abort_unless($this->canManage(), 403);
        abort_unless($invoice->canConfirmCashPayment(auth()->user()), 403);
        $this->assertAccountantOwnership();
        $this->claimAccountantIfNeeded();

        DB::transaction(function () use ($invoice) {
            $debt = CongNo::query()->whereKey($invoice->id_congno)->lockForUpdate()->firstOrFail();
            $locked = CongNoPayment::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            $locked->forceFill([
                'status' => InvoicePaymentStatusEnum::DA_THANH_TOAN->value,
                'paid_at' => now(),
                'id_ketoan' => auth()->id(),
                'payment_confirmed_by' => auth()->id(),
            ])->save();

            $locked->writeStatusLog('cash_confirmed', InvoicePaymentStatusEnum::DA_GUI_HOA_DON_TT, InvoicePaymentStatusEnum::DA_THANH_TOAN, auth()->id());

            $debt->syncPaidAmountFromPayments();
            $debt->refresh();

            $debt->writeActivityLog(
                action: 'cash_payment_confirmed',
                title: 'Xác nhận đã thanh toán',
                fromStatus: $this->debt->status,
                toStatus: $debt->status,
                metadata: array_filter([
                    'invoice_id' => $locked->id,
                    'invoice_code' => $locked->ma_hoa_don,
                    'amount' => (float) $locked->amount,
                ], fn ($v) => $v !== null && $v !== ''),
            );

            $orderStatus = $debt->status === DebtStatusEnum::DA_THANH_TOAN
                ? DebtStatusEnum::DA_THANH_TOAN->value
                : DebtStatusEnum::DA_THANH_TOAN_MOT_PHAN->value;

            $debt->orders()->update([
                'customer_payment_status' => $orderStatus,
                'customer_paid_at' => $orderStatus === DebtStatusEnum::DA_THANH_TOAN->value ? now() : null,
            ]);
        });

        $this->reloadDebt();
        Flux::toast(heading: 'Đã xác nhận thanh toán', text: 'Hóa đơn ' . $invoice->ma_hoa_don . ' đã được ghi nhận đã thanh toán.', variant: 'success');
    }

    // =========================================================================
    // E-Invoice Methods
    // =========================================================================

    public function openEInvoiceModal(): void
    {
        abort_unless($this->canManage(), 403);
        $this->claimAccountantIfNeeded();

        if ($this->debt->status !== DebtStatusEnum::DA_THANH_TOAN) {
            Flux::toast(heading: 'Chưa thanh toán xong', text: 'Chỉ tạo hóa đơn điện tử khi công nợ đã thanh toán hết.', variant: 'warning');
            return;
        }

        if (CongNoEInvoice::hasSuccessfulInvoice($this->debt->id)) {
            Flux::toast(heading: 'Đã có hóa đơn', text: 'Công nợ này đã có hóa đơn điện tử thành công.', variant: 'warning');
            return;
        }

        $this->einvoiceProvider = EInvoiceProviderManager::defaultProvider();
        $this->einvoiceNotes = '';
        $this->showEInvoiceModal = true;

        Flux::modal('create-einvoice')->show();
    }

    public function closeEInvoiceModal(): void
    {
        $this->showEInvoiceModal = false;
        $this->einvoiceProvider = '';
        $this->einvoiceNotes = '';

        Flux::modal('create-einvoice')->close();
    }

    public function submitEInvoice(): void
    {
        abort_unless($this->canManage(), 403);
        $this->claimAccountantIfNeeded();

        if ($this->debt->status !== DebtStatusEnum::DA_THANH_TOAN) {
            Flux::toast(heading: 'Chưa thanh toán xong', text: 'Chỉ tạo hóa đơn điện tử khi công nợ đã thanh toán hết.', variant: 'warning');
            return;
        }

        if (CongNoEInvoice::hasSuccessfulInvoice($this->debt->id)) {
            Flux::toast(heading: 'Đã có hóa đơn', text: 'Công nợ này đã có hóa đơn điện tử thành công.', variant: 'warning');
            return;
        }

        $providerKey = in_array($this->einvoiceProvider, EInvoiceProviderManager::allProviders(), true)
            ? $this->einvoiceProvider
            : EInvoiceProviderManager::defaultProvider();

        // Lấy config provider
        $options = data_get(\App\Models\Setting::first(), 'options', []);
        $providerAccountId = $options["einvoice_{$providerKey}_provider_account_id"] ?? '';
        $templateCode = $options["einvoice_{$providerKey}_template_code"] ?? '';
        $invoiceSeries = $options["einvoice_{$providerKey}_invoice_series"] ?? '';

        if (! $providerAccountId || ! $templateCode || ! $invoiceSeries) {
            Flux::toast(heading: 'Chưa cấu hình', text: 'Vui lòng cấu hình Provider Account ID, Template Code và Invoice Series trong Cấu hình hệ thống.', variant: 'warning');
            return;
        }

        // Build buyer info (theo format SePay — bỏ field rỗng phía service)
        $customer = $this->debt->customer;
        $company = data_get($customer, 'options.company', []);
        $buyer = [
            'name' => data_get($company, 'company_name') ?: $customer?->company_name ?: $customer?->fullname ?: ($customer?->username ?: 'Khách hàng'),
            'tax_code' => data_get($company, 'tax_code') ?: null,
            'email' => data_get($company, 'company_email') ?: $customer?->email ?: null,
            'phone' => data_get($company, 'company_phone') ?: $customer?->phone ?: null,
            'address' => data_get($company, 'address_detail') ?: $customer?->address ?: null,
        ];

        // Build item: 1 dòng duy nhất "phí vận chuyển các mã vận đơn" (liệt kê mã đơn), không VAT.
        $details = $this->debt->details()->with('order')->get();
        $orderCodes = $details
            ->map(fn ($detail) => $detail->order_code ?: $detail->order?->id_bill ?: ('#' . $detail->id_order))
            ->filter()
            ->values()
            ->all();

        $itemName = 'Thanh toán phí vận chuyển các mã vận đơn: ' . implode(', ', $orderCodes);
        $totalAmount = (int) round((float) $this->debt->total_cuocban);

        $items = [
            [
                'line_number' => 1,
                'line_type' => 1,
                'item_code' => $this->debt->sohoadon ?: ('CN-' . $this->debt->id),
                'item_name' => $itemName,
                'unit' => 'Đơn',
                'quantity' => 1,
                'unit_price' => $totalAmount,
            ],
        ];

        $reference = CongNoEInvoice::generateReference($this->debt);

        try {
            $driver = app(EInvoiceProviderManager::class)->driver($providerKey);

            $requestData = new EInvoiceRequestData(
                reference: $reference,
                templateCode: $templateCode,
                invoiceSeries: $invoiceSeries,
                issuedDate: now()->format('Y-m-d H:i:s'),
                providerAccountId: $providerAccountId,
                buyer: $buyer,
                items: $items,
                amount: (int) round((float) $this->debt->total_cuocban),
                paymentMethod: 'CK',
                isDraft: false, // tạo + phát hành luôn để có số hóa đơn
                notes: $this->einvoiceNotes ?: null,
            );

            $result = $driver->create($requestData);

            // Tạo record với trạng thái pending (SePay xử lý bất đồng bộ).
            $einvoice = CongNoEInvoice::create([
                'id_congno' => $this->debt->id,
                'id_user' => auth()->id(),
                'provider' => $providerKey,
                'provider_account_id' => $providerAccountId,
                'reference' => $reference,
                'template_code' => $templateCode,
                'invoice_series' => $invoiceSeries,
                'issued_date' => now()->toDateString(),
                'tracking_code' => $result->trackingCode,
                'provider_reference_code' => $result->providerReferenceCode,
                'tracking_url' => $result->trackingUrl,
                'invoice_url' => $result->invoiceUrl,
                'invoice_number' => $result->invoiceNumber,
                'amount' => (float) $this->debt->total_cuocban,
                'status' => $result->invoiceNumber ? CongNoEInvoice::STATUS_SUCCESS : CongNoEInvoice::STATUS_PENDING,
                'buyer' => $buyer,
                'items' => $items,
                'provider_payload' => $result->raw,
                'notes' => $this->einvoiceNotes ?: null,
                'issued_at' => $result->invoiceNumber ? now() : null,
            ]);

            // Auto-poll ngay (SePay thường xử lý trong 1–3s) để lấy invoice_number.
            // Nếu chưa kịp, user vẫn có thể bấm "Kiểm tra" sau.
            if ($result->trackingCode && ! $result->invoiceNumber) {
                $this->pollEInvoiceStatus($einvoice, $driver, attempts: 3, delayMs: 1500);
            }

            $fresh = $einvoice->fresh();
            $this->debt->writeActivityLog(
                action: 'einvoice_created',
                title: 'Tạo hóa đơn điện tử',
                metadata: array_filter([
                    'einvoice_id' => $einvoice->id,
                    'reference' => $reference,
                    'provider' => $providerKey,
                    'tracking_code' => $fresh?->tracking_code,
                    'invoice_number' => $fresh?->invoice_number,
                    'status' => $fresh?->status,
                    'amount' => (float) $einvoice->amount,
                ], fn ($v) => $v !== null && $v !== ''),
            );
        } catch (\Throwable $e) {
            // Lưu record thất bại
            $failed = CongNoEInvoice::create([
                'id_congno' => $this->debt->id,
                'id_user' => auth()->id(),
                'provider' => $providerKey,
                'provider_account_id' => $providerAccountId,
                'reference' => $reference,
                'template_code' => $templateCode,
                'invoice_series' => $invoiceSeries,
                'issued_date' => now()->toDateString(),
                'amount' => (float) $this->debt->total_cuocban,
                'status' => CongNoEInvoice::STATUS_FAILED,
                'buyer' => $buyer,
                'items' => $items,
                'notes' => $this->einvoiceNotes ?: null,
                'error_message' => $e->getMessage(),
            ]);

            $this->debt->writeActivityLog(
                action: 'einvoice_create_failed',
                title: 'Tạo hóa đơn điện tử thất bại',
                note: $e->getMessage(),
                metadata: array_filter([
                    'einvoice_id' => $failed->id,
                    'reference' => $reference,
                    'provider' => $providerKey,
                ], fn ($v) => $v !== null && $v !== ''),
            );

            Flux::toast(heading: 'Lỗi tạo hóa đơn', text: $e->getMessage(), variant: 'danger');
            $this->closeEInvoiceModal();
            $this->reloadDebt();
            return;
        }

        $this->closeEInvoiceModal();
        $this->reloadDebt();

        // Lấy lại einvoice mới nhất để xem có số hóa đơn chưa.
        $latestEInvoice = CongNoEInvoice::latestForCongNo($this->debt->id);

        if ($latestEInvoice && $latestEInvoice->invoice_number) {
            Flux::toast(
                heading: 'Đã phát hành hóa đơn',
                text: 'Số hóa đơn: ' . $latestEInvoice->invoice_number,
                variant: 'success'
            );
        } else {
            Flux::toast(
                heading: 'Đã tạo hóa đơn',
                text: 'Hóa đơn đang được ' . ucfirst($providerKey) . ' xử lý. Bấm "Kiểm tra" sau ít phút để lấy số hóa đơn.',
                variant: 'success'
            );
        }
    }

    /**
     * Poll SePay để lấy invoice_number ngay sau khi tạo.
     * Thử tối đa $attempts lần, mỗi lần cách $delayMs ms.
     */
    protected function pollEInvoiceStatus(CongNoEInvoice $einvoice, $driver, int $attempts = 3, int $delayMs = 1500): void
    {
        for ($i = 0; $i < $attempts; $i++) {
            usleep($delayMs * 1000);

            try {
                $statusData = $driver->status($einvoice->tracking_code);
                $einvoice->updateFromStatusData($statusData);

                if (! $einvoice->isPending()) {
                    // Thành công → tải file PDF/XML về lưu local + ghi số HĐ lên công nợ
                    if ($einvoice->isSuccess()) {
                        $einvoice->downloadAndStoreFiles('public');
                        $this->syncEInvoiceNumberToDebt($einvoice);
                    }
                    return; // Đã có kết quả (success hoặc failed)
                }
            } catch (\Throwable) {
                // Bỏ qua lỗi poll, user có thể kiểm tra thủ công sau
                continue;
            }
        }
    }

    /**
     * Sync số hóa đơn điện tử lên cột tham chiếu của công nợ.
     */
    protected function syncEInvoiceNumberToDebt(CongNoEInvoice $einvoice): void
    {
        if (! $einvoice->invoice_number) {
            return;
        }

        if ((string) $this->debt->sohoadon_thamchieu === (string) $einvoice->invoice_number) {
            return;
        }

        $this->debt->forceFill([
            'sohoadon_thamchieu' => $einvoice->invoice_number,
        ])->save();
    }

    public function checkEInvoiceStatus(int $einvoiceId): void
    {
        $einvoice = CongNoEInvoice::query()->whereKey($einvoiceId)->firstOrFail();

        if (! $einvoice->isPending()) {
            Flux::toast(heading: 'Không cần kiểm tra', text: 'Hóa đơn đã ở trạng thái cuối.', variant: 'warning');
            return;
        }

        if (! $einvoice->tracking_code) {
            Flux::toast(heading: 'Không có tracking code', text: 'Không thể kiểm tra trạng thái.', variant: 'warning');
            return;
        }

        $beforeStatus = $einvoice->status;
        $beforeInvoiceNumber = $einvoice->invoice_number;

        try {
            $driver = app(EInvoiceProviderManager::class)->driver($einvoice->provider);
            $statusData = $driver->status($einvoice->tracking_code);
            $einvoice->updateFromStatusData($statusData);

            // Nếu đã thành công và chưa có file local → tải xuống
            if ($einvoice->fresh()->isSuccess() && ! $einvoice->fresh()->hasLocalPdf()) {
                $einvoice->fresh()->downloadAndStoreFiles('public');
            }

            // Ghi số hóa đơn lên công nợ (tham chiếu)
            if ($einvoice->fresh()->isSuccess()) {
                $this->syncEInvoiceNumberToDebt($einvoice->fresh());
            }
        } catch (\Throwable $e) {
            Flux::toast(heading: 'Lỗi kiểm tra', text: $e->getMessage(), variant: 'danger');
            return;
        }

        $fresh = $einvoice->fresh();

        if ($beforeStatus !== $fresh->status || $beforeInvoiceNumber !== $fresh->invoice_number) {
            $this->debt->writeActivityLog(
                action: 'einvoice_status_checked',
                title: 'Cập nhật trạng thái hóa đơn điện tử',
                metadata: array_filter([
                    'einvoice_id' => $fresh->id,
                    'reference' => $fresh->reference,
                    'provider' => $fresh->provider,
                    'status_from' => $beforeStatus,
                    'status_to' => $fresh->status,
                    'invoice_number_from' => $beforeInvoiceNumber,
                    'invoice_number_to' => $fresh->invoice_number,
                ], fn ($v) => $v !== null && $v !== ''),
            );
        }

        $this->reloadDebt();

        $label = $fresh->statusLabel();
        $extraText = $fresh->invoice_number ? " (Số HĐ: {$fresh->invoice_number})" : '';
        Flux::toast(heading: 'Đã cập nhật', text: "Trạng thái: {$label}{$extraText}", variant: 'success');
    }

    /**
     * Action thủ công: tải lại file PDF/XML từ provider.
     */
    public function downloadEInvoiceFiles(int $einvoiceId): void
    {
        abort_unless($this->canManage(), 403);
        $this->claimAccountantIfNeeded();

        $einvoice = CongNoEInvoice::query()->whereKey($einvoiceId)->firstOrFail();

        if (! $einvoice->isSuccess()) {
            Flux::toast(heading: 'Không thể tải', text: 'Chỉ tải được file của hóa đơn đã phát hành thành công.', variant: 'warning');
            return;
        }

        try {
            $downloaded = $einvoice->downloadAndStoreFiles('public');
        } catch (\Throwable $e) {
            Flux::toast(heading: 'Lỗi tải file', text: $e->getMessage(), variant: 'danger');
            return;
        }

        $this->reloadDebt();

        if ($downloaded) {
            $this->debt->writeActivityLog(
                action: 'einvoice_files_downloaded',
                title: 'Tải file hóa đơn điện tử',
                metadata: array_filter([
                    'einvoice_id' => $einvoice->id,
                    'reference' => $einvoice->reference,
                    'invoice_number' => $einvoice->invoice_number,
                ], fn ($v) => $v !== null && $v !== ''),
            );

            Flux::toast(heading: 'Đã tải file', text: 'PDF/XML đã được lưu vào hệ thống.', variant: 'success');
        } else {
            Flux::toast(heading: 'Đã có sẵn', text: 'File đã được tải trước đó hoặc provider chưa sẵn sàng.', variant: 'warning');
        }
    }

    public function confirmSendEInvoiceEmail(int $einvoiceId): void
    {
        abort_unless($this->canManage(), 403);
        $this->claimAccountantIfNeeded();

        $einvoice = CongNoEInvoice::query()->whereKey($einvoiceId)->firstOrFail();

        if (! $einvoice->isSuccess() || ! $einvoice->pdf_path) {
            Flux::toast(heading: 'Không thể gửi', text: 'Hóa đơn chưa có file PDF để gửi.', variant: 'warning');
            return;
        }

        $customerEmail = $this->debt->customer?->email;

        if (! $customerEmail) {
            Flux::toast(heading: 'Thiếu email', text: 'Khách hàng chưa có địa chỉ email.', variant: 'warning');
            return;
        }

        $this->pendingEmailEInvoiceId = $einvoiceId;

        $this->dispatch('open-confirm', [
            'title' => 'Gửi hóa đơn qua email',
            'message' => "Gửi hóa đơn điện tử #{$einvoice->invoice_number} đến email {$customerEmail}?",
            'confirmText' => 'Gửi email',
            'cancelText' => 'Hủy',
            'variant' => 'info',
        ]);
    }

    #[On('confirm-action')]
    public function handleConfirmAction(): void
    {
        if ($this->pendingEmailEInvoiceId) {
            $this->executeSendEInvoiceEmail($this->pendingEmailEInvoiceId);
            $this->pendingEmailEInvoiceId = null;
        }
    }

    protected function executeSendEInvoiceEmail(int $einvoiceId): void
    {
        $einvoice = CongNoEInvoice::query()->whereKey($einvoiceId)->first();

        if (! $einvoice || ! $einvoice->isSuccess() || ! $einvoice->pdf_path) {
            return;
        }

        $customerEmail = $this->debt->customer?->email;

        if (! $customerEmail) {
            return;
        }

        // Apply SMTP config from system settings
        $options = Setting::query()->first()?->options ?? [];
        $smtpHost = $options['smtp_host'] ?? null;
        $smtpUsername = $options['smtp_username'] ?? null;
        $smtpPassword = $options['smtp_password'] ?? null;

        if (! $smtpHost || ! $smtpUsername || ! $smtpPassword) {
            Flux::toast(heading: 'Chưa cấu hình SMTP', text: 'Vui lòng cấu hình SMTP trong Cài đặt hệ thống trước khi gửi email.', variant: 'danger');
            return;
        }

        Config::set('mail.mailers.smtp.host', $smtpHost);
        Config::set('mail.mailers.smtp.port', (int) ($options['smtp_port'] ?? 587));
        Config::set('mail.mailers.smtp.username', $smtpUsername);
        Config::set('mail.mailers.smtp.password', $smtpPassword);
        Config::set('mail.mailers.smtp.encryption', 'tls');
        Config::set('mail.from.address', $options['smtp_from_email'] ?? $smtpUsername);
        Config::set('mail.from.name', $options['smtp_from_name'] ?? ($options['company_short_name'] ?? ($options['company_name'] ?? config('app.name'))));

        // Purge cached mailer so new config takes effect
        app('mail.manager')->purge('smtp');

        try {
            Mail::mailer('smtp')->to($customerEmail)->send(new EInvoiceMail($einvoice, $this->debt));

            $einvoice->forceFill(['email_sent_at' => now()])->save();
            $this->debt->writeActivityLog(
                action: 'einvoice_email_sent',
                title: 'Gửi email hóa đơn điện tử',
                metadata: array_filter([
                    'einvoice_id' => $einvoice->id,
                    'reference' => $einvoice->reference,
                    'invoice_number' => $einvoice->invoice_number,
                    'email' => $customerEmail,
                ], fn ($v) => $v !== null && $v !== ''),
            );
            $this->reloadDebt();

            Flux::toast(heading: 'Đã gửi email', text: "Hóa đơn đã được gửi đến {$customerEmail}", variant: 'success');
        } catch (\Throwable $e) {
            Flux::toast(heading: 'Gửi email thất bại', text: $e->getMessage(), variant: 'danger');
        }
    }

    #[Computed]
    public function einvoiceProviderLabels(): array
    {
        return EInvoiceProviderManager::providerLabels();
    }

    #[Computed]
    public function eInvoiceEnabled(): bool
    {
        return EInvoiceProviderManager::hasAnyEnabled();
    }

    #[Computed]
    public function onlinePaymentEnabled(): bool
    {
        return \App\Services\Payments\PaymentProviderManager::hasAnyEnabled();
    }

    #[Computed]
    public function canCreateEInvoice(): bool
    {
        return EInvoiceProviderManager::hasAnyEnabled()
            && $this->debt->status === DebtStatusEnum::DA_THANH_TOAN
            && ! CongNoEInvoice::hasSuccessfulInvoice($this->debt->id);
    }

    public function openSaleChargeModal(int $detailId): void
    {
        abort_unless($this->canEditSaleCharge(), 403);
        $this->claimAccountantIfNeeded();

        if ($this->debt->canCreatePaymentInvoice()) {
            Flux::toast(heading: 'Không thể sửa', text: 'Công nợ đã chốt cước, không thể sửa cước bán.', variant: 'warning');
            return;
        }

        $detail = $this->debt->details()->with('order')->whereKey($detailId)->firstOrFail();
        $order = $detail->order;

        if (! $order) {
            Flux::toast(heading: 'Không tìm thấy order', text: 'Order của dòng công nợ này không còn tồn tại.', variant: 'warning');
            return;
        }

        $this->editingSaleChargeDetailId = $detail->id;
        $this->editingSaleChargeOrderCode = $order->id_bill ?: 'ORDER-'.$order->id;
        $this->editingSaleCharge = $this->recalculateGroup(
            $this->hydrateEditingPaymentSelections($this->normalizePayment($order->payment_cuocban, 'dongiaban')),
            'dongiaban',
            ['phuphi', 'hh_khachhang']
        );
        $this->editingSaleChargeTotal = $this->money($this->editingSaleCharge['total_tongcuoc'] ?? $detail->cuocban);

        Flux::modal('edit-sale-charge')->show();
    }

    public function saveSaleCharge(): void
    {
        abort_unless($this->canEditSaleCharge(), 403);
        $this->claimAccountantIfNeeded();

        if ($this->debt->canCreatePaymentInvoice() || $this->debt->status === DebtStatusEnum::DA_THANH_TOAN) {
            Flux::toast(heading: 'Không thể sửa', text: 'Công nợ đã chốt cước, không thể sửa cước bán.', variant: 'warning');
            return;
        }

        $data = $this->validate([
            'editingSaleChargeDetailId' => ['required', 'integer'],
            'editingSaleCharge.dongiaban' => ['nullable', 'regex:/^[0-9.,]+$/'],
            'editingSaleCharge.vat_percent' => ['nullable', 'numeric', 'min:0'],
            'editingSaleCharge.ppxd_percent' => ['nullable', 'numeric', 'min:0'],
            'editingSaleCharge.phuphi.*.soluong' => ['nullable', 'numeric', 'min:0'],
            'editingSaleCharge.phuphi.*.price' => ['nullable', 'regex:/^[0-9.,]+$/'],
            'editingSaleCharge.phuphi.*.vat_percent' => ['nullable', 'numeric', 'min:0'],
            'editingSaleCharge.phuphi.*.note' => ['nullable', 'string', 'max:500'],
            'editingSaleCharge.phuphi.*.id_loaiphuphi' => ['nullable', 'integer'],
            'editingSaleCharge.hh_khachhang.*.id_loaichi' => ['nullable', 'integer'],
            'editingSaleCharge.hh_khachhang.*.diengiai_chi' => ['nullable', 'string', 'max:500'],
            'editingSaleCharge.hh_khachhang.*.so_tien' => ['nullable', 'regex:/^[0-9.,]+$/'],
        ], [], [
            'editingSaleCharge.dongiaban' => 'Cước bán',
        ]);

        $salePrice = $this->number(data_get($this->editingSaleCharge, 'dongiaban'));

        if ($salePrice < 0) {
            Flux::toast(heading: 'Cước bán không hợp lệ', text: 'Vui lòng nhập cước bán lớn hơn hoặc bằng 0.', variant: 'warning');
            return;
        }

        DB::transaction(function () use ($data) {
            $detail = $this->debt->details()
                ->whereKey((int) $data['editingSaleChargeDetailId'])
                ->lockForUpdate()
                ->firstOrFail();

            $order = Order::query()->whereKey($detail->id_order)->lockForUpdate()->firstOrFail();
            $before = $this->orderPaymentHistorySnapshot($order);

            [$salePayment, $costPayment, $basePayment, $profitPayment] = $this->recalculateOrderPayments($order, $this->editingSaleCharge);

            $order->forceFill([
                'payment_cuocban' => $salePayment,
                'payment_cuocvon' => $costPayment,
                'payment_cuocgoc' => $basePayment,
                'payment_loinhuan' => $profitPayment,
            ])->save();

            $detail->forceFill([
                'cuocban' => $this->number($salePayment['total_tongcuoc'] ?? 0),
                'cuocvon' => $this->number($costPayment['total_tongcuoc'] ?? 0),
                'cuocgoc' => $this->number($basePayment['total_tongcuoc'] ?? 0),
                'vat' => $this->number($salePayment['total_vat'] ?? 0),
                'ppxd' => $this->number($salePayment['ppxd_amount'] ?? 0),
                'phuphi' => $this->number($salePayment['total_phuphi'] ?? 0),
                'hoahong' => $this->number($costPayment['bonus_sale_amount'] ?? 0),
                'snapshot' => $this->detailSnapshot($detail->snapshot ?? [], $order, $salePayment, $costPayment, $basePayment),
            ])->save();

            $this->debt->syncTotalsFromDetails();
            $this->debt->syncPaidAmountFromPayments();
            $this->syncOrderPaymentStateFromDebt();

            $after = $this->orderPaymentHistorySnapshot($order->fresh());

            RecordOrderEditHistoryAction::execute(
                $order,
                'edit_sale_charge_from_debt',
                'payment',
                $before,
                $after,
                'cập nhật cước bán từ công nợ'
            );

            $this->debt->writeActivityLog(
                action: 'sale_charge_updated',
                title: 'Cập nhật cước bán',
                metadata: array_filter([
                    'detail_id' => $detail->id,
                    'order_id' => $order->id,
                    'order_code' => $order->id_bill,
                    'amount' => (float) $detail->cuocban,
                ], fn ($v) => $v !== null && $v !== ''),
            );
        });

        $this->resetSaleChargeModal();
        $this->reloadDebt();

        Flux::modal('edit-sale-charge')->close();
        Flux::toast(heading: 'Đã cập nhật cước bán', text: 'Hoa hồng, lợi nhuận và tổng công nợ đã được tính lại.', variant: 'success');
    }

    public function removeOrder(int $detailId): void
    {
        abort_unless($this->canManage(), 403);
        $this->claimAccountantIfNeeded();

        if ($this->debt->canCreatePaymentInvoice()) {
            Flux::toast(heading: 'Không thể xóa', text: 'Công nợ đã chốt cước, không thể gỡ order.', variant: 'warning');
            return;
        }

        $detail = $this->debt->details()->whereKey($detailId)->firstOrFail();
        $orderCode = $detail->order_code ?: $detail->order?->id_bill ?: data_get($detail->snapshot, 'order_code');
        $orderId = $detail->id_order;

        $detail->delete();
        $this->debt->syncTotalsFromDetails();
        $this->debt->writeActivityLog(
            action: 'order_removed',
            title: 'Gỡ order khỏi công nợ',
            metadata: array_filter([
                'detail_id' => $detailId,
                'order_id' => $orderId,
                'order_code' => $orderCode,
            ], fn ($v) => $v !== null && $v !== ''),
        );
        $this->reloadDebt();

        Flux::toast(heading: 'Đã xóa order', text: 'Order đã được gỡ khỏi công nợ.', variant: 'success');
    }

    protected function resetSaleChargeModal(): void
    {
        $this->editingSaleChargeDetailId = null;
        $this->editingSaleChargeOrderCode = null;
        $this->editingSaleChargeTotal = null;
        $this->editingSaleCharge = [];
    }

    public function addEditingSaleChargeFee(string $bucket): void
    {
        abort_unless($this->canManage(), 403);
        $this->claimAccountantIfNeeded();

        if (! in_array($bucket, ['phuphi', 'hh_khachhang'], true)) {
            return;
        }

        $this->editingSaleCharge[$bucket][] = [
            '_key' => (string) Str::uuid(),
            'id_loaiphuphi' => null,
            'id_loaichi' => null,
            'name' => '',
            'note' => '',
            'diengiai_chi' => '',
            'soluong' => 1,
            'price' => 0,
            'so_tien' => 0,
            'vat_percent' => 0,
            'vat_amount' => 0,
            'total' => 0,
            'total_after_vat' => 0,
        ];

        $this->editingSaleCharge = $this->recalculateGroup($this->editingSaleCharge, 'dongiaban', ['phuphi', 'hh_khachhang']);
    }

    public function removeEditingSaleChargeFee(string $bucket, int $index): void
    {
        abort_unless($this->canManage(), 403);
        $this->claimAccountantIfNeeded();

        if (! in_array($bucket, ['phuphi', 'hh_khachhang'], true)) {
            return;
        }

        unset($this->editingSaleCharge[$bucket][$index]);
        $this->editingSaleCharge[$bucket] = array_values($this->editingSaleCharge[$bucket] ?? []);
        $this->editingSaleCharge = $this->recalculateGroup($this->editingSaleCharge, 'dongiaban', ['phuphi', 'hh_khachhang']);
    }

    protected function reloadDebt(): void
    {
        $this->debt = $this->loadDebt($this->debt->uuid ?: (string) $this->debt->id);
        $this->dispatch('debt-activity-updated');
    }

    public function canView(): bool
    {
        $user = auth()->user();

        if ($user->hasAnyRole(['admin', 'manager', 'ketoan'])) {
            return true;
        }

        if ($user->hasRole('sale')) {
            return (int) $this->debt->id_sale === (int) $user->id;
        }

        if ($user->hasRole('ctv')) {
            return (int) $this->debt->id_customer === (int) $user->id || (int) $this->debt->id_ctv === (int) $user->id;
        }

        return false;
    }

    /**
     * Admin/manager luôn có toàn quyền thao tác công nợ.
     */
    public function hasDebtAdminPower(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'manager']);
    }

    /**
     * Kế toán đang được gán cho công nợ này.
     */
    public function isAssignedAccountant(): bool
    {
        $user = auth()->user();

        return $user->hasRole('ketoan')
            && (int) $this->debt->id_ketoan === (int) $user->id;
    }

    /**
     * Kế toán nhưng công nợ chưa có kế toán phụ trách → được phép nhận.
     */
    public function isUnassignedAccountant(): bool
    {
        $user = auth()->user();

        return $user->hasRole('ketoan') && empty($this->debt->id_ketoan);
    }

    /**
     * Sale phụ trách công nợ và công nợ chưa chốt cước.
     */
    public function isOwnerSaleEditable(): bool
    {
        $user = auth()->user();

        return $user->hasRole('sale')
            && (int) $this->debt->id_sale === (int) $user->id
            && $this->debt->status === DebtStatusEnum::MOI_TAO;
    }

    public function canManage(): bool
    {
        return $this->hasDebtAdminPower()
            || $this->isAssignedAccountant()
            || $this->isUnassignedAccountant()
            || $this->isOwnerSaleEditable();
    }

    /**
     * Quyền tạo hóa đơn thu từ công nợ:
     * - admin/manager/kế toán phụ trách: như canManage.
     * - sale phụ trách công nợ: được tạo hóa đơn khi công nợ đã chốt cước (canCreatePaymentInvoice của model).
     *   Hóa đơn tạo ra ở trạng thái Chờ duyệt; sau khi kế toán duyệt, sale mới tạo được hình thức thanh toán.
     */
    public function canCreatePaymentInvoice(): bool
    {
        if ($this->hasDebtAdminPower()
            || $this->isAssignedAccountant()
            || $this->isUnassignedAccountant()) {
            return true;
        }

        $user = auth()->user();

        return $user->hasRole('sale')
            && (int) $this->debt->id_sale === (int) $user->id;
    }

    /**
     * Cổng cho các thao tác thanh toán hóa đơn (gửi tiền mặt, tạo QR...) ngay trên trang công nợ.
     * Quyền chi tiết theo trạng thái hóa đơn do CongNoPayment::canPay() quyết định;
     * helper này chỉ mở cổng cho admin/manager/kế toán phụ trách và sale phụ trách công nợ.
     */
    public function canActOnInvoicePayment(): bool
    {
        return $this->canCreatePaymentInvoice();
    }

    public function canEditSaleCharge(): bool
    {
        return $this->hasDebtAdminPower()
            || $this->isAssignedAccountant()
            || $this->isUnassignedAccountant();
    }

    /**
     * Kế toán hiện tại được phép nhận công nợ chưa có người phụ trách.
     */
    protected function canClaimAsAccountant(): bool
    {
        $user = auth()->user();

        return $user->hasRole('ketoan')
            && ! $user->hasAnyRole(['admin', 'manager'])
            && empty($this->debt->id_ketoan);
    }

    /**
     * Chặn kế toán khác khi công nợ đã có kế toán phụ trách.
     */
    protected function assertAccountantOwnership(): void
    {
        $user = auth()->user();

        if ($user->hasAnyRole(['admin', 'manager'])) {
            return;
        }

        if (! empty($this->debt->id_ketoan)
            && $user->hasRole('ketoan')
            && (int) $this->debt->id_ketoan !== (int) $user->id) {
            abort(403, 'Công nợ này đã có kế toán phụ trách.');
        }
    }

    /**
     * Gán kế toán đầu tiên thao tác (edit/tạo hóa đơn...) cho công nợ chưa có kế toán.
     * Chỉ áp dụng cho user role ketoan; admin/manager/sale không bao giờ tự nhận.
     */
    protected function claimAccountantIfNeeded(): void
    {
        if (! $this->canClaimAsAccountant()) {
            return;
        }

        $assigned = DB::transaction(function () {
            $debt = CongNo::query()->whereKey($this->debt->id)->lockForUpdate()->firstOrFail();

            if (! empty($debt->id_ketoan)) {
                return false;
            }

            $debt->forceFill(['id_ketoan' => auth()->id()])->save();

            $debt->writeActivityLog(
                action: 'accountant_assigned',
                title: 'Nhận phụ trách công nợ',
                metadata: ['ketoan_id' => auth()->id()],
            );

            return true;
        });

        if ($assigned) {
            $this->reloadDebt();
        }
    }

    /**
     * Quét lại các đơn hàng thỏa điều kiện (cùng khách hàng, cùng sale, cùng khoảng
     * ngày, chưa thuộc công nợ đang mở) và bổ sung vào công nợ này. Chỉ áp dụng khi
     * công nợ chưa chốt cước.
     */
    public function refreshOrders(): void
    {
        abort_unless($this->canManage(), 403);
        $this->claimAccountantIfNeeded();

        if ($this->debt->status !== DebtStatusEnum::MOI_TAO) {
            Flux::toast(heading: 'Không thể làm mới', text: 'Chỉ công nợ chưa chốt cước mới có thể bổ sung order.', variant: 'warning');
            return;
        }

        $from = $this->debt->tungay ? Carbon::parse($this->debt->tungay)->startOfDay() : null;
        $to = $this->debt->denngay ? Carbon::parse($this->debt->denngay)->endOfDay() : null;
        $saleId = (int) $this->debt->id_sale;
        $customerId = (int) $this->debt->id_customer;

        if (! $from || ! $to || $saleId <= 0 || $customerId <= 0) {
            Flux::toast(heading: 'Thiếu thông tin', text: 'Công nợ thiếu khoảng ngày, sale hoặc khách hàng để quét.', variant: 'warning');
            return;
        }

        $existingOrderIds = $this->debt->details()->pluck('id_order')->all();

        $newOrders = $this->eligibleOrdersQuery($from, $to, $saleId, $customerId)
            ->whereNotIn('id', $existingOrderIds)
            ->with(['packages'])
            ->get();

        if ($newOrders->isEmpty()) {
            Flux::toast(heading: 'Không có đơn mới', text: 'Không tìm thấy order mới thỏa điều kiện để bổ sung.', variant: 'info');
            return;
        }

        DB::transaction(function () use ($newOrders) {
            $rows = $newOrders->map(fn (Order $order) => [
                'id_congno' => $this->debt->id,
                'id_order' => $order->id,
                ...$this->snapshotForOrder($order),
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            CongNoDetail::insert($rows);
            $this->debt->syncTotalsFromDetails();
            $this->debt->writeActivityLog(
                action: 'orders_refreshed',
                title: 'Bổ sung order vào công nợ',
                metadata: [
                    'added_count' => $newOrders->count(),
                    'order_ids' => $newOrders->pluck('id')->values()->all(),
                    'order_codes' => $newOrders->pluck('id_bill')->filter()->values()->all(),
                ],
            );
        });

        $this->reloadDebt();
        Flux::toast(heading: 'Đã làm mới', text: "Đã bổ sung {$newOrders->count()} order vào công nợ.", variant: 'success');
        Flux::modal('refresh-orders')->close();
    }

    protected function eligibleOrdersQuery(Carbon $from, Carbon $to, int $saleId, int $customerId)
    {
        return Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->where('id_sale', $saleId)
            ->where('id_customer', $customerId)
            ->where('bill_status', '!=', OrderStatusEnum::HUY->value)
            ->whereNotNull('sale_price_locked_at')
            ->where(function ($q) {
                $q->whereNull('customer_payment_status')
                    ->orWhereNotIn('customer_payment_status', [
                        DebtStatusEnum::DA_CHOT_CUOC->value,
                        DebtStatusEnum::DA_THANH_TOAN_MOT_PHAN->value,
                        DebtStatusEnum::DA_THANH_TOAN->value,
                    ]);
            })
            ->whereDoesntHave('congNoDetails.congNo', fn ($q) => $q->where('status', '!=', DebtStatusEnum::DA_THANH_TOAN->value));
    }

    protected function snapshotForOrder(Order $order): array
    {
        $weight = (float) $order->packages->sum(fn ($package) => (float) ($package->row_c_weight ?: $package->c_weight ?: 0));
        $snapshot = [
            'order_code' => $order->id_bill,
            'sale_total' => $this->number(data_get($order->payment_cuocban, 'total_tongcuoc', 0)),
            'cost_total' => $this->number(data_get($order->payment_cuocvon, 'total_tongcuoc', 0)),
            'base_total' => $this->number(data_get($order->payment_cuocgoc, 'total_tongcuoc', 0)),
            'vat' => $this->number(data_get($order->payment_cuocban, 'total_vat', 0)),
            'ppxd' => $this->number(data_get($order->payment_cuocban, 'ppxd_amount', 0)),
            'fee' => $this->number(data_get($order->payment_cuocban, 'total_phuphi', 0)),
            'commission' => $this->number(data_get($order->payment_cuocvon, 'bonus_sale_amount', 0)),
            'weight' => $weight,
        ];

        return [
            'weight' => $weight,
            'cuocban' => $snapshot['sale_total'],
            'cuocvon' => $snapshot['cost_total'],
            'cuocgoc' => $snapshot['base_total'],
            'vat' => $snapshot['vat'],
            'ppxd' => $snapshot['ppxd'],
            'phuphi' => $snapshot['fee'],
            'hoahong' => $snapshot['commission'],
            'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    public ?int $reassignAccountantId = null;

    public function reassignAccountant(): void
    {
        abort_unless($this->hasDebtAdminPower(), 403);

        $data = $this->validate([
            'reassignAccountantId' => ['required', 'integer', 'exists:user,id'],
        ], [], [
            'reassignAccountantId' => 'Kế toán phụ trách',
        ]);

        $accountant = \App\Models\User::role('ketoan')->whereKey((int) $data['reassignAccountantId'])->first();

        if (! $accountant) {
            Flux::toast(heading: 'Không hợp lệ', text: 'User được chọn không phải kế toán.', variant: 'warning');
            return;
        }

        $oldAccountantId = $this->debt->id_ketoan;

        $this->debt->forceFill(['id_ketoan' => $accountant->id])->save();
        $this->debt->writeActivityLog(
            action: 'accountant_reassigned',
            title: 'Đổi kế toán phụ trách',
            metadata: [
                'ketoan_from' => $oldAccountantId,
                'ketoan_to' => $accountant->id,
            ],
        );

        $this->reloadDebt();
        Flux::modal('reassign-accountant')->close();
        Flux::toast(heading: 'Đã cập nhật', text: 'Đã đổi kế toán phụ trách công nợ.', variant: 'success');
    }

    #[Computed]
    public function accountants()
    {
        return \App\Models\User::role('ketoan')->orderBy('fullname')->get(['id', 'fullname', 'username', 'code']);
    }

    #[Computed]
    public function availableForNewInvoice(): float
    {
        return $this->debt->availableForNewInvoice();
    }

    #[Computed]
    public function pendingInvoicesTotal(): float
    {
        return $this->debt->pendingInvoicesTotal();
    }

    #[Computed]
    public function payingInvoice(): ?CongNoPayment
    {
        if (! $this->payingInvoiceId) {
            return null;
        }

        return $this->debt->payments->firstWhere('id', $this->payingInvoiceId);
    }

    protected function hydrateEditingPaymentSelections(array $payment): array
    {
        foreach (['phuphi', 'hh_khachhang'] as $bucket) {
            $payment[$bucket] = array_values(array_map(function ($row) use ($bucket) {
                if (! is_array($row)) {
                    $row = [];
                }

                $row['_key'] = (string) ($row['_key'] ?? Str::uuid());
                $name = trim((string) ($row['name'] ?? ''));

                if ($bucket === 'phuphi' && empty($row['id_loaiphuphi']) && $name !== '') {
                    $option = collect($this->feeOptions)->first(fn ($item) => strcasecmp($item['name'], $name) === 0);
                    $row['id_loaiphuphi'] = $option['id'] ?? null;
                }

                if ($bucket === 'hh_khachhang' && empty($row['id_loaichi']) && $name !== '') {
                    $option = collect($this->expenseOptions)->first(fn ($item) => strcasecmp($item['name'], $name) === 0);
                    $row['id_loaichi'] = $option['id'] ?? null;
                }

                return $row;
            }, $payment[$bucket] ?? []));
        }

        return $payment;
    }

    protected function syncEditingSelectedFee(string $property): void
    {
        $parts = explode('.', $property);
        $index = (int) ($parts[2] ?? -1);
        $optionId = (int) data_get($this->editingSaleCharge, "phuphi.$index.id_loaiphuphi");
        $option = collect($this->feeOptions)->firstWhere('id', $optionId);

        if (isset($this->editingSaleCharge['phuphi'][$index])) {
            $this->editingSaleCharge['phuphi'][$index]['name'] = $option['name'] ?? '';
            $this->editingSaleCharge['phuphi'][$index]['price'] = $option ? (float) ($option['price'] ?? 0) : $this->number($this->editingSaleCharge['phuphi'][$index]['price'] ?? 0);
        }

        $this->editingSaleCharge = $this->recalculateGroup($this->editingSaleCharge, 'dongiaban', ['phuphi', 'hh_khachhang']);
    }

    protected function syncEditingSelectedExpense(string $property): void
    {
        $parts = explode('.', $property);
        $index = (int) ($parts[2] ?? -1);
        $optionId = (int) data_get($this->editingSaleCharge, "hh_khachhang.$index.id_loaichi");
        $option = collect($this->expenseOptions)->firstWhere('id', $optionId);

        if (isset($this->editingSaleCharge['hh_khachhang'][$index])) {
            $this->editingSaleCharge['hh_khachhang'][$index]['name'] = $option['name'] ?? '';
        }

        $this->editingSaleCharge = $this->recalculateGroup($this->editingSaleCharge, 'dongiaban', ['phuphi', 'hh_khachhang']);
    }

    protected function isEditingComputedProperty(string $property): bool
    {
        foreach ([
            '.total',
            '.total_after_vat',
            '.vat_amount',
            '.ppxd_amount',
            '.total_vat',
            '.total_vat_phuphi',
            '.total_phuphi',
            '.total_phuphi_no_vat',
            '.total_tongcuoc',
            '.total_tongcuoc_no_vat',
            '.tongcuoc',
            '.name',
        ] as $computedSuffix) {
            if (str_ends_with($property, $computedSuffix)) {
                return true;
            }
        }

        return false;
    }

    protected function recalculateOrderPayments(Order $order, array $salePaymentInput): array
    {
        $salePayment = $this->normalizePayment($salePaymentInput, 'dongiaban');
        $costPayment = $order->payment_cuocvon ?? [];
        $basePayment = $this->normalizePayment($order->payment_cuocgoc, 'dongiagoc');

        $salePayment = $this->recalculateGroup($salePayment, 'dongiaban', ['phuphi', 'hh_khachhang']);
        $basePayment = $this->recalculateGroup($basePayment, 'dongiagoc', ['phuphi']);

        if ($order->agency_payment_status !== DebtStatusEnum::DA_THANH_TOAN->value) {
            $costPayment = $this->recalculateGroup($this->normalizePayment($costPayment, 'dongiavon'), 'dongiavon', ['phuphi', 'phichiho'], ['phichiho']);
            $saleBonusPercent = $this->number($costPayment['bonus_sale_percent'] ?? 0);
            $costPayment['bonus_sale_percent'] = $saleBonusPercent;
            $costPayment['bonus_sale_amount'] = round($this->number($salePayment['total_tongcuoc_no_vat'] ?? 0) * ($saleBonusPercent / 100));
        }

        return [
            $salePayment,
            $costPayment,
            $basePayment,
            $this->profitSnapshotFor($salePayment, $costPayment, $basePayment),
        ];
    }

    protected function normalizePayment(?array $payment, string $priceKey): array
    {
        return array_merge([
            $priceKey => 0,
            'vat_percent' => 0,
            'vat_amount' => 0,
            'ppxd_percent' => 0,
            'ppxd_amount' => 0,
            'tongcuoc' => 0,
            'phuphi' => [],
            'phichiho' => [],
            'hh_khachhang' => [],
            'bonus_sale_percent' => 0,
            'bonus_sale_amount' => 0,
            'total_hh_khachhang' => 0,
            'total_vat' => 0,
            'total_vat_phuphi' => 0,
            'total_phuphi_no_vat' => 0,
            'total_phuphi' => 0,
            'total_tongcuoc_no_vat' => 0,
            'total_tongcuoc' => 0,
        ], $payment ?? []);
    }

    protected function recalculateGroup(array $group, string $priceKey, array $buckets, array $excludedBuckets = []): array
    {
        $price = $this->number($group[$priceKey] ?? 0);
        $vatPercent = $this->number($group['vat_percent'] ?? 0);
        $ppxdPercent = $this->number($group['ppxd_percent'] ?? 0);
        $ppxdAmount = round($price * $ppxdPercent / 100);
        $vatAmount = round(($price + $ppxdAmount) * $vatPercent / 100);
        $feeTotalNoVat = 0;
        $feeVatTotal = 0;
        $feeTotal = 0;

        foreach ($buckets as $bucket) {
            $rows = $group[$bucket] ?? [];

            foreach ($rows as $index => $row) {
                if ($bucket === 'hh_khachhang') {
                    $amount = $this->number($row['so_tien'] ?? ($row['price'] ?? 0));
                    $rows[$index]['total'] = $amount;
                    $rows[$index]['total_after_vat'] = $amount;
                    $rows[$index]['vat_amount'] = 0;
                    continue;
                }

                $qty = max(0, $this->number($row['soluong'] ?? 0));
                $rowPrice = $this->number($row['price'] ?? ($row['so_tien'] ?? 0));
                $rowVatPercent = $this->number($row['vat_percent'] ?? ($row['vat'] ?? 0));
                $rowTotal = round(($qty ?: 1) * $rowPrice);
                $rowVatAmount = round($rowTotal * $rowVatPercent / 100);

                $rows[$index]['vat_amount'] = $rowVatAmount;
                $rows[$index]['total'] = $rowTotal;
                $rows[$index]['total_after_vat'] = $rowTotal + $rowVatAmount;

                if (in_array($bucket, $excludedBuckets, true)) {
                    continue;
                }

                $feeTotalNoVat += $rowTotal;
                $feeVatTotal += $rowVatAmount;
                $feeTotal += $rows[$index]['total_after_vat'];
            }

            $group[$bucket] = array_values($rows);
        }

        $group[$priceKey] = $price;
        $group['ppxd_amount'] = $ppxdAmount;
        $group['vat_amount'] = $vatAmount;
        $group['total_vat'] = $vatAmount + $feeVatTotal;
        $group['total_vat_phuphi'] = $feeVatTotal;
        $group['total_phuphi_no_vat'] = $feeTotalNoVat;
        $group['total_phuphi'] = $feeTotal;
        $group['total_phichiho'] = array_sum(array_map(
            fn ($row) => $this->number($row['price'] ?? ($row['so_tien'] ?? 0)),
            $group['phichiho'] ?? []
        ));
        $group['total_hh_khachhang'] = array_sum(array_map(
            fn ($row) => $this->number($row['so_tien'] ?? ($row['price'] ?? 0)),
            $group['hh_khachhang'] ?? []
        ));
        $group['total_tongcuoc_no_vat'] = $price + $ppxdAmount + $feeTotalNoVat;
        $group['tongcuoc'] = $price + $ppxdAmount + $vatAmount;
        $group['total_tongcuoc'] = $group['tongcuoc'] + $feeVatTotal + $feeTotalNoVat;

        return $group;
    }

    protected function profitSnapshotFor(array $salePayment, array $costPayment, array $basePayment): array
    {
        $saleNoVat = $this->number($salePayment['total_tongcuoc_no_vat'] ?? 0);
        $sale = $this->number($salePayment['total_tongcuoc'] ?? 0);
        $costNoVat = $this->number($costPayment['total_tongcuoc_no_vat'] ?? 0);
        $cost = $this->number($costPayment['total_tongcuoc'] ?? 0);
        $baseNoVat = $this->number($basePayment['total_tongcuoc_no_vat'] ?? 0);
        $base = $this->number($basePayment['total_tongcuoc'] ?? 0);
        $customerCommission = $this->number($salePayment['total_hh_khachhang'] ?? 0);
        $saleBonus = $this->number($costPayment['bonus_sale_amount'] ?? 0);
        $estimatedProfit = $saleNoVat - $costNoVat - $customerCommission;
        $profit = $estimatedProfit - $saleBonus;

        return [
            'cuocban_no_vat' => $saleNoVat,
            'cuocban' => $sale,
            'cuocvon_no_vat' => $costNoVat,
            'cuocvon' => $cost,
            'cuocgoc_no_vat' => $baseNoVat,
            'cuocgoc' => $base,
            'loinhuantamtinh' => $estimatedProfit,
            'tysuattamtinh' => $saleNoVat > 0 ? round(($estimatedProfit * 100) / $saleNoVat, 2) : 0,
            'loinhuan' => $profit,
            'tysuat' => $saleNoVat > 0 ? round(($profit * 100) / $saleNoVat, 2) : 0,
            'tysuatloinhuan' => $saleNoVat > 0 ? round(($profit * 100) / $saleNoVat, 2) : 0,
        ];
    }

    protected function detailSnapshot(array $snapshot, Order $order, array $salePayment, array $costPayment, array $basePayment): array
    {
        return array_merge($snapshot, [
            'order_code' => $order->id_bill,
            'sale_total' => $this->number($salePayment['total_tongcuoc'] ?? 0),
            'cost_total' => $this->number($costPayment['total_tongcuoc'] ?? 0),
            'base_total' => $this->number($basePayment['total_tongcuoc'] ?? 0),
            'vat' => $this->number($salePayment['total_vat'] ?? 0),
            'ppxd' => $this->number($salePayment['ppxd_amount'] ?? 0),
            'fee' => $this->number($salePayment['total_phuphi'] ?? 0),
            'commission' => $this->number($costPayment['bonus_sale_amount'] ?? 0),
        ]);
    }

    protected function orderPaymentHistorySnapshot(Order $order): array
    {
        return [
            'don_gia_ban' => $this->number(data_get($order->payment_cuocban, 'dongiaban')),
            'cuoc_ban' => $this->number(data_get($order->payment_cuocban, 'total_tongcuoc')),
            'hoa_hong_sale' => $this->number(data_get($order->payment_cuocvon, 'bonus_sale_amount')),
            'loi_nhuan_tam_tinh' => $this->number(data_get($order->payment_loinhuan, 'loinhuantamtinh')),
            'loi_nhuan' => $this->number(data_get($order->payment_loinhuan, 'loinhuan')),
        ];
    }

    protected function syncOrderPaymentStateFromDebt(): void
    {
        if ((float) $this->debt->paid_amount <= 0) {
            return;
        }

        $orderStatus = $this->debt->remaining_amount <= 0
            ? DebtStatusEnum::DA_THANH_TOAN->value
            : DebtStatusEnum::DA_THANH_TOAN_MOT_PHAN->value;

        $this->debt->orders()->update([
            'customer_payment_status' => $orderStatus,
            'customer_paid_at' => $orderStatus === DebtStatusEnum::DA_THANH_TOAN->value ? now() : null,
        ]);
    }

    public function number(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = preg_replace('/[^\d.,-]/', '', (string) ($value ?? 0));

        if ($normalized === '' || $normalized === '-') {
            return 0;
        }

        $hasComma = str_contains($normalized, ',');
        $hasDot = str_contains($normalized, '.');

        if ($hasComma && $hasDot) {
            $lastComma = strrpos($normalized, ',');
            $lastDot = strrpos($normalized, '.');

            if ($lastComma > $lastDot) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        } elseif ($hasComma) {
            if (preg_match('/^-?\d{1,3}(,\d{3})+$/', $normalized)) {
                $normalized = str_replace(',', '', $normalized);
            } else {
                $normalized = str_replace(',', '.', $normalized);
            }
        } elseif ($hasDot && preg_match('/^-?\d{1,3}(\.\d{3})+$/', $normalized)) {
            $normalized = str_replace('.', '', $normalized);
        }

        return (float) $normalized;
    }

    public function money(mixed $value): string
    {
        return number_format((float) $value, 0, ',', '.').' đ';
    }

    public function render()
    {
        return $this->view();
    }
};
