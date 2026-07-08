/**
 * TakePOS V2 — UI enhancement patches
 * -----------------------------------------------
 * 1. Category active pill   — dark highlight on the selected category
 * 2. Category label cleanup — strips trailing " - description" suffix
 * 3. Pagination label       — inserts "‹  X / Y  ›" text between the two arrow tiles
 *
 * HOW TO INCLUDE in index.php (before </body>):
 *
 *   <?php if ($takeposV2Enabled) { ?>
 *   <script src="<?php echo DOL_URL_ROOT; ?>/takepos/js/takepos_v2_cat_active.js"></script>
 *   <?php } ?>
 */
(function () {
    'use strict';

    document.querySelectorAll('.div3 button.actionbutton').forEach(b => {
        const dataId = b.getAttribute('data-takepos-action-id') || '';
        const id = b.id || '';
        const text = (b.innerText || '').replace(/\s+/g, ' ').trim().substring(0, 30);
        const iconClasses = [...b.querySelectorAll('.fa, .fas, .far, .fab')].map(e => e.className).join(' | ');
        console.log(id, '|', dataId, '|', text, '|', iconClasses);
    });

    /* ------------------------------------------------------------------ */
    /* Boot                                                                 */
    /* ------------------------------------------------------------------ */
    function init() {
        var div4 = document.querySelector('#takepos-main-layout .div4');
        var div5 = document.querySelector('#takepos-main-layout .div5');
        if (!div4 || !div5) { setTimeout(init, 400); return; }

        bindCategoryActive(div4);
        cleanCategoryLabels(div4);
        watchDiv4(div4);

        injectPaginationLabel(div5);
        watchDiv5(div5);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { setTimeout(init, 200); });
    } else {
        setTimeout(init, 200);
    }

    /* ================================================================== */
    /* 1. CATEGORY BAR                                                      */
    /* ================================================================== */

    function bindCategoryActive(div4) {
        div4.addEventListener('click', function (e) {
            var wrapper = e.target.closest('.wrapper');
            if (!wrapper) return;
            if (wrapper.querySelector('.fa-chevron-left, .fa-chevron-right')) return;
            div4.querySelectorAll('.wrapper').forEach(function (w) {
                w.classList.remove('tpv2-cat-active');
            });
            wrapper.classList.add('tpv2-cat-active');
        }, true);
    }

    function stripCatSuffix(text) {
        if (!text) return text;
        // Remove " - anything" after the count number
        return text.replace(/\s+[-\u2010-\u2015\u2212\uFE58\uFE63\uFF0D\u2014\u2013][\s\S]*$/u, '').trim();
    }

    function cleanCategoryLabels(div4) {
        div4.querySelectorAll('.description_content').forEach(function (el) {
            var b = el.querySelector('b, strong');
            if (b) {
                b.textContent = stripCatSuffix(b.textContent);
                el.innerHTML = b.outerHTML;
            } else {
                el.textContent = stripCatSuffix(el.textContent);
            }
        });
    }

    function watchDiv4(div4) {
        if (typeof MutationObserver === 'undefined') return;
        var busy = false;
        new MutationObserver(function () {
            if (busy) return; busy = true;
            setTimeout(function () { cleanCategoryLabels(div4); busy = false; }, 0);
        }).observe(div4, { childList: true, subtree: true, characterData: true });
    }

    /* ================================================================== */
    /* 2. PRODUCT GRID — pagination label between ‹ and › tiles           */
    /* ================================================================== */

    /**
     * Dolibarr renders exactly two .wrapper2.arrow tiles:
     *   index MAXPRODUCT-2  → ← (less)
     *   index MAXPRODUCT-1  → → (more)
     *
     * We inject a <span class="tpv2-page-label"> between them.
     * The label text is read from #search_pagination (Dolibarr sets it) or
     * derived from the visible non-empty, non-arrow tiles.
     */
    function injectPaginationLabel(div5) {
        var arrows = div5.querySelectorAll('.wrapper2.arrow');
        if (arrows.length < 2) return; // not ready yet

        var prevArrow = arrows[0]; // ‹
        var nextArrow = arrows[1]; // ›

        // If label already injected, skip
        if (div5.querySelector('.tpv2-page-label')) return;

        var label = document.createElement('div');
        label.className = 'tpv2-page-label';
        label.setAttribute('aria-live', 'polite');
        label.style.cssText = [
            'grid-column: 1 / -1',
            'display: flex',
            'align-items: center',
            'justify-content: center',
            'gap: 16px',
            'height: 52px',
            'font-size: 12.5px',
            'font-weight: 600',
            'color: var(--tp-text-muted)',
            'font-family: var(--tp-font-num)',
            'pointer-events: none'
        ].join(';');

        // Move both arrow tiles and insert label between them, all inside a
        // flex row that is itself a grid-spanning div
        var paginationRow = document.createElement('div');
        paginationRow.className = 'tpv2-pagination-row';
        paginationRow.style.cssText = [
            'grid-column: 1 / -1',
            'display: flex',
            'align-items: center',
            'justify-content: center',
            'gap: 12px',
            'padding: 6px 0 12px',
            'align-self: start'
        ].join(';');

        // Style the arrow wrappers as inline buttons within the row
        [prevArrow, nextArrow].forEach(function (a) {
            a.style.cssText = [
                'grid-column: auto',
                'height: 36px',
                'width: 36px',
                'display: flex',
                'align-items: center',
                'justify-content: center',
                'background: var(--tp-bg-panel)',
                'border: 1px solid var(--tp-border)',
                'border-radius: 8px',
                'cursor: pointer',
                'box-shadow: var(--tp-shadow-xs)',
                'transition: all 0.15s',
                'flex-shrink: 0'
            ].join(';');
        });

        div5.insertBefore(paginationRow, prevArrow);
        paginationRow.appendChild(prevArrow);
        paginationRow.appendChild(label);
        paginationRow.appendChild(nextArrow);

        updatePageLabel(div5, label);
    }

    function updatePageLabel(div5, label) {
        // Try to read Dolibarr's hidden pagination input
        var paginationInput = document.getElementById('search_pagination');
        if (paginationInput && paginationInput.value) {
            label.textContent = paginationInput.value;
            return;
        }
        // Fallback: count visible non-empty non-arrow product tiles
        var tiles = div5.querySelectorAll('.wrapper2:not(.arrow):not(.divempty)');
        if (tiles.length) {
            label.textContent = tiles.length + ' منتجات';
        }
    }

    function watchDiv5(div5) {
        if (typeof MutationObserver === 'undefined') return;
        var busy = false;
        new MutationObserver(function () {
            if (busy) return; busy = true;
            setTimeout(function () {
                // Re-check pagination row exists after AJAX reload
                var row = div5.querySelector('.tpv2-pagination-row');
                if (!row) { injectPaginationLabel(div5); }
                var label = div5.querySelector('.tpv2-page-label');
                if (label) updatePageLabel(div5, label);
                busy = false;
            }, 80);
        }).observe(div5, { childList: true, subtree: false });
    }

})();

