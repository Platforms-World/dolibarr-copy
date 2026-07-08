/**
 * takepos_stock_badges.js
 *
 * FIX (stock-branch-v9 / v10):
 * Adds stock quantity badges and expiry date warnings to product tiles.
 *
 * FIX (stock-badge-loop-v1): Prevent infinite MutationObserver loop.
 * Root cause: injecting badge DOM nodes fires childList mutations on the grid
 * -> observer fires -> renderBadges() -> injects nodes -> loop.
 *
 * Fix strategy (3 layers of protection):
 *  1. _rendering flag: if a render is already in flight, ignore new mutations.
 *  2. pauseObserver/resumeObserver: disconnect before DOM writes, reconnect after.
 *  3. data-tp-badge-stamp attribute: tiles already stamped with current rowid
 *     are skipped entirely, so repeat calls are no-ops.
 */
(function () {
    'use strict';

    var BADGE_CLASSNAME  = 'tp-stock-badge-wrap';
    var BADGE_STAMP_ATTR = 'data-tp-badge-stamp';
    var DEBOUNCE_MS      = 400;
    var EXPIRY_WARN_DAYS = window.takeposExpiryWarnDays || 30;
    var EXPIRY_CRIT_DAYS = window.takeposExpiryCritDays || 7;
    var BATCH_ENABLED    = window.takeposBatchEnabled   || false;

    // Styles
    function injectStyles() {
        if (document.getElementById('tp-stock-badge-styles')) return;
        var s = document.createElement('style');
        s.id = 'tp-stock-badge-styles';
        s.textContent = [
            /* wrapper2 overflow visible */
            'html body.bodytakepos .div5 .wrapper2{overflow:visible!important}',
            /* حاوية الشارات في أسفل البطاقة */
            '.tp-stock-badge-wrap{position:absolute;bottom:6px;left:6px;right:6px;',
            'display:flex;flex-direction:row;flex-wrap:wrap;gap:4px;',
            'pointer-events:none;z-index:6}',
            /* شارة مشتركة */
            '.tp-stock-badge{display:inline-flex;align-items:center;',
            'padding:3px 8px;border-radius:20px;',
            'font-size:9.5px;font-weight:700;line-height:1.4;',
            'white-space:nowrap;box-shadow:0 1px 4px rgba(0,0,0,.3)}',
            /* Stock */
            '.tp-stock-ok{background:#059669;color:#fff}',
            '.tp-stock-low{background:#d97706;color:#fff}',
            '.tp-stock-out{background:#dc2626;color:#fff}',
            '.tp-stock-na{background:#6b7280;color:#fff}',
            /* Expiry */
            '.tp-expiry-ok{background:#6366f1;color:#fff}',
            '.tp-expiry-warn{background:#ea580c;color:#fff;animation:tp-blink 1.5s ease-in-out infinite}',
            '.tp-expiry-crit{background:#dc2626;color:#fff;animation:tp-blink .7s ease-in-out infinite}',
            '.tp-expiry-exp{background:#7f1d1d;color:#fff;animation:tp-blink .4s ease-in-out infinite}',
            '@keyframes tp-blink{0%,100%{opacity:1}50%{opacity:.55}}'
        ].join('');
        document.head.appendChild(s);
    }

    function stockClass(qty, thr) {
        thr = thr > 0 ? thr : 5;
        if (qty === null || qty === undefined) return 'tp-stock-na';
        if (qty <= 0)   return 'tp-stock-out';
        if (qty <= thr) return 'tp-stock-low';
        return 'tp-stock-ok';
    }
    function stockLabel(qty) {
        var label = window.takeposStockBadgeQtyLabel || '';
        if (qty === null || qty === undefined) return '? ' + label;
        return (Math.round(qty * 100) / 100) + (label ? ' ' + label : '');
    }
    function expiryClass(d) {
        if (!d) return null;
        var today = new Date(); today.setHours(0,0,0,0);
        var exp = new Date(d); exp.setHours(0,0,0,0);
        var diff = Math.floor((exp - today) / 86400000);
        if (diff < 0)                 return 'tp-expiry-exp';
        if (diff <= EXPIRY_CRIT_DAYS) return 'tp-expiry-crit';
        if (diff <= EXPIRY_WARN_DAYS) return 'tp-expiry-warn';
        return 'tp-expiry-ok';
    }
    function formatDate(d) {
        var p = d ? d.split('-') : [];
        return p.length === 3 ? p[2]+'/'+p[1]+'/'+p[0].slice(2) : (d || '');
    }

    // Observer state
    var _obs          = null;
    var _grid         = null;
    var _pendingTimer = null;
    var _rendering    = false;

    function pauseObserver()  { if (_obs) _obs.disconnect(); }
    function resumeObserver() {
        if (_obs && _grid) {
            _obs.observe(_grid, {
                childList: true, subtree: true,
                attributes: true, attributeFilter: ['data-rowid']
            });
        }
    }

    function scheduleRefresh() {
        if (_rendering) return;
        clearTimeout(_pendingTimer);
        _pendingTimer = setTimeout(renderBadges, DEBOUNCE_MS);
    }

    function renderBadges() {
        var endpoint = window.takeposStockBadgesEndpoint;
        if (!endpoint || _rendering) return;

        // Find tiles that need a badge (no stamp, or stamp doesn't match current rowid)
        var tiles = document.querySelectorAll('.wrapper2[data-rowid]');
        var idMap = {};
        tiles.forEach(function (tile) {
            var rowid = parseInt(tile.getAttribute('data-rowid'), 10);
            if (!rowid || tile.classList.contains('arrow')) return;
            if (tile.getAttribute(BADGE_STAMP_ATTR) === String(rowid)) return; // already current
            if (!idMap[rowid]) idMap[rowid] = [];
            idMap[rowid].push(tile);
        });

        var ids = Object.keys(idMap);
        if (!ids.length) return;

        // --- SET flag and PAUSE observer BEFORE touching DOM ---
        _rendering = true;
        pauseObserver();

        // Remove stale badges on tiles we're about to update
        ids.forEach(function (pid) {
            idMap[pid].forEach(function (tile) {
                var old = tile.querySelector('.' + BADGE_CLASSNAME);
                if (old) old.remove();
            });
        });

        fetch(endpoint + '?product_ids=' + ids.join(','), { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data) return;
                ids.forEach(function (pid) {
                    var info     = data[pid];
                    var tileList = idMap[pid];
                    if (!tileList) return;
                    var qty       = info ? info.qty       : null;
                    var threshold = info ? (info.threshold || 0) : 0;
                    var expiry    = info ? info.expiry : null;

                    tileList.forEach(function (tile) {
                        var wrap = document.createElement('div');
                        wrap.className = BADGE_CLASSNAME;

                        var qtyBadge = document.createElement('span');
                        qtyBadge.className = 'tp-stock-badge ' + stockClass(qty, threshold);
                        qtyBadge.textContent = stockLabel(qty);
                        qtyBadge.title = window.takeposStockBadgeTitle || 'Stock';
                        wrap.appendChild(qtyBadge);

                        if (expiry) {
                            var ec = expiryClass(expiry);
                            if (ec) {
                                var expBadge = document.createElement('span');
                                expBadge.className = 'tp-stock-badge ' + ec;
                                var _t = new Date(); _t.setHours(0,0,0,0);
                                var _e = new Date(expiry); _e.setHours(0,0,0,0);
                                var _d = Math.floor((_e - _t) / 86400000);
                                var _txt;
                                if (_d < 0)       _txt = '\u26d4 Expired';
                                else if (_d === 0) _txt = '\ud83d\udd34 Today';
                                else if (_d <= EXPIRY_CRIT_DAYS) _txt = '\ud83d\udd34 ' + _d + 'd left';
                                else               _txt = '\u26a0 ' + _d + 'd left';
                                expBadge.textContent = _txt;
                                expBadge.title = (window.takeposExpiryLabel || 'Expiry') + ': ' + expiry;
                                wrap.appendChild(expBadge);
                            }
                        }

                        // DOM write (observer is paused — safe)
                        tile.appendChild(wrap);
                        // Stamp the tile so we don't re-process it next cycle
                        tile.setAttribute(BADGE_STAMP_ATTR, String(pid));
                    });
                });
            })
            .catch(function () { /* network error — silently ignore */ })
            .finally(function () {
                // --- CLEAR flag and RESUME observer AFTER all DOM writes ---
                _rendering = false;
                resumeObserver();
            });
    }

    function startObserver() {
        _grid = document.getElementById('div_products_list')
            || document.querySelector('.div5')
            || document.querySelector('[id^=div_products]');

        if (!_grid) { setTimeout(startObserver, 500); return; }

        _obs = new MutationObserver(function (mutations) {
            // Layer 1: ignore mutations we caused ourselves
            if (_rendering) return;

            // Layer 2: only react to rowid attribute changes or new wrapper2 tiles
            // (NOT to badge node insertions inside existing tiles)
            var relevant = mutations.some(function (m) {
                if (m.type === 'attributes' && m.attributeName === 'data-rowid') return true;
                if (m.type === 'childList') {
                    // Ignore mutations whose target is inside a badge we own
                    var t = m.target;
                    if (t.classList && (
                        t.classList.contains(BADGE_CLASSNAME) ||
                        t.classList.contains('tp-stock-badge')
                    )) return false;
                    if (t.parentElement && t.parentElement.classList &&
                        t.parentElement.classList.contains(BADGE_CLASSNAME)) return false;
                    // Relevant if target is the grid or a wrapper2 tile
                    if (t === _grid) return true;
                    if (t.classList && t.classList.contains('wrapper2')) return true;
                    // Or if new wrapper2 nodes were added (category switch)
                    return Array.from(m.addedNodes).some(function (n) {
                        return n.nodeType === 1 && n.classList && n.classList.contains('wrapper2');
                    });
                }
                return false;
            });

            if (relevant) scheduleRefresh();
        });

        resumeObserver();
        scheduleRefresh(); // initial pass
    }

    function init() {
        if (!window.takeposStockBadgesEnabled) return;
        injectStyles();
        startObserver();
    }

    // ─────────────────────────────────────────────────────────────────────
    // FIX (stock-badge-refresh-v1): Public API to invalidate the badge cache
    // and force a refresh.
    //
    // Background:
    //   Each tile is stamped with data-tp-badge-stamp=<rowid> after its badge
    //   is drawn (line 149 above). Subsequent renderBadges() calls skip any
    //   tile whose stamp already matches its rowid (line 93). That means
    //   after a sale completes, the stock badge on the same product tile
    //   never refreshes — it keeps showing the pre-sale quantity even
    //   though llx_product_stock has been updated.
    //
    // Fix:
    //   Expose window.takeposRefreshStockBadges() that strips every stamp
    //   and triggers a render. index.php calls this from
    //   TakeposFinalizePaymentUi() right after a successful payment, so
    //   badges reflect the new stock immediately.
    // ─────────────────────────────────────────────────────────────────────
    function refreshStockBadges() {
        try {
            // Drop every stamp so renderBadges() will reprocess every tile.
            var stamped = document.querySelectorAll('[' + BADGE_STAMP_ATTR + ']');
            stamped.forEach(function (tile) {
                tile.removeAttribute(BADGE_STAMP_ATTR);
            });
            // Force an immediate render (bypass the debounce so the user sees
            // the new number as soon as the cart finalizes).
            if (_rendering) {
                // A render is already in flight — let it finish, then re-run.
                clearTimeout(_pendingTimer);
                _pendingTimer = setTimeout(refreshStockBadges, DEBOUNCE_MS);
                return;
            }
            clearTimeout(_pendingTimer);
            renderBadges();
        } catch (e) {
            // Never throw out of a payment-finalize callback.
            try { console.error('takeposRefreshStockBadges failed', e); } catch (_) {}
        }
    }
    window.takeposRefreshStockBadges = refreshStockBadges;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();