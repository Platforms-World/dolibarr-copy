/**
 * TakePOS — Category Dropdown + Favorites
 * Multi-level: L1 → L2 → L3 → Products
 */
(function () {
    'use strict';

    var LANG = /^ar/i.test(document.documentElement.lang || '') ? 'ar' : 'en';
    var T = LANG === 'ar' ? {
        all: 'الكل', search: 'بحث...', favorites: 'المفضلة',
        addFav: 'إضافة للمفضلة', removeFav: 'إزالة من المفضلة',
        addedToFav: 'أُضيف للمفضلة ❤️', removedFromFav: 'أُزيل من المفضلة'
    } : {
        all: 'All', search: 'Search...', favorites: 'Favorites',
        addFav: 'Add to favorites', removeFav: 'Remove from favorites',
        addedToFav: 'Added ❤️', removedFromFav: 'Removed'
    };

    var FAV_KEY = 'takepos_fav_products';
    var _favs = {};
    var _showFavs = false;

    function loadFavs() { try { _favs = JSON.parse(localStorage.getItem(FAV_KEY) || '{}'); } catch(e) { _favs = {}; } }
    function saveFavs() { try { localStorage.setItem(FAV_KEY, JSON.stringify(_favs)); } catch(e) {} }
    function isFav(pid) { return !!_favs[String(pid)]; }
    function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    function hideTile(t) { if (t) t.style.setProperty('display', 'none', 'important'); }
    function showTile(t) { if (t) t.style.removeProperty('display'); }

    function parseCatLabel(raw) {
        var s = String(raw||'').replace(/\u00A0/g,' ').trim();
        var icon = '📁', count = '';
        try { var em = s.match(/^([\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}])\s*/u); if (em) { icon = em[1]; s = s.slice(em[0].length); } } catch(e) {}
        var cm = s.match(/\s+(\d+)\s*$/); if (cm) { count = cm[1]; s = s.slice(0, s.length - cm[0].length); }
        return { icon: icon, name: s.trim() || raw, count: count };
    }

    /* ── Boot ── */
    function boot() {
        if (!document.body.classList.contains('takepos-v2')) { setTimeout(boot, 300); return; }
        var infoBar = document.querySelector('.tpv2-info-bar');
        if (!infoBar) { setTimeout(boot, 300); return; }
        if (document.getElementById('tpv2-cat-dropdown-btn')) return;
        loadFavs();
        buildDropdown(infoBar);
        buildFavoritesBtn(infoBar);
        observeGrid();
        setTimeout(addHeartButtons, 1000);
        injectCss();
    }
    document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', function(){ setTimeout(boot, 400); })
        : setTimeout(boot, 400);

    /* ── CSS ── */
    function injectCss() {
        var s = document.createElement('style');
        s.textContent = [
            '.tpv2-cat-item.has-children { }',
            '.tpv2-cat-arrow { font-size:15px; opacity:0.5; flex-shrink:0; margin-inline-start:auto; padding-inline-start:6px; }',
            '.tpv2-cat-back { font-weight:700 !important; color:var(--tp-primary) !important; border-bottom:1px solid var(--tp-border); margin-bottom:4px; padding-bottom:10px !important; }',
            '.tpv2-cat-breadcrumb { font-size:11px; color:var(--tp-text-muted); padding:6px 10px 2px; border-bottom:1px solid var(--tp-border); margin-bottom:4px; }',
        ].join('\n');
        document.head.appendChild(s);
    }

    /* ────────────────────────────────────────────────
       DROPDOWN
    ──────────────────────────────────────────────── */
    function buildDropdown(infoBar) {
        var btn = document.createElement('button');
        btn.type = 'button'; btn.id = 'tpv2-cat-dropdown-btn';
        btn.innerHTML = '<span class="tpv2-dd-icon">📦</span><span class="tpv2-dd-label">' + esc(T.all) + '</span><span class="tpv2-dd-chevron">▼</span>';

        var menu = document.createElement('div');
        menu.id = 'tpv2-cat-dropdown-menu';
        menu.innerHTML = '<div id="tpv2-cat-search"><input type="text" placeholder="' + esc(T.search) + '" autocomplete="off"></div><div id="tpv2-cat-list"></div>';
        document.body.appendChild(menu);
        infoBar.insertBefore(btn, infoBar.firstChild);

        showLevel(null, null); // show L1

        menu.querySelector('#tpv2-cat-search input').addEventListener('input', function(){ filterCatList(this.value); });

        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            menu.classList.contains('is-open') ? closeDropdown() : openDropdown();
        });
        document.addEventListener('click', closeDropdown);
        menu.addEventListener('click', function(e){ e.stopPropagation(); });
    }

    function openDropdown() {
        var btn  = document.getElementById('tpv2-cat-dropdown-btn');
        var menu = document.getElementById('tpv2-cat-dropdown-menu');
        if (!btn || !menu) return;
        menu.classList.add('is-open');
        btn.classList.add('is-open');
        var rect = btn.getBoundingClientRect();
        var mw = menu.offsetWidth || 260;
        var margin = 8;
        var isRtl = document.documentElement.dir === 'rtl' || document.body.dir === 'rtl';
        var left = isRtl ? (rect.right - mw) : rect.left;
        var maxLeft = window.innerWidth - mw - margin;
        if (left > maxLeft) left = maxLeft;
        if (left < margin) left = margin;
        menu.style.left = left + 'px';
        menu.style.right = 'auto';
        menu.style.top = (rect.bottom + 4) + 'px';
        var inp = menu.querySelector('input');
        if (inp) { inp.value = ''; filterCatList(''); setTimeout(function(){ inp.focus(); }, 40); }
    }

    function closeDropdown() {
        var menu = document.getElementById('tpv2-cat-dropdown-menu');
        var btn  = document.getElementById('tpv2-cat-dropdown-btn');
        if (menu) menu.classList.remove('is-open');
        if (btn)  btn.classList.remove('is-open');
    }

    /* ── Generic level renderer ── */
    function showLevel(parentId, parentChain) {
        var list = document.getElementById('tpv2-cat-list');
        if (!list) return;

        var allCats = getAllCats();
        var items = parentId === null
            ? allCats.filter(function(r){ return !r.fk_parent || parseInt(r.fk_parent) === 0; })
            : allCats.filter(function(r){ return String(r.fk_parent) === String(parentId); });

        var html = '';

        if (parentChain && parentChain.length > 0) {
            var parent = parentChain[parentChain.length - 1];
            html += '<div class="tpv2-cat-item tpv2-cat-back" data-back="1">'
                + '<span class="tpv2-cat-item-icon">←</span>'
                + '<span class="tpv2-cat-item-name">' + esc(parent.name) + '</span>'
                + '</div>';
        }

        if (parentId === null) {
            var total = window.takeposTotalProductsCount || 0;
            html += '<div class="tpv2-cat-item is-active" data-all="1">'
                + '<span class="tpv2-cat-item-icon">📦</span>'
                + '<span class="tpv2-cat-item-name">' + esc(T.all) + '</span>'
                + '<span class="tpv2-cat-item-count">' + total + '</span>'
                + '</div>';
        }

        items.forEach(function(row) {
            if (!row || !row.label) return;
            var p = parseCatLabel(row.label);
            var id = String(row.rowid || row.id || '');
            var hasSubs = allCats.some(function(r){ return String(r.fk_parent) === id; });
            html += '<div class="tpv2-cat-item' + (hasSubs ? ' has-children' : '') + '" data-id="' + esc(id) + '" data-name="' + esc(p.name) + '" data-icon="' + esc(p.icon) + '">'
                + '<span class="tpv2-cat-item-icon">' + esc(p.icon) + '</span>'
                + '<span class="tpv2-cat-item-name">' + esc(p.name) + '</span>'
                + (p.count ? '<span class="tpv2-cat-item-count">' + p.count + '</span>' : '')
                + (hasSubs ? '<span class="tpv2-cat-arrow">›</span>' : '')
                + '</div>';
        });

        list.innerHTML = html;

        var backBtn = list.querySelector('.tpv2-cat-back');
        if (backBtn) {
            backBtn.addEventListener('click', function() {
                var prev = parentChain.slice(0, -1);
                var prevParentId = prev.length > 0 ? prev[prev.length - 1].id : null;
                showLevel(prevParentId, prev);
            });
        }

        var allBtn = list.querySelector('[data-all]');
        if (allBtn) {
            allBtn.addEventListener('click', function() {
                closeDropdown();
                _showFavs = false; updateFavBtn();
                updateBtn('📦', T.all);
                loadAllProducts();
            });
        }

        list.querySelectorAll('.tpv2-cat-item:not(.tpv2-cat-back):not([data-all])').forEach(function(item) {
            item.addEventListener('click', function() {
                var catId = item.getAttribute('data-id');
                var name  = item.getAttribute('data-name') || '';
                var icon  = item.getAttribute('data-icon') || '📁';
                var allCatsLocal = getAllCats();
                var hasSubs = allCatsLocal.some(function(r){ return String(r.fk_parent) === String(catId); });

                if (hasSubs) {
                    var newChain = (parentChain || []).concat([{id: catId, name: name, icon: icon}]);
                    showLevel(catId, newChain);
                } else {
                    closeDropdown();
                    _showFavs = false; updateFavBtn();
                    updateBtn(icon, name);
                    loadCategory(parseInt(catId, 10));
                }
            });
        });
    }

    /* ── Helpers ── */
    function getAllCats() {
        var main = window.categories ? (Array.isArray(window.categories) ? window.categories : Object.values(window.categories)) : [];
        var sub  = window.subcategories ? (Array.isArray(window.subcategories) ? window.subcategories : Object.values(window.subcategories)) : [];
        return main.concat(sub);
    }

    function updateBtn(icon, name) {
        var btn = document.getElementById('tpv2-cat-dropdown-btn');
        if (!btn) return;
        btn.querySelector('.tpv2-dd-icon').textContent = icon;
        btn.querySelector('.tpv2-dd-label').textContent = name;
    }

    function filterCatList(q) {
        var list = document.getElementById('tpv2-cat-list'); if (!list) return;
        var lq = q.toLowerCase();
        list.querySelectorAll('.tpv2-cat-item').forEach(function(item) {
            var name = (item.getAttribute('data-name') || '').toLowerCase();
            item.style.display = (!q || item.hasAttribute('data-all') || item.hasAttribute('data-back') || name.indexOf(lq) !== -1) ? '' : 'none';
        });
    }

    function loadAllProducts() {
        window.takeposAllProductsMode = true;
        try {
            var el = document.getElementById('catdiv0');
            if (el) el.setAttribute('data-rowid', '0');
            if (window.jQuery) { jQuery('#catdiv0').attr('data-rowid', '0'); jQuery('#catdiv0').data('rowid', 0); }
        } catch(e){}
        if (typeof window.LoadProducts === 'function') window.LoadProducts(0);
    }

    function loadCategory(catId) {
        window.takeposAllProductsMode = false;
        window._takeposForceProductReload = true;
        try { if(window.takeposProductCache) window.takeposProductCache.invalidateAll(); } catch(e){}
        // FIX: jQuery .data() and DOM setAttribute are separate stores.
        // LoadProducts reads via jQuery .data('rowid') — must sync both with .attr() first.
        try {
            var el = document.getElementById('catdiv0');
            if (el) el.setAttribute('data-rowid', String(catId));
            if (window.jQuery) { jQuery('#catdiv0').attr('data-rowid', String(catId)); jQuery('#catdiv0').data('rowid', catId); }
        } catch(e){}
        if (typeof window.LoadProducts === 'function') window.LoadProducts(0);
    }

    /* ────────────────────────────────────────────────
       FAVORITES BUTTON
    ──────────────────────────────────────────────── */
    function buildFavoritesBtn(infoBar) {
        var btn = document.createElement('button');
        btn.type = 'button'; btn.id = 'tpv2-favorites-btn';
        btn.innerHTML = '⭐ ' + esc(T.favorites);
        var ddBtn = document.getElementById('tpv2-cat-dropdown-btn');
        infoBar.insertBefore(btn, ddBtn ? ddBtn.nextSibling : infoBar.firstChild);
        btn.addEventListener('click', function() {
            _showFavs = !_showFavs;
            updateFavBtn();
            if (_showFavs) { loadFavs(); applyFavFilter(); }
            else { clearFavFilter(); loadAllProducts(); }
        });
    }

    function updateFavBtn() {
        var btn = document.getElementById('tpv2-favorites-btn');
        if (btn) btn.classList.toggle('is-active', _showFavs);
    }

    /* ── Fav Filter ── */
    function applyFavFilter() {
        if (!_showFavs) return;
        loadFavs();
        var favIds = Object.keys(_favs);
        if (favIds.length === 0) { _renderFavTiles([]); return; }
        var baseUrl = (window.location.pathname.replace(/\/takepos\/index\.php.*/, '') || '') + '/takepos/ajax/ajax.php';
        var token = window.CSRF_TOKEN || window.takeposCsrfToken || '';
        var thirdpartyid = (document.getElementById('thirdpartyid') || {}).value || 0;
        var url = baseUrl + '?action=getFavoriteProducts&token=' + encodeURIComponent(token)
            + '&thirdpartyid=' + encodeURIComponent(thirdpartyid)
            + '&ids=' + encodeURIComponent(favIds.join(','));
        fetch(url, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) { _renderFavTiles(data || []); })
            .catch(function() { _renderFavTiles([]); });
    }

    function _renderFavTiles(products) {
        var div5 = document.querySelector('#takepos-main-layout .div5');
        if (!div5) return;
        var tiles = div5.querySelectorAll('.wrapper2:not(.arrow)');
        tiles.forEach(function(tile) {
            tile.setAttribute('data-rowid', ''); tile.setAttribute('data-iscat', '0');
            tile.classList.add('divempty'); tile.classList.remove('is-fav-tile');
            tile.style.removeProperty('display');
            var desc = tile.querySelector('.description_content, [id^="prodesc"]'); if (desc) desc.textContent = '';
            var img = tile.querySelector('img[id^="proimg"]'); if (img) { img.src = 'genimg/empty.png'; img.title = ''; }
            var price = tile.querySelector('[id^="proprice"]'); if (price) { price.className = 'hidden'; price.innerHTML = ''; }
            var btn = tile.querySelector('[id^="probutton"]'); if (btn) { btn.textContent = ''; btn.style.display = 'none'; }
            var descDiv = tile.querySelector('[id^="prodivdesc"]'); if (descDiv) descDiv.style.display = 'none';
            var heart = tile.querySelector('.tpv2-fav-heart-btn'); if (heart) heart.remove();
        });
        if (products.length === 0) return;
        var tileEls = Array.prototype.slice.call(tiles);
        products.forEach(function(prod, i) {
            if (i >= tileEls.length) return;
            var tile = tileEls[i];
            var pid = String(prod.rowid || prod.id || ''); if (!pid) return;
            tile.setAttribute('data-rowid', pid); tile.setAttribute('data-iscat', '0'); tile.classList.remove('divempty');
            var price = tile.querySelector('[id^="proprice"]'); if (price) { price.className = 'productprice'; price.innerHTML = prod.price_ttc_formated || prod.price_formated || ''; }
            var img = tile.querySelector('img[id^="proimg"]'); if (img) { img.src = prod.img || prod.image_url || 'genimg/empty.png'; img.title = prod.ref || ''; }
            var btnEl = tile.querySelector('[id^="probutton"]'); if (btnEl) { btnEl.textContent = prod.label || ''; btnEl.style.display = ''; }
            var descDiv = tile.querySelector('[id^="prodivdesc"]'); if (descDiv) descDiv.style.display = '';
            var desc = tile.querySelector('.description_content, [id^="prodesc"]'); if (desc) desc.textContent = prod.label || '';
            var heart = document.createElement('button');
            heart.type = 'button'; heart.className = 'tpv2-fav-heart-btn is-fav'; heart.textContent = '❤️'; heart.title = T.removeFav;
            heart.addEventListener('click', heartClickHandler);
            tile.appendChild(heart);
        });
    }

    function clearFavFilter() {
        var div5 = document.querySelector('#takepos-main-layout .div5'); if (!div5) return;
        div5.querySelectorAll('.wrapper2').forEach(function(tile) { showTile(tile); });
        div5.querySelectorAll('.tpv2-fav-remove-btn').forEach(function(b){ b.remove(); });
    }

    /* ── Hearts ── */
    function heartClickHandler(e) {
        e.stopPropagation(); e.preventDefault();
        var heart = e.currentTarget;
        var tile = heart.closest('.wrapper2'); if (!tile) return;
        var pid = String(tile.getAttribute('data-rowid') || ''); if (!pid) return;
        var label = ''; try { label = tile.querySelector('.description_content').textContent.trim(); } catch(ex){}
        if (isFav(pid)) {
            delete _favs[String(pid)]; saveFavs();
            heart.textContent = '🤍'; heart.classList.remove('is-fav'); heart.title = T.addFav;
            showToast(T.removedFromFav, false);
            if (_showFavs) hideTile(tile);
        } else {
            _favs[String(pid)] = label; saveFavs();
            heart.textContent = '❤️'; heart.classList.add('is-fav'); heart.title = T.removeFav;
            heart.classList.add('tpv2-heart-pop');
            setTimeout(function(){ heart.classList.remove('tpv2-heart-pop'); }, 400);
            showToast(T.addedToFav, true);
        }
    }

    function addHeartButtons() {
        var div5 = document.querySelector('#takepos-main-layout .div5'); if (!div5) return;
        div5.querySelectorAll('.wrapper2:not(.arrow):not(.divempty)').forEach(function(tile) {
            var pid = String(tile.getAttribute('data-rowid') || ''); if (!pid) return;
            if (tile.getAttribute('data-iscat') === '1') { var stale = tile.querySelector('.tpv2-fav-heart-btn'); if (stale) stale.remove(); return; }
            var existing = tile.querySelector('.tpv2-fav-heart-btn');
            if (existing) { existing.textContent = isFav(pid) ? '❤️' : '🤍'; existing.classList.toggle('is-fav', isFav(pid)); existing.title = isFav(pid) ? T.removeFav : T.addFav; return; }
            var heart = document.createElement('button');
            heart.type = 'button'; heart.className = 'tpv2-fav-heart-btn' + (isFav(pid) ? ' is-fav' : '');
            heart.textContent = isFav(pid) ? '❤️' : '🤍'; heart.title = isFav(pid) ? T.removeFav : T.addFav;
            heart.addEventListener('click', heartClickHandler);
            tile.appendChild(heart);
        });
    }

    function observeGrid() {
        var div5 = document.querySelector('#takepos-main-layout .div5'); if (!div5 || typeof MutationObserver === 'undefined') return;
        var busy = false;
        new MutationObserver(function() { if (busy) return; busy = true; setTimeout(function() { addHeartButtons(); busy = false; }, 300); }).observe(div5, { childList: true, subtree: true });
    }

    function showToast(msg, isAdd) {
        var el = document.getElementById('tpv2-fav-toast');
        if (!el) { el = document.createElement('div'); el.id = 'tpv2-fav-toast'; document.body.appendChild(el); }
        el.textContent = msg; el.className = 'tpv2-fav-toast ' + (isAdd ? 'is-add' : 'is-remove');
        clearTimeout(el._t);
        setTimeout(function(){ el.classList.add('is-show'); }, 10);
        el._t = setTimeout(function(){ el.classList.remove('is-show'); }, 2200);
    }

})();