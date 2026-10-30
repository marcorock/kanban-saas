'use strict';

for (const form of document.querySelectorAll('[data-members-form]')) {
    const button = form.querySelector('button[type="submit"]');
    if (!button) continue;
    const label = button.textContent;
    form.addEventListener('submit', event => {
        if (form.getAttribute('aria-busy') === 'true') { event.preventDefault(); return; }
        form.setAttribute('aria-busy', 'true'); button.disabled = true; button.textContent = button.dataset.pending;
    });
    window.addEventListener('pageshow', () => { form.removeAttribute('aria-busy'); button.disabled = false; button.textContent = label; });
}
document.querySelector('[data-members-errors]')?.focus();
