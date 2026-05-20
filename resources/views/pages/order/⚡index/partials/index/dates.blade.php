<div class="text-sm text-neutral-700">
    <div>{{ $order->created_at?->format('d/m/Y H:i') }}</div>
    <div class="text-xs text-neutral-400">Xuất: {{ $order->ngayxuathang?->format('d/m/Y') ?: '—' }}</div>
</div>
