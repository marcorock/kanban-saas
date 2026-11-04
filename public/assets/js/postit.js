'use strict';
(() => {
    const themes = {green: 'priority-baixa', yellow: 'priority-media', orange: 'priority-alta'};
    const dateFormat = new Intl.DateTimeFormat('pt-BR', {timeZone: 'America/Sao_Paulo', day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'});
    for (const form of document.querySelectorAll('[data-postit-form]')) {
        function update(date) {
            form.classList.remove(...Object.values(themes));
            form.classList.add(themes[form.elements.color.value] || themes.yellow);
            const project = form.elements.project_id; const visibility = form.elements.visibility;
            const shared = visibility.querySelector('[value="project"]'); shared.disabled = !project.value;
            if (shared.disabled && visibility.value === 'project') visibility.value = 'private';
            form.querySelector('[data-note-privacy-help]').textContent = visibility.value === 'project' ? 'Visível aos membros do projeto.' : 'Só você vê esta nota.';
            const time = form.querySelector('[data-postit-date]'); const parsed = new Date(date || time.dateTime);
            if (!Number.isNaN(parsed.getTime())) { time.dateTime = parsed.toISOString(); time.textContent = dateFormat.format(parsed).replace(', ', ' '); }
        }
        form.addEventListener('change', () => update());
        form.addEventListener('reset', () => queueMicrotask(() => update()));
        form.addEventListener('postit:refresh', event => update(event.detail?.date));
        // Impede o submit implícito pelo título; textarea mantém Enter nativo.
        form.elements.title.addEventListener('keydown', event => { if (event.key === 'Enter' && !event.isComposing) event.preventDefault(); });
        update();
    }
})();
