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
    let taskTab = 'subtasks';
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
                const summary = card.querySelector('[data-subtask-summary]'); summary.hidden = !task.subtask_total;
                if (task.subtask_total) {
                    summary.querySelector('[data-subtask-count]').textContent = `${task.subtask_done}/${task.subtask_total} subtarefas`;
                    summary.querySelector('[data-subtask-percent]').textContent = `${Math.round(task.subtask_done * 100 / task.subtask_total)}%`;
                    const progress = summary.querySelector('progress'); progress.max = task.subtask_total; progress.value = task.subtask_done;
                    progress.setAttribute('aria-label', `${task.subtask_done} de ${task.subtask_total} subtarefas concluídas`);
                }
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
        const panelDisabled = new Map([...panelContent.querySelectorAll('input,select,textarea,button')].map(control => [control, control.disabled]));
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
            panelContent.querySelectorAll('input,select,textarea,button').forEach(item => { item.disabled = panelDisabled.get(item) || (!board.can_write && !item.dataset.page && !(item.hasAttribute('data-task-delete') && taskDetails?.can_delete)); });
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
        taskMessage.hidden = false; taskMessage.className = `tp-notice tp-notice-${error ? 'error' : 'success'}`;
        taskMessage.textContent = text; taskMessage.setAttribute('role', error ? 'alert' : 'status');
        if (error) taskMessage.focus();
    }
    function discard() { return dirtyForms.size === 0 || window.confirm('Descartar as alterações ainda não salvas?'); }
    function stamp(value) {
        return value ? new Intl.DateTimeFormat('pt-BR', {timeZone: 'UTC', dateStyle: 'short', timeStyle: 'short'}).format(new Date(value.replace(' ', 'T') + 'Z')) + ' UTC' : 'Não definido';
    }
    function bindTaskAction(node, key, value) {
        node.querySelectorAll('[data-task-action]').forEach(control => { control.dataset[key] = String(value); });
    }
    function taskPages(parent, result, type) {
        if (result.pages <= 1) return;
        const nav = cloneTemplate('task-pages-template'); nav.setAttribute('aria-label', type === 'comments' ? 'Páginas de comentários' : 'Páginas do histórico');
        nav.querySelector('span').textContent = `Página ${result.page} de ${result.pages}`;
        for (const [selector, number] of [['[data-previous]', result.page - 1], ['[data-next]', result.page + 1]]) {
            const control = nav.querySelector(selector); control.dataset.page = type; control.dataset.number = String(number);
            control.disabled = number < 1 || number > result.pages;
        }
        parent.append(nav);
    }
    function selectTaskTab(name, focus = false) {
        taskTab = name;
        panelContent.querySelectorAll('[data-task-tab]').forEach(tab => {
            const active = tab.dataset.taskTab === name; tab.setAttribute('aria-selected', String(active)); tab.tabIndex = active ? 0 : -1;
            document.getElementById(tab.getAttribute('aria-controls')).hidden = !active;
            if (active && focus) tab.focus();
        });
    }
    function editItem(form, editing) {
        form.dataset.editing = String(editing);
        form.querySelectorAll('[data-item-edit], [data-task-action="item.delete"]').forEach(control => { control.hidden = editing; });
        form.querySelector('input[name="title"]').hidden = !editing;
        form.querySelector('[data-item-confirm]').hidden = !editing; form.querySelector('[data-item-cancel]').hidden = !editing;
        form.querySelector('[name="is_done"]').disabled = editing;
        if (editing) { const input = form.querySelector('[name="title"]'); input.focus(); input.select(); }
        else { form.querySelector('[name="title"]').value = form.dataset.originalTitle; dirtyForms.delete(form); form.querySelector('.tp-item-title').focus({preventScroll: true}); }
    }
    function renderTask() {
        const data = taskDetails; const task = data.task; const writable = board.can_write;
        const content = cloneTemplate('task-details-template');
        content.querySelector('[data-task-heading]').textContent = task.title;
        content.querySelector('[data-task-delete]').hidden = !data.can_delete;
        const column = board.columns.find(column => column.tasks.some(item => item.id === task.id));
        content.querySelector('[data-task-meta]').textContent = [column?.name, task.due_on ? 'Prazo: ' + task.due_on.split('-').reverse().join('/') : 'Sem prazo', ...data.members.filter(member => task.assignee_ids.includes(member.id)).map(member => member.name)].filter(Boolean).join(' · ');
        const allItems = data.checklists.flatMap(list => list.items); const doneItems = allItems.filter(item => item.is_done).length;
        content.querySelector('[data-total-count]').textContent = allItems.length ? `${doneItems} de ${allItems.length} subtarefas concluídas` : 'Sem subtarefas';
        content.querySelector('[data-total-percent]').textContent = allItems.length ? `${Math.round(doneItems * 100 / allItems.length)}%` : '';
        const totalProgress = content.querySelector('[data-total-progress]'); totalProgress.hidden = !allItems.length; totalProgress.max = allItems.length || 1; totalProgress.value = doneItems;
        totalProgress.setAttribute('aria-label', `${doneItems} de ${allItems.length} subtarefas concluídas`);
        content.querySelector('[data-task-stamp]').textContent = `Criada em ${stamp(task.created_at)} · Conclusão: ${task.completed_at ? stamp(task.completed_at) : 'Pendente'}`;
        content.querySelector('[data-readonly]').hidden = writable;
        if (!writable && data.can_delete) content.querySelector('[data-readonly]').textContent = 'Você pode consultar esta tarefa e excluí-la porque é quem a criou.';
        const edit = content.querySelector('#task-edit'); edit.querySelector('fieldset').disabled = !writable;
        for (const name of ['title', 'description', 'priority', 'starts_on', 'due_on']) edit.elements[name].value = task[name] || '';
        edit.elements.started_at.value = task.started_at?.replace(' ', 'T') || '';
        for (const member of data.members) {
            const label = cloneTemplate('task-assignee-template'); label.querySelector('span').textContent = member.name;
            const checkbox = label.querySelector('input'); checkbox.value = String(member.id); checkbox.checked = task.assignee_ids.includes(member.id);
            content.querySelector('[data-assignees]').append(label);
        }
        content.querySelector('[data-checklist-empty]').hidden = data.checklists.length > 0;
        for (const checklist of data.checklists) {
            const section = cloneTemplate('task-checklist-template'); const done = checklist.items.filter(item => item.is_done).length;
            section.querySelector('h4').textContent = checklist.title;
            section.querySelector('[data-list-count]').textContent = `${done}/${checklist.items.length}`;
            bindTaskAction(section, 'checklistId', checklist.id);
            section.querySelector('[data-task-action="checklist.update"] input').value = checklist.title;
            section.querySelector('[data-item-empty]').hidden = checklist.items.length > 0;
            for (const item of checklist.items) {
                const row = cloneTemplate('task-item-template'); bindTaskAction(row, 'itemId', item.id);
                const form = row.querySelector('form'); form.dataset.originalTitle = item.title; form.dataset.done = String(item.is_done);
                row.querySelector('input[name="title"]').value = item.title; row.querySelector('input[name="is_done"]').checked = item.is_done;
                row.querySelector('input[name="is_done"]').setAttribute('aria-label', `${item.is_done ? 'Reabrir' : 'Concluir'} subtarefa: ${item.title}`);
                row.querySelector('.tp-item-title').textContent = item.title;
                const read = row.querySelector('[data-item-readonly]'); read.hidden = writable; read.textContent = `${item.is_done ? 'Concluído' : 'Pendente'} · ${item.title}`;
                section.querySelector('[data-items]').append(row);
            }
            content.querySelector('[data-checklists]').append(section);
        }
        content.querySelector('[data-comment-empty]').hidden = data.comments.items.length > 0;
        for (const comment of data.comments.items) {
            const article = cloneTemplate('task-comment-template'); article.dataset.commentId = String(comment.id); bindTaskAction(article, 'commentId', comment.id);
            article.querySelector('[data-comment-stamp]').textContent = `${comment.author} · ${stamp(comment.created_at)}`;
            article.querySelector('[data-comment-body]').textContent = comment.body;
            const own = Number(comment.author_id) === data.actor_id;
            if (writable && own) article.querySelector('textarea').value = comment.body;
            else article.querySelector('[data-comment-edit]').remove();
            if (!writable || (!own && !board.can_manage)) article.querySelector('[data-comment-delete]').remove();
            content.querySelector('[data-comments]').append(article);
        }
        taskPages(content.querySelector('[data-comment-pages]'), data.comments, 'comments');
        const labels = {'task.created': 'Tarefa criada', 'task.updated': 'Detalhes alterados', 'task.moved': 'Tarefa movimentada', 'task.completed': 'Tarefa concluída', 'task.reopened': 'Tarefa reaberta', 'task.assignees_changed': 'Responsáveis alterados', 'checklist.created': 'Lista criada', 'checklist.updated': 'Lista alterada', 'checklist.deleted': 'Lista excluída', 'checklist.item_created': 'Item criado', 'checklist.item_updated': 'Item alterado', 'checklist.item_deleted': 'Item excluído', 'comment.created': 'Comentário adicionado', 'comment.updated': 'Comentário alterado', 'comment.deleted': 'Comentário excluído'};
        content.querySelector('[data-activity-empty]').hidden = data.activity.items.length > 0;
        for (const event of data.activity.items) {
            const row = cloneTemplate('task-activity-template'); row.querySelector('[data-event-title]').textContent = labels[event.action] || 'Atividade registrada';
            row.querySelector('[data-event-stamp]').textContent = `${event.author} · ${stamp(event.created_at)}`; content.querySelector('[data-activity]').append(row);
        }
        taskPages(content.querySelector('[data-activity-pages]'), data.activity, 'activity');
        if (!writable) content.querySelectorAll('[data-write-only]').forEach(node => node.remove());
        panelContent.replaceChildren(content); dirtyForms.clear(); selectTaskTab(taskTab);
    }
    function panelPosition() {
        const control = document.activeElement; const form = control?.closest('form');
        return {scroll: panel.querySelector('.offcanvas-body').scrollTop, page: control?.dataset.page, direction: control?.hasAttribute('data-next') ? 'next' : 'previous', action: form?.dataset.taskAction, checklistId: form?.dataset.checklistId, itemId: form?.dataset.itemId, commentId: form?.dataset.commentId, name: control?.name || form?.querySelector('input,textarea,select')?.name};
    }
    function restorePanelPosition(position) {
        if (!position) return;
        const form = [...panelContent.querySelectorAll('form')].find(form => ['action', 'checklistId', 'itemId', 'commentId'].every(key => (key === 'action' ? form.dataset.taskAction : form.dataset[key]) === position[key]));
        let control = form && [...form.elements].find(control => control.name === position.name);
        if (!control && position.page) {
            const choices = [...panelContent.querySelectorAll('button[data-page]')].filter(button => button.dataset.page === position.page && !button.disabled);
            control = choices.find(button => button.hasAttribute('data-' + position.direction)) || choices[0];
        }
        if (form?.dataset.taskAction === 'item.update' && position.name === 'title') control = form.querySelector('.tp-item-title');
        if (control) {
            const details = control.closest('details'); if (details) details.open = true;
            control.focus({preventScroll: true});
        }
        panel.querySelector('.offcanvas-body').scrollTop = position.scroll;
    }
    async function openTask(id, page = {}, force = false, position = null) {
        if (saving || (!force && !discard())) return;
        if (taskId !== Number(id)) taskTab = 'subtasks';
        taskId = Number(id); const sequence = ++taskRequest;
        panelContent.replaceChildren(); dirtyForms.clear(); taskDetails = null;
        document.getElementById('task-retry').hidden = true;
        panelContent.setAttribute('aria-busy', 'true');
        bootstrap.Offcanvas.getOrCreateInstance(panel).show(); taskNotify('Carregando detalhes…');
        try {
            const query = new URLSearchParams({comments_page: page.comments || 1, activity_page: page.activity || 1});
            const response = await fetch(`/projects/${board.project_id}/tasks/${taskId}?${query}`, {credentials: 'same-origin', headers: {'Accept': 'application/json'}});
            const result = await response.json();
            if (sequence !== taskRequest) return;
            if (!response.ok || !result.ok) throw new Error(result.message || 'Não foi possível carregar a tarefa.');
            board = result.board; taskDetails = result.details; render(); renderTask(); taskMessage.hidden = true;
            if (position) restorePanelPosition(position); else panel.querySelector('.offcanvas-body').scrollTop = 0;
        } catch (error) { if (sequence === taskRequest) { taskNotify(error.message, true); document.getElementById('task-retry').hidden = false; } }
        finally { if (sequence === taskRequest) panelContent.removeAttribute('aria-busy'); }
    }
    async function taskSave(action, payload, source = null) {
        if (saving) return false;
        if (Array.from(dirtyForms).some(form => form !== source) && !window.confirm('Há alterações em outros campos. Descartá-las e continuar?')) return false;
        if (action.endsWith('.delete') && !window.confirm(action === 'task.delete' ? 'Excluir esta tarefa, suas subtarefas e comentários? Esta ação não pode ser desfeita.' : 'Confirmar a exclusão?')) return false;
        if (action.endsWith('.delete')) payload.confirm = true;
        const position = panelPosition();
        const success = await save(action, {task_id: taskId, ...payload});
        if (success && action === 'task.delete') { dirtyForms.clear(); closeTask(); taskDetails = null; const url = new URL(location.href); url.searchParams.delete('task'); history.replaceState(null, '', url); notify('Tarefa excluída.'); }
        else if (success) await openTask(taskId, {}, true, position);
        return success;
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
    panelContent.addEventListener('input', event => { const form = event.target.closest('form'); if (form && event.target.name !== 'is_done') dirtyForms.add(form); });
    panelContent.addEventListener('change', async event => {
        const form = event.target.closest('form'); if (!form) return;
        if (form.dataset.taskAction === 'item.update' && event.target.name === 'is_done') {
            const checkbox = event.target; const previous = form.dataset.done === 'true';
            const success = await taskSave('item.update', {item_id: Number(form.dataset.itemId), title: form.dataset.originalTitle, is_done: checkbox.checked}, form);
            if (!success) { checkbox.checked = previous; dirtyForms.delete(form); }
        } else dirtyForms.add(form);
    });
    panelContent.addEventListener('submit', event => {
        event.preventDefault(); const form = event.target; const fields = new FormData(form); const payload = {};
        for (const key of ['checklistId', 'itemId', 'commentId']) if (form.dataset[key]) payload[key.replace('Id', '_id')] = Number(form.dataset[key]);
        for (const key of ['title', 'description', 'priority', 'starts_on', 'due_on', 'body']) if (fields.has(key)) payload[key] = fields.get(key);
        if (fields.has('started_at')) { const value = fields.get('started_at'); payload.started_at = value ? value.replace('T', ' ') + (value.length === 16 ? ':00' : '') : ''; }
        if (form.dataset.taskAction === 'task.update') payload.assignee_ids = fields.getAll('assignee_ids').map(Number);
        if (form.dataset.taskAction === 'item.update') payload.is_done = form.querySelector('[name="is_done"]').checked;
        taskSave(form.dataset.taskAction, payload, form);
    });
    panelContent.addEventListener('click', event => {
        const control = event.target.closest('button[type="button"]'); if (!control || saving) return;
        if (control.dataset.taskTab) { selectTaskTab(control.dataset.taskTab); return; }
        if (control.hasAttribute('data-item-edit')) { editItem(control.closest('form'), true); return; }
        if (control.hasAttribute('data-item-cancel')) { editItem(control.closest('form'), false); return; }
        if (control.dataset.page) {
            const page = {comments: taskDetails.comments.page, activity: taskDetails.activity.page};
            if (control.dataset.page !== 'reload') page[control.dataset.page] = Number(control.dataset.number);
            openTask(taskId, page, false, panelPosition()); return;
        }
        const payload = {};
        for (const key of ['checklistId', 'itemId', 'commentId']) if (control.dataset[key]) payload[key.replace('Id', '_id')] = Number(control.dataset[key]);
        taskSave(control.dataset.taskAction, payload);
    });
    panelContent.addEventListener('keydown', event => {
        const tab = event.target.closest('[data-task-tab]');
        if (tab && ['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
            event.preventDefault(); const tabs = [...panelContent.querySelectorAll('[data-task-tab]')]; const index = tabs.indexOf(tab);
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
            selectTaskTab(tabs[next].dataset.taskTab, true);
        }
        const form = event.target.closest('[data-editing="true"]');
        if (event.key === 'Escape' && form) { event.preventDefault(); event.stopPropagation(); editItem(form, false); }
    });
    let returnTaskId = null;
    function closeTask() {
        if (saving || !discard()) return;
        returnTaskId = taskId; ++taskRequest; dirtyForms.clear(); bootstrap.Offcanvas.getOrCreateInstance(panel).hide(); taskId = null;
    }
    document.getElementById('task-panel-close').addEventListener('click', closeTask);
    document.getElementById('task-retry').addEventListener('click', () => { if (taskId) openTask(taskId); });
    panel.addEventListener('keydown', event => { if (event.key === 'Escape') { event.preventDefault(); closeTask(); } });
    panel.addEventListener('hidden.bs.offcanvas', () => {
        const trigger = [...root.querySelectorAll('[data-task-open]')].find(control => Number(control.dataset.taskOpen) === returnTaskId);
        (trigger || root).focus({preventScroll: true});
    });
    window.addEventListener('beforeunload', event => { if (dirtyForms.size || saving) { event.preventDefault(); event.returnValue = ''; } });

    if (typeof Sortable === 'undefined') { notify('Não foi possível carregar o quadro. Atualize a página.', true); return; }
    document.querySelectorAll('[data-board-enhancement]').forEach(node => { node.hidden = false; });
    render();
    const linkedTask = new URLSearchParams(window.location.search).get('task');
    if (linkedTask && /^[0-9]{1,18}$/.test(linkedTask) && Number(linkedTask) > 0) openTask(Number(linkedTask));
})();
