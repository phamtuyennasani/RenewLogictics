<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tra cước vận chuyển | {{ config('system.name', 'Logistics') }}</title>
    <meta name="description" content="Ước tính cước vận chuyển quốc tế {{ config('system.name') }} — chọn dịch vụ, quốc gia đến và cân nặng để nhận giá tham khảo ngay.">
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
                <div class="text-xs text-neutral-500">Tra cước vận chuyển</div>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-4 py-8">
        <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
            <h1 class="text-base font-extrabold text-neutral-900">Ước tính cước vận chuyển</h1>
            <p class="mt-1 text-sm text-neutral-500">Chọn dịch vụ, quốc gia đến và nhập thông tin kiện hàng để nhận giá tham khảo.</p>

            <form method="get" action="{{ route('quote') }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-neutral-500">Dịch vụ</label>
                    <select name="service_id" required
                        onchange="this.form.querySelector('[name=country_id]').value=''; this.form.submit()"
                        class="h-11 w-full rounded-xl border border-neutral-300 bg-white px-3 text-sm font-semibold text-neutral-900 outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100">
                        <option value="">— Chọn dịch vụ —</option>
                        @foreach($services as $service)
                            <option value="{{ $service->id }}" @selected($input['service_id'] === $service->id)>{{ $service->namevi }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-neutral-500">Quốc gia đến</label>
                    <select name="country_id" required
                        class="h-11 w-full rounded-xl border border-neutral-300 bg-white px-3 text-sm font-semibold text-neutral-900 outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100">
                        <option value="">— Chọn quốc gia —</option>
                        @foreach($countries as $country)
                            <option value="{{ $country->id }}" @selected($input['country_id'] === $country->id)>{{ $country->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-neutral-500">Cân nặng thực (kg)</label>
                    <input type="number" name="g_weight" value="{{ $input['g_weight'] }}" step="0.1" min="0.01" required placeholder="VD: 2.5"
                        class="h-11 w-full rounded-xl border border-neutral-300 bg-white px-3 text-sm font-semibold text-neutral-900 outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-neutral-500">Kích thước D × R × C (cm) <span class="font-normal normal-case text-neutral-400">— không bắt buộc</span></label>
                    <div class="flex gap-2">
                        <input type="number" name="length" value="{{ $input['length'] }}" step="1" min="0" placeholder="Dài"
                            class="h-11 w-full rounded-xl border border-neutral-300 bg-white px-3 text-sm font-semibold outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100">
                        <input type="number" name="width" value="{{ $input['width'] }}" step="1" min="0" placeholder="Rộng"
                            class="h-11 w-full rounded-xl border border-neutral-300 bg-white px-3 text-sm font-semibold outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100">
                        <input type="number" name="height" value="{{ $input['height'] }}" step="1" min="0" placeholder="Cao"
                            class="h-11 w-full rounded-xl border border-neutral-300 bg-white px-3 text-sm font-semibold outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100">
                    </div>
                </div>

                <div class="sm:col-span-2">
                    <button type="submit"
                        class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary-600 text-sm font-bold text-white transition hover:bg-primary-700 sm:w-auto sm:px-8">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-2 4h2M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/></svg>
                        Tính cước
                    </button>
                </div>
            </form>

            @error('g_weight')
                <p class="mt-3 text-sm font-semibold text-red-600">{{ $message }}</p>
            @enderror

            @if($error)
                <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
                    {{ $error }}
                </div>
            @endif

            @if($quote)
                <div class="mt-5 rounded-2xl border-2 border-primary-100 bg-primary-50 p-5">
                    <div class="text-xs font-bold uppercase tracking-wide text-primary-700">Cước vận chuyển tham khảo</div>
                    <div class="mt-1 text-3xl font-extrabold text-primary-700">{{ number_format($quote['sale_price'], 0, ',', '.') }} đ</div>
                    <div class="mt-3 grid gap-2 text-sm text-neutral-700 sm:grid-cols-2">
                        <div>Cân tính cước: <b>{{ rtrim(rtrim(number_format($quote['chargeable_weight'], 2, '.', ''), '0'), '.') }} kg</b></div>
                        <div>
                            @if($quote['quycach'] === 'DON_GIA')
                                Đơn giá: <b>{{ number_format($quote['unit_price'], 0, ',', '.') }} đ/kg</b>
                            @else
                                Giá cố định theo khoảng cân
                            @endif
                        </div>
                    </div>
                </div>
                <p class="mt-3 text-xs leading-relaxed text-neutral-500">
                    * Giá trên là <b>cước tham khảo</b>, chưa bao gồm phụ phí (hải quan, xăng dầu...), VAT và có thể
                    thay đổi theo tính chất hàng hóa thực tế. Cân tính cước lấy theo mức cao hơn giữa cân thực và cân
                    thể tích (D×R×C/{{ number_format($dim, 0) }}). Vui lòng liên hệ để nhận báo giá chính thức.
                </p>
            @endif
        </div>
    </main>

    <footer class="mx-auto max-w-3xl px-4 pb-8 pt-4 text-center text-xs text-neutral-400">
        &copy; {{ date('Y') }} {{ config('system.name', 'Logistics') }} — {{ config('system.slogan', '') }}
    </footer>
</body>
</html>