/* ================================================================== */
/* SHORTCUTS SEARCH — filter tiles as user types                        */
/* ================================================================== */
(function () {
    'use strict';

    function initShortcutsSearch() {
        var input = document.getElementById('takepos-shortcuts-search-input');
        if (!input) return; // search bar not in DOM yet

        input.addEventListener('input', function () {
            filterShortcuts(input.value.trim().toLowerCase());
        });

        // Clear on Escape
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                input.value = '';
                filterShortcuts('');
            }
        });
    }

    function filterShortcuts(query) {
        var drawer = document.getElementById('takepos-shortcuts-drawer');
        if (!drawer) return;

        var sections = drawer.querySelectorAll('.takepos-shortcut-section');
        sections.forEach(function (section) {
            var tiles = section.querySelectorAll('.takepos-shortcut-link');
            var visibleCount = 0;

            tiles.forEach(function (tile) {
                var label = (tile.getAttribute('title') || tile.textContent || '').toLowerCase();
                if (!query || label.indexOf(query) !== -1) {
                    tile.classList.remove('tpv2-hidden');
                    visibleCount++;
                } else {
                    tile.classList.add('tpv2-hidden');
                }
            });

            // Show/hide section based on matches
            if (visibleCount === 0 && query) {
                section.classList.add('tpv2-section-hidden');
            } else {
                section.classList.remove('tpv2-section-hidden');
                // If searching, expand collapsed sections that have matches
                if (query && visibleCount > 0) {
                    section.classList.remove('is-collapsed');
                }
            }
        });
    }

    // Init when drawer opens (it may not exist on page load)
    function watchForDrawer() {
        var drawer = document.getElementById('takepos-shortcuts-drawer');
        if (!drawer) { setTimeout(watchForDrawer, 500); return; }

        // Try init immediately
        initShortcutsSearch();

        // Also re-init if drawer is opened (in case it was rebuilt)
        var observer = new MutationObserver(function () {
            if (!document.getElementById('takepos-shortcuts-search-input')) return;
            initShortcutsSearch();
        });
        observer.observe(drawer, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', watchForDrawer);
    } else {
        watchForDrawer();
    }
})();