'use strict';
(() => {
    async function json(url, signal) {
        const response = await fetch(url, {credentials: 'same-origin', cache: 'no-store', headers: {Accept: 'application/json'}, signal});
        if (!response.ok) { const error = new Error('Não foi possível sincronizar.'); error.status = response.status; throw error; }
        return response.json();
    }
    function create({run, busy = () => false, onError = () => {}, onRecover = () => {}, interval = 7000}) {
        interval = Math.max(5000, Math.min(10000, Number(interval) || 7000));
        let timer; let controller; let running = false; let stopped = false; let pending = false; let failures = 0; let suspended = false;
        const active = () => !suspended && !document.hidden && navigator.onLine !== false;
        function schedule(delay = interval) { clearTimeout(timer); if (!stopped && active()) timer = setTimeout(tick, delay); }
        async function tick() {
            if (stopped || !active()) return;
            if (running) { pending = true; return; }
            if (busy()) { schedule(); return; }
            running = true; controller = new AbortController(); let timedOut = false;
            const timeout = setTimeout(() => { timedOut = true; controller.abort(); }, 15000);
            try { await run(controller.signal); if (failures) onRecover(); failures = 0; }
            catch (error) {
                if (error.name !== 'AbortError' || timedOut) {
                    failures++; if ([401, 403, 404].includes(error.status)) stopped = true;
                    onError(error);
                }
            } finally {
                clearTimeout(timeout); running = false; controller = null;
                const immediate = pending; pending = false;
                schedule(immediate ? 0 : Math.min(60000, interval * (2 ** Math.min(failures, 4))));
            }
        }
        function requestNow() { clearTimeout(timer); if (running) pending = true; else if (!stopped && active()) schedule(0); }
        function visibility() { if (!active()) { clearTimeout(timer); controller?.abort(); } else requestNow(); }
        function offline() { visibility(); if (!stopped) { failures = Math.max(1, failures); onError(new Error('Conexão indisponível.')); } }
        document.addEventListener('visibilitychange', visibility); window.addEventListener('online', requestNow); window.addEventListener('offline', offline);
        function destroy() { stopped = true; clearTimeout(timer); controller?.abort(); document.removeEventListener('visibilitychange', visibility); window.removeEventListener('online', requestNow); window.removeEventListener('offline', offline); }
        window.addEventListener('pagehide', event => { suspended = true; if (event.persisted) { clearTimeout(timer); controller?.abort(); } else destroy(); });
        window.addEventListener('pageshow', event => { if (event.persisted) { suspended = false; requestNow(); } });
        schedule(); return {requestNow, destroy};
    }
    window.FluxoSync = {create, json};
})();
