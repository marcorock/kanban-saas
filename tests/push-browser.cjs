'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const {chromium} = require('playwright-core');
const {default: AxeBuilder} = require('@axe-core/playwright');
(async () => {
    if (process.env.AUTH_TEST_DATABASE !== 'disposable') throw new Error('Use banco descartável.');
    const base = process.env.AUTH_TEST_URL || 'http://127.0.0.1:8000';
    const fixture = JSON.parse(await fs.readFile('/app/storage/previews/notifications-fixture.json', 'utf8'));
    const browser = await chromium.launch({executablePath: '/usr/bin/chromium-browser', args: ['--no-sandbox']});
    const context = await browser.newContext(); const page = await context.newPage(); const errors = []; page.on('pageerror', error => errors.push(error.message));
    try {
        await page.goto(base + '/login'); await page.locator('#email').fill(fixture.users.member.email); await page.locator('#password').fill('Senha-notificacao-ui-123'); await page.getByRole('button', {name: 'Entrar', exact: true}).click(); await page.waitForURL('**/dashboard');
        // Navegador real/CSP/UI; provedor de push simulado para não inscrever dispositivo de teste em serviço externo.
        const publicKey = (await (await context.request.get(base + '/notifications/push-config')).json()).public_key;
        await page.addInitScript(key => {
            window.pushCalls = {permission: 0, register: 0, subscribe: 0};
            const bytes = Uint8Array.from(atob(key.replace(/-/g, '+').replace(/_/g, '/')), item => item.charCodeAt(0));
            let active = null;
            const manager = {getSubscription: async () => active, subscribe: async () => { window.pushCalls.subscribe++; active = {endpoint: 'https://fcm.googleapis.com/fcm/send/ui-device', options: {applicationServerKey: bytes}, unsubscribe: async () => { active = null; return true; }, toJSON: () => ({endpoint: 'https://fcm.googleapis.com/fcm/send/ui-device', keys: {p256dh: key, auth: btoa('1234567890123456').replace(/=/g, '')}})}; return active; }};
            const registration = {pushManager: manager};
            Object.defineProperty(Notification, 'requestPermission', {value: async () => { window.pushCalls.permission++; return 'granted'; }});
            Object.defineProperty(navigator, 'serviceWorker', {value: {getRegistration: async () => active ? registration : undefined, register: async () => { window.pushCalls.register++; return registration; }, ready: Promise.resolve(registration)}});
        }, publicKey);
        for (const width of [320, 390, 768, 1440]) {
            await page.setViewportSize({width, height: 900}); await page.goto(base + '/settings'); await page.locator('#notification-settings > summary').click(); await page.getByRole('button', {name: 'Ativar neste dispositivo', exact: true}).waitFor();
            await page.waitForFunction(() => !document.querySelector('[data-push-enable]').disabled);
            assert.deepEqual(await page.evaluate(() => window.pushCalls), {permission: 0, register: 0, subscribe: 0}, 'Nenhuma permissão ou inscrição no carregamento');
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
            const results = await new AxeBuilder({page}).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze(); assert.deepEqual(results.violations.map(item => item.id), []);
        }
        await page.getByRole('button', {name: 'Ativar neste dispositivo', exact: true}).click(); await page.locator('[data-push-status]').filter({hasText: 'Push ativado neste dispositivo e na sua conta.'}).waitFor();
        assert.deepEqual(await page.evaluate(() => window.pushCalls), {permission: 1, register: 1, subscribe: 1}); assert.equal(await page.locator('[name="push_enabled"]').isChecked(), true);
        const current = await (await context.request.get(base + '/notifications/push-context')).json(); assert.equal(current.enabled, true);
        await page.getByRole('button', {name: 'Desativar neste dispositivo', exact: true}).click(); await page.locator('[data-push-status]').filter({hasText: 'Push desativado neste dispositivo.'}).waitFor();
        const worker = await context.request.get(base + '/service-worker.js'); assert.equal(worker.status(), 200); assert.ok((await worker.text()).includes("self.addEventListener('push'"));
        const manifest = await context.request.get(base + '/manifest.webmanifest'); assert.equal((await manifest.json()).display, 'standalone');
        assert.ok((await context.request.get(base + '/settings')).headers()['content-security-policy'].includes("worker-src 'self'"));
        assert.deepEqual(errors, []); console.log('OK: Push por clique, persistência real, remoção do dispositivo, sem permissão automática, CSP, manifest e UI/axe 320–1440px.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
