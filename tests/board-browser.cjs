'use strict';
const assert = require('node:assert/strict');
const {chromium} = require('playwright-core');

async function runBoardBrowser(extra = null) {
    if (process.env.AUTH_TEST_DATABASE !== 'disposable') throw new Error('Use somente ambiente e banco descartáveis.');
    const base = process.env.AUTH_TEST_URL || 'http://127.0.0.1:8000';
    const mailpit = process.env.MAILPIT_TEST_URL || 'http://127.0.0.1:8025';
    const browser = await chromium.launch({executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium-browser', args: ['--no-sandbox']});
    const context = await browser.newContext({viewport: {width: 1280, height: 1000}});
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    try {
        const email = `browser-${Date.now()}@example.com`;
        const password = 'Senha-browser-segura-123';
        await page.goto(base + '/register');
        await page.locator('#name').fill('Teste de navegador'); await page.locator('#email').fill(email);
        await page.locator('#password').fill(password); await page.locator('#confirmation').fill(password);
        await Promise.all([page.waitForURL('**/dashboard'), page.getByRole('button', {name: 'Criar conta', exact: true}).click()]);
        const messages = await (await page.request.get(mailpit + '/api/v1/messages?limit=100')).json();
        const message = messages.messages.find(message => message.To[0]?.Address === email);
        assert.ok(message, 'Confirmação no Mailpit');
        const detail = await (await page.request.get(mailpit + '/api/v1/message/' + message.ID)).json();
        const link = detail.Text.match(/https?:\/\/[^\s]+\/email\/verify\?token=[a-f0-9]{64}/)[0];
        await page.goto(link);
        await Promise.all([page.waitForResponse(response => response.request().method() === 'POST'), page.getByRole('button', {name: 'Confirmar e-mail', exact: true}).click()]);
        await page.goto(base + '/projects/new'); await page.locator('#name').fill('Quadro no navegador');
        await Promise.all([page.waitForURL(/\/projects\/\d+$/), page.getByRole('button', {name: 'Salvar projeto'}).click()]);
        await page.getByRole('link', {name: 'Abrir quadro Kanban'}).click();
        await page.locator('.kanban-tasks').first().waitFor();
        const boardUrl = page.url();
        const column = index => page.locator('.kanban-column').nth(index);
        async function save(operation, expected = 200) {
            const [response] = await Promise.all([page.waitForResponse(response => response.url() === boardUrl && response.request().method() === 'POST'), operation()]);
            assert.equal(response.status(), expected);
            await page.waitForFunction(() => document.getElementById('kanban').getAttribute('aria-busy') !== 'true');
            return response;
        }
        for (const title of ['Primeira tarefa', 'Segunda tarefa']) {
            const form = column(0).locator('form[data-action="task.create"]');
            await form.locator('input').fill(title);
            await save(() => form.getByRole('button').click());
        }
        assert.equal(await column(0).locator('.kanban-task').count(), 2);
        // Drag real no Chromium, entre listas Sortable aninhadas.
        await save(() => column(0).locator('.kanban-task-handle').first().dragTo(column(1).locator('.kanban-tasks')));
        assert.equal(await column(0).locator('.kanban-task').count(), 1);
        assert.equal(await column(1).locator('.kanban-task').count(), 1);
        const cardId = await column(1).locator('.kanban-task').getAttribute('data-id');
        // Resposta de erro: a posição visual deve voltar à última versão confirmada.
        await page.route(boardUrl, route => route.request().method() === 'POST'
            ? route.fulfill({status: 500, contentType: 'application/json', body: JSON.stringify({ok: false, message: 'Falha simulada de gravação.'})}) : route.continue());
        await save(() => column(1).locator('.kanban-task-handle').dragTo(column(0).locator('.kanban-tasks')), 500);
        assert.equal(await column(1).locator(`.kanban-task[data-id="${cardId}"]`).count(), 1, 'Restaurar posição após falha');
        assert.match(await page.locator('#board-message').innerText(), /Falha simulada/);
        await page.unroute(boardUrl);
        // A versão desatualizada em uma segunda aba não pode sobrescrever mudanças.
        const stale = await context.newPage(); await stale.goto(boardUrl); await stale.locator('.kanban-tasks').first().waitFor();
        await column(0).locator('.kanban-task details summary').click();
        const rename = column(0).locator('form[data-action="task.rename"]');
        await rename.locator('input').fill('Título persistido'); await save(() => rename.getByRole('button').click());
        await stale.locator('#new-column-name').fill('Conflito');
        const [conflict] = await Promise.all([stale.waitForResponse(response => response.url() === boardUrl && response.request().method() === 'POST'), stale.getByRole('button', {name: 'Adicionar coluna'}).click()]);
        assert.equal(conflict.status(), 409);
        await stale.waitForFunction(() => document.getElementById('kanban').getAttribute('aria-busy') !== 'true');
        assert.equal(await stale.locator('.kanban-column').count(), 5);
        await stale.close();
        // Conclusão via controle de formulário também funciona sem arrastar horizontalmente.
        await column(1).locator('.kanban-task details summary').click();
        const move = column(1).locator('form[data-action="task.move"]');
        const doneId = await column(4).getAttribute('data-id');
        await move.locator('select').selectOption(doneId); await save(() => move.getByRole('button').click());
        assert.equal(await column(4).getByText('Concluída', {exact: true}).innerText(), 'Concluída');
        // Ordenação de colunas via formulário e persistência após reload.
        await column(0).locator(':scope > details > summary').click();
        const originalFirst = await column(0).getAttribute('data-id');
        const position = column(0).locator('form[data-action="column.position"]');
        await position.locator('select').selectOption({value: '1'});
        await save(() => position.getByRole('button').click());
        assert.equal(await column(1).getAttribute('data-id'), originalFirst);
        await page.reload(); await page.locator('.kanban-tasks').first().waitFor();
        assert.equal(await column(1).getAttribute('data-id'), originalFirst);
        assert.ok((await page.locator('#kanban').innerText()).includes('Título persistido'));
        const beforeColumnDrag = await column(0).getAttribute('data-id');
        await save(() => column(0).locator(':scope > div > .kanban-handle').dragTo(column(1), {targetPosition: {x: 240, y: 20}}));
        assert.equal(await column(1).getAttribute('data-id'), beforeColumnDrag, 'Arrastar coluna');
        await page.setViewportSize({width: 390, height: 844});
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true, 'Sem overflow horizontal da página no mobile');
        assert.equal(await page.locator('#kanban').evaluate(item => item.scrollWidth > item.clientWidth), true, 'Quadro com rolagem própria');
        if (extra) await extra({page, context, save, boardUrl});
        assert.deepEqual(errors, [], 'Sem erros de JavaScript');
        console.log('OK: Chromium, cadastro/e-mail, cards, drag and drop, restauração em erro, conflito, conclusão, persistência e mobile.');
    } finally { await browser.close(); }
}

module.exports = runBoardBrowser;
if (require.main === module) runBoardBrowser().catch(error => { console.error(error); process.exitCode = 1; });
