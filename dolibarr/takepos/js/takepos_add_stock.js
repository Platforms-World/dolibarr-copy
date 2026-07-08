/**
 * takepos_add_stock.js
 *
 * Renders an "Add stock" popup when the cashier tries to add a zero-stock
 * product to the cart. Requires manager credentials and posts to
 * ajax/add_stock.php which performs a real MouvementStock::reception().
 *
 * Public API (attached to window):
 *   takeposAddStockPrompt(opts):
 *       opts = {
 *         productId:    int       (required)
 *         productLabel: string    (shown in the popup header)
 *         qtyWanted:    number    (pre-fills the qty input — typically what
 *                                  the cashier was trying to add)
 *         stockFree:    number    (current free stock — shown for context)
 *         onSuccess:    function(newStock, qtyAdded) — called after the
 *                       server confirms the movement; the caller can then
 *                       retry the original add-to-cart flow.
 *         onCancel:     function()  (optional)
 *       }
 *
 * Configuration read from window globals injected by index.php:
 *   window.takeposAddStockEndpoint  — URL of ajax/add_stock.php
 *   window.takeposCsrfToken          — CSRF token (newtoken)
 *   window.takeposAddStockLabels     — object with localized strings
 */
(function () {
    'use strict';

    function L(key, fallback) {
        var labels = window.takeposAddStockLabels || {};
        return (labels && labels[key]) ? labels[key] : fallback;
    }

    // --- Styles, injected once
    function injectStyles() {
        if (document.getElementById('tp-as-styles')) return;
        var s = document.createElement('style');
        s.id = 'tp-as-styles';
        s.textContent = [
            // backdrop
            '.tp-as-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.62);z-index:99999;display:flex;align-items:center;justify-content:center;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen,Ubuntu,sans-serif;padding:16px;animation:tpasFade .15s ease-out}',
            '@keyframes tpasFade{from{opacity:0}to{opacity:1}}',
            // modal shell
            '.tp-as-modal{background:#fff;color:#0f172a;border-radius:14px;box-shadow:0 24px 60px rgba(0,0,0,.4);width:min(520px,100%);max-height:calc(100vh - 32px);overflow:auto;direction:inherit;animation:tpasPop .18s ease-out}',
            '@keyframes tpasPop{from{transform:scale(.96);opacity:0}to{transform:scale(1);opacity:1}}',
            // header
            '.tp-as-head{padding:20px 24px 14px;border-bottom:1px solid #e2e8f0}',
            '.tp-as-title{font-size:18px;font-weight:700;margin:0 0 4px;line-height:1.3;color:#0f172a}',
            '.tp-as-sub{font-size:13px;color:#64748b;margin:0;line-height:1.4;word-break:break-word}',
            // body
            '.tp-as-body{padding:18px 24px 20px}',
            // stock info card
            '.tp-as-stockinfo{display:flex;justify-content:space-between;gap:16px;font-size:13px;background:#f1f5f9;border-radius:8px;padding:12px 14px;margin-bottom:16px;line-height:1.5}',
            '.tp-as-stockinfo .tp-as-si-item{display:flex;flex-direction:column;gap:2px}',
            '.tp-as-stockinfo .tp-as-si-label{font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:#64748b;font-weight:600}',
            '.tp-as-stockinfo .tp-as-si-value{font-size:16px;font-weight:700;color:#0f172a}',
            // message banner
            '.tp-as-msg{font-size:13px;padding:11px 13px;border-radius:8px;margin-bottom:14px;display:none;line-height:1.45}',
            '.tp-as-msg.show{display:block}',
            '.tp-as-msg.error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}',
            '.tp-as-msg.success{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}',
            // form rows
            '.tp-as-row{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}',
            '.tp-as-row-2col{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px}',
            '.tp-as-row label{font-size:12px;font-weight:600;color:#334155;letter-spacing:.1px}',
            '.tp-as-row input{padding:11px 13px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:15px;color:#0f172a;background:#fff;width:100%;box-sizing:border-box;transition:border-color .12s,box-shadow .12s}',
            '.tp-as-row input::placeholder{color:#94a3b8}',
            '.tp-as-row input:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.18)}',
            '.tp-as-row input:disabled{background:#f8fafc;color:#64748b}',
            // actions
            '.tp-as-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:18px;padding-top:14px;border-top:1px solid #e2e8f0}',
            '.tp-as-btn{padding:11px 20px;border-radius:8px;font-size:14px;font-weight:600;border:1.5px solid transparent;cursor:pointer;line-height:1.2;min-height:42px;transition:background .12s,border-color .12s,opacity .12s}',
            '.tp-as-btn-cancel{background:#fff;color:#475569;border-color:#cbd5e1}',
            '.tp-as-btn-cancel:hover{background:#f1f5f9;border-color:#94a3b8}',
            '.tp-as-btn-confirm{background:#16a34a;color:#fff;border-color:#15803d;min-width:180px}',
            '.tp-as-btn-confirm:hover{background:#15803d}',
            '.tp-as-btn[disabled]{opacity:.5;cursor:not-allowed}',
            // small-screen tweaks
            '@media (max-width:520px){.tp-as-row-2col{grid-template-columns:1fr}.tp-as-stockinfo{flex-direction:column;gap:8px}}'
        ].join('');
        document.head.appendChild(s);
    }

    function escapeHtml(s) {
        if (s == null) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // Close any previously-open popup before opening a new one
    function closeExisting() {
        var prev = document.getElementById('tp-add-stock-backdrop');
        if (prev) prev.parentNode.removeChild(prev);
    }

    window.takeposAddStockPrompt = function (opts) {
        opts = opts || {};
        var productId    = parseInt(opts.productId, 10);
        var productLabel = String(opts.productLabel || '');
        var qtyWanted    = parseFloat(opts.qtyWanted) || 1;
        var stockFree    = (typeof opts.stockFree === 'number') ? opts.stockFree : null;
        var onSuccess    = (typeof opts.onSuccess === 'function') ? opts.onSuccess : function () {};
        var onCancel     = (typeof opts.onCancel  === 'function') ? opts.onCancel  : function () {};

        if (!productId || productId <= 0) {
            console.warn('[takeposAddStockPrompt] missing productId');
            return;
        }

        injectStyles();
        closeExisting();

        var backdrop = document.createElement('div');
        backdrop.className = 'tp-as-backdrop';
        backdrop.id        = 'tp-add-stock-backdrop';

        var qtyDefault = qtyWanted;
        if (stockFree !== null && stockFree < qtyWanted) {
            // Suggest enough to cover the shortage
            qtyDefault = Math.max(qtyWanted - stockFree, 1);
        }

        var html = ''
            + '<div class="tp-as-modal" role="dialog" aria-modal="true" aria-labelledby="tp-as-title">'
            +   '<div class="tp-as-head">'
            +     '<h3 class="tp-as-title" id="tp-as-title">' + escapeHtml(L('title', 'Add stock for this product')) + '</h3>'
            +     '<p class="tp-as-sub">' + escapeHtml(productLabel) + '</p>'
            +   '</div>'
            +   '<div class="tp-as-body">'
            +     '<div class="tp-as-stockinfo">'
            +       '<div class="tp-as-si-item">'
            +         '<span class="tp-as-si-label">' + escapeHtml(L('currentStock', 'Current free stock')) + '</span>'
            +         '<span class="tp-as-si-value">' + (stockFree !== null ? stockFree : '?') + '</span>'
            +       '</div>'
            +       '<div class="tp-as-si-item">'
            +         '<span class="tp-as-si-label">' + escapeHtml(L('qtyRequested', 'Qty requested')) + '</span>'
            +         '<span class="tp-as-si-value">' + qtyWanted + '</span>'
            +       '</div>'
            +     '</div>'
            +     '<div class="tp-as-msg" id="tp-as-msg"></div>'
            +     '<div class="tp-as-row">'
            +       '<label for="tp-as-qty">' + escapeHtml(L('qtyToAdd', 'Quantity to add to stock')) + '</label>'
            +       '<input type="number" id="tp-as-qty" inputmode="decimal" step="any" min="0.001" value="' + qtyDefault + '" />'
            +     '</div>'
            +     '<div class="tp-as-row-2col">'
            +       '<div class="tp-as-row" style="margin-bottom:0">'
            +         '<label for="tp-as-mgr-login">' + escapeHtml(L('managerLogin', 'Manager login')) + '</label>'
            +         '<input type="text" id="tp-as-mgr-login" autocomplete="off" />'
            +       '</div>'
            +       '<div class="tp-as-row" style="margin-bottom:0">'
            +         '<label for="tp-as-mgr-pw">' + escapeHtml(L('managerPassword', 'Manager password')) + '</label>'
            +         '<input type="password" id="tp-as-mgr-pw" autocomplete="off" />'
            +       '</div>'
            +     '</div>'
            +     '<div class="tp-as-row">'
            +       '<label for="tp-as-reason">' + escapeHtml(L('reason', 'Reason (optional)')) + '</label>'
            +       '<input type="text" id="tp-as-reason" maxlength="200" placeholder="' + escapeHtml(L('reasonPlaceholder', 'e.g. found extra units in storage')) + '" />'
            +     '</div>'
            +     '<div class="tp-as-actions">'
            +       '<button type="button" class="tp-as-btn tp-as-btn-cancel" id="tp-as-cancel">' + escapeHtml(L('cancel', 'Cancel')) + '</button>'
            +       '<button type="button" class="tp-as-btn tp-as-btn-confirm" id="tp-as-confirm">' + escapeHtml(L('confirm', 'Add stock & continue')) + '</button>'
            +     '</div>'
            +   '</div>'
            + '</div>';

        backdrop.innerHTML = html;
        document.body.appendChild(backdrop);

        var modal     = backdrop.querySelector('.tp-as-modal');
        var qtyInput  = backdrop.querySelector('#tp-as-qty');
        var loginInp  = backdrop.querySelector('#tp-as-mgr-login');
        var pwInp     = backdrop.querySelector('#tp-as-mgr-pw');
        var reasonInp = backdrop.querySelector('#tp-as-reason');
        var msgBox    = backdrop.querySelector('#tp-as-msg');
        var btnCancel = backdrop.querySelector('#tp-as-cancel');
        var btnOk     = backdrop.querySelector('#tp-as-confirm');

        setTimeout(function () { loginInp.focus(); }, 30);

        function showMsg(text, level) {
            msgBox.textContent = text || '';
            msgBox.className   = 'tp-as-msg show ' + (level === 'success' ? 'success' : 'error');
        }

        function close() {
            if (backdrop.parentNode) backdrop.parentNode.removeChild(backdrop);
        }

        function cancel() {
            close();
            try { onCancel(); } catch (e) {}
        }

        // Click outside the modal = cancel
        backdrop.addEventListener('click', function (e) {
            if (e.target === backdrop) cancel();
        });
        // Esc cancels
        backdrop.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') cancel();
        });
        btnCancel.addEventListener('click', cancel);

        // Enter in the password field submits
        pwInp.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); btnOk.click(); }
        });

        btnOk.addEventListener('click', function () {
            var qty   = parseFloat(qtyInput.value);
            var login = (loginInp.value || '').trim();
            var pw    = pwInp.value || '';
            var reason = (reasonInp.value || '').trim();

            if (!(qty > 0)) {
                showMsg(L('errQty', 'Quantity must be greater than zero.'), 'error');
                qtyInput.focus();
                return;
            }
            if (!login || !pw) {
                showMsg(L('errCreds', 'Manager login and password are required.'), 'error');
                (!login ? loginInp : pwInp).focus();
                return;
            }

            btnOk.disabled = true;
            btnCancel.disabled = true;
            showMsg(L('working', 'Saving stock movement...'), 'success');

            var endpoint = window.takeposAddStockEndpoint;
            var tok      = window.takeposCsrfToken || '';
            if (!endpoint) {
                showMsg('Endpoint not configured.', 'error');
                btnOk.disabled = false; btnCancel.disabled = false;
                return;
            }

            var fd = new FormData();
            fd.append('product_id',       String(productId));
            fd.append('qty',              String(qty));
            fd.append('manager_login',    login);
            fd.append('manager_password', pw);
            fd.append('reason',           reason);
            fd.append('token',            tok);

            fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                body: fd
            })
                .then(function (r) {
                    return r.json().then(function (data) { return { ok: r.ok, data: data }; })
                        .catch(function () { return { ok: false, data: null }; });
                })
                .then(function (out) {
                    btnOk.disabled = false;
                    btnCancel.disabled = false;
                    if (!out.data) {
                        showMsg(L('errNetwork', 'Network error. Please try again.'), 'error');
                        return;
                    }
                    if (out.data.success) {
                        showMsg(out.data.message || L('ok', 'Stock added.'), 'success');
                        // Tell the rest of the app to drop any cached stock for this product
                        try {
                            if (window.takeposProductCache
                                && typeof window.takeposProductCache.invalidateAll === 'function') {
                                window.takeposProductCache.invalidateAll();
                            }
                        } catch (e) {}
                        // Brief delay so cashier sees the success message, then close + retry add-to-cart
                        var newStock = (typeof out.data.new_stock === 'number') ? out.data.new_stock : null;
                        var qtyAdded = (typeof out.data.qty_added === 'number') ? out.data.qty_added : qty;
                        setTimeout(function () {
                            close();
                            try { onSuccess(newStock, qtyAdded); } catch (e) { console.warn(e); }
                        }, 600);
                    } else {
                        showMsg(out.data.message || L('errGeneric', 'Could not add stock.'), 'error');
                    }
                })
                .catch(function () {
                    btnOk.disabled = false;
                    btnCancel.disabled = false;
                    showMsg(L('errNetwork', 'Network error. Please try again.'), 'error');
                });
        });
    };
})();