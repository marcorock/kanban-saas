'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const {chromium} = require('playwright-core');
const {default: AxeBuilder} = require('@axe-core/playwright');
(async () => {
    if (process.env.AUTH_TEST_DATABASE !== 'disposable') throw new Error('Use banco descartável.');
    const f = JSON.parse(await fs.readFile('/app/storage/previews/notes-fixture.json', 'utf8'));
    const base = process.env.AUTH_TEST_URL;
    const browser = await chromium.launch({executablePath: '/usr/bin/chromium-browser', args: ['--no-sandbox']});
    async function login(page) { await page.goto(base + '/login'); await page.locator('#email').fill(f.users.owner.email); await page.locator('#password').fill('Senha-notas-ui-123'); await page.getByRole('button', {name: 'Entrar', exact: true}).click(); await page.waitForURL('**/dashboard'); }
    try {
        const context = await browser.newContext(); const page = await context.newPage(); const errors = []; page.on('pageerror', e => errors.push(e.message));
        await login(page); await page.goto(base + '/tasks'); await page.keyboard.press('Control+k');
        const form = page.locator('#quick-note-form'); let posts = 0; page.on('request', request => { if (request.method() === 'POST' && request.url() === base + '/notes') posts++; });
        assert.equal(await form.locator('[name="color"]:checked').inputValue(), 'yellow'); assert.equal(await page.locator('#quick-note-body').evaluate(n => n === document.activeElement), true);
        const backgrounds = new Set();
        for (const [value, theme] of [['green', 'priority-baixa'], ['yellow', 'priority-media'], ['orange', 'priority-alta']]) {
            await form.locator(`[name="color"][value="${value}"]`).check();
            assert.ok((await form.getAttribute('class')).includes(theme)); backgrounds.add(await form.evaluate(n => getComputedStyle(n).backgroundColor));
            assert.equal(await form.locator('[style]').count(), 0);
        }
        assert.equal(backgrounds.size, 3);
        for (const selector of ['.pi-title', '.pi-body']) { await form.locator(selector).focus(); const style = await form.locator(selector).evaluate(n => { const s = getComputedStyle(n); return [s.borderTopWidth, s.outlineWidth, s.boxShadow]; }); assert.deepEqual(style, ['0px', '0px', 'none'], 'Sem caixas no foco'); }
        await form.locator('.pi-body').fill('Texto para testar submit implícito'); await form.locator('.pi-title').press('Enter'); await page.waitForTimeout(100); assert.equal(posts, 0, 'Enter no título não envia'); await form.locator('.pi-body').fill(''); assert.equal(await page.locator('#quick-note-dialog').evaluate(n => n.open), true);
        assert.equal(await form.getByText('Enter salva', {exact: false}).count(), 0); assert.equal(await form.locator('.pi-title').evaluate(n => getComputedStyle(n).borderTopWidth), '0px');
        assert.equal(await form.locator('.pi-body').evaluate(n => getComputedStyle(n).backgroundColor), 'rgba(0, 0, 0, 0)');
        for (const width of [320, 390, 768, 1024, 1440]) {
            await page.setViewportSize({width, height: width === 320 ? 568 : 900});
            assert.equal(await page.locator('#quick-note-dialog').evaluate(n => n.scrollWidth <= n.clientWidth), true, 'Sem overflow ' + width);
            const audit = await new AxeBuilder({page}).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze(); assert.deepEqual(audit.violations.map(v => ({id: v.id, targets: v.nodes.map(n => n.target)})), [], 'Axe ' + width);
            if (width === 390) await page.locator('#quick-note-dialog').screenshot({path: '/app/storage/previews/postit-form-390.png'});
        }
        await page.locator('#quick-note-heading').fill('Papel de verdade'); await page.locator('#quick-note-body').fill('Primeira linha'); await page.locator('#quick-note-body').press('Enter'); await page.keyboard.type('Segunda linha'); assert.match(await page.locator('#quick-note-body').inputValue(), /\n/); await page.waitForTimeout(100); assert.equal(posts, 0, 'Enter no texto quebra linha sem enviar');
        await page.route('**/notes', route => route.request().method() === 'POST' ? route.fulfill({status: 503, contentType: 'application/json', body: '{"message":"Falha simulada"}'}) : route.continue());
        await form.getByRole('button', {name: 'Salvar nota', exact: true}).click(); await page.locator('[data-note-dialog] [data-note-error]').filter({hasText: 'Falha simulada'}).waitFor(); assert.match(await page.locator('#quick-note-body').inputValue(), /Segunda linha/);
        await page.unroute('**/notes'); const saved = page.waitForResponse(r => r.request().method() === 'POST' && r.url() === base + '/notes'); await form.getByRole('button', {name: 'Salvar nota', exact: true}).click(); assert.equal((await saved).status(), 200); await page.locator('[data-note-title="Papel de verdade"]').waitFor();
        await page.locator('[data-note-title="Papel de verdade"] .qn-card-actions [data-note-edit]').click(); assert.equal(await form.locator('[name="color"]:checked').inputValue(), 'orange'); assert.ok((await form.getAttribute('class')).includes('priority-alta')); await page.getByRole('button', {name: 'Cancelar', exact: true}).click();
        await page.keyboard.press('Control+k'); assert.equal(await form.locator('[name="color"]:checked').inputValue(), 'yellow'); await page.getByRole('button', {name: 'Fechar nota rápida', exact: true}).click();
        await page.goto(base + '/notes/new'); const standalone = page.locator('#note-form'); await standalone.locator('[name="color"][value="green"]').check(); assert.ok((await standalone.getAttribute('class')).includes('priority-baixa')); await page.locator('#note-body').fill('Enter insere nova linha'); await page.locator('#note-body').press('Enter'); assert.equal(await standalone.evaluate(n => n.isConnected), true); await standalone.getByRole('button', {name: 'Salvar nota', exact: true}).click(); await page.waitForURL('**/tasks#brainstorm');
        const nojs = await browser.newContext({javaScriptEnabled: false}); const raw = await nojs.newPage(); await login(raw); await raw.goto(base + '/notes/new'); await raw.locator('#note-body').fill('Formulário nativo preservado'); await raw.getByRole('button', {name: 'Salvar nota', exact: true}).click(); await raw.waitForURL('**/tasks#brainstorm'); await raw.locator('.qn-body').filter({hasText: 'Formulário nativo preservado'}).waitFor(); await nojs.close();
        assert.deepEqual(errors, []); console.log('OK: papel sem bordas, três temas imediatos, amarelo padrão, 320–1440px/axe, falha/rascunho, Enter nativo/salvar por botão, edição/cancelar/fechar, página nativa e sem JS.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
