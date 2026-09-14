import { test, expect, login, tableRow, submit } from './helpers.js';

test('every manual section and quick link has a working anchor', async ({ page }) => {
    await login(page);
    await page.getByRole('navigation').getByRole('link', { name: 'Manual', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Manual', exact: true })).toBeVisible();
    const anchors = page.locator('a[href^="#"]');
    expect(await anchors.count()).toBeGreaterThan(10);
    for (let index = 0; index < await anchors.count(); index++) {
        const link = anchors.nth(index);
        const hash = await link.getAttribute('href');
        await link.click();
        await expect(page).toHaveURL(url => decodeURIComponent(url.hash) === hash);
        const target = page.locator(`[id="${hash.slice(1)}"]`);
        await expect(target).toHaveCount(1);
        await expect(target).toBeInViewport();
    }
});

test('mobile main menu opens, closes, navigates every screen and logs out', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page);
    const toggle = page.getByRole('button', { name: 'Open main menu', exact: true });
    const menu = page.locator('#mobile-menu');
    await expect(menu).toBeHidden();
    await toggle.click();
    await expect(menu).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await toggle.click();
    await expect(menu).toBeHidden();
    for (const name of ['Manual', 'Translators', 'Settings', 'Languages']) {
        await toggle.click();
        await menu.getByRole('link', { name, exact: true }).click();
        await expect(page.getByRole('heading', { name, exact: true })).toBeVisible();
    }
    await toggle.click();
    await menu.getByRole('button', { name: 'Logout', exact: true }).click();
    await expect(page).toHaveURL(/\/translator\/login$/);
});

test('navbar brand returns to Languages and toast dismissal works', async ({ page }) => {
    await login(page);
    await submit(page, tableRow(page, 'German').getByRole('button', { name: 'Delete', exact: true }));
    const toast = page.locator('#toasts').getByRole('status');
    await expect(toast).toBeVisible();
    await toast.getByRole('button', { name: 'Dismiss notification', exact: true }).click();
    await expect(toast).toHaveCount(0);
    await page.getByRole('navigation').getByRole('link', { name: 'Manual', exact: true }).click();
    await page.getByRole('navigation').getByRole('link').first().click();
    await expect(page.getByRole('heading', { name: 'Languages', exact: true })).toBeVisible();
});

for (const raw of ['', '{broken', 'null']) {
    test(`toast payload ${JSON.stringify(raw)} cannot abort dropdown initialization`, async ({ page }) => {
        await login(page);
        await page.route('**/translator/translators?create=1', async route => {
            const response = await route.fetch();
            const body = (await response.text()).replace(/data-toast="[^"]*"/, `data-toast="${raw}"`);
            await route.fulfill({ response, body });
        });
        await page.getByRole('navigation').getByRole('link', { name: 'Translators', exact: true }).click();
        await page.getByRole('button', { name: 'Create Translator', exact: true }).click();
        const form = page.locator('#createOrUpdateForm');
        await form.getByRole('button', { name: 'Languages', exact: true }).click();
        await expect(form.getByRole('checkbox', { name: 'English', exact: true })).toBeVisible();
        await form.getByRole('checkbox', { name: 'English', exact: true }).check();
        await form.getByRole('button', { name: 'Languages', exact: true }).click();
        await expect(form.getByRole('checkbox', { name: 'English', exact: true })).toBeHidden();
        await page.getByRole('button', { name: /Notifications \(/ }).click();
        await expect(page.getByRole('button', { name: 'Mark all as read', exact: true })).toBeVisible();
    });
}
