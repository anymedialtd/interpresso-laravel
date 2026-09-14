import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
let source = await readFile(new URL('../../resources/js/modules/toast.js', import.meta.url), 'utf8');
const i18n = await readFile(new URL('../../resources/js/modules/i18n.js', import.meta.url), 'utf8');
source = source.replace("'./i18n.js'", `'data:text/javascript;base64,${Buffer.from(i18n).toString('base64')}'`);
const { initToasts } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);

for (const raw of [undefined, '', '{broken', 'null']) {
    test(`toast ${JSON.stringify(raw)} does not abort initialization and still registers later notifications`, t => {
        const listeners = [];
        globalThis.document = {
            getElementById: id => id === 'toast-data' ? { dataset: { toast: raw } } : null,
            addEventListener: (name, listener) => listeners.push({ name, listener }),
        };
        t.after(() => { delete globalThis.document; });
        assert.doesNotThrow(initToasts);
        assert.equal(listeners.length, 1);
        assert.equal(listeners[0].name, 'interpresso:toast');
        assert.doesNotThrow(() => listeners[0].listener({ detail: { message: 'Still usable' } }));
    });
}
