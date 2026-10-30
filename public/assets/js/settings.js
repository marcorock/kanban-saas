'use strict';
// Apenas melhorias progressivas: os POSTs e disclosures funcionam sem JS.
for (const toggle of document.querySelectorAll('[data-settings-password]')) {
    const input = document.getElementById(toggle.getAttribute('aria-controls'));
    if (!input) continue;
    toggle.hidden = false; input.closest('.sp-password').classList.add('is-enhanced');
    toggle.addEventListener('click', () => {
        const visible = input.type === 'password'; input.type = visible ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', String(visible)); toggle.setAttribute('aria-label', `${visible ? 'Ocultar' : 'Mostrar'} ${toggle.dataset.label}`);
        toggle.querySelector('[data-eye-show]').hidden = visible; toggle.querySelector('[data-eye-hide]').hidden = !visible;
    });
}
for (const form of document.querySelectorAll('[data-settings-form]')) {
    const button = form.querySelector('button[type="submit"]'); const label = button.textContent;
    form.addEventListener('submit', event => {
        if (form.getAttribute('aria-busy') === 'true') { event.preventDefault(); return; }
        form.setAttribute('aria-busy', 'true'); button.disabled = true; button.textContent = button.dataset.pending;
    });
    window.addEventListener('pageshow', () => { form.removeAttribute('aria-busy'); button.disabled = false; button.textContent = label; });
}
document.querySelector('[data-settings-errors]')?.focus();
