'use strict';
const assert = require('node:assert/strict');
const {chromium} = require('playwright-core');
const {default: AxeBuilder} = require('@axe-core/playwright');
const fs = require('node:fs/promises');

async function run() {
    if (process.env.AUTH_TEST_DATABASE !== 'disposable') throw new Error('Use somente banco e ambiente descartáveis.');
    const base = process.env.AUTH_TEST_URL || 'http://127.0.0.1:8000';
    const mailpit = process.env.MAILPIT_TEST_URL || 'http://127.0.0.1:8025';
    const preview = process.env.UI_PREVIEW_DIR || '/app/storage/previews';
    await fs.mkdir(preview, {recursive: true});
    const browser = await chromium.launch({executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium-browser', args: ['--no-sandbox']});
    const context = await browser.newContext();
    const page = await context.newPage();
    const errors = []; const cspErrors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => { if (/Content Security Policy|violates.*directive/i.test(message.text())) cspErrors.push(message.text()); });
    async function audit(label) {
        const results = await new AxeBuilder({page}).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
        assert.deepEqual(results.violations.map(item => ({id: item.id, nodes: item.nodes.map(node => node.target)})), [], label);
    }
    async function submitted(button, status) {
        const [response] = await Promise.all([page.waitForResponse(response => response.request().method() === 'POST'), button.click()]);
        assert.equal(response.status(), status);
        await page.waitForLoadState('load');
    }
    async function mailLink(email, path) {
        const messages = await (await page.request.get(mailpit + '/api/v1/messages?limit=100')).json();
        for (const message of messages.messages.filter(item => item.To[0]?.Address === email)) {
            const detail = await (await page.request.get(mailpit + '/api/v1/message/' + message.ID)).json();
            const link = detail.Text.match(new RegExp('https?://[^\\s]+' + path + '\\?token=[a-f0-9]{64}'))?.[0];
            if (link) return link;
        }
        throw new Error('E-mail de teste não encontrado');
    }
    try {
        const paths = ['/login', '/register', '/password/forgot', '/password/reset?token=' + 'a'.repeat(64), '/email/resend', '/email/verify?token=' + 'a'.repeat(64)];
        for (const width of [320, 390, 768, 1024, 1440]) {
            await page.setViewportSize({width, height: width < 640 ? 844 : 1000});
            for (const path of paths) {
                const response = await page.goto(base + path);
                assert.equal(response.status(), 200, path);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true, 'Sem overflow: ' + width + path);
                assert.equal(await page.locator('h1').count(), 1, 'Um título principal');
                assert.equal(await page.locator('[style], style, script:not([src])').count(), 0, 'HTML sem CSS/JS inline');
                assert.equal(await page.locator('link[href*="bootstrap"]').count(), 0, 'Layout de acesso independente');
                assert.equal(await page.locator('input:not([type="hidden"])').evaluateAll(inputs => inputs.every(input => parseFloat(getComputedStyle(input).fontSize) >= 16 && input.getBoundingClientRect().height >= 44)), true, 'Campos legíveis e alvos confortáveis');
                if (width === 390 || width === 1440) await audit(width + path);
                if (path === '/login' && (width === 390 || width === 1440)) await page.screenshot({path: `${preview}/login-${width}.png`, fullPage: true});
                if (width === 390 && (path === '/register' || path === '/password/forgot' || path.startsWith('/password/reset'))) {
                    const name = path === '/register' ? 'cadastro' : (path === '/password/forgot' ? 'recuperacao' : 'nova-senha');
                    await page.screenshot({path: `${preview}/${name}-390.png`, fullPage: true});
                }
            }
        }
        await page.setViewportSize({width: 390, height: 844});
        await page.goto(base + '/login'); await page.keyboard.press('Tab');
        assert.equal(await page.locator('.ds-skip-link').evaluate(element => element === document.activeElement), true, 'Pular para conteúdo por teclado');
        await page.keyboard.press('Enter');
        assert.match(page.url(), /#auth-title$/);
        await page.locator('#password').fill('Teste-de-visibilidade-123');
        await page.getByRole('button', {name: 'Mostrar senha', exact: true}).click();
        assert.equal(await page.locator('#password').getAttribute('type'), 'text');
        assert.equal(await page.getByRole('button', {name: 'Ocultar senha', exact: true}).getAttribute('aria-pressed'), 'true');
        await page.getByRole('button', {name: 'Ocultar senha', exact: true}).click();
        assert.equal(await page.locator('#password').getAttribute('type'), 'password');
        await page.locator('#email').fill('expired@example.com');
        await page.locator('input[name="_token"]').evaluate(input => { input.value = 'invalid'; });
        await submitted(page.getByRole('button', {name: 'Entrar', exact: true}), 419);
        await page.getByRole('alert').filter({hasText: 'Sua sessão expirou.'}).waitFor();
        const email = `ui-${Date.now()}@example.com`; const password = 'Senha-ui-segura-123'; const newPassword = 'Nova-senha-ui-segura-456';
        await page.goto(base + '/register'); await page.locator('#name').fill('Marina Teste');
        await page.locator('#email').fill(email); await page.locator('#password').fill(password); await page.locator('#confirmation').fill(password);
        await submitted(page.getByRole('button', {name: 'Criar conta', exact: true}), 303);
        await page.waitForURL('**/dashboard');
        await page.getByRole('heading', {name: 'Seu espaço está quase pronto.'}).waitFor(); await audit('Aviso de confirmação');
        assert.equal((await page.request.post(base + '/login', {form: {_token: 'invalid'}})).status(), 419, 'Preserva CSRF em sessão autenticada');
        await page.goto(await mailLink(email, '/email/verify'));
        await submitted(page.getByRole('button', {name: 'Confirmar e-mail', exact: true}), 200);
        await page.getByRole('status').waitFor(); await audit('E-mail confirmado');
        await page.getByRole('link', {name: 'Ir para meu painel'}).click();
        await page.getByRole('button', {name: 'Mais', exact: true}).click();
        await page.getByRole('dialog', {name: 'Mais opções'}).getByRole('button', {name: 'Sair', exact: true}).click();
        await page.waitForURL('**/login');
        await page.locator('#email').fill(email); await page.locator('#password').fill('Senha-incorreta-123');
        await submitted(page.getByRole('button', {name: 'Entrar', exact: true}), 401);
        await page.getByRole('alert').waitFor();
        assert.equal(await page.locator('#email').inputValue(), email, 'Preserva e-mail');
        assert.equal(await page.locator('#password').inputValue(), '', 'Não repete senha');
        assert.equal(await page.locator('#auth-errors').evaluate(element => element === document.activeElement), true, 'Foco nos erros do servidor');
        await audit('Erro de login');
        await page.getByRole('link', {name: 'Esqueci minha senha'}).click(); await page.locator('#email').fill(email);
        await submitted(page.getByRole('button', {name: 'Enviar link', exact: true}), 200);
        await page.getByRole('status').waitFor(); await audit('Recuperação solicitada');
        await page.goto(await mailLink(email, '/password/reset'));
        await page.locator('#password').fill(newPassword); await page.locator('#confirmation').fill(newPassword);
        await submitted(page.getByRole('button', {name: 'Salvar nova senha', exact: true}), 200);
        await page.getByRole('status').waitFor(); await audit('Senha redefinida');
        const noJs = await browser.newContext({javaScriptEnabled: false, viewport: {width: 390, height: 844}});
        try {
            const fallback = await noJs.newPage(); await fallback.goto(base + '/login');
            assert.equal(await fallback.locator('[data-password-toggle]').isVisible(), false);
            await fallback.locator('#email').fill(email); await fallback.locator('#password').fill(newPassword);
            await fallback.getByRole('button', {name: 'Entrar', exact: true}).click(); await fallback.waitForURL('**/dashboard');
            assert.equal((await fallback.reload()).status(), 200, 'Login sem JavaScript');
        } finally { await noJs.close(); }
        await page.goto(base + '/login');
        await page.emulateMedia({reducedMotion: 'reduce'});
        assert.equal(await page.locator('.ds-button-primary').evaluate(element => getComputedStyle(element).transitionDuration), '0s', 'Movimento reduzido');
        assert.deepEqual(errors, [], 'Sem erros JS'); assert.deepEqual(cspErrors, [], 'Sem violações CSP');
        console.log('OK: UI de acesso em 320/390/768/1024/1440px, axe WCAG A/AA, teclado, mostrar senha, cadastro/confirmação, recuperação/reset, erro com foco, sem JS, CSP e previews.');
    } finally { await browser.close(); }
}
run().catch(error => { console.error(error); process.exitCode = 1; });
