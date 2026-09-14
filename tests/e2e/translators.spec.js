import { test, expect } from './helpers.js';
import { ADMIN, TRANSLATOR, login, formField, expectNoServerError } from './helpers.js';

const NEW_TRANSLATOR = {
    email: 'new-translator@example.test',
    password: 'new-translator-password',
    first_name: 'Jamie',
    last_name: 'Example',
    phone: '+41000000002',
};

function translatorRow(page, email = NEW_TRANSLATOR.email) {
    return page.getByRole('row').filter({
        has: page.getByRole('cell', { name: email, exact: true }),
    });
}

async function createTranslator(page) {
    await page.getByRole('button', { name: 'Create Translator', exact: true }).click();
    const form = page.locator('#createOrUpdateForm');
    await expect(form).toBeVisible();
    for (const [name, value] of Object.entries(NEW_TRANSLATOR)) {
        await formField(form, name).fill(value);
    }
    await formField(form, 'password_confirmation').fill(NEW_TRANSLATOR.password);
    await expect(form.locator('#admin').getByRole('checkbox')).not.toBeChecked();
    await form.getByRole('button', { name: 'Languages', exact: true }).click();
    await form.getByRole('checkbox', { name: 'English', exact: true }).check();
    await form.getByRole('button', { name: 'Languages', exact: true }).click();
    await form.getByRole('button', { name: 'Create', exact: true }).click();
    await expect(form).toHaveCount(0);
    await expect(translatorRow(page)).toBeVisible();
}

test.describe('translators', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
        await page.getByRole('navigation').getByRole('link', { name: 'Translators', exact: true }).click();
        await expect(page.getByRole('heading', { name: 'Translators', exact: true })).toBeVisible();
    });

    test('admin creates a translator that remains in the list after reload', async ({ page }) => {
        await expect(translatorRow(page)).toHaveCount(0);
        await createTranslator(page);
        for (const value of [NEW_TRANSLATOR.first_name, NEW_TRANSLATOR.last_name, NEW_TRANSLATOR.phone, 'English']) {
            await expect(translatorRow(page).getByRole('cell', { name: value, exact: true })).toBeVisible();
        }

        await page.reload();
        await expectNoServerError(page);
        await expect(translatorRow(page)).toBeVisible();
        for (const value of [NEW_TRANSLATOR.first_name, NEW_TRANSLATOR.last_name, NEW_TRANSLATOR.phone, 'English']) {
            await expect(translatorRow(page).getByRole('cell', { name: value, exact: true })).toBeVisible();
        }
    });

    test('editing a translator persists the changed profile', async ({ page }) => {
        const original = {
            email: TRANSLATOR.email,
            first_name: 'Regular',
            last_name: 'Translator',
            phone: '+41000000001',
        };
        const row = translatorRow(page, original.email);
        await row.getByText('Edit', { exact: true }).click();
        await expect(page.locator('#createOrUpdateForm')).toBeVisible();
        const edited = {
            email: 'edited-translator@example.test',
            first_name: 'Taylor',
            last_name: 'Updated',
            phone: '+41000000003',
        };
        for (const [name, value] of Object.entries(edited)) {
            await expect(formField(page, name)).toHaveValue(original[name]);
            await formField(page, name).fill(value);
        }
        await page.getByRole('button', { name: 'Update', exact: true }).click();
        await expect(page.locator('#createOrUpdateForm')).toHaveCount(0);
        await expect(row).toHaveCount(0);
        await expect(translatorRow(page, edited.email)).toBeVisible();

        await page.reload();
        await expectNoServerError(page);
        await expect(row).toHaveCount(0);
        for (const value of Object.values(edited)) {
            await expect(translatorRow(page, edited.email).getByRole('cell', { name: value, exact: true })).toBeVisible();
        }
        await translatorRow(page, edited.email).getByText('Edit', { exact: true }).click();
        for (const [name, value] of Object.entries(edited)) {
            await expect(formField(page, name)).toHaveValue(value);
        }
    });

    test('assigning another language persists both selected languages', async ({ page }) => {
        const row = translatorRow(page, TRANSLATOR.email);
        await row.getByText('Edit', { exact: true }).click();
        const form = page.locator('#createOrUpdateForm');
        await expect(form).toBeVisible();
        await form.getByRole('button', { name: 'Languages', exact: true }).click();
        await expect(form.getByRole('checkbox', { name: 'English', exact: true })).toBeChecked();
        await expect(form.getByRole('checkbox', { name: 'German', exact: true })).not.toBeChecked();
        await form.getByRole('checkbox', { name: 'German', exact: true }).check();
        await form.getByRole('button', { name: 'Languages', exact: true }).click();
        await form.getByRole('button', { name: 'Update', exact: true }).click();
        await expect(form).toHaveCount(0);
        await expect(row.getByRole('cell', { name: 'English, German', exact: true })).toBeVisible();

        await page.reload();
        await expect(row.getByRole('cell', { name: 'English, German', exact: true })).toBeVisible();
        await row.getByText('Edit', { exact: true }).click();
        await form.getByRole('button', { name: 'Languages', exact: true }).click();
        await expect(form.getByRole('checkbox', { name: 'English', exact: true })).toBeChecked();
        await expect(form.getByRole('checkbox', { name: 'German', exact: true })).toBeChecked();
    });

    test('deleting a translator removes it from the list after reload', async ({ page }) => {
        const row = translatorRow(page, TRANSLATOR.email);
        await expect(row).toBeVisible();
        await row.getByText('Delete', { exact: true }).click();
        await expect(row).toHaveCount(0);
        await expect(translatorRow(page, ADMIN.email)).toBeVisible();

        await page.reload();
        await expectNoServerError(page);
        await expect(row).toHaveCount(0);
        await expect(translatorRow(page, ADMIN.email)).toBeVisible();
        await expect(page.getByRole('row').filter({ has: page.getByRole('cell') })).toHaveCount(1);
    });
});

