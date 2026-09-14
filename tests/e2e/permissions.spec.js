import { test, expect } from './helpers.js';
import { TRANSLATOR, login, openTranslations, expectNoServerError } from './helpers.js';

test.describe('non-admin permissions', () => {
    test.beforeEach(async ({ page }) => {
        // This real login also verifies seed.sh created usable credentials.
        await login(page, TRANSLATOR);
        await expect(page.getByRole('heading', { name: 'Languages', exact: true })).toBeVisible();
        await expectNoServerError(page);
    });

    test('a translator sees assigned languages without admin navigation or language actions', async ({ page }) => {
        const assertPermissions = async () => {
            const navigation = page.getByRole('navigation');
            await expect(navigation.getByRole('link', { name: 'Languages', exact: true })).toBeVisible();
            await expect(navigation.getByRole('link', { name: 'Manual', exact: true })).toBeVisible();
            await expect(navigation.getByRole('button', { name: 'Logout', exact: true })).toBeVisible();
            await expect(navigation.getByRole('link', { name: 'Settings', exact: true })).toHaveCount(0);
            await expect(navigation.getByRole('link', { name: 'Translators', exact: true })).toHaveCount(0);

            const rows = page.getByRole('row').filter({ has: page.getByRole('cell') });
            await expect(rows).toHaveCount(1);
            await expect(rows).toContainText('English');
            await expect(rows.getByRole('link', { name: 'View', exact: true })).toBeVisible();
            await expect(page.getByRole('cell', { name: 'German', exact: true })).toHaveCount(0);
            for (const name of ['Add Language', 'Import Languages', 'Import Translations', 'Find Missing Translations', 'Approve (All Languages) Translations', 'Export All Languages', 'Export All Translated Models', 'Delete running Batch (Jobs)']) {
                await expect(page.getByRole('button', { name, exact: true })).toHaveCount(0);
            }
            for (const name of ['Delete', 'Edit']) {
                await expect(rows.getByText(name, { exact: true })).toHaveCount(0);
            }
        };
        await assertPermissions();
        await page.reload();
        await assertPermissions();
    });

    test('a translator can see translations without approval, export or request actions', async ({ page }) => {
        await openTranslations(page);
        const assertPermissions = async () => {
            const rows = page.getByRole('row').filter({ has: page.getByRole('cell') });
            await expect(rows).toHaveCount(6);
            await expect(rows.getByText('Translate', { exact: true })).toHaveCount(6);
            for (const name of ['Approve', 'Approve (en) Translations', 'Export Language', 'Export Translated Models']) {
                await expect(page.getByRole('button', { name, exact: true })).toHaveCount(0);
            }
            for (const name of ['Request translation', 'Remove translation request', 'Restore', 'Delete', 'Edit']) {
                await expect(rows.getByText(name, { exact: true })).toHaveCount(0);
            }
        };
        await assertPermissions();
        await page.reload();
        await assertPermissions();
    });

    for (const screen of ['settings', 'translators']) {
        test(`a translator cannot open the ${screen} screen directly`, async ({ page }) => {
            // Deliberate HTTP denials use the session-sharing request client.
            // Actual browser traffic has no health-guard exemptions.
            for (let attempt = 0; attempt < 2; attempt++) {
                const response = await page.request.get(`/translator/${screen}`);
                expect(response.status()).toBe(403);
                expect(await response.text()).toContain('Forbidden');
                expect(await response.text()).not.toContain('Create Translator');
                expect(await response.text()).not.toContain('Import Settings');
            }
            // A forbidden screen must not log the translator out.
            await page.goto('/translator/languages');
            await expect(page.getByRole('heading', { name: 'Languages', exact: true })).toBeVisible();
        });
    }
});

test('an assigned non-admin can edit a translation and the value survives reload', async ({ page }) => {
    await login(page, TRANSLATOR);
    await openTranslations(page);
    const row = page.getByRole('row').filter({ has: page.getByRole('cell', { name: 'welcome', exact: true }) });
    await row.getByRole('button', { name: 'Translate', exact: true }).click();
    const modal = page.getByRole('dialog');
    await expect(modal.getByRole('textbox')).toHaveValue('Welcome home');
    await modal.getByRole('textbox').fill('Edited by the assigned translator');
    await modal.getByRole('button', { name: 'Update Translation', exact: true }).click();
    await expect(modal).toBeHidden();
    await page.reload();
    await expect(row.getByRole('cell', { name: 'Edited by the assigned translator', exact: true })).toBeVisible();
    await expect(row.getByRole('button', { name: 'Approve', exact: true })).toHaveCount(0);
});
