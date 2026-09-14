import { test, expect, submitBatch, ADMIN, TRANSLATOR, login, openTranslations, tableRow, submit, changeSetting } from './helpers.js';

test.use({ queueConnection: 'database' });

const keys = ['welcome', 'checkout', 'profile', 'vendor_notice', 'vendor_pending', 'vendor_ready'];
async function expectRows(page, expected) {
    await expect(page.locator('tbody tr')).toHaveCount(expected.length);
    for (const key of keys) await expect(tableRow(page, key)).toHaveCount(expected.includes(key) ? 1 : 0);
}

test.beforeEach(async ({ page }) => { await login(page); await openTranslations(page); });

test.describe('multi-select filters', () => {
    test.use({ fixtureScenario: 'filters' });
    const cases = [
        { label: 'Type', name: 'types', first: 'PHP', second: 'JSON', firstKeys: ['welcome', 'vendor_ready'], both: ['welcome', 'vendor_ready', 'checkout', 'vendor_notice'], secondKeys: ['checkout', 'vendor_notice'] },
        { label: 'Updated by', name: 'updatedBy', first: ADMIN.email, second: TRANSLATOR.email, firstKeys: ['welcome', 'profile'], both: ['welcome', 'profile', 'checkout', 'vendor_notice'], secondKeys: ['checkout', 'vendor_notice'] },
        { label: 'Approved by', name: 'approvedBy', first: ADMIN.email, second: TRANSLATOR.email, firstKeys: ['welcome', 'vendor_notice'], both: ['welcome', 'vendor_notice', 'profile', 'vendor_pending'], secondKeys: ['profile', 'vendor_pending'] },
    ];
    for (const filter of cases) {
        test(`${filter.label} opens, submits multiple selections, reloads and clears`, async ({ page }) => {
            const toggle = page.getByRole('button', { name: filter.label, exact: true });
            const panel = page.locator(`[id="filter-${filter.name}-options"]`);
            for (const [option, checked, expected] of [[filter.first, true, filter.firstKeys], [filter.second, true, filter.both], [filter.first, false, filter.secondKeys], [filter.second, false, keys]]) {
                await toggle.click();
                await expect(toggle).toHaveAttribute('aria-expanded', 'true');
                await expect(panel).toBeVisible();
                await Promise.all([page.waitForNavigation(), panel.getByRole('checkbox', { name: option, exact: true }).setChecked(checked)]);
                await expectRows(page, expected);
                await page.reload();
                await expectRows(page, expected);
                await toggle.click();
                await expect(panel.getByRole('checkbox', { name: option, exact: true })).toBeChecked({ checked });
                await submit(page, panel.getByRole('button', { name: 'Apply', exact: true }));
                await expectRows(page, expected);
            }
        });
    }
    test('Model selects its distinct rows and combines with search and a state filter', async ({ page }) => {
        await page.getByRole('button', { name: 'Type', exact: true }).click();
        await Promise.all([page.waitForNavigation(), page.getByRole('checkbox', { name: 'Model', exact: true }).check()]);
        await expectRows(page, ['profile', 'vendor_pending']);
        await page.getByRole('button', { name: 'State Filters', exact: true }).click();
        await submit(page, page.getByRole('button', { name: 'Needs Translation', exact: true }));
        await expectRows(page, ['vendor_pending']);
        await page.getByRole('searchbox').fill('Vendor draft');
        await submit(page, page.getByRole('button', { name: 'Search', exact: true }));
        await page.reload();
        await expectRows(page, ['vendor_pending']);
        await expect(page).toHaveURL(url => url.searchParams.get('needs_translation') === 'true' && url.searchParams.getAll('types[]').includes('model') && url.searchParams.get('search') === 'Vendor draft');
    });
});

test('Approve persists the approval and clears the previous draft state', async ({ page }) => {
    const row = tableRow(page, 'profile');
    await submit(page, row.getByRole('button', { name: 'Approve', exact: true }));
    await page.reload();
    await expect(row.getByRole('button', { name: 'Approve', exact: true })).toHaveCount(0);
    await expect(row.getByRole('button', { name: 'Restore', exact: true })).toHaveCount(0);
    await expect(row).not.toContainText('Previous Your profile');
    await page.getByRole('button', { name: 'State Filters', exact: true }).click();
    await submit(page, page.getByRole('button', { name: 'Approved', exact: true }));
    await expect(row).toBeVisible();
});

test('Request translation and Remove translation request both persist', async ({ page }) => {
    const row = tableRow(page, 'welcome');
    await submit(page, row.getByRole('button', { name: 'Request translation', exact: true }));
    await page.reload();
    await expect(row.getByRole('button', { name: 'Remove translation request', exact: true })).toBeVisible();
    await expect(row.getByRole('button', { name: 'Approve', exact: true })).toBeVisible();
    await submit(page, row.getByRole('button', { name: 'Remove translation request', exact: true }));
    await page.reload();
    await expect(row.getByRole('button', { name: 'Request translation', exact: true })).toBeVisible();
    await expect(row.getByRole('button', { name: 'Approve', exact: true })).toHaveCount(0);
});

