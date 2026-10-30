'use strict';
const assert = require('node:assert/strict');
const runBoardBrowser = require('./board-browser.cjs');

async function runProfileBrowser(extra = null) {
return runBoardBrowser(async ({page, context, boardUrl}) => {
    const base = new URL(boardUrl).origin;
    const originalPassword = 'Senha-browser-segura-123';
    const newPassword = 'Nova-senha-browser-456';
    await page.setViewportSize({width: 1280, height: 1000});
    await page.getByRole('link', {name: 'Configurações', exact: true}).click();
    const email = await page.locator('section strong').innerText();
    const name = '<img src=x onerror=window.profileXss=true>';
    await page.locator('#name').fill(name);
    await page.getByRole('button', {name: 'Salvar perfil', exact: true}).click();
    await page.getByRole('status').filter({hasText: 'Nome atualizado.'}).waitFor();
    assert.equal(await page.locator('#name').inputValue(), name);
    assert.equal(await page.evaluate(() => window.profileXss), undefined);
    // A política deve bloquear script executável inline mesmo se inserido no DOM.
    await page.evaluate(() => {
        const script = document.createElement('script'); script.textContent = 'window.inlineExecuted=true'; document.body.append(script);
    });
    assert.equal(await page.evaluate(() => window.inlineExecuted), undefined, 'CSP bloqueia scripts inline');
    const observer = await context.browser().newContext();
    try {
        const otherPage = await observer.newPage();
        async function login(password) {
            await otherPage.goto(base + '/login');
            await otherPage.locator('#email').fill(email); await otherPage.locator('#password').fill(password);
            await otherPage.getByRole('button', {name: 'Entrar', exact: true}).click(); await otherPage.waitForURL('**/dashboard');
        }
        await login(originalPassword);
        await page.locator('#password-settings > summary').click();
        await page.locator('#current-password').fill('Senha-incorreta-123');
        await page.locator('#password').fill(newPassword); await page.locator('#confirmation').fill(newPassword);
        await page.getByRole('button', {name: 'Alterar senha', exact: true}).click();
        await page.getByRole('alert').filter({hasText: 'A senha atual não confere.'}).waitFor();
        assert.equal(await page.locator('#current-password').inputValue(), '', 'Senha não volta ao formulário');
        await page.locator('#current-password').fill(originalPassword);
        await page.locator('#password').fill(newPassword); await page.locator('#confirmation').fill(newPassword);
        await page.getByRole('button', {name: 'Alterar senha', exact: true}).click();
        await page.getByRole('status').filter({hasText: 'Senha alterada.'}).waitFor();
        await otherPage.goto(base + '/settings'); await otherPage.waitForURL('**/login');
        await login(newPassword);
        await page.locator('#session-settings > summary').click();
        await page.locator('#session-password').fill(newPassword);
        await page.getByRole('button', {name: 'Encerrar demais sessões', exact: true}).click();
        await page.getByRole('status').filter({hasText: 'As demais sessões foram encerradas.'}).waitFor();
        await otherPage.goto(base + '/settings'); await otherPage.waitForURL('**/login');
        await page.setViewportSize({width: 390, height: 844});
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true, 'Configurações responsivas');
        await page.getByRole('link', {name: 'Projetos', exact: true}).click();
        assert.equal(await page.locator('.pl-filters [aria-current]').innerText(), 'Ativos');
    } finally { await observer.close(); }
    if (extra) await extra({page, context, boardUrl, email, password: newPassword});
    console.log('OK: perfil no Chromium, CSP, escape, troca de senha, revogação de sessões e mobile.');
});
}
module.exports = runProfileBrowser;
if (require.main === module) runProfileBrowser().catch(error => { console.error(error); process.exitCode = 1; });
