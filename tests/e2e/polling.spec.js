import { test, expect, login } from './helpers.js';

test.use({ fixtureScenario: 'running' });

test('notification and batch polling pause when hidden and batch polling stops after completion', async ({ page }) => {
    await page.clock.install();
    let batchRequests = 0;
    let notificationRequests = 0;
    let finished = false;
    await page.route('**/batch/progress**', route => {
        batchRequests++;
        return route.fulfill({ json: { id: 'browser-progress', progress: finished ? 100 : 50, finished, failed: false, cancelled: false } });
    });
    page.on('request', request => { if (request.url().endsWith('/notifications')) notificationRequests++; });
    await login(page);
    await expect.poll(() => batchRequests).toBeGreaterThan(0);
    await expect.poll(() => notificationRequests).toBeGreaterThan(0);
    await expect(page.getByRole('progressbar', { name: 'Batch progress' })).toHaveAttribute('value', '50');
    // Feed the browser visibility signal deterministically. The real polling
    // modules, request scheduling, DOM rendering and stop behavior are exercised.
    await page.evaluate(() => {
        window.__testVisibility = 'hidden';
        Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => window.__testVisibility });
        document.dispatchEvent(new Event('visibilitychange'));
    });
    const paused = [batchRequests, notificationRequests];
    await page.clock.runFor(12_000);
    expect([batchRequests, notificationRequests]).toEqual(paused);
    finished = true;
    await page.evaluate(() => {
        window.__testVisibility = 'visible';
        document.dispatchEvent(new Event('visibilitychange'));
    });
    await expect(page.locator('#batch-progress')).toBeHidden();
    await expect(page.getByText('Batch finished. Reload to see changes.', { exact: true })).toBeVisible();
    const complete = batchRequests;
    await page.clock.runFor(12_000);
    expect(batchRequests).toBe(complete);
    expect(notificationRequests).toBeGreaterThan(paused[1]);
});
