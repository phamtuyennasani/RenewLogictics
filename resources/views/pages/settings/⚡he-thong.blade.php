<?php

use App\Models\Setting;
use App\Services\Payments\PaymentProviderManager;
use Flux\Flux;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

new class extends Component
{
    public string $tab = 'payment';

    /** @var array<string, bool> [providerKey => enabled] */
    public array $paymentEnabled = [];

    /** @var array<string, string> [storageKey => value] — gom mọi field cấu hình của các cổng */
    public array $paymentConfig = [];

    public bool $einvoiceSepayEnabled = false;
    public string $einvoiceSepayEnvironment = 'sandbox';
    public string $einvoiceSepayClientId = '';
    public string $einvoiceSepayClientSecret = '';

    public string $sensitiveConfigGateway = '';
    public string $sensitiveConfigAuthGateway = '';
    public string $sensitiveConfigPassword = '';

    public bool $emailOrderEnabled = false;

    public string $smtpHost = '';
    public string $smtpPort = '587';
    public string $smtpUsername = '';
    public string $smtpPassword = '';
    public string $smtpFromEmail = '';
    public string $smtpFromName = '';

    public bool $isSaving = false;

    public function mount(): void
    {
        $this->loadFromSettings();
    }

    /**
     * Bật/tắt một cổng thanh toán theo key động.
     * Dùng method thay vì $toggle để chắc chắn hoạt động trên dot-path mảng.
     */
    public function togglePayment(string $key): void
    {
        $this->paymentEnabled[$key] = ! ($this->paymentEnabled[$key] ?? false);
    }

    protected function loadFromSettings(): void
    {
        $options = data_get(Setting::first(), 'options', []);

        // Payment: nạp động theo schema do từng cổng khai báo.
        foreach (PaymentProviderManager::configSchemas() as $key => $schema) {
            $this->paymentEnabled[$key] = (bool) ($options["payment_{$key}_enabled"] ?? false);

            foreach ($schema['fields'] as $field) {
                $this->paymentConfig[$field['key']] = (string) ($options[$field['key']] ?? '');
            }
        }

        $this->einvoiceSepayEnabled = (bool) ($options['einvoice_sepay_enabled'] ?? false);
        $this->einvoiceSepayEnvironment = $options['einvoice_sepay_environment'] ?? config('sepay.einvoice.environment', 'sandbox');
        $this->einvoiceSepayClientId = $options['einvoice_sepay_client_id'] ?? config('sepay.einvoice.client_id', '');
        $this->einvoiceSepayClientSecret = $options['einvoice_sepay_client_secret'] ?? config('sepay.einvoice.client_secret', '');

        $this->emailOrderEnabled = (bool) ($options['email_order_enabled'] ?? false);

        $this->smtpHost = $options['smtp_host'] ?? '';
        $this->smtpPort = $options['smtp_port'] ?? '587';
        $this->smtpUsername = $options['smtp_username'] ?? '';
        $this->smtpPassword = $options['smtp_password'] ?? '';
        $this->smtpFromEmail = $options['smtp_from_email'] ?? '';
        $this->smtpFromName = $options['smtp_from_name'] ?? '';
    }

    public function save(): void
    {
        // Payment: validate động theo schema, chỉ khi cổng đang bật.
        foreach (PaymentProviderManager::configSchemas() as $key => $schema) {
            if (! ($this->paymentEnabled[$key] ?? false)) {
                continue;
            }

            $rules = [];
            $messages = [];

            foreach ($schema['fields'] as $field) {
                if (! ($field['required'] ?? false)) {
                    continue;
                }

                // Field nhạy cảm chỉ validate được khi form đang mở khóa.
                if (($field['sensitive'] ?? false) && $this->sensitiveConfigGateway !== $key) {
                    continue;
                }

                $stateKey = "paymentConfig.{$field['key']}";
                $rules[$stateKey] = 'required|string|max:255';
                $messages["{$stateKey}.required"] = "Vui lòng nhập {$field['label']} ({$schema['name']}).";
            }

            if ($rules !== []) {
                $this->validate($rules, $messages);
            }
        }

        if ($this->einvoiceSepayEnabled) {
            $this->validate([
                'einvoiceSepayEnvironment' => 'required|in:sandbox,production',
                'einvoiceSepayClientId' => 'required|string|max:255',
                'einvoiceSepayClientSecret' => 'required|string|max:255',
            ], [
                'einvoiceSepayEnvironment.required' => 'Vui lòng chọn môi trường hóa đơn điện tử.',
                'einvoiceSepayClientId.required' => 'Vui lòng nhập Client ID hóa đơn điện tử SePay.',
                'einvoiceSepayClientSecret.required' => 'Vui lòng nhập Client Secret hóa đơn điện tử SePay.',
            ]);
        }

        $this->isSaving = true;

        $setting = Setting::firstOrCreate([], ['namevi' => 'Cấu hình hệ thống', 'options' => []]);
        $options = $setting->options ?? [];

        // Payment: lưu động theo schema.
        foreach (PaymentProviderManager::configSchemas() as $key => $schema) {
            $options["payment_{$key}_enabled"] = $this->paymentEnabled[$key] ?? false;

            foreach ($schema['fields'] as $field) {
                $isSensitive = $field['sensitive'] ?? false;

                // Field nhạy cảm chỉ ghi khi đã mở khóa đúng cổng + có quyền Admin.
                if ($isSensitive && ! ($this->sensitiveConfigGateway === $key && $this->canManageSensitiveConfig())) {
                    // Nạp lại giá trị cũ để tránh ghi đè rỗng và để UI hiển thị đúng.
                    $this->paymentConfig[$field['key']] = (string) ($options[$field['key']] ?? '');

                    continue;
                }

                $value = $this->paymentConfig[$field['key']] ?? '';
                $options[$field['key']] = $value;

                // Ghi kèm các khóa mirror (tương thích ngược, vd bank_code -> bank_name).
                foreach (($field['mirrorKeys'] ?? []) as $mirrorKey) {
                    $options[$mirrorKey] = $value;
                }
            }
        }

        $options['einvoice_sepay_enabled'] = $this->einvoiceSepayEnabled;

        if ($this->sensitiveConfigGateway === 'einvoice' && $this->canManageSensitiveConfig()) {
            $options['einvoice_sepay_environment'] = $this->einvoiceSepayEnvironment;
            $options['einvoice_sepay_client_id'] = $this->einvoiceSepayClientId;
            $options['einvoice_sepay_client_secret'] = $this->einvoiceSepayClientSecret;
        } else {
            $this->einvoiceSepayEnvironment = $options['einvoice_sepay_environment'] ?? config('sepay.einvoice.environment', 'sandbox');
            $this->einvoiceSepayClientId = $options['einvoice_sepay_client_id'] ?? config('sepay.einvoice.client_id', '');
            $this->einvoiceSepayClientSecret = $options['einvoice_sepay_client_secret'] ?? config('sepay.einvoice.client_secret', '');
        }

        $options['email_order_enabled'] = $this->emailOrderEnabled;

        $options['smtp_host'] = $this->smtpHost;
        $options['smtp_port'] = $this->smtpPort;
        $options['smtp_username'] = $this->smtpUsername;
        $options['smtp_password'] = $this->smtpPassword;
        $options['smtp_from_email'] = $this->smtpFromEmail;
        $options['smtp_from_name'] = $this->smtpFromName;

        $setting->update(['options' => $options]);

        $this->isSaving = false;

        Flux::toast(
            duration: 2000,
            heading: 'Thành công',
            text: 'Cấu hình hệ thống đã được lưu!',
            variant: 'success'
        );
    }

    public function openSensitiveConfigAuth(string $gateway): void
    {
        if (! in_array($gateway, $this->sensitiveGateways(), true)) {
            return;
        }

        if (! $this->canManageSensitiveConfig()) {
            Flux::toast(
                duration: 2500,
                heading: 'Không có quyền',
                text: 'Chỉ tài khoản Admin mới được xem hoặc chỉnh sửa API key.',
                variant: 'danger'
            );

            return;
        }

        $this->resetErrorBag('sensitiveConfigPassword');
        $this->sensitiveConfigPassword = '';
        $this->sensitiveConfigAuthGateway = $gateway;

        Flux::modal('payment-api-auth')->show();
    }

    public function authorizeSensitiveConfig(): void
    {
        $this->validate([
            'sensitiveConfigPassword' => 'required|string',
        ], [
            'sensitiveConfigPassword.required' => 'Vui lòng nhập mật khẩu Admin.',
        ]);

        $user = auth()->user();

        if (! $user || ! $this->canManageSensitiveConfig() || ! Hash::check($this->sensitiveConfigPassword, $user->password)) {
            $this->addError('sensitiveConfigPassword', 'Mật khẩu không đúng hoặc tài khoản không có quyền Admin.');

            return;
        }

        $this->sensitiveConfigGateway = $this->sensitiveConfigAuthGateway;
        $this->sensitiveConfigAuthGateway = '';
        $this->sensitiveConfigPassword = '';

        Flux::modal('payment-api-auth')->close();

        Flux::toast(
            duration: 2000,
            heading: 'Đã mở khóa',
            text: 'Bạn có thể xem và chỉnh sửa API key của cổng đã chọn.',
            variant: 'success'
        );
    }

    public function closeSensitiveConfigAuth(): void
    {
        $this->sensitiveConfigPassword = '';
        $this->sensitiveConfigAuthGateway = '';
        $this->resetErrorBag('sensitiveConfigPassword');
    }

    public function lockSensitiveConfig(string $gateway = ''): void
    {
        if ($gateway === '' || $this->sensitiveConfigGateway === $gateway) {
            $this->sensitiveConfigGateway = '';
        }

        $this->sensitiveConfigPassword = '';
    }

    protected function canManageSensitiveConfig(): bool
    {
        return (bool) auth()->user()?->hasRole('admin');
    }

    /**
     * Danh sách "cổng" có dữ liệu nhạy cảm cần xác thực lại Admin để xem/sửa:
     * các cổng payment có field sensitive + cổng e-invoice.
     *
     * @return array<int, string>
     */
    protected function sensitiveGateways(): array
    {
        $gateways = ['einvoice'];

        foreach (PaymentProviderManager::configSchemas() as $key => $schema) {
            foreach ($schema['fields'] as $field) {
                if ($field['sensitive'] ?? false) {
                    $gateways[] = $key;
                    break;
                }
            }
        }

        return $gateways;
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
    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        <div class="flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-normal text-neutral-500">Cấu hình</p>
                <h1 class="mt-1 text-2xl font-black tracking-normal text-neutral-950">Cấu hình hệ thống</h1>
                <p class="mt-2 max-w-2xl text-sm font-medium leading-6 text-neutral-500">
                    Quản lý cổng thanh toán, SMTP và thông tin nhận chuyển khoản cho toàn bộ hệ thống.
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-3 rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg text-white" style="{{ $gradientStyle }}">
                    <flux:icon.cog-6-tooth class="size-5" />
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-normal text-neutral-500">Trạng thái</p>
                    <p class="text-sm font-bold text-neutral-950">Sẵn sàng cấu hình</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 border-t border-neutral-100 bg-neutral-50/70 sm:grid-cols-3">
            <div class="flex items-center gap-3 border-b border-neutral-100 px-5 py-4 sm:border-b-0 sm:border-r sm:px-6">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">
                    <flux:icon.credit-card class="size-5" />
                </span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold text-neutral-950">
                        Cổng thanh toán
                    </p>
                    <p class="truncate text-xs font-medium text-neutral-500">
                        {{ collect($paymentEnabled)->filter()->count() }}/{{ count($paymentEnabled) }} cổng bật
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3 border-b border-neutral-100 px-5 py-4 sm:border-b-0 sm:border-r sm:px-6">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-sky-50 text-sky-700">
                    <flux:icon.receipt-percent class="size-5" />
                </span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold text-neutral-950">
                        Cổng hóa đơn
                    </p>
                    <p class="truncate text-xs font-medium text-neutral-500">
                        {{ $einvoiceSepayEnabled ? 'SePay đang bật' : 'Chưa bật' }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3 px-5 py-4 sm:px-6">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-sky-50 text-sky-700">
                    <flux:icon.envelope class="size-5" />
                </span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold text-neutral-950">
                        {{ $emailOrderEnabled ? 'Đang gửi email' : 'Chưa bật email' }}
                    </p>
                    <p class="truncate text-xs font-medium text-neutral-500">Thông báo đơn hàng</p>
                </div>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white p-1 shadow-sm">
        <div class="grid min-w-max grid-cols-3 gap-1 sm:min-w-0">
            @foreach ([
                ['key' => 'payment', 'label' => 'Thanh toán', 'icon' => 'credit-card'],
                ['key' => 'invoice', 'label' => 'Hóa đơn', 'icon' => 'receipt-percent'],
                ['key' => 'email', 'label' => 'Email', 'icon' => 'envelope'],
            ] as $t)
            <button
                wire:click="$set('tab', '{{ $t['key'] }}')"
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-md px-4 py-2.5 text-sm font-bold transition-colors whitespace-nowrap
                    @if($tab === $t['key'])
                        bg-primary-50 text-primary-700
                    @else
                        text-neutral-500 hover:bg-neutral-50 hover:text-neutral-800
                    @endif">
                @switch($t['icon'])
                    @case('credit-card')
                        <flux:icon.credit-card class="size-4" />
                        @break
                    @case('envelope')
                        <flux:icon.envelope class="size-4" />
                        @break
                    @case('receipt-percent')
                        <flux:icon.receipt-percent class="size-4" />
                        @break
                @endswitch
                {{ $t['label'] }}
            </button>
            @endforeach
        </div>
    </div>

    <form wire:submit="save" class="space-y-5">
        @if($tab === 'payment')
            <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-neutral-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div>
                        <h2 class="text-base font-black tracking-normal text-neutral-950">Cổng thanh toán</h2>
                        <p class="mt-1 text-sm font-medium text-neutral-500">Kiểm soát các phương thức khách hàng có thể chọn khi thanh toán.</p>
                    </div>
                    <span class="inline-flex w-fit items-center rounded-md bg-neutral-100 px-3 py-1 text-xs font-bold text-neutral-700">
                        {{ collect($paymentEnabled)->filter()->count() }} đang bật
                    </span>
                </div>

                <div class="space-y-3 p-5 sm:p-6">
                    @foreach (\App\Services\Payments\PaymentProviderManager::configSchemas() as $providerKey => $p)
                        @php
                            $isEnabled = $paymentEnabled[$providerKey] ?? false;
                            $hasSensitive = collect($p['fields'])->contains(fn ($f) => $f['sensitive'] ?? false);
                            $isUnlocked = $sensitiveConfigGateway === $providerKey;
                            $fieldCount = count($p['fields']);
                            $colsMd = $fieldCount <= 1 ? 'md:grid-cols-1' : ($fieldCount <= 2 ? 'md:grid-cols-2' : 'md:grid-cols-3');
                            $colsLg = $fieldCount <= 1 ? 'lg:grid-cols-1' : ($fieldCount <= 2 ? 'lg:grid-cols-2' : 'lg:grid-cols-3');
                        @endphp

                        <div class="flex items-center gap-3 rounded-lg border border-neutral-200 bg-white px-4 py-3 shadow-sm">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                <flux:icon :icon="$p['icon']" class="size-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-bold leading-5 text-neutral-950">{{ $p['name'] }}</p>
                                <p class="truncate text-xs font-medium leading-5 text-slate-400">{{ $p['description'] }}</p>
                            </div>
                            <button
                                type="button"
                                role="switch"
                                aria-checked="{{ $isEnabled ? 'true' : 'false' }}"
                                wire:click="togglePayment('{{ $providerKey }}')"
                                class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 {{ $isEnabled ? 'bg-emerald-500' : 'bg-neutral-300' }}">
                                <span class="inline-block h-4 w-4 rounded-full bg-white shadow-sm transition-transform {{ $isEnabled ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
                            </button>
                        </div>

                        @if($isEnabled)
                            @if(! $hasSensitive)
                                {{-- Cổng KHÔNG có field nhạy cảm: hiển thị form trực tiếp (kiểu SePay). --}}
                                <div class="overflow-hidden rounded-lg border border-emerald-100 bg-white shadow-sm">
                                    <div class="flex flex-col gap-3 border-b border-emerald-100 bg-emerald-50/60 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="flex min-w-0 items-start gap-3">
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-emerald-700 shadow-sm">
                                                <flux:icon.banknotes class="size-5" />
                                            </span>
                                            <div class="min-w-0">
                                                <p class="text-sm font-bold text-neutral-950">Cấu hình {{ $p['name'] }}</p>
                                                <p class="mt-1 text-xs font-medium text-neutral-500">Thông tin này bắt buộc để cổng hoạt động.</p>
                                            </div>
                                        </div>
                                        <span class="inline-flex w-fit items-center rounded-md bg-white px-2.5 py-1 text-xs font-bold text-emerald-700 shadow-sm">
                                            Bắt buộc
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-1 gap-4 p-4 {{ $colsMd }}">
                                        @foreach ($p['fields'] as $field)
                                            <flux:field>
                                                <flux:label :badge="($field['required'] ?? false) ? 'Bắt buộc' : null">{{ $field['label'] }}</flux:label>
                                                @if(($field['type'] ?? 'text') === 'select')
                                                    <flux:select wire:model="paymentConfig.{{ $field['key'] }}">
                                                        @foreach (($field['options'] ?? []) as $optValue => $optLabel)
                                                            <flux:select.option value="{{ $optValue }}">{{ $optLabel }}</flux:select.option>
                                                        @endforeach
                                                    </flux:select>
                                                @else
                                                    <flux:input
                                                        wire:model="paymentConfig.{{ $field['key'] }}"
                                                        type="{{ $field['type'] ?? 'text' }}"
                                                        placeholder="{{ $field['placeholder'] ?? '' }}" />
                                                @endif
                                                @error('paymentConfig.' . $field['key']) <flux:error>{{ $message }}</flux:error> @enderror
                                            </flux:field>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                {{-- Cổng CÓ field nhạy cảm: gate sau xác thực Admin. --}}
                                <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
                                    <div class="flex flex-col gap-3 border-b border-neutral-100 bg-neutral-50/70 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="flex min-w-0 items-start gap-3">
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-neutral-100 text-neutral-600">
                                                <flux:icon.key class="size-5" />
                                            </span>
                                            <div class="min-w-0">
                                                <p class="text-sm font-bold text-neutral-950">Thông tin API {{ $p['name'] }}</p>
                                                <p class="mt-1 text-xs font-medium text-neutral-500">Nhập khóa kết nối được cấp từ {{ $p['name'] }}.</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            @if($isUnlocked)
                                                <button
                                                    type="button"
                                                    wire:click="lockSensitiveConfig('{{ $providerKey }}')"
                                                    class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 bg-white px-2.5 py-1 text-xs font-bold text-neutral-600 transition-colors hover:bg-neutral-50">
                                                    <flux:icon.lock-closed class="size-3.5" />
                                                    Khóa lại
                                                </button>
                                            @else
                                                <span class="inline-flex w-fit items-center gap-1.5 rounded-md bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700">
                                                    <flux:icon.lock-closed class="size-3.5" />
                                                    Đang khóa
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    @if($isUnlocked)
                                        <div class="grid grid-cols-1 gap-4 p-4 {{ $colsLg }}">
                                            @foreach ($p['fields'] as $field)
                                                <flux:field>
                                                    <flux:label :badge="($field['required'] ?? false) ? 'Bắt buộc' : null">{{ $field['label'] }}</flux:label>
                                                    @if(($field['type'] ?? 'text') === 'select')
                                                        <flux:select wire:model="paymentConfig.{{ $field['key'] }}">
                                                            @foreach (($field['options'] ?? []) as $optValue => $optLabel)
                                                                <flux:select.option value="{{ $optValue }}">{{ $optLabel }}</flux:select.option>
                                                            @endforeach
                                                        </flux:select>
                                                    @else
                                                        <flux:input
                                                            wire:model="paymentConfig.{{ $field['key'] }}"
                                                            type="{{ $field['type'] ?? 'text' }}"
                                                            placeholder="{{ $field['placeholder'] ?? '' }}" />
                                                    @endif
                                                    @error('paymentConfig.' . $field['key']) <flux:error>{{ $message }}</flux:error> @enderror
                                                </flux:field>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="grid grid-cols-1 gap-3 p-4 lg:grid-cols-[1fr_auto] lg:items-center">
                                            <div class="grid grid-cols-1 gap-3 {{ $colsMd }}">
                                                @foreach ($p['fields'] as $field)
                                                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5">
                                                        <p class="text-xs font-bold text-neutral-500">{{ $field['label'] }}</p>
                                                        <p class="mt-1 font-mono text-sm font-bold tracking-normal text-neutral-400">••••••••••••</p>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <button
                                                type="button"
                                                wire:click="openSensitiveConfigAuth('{{ $providerKey }}')"
                                                class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-bold text-white shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                                                style="{{ $gradientStyle }}">
                                                <flux:icon.eye class="size-4" />
                                                Xem / chỉnh sửa
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        @if($tab === 'invoice')
            <div class="space-y-5">
                <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-3 border-b border-neutral-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-700">
                                <flux:icon.receipt-percent class="size-5" />
                            </span>
                            <div class="min-w-0">
                                <h2 class="text-base font-black tracking-normal text-neutral-950">Cổng hóa đơn điện tử</h2>
                                <p class="mt-1 text-sm font-medium text-neutral-500">Cấu hình API hóa đơn điện tử SePay.</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            aria-checked="{{ $einvoiceSepayEnabled ? 'true' : 'false' }}"
                            wire:click="$toggle('einvoiceSepayEnabled')"
                            class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 {{ $einvoiceSepayEnabled ? 'bg-emerald-500' : 'bg-neutral-300' }}">
                            <span class="inline-block h-4 w-4 rounded-full bg-white shadow-sm transition-transform {{ $einvoiceSepayEnabled ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
                        </button>
                    </div>

                    @if($einvoiceSepayEnabled)
                        <div class="overflow-hidden">
                            <div class="flex flex-col gap-3 border-b border-neutral-100 bg-neutral-50/70 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex min-w-0 items-start gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-700">
                                        <flux:icon.key class="size-5" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-neutral-950">API SePay eInvoice</p>
                                        <p class="mt-1 text-xs font-medium text-neutral-500">Client ID và Client Secret dùng để lấy token tạo hóa đơn.</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if($sensitiveConfigGateway === 'einvoice')
                                        <button
                                            type="button"
                                            wire:click="lockSensitiveConfig('einvoice')"
                                            class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 bg-white px-2.5 py-1 text-xs font-bold text-neutral-600 transition-colors hover:bg-neutral-50">
                                            <flux:icon.lock-closed class="size-3.5" />
                                            Khóa lại
                                        </button>
                                    @else
                                        <span class="inline-flex w-fit items-center gap-1.5 rounded-md bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700">
                                            <flux:icon.lock-closed class="size-3.5" />
                                            Đang khóa
                                        </span>
                                    @endif
                                </div>
                            </div>

                            @if($sensitiveConfigGateway === 'einvoice')
                                <div class="grid grid-cols-1 gap-4 p-4 lg:grid-cols-3">
                                    <flux:field>
                                        <flux:label badge="Bắt buộc">Môi trường</flux:label>
                                        <flux:select wire:model="einvoiceSepayEnvironment">
                                            <flux:select.option value="sandbox">Sandbox</flux:select.option>
                                            <flux:select.option value="production">Production</flux:select.option>
                                        </flux:select>
                                        @error('einvoiceSepayEnvironment') <flux:error>{{ $message }}</flux:error> @enderror
                                    </flux:field>

                                    <flux:field>
                                        <flux:label badge="Bắt buộc">Client ID</flux:label>
                                        <flux:input wire:model="einvoiceSepayClientId" placeholder="Client ID từ SePay" />
                                        @error('einvoiceSepayClientId') <flux:error>{{ $message }}</flux:error> @enderror
                                    </flux:field>

                                    <flux:field>
                                        <flux:label badge="Bắt buộc">Client Secret</flux:label>
                                        <flux:input wire:model="einvoiceSepayClientSecret" type="password" placeholder="Client Secret từ SePay" />
                                        @error('einvoiceSepayClientSecret') <flux:error>{{ $message }}</flux:error> @enderror
                                    </flux:field>
                                </div>
                            @else
                                <div class="grid grid-cols-1 gap-3 p-4 lg:grid-cols-[1fr_auto] lg:items-center">
                                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5">
                                            <p class="text-xs font-bold text-neutral-500">Môi trường</p>
                                            <p class="mt-1 text-sm font-bold tracking-normal text-neutral-700">{{ $einvoiceSepayEnvironment === 'production' ? 'Production' : 'Sandbox' }}</p>
                                        </div>
                                        @foreach (['Client ID', 'Client Secret'] as $label)
                                            <div class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5">
                                                <p class="text-xs font-bold text-neutral-500">{{ $label }}</p>
                                                <p class="mt-1 font-mono text-sm font-bold tracking-normal text-neutral-400">••••••••••••</p>
                                            </div>
                                        @endforeach
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="openSensitiveConfigAuth('einvoice')"
                                        class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-bold text-white shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                                        style="{{ $gradientStyle }}">
                                        <flux:icon.eye class="size-4" />
                                        Xem / chỉnh sửa
                                    </button>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="p-5 sm:p-6">
                            <div class="rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3">
                                <p class="text-sm font-bold text-neutral-900">Cổng hóa đơn đang tắt</p>
                                <p class="mt-1 text-xs font-medium text-neutral-500">Bật cổng hóa đơn để cấu hình API SePay eInvoice.</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        @if($tab === 'email')
            <div class="space-y-5">
                <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
                    <div class="p-5 sm:p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex min-w-0 items-start gap-4">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-700">
                                    <flux:icon.envelope class="size-5" />
                                </span>
                                <div class="min-w-0">
                                    <h2 class="text-base font-black tracking-normal text-neutral-950">Email đơn hàng</h2>
                                    <p class="mt-1 text-sm font-medium text-neutral-500">Tự động gửi email thông báo sau khi tạo đơn.</p>
                                </div>
                            </div>
                            <flux:checkbox wire:model="emailOrderEnabled" />
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
                    <div class="flex items-center gap-3 border-b border-neutral-100 px-5 py-5 sm:px-6">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-neutral-100 text-neutral-700">
                            <flux:icon.server-stack class="size-5" />
                        </span>
                        <div>
                            <h2 class="text-base font-black tracking-normal text-neutral-950">Cấu hình SMTP</h2>
                            <p class="mt-1 text-sm font-medium text-neutral-500">Thông tin máy chủ gửi email của hệ thống.</p>
                        </div>
                    </div>

                    <div class="space-y-5 p-5 sm:p-6">
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

        <div class="flex flex-col gap-3 rounded-lg border border-neutral-200 bg-white px-5 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <p class="text-sm font-medium text-neutral-500">
                Thay đổi chỉ có hiệu lực sau khi lưu cấu hình.
            </p>
            <button
                type="submit"
                wire:loading.attr="disabled"
                class="inline-flex items-center justify-center gap-2 rounded-lg px-5 py-2.5 text-sm font-bold text-white shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0 disabled:hover:shadow-none sm:w-auto"
                style="{{ $gradientStyle }}">
                @if ($isSaving)
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Đang lưu...
                @else
                    <flux:icon.check-circle class="size-4" />
                    Lưu cấu hình
                @endif
            </button>
        </div>
    </form>

    <flux:modal name="payment-api-auth" class="w-full max-w-md" @close="$wire.closeSensitiveConfigAuth()">
        <div class="space-y-5">
            <div class="flex items-start gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-700">
                    <flux:icon.shield-check class="size-5" />
                </span>
                <div class="min-w-0">
                    <h2 class="text-base font-black tracking-normal text-neutral-950">
                        Xác thực Admin{{ $sensitiveConfigAuthGateway ? ' - ' . match ($sensitiveConfigAuthGateway) {
                            'momo' => 'MoMo',
                            'vnpay' => 'VNPay',
                            'einvoice' => 'Hóa đơn SePay',
                            default => '',
                        } : '' }}
                    </h2>
                    <p class="mt-1 text-sm font-medium leading-6 text-neutral-500">
                        API key là thông tin bảo mật. Vui lòng nhập mật khẩu tài khoản Admin hiện tại để xem hoặc chỉnh sửa cổng đã chọn.
                    </p>
                </div>
            </div>

            <flux:field>
                <flux:label>Mật khẩu Admin</flux:label>
                <flux:input
                    wire:model.defer="sensitiveConfigPassword"
                    type="password"
                    placeholder="Nhập mật khẩu"
                    autocomplete="current-password"
                    wire:keydown.enter="authorizeSensitiveConfig" />
                @error('sensitiveConfigPassword') <flux:error>{{ $message }}</flux:error> @enderror
            </flux:field>

            <div class="rounded-lg border border-amber-100 bg-amber-50 px-3 py-2.5">
                <p class="text-xs font-medium leading-5 text-amber-800">
                    Chỉ user có role Admin và nhập đúng mật khẩu mới được mở khóa. Khi chưa mở khóa, thao tác lưu sẽ không ghi đè các API key đang có.
                </p>
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <flux:modal.close>
                    <button type="button" class="inline-flex items-center justify-center rounded-lg border border-neutral-200 bg-white px-4 py-2.5 text-sm font-bold text-neutral-700 transition-colors hover:bg-neutral-50">
                        Hủy
                    </button>
                </flux:modal.close>

                <button
                    type="button"
                    wire:click="authorizeSensitiveConfig"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-neutral-950 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition-colors hover:bg-neutral-800">
                    <flux:icon.lock-open class="size-4" />
                    Mở khóa
                </button>
            </div>
        </div>
    </flux:modal>
</div>
