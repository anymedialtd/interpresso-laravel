import { test, expect } from './helpers.js';
import { ADMIN, login } from './helpers.js';

test.describe('authentication', () => {
    test('the login page renders for an anonymous visitor', async ({ page }) => {
        // Anonymous pages must render the full layout without requiring a translator.
        const response = await page.goto('/translator/login');

        expect(response.status()).toBe(200);
        await expect(page.locator('input#email')).toBeVisible();
        await expect(page.locator('input#password')).toBeVisible();
    });

    test('a valid admin can log in and reaches the languages screen', async ({ page }) => {
        await login(page);

        await expect(page).toHaveURL(/\/translator\/languages/);
    });

    test('invalid credentials are rejected and stay on the login page', async ({ page }) => {
        await page.goto('/translator/login');
        await page.fill('input#email', ADMIN.email);
        await page.fill('input#password', 'definitely-not-the-password');
        await page.click('button[type="submit"]');

        await expect(page.locator('body')).toContainText(/invalid/i);
        await expect(page).toHaveURL(/\/translator\/login/);
    });

    test('an anonymous visitor cannot reach an authenticated screen', async ({ page }) => {
        const response = await page.goto('/translator/languages');

        // Either redirected to login, or refused outright - never rendered.
        expect(response.status() === 403 || page.url().includes('login')).toBeTruthy();
    });
});

test('login throttles the IP after ten invalid attempts even when the email changes', async ({ page }) => {
    await page.goto('/translator/login');
    for (let attempt = 0; attempt < 10; attempt++) {
        await page.locator('#email').fill(`attempt-${attempt}@example.test`);
        await page.locator('#password').fill('incorrect-password');
        await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Sign in', exact: true }).click()]);
        await expect(page.getByText('Email or password are invalid.')).toBeVisible();
    }
    await page.locator('#email').fill(ADMIN.email);
    await page.locator('#password').fill(ADMIN.password);
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Sign in', exact: true }).click()]);
    await expect(page.getByText(/Slow down! Please wait another \d+ seconds to log in\./)).toBeVisible();
    await page.goto('/translator/languages');
    await expect(page).toHaveURL(/\/translator\/login$/);
});

test('Remember me restores authentication after the session cookie is removed and logout revokes it', async ({ page, context }) => {
    await page.goto('/translator/login');
    await page.locator('#email').fill(ADMIN.email);
    await page.locator('#password').fill(ADMIN.password);
    await page.getByRole('checkbox', { name: 'Remember me' }).check();
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Sign in', exact: true }).click()]);
    const remember = (await context.cookies()).filter(cookie => cookie.name.startsWith('remember_'));
    expect(remember).toHaveLength(1);
    await context.clearCookies();
    await context.addCookies(remember);
    await page.reload();
    await expect(page.getByRole('heading', { name: 'Languages', exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Logout', exact: true }).click();
    await expect(page).toHaveURL(/\/translator\/login$/);
    await page.reload();
    await page.goto('/translator/languages');
    await expect(page).toHaveURL(/\/translator\/login$/);
    expect((await context.cookies()).filter(cookie => cookie.name.startsWith('remember_'))).toHaveLength(0);
});

test('required login fields prevent an empty form submission', async ({ page }) => {
    await page.goto('/translator/login');
    let submitted = false;
    page.on('request', request => { if (request.method() === 'POST') submitted = true; });
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    expect(await page.locator('#email').evaluate(input => input.validity.valueMissing)).toBe(true);
    expect(submitted).toBe(false);
});
