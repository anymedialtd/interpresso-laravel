import { initToasts } from './modules/toast';
import { initModal } from './modules/modal';
import { initBatchProgress } from './modules/batch-progress';
import { initNotifications } from './modules/notifications';
import { initFilters } from './modules/filters';
import { initSearch } from './modules/search';
import { initTheme } from './modules/theme';
import { initLocale } from './modules/locale';

initTheme();
initLocale();
initToasts();
initFilters();
initSearch();
initModal();
initBatchProgress();
initNotifications();

document.querySelectorAll('form[method="POST"]').forEach(form => {
    form.addEventListener('submit', event => {
        if (form.dataset.submitting) { event.preventDefault(); return; }
        form.dataset.submitting = 'true';
        if (event.submitter) event.submitter.setAttribute('aria-busy', 'true');
        // Keep successful controls enabled until the browser has captured them.
        // This also preserves a submitter's formaction (the translate-all action).
        setTimeout(() => form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = true; }), 0);
    });
});
window.addEventListener('pageshow', event => {
    if (event.persisted) location.reload();
});
