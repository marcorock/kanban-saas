'use strict';

// Melhorias progressivas: os formulários continuam funcionando sem JavaScript.
for (const toggle of document.querySelectorAll('[data-password-toggle]')) {
    const input = document.getElementById(toggle.getAttribute('aria-controls'));
    if (!input) continue;
    toggle.hidden = false;
    input.closest('.ds-password').classList.add('is-enhanced');
    toggle.addEventListener('click', () => {
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', String(show));
        toggle.setAttribute('aria-label', `${show ? 'Ocultar' : 'Mostrar'} ${toggle.dataset.label}`);
        toggle.querySelector('[data-eye-show]').hidden = show;
        toggle.querySelector('[data-eye-hide]').hidden = !show;
    });
}

for (const form of document.querySelectorAll('[data-auth-form]')) {
    const button = form.querySelector('[data-submit]');
    const label = button?.querySelector('[data-submit-label]');
    if (!button || !label) continue;
    const original = label.textContent;
    form.addEventListener('submit', event => {
        if (form.getAttribute('aria-busy') === 'true') { event.preventDefault(); return; }
        form.setAttribute('aria-busy', 'true');
        button.disabled = true;
        label.textContent = button.dataset.pendingLabel;
    });
    // Ao voltar pelo histórico, o formulário precisa poder ser enviado novamente.
    window.addEventListener('pageshow', () => {
        form.removeAttribute('aria-busy');
        button.disabled = false;
        label.textContent = original;
    });
}

document.querySelector('[data-error-summary]')?.focus();
