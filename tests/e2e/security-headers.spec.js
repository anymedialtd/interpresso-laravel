import { test, expect } from './helpers.js';
import { ADMIN, login, openTranslations, settingControl } from './helpers.js';

test.use({ colorScheme: 'light' });

test('login, all four screens, translations and UI actions produce zero CSP violations', async ({ page }) => {
    const documents = [];
    page.on('response', response => {
        if (response.request().isNavigationRequest() && response.request().frame() === page.mainFrame()) {
            documents.push({ url: response.url(), policy: response.headers()['content-security-policy'] });
        }
    });

    await page.goto('/translator/login');
    await page.locator('#email').fill(ADMIN.email);
    await page.locator('#password').fill('incorrect-password');
    await page.getByRole('button', { name: /log in|login|sign in/i }).click();
    await expect(page.getByText('Email or password are invalid.')).toBeVisible();
    await login(page);

    for (const screen of ['languages', 'translators', 'settings', 'manual']) {
        const response = await page.goto(`/translator/${screen}`);
        expect(response.status()).toBe(200);
        await page.waitForLoadState('networkidle');
        await expect(page.locator('script:not([src]), style, [style], [onclick], [onchange], [onsubmit], [oninput]')).toHaveCount(0);
    }

    await page.getByRole('button', { name: 'Toggle dark mode' }).click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await page.getByRole('button', { name: /Notifications \(/ }).click();
    await Promise.all([
        page.waitForResponse(response => response.url().endsWith('/notifications/read-all') && response.status() === 200),
        page.getByRole('button', { name: 'Mark all as read' }).click(),
    ]);

    await openTranslations(page);
    await page.getByRole('row').filter({ has: page.getByRole('cell', { name: 'welcome', exact: true }) })
        .getByRole('button', { name: 'Translate', exact: true }).click();
    const modal = page.locator('#edit-translation-modal');
    await expect(modal.getByRole('textbox')).toHaveValue('Welcome home');
    await modal.getByRole('textbox').fill('CSP verified translation');
    await modal.getByRole('button', { name: 'Update Translation', exact: true }).click();
    await expect(page.getByRole('cell', { name: 'CSP verified translation', exact: true })).toBeVisible();
    await expect(page.locator('#toasts').getByRole('status')).toBeVisible();

    await page.goto('/translator/settings');
    await page.locator('[id="setting.allow_deleting_languages"]').locator('label').click();
    await expect(settingControl(page, 'allow_deleting_languages')).not.toBeChecked();
    await page.waitForLoadState('networkidle');
    await page.getByRole('button', { name: /log out|logout/i }).click();
    await expect(page).toHaveURL(/\/translator\/login$/);
    await page.waitForLoadState('networkidle');

    expect(documents.length).toBeGreaterThan(8);
    for (const { url, policy } of documents) {
        expect(policy, url).toContain("default-src 'none'");
        expect(policy, url).toContain("script-src 'self'");
        expect(policy, url).toContain("style-src 'self'");
        expect(policy, url).not.toMatch(/unsafe-inline|unsafe-eval|\*/);
    }
});

test('a legacy theme migrates to a cookie and remains authoritative on later requests', async ({ page, context }) => {
    await page.emulateMedia({ colorScheme: 'light' });
    await context.addInitScript(() => {
        if (!localStorage.getItem('theme-migration-tested')) {
            localStorage.setItem('color-theme', 'dark');
            localStorage.setItem('theme-migration-tested', '1');
        }
    });
    await page.goto('/translator/login');
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await expect.poll(async () => (await context.cookies()).find(cookie => cookie.name === 'interpresso-color-theme')?.value).toBe('dark');
    const cookie = (await context.cookies()).find(cookie => cookie.name === 'interpresso-color-theme');
    expect(cookie.path).toBe('/translator');
    expect(cookie.sameSite).toBe('Lax');
    expect(cookie.httpOnly).toBe(false);

    // A stale storage value must not override the preference sent to the server.
    await page.evaluate(() => localStorage.setItem('color-theme', 'light'));
    const response = await page.reload();
    expect(await response.text()).toMatch(/<html[^>]*data-theme="dark"[^>]*class="dark"/);
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await expect.poll(() => page.evaluate(() => localStorage.getItem('color-theme'))).toBe('dark');

    await page.getByRole('button', { name: 'Toggle dark mode' }).click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    await expect.poll(async () => (await context.cookies()).find(cookie => cookie.name === 'interpresso-color-theme')?.value).toBe('light');
    expect(await page.evaluate(() => localStorage.getItem('color-theme'))).toBe('light');
    expect(await (await page.reload()).text()).toMatch(/<html[^>]*data-theme="light"[^>]*class=""/);
});

test('without a saved choice the theme follows the system without persisting it', async ({ page, context }) => {
    await page.emulateMedia({ colorScheme: 'dark' });
    await page.goto('/translator/login');
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    expect(await page.evaluate(() => localStorage.getItem('color-theme'))).toBeNull();
    expect((await context.cookies()).some(cookie => cookie.name === 'interpresso-color-theme')).toBe(false);
    await page.emulateMedia({ colorScheme: 'light' });
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    await page.getByRole('button', { name: 'Toggle dark mode' }).click();
    await page.emulateMedia({ colorScheme: 'dark' });
    await page.emulateMedia({ colorScheme: 'light' });
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
});

test('theme toggling still works when localStorage is unavailable', async ({ page, context }) => {
    await context.addInitScript(() => {
        Object.defineProperty(window, 'localStorage', { get() { throw new DOMException('Storage unavailable', 'SecurityError'); } });
    });
    await page.emulateMedia({ colorScheme: 'light' });
    await page.goto('/translator/login');
    await page.getByRole('button', { name: 'Toggle dark mode' }).click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    expect(await (await page.reload()).text()).toMatch(/<html[^>]*data-theme="dark"[^>]*class="dark"/);
});

test.describe('theme before JavaScript', () => {
    test.use({ javaScriptEnabled: false, colorScheme: 'dark' });

    test('CSS follows the system and a cookie overrides it without a module', async ({ page, context, baseURL }) => {
        await page.goto('/translator/login');
        await expect(page.locator('html')).not.toHaveAttribute('data-theme');
        await expect(page.locator('html')).toHaveCSS('color-scheme', 'dark');
        await context.addCookies([{ name: 'interpresso-color-theme', value: 'light', url: `${baseURL}/translator/login` }]);
        await page.reload();
        await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
        await expect(page.locator('html')).toHaveCSS('color-scheme', 'light');
    });
});

for (const theme of ['light', 'dark']) {
    test(`saved ${theme} is correct on first paint while application JavaScript is held`, async ({ page, context, baseURL }) => {
        await context.addCookies([{ name: 'interpresso-color-theme', value: theme, url: `${baseURL}/translator/login` }]);
        await page.emulateMedia({ colorScheme: theme === 'dark' ? 'light' : 'dark' });
        await context.addInitScript(() => {
            window.__firstPaintTheme = null;
            new PerformanceObserver(list => {
                if (list.getEntries().some(entry => entry.name === 'first-paint')) {
                    window.__firstPaintTheme = {
                        theme: document.documentElement.dataset.theme,
                        scheme: getComputedStyle(document.documentElement).colorScheme,
                    };
                }
            }).observe({ type: 'paint', buffered: true });
        });
        let release;
        const held = new Promise(resolve => { release = resolve; });
        await page.route('**/vendor/interpresso/js/app.js', async route => { await held; await route.continue(); });
        try {
            const response = await page.goto('/translator/login', { waitUntil: 'commit' });
            await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
            await expect.poll(() => page.evaluate(() => window.__firstPaintTheme)).toEqual({ theme, scheme: theme });
            expect(await response.text()).toContain(`data-theme="${theme}"`);
        } finally { release(); }
        await page.waitForLoadState('load');
        await page.getByRole('button', { name: 'Toggle dark mode' }).click();
        const changed = theme === 'dark' ? 'light' : 'dark';
        await expect(page.locator('html')).toHaveAttribute('data-theme', changed);
        expect(await (await page.reload()).text()).toContain(`data-theme="${changed}"`);
    });
}
