'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const {chromium} = require('playwright-core');
const {default: AxeBuilder} = require('@axe-core/playwright');

async function run() {
    if (process.env.AUTH_TEST_DATABASE !== 'disposable') throw new Error('Use somente ambiente/banco descartáveis.');
    const base = process.env.AUTH_TEST_URL || 'http://127.0.0.1:8000';
    const preview = process.env.UI_PREVIEW_DIR || '/app/storage/previews';
    const fixture = JSON.parse(await fs.readFile(process.env.PROJECTS_UI_FIXTURE || '/app/storage/previews/projects-fixture.json', 'utf8'));
    const browser = await chromium.launch({executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium-browser', args: ['--no-sandbox']});
    const context = await browser.newContext(); const page = await context.newPage(); const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    async function login(key) {
        await context.clearCookies(); await page.goto(base + '/login');
        await page.locator('#email').fill(fixture.users[key].email); await page.locator('#password').fill('Senha-listagem-segura-123');
        await page.getByRole('button', {name: 'Entrar', exact: true}).click(); await page.waitForURL('**/dashboard');
    }
    async function audit(label) {
        const result = await new AxeBuilder({page}).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
        assert.deepEqual(result.violations.map(item => ({id: item.id, nodes: item.nodes.map(node => node.target)})), [], label);
    }
    try {
        await login('owner');
        for (const width of [320, 390, 768, 1024, 1440]) {
            await page.setViewportSize({width, height: width < 640 ? 844 : 1000});
            for (const archived of [false, true]) {
                await page.goto(base + '/projects' + (archived ? '?status=archived' : ''));
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'Sem overflow ' + width);
                assert.equal(await page.locator('.pl-card').count(), archived ? 2 : 6);
                assert.equal(await page.locator('.pl-filters [aria-current]').innerText(), archived ? 'Arquivados' : 'Ativos');
                assert.equal(await page.locator('.project-listing [style], .project-listing style, .project-listing script').count(), 0, 'Sem CSS/JS inline');
                assert.doesNotMatch(await page.locator('main').innerText(), /PRIVADO NÃO MOSTRAR|EXCLUIDO NÃO MOSTRAR/);
                if (width === 390 || width === 1440) {
                    await audit('Projetos ' + width + ' ' + archived);
                    await page.screenshot({path: `${preview}/projects-${archived ? 'archived-' : ''}${width}.png`, fullPage: false});
                }
            }
        }
        await page.setViewportSize({width: 390, height: 844}); await page.goto(base + '/projects');
        await page.keyboard.press('Tab'); await page.keyboard.press('Enter');
        assert.equal(await page.locator('#app-content').evaluate(element => element === document.activeElement), true, 'Acesso por teclado ao conteúdo');
        await page.getByRole('link', {name: 'Abrir quadro de Pesquisa com clientes', exact: true}).click(); await page.locator('#kanban').waitFor();
        assert.match(await page.locator('main').innerText(), /acesso de leitura/);
        assert.equal(await page.getByRole('button', {name: 'Adicionar coluna', exact: true}).count(), 0);
        await page.goto(base + '/projects?status=archived');
        await page.getByRole('link', {name: 'Consultar quadro de Pesquisa anterior', exact: true}).click(); await page.locator('#kanban').waitFor();
        assert.match(await page.locator('main').innerText(), /Projeto arquivado/);
        assert.equal(await page.getByRole('button', {name: 'Adicionar coluna', exact: true}).count(), 0);
        assert.equal((await page.request.get(base + '/projects/' + fixture.hidden + '/board')).status(), 404, 'Link não contorna escopo');
        const noJs = await browser.newContext({javaScriptEnabled: false, viewport: {width: 390, height: 844}});
        try {
            await noJs.addCookies(await context.cookies()); const fallback = await noJs.newPage(); await fallback.goto(base + '/projects');
            await fallback.locator('.pl-filters').getByRole('link', {name: 'Arquivados', exact: true}).click(); await fallback.waitForURL('**/projects?status=archived');
            await fallback.getByRole('link', {name: 'Ver detalhes de Primeiro ciclo de entregas', exact: true}).click(); await fallback.waitForURL(base + '/projects/' + fixture.archived);
        } finally { await noJs.close(); }
        await login('pager');
        for (const status of ['active', 'archived']) {
            await page.goto(base + '/projects?status=' + status);
            assert.equal(await page.locator('.pl-card').count(), 25);
            await page.getByRole('link', {name: 'Próxima', exact: true}).click();
            await page.waitForURL(base + '/projects?status=' + status + '&page=2');
            assert.equal(await page.locator('.pl-card').count(), 1);
            assert.match(await page.locator('.pl-pagination').innerText(), /Página 2 de 2/);
            await page.getByRole('link', {name: 'Anterior', exact: true}).click(); await page.waitForURL(base + '/projects?status=' + status + '&page=1');
        }
        await login('xss'); await page.setViewportSize({width: 320, height: 640}); await page.goto(base + '/projects');
        assert.match(await page.locator('.pl-card h3').innerText(), /<script>/);
        assert.equal(await page.evaluate(() => window.projectsXss), undefined);
        assert.equal(await page.locator('.pl-card img, .pl-card script').count(), 0);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'Nome longo não causa overflow');
        await audit('Nomes e descrição longos');
        await login('empty'); await page.setViewportSize({width: 390, height: 844});
        for (const archived of [false, true]) {
            await page.goto(base + '/projects' + (archived ? '?status=archived' : ''));
            assert.equal(await page.locator('.pl-empty').count(), 1); await audit('Lista vazia ' + archived);
            await page.screenshot({path: `${preview}/projects-empty-${archived ? 'archived-' : ''}390.png`, fullPage: false});
        }
        await page.locator('.pl-empty').getByRole('link', {name: 'Voltar aos ativos'}).click(); await page.waitForURL(base + '/projects');
        await page.getByRole('link', {name: 'Criar projeto', exact: true}).click(); await page.waitForURL(base + '/projects/new');
        assert.deepEqual(errors, [], 'Sem erros JavaScript');
        console.log('OK: listagem 320–1440px, axe A/AA, papéis, arquivados, quadro viewer, paginação/filtro, XSS, vazios, teclado e navegação sem JS.');
    } finally { await browser.close(); }
}
run().catch(error => { console.error(error); process.exitCode = 1; });
