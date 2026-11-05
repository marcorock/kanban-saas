'use strict';
(() => {
    const bell = document.querySelector('[data-notification-bell]');
    if (!bell || !window.FluxoSync) return;
    const badge = bell.querySelector('[data-notification-count]');
    let unread = null;
    function label(stale = false) {
        const text = unread ? `Notificações: ${unread} não lidas` : 'Notificações';
        const value = stale ? text + ' · atualização temporariamente indisponível' : text;
        if (bell.getAttribute('aria-label') !== value) bell.setAttribute('aria-label', value);
    }
    const sync = FluxoSync.create({
        async run(signal) {
            const data = await FluxoSync.json((document.body?.dataset.workspacePath || '') + '/notifications?format=count', signal);
            if (!Number.isSafeInteger(data.unread) || data.unread < 0) throw new Error('Contagem inválida.');
            if (data.unread !== unread) {
                unread = data.unread; badge.textContent = unread > 99 ? '99+' : String(unread); badge.hidden = unread === 0;
            }
            if (bell.dataset.notificationSync !== 'ready') bell.dataset.notificationSync = 'ready'; label();
        },
        onError(error) {
            if ([401, 403, 404].includes(error.status)) {
                unread = null; badge.textContent = ''; badge.hidden = true; bell.dataset.notificationSync = 'denied';
                bell.setAttribute('aria-label', 'Notificações: entre novamente para atualizar');
            } else { bell.dataset.notificationSync = 'offline'; label(true); }
        },
        onRecover() { if (bell.dataset.notificationSync !== 'ready') bell.dataset.notificationSync = 'ready'; label(); }
    });
    sync.requestNow();
})();
