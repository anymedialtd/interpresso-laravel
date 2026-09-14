// Text is escaped by Blade in the page attribute, keeping the strict CSP.
// English fallbacks also support older published host templates.
export function uiText(key, fallback) {
    try {
        const messages = JSON.parse(globalThis.document?.documentElement?.dataset?.uiMessages || '{}');
        return typeof messages[key] === 'string' ? messages[key] : fallback;
    } catch {
        return fallback;
    }
}
