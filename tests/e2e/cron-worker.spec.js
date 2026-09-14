import { test, expect, login, submit, databaseRows, openTranslations, tableRow } from './helpers.js';
import { execFileSync } from 'node:child_process';
import { join } from 'node:path';

test.use({ fixtureScenario: 'bulk', queueConnection: 'database' });

test('a browser batch completes with the bounded cron worker and no Supervisor', async ({ page }) => {
    await login(page);
    await submit(page, page.getByRole('button', { name: 'Approve (All Languages) Translations', exact: true }));
    const [batch] = databaseRows('SELECT * FROM job_batches');
    expect(Number(batch.pending_jobs)).toBeGreaterThan(0);
    expect(databaseRows('SELECT * FROM {translations} WHERE approved = 0').length).toBeGreaterThan(0);

    // A separate CLI process consumes exactly what the HTTP server dispatched.
    // execFileSync fails on a nonzero exit or if the bounded worker does not exit.
    execFileSync(join(__dirname, '../../vendor/bin/testbench'), ['interpresso:work'], {
        encoding: 'utf8', timeout: 30_000,
        env: {
            ...process.env,
            DB_CONNECTION: 'sqlite', INTERPRESSO_DB_CONNECTION: 'sqlite',
            DB_DATABASE: join(__dirname, '.data/e2e.sqlite'), INTERPRESSO_E2E: '1',
            QUEUE_CONNECTION: 'database', CACHE_STORE: 'file', SESSION_DRIVER: 'file',
        },
    });

    const [completed] = databaseRows('SELECT * FROM job_batches');
    expect(completed.id).toBe(batch.id);
    expect(Number(completed.pending_jobs)).toBe(0);
    expect(Number(completed.failed_jobs)).toBe(0);
    expect(completed.finished_at).not.toBeNull();
    expect(databaseRows('SELECT * FROM jobs')).toEqual([]);
    expect(databaseRows('SELECT * FROM failed_jobs')).toEqual([]);
    expect(databaseRows('SELECT * FROM {translations} WHERE approved = 0')).toEqual([]);
    expect(Number(databaseRows('SELECT process_running FROM {settings}')[0].process_running)).toBe(0);
    expect(databaseRows('SELECT * FROM notifications').length).toBeGreaterThan(0);

    await openTranslations(page);
    await page.reload();
    await expect(tableRow(page, 'checkout').getByRole('button', { name: 'Approve', exact: true })).toHaveCount(0);
});
