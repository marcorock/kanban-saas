'use strict';
(() => {
    const root = document.getElementById('kanban');
    if (!root) return;
    const message = document.getElementById('board-message');
    let board = JSON.parse(document.getElementById('board-state').textContent);
    let sortables = [];
    let saving = false;
    function element(tag, className = '', text = '') {
        const item = document.createElement(tag);
        item.className = className;
        item.textContent = text;
        return item;
    }
    function notify(text, error = false) {
        message.textContent = text;
        message.className = `alert alert-${error ? 'danger' : 'success'}`;
    }
    function input(name, label, value, max, type = 'text') {
        const wrapper = element('label', 'form-label d-block');
        wrapper.append(document.createTextNode(label));
        const field = element('input', type === 'checkbox' ? 'form-check-input ms-2' : 'form-control form-control-sm mt-1');
        field.name = name;
        field.type = type;
        if (type === 'checkbox') field.checked = Boolean(value);
        else { field.value = value; field.required = true; field.maxLength = max; }
        wrapper.append(field);
        return wrapper;
    }
    function button(text, className = 'btn btn-sm btn-outline-primary') {
        const item = element('button', className, text);
        item.type = 'submit';
        return item;
    }
    function form(action, id, idName) {
        const item = element('form', 'mt-2');
        item.dataset.action = action;
        if (idName) item.dataset[idName] = String(id);
        return item;
    }
    function render() {
        sortables.forEach(sortable => sortable.destroy());
        sortables = [];
        root.replaceChildren();
        for (const column of board.columns) {
            const section = element('section', 'kanban-column card card-body');
            section.dataset.id = String(column.id);
            const heading = element('div', 'd-flex justify-content-between align-items-start gap-2');
            heading.append(element('h3', 'h6 kanban-title', column.name));
            if (board.can_manage) heading.append(element('span', 'kanban-handle', '↔'));
            section.append(heading);
            section.append(element('p', 'small text-body-secondary mb-1', `${column.tasks.length} tarefa(s)${column.is_done ? ' · Conclusão' : ''}`));
            const list = element('div', 'kanban-tasks');
            list.dataset.columnId = String(column.id);
            for (const task of column.tasks) {
                const card = element('article', 'kanban-task card card-body p-2 mb-2');
                card.dataset.id = String(task.id);
                card.append(element('div', 'kanban-title', task.title));
                if (task.completed_at) card.append(element('span', 'badge text-bg-success align-self-start mt-1', 'Concluída'));
                if (board.can_write) {
                    card.append(element('span', 'kanban-task-handle kanban-handle small mt-2', '↕ Mover tarefa'));
                    const details = element('details', 'mt-2');
                    details.append(element('summary', 'small', 'Editar título / mover'));
                    const rename = form('task.rename', task.id, 'taskId');
                    rename.append(input('title', 'Título', task.title, 200), button('Salvar título'));
                    details.append(rename);
                    // Alternativa ao arrastar para teclado e dispositivos de toque.
                    const move = form('task.move', task.id, 'taskId');
                    const label = element('label', 'form-label d-block small', 'Mover para o fim da coluna');
                    const select = element('select', 'form-select form-select-sm mt-1'); select.name = 'column_id';
                    for (const destination of board.columns) {
                        const option = element('option', '', destination.name); option.value = String(destination.id); option.selected = destination.id === column.id; select.append(option);
                    }
                    label.append(select); move.append(label, button('Mover')); details.append(move); card.append(details);
                }
                list.append(card);
            }
            section.append(list);
            if (board.can_write) {
                const create = form('task.create', column.id, 'columnId');
                create.append(input('title', 'Nova tarefa', '', 200), button('Adicionar tarefa')); section.append(create);
            }
            if (board.can_manage) {
                const details = element('details', 'mt-3'); details.append(element('summary', 'small', 'Configurar coluna'));
                const edit = form('column.update', column.id, 'columnId');
                edit.append(input('name', 'Nome', column.name, 80), input('is_done', 'Conclui tarefas', column.is_done, null, 'checkbox'), button('Salvar coluna')); details.append(edit);
                const positions = form('column.position', column.id, 'columnId');
                const label = element('label', 'form-label d-block small mt-2', 'Posição da coluna');
                const select = element('select', 'form-select form-select-sm mt-1'); select.name = 'position';
                board.columns.forEach((item, index) => { const option = element('option', '', String(index + 1)); option.value = String(index); option.selected = item.id === column.id; select.append(option); });
                label.append(select); positions.append(label, button('Salvar posição')); details.append(positions);
                const remove = form('column.delete', column.id, 'columnId');
                const removeButton = button('Excluir coluna vazia', 'btn btn-sm btn-outline-danger'); removeButton.disabled = column.tasks.length > 0 || board.columns.length <= 1; remove.append(removeButton); details.append(remove); section.append(details);
            }
            root.append(section);
            if (board.can_write) sortables.push(Sortable.create(list, {
                group: 'tasks', draggable: '.kanban-task', handle: '.kanban-task-handle', animation: 150, ghostClass: 'kanban-ghost',
                onEnd: event => save('task.move', {task_id: Number(event.item.dataset.id), column_id: Number(event.to.dataset.columnId), position: event.newDraggableIndex})
            }));
        }
        if (board.can_manage) sortables.push(Sortable.create(root, {
            draggable: '.kanban-column', handle: '.kanban-column > div > .kanban-handle', animation: 150, direction: 'horizontal', ghostClass: 'kanban-ghost',
            onEnd: () => save('columns.reorder', {column_ids: Array.from(root.children, child => Number(child.dataset.id))})
        }));
    }
    async function save(action, payload) {
        if (saving) return;
        saving = true;
        root.setAttribute('aria-busy', 'true');
        sortables.forEach(sortable => sortable.option('disabled', true));
        document.querySelectorAll('#kanban input, #kanban select, #kanban button, #column-create input, #column-create button').forEach(item => { item.disabled = true; });
        notify('Salvando…');
        try {
            const response = await fetch(root.dataset.endpoint, {
                method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                body: JSON.stringify({...payload, action, version: board.version, _token: root.dataset.csrf})
            });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.message || 'Não foi possível salvar. Atualize o quadro para conferir o estado atual.');
            board = result.board;
            if (action === 'column.create') document.getElementById('column-create').reset();
            notify('Quadro atualizado.');
        } catch (error) {
            notify(error instanceof TypeError ? 'A conexão falhou. Atualize o quadro para conferir o estado atual antes de tentar novamente.' : error.message, true);
        } finally {
            saving = false;
            // Reconstrói a última versão confirmada, inclusive após falha ou conflito.
            render();
            document.querySelectorAll('#column-create input, #column-create button').forEach(item => { item.disabled = false; });
            root.removeAttribute('aria-busy');
        }
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
    if (typeof Sortable === 'undefined') { notify('Não foi possível carregar o quadro. Atualize a página.', true); return; }
    render();
})();
