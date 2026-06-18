<div id="activity-log-page" class="space-y-4">
    <section class="space-y-4">
        <div class="flex flex-col gap-4 border-b border-neutral-200 pb-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm text-neutral-500">Hệ thống / Nhật ký</p>
                <h1 class="mt-1 text-2xl font-bold text-neutral-900">Nhật ký hệ thống</h1>
                <p class="mt-1 text-sm text-neutral-500">Theo dõi các hành động nhạy cảm: xóa đơn hàng, công nợ, hóa đơn.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:modal.trigger name="activity-log-filter">
                    <flux:button type="button" variant="outline" icon="funnel">
                        Bộ lọc
                    </flux:button>
                </flux:modal.trigger>
            </div>
        </div>
    </section>

    <flux:modal name="activity-log-filter" class="w-full max-w-5xl !overflow-visible">
        <div id="activity-log-filter-panel" class="order-filter-panel">
            <div class="order-filter-header">
                <div class="order-filter-title-row">
                    <div class="order-filter-icon">
                        <flux:icon.funnel class="size-5" />
                    </div>
                    <div>
                        <flux:heading size="lg">Bộ lọc nhật ký</flux:heading>
                        <flux:subheading>Lọc theo thời gian, hành động, người thực hiện và nội dung nhật ký.</flux:subheading>
                    </div>
                </div>
            </div>

            <div class="order-filter-content">
                <section class="order-filter-section">
                    <div class="order-filter-section-heading">
                        <div>
                            <h3>Thời gian</h3>
                            <p>Khoảng ngày ghi nhận thao tác</p>
                        </div>
                        <div class="order-filter-presets">
                            <button type="button" wire:click="setDatePreset('today')">Hôm nay</button>
                            <button type="button" wire:click="setDatePreset('7')">7 ngày</button>
                            <button type="button" wire:click="setDatePreset('30')">30 ngày</button>
                        </div>
                    </div>
                    <div class="order-filter-section-grid order-filter-section-grid-2">
                        <div class="order-filter-field">
                            <label class="order-filter-label">Từ ngày</label>
                            <div class="order-date-picker-field">
                                <input type="text" value="{{ $fromDate }}" data-activity-date-picker data-livewire-model="fromDate" autocomplete="off" class="order-filter-control">
                            </div>
                        </div>
                        <div class="order-filter-field">
                            <label class="order-filter-label">Đến ngày</label>
                            <div class="order-date-picker-field">
                                <input type="text" value="{{ $toDate }}" data-activity-date-picker data-livewire-model="toDate" autocomplete="off" class="order-filter-control">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="order-filter-section">
                    <div class="order-filter-section-heading">
                        <div>
                            <h3>Đối tượng</h3>
                            <p>Loại thao tác và tài khoản thực hiện</p>
                        </div>
                    </div>
                    <div class="order-filter-section-grid order-filter-section-grid-2">
                        <div class="order-filter-field">
                            <label class="order-filter-label">Hành động</label>
                            <select wire:model.live="action" data-livewire-model="action" data-placeholder="Tất cả hành động" class="tomselectEml order-filter-tomselect">
                                <option value="">Tất cả hành động</option>
                                @foreach ($this->actionOptions() as $value => $label)
                                    <option value="{{ $value }}" @selected($action === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="order-filter-field">
                            <label class="order-filter-label">Người thực hiện</label>
                            <select wire:model.live="actorId" data-livewire-model="actorId" data-placeholder="Tất cả người thực hiện" class="tomselectEml order-filter-tomselect">
                                <option value="">Tất cả người thực hiện</option>
                                @foreach ($this->actors as $actor)
                                    <option value="{{ $actor->id }}" @selected((int) $actorId === (int) $actor->id)>{{ $actor->fullname ?: $actor->username }}{{ $actor->code ? ' - '.$actor->code : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </section>

                <section class="order-filter-section order-filter-section-wide">
                    <div class="order-filter-section-heading">
                        <div>
                            <h3>Nội dung</h3>
                            <p>Tìm theo tiêu đề, người thực hiện hoặc địa chỉ IP</p>
                        </div>
                    </div>
                    <div class="order-filter-section-grid">
                        <div class="order-filter-field">
                            <label class="order-filter-label">Từ khóa</label>
                            <input type="search" wire:model.live.debounce.400ms="keyword" placeholder="Tiêu đề, người thực hiện, IP" class="order-filter-control">
                        </div>
                    </div>
                </section>
            </div>

            <div class="order-filter-actions">
                <flux:button type="button" wire:click="resetFilters" variant="ghost">Làm mới</flux:button>
                <flux:modal.close>
                    <flux:button type="button" variant="primary">Áp dụng</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    {{-- Bảng nhật ký --}}
    <section class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-neutral-50 text-xs uppercase text-neutral-500">
                <tr>
                    <th class="px-4 py-3">Thời gian</th>
                    <th class="px-4 py-3">Hành động</th>
                    <th class="px-4 py-3">Nội dung</th>
                    <th class="px-4 py-3">Người thực hiện</th>
                    <th class="px-4 py-3">IP</th>
                    <th class="px-4 py-3 text-right">Chi tiết</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse ($this->items as $log)
                    <tr class="hover:bg-neutral-50" wire:key="log-{{ $log->id }}">
                        <td class="whitespace-nowrap px-4 py-3 text-neutral-600">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $this->actionColor($log->action) }}">
                                {{ $this->actionLabel($log->action) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-medium text-neutral-900">{{ $log->title }}</td>
                        <td class="px-4 py-3 text-neutral-700">
                            {{ $log->actor_name ?: '-' }}
                            @if ($log->actor_role)
                                <span class="ml-1 text-xs text-neutral-400">({{ $log->actor_role }})</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-neutral-500">{{ $log->ip_address ?: '-' }}</td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" wire:click="showDetail({{ $log->id }})"
                                class="inline-flex h-8 items-center justify-center rounded-lg border border-neutral-200 bg-white px-3 text-xs font-semibold text-neutral-700 transition hover:bg-neutral-50">
                                Xem
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-neutral-400">Chưa có nhật ký nào phù hợp bộ lọc.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div>
        {{ $this->items->links() }}
    </div>

    {{-- Modal chi tiết --}}
    @if ($this->detail)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click.self="closeDetail">
            <div class="w-full max-w-2xl rounded-xl bg-white p-5 shadow-xl">
                <div class="flex items-start justify-between gap-4 border-b border-neutral-100 pb-4">
                    <div>
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $this->actionColor($this->detail->action) }}">
                            {{ $this->actionLabel($this->detail->action) }}
                        </span>
                        <h2 class="mt-2 text-lg font-bold text-neutral-950">{{ $this->detail->title }}</h2>
                        <p class="mt-1 text-sm text-neutral-500">
                            {{ $this->detail->created_at?->format('d/m/Y H:i:s') }}
                            · {{ $this->detail->actor_name ?: '-' }}
                            @if ($this->detail->actor_role) ({{ $this->detail->actor_role }}) @endif
                            · IP {{ $this->detail->ip_address ?: '-' }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeDetail" class="rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-800">
                        <span class="sr-only">Đóng</span>
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="max-h-[60vh] overflow-auto py-4">
                    @if ($this->detail->note)
                        <p class="mb-3 rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-2 text-sm text-neutral-700">{{ $this->detail->note }}</p>
                    @endif
                    <p class="mb-2 text-xs font-bold uppercase text-neutral-500">Dữ liệu đã lưu (snapshot)</p>
                    <pre class="overflow-auto rounded-lg border border-neutral-200 bg-neutral-900 p-4 text-xs leading-relaxed text-neutral-100">{{ json_encode($this->detail->snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>

                <div class="flex justify-end border-t border-neutral-100 pt-4">
                    <button type="button" wire:click="closeDetail" class="inline-flex h-9 items-center justify-center rounded-lg border border-neutral-200 bg-white px-4 text-sm font-semibold text-neutral-700 transition hover:bg-neutral-50">Đóng</button>
                </div>
            </div>
        </div>
    @endif
</div>

@push('scripts')
    <script>
        (() => {
            let activityFilterRetryCount = 0;

            const filterPanel = () => document.getElementById('activity-log-filter-panel');

            const findLivewireComponent = (element) => {
                const componentId = element?.closest('[wire\\:id]')?.getAttribute('wire:id');

                return componentId && window.Livewire?.find ? window.Livewire.find(componentId) : null;
            };

            const setLivewireModel = (input, value) => {
                const model = input?.dataset.livewireModel;
                const component = findLivewireComponent(input);
                const normalizedValue = value || '';

                if (!model || !component) return;
                if (input.dataset.lastLivewireValue === normalizedValue) return;

                input.dataset.lastLivewireValue = normalizedValue;
                component.set(model, normalizedValue || null, true);
            };

            const initActivityLogFilters = () => {
                const root = filterPanel();
                if (!root) return;

                if (!window.flatpickr || !window.TomSelectHelper) {
                    if (activityFilterRetryCount < 20) {
                        activityFilterRetryCount++;
                        setTimeout(initActivityLogFilters, 100);
                    }
                    return;
                }

                root.querySelectorAll('input[data-activity-date-picker]').forEach((input) => {
                    if (input._flatpickr) return;

                    input.dataset.lastLivewireValue = input.value || '';
                    window.flatpickr(input, {
                        dateFormat: 'Y-m-d',
                        altInput: true,
                        altFormat: 'd/m/Y',
                        allowInput: true,
                        defaultDate: input.value || null,
                        static: true,
                        position: 'below left',
                        positionElement: input,
                        disableMobile: true,
                        clickOpens: true,
                        onReady: (_selectedDates, _dateStr, instance) => {
                            instance.calendarContainer.classList.add('order-filter-calendar');
                        },
                        onChange: (_selectedDates, dateStr) => {
                            setLivewireModel(input, dateStr);
                        },
                        onClose: (_selectedDates, dateStr) => {
                            setLivewireModel(input, dateStr);
                        },
                    });
                });

                window.TomSelectHelper.init(root);
            };

            const syncActivityLogFilters = (filters = {}) => {
                const root = filterPanel();
                if (!root) return;

                root.querySelectorAll('input[data-activity-date-picker]').forEach((input) => {
                    const model = input.dataset.livewireModel;
                    const value = filters[model] || '';

                    if (input._flatpickr) {
                        input._flatpickr.setDate(value || null, false);
                    } else {
                        input.value = value;
                    }

                    input.dataset.lastLivewireValue = value;
                });

                root.querySelectorAll('select.order-filter-tomselect').forEach((select) => {
                    const model = select.dataset.livewireModel;
                    const value = filters[model] || '';

                    if (select.tomselect) {
                        select.tomselect.setValue(value, true);
                    } else {
                        select.value = value;
                    }
                });
            };

            document.addEventListener('activity-log-filter-synced', (event) => {
                setTimeout(() => syncActivityLogFilters(event.detail?.filters || {}), 50);
            });

            document.addEventListener('DOMContentLoaded', () => setTimeout(initActivityLogFilters, 75));
            document.addEventListener('livewire:navigated', () => setTimeout(initActivityLogFilters, 75));

            new MutationObserver(() => {
                if (filterPanel()) {
                    setTimeout(initActivityLogFilters, 50);
                }
            }).observe(document.body, { childList: true, subtree: true });

            setTimeout(initActivityLogFilters, 75);
        })();
    </script>
@endpush
