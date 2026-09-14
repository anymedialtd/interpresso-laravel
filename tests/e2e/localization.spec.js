import { test, expect, login, tableRow, changeSetting } from './helpers.js';

test.use({ appLocale: 'de' });

async function expectGermanScreen(page, heading) {
    await expect(page.locator('html')).toHaveAttribute('lang', 'de');
    await expect(page.getByRole('heading', { name: heading, exact: true })).toBeVisible();
    // Inspect rendered text nodes, including labels next to nested icons/spans.
    // Check namespaced Laravel keys too, since that is how package failures render.
    const leaks = await page.locator('body').evaluate(body => {
        const walker = document.createTreeWalker(body, NodeFilter.SHOW_TEXT);
        const keys = [];
        while (walker.nextNode()) {
            const node = walker.currentNode;
            const parent = node.parentElement;
            if (!parent || parent.closest('script, style') || !parent.checkVisibility({ visibilityProperty: true })) continue;
            const text = node.textContent.trim();
            if (/^(?:[a-z_]+::)?[a-z_]+\.[a-z_.]+$/.test(text)) keys.push(text);
        }
        return keys;
    });
    expect(leaks, `Raw translation keys on ${page.url()}`).toEqual([]);
}

test('German covers all four working screens, the editor and the localized manual', async ({ page }) => {
    await page.goto('/translator/login');
    await expect(page.getByRole('button', { name: 'Anmelden', exact: true })).toBeVisible();
    await login(page);
    await expectGermanScreen(page, 'Sprachen');
    await expect(page.getByRole('button', { name: 'Übersetzungen importieren', exact: true })).toBeVisible();
    // The base fixtures store translations in English; app.locale still stays de.
    await tableRow(page, 'English').getByRole('link', { name: 'Anzeigen', exact: true }).click();
    await expectGermanScreen(page, 'Übersetzungen: English (en)');
    await page.getByRole('button', { name: 'Übersetzen', exact: true }).first().click();
    await expect(page.getByRole('textbox', { name: 'Übersetzung', exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Dialog schließen', exact: true }).click();
    for (const [path, title] of [['translators', 'Übersetzer'], ['settings', 'Einstellungen']]) {
        await page.goto(`/translator/${path}`);
        await expectGermanScreen(page, title);
        if (path === 'translators') {
            await expect(page.getByRole('button', { name: 'Suchen', exact: true })).toBeVisible();
        }
    }
    await changeSetting(page, 'import_vendor', true);
    await expectGermanScreen(page, 'Einstellungen');
    await expect(page.getByText('Einstellung gespeichert.', { exact: true })).toBeVisible();
    await page.goto('/translator/manual');
    await expect(page.getByRole('heading', { name: 'Handbuch', exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Erste Schritte', exact: true })).toBeVisible();
    // Code identifiers in the manual are intentional text, not translation leaks.
    const links = page.locator('a[href^="#"]');
    for (const href of await links.evaluateAll(nodes => nodes.map(node => node.getAttribute('href')))) {
        await expect(page.locator(`[id="${href.slice(1)}"]`)).toHaveCount(1);
    }
    await page.locator('header a[href="#languages"]').click();
    await expect(page.locator('article #languages')).toBeInViewport();
});
