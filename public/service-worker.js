'use strict';
// Sem cache de páginas, respostas autenticadas ou dados de tarefas.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', event => event.waitUntil(self.clients.claim()));
function destination(value) {
    try {
        const url = new URL(value, self.location.origin);
        if (url.origin === self.location.origin && /^\/(projects\/|notifications(?:\?|$)|tasks(?:\?|$)|invitations\/accept(?:\?|$))/.test(url.pathname + url.search)) return url.href;
    } catch { /* Conteúdo inválido usa a central. */ }
    return self.location.origin + '/notifications';
}
async function account() {
    try {
        const response = await fetch('/notifications/push-context', {credentials: 'same-origin', cache: 'no-store'});
        return response.ok ? await response.json() : null;
    } catch { return null; }
}
self.addEventListener('push', event => {
    event.waitUntil((async () => {
        let data;
        try { data = event.data?.json(); } catch { return; }
        if (!data || typeof data.title !== 'string' || !Number.isSafeInteger(data.user_id)) return;
        const current = await account();
        // Evita expor avisos de outra conta em dispositivo compartilhado ou após logout.
        if (!current || current.user_id !== data.user_id || !current.enabled) return;
        await self.registration.showNotification(data.title.slice(0, 120), {body: String(data.body || '').slice(0, 180), icon: '/assets/brand/mark.svg', tag: String(data.tag || 'kanban').slice(0, 80), data: {url: destination(data.url), user_id: data.user_id}});
    })());
});
self.addEventListener('notificationclick', event => {
    event.notification.close();
    event.waitUntil((async () => {
        const current = await account();
        const url = current && current.user_id === event.notification.data?.user_id ? destination(event.notification.data.url) : self.location.origin + '/login';
        const windows = await self.clients.matchAll({type: 'window', includeUncontrolled: true});
        const client = windows.find(item => new URL(item.url).origin === self.location.origin);
        if (client) { await client.navigate(url); return client.focus(); }
        return self.clients.openWindow(url);
    })());
});
