<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tra cứu đơn hàng {{ $order?->id_bill ? '| '.$order->id_bill : '' }} | {{ config('system.name', 'Logistics') }}</title>
    <meta name="description" content="Tra cứu hành trình đơn hàng {{ config('system.name') }} - nhập mã vận đơn để xem trạng thái giao hàng mới nhất.">
    <link rel="icon" type="image/svg+xml" href="{{ asset(config('system.favicon') ?: 'favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Be Vietnam Pro', 'sans-serif'] },
                    colors: {
                        primary: {
                            50: '#ecfdf5',
                            100: '#d1fae5',
                            500: '{{ config('theme.primary.hex', '#0f766e') }}',
                            600: '{{ config('theme.primary.hex', '#0f766e') }}',
                            700: '{{ config('theme.primary.dark', '#115e59') }}',
                        },
                        ink: '#14211f',
                        paper: '#f6f4ed',
                    },
                    boxShadow: {
                        soft: '0 24px 70px rgba(20, 33, 31, 0.12)',
                        lift: '0 14px 34px rgba(20, 33, 31, 0.10)',
                    },
                },
            },
        };
    </script>
    <style>
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Be Vietnam Pro', sans-serif;
            font-variant-numeric: tabular-nums;
        }
        .tracking-shell {
            background:
                radial-gradient(circle at 12% 8%, rgba(15, 118, 110, 0.16), transparent 30rem),
                radial-gradient(circle at 86% 18%, rgba(180, 83, 9, 0.12), transparent 28rem),
                linear-gradient(135deg, #f7f5ee 0%, #edf4ef 52%, #f9faf7 100%);
        }
        .tracking-shell::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            opacity: 0.32;
            background-image:
                linear-gradient(rgba(20, 33, 31, 0.045) 1px, transparent 1px),
                linear-gradient(90deg, rgba(20, 33, 31, 0.04) 1px, transparent 1px);
            background-size: 42px 42px;
            mask-image: linear-gradient(to bottom, black, transparent 72%);
        }
    </style>