test('Restore replaces the draft with the previous value after reload', async ({ page }) => {
    const row = tableRow(page, 'profile');
    await submit(page, row.getByRole('button', { name: 'Restore', exact: true }));
    await page.reload();
    await expect(row.getByRole('cell', { name: 'Previous Your profile', exact: true })).toBeVisible();
    await expect(row.getByRole('cell', { name: 'Your profile', exact: true })).toHaveCount(0);
    await expect(row.getByRole('button', { name: 'Restore', exact: true })).toHaveCount(0);
});

test('Approve all for a language applies to every row and reports a second no-op', async ({ page }) => {
    const approve = page.getByRole('button', { name: 'Approve (en) Translations', exact: true });
    await submitBatch(page, approve);
    await page.reload();
    await expect(page.locator('tbody').getByRole('button', { name: 'Approve', exact: true })).toHaveCount(0);
    await expect(page.locator('tbody').getByRole('button', { name: 'Remove translation request', exact: true })).toHaveCount(0);
    await submitBatch(page, approve);
    await expect(page.getByText('Nothing approved.', { exact: true })).toBeVisible();
});

for (const close of ['Close modal', 'Escape', 'backdrop']) {
    test(`modal ${close} discards edits and returns focus to Translate`, async ({ page }) => {
        const trigger = tableRow(page, 'welcome').getByRole('button', { name: 'Translate', exact: true });
        await trigger.click();
        const modal = page.getByRole('dialog');
        await expect(modal.getByRole('textbox')).toHaveValue('Welcome home');
        await modal.getByRole('textbox').fill('Discard this draft');
        if (close === 'Escape') await page.keyboard.press('Escape');
        else if (close === 'backdrop') await page.mouse.click(2, 2);
        else await modal.getByRole('button', { name: close, exact: true }).click();
        await expect(modal).toBeHidden();
        await expect(trigger).toBeFocused();
        await page.reload();
        await trigger.click();
        await expect(modal.getByRole('textbox')).toHaveValue('Welcome home');
    });
}

test('OpenAI controls are hidden when the setting is disabled', async ({ page }) => {
    await tableRow(page, 'welcome').getByRole('button', { name: 'Translate', exact: true }).click();
    await expect(page.getByRole('dialog').getByRole('textbox')).toHaveValue('Welcome home');
    await expect(page.getByRole('button', { name: 'Translate with OPEN AI', exact: true })).toBeHidden();
    await expect(page.getByRole('button', { name: 'Update & auto-translate others (OPEN AI)', exact: true })).toBeHidden();
});

test.describe('example languages and auto-translation', () => {
    test.use({ fixtureScenario: 'examples' });
    test('Example Language selects modal content and each example disclosure opens', async ({ page }) => {
        await page.getByRole('combobox', { name: 'Example Language' }).selectOption({ label: 'German (de)' });
        await tableRow(page, 'welcome').getByRole('button', { name: 'Translate', exact: true }).click();
        const modal = page.getByRole('dialog');
        await expect(modal.locator('[data-example]')).toHaveText('Willkommen zu Hause');
        for (const [code, value] of [['en', 'Welcome home'], ['de', 'Willkommen zu Hause']]) {
            const details = modal.locator('details').filter({ has: page.locator('summary', { hasText: code }) });
            await details.locator('summary').click();
            await expect(details.locator('p')).toHaveText(value);
            await expect(details.locator('p')).toBeVisible();
            await details.locator('summary').click();
            await expect(details.locator('p')).toBeHidden();
        }
    });
    test('Update and auto-translate others submits its own action and saves both languages', async ({ page }) => {
        await page.getByRole('navigation').getByRole('link', { name: 'Settings', exact: true }).click();
        await changeSetting(page, 'enable_open_ai_translations', true);
        await openTranslations(page);
        await tableRow(page, 'welcome').getByRole('button', { name: 'Translate', exact: true }).click();
        const modal = page.getByRole('dialog');
        await expect(modal.getByRole('button', { name: 'Translate with OPEN AI', exact: true })).toBeVisible();
        await modal.getByRole('textbox').fill('Updated root text');
        await submitBatch(page, modal.getByRole('button', { name: 'Update & auto-translate others (OPEN AI)', exact: true }));
        await page.reload();
        await expect(tableRow(page, 'welcome').getByRole('cell', { name: 'Updated root text', exact: true })).toBeVisible();
        await openTranslations(page, 'German');
        await page.reload();
        await expect(tableRow(page, 'welcome').getByRole('cell', { name: '[de] Updated root text', exact: true })).toBeVisible();
        await tableRow(page, 'welcome').getByRole('button', { name: 'Translate', exact: true }).click();
        await expect(modal.getByRole('button', { name: 'Update & auto-translate others (OPEN AI)', exact: true })).toBeHidden();
    });
});
