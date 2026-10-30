'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
(async () => {
    const handlers = {}; const shown = []; const opened = []; let current = {user_id: 7, enabled: true}; let closed = false;
    const self = {location: {origin: 'https://kanban.example'}, addEventListener: (name, handler) => { handlers[name] = handler; }, skipWaiting: () => Promise.resolve(), registration: {showNotification: async (title, options) => shown.push({title, options})}, clients: {claim: async () => {}, matchAll: async () => [], openWindow: async url => opened.push(url)}};
    vm.runInNewContext(fs.readFileSync('/app/public/service-worker.js', 'utf8'), {self, URL, fetch: async () => ({ok: !!current, json: async () => current})});
    async function dispatch(name, data) { let work; handlers[name]({...data, waitUntil: promise => { work = promise; }}); await work; }
    const payload = {title: 'Tarefa atribuída', body: 'Entrega · Projeto', user_id: 7, url: '/projects/1/board?task=2', tag: 'kanban-1'};
    await dispatch('push', {data: {json: () => payload}}); assert.equal(shown.length, 1); assert.equal(shown[0].options.data.url, 'https://kanban.example/projects/1/board?task=2');
    current = {user_id: 8, enabled: true}; await dispatch('push', {data: {json: () => payload}}); assert.equal(shown.length, 1, 'Outra conta não vê conteúdo');
    current = null; await dispatch('push', {data: {json: () => payload}}); assert.equal(shown.length, 1, 'Logout não exibe conteúdo');
    current = {user_id: 7, enabled: false}; await dispatch('push', {data: {json: () => payload}}); assert.equal(shown.length, 1, 'Canal desligado no dispositivo');
    current = {user_id: 7, enabled: true}; await dispatch('push', {data: {json: () => ({...payload, url: 'https://attacker.example'})}}); assert.equal(shown[1].options.data.url, 'https://kanban.example/notifications', 'URL externa descartada');
    await dispatch('notificationclick', {notification: {close: () => { closed = true; }, data: shown[0].options.data}}); assert.equal(closed, true); assert.equal(opened[0], 'https://kanban.example/projects/1/board?task=2');
    current = null; await dispatch('notificationclick', {notification: {close: () => {}, data: shown[0].options.data}}); assert.equal(opened[1], 'https://kanban.example/login');
    await dispatch('push', {data: {json: () => { throw new Error('invalid'); }}}); assert.equal(shown.length, 2);
    console.log('OK: service worker, sessão/conta/canal, URL interna, clique e payload inválido.');
})().catch(error => { console.error(error); process.exitCode = 1; });
