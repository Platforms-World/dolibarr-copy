/**
 * TakePOS Custom UI Script
 *
 * FIX (I03): Removed duplicate clock, toast, and fullscreen button implementations.
 * These are all handled by takepos_ui.js which runs first and exposes:
 *   window.tpShowToast(msg, type)   — unified toast system
 *   window.takePOSUI.toast(msg, type) — same, namespaced
 *
 * This file keeps only the components that are unique to the custom HTML layout:
 * shortcuts drawer, keypad toggle, view toggle (grid/list).
 */
(function () {
    'use strict';

    /* ── Shortcuts drawer ─────────────────────────────────────────────── */
    function initShortcutsDrawer() {
        var launcher = document.getElementById('shortcuts-launcher');
        var drawer   = document.getElementById('shortcuts-drawer');
        var overlay  = document.getElementById('shortcuts-overlay');
        var closeBtn = document.getElementById('shortcuts-close');
        var searchIn = document.getElementById('shortcuts-search-input');
        if (!launcher || !drawer) return;

        function open()  { drawer.classList.add('open');    if (overlay) overlay.classList.add('open'); }
        function close() { drawer.classList.remove('open'); if (overlay) overlay.classList.remove('open'); }

        launcher.addEventListener('click', open);
        if (closeBtn) closeBtn.addEventListener('click', close);
        if (overlay)  overlay.addEventListener('click', close);
        if (searchIn) {
            searchIn.addEventListener('input', function () {
                var q = searchIn.value.trim().toLowerCase();
                document.querySelectorAll('.shortcut-section').forEach(function (sec) {
                    var links = sec.querySelectorAll('.shortcut-link');
                    var vis = 0;
                    links.forEach(function (link) {
                        var match = !q || (link.querySelector('.text') || link).textContent.toLowerCase().includes(q);
                        link.style.display = match ? '' : 'none';
                        if (match) vis++;
                    });
                    sec.style.display = q ? (vis > 0 ? '' : 'none') : '';
                    if (q) sec.classList.remove('collapsed');
                });
            });
        }
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); drawer.classList.contains('open') ? close() : open(); }
            if (e.key === 'Escape') close();
        });
    }

    /* ── Keypad toggle ────────────────────────────────────────────────── */
    function initKeypad() {
        var toggle = document.getElementById('keypad-toggle');
        var keypad = document.getElementById('keypad');
        var closeK = document.getElementById('keypad-close');
        if (!toggle || !keypad) return;
        toggle.addEventListener('click', function () { keypad.classList.add('visible'); toggle.style.display = 'none'; });
        if (closeK) closeK.addEventListener('click', function () { keypad.classList.remove('visible'); toggle.style.display = ''; });
    }

    /* ── Grid / List view toggle ──────────────────────────────────────── */
    function initViewToggle() {
        var gBtn = document.getElementById('grid-view-btn');
        var lBtn = document.getElementById('list-view-btn');
        var grid = document.getElementById('div_products_list') || document.getElementById('products-grid');
        if (!gBtn || !lBtn || !grid) return;
        gBtn.addEventListener('click', function () { gBtn.classList.add('active'); lBtn.classList.remove('active'); grid.classList.remove('list-view'); });
        lBtn.addEventListener('click', function () { lBtn.classList.add('active'); gBtn.classList.remove('active'); grid.classList.add('list-view'); });
    }

    /* ── Backward-compat toast alias ─────────────────────────────────── */
    window.showTakePOSToast = function (msg, type) {
        if (typeof window.tpShowToast === 'function') window.tpShowToast(msg, type);
    };

    document.addEventListener('DOMContentLoaded', function () {
        initShortcutsDrawer();
        initKeypad();
        initViewToggle();
    });
}());
