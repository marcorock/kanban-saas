'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const {chromium} = require('playwright-core');
const {default: AxeBuilder} = require('@axe-core/playwright');

(async () => {
    if (process.env.AUTH_TEST_DATABASE !== 'disposable') throw new Error('Use somente banco descartável.');
    const base = process.env.AUTH_TEST_URL || 'http://127.0.0.1:8000';
    const preview = process.env.UI_PREVIEW_DIR || '/app/storage/previews';
    const fixture = JSON.parse(await fs.readFile(preview + '/my-tasks-fixture.json', 'utf8'));
    const browser = await chromium.launch({executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium-browser', args: ['--no-sandbox']});
    const context = await browser.newContext({viewport: {width: 1440, height: 1000}});
    const page = await context.newPage(); const errors = []; page.on('pageerror', error => errors.push(error.message));
    async function login(target, role) {
        await target.goto(base + '/login'); await target.locator('#email').fill(fixture.users[role].email); await target.locator('#password').fill('Senha-minhas-tarefas-123');
        await target.getByRole('button', {name: 'Entrar', exact: true}).click(); await target.waitForURL('**/dashboard'); await target.goto(base + '/tasks');
    }
    async function audit(target, label) {
        const result = await new AxeBuilder({page: target}).withTags(['wcag2a','wcag2aa','wcag21aa','wcag22aa']).analyze();
        assert.deepEqual(result.violations.map(item => ({id: item.id, targets: item.nodes.map(node => node.target)})), [], label);
    }
    async function filters() { if (await page.locator('#my-task-filters').getAttribute('open') === null) await page.locator('#my-task-filters > summary').click(); }
    async function submit(key, value) {
        await Promise.all([page.waitForURL(url => url.searchParams.get(key) === value), page.getByRole('button', {name: 'Filtrar', exact: true}).click()]);
    }
    try {
        await login(page, 'owner');
        for (const width of [320,390,768,1024,1440]) {
            await page.setViewportSize({width, height: width < 640 ? 844 : 1000}); await page.goto(base + '/tasks');
            assert.equal(await page.locator('.mt-total').innerText(), '27 tarefas');
            assert.equal(await page.locator('.mt-task').count(), 25); assert.equal(await page.locator('.mt-status [aria-current]').innerText(), 'Pendentes');
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'Sem overflow ' + width);
            assert.equal(await page.locator('.my-tasks-page [style], .my-tasks-page style, .my-tasks-page script').count(), 0);
            assert.equal(await page.locator('.mt-list input[type="checkbox"]').count(), 0, 'Consulta não simula ação de concluir');
            if (width === 390 || width === 1440) { await audit(page, 'Lista ' + width); await page.screenshot({path: `${preview}/my-tasks-${width}.png`}); }
        }
        // Filtros nativos com teclado e preservação do estado em links/paginação.
        await page.locator('#my-task-filters > summary').focus(); await page.keyboard.press('Enter');
        assert.equal(await page.locator('#priority').isVisible(), true);
        assert.equal(await page.locator('#project option').filter({hasText: 'Projeto restrito'}).count(), 0);
        await page.locator('#priority').selectOption('urgent'); await submit('priority', 'urgent');
        assert.equal(await page.locator('.mt-task').count(), 1); assert.match(await page.locator('.mt-task-date').innerText(), /Atrasada/);
        await page.getByRole('navigation', {name: 'Estado das minhas tarefas'}).getByRole('link', {name: 'Iniciadas', exact: true}).click();
        assert.equal(new URL(page.url()).searchParams.get('priority'), 'urgent'); assert.equal(await page.locator('.mt-task').count(), 1);
        await page.locator('#my-task-filters').getByRole('link', {name: 'Limpar filtros', exact: true}).click();
        await page.getByRole('navigation', {name: 'Paginação de tarefas'}).getByRole('link', {name: 'Próxima', exact: true}).click();
        assert.equal(await page.locator('.mt-task').count(), 2); assert.match(await page.locator('.mt-task-title').last().innerText(), /<script>/);
        assert.equal(await page.evaluate(() => window.myTasksXss), undefined);
        await page.setViewportSize({width: 320, height: 844}); assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
        await audit(page, 'Título longo e HTML literal');
        await page.goto(base + '/tasks?status=all&archived=all');
        assert.equal(await page.locator('.mt-total').innerText(), '29 tarefas');
        await page.getByRole('navigation', {name: 'Paginação de tarefas'}).getByRole('link', {name: 'Próxima', exact: true}).click();
        assert.equal(new URL(page.url()).searchParams.get('archived'), 'all'); assert.equal(new URL(page.url()).searchParams.get('status'), 'all');
        await page.goto(base + '/tasks?status=completed'); assert.equal(await page.locator('.mt-task').count(), 1); assert.match(await page.locator('.mt-task-date').innerText(), /Concluída/); await audit(page, 'Concluídas');
        await page.goto(base + '/tasks?archived=archived'); assert.equal(await page.locator('.mt-task').count(), 1); assert.match(await page.locator('.mt-context').innerText(), /consulta/);
        await page.locator('.mt-task-title').click(); await page.locator('[data-readonly]').waitFor();
        assert.equal(await page.locator('#task-content [data-write-only]').count(), 0);
        await page.goto(base + '/tasks?status=all'); await filters(); await page.locator('#due').selectOption('upcoming'); await submit('due','upcoming'); assert.equal(await page.locator('.mt-task').count(), 1);
        await page.locator('#due').selectOption('none'); await submit('due','none'); assert.equal(await page.locator('.mt-total').innerText(), '26 tarefas');
        await page.goto(base + '/tasks'); await filters(); await page.locator('#project').selectOption(String(fixture.projects.active)); await page.locator('#from').fill('1999-01-01'); await page.locator('#to').fill('2001-01-01'); await submit('from','1999-01-01');
        assert.equal(await page.locator('.mt-task').count(), 1); await audit(page, 'Filtros abertos');
        await page.setViewportSize({width:390,height:844}); await page.screenshot({path: `${preview}/my-tasks-filters-390.png`});
        await page.locator('.mt-task-title').click(); await page.waitForURL('**/board?task=' + fixture.tasks.active[1]); await page.locator('[data-task-heading]').waitFor();
        assert.equal(await page.locator('[data-task-heading]').innerText(), 'Revisar os textos');
        const bad = await page.goto(base + '/tasks?from=2026-12-02&to=2026-12-01'); assert.equal(bad.status(), 422); await page.getByRole('heading', {name: 'Revise os filtros.', exact:true}).waitFor(); await audit(page, 'Erro 422');
        const restricted = await page.goto(base + '/tasks?project_id=' + fixture.projects.private); assert.equal(restricted.status(), 404);
        for (const role of ['viewer','empty']) {
            const other = await browser.newContext({viewport:{width:390,height:844}});
            try {
                const target = await other.newPage(); await login(target,role);
                assert.equal(await target.locator('.mt-task').count(), role === 'viewer' ? 1 : 0); await audit(target, role);
                if (role === 'viewer') { await target.locator('.mt-task-title').click(); await target.locator('[data-readonly]').waitFor(); assert.equal(await target.locator('#task-content [data-write-only]').count(), 0); }
                else await target.screenshot({path: `${preview}/my-tasks-empty-390.png`});
            } finally { await other.close(); }
        }
        const fallback = await browser.newContext({javaScriptEnabled:false,viewport:{width:390,height:844}});
        try {
            await fallback.addCookies(await context.cookies()); const target = await fallback.newPage(); await target.goto(base + '/tasks');
            await target.locator('#my-task-filters > summary').click(); await target.locator('#priority').selectOption('urgent'); await target.getByRole('button',{name:'Filtrar',exact:true}).click();
            assert.equal(await target.locator('.mt-task').count(),1); await target.getByRole('navigation',{name:'Estado das minhas tarefas'}).getByRole('link',{name:'Iniciadas',exact:true}).click();
            assert.equal(new URL(target.url()).searchParams.get('priority'),'urgent'); assert.equal(await target.locator('.mt-task').count(),1);
        } finally { await fallback.close(); }
        assert.deepEqual(errors,[]);
        console.log('OK: Minhas Tarefas 320–1440px, axe A/AA, filtros/estados, paginação, XSS, papéis, arquivados, deep link, 422/404 e uso sem JS.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