test.describe('additional translator forms', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
        await page.getByRole('navigation').getByRole('link', { name: 'Translators', exact: true }).click();
    });

    test('create validation keeps the form, values and language dropdown usable', async ({ page }) => {
        await page.getByRole('button', { name: 'Create Translator', exact: true }).click();
        const form = page.locator('#createOrUpdateForm');
        for (const [name, value] of Object.entries(NEW_TRANSLATOR)) await formField(form, name).fill(value);
        await formField(form, 'password_confirmation').fill('different-password');
        await Promise.all([page.waitForNavigation(), form.getByRole('button', { name: 'Create', exact: true }).click()]);
        await expect(form).toBeVisible();
        await expect(form).toContainText('The languages field is required.');
        await expect(form).toContainText('The password confirmation field must match password.');
        await expect(formField(form, 'email')).toHaveValue(NEW_TRANSLATOR.email);
        await expect(translatorRow(page)).toHaveCount(0);
        await formField(form, 'password').fill(NEW_TRANSLATOR.password);
        await formField(form, 'password_confirmation').fill(NEW_TRANSLATOR.password);
        await form.getByRole('button', { name: 'Languages', exact: true }).click();
        await expect(form.getByRole('checkbox', { name: 'English', exact: true })).toBeVisible();
        await form.getByRole('checkbox', { name: 'English', exact: true }).check();
        await form.getByRole('button', { name: 'Languages', exact: true }).click();
        await Promise.all([page.waitForNavigation(), form.getByRole('button', { name: 'Create', exact: true }).click()]);
        await page.reload();
        await expect(translatorRow(page)).toBeVisible();
    });

    test('create, edit and password Close links discard changes', async ({ page }) => {
        await page.getByRole('button', { name: 'Create Translator', exact: true }).click();
        await formField(page, 'email').fill(NEW_TRANSLATOR.email);
        await page.getByRole('button', { name: 'Close', exact: true }).click();
        await expect(page.locator('#createOrUpdateForm')).toHaveCount(0);
        await translatorRow(page, TRANSLATOR.email).getByRole('link', { name: 'Edit', exact: true }).click();
        await formField(page, 'first_name').fill('Discarded');
        await page.getByRole('button', { name: 'Close', exact: true }).click();
        await page.reload();
        await expect(translatorRow(page, TRANSLATOR.email)).toContainText('Regular');
        await expect(translatorRow(page)).toHaveCount(0);
        await translatorRow(page, TRANSLATOR.email).getByRole('link', { name: 'Edit', exact: true }).click();
        await page.getByRole('button', { name: 'Update Password', exact: true }).click();
        await formField(page, 'new_password').fill('discarded-password');
        await page.getByRole('button', { name: 'Close', exact: true }).click();
        await expect(page.locator('#createOrUpdateForm')).toBeVisible();
        await page.getByRole('button', { name: 'Logout', exact: true }).click();
        await login(page, TRANSLATOR);
    });

    test('password validation recovers and only the replacement password logs in', async ({ page }) => {
        await translatorRow(page, TRANSLATOR.email).getByRole('link', { name: 'Edit', exact: true }).click();
        await page.getByRole('button', { name: 'Update Password', exact: true }).click();
        await formField(page, 'new_password').fill('replacement-password');
        await formField(page, 'new_password_confirmation').fill('mismatched-password');
        await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Update Password', exact: true }).click()]);
        await expect(page.getByText(/new password confirmation.*match new password/i)).toBeVisible();
        await formField(page, 'new_password').fill('replacement-password');
        await formField(page, 'new_password_confirmation').fill('replacement-password');
        await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Update Password', exact: true }).click()]);
        await expect(page.locator('#createOrUpdateForm')).toBeVisible();
        await page.reload();
        await page.getByRole('button', { name: 'Logout', exact: true }).click();
        await page.locator('#email').fill(TRANSLATOR.email);
        await page.locator('#password').fill(TRANSLATOR.password);
        await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Sign in', exact: true }).click()]);
        await expect(page.getByText('Email or password are invalid.')).toBeVisible();
        await login(page, { ...TRANSLATOR, password: 'replacement-password' });
        await page.reload();
        await expect(page.getByRole('heading', { name: 'Languages', exact: true })).toBeVisible();
    });

    test('administrator switch changes permissions after save and language selections can be removed', async ({ page }) => {
        const row = translatorRow(page, TRANSLATOR.email);
        await row.getByRole('link', { name: 'Edit', exact: true }).click();
        const form = page.locator('#createOrUpdateForm');
        await form.locator('#admin').getByRole('checkbox').check();
        await form.getByRole('button', { name: 'Languages', exact: true }).click();
        await form.getByRole('checkbox', { name: 'English', exact: true }).uncheck();
        await form.getByRole('button', { name: 'Languages', exact: true }).click();
        await Promise.all([page.waitForNavigation(), form.getByRole('button', { name: 'Update', exact: true }).click()]);
        await page.reload();
        await row.getByRole('link', { name: 'Edit', exact: true }).click();
        await expect(form.locator('#admin').getByRole('checkbox')).toBeChecked();
        await form.getByRole('button', { name: 'Languages', exact: true }).click();
        await expect(form.getByRole('checkbox', { name: 'English', exact: true })).not.toBeChecked();
        await page.getByRole('button', { name: 'Logout', exact: true }).click();
        await login(page, TRANSLATOR);
        await page.getByRole('navigation').getByRole('link', { name: 'Settings', exact: true }).click();
        await expect(page.getByRole('heading', { name: 'Settings', exact: true })).toBeVisible();
    });

    test('search and Filter by languages submit together and clear after reload', async ({ page }) => {
        await page.getByRole('searchbox').fill('translator@example.test');
        await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Search', exact: true }).click()]);
        await expect(page.locator('tbody tr')).toHaveCount(1);
        const toggle = page.getByRole('button', { name: 'Filter by languages', exact: true });
        await toggle.click();
        await Promise.all([page.waitForNavigation(), page.getByRole('checkbox', { name: 'English', exact: true }).check()]);
        await page.reload();
        await expect(page.locator('tbody tr')).toHaveCount(1);
        await toggle.click();
        await expect(page.getByRole('checkbox', { name: 'English', exact: true })).toBeChecked();
        await Promise.all([page.waitForNavigation(), page.getByRole('checkbox', { name: 'German', exact: true }).check()]);
        await expect(page.locator('tbody tr')).toHaveCount(0);
        await toggle.click();
        await Promise.all([page.waitForNavigation(), page.getByRole('checkbox', { name: 'German', exact: true }).uncheck()]);
        await toggle.click();
        await Promise.all([page.waitForNavigation(), page.getByRole('checkbox', { name: 'English', exact: true }).uncheck()]);
        await toggle.click();
        await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Apply', exact: true }).click()]);
        await page.getByRole('searchbox').clear();
        await expect(page.locator('tbody tr')).toHaveCount(2);
    });
});
