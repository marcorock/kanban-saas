'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const {default: AxeBuilder} = require('@axe-core/playwright');
const runBoardBrowser = require('./board-browser.cjs');

runBoardBrowser(async ({page, context, save, boardUrl}) => {
    const base = new URL(boardUrl).origin;
    const preview = process.env.UI_PREVIEW_DIR || '/app/storage/previews';
    const fixture = JSON.parse(await fs.readFile(preview + '/members-fixture.json', 'utf8'));
    async function audit(target, label) {
        const result = await new AxeBuilder({page: target}).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
        assert.deepEqual(result.violations.map(item => ({id: item.id, nodes: item.nodes.map(node => node.target)})), [], label);
    }
    await page.setViewportSize({width: 1440, height: 1100});
    for (const [index, title] of [[0, 'Organizar a próxima entrega'], [1, 'Revisar os detalhes']]) {
        const form = page.locator('.kanban-column').nth(index).locator('[data-action="task.create"]');
        await form.locator('input').fill(title); await save(() => form.getByRole('button').click());
        assert.equal(await page.locator('.kanban-column').nth(index).locator('[data-action="task.create"] input').evaluate(input => input === document.activeElement), true, 'Foco volta para criar próxima tarefa');
    }
    for (const width of [320, 390, 768, 1024, 1440]) {
        await page.setViewportSize({width, height: width < 640 ? 844 : 1100}); await page.goto(boardUrl);
        await page.locator('[data-task-open]').first().waitFor();
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'Sem overflow da página ' + width);
        assert.equal(await page.locator('#kanban').evaluate(root => root.scrollWidth > root.clientWidth), true, 'Rolagem própria');
        assert.equal(await page.locator('.board-page [style], .board-page style, .board-page script').count(), 0, 'Sem CSS/JS inline no quadro');
        if (width === 390 || width === 1440) { await audit(page, 'Quadro ' + width); await page.screenshot({path: `${preview}/kanban-${width}.png`, fullPage: false}); }
    }
    await page.setViewportSize({width: 390, height: 844}); await page.goto(boardUrl);
    await page.getByRole('button', {name: 'Próxima coluna', exact: true}).click();
    await page.waitForFunction(() => document.getElementById('kanban').scrollLeft > 100);
    await page.locator('#kanban').evaluate(root => new Promise(resolve => {
        let previous = root.scrollLeft, stable = 0;
        function frame() { const current = root.scrollLeft; stable = Math.abs(current - previous) < 1 ? stable + 1 : 0; previous = current; if (stable >= 8) resolve(); else requestAnimationFrame(frame); }
        requestAnimationFrame(frame);
    }));
    const create = page.locator('.kanban-column').nth(1).locator('[data-action="task.create"]');
    await create.locator('input').fill('Persistir na coluna atual');
    const scroll = await page.locator('#kanban').evaluate(root => root.scrollLeft);
    await save(() => create.locator('input').press('Enter'));
    const savedScroll = await page.locator('#kanban').evaluate(root => root.scrollLeft);
    assert.ok(Math.abs(savedScroll - scroll) < 4, 'Mantém posição horizontal ao salvar');
    await page.locator('#board-filters > summary').click(); await page.locator('#filter-priority').selectOption('urgent');
    assert.equal(await page.locator('.kanban-task').count(), 0); assert.match(await page.locator('#filter-count').innerText(), /1 filtro/);
    assert.equal(await page.locator('#filter-note').isVisible(), true);
    await audit(page, 'Filtros abertos');
    await page.getByRole('button', {name: 'Limpar filtros', exact: true}).click();
    assert.ok(await page.locator('.kanban-task').count() > 0); assert.equal(await page.locator('#filter-note').isVisible(), false);
    for (const [key, archived] of [['member', false], ['viewer', false], ['owner', true]]) {
        const roleContext = await context.browser().newContext({viewport: {width: 390, height: 844}});
        try {
            const target = await roleContext.newPage(); await target.goto(base + '/login');
            await target.locator('#email').fill(fixture.users[key].email); await target.locator('#password').fill('Senha-equipe-ui-segura-123');
            await target.getByRole('button', {name: 'Entrar', exact: true}).click(); await target.waitForURL('**/dashboard');
            await target.goto(base + '/projects/' + fixture.projects[archived ? 'archived' : 'active'] + '/board');
            await target.locator('.kanban-column').first().waitFor();
            assert.equal(await target.locator('#column-create').count(), 0, 'Sem gestão de colunas');
            assert.equal(await target.locator('#kanban [data-action="task.create"]').count(), key === 'member' ? 5 : 0);
            await audit(target, key + archived);
        } finally { await roleContext.close(); }
    }
    const fallbackContext = await context.browser().newContext({javaScriptEnabled: false, viewport: {width: 390, height: 844}});
    try {
        await fallbackContext.addCookies(await context.cookies()); const fallback = await fallbackContext.newPage(); await fallback.goto(boardUrl);
        assert.ok(await fallback.locator('.kanban-task').count() > 0);
        assert.equal(await fallback.locator('.kb-toolbar').isVisible(), false);
        assert.match(await fallback.locator('noscript').innerText(), /consulta/);
    } finally { await fallbackContext.close(); }
    console.log('OK: Kanban 320–1440px, axe A/AA, templates, foco/rolagem, navegação de colunas, filtros/limpeza, permissões e consulta sem JS.');
}).catch(error => { console.error(error); process.exitCode = 1; });
