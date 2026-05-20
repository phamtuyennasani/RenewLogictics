<section class="space-y-4">
    <div class="flex flex-col gap-4 border-b border-neutral-200 pb-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="flex items-center gap-3">
            <div>
                <p class="text-sm text-neutral-500 capitalize">Tác vụ / Đơn hàng</p>
                <h1 class="text-2xl font-bold text-neutral-900">Quản lý đơn hàng</h1>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:modal.trigger name="order-index-filter">
                <flux:button type="button" variant="outline" icon="funnel">
                    Bộ lọc
                </flux:button>
            </flux:modal.trigger>

            <flux:button type="button" id="orders-export" variant="outline" icon="arrow-down-tray">
                Xuất CSV
            </flux:button>

            @if ($capabilities['canCreate'] ?? false)
                <flux:button href="{{ route('orders.create') }}" variant="primary" icon="plus" wire:navigate>
                    Tạo Order
                </flux:button>
            @endif
        </div>
    </div>
    
</section>
