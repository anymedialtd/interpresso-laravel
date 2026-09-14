import { test, expect, login, openTranslations, tableRow, submit, changeSetting } from './helpers.js';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { execFileSync } from 'node:child_process';

test.beforeEach(async ({ page }) => { await login(page); });

test('deleting a language removes it and its translation rows after reload', async ({ page }) => {
    await submit(page, tableRow(page, 'English').getByRole('button', { name: 'Delete', exact: true }));
    await page.reload();
    await expect(tableRow(page, 'English')).toHaveCount(0);
    await expect(tableRow(page, 'German')).toBeVisible();
    await page.getByRole('button', { name: 'Add Language', exact: true }).click();
    await page.getByRole('combobox').selectOption({ label: 'English' });
    await submit(page, page.getByRole('button', { name: 'Add', exact: true }));
    await openTranslations(page);
    await expect(page.locator('tbody tr')).toHaveCount(0);
});

test.describe('imports', () => {
    test.use({ fixtureScenario: 'imports' });
    test('Import Languages discovers a real language directory', async ({ page }) => {
        await expect(tableRow(page, 'Italian')).toHaveCount(0);
        await submit(page, page.getByRole('button', { name: 'Import Languages', exact: true }));
        await page.reload();
        await expect(tableRow(page, 'Italian')).toBeVisible();
        await expect(tableRow(page, 'Italian').getByRole('cell', { name: 'it', exact: true })).toBeVisible();
    });
    test('Import Translations imports PHP and JSON file contents', async ({ page }) => {
        await submit(page, page.getByRole('button', { name: 'Import Translations', exact: true }));
        await openTranslations(page);
        await page.reload();
        await expect(tableRow(page, 'imported')).toContainText('Imported from a real PHP file');
        await expect(tableRow(page, 'Imported JSON key')).toContainText('Imported from a real JSON file');
    });
});

test('Find Missing Translations creates the missing rows for German', async ({ page }) => {
    await openTranslations(page, 'German');
    await expect(page.locator('tbody tr')).toHaveCount(0);
    await page.getByRole('navigation').getByRole('link', { name: 'Languages', exact: true }).click();
    await submit(page, page.getByRole('button', { name: 'Find Missing Translations', exact: true }));
    await openTranslations(page, 'German');
    await page.reload();
    await expect(page.locator('tbody tr')).toHaveCount(6);
    await expect(tableRow(page, 'welcome').getByRole('button', { name: 'Remove translation request', exact: true })).toBeVisible();
});

test.describe('approvals and exports', () => {
    test.use({ fixtureScenario: 'bulk' });
    test('Approve all languages approves rows in both languages', async ({ page }) => {
        await submit(page, page.getByRole('button', { name: 'Approve (All Languages) Translations', exact: true }));
        for (const language of ['English', 'German']) {
            await openTranslations(page, language);
            await page.reload();
            await expect(page.locator('tbody tr')).not.toHaveCount(0);
            await expect(page.locator('tbody').getByRole('button', { name: 'Approve', exact: true })).toHaveCount(0);
            await expect(page.locator('tbody').getByRole('button', { name: 'Remove translation request', exact: true })).toHaveCount(0);
        }
    });
    for (const scope of ['language', 'all languages']) {
        test(`Export ${scope} writes approved translations to real files`, async ({ page }) => {
            await page.getByRole('navigation').getByRole('link', { name: 'Settings', exact: true }).click();
            await changeSetting(page, 'db_loader', false);
            if (scope === 'language') {
                await openTranslations(page);
                await submit(page, page.getByRole('button', { name: 'Export Language', exact: true }));
            } else {
                await openTranslations(page, 'German');
                await submit(page, page.getByRole('button', { name: 'Approve (de) Translations', exact: true }));
                await page.getByRole('navigation').getByRole('link', { name: 'Languages', exact: true }).click();
                await submit(page, page.getByRole('button', { name: 'Export All Languages', exact: true }));
                await openTranslations(page);
            }
            await page.reload();
            await page.getByRole('button', { name: 'State Filters', exact: true }).click();
            await submit(page, page.getByRole('button', { name: 'Exported', exact: true }));
            await expect(tableRow(page, 'vendor_notice')).toBeVisible();
            await expect(tableRow(page, 'checkout')).toHaveCount(0);
            const exported = readFileSync(join(__dirname, '.data/lang/vendor/e2e-vendor/en/e2e.php'), 'utf8');
            expect(exported).toContain("'vendor_notice' => 'Vendor notice'");
            expect(exported).not.toContain('Vendor draft');
            if (scope === 'all languages') {
                expect(readFileSync(join(__dirname, '.data/lang/de/e2e.php'), 'utf8')).toContain("'welcome' => 'Willkommen zu Hause'");
            }
        });
    }
    for (const scope of ['language', 'all languages']) {
        test(`model-only export for ${scope} reports when no model translations qualify`, async ({ page }) => {
            if (scope === 'language') await openTranslations(page);
            await submit(page, page.getByRole('button', { name: scope === 'language' ? 'Export Translated Models' : 'Export All Translated Models', exact: true }));
            await expect(page.getByText('Nothing exported.', { exact: true })).toBeVisible();
            await openTranslations(page);
            await page.reload();
            await page.getByRole('button', { name: 'State Filters', exact: true }).click();
            await submit(page, page.getByRole('button', { name: 'Exported', exact: true }));
            await expect(tableRow(page, 'vendor_notice')).toHaveCount(0);
        });
    }
});

test.describe('model exports', () => {
    test.use({ fixtureScenario: 'models' });
    for (const all of [false, true]) {
        test(`${all ? 'all-language' : 'single-language'} model export updates the actual JSON column`, async ({ page }) => {
            if (!all) await openTranslations(page);
            await submit(page, page.getByRole('button', { name: all ? 'Export All Translated Models' : 'Export Translated Models', exact: true }));
            await page.reload();
            const value = JSON.parse(execFileSync('php', ['-r', '$db = new PDO("sqlite:".$argv[1]); echo $db->query("SELECT title FROM e2e_articles WHERE id = 1")->fetchColumn();', join(__dirname, '.data/e2e.sqlite')], { encoding: 'utf8' }));
            expect(value).toEqual({ en: 'Model English', de: all ? 'Model German' : 'Old German' });
            await openTranslations(page);
            await page.getByRole('button', { name: 'State Filters', exact: true }).click();
            await submit(page, page.getByRole('button', { name: 'Exported', exact: true }));
            await expect(tableRow(page, 'Model English')).toBeVisible();
            await expect(tableRow(page, 'vendor_notice')).toHaveCount(0);
        });
    }
});

test.describe('running jobs', () => {
    test.use({ fixtureScenario: 'running' });
    test('Cancel running jobs cancels a real active batch and allows the next operation', async ({ page }) => {
        await expect(page.getByRole('progressbar', { name: 'Batch progress' })).toHaveAttribute('value', '50');
        await submit(page, page.getByRole('button', { name: 'Delete running Batch (Jobs)', exact: true }));
        await expect(page.getByText(/Jobs deleted: 1|1.*job/i).first()).toBeVisible();
        await page.reload();
        await expect(page.locator('#batch-progress')).toBeHidden();
        await submit(page, page.getByRole('button', { name: 'Approve (All Languages) Translations', exact: true }));
        await openTranslations(page);
        await page.reload();
        await expect(tableRow(page, 'checkout').getByRole('button', { name: 'Approve', exact: true })).toHaveCount(0);
    });
});
