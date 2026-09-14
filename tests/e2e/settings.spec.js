import { test, expect, login, settingControl, formField, changeSetting, submit } from './helpers.js';

const TOGGLES = [
    'db_loader', 'import_vendor', 'enable_pending_notifications',
    'enable_automatic_pending_notifications', 'enable_open_ai_translations',
    'import_only_from_root_language', 'allow_deleting_languages',
];

test.beforeEach(async ({ page }) => {
    await login(page);
    await page.getByRole('navigation').getByRole('link', { name: 'Settings', exact: true }).click();
});

for (const name of TOGGLES) {
    test(`${name} saves both states independently and survives reload`, async ({ page }) => {
        const before = {};
        for (const field of TOGGLES) before[field] = await settingControl(page, field).isChecked();
        for (const enabled of [!before[name], before[name]]) {
            await changeSetting(page, name, enabled);
            await expect(page.getByText('Setting saved.', { exact: true })).toBeVisible();
            await page.reload();
            for (const field of TOGGLES) {
                await expect(settingControl(page, field)).toBeChecked({ checked: field === name ? enabled : before[field] });
            }
        }
    });
}

test('multi-host requires saved domains only while enabled and recovers from invalid input', async ({ page }) => {
    const toggle = settingControl(page, 'enable_multi_host');
    const domains = formField(page, 'setting.domains');
    await expect(toggle).not.toBeChecked();
    await expect(domains).not.toHaveAttribute('required');
    await submit(page, page.locator('[id="setting.enable_multi_host"] label'));
    await expect(page.getByText('Domains are required when multi-host coordination is enabled.')).toBeVisible();
    await expect(domains).toHaveAttribute('required', '');
    await page.reload();
    await expect(toggle).not.toBeChecked();
    await expect(domains).not.toHaveAttribute('required');

    await domains.fill('https://translations.example.test');
    await Promise.all([page.waitForNavigation(), domains.press('Tab')]);
    await page.reload();
    await expect(domains).toHaveValue('https://translations.example.test');
    await changeSetting(page, 'enable_multi_host', true);
    await page.reload();
    await expect(toggle).toBeChecked();
    await expect(domains).toHaveAttribute('required', '');
    await domains.clear();
    await domains.press('Tab');
    expect(await domains.evaluate(input => input.validity.valueMissing)).toBe(true);
    await page.reload();
    await expect(domains).toHaveValue('https://translations.example.test');
    await changeSetting(page, 'enable_multi_host', false);
    await domains.clear();
    await Promise.all([page.waitForNavigation(), domains.press('Tab')]);
    await page.reload();
    await expect(toggle).not.toBeChecked();
    await expect(domains).toHaveValue('');
    await expect(domains).not.toHaveAttribute('required');
});

test.describe('settings Save buttons without JavaScript', () => {
    test.use({ javaScriptEnabled: false });
    for (const name of [...TOGGLES, 'domains', 'enable_multi_host']) {
        test(`${name} has a working independent Save button`, async ({ page }) => {
            // Domains must already be saved before enabling multi-host.
            if (name === 'enable_multi_host') {
                const field = formField(page, 'setting.domains');
                await field.fill('https://translations.example.test');
                await submit(page, page.locator('form[action$="/settings/domains"]').getByRole('button', { name: 'Save', exact: true }));
            }
            const form = page.locator(`form[action$="/settings/${name}"]`);
            let expected;
            if (name === 'domains') {
                expected = 'https://saved.example.test';
                await formField(page, 'setting.domains').fill(expected);
            } else {
                expected = !(await settingControl(page, name).isChecked());
                await settingControl(page, name).setChecked(expected);
            }
            await submit(page, form.getByRole('button', { name: 'Save', exact: true }));
            await page.reload();
            if (name === 'domains') await expect(formField(page, 'setting.domains')).toHaveValue(expected);
            else await expect(settingControl(page, name)).toBeChecked({ checked: expected });
        });
    }
});
