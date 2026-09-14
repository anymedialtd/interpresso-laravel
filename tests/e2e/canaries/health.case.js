import { test as guarded, expect } from '../helpers.js';

// Run only by health-guard.spec.js in a child runner. Deliberate errors must
// fail, and the parent checks that the automatic guard caused each failure.
const test = guarded.extend({ database: [async ({}, use) => { await use(); }, { auto: true }] });

test('healthy control', async ({ page }) => {
    await page.goto('/translator/login');
    await expect(page.getByRole('button', { name: 'Toggle dark mode' })).toBeVisible();
});
test('pageerror before initialization survives navigation', async ({ page }) => {
    await page.route('**/vendor/interpresso/js/app.js', route => route.fulfill({ contentType: 'text/javascript', body: "JSON.parse('');" }));
    await page.goto('/translator/login');
    await page.unroute('**/vendor/interpresso/js/app.js');
    await page.goto('/translator/login');
});
test('console.error in secondary tab', async ({ context }) => {
    const extra = await context.newPage();
    await extra.goto('/translator/login');
    await extra.evaluate(() => console.error('health-canary-console'));
    await extra.close();
});
test('securitypolicyviolation', async ({ page }) => {
    await page.goto('/translator/login');
    await page.evaluate(() => new Promise(resolve => {
        document.addEventListener('securitypolicyviolation', () => resolve(), { once: true });
        const script = document.createElement('script');
        script.textContent = 'window.inlineScriptMustNeverRun = true;';
        document.head.append(script);
    }));
});
test('same-origin HTTP failure', async ({ page }) => {
    await page.route('**/health-canary', route => route.fulfill({ status: 418, body: 'intentional canary' }));
    await page.goto('/translator/login');
    await page.evaluate(() => fetch('/health-canary'));
});
