import { test, expect, login, openTranslations, submit } from './helpers.js';

test.use({ fixtureScenario: 'pagination' });
test.beforeEach(async ({ page }) => { await login(page); });

async function checkPages(page, expectedFilters, firstCount, secondCount) {
    const pagination = page.locator('[aria-label="Pagination"]');
    await expect(page.locator('tbody tr')).toHaveCount(firstCount);
    await pagination.getByRole('link', { name: 'Next', exact: true }).click();
    const assertFilters = async () => {
        const query = new URL(page.url()).searchParams;
        for (const [key, values] of Object.entries(expectedFilters)) expect([...query.entries()].filter(([name]) => name === key || (key.endsWith('[]') && name.replace(/\[\d+\]$/, '[]') === key)).map(([, value]) => value)).toEqual(values);
        expect(query.get('page')).toBe('2');
    };
    await expect(page.locator('tbody tr')).toHaveCount(secondCount);
    await assertFilters();
    await page.reload();
    await assertFilters();
    await expect(page.locator('tbody tr')).toHaveCount(secondCount);
    await pagination.getByRole('link', { name: 'Previous', exact: true }).click();
    await expect(page.locator('tbody tr')).toHaveCount(firstCount);
    await pagination.getByRole('link', { name: '2', exact: true }).click();
    await assertFilters();
    await expect(pagination.getByRole('link', { name: '2', exact: true })).toHaveAttribute('aria-current', 'page');
}

test('translation pagination preserves search, three multi-selects and all five state filters', async ({ page }) => {
    await openTranslations(page);
    await page.getByRole('searchbox').fill('paging_');
    await submit(page, page.getByRole('button', { name: 'Search', exact: true }));
    for (const [label, option] of [['Type', 'PHP'], ['Updated by', 'admin@admin.com'], ['Approved by', 'admin@admin.com']]) {
        await page.getByRole('button', { name: label, exact: true }).click();
        await Promise.all([page.waitForNavigation(), page.getByRole('checkbox', { name: option, exact: true }).check()]);
    }
    await page.getByRole('button', { name: 'State Filters', exact: true }).click();
    for (const [label, cycles] of [['Needs Translation', 2], ['Approved', 1], ['Updated', 2], ['Is Vendor', 1], ['Exported', 1]]) {
        for (let cycle = 0; cycle < cycles; cycle++) await submit(page, page.getByRole('button', { name: label, exact: true }));
    }
    await checkPages(page, {
        search: ['paging_'], 'types[]': ['php'], 'updatedBy[]': ['1'], 'approvedBy[]': ['1'],
        needs_translation: ['false'], approved: ['true'], updated_translation: ['false'], is_vendor: ['true'], exported: ['true'],
    }, 20, 5);
});

test('language pagination preserves search', async ({ page }) => {
    await page.getByRole('searchbox').fill('Paging');
    await submit(page, page.getByRole('button', { name: 'Search', exact: true }));
    await checkPages(page, { search: ['Paging'] }, 10, 10);
});

test('translator pagination preserves search and language selection', async ({ page }) => {
    await page.getByRole('navigation').getByRole('link', { name: 'Translators', exact: true }).click();
    await page.getByRole('searchbox').fill('paging-');
    await submit(page, page.getByRole('button', { name: 'Search', exact: true }));
    await page.getByRole('button', { name: 'Filter by languages', exact: true }).click();
    await Promise.all([page.waitForNavigation(), page.getByRole('checkbox', { name: 'English', exact: true }).check()]);
    await checkPages(page, { search: ['paging-'], 'selectedLanguages[]': ['1'] }, 10, 3);
});
