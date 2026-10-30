'use strict';
const assert = require('node:assert/strict');
const {default: AxeBuilder} = require('@axe-core/playwright');
const fs = require('node:fs/promises');
const runBoardBrowser = require('./board-browser.cjs');

runBoardBrowser(async ({page, context, save, boardUrl}) => {
    const base = new URL(boardUrl).origin;
    const preview = process.env.UI_PREVIEW_DIR || '/app/storage/previews';
    await fs.mkdir(preview, {recursive: true});
    await page.setViewportSize({width: 1280, height: 1000});
    await page.getByRole('button', {name: 'Título persistido', exact: true}).click();
    await page.getByRole('tab', {name: 'Detalhes', exact: true}).click();
    const edit = page.locator('#task-edit'); await edit.waitFor();
    const due = new Date(Date.now() + 2 * 86400000).toISOString().slice(0, 10);
    await edit.locator('[name="title"]').fill('Preparar a próxima entrega');
    await edit.locator('[name="due_on"]').fill(due);
    await edit.locator('[name="assignee_ids"]').first().check();
    await save(() => edit.getByRole('button', {name: 'Salvar detalhes'}).click()); await edit.waitFor();
    await page.locator('#task-panel-close').click();
    await page.goto(boardUrl.replace(/\/board$/, '/edit')); await page.locator('#name').fill('Lançamento do site');
    await page.getByRole('button', {name: 'Salvar projeto'}).click(); await page.waitForURL(/\/projects\/\d+$/);
    await page.goto(base + '/projects/new'); await page.locator('#name').fill('Próximas ideias');
    await page.getByRole('button', {name: 'Salvar projeto'}).click(); await page.waitForURL(/\/projects\/\d+$/);
    const emptyProjectUrl = page.url();
    async function audit(label, target = page) {
        const result = await new AxeBuilder({page: target}).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
        assert.deepEqual(result.violations.map(item => ({id: item.id, nodes: item.nodes.map(node => node.target)})), [], label);
    }
    for (const width of [320, 390, 768, 1024, 1440]) {
        await page.setViewportSize({width, height: width < 640 ? 844 : 1000}); await page.goto(base + '/dashboard');
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true, 'Sem overflow em ' + width);
        assert.equal(await page.locator('[data-metric="pending"]').innerText(), '1');
        assert.equal(await page.locator('[data-metric="active_projects"]').innerText(), '2');
        assert.equal(await page.locator('.dashboard [style], .dashboard style, .dashboard script').count(), 0, 'Dashboard sem CSS/JS inline');
        const values = await page.locator('progress').evaluateAll(items => items.map(item => item.value).sort((a, b) => a - b));
        assert.deepEqual(values, [0, 50], 'Progresso real e projeto sem tarefas');
        assert.equal(await page.locator('.dash-deadline strong').innerText(), 'Preparar a próxima entrega');
        assert.equal(await page.locator('.dash-deadline time').getAttribute('datetime'), due);
        for (const date of await page.locator('.dash-activity-content time').evaluateAll(items => items.map(item => item.dateTime))) assert.equal(Number.isNaN(Date.parse(date)), false, 'Timestamp ISO em UTC');
        if (width === 390 || width === 1440) {
            await audit('Dashboard ' + width);
            await page.screenshot({path: `${preview}/dashboard-${width}.png`, fullPage: false});
        }
    }
    for (const [key, query] of [['pending', 'status=pending'], ['in_progress', 'status=in_progress'], ['completed', 'status=completed'], ['overdue', 'due=overdue']]) {
        await page.goto(base + '/dashboard');
        await page.locator(`[data-metric="${key}"]`).click();
        await page.waitForURL(base + '/tasks?' + query);
    }
    await page.goto(base + '/dashboard'); await page.locator('.dash-deadline').click();
    await page.waitForURL(/\/board\?task=\d+/); await page.getByRole('tab', {name: 'Detalhes', exact: true}).click(); await edit.waitFor();
    assert.equal(await edit.locator('[name="title"]').inputValue(), 'Preparar a próxima entrega', 'Prazo abre tarefa correta');
    await edit.locator('[name="title"]').fill('<script>window.dashboardXss=true</script>');
    const saved = page.waitForResponse(response => response.request().method() === 'POST');
    await edit.getByRole('button', {name: 'Salvar detalhes'}).click(); assert.equal((await saved).status(), 200);
    await page.goto(base + '/dashboard');
    assert.match(await page.locator('.dash-deadline strong').innerText(), /<script>/);
    assert.equal(await page.evaluate(() => window.dashboardXss), undefined, 'Título é texto');
    await page.setViewportSize({width: 320, height: 640});
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true, 'Título longo não causa overflow');
    await page.locator('.dash-project-list').getByRole('link', {name: 'Próximas ideias', exact: true}).click(); await page.waitForURL(emptyProjectUrl + '/board');
    const noJs = await context.browser().newContext({javaScriptEnabled: false, viewport: {width: 390, height: 844}});
    try {
        await noJs.addCookies(await context.cookies()); const fallback = await noJs.newPage(); await fallback.goto(base + '/dashboard');
        assert.deepEqual(await fallback.locator('progress').evaluateAll(items => items.map(item => item.value).sort((a, b) => a - b)), [0, 50], 'Progresso sem JS');
        await fallback.locator('[data-metric="pending"]').click(); await fallback.waitForURL(base + '/tasks?status=pending');
    } finally { await noJs.close(); }
    const fresh = await context.browser().newContext({viewport: {width: 390, height: 844}});
    try {
        const empty = await fresh.newPage(); const email = `dashboard-empty-${Date.now()}@example.com`;
        await empty.goto(base + '/register'); await empty.locator('#name').fill('Marina'); await empty.locator('#email').fill(email);
        await empty.locator('#password').fill('Senha-dashboard-segura-123'); await empty.locator('#confirmation').fill('Senha-dashboard-segura-123');
        await empty.getByRole('button', {name: 'Criar conta', exact: true}).click(); await empty.waitForURL('**/dashboard');
        const mailpit = process.env.MAILPIT_TEST_URL || 'http://127.0.0.1:8025';
        const messages = await (await empty.request.get(mailpit + '/api/v1/messages?limit=100')).json();
        const message = messages.messages.find(item => item.To[0]?.Address === email);
        const detail = await (await empty.request.get(mailpit + '/api/v1/message/' + message.ID)).json();
        await empty.goto(detail.Text.match(/https?:\/\/[^\s]+\/email\/verify\?token=[a-f0-9]{64}/)[0]);
        await empty.getByRole('button', {name: 'Confirmar e-mail', exact: true}).click(); await empty.getByRole('status').waitFor();
        await empty.goto(base + '/dashboard');
        assert.deepEqual(await empty.locator('[data-metric]').allInnerTexts(), ['0', '0', '0', '0', '0']);
        assert.equal(await empty.locator('.dash-empty').count(), 3);
        await audit('Dashboard vazio', empty); await empty.screenshot({path: `${preview}/dashboard-empty-390.png`, fullPage: false});
    } finally { await fresh.close(); }
    console.log('OK: dashboard 320–1440px, axe A/AA, métricas/filtros, progresso nativo sem JS, prazos/deep links, datas UTC, XSS e conta vazia.');
}).catch(error => { console.error(error); process.exitCode = 1; });
