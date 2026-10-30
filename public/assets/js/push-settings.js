'use strict';
(() => {
    const root = document.querySelector('[data-push-settings]');
    if (!root) return;
    const enable = root.querySelector('[data-push-enable]');
    const remove = root.querySelector('[data-push-remove]');
    const status = root.querySelector('[data-push-status]');
    let publicKey = null;
    let busy = false;
    const supported = window.isSecureContext && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    function message(text) { status.textContent = text; }
    async function existing() { return (await navigator.serviceWorker.getRegistration('/'))?.pushManager.getSubscription(); }
    async function post(path, data) {
        const response = await fetch(path, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', Accept: 'application/json'}, body: JSON.stringify({...data, _token: root.dataset.csrf})});
        const result = await response.json();
        if (!response.ok) { const error = new Error(result.message || 'Não foi possível salvar. Tente novamente.'); error.status = response.status; throw error; }
    }
    function keyBytes(value) { const raw = atob(value.replace(/-/g, '+').replace(/_/g, '/')); return Uint8Array.from(raw, character => character.charCodeAt(0)); }
    async function activate() {
        if (busy || !publicKey) return;
        busy = true; enable.disabled = true; remove.disabled = true;
        try {
            // A chamada acontece diretamente no clique, antes de registro/fetch assíncronos.
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') { message('Permissão não concedida. Você pode revisá-la nas configurações do navegador.'); return; }
            const registration = await navigator.serviceWorker.register('/service-worker.js', {scope: '/'});
            await navigator.serviceWorker.ready;
            let subscription = await registration.pushManager.getSubscription();
            if (subscription && subscription.options.applicationServerKey && new Uint8Array(subscription.options.applicationServerKey).join(',') !== keyBytes(publicKey).join(',')) { await subscription.unsubscribe(); subscription = null; }
            subscription ||= await registration.pushManager.subscribe({userVisibleOnly: true, applicationServerKey: keyBytes(publicKey)});
            try { await post('/settings/notifications/push', subscription.toJSON()); }
            catch (error) {
                if (error.status !== 409) throw error;
                await subscription.unsubscribe();
                subscription = await registration.pushManager.subscribe({userVisibleOnly: true, applicationServerKey: keyBytes(publicKey)});
                await post('/settings/notifications/push', subscription.toJSON());
            }
            document.querySelector('[name="push_enabled"]').checked = true;
            remove.disabled = false;
            message('Push ativado neste dispositivo e na sua conta. Os outros canais mantêm suas preferências.');
        } catch (error) { message(error.message || 'Não foi possível ativar. Tente novamente.'); }
        finally { busy = false; enable.disabled = false; }
    }
    async function deactivate() {
        if (busy) return;
        busy = true; enable.disabled = true; remove.disabled = true;
        try {
            const subscription = await existing();
            if (subscription) { await post('/settings/notifications/push/remove', {endpoint: subscription.endpoint}); await subscription.unsubscribe(); }
            message('Push desativado neste dispositivo. Os outros dispositivos mantêm sua configuração.');
        } catch (error) { message(error.message || 'Não foi possível desativar. Tente novamente.'); remove.disabled = false; }
        finally { busy = false; enable.disabled = false; }
    }
    enable.addEventListener('click', activate); remove.addEventListener('click', deactivate);
    if (!supported) { message('Web Push requer um navegador compatível e HTTPS (ou localhost). No iPhone, adicione à Tela de Início.'); return; }
    (async () => {
        try {
            const response = await fetch('/notifications/push-config', {credentials: 'same-origin', headers: {Accept: 'application/json'}});
            if (!response.ok) throw new Error();
            publicKey = (await response.json()).public_key;
            if (!publicKey) { message('Web Push ainda não está configurado neste servidor. O sino e o e-mail continuam disponíveis.'); return; }
            enable.disabled = false; remove.disabled = !(await existing());
            message(remove.disabled ? 'Pronto para ativar. A permissão só será solicitada pelo botão.' : 'Este navegador possui uma assinatura. Ative para confirmar a conta atual ou desative neste dispositivo.');
        } catch { message('Não foi possível consultar o Push. Recarregue a página para tentar novamente.'); }
    })();
})();
