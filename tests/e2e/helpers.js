import { test as base, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { join } from 'node:path';
import { readFileSync, writeFileSync } from 'node:fs';

export { expect };

// Automatic and context-wide: installed before the first page, retained across
// redirects, and also applied to popups/secondary tabs. There are no allowlists.
export const test = base.extend({
    fixtureScenario: ['base', { option: true }],
    queueConnection: ['sync', { option: true }],
    appLocale: ['en', { option: true }],
    database: [async ({ fixtureScenario, queueConnection, appLocale }, use) => {
        resetDatabase(fixtureScenario);
        writeFileSync(join(__dirname, '.data/locale'), appLocale);
        if (queueConnection === 'database') writeFileSync(join(__dirname, '.data/queue-connection'), 'database');
        await use();
    }, { auto: true }],
    browserHealth: [async ({ context, baseURL }, use, testInfo) => {
        const failures = [];
        const origin = new URL(baseURL).origin;
        const record = (kind, detail) => failures.push({ kind, ...detail });
        const watch = page => {
            page.on('pageerror', error => record('pageerror', { url: page.url(), message: error.stack || error.message }));
            page.on('console', message => {
                if (message.type() === 'error') record('console.error', { url: page.url(), message: message.text(), location: message.location() });
            });
        };
        context.on('page', watch);
        context.pages().forEach(watch);
        context.on('response', response => {
            if (new URL(response.url()).origin === origin && response.status() >= 400) {
                record('http', { url: response.url(), status: response.status(), method: response.request().method() });
            }
        });
        await context.exposeBinding('__recordBrowserCspViolation', ({ frame }, violation) => {
            record('securitypolicyviolation', { frame: frame.url(), ...violation });
        });
        await context.addInitScript(() => {
            document.addEventListener('securitypolicyviolation', event => {
                window.__recordBrowserCspViolation({
                    url: event.documentURI, directive: event.effectiveDirective,
                    blocked: event.blockedURI, source: event.sourceFile, line: event.lineNumber,
                });
            });
        });
        await use();
        if (failures.length) {
            await testInfo.attach('browser-health', { body: JSON.stringify(failures, null, 2), contentType: 'application/json' });
        }
        expect(failures, 'Browser health: no JavaScript errors, console errors, CSP violations or same-origin HTTP failures').toEqual([]);
    }, { auto: true }],
});

// Created by the package's own create_admin_translator migration, so the e2e
// database needs no seeding beyond migrate:fresh.
export const ADMIN = {
    email: 'admin@admin.com',
    password: 'aaaaaaaa',
};

export const TRANSLATOR = {
    email: 'translator@example.test',
    password: 'translator-password',
};

// The file database is shared with the server, including a reused server.
// Keep workers: 1. Reset before login so no test depends on another test's edits.
export function resetDatabase(scenario = 'base') {
    const output = execFileSync(join(__dirname, 'seed.sh'), {
        encoding: 'utf8',
        stdio: 'pipe',
        timeout: 30_000,
        env: { ...process.env, E2E_SCENARIO: scenario },
    });
    // Tinker can print a PHP exception without returning a failing exit code.
    if (!output.includes('seeded: 2 languages, admin + non-admin, 6 translations')) {
        throw new Error(`E2E fixture setup did not finish:\n${output}`);
    }
}

export function tableRow(page, value) {
    return page.getByRole('row').filter({ has: page.getByRole('cell', { name: value, exact: true }) });
}

export async function submit(page, button) {
    await Promise.all([page.waitForNavigation(), button.click()]);
}

export function runWorker(once = false) {
    execFileSync('bash', [join(__dirname, 'work.sh'), ...(once ? ['--once'] : [])], { encoding: 'utf8', timeout: 30_000 });
}

export async function submitBatch(page, button) {
    await submit(page, button);
    runWorker();
}

export function databaseRows(sql) {
    const tables = JSON.parse(readFileSync(join(__dirname, '.data/tables.json'), 'utf8'));
    sql = sql.replace(/\{(translations|settings)\}/g, (_, table) => `"${tables[table].replaceAll('"', '""')}"`);
    return JSON.parse(execFileSync('php', ['-r', '$db = new PDO("sqlite:".$argv[1]); echo json_encode($db->query($argv[2])->fetchAll(PDO::FETCH_ASSOC), JSON_THROW_ON_ERROR);',
        join(__dirname, '.data/e2e.sqlite'), sql], { encoding: 'utf8' }));
}

export async function changeSetting(page, name, enabled) {
    const control = settingControl(page, name);
    if (await control.isChecked() !== enabled) {
        await Promise.all([
            page.waitForNavigation(),
            page.locator(`[id="setting.${name}"]`).locator('label').click(),
        ]);
    }
    await expect(control).toBeChecked({ checked: enabled });
}

// Keep using the stable wrapper IDs, then native roles/types. Labels are also
// associated with the real input; selectors do not depend on framework state.
export function formField(page, id) {
    return page.locator(`[id="${id}"]`).locator('input');
}

export function settingControl(page, name) {
    return page.locator(`[id="setting.${name}"]`).getByRole('checkbox');
}

export async function openTranslations(page, language = 'English') {
    await page.getByRole('navigation').getByRole('link', { name: 'Languages', exact: true }).click();
    const row = page.getByRole('row').filter({
        has: page.getByRole('cell', { name: language, exact: true }),
    });
    // The language list is paginated by code; larger fixtures can put this
    // language on a later page. Find it through the real search control.
    if (await row.count() === 0) {
        await page.getByRole('searchbox').fill(language);
        await submit(page, page.getByRole('button', { name: 'Search', exact: true }));
    }
    await row.getByRole('link', { name: 'View', exact: true }).click();
    await expect(page.getByRole('heading', { name: `Translations ${language}`, exact: false })).toBeVisible();
    await expectNoServerError(page);
}

/**
 * Log in through the real form rather than by seeding a session, so the test
 * exercises the actual submit path. A control that renders but is not wired up
 * is precisely the failure these tests exist to catch.
 */
export async function login(page, credentials = ADMIN) {
    await page.goto('/translator/login');
    await page.fill('input#email', credentials.email);
    await page.fill('input#password', credentials.password);
    await page.locator('form').filter({ has: page.locator('input#email') }).getByRole('button').click();
    await page.waitForURL(/\/translator\/(languages|translators)/, { timeout: 15_000 });
}

/** Assert the page is not an error page. */
export async function expectNoServerError(page) {
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
    await expect(page.locator('body')).not.toContainText('SQLSTATE');
}
