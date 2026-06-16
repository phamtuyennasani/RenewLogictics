<button type="button"
        x-data="systemThemeFactory()"
        @click="toggleTheme()"
        {{ $attributes->class('relative inline-flex h-10 w-[4.35rem] shrink-0 items-center rounded-full border p-1 shadow-sm transition-all duration-200 active:scale-[0.98]') }}
        :class="theme === 'dark'
            ? 'border-sky-400/25 bg-slate-900 text-sky-100 shadow-black/25'
            : 'border-amber-200 bg-amber-50 text-amber-700 shadow-amber-900/10'"
        :aria-label="theme === 'dark' ? 'Dung theme sang' : 'Dung theme toi'"
        :title="theme === 'dark' ? 'Theme toi' : 'Theme sang'">
    <span class="absolute left-1 top-1 h-8 w-8 rounded-full bg-white shadow-sm ring-1 ring-black/5 transition-transform duration-200 dark:bg-slate-800 dark:ring-white/10"
          :class="theme === 'dark' ? 'translate-x-[1.6rem]' : 'translate-x-0'"></span>
    <span class="relative z-10 grid h-8 w-8 place-items-center rounded-full transition-colors"
          :class="theme === 'dark' ? 'text-slate-500' : 'text-amber-500'">
        <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M12 3v2.1m0 13.8V21m9-9h-2.1M5.1 12H3m15.36-6.36-1.49 1.49M7.13 16.87l-1.49 1.49m12.72 0-1.49-1.49M7.13 7.13 5.64 5.64M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0z"/>
        </svg>
    </span>
    <span class="relative z-10 grid h-8 w-8 place-items-center rounded-full transition-colors"
          :class="theme === 'dark' ? 'text-sky-200' : 'text-amber-300'">
        <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M21 12.8A8.5 8.5 0 1 1 11.2 3 6.5 6.5 0 0 0 21 12.8z"/>
        </svg>
    </span>
</button>
