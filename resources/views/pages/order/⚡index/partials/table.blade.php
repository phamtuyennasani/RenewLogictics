<div class="order-table-card space-y-3 rounded-lg border border-neutral-200 bg-white p-3">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <flux:input id="orders-search" type="search" placeholder="Tìm mã AWB, REF, khách hàng, sale..." class="lg:max-w-md" />
        <div class="flex items-center gap-2 text-sm text-neutral-600">
            <span>Hiển thị</span>
            <select id="orders-page-size" class="rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm">
                @foreach ($pageSizes as $size)
                    <option value="{{ $size }}" @selected($size === 25)>{{ $size }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="order-table-frame overflow-hidden">
        <table id="orders-table" class="w-full text-left text-sm">
            @php $isSaleUser = $capabilities['isSaleUser'] ?? false; @endphp
            <colgroup>
                @if ($isSaleUser)
                    <col style="width: 180px;">
                    <col style="width: 150px;">
                    <col style="width: 170px;">
                    <col style="width: 250px;">
                    <col style="width: 250px;">
                    <col style="width: 280px;">
                    <col style="width: 150px;">
                    <col style="width: 150px;">
                    <col style="width: 100px;">
                    <col style="width: 100px;">
                    <col style="width: 130px;">
                    <col style="width: 130px;">
                    <col style="width: 100px;">
                    <col style="width: 100px;">
                @else
                    <col style="width: 52px;">
                    <col style="width: 180px;">
                    <col style="width: 150px;">
                    <col style="width: 170px;">
                    <col style="width: 190px;">
                    <col style="width: 280px;">
                    <col style="width: 280px;">
                    <col style="width: 320px;">
                    <col style="width: 240px;">
                    <col style="width: 140px;">
                    <col style="width: 150px;">
                    <col style="width: 100px;">
                    <col style="width: 110px;">
                    <col style="width: 130px;">
                    <col style="width: 130px;">
                    <col style="width: 200px;">
                    <col style="width: 200px;">
                    <col style="width: auto;">
                @endif
            </colgroup>
            <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                <tr data-dt-order="disable">
                    @if (! $isSaleUser)
                    <th class="w-12 px-4 py-3 text-center">
                        <label class="order-checkbox relative mx-auto flex w-fit cursor-pointer select-none items-center justify-center">
                            <input id="orders-check-all" type="checkbox" class="peer sr-only">
                            <span class="flex h-[18px] w-[18px] items-center justify-center rounded-md border border-neutral-300 bg-white transition peer-checked:border-primary-600 peer-checked:bg-primary-600 peer-hover:border-primary-400"></span>
                            <svg class="pointer-events-none absolute hidden h-3 w-3 text-white peer-checked:block" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.5l5 5 10-11" />
                            </svg>
                        </label>
                    </th>
                    @endif
                    <th class="px-3 py-3">Mã AWB / REF</th>
                    <th class="px-3 py-3">Trạng thái</th>
                    <th class="px-3 py-3">Ngày tạo / xuất / giao</th>
                    <th class="px-3 py-3">Khách hàng</th>
                    <th class="px-3 py-3">Người gửi</th>
                    <th class="px-3 py-3">Người nhận</th>
                    <th class="px-3 py-3">Địa chỉ người nhận</th>
                    <th class="px-3 py-3">Dịch vụ</th>
                    <th class="px-3 py-3">Quốc gia</th>
                    @if (! $isSaleUser)
                        <th class="px-3 py-3">Đại lý</th>
                    @endif
                    <th class="px-3 py-3 text-right">Kiện hàng</th>
                    <th class="px-3 py-3">Cước bán</th>
                    @if ($isSaleUser)
                        <th class="px-3 py-3">Hoa hồng sale</th>
                    @else
                        <th class="px-3 py-3">Cước vốn</th>
                        <th class="px-3 py-3">Lợi nhuận</th>
                    @endif
                    <th class="px-3 py-3">Khách hàng thanh toán</th>
                    @if (! $isSaleUser)
                        <th class="px-3 py-3">Thanh toán đại lý</th>
                    @endif
                    <th class="px-3 py-3 text-right">Thao tác</th>
                </tr>
            </thead>
        </table>
    </div>
</div>
