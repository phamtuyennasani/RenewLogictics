<div
    x-data="{
        pending: 0,
        show: false,
        startRequest() {
            this.pending++;
        },
        endRequest() {
            this.pending = 0;
        }
    }"
    x-init="
        show = false;
        Livewire.hook('commit.prepare', () => {  });
        Livewire.interceptRequest(({ onResponse, onSuccess, onError, onFailure }) => {
            let timer = null;
            startRequest();
            timer = setTimeout(() => {
                show = true;
            }, 750);

            const cleanup = () => {
                clearTimeout(timer);
                timer = null;
                show = false;
                endRequest();
            };
            onResponse(() => cleanup());
            onSuccess(() => cleanup());
            onError(() => cleanup());
            onFailure(() => cleanup());
        });

        document.addEventListener('livewire:navigate', () => {
            startRequest();
            show = true;
        });
        document.addEventListener('livewire:navigated', () => {
            show = false;
            endRequest();
        });
    "
>
    <template x-if="pending > 0 && show">
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
