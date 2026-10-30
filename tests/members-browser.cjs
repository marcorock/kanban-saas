'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const {chromium} = require('playwright-core');
const {default: AxeBuilder} = require('@axe-core/playwright');

async function run() {
    if (process.env.AUTH_TEST_DATABASE !== 'disposable') throw new Error('Use somente banco/ambiente descartáveis.');
    const base = process.env.AUTH_TEST_URL || 'http://127.0.0.1:8000';
    const mailpit = process.env.MAILPIT_TEST_URL || 'http://127.0.0.1:8025';
    const preview = process.env.UI_PREVIEW_DIR || '/app/storage/previews';
    const f = JSON.parse(await fs.readFile(preview + '/members-fixture.json', 'utf8'));
    const browser = await chromium.launch({executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium-browser', args: ['--no-sandbox']});
    const context = await browser.newContext(); const page = await context.newPage(); const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const url = base + '/projects/' + f.projects.active + '/members';
    async function login(target, key) {
        await target.goto(base + '/login'); await target.locator('#email').fill(f.users[key].email); await target.locator('#password').fill('Senha-equipe-ui-segura-123');
        await target.getByRole('button', {name: 'Entrar', exact: true}).click();
        await target.waitForURL(/\/(dashboard|invitations\/accept)$/);
    }
    async function audit(target, label) {
        const result = await new AxeBuilder({page: target}).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
        assert.deepEqual(result.violations.map(item => ({id: item.id, nodes: item.nodes.map(node => node.target)})), [], label);
    }
    async function send(button, status = 303) {
        const response = page.waitForResponse(response => response.request().method() === 'POST');
        await button.click(); assert.equal((await response).status(), status); await page.waitForLoadState('load');
    }
    async function inviteLink(email) {
        const messages = await (await page.request.get(mailpit + '/api/v1/messages?limit=100')).json();
        const message = messages.messages.find(item => item.To[0]?.Address === email); assert.ok(message);
        const detail = await (await page.request.get(mailpit + '/api/v1/message/' + message.ID)).json();
        return detail.Text.match(/https?:\/\/[^\s]+\/invitations\/accept\?token=[a-f0-9]{64}/)[0];
    }
    try {
        await login(page, 'owner');
        for (const width of [320, 390, 768, 1024, 1440]) {
            await page.setViewportSize({width, height: width < 640 ? 844 : 1100});
            for (const archived of [false, true]) {
                await page.goto(base + '/projects/' + f.projects[archived ? 'archived' : 'active'] + '/members');
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                assert.equal(await page.locator('.project-members [style], .project-members style, .project-members script').count(), 0);
                assert.equal(await page.locator('.pm-member').count(), 4);
                assert.equal(await page.locator('#email').count(), archived ? 0 : 1);
                if (width === 390 || width === 1440) { await audit(page, 'Equipe ' + width + archived); await page.screenshot({path: `${preview}/members-${archived ? 'archived-' : ''}${width}.png`, fullPage: false}); }
            }
        }
        await page.setViewportSize({width: 390, height: 844});
        for (const key of ['admin', 'member', 'viewer']) {
            const roleContext = await browser.newContext({viewport: {width: 390, height: 844}});
            try {
                const target = await roleContext.newPage(); await login(target, key); await target.goto(url);
                assert.equal(await target.locator('#email').count(), key === 'admin' ? 1 : 0);
                if (key === 'admin') {
                    assert.equal(await target.locator('#invite-role option[value="admin"]').count(), 0);
                    assert.equal(await target.locator('.pm-invitation').getByRole('button').count(), 0, 'Admin não gerencia convite admin');
                } else {
                    const html = await target.content();
                    assert.equal(html.includes(f.users.owner.email) || html.includes(f.pending), false, 'Privacidade dos e-mails/convites');
                    assert.equal(await target.locator('.pm-pending, .pm-access').count(), 0);
                }
                await audit(target, key);
            } finally { await roleContext.close(); }
        }
        await page.goto(url); const invite = page.locator('.pm-invite');
        await invite.locator('#email').fill(f.users.recipient.email);
        await invite.locator('input[name="_token"]').evaluate(input => { input.value = 'invalid'; });
        await send(invite.getByRole('button', {name: 'Enviar convite'}), 419);
        assert.equal(await page.locator('#email').inputValue(), f.users.recipient.email);
        assert.equal(await page.locator('[data-members-errors]').evaluate(element => element === document.activeElement), true);
        await send(page.locator('.pm-invite').getByRole('button', {name: 'Enviar convite'}));
        const first = await inviteLink(f.users.recipient.email);
        let pending = page.locator('.pm-invitation').filter({hasText: f.users.recipient.email});
        await send(pending.getByRole('button', {name: 'Reenviar convite'})); const resent = await inviteLink(f.users.recipient.email); assert.notEqual(first, resent);
        await send(page.locator('.pm-invitation').filter({hasText: f.users.recipient.email}).getByRole('button', {name: 'Cancelar convite'}));
        assert.equal(await page.locator('.pm-invitation').filter({hasText: f.users.recipient.email}).count(), 0);
        await page.locator('#email').fill(f.users.recipient.email); await send(page.locator('.pm-invite').getByRole('button', {name: 'Enviar convite'}));
        const link = await inviteLink(f.users.recipient.email);
        const wrongContext = await browser.newContext({viewport: {width: 390, height: 844}});
        try {
            const target = await wrongContext.newPage(); await target.goto(link); assert.equal(new URL(target.url()).search, '');
            await audit(target, 'Convite visitante'); await target.screenshot({path: `${preview}/invitation-guest-390.png`, fullPage: false});
            await target.locator('.project-accept').getByRole('link', {name: 'Entrar', exact: true}).click(); await login(target, 'wrong');
            assert.equal(await target.getByRole('button', {name: 'Aceitar convite'}).count(), 0);
            assert.equal((await target.content()).includes('Lançamento do site'), false);
            await audit(target, 'Conta incompatível');
        } finally { await wrongContext.close(); }
        const recipientContext = await browser.newContext({javaScriptEnabled: false, viewport: {width: 390, height: 844}});
        try {
            const target = await recipientContext.newPage(); await target.goto(link); await login(target, 'recipient');
            assert.match(await target.locator('.project-accept').innerText(), /Lançamento do site/);
            await target.screenshot({path: `${preview}/invitation-accept-390.png`, fullPage: false});
            await target.getByRole('button', {name: 'Aceitar convite', exact: true}).click(); await target.waitForURL(base + '/projects/' + f.projects.active);
            await target.goto(link); assert.equal(await target.getByRole('button', {name: 'Aceitar convite'}).count(), 0, 'Uso único');
        } finally { await recipientContext.close(); }
        await page.goto(url); let row = page.locator(`[data-member-id="${f.users.recipient.id}"]`);
        assert.match(await row.locator('h4').innerText(), /<script>/); assert.equal(await page.evaluate(() => window.memberXss), undefined);
        await row.locator('.pm-access > summary').click(); await row.locator('select').selectOption('viewer');
        await send(row.getByRole('button', {name: 'Salvar', exact: true})); await page.getByRole('status').filter({hasText: 'Papel atualizado.'}).waitFor();
        row = page.locator(`[data-member-id="${f.users.recipient.id}"]`);
        await row.locator('.pm-access > summary').click(); await row.locator('.pm-sensitive > summary').filter({hasText: 'Remover acesso'}).click();
        await row.getByRole('button', {name: 'Remover participante'}).click(); assert.equal(await row.locator('input[value="remove"]').evaluate(input => input.matches(':invalid')), true);
        await audit(page, 'Gestão expandida');
        await row.locator('input[value="remove"]').check(); await send(row.getByRole('button', {name: 'Remover participante'}));
        assert.equal(await page.locator(`[data-member-id="${f.users.recipient.id}"]`).count(), 0);
        const adminRow = page.locator(`[data-member-id="${f.users.admin.id}"]`);
        await adminRow.locator('.pm-access > summary').click(); await adminRow.locator('.pm-sensitive > summary').filter({hasText: 'Transferência'}).click();
        await adminRow.locator('input[value="transfer"]').check(); await send(adminRow.getByRole('button', {name: 'Transferir propriedade'}));
        assert.equal(await page.locator('#invite-role option[value="admin"]').count(), 0, 'Antigo owner passa a admin');
        assert.equal(await page.getByRole('button', {name: 'Transferir propriedade'}).count(), 0);
        assert.deepEqual(errors, [], 'Sem erros JS');
        console.log('OK: equipe 320–1440px, axe A/AA, privacidade/papéis, convites SMTP/reenvio/cancelamento, aceite sem JS/uso único, CSRF, remoção, transferência e XSS.');
    } finally { await browser.close(); }
}
run().catch(error => { console.error(error); process.exitCode = 1; });
