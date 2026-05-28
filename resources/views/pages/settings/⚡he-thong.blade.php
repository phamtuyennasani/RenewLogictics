<?php

use App\Models\Setting;
use Flux\Flux;
use Livewire\Component;

new class extends Component
{
    public string $tab = 'payment';

    public bool $sepayEnabled = false;
    public bool $momoEnabled = false;
    public bool $vnpayEnabled = false;

    public bool $emailOrderEnabled = false;

    public string $smtpHost = '';
    public string $smtpPort = '587';
    public string $smtpUsername = '';
    public string $smtpPassword = '';
    public string $smtpFromEmail = '';
    public string $smtpFromName = '';

    public string $bankName = '';
    public string $bankAccountNumber = '';
    public string $bankAccountName = '';
    public string $bankBranch = '';

    public bool $isSaving = false;

    public function mount(): void
    {
        $this->loadFromSettings();
    }

    protected function loadFromSettings(): void
    {
        $options = data_get(Setting::first(), 'options', []);

        $this->sepayEnabled = (bool) ($options['payment_sepay_enabled'] ?? false);
        $this->momoEnabled = (bool) ($options['payment_momo_enabled'] ?? false);
        $this->vnpayEnabled = (bool) ($options['payment_vnpay_enabled'] ?? false);

        $this->emailOrderEnabled = (bool) ($options['email_order_enabled'] ?? false);

        $this->smtpHost = $options['smtp_host'] ?? '';
        $this->smtpPort = $options['smtp_port'] ?? '587';
        $this->smtpUsername = $options['smtp_username'] ?? '';
        $this->smtpPassword = $options['smtp_password'] ?? '';
        $this->smtpFromEmail = $options['smtp_from_email'] ?? '';
        $this->smtpFromName = $options['smtp_from_name'] ?? '';

        $this->bankName = $options['bank_name'] ?? '';
        $this->bankAccountNumber = $options['bank_account_number'] ?? '';
        $this->bankAccountName = $options['bank_account_name'] ?? '';
        $this->bankBranch = $options['bank_branch'] ?? '';
    }

    public function save(): void
    {
        $this->isSaving = true;

        $setting = Setting::firstOrCreate([], ['namevi' => 'Cấu hình hệ thống', 'options' => []]);
        $options = $setting->options ?? [];

        $options['payment_sepay_enabled'] = $this->sepayEnabled;
        $options['payment_momo_enabled'] = $this->momoEnabled;
        $options['payment_vnpay_enabled'] = $this->vnpayEnabled;

        $options['email_order_enabled'] = $this->emailOrderEnabled;

        $options['smtp_host'] = $this->smtpHost;
        $options['smtp_port'] = $this->smtpPort;
        $options['smtp_username'] = $this->smtpUsername;
        $options['smtp_password'] = $this->smtpPassword;
        $options['smtp_from_email'] = $this->smtpFromEmail;
        $options['smtp_from_name'] = $this->smtpFromName;

        $options['bank_name'] = $this->bankName;
        $options['bank_account_number'] = $this->bankAccountNumber;
        $options['bank_account_name'] = $this->bankAccountName;
        $options['bank_branch'] = $this->bankBranch;

        $setting->update(['options' => $options]);

        $this->isSaving = false;

        Flux::toast(
            duration: 2000,
            heading: 'Thành công',
            text: 'Cấu hình hệ thống đã được lưu!',
            variant: 'success'
        );
    }

    public function render()
    {
        return $this->view();
    }
};

?>

@php
$primaryHex = config('theme.primary.hex', '#3b82f6');
$accentHex  = config('theme.accent.hex', '#0ea5e9');
$gradientStyle = "background: linear-gradient(135deg, {$primaryHex}, {$accentHex});";
@endphp

