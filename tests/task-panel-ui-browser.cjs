'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const {default: AxeBuilder} = require('@axe-core/playwright');
const runTasksBrowser = require('./tasks-browser.cjs');

runTasksBrowser(async ({page, context, save, boardUrl}) => {
    const preview = process.env.UI_PREVIEW_DIR || '/app/storage/previews';
    const fixture = JSON.parse(await fs.readFile(preview + '/members-fixture.json', 'utf8'));
    const base = new URL(boardUrl).origin;
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    async function audit(target, label) {
        const result = await new AxeBuilder({page: target}).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
        assert.deepEqual(result.violations.map(item => ({id: item.id, targets: item.nodes.map(node => node.target)})), [], label);
    }
    async function loaded(target) { await target.locator('#task-edit').waitFor({state: 'attached'}); await target.waitForFunction(() => { const panel = document.getElementById('task-panel'); return panel.classList.contains('show') && !panel.classList.contains('showing') && !document.getElementById('task-content').hasAttribute('aria-busy'); }); }
    await page.getByRole('button', {name: 'Detalhes no painel', exact: true}).click(); await loaded(page);
    const rows = page.locator('.tp-item-row');
    const firstRow = rows.first();
    await firstRow.locator('[data-item-pencil]').click(); await firstRow.locator('[name="title"]').fill('Rascunho cancelado');
    await firstRow.locator('[data-item-cancel]').click();
    assert.equal(await firstRow.locator('[name="title"]').inputValue(), 'Revisar a entrega');
    await firstRow.locator('[data-item-pencil]').click(); await firstRow.locator('[name="title"]').fill('Definir o escopo');
    await save(() => firstRow.locator('[name="title"]').press('Enter')); await loaded(page);
    assert.equal(await firstRow.locator('.tp-item-title').innerText(), 'Definir o escopo');
    for (const title of ['Preparar os arquivos', 'Revisar os textos', 'Validar a entrega', 'Publicar o projeto']) {
        const create = page.locator('[data-task-action="item.create"]'); await create.locator('input').fill(title);
        await save(() => create.getByRole('button', {name: 'Adicionar item', exact: true}).click()); await loaded(page);
    }
    for (const index of [1, 2]) { await save(() => rows.nth(index).locator('[name="is_done"]').check()); await loaded(page); }
    assert.equal(await page.locator('[data-total-count]').innerText(), '3 de 5 subtarefas concluídas');
    assert.equal(await page.locator('[data-total-percent]').innerText(), '60%');
    assert.equal(await page.locator('.kanban-task [data-subtask-percent]').first().innerText(), '60%');
    // A marcação que falha volta ao estado confirmado; erro não perde texto digitado.
    await page.route(boardUrl, route => route.request().method() === 'POST' ? route.fulfill({status: 500, contentType: 'application/json', body: JSON.stringify({ok: false, message: 'Falha simulada ao marcar.'})}) : route.continue());
    await save(() => firstRow.locator('[name="is_done"]').click(), 500);
    await page.waitForFunction(() => document.querySelector('.tp-item-row [name="is_done"]').checked);
    assert.equal(await page.locator('[data-total-percent]').innerText(), '60%');
    await firstRow.locator('[data-item-pencil]').click(); await firstRow.locator('[name="title"]').fill('Rascunho preservado');
    await save(() => firstRow.locator('[data-item-confirm]').click(), 500);
    assert.equal(await firstRow.locator('[name="title"]').inputValue(), 'Rascunho preservado');
    assert.equal(await firstRow.locator('[name="title"]').isEnabled(), true);
    await firstRow.locator('[data-item-cancel]').click(); await page.unroute(boardUrl);
    await firstRow.locator('[data-item-pencil]').click(); await firstRow.locator('[name="title"]').fill('Não salvar');
    await firstRow.locator('[name="title"]').press('Escape');
    assert.equal(await firstRow.locator('[name="title"]').isHidden(), true);
    await page.getByRole('tab', {name: 'Subtarefas', exact: true}).focus(); await page.keyboard.press('ArrowRight');
    assert.equal(await page.getByRole('tab', {name: 'Comentários', exact: true}).getAttribute('aria-selected'), 'true');
    await page.keyboard.press('Home');
    await page.locator('#task-panel-close').click(); await page.locator('#task-panel').waitFor({state: 'hidden'});
    for (const width of [320, 390, 768, 1024, 1440]) {
        await page.setViewportSize({width, height: width < 640 ? 844 : 1000});
        await page.getByRole('button', {name: 'Detalhes no painel', exact: true}).click(); await loaded(page);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'Página sem overflow ' + width);
        assert.equal(await page.locator('#task-panel').evaluate(panel => panel.scrollWidth <= panel.clientWidth), true, 'Painel sem overflow ' + width);
        const aligned = await rows.first().evaluate(form => {
            const controls = [...form.children].filter(node => !node.hidden).map(node => node.getBoundingClientRect());
            return controls.every(rect => Math.abs(rect.top + rect.height / 2 - (controls[0].top + controls[0].height / 2)) < 3) && controls.at(-1).right <= form.getBoundingClientRect().right + 1;
        });
        assert.equal(aligned, true, 'Ações e checkbox na mesma linha ' + width);
        assert.equal(await page.locator('#task-content [style], #task-content script, #task-content style').count(), 0, 'Sem CSS/JS inline');
        if (width === 390 || width === 1440) {
            await audit(page, 'Painel ' + width);
            await page.screenshot({path: `${preview}/task-panel-${width}.png`});
            if (width === 390) {
                await rows.nth(3).locator('[data-item-pencil]').click();
                await page.screenshot({path: `${preview}/task-inline-edit-390.png`});
                await rows.nth(3).locator('[data-item-cancel]').click();
            }
            await page.locator('#task-content [data-checklists]').scrollIntoViewIfNeeded();
            await page.screenshot({path: `${preview}/task-checklists-${width}.png`});
        }
        await page.locator('#task-panel-close').press('Escape');
        await page.locator('#task-panel').waitFor({state: 'hidden'});
        assert.equal(await page.getByRole('button', {name: 'Detalhes no painel', exact: true}).evaluate(button => button === document.activeElement), true, 'Foco retorna ao card');
    }
    await page.getByRole('button', {name: 'Detalhes no painel', exact: true}).click(); await loaded(page);
    // A trap mantém Tab no painel, inclusive com navegação inferior ao fundo.
    await page.locator('#task-panel-close').focus(); await page.keyboard.press('Shift+Tab');
    assert.equal(await page.evaluate(() => document.getElementById('task-panel').contains(document.activeElement)), true);
    await page.getByRole('tab', {name: 'Comentários', exact: true}).click();
    const comment = page.locator('#task-content [data-task-action="comment.create"]');
    await comment.locator('textarea').fill('Posição preservada');
    const before = await page.locator('.tp-body').evaluate(body => body.scrollTop);
    await save(() => comment.getByRole('button').click()); await loaded(page);
    assert.equal(await page.locator('#task-content [data-task-action="comment.create"] textarea').evaluate(input => input === document.activeElement), true);
    assert.ok(Math.abs(await page.locator('.tp-body').evaluate(body => body.scrollTop) - before) < 100, 'Posição preservada após salvar');
    // Cancelar exclusão não altera dados; confirmar usa o contrato existente.
    await page.getByRole('tab', {name: 'Subtarefas', exact: true}).click();
    const item = page.locator('[data-task-action="item.update"]').first();
    page.once('dialog', dialog => dialog.dismiss()); await item.getByRole('button', {name: 'Excluir item', exact: true}).click();
    assert.equal(await item.count(), 1);
    page.once('dialog', dialog => dialog.accept()); await save(() => item.getByRole('button', {name: 'Excluir item', exact: true}).click()); await loaded(page);
    assert.equal(await rows.count(), 4);
    // Recuperação da leitura com falha sem manter editor de outra tarefa.
    await page.locator('#task-panel-close').click(); await page.locator('#task-panel').waitFor({state: 'hidden'});
    const route = /\/projects\/\d+\/tasks\/\d+/;
    await page.route(route, route => route.fulfill({status: 503, contentType: 'application/json', body: JSON.stringify({ok: false, message: 'Leitura indisponível.'})}));
    await page.getByRole('button', {name: 'Detalhes no painel', exact: true}).click(); await page.locator('#task-retry').waitFor();
    assert.equal(await page.locator('#task-edit').count(), 0); await audit(page, 'Erro de leitura');
    await page.unroute(route); await page.locator('#task-retry').click(); await loaded(page);
    await page.locator('#task-panel-close').click(); await page.locator('#task-panel').waitFor({state: 'hidden'});
    // Fixture colaborativa: comentários em duas páginas e leitura por papel.
    const ownerContext = await context.browser().newContext({viewport: {width: 390, height: 844}});
    async function login(target, role) {
        await target.goto(base + '/login'); await target.locator('#email').fill(fixture.users[role].email); await target.locator('#password').fill('Senha-equipe-ui-segura-123');
        await target.getByRole('button', {name: 'Entrar', exact: true}).click(); await target.waitForURL('**/dashboard');
        await target.goto(base + '/projects/' + fixture.projects.active + '/board'); await target.locator('.kanban-column').first().waitFor();
    }
    try {
        const owner = await ownerContext.newPage(); await login(owner, 'owner');
        // Dados de teste passam pela API autenticada, com CSRF e versão, no banco descartável.
        const taskId = await owner.evaluate(async () => {
            const root = document.getElementById('kanban'); let board = JSON.parse(document.getElementById('board-state').textContent);
            async function mutate(action, payload) {
                const response = await fetch(root.dataset.endpoint, {method: 'POST', headers: {'Content-Type': 'application/json', Accept: 'application/json'}, body: JSON.stringify({...payload, action, version: board.version, _token: root.dataset.csrf})});
                const result = await response.json(); if (!response.ok) throw new Error(result.message); board = result.board;
            }
            await mutate('task.create', {column_id: board.columns[0].id, title: 'Revisão colaborativa'}); const id = board.columns[0].tasks.at(-1).id;
            for (let index = 1; index <= 21; index++) await mutate('comment.create', {task_id: id, body: 'Atualização ' + index});
            return id;
        });
        const taskUrl = base + '/projects/' + fixture.projects.active + '/board?task=' + taskId;
        await owner.goto(taskUrl); await loaded(owner);
        await owner.getByRole('tab', {name: 'Comentários', exact: true}).click();
        const pages = owner.getByRole('navigation', {name: 'Páginas de comentários'});
        assert.equal(await pages.getByRole('button', {name: 'Anterior', exact: true}).isDisabled(), true);
        await pages.getByRole('button', {name: 'Próxima', exact: true}).click(); await owner.getByText('Página 2 de 2', {exact: true}).waitFor();
        assert.equal(await owner.locator('#task-content article').count(), 1);
        assert.equal(await pages.evaluate(nav => nav.contains(document.activeElement)), true, 'Foco fica na paginação');
        await owner.getByRole('tab', {name: 'Detalhes', exact: true}).click();
        const historyPages = owner.getByRole('navigation', {name: 'Páginas do histórico'});
        await historyPages.getByRole('button', {name: 'Próxima', exact: true}).click(); await historyPages.getByText('Página 2 de 2', {exact: true}).waitFor();
        assert.equal(await historyPages.evaluate(nav => nav.contains(document.activeElement)), true);
        await audit(owner, 'Paginação de comentários e histórico');
        for (const role of ['admin', 'member', 'viewer']) {
            const roleContext = await context.browser().newContext({viewport: {width: 390, height: 844}});
            try {
                const target = await roleContext.newPage(); await login(target, role); await target.goto(taskUrl); await loaded(target);
                assert.equal(await target.locator('[data-task-action="comment.update"]').count(), 0, 'Comentário de outra pessoa');
                assert.equal(await target.locator('[data-task-action="comment.delete"]').count(), role === 'admin' ? 20 : 0);
                assert.equal(await target.locator('#task-edit [name="title"]').isDisabled(), role === 'viewer');
                assert.equal(await target.locator('[data-task-action="comment.create"]').count(), role === 'viewer' ? 0 : 1);
                await audit(target, role);
            } finally { await roleContext.close(); }
        }
        await owner.goto(base + '/projects/' + fixture.projects.active); await owner.getByRole('button', {name: 'Arquivar projeto', exact: true}).click();
        await owner.goto(taskUrl); await loaded(owner);
        assert.equal(await owner.locator('#task-edit [name="title"]').isDisabled(), true);
        assert.equal(await owner.locator('#task-content [data-write-only]').count(), 0); await audit(owner, 'Projeto arquivado');
    } finally { await ownerContext.close(); }
    assert.deepEqual(errors, []);
    console.log('OK: painel Fluxo Claro 320–1440px, axe A/AA, checklist inline, 3/5 e 60%, falhas/rascunhos, foco/Escape, abas, exclusão, retry, paginação, quatro papéis e arquivados.');
}).catch(error => { console.error(error); process.exitCode = 1; });
