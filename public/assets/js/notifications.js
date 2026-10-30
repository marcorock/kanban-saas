'use strict';
(() => {
    const bell = document.querySelector('[data-notification-bell]');
    if (!bell) return;
    const badge = bell.querySelector('[data-notification-count]');
    let running = false;
    async function refresh() {
        if (running || document.hidden) return;
        running = true;
        try {
            const response = await fetch('/notifications?format=count', {credentials: 'same-origin', headers: {Accept: 'application/json'}});
            if (!response.ok) throw new Error('unavailable');
            const data = await response.json();
            const count = Number.isSafeInteger(data.unread) && data.unread > 0 ? data.unread : 0;
            badge.textContent = count > 99 ? '99+' : String(count); badge.hidden = count === 0;
            bell.setAttribute('aria-label', count ? `Notificações: ${count} não lidas` : 'Notificações');
        } catch { badge.hidden = true; bell.setAttribute('aria-label', 'Notificações'); }
        finally { running = false; }
    }
    refresh(); setInterval(refresh, 60000);
    document.addEventListener('visibilitychange', refresh);
})();
