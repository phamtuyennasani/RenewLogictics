<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tra cước vận chuyển | {{ config('system.name', 'Logistics') }}</title>
    <meta name="description" content="Ước tính cước vận chuyển quốc tế {{ config('system.name') }} - chọn dịch vụ, quốc gia đến và cân nặng để nhận giá tham khảo ngay.">
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
        .quote-shell {
            background:
                radial-gradient(circle at 12% 8%, rgba(15, 118, 110, 0.16), transparent 30rem),
                radial-gradient(circle at 86% 18%, rgba(180, 83, 9, 0.12), transparent 28rem),
                linear-gradient(135deg, #f7f5ee 0%, #edf4ef 52%, #f9faf7 100%);
        }
        .quote-shell::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            opacity: 0.3;
            background-image:
                linear-gradient(rgba(20, 33, 31, 0.045) 1px, transparent 1px),
                linear-gradient(90deg, rgba(20, 33, 31, 0.04) 1px, transparent 1px);
            background-size: 42px 42px;
            mask-image: linear-gradient(to bottom, black, transparent 74%);
        }
    </style>
</head>
<body class="quote-shell min-h-screen text-ink antialiased">
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
                        <span class="block text-xs font-semibold text-ink/55">Cổng tính cước vận chuyển</span>
                    </span>
                </a>

                <a href="{{ route('tracking') }}" class="hidden rounded-2xl border border-white/80 bg-white/55 px-3 py-2 text-xs font-bold text-ink/65 shadow-sm backdrop-blur transition duration-200 hover:-translate-y-0.5 hover:bg-white sm:inline-flex">
                    Tra cứu hành trình
                </a>
            </div>
        </header>

        <main class="relative z-10 mx-auto max-w-6xl px-5 pb-12 pt-4 sm:px-6 lg:px-8">
            <section class="grid gap-6 lg:grid-cols-[0.92fr_1.08fr] lg:items-start">
                <div class="pb-2 pt-6 sm:pt-12">
                    <div class="inline-flex items-center rounded-xl border border-primary-700/10 bg-white/55 px-3 py-1.5 text-xs font-bold text-primary-700 shadow-sm backdrop-blur">
                        Công cụ tính cước
                    </div>
                    <h1 class="mt-5 max-w-3xl text-4xl font-extrabold leading-tight tracking-tight text-ink sm:text-5xl lg:text-6xl">
                        Ước tính cước vận chuyển trước khi tạo đơn.
                    </h1>
                    <p class="mt-5 max-w-2xl text-base font-medium leading-7 text-ink/62 sm:text-lg">
                        Chọn dịch vụ, quốc gia đến và nhập thông tin kiện hàng để nhận mức giá tham khảo theo bảng giá đang áp dụng.
                    </p>

                    <div class="mt-7 grid max-w-xl grid-cols-3 gap-3">
                        <div class="rounded-2xl border border-white/80 bg-white/58 p-4 shadow-sm backdrop-blur">
                            <div class="text-2xl font-bold text-ink">1</div>
                            <div class="mt-1 text-xs font-bold text-ink/52">Kiện mẫu</div>
                        </div>
                        <div class="rounded-2xl border border-white/80 bg-white/58 p-4 shadow-sm backdrop-blur">
                            <div class="text-2xl font-bold text-ink">{{ number_format($dim, 0) }}</div>
                            <div class="mt-1 text-xs font-bold text-ink/52">Hệ số DIM</div>
                        </div>
                        <div class="rounded-2xl border border-white/80 bg-white/58 p-4 shadow-sm backdrop-blur">
                            <div class="text-2xl font-bold text-ink">net</div>
                            <div class="mt-1 text-xs font-bold text-ink/52">Chỉ hiện cước bán</div>
                        </div>
                    </div>
                </div>

                <section class="rounded-[1.75rem] border border-white/80 bg-white/72 p-3 shadow-soft backdrop-blur">
                    <div class="rounded-[1.35rem] bg-white p-4 shadow-inner ring-1 ring-ink/5 sm:p-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="text-xs font-bold uppercase tracking-[0.18em] text-ink/42">Thông tin tính cước</div>
                                <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-ink">Nhập tuyến và kiện hàng</h2>
                            </div>
                            <div class="inline-flex w-max items-center rounded-xl bg-primary-50 px-3 py-2 text-xs font-bold text-primary-700 ring-1 ring-primary-700/10">
                                Giá tham khảo
                            </div>
                        </div>

                        <form method="get" action="{{ route('quote') }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="service_id" class="mb-1.5 block text-xs font-bold uppercase tracking-[0.16em] text-ink/42">Dịch vụ</label>
                                <select id="service_id" name="service_id" required
                                    onchange="this.form.querySelector('[name=country_id]').value=''; this.form.submit()"
                                    class="h-14 min-h-14 w-full rounded-2xl border border-ink/10 bg-paper/70 px-4 py-3 text-sm font-semibold text-ink outline-none transition duration-200 focus:border-primary-600 focus:bg-white focus:ring-4 focus:ring-primary-600/10">
                                    <option value="">Chọn dịch vụ</option>
                                    @foreach($services as $service)
                                        <option value="{{ $service->id }}" @selected($input['service_id'] === $service->id)>{{ $service->namevi }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="country_id" class="mb-1.5 block text-xs font-bold uppercase tracking-[0.16em] text-ink/42">Quốc gia đến</label>
                                <select id="country_id" name="country_id" required
                                    class="h-14 min-h-14 w-full rounded-2xl border border-ink/10 bg-paper/70 px-4 py-3 text-sm font-semibold text-ink outline-none transition duration-200 focus:border-primary-600 focus:bg-white focus:ring-4 focus:ring-primary-600/10">
                                    <option value="">Chọn quốc gia</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->id }}" @selected($input['country_id'] === $country->id)>{{ $country->name }}</option>
                                    @endforeach
                                </select>
                                @if($input['service_id'] && $countries->isEmpty())
                                    <p class="mt-2 text-xs font-bold text-amber-700">Dịch vụ này chưa có quốc gia áp dụng.</p>
                                @endif
                            </div>

                            <div>
                                <label for="g_weight" class="mb-1.5 block text-xs font-bold uppercase tracking-[0.16em] text-ink/42">Cân nặng thực</label>
                                <div class="relative">
                                    <input id="g_weight" type="number" name="g_weight" value="{{ $input['g_weight'] }}" step="0.1" min="0.01" required placeholder="VD: 2.5"
                                        class="h-14 min-h-14 w-full rounded-2xl border border-ink/10 bg-paper/70 px-4 py-3 pr-12 text-sm font-semibold text-ink outline-none transition duration-200 placeholder:text-ink/30 focus:border-primary-600 focus:bg-white focus:ring-4 focus:ring-primary-600/10">
                                    <span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-ink/38">kg</span>
                                </div>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-xs font-bold uppercase tracking-[0.16em] text-ink/42">Kích thước</label>
                                <div class="grid grid-cols-3 gap-2">
                                    <input type="number" name="length" value="{{ $input['length'] }}" step="1" min="0" placeholder="Dài"
                                        class="h-14 min-h-14 w-full rounded-2xl border border-ink/10 bg-paper/70 px-3 py-3 text-sm font-semibold text-ink outline-none transition duration-200 placeholder:text-ink/30 focus:border-primary-600 focus:bg-white focus:ring-4 focus:ring-primary-600/10">
                                    <input type="number" name="width" value="{{ $input['width'] }}" step="1" min="0" placeholder="Rộng"
                                        class="h-14 min-h-14 w-full rounded-2xl border border-ink/10 bg-paper/70 px-3 py-3 text-sm font-semibold text-ink outline-none transition duration-200 placeholder:text-ink/30 focus:border-primary-600 focus:bg-white focus:ring-4 focus:ring-primary-600/10">
                                    <input type="number" name="height" value="{{ $input['height'] }}" step="1" min="0" placeholder="Cao"
                                        class="h-14 min-h-14 w-full rounded-2xl border border-ink/10 bg-paper/70 px-3 py-3 text-sm font-semibold text-ink outline-none transition duration-200 placeholder:text-ink/30 focus:border-primary-600 focus:bg-white focus:ring-4 focus:ring-primary-600/10">
                                </div>
                                <p class="mt-2 text-xs font-semibold text-ink/45">Đơn vị cm, có thể bỏ trống nếu chưa có số đo.</p>
                            </div>

                            <div class="sm:col-span-2">
                                <button type="submit"
                                    class="inline-flex h-14 w-full items-center justify-center gap-2 rounded-2xl bg-primary-700 px-6 text-sm font-bold text-white shadow-lift transition duration-200 hover:-translate-y-0.5 hover:bg-primary-600 focus:outline-none focus:ring-4 focus:ring-primary-600/20 active:translate-y-0 sm:w-auto">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-2 4h2M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/></svg>
                                    Tính cước
                                </button>
                            </div>
                        </form>

                        @if($errors->any())
                            <div class="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">
                                <div class="font-bold">Vui lòng kiểm tra lại thông tin</div>
                                <ul class="mt-1 list-inside list-disc">
                                    @foreach($errors->all() as $message)
                                        <li>{{ $message }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if($error)
                            <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold leading-6 text-amber-900">
                                {{ $error }}
                            </div>
                        @endif
                    </div>
                </section>
            </section>

            <section class="mt-8 grid gap-6 lg:grid-cols-[1.08fr_0.92fr]">
                @if($quote)
                    <article class="overflow-hidden rounded-[1.75rem] border border-white/80 bg-white shadow-soft">
                        <div class="bg-[linear-gradient(135deg,#0f3d39,#0f766e_62%,#315e7c)] p-5 text-white sm:p-6">
                            <div class="text-xs font-bold uppercase tracking-[0.2em] text-white/55">Cước vận chuyển tham khảo</div>
                            <div class="mt-2 flex flex-wrap items-end gap-x-3 gap-y-1">
                                <div class="font-mono text-4xl font-extrabold leading-none tracking-tight sm:text-5xl">{{ number_format($quote['sale_price'], 0, ',', '.') }}</div>
                                <div class="pb-1 text-xl font-bold text-white/78">đ</div>
                            </div>
                            <p class="mt-3 max-w-xl text-sm font-semibold leading-6 text-white/70">
                                Giá này chỉ dùng để tham khảo nhanh trước khi tạo đơn. Phí chính thức có thể thay đổi theo hàng hóa và phụ phí thực tế.
                            </p>
                        </div>

                        <div class="grid gap-3 p-4 sm:grid-cols-3 sm:p-5">
                            <div class="rounded-2xl bg-paper/70 p-4 ring-1 ring-ink/5">
                                <div class="text-xs font-bold uppercase tracking-[0.16em] text-ink/38">Cân tính cước</div>
                                <div class="mt-2 font-mono text-2xl font-bold text-ink">{{ rtrim(rtrim(number_format($quote['chargeable_weight'], 2, '.', ''), '0'), '.') }} kg</div>
                            </div>
                            <div class="rounded-2xl bg-white p-4 ring-1 ring-ink/6 sm:col-span-2">
                                <div class="text-xs font-bold uppercase tracking-[0.16em] text-ink/38">Quy cách giá</div>
                                @if($quote['quycach'] === 'DON_GIA')
                                    <div class="mt-2 text-lg font-bold text-ink">{{ number_format($quote['unit_price'], 0, ',', '.') }} đ/kg</div>
                                    <div class="mt-1 text-xs font-bold text-ink/48">Tính theo đơn giá nhân với cân tính cước.</div>
                                @else
                                    <div class="mt-2 text-lg font-bold text-ink">Giá cố định theo khoảng cân</div>
                                    <div class="mt-1 text-xs font-bold text-ink/48">Áp dụng mức giá của khoảng cân phù hợp.</div>
                                @endif
                            </div>
                        </div>
                    </article>
                @else
                    <article class="rounded-[1.75rem] border border-white/80 bg-white/70 p-6 shadow-soft backdrop-blur sm:p-8">
                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary-50 text-primary-700 ring-1 ring-primary-700/10">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M6 11h12M8 15h8M5 19h14"/></svg>
                        </div>
                    <h2 class="mt-5 text-2xl font-extrabold tracking-tight text-ink">Kết quả sẽ hiện tại đây</h2>
                        <p class="mt-2 max-w-lg text-sm font-semibold leading-6 text-ink/56">
                            Sau khi chọn tuyến và nhập cân nặng, hệ thống sẽ trả về cước bán tham khảo cùng cân tính cước.
                        </p>
                    </article>
                @endif

                <aside class="rounded-[1.75rem] border border-white/80 bg-white/72 p-5 shadow-soft backdrop-blur sm:p-6">
                    <div class="text-xs font-bold uppercase tracking-[0.2em] text-primary-700/70">Cách tính</div>
                    <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-ink">Lưu ý trước khi gửi hàng</h2>
                    <div class="mt-5 space-y-3">
                        <div class="rounded-2xl bg-white p-4 ring-1 ring-ink/6">
                            <div class="text-sm font-bold text-ink">Cân tính cước</div>
                            <p class="mt-1 text-sm font-semibold leading-6 text-ink/56">Lấy mức cao hơn giữa cân thực và cân thể tích theo công thức D x R x C / {{ number_format($dim, 0) }}.</p>
                        </div>
                        <div class="rounded-2xl bg-white p-4 ring-1 ring-ink/6">
                            <div class="text-sm font-bold text-ink">Chỉ là giá tham khảo</div>
                            <p class="mt-1 text-sm font-semibold leading-6 text-ink/56">Chưa bao gồm VAT, phụ phí hải quan, phụ phí xăng dầu hoặc phí phát sinh theo tính chất hàng hóa.</p>
                        </div>
                        <div class="rounded-2xl bg-white p-4 ring-1 ring-ink/6">
                            <div class="text-sm font-bold text-ink">Báo giá chính thức</div>
                            <p class="mt-1 text-sm font-semibold leading-6 text-ink/56">Liên hệ nhân viên phụ trách để xác nhận tuyến, loại hàng và điều kiện vận chuyển trước khi gửi.</p>
                        </div>
                    </div>
                </aside>
            </section>
        </main>

        <footer class="relative z-10 mx-auto max-w-6xl px-5 pb-8 pt-2 text-center text-xs font-semibold text-ink/40 sm:px-6 lg:px-8">
            &copy; {{ date('Y') }} {{ config('system.name', 'Logistics') }}@if(config('system.slogan')) - {{ config('system.slogan') }}@endif
        </footer>
    </div>
</body>
</html>
