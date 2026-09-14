import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
let source = await readFile(new URL('../../resources/js/modules/http.js', import.meta.url), 'utf8');
const i18n = await readFile(new URL('../../resources/js/modules/i18n.js', import.meta.url), 'utf8');
source = source.replace("'./i18n.js'", `'data:text/javascript;base64,${Buffer.from(i18n).toString('base64')}'`);
const { json } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);

for (const body of ['', '{broken', '<html>Error</html>']) {
    test(`invalid JSON ${JSON.stringify(body)} rejects instead of becoming a successful empty object`, async t => {
        t.mock.method(globalThis, 'fetch', async () => new Response(body, { status: 200 }));
        await assert.rejects(json('/suggest'), { message: 'The server returned an invalid response. Please try again.', status: 200 });
    });
}
for (const status of [401, 403, 419, 502]) {
    test(`HTTP ${status} preserves status for polling shutdown even with malformed JSON`, async t => {
        t.mock.method(globalThis, 'fetch', async () => new Response('', { status }));
        await assert.rejects(json('/notifications'), { status });
    });
}
test('successful suggestions preserve the returned string', async t => {
    t.mock.method(globalThis, 'fetch', async () => Response.json({ value: 'Translated :name' }));
    assert.deepEqual(await json('/suggest'), { value: 'Translated :name' });
});
test('structured HTTP errors preserve their message and status', async t => {
    t.mock.method(globalThis, 'fetch', async () => Response.json({ message: 'Suggestion service unavailable.' }, { status: 502 }));
    await assert.rejects(json('/suggest'), { message: 'Suggestion service unavailable.', status: 502 });
});
test('cancelling a response body stays an AbortError so hidden tabs and closed modals stay quiet', async t => {
    const abort = new DOMException('Cancelled', 'AbortError');
    t.mock.method(globalThis, 'fetch', async () => ({ status: 200, ok: true, json: async () => { throw abort; } }));
    await assert.rejects(json('/notifications'), error => error === abort);
});
