<flux:modal name="order-index-filter" class="w-full max-w-7xl !overflow-visible">
    <div class="order-filter-panel">
        <div class="order-filter-header">
            <div class="order-filter-title-row">
                <div class="order-filter-icon">
                    <flux:icon.funnel class="size-5" />
                </div>
                <div>
                    <flux:heading size="lg">Bộ lọc order</flux:heading>
                    <flux:subheading>Lọc theo trạng thái, thời gian, nhân sự, khách hàng, dịch vụ và chi nhánh.</flux:subheading>
                </div>
            </div>
        </div>

        <div class="order-filter-content">
            <section class="order-filter-section order-filter-section-time">
                <div class="order-filter-section-heading">
                    <div>
                        <h3>Thời gian</h3>
                        <p>Khoảng ngày tạo order</p>
                    </div>
                    <div class="order-filter-presets">
                        <button type="button" data-order-date-preset="today">Hôm nay</button>
                        <button type="button" data-order-date-preset="7">7 ngày</button>
                        <button type="button" data-order-date-preset="30">30 ngày</button>
                    </div>
                </div>
                <div class="order-filter-section-grid order-filter-section-grid-2">
                    <div class="order-filter-field">
                        <label class="order-filter-label">Từ ngày</label>
                        <div class="order-date-picker-field">
                            <input type="text" data-order-filter="fromDate" data-order-date-picker value="{{ $filters['fromDate'] }}" autocomplete="off" class="order-filter-control">
                        </div>
                    </div>
                    <div class="order-filter-field">
                        <label class="order-filter-label">Đến ngày</label>
                        <div class="order-date-picker-field">
                            <input type="text" data-order-filter="toDate" data-order-date-picker value="{{ $filters['toDate'] }}" autocomplete="off" class="order-filter-control">
                        </div>
                    </div>
                </div>
            </section>

            <section class="order-filter-section">
                <div class="order-filter-section-heading">
                    <div>
                        <h3>{{ $capabilities['isSaleUser'] ? 'Khách hàng / CTV' : 'Phụ trách' }}</h3>
                        <p>{{ $capabilities['isSaleUser'] ? 'Lọc theo khách hàng / CTV của bạn' : 'Sale và khách hàng / CTV' }}</p>
                    </div>
                </div>
                <div class="order-filter-section-grid{{ !$capabilities['isSaleUser'] ? ' order-filter-section-grid-2' : '' }}">
                    @if (!$capabilities['isSaleUser'])
                    <div class="order-filter-field">
                        <label class="order-filter-label">Sale phụ trách</label>
                        <select data-order-filter="saleId" data-placeholder="Tất cả nhân sự" class="tomselectEml order-filter-tomselect">
                            <option value="">Tất cả nhân sự</option>
                            @foreach ($sales as $sale)
                                <option value="{{ $sale['id'] }}" {{ (string) $sale['id'] === ($filters['saleId'] ?? '') ? 'selected' : '' }}>{{ $sale['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    @if ($capabilities['isSaleUser'])
                    <input type="hidden" data-order-filter="saleId" value="{{ $filters['saleId'] ?? '' }}">
                    @endif
                    <div class="order-filter-field">
                        <label class="order-filter-label">Khách hàng / CTV</label>
                        <select data-order-filter="customerId" data-placeholder="Tất cả khách hàng" class="tomselectEml order-filter-tomselect">
                            <option value="">Tất cả khách hàng</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer['id'] }}">{{ $customer['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </section>

            <section class="order-filter-section order-filter-section-wide">
                <div class="order-filter-section-heading">
                    <div>
                        <h3>Vận hành</h3>
                        <p>Trạng thái, dịch vụ và chi nhánh</p>
                    </div>
                </div>
                <div class="order-filter-section-grid order-filter-section-grid-3">
                    <div class="order-filter-field">
                        <label class="order-filter-label">Trạng thái xử lý</label>
                        <select data-order-filter="status" data-placeholder="Tất cả trạng thái" class="tomselectEml order-filter-tomselect">
                            <option value="">Tất cả trạng thái</option>
                            @foreach ($statusOptions as $status)
                                <option value="{{ $status['value'] }}">{{ $status['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="order-filter-field">
                        <label class="order-filter-label">Dịch vụ</label>
                        <select data-order-filter="serviceId" data-placeholder="Tất cả dịch vụ" class="tomselectEml order-filter-tomselect">
                            <option value="">Tất cả dịch vụ</option>
                            @foreach ($services as $service)
                                <option value="{{ $service['id'] }}">{{ $service['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="order-filter-field">
                        <label class="order-filter-label">Chi nhánh nhận hàng</label>
                        <select data-order-filter="branchId" data-placeholder="Tất cả chi nhánh" class="tomselectEml order-filter-tomselect">
                            <option value="">Tất cả chi nhánh</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch['id'] }}">{{ $branch['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </section>

            @if (!empty($capabilities['canSeeExtraFilters']))
            <section class="order-filter-section order-filter-section-wide">
                <div class="order-filter-section-heading">
                    <div>
                        <h3>Đại lý / Vận chuyển</h3>
                        <p>Đại lý, hãng bay và đối tác chung chuyển</p>
                    </div>
                </div>
                <div class="order-filter-section-grid order-filter-section-grid-3">
                    <div class="order-filter-field">
                        <label class="order-filter-label">Đại lý</label>
                        <select data-order-filter="agencyId" data-placeholder="Tất cả đại lý" class="tomselectEml order-filter-tomselect">
                            <option value="">Tất cả đại lý</option>
                            @foreach ($agencies as $agency)
                                <option value="{{ $agency['id'] }}">{{ $agency['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="order-filter-field">
                        <label class="order-filter-label">Hãng bay</label>
                        <select data-order-filter="airlineId" data-placeholder="Tất cả hãng bay" class="tomselectEml order-filter-tomselect">
                            <option value="">Tất cả hãng bay</option>
                            @foreach ($airlines as $airline)
                                <option value="{{ $airline['id'] }}">{{ $airline['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="order-filter-field">
                        <label class="order-filter-label">Đối tác chung chuyển</label>
                        <select data-order-filter="transitPartnerId" data-placeholder="Tất cả đối tác" class="tomselectEml order-filter-tomselect">
                            <option value="">Tất cả đối tác</option>
                            @foreach ($transitPartners as $partner)
                                <option value="{{ $partner['id'] }}">{{ $partner['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </section>
            @endif
        </div>

        <div class="order-filter-actions">
            <flux:button type="button" id="orders-reset-filter" variant="ghost">Làm mới</flux:button>
            <flux:modal.close>
                <flux:button type="button" id="orders-apply-filter" variant="primary">Áp dụng</flux:button>
            </flux:modal.close>
        </div>
    </div>
</flux:modal>
