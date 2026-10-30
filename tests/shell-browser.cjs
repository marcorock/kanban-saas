'use strict';
const assert = require('node:assert/strict');
const {default: AxeBuilder} = require('@axe-core/playwright');
const fs = require('node:fs/promises');
const runBoardBrowser = require('./board-browser.cjs');

runBoardBrowser(async ({page, context, boardUrl}) => {
    const base = new URL(boardUrl).origin;
    const preview = process.env.UI_PREVIEW_DIR || '/app/storage/previews';
    await fs.mkdir(preview, {recursive: true});
    async function audit(label) {
        // Componentes dos módulos serão migrados em incrementos próprios.
        const result = await new AxeBuilder({page}).include('.app-sidebar').include('.app-header').include('.app-bottom-nav').include('.app-footer').include('#app-more-dialog').withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
        assert.deepEqual(result.violations.map(item => ({id: item.id, nodes: item.nodes.map(node => node.target)})), [], label);
    }
    for (const width of [320, 390, 768, 1024, 1440]) {
        await page.setViewportSize({width, height: 844});
        for (const path of ['/dashboard', '/projects', '/tasks', '/history', '/settings', new URL(boardUrl).pathname]) {
            assert.equal((await page.goto(base + path)).status(), 200);
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true, width + path);
            assert.equal(await page.locator('.app-sidebar').isVisible(), width >= 1024);
            assert.equal(await page.locator('.app-bottom-nav').isVisible(), width < 1024);
            assert.equal(await page.locator('.app-sidebar [style], .app-header [style], .app-bottom-nav [style], .app-more-dialog [style]').count(), 0, 'Shell sem estilos inline');
            assert.equal(await page.locator('h1').count(), 1);
        }
        await page.goto(base + '/dashboard');
        if (width === 390 || width === 1440) {
            await audit('Shell ' + width);
            await page.screenshot({path: `${preview}/shell-${width}.png`, fullPage: false});
        }
    }
    await page.setViewportSize({width: 390, height: 844}); await page.goto(base + '/dashboard');
    const more = page.getByRole('button', {name: 'Mais', exact: true});
    const dialog = page.getByRole('dialog', {name: 'Mais opções'});
    await more.click(); await dialog.waitFor();
    assert.equal(await more.getAttribute('aria-expanded'), 'true');
    assert.equal(await dialog.getByRole('button', {name: 'Fechar mais opções'}).evaluate(element => element === document.activeElement), true);
    // O foco deve permanecer no diálogo, mesmo depois de percorrer seus controles.
    for (let i = 0; i < 9; i++) {
        await page.keyboard.press('Tab');
        assert.equal(await dialog.evaluate(element => element.contains(document.activeElement)), true, 'Foco contido no painel');
    }
    await audit('Mais opções');
    await page.screenshot({path: `${preview}/shell-menu-390.png`, fullPage: false});
    await page.keyboard.press('Escape'); await dialog.waitFor({state: 'hidden'});
    assert.equal(await more.evaluate(element => element === document.activeElement), true, 'Devolve foco ao fechar');
    assert.equal(await more.getAttribute('aria-expanded'), 'false');
    await more.click(); await dialog.getByRole('link', {name: 'Meu Histórico', exact: true}).click();
    await page.waitForURL('**/history');
    assert.equal(await page.locator('[data-more-open]').getAttribute('data-active'), 'true', 'Atividade secundária sinalizada');
    await more.click(); await dialog.getByRole('link', {name: 'Configurações', exact: true}).click(); await page.waitForURL('**/settings');
    await more.click(); await page.setViewportSize({width: 1280, height: 900});
    await dialog.waitFor({state: 'hidden'});
    assert.equal(await page.locator('body').evaluate(element => element.classList.contains('app-modal-open')), false, 'Resize libera a página');
    assert.equal(await page.locator('.app-nav a[aria-current]').innerText(), 'Configurações');
    assert.equal(await page.locator('.app-nav a[aria-current]').evaluate(element => element === document.activeElement), true, 'Resize devolve foco ao destino ativo no desktop');
    await page.goto(boardUrl.replace(/\/board$/, '/history'));
    assert.equal(await page.locator('.app-nav a[aria-current]').innerText(), 'Projetos', 'Contexto de projeto');
    assert.equal(await page.locator('.app-nav a[aria-current]').getAttribute('aria-current'), 'location');
    // Coexistência com offcanvas Bootstrap: a barra não deve cobrir o painel de tarefa.
    await page.setViewportSize({width: 390, height: 844}); await page.goto(boardUrl);
    await page.getByRole('button', {name: 'Título persistido', exact: true}).click(); await page.getByRole('tab', {name: 'Detalhes', exact: true}).click(); await page.locator('#task-edit').waitFor();
    const close = page.locator('#task-panel-close');
    await page.waitForFunction(() => {
        const element = document.getElementById('task-panel-close'); const rect = element.getBoundingClientRect();
        return element.contains(document.elementFromPoint(rect.x + rect.width / 2, rect.y + rect.height / 2));
    }, null, {timeout: 5000});
    await close.click(); await page.waitForFunction(() => !document.getElementById('task-panel').classList.contains('show'));
    const noJs = await context.browser().newContext({javaScriptEnabled: false, viewport: {width: 390, height: 844}});
    try {
        await noJs.addCookies(await context.cookies());
        const fallback = await noJs.newPage(); await fallback.goto(base + '/dashboard');
        assert.equal(await fallback.locator('.app-more-fallback').isVisible(), true);
        await fallback.locator('.app-more-fallback summary').click();
        await fallback.locator('.app-more-fallback').getByRole('link', {name: 'Configurações', exact: true}).click(); await fallback.waitForURL('**/settings');
        await fallback.locator('.app-more-fallback summary').click();
        await fallback.locator('.app-more-fallback').getByRole('button', {name: 'Sair', exact: true}).click(); await fallback.waitForURL('**/login');
    } finally { await noJs.close(); }
    console.log('OK: shell 320–1440px, axe A/AA dos componentes, destinos/estado ativo, foco/Escape/resize, offcanvas, navegação e saída sem JavaScript.');
}).catch(error => { console.error(error); process.exitCode = 1; });
