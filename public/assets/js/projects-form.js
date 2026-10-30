'use strict';

const projectForm = document.querySelector('[data-project-form]');
if (projectForm) {
    for (const input of projectForm.querySelectorAll('[data-counted]')) {
        const counter = projectForm.querySelector(`[data-count-for="${input.id}"]`);
        if (!counter) continue;
        const update = () => { counter.textContent = `${[...input.value].length} / ${input.maxLength}`; };
        counter.hidden = false; update(); input.addEventListener('input', update);
    }
    const start = projectForm.querySelector('#starts_on');
    const due = projectForm.querySelector('#due_on');
    const updateDateLimit = () => { due.min = start.value || '1000-01-01'; };
    updateDateLimit(); start.addEventListener('input', updateDateLimit);
    const button = projectForm.querySelector('[data-project-submit]');
    const label = button.querySelector('[data-project-submit-label]');
    projectForm.addEventListener('submit', event => {
        if (projectForm.getAttribute('aria-busy') === 'true') { event.preventDefault(); return; }
        projectForm.setAttribute('aria-busy', 'true'); button.disabled = true; label.textContent = 'Salvando…';
    });
    window.addEventListener('pageshow', () => {
        projectForm.removeAttribute('aria-busy'); button.disabled = false; label.textContent = 'Salvar projeto';
    });
    document.querySelector('[data-project-errors]')?.focus();
}
