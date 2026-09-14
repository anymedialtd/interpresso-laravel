export function initLocale() {
    const form = document.getElementById('interface-locale-form');
    const select = form?.querySelector('select[name="locale"]');
    if (!select || typeof form.requestSubmit !== 'function') return;

    select.addEventListener('change', () => form.requestSubmit());
    const button = form.querySelector('button[type="submit"]');
    if (button) button.hidden = true;
}
