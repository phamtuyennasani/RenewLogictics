@php
    $isSuccess = $status === 'success';
    $isProcessing = $status === 'processing';
    $isFailed = $status === 'failed';

    $title = $isSuccess
        ? 'Thanh toán thành công'
        : ($isProcessing ? 'Đang cập nhật thanh toán' : 'Thanh toán chưa thành công');

    $subtitle = $isSuccess
        ? 'Cảm ơn quý khách. Hóa đơn đã được ghi nhận thanh toán trong hệ thống.'
        : ($isProcessing
            ? 'Cổng thanh toán đã báo giao dịch thành công. Hóa đơn sẽ được cập nhật trong ít phút.'
            : 'Giao dịch chưa hoàn tất. Quý khách vui lòng kiểm tra lại thông tin thanh toán.');

    $tone = $isSuccess ? 'success' : ($isProcessing ? 'processing' : 'failed');
    $amount = (int) ($data['amount'] ?? 0);
    $invoiceCode = $invoice?->ma_hoa_don ?: ($data['reference'] ?? null);
    $invoiceStatus = $invoice?->status?->label() ?? 'Đang kiểm tra';
    $paidAt = $data['paid_at'] ? $data['paid_at']->format('d/m/Y H:i:s') : 'Đang cập nhật';
    $customerName = $invoice?->user
        ? trim(($invoice->user->fullname ?: $invoice->user->username) . ($invoice->user->code ? ' (' . $invoice->user->code . ')' : ''))
        : null;

    $signatureStatus = $data['signature_status'] ?? 'missing';
    $checkMeta = match ($signatureStatus) {
        'valid' => ['label' => 'Thông tin đã được kiểm tra', 'class' => 'success', 'text' => 'Kết quả trả về từ cổng thanh toán hợp lệ.'],
        'invalid' => ['label' => 'Cần kiểm tra lại', 'class' => 'failed', 'text' => 'Thông tin trả về chưa khớp. Bộ phận hỗ trợ sẽ cần mã giao dịch để đối chiếu.'],
        default => ['label' => 'Đang kiểm tra thông tin', 'class' => 'processing', 'text' => 'Hệ thống đang đối chiếu kết quả thanh toán.'],
    };

    $displayStatus = $isSuccess ? 'Đã thanh toán' : ($isProcessing ? 'Đang cập nhật' : 'Chưa hoàn tất');
    $supportCode = $data['transaction_id'] ?: $invoiceCode ?: 'Không có dữ liệu';
    $homeUrl = url('/');
