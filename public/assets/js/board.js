'use strict';
(() => {
    const root = document.getElementById('kanban');
    if (!root) return;
    const message = document.getElementById('board-message');
    let board = JSON.parse(document.getElementById('board-state').textContent);
    let sortables = [];
    let saving = false;
    const panel = document.getElementById('task-panel');
    const panelContent = document.getElementById('task-content');
    const taskMessage = document.getElementById('task-message');
    const dirtyForms = new Set();
    let taskId = null;
    let taskDetails = null;
    let taskRequest = 0;
    const priorities = {low: 'Baixa', normal: 'Normal', high: 'Alta', urgent: 'Urgente'};
    const filters = {priority: document.getElementById('filter-priority'), assignee: document.getElementById('filter-assignee'), column: document.getElementById('filter-column'), due: document.getElementById('filter-due')};
    function fillFilter(select, options) {
        const value = select.value;
        select.replaceChildren();
        for (const [id, text] of options) { const option = element('option', '', text); option.value = String(id); select.append(option); }
        if (Array.from(select.options).some(option => option.value === value)) select.value = value;
    }
    function matches(task) {
        const today = new Date().toISOString().slice(0, 10);
        const nextWeek = new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10);
        return (!filters.priority.value || task.priority === filters.priority.value)
            && (!filters.assignee.value || task.assignees.some(member => member.id === Number(filters.assignee.value)))
            && (!filters.due.value || (filters.due.value === 'overdue' && task.overdue)
                || (filters.due.value === 'upcoming' && !task.completed_at && task.due_on && task.due_on >= today && task.due_on <= nextWeek)
                || (filters.due.value === 'none' && !task.due_on));
    }
    function element(tag, className = '', text = '') {
        const item = document.createElement(tag);
        item.className = className;
        item.textContent = text;
        return item;
    }
    function notify(text, error = false) {
        message.textContent = text;
        message.className = `kb-notice kb-notice-${error ? 'error' : 'success'}`;
    }
    function button(text, className = 'btn btn-sm btn-outline-primary') {
        const item = element('button', className, text);
        item.type = 'submit';
        return item;
    }
    function cloneTemplate(id) {
        return document.getElementById(id).content.firstElementChild.cloneNode(true);
    }
    function updateColumnNavigation() {
        document.getElementById('previous-column').disabled = root.scrollLeft <= 1;
        document.getElementById('next-column').disabled = root.scrollLeft + root.clientWidth >= root.scrollWidth - 1;
    }
    function render() {
        const scrollLeft = root.scrollLeft;
        sortables.forEach(sortable => sortable.destroy()); sortables = [];
        fillFilter(filters.assignee, [['', 'Todos os responsáveis'], ...(board.members || []).map(member => [member.id, member.name])]);
        fillFilter(filters.column, [['', 'Todas as colunas'], ...board.columns.map(column => [column.id, column.name])]);
        const filterCount = Object.values(filters).filter(field => field.value !== '').length;
        const filtered = filterCount > 0;
        document.getElementById('filter-count').textContent = filtered ? `${filterCount} filtro(s) ativo(s)` : 'Nenhum filtro';
        document.getElementById('filter-note').classList.toggle('d-none', !filtered);
        root.replaceChildren();
        for (const column of board.columns) {
            if (filters.column.value && Number(filters.column.value) !== column.id) continue;
            const section = cloneTemplate('kanban-column-template');
            section.dataset.id = String(column.id); section.dataset.done = String(column.is_done);
            section.querySelector('h3').textContent = column.name;
            const visible = column.tasks.filter(matches);
            section.querySelector('.kb-column-count').textContent = `${filtered ? visible.length + ' de ' : ''}${column.tasks.length} tarefa(s)${column.is_done ? ' · Conclusão' : ''}`;
            const list = section.querySelector('.kanban-tasks'); list.dataset.columnId = String(column.id);
            for (const task of visible) {
                const card = cloneTemplate('kanban-task-template'); card.dataset.id = String(task.id);
                const title = card.querySelector('[data-task-open]'); title.textContent = task.title; title.dataset.taskOpen = String(task.id);
                const priority = card.querySelector('.kb-priority'); priority.textContent = priorities[task.priority]; priority.dataset.priority = task.priority;
                card.querySelector('.kb-completed').hidden = !task.completed_at;
                const due = card.querySelector('.kb-task-due'); due.hidden = !task.due_on;
                if (task.due_on) { due.textContent = `${task.overdue ? 'Atrasada · ' : ''}Prazo: ${task.due_on.split('-').reverse().join('/')}`; due.classList.toggle('kb-overdue', task.overdue); }
                const assigned = card.querySelector('.kb-task-assignees'); assigned.hidden = !task.assignees.length;
                assigned.textContent = 'Responsáveis: ' + task.assignees.map(member => member.name).join(', ');
                if (board.can_write) {
                    card.querySelectorAll('form').forEach(form => { form.dataset.taskId = String(task.id); });
                    card.querySelector('input[name="title"]').value = task.title;
                    const select = card.querySelector('select[name="column_id"]');
                    fillFilter(select, board.columns.map(destination => [destination.id, destination.name])); select.value = String(column.id);
                } else card.querySelectorAll('[data-write-only]').forEach(node => node.remove());
                list.append(card);
            }
            const empty = section.querySelector('[data-column-empty]'); empty.hidden = visible.length > 0;
            empty.textContent = filtered && column.tasks.length ? 'Nenhuma tarefa corresponde aos filtros.' : 'Nenhuma tarefa nesta coluna.';
            section.querySelectorAll(':scope > form, :scope > details > form').forEach(form => { form.dataset.columnId = String(column.id); });
            if (!board.can_write) section.querySelectorAll('[data-write-only]').forEach(node => node.remove());
            if (board.can_manage) {
                section.querySelector('input[name="name"]').value = column.name;
                section.querySelector('input[name="is_done"]').checked = column.is_done;
                const select = section.querySelector('select[name="position"]');
                fillFilter(select, board.columns.map((item, index) => [index, String(index + 1)])); select.value = String(board.columns.findIndex(item => item.id === column.id));
                section.querySelector('[data-action="column.delete"] button').disabled = column.tasks.length > 0 || board.columns.length <= 1;
            } else section.querySelectorAll('[data-manage-only]').forEach(node => node.remove());
            root.append(section);
            if (board.can_write && !filtered) sortables.push(Sortable.create(list, {
                group: 'tasks', draggable: '.kanban-task', handle: '.kanban-task-handle', animation: 150, ghostClass: 'kanban-ghost',
                onEnd: event => save('task.move', {task_id: Number(event.item.dataset.id), column_id: Number(event.to.dataset.columnId), position: event.newDraggableIndex})
            }));
        }
        if (board.can_manage && !filtered) sortables.push(Sortable.create(root, {
            draggable: '.kanban-column', handle: '.kanban-column > div > .kanban-handle', animation: 150, direction: 'horizontal', ghostClass: 'kanban-ghost',
            onEnd: () => save('columns.reorder', {column_ids: Array.from(root.children, child => Number(child.dataset.id))})
        }));
        root.scrollLeft = scrollLeft; updateColumnNavigation();
    }
    function focusTarget() {
        const control = document.activeElement; const form = control?.closest('form');
        if (!form || (!root.contains(form) && form.id !== 'column-create')) return null;
        return {action: form.dataset.action, taskId: form.dataset.taskId, columnId: form.dataset.columnId, staticForm: form.id === 'column-create', name: control.name || form.querySelector('input:not([type="checkbox"]), select')?.name};
    }
    function restoreFocus(target) {
        if (!target) return;
        const form = target.staticForm ? document.getElementById('column-create') : [...root.querySelectorAll('form')].find(form => form.dataset.action === target.action && form.dataset.taskId === target.taskId && form.dataset.columnId === target.columnId);
        const control = form && [...form.elements].find(control => control.name === target.name);
        if (control && !control.disabled) {
            let details = control.closest('details');
            while (details && root.contains(details)) { details.open = true; details = details.parentElement.closest('details'); }
            control.focus({preventScroll: true});
        }
    }
    async function save(action, payload) {
        if (saving) return;
        const focus = focusTarget();
        saving = true;
        let success = false;
        document.getElementById('task-panel-close').disabled = true;
        root.setAttribute('aria-busy', 'true');
        sortables.forEach(sortable => sortable.option('disabled', true));
        document.querySelectorAll('#kanban input, #kanban select, #kanban button, #column-create input, #column-create button, #task-content input, #task-content select, #task-content textarea, #task-content button, #board-filters select, #clear-board-filters').forEach(item => { item.disabled = true; });
        notify('Salvando…');
        try {
            const response = await fetch(root.dataset.endpoint, {
                method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                body: JSON.stringify({...payload, action, version: board.version, _token: root.dataset.csrf})
            });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.message || 'Não foi possível salvar. Atualize o quadro para conferir o estado atual.');
            board = result.board;
            success = true;
            if (action === 'column.create') document.getElementById('column-create').reset();
            notify('Quadro atualizado.');
        } catch (error) {
            if (panel.classList.contains('show')) taskNotify(error.message, true);
            notify(error instanceof TypeError ? 'A conexão falhou. Atualize o quadro para conferir o estado atual antes de tentar novamente.' : error.message, true);
        } finally {
            saving = false;
            // Reconstrói a última versão confirmada, inclusive após falha ou conflito.
            render();
            document.querySelectorAll('#column-create input, #column-create button').forEach(item => { item.disabled = false; });
            root.removeAttribute('aria-busy');
            document.querySelectorAll('#board-filters select, #clear-board-filters').forEach(item => { item.disabled = false; });
            restoreFocus(focus);
            document.getElementById('task-panel-close').disabled = false;
            panelContent.querySelectorAll('input,select,textarea,button').forEach(item => { item.disabled = !board.can_write && !item.dataset.page; });
        }
        return success;
    }
    root.addEventListener('submit', event => {
        event.preventDefault();
        const target = event.target;
        const fields = new FormData(target);
        let action = target.dataset.action;
        let payload = {};
        if (target.dataset.taskId) payload.task_id = Number(target.dataset.taskId);
        if (target.dataset.columnId) payload.column_id = Number(target.dataset.columnId);
        if (fields.has('title')) payload.title = fields.get('title');
        if (fields.has('name')) payload.name = fields.get('name');
        if (action === 'column.update') payload.is_done = fields.has('is_done');
        if (action === 'task.move') {
            payload.column_id = Number(fields.get('column_id'));
            const destination = board.columns.find(column => column.id === payload.column_id);
            payload.position = destination.tasks.filter(task => task.id !== payload.task_id).length;
        }
        if (action === 'column.position') {
            const ids = board.columns.map(column => column.id).filter(id => id !== payload.column_id);
            ids.splice(Number(fields.get('position')), 0, payload.column_id);
            action = 'columns.reorder'; payload = {column_ids: ids};
        }
        if (action === 'column.delete' && !window.confirm('Excluir esta coluna vazia?')) return;
        save(action, payload);
    });
    document.getElementById('column-create')?.addEventListener('submit', event => {
        event.preventDefault();
        const data = new FormData(event.target);
        save('column.create', {name: data.get('name'), is_done: data.has('is_done')});
    });
    function taskNotify(text, error = false) {
        taskMessage.className = `alert alert-${error ? 'danger' : 'success'}`; taskMessage.textContent = text;
    }
    function discard() { return dirtyForms.size === 0 || window.confirm('Descartar as alterações ainda não salvas?'); }
    function stamp(value) {
        return value ? new Intl.DateTimeFormat('pt-BR', {timeZone: 'UTC', dateStyle: 'short', timeStyle: 'short'}).format(new Date(value.replace(' ', 'T') + 'Z')) + ' UTC' : 'Não definido';
    }
    function field(name, label, value, type = 'text', max = 200) {
        const wrapper = element('label', 'form-label d-block'); wrapper.append(document.createTextNode(label));
        const control = element(type === 'textarea' ? 'textarea' : 'input', 'form-control mt-1'); control.name = name;
        if (type !== 'textarea') control.type = type;
        control.value = value || '';
        if (type === 'text' || type === 'textarea') control.maxLength = max;
        if (type === 'datetime-local') control.step = '1';
        if (type === 'date') { control.min = '1000-01-01'; control.max = '9999-12-31'; }
        wrapper.append(control); return wrapper;
    }
    function editor(action, data = {}) {
        const form = element('form', 'mt-3'); form.dataset.taskAction = action;
        for (const [key, value] of Object.entries(data)) form.dataset[key] = String(value);
        return form;
    }
    function actionButton(action, label, data, danger = true) {
        const control = button(label, `btn btn-sm btn-outline-${danger ? 'danger' : 'secondary'} mt-2`); control.type = 'button'; control.dataset.taskAction = action;
        for (const [key, value] of Object.entries(data)) control.dataset[key] = String(value);
        return control;
    }
    function heading(text) { return element('h3', 'h6 mt-4', text); }
    function pages(parent, result, type) {
        const nav = element('div', 'd-flex justify-content-between align-items-center mt-2 small');
        for (const [label, page] of [['Anterior', result.page - 1], ['Próxima', result.page + 1]]) {
            if (page < 1 || page > result.pages) continue;
            const control = button(label, 'btn btn-sm btn-outline-secondary'); control.type = 'button'; control.dataset.page = type; control.dataset.number = String(page); nav.append(control);
        }
        nav.append(element('span', '', `Página ${result.page} de ${result.pages}`)); parent.append(nav);
    }
    function renderTask() {
        const data = taskDetails; const task = data.task; const writable = board.can_write;
        panelContent.replaceChildren(); dirtyForms.clear();
        panelContent.append(element('p', 'small text-body-secondary', `Criada em ${stamp(task.created_at)} · Conclusão: ${task.completed_at ? stamp(task.completed_at) : 'Pendente'}`));
        const reload = button('Atualizar detalhes', 'btn btn-sm btn-outline-secondary'); reload.type = 'button'; reload.dataset.page = 'reload'; panelContent.append(reload);
        const edit = editor('task.update'); edit.id = 'task-edit';
        const fields = element('fieldset'); fields.disabled = !writable;
        fields.append(field('title', 'Título', task.title), field('description', 'Descrição', task.description, 'textarea', 16000));
        const priorityLabel = element('label', 'form-label d-block', 'Prioridade'); const priority = element('select', 'form-select'); priority.name = 'priority';
        for (const [value, title] of Object.entries(priorities)) { const option = element('option', '', title); option.value = value; option.selected = task.priority === value; priority.append(option); }
        priorityLabel.append(priority); fields.append(priorityLabel);
        fields.append(field('starts_on', 'Início previsto', task.starts_on, 'date'), field('due_on', 'Prazo previsto', task.due_on, 'date'), field('started_at', 'Início real (UTC)', task.started_at?.replace(' ', 'T'), 'datetime-local'));
        const assigned = element('fieldset', 'mt-3'); assigned.append(element('legend', 'h6', 'Responsáveis'));
        const choices = element('div', 'task-assignee-choices');
        for (const member of data.members) {
            const label = element('label', 'form-check d-block'); const checkbox = element('input', 'form-check-input'); checkbox.type = 'checkbox'; checkbox.name = 'assignee_ids'; checkbox.value = String(member.id); checkbox.checked = task.assignee_ids.includes(member.id);
            label.append(checkbox, document.createTextNode(member.name)); choices.append(label);
        }
        assigned.append(choices); fields.append(assigned);
        if (writable) fields.append(button('Salvar detalhes', 'btn btn-primary mt-3')); edit.append(fields); panelContent.append(edit);
        panelContent.append(heading('Checklists e subtarefas'));
        for (const checklist of data.checklists) {
            const section = element('section', 'card card-body mt-3'); section.append(element('h4', 'h6', `${checklist.title} · ${checklist.items.filter(item => item.is_done).length}/${checklist.items.length}`));
            if (writable) {
                const settings = element('details'); settings.append(element('summary', 'small', 'Configurar lista'));
                const rename = editor('checklist.update', {checklistId: checklist.id}); rename.append(field('title', 'Nome da lista', checklist.title, 'text', 120), button('Salvar lista')); settings.append(rename, actionButton('checklist.delete', 'Excluir lista e itens', {checklistId: checklist.id})); section.append(settings);
            }
            for (const item of checklist.items) {
                if (!writable) { section.append(element('p', 'task-note mt-2', `${item.is_done ? '☑' : '☐'} ${item.title}`)); continue; }
                const entry = editor('item.update', {itemId: item.id}); entry.append(field('title', 'Item / subtarefa', item.title, 'text', 300));
                const label = element('label', 'form-check d-block'); const done = element('input', 'form-check-input'); done.type = 'checkbox'; done.name = 'is_done'; done.checked = item.is_done; label.append(done, document.createTextNode('Concluído'));
                entry.append(label, button('Salvar item')); section.append(entry, actionButton('item.delete', 'Excluir item', {itemId: item.id}));
            }
            if (writable) { const create = editor('item.create', {checklistId: checklist.id}); create.append(field('title', 'Novo item', '', 'text', 300), button('Adicionar item')); section.append(create); }
            panelContent.append(section);
        }
        if (writable) { const create = editor('checklist.create'); create.append(field('title', 'Nova lista', '', 'text', 120), button('Adicionar lista')); panelContent.append(create); }
        panelContent.append(heading('Comentários'));
        if (writable) { const create = editor('comment.create'); create.append(field('body', 'Novo comentário', '', 'textarea', 5000), button('Comentar')); panelContent.append(create); }
        for (const comment of data.comments.items) {
            const article = element('article', 'border rounded p-3 mt-3'); article.dataset.commentId = String(comment.id);
            article.append(element('p', 'small text-body-secondary', `${comment.author} · ${stamp(comment.created_at)}`), element('p', 'task-note', comment.body));
            const own = Number(comment.author_id) === data.actor_id;
            if (writable && own) {
                const details = element('details'); details.append(element('summary', 'small', 'Editar comentário'));
                const update = editor('comment.update', {commentId: comment.id}); update.append(field('body', 'Comentário', comment.body, 'textarea', 5000), button('Salvar comentário')); details.append(update); article.append(details);
            }
            if (writable && (own || board.can_manage)) article.append(actionButton('comment.delete', 'Excluir comentário', {commentId: comment.id}));
            panelContent.append(article);
        }
        pages(panelContent, data.comments, 'comments');
        panelContent.append(heading('Histórico da tarefa'));
        const labels = {'task.created': 'Tarefa criada', 'task.updated': 'Detalhes alterados', 'task.moved': 'Tarefa movimentada', 'task.completed': 'Tarefa concluída', 'task.reopened': 'Tarefa reaberta', 'task.assignees_changed': 'Responsáveis alterados', 'checklist.created': 'Lista criada', 'checklist.updated': 'Lista alterada', 'checklist.deleted': 'Lista excluída', 'checklist.item_created': 'Item criado', 'checklist.item_updated': 'Item alterado', 'checklist.item_deleted': 'Item excluído', 'comment.created': 'Comentário adicionado', 'comment.updated': 'Comentário alterado', 'comment.deleted': 'Comentário excluído'};
        for (const event of data.activity.items) panelContent.append(element('p', 'small mb-2', `${labels[event.action] || 'Atividade registrada'} · ${event.author} · ${stamp(event.created_at)}`));
        pages(panelContent, data.activity, 'activity');
    }
    async function openTask(id, page = {}, force = false) {
        if (saving || (!force && !discard())) return;
        taskId = Number(id); const sequence = ++taskRequest;
        panelContent.replaceChildren(); dirtyForms.clear(); taskDetails = null;
        bootstrap.Offcanvas.getOrCreateInstance(panel).show(); taskNotify('Carregando detalhes…');
        try {
            const query = new URLSearchParams({comments_page: page.comments || 1, activity_page: page.activity || 1});
            const response = await fetch(`/projects/${board.project_id}/tasks/${taskId}?${query}`, {credentials: 'same-origin', headers: {'Accept': 'application/json'}});
            const result = await response.json();
            if (sequence !== taskRequest) return;
            if (!response.ok || !result.ok) throw new Error(result.message || 'Não foi possível carregar a tarefa.');
            board = result.board; taskDetails = result.details; render(); renderTask(); taskMessage.className = 'alert d-none';
        } catch (error) { if (sequence === taskRequest) taskNotify(error.message, true); }
    }
    async function taskSave(action, payload, source = null) {
        if (saving) return;
        if (Array.from(dirtyForms).some(form => form !== source) && !window.confirm('Há alterações em outros campos. Descartá-las e continuar?')) return;
        if (action.endsWith('.delete') && !window.confirm('Confirmar a exclusão?')) return;
        if (action.endsWith('.delete')) payload.confirm = true;
        if (await save(action, {task_id: taskId, ...payload})) await openTask(taskId, {}, true);
    }
    for (const field of Object.values(filters)) field.addEventListener('change', () => { if (!saving) render(); });
    document.getElementById('clear-board-filters').addEventListener('click', () => {
        if (saving) return;
        Object.values(filters).forEach(field => { field.value = ''; }); render();
    });
    for (const [id, direction] of [['previous-column', -1], ['next-column', 1]]) document.getElementById(id).addEventListener('click', () => {
        const column = root.querySelector('.kanban-column'); if (!column) return;
        const gap = parseFloat(getComputedStyle(root).columnGap) || 0;
        root.scrollBy({left: direction * (column.getBoundingClientRect().width + gap), behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'});
    });
    root.addEventListener('scroll', updateColumnNavigation, {passive: true});
    window.addEventListener('resize', updateColumnNavigation);
    root.addEventListener('click', event => { const target = event.target.closest('[data-task-open]'); if (target) openTask(target.dataset.taskOpen); });
    panelContent.addEventListener('input', event => { const form = event.target.closest('form'); if (form) dirtyForms.add(form); });
    panelContent.addEventListener('change', event => { const form = event.target.closest('form'); if (form) dirtyForms.add(form); });
    panelContent.addEventListener('submit', event => {
        event.preventDefault(); const form = event.target; const fields = new FormData(form); const payload = {};
        for (const key of ['checklistId', 'itemId', 'commentId']) if (form.dataset[key]) payload[key.replace('Id', '_id')] = Number(form.dataset[key]);
        for (const key of ['title', 'description', 'priority', 'starts_on', 'due_on', 'body']) if (fields.has(key)) payload[key] = fields.get(key);
        if (fields.has('started_at')) { const value = fields.get('started_at'); payload.started_at = value ? value.replace('T', ' ') + (value.length === 16 ? ':00' : '') : ''; }
        if (form.dataset.taskAction === 'task.update') payload.assignee_ids = fields.getAll('assignee_ids').map(Number);
        if (form.dataset.taskAction === 'item.update') payload.is_done = fields.has('is_done');
        taskSave(form.dataset.taskAction, payload, form);
    });
    panelContent.addEventListener('click', event => {
        const control = event.target.closest('button[type="button"]'); if (!control || saving) return;
        if (control.dataset.page) {
            const page = {comments: taskDetails.comments.page, activity: taskDetails.activity.page};
            if (control.dataset.page !== 'reload') page[control.dataset.page] = Number(control.dataset.number);
            openTask(taskId, page); return;
        }
        const payload = {};
        for (const key of ['checklistId', 'itemId', 'commentId']) if (control.dataset[key]) payload[key.replace('Id', '_id')] = Number(control.dataset[key]);
        taskSave(control.dataset.taskAction, payload);
    });
    document.getElementById('task-panel-close').addEventListener('click', () => {
        if (saving || !discard()) return; ++taskRequest; dirtyForms.clear(); bootstrap.Offcanvas.getOrCreateInstance(panel).hide(); taskId = null;
    });
    window.addEventListener('beforeunload', event => { if (dirtyForms.size || saving) { event.preventDefault(); event.returnValue = ''; } });

    if (typeof Sortable === 'undefined') { notify('Não foi possível carregar o quadro. Atualize a página.', true); return; }
    document.querySelectorAll('[data-board-enhancement]').forEach(node => { node.hidden = false; });
    render();
    const linkedTask = new URLSearchParams(window.location.search).get('task');
    if (linkedTask && /^[0-9]{1,18}$/.test(linkedTask) && Number(linkedTask) > 0) openTask(Number(linkedTask));
})();
