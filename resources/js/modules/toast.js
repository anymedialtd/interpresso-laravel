import { uiText } from './i18n.js';
const TYPES = ['SUCCESS', 'DELETED', 'INFO', 'WARNING'];
const colors = {
    SUCCESS: 'alert-success',
    DELETED: 'alert-error',
    INFO: 'alert-info',
    WARNING: 'alert-warning',
};

export function toast(message, type = 'SUCCESS', duration = 3000) {
    const host = document.getElementById('toasts');
    if (!host || !message) return;
    if (!TYPES.includes(type)) type = 'INFO';
    const element = document.createElement('div');
    element.className = `alert ${type} alert-horizontal shadow-lg max-w-md ${colors[type]}`;
    element.setAttribute('role', 'status');
    const text = document.createElement('span');
    text.textContent = message;
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn btn-ghost btn-sm';
    close.textContent = uiText('close', 'Close');
    close.setAttribute('aria-label', uiText('dismiss_notification', 'Dismiss notification'));
    const timer = setTimeout(() => element.remove(), Number.isFinite(duration) ? Math.max(0, duration) : 3000);
    close.addEventListener('click', () => { clearTimeout(timer); element.remove(); });
    element.append(text, close);
    host.append(element);
}

export function initToasts() {
    // ?? only guards null/undefined. An empty attribute yields '', and
    // JSON.parse('') throws - an uncaught throw here aborts the rest of the
    // bundle's initialisation, which previously left every dropdown dead.
    const raw = document.getElementById('toast-data')?.dataset.toast;
    let data = null;

    try {
        data = JSON.parse(raw || 'null');
    } catch {
        data = null;
    }
    if (data) toast(data.message, data.type, data.duration);
    document.addEventListener('interpresso:toast', ({ detail }) => toast(detail.message, detail.type, detail.duration));
}
