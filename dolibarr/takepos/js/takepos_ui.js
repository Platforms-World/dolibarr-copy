/**
 * TakePOS — Modern UI Enhancements (JS)
 * ======================================
 * Drop this file in: htdocs/takepos/js/takepos_ui.js
 * Link it at the BOTTOM of takepos/index.php, AFTER Dolibarr's own scripts.
 *
 * This file ONLY adds visual / UX polish — it never replaces PHP logic.
 * Every original Dolibarr event handler remains intact.
 */

(function () {
  'use strict';

  /* ============================================================
     HELPERS
     ============================================================ */
  function qs(sel, root) { return (root || document).querySelector(sel); }
  function qsa(sel, root) { return Array.from((root || document).querySelectorAll(sel)); }
  function ce(tag, attrs, children) {
    var el = document.createElement(tag);
    Object.entries(attrs || {}).forEach(function (e) { el.setAttribute(e[0], e[1]); });
    (children || []).forEach(function (c) {
      el.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
    });
    return el;
  }

  /* ============================================================
     LIVE CLOCK & DATE IN TOP BAR
     ============================================================ */
  function injectClock() {
    var bar = qs('#poslogodiv') || qs('.posmenubar') || qs('#posmenubar');
    if (!bar) return;
    if (qs('#tp-live-clock')) return;   // already injected

    var wrap = ce('div', { id: 'tp-clock-wrap', style: [
      'display:flex', 'align-items:center', 'gap:10px',
      'color:#94a3b8', 'font-size:12px', 'margin-left:auto', 'padding-right:8px'
    ].join(';') });

    var dateSpan  = ce('span', { id: 'tp-live-date' }, ['--/--/----']);
    var clockSpan = ce('span', { id: 'tp-live-clock', style: [
      'font-family:"JetBrains Mono","Courier New",monospace',
      'font-weight:600', 'color:#e2e8f0', 'font-size:13px'
    ].join(';') }, ['--:--']);

    wrap.appendChild(ce('span', {}, ['📅']));
    wrap.appendChild(dateSpan);
    wrap.appendChild(ce('span', {}, ['🕐']));
    wrap.appendChild(clockSpan);
    bar.appendChild(wrap);

    function tick() {
      var now = new Date();
      var h  = String(now.getHours()).padStart(2, '0');
      var m  = String(now.getMinutes()).padStart(2, '0');
      var d  = String(now.getDate()).padStart(2, '0');
      var mo = String(now.getMonth() + 1).padStart(2, '0');
      var y  = now.getFullYear();
      clockSpan.textContent = h + ':' + m;
      dateSpan.textContent  = d + '/' + mo + '/' + y;
    }
    tick();
    setInterval(tick, 30000);
  }

  /* ============================================================
     TOAST NOTIFICATION SYSTEM
     ============================================================ */
  (function buildToast() {
    if (qs('#tp-toast')) return;

    var style = document.createElement('style');
    style.textContent = [
      '#tp-toast{',
        'position:fixed;top:60px;left:50%;transform:translateX(-50%);',
        'background:#0f172a;color:#fff;',
        'padding:10px 18px;border-radius:8px;font-size:13px;font-weight:500;',
        'box-shadow:0 12px 32px rgba(15,23,42,.25);z-index:9999;',
        'display:flex;align-items:center;gap:8px;',
        'opacity:0;pointer-events:none;',
        'transition:opacity .25s,top .25s;',
        'font-family:"IBM Plex Sans Arabic","Segoe UI",system-ui,sans-serif;',
        'white-space:nowrap;',
      '}',
      '#tp-toast.show{opacity:1;top:70px}',
      '#tp-toast .tp-toast-icon{',
        'width:20px;height:20px;border-radius:50%;',
        'background:#047857;display:flex;align-items:center;justify-content:center;',
        'font-size:11px;flex-shrink:0',
      '}'
    ].join('');
    document.head.appendChild(style);

    var toast = ce('div', { id: 'tp-toast' });
    var icon  = ce('div', { class: 'tp-toast-icon' }, ['✓']);
    var msg   = ce('span', { id: 'tp-toast-msg' }, ['']);
    toast.appendChild(icon);
    toast.appendChild(msg);
    document.body.appendChild(toast);

    var timer;
    window.tpShowToast = function (message, type) {
      var colors = { success: '#047857', warning: '#b45309', error: '#b91c1c' };
      icon.style.background = colors[type] || colors.success;
      icon.textContent = type === 'error' ? '✕' : type === 'warning' ? '!' : '✓';
      qs('#tp-toast-msg').textContent = message;
      toast.classList.add('show');
      clearTimeout(timer);
      timer = setTimeout(function () { toast.classList.remove('show'); }, 2400);
    };
  })();

  /* ============================================================
     PRODUCT CARD ENHANCEMENTS
     ============================================================ */
  function enhanceProductCards() {
    qsa('.productdiv, #products .product').forEach(function (card) {
      if (card.dataset.tpEnhanced) return;
      card.dataset.tpEnhanced = '1';

      /* Price badge on image */
      var priceEl = qs('[data-price], .product-price, .price', card);
      var imgEl   = qs('img, .no-image', card);
      if (priceEl && imgEl && !qs('.tp-price-badge', card)) {
        var badge = ce('div', {
          class: 'tp-price-badge',
          style: [
            'position:absolute;top:6px;left:6px;z-index:2;',
            'background:#0f172a;color:#fff;',
            'font-size:11px;padding:3px 8px;border-radius:6px;',
            'font-weight:700;font-family:"JetBrains Mono","Courier New",monospace;',
            'box-shadow:0 2px 4px rgba(15,23,42,.2);letter-spacing:-.3px;',
            'pointer-events:none'
          ].join('')
        }, [priceEl.textContent.trim()]);
        if (getComputedStyle(card).position === 'static') {
          card.style.position = 'relative';
        }
        card.appendChild(badge);
      }

      /* Hover ripple */
      card.addEventListener('mouseenter', function () {
        card.style.transform = 'translateY(-2px)';
      });
      card.addEventListener('mouseleave', function () {
        card.style.transform = '';
      });
    });
  }

  /* ============================================================
     CART LINE QUANTITY STEPPERS
     Wraps existing qty cells with – / + buttons if not already present
     ============================================================ */
  function enhanceCartLines() {
    qsa('#invoicelines tr, .invoiceline').forEach(function (row) {
      if (row.dataset.tpQtyEnhanced) return;
      var qtyCell = qs('td.lineqty, td[class*=qty]', row);
      if (!qtyCell || qs('.tp-qty-stepper', qtyCell)) return;
      row.dataset.tpQtyEnhanced = '1';

      var val = qtyCell.textContent.trim();
      qtyCell.innerHTML = '';

      var stepper = ce('div', {
        class: 'tp-qty-stepper',
        style: [
          'display:inline-flex;align-items:center;',
          'background:#f6f8fc;border-radius:6px;border:1px solid #e2e8f0;',
          'height:26px;overflow:hidden;'
        ].join('')
      });

      var btnStyle = [
        'width:22px;height:24px;background:transparent;border:none;',
        'cursor:pointer;color:#475569;font-size:14px;font-weight:600;',
        'display:flex;align-items:center;justify-content:center;',
        'transition:background .12s;padding:0;'
      ].join('');

      var minus = ce('button', { style: btnStyle, type: 'button' }, ['−']);
      var qty   = ce('span', {
        style: [
          'min-width:24px;text-align:center;font-weight:700;color:#0f172a;',
          'font-family:"JetBrains Mono","Courier New",monospace;font-size:12px;'
        ].join('')
      }, [val]);
      var plus  = ce('button', { style: btnStyle, type: 'button' }, ['+']);

      stepper.appendChild(minus);
      stepper.appendChild(qty);
      stepper.appendChild(plus);
      qtyCell.appendChild(stepper);

      /* FIX (I04): Wired to Dolibarr's global Edit(rowid, field, value) function.
       * Edit() is defined in index.php and POSTs to invoice.php to update the
       * cart line on the server. We read data-rowid from the closest <tr>. */
      function getLineRowId() {
        var tr = row.closest ? row.closest('tr[data-rowid]') : null;
        return tr ? (tr.dataset.rowid || '') : '';
      }

      minus.addEventListener('click', function (e) {
        e.stopPropagation();
        var cur = parseFloat(qty.textContent) || 1;
        if (cur <= 1) return; // don't go below 1 via stepper
        var newQty = Math.round((cur - 1) * 1000) / 1000;
        qty.textContent = newQty;
        var rid = getLineRowId();
        if (rid && typeof Edit === 'function') { Edit(rid, 'qty', newQty); }
      });

      plus.addEventListener('click', function (e) {
        e.stopPropagation();
        var cur = parseFloat(qty.textContent) || 1;
        var newQty = Math.round((cur + 1) * 1000) / 1000;
        qty.textContent = newQty;
        var rid = getLineRowId();
        if (rid && typeof Edit === 'function') { Edit(rid, 'qty', newQty); }
      });
    });
  }

  /* ============================================================
     NUMPAD STYLING ENHANCEMENTS
     Adds mode-tab class mapping based on button text
     ============================================================ */
  function styleNumpad() {
    var numpad = qs('#numpad, .numpad, #pos-numpad');
    if (!numpad || numpad.dataset.tpStyled) return;
    numpad.dataset.tpStyled = '1';

    /* Wrap numpad buttons in a grid if not already */
    qsa('button', numpad).forEach(function (btn) {
      var text = btn.textContent.trim().toUpperCase();
      if (['QTY', 'CANTIDAD', 'الكمية', 'KOL'].indexOf(text) >= 0) {
        btn.classList.add('qty');
      }
      if (['DEL', 'BS', 'BACKSPACE', '⌫', 'حذف'].indexOf(text) >= 0) {
        btn.classList.add('del');
      }
    });
  }

  /* ============================================================
     FULLSCREEN TOGGLE
     ============================================================ */
  function addFullscreenBtn() {
    var bar = qs('#poslogodiv') || qs('.posmenubar');
    if (!bar || qs('#tp-fullscreen-btn')) return;

    var btn = ce('button', {
      id: 'tp-fullscreen-btn',
      title: 'Fullscreen (F11)',
      style: [
        'width:32px;height:32px;border-radius:6px;',
        'background:transparent;border:1px solid transparent;',
        'color:#94a3b8;cursor:pointer;font-size:14px;',
        'display:flex;align-items:center;justify-content:center;',
        'transition:all .15s;'
      ].join('')
    }, ['⛶']);

    btn.addEventListener('mouseenter', function () {
      btn.style.background = '#1e293b';
      btn.style.color = '#fff';
    });
    btn.addEventListener('mouseleave', function () {
      btn.style.background = 'transparent';
      btn.style.color = '#94a3b8';
    });

    btn.addEventListener('click', function () {
      if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen && document.documentElement.requestFullscreen();
      } else {
        document.exitFullscreen && document.exitFullscreen();
      }
    });

    bar.appendChild(btn);
  }

  /* ============================================================
     SELECTED LINE HIGHLIGHT
     Adds .selected class based on Dolibarr's selectedrowid var
     ============================================================ */
  function highlightSelectedLine() {
    if (typeof selectedrowid === 'undefined') return;
    qsa('#invoicelines tr, .invoiceline').forEach(function (row) {
      var id = row.dataset.rowid || row.id || '';
      if (id && id.indexOf(String(selectedrowid)) >= 0) {
        row.classList.add('selected');
      } else {
        row.classList.remove('selected');
      }
    });
  }

  /* ============================================================
     BARCODE SCAN VISUAL FEEDBACK
     Flashes the search input when a barcode is scanned
     ============================================================ */
  function barcodeFeedback() {
    var searchInput = qs('#search, #pos-search, input[name=search]');
    if (!searchInput || searchInput.dataset.tpBarcodeListening) return;
    searchInput.dataset.tpBarcodeListening = '1';

    var flashStyle = 'box-shadow:0 0 0 3px rgba(34,197,94,.35);border-color:#22c55e !important;';
    var origOl = searchInput.style.outline;

    searchInput.addEventListener('change', function () {
      searchInput.style.cssText += flashStyle;
      setTimeout(function () {
        searchInput.style.outline = origOl;
        searchInput.style.boxShadow = '';
        searchInput.style.borderColor = '';
      }, 600);
    });
  }

  /* ============================================================
     GRAND TOTAL COUNTER ANIMATION
     ============================================================ */
  function animateTotalChange() {
    var totalEl = qs('#grandtotalprice, #grandtotalttc, .grandtotal .amount');
    if (!totalEl) return;

    var observer = new MutationObserver(function () {
      totalEl.style.transition = 'color .2s';
      totalEl.style.color = '#4ade80';
      setTimeout(function () {
        totalEl.style.color = '';
      }, 500);
    });
    observer.observe(totalEl, { childList: true, subtree: true, characterData: true });
  }

  /* ============================================================
     BOOTSTRAP — run on DOM ready, then periodically for dynamic content
     ============================================================ */
  function boot() {
    injectClock();
    addFullscreenBtn();
    enhanceProductCards();
    enhanceCartLines();
    styleNumpad();
    barcodeFeedback();
    animateTotalChange();
    highlightSelectedLine();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  /* Re-run enhancements when Dolibarr dynamically reloads sections */
  var refreshInterval = setInterval(function () {
    enhanceProductCards();
    enhanceCartLines();
    highlightSelectedLine();
  }, 800);

  /* Stop polling after 5 minutes (after page load stabilizes) */
  setTimeout(function () { clearInterval(refreshInterval); }, 300000);

  /* Expose public API */
  window.takePOSUI = {
    toast: function (msg, type) { window.tpShowToast && window.tpShowToast(msg, type); },
    refresh: function () { enhanceProductCards(); enhanceCartLines(); }
  };

})();
