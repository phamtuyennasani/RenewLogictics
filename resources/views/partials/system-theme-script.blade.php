<script>
    (() => {
        const storageKey = 'hethong.theme';
        const legacyMobileStorageKey = 'hethong.mobile.theme';
        const validThemes = ['light', 'dark'];

        const readStoredTheme = () => {
            try {
                return localStorage.getItem(storageKey) || localStorage.getItem(legacyMobileStorageKey);
            } catch (error) {
                return null;
            }
        };

        const normalizeTheme = (theme) => validThemes.includes(theme) ? theme : 'light';

        window.__applySystemTheme = function (nextTheme, persist = true) {
            const theme = normalizeTheme(nextTheme);

            document.documentElement.classList.toggle('dark', theme === 'dark');
            document.documentElement.dataset.theme = theme;
            document.documentElement.dataset.mobileTheme = theme;

            if (persist) {
                try {
                    localStorage.setItem(storageKey, theme);
                    localStorage.removeItem(legacyMobileStorageKey);
                } catch (error) {
                    // Ignore storage failures in private browsing.
                }
            }

            window.dispatchEvent(new CustomEvent('system-theme-changed', {
                detail: { theme },
            }));
        };

        window.systemThemeFactory = function () {
            return {
                theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
                init() {
                    window.addEventListener('system-theme-changed', (event) => {
                        this.theme = normalizeTheme(event.detail?.theme);
                    });
                },
                apply(nextTheme) {
                    window.__applySystemTheme(nextTheme);
                },
                toggleTheme() {
                    this.apply(this.theme === 'dark' ? 'light' : 'dark');
                },
            };
        };

        window.mobileThemeFactory = window.systemThemeFactory;
        window.__applySystemTheme(readStoredTheme(), false);
    })();
</script>
