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
    async function audit(label) {
        const result = await new AxeBuilder({page}).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
        assert.deepEqual(result.violations.map(item => ({id: item.id, nodes: item.nodes.map(node => node.target)})), [], label);
    }
    async function submit(status) {
        const response = page.waitForResponse(response => response.request().method() === 'POST');
        await page.getByRole('button', {name: 'Salvar projeto', exact: true}).click();
        assert.equal((await response).status(), status); await page.waitForLoadState('load');
    }
    try {
        await page.goto(base + '/login'); await page.locator('#email').fill(fixture.users.owner.email);
        await page.locator('#password').fill('Senha-listagem-segura-123');
        await page.getByRole('button', {name: 'Entrar', exact: true}).click(); await page.waitForURL('**/dashboard');
        for (const width of [320, 390, 768, 1024, 1440]) {
            await page.setViewportSize({width, height: width < 640 ? 844 : 1200});
            for (const edit of [false, true]) {
                await page.goto(base + (edit ? `/projects/${fixture.own}/edit` : '/projects/new'));
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, width + ' ' + edit);
                assert.equal(await page.locator('.project-editor [style], .project-editor style, .project-editor script').count(), 0, 'Sem CSS/JS inline');
                assert.equal(await page.locator('.pf-field input, .pf-field textarea').evaluateAll(items => items.every(item => parseFloat(getComputedStyle(item).fontSize) >= 16 && item.getBoundingClientRect().height >= 44)), true, 'Campos legíveis e confortáveis');
                if (width === 390 || width === 1440) {
                    await audit('Formulário ' + width + ' ' + edit);
                    await page.screenshot({path: `${preview}/projects-${edit ? 'edit' : 'new'}-${width}.png`, fullPage: false});
                }
            }
        }
        await page.setViewportSize({width: 390, height: 844}); await page.goto(base + '/projects/new');
        await page.locator('#name').fill('Evolução');
        assert.equal(await page.locator('[data-count-for="name"]').innerText(), '8 / 120');
        await page.locator('#starts_on').fill('2026-11-10');
        assert.equal(await page.locator('#due_on').getAttribute('min'), '2026-11-10');
        await page.locator('#starts_on').fill('');
        assert.equal(await page.locator('#due_on').getAttribute('min'), '1000-01-01');
        const name = '<script>window.projectFormXss=true</script>';
        const description = '</textarea><img src=x onerror=window.projectFormXss=true>';
        await page.locator('#name').fill(name); await page.locator('#description').fill(description);
        await page.locator('#starts_on').fill('2026-11-10'); await page.locator('#due_on').fill('2026-11-01');
        await page.locator('[data-project-form]').evaluate(form => { form.noValidate = true; });
        await submit(422); await page.getByRole('alert').waitFor();
        assert.equal(await page.locator('#project-form-errors').evaluate(item => item === document.activeElement), true, 'Foco no erro do servidor');
        assert.equal(await page.locator('#name').inputValue(), name);
        assert.equal(await page.locator('#description').inputValue(), description);
        assert.equal(await page.locator('#due_on').inputValue(), '2026-11-01');
        assert.equal(await page.evaluate(() => window.projectFormXss), undefined);
        assert.equal(await page.locator('.project-editor img, .project-editor script').count(), 0, 'Descrição e nome tratados como texto');
        await audit('Erro de datas');
        await page.locator('#due_on').fill('2026-11-20');
        await page.locator('[data-project-form] input[name="_token"]').evaluate(input => { input.value = 'invalid'; });
        await submit(419); await page.getByRole('alert').filter({hasText: 'expirou'}).waitFor();
        assert.equal(await page.locator('#name').inputValue(), name, 'Preserva dados em 419');
        assert.equal(await page.locator('#description').inputValue(), description);
        await page.locator('#name').fill('Plano de trabalho'); await page.locator('#description').fill('Uma entrega de cada vez.');
        await submit(303); await page.waitForURL(/\/projects\/\d+$/); const created = page.url();
        await page.goto(created + '/edit');
        assert.equal(await page.locator('#name').inputValue(), 'Plano de trabalho');
        await page.locator('#name').fill('Plano atualizado'); await page.locator('#starts_on').fill(''); await page.locator('#due_on').fill('');
        await submit(303); await page.waitForURL(created); await page.goto(created + '/edit');
        assert.equal(await page.locator('#name').inputValue(), 'Plano atualizado');
        assert.equal(await page.locator('#starts_on').inputValue(), ''); assert.equal(await page.locator('#due_on').inputValue(), '');
        await page.locator('#name').fill('Não salvar'); await page.getByRole('link', {name: 'Cancelar', exact: true}).click(); await page.waitForURL(created);
        await page.goto(created + '/edit'); assert.equal(await page.locator('#name').inputValue(), 'Plano atualizado', 'Cancelar não altera dados');
        await page.goto(base + '/projects/new'); await page.getByRole('link', {name: 'Cancelar', exact: true}).click(); await page.waitForURL(base + '/projects');
        for (const [title, expected] of [['Identidade da marca', 200], ['Planejamento de conteúdo', 403], ['Pesquisa com clientes', 403]]) {
            const href = await page.getByRole('link', {name: 'Ver detalhes de ' + title, exact: true}).getAttribute('href');
            assert.equal((await page.request.get(base + href + '/edit')).status(), expected, 'Papel: ' + title);
        }
        assert.equal((await page.request.get(base + '/projects/' + fixture.archived + '/edit')).status(), 409);
        assert.equal((await page.request.get(base + '/projects/' + fixture.hidden + '/edit')).status(), 404);
        const noJs = await browser.newContext({javaScriptEnabled: false, viewport: {width: 390, height: 844}});
        try {
            await noJs.addCookies(await context.cookies()); const fallback = await noJs.newPage(); await fallback.goto(base + '/projects/new');
            assert.equal(await fallback.locator('[data-count-for="name"]').isVisible(), false);
            await fallback.locator('#name').fill('Projeto sem JavaScript');
            await fallback.getByRole('button', {name: 'Salvar projeto', exact: true}).click(); await fallback.waitForURL(/\/projects\/\d+$/);
            await fallback.goto(fallback.url() + '/edit');
            assert.equal(await fallback.locator('#name').inputValue(), 'Projeto sem JavaScript');
        } finally { await noJs.close(); }
        assert.deepEqual(errors, [], 'Sem erros JavaScript');
        console.log('OK: formulários 320–1440px, axe A/AA, criação/edição, datas opcionais, erros/foco/preservação, 419, XSS, cancelamento, papéis e envio sem JS.');
    } finally { await browser.close(); }
}
run().catch(error => { console.error(error); process.exitCode = 1; });
