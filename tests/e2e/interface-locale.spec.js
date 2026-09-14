import { test, expect, login, submit, TRANSLATOR, tableRow } from './helpers.js';

async function expectLocale(page, locale, heading) {
    await expect(page.locator('html')).toHaveAttribute('lang', locale);
    await expect(page.getByRole('heading', { name: heading, exact: true })).toBeVisible();
    await expect(page.locator('#interface-locale')).toHaveValue(locale);
    await expect(page.locator('body')).not.toContainText('interpresso::');
    const leaks = await page.locator('body').evaluate(body => {
        const walker = document.createTreeWalker(body, NodeFilter.SHOW_TEXT);
        const keys = [];
        while (walker.nextNode()) {
            const node = walker.currentNode;
            const parent = node.parentElement;
            if (!parent || parent.closest('script, style') || !parent.checkVisibility({ visibilityProperty: true })) continue;
            if (/^(?:[a-z_]+::)?[a-z_]+\.[a-z_.]+$/.test(node.textContent.trim())) keys.push(node.textContent.trim());
        }
        return keys;
    });
    expect(leaks, `Raw translation keys on ${page.url()}`).toEqual([]);
}

async function switchLocale(page, locale) {
    const form = page.locator('#interface-locale-form');
    await form.getByRole('combobox').selectOption(locale);
    await submit(page, form.getByRole('button'));
}

test('a translator switches to German before rendering and keeps French after logout and login', async ({ page, context }) => {
    await login(page, TRANSLATOR);
    const form = page.locator('#interface-locale-form');
    await expect(form.locator('option')).toHaveText(['Deutsch', 'English', 'Español', 'Français', 'Italiano']);
    await expect(form.locator('input[name="_token"]')).toHaveCount(1);
    await switchLocale(page, 'de');
    await expectLocale(page, 'de', 'Sprachen');
    const response = await page.reload();
    expect(await response.text()).toContain('<html lang="de"');
    await expectLocale(page, 'de', 'Sprachen');
    await switchLocale(page, 'fr');
    await expectLocale(page, 'fr', 'Langues');
    await submit(page, page.locator('form[action$="/logout"] button'));
    await expect(page).toHaveURL(/\/translator\/login$/);
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');

    // Remove both session and locale cookies to verify persistence on the account.
    await context.clearCookies();
    await login(page, TRANSLATOR);
    await expectLocale(page, 'fr', 'Langues');
    await expect(page.locator('script:not([src]), [onclick], [onchange], [onsubmit]')).toHaveCount(0);
});

test.describe('anonymous language switching without JavaScript', () => {
    test.use({ javaScriptEnabled: false });

    test('the login language is saved in a cookie and is correct in the reload response', async ({ page, context }) => {
        await page.goto('/translator/login');
        await switchLocale(page, 'de');
        await expect(page.getByRole('button', { name: 'Anmelden', exact: true })).toBeVisible();
        const cookie = (await context.cookies()).find(cookie => cookie.name === 'interpresso-locale');
        expect(cookie.path).toBe('/translator');
        expect(cookie.httpOnly).toBe(true);
        expect(cookie.sameSite).toBe('Lax');
        expect(cookie.expires).toBeGreaterThan(Date.now() / 1000 + 360 * 24 * 3600);
        const response = await page.reload();
        expect(await response.text()).toContain('<html lang="de"');
        await expect(page.locator('#interface-locale')).toHaveValue('de');
        await expect(page.getByRole('button', { name: 'Anmelden', exact: true })).toBeVisible();
        await expect(page.locator('body')).not.toContainText('interpresso::');
    });
});

test('an admin can save another translator interface language in the profile form', async ({ page, context }) => {
    await login(page);
    await page.goto('/translator/translators');
    await tableRow(page, TRANSLATOR.email).getByRole('link', { name: 'Edit', exact: true }).click();
    const form = page.locator('#createOrUpdateForm');
    await form.getByLabel('Interface language', { exact: true }).selectOption('de');
    await submit(page, form.getByRole('button', { name: 'Update', exact: true }));
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await tableRow(page, TRANSLATOR.email).getByRole('link', { name: 'Edit', exact: true }).click();
    await expect(form.getByLabel('Interface language', { exact: true })).toHaveValue('de');
    await context.clearCookies();
    await login(page, TRANSLATOR);
    await expectLocale(page, 'de', 'Sprachen');
});
