import { test, expect, login, TRANSLATOR, tableRow, changeSetting, submit } from './helpers.js';

test.describe('notification dropdown', () => {
    test.use({ fixtureScenario: 'notifications' });
    test('opens and closes, marks one read, then marks all read with persistence', async ({ page }) => {
        await login(page);
        await page.getByRole('button', { name: 'Notifications (2)', exact: true }).click();
        const panel = page.locator('#notification-list');
        await expect(panel).toBeVisible();
        await page.getByRole('button', { name: 'Notifications (2)', exact: true }).click();
        await expect(panel).toBeHidden();
        await page.getByRole('button', { name: 'Notifications (2)', exact: true }).click();
        const item = panel.getByRole('listitem').filter({ hasText: 'First browser notification' });
        await item.getByRole('button', { name: 'Mark as read', exact: true }).click();
        await expect(page.getByRole('button', { name: 'Notifications (1)', exact: true })).toBeVisible();
        await page.reload();
        await page.getByRole('button', { name: 'Notifications (1)', exact: true }).click();
        await expect(item).toHaveCount(0);
        await expect(panel).toContainText('Second browser notification');
        await panel.getByRole('button', { name: 'Mark all as read', exact: true }).click();
        await expect(page.getByRole('button', { name: 'Notifications (0)', exact: true })).toBeVisible();
        await Promise.all([page.waitForResponse(response => response.url().endsWith('/notifications') && response.status() === 200), page.reload()]);
        await page.getByRole('button', { name: 'Notifications (0)', exact: true }).click();
        await expect(panel.getByRole('listitem')).toHaveCount(0);
    });
});

test('pending notification reaches the assigned translator and respects its setting', async ({ page }) => {
    await login(page);
    await page.getByRole('navigation').getByRole('link', { name: 'Settings', exact: true }).click();
    await changeSetting(page, 'enable_pending_notifications', false);
    await page.getByRole('navigation').getByRole('link', { name: 'Translators', exact: true }).click();
    await tableRow(page, TRANSLATOR.email).getByRole('link', { name: 'Edit', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Send pending translations notification', exact: true })).toHaveCount(0);
    await page.getByRole('navigation').getByRole('link', { name: 'Settings', exact: true }).click();
    await changeSetting(page, 'enable_pending_notifications', true);
    await page.getByRole('navigation').getByRole('link', { name: 'Translators', exact: true }).click();
    await tableRow(page, TRANSLATOR.email).getByRole('link', { name: 'Edit', exact: true }).click();
    await submit(page, page.getByRole('button', { name: 'Send pending translations notification', exact: true }));
    await expect(page.getByText(`Notification has been sent to ${TRANSLATOR.email}.`, { exact: true })).toBeVisible();
    await page.reload();
    await page.getByRole('button', { name: 'Logout', exact: true }).click();
    await login(page, TRANSLATOR);
    await page.getByRole('button', { name: 'Notifications (1)', exact: true }).click();
    await expect(page.locator('#notification-list')).toContainText('You have pending translations. Total: 2.');
    await page.reload();
    await page.getByRole('button', { name: 'Notifications (1)', exact: true }).click();
    await expect(page.locator('#notification-list')).toContainText('You have pending translations. Total: 2.');
});