<div class="mx-auto space-y-6">
    <div>
        <p class="text-sm text-neutral-500">Cấu hình</p>
        <h1 class="text-2xl font-bold text-neutral-900">Cấu hình hệ thống</h1>
    </div>

    <div class="flex border-b border-neutral-200 gap-1 overflow-x-auto">
        @foreach ([
            ['key' => 'payment', 'label' => 'Thanh toán'],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'bank', 'label' => 'Ngân hàng'],
        ] as $t)
            <button
                wire:click="$set('tab', '{{ $t['key'] }}')"
                type="button"
                class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap
                    @if($tab === $t['key'])
                        border-primary-500 text-primary-600
                    @else
                        border-transparent text-neutral-500 hover:text-neutral-700 hover:border-neutral-300
                    @endif">
                {{ $t['label'] }}
            </button>
        @endforeach
    </div>

    <form wire:submit="save" class="space-y-5">
        @if($tab === 'payment')
            <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm">
                <div class="px-6 py-5 border-b border-neutral-100">
                    <h2 class="text-sm font-semibold text-neutral-700 uppercase tracking-wide">Bật / Tắt cổng thanh toán</h2>
                    <p class="text-xs text-neutral-500 mt-1">Bật cổng thanh toán để hiển thị phương thức thanh toán cho khách hàng.</p>
                </div>

                <div class="p-6 space-y-4">
                    <div class="flex items-center justify-between rounded-xl border border-neutral-100 bg-neutral-50 p-4">
                        <div>
                            <p class="text-sm font-semibold text-neutral-900">SePay</p>
                            <p class="text-xs text-neutral-500 mt-1">Thanh toán QR và đối soát tự động.</p>
                        </div>
                        <flux:checkbox wire:model="sepayEnabled" />
                    </div>

                    <div class="flex items-center justify-between rounded-xl border border-neutral-100 bg-neutral-50 p-4">
                        <div>
                            <p class="text-sm font-semibold text-neutral-900">MoMo</p>
                            <p class="text-xs text-neutral-500 mt-1">Thanh toán qua ví MoMo.</p>
                        </div>
                        <flux:checkbox wire:model="momoEnabled" />
                    </div>

                    <div class="flex items-center justify-between rounded-xl border border-neutral-100 bg-neutral-50 p-4">
                        <div>
                            <p class="text-sm font-semibold text-neutral-900">VNPay</p>
                            <p class="text-xs text-neutral-500 mt-1">Thanh toán qua thẻ và Internet Banking.</p>
                        </div>
                        <flux:checkbox wire:model="vnpayEnabled" />
                    </div>
                </div>
            </div>
        @endif

        @if($tab === 'email')
            <div class="space-y-5">
                <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm">
                    <div class="px-6 py-5 border-b border-neutral-100">
                        <h2 class="text-sm font-semibold text-neutral-700 uppercase tracking-wide">Bật / Tắt email</h2>
                    </div>

                    <div class="p-6">
                        <div class="flex items-center justify-between rounded-xl border border-neutral-100 bg-neutral-50 p-4">
                            <div>
                                <p class="text-sm font-semibold text-neutral-900">Gửi email khi tạo đơn hàng</p>
                                <p class="text-xs text-neutral-500 mt-1">Bật để gửi email thông báo sau khi tạo đơn.</p>
                            </div>
                            <flux:checkbox wire:model="emailOrderEnabled" />
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm">
                    <div class="px-6 py-5 border-b border-neutral-100">
                        <h2 class="text-sm font-semibold text-neutral-700 uppercase tracking-wide">Cấu hình SMTP</h2>
                    </div>

                    <div class="p-6 space-y-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <flux:field>
                                <flux:label>SMTP Host</flux:label>
                                <flux:input wire:model="smtpHost" placeholder="smtp.gmail.com" />
                            </flux:field>
                            <flux:field>
                                <flux:label>SMTP Port</flux:label>
                                <flux:input wire:model="smtpPort" placeholder="587" />
                            </flux:field>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <flux:field>
                                <flux:label>Username</flux:label>
                                <flux:input wire:model="smtpUsername" placeholder="noreply@company.com" />
                            </flux:field>
                            <flux:field>
                                <flux:label>Password</flux:label>
                                <flux:input wire:model="smtpPassword" type="password" placeholder="••••••••" />
                            </flux:field>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <flux:field>
                                <flux:label>From Email</flux:label>
                                <flux:input wire:model="smtpFromEmail" type="email" placeholder="noreply@company.com" />
                            </flux:field>
                            <flux:field>
                                <flux:label>From Name</flux:label>
                                <flux:input wire:model="smtpFromName" placeholder="RenewLogictics" />
                            </flux:field>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if($tab === 'bank')
            <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm">
                <div class="px-6 py-5 border-b border-neutral-100">
                    <h2 class="text-sm font-semibold text-neutral-700 uppercase tracking-wide">Thông tin tài khoản ngân hàng</h2>
                </div>

                <div class="p-6 space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <flux:field>
                            <flux:label>Tên ngân hàng</flux:label>
                            <flux:input wire:model="bankName" placeholder="VD: Vietcombank" />
                        </flux:field>
                        <flux:field>
                            <flux:label>Chi nhánh</flux:label>
                            <flux:input wire:model="bankBranch" placeholder="VD: Chi nhánh TP.HCM" />
                        </flux:field>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <flux:field>
                            <flux:label>Số tài khoản</flux:label>
                            <flux:input wire:model="bankAccountNumber" placeholder="1234567890" />
                        </flux:field>
                        <flux:field>
                            <flux:label>Tên tài khoản</flux:label>
                            <flux:input wire:model="bankAccountName" placeholder="CONG TY TNHH ABC" />
                        </flux:field>
                    </div>
                </div>
            </div>
        @endif

        <div class="px-6 py-4 border border-neutral-100 rounded-2xl flex items-center justify-end bg-neutral-50/50">
            <button
                type="submit"
                wire:loading.attr="disabled"
                class="px-6 py-2.5 text-sm font-medium text-white rounded-xl transition-all shadow-sm hover:shadow-md hover:-translate-y-0.5 flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:shadow-none disabled:hover:translate-y-0"
                style="{{ $gradientStyle }}">
                @if ($isSaving)
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Đang lưu...
                @else
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                    </svg>
                    Lưu cấu hình
                @endif
            </button>
        </div>
    </form>
</div>
