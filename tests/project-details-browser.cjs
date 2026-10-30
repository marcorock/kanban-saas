'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const {chromium} = require('playwright-core');
const {default: AxeBuilder} = require('@axe-core/playwright');

async function run() {
    if (process.env.AUTH_TEST_DATABASE !== 'disposable') throw new Error('Use somente banco/ambiente descartáveis.');
    const base = process.env.AUTH_TEST_URL || 'http://127.0.0.1:8000';
    const preview = process.env.UI_PREVIEW_DIR || '/app/storage/previews';
    const fixture = JSON.parse(await fs.readFile(process.env.PROJECTS_UI_FIXTURE || '/app/storage/previews/projects-fixture.json', 'utf8'));
    const browser = await chromium.launch({executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium-browser', args: ['--no-sandbox']});
    const context = await browser.newContext(); const page = await context.newPage(); const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    async function login(key) {
        await context.clearCookies(); await page.goto(base + '/login'); await page.locator('#email').fill(fixture.users[key].email);
        await page.locator('#password').fill('Senha-listagem-segura-123');
        await page.getByRole('button', {name: 'Entrar', exact: true}).click(); await page.waitForURL('**/dashboard');
    }
    async function audit(label) {
        const result = await new AxeBuilder({page}).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
        assert.deepEqual(result.violations.map(item => ({id: item.id, nodes: item.nodes.map(node => node.target)})), [], label);
    }
    async function postButton(name) {
        const response = page.waitForResponse(response => response.request().method() === 'POST');
        await page.getByRole('button', {name, exact: true}).click(); assert.equal((await response).status(), 303); await page.waitForLoadState('load');
    }
    try {
        await login('owner');
        for (const width of [320, 390, 768, 1024, 1440]) {
            await page.setViewportSize({width, height: width < 640 ? 844 : 1100});
            for (const archived of [false, true]) {
                await page.goto(base + '/projects/' + (archived ? fixture.archived : fixture.own));
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'Sem overflow ' + width);
                assert.equal(await page.locator('.project-details [style], .project-details style, .project-details script').count(), 0, 'Sem CSS/JS inline');
                assert.equal(await page.locator('.pd-description').evaluate(element => getComputedStyle(element).whiteSpace), 'pre-wrap');
                assert.equal(await page.getByRole('button', {name: archived ? 'Restaurar projeto' : 'Arquivar projeto', exact: true}).count(), 1);
                assert.equal(await page.getByRole('link', {name: 'Editar projeto', exact: true}).count(), archived ? 0 : 1);
                assert.equal(await page.locator('.pd-danger').getAttribute('open'), null, 'Exclusão recolhida');
                for (const date of await page.locator('.pd-dates time').evaluateAll(items => items.map(item => item.dateTime))) assert.equal(Number.isNaN(Date.parse(date)), false);
                if (width === 390 || width === 1440) {
                    await audit('Detalhes ' + width + ' ' + archived);
                    await page.screenshot({path: `${preview}/project-details-${archived ? 'archived-' : ''}${width}.png`, fullPage: false});
                }
            }
        }
        await page.setViewportSize({width: 390, height: 844}); await page.goto(base + '/projects/' + fixture.own);
        await page.getByRole('link', {name: 'Abrir quadro Kanban', exact: true}).click(); await page.locator('#kanban').waitFor();
        await page.goto(base + '/projects/' + fixture.own); await page.getByRole('link', {name: 'Participantes', exact: true}).click(); await page.waitForURL('**/members');
        await page.goto(base + '/projects/' + fixture.own); await page.getByRole('link', {name: 'Histórico do projeto', exact: true}).click(); await page.waitForURL('**/history');
        await page.goto(base + '/projects');
        const shared = {};
        for (const title of ['Identidade da marca', 'Planejamento de conteúdo', 'Pesquisa com clientes']) shared[title] = await page.getByRole('link', {name: 'Ver detalhes de ' + title, exact: true}).getAttribute('href');
        for (const [title, manages] of [['Identidade da marca', true], ['Planejamento de conteúdo', false], ['Pesquisa com clientes', false]]) {
            await page.goto(base + shared[title]);
            assert.equal(await page.getByRole('link', {name: 'Editar projeto', exact: true}).count(), manages ? 1 : 0);
            assert.equal(await page.locator('.pd-management').count(), 0, 'Gestão exclusiva do proprietário');
            const csrf = await page.locator('input[name="_token"]').first().inputValue();
            assert.equal((await page.request.post(base + shared[title] + '/archive', {form: {_token: csrf}, maxRedirects: 0})).status(), 403, 'Não contorna permissão');
            await audit('Papel ' + title);
        }
        await page.goto(base + '/projects/' + fixture.sharedArchive);
        assert.equal(await page.locator('.pd-management').count(), 0);
        assert.match(await page.locator('.pd-context').innerText(), /arquivado/);
        assert.equal((await page.request.get(base + '/projects/' + fixture.hidden)).status(), 404);
        await page.goto(base + '/projects/' + fixture.own);
        await postButton('Arquivar projeto'); await page.getByRole('status').filter({hasText: 'Projeto arquivado.'}).waitFor();
        assert.equal(await page.getByRole('link', {name: 'Editar projeto', exact: true}).count(), 0);
        await postButton('Restaurar projeto'); await page.getByRole('status').filter({hasText: 'Projeto restaurado.'}).waitFor();
        assert.equal(await page.getByRole('link', {name: 'Editar projeto', exact: true}).count(), 1);
        await page.goto(base + '/projects/new'); await page.locator('#name').fill('Projeto temporário de teste');
        await postButton('Salvar projeto'); const disposableUrl = page.url();
        const danger = page.locator('.pd-danger'); await danger.locator('summary').click();
        await page.getByRole('button', {name: 'Excluir projeto', exact: true}).click();
        assert.equal(page.url(), disposableUrl); assert.equal(await page.locator('#confirm').evaluate(input => input.matches(':invalid')), true, 'Confirmação obrigatória');
        await audit('Exclusão expandida');
        assert.equal((await page.request.post(disposableUrl + '/delete', {form: {_token: 'invalid', confirm: 'delete'}, maxRedirects: 0})).status(), 419);
        assert.equal((await page.request.get(disposableUrl)).status(), 200, 'CSRF não exclui');
        await page.locator('#confirm').check(); await postButton('Excluir projeto'); await page.waitForURL(base + '/projects');
        await page.getByRole('status').filter({hasText: 'Projeto excluído.'}).waitFor();
        assert.equal((await page.request.get(disposableUrl)).status(), 404);
        const noJs = await browser.newContext({javaScriptEnabled: false, viewport: {width: 390, height: 844}});
        try {
            await noJs.addCookies(await context.cookies()); const fallback = await noJs.newPage(); await fallback.goto(base + '/projects/' + fixture.own);
            await fallback.getByRole('button', {name: 'Arquivar projeto', exact: true}).click(); await fallback.getByRole('status').filter({hasText: 'Projeto arquivado.'}).waitFor();
            await fallback.getByRole('button', {name: 'Restaurar projeto', exact: true}).click(); await fallback.getByRole('status').filter({hasText: 'Projeto restaurado.'}).waitFor();
            await fallback.locator('.pd-danger summary').click(); assert.equal(await fallback.locator('#confirm').isVisible(), true, 'Disclosure nativo sem JS');
        } finally { await noJs.close(); }
        await login('xss'); await page.setViewportSize({width: 320, height: 640}); await page.goto(base + '/projects/' + fixture.xss);
        assert.match(await page.locator('.pd-intro h2').innerText(), /<script>/);
        assert.match(await page.locator('.pd-description').innerText(), /<img/);
        assert.equal(await page.evaluate(() => window.projectsXss), undefined);
        assert.equal(await page.locator('.project-details img, .project-details script').count(), 0);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
        await audit('Texto longo e XSS');
        assert.deepEqual(errors, [], 'Sem erros JS');
        console.log('OK: detalhes 320–1440px, axe A/AA, papéis, arquivar/restaurar/excluir, confirmação/CSRF, sem JS, atalhos, consulta e XSS.');
    } finally { await browser.close(); }
}
run().catch(error => { console.error(error); process.exitCode = 1; });
