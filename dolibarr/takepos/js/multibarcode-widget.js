/**
 * TakePos Multi-Barcode Widget + UX Enhancement
 * File: htdocs/takepos/js/multibarcode-widget.js
 *
 * Works on BOTH:
 *   - New product page  (action=create) → stores barcodes after product save via session
 *   - View/Edit page    (product exists) → live CRUD via AJAX
 *
 * Zero changes to any Dolibarr PHP file needed beyond the one-line include.
 */
(function () {
  'use strict';

  /* ══════════════════════════════════════════════════════════════════════════
     0.  HELPERS
  ══════════════════════════════════════════════════════════════════════════ */
  function esc(s) {
    return String(s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function $id(id)  { return document.getElementById(id); }
  function $q(sel)  { return document.querySelector(sel); }
  function $qa(sel) { return Array.from(document.querySelectorAll(sel)); }

  /* ══════════════════════════════════════════════════════════════════════════
     1.  DETECT PAGE CONTEXT
  ══════════════════════════════════════════════════════════════════════════ */
  const urlParams = new URLSearchParams(window.location.search);
  const action    = urlParams.get('action') || 'view';
  const productId = parseInt(urlParams.get('id') || '0', 10);
  const isCreate  = (action === 'create');
  const isEdit    = (action === 'edit');
  const isView    = (!isCreate && !isEdit && productId > 0);

  /* Only run on product/card.php */
  if (!window.location.pathname.includes('/product/card')) return;

  /* ══════════════════════════════════════════════════════════════════════════
     2.  CSS  (self-contained, prefixed .tpbc-)
  ══════════════════════════════════════════════════════════════════════════ */
  const CSS = `
  /* ── Design tokens ── */
  .tpbc-wrap {
    --c-blue:    #2563eb;
    --c-blue-h:  #1d4ed8;
    --c-blue-lt: #eff6ff;
    --c-red:     #ef4444;
    --c-red-h:   #dc2626;
    --c-green:   #10b981;
    --c-border:  #e2e8f0;
    --c-text:    #1e293b;
    --c-muted:   #64748b;
    --c-bg:      #ffffff;
    --r:         8px;
    --shadow:    0 1px 4px rgba(0,0,0,.08);
    font-family: inherit;
  }

  /* ── UX Enhancement Banner (create/edit only) ── */
  .tpbc-ux-banner {
    margin: 0 0 18px;
    padding: 14px 18px;
    background: linear-gradient(135deg,#f0f9ff,#e0f2fe);
    border: 1px solid #bae6fd;
    border-radius: var(--r);
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: .85rem;
    color: #0369a1;
    font-weight: 500;
  }
  .tpbc-ux-banner svg { flex-shrink: 0; }

  /* ── Section wrapper ── */
  .tpbc-section {
    margin: 14px 0 6px;
    background: var(--c-bg);
    border: 1px solid var(--c-border);
    border-radius: var(--r);
    box-shadow: var(--shadow);
    overflow: hidden;
  }

  /* ── Section header ── */
  .tpbc-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 11px 16px;
    background: linear-gradient(135deg,#f0f7ff,#e8f3ff);
    border-bottom: 1px solid var(--c-border);
    cursor: pointer;
    user-select: none;
    gap: 8px;
  }
  .tpbc-header-left {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    font-size: .875rem;
    color: var(--c-blue);
  }
  .tpbc-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 5px;
    background: var(--c-blue);
    color: #fff;
    border-radius: 10px;
    font-size: .68rem;
    font-weight: 700;
  }
  .tpbc-chevron {
    transition: transform .22s;
    color: var(--c-muted);
    margin-left: auto;
  }
  .tpbc-section.tpbc-collapsed .tpbc-chevron { transform: rotate(-90deg); }
  .tpbc-section.tpbc-collapsed .tpbc-body    { display: none; }

  /* ── Body ── */
  .tpbc-body { padding: 14px 16px; }

  /* ── Hint line ── */
  .tpbc-hint {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: .76rem;
    color: var(--c-muted);
    margin-bottom: 12px;
  }

  /* ── Input row ── */
  .tpbc-add-row {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
    margin-bottom: 14px;
    align-items: center;
  }
  .tpbc-input {
    flex: 1 1 140px;
    min-width: 0;
    height: 34px;
    padding: 0 10px;
    border: 1px solid var(--c-border);
    border-radius: 6px;
    font-size: .84rem;
    color: var(--c-text);
    background: #fff;
    transition: border-color .15s, box-shadow .15s;
    outline: none;
    font-family: inherit;
  }
  .tpbc-input:focus {
    border-color: var(--c-blue);
    box-shadow: 0 0 0 3px rgba(37,99,235,.12);
  }
  .tpbc-input::placeholder { color: #a0aec0; }
  .tpbc-input-bc  { max-width: 200px; font-family: monospace; letter-spacing: .04em; }
  .tpbc-input-lbl { max-width: 160px; }
  .tpbc-input-qty   { max-width: 74px;  flex: 0 0 74px;  text-align: center; }
  .tpbc-input-price { max-width: 108px; flex: 0 0 108px; text-align: right; }
  /* hide number spinners for a cleaner look */
  .tpbc-input-qty::-webkit-outer-spin-button,
  .tpbc-input-qty::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

  /* ── Buttons ── */
  .tpbc-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    height: 34px;
    padding: 0 13px;
    border: none;
    border-radius: 6px;
    font-size: .8rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: background .15s, transform .1s, box-shadow .15s;
    font-family: inherit;
  }
  .tpbc-btn:active { transform: translateY(1px); }
  .tpbc-btn-add {
    background: var(--c-blue);
    color: #fff;
    box-shadow: 0 1px 4px rgba(37,99,235,.28);
  }
  .tpbc-btn-add:hover:not(:disabled) { background: var(--c-blue-h); }
  .tpbc-btn-add:disabled {
    background: #93c5fd;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
  }

  /* ── Tag list ── */
  .tpbc-list {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    min-height: 28px;
    align-items: center;
  }
  .tpbc-empty {
    color: var(--c-muted);
    font-size: .78rem;
    font-style: italic;
  }
  .tpbc-tag {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 9px 4px 8px;
    background: var(--c-blue-lt);
    border: 1px solid #bfdbfe;
    border-radius: 16px;
    font-size: .78rem;
    color: var(--c-blue);
    font-weight: 500;
    animation: tpbc-pop .18s ease;
  }
  @keyframes tpbc-pop {
    from { transform: scale(.6); opacity: 0; }
    to   { transform: scale(1);  opacity: 1; }
  }
  .tpbc-tag-bc  { font-family: monospace; letter-spacing: .03em; }
  .tpbc-tag-lbl { font-size: .7rem; color: var(--c-muted); font-weight: 400; }
  .tpbc-tag-qty {
    font-size: .68rem; font-weight: 700; color: #fff;
    background: #6366f1; border-radius: 9px; padding: 1px 6px;
  }
  .tpbc-tag-price {
    font-size: .68rem; font-weight: 700; color: #065f46;
    background: #d1fae5; border-radius: 9px; padding: 1px 6px;
  }
  .tpbc-tag-del {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 15px; height: 15px;
    border: none;
    border-radius: 50%;
    background: transparent;
    color: var(--c-muted);
    cursor: pointer;
    font-size: 14px;
    line-height: 1;
    padding: 0;
    transition: background .12s, color .12s;
  }
  .tpbc-tag-del:hover { background: var(--c-red); color: #fff; }

  /* ── Toast ── */
  .tpbc-toast {
    position: fixed;
    bottom: 22px; right: 22px;
    z-index: 99999;
    padding: 9px 16px;
    border-radius: 8px;
    font-size: .8rem;
    font-weight: 600;
    color: #fff;
    box-shadow: 0 4px 16px rgba(0,0,0,.18);
    animation: tpbc-sin .22s ease, tpbc-fout .28s 2s forwards;
    pointer-events: none;
    font-family: inherit;
  }
  .tpbc-toast.ok  { background: var(--c-green); }
  .tpbc-toast.err { background: var(--c-red);   }
  @keyframes tpbc-sin  { from { transform:translateY(18px);opacity:0 } to { transform:translateY(0);opacity:1 } }
  @keyframes tpbc-fout { to   { opacity:0 } }

  /* ── Spinner ── */
  .tpbc-spin {
    display: inline-block;
    width: 12px; height: 12px;
    border: 2px solid rgba(255,255,255,.35);
    border-top-color: #fff;
    border-radius: 50%;
    animation: tpbc-r .55s linear infinite;
    vertical-align: middle;
  }
  @keyframes tpbc-r { to { transform: rotate(360deg); } }

  /* ── UX improvements for the Dolibarr product form ── */
  /* Make required field labels pop */
  .mod-product.page-card td.fieldrequired,
  .mod-product.page-card td.titlefieldcreate.fieldrequired {
    font-weight: 600 !important;
  }
  /* Slightly enlarge the main ref & label inputs */
  .mod-product.page-card input#ref,
  .mod-product.page-card input[name="label"] {
    font-size: .95rem !important;
    padding: 6px 10px !important;
    height: auto !important;
  }
  /* Nicer barcode icon+input alignment */
  .mod-product.page-card td img.pictofixedwidth + input[name="barcode"] {
    vertical-align: middle;
    margin-left: 4px;
  }
  /* Highlight the barcode section visually */
  .tpbc-bc-row td {
    background: #fafcff !important;
  }
  `;

  const styleEl = document.createElement('style');
  styleEl.textContent = CSS;
  document.head.appendChild(styleEl);

  /* ══════════════════════════════════════════════════════════════════════════
     3.  STATE
  ══════════════════════════════════════════════════════════════════════════ */
  // On CREATE page: barcodes are stored in memory, then submitted as hidden
  // inputs with the form. A PHP snippet in card.php saves them after product
  // creation. (See INSTALL notes for that snippet — it's the only PHP touch.)
  //
  // On VIEW/EDIT page: barcodes are live-saved via AJAX to multibarcode.php.

  /* ══════════════════════════════════════════════════════════════════════════
     STATE  — two independent groups in the SAME table:
       alt  = alternate barcodes (qty_multiplier = 1, price_override = NULL)
       pack = packaging / carton  (qty_multiplier > 1 and/or price_override set)
  ══════════════════════════════════════════════════════════════════════════ */
  const state = { alt: [], pack: [] };

  /* ── Extra CSS for the packaging row + qty/price chips ── */
  (function () {
    const css = `
      .tpbc-input-qty   { max-width: 74px;  flex: 0 0 74px;  text-align: center; }
      .tpbc-input-price { max-width: 108px; flex: 0 0 108px; text-align: right; }
      .tpbc-input-qty::-webkit-outer-spin-button,
      .tpbc-input-qty::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
      .tpbc-tag-qty   { font-size:.68rem; font-weight:700; color:#fff;     background:#6366f1; border-radius:9px; padding:1px 6px; }
      .tpbc-tag-price { font-size:.68rem; font-weight:700; color:#065f46; background:#d1fae5; border-radius:9px; padding:1px 6px; }
    `;
    const s = document.createElement('style');
    s.textContent = css;
    document.head.appendChild(s);
  })();

  /* ══════════════════════════════════════════════════════════════════════════
     AJAX + TOAST
  ══════════════════════════════════════════════════════════════════════════ */
  const AJAX_URL = (window.DOL_URL_ROOT || '') + '/takepos/ajax/multibarcode.php';
  const TOKEN    = ($q('meta[name="anti-csrf-newtoken"]') || {}).getAttribute?.('content') || '';

  function api(params) {
    params.token = TOKEN;
    const fd = new FormData();
    Object.entries(params).forEach(([k, v]) => fd.append(k, String(v)));
    return fetch(AJAX_URL, { method: 'POST', body: fd }).then(r => r.json());
  }

  function toast(msg, type = 'ok') {
    const el = document.createElement('div');
    el.className = 'tpbc-toast ' + type;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 2500);
  }

  /* Decide which group a server row belongs to. */
  function classify(row) {
    const q = parseFloat(row.qty_multiplier);
    const hasPrice = row.price_override !== null && row.price_override !== undefined && row.price_override !== '';
    return ((q && q > 1) || hasPrice) ? 'pack' : 'alt';
  }

  /* Per-kind DOM ids. */
  function ids(kind) {
    return {
      bc:    'tpbc-bc-' + kind,
      lbl:   'tpbc-lbl-' + kind,
      qty:   'tpbc-qty-' + kind,
      price: 'tpbc-price-' + kind,
      add:   'tpbc-add-' + kind,
      list:  'tpbc-list-' + kind,
      badge: 'tpbc-badge-' + kind,
      empty: 'tpbc-empty-' + kind,
    };
  }

  /* ══════════════════════════════════════════════════════════════════════════
     BUILD ONE SECTION
  ══════════════════════════════════════════════════════════════════════════ */
  function buildSection(kind) {
    const i = ids(kind);
    const isPack = (kind === 'pack');
    const title  = isPack ? 'Packaging / Carton' : 'Extra Barcodes';
    const hint   = isPack
      ? 'Barcodes that represent a package (e.g. a carton of 24). Scanning adds that quantity, optionally at its own price.'
      : 'Alternate barcodes for this product. Same unit &amp; price — just extra codes searchable from POS.';

    const section = document.createElement('div');
    section.className = 'tpbc-section';
    section.innerHTML = `
      <div class="tpbc-header" data-kind="${kind}">
        <div class="tpbc-header-left">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <rect x="2" y="4" width="3" height="16" rx="1"/><rect x="7" y="4" width="1.5" height="16" rx="1"/>
            <rect x="11" y="4" width="3" height="16" rx="1"/><rect x="16.5" y="4" width="1.5" height="16" rx="1"/>
            <rect x="20" y="4" width="2" height="16" rx="1"/>
          </svg>
          ${title}
          <span class="tpbc-badge" id="${i.badge}">0</span>
          <span style="font-size:.72rem;font-weight:400;color:#64748b;margin-left:4px">(TakePos POS scan)</span>
        </div>
        <svg class="tpbc-chevron" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <polyline points="6 9 12 15 18 9"/>
        </svg>
      </div>
      <div class="tpbc-body">
        <div class="tpbc-hint">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          ${hint}
        </div>
        <div class="tpbc-add-row">
          <input id="${i.bc}"  class="tpbc-input tpbc-input-bc"  placeholder="Barcode value (scan/type)" autocomplete="off"/>
          <input id="${i.lbl}" class="tpbc-input tpbc-input-lbl" placeholder="Label (optional)" autocomplete="off"/>
          ${isPack ? `<input id="${i.qty}"   class="tpbc-input tpbc-input-qty"   type="number" min="1" step="1" value="1" title="Units per scan (e.g. 24)" placeholder="Qty"/>` : ''}
          ${isPack ? `<input id="${i.price}" class="tpbc-input tpbc-input-price" type="number" min="0" step="0.001" title="Price of the WHOLE package (e.g. 200 for a carton of 20). Empty = qty × unit price." placeholder="Price (opt.)" autocomplete="off"/>` : ''}
          <button id="${i.add}" class="tpbc-btn tpbc-btn-add">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add
          </button>
        </div>
        <div class="tpbc-list" id="${i.list}" data-kind="${kind}">
          <span class="tpbc-empty" id="${i.empty}">${isPack ? 'No packaging barcodes yet' : 'No extra barcodes yet'}</span>
        </div>
      </div>
    `;
    return section;
  }

  /* ══════════════════════════════════════════════════════════════════════════
     RENDER ONE GROUP
  ══════════════════════════════════════════════════════════════════════════ */
  function renderList(kind) {
    const i = ids(kind);
    const listEl  = $id(i.list);
    const badgeEl = $id(i.badge);
    const emptyEl = $id(i.empty);
    if (!listEl) return;

    listEl.querySelectorAll('.tpbc-tag').forEach(t => t.remove());
    const rows = state[kind];
    if (badgeEl) badgeEl.textContent = rows.length;
    if (emptyEl) emptyEl.style.display = rows.length === 0 ? '' : 'none';

    rows.forEach(bc => {
      const tag = document.createElement('span');
      tag.className = 'tpbc-tag';
      tag.dataset.id = bc.id;
      const qty   = parseFloat(bc.qty_multiplier);
      const price = (bc.price_override === null || bc.price_override === undefined || bc.price_override === '')
        ? null : parseFloat(bc.price_override);
      tag.innerHTML =
        `<span class="tpbc-tag-bc">${esc(bc.barcode)}</span>` +
        (bc.label ? `<span class="tpbc-tag-lbl">(${esc(bc.label)})</span>` : '') +
        ((kind === 'pack' && qty && qty > 1) ? `<span class="tpbc-tag-qty" title="Units per scan">×${esc(qty)}</span>` : '') +
        ((kind === 'pack' && price !== null && !isNaN(price)) ? `<span class="tpbc-tag-price" title="Price override (TTC)">${esc(price)}</span>` : '') +
        `<button class="tpbc-tag-del" data-id="${esc(bc.id)}" title="Remove">×</button>`;
      listEl.appendChild(tag);
    });
  }

  /* ══════════════════════════════════════════════════════════════════════════
     ADD  (AJAX — view page, product already exists)
  ══════════════════════════════════════════════════════════════════════════ */
  function addRow(kind) {
    const i = ids(kind);
    const bcEl  = $id(i.bc);
    const lblEl = $id(i.lbl);
    const btn   = $id(i.add);
    const bc    = bcEl.value.trim();
    if (!bc) { bcEl.focus(); return; }
    if (!productId) { toast('Save the product first', 'err'); return; }

    let qty = 1, price = null;
    if (kind === 'pack') {
      qty = parseFloat(String(($id(i.qty) || {}).value || '1').replace(',', '.'));
      if (!qty || qty <= 0) qty = 1;
      const praw = ($id(i.price) || {}).value ? $id(i.price).value.trim() : '';
      price = (praw !== '' && !isNaN(parseFloat(praw.replace(',', '.')))) ? parseFloat(praw.replace(',', '.')) : null;
    }

    // Duplicate check across BOTH groups
    if (state.alt.concat(state.pack).some(b => b.barcode === bc)) {
      toast('Barcode already in list', 'err');
      bcEl.select();
      return;
    }

    const reset = () => {
      bcEl.value = '';
      lblEl.value = '';
      if (kind === 'pack') { if ($id(i.qty)) $id(i.qty).value = '1'; if ($id(i.price)) $id(i.price).value = ''; }
      bcEl.focus();
    };

    btn.disabled = true;
    const oldHtml = btn.innerHTML;
    btn.innerHTML = '<span class="tpbc-spin"></span> Saving…';
    api({
      action: 'add', product_id: productId, barcode: bc, label: lblEl.value.trim(),
      qty_multiplier: qty, price_override: (price === null ? '' : price)
    })
      .then(data => {
        if (data.success) {
          state[kind].push({ id: data.id, barcode: bc, label: lblEl.value.trim(), qty_multiplier: qty, price_override: price });
          renderList(kind);
          reset();
          toast('Saved ✓');
        } else {
          toast(data.error || 'Failed to save', 'err');
        }
      })
      .catch(() => toast('Network error', 'err'))
      .finally(() => { btn.disabled = false; btn.innerHTML = oldHtml; });
  }

  /* ══════════════════════════════════════════════════════════════════════════
     DELETE
  ══════════════════════════════════════════════════════════════════════════ */
  function deleteRow(kind, id) {
    api({ action: 'delete', product_id: productId, barcode_id: id })
      .then(data => {
        if (data.success) {
          state[kind] = state[kind].filter(b => String(b.id) !== String(id));
          renderList(kind);
          toast('Removed');
        } else {
          toast('Failed to remove', 'err');
        }
      })
      .catch(() => toast('Network error', 'err'));
  }

  /* ══════════════════════════════════════════════════════════════════════════
     LOAD EXISTING + classify into the two groups
  ══════════════════════════════════════════════════════════════════════════ */
  function loadAll() {
    if (!productId) return;
    api({ action: 'list', product_id: productId })
      .then(data => {
        if (!data.success) return;
        state.alt = []; state.pack = [];
        (data.barcodes || []).forEach(r => { state[classify(r)].push(r); });
        renderList('alt');
        renderList('pack');
      })
      .catch(() => {/* silent */ });
  }

  /* ══════════════════════════════════════════════════════════════════════════
     INJECTION POINT  (after the native "Barcode value" row)
  ══════════════════════════════════════════════════════════════════════════ */
  function inject(wrapEl) {
    const trs = $qa('table.border tr, table.allwidth tr');
    let target = null;
    for (const tr of trs) {
      const td = tr.querySelector('td');
      if (!td) continue;
      const txt = td.textContent.trim().toLowerCase();
      if (txt === 'barcode value' || txt === 'barcode' || /^barcode\s*val/i.test(txt)) { target = tr; break; }
    }
    if (!target) {
      for (const tr of trs) {
        const td = tr.querySelector('td');
        if (td && /barcode type/i.test(td.textContent)) { target = tr; break; }
      }
    }
    if (target) {
      const tr = document.createElement('tr');
      tr.className = 'tpbc-bc-row';
      const td = document.createElement('td');
      td.setAttribute('colspan', '4');
      td.style.cssText = 'padding: 0 10px 10px; border: none;';
      td.appendChild(wrapEl);
      tr.appendChild(td);
      target.insertAdjacentElement('afterend', tr);
      return true;
    }
    const fiche = $q('.fichecenter') || $q('.fiche') || $q('form[name="formprod"]');
    if (fiche) { fiche.prepend(wrapEl); return true; }
    return false;
  }

  /* ══════════════════════════════════════════════════════════════════════════
     UX niceties on the native form
  ══════════════════════════════════════════════════════════════════════════ */
  function applyUXImprovements() {
    document.addEventListener('keydown', e => {
      const bcInput = $id('tpbc-bc-alt');
      if (!bcInput) return;
      const active = document.activeElement;
      if (active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA')) return;
      if (e.key.length === 1) bcInput.focus();
    });
  }

  /* ══════════════════════════════════════════════════════════════════════════
     BOOTSTRAP  — runs on the VIEW page (product exists, native layout shown)
  ══════════════════════════════════════════════════════════════════════════ */
  function init() {
    // The create/edit pages use the redesigned card in product/card.php, which
    // hides the native tables. Only build here when there is a real product to
    // manage and a native barcode row to attach to.
    if (!productId || isCreate || isEdit) return;

    const wrap = document.createElement('div');
    wrap.className = 'tpbc-wrap';
    wrap.appendChild(buildSection('alt'));
    wrap.appendChild(buildSection('pack'));

    if (!inject(wrap)) return;

    ['alt', 'pack'].forEach(kind => {
      const i = ids(kind);
      const header = $q(`.tpbc-header[data-kind="${kind}"]`);
      if (header) header.addEventListener('click', () => header.closest('.tpbc-section').classList.toggle('tpbc-collapsed'));
      $id(i.add)?.addEventListener('click', () => addRow(kind));
      [i.bc, i.lbl, i.qty, i.price].forEach(id => {
        $id(id)?.addEventListener('keydown', e => {
          if (e.key === 'Enter') { e.preventDefault(); addRow(kind); }
        });
      });
      const listEl = $id(i.list);
      if (listEl) listEl.addEventListener('click', e => {
        const btn = e.target.closest('.tpbc-tag-del');
        if (btn) deleteRow(kind, btn.dataset.id);
      });
    });

    loadAll();
    applyUXImprovements();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
