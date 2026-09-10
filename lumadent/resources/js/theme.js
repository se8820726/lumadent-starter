(() => {
    const root = document.documentElement;
    const key = 'lumadent-theme';
    const valid = (value) => ['light', 'dark', 'auto'].includes(value) ? value : 'auto';
    const system = window.matchMedia('(prefers-color-scheme: dark)');
    const read = () => {
        try {
            return valid(window.localStorage.getItem(key));
        } catch {
            return 'auto';
        }
    };
    let preference = read();

    const apply = () => {
        const theme = preference === 'auto' ? (system.matches ? 'dark' : 'light') : preference;
        root.dataset.theme = theme;
        root.dataset.themePreference = preference;
        root.style.colorScheme = theme;
        document.querySelectorAll('[data-theme-select]').forEach((select) => {
            select.value = preference;
        });
    };

    apply();
    system.addEventListener('change', apply);
    window.addEventListener('storage', (event) => {
        if (event.key === key || event.key === null) {
            preference = read();
            apply();
        }
    });
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            preference = read();
            apply();
        }
    });
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-theme-select]').forEach((select) => {
            select.value = preference;
            select.addEventListener('change', () => {
                preference = valid(select.value);
                try {
                    window.localStorage.setItem(key, preference);
                } catch {
                    // Keep the selected appearance for this page when storage is unavailable.
                }
                apply();
            });
        });
        document.querySelectorAll('[data-theme-control]').forEach((control) => {
            control.hidden = false;
        });
    }, { once: true });
})();
