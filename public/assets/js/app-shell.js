'use strict';

// O disclosure nativo permanece disponível quando dialog/JavaScript não existir.
const dialog = document.getElementById('app-more-dialog');
const opener = document.querySelector('[data-more-open]');
const fallback = document.querySelector('.app-more-fallback');
if (dialog && opener && fallback && typeof dialog.showModal === 'function') {
    fallback.hidden = true;
    opener.hidden = false;
    const desktop = window.matchMedia('(min-width: 1024px)');
    opener.addEventListener('click', () => {
        if (desktop.matches || dialog.open) return;
        dialog.showModal();
        document.body.classList.add('app-modal-open');
        opener.setAttribute('aria-expanded', 'true');
        dialog.querySelector('[data-more-close]').focus();
    });
    dialog.querySelector('[data-more-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('keydown', event => {
        if (event.key !== 'Tab') return;
        const controls = [...dialog.querySelectorAll('a[href], button:not([disabled])')].filter(element => element.getClientRects().length);
        const first = controls[0]; const last = controls.at(-1);
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    dialog.addEventListener('close', () => {
        document.body.classList.remove('app-modal-open');
        opener.setAttribute('aria-expanded', 'false');
        const target = opener.getClientRects().length ? opener : (document.querySelector('.app-nav [aria-current]') || document.querySelector('.app-brand'));
        target?.focus();
    });
    dialog.addEventListener('click', event => {
        if (event.target !== dialog) return;
        const rect = dialog.getBoundingClientRect();
        if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
    });
    for (const link of dialog.querySelectorAll('a')) link.addEventListener('click', () => dialog.close());
    desktop.addEventListener('change', () => { if (desktop.matches && dialog.open) dialog.close(); });
}