@endphp
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} - {{ config('system.name', 'BEE EXPRESS') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>
        .payment-status-badge {
            position: relative;
            isolation: isolate;
            overflow: hidden;
            box-shadow: 0 10px 28px rgb(15 23 42 / 0.08);
            transition: transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease;
        }

        .payment-status-badge::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -1;
            background: linear-gradient(120deg, transparent 0%, rgb(255 255 255 / 0.72) 42%, transparent 68%);
            transform: translateX(-130%);
            animation: payment-status-shine 3.2s ease-in-out infinite;
        }

        .payment-status-badge:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 34px rgb(15 23 42 / 0.12);
        }

        .payment-status-dot {
            position: relative;
            box-shadow: 0 0 0 0 currentColor;
            animation: payment-status-pulse 1.8s ease-out infinite;
        }

        @keyframes payment-status-shine {
            0%, 42% { transform: translateX(-130%); }
            70%, 100% { transform: translateX(130%); }
        }

        @keyframes payment-status-pulse {
            0% { box-shadow: 0 0 0 0 currentColor; opacity: 1; }
            70% { box-shadow: 0 0 0 7px transparent; opacity: 0.88; }
            100% { box-shadow: 0 0 0 0 transparent; opacity: 1; }
        }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
            .print-shadow { box-shadow: none !important; }
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-sky-50 font-sans text-neutral-900 antialiased">
    <main class="min-h-screen px-4 py-6 sm:px-6 lg:px-8">
        <section class="mx-auto flex min-h-[calc(100vh-3rem)] w-full max-w-6xl items-center">
            <div class="print-shadow w-full overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-2xl shadow-blue-900/10">
                <header class="flex flex-col gap-4 border-b border-neutral-100 bg-white/90 px-6 py-5 sm:flex-row sm:items-center sm:justify-between lg:px-8">
                    <div class="flex min-w-0 items-center gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-500 to-sky-600 text-lg font-extrabold text-white shadow-lg shadow-blue-500/25">
                            {{ strtoupper(mb_substr(config('system.name', 'BEE EXPRESS'), 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-base font-extrabold text-neutral-900">{{ config('system.name', 'BEE EXPRESS') }}</p>
                            <p class="text-sm font-medium text-neutral-500">Kết quả thanh toán {{ $providerName }}</p>
                        </div>
                    </div>

                    <div class="payment-status-badge inline-flex w-fit items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-bold
                        @if($isSuccess) border-emerald-200 bg-emerald-50 text-emerald-700
                        @elseif($isProcessing) border-amber-200 bg-amber-50 text-amber-700
                        @else border-red-200 bg-red-50 text-red-700 @endif">
                        <span class="payment-status-dot h-2 w-2 rounded-full
                            @if($isSuccess) bg-emerald-500
                            @elseif($isProcessing) bg-amber-500
                            @else bg-red-500 @endif"></span>
                        {{ $displayStatus }}
                    </div>
                </header>

                <section class="grid gap-8 px-6 py-8 lg:grid-cols-[minmax(0,1fr)_22rem] lg:px-8 lg:py-10">
                    <div class="flex gap-5">
                        <div class="hidden shrink-0 sm:block">
                            <div class="flex h-16 w-16 items-center justify-center rounded-2xl
                                @if($isSuccess) bg-emerald-100 text-emerald-600
                                @elseif($isProcessing) bg-amber-100 text-amber-600
                                @else bg-red-100 text-red-600 @endif">
                                @if($isSuccess)
                                    <svg class="h-9 w-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                @elseif($isProcessing)
                                    <svg class="h-9 w-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 6v6l4 2" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12a9 9 0 1 1-3.6-7.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                @else
                                    <svg class="h-9 w-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M18 6 6 18M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                @endif
                            </div>
                        </div>

                        <div class="min-w-0 flex-1">
                            <h1 class="max-w-3xl text-3xl font-extrabold leading-tight tracking-normal text-neutral-950 sm:text-4xl lg:text-5xl">{{ $title }}</h1>
                            <p class="mt-4 max-w-2xl text-base font-medium leading-7 text-neutral-600 sm:text-lg">{{ $subtitle }}</p>

                            <div class="mt-8 grid gap-3 sm:grid-cols-3">
                                <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4">
                                    <p class="text-xs font-bold uppercase tracking-normal text-neutral-500">Mã hóa đơn</p>
                                    <p class="mt-2 break-words text-sm font-extrabold text-neutral-950">{{ $invoiceCode ?: 'Đang cập nhật' }}</p>
                                </div>
                                <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4">
                                    <p class="text-xs font-bold uppercase tracking-normal text-neutral-500">Mã giao dịch</p>
                                    <p class="mt-2 break-words text-sm font-extrabold text-neutral-950">{{ $data['transaction_id'] ?: 'Đang cập nhật' }}</p>
                                </div>
                                <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4">
                                    <p class="text-xs font-bold uppercase tracking-normal text-neutral-500">Thời gian</p>
                                    <p class="mt-2 break-words text-sm font-extrabold text-neutral-950">{{ $paidAt }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <aside class="rounded-2xl border border-blue-100 bg-gradient-to-br from-blue-50 to-sky-50 p-6 shadow-lg shadow-blue-500/10">
                        <p class="text-sm font-bold text-neutral-500">Số tiền thanh toán</p>
                        <p class="mt-3 text-4xl font-extrabold tracking-normal text-neutral-950">{{ number_format($amount, 0, ',', '.') }} đ</p>
                        <div class="mt-5 rounded-xl bg-white/80 p-4 ring-1 ring-blue-100">
                            <p class="text-xs font-bold uppercase tracking-normal text-neutral-500">Mã giao dịch</p>
                            <p class="mt-1 break-words text-sm font-extrabold text-blue-700">{{ $supportCode }}</p>
                        </div>
                    </aside>
                </section>

                <section class="grid border-t border-neutral-100 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <div class="px-6 py-7 lg:px-8">
                        <h2 class="text-xl font-extrabold text-neutral-950">Thông tin thanh toán</h2>
                        <div class="mt-5 divide-y divide-neutral-100 rounded-2xl border border-neutral-200 bg-white">
                            <div class="grid gap-1 px-5 py-4 sm:grid-cols-[12rem_1fr] sm:gap-6">
                                <p class="text-sm font-bold text-neutral-500">Trạng thái hóa đơn</p>
                                <p class="break-words text-sm font-extrabold text-neutral-950">{{ $invoiceStatus }}</p>
                            </div>
                            <div class="grid gap-1 px-5 py-4 sm:grid-cols-[12rem_1fr] sm:gap-6">
                                <p class="text-sm font-bold text-neutral-500">Nội dung</p>
                                <p class="break-words text-sm font-extrabold text-neutral-950">{{ $data['order_info'] ?: $data['message'] }}</p>
                            </div>
                            <div class="grid gap-1 px-5 py-4 sm:grid-cols-[12rem_1fr] sm:gap-6">
                                <p class="text-sm font-bold text-neutral-500">Phương thức</p>
                                <p class="break-words text-sm font-extrabold text-neutral-950">{{ $providerName }}{{ $data['bank_code'] ? ' · ' . $data['bank_code'] : '' }}{{ $data['card_type'] ? ' · ' . $data['card_type'] : '' }}</p>
                            </div>
                            <div class="grid gap-1 px-5 py-4 sm:grid-cols-[12rem_1fr] sm:gap-6">
                                <p class="text-sm font-bold text-neutral-500">Khách hàng</p>
                                <p class="break-words text-sm font-extrabold text-neutral-950">{{ $customerName ?: 'Không hiển thị' }}</p>
                            </div>
                        </div>

                        <div class="no-print mt-6 flex flex-wrap gap-3">
                            @if($supportPhone && $supportPhoneHref)
                                <a href="{{ $supportPhoneHref }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-500 to-sky-500 px-5 text-sm font-bold text-white shadow-lg shadow-blue-500/25 transition hover:from-blue-600 hover:to-sky-600">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.91.32 1.8.59 2.65a2 2 0 0 1-.45 2.11L8 9.73a16 16 0 0 0 6.27 6.27l1.25-1.25a2 2 0 0 1 2.11-.45c.85.27 1.74.47 2.65.59A2 2 0 0 1 22 16.92Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Gọi hỗ trợ {{ $supportPhone }}
                                </a>
                            @endif
                            @if($supportEmail && $supportEmailHref)
                                <a href="{{ $supportEmailHref }}?subject={{ rawurlencode('Hỗ trợ thanh toán ' . ($invoiceCode ?: $supportCode)) }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-neutral-200 bg-white px-5 text-sm font-bold text-neutral-800 transition hover:bg-neutral-50">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" stroke-linecap="round" stroke-linejoin="round"/><path d="m22 6-10 7L2 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Gửi email hỗ trợ
                                </a>
                            @endif
                            @unless(($supportPhone && $supportPhoneHref) || ($supportEmail && $supportEmailHref))
                                <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-700">Thông tin hỗ trợ đang được cập nhật.</p>
                            @endunless
                        </div>
                    </div>
                    <aside class="border-t border-neutral-100 bg-neutral-50 px-6 py-7 lg:border-l lg:border-t-0 lg:px-8">
                        <h2 class="text-xl font-extrabold text-neutral-950">Tiến trình xử lý</h2>
                        <div class="mt-5 space-y-4">
                            <div class="flex gap-3">
                                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $data['gateway_success'] ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }} text-sm font-extrabold">1</span>
                                <div>
                                    <p class="text-sm font-extrabold text-neutral-950">{{ $data['gateway_success'] ? 'Đã nhận kết quả từ cổng thanh toán' : 'Cổng thanh toán chưa xác nhận' }}</p>
                                    <p class="mt-1 text-sm leading-6 text-neutral-600">Kết quả: {{ $data['response_message'] ?? 'Đang cập nhật kết quả thanh toán.' }}</p>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full
                                    @if($checkMeta['class'] === 'success') bg-emerald-100 text-emerald-700
                                    @elseif($checkMeta['class'] === 'failed') bg-red-100 text-red-700
                                    @else bg-amber-100 text-amber-700 @endif text-sm font-extrabold">2</span>
                                <div>
                                    <p class="text-sm font-extrabold text-neutral-950">{{ $checkMeta['label'] }}</p>
                                    <p class="mt-1 text-sm leading-6 text-neutral-600">{{ $checkMeta['text'] }}</p>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $invoice ? ($isSuccess ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700') : 'bg-neutral-200 text-neutral-600' }} text-sm font-extrabold">3</span>
                                <div>
                                    <p class="text-sm font-extrabold text-neutral-950">{{ $invoice ? 'Hóa đơn đã được tìm thấy' : 'Đang tìm hóa đơn' }}</p>
                                    <p class="mt-1 text-sm leading-6 text-neutral-600">{{ $invoice ? 'Trạng thái hiện tại: ' . $invoiceStatus . '.' : 'Nếu vừa thanh toán xong, hệ thống có thể cần thêm ít phút để cập nhật.' }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 rounded-2xl border border-blue-100 bg-white p-4 text-sm leading-6 text-neutral-600">
                            Quý khách vui lòng lưu lại mã giao dịch. Nếu cần hỗ trợ, gửi mã này cho bộ phận chăm sóc khách hàng để được kiểm tra nhanh hơn.
                        </div>
                    </aside>
                </section>
            </div>
        </section>
    </main>
</body>
</html>