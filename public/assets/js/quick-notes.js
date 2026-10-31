'use strict';
(() => {
    const dialog = document.querySelector('[data-note-dialog]');
    const convertDialog = document.querySelector('[data-note-convert-dialog]');
    if (!dialog || !convertDialog) return;
    const form = dialog.querySelector('form');
    const convertForm = convertDialog.querySelector('form');
    const text = form.elements.body;
    const destination = form.elements.project_id;
    const target = convertForm.elements.project_id;
    const column = convertForm.elements.column_id;
    const toast = document.querySelector('[data-note-toast]');
    let timer; let opener; let editing = false; let loading = 0; let columnsLoading = 0;
    function error(parent, message = '') { const node = parent.querySelector('[data-note-error]'); node.textContent = message; node.hidden = !message; }
    function announce(message) { clearTimeout(timer); toast.textContent = message; toast.hidden = false; timer = setTimeout(() => { toast.hidden = true; }, 6000); }
    async function get(path) {
        const response = await fetch(path, {credentials: 'same-origin', headers: {Accept: 'application/json'}, cache: 'no-store'});
        const result = await response.json(); if (!response.ok) throw new Error(result.message || 'Não foi possível carregar. Reabra a nota para tentar novamente.'); return result;
    }
    async function send(formNode) {
        const controller = new AbortController(); const timeout = setTimeout(() => controller.abort(), 15000);
        try {
            const response = await fetch(formNode.action, {method: 'POST', credentials: 'same-origin', headers: {Accept: 'application/json'}, body: new URLSearchParams(new FormData(formNode)), signal: controller.signal});
            let result;
            try { result = await response.json(); } catch { throw new Error('Não foi possível confirmar a alteração. Atualize a lista antes de tentar novamente.'); }
            if (!response.ok) throw new Error(result.message || 'Não foi possível salvar. Tente novamente.'); return result;
        } catch (issue) {
            if (issue.name === 'AbortError') throw new Error('Não foi possível confirmar o salvamento a tempo. Confira a lista antes de tentar novamente.');
            if (issue instanceof TypeError) throw new Error('Falha de conexão. Sua nota permanece no formulário.');
            throw issue;
        } finally { clearTimeout(timeout); }
    }
    function previewColor() { dialog.dataset.color = form.elements.color.value || 'yellow'; }
    form.addEventListener('change', previewColor);
    function close(modal) { if (modal.querySelector('form')?.dataset.busy) return; modal.close(); if (opener?.isConnected) opener.focus(); }
    async function refresh() {
        const section = document.querySelector('[data-note-section]'); if (!section) return;
        try {
            const response = await fetch(location.href, {credentials: 'same-origin', cache: 'no-store'});
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const fresh = page.querySelector('[data-note-section]'); if (!response.ok || !fresh) throw new Error();
            section.replaceWith(document.importNode(fresh, true));
        } catch { announce('Alteração salva. Atualize a página para ver a lista.'); }
    }
    async function openNote(button) {
        if (document.querySelector('dialog[open]')) return;
        if (editing) { form.reset(); text.value = ''; } editing = false; form.action = '/notes'; destination.disabled = false; document.getElementById('quick-note-title').textContent = 'Nota rápida';
        opener = button || document.activeElement; const sequence = ++loading;
        error(dialog); destination.replaceChildren(new Option('Sem projeto / Inbox pessoal', ''));
        previewColor(); dialog.showModal(); text.focus();
        try {
            const result = await get('/notes/projects'); if (sequence !== loading || !dialog.open) return;
            for (const item of result.projects) destination.add(new Option(item.name, String(item.id)));
            const selected = button?.dataset.noteProject || '';
            if ([...destination.options].some(option => option.value === selected)) destination.value = selected;
        } catch (issue) { if (dialog.open) error(dialog, issue.message + ' Você ainda pode salvar no Inbox pessoal.'); }
    }
    function editNote(button) {
        if (document.querySelector('dialog[open]')) return;
        ++loading; opener = button.closest('details')?.querySelector('summary') || button; editing = true; error(dialog);
        form.action = '/notes/' + button.dataset.noteEdit + '/update';
        const card = button.closest('.qn-card');
        text.value = card.querySelector('.qn-body').textContent;
        form.elements.title.value = card.dataset.noteTitle;
        form.elements.color.value = card.dataset.color;
        card.querySelector('details').open = false;
        const project = button.dataset.noteProject || '';
        destination.replaceChildren(new Option(project ? 'Projeto atual · nota privada' : 'Sem projeto / Inbox pessoal', project)); destination.disabled = true;
        document.getElementById('quick-note-title').textContent = 'Editar nota';
        previewColor(); dialog.showModal(); text.focus();
    }
    async function loadColumns() {
        const sequence = ++columnsLoading; const project = target.value;
        column.disabled = true; convertForm.querySelector('[type="submit"]').disabled = true;
        column.replaceChildren(new Option('Selecione uma coluna', ''));
        if (!project) return;
        try {
            const result = await get('/notes/columns?project_id=' + encodeURIComponent(project));
            if (sequence !== columnsLoading || target.value !== project || !convertDialog.open) return;
            for (const item of result.columns) column.add(new Option(item.name + (Number(item.is_done) ? ' (concluída)' : ''), String(item.id)));
            column.disabled = !result.columns.length; convertForm.querySelector('[type="submit"]').disabled = !result.columns.length;
            if (!result.columns.length) error(convertDialog, 'Este projeto ainda não tem colunas.');
        } catch (issue) { if (sequence === columnsLoading) error(convertDialog, issue.message); }
    }
    async function openConversion(button) {
        if (document.querySelector('dialog[open]')) return;
        opener = button; error(convertDialog); convertForm.action = '/notes/' + button.dataset.noteConvert + '/convert';
        target.replaceChildren(new Option('Selecione um projeto', '')); column.replaceChildren(new Option('Selecione um projeto primeiro', '')); column.disabled = true;
        convertForm.querySelector('[type="submit"]').disabled = true; convertDialog.showModal();
        try {
            const result = await get('/notes/projects?writable=1'); if (!convertDialog.open) return;
            const fixed = button.dataset.noteProject;
            for (const item of result.projects) if (!fixed || String(item.id) === fixed) target.add(new Option(item.name, String(item.id)));
            if (fixed) { target.value = fixed; await loadColumns(); column.focus(); }
            else target.focus();
            if (target.options.length === 1) error(convertDialog, 'Nenhum projeto ativo permite criar tarefas. Crie um projeto ou revise seu acesso.');
        } catch (issue) { error(convertDialog, issue.message); }
    }
    document.addEventListener('click', event => {
        if (!(event.target instanceof Element)) return;
        const button = event.target.closest('[data-note-open]');
        if (button) { event.preventDefault(); openNote(button); return; }
        const edit = event.target.closest('[data-note-edit]'); if (edit) { editNote(edit); return; }
        const conversion = event.target.closest('[data-note-convert]');
        if (conversion) openConversion(conversion);
        const cancel = event.target.closest('[data-note-close]');
        if (cancel) close(cancel.closest('dialog'));
    });
    document.addEventListener('keydown', event => {
        if (!(event.target instanceof Element) || event.isComposing || event.repeat || document.querySelector('dialog[open]')) return;
        const editable = event.target.closest('input,textarea,select,[contenteditable]:not([contenteditable="false"]),[role="textbox"]');
        if (editable) return;
        const key = event.key.toLowerCase();
        if ((key === 'k' && (event.ctrlKey || event.metaKey) && !event.altKey && !event.shiftKey) || (key === 'n' && !event.ctrlKey && !event.metaKey && !event.altKey)) { event.preventDefault(); openNote(); }
    });
    text.addEventListener('keydown', event => { if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) { event.preventDefault(); form.requestSubmit(); } });
    for (const modal of [dialog, convertDialog]) modal.addEventListener('cancel', event => { if (modal.querySelector('form')?.dataset.busy) { event.preventDefault(); return; } if (opener?.isConnected) setTimeout(() => opener.focus(), 0); });
    async function submit(event, modal, currentForm, converted) {
        event.preventDefault(); const submitter = currentForm.querySelector('[type="submit"]'); if (submitter.disabled) return;
        submitter.disabled = true; currentForm.dataset.busy = 'true'; for (const cancel of modal.querySelectorAll('[data-note-close]')) cancel.disabled = true; error(modal);
        try {
            const result = await send(currentForm); delete currentForm.dataset.busy; if (!converted) form.reset(); close(modal); announce(result.message); await refresh();
        } catch (issue) { error(modal, issue.message || 'Não foi possível salvar. Sua nota permanece no formulário.'); }
        finally { delete currentForm.dataset.busy; submitter.disabled = false; for (const cancel of modal.querySelectorAll('[data-note-close]')) cancel.disabled = false; }
    }
    form.addEventListener('submit', event => submit(event, dialog, form, false));
    convertForm.addEventListener('submit', event => submit(event, convertDialog, convertForm, true));
    target.addEventListener('change', () => { error(convertDialog); loadColumns(); });
    document.addEventListener('submit', async event => {
        const deletion = event.target.closest('[data-note-delete]'); if (!deletion) return;
        event.preventDefault(); if (!confirm('Excluir esta nota?')) return;
        const button = deletion.querySelector('button'); if (button.disabled) return; button.disabled = true;
        try { const result = await send(deletion); announce(result.message); await refresh(); }
        catch (issue) { announce(issue.message || 'Não foi possível excluir. Tente novamente.'); button.disabled = false; }
    });
})();
