<flux:modal name="order-index-filter" class="w-full max-w-5xl">
    <div class="space-y-5">
        <div>
            <flux:heading size="lg">Bộ lọc order</flux:heading>
            <flux:subheading>Lọc theo trạng thái, thời gian, nhân sự, khách hàng, dịch vụ và chi nhánh.</flux:subheading>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <flux:field>
                <flux:label>Từ ngày</flux:label>
                <flux:input type="date" data-order-filter="fromDate" value="{{ $filters['fromDate'] }}" />
            </flux:field>
            <flux:field>
                <flux:label>Đến ngày</flux:label>
                <flux:input type="date" data-order-filter="toDate" value="{{ $filters['toDate'] }}" />
            </flux:field>
            <flux:field>
                <flux:label>Trạng thái xử lý</flux:label>
                <select data-order-filter="status" class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm">
                    <option value="">Tất cả trạng thái</option>
                    @foreach ($statusOptions as $status)
                        <option value="{{ $status['value'] }}">{{ $status['label'] }}</option>
                    @endforeach
                </select>
            </flux:field>
            <flux:field>
                <flux:label>Sale phụ trách</flux:label>
                <select data-order-filter="saleId" class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm">
                    <option value="">Tất cả nhân sự</option>
                    @foreach ($sales as $sale)
                        <option value="{{ $sale['id'] }}">{{ $sale['label'] }}</option>
                    @endforeach
                </select>
            </flux:field>
            <flux:field>
                <flux:label>Khách hàng / CTV</flux:label>
                <select data-order-filter="customerId" class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm">
                    <option value="">Tất cả khách hàng</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer['id'] }}">{{ $customer['label'] }}</option>
                    @endforeach
                </select>
            </flux:field>
            <flux:field>
                <flux:label>Dịch vụ</flux:label>
                <select data-order-filter="serviceId" class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm">
                    <option value="">Tất cả dịch vụ</option>
                    @foreach ($services as $service)
                        <option value="{{ $service['id'] }}">{{ $service['label'] }}</option>
                    @endforeach
                </select>
            </flux:field>
            <flux:field>
                <flux:label>Chi nhánh nhận hàng</flux:label>
                <select data-order-filter="branchId" class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm">
                    <option value="">Tất cả chi nhánh</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch['id'] }}">{{ $branch['label'] }}</option>
                    @endforeach
                </select>
            </flux:field>
        </div>

        <div class="flex justify-end gap-2">
            <flux:button type="button" id="orders-reset-filter" variant="ghost">Làm mới</flux:button>
            <flux:modal.close>
                <flux:button type="button" id="orders-apply-filter" variant="primary">Áp dụng</flux:button>
            </flux:modal.close>
        </div>
    </div>
</flux:modal>
