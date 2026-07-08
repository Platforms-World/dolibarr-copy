/**
 * TakePOS V2 — Custom Category Strip + Top Info Bar (v5)
 * -----------------------------------------------------------------------------
 * This version REPLACES the visible category strip entirely with one we build
 * ourselves from window.categories. The original .div4 pills remain in the DOM
 * (hidden) so Dolibarr's LoadProducts(N) keeps working — we just route clicks
 * through them.
 *
 * Adds:
 *   - Visible custom strip with each category showing: emoji + name + count
 *   - Info bar: breadcrumb + view-mode toggle (Grid/List actually working) +
 *     "Showing X of Y" counter
 *   - All built from window.categories so it works even if the original strip
 *     fails to render
 * =========================================================================== */
(function () {
    'use strict';

    /* i18n */
    function detectLang() {
        var d = document.documentElement;
        var dir = (d.getAttribute('dir') || '').toLowerCase();
        var lang = (d.getAttribute('lang') || '').toLowerCase();
        if (dir === 'rtl' || lang.indexOf('ar') === 0 || lang.indexOf('he') === 0 || lang.indexOf('fa') === 0) return 'ar';
        return 'en';
    }
    var LANG = detectLang();
    var T = (LANG === 'ar') ? {
        catalog: 'الكتالوج', allProducts: 'جميع المنتجات',
        showing: 'المعروض', of: 'من',
        gridView: 'عرض شبكي', listView: 'عرض قائمة', all: 'الكل',
        prevPage: 'الصفحة السابقة', nextPage: 'الصفحة التالية', page: 'صفحة'
    } : {
        catalog: 'Catalog', allProducts: 'All products',
        showing: 'Showing', of: 'of',
        gridView: 'Grid view', listView: 'List view', all: 'All',
        prevPage: 'Previous page', nextPage: 'Next page', page: 'Page'
    };

    /* Boot */
    function boot() {
        var div4 = document.querySelector('#takepos-main-layout .div4');
        var div5 = document.querySelector('#takepos-main-layout .div5');
        if (!div4 || !div5) { setTimeout(boot, 300); return; }
        if (document.querySelector('.tpv2-info-bar')) return;
        if (!document.body.classList.contains('takepos-v2')) return;

        injectInfoBar(div4);
        injectCustomStrip(div4);
        watchProductGrid(div5);

        // Initial counter render + retries while data loads
        updateCounter(div5);
        var pings = 0;
        var pinger = setInterval(function () {
            updateCounter(div5);
            rebuildCustomStripIfNeeded(div4);
            if (++pings > 24) clearInterval(pinger);
        }, 250);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { setTimeout(boot, 250); });
    } else { setTimeout(boot, 250); }

    /* ================================================================== */
    /* 1. INFO BAR with WORKING view toggle                                */
    /* ================================================================== */
    function injectInfoBar(div4) {
        var bar = document.createElement('div');
        bar.className = 'tpv2-info-bar';
        bar.innerHTML =
            '<div class="tpv2-breadcrumb">' +
            '<span class="tpv2-bc-home" aria-hidden="true">🏠</span>' +
            '<span>' + escapeHtml(T.catalog) + '</span>' +
            '<span class="tpv2-bc-sep" aria-hidden="true">›</span>' +
            '<span class="tpv2-bc-current" id="tpv2-bc-current">' + escapeHtml(T.allProducts) + '</span>' +
            '</div>' +
            '<div class="tpv2-info-spacer"></div>' +
            '<div class="tpv2-view-toggle" role="group">' +
            '<button type="button" class="tpv2-view-btn is-active" data-tpv2-view="grid" title="' + escapeHtml(T.gridView) + '" aria-label="' + escapeHtml(T.gridView) + '"><span aria-hidden="true">▦</span></button>' +
            '<button type="button" class="tpv2-view-btn" data-tpv2-view="list" title="' + escapeHtml(T.listView) + '" aria-label="' + escapeHtml(T.listView) + '"><span aria-hidden="true">☰</span></button>' +
            '</div>' +
            '<div class="tpv2-counter" id="tpv2-counter" dir="rtl">' +
            '<span>' + escapeHtml(T.showing) + ':</span>' +
            '<span class="tpv2-counter-num" id="tpv2-counter-shown">0</span>' +
            '<span class="tpv2-counter-sep">' + escapeHtml(T.of) + '</span>' +
            '<span class="tpv2-counter-num" id="tpv2-counter-total">0</span>' +
            '</div>';

        var parent = div4.parentNode;
        if (!parent) return;
        parent.insertBefore(bar, div4);

        // ── PAGINATION ROW ──────────────────────────────────────────────
        // Inject a visible Prev / Page X of Y / Next bar below the product grid
        var paginationRow = document.createElement('div');
        paginationRow.id  = 'tpv2-pagination-row';
        paginationRow.className = 'tpv2-pagination-row';
        // FIX (I14): Swap arrow icons in RTL so Prev/Next point the correct direction.
        // In Arabic (RTL) 'previous page' is visually to the RIGHT — arrow points left.
        var _rtl = document.body.classList.contains('tp-rtl') ||
                   document.body.getAttribute('dir') === 'rtl' ||
                   document.documentElement.getAttribute('dir') === 'rtl';
        var _prevIcon = _rtl ? 'fa-chevron-left'  : 'fa-chevron-right';
        var _nextIcon = _rtl ? 'fa-chevron-right' : 'fa-chevron-left';

        paginationRow.innerHTML =
            '<button type="button" id="tpv2-page-prev" class="tpv2-page-btn" title="' + escapeHtml(T.prevPage || 'السابق') + '">' +
            '<span class="fa ' + _prevIcon + '"></span>' +
            '</button>' +
            '<span class="tpv2-page-info" id="tpv2-page-info">1 / 1</span>' +
            '<button type="button" id="tpv2-page-next" class="tpv2-page-btn tpv2-page-btn-next" title="' + escapeHtml(T.nextPage || 'التالي') + '">' +
            '<span class="fa ' + _nextIcon + '"></span>' +
            '</button>';

        // Append to body since it's position:fixed
        document.body.appendChild(paginationRow);

        // Wire up buttons AFTER insertion so elements exist
        var prevBtn2 = document.getElementById('tpv2-page-prev');
        var nextBtn2 = document.getElementById('tpv2-page-next');

        // Simple direct calls - MoreProducts is a true global var (declared with var at page scope)
        function _safeMoreProducts(direction) {
            try {
                // currentcat may be undefined if page just loaded and no category was clicked
                // Fix: ensure it's set by reading from catdiv0 or defaulting to 0
                if (typeof currentcat === 'undefined' || currentcat === undefined || currentcat === null) { // eslint-disable-line
                    try {
                        var cd = document.getElementById('catdiv0');
                        if (cd) {
                            var rId = cd.getAttribute('data-rowid') || jQuery('#catdiv0').data('rowid'); // eslint-disable-line
                            currentcat = rId || 0; // eslint-disable-line
                        } else {
                            currentcat = 0; // eslint-disable-line
                        }
                    } catch(ex) { currentcat = 0; } // eslint-disable-line
                }
                // FIX: Restore takeposAllProductsMode for All view pagination
                // so MoreProducts offset calculation is correct
                try {
                    var cc = (typeof currentcat !== 'undefined') ? currentcat : null; // eslint-disable-line
                    window.takeposAllProductsMode = (cc === 0 || cc === '0');
                } catch(ex) {}
                MoreProducts(direction); // eslint-disable-line
            } catch(e) {
                console.warn('[tpv2-pagination] MoreProducts failed:', e);
            }
        }

        if (prevBtn2) {
            prevBtn2.addEventListener('click', function () {
                _safeMoreProducts('less');
            });
        }
        if (nextBtn2) {
            nextBtn2.addEventListener('click', function () {
                _safeMoreProducts('more');
            });
        }

        // Update counter by reading window.pageproducts (var at script scope = global)
        function _refreshPagination() {
            var p = (typeof pageproducts !== 'undefined') ? pageproducts : // eslint-disable-line
                (typeof window.pageproducts === 'number' ? window.pageproducts : 0);
            if (typeof window.tpv2UpdatePagination === 'function') {
                window.tpv2UpdatePagination(p);
            }
        }

        // Watch product grid attribute changes (data-rowid) to refresh pagination
        // Uses subtree:true + attributeFilter so it fires when LoadProducts sets
        // data-rowid on wrapper2 tiles after AJAX completes
        var _div5Watch = document.querySelector('#takepos-main-layout .div5');
        if (_div5Watch && typeof MutationObserver !== 'undefined') {
            new MutationObserver(function() {
                setTimeout(_refreshPagination, 200);
            }).observe(_div5Watch, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-rowid', 'class'] });
        }

        // Also reset counter when category is clicked
        document.addEventListener('click', function(e) {
            if (e.target && e.target.closest && e.target.closest('.div4 .wrapper')) {
                setTimeout(_refreshPagination, 400);
            }
        });

        // Update pagination info - reads pageproducts directly from global scope
        // FIX (pagination-v2 + empty-category-v5): Read the total count from the
        // currently-active pill's badge AT CALL TIME. If a pill IS active, its
        // count is the truth — including 0 (empty category). Only fall through
        // to the global total when no pill is resolved at all (very first render).
        window.tpv2UpdatePagination = function (currentPageNum) {
            var pageInfo = document.getElementById('tpv2-page-info');
            var prevBtn  = document.getElementById('tpv2-page-prev');
            var nextBtn  = document.getElementById('tpv2-page-next');
            if (!pageInfo) return;

            // perPage = MAXPRODUCT-2 = actual tiles rendered per page
            var perPage = (typeof window.takeposMaxProductsPerPage === 'number'
                && window.takeposMaxProductsPerPage > 0)
                ? window.takeposMaxProductsPerPage : 9;

            // PRIMARY: read total from the active pill in the custom strip.
            var total = 0;
            var pillResolved = false;
            try {
                var activePill = document.querySelector('#tpv2-custom-strip .tpv2-pill-active');
                if (activePill) {
                    var pillCountEl = activePill.querySelector('.tpv2-pill-count');
                    // Even a missing badge means count=0 (empty category) — trust the pill.
                    total = pillCountEl ? (parseInt(pillCountEl.textContent, 10) || 0) : 0;
                    pillResolved = true;
                }
            } catch (e) {}

            // FALLBACKS: only if no pill is resolved (first paint, before any click)
            if (!pillResolved) {
                try {
                    var fallback = getTotal();
                    if (typeof fallback === 'number' && fallback > 0) total = fallback;
                } catch (e) {}
                if (total <= 0
                    && typeof window.takeposTotalProductsCount === 'number'
                    && window.takeposTotalProductsCount > 0) {
                    total = window.takeposTotalProductsCount;
                }
            }

            var current = 0;
            if (typeof currentPageNum === 'number') {
                current = currentPageNum;
            } else {
                try { current = pageproducts || 0; } catch(e) {} // eslint-disable-line
            }

            var totalPages = total > 0 ? Math.ceil(total / perPage) : 1;
            if (totalPages < 1) totalPages = 1;
            var currentPage = current + 1;

            pageInfo.textContent = currentPage + ' / ' + totalPages;

            if (prevBtn) prevBtn.disabled = (current <= 0);
            if (nextBtn) nextBtn.disabled = (currentPage >= totalPages);

            if (paginationRow) {
                paginationRow.style.display = totalPages > 1 ? '' : 'none';
            }
        };

        // Initial render - try multiple times to ensure page is ready
        setTimeout(function() { window.tpv2UpdatePagination(); }, 600);
        setTimeout(function() { window.tpv2UpdatePagination(); }, 1500);
        setTimeout(function() { window.tpv2UpdatePagination(); }, 3000);

        bar.querySelectorAll('.tpv2-view-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var mode = btn.getAttribute('data-tpv2-view');
                bar.querySelectorAll('.tpv2-view-btn').forEach(function (b) {
                    b.classList.toggle('is-active', b === btn);
                });
                applyViewMode(mode);
            });
        });

        // Restore saved view
        try {
            var saved = localStorage.getItem('tpv2_view_mode');
            if (saved === 'list') {
                setTimeout(function () {
                    var btn = bar.querySelector('.tpv2-view-btn[data-tpv2-view="list"]');
                    if (btn) btn.click();
                }, 200);
            }
        } catch (e) {}
    }

    function applyViewMode(mode) {
        var div5 = document.querySelector('#takepos-main-layout .div5');
        if (!div5) return;
        if (mode === 'list') div5.classList.add('tpv2-list-view');
        else div5.classList.remove('tpv2-list-view');
        try { localStorage.setItem('tpv2_view_mode', mode); } catch (e) {}
    }

    /* ================================================================== */
    /* 2. CUSTOM CATEGORY STRIP — built from window.categories             */
    /* ================================================================== */
    function injectCustomStrip(div4) {
        // Hide the original strip
        div4.classList.add('tpv2-strip-hidden');

        // Create our strip as a sibling AFTER .div4
        var strip = document.createElement('div');
        strip.className = 'tpv2-custom-strip';
        strip.id = 'tpv2-custom-strip';

        var parent = div4.parentNode;
        if (!parent) return;
        parent.insertBefore(strip, div4.nextSibling);

        rebuildCustomStrip(strip);
    }

    function rebuildCustomStripIfNeeded(div4) {
        var strip = document.getElementById('tpv2-custom-strip');
        if (!strip) return;
        // Rebuild if categories now have data and our strip is empty
        var cats = window.categories;
        var hasData = cats && (Array.isArray(cats) ? cats.length > 0 : Object.keys(cats).length > 0);
        var hasPills = strip.querySelectorAll('.tpv2-pill:not(.tpv2-pill-all)').length > 0;
        if (hasData && !hasPills) {
            rebuildCustomStrip(strip);
        }
    }

    function rebuildCustomStrip(strip) {
        var cats = window.categories;
        if (!cats) return;

        // Convert object/array to array
        var catList = [];
        if (Array.isArray(cats)) {
            catList = cats.slice();
        } else {
            for (var k in cats) {
                if (Object.prototype.hasOwnProperty.call(cats, k)) {
                    catList.push(cats[k]);
                }
            }
        }
        if (catList.length === 0) return;

        // Compute total count
        var total = 0;
        catList.forEach(function (row) {
            if (!row || !row.label) return;
            var m = String(row.label).match(/(\d+)\s*$/);
            if (m) total += parseInt(m[1], 10) || 0;
        });

        // Also include subcategories in total if they exist
        var subs = window.subcategories;
        if (subs) {
            var subTotal = 0;
            for (var sk in subs) {
                if (!Object.prototype.hasOwnProperty.call(subs, sk)) continue;
                var srow = subs[sk];
                if (!srow || !srow.label) continue;
                var sm = String(srow.label).match(/(\d+)\s*$/);
                if (sm) subTotal += parseInt(sm[1], 10) || 0;
            }
            if (subTotal > total) total = subTotal;
        }

        // Build pills HTML
        var html = '';

        // "All" pill
        html += '<button type="button" class="tpv2-pill tpv2-pill-all tpv2-pill-active" data-tpv2-all="1">' +
            '<span class="tpv2-pill-icon" aria-hidden="true">📦</span>' +
            '<span class="tpv2-pill-label">' + escapeHtml(T.all) + '</span>' +
            '<span class="tpv2-pill-count">' + total + '</span>' +
            '</button>';

        // One pill per top-level category
        catList.forEach(function (row, idx) {
            if (!row) return;
            var rawLabel = String(row.label || '');
            // Extract emoji + name + count
            var parsed = parseLabel(rawLabel);
            var pillIcon = parsed.icon || '📁';
            var pillName = parsed.name || ('Category ' + (idx + 1));
            var pillCount = parsed.count || '';
            var catId = row.rowid || row.id || '';

            html += '<button type="button" class="tpv2-pill" ' +
                'data-tpv2-cat-index="' + idx + '" ' +
                'data-tpv2-cat-id="' + escapeHtml(String(catId)) + '" ' +
                'data-tpv2-cat-name="' + escapeHtml(pillName) + '">' +
                '<span class="tpv2-pill-icon" aria-hidden="true">' + escapeHtml(pillIcon) + '</span>' +
                '<span class="tpv2-pill-label">' + escapeHtml(pillName) + '</span>' +
                (pillCount ? '<span class="tpv2-pill-count">' + escapeHtml(pillCount) + '</span>' : '') +
                '</button>';
        });

        strip.innerHTML = html;

        // Bind clicks
        strip.querySelectorAll('.tpv2-pill').forEach(function (pill) {
            pill.addEventListener('click', function () {
                strip.querySelectorAll('.tpv2-pill').forEach(function (p) { p.classList.remove('tpv2-pill-active'); });
                pill.classList.add('tpv2-pill-active');

                if (pill.hasAttribute('data-tpv2-all')) {
                    handleAllClick();
                    updateBreadcrumb(T.allProducts);
                    // FIX (counter-v1): All tab — show global total
                    setCurrentCategoryCount(null);
                    if (typeof window.tpv2UpdatePagination === 'function') {
                        window.tpv2UpdatePagination(0);
                    }
                } else {
                    var idx = parseInt(pill.getAttribute('data-tpv2-cat-index'), 10);
                    var name = pill.getAttribute('data-tpv2-cat-name') || '';
                    // Clear All mode flag when switching to a specific category
                    window.takeposAllProductsMode = false;

                    // FIX (cache-stale-v6): force a fresh fetch when the user clicks
                    // a category pill. The product cache normally serves prior
                    // responses, but if a previous request was corrupted (e.g. mid-
                    // deploy or a server hiccup returned partial data), the bad
                    // result sticks until the cache TTL expires. Setting the same
                    // flag the shift-click uses guarantees this click hits the
                    // server and overwrites whatever the cache had.
                    window._takeposForceProductReload = true;
                    try {
                        if (window.takeposProductCache
                            && typeof window.takeposProductCache.invalidateAll === 'function') {
                            // Drop in-memory + sessionStorage cache so MoreProducts
                            // pagination within this category also starts fresh.
                            window.takeposProductCache.invalidateAll();
                        }
                    } catch (e) {}

                    handleCategoryClick(idx);
                    updateBreadcrumb(name);
                    // FIX (counter-v1 + empty-category-v6): read this category's count
                    // from the pill badge. Treat 0 as a real value (empty category),
                    // NOT as null/fallback — otherwise the counter shows the global
                    // total when a category genuinely has 0 products.
                    var countEl = pill.querySelector('.tpv2-pill-count');
                    var catCount = countEl ? (parseInt(countEl.textContent, 10) || 0) : 0;
                    setCurrentCategoryCount(catCount); // pass 0 through, do NOT convert to null
                    // Update pagination immediately based on category count
                    // (arrows will be re-updated again after AJAX via watchProductGrid)
                    if (typeof window.tpv2UpdatePagination === 'function') {
                        window.tpv2UpdatePagination(0);
                    }
                }
            });
        });
    }

    function parseLabel(label) {
        // Input: "🥖 Bakery   12" or "🥖\u00A0Bakery 12" or just "Bakery"
        var s = String(label || '').replace(/\u00A0/g, ' ').trim();
        var icon = '';
        var count = '';

        // Extract leading emoji (first char if it's in emoji ranges)
        var emojiMatch = s.match(/^([\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}\u{1F000}-\u{1F2FF}])\s*/u);
        if (emojiMatch) {
            icon = emojiMatch[1];
            s = s.substring(emojiMatch[0].length);
        }

        // Extract trailing number
        var countMatch = s.match(/\s+(\d+)\s*$/);
        if (countMatch) {
            count = countMatch[1];
            s = s.substring(0, s.length - countMatch[0].length);
        }

        // Whatever remains is the name
        var name = s.trim();

        // If no emoji was extracted, pick one from the name
        if (!icon) icon = pickEmojiByKeyword(name);

        return { icon: icon, name: name, count: count };
    }

    function pickEmojiByKeyword(name) {
        var n = String(name).toLowerCase();
        var rules = [
            [['water','مياه','ماء'], '💧'],
            [['tea','coffee','شاي','قهوة'], '☕'],
            [['vegetable','خضار','خضرو'], '🥬'],
            [['bakery','bread','مخبز','مخبوز','خبز','معجن'], '🥖'],
            [['can','معلب'], '🥫'],
            [['dairy','milk','ألبان','حليب','لبن'], '🥛'],
            [['service','خدم'], '🛠️'],
            [['fruit','فاكهة','فواكه'], '🍎'],
            [['meat','لحم','دجاج'], '🥩'],
            [['fish','سمك'], '🐟'],
            [['drink','عصير','مشروب'], '🥤'],
            [['snack','شيبس','وجبة'], '🍿'],
            [['sweet','حلوى','حلوي'], '🍬'],
            [['cleaning','تنظيف','منظف'], '🧴'],
            [['baby','طفل'], '🍼'],
            [['frozen','مجمد'], '🧊']
        ];
        for (var i = 0; i < rules.length; i++) {
            for (var j = 0; j < rules[i][0].length; j++) {
                if (n.indexOf(rules[i][0][j]) !== -1) return rules[i][1];
            }
        }
        return '📁';
    }

    function handleCategoryClick(idx) {
        // FIX (wrong-category-v7): the previous implementation used the pill's
        // positional index `idx` to figure out which `#catdiv<sub>` the original
        // LoadProducts() should read its rowid from. That positional mapping
        // breaks when the visible custom strip and the hidden #catdiv array
        // get out of sync (different sort order, paged categories, late
        // PrintCategories runs, etc.). Symptom: clicking خصراوات would set
        // currentcat to BAKERY's id and load BAKERY products.
        //
        // The fix: bypass the positional dance entirely. Every pill already
        // carries its real category rowid in data-tpv2-cat-id. We plant that
        // rowid on #catdiv0 (same trick handleAllClick uses) and call
        // LoadProducts(0). LoadProducts then reads the correct rowid from
        // catdiv0.data-rowid and queries the right category.
        var strip = document.getElementById('tpv2-custom-strip');
        if (!strip) return;
        var pill = strip.querySelector('.tpv2-pill[data-tpv2-cat-index="' + idx + '"]');
        if (!pill) return;
        var realCatId = parseInt(pill.getAttribute('data-tpv2-cat-id'), 10);
        if (isNaN(realCatId) || realCatId <= 0) return;

        try {
            var el = document.getElementById('catdiv0');
            if (el) { el.setAttribute('data-rowid', realCatId); }
            if (window.jQuery) { jQuery('#catdiv0').data('rowid', realCatId); }
        } catch (e) {}

        if (typeof window.LoadProducts === 'function') {
            try { window.LoadProducts(0); } catch (e) { console.warn('[tpv2] LoadProducts failed', e); }
        }
    }

    function handleAllClick() {
        // FIX (counter-v1): Reset to global total when All is clicked
        _currentCatCount = null;

        // FIX (all-products-v2): Show all categorized products.
        // Strategy: force currentcat=0 so ajax.php returns all products.
        // We use TWO mechanisms so it works regardless of deployment state:
        // 1. Set window.takeposAllProductsMode = true (read by patched LoadProducts)
        // 2. Also directly override catdiv0 data-rowid AFTER PrintCategories
        //    so even unpatched LoadProducts gets currentcat=0
        window.takeposAllProductsMode = true;

        if (typeof window.PrintCategories === 'function') {
            try { window.pagecategories = 0; window.PrintCategories(0); } catch (e) {}
        }

        // Override catdiv0 AFTER PrintCategories (which sets it to real category id)
        // Both the HTML attribute AND jQuery internal store must be set
        try {
            var el = document.getElementById('catdiv0');
            if (el) { el.setAttribute('data-rowid', '0'); }
            if (window.jQuery) { jQuery('#catdiv0').data('rowid', 0); }
        } catch(e) {}

        if (typeof window.LoadProducts === 'function') {
            try { window.LoadProducts(0); } catch (e) { console.warn('[tpv2] LoadProducts(0) failed', e); }
        }
        // Keep flag TRUE — LoadProducts is async, the AJAX .then() callback
        // uses currentcat (already 0), not the flag, so it's safe to leave set.
        // The flag persists for MoreProducts pagination too.
    }
    function computeVisibleCount() {
        // The original code uses MAXCATEG - 2 as the visible count per page.
        // We don't have a direct global; count actual rendered #catdiv slots.
        return document.querySelectorAll('#takepos-main-layout .div4 [id^="catdiv"]').length - 2; // minus 2 nav arrows
    }

    /* ================================================================== */
    /* 3. BREADCRUMB                                                       */
    /* ================================================================== */
    function updateBreadcrumb(label) {
        var el = document.getElementById('tpv2-bc-current');
        if (!el) return;
        el.textContent = label || T.allProducts;
    }

    /* ================================================================== */
    /* 4. COUNTER                                                          */
    /* ================================================================== */
    function computeTotalProductCount() {
        // Use the real total injected from PHP (exact DB count of sellable products)
        if (typeof window.takeposTotalProductsCount === 'number' && window.takeposTotalProductsCount > 0) {
            return window.takeposTotalProductsCount;
        }
        // Fallback: sum numbers from category labels
        var subs = window.subcategories;
        var cats = window.categories;
        var total = 0;
        var seen = {};
        function add(arr) {
            if (!arr) return;
            for (var k in arr) {
                if (!Object.prototype.hasOwnProperty.call(arr, k)) continue;
                var row = arr[k];
                if (!row) continue;
                var id = row.rowid || row.id || k;
                if (seen[id]) continue;
                seen[id] = true;
                var m = String(row.label || '').match(/(\d+)\s*$/);
                if (m) total += parseInt(m[1], 10) || 0;
            }
        }
        if (subs && Object.keys(subs).length > 0) add(subs); else add(cats);
        return total;
    }

    var _totalCache   = null;  // global all-products count (set once)
    var _currentCatCount = null; // count for the currently active category tab (null = show all)

    function getTotal() {
        // FIX (counter-v1): If we're viewing a specific category, return that
        // category's product count instead of the global all-products count.
        if (_currentCatCount !== null && _currentCatCount >= 0) {
            return _currentCatCount;
        }
        if (_totalCache == null) _totalCache = computeTotalProductCount();
        return _totalCache;
    }

    // FIX (counter-v1): Call this when switching to a specific category tab
    // to make the "total" counter show that category's count.
    function setCurrentCategoryCount(count) {
        _currentCatCount = (typeof count === 'number' && count >= 0) ? count : null;
        var div5 = document.querySelector('#takepos-main-layout .div5');
        if (div5) updateCounter(div5);
    }

    function updateCounter(div5) {
        var s = document.getElementById('tpv2-counter-shown');
        var t = document.getElementById('tpv2-counter-total');
        if (!s || !t) return;
        // Count visible filled product cards on the current page (non-empty, non-arrow)
        var shown = div5.querySelectorAll('.wrapper2:not(.arrow):not(.divempty)').length;
        s.textContent = shown;
        // FIX (empty-category-v5): if we're inside a specific category, trust its
        // count even when it's 0 — don't keep the previous category's number.
        if (_currentCatCount !== null) {
            t.textContent = (_currentCatCount >= 0) ? _currentCatCount : shown;
            return;
        }
        // All view — fall back to global total
        var tot = getTotal();
        if (tot > 0) {
            t.textContent = tot;
        } else if (shown > 0) {
            t.textContent = shown;
        } else {
            t.textContent = '0';
        }
    }

    function watchProductGrid(div5) {
        if (typeof MutationObserver === 'undefined') return;
        var busy = false;
        new MutationObserver(function () {
            if (busy) return; busy = true;
            setTimeout(function () {
                updateCounter(div5);
                // Also refresh the pagination bar (page X/Y, enable/disable arrows)
                if (typeof window.tpv2UpdatePagination === 'function') {
                    try {
                        var pp = (typeof pageproducts !== 'undefined') ? pageproducts : 0; // eslint-disable-line
                        window.tpv2UpdatePagination(pp);
                    } catch(e) {}
                }
                busy = false;
            }, 80);
        }).observe(div5, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });
    }

    /* ================================================================== */
    /* utils                                                               */
    /* ================================================================== */
    function escapeHtml(s) {
        if (s == null) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

})();