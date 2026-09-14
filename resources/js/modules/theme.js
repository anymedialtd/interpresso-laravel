const COOKIE_NAME = 'interpresso-color-theme';
const STORAGE_KEY = 'color-theme';
const isTheme = value => value === 'light' || value === 'dark';

export function initTheme() {
    const root = document.documentElement;
    const system = window.matchMedia('(prefers-color-scheme: dark)');
    let preference = isTheme(root.dataset.theme) ? root.dataset.theme : null;

    if (!preference) {
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (isTheme(saved)) preference = saved;
        } catch (_) {}
    }

    const apply = theme => {
        root.dataset.theme = theme;
        root.classList.toggle('dark', theme === 'dark');
    };
    const persist = theme => {
        const secure = location.protocol === 'https:' ? '; Secure' : '';
        const path = root.dataset.themeCookiePath || '/';
        try {
            // A trailing-slash cookie is sent first on child routes and would
            // shadow our canonical preference on the next server render.
            if (path !== '/') {
                document.cookie = `${COOKIE_NAME}=; Path=${path}/; Max-Age=0; SameSite=Lax${secure}`;
            }
            document.cookie = `${COOKIE_NAME}=${theme}; Path=${path}; Max-Age=31536000; SameSite=Lax${secure}`;
        } catch (_) {}
        try { localStorage.setItem(STORAGE_KEY, theme); } catch (_) {}
    };

    // CSS follows the system before this module runs. A cookie is already
    // rendered in HTML; migrate a legacy storage-only choice on the first visit.
    apply(preference || (system.matches ? 'dark' : 'light'));
    if (preference) persist(preference);

    system.addEventListener('change', event => {
        if (!preference) apply(event.matches ? 'dark' : 'light');
    });
    document.getElementById('theme-toggle')?.addEventListener('click', () => {
        preference = root.dataset.theme === 'dark' ? 'light' : 'dark';
        apply(preference);
        persist(preference);
    });
}
