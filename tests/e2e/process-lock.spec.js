import { test, expect, login, openTranslations, submit, tableRow } from './helpers.js';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';

function state() {
    const { settings } = JSON.parse(readFileSync(join(__dirname, '.data/tables.json'), 'utf8'));
    return JSON.parse(execFileSync('php', ['-r', `
        $db = new PDO('sqlite:'.$argv[1]);
        $table = '"'.str_replace('"', '""', $argv[2]).'"';
        echo json_encode([
            'lock' => $db->query('SELECT process_running, process_owner, process_started_at, process_expires_at FROM '.$table)->fetch(PDO::FETCH_ASSOC),
            'jobs' => $db->query('SELECT COUNT(*) FROM jobs')->fetchColumn(),
            'batches' => $db->query('SELECT COUNT(*) FROM job_batches')->fetchColumn(),
        ], JSON_THROW_ON_ERROR);
    `, join(__dirname, '.data/e2e.sqlite'), settings], { encoding: 'utf8' }));
}

test.describe('a live cron lock without a worker', () => {
    test.use({ fixtureScenario: 'locked' });
    test('the UI names the owner and start time and refuses overlapping work', async ({ page }) => {
        await login(page);
        const before = state();
        expect(before.jobs).toBe(0);
        expect(before.batches).toBe(0);
        await submit(page, page.getByRole('button', { name: 'Import Translations', exact: true }));
        await expect(page.getByText(/cron-host:123 import translations \(started \d{4}-/)).toBeVisible();
        await openTranslations(page);
        await submit(page, tableRow(page, 'checkout').getByRole('button', { name: 'Approve', exact: true }));
        await expect(page.getByText(/cron-host:123 import translations \(started \d{4}-/)).toBeVisible();
        await expect(tableRow(page, 'checkout').getByRole('button', { name: 'Approve', exact: true })).toBeVisible();
        expect(state()).toEqual(before);
    });
});

test.describe('an expired cron lock without a worker', () => {
    test.use({ fixtureScenario: 'expired-lock' });
    test('sync work reacquires the lease and clears all lock fields on completion', async ({ page }) => {
        await login(page);
        await submit(page, page.getByRole('button', { name: 'Approve (All Languages) Translations', exact: true }));
        await openTranslations(page);
        await expect(tableRow(page, 'checkout').getByRole('button', { name: 'Approve', exact: true })).toHaveCount(0);
        expect(state()).toEqual({
            lock: { process_running: 0, process_owner: null, process_started_at: null, process_expires_at: null },
            jobs: 0, batches: 1,
        });
    });
});