</head>
<body class="tracking-shell min-h-screen text-ink antialiased">
    <div class="relative min-h-screen overflow-hidden">
        <header class="relative z-10">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-5 sm:px-6 lg:px-8">
                <a href="{{ url('/') }}" class="group inline-flex min-w-0 items-center gap-3">
                    @if(config('system.logo'))
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-white/80 bg-white/70 p-2 shadow-lift backdrop-blur">
                            <img src="{{ asset(config('system.logo')) }}" alt="{{ config('system.name') }}" class="max-h-8 w-auto object-contain">
                        </span>
                    @else
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary-700 text-base font-bold text-white shadow-lift">
                            {{ mb_substr(config('system.short_name', config('system.name', 'L')), 0, 2) }}
                        </span>
                    @endif
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-bold tracking-tight text-ink">{{ config('system.name', 'Logistics') }}</span>
                        <span class="block text-xs font-semibold text-ink/55">Cổng tra cứu đơn hàng</span>
                    </span>
                </a>

                <div class="hidden items-center gap-2 rounded-2xl border border-white/80 bg-white/55 px-3 py-2 text-xs font-bold text-ink/65 shadow-sm backdrop-blur sm:flex">
                    <span class="h-2 w-2 rounded-full bg-primary-600"></span>
                    Cập nhật từ hệ thống vận hành
                </div>
            </div>
        </header>

        <main class="relative z-10 mx-auto max-w-6xl px-5 pb-12 pt-4 sm:px-6 lg:px-8">
            <section class="grid items-end gap-6 lg:grid-cols-[1.05fr_0.95fr]">
                <div class="pb-2 pt-6 sm:pt-12">
                    <div class="inline-flex items-center rounded-xl border border-primary-700/10 bg-white/55 px-3 py-1.5 text-xs font-bold text-primary-700 shadow-sm backdrop-blur">
                        Theo dõi vận đơn
                    </div>
                    <h1 class="mt-5 max-w-3xl text-4xl font-extrabold leading-tight tracking-tight text-ink sm:text-5xl lg:text-6xl">
                        Biết đơn hàng đang ở đâu, rõ ràng trong từng chặng.
                    </h1>
                    <p class="mt-5 max-w-2xl text-base font-medium leading-7 text-ink/62 sm:text-lg">
                        Nhập mã vận đơn hoặc tracking code để xem trạng thái mới nhất, điểm đến đã che thông tin nhạy cảm và lịch sử xử lý từ hệ thống.
                    </p>

                    <div class="mt-7 grid max-w-xl grid-cols-3 gap-3">
                        <div class="rounded-2xl border border-white/80 bg-white/58 p-4 shadow-sm backdrop-blur">
                            <div class="text-2xl font-bold text-ink">24/7</div>
                            <div class="mt-1 text-xs font-bold text-ink/52">Tự tra cứu</div>
                        </div>
                        <div class="rounded-2xl border border-white/80 bg-white/58 p-4 shadow-sm backdrop-blur">
                            <div class="text-2xl font-bold text-ink">ẩn</div>
                            <div class="mt-1 text-xs font-bold text-ink/52">Thông tin riêng tư</div>
                        </div>
                        <div class="rounded-2xl border border-white/80 bg-white/58 p-4 shadow-sm backdrop-blur">
                            <div class="text-2xl font-bold text-ink">live</div>
                            <div class="mt-1 text-xs font-bold text-ink/52">Theo trạng thái</div>
                        </div>
                    </div>
                </div>

                <form action="{{ route('tracking', ['idbill' => '__CODE__']) }}" method="get"
                    class="rounded-[1.75rem] border border-white/80 bg-white/72 p-3 shadow-soft backdrop-blur"
                    onsubmit="event.preventDefault(); const c = this.querySelector('input').value.trim(); if (c) window.location.href = this.action.replace('__CODE__', encodeURIComponent(c));">
                    <div class="rounded-[1.35rem] bg-white p-4 shadow-inner ring-1 ring-ink/5 sm:p-5">
                        <label for="tracking-code" class="text-xs font-bold uppercase tracking-[0.18em] text-ink/45">Mã vận đơn</label>
                        <div class="mt-3 flex flex-col gap-3 sm:flex-row">
                            <div class="relative flex-1">
                                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-ink/38">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h10m-8 4h12M6 15h7m3.5 4.5 3-3m0 0-3-3m3 3H15"/></svg>
                                </span>
                                <input id="tracking-code" type="text" inputmode="text" autocomplete="off"
                                    value="{{ $keyword }}"
                                    placeholder="VD: BEE260703001"
                                    class="h-14 w-full rounded-2xl border border-ink/10 bg-paper/70 pl-12 pr-4 text-base font-semibold tracking-wide text-ink outline-none transition duration-200 placeholder:text-ink/30 focus:border-primary-600 focus:bg-white focus:ring-4 focus:ring-primary-600/10">
                            </div>
                            <button type="submit"
                                class="inline-flex h-14 shrink-0 items-center justify-center gap-2 rounded-2xl bg-primary-700 px-6 text-sm font-bold text-white shadow-lift transition duration-200 hover:-translate-y-0.5 hover:bg-primary-600 focus:outline-none focus:ring-4 focus:ring-primary-600/20 active:translate-y-0">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.34-4.34M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/></svg>
                                Tra cứu
                            </button>
                        </div>
                        <p class="mt-3 text-sm font-semibold text-ink/48">Mã hợp lệ gồm chữ, số, dấu gạch ngang, gạch dưới hoặc dấu chấm.</p>
                    </div>
                </form>
            </section>

            @if($keyword !== '' && ! $order)
                <section class="mt-8 rounded-[1.5rem] border border-amber-200/80 bg-amber-50/80 p-5 shadow-lift backdrop-blur sm:p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-amber-700 ring-1 ring-amber-200">
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        </div>
                        <div class="min-w-0">
                            <div class="text-xl font-bold tracking-tight text-amber-950">Không tìm thấy đơn hàng</div>
                            <p class="mt-2 max-w-2xl text-sm font-semibold leading-6 text-amber-900/80">
                                Mã <span class="rounded-lg bg-white/70 px-1.5 py-0.5 font-mono font-bold text-amber-950">{{ $keyword }}</span> chưa có trong hệ thống. Vui lòng kiểm tra lại mã vận đơn hoặc liên hệ người gửi.
                            </p>
                        </div>
                    </div>
                </section>
            @elseif($order)
                <section class="mt-8 grid gap-6 lg:grid-cols-[0.92fr_1.08fr]">
                    <article class="overflow-hidden rounded-[1.75rem] border border-white/80 bg-white shadow-soft">
                        <div class="bg-[linear-gradient(135deg,#0f3d39,#0f766e_62%,#315e7c)] p-5 text-white sm:p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="text-xs font-bold uppercase tracking-[0.2em] text-white/55">Mã vận đơn</div>
                                <div class="mt-2 break-words font-mono text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $order->id_bill ?: 'ORDER-'.$order->id }}</div>
                                    @if($order->tracking_code)
                                        <div class="mt-2 inline-flex max-w-full items-center rounded-xl bg-white/12 px-3 py-1.5 text-xs font-bold text-white/78 ring-1 ring-white/16">
                                            Tracking: <span class="ml-1 truncate font-mono text-white">{{ $order->tracking_code }}</span>
                                        </div>
                                    @endif
                                </div>
                                <span class="inline-flex max-w-[12rem] shrink-0 items-center gap-1.5 rounded-2xl px-3 py-2 text-xs font-bold shadow-sm ring-1 ring-white/35 {{ $statusColor }}">
                                    @if($isDelivered)
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    @else
                                        <span class="h-2 w-2 rounded-full bg-current"></span>
                                    @endif
                                    <span class="truncate">{{ $statusLabel ?: 'Đang cập nhật' }}</span>
                                </span>
                            </div>
                        </div>

                        <div class="grid gap-3 p-4 sm:p-5">
                            <div class="rounded-2xl bg-paper/70 p-4 ring-1 ring-ink/5">
                                <div class="text-xs font-bold uppercase tracking-[0.16em] text-ink/38">Người nhận</div>
                                <div class="mt-2 text-base font-bold text-ink">{{ $receiverMasked['name'] }}</div>
                                <div class="mt-0.5 font-mono text-sm font-bold text-ink/54">{{ $receiverMasked['phone'] }}</div>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-2xl bg-white p-4 ring-1 ring-ink/6">
                                    <div class="text-xs font-bold uppercase tracking-[0.16em] text-ink/38">Điểm đến</div>
                                    <div class="mt-2 text-sm font-bold leading-5 text-ink">{{ $receiverMasked['destination'] }}</div>
                                </div>
                                <div class="rounded-2xl bg-white p-4 ring-1 ring-ink/6">
                                    <div class="text-xs font-bold uppercase tracking-[0.16em] text-ink/38">Ngày tạo đơn</div>
                                    <div class="mt-2 text-sm font-bold text-ink">{{ $order->created_at?->format('d/m/Y') ?? '-' }}</div>
                                    @if($order->ngaygiaodukien)
                                        <div class="mt-1 text-xs font-bold text-ink/48">Dự kiến: {{ $order->ngaygiaodukien->format('d/m/Y') }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="rounded-[1.75rem] border border-white/80 bg-white/82 p-5 shadow-soft backdrop-blur sm:p-6">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <div class="text-xs font-bold uppercase tracking-[0.2em] text-primary-700/70">Hành trình</div>
                                <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-ink">Cập nhật đơn hàng</h2>
                            </div>
                            <div class="rounded-2xl bg-primary-50 px-3 py-2 text-xs font-bold text-primary-700 ring-1 ring-primary-700/10">
                                {{ count($timeline) }} mốc
                            </div>
                        </div>

                        <div class="mt-6">
                            @forelse($timeline as $index => $event)
                                <div class="relative grid grid-cols-[1.35rem_1fr] gap-4 pb-6 {{ $loop->last ? 'pb-0' : '' }}">
                                    @unless($loop->last)
                                        <span class="absolute left-[0.66rem] top-7 h-full w-px {{ $index === 0 ? 'bg-primary-600/35' : 'bg-ink/10' }}"></span>
                                    @endunless
                                    <span class="relative mt-1 flex h-5 w-5 items-center justify-center rounded-full {{ $index === 0 ? 'bg-primary-700 text-white shadow-lift' : 'bg-paper text-ink/35 ring-1 ring-ink/10' }}">
                                        @if($index === 0)
                                            <span class="h-2 w-2 rounded-full bg-white"></span>
                                        @else
                                            <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                                        @endif
                                    </span>
                                    <div class="min-w-0 rounded-2xl {{ $index === 0 ? 'bg-primary-50 ring-primary-700/10' : 'bg-white ring-ink/6' }} p-4 ring-1">
                                        <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                                            <div class="text-base font-bold leading-5 {{ $index === 0 ? 'text-primary-700' : 'text-ink' }}">{{ $event['status'] ?: 'Cập nhật hành trình' }}</div>
                                            <div class="shrink-0 font-mono text-xs font-bold text-ink/42">{{ $event['time'] ?: '-' }}</div>
                                        </div>
                                        @if($event['location'])
                                            <div class="mt-2 flex items-start gap-2 text-sm font-semibold leading-5 text-ink/58">
                                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-primary-700/60" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-4.35 7-11a7 7 0 1 0-14 0c0 6.65 7 11 7 11Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5h.01"/></svg>
                                                <span>{{ $event['location'] }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-2xl border border-dashed border-ink/12 bg-paper/70 p-5 text-sm font-semibold leading-6 text-ink/56">
                                    Chưa có cập nhật hành trình cho đơn này. Khi hệ thống ghi nhận mốc mới, thông tin sẽ hiển thị tại đây.
                                </div>
                            @endforelse
                        </div>
                    </article>
                </section>
            @else
                <section class="mt-10 rounded-[1.75rem] border border-white/80 bg-white/70 p-6 text-center shadow-soft backdrop-blur sm:p-8">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-primary-50 text-primary-700 ring-1 ring-primary-700/10">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h1m8-1a1 1 0 0 1-1 1H9m4-1V8a1 1 0 0 1 1-1h2.586a1 1 0 0 1 .707.293l3.414 3.414a1 1 0 0 1 .293.707V16a1 1 0 0 1-1 1h-1m-6-1a1 1 0 0 0 1 1h1M5 17a2 2 0 1 0 4 0m-4 0a2 2 0 1 1 4 0m6 0a2 2 0 1 0 4 0m-4 0a2 2 0 1 1 4 0"/></svg>
                    </div>
                    <h2 class="mt-5 text-2xl font-extrabold tracking-tight text-ink">Tra cứu đơn hàng của bạn</h2>
                    <p class="mx-auto mt-2 max-w-lg text-sm font-semibold leading-6 text-ink/56">
                        Mã vận đơn thường nằm trên phiếu gửi hàng, email xác nhận hoặc tin nhắn thông báo từ người gửi.
                    </p>
                </section>
            @endif
        </main>

        <footer class="relative z-10 mx-auto max-w-6xl px-5 pb-8 pt-2 text-center text-xs font-semibold text-ink/40 sm:px-6 lg:px-8">
            &copy; {{ date('Y') }} {{ config('system.name', 'Logistics') }}@if(config('system.slogan')) - {{ config('system.slogan') }}@endif
        </footer>
    </div>
</body>
</html>
