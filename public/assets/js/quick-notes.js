'use strict';
(() => {
    const dialog = document.querySelector('[data-note-dialog]');
    const convertDialog = document.querySelector('[data-note-convert-dialog]');
    const transferDialog = document.querySelector('[data-note-transfer-dialog]');
    if (!dialog || !convertDialog || !transferDialog) return;
    const form = dialog.querySelector('form');
    const convertForm = convertDialog.querySelector('form');
    const transferForm = transferDialog.querySelector('form');
    let transferLoading = 0; let mutations = 0; let operation = 0; let noteSync; let syncDenied = false; let syncFailed = false;
    const text = form.elements.body;
    const destination = form.elements.project_id;
    const visibility = form.elements.visibility;
    const target = convertForm.elements.project_id;
    function syncVisibility() {
        const option = visibility.querySelector('[value="project"]'); option.disabled = !destination.value;
        if (option.disabled && visibility.value === 'project') visibility.value = 'private';
        dialog.querySelector('[data-note-privacy-help]').textContent = (visibility.value === 'project' ? 'Visível aos membros do projeto.' : 'Só você vê esta nota.') + ' Enter salva · Shift+Enter quebra a linha.';
    }
    destination.addEventListener('change', syncVisibility); visibility.addEventListener('change', syncVisibility);
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
        mutations++; operation++;
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
        } finally { clearTimeout(timeout); mutations--; noteSync?.requestNow(); }
    }
    function previewColor() { dialog.dataset.color = form.elements.color.value || 'yellow'; }
    form.addEventListener('change', previewColor);
    function close(modal) { if (modal.querySelector('form')?.dataset.busy) return; modal.close(); if (opener?.isConnected) opener.focus(); }
    function sectionUrl(resource) {
        const section = document.querySelector('[data-note-section]');
        const url = new URL('/notes/' + resource, location.origin);
        if (section.dataset.noteProject) url.searchParams.set('project_id', section.dataset.noteProject);
        url.searchParams.set('notes_page', section.dataset.notePage);
        return url;
    }
    const focusable = 'button,a,input,select,textarea,summary,[tabindex="0"]';
    function reference(section, node) {
        if (!node || !section.contains(node)) return null;
        const card = node.closest('[data-note-id]');
        return card ? {id: card.dataset.noteId, index: [...card.querySelectorAll(focusable)].indexOf(node)} : {create: node.hasAttribute('data-note-open'), pagination: node.closest('.qn-pagination') ? node.textContent : null};
    }
    function resolve(section, ref) {
        if (!ref) return null;
        if (ref.create) return section.querySelector('[data-note-open]');
        if (ref.pagination) return [...section.querySelectorAll('.qn-pagination a')].find(link => link.textContent === ref.pagination);
        return section.querySelector(`[data-note-id="${ref.id}"]`)?.querySelectorAll(focusable)[ref.index];
    }
    function applySection(html) {
        const section = document.querySelector('[data-note-section]');
        const fresh = new DOMParser().parseFromString(html, 'text/html').querySelector('[data-note-section]');
        if (!fresh) throw new Error('Lista indisponível.');
        const position = {x: window.scrollX, y: window.scrollY};
        const active = reference(section, document.activeElement); const origin = reference(section, opener);
        const cards = [...section.querySelectorAll('[data-note-id]')].map(card => ({id: card.dataset.noteId, open: card.querySelector('details')?.open, scroll: card.querySelector('.qn-body').scrollTop}));
        section.replaceWith(document.importNode(fresh, true));
        const current = document.querySelector('[data-note-section]');
        for (const state of cards) {
            const card = current.querySelector(`[data-note-id="${state.id}"]`); if (!card) continue;
            const menu = card.querySelector('details'); if (menu) menu.open = Boolean(state.open);
            card.querySelector('.qn-body').scrollTop = state.scroll;
        }
        if (origin) opener = resolve(current, origin) || current;
        if (active) (resolve(current, active) || current).focus({preventScroll: true});
        window.scrollTo(position.x, position.y);
    }
    async function syncNotes(signal) {
        if (syncDenied || !document.querySelector('[data-note-section]')) return;
        const generation = operation;
        const meta = await FluxoSync.json(sectionUrl('version'), signal);
        if (meta.token === document.querySelector('[data-note-section]').dataset.noteToken || mutations || generation !== operation) return;
        const result = await FluxoSync.json(sectionUrl('state'), signal);
        if (mutations || generation !== operation || signal.aborted) return;
        applySection(result.html); document.querySelector('[data-note-section]').dataset.noteToken = result.token;
    }
    async function refresh() {
        if (!document.querySelector('[data-note-section]')) return;
        document.querySelector('[data-note-section]').dataset.noteToken = '';
        if (noteSync) noteSync.requestNow();
        else {
            try { const response = await get(sectionUrl('state')); applySection(response.html); }
            catch { announce('Alteração salva. Atualize a página para ver a lista.'); }
        }
    }
    async function openNote(button) {
        if (document.querySelector('dialog[open]')) return;
        if (editing) { form.reset(); text.value = ''; } editing = false; form.elements.revision.value = ''; form.action = '/notes'; destination.disabled = false; document.getElementById('quick-note-title').textContent = 'Nota rápida';
        opener = button || document.activeElement; const sequence = ++loading;
        error(dialog); destination.replaceChildren(new Option('Sem projeto / Inbox pessoal', ''));
        previewColor(); syncVisibility(); dialog.showModal(); text.focus();
        try {
            const result = await get('/notes/projects'); if (sequence !== loading || !dialog.open) return;
            for (const item of result.projects) destination.add(new Option(item.name, String(item.id)));
            const selected = button?.dataset.noteProject || '';
            if ([...destination.options].some(option => option.value === selected)) destination.value = selected;
            syncVisibility();
        } catch (issue) { if (dialog.open) error(dialog, issue.message + ' Você ainda pode salvar no Inbox pessoal.'); }
    }
    async function editNote(button) {
        if (document.querySelector('dialog[open]')) return;
        const sequence = ++loading; opener = button.closest('details')?.querySelector('summary') || button; editing = true; error(dialog);
        form.action = '/notes/' + button.dataset.noteEdit + '/update';
        const card = button.closest('.qn-card');
        text.value = card.querySelector('.qn-body').textContent;
        form.elements.title.value = card.dataset.noteTitle;
        form.elements.color.value = card.dataset.color;
        form.elements.visibility.value = card.dataset.noteVisibility;
        form.elements.revision.value = card.dataset.noteRevision;
        card.querySelector('details').open = false;
        const project = button.dataset.noteProject || '';
        destination.replaceChildren(new Option(project ? 'Projeto atual' : 'Sem projeto / Inbox pessoal', project)); destination.disabled = false;
        document.getElementById('quick-note-title').textContent = 'Editar nota';
        previewColor(); syncVisibility(); dialog.showModal(); text.focus();
        try {
            const result = await get('/notes/projects'); if (sequence !== loading || !dialog.open) return;
            destination.replaceChildren(new Option('Sem projeto / Inbox pessoal', ''));
            for (const item of result.projects) destination.add(new Option(item.name, String(item.id)));
            if (project && ![...destination.options].some(option => option.value === project)) destination.add(new Option('Projeto atual (arquivado)', project));
            destination.value = project; syncVisibility();
        } catch (issue) { if (dialog.open && sequence === loading) error(dialog, issue.message); }
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
        const project = button.dataset.noteProject; if (!project) { announce('Vincule a nota a um projeto antes de converter.'); return; }
        const card = button.closest('.qn-card'); opener = button; error(convertDialog); convertForm.action = '/notes/' + button.dataset.noteConvert + '/convert';
        target.value = project; convertForm.elements.title.value = card.dataset.noteTitle; convertForm.elements.revision.value = card.dataset.noteRevision;
        column.replaceChildren(new Option('Carregando colunas', '')); column.disabled = true;
        convertForm.querySelector('[type="submit"]').disabled = true; convertDialog.showModal(); convertForm.elements.title.focus(); await loadColumns();
    }
    async function openTransfer(button) {
        if (document.querySelector('dialog[open]')) return;
        const card = button.closest('.qn-card'); const sequence = ++transferLoading;
        opener = button.closest('details')?.querySelector('summary') || button; card.querySelector('details').open = false;
        transferForm.reset(); error(transferDialog); transferForm.action = '/notes/' + button.dataset.noteTransfer + '/transfer';
        transferForm.elements.project_id.value = button.dataset.noteProject; transferForm.elements.revision.value = card.dataset.noteRevision;
        const recipient = transferForm.elements.recipient_id; recipient.replaceChildren(new Option('Selecione um membro', ''));
        transferForm.querySelector('[type="submit"]').disabled = true; transferDialog.showModal(); recipient.focus();
        try {
            const result = await get('/notes/' + button.dataset.noteTransfer + '/recipients'); if (!transferDialog.open || sequence !== transferLoading) return;
            for (const person of result.recipients) recipient.add(new Option(person.name, String(person.id)));
            transferForm.querySelector('[type="submit"]').disabled = !result.recipients.length;
            if (!result.recipients.length) error(transferDialog, 'Nenhum outro membro disponível neste projeto.');
        } catch (issue) { if (transferDialog.open && sequence === transferLoading) error(transferDialog, issue.message); }
    }
    document.addEventListener('click', event => {
        if (!(event.target instanceof Element)) return;
        const button = event.target.closest('[data-note-open]');
        if (button) { event.preventDefault(); openNote(button); return; }
        const edit = event.target.closest('[data-note-edit]'); if (edit) { editNote(edit); return; }
        const transfer = event.target.closest('[data-note-transfer]'); if (transfer) { openTransfer(transfer); return; }
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
    for (const modal of [dialog, convertDialog, transferDialog]) modal.addEventListener('cancel', event => { if (modal.querySelector('form')?.dataset.busy) { event.preventDefault(); return; } if (opener?.isConnected) setTimeout(() => opener.focus(), 0); });
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
    transferForm.addEventListener('submit', event => submit(event, transferDialog, transferForm, true));
    target.addEventListener('change', () => { error(convertDialog); loadColumns(); });
    document.addEventListener('submit', async event => {
        const deletion = event.target.closest('[data-note-delete]'); if (!deletion) return;
        event.preventDefault(); if (!confirm('Excluir esta nota?')) return;
        const button = deletion.querySelector('button'); if (button.disabled) return; button.disabled = true;
        try { const result = await send(deletion); announce(result.message); await refresh(); }
        catch (issue) { announce(issue.message || 'Não foi possível excluir. Tente novamente.'); button.disabled = false; }
    });
    if (window.FluxoSync && document.querySelector('[data-note-section]')) noteSync = FluxoSync.create({
        busy: () => mutations > 0,
        run: syncNotes,
        onError(issue) {
            if ([401, 403, 404].includes(issue.status)) {
                syncDenied = true;
                const section = document.querySelector('[data-note-section]');
                const notice = document.createElement('p'); notice.className = 'qn-help'; notice.setAttribute('role', 'status');
                notice.textContent = 'Seu acesso às notas não está mais disponível. Entre novamente ou verifique sua participação no projeto.';
                section.replaceChildren(notice);
                if (dialog.open || convertDialog.open || transferDialog.open) announce('O acesso à lista mudou. Seus rascunhos foram preservados; confira o destino antes de salvar.');
            } else if (!syncFailed) { syncFailed = true; announce('A sincronização das notas foi interrompida. Tentaremos reconectar; seus rascunhos foram mantidos.'); }
        },
        onRecover() { syncFailed = false; announce('Sincronização das notas restabelecida.'); }
    });
})();
