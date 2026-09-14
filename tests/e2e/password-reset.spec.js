import { test, expect, login, submit, runWorker, mailbox, passwordLink, TRANSLATOR, tableRow } from './helpers.js';

async function requestReset(page, email) {
    await page.goto('/translator/login');
    await page.getByRole('link', { name: 'Forgot your password?', exact: true }).click();
    await page.getByLabel('Email', { exact: true }).fill(email);
    await submit(page, page.getByRole('button', { name: 'Send reset link', exact: true }));
    await expect(page.getByRole('status')).toContainText('If a translator account exists for this email');
    runWorker();
}

async function setPassword(page, password, button = 'Reset password') {
    await page.getByLabel('New password', { exact: true }).fill(password);
    await page.getByLabel('Confirm password', { exact: true }).fill(password);
    await submit(page, page.getByRole('button', { name: button, exact: true }));
}

test('the login reset link leads through real mail, validation and a single-use password reset', async ({ page }) => {
    await requestReset(page, TRANSLATOR.email);
    const mail = mailbox(TRANSLATOR.email);
    expect(mail).toHaveLength(1);
    expect(mail[0].subject).toBe('Reset your Interpresso password');
    const url = passwordLink(mail[0]);
    await page.goto(url);
    await expect(page.getByLabel('Email', { exact: true })).toHaveValue(TRANSLATOR.email);
    await page.getByLabel('New password', { exact: true }).fill('new-account-password');
    await page.getByLabel('Confirm password', { exact: true }).fill('mismatched-password');
    await submit(page, page.getByRole('button', { name: 'Reset password', exact: true }));
    await expect(page.getByText('The password confirmation must match the password.')).toBeVisible();
    await expect(page.getByLabel('New password', { exact: true })).toHaveValue('');
    await setPassword(page, 'new-account-password');
    await expect(page).toHaveURL(/\/translator\/login$/);
    await expect(page.getByRole('status')).toContainText('Your password has been saved.');
    await page.goto(url);
    await setPassword(page, 'replayed-password');
    await expect(page.getByText('This password link is invalid or has expired. Please request a new link.')).toBeVisible();
    await page.goto('/translator/login');
    await page.locator('#email').fill(TRANSLATOR.email);
    await page.locator('#password').fill(TRANSLATOR.password);
    await submit(page, page.getByRole('button', { name: 'Sign in', exact: true }));
    await expect(page.getByText('Email or password are invalid.')).toBeVisible();
    await login(page, { ...TRANSLATOR, password: 'new-account-password' });
    await expect(page.getByRole('heading', { name: 'Languages', exact: true })).toBeVisible();
});

test('unknown addresses see the same message and receive no mail', async ({ page }) => {
    await requestReset(page, 'missing@example.test');
    const unknownMessage = await page.getByRole('status').textContent();
    expect(mailbox('missing@example.test')).toHaveLength(0);
    await requestReset(page, TRANSLATOR.email);
    expect(await page.getByRole('status').textContent()).toBe(unknownMessage);
    expect(mailbox(TRANSLATOR.email)).toHaveLength(1);
});

test('creation sends an invitation, resend replaces the link, and the recipient completes first login', async ({ page }) => {
    const email = 'invited@example.test';
    await login(page);
    await page.goto('/translator/translators?create=1');
    const form = page.locator('#createOrUpdateForm');
    await expect(page.locator('input[type="password"]')).toHaveCount(0);
    await form.getByLabel('Email', { exact: true }).fill(email);
    await form.getByLabel('First Name', { exact: true }).fill('Invited');
    await form.getByLabel('Last Name', { exact: true }).fill('Translator');
    await form.getByRole('button', { name: 'Languages', exact: true }).click();
    await form.getByRole('checkbox', { name: 'English', exact: true }).check();
    await form.getByRole('button', { name: 'Languages', exact: true }).click();
    await submit(page, form.getByRole('button', { name: 'Create', exact: true }));
    expect(mailbox(email)).toHaveLength(1);
    const first = mailbox(email)[0];
    expect(first.subject).toBe('You are invited to Interpresso');
    await tableRow(page, email).getByRole('link', { name: 'Edit', exact: true }).click();
    await expect(page.locator('input[type="password"]')).toHaveCount(0);
    await submit(page, page.getByRole('button', { name: 'Resend invitation', exact: true }));
    expect(mailbox(email)).toHaveLength(2);
    const second = mailbox(email)[1];
    expect(passwordLink(second)).not.toBe(passwordLink(first));
    await submit(page, page.getByRole('button', { name: 'Logout', exact: true }));
    await page.goto(passwordLink(first));
    await setPassword(page, 'old-invitation-password', 'Set password');
    await expect(page.getByText('This password link is invalid or has expired. Please request a new link.')).toBeVisible();
    await page.goto(passwordLink(second));
    await setPassword(page, 'recipient-chosen-password', 'Set password');
    await login(page, { email, password: 'recipient-chosen-password' });
    await expect(page.getByRole('heading', { name: 'Languages', exact: true })).toBeVisible();
});

test.describe('without JavaScript', () => {
    test.use({ javaScriptEnabled: false });
    test('password reset works with native forms and emailed links', async ({ page }) => {
        await requestReset(page, TRANSLATOR.email);
        await page.goto(passwordLink(mailbox(TRANSLATOR.email)[0]));
        await setPassword(page, 'password-without-js');
        await login(page, { ...TRANSLATOR, password: 'password-without-js' });
        await expect(page.getByRole('heading', { name: 'Languages', exact: true })).toBeVisible();
    });
});
