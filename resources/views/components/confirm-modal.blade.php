<div
    x-data="{
        show: false,
        title: '',
        message: '',
        confirmText: 'Xác nhận',
        cancelText: 'Hủy',
        variant: 'danger',
        open(options = {}) {
            const opt = Array.isArray(options) ? options[0] : options;
            this.title       = opt.title       || 'Xác nhận';
            this.message     = opt.message     || 'Bạn có chắc chắn?';
            this.confirmText = opt.confirmText || 'Xác nhận';
            this.cancelText  = opt.cancelText  || 'Hủy';
            this.variant     = opt.variant     || 'danger';
            this.show = true;
        },
        close() {
            this.show = false;
        },
        confirm() {
            window.Livewire.dispatch('confirm-action');
            this.close();
        }
    }"
    x-on:open-confirm.window="open($event.detail)"
    x-on:keydown.escape.window="close()"
>
    <!-- Backdrop -->
    <div
        x-show="show"
        x-cloak
        x-transition:enter="transition duration-200 ease-out"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition duration-150 ease-in"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/40 backdrop-blur-sm"
        x-on:click="close()"
    >
        <!-- Modal -->
        <div
            x-show="show"
            x-transition:enter="transition duration-200 ease-out"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition duration-150 ease-in"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden"
            x-on:click.stop
        >
            <!-- Header -->
            <div class="px-6 pt-6 pb-4">
                <div class="flex items-start gap-4">
                    <!-- Icon -->
                    <div class="flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center"
                         :class="{
                             'bg-red-100 text-red-600': variant === 'danger',
                             'bg-amber-100 text-amber-600': variant === 'warning',
                             'bg-blue-100 text-blue-600': variant === 'info',
                         }"
                    >
                        <!-- Danger icon -->
                        <svg x-show="variant === 'danger'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <!-- Warning icon -->
                        <svg x-show="variant === 'warning'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <!-- Info icon -->
                        <svg x-show="variant === 'info'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>

                    <!-- Text -->
                    <div class="flex-1 min-w-0">
                        <h3 class="text-lg font-semibold text-gray-900 leading-tight"
                            x-text="title"
                        ></h3>
                        <p class="mt-1.5 text-sm text-gray-500 leading-relaxed"
                           x-text="message"
                        ></p>
                    </div>

                    <!-- Close btn -->
                    <button
                        type="button"
                        x-on:click="close()"
                        class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Actions -->
            <div class="px-6 pb-6 pt-2">
                <div class="flex gap-3 justify-end">
                    <!-- Cancel -->
                    <button
                        type="button"
                        x-on:click="close()"
                        class="px-4 py-2.5 rounded-xl text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200
                               transition-all focus:outline-none focus:ring-2 focus:ring-gray-300"
                        x-text="cancelText"
                    ></button>

                    <!-- Confirm -->
                    <button
                        type="button"
                        x-on:click="confirm()"
                        class="px-4 py-2.5 rounded-xl text-sm font-semibold text-white transition-all
                               focus:outline-none focus:ring-2 focus:ring-offset-2"
                        :class="{
                             'bg-red-600 hover:bg-red-700 focus:ring-red-500': variant === 'danger',
                             'bg-amber-500 hover:bg-amber-600 focus:ring-amber-400': variant === 'warning',
                             'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500': variant === 'info',
                        }"
                        x-text="confirmText"
                    ></button>
                </div>
            </div>
        </div>
    </div>
</div>
