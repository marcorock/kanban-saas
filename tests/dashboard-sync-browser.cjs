'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const {chromium} = require('playwright-core');
const {default: AxeBuilder} = require('@axe-core/playwright');
(async () => {
    if (process.env.AUTH_TEST_DATABASE !== 'disposable') throw new Error('Use banco descartável.');
    const f = JSON.parse(await fs.readFile(process.env.DASHBOARD_SYNC_FIXTURE || '/tmp/kanban-dashboard-sync.json', 'utf8'));
    const base = process.env.AUTH_TEST_URL || 'http://127.0.0.1:8000';
    const browser = await chromium.launch({executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', args: ['--no-sandbox']});
    try {
        const actorContext = await browser.newContext(); const context = await browser.newContext();
        const actor = await actorContext.newPage(); const page = await context.newPage();
        const errors = []; page.on('pageerror', e => errors.push(e.message));
        async function login(target, role) {
            await target.goto(base + '/login'); await target.locator('#email').fill(f.users[role].email);
            await target.locator('#password').fill(f.password); await target.getByRole('button', {name: 'Entrar', exact: true}).click();
            await target.waitForURL('**/dashboard');
            await target.goto(base + '/workspaces/' + f.workspaceId + '/dashboard');
        }
        await login(actor, 'owner'); await page.clock.install(); await login(page, 'member');
        const endpoint = base + '/projects/' + f.project + '/board';
        await actor.goto(endpoint);
        const csrf = await actor.locator('#kanban').getAttribute('data-csrf');
        async function change(action, data) {
            const state = (await (await actorContext.request.get(endpoint + '/state')).json()).board;
            const response = await actorContext.request.post(endpoint, {data: {action, version: state.version, _token: csrf, ...data}});
            assert.equal(response.status(), 200, await response.text()); return (await response.json()).board;
        }
        let versions = 0; let snapshots = 0; let navigations = 0;
        page.on('request', r => {
            if (r.isNavigationRequest()) navigations++;
            const mode = new URL(r.url()).searchParams.get('sync');
            if (mode === 'version') versions++; if (mode === 'state') snapshots++;
        });
        async function tick(ms = 7000) {
            const response = page.waitForResponse(r => new URL(r.url()).searchParams.get('sync') === 'version');
            await page.clock.fastForward(ms); await response; await page.waitForTimeout(150);
        }
        await tick(); assert.equal(snapshots, 0, 'Sem leitura completa quando nada mudou');
        let state = (await (await actorContext.request.get(endpoint + '/state')).json()).board;
        const first = state.columns[0].id; const done = state.columns[4].id;
        state = await change('task.create', {column_id: first, title: '<script>window.syncXss=true</script>'});
        const task = state.columns[0].tasks[0].id;
        await change('task.update', {task_id: task, title: '<script>window.syncXss=true</script>', description: '', priority: 'normal', starts_on: '', due_on: new Date().toISOString().slice(0, 10), assignee_ids: [f.users.member.id]});
        await tick(); await page.locator('[data-metric="pending"]').filter({hasText: '1'}).waitFor();
        assert.equal(await page.locator('.dash-deadline').count(), 1);
        assert.ok((await page.locator('.dash-activity-list').innerText()).includes('<script>window.syncXss=true</script>'));
        assert.equal(await page.evaluate(() => window.syncXss), undefined);
        await page.locator('.dash-overview').evaluate(n => n.dataset.marker = 'retido');
        const snapshotsBefore = snapshots; await tick(); assert.equal(snapshots, snapshotsBefore);
        await page.locator('.dash-deadline').focus();
        await change('task.move', {task_id: task, column_id: done, position: 0}); await tick();
        assert.equal(await page.locator('.dash-deadline').evaluate(n => n === document.activeElement), true, 'Foco preservado');
        assert.equal(await page.locator('[data-metric="completed"]').innerText(), '0', 'Aplicação adiada durante foco');
        await page.locator('.dash-welcome h2').click(); await tick();
        assert.equal(await page.locator('[data-metric="completed"]').innerText(), '1');
        assert.equal(await page.locator('.dash-progress').getAttribute('value'), '100');
        assert.equal(await page.locator('.dash-deadline').count(), 0);
        await page.locator('.dash-overview').evaluate(n => n.dataset.marker = 'retido');
        await change('task.rename', {task_id: task, title: 'Atualizado remotamente'}); await tick();
        assert.equal(await page.locator('.dash-overview').getAttribute('data-marker'), 'retido', 'Bloco sem mudança preservado');
        await context.setOffline(true); await page.evaluate(() => window.dispatchEvent(new Event('offline')));
        await change('task.rename', {task_id: task, title: 'Após reconexão'});
        await context.setOffline(false); await page.evaluate(() => window.dispatchEvent(new Event('online'))); await tick(0);
        assert.match(await page.locator('.dash-activity-list').innerText(), /Após reconexão/);
        await page.evaluate(() => { window.testHidden = true; Object.defineProperty(document, 'hidden', {configurable: true, get: () => window.testHidden}); document.dispatchEvent(new Event('visibilitychange')); });
        const before = versions; await change('task.rename', {task_id: task, title: 'Retorno à aba'});
        await page.clock.fastForward(70000); assert.equal(versions, before, 'Aba inativa não consulta');
        await page.evaluate(() => { window.testHidden = false; document.dispatchEvent(new Event('visibilitychange')); }); await tick(0);
        assert.match(await page.locator('.dash-activity-list').innerText(), /Retorno à aba/);
        assert.equal(navigations, 0, 'Nenhum reload');
        for (const width of [320, 1440]) {
            await page.setViewportSize({width, height: 900});
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
            const audit = await new AxeBuilder({page}).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
            assert.deepEqual(audit.violations.map(v => v.id), []);
        }
        const fallback = await browser.newContext({javaScriptEnabled: false}); await fallback.addCookies(await context.cookies());
        const noJs = await fallback.newPage(); await noJs.goto(base + '/workspaces/' + f.workspaceId + '/dashboard');
        assert.equal(await noJs.locator('[data-metric="completed"]').innerText(), '1');
        assert.equal(await noJs.locator('.dash-progress').getAttribute('value'), '100');
        await fallback.close();
        const logoutToken = await page.locator('form[action="/logout"] input[name="_token"]').first().inputValue();
        await context.request.post(base + '/logout', {form: {_token: logoutToken}});
        await tick(); const stopped = versions; await page.clock.fastForward(60000);
        assert.equal(versions, stopped, 'Sessão encerrada interrompe polling');
        assert.deepEqual(errors, []);
        console.log('OK: dois usuários, polling condicional, indicadores/prazos/progresso/atividades, foco, DOM estável, XSS, offline, aba inativa/retorno, sem reload, axe 320/1440 fallback sem JS e interrupção após logout.');
    } finally { await browser.close(); }
})().catch(e => {console.error(e); process.exitCode = 1;});
