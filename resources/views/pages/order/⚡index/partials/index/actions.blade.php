@php
    $user = auth()->user();
    $canPrint = $user?->hasAnyRole(['admin', 'manager', 'cs', 'sale', 'ctv', 'ops']);
    $canCancelOrDelete = in_array($order->bill_status, [
        \App\Enums\OrderStatusEnum::MOI_TAO,
        \App\Enums\OrderStatusEnum::DA_XAC_NHAN,
        \App\Enums\OrderStatusEnum::DA_NHAN_HANG,
        \App\Enums\OrderStatusEnum::DUYET_XUAT_HANG,
    ], true);

    // Admin xóa được nhiều trạng thái; CS chỉ xóa được "Mới tạo".
    $canDeleteThisOrder = $user?->hasRole('admin')
        ? $canCancelOrDelete
        : ($user?->hasRole('cs') && $order->bill_status === \App\Enums\OrderStatusEnum::MOI_TAO);
    $canCancelThisOrder = $user?->hasRole('admin') && $canCancelOrDelete;
@endphp
<div class="flex items-center justify-end">
    <div class="inline-flex items-center justify-end gap-0.5 rounded-lg border border-neutral-200 bg-white p-0.5 shadow-xs">
        <flux:tooltip position="top" content="Xem chi tiết">
            <a wire:navigate href="{{ route('orders.show', ['uuid' => $order->uuid]) }}" class="inline-flex h-7 w-7 items-center justify-center rounded-md text-neutral-600 transition hover:bg-neutral-100 hover:text-neutral-900" aria-label="Xem chi tiết">
                <i class="pi pi-eye text-xs"></i>
            </a>
        </flux:tooltip>

        @if ($canPrint)
            <flux:tooltip position="top" content="Print Label">
                <button type="button" data-order-print="label" data-order-print-url="{{ route('orders.show', ['uuid' => $order->uuid]) }}" class="inline-flex h-7 w-7 items-center justify-center rounded-md text-violet-700 transition hover:bg-violet-50 hover:text-violet-800" aria-label="Print Label">
                    <i class="pi pi-tag text-xs"></i>
                </button>
            </flux:tooltip>
            <flux:tooltip position="top" content="Print Bill">
                <button type="button" data-order-print="bill" data-order-print-url="{{ route('orders.show', ['uuid' => $order->uuid]) }}" class="inline-flex h-7 w-7 items-center justify-center rounded-md text-sky-700 transition hover:bg-sky-50 hover:text-sky-800" aria-label="Print Bill">
                    <i class="pi pi-file text-xs"></i>
                </button>
            </flux:tooltip>
        @endif

        @if ($canCancelThisOrder || $canDeleteThisOrder)
            <span class="mx-0.5 h-4 w-px bg-neutral-200"></span>
        @endif

        @if ($canCancelThisOrder)
            <flux:tooltip position="top" content="Hủy đơn">
                <button
                    type="button"
                    data-order-cancel="{{ $order->id }}"
                    class="inline-flex h-7 w-7 items-center justify-center rounded-md text-amber-700 transition hover:bg-amber-50 hover:text-amber-800"
                    aria-label="Hủy đơn"
                >
                    <i class="pi pi-times-circle text-xs"></i>
                </button>
            </flux:tooltip>
        @endif

        @if ($canDeleteThisOrder)
            <flux:tooltip position="top" content="Xóa đơn">
                <button
                    type="button"
                    data-order-delete="{{ $order->id }}"
                    class="inline-flex h-7 w-7 items-center justify-center rounded-md text-red-700 transition hover:bg-red-50 hover:text-red-800"
                    aria-label="Xóa đơn"
                >
                    <i class="pi pi-trash text-xs"></i>
                </button>
            </flux:tooltip>
        @endif
    </div>

</div>
