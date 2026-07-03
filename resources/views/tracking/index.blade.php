<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tra cứu đơn hàng {{ $order?->id_bill ? '— '.$order->id_bill : '' }} | {{ config('system.name', 'Logistics') }}</title>
    <meta name="description" content="Tra cứu hành trình đơn hàng {{ config('system.name') }} — nhập mã vận đơn để xem trạng thái giao hàng mới nhất.">
    <link rel="icon" type="image/svg+xml" href="{{ asset(config('system.favicon') ?: 'favicon.svg') }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        primary: {
                            50: '#eff6ff', 100: '#dbeafe', 500: '{{ config('theme.primary.hex', '#3b82f6') }}',
                            600: '{{ config('theme.primary.hex', '#3b82f6') }}', 700: '{{ config('theme.primary.dark', '#2563eb') }}',
                        },
                    },
                },
            },
        };
    </script>
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-screen bg-neutral-50">
    <header class="border-b border-neutral-200 bg-white">
        <div class="mx-auto flex max-w-3xl items-center gap-3 px-4 py-4">
            @if(config('system.logo'))
                <img src="{{ asset(config('system.logo')) }}" alt="{{ config('system.name') }}" class="h-9 w-auto object-contain">
            @else
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary-600 text-sm font-extrabold text-white">
                    {{ mb_substr(config('system.short_name', config('system.name', 'L')), 0, 2) }}
                </div>
            @endif
            <div>
                <div class="text-sm font-extrabold text-neutral-900">{{ config('system.name', 'Logistics') }}</div>
                <div class="text-xs text-neutral-500">Tra cứu hành trình đơn hàng</div>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-4 py-8">
        {{-- Form tra cứu — luôn hiển thị để tra mã khác --}}
        <form action="{{ route('tracking', ['idbill' => '__CODE__']) }}" method="get" class="flex gap-2"
            onsubmit="event.preventDefault(); const c = this.querySelector('input').value.trim(); if (c) window.location.href = this.action.replace('__CODE__', encodeURIComponent(c));">
            <input type="text" inputmode="text" autocomplete="off"
                value="{{ $keyword }}"
                placeholder="Nhập mã vận đơn, ví dụ: BEE260703001"
                class="h-12 flex-1 rounded-xl border border-neutral-300 bg-white px-4 text-sm font-semibold tracking-wide text-neutral-900 outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-100">
            <button type="submit"
                class="inline-flex h-12 items-center gap-2 rounded-xl bg-primary-600 px-5 text-sm font-bold text-white transition hover:bg-primary-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.34-4.34M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/></svg>
                Tra cứu
            </button>
        </form>

        @if($keyword !== '' && ! $order)
            {{-- Không tìm thấy --}}
            <div class="mt-8 rounded-2xl border border-amber-200 bg-amber-50 p-6 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100">
                    <svg class="h-6 w-6 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </div>
                <div class="mt-3 text-base font-bold text-amber-900">Không tìm thấy đơn hàng</div>
                <p class="mt-1 text-sm text-amber-800">
                    Mã <span class="font-mono font-bold">{{ $keyword }}</span> không tồn tại trong hệ thống.
                    Vui lòng kiểm tra lại mã vận đơn hoặc liên hệ người gửi.
                </p>
            </div>
        @elseif($order)
            {{-- Thông tin đơn --}}
            <div class="mt-8 overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-neutral-100 px-5 py-4">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Mã vận đơn</div>
                        <div class="text-xl font-extrabold tracking-wide text-neutral-900">{{ $order->id_bill ?: 'ORDER-'.$order->id }}</div>
                        @if($order->tracking_code)
                            <div class="mt-0.5 text-xs text-neutral-500">Tracking: <span class="font-semibold">{{ $order->tracking_code }}</span></div>
                        @endif
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-bold {{ $statusColor }}">
                        @if($isDelivered)
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        @endif
                        {{ $statusLabel }}
                    </span>
                </div>

                <div class="grid gap-4 px-5 py-4 sm:grid-cols-3">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Người nhận</div>
                        <div class="mt-1 text-sm font-bold text-neutral-900">{{ $receiverMasked['name'] }}</div>
                        <div class="text-xs text-neutral-500">{{ $receiverMasked['phone'] }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Điểm đến</div>
                        <div class="mt-1 text-sm font-bold text-neutral-900">{{ $receiverMasked['destination'] }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Ngày tạo đơn</div>
                        <div class="mt-1 text-sm font-bold text-neutral-900">{{ $order->created_at?->format('d/m/Y') ?? '-' }}</div>
                        @if($order->ngaygiaodukien)
                            <div class="text-xs text-neutral-500">Dự kiến giao: {{ $order->ngaygiaodukien->format('d/m/Y') }}</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Timeline hành trình --}}
            <div class="mt-6 rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
                <div class="mb-4 text-sm font-extrabold uppercase tracking-wide text-neutral-700">Hành trình đơn hàng</div>

                @forelse($timeline as $index => $event)
                    <div class="relative flex gap-4 pb-6 {{ $loop->last ? 'pb-0' : '' }}">
                        @unless($loop->last)
                            <span class="absolute left-[7px] top-5 h-full w-0.5 {{ $index === 0 ? 'bg-primary-200' : 'bg-neutral-200' }}"></span>
                        @endunless
                        <span class="relative mt-1 flex h-4 w-4 shrink-0 items-center justify-center">
                            <span class="h-4 w-4 rounded-full border-2 {{ $index === 0 ? 'border-primary-600 bg-primary-100' : 'border-neutral-300 bg-white' }}"></span>
                            @if($index === 0)
                                <span class="absolute h-2 w-2 rounded-full bg-primary-600"></span>
                            @endif
                        </span>
                        <div class="min-w-0">
                            <div class="text-sm font-bold {{ $index === 0 ? 'text-primary-700' : 'text-neutral-800' }}">{{ $event['status'] ?: 'Cập nhật hành trình' }}</div>
                            @if($event['location'])
                                <div class="mt-0.5 text-sm text-neutral-600">{{ $event['location'] }}</div>
                            @endif
                            <div class="mt-0.5 text-xs text-neutral-400">{{ $event['time'] ?: '-' }}</div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-neutral-500">Chưa có cập nhật hành trình cho đơn này.</p>
                @endforelse
            </div>
        @else
            {{-- Chưa nhập mã --}}
            <div class="mt-12 text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-primary-50">
                    <svg class="h-8 w-8 text-primary-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h1m8-1a1 1 0 0 1-1 1H9m4-1V8a1 1 0 0 1 1-1h2.586a1 1 0 0 1 .707.293l3.414 3.414a1 1 0 0 1 .293.707V16a1 1 0 0 1-1 1h-1m-6-1a1 1 0 0 0 1 1h1M5 17a2 2 0 1 0 4 0m-4 0a2 2 0 1 1 4 0m6 0a2 2 0 1 0 4 0m-4 0a2 2 0 1 1 4 0"/></svg>
                </div>
                <h1 class="mt-4 text-lg font-extrabold text-neutral-900">Tra cứu đơn hàng của bạn</h1>
                <p class="mx-auto mt-1 max-w-md text-sm text-neutral-500">
                    Nhập mã vận đơn được cung cấp khi gửi hàng để xem trạng thái và hành trình giao hàng mới nhất.
                </p>
            </div>
        @endif
    </main>

    <footer class="mx-auto max-w-3xl px-4 pb-8 pt-4 text-center text-xs text-neutral-400">
        &copy; {{ date('Y') }} {{ config('system.name', 'Logistics') }} — {{ config('system.slogan', '') }}
    </footer>
</body>
</html>
