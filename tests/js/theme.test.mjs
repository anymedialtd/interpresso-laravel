import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const source = await readFile(new URL('../../resources/js/modules/theme.js', import.meta.url), 'utf8');
const { initTheme } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);

function environment(t, path, saved, legacyPath) {
    const cookies = new Map(legacyPath ? [[legacyPath, saved]] : []);
    const writes = [];
    const root = {
        dataset: { themeCookiePath: path, ...(saved ? { theme: saved } : {}) },
        classList: { toggle() {} },
    };
    const toggle = new EventTarget();
    const system = new EventTarget();
    system.matches = false;
    const storage = new Map();
    const document = {
        documentElement: root,
        getElementById: id => id === 'theme-toggle' ? toggle : null,
        set cookie(value) {
            writes.push(value);
            const [pair, ...attributes] = value.split('; ');
            const [name, theme] = pair.split('=');
            assert.equal(name, 'interpresso-color-theme');
            const options = Object.fromEntries(attributes.map(attribute => attribute.split('=')));
            if (options['Max-Age'] === '0') cookies.delete(options.Path);
            else cookies.set(options.Path, theme);
        },
    };
    globalThis.document = document;
    globalThis.location = { protocol: 'https:' };
    globalThis.window = { matchMedia: () => system };
    globalThis.localStorage = { getItem: key => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value) };
    t.after(() => {
        for (const key of ['document', 'location', 'window', 'localStorage']) delete globalThis[key];
    });
    return { root, cookies, storage, writes, toggle: () => toggle.dispatchEvent(new Event('click')) };
}

for (const saved of ['light', 'dark']) {
    test(`toggling saved ${saved} retires the cookie that would shadow the next server render`, t => {
        const page = environment(t, '/translator', saved, '/translator/');
        initTheme();
        const changed = saved === 'dark' ? 'light' : 'dark';
        page.toggle();
        assert.equal(page.root.dataset.theme, changed);
        assert.deepEqual([...page.cookies], [['/translator', changed]]);
        assert.equal(page.storage.get('color-theme'), changed);
        assert.ok(page.writes.every(value => value.endsWith('; SameSite=Lax; Secure')));
    });
}

test('scope cleanup follows the configured installation prefix', t => {
    const page = environment(t, '/project/translations', 'dark', '/project/translations/');
    page.cookies.set('/another-panel', 'light');
    initTheme();
    assert.deepEqual([...page.cookies], [['/another-panel', 'light'], ['/project/translations', 'dark']]);
});

test('a root installation writes a usable cookie when localStorage is unavailable', t => {
    const page = environment(t, '/', null, null);
    Object.defineProperty(globalThis, 'localStorage', {
        configurable: true,
        get() { throw new DOMException('Storage unavailable', 'SecurityError'); },
    });
    initTheme();
    assert.equal(page.cookies.size, 0);
    page.toggle();
    assert.deepEqual([...page.cookies], [['/', 'dark']]);
    assert.equal(page.writes.length, 1);
});
