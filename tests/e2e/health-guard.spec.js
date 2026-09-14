import { test, expect } from './helpers.js';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { join } from 'node:path';

test('health guard rejects each failure class and preserves a healthy control', async () => {
    test.setTimeout(60_000);
    let report;
    try {
        await promisify(execFile)(process.execPath, [join(__dirname, '../../node_modules/@playwright/test/cli.js'), 'test', '--config', join(__dirname, 'canaries/playwright.config.js')], {
            timeout: 55_000, maxBuffer: 4 * 1024 * 1024,
            env: { ...process.env, CI: '', FORCE_COLOR: '0', PLAYWRIGHT_JSON_OUTPUT_NAME: '', PLAYWRIGHT_JSON_OUTPUT_FILE: '' },
        });
        throw new Error('The health canaries unexpectedly passed.');
    } catch (error) {
        expect(error.code).toBe(1);
        report = JSON.parse(error.stdout);
    }
    const specs = report.suites.flatMap(suite => suite.specs);
    expect(specs).toHaveLength(5);
    expect(specs.find(spec => spec.title === 'healthy control').tests[0].results[0].status).toBe('passed');
    for (const [name, kind] of [['pageerror before initialization survives navigation', 'pageerror'], ['console.error in secondary tab', 'console.error'], ['securitypolicyviolation', 'securitypolicyviolation'], ['same-origin HTTP failure', 'http']]) {
        const result = specs.find(spec => spec.title === name).tests[0].results[0];
        expect(result.status, name).toBe('failed');
        expect(result.errors.some(error => error.message.includes('Browser health:')), name).toBe(true);
        const attachment = result.attachments.find(attachment => attachment.name === 'browser-health');
        expect(attachment, name).toBeTruthy();
        const failures = JSON.parse(Buffer.from(attachment.body, 'base64').toString());
        expect(failures.some(failure => failure.kind === kind), name).toBe(true);
    }
});
