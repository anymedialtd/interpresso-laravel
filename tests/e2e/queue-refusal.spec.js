import { test, expect, login, submit, databaseRows } from './helpers.js';

test.use({ fixtureScenario: 'imports', queueConnection: 'sync' });

test('Import Translations with sync shows the CLI command and starts no batch', async ({ page }) => {
    await login(page);
    const before = databaseRows('SELECT * FROM {translations} ORDER BY id');
    const lock = databaseRows('SELECT * FROM {settings}');
    await submit(page, page.getByRole('button', { name: 'Import Translations', exact: true }));
    const warning = page.locator('#toasts [role="status"]');
    await expect(warning).toContainText('No background queue is configured');
    await expect(warning).toContainText('php artisan interpresso:import-translations');
    await expect(warning).toContainText('QUEUE_CONNECTION=database');
    await expect(warning).toContainText('interpresso.schedule.queue_worker');
    await expect(warning).toContainText('php artisan schedule:run every minute via cron');
    await expect(page.locator('#batch-progress')).toBeHidden();
    await page.reload();
    await expect(page.locator('#batch-progress')).toBeHidden();
    expect(databaseRows('SELECT * FROM job_batches')).toEqual([]);
    expect(databaseRows('SELECT * FROM jobs')).toEqual([]);
    expect(databaseRows('SELECT * FROM notifications')).toEqual([]);
    expect(databaseRows('SELECT * FROM {translations} ORDER BY id')).toEqual(before);
    expect(databaseRows('SELECT * FROM {settings}')).toEqual(lock);
});
