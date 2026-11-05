'use strict';
(() => {
    const root = document.querySelector('[data-dashboard-version]');
    if (!root || !window.FluxoSync) return;
    let version = root.dataset.dashboardVersion;
    function endpoint(mode) {
        const url = new URL(window.location.href);
        url.searchParams.set('sync', mode);
        return url.pathname + url.search;
    }
    FluxoSync.create({
        async run(signal) {
            const meta = await FluxoSync.json(endpoint('version'), signal);
            if (meta.version === version) return;
            const state = await FluxoSync.json(endpoint('state'), signal);
            const fragment = new DOMParser().parseFromString(state.html, 'text/html');
            const changes = [];
            for (const current of root.querySelectorAll('[data-dashboard-part]')) {
                const next = fragment.querySelector(`[data-dashboard-part="${current.dataset.dashboardPart}"]`);
                if (!next) throw new Error('Dashboard incompleto.');
                if (current.innerHTML !== next.innerHTML) changes.push([current, next]);
            }
            // Adia a aplicação para não remover o link que o usuário está usando.
            if (changes.some(([current]) => current.contains(document.activeElement))) return;
            for (const [current, next] of changes) current.replaceChildren(...next.childNodes);
            version = state.version;
            root.dataset.dashboardVersion = version;
        }
    });
})();
