import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readdir, readFile } from 'node:fs/promises';

// Protect future specs from silently opting out of the global health guard.
test('every browser spec uses the automatic health fixture', async () => {
    const directory = new URL('../e2e/', import.meta.url);
    const files = await readdir(directory, { recursive: true });
    const specs = files.filter(file => file.endsWith('.spec.js'));
    assert.ok(specs.length > 0);
    for (const file of specs) {
        const source = await readFile(new URL(file, directory), 'utf8');
        assert.doesNotMatch(source, /from\s+['"]@playwright\/test['"]/, file);
        assert.match(source, /import\s*\{[^}]*\btest\b[^}]*\}\s*from\s*['"]\.\/helpers\.js['"]/, file);
    }
});
