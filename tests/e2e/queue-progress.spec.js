import { test, expect, login, submit, openTranslations, tableRow } from './helpers.js';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';

test.use({ fixtureScenario: 'queued' });

function work(once = false) {
    execFileSync('bash', [join(__dirname, 'work.sh'), ...(once ? ['--once'] : [])], { encoding: 'utf8', timeout: 30_000 });
}

function rows(sql) {
    const tables = JSON.parse(readFileSync(join(__dirname, '.data/tables.json'), 'utf8'));
    sql = sql.replace(/\{(translations|settings)\}/g, (_, table) => `"${tables[table].replaceAll('"', '""')}"`);
    return JSON.parse(execFileSync('php', ['-r', '$db = new PDO("sqlite:".$argv[1]); echo json_encode($db->query($argv[2])->fetchAll(PDO::FETCH_ASSOC), JSON_THROW_ON_ERROR);',
        join(__dirname, '.data/e2e.sqlite'), sql], { encoding: 'utf8' }));
}

test('a UI batch advances from 0 to 50 to 100, notifies the admin and stops polling', async ({ page }) => {
    await page.clock.install();
    let polls = 0;
    const progress = [];
    page.on('response', async response => {
        if (response.url().includes('/batch/progress')) {
            polls++;
            progress.push((await response.json()).progress);
        }
    });
    await login(page);
    await submit(page, page.getByRole('button', { name: 'Approve (All Languages) Translations', exact: true }));
    const panel = page.locator('#batch-progress');
    await expect(panel).toBeVisible();
    await expect(panel.getByRole('progressbar')).toHaveAttribute('value', '0');
    expect(rows('SELECT approved FROM {translations} WHERE approved = 0')).toHaveLength(4);
    expect(rows('SELECT * FROM notifications')).toHaveLength(0);
    work(true);
    await page.clock.runFor(1200);
    await expect(panel.getByRole('progressbar')).toHaveAttribute('value', '50');
    expect(rows('SELECT approved FROM {translations} WHERE approved = 0')).toHaveLength(1);
    expect(rows('SELECT * FROM notifications')).toHaveLength(0);
    work();
    await page.clock.runFor(1200);
    await expect(panel).toBeHidden();
    await expect(page.getByText('Batch finished. Reload to see changes.', { exact: true })).toBeVisible();
    expect(progress).toEqual(expect.arrayContaining([0, 50, 100]));
    const completed = polls;
    await page.clock.runFor(12_000);
    expect(polls).toBe(completed);
    expect(rows('SELECT approved FROM {translations} WHERE approved = 0')).toHaveLength(0);
    const [batch] = rows('SELECT * FROM job_batches');
    expect(batch.pending_jobs).toBe(0);
    expect(batch.failed_jobs).toBe(0);
    expect(batch.finished_at).not.toBeNull();
    expect(rows('SELECT process_running FROM {settings}')[0].process_running).toBe(0);
    expect(rows('SELECT * FROM jobs')).toHaveLength(0);
    const notices = rows('SELECT data, notifiable_id FROM notifications');
    expect(notices).toHaveLength(2);
    expect(notices.map(row => JSON.parse(row.data).message)).toEqual(expect.arrayContaining([
        expect.stringContaining('English'), expect.stringContaining('German'),
    ]));
    await page.getByRole('button', { name: /^Notifications/ }).click();
    await expect(page.locator('#notification-list')).toContainText('English');
    await expect(page.locator('#notification-list')).toContainText('German');
});

test('a new page discovers a real running batch without a redirect session ID', async ({ page, context }) => {
    await login(page);
    await submit(page, page.getByRole('button', { name: 'Approve (All Languages) Translations', exact: true }));
    const [batch] = rows('SELECT id FROM job_batches');
    // Consume the flash value on the original page, then load a fresh document.
    await page.reload();
    const other = await context.newPage();
    await other.goto('/translator/languages');
    await expect(other.locator('#batch-progress')).toHaveAttribute('data-batch-id', batch.id);
    await expect(other.getByRole('progressbar', { name: 'Batch progress' })).toHaveAttribute('value', '0');
    work(true);
    await expect(other.getByRole('progressbar', { name: 'Batch progress' })).toHaveAttribute('value', '50');
    work();
    await expect(other.locator('#batch-progress')).toBeHidden();
    await expect(other.getByText('Batch finished. Reload to see changes.', { exact: true })).toBeVisible();
});

test('the busy warning refuses a real UI action and cancellation prevents its queued writes', async ({ page }) => {
    await login(page);
    await submit(page, page.getByRole('button', { name: 'Approve (All Languages) Translations', exact: true }));
    const [batch] = rows('SELECT id FROM job_batches');
    await openTranslations(page);
    await submit(page, tableRow(page, 'checkout').getByRole('button', { name: 'Approve', exact: true }));
    await expect(page.getByText('A process is running in the background, no action allowed. Wait until the task finishes.', { exact: true })).toBeVisible();
    expect(rows('SELECT approved FROM {translations} WHERE approved = 0')).toHaveLength(4);
    await page.getByRole('navigation').getByRole('link', { name: 'Languages', exact: true }).click();
    await submit(page, page.getByRole('button', { name: 'Delete running Batch (Jobs)', exact: true }));
    work();
    expect(rows('SELECT approved FROM {translations} WHERE approved = 0')).toHaveLength(4);
    expect(rows('SELECT * FROM jobs')).toHaveLength(0);
    expect(rows('SELECT * FROM notifications')).toHaveLength(0);
    expect(rows(`SELECT cancelled_at FROM job_batches WHERE id = '${batch.id}'`)[0].cancelled_at).not.toBeNull();
    await expect(page.locator('#batch-progress')).toBeHidden();
    await openTranslations(page);
    await submit(page, tableRow(page, 'checkout').getByRole('button', { name: 'Approve', exact: true }));
    await expect(page.getByText('Translation approved.', { exact: true })).toBeVisible();
    expect(rows("SELECT approved FROM {translations} WHERE key = 'checkout'")[0].approved).toBe(1);
});
