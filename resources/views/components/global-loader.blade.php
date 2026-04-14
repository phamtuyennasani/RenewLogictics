<div
    x-data="{ pending: 0 }"
    x-init="
        // AJAX requests (Livewire 4 = commit hooks)
        Livewire.hook('commit.prepare', () => { pending++; });
        Livewire.hook('commit.succeed', () => { if (pending > 0) pending--; });
        Livewire.hook('commit.fail',    () => { if (pending > 0) pending--; });

        // Full-page navigation (Livewire 4 = DOM events)
        document.addEventListener('livewire:navigating', () => { pending++; });
        document.addEventListener('livewire:navigated',  () => { if (pending > 0) pending--; });
    "
>
    <template x-if="pending > 0">
        <div x-cloak
            x-transition:enter="transition duration-200 ease-out"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition duration-150 ease-in"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/30 backdrop-blur-[2px]"
        >
            <div class="bg-white p-5 rounded-lg shadow-xl flex flex-col items-center">
                <i class="pi pi-spin pi-spinner text-4xl text-primary-600"></i>
                <span class="mt-3 text-gray-600 font-medium">Đang tải...</span>
            </div>
        </div>
    </template>
</div>
