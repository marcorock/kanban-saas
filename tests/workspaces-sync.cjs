'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const listeners = () => ({addEventListener() {}, removeEventListener() {}});
const document = {...listeners(), body: {dataset: {workspacePath: '/workspaces/1'}}, hidden: false};
const window = listeners();
let release;
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../public/assets/js/live-sync.js'), 'utf8'), {
    document, window, navigator: {onLine: true}, DOMException, AbortController, setTimeout, clearTimeout,
    fetch: async () => ({ok: true, json: () => new Promise(resolve => { release = resolve; })})
});
(async () => {
    // Simula resposta antiga chegando depois que o documento mudou de Workspace.
    const old = window.FluxoSync.json('/workspaces/1/dashboard?sync=state');
    await new Promise(resolve => setImmediate(resolve));
    document.body.dataset.workspacePath = '/workspaces/2';
    release({html: '<div>dados do primeiro Workspace</div>'});
    await assert.rejects(old, error => error.name === 'AbortError');
    assert.equal(window.FluxoSync.current(), false);
    assert.throws(() => window.FluxoSync.assertCurrent(), error => error.name === 'AbortError');
    document.body.dataset.workspacePath = '/workspaces/1';
    const controller = new AbortController();
    const aborted = window.FluxoSync.json('/workspaces/1/notes/state', controller.signal);
    await new Promise(resolve => setImmediate(resolve));
    controller.abort(); release({html: 'descartar'});
    await assert.rejects(aborted, error => error.name === 'AbortError');
    const valid = window.FluxoSync.json('/workspaces/1/projects/1/board/state');
    await new Promise(resolve => setImmediate(resolve)); release({board: {version: 3}});
    assert.equal((await valid).board.version, 3);
    console.log('OK: respostas atrasadas de outro Workspace e requisições abortadas descartadas; contexto atual aceito.');
})().catch(error => { console.error(error); process.exitCode = 1; });
