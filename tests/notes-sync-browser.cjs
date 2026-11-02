'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const {chromium} = require('playwright-core');
const {default: AxeBuilder} = require('@axe-core/playwright');
(async () => {
    if (process.env.AUTH_TEST_DATABASE !== 'disposable') throw new Error('Use banco descartável.');
    const f = JSON.parse(await fs.readFile('/app/storage/previews/notes-fixture.json', 'utf8'));
    const base = process.env.AUTH_TEST_URL; const project = base + '/projects/' + f.project;
    const browser = await chromium.launch({executablePath: '/usr/bin/chromium-browser', args: ['--no-sandbox']});
    try {
        const owner = await browser.newContext(); const member = await browser.newContext();
        const page = await owner.newPage(); const other = await member.newPage(); const errors = [];
        page.on('pageerror', error => errors.push(error.message)); other.on('pageerror', error => errors.push(error.message));
        async function login(target, key) {
            await target.clock.install({time: new Date('2026-11-02T12:00:00Z')}); await target.clock.pauseAt(new Date('2026-11-02T12:00:01Z')); await target.goto(base + '/login');
            await target.locator('#email').fill(f.users[key].email); await target.locator('#password').fill('Senha-notas-ui-123');
            await target.getByRole('button', {name: 'Entrar', exact: true}).click(); await target.waitForURL('**/dashboard'); await target.goto(project);
        }
        await login(page, 'owner'); await login(other, 'other');
        const csrf = {owner: await page.locator('[data-note-form] [name="_token"]').inputValue(), member: await other.locator('[data-note-form] [name="_token"]').inputValue()};
        async function change(context, path, data, key = 'owner') {
            const response = await context.request.post(base + path, {form: {_token: csrf[key], ...data}, headers: {Accept: 'application/json'}});
            assert.equal(response.status(), 200, await response.text()); return response.json();
        }
        async function rows(context, scope = '?project_id=' + f.project) {
            const response = await context.request.get(base + '/notes/state' + scope); assert.equal(response.status(), 200);
            const state = await response.json(); return page.evaluate(html => [...new DOMParser().parseFromString(html, 'text/html').querySelectorAll('[data-note-id]')].map(card => ({...card.dataset})), state.html);
        }
        async function tick(target = page, ms = 7000) {
            const response = target.waitForResponse(r => r.url().includes('/notes/version?'));
            await target.clock.fastForward(ms); const received = await response;
            if (received.status() === 200) { const meta = await received.json(); await target.waitForFunction(token => document.querySelector('[data-note-section]')?.dataset.noteToken === token, meta.token); }
            else await target.waitForFunction(() => document.querySelectorAll('[data-note-section] .qn-card').length === 0);
        }
        let snapshots = 0; let navigations = 0; page.on('request', r => { if (r.url().includes('/notes/state?')) snapshots++; if (r.isNavigationRequest()) navigations++; });
        await tick(); assert.equal(snapshots, 1); await page.locator('[data-note-section]').evaluate(n => n.dataset.marker = 'estavel');
        await tick(); assert.equal(snapshots, 1); assert.equal(await page.locator('[data-note-section]').getAttribute('data-marker'), 'estavel');
        await change(member, '/notes', {body: 'Nota invisível para o proprietário', project_id: f.project, visibility: 'private'}, 'member');
        await tick(); assert.equal(snapshots, 1, 'Privada alheia não altera versão visível');
        await change(member, '/notes', {body: '<script>window.syncXss=true</script>\n' + 'Ideia longa '.repeat(200), title: 'Ideia remota', project_id: f.project, visibility: 'project'}, 'member');
        const note = (await rows(owner)).find(row => row.noteTitle === 'Ideia remota'); const card = page.locator(`[data-note-id="${note.noteId}"]`);
        const own = page.locator('[data-note-id]').first(); const ownId = await own.getAttribute('data-note-id'); const ownCard = page.locator(`[data-note-id="${ownId}"]`); await own.locator('summary').click(); await own.locator('summary').focus();
        await page.evaluate(() => scrollTo(0, 200)); const scroll = await page.evaluate(() => scrollY);
        await tick(); await card.waitFor(); assert.equal(await ownCard.locator('details').evaluate(n => n.open), true); assert.equal(await ownCard.locator('summary').evaluate(n => n === document.activeElement), true); assert.equal(await page.evaluate(() => scrollY), scroll);
        assert.equal(await page.evaluate(() => window.syncXss), undefined); await card.locator('.qn-body').evaluate(n => n.scrollTop = 90);
        await change(member, '/notes/' + note.noteId + '/update', {body: 'Atualizada ' + 'ideia '.repeat(500), title: 'Ideia editada', color: 'orange', revision: 1}, 'member'); await tick();
        assert.equal(await card.getAttribute('data-note-title'), 'Ideia editada'); assert.equal(await card.locator('.qn-body').evaluate(n => n.scrollTop), 90);
        await page.locator(`[data-note-id="${ownId}"] .qn-card-actions [data-note-edit]`).first().click(); await page.locator('#quick-note-body').fill('Rascunho preservado');
        const ownRevision = await page.locator('[data-note-form] [name="revision"]').inputValue();
        await change(owner, '/notes/' + ownId + '/update', {body: 'Alterada em outro dispositivo', revision: ownRevision}); await tick();
        assert.equal(await page.locator('#quick-note-body').inputValue(), 'Rascunho preservado');
        const conflict = page.waitForResponse(r => r.request().method() === 'POST' && r.url().endsWith('/notes/' + ownId + '/update'));
        await page.getByRole('button', {name: 'Salvar nota', exact: true}).click(); assert.equal((await conflict).status(), 409); assert.equal(await page.locator('#quick-note-body').inputValue(), 'Rascunho preservado');
        await page.getByRole('button', {name: 'Cancelar', exact: true}).click();
        console.log('OK: metadata, privacidade, criação/edição, foco/menus/rolagem e rascunho em conflito');
        await change(member, '/notes/' + note.noteId + '/transfer', {recipient_id: f.users.owner.id, confirm: '1', revision: 2}, 'member'); await tick(); assert.equal(await card.locator('[data-note-edit]').count(), 2);
        await change(owner, '/notes/' + note.noteId + '/update', {body: 'Privada depois da transferência', visibility: 'private', revision: 3}); await tick(); await tick(other);
        assert.equal(await other.locator(`[data-note-id="${note.noteId}"]`).count(), 0, 'Privatização retira conteúdo do outro usuário');
        await change(owner, '/notes/' + note.noteId + '/update', {body: 'Movida ao Inbox', project_id: '', visibility: 'private', revision: 4}); await tick(); assert.equal(await card.count(), 0);
        const inboxContext = await browser.newContext({storageState: await owner.storageState()}); const inbox = await inboxContext.newPage(); await inbox.clock.install({time: new Date('2026-11-02T12:00:00Z')}); await inbox.clock.pauseAt(new Date('2026-11-02T12:00:01Z')); await inbox.goto(base + '/tasks'); await tick(inbox); await inbox.locator(`[data-note-id="${note.noteId}"]`).waitFor();
        await change(owner, '/notes/' + note.noteId + '/update', {body: 'Volta ao projeto', project_id: f.project, visibility: 'project', revision: 5}); await tick(inbox); await tick(); assert.equal(await inbox.locator(`[data-note-id="${note.noteId}"]`).count(), 0); await card.waitFor(); await inboxContext.close();
        await owner.setOffline(true); await page.evaluate(() => window.dispatchEvent(new Event('offline'))); await page.clock.fastForward(7000);
        await change(member, '/notes', {body: 'Criada offline no outro navegador', title: 'Após reconexão', project_id: f.project, visibility: 'project'}, 'member');
        await owner.setOffline(false); await page.evaluate(() => window.dispatchEvent(new Event('online'))); await tick(page, 0); await page.locator('[data-note-title="Após reconexão"]').waitFor();
        await page.evaluate(() => { window.testHidden = true; Object.defineProperty(document, 'hidden', {configurable: true, get: () => window.testHidden}); document.dispatchEvent(new Event('visibilitychange')); });
        const before = snapshots; await change(member, '/notes', {body: 'Retorno da aba', title: 'Após aba inativa', project_id: f.project, visibility: 'project'}, 'member'); await page.clock.fastForward(70000); assert.equal(snapshots, before);
        await page.evaluate(() => { window.testHidden = false; document.dispatchEvent(new Event('visibilitychange')); }); await tick(page, 0); await page.locator('[data-note-title="Após aba inativa"]').waitFor();
        await change(owner, '/notes/' + note.noteId + '/convert', {project_id: f.project, column_id: f.column, title: 'Tarefa sincronizada', revision: 6}); await tick(); assert.equal(await card.count(), 0);
        const remove = (await rows(member)).find(row => row.noteTitle === 'Após reconexão'); await change(member, '/notes/' + remove.noteId + '/delete', {revision: 1}, 'member'); await tick(); assert.equal(await page.locator(`[data-note-id="${remove.noteId}"]`).count(), 0);
        console.log('OK: transferência, visibilidade, Inbox, reconexão, aba inativa e conversão/exclusão');
        assert.equal(navigations, 0, 'Sem refresh ou navegação integral');
        const auditContext = await browser.newContext({storageState: await owner.storageState()}); const auditPage = await auditContext.newPage(); await auditPage.goto(project);
        for (const width of [320, 390, 768, 1024, 1440]) { await auditPage.setViewportSize({width, height: 1000}); assert.equal(await auditPage.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true); const audit = await new AxeBuilder({page: auditPage}).include('#brainstorm').withTags(['wcag2a','wcag2aa','wcag21aa','wcag22aa']).analyze(); assert.deepEqual(audit.violations.map(v => v.id), [], 'Axe ' + width); }
        await auditContext.close();
        // Revogação da sessão apaga a lista, preservando o texto digitado no modal.
        await page.locator(`[data-note-id="${ownId}"] .qn-card-actions [data-note-edit]`).first().click(); await page.locator('#quick-note-body').fill('Rascunho após sessão encerrada');
        const logout = await owner.request.post(base + '/logout', {form: {_token: csrf.owner}, maxRedirects: 0}); assert.equal(logout.status(), 303); await tick();
        assert.equal(await page.locator('[data-note-section] .qn-card').count(), 0); assert.equal(await page.locator('#quick-note-body').inputValue(), 'Rascunho após sessão encerrada');
        assert.deepEqual(errors, []); console.log('OK: dois usuários/dispositivos, metadata sem snapshot ocioso, privacidade, revisão/rascunho, criação/edição/exclusão/conversão/transferência, Inbox, foco/menus/rolagem, reconexão, aba inativa, sessão revogada e axe 320–1440px.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
