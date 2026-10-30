'use strict';
const assert = require('node:assert/strict');
const runBoardBrowser = require('./board-browser.cjs');

async function runTasksBrowser(extra = null) {
return runBoardBrowser(async ({page, save, context, boardUrl}) => {
        // Painel de detalhes com metadados, responsáveis e conteúdo em texto simples.
        await page.setViewportSize({width: 1280, height: 1000});
        await page.getByRole('button', {name: 'Título persistido', exact: true}).click();
        await page.getByRole('tab', {name: 'Detalhes', exact: true}).click();
        const editTask = page.locator('#task-edit'); await editTask.waitFor();
        await editTask.locator('[name="title"]').fill('Detalhes no painel');
        await editTask.locator('[name="description"]').fill('<script>window.taskXss = true</script>\nDescrição da tarefa');
        await editTask.locator('[name="priority"]').selectOption('urgent');
        await editTask.locator('[name="starts_on"]').fill('2000-01-01');
        await editTask.locator('[name="due_on"]').fill('2000-01-02');
        await editTask.locator('[name="started_at"]').fill('2000-01-01T12:00');
        await editTask.locator('[name="assignee_ids"]').first().check();
        const assigneeId = await editTask.locator('[name="assignee_ids"]').first().getAttribute('value');
        await save(() => editTask.getByRole('button', {name: 'Salvar detalhes'}).click());
        await editTask.waitFor();
        assert.equal(await editTask.locator('[name="title"]').inputValue(), 'Detalhes no painel');
        assert.equal(await editTask.locator('[name="priority"]').inputValue(), 'urgent');
        assert.equal(await editTask.locator('[name="assignee_ids"]').first().isChecked(), true);
        assert.equal(await page.evaluate(() => window.taskXss), undefined);
        await page.getByRole('tab', {name: 'Subtarefas', exact: true}).click();
        await page.locator('.tp-new-checklist > summary').click();
        const checklist = page.locator('#task-content form[data-task-action="checklist.create"]');
        await checklist.locator('input').fill('Lista de entrega'); await save(() => checklist.getByRole('button').click());
        const itemCreate = page.locator('#task-content form[data-task-action="item.create"]'); await itemCreate.waitFor();
        await itemCreate.locator('input').fill('Revisar a entrega'); await save(() => itemCreate.getByRole('button').click());
        const itemUpdate = page.locator('#task-content form[data-task-action="item.update"]'); await itemUpdate.waitFor();
        await save(() => itemUpdate.locator('[name="is_done"]').check());
        await page.locator('#task-content h4').waitFor(); assert.equal(await page.locator('[data-list-count]').innerText(), '1/1');
        await page.getByRole('tab', {name: 'Comentários', exact: true}).click();
        const comment = page.locator('#task-content form[data-task-action="comment.create"]');
        await comment.locator('textarea').fill('<img src=x onerror="window.taskXss=true"> Comentário literal'); await save(() => comment.getByRole('button').click());
        await page.locator('#task-content article').waitFor();
        assert.match(await page.locator('#task-content article .task-note').innerText(), /<img src=x/);
        assert.equal(await page.evaluate(() => window.taskXss), undefined);
        await page.locator('#task-content article summary').click();
        const commentUpdate = page.locator('#task-content form[data-task-action="comment.update"]');
        await commentUpdate.locator('textarea').fill('Comentário revisado'); await save(() => commentUpdate.getByRole('button').click());
        await page.locator('#task-content article').waitFor(); assert.equal(await page.locator('#task-content article .task-note').innerText(), 'Comentário revisado');
        // Erro de validação mantém o rascunho no painel e os controles utilizáveis.
        await page.getByRole('tab', {name: 'Detalhes', exact: true}).click();
        await editTask.locator('[name="title"]').fill(''); await save(() => editTask.getByRole('button', {name: 'Salvar detalhes'}).click(), 422);
        assert.equal(await editTask.locator('[name="title"]').inputValue(), '');
        assert.equal(await editTask.locator('[name="title"]').isEnabled(), true);
        assert.match(await page.locator('#task-message').innerText(), /textos|limites/i);
        page.once('dialog', dialog => dialog.dismiss()); await page.locator('#task-panel-close').click();
        assert.equal(await page.locator('#task-panel').evaluate(item => item.classList.contains('show')), true, 'Confirmar descarte');
        page.once('dialog', dialog => dialog.accept()); await page.locator('#task-panel-close').click();
        await page.waitForFunction(() => !document.getElementById('task-panel').classList.contains('show'));
        // Leitura com falha não mantém um editor apontando para dados anteriores.
        const detailRoute = /\/projects\/\d+\/tasks\/\d+/;
        await page.route(detailRoute, route => route.fulfill({status: 404, contentType: 'application/json', body: JSON.stringify({ok: false, message: 'Falha simulada de leitura.'})}));
        await page.getByRole('button', {name: 'Detalhes no painel', exact: true}).click();
        await page.getByText('Falha simulada de leitura.', {exact: true}).waitFor();
        assert.equal(await page.locator('#task-content').innerText(), '', 'Não exibir editor antigo em falha de leitura');
        await page.locator('#task-panel-close').click();
        await page.waitForFunction(() => !document.getElementById('task-panel').classList.contains('show'));
        await page.unroute(detailRoute);
        await page.locator('#board-filters > summary').click();
        await page.locator('#filter-priority').selectOption('high'); assert.equal(await page.locator('.kanban-task').count(), 0);
        await page.locator('#filter-priority').selectOption('urgent'); assert.equal(await page.locator('.kanban-task').count(), 1);
        await page.locator('#filter-assignee').selectOption(assigneeId); await page.locator('#filter-due').selectOption('overdue');
        assert.equal(await page.locator('.kanban-task').count(), 1); assert.match(await page.locator('.kanban-task').innerText(), /Atrasada/);
        assert.equal(await page.evaluate(() => Sortable.get(document.getElementById('kanban')) == null), true, 'Drag indisponível em listagem filtrada');
        for (const id of ['filter-priority', 'filter-assignee', 'filter-due']) await page.locator('#' + id).selectOption({value: ''});
        await page.setViewportSize({width: 390, height: 844}); await page.getByRole('button', {name: 'Detalhes no painel', exact: true}).click();
        await page.getByRole('tab', {name: 'Detalhes', exact: true}).click(); await editTask.waitFor();
        assert.equal(await editTask.locator('[name="description"]').inputValue(), '<script>window.taskXss = true</script>\nDescrição da tarefa');
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true, 'Painel mobile sem overflow');
        await page.locator('#task-panel-close').click();
        await page.waitForFunction(() => !document.getElementById('task-panel').classList.contains('show'));

    if (extra) await extra({page, save, context, boardUrl});
    console.log('OK: painel, responsáveis, datas, checklists, comentários, XSS, rascunhos, filtros e mobile.');
});
}
module.exports = runTasksBrowser;
if (require.main === module) runTasksBrowser().catch(error => { console.error(error); process.exitCode = 1; });
