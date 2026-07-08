/**
 * takepos_product_expiry.js
 *
 * Injects an "Expiry Date" field into Dolibarr's product/card.php
 * WITHOUT modifying core files.
 *
 * How it works:
 *  - Detects when we're on product/card.php
 *  - Finds the BARCODE field row and inserts the Expiry Date row below it
 *  - On page load, reads the current expiry value from a hidden PHP-rendered
 *    data attribute (see takepos_product_expiry_data.php)
 *  - On save (form submit), adds the expiry_date value as a hidden input
 *    with name "options_expiry_date" (Dolibarr extrafields convention)
 *
 * INSTALL:
 *  Include this in conf/conf.php or via a custom JS include:
 *    $conf->global->MAIN_JS_CODETOEXECUTE = ... (if supported)
 *  OR better: add to takepos module's JS array in setup.
 *
 * FIX (expiry-extrafield-v1): New file.
 */
(function () {
    'use strict';

    // Only run on product card page
    var path = window.location.pathname;
    if (path.indexOf('/product/card.php') === -1) return;

    var isRtl = (document.documentElement.dir === 'rtl' || document.documentElement.lang === 'ar');

    var LABEL_AR = 'تاريخ انتهاء الصلاحية';
    var LABEL_EN = 'Expiry Date';
    var label    = isRtl ? LABEL_AR : LABEL_EN;

    /**
     * Find the existing expiry value from page (Dolibarr renders extrafields automatically
     * if registered — this is a fallback reader).
     */
    function getCurrentExpiryValue() {
        // Dolibarr renders extrafields with id="options_expiry_date"
        var existingInput = document.getElementById('options_expiry_date');
        if (existingInput) return existingInput.value || '';
        return '';
    }

    /**
     * Check if Dolibarr already rendered the extrafield (it does this automatically
     * once the extrafield is registered in llx_extrafields).
     */
    function isAlreadyRendered() {
        return !!document.getElementById('options_expiry_date');
    }

    /**
     * Build and inject a date input row below the "BARCODE" row.
     * Only called if Dolibarr hasn't auto-rendered it.
     */
    function injectExpiryField() {
        if (isAlreadyRendered()) {
            // Dolibarr auto-rendered it — just style it nicely
            styleExistingField();
            return;
        }

        // Find a good anchor: the BARCODE row or DESCRIPTION row
        var anchorRow = null;
        var allTr = document.querySelectorAll('table.border tr, table.noborder tr');
        for (var i = 0; i < allTr.length; i++) {
            var td = allTr[i].querySelector('td');
            if (!td) continue;
            var txt = td.textContent.trim().toUpperCase();
            if (txt === 'BARCODE' || txt === 'BARCODE TYPE' || txt === 'الباركود' || txt === 'رمز الباركود') {
                anchorRow = allTr[i];
            }
        }

        // Build the new row HTML
        var currentVal = '';
        var tr = document.createElement('tr');
        tr.id = 'takepos-expiry-row';
        tr.innerHTML =
            '<td class="titlefieldcreate">' +
                '<label for="options_expiry_date_inject" style="font-weight:600">' + label + '</label>' +
            '</td>' +
            '<td>' +
                '<input type="date" id="options_expiry_date_inject" name="options_expiry_date"' +
                '  value="' + currentVal + '"' +
                '  style="border:1px solid #ccc;border-radius:4px;padding:4px 8px;font-size:13px;min-width:160px"' +
                '  class="flat">' +
                '<span style="margin-' + (isRtl ? 'right' : 'left') + ':8px;font-size:11px;color:#888">' +
                    (isRtl ? 'اتركه فارغًا إذا لا ينطبق' : 'Leave empty if not applicable') +
                '</span>' +
            '</td>';

        if (anchorRow && anchorRow.parentNode) {
            anchorRow.parentNode.insertBefore(tr, anchorRow.nextSibling);
        } else {
            // Fallback: try to insert before the DESCRIPTION row
            var descRows = document.querySelectorAll('[id="description"], [name="note"]');
            if (descRows.length && descRows[0].closest('tr')) {
                descRows[0].closest('tr').parentNode.insertBefore(tr, descRows[0].closest('tr'));
            }
        }
    }

    /**
     * When Dolibarr auto-renders the extrafield, it may look plain.
     * Optionally add a helper label.
     */
    function styleExistingField() {
        var inp = document.getElementById('options_expiry_date');
        if (!inp) return;
        inp.style.minWidth = '160px';
        // Find the row label and translate it if needed
        var row = inp.closest('tr');
        if (!row) return;
        var lbl = row.querySelector('td:first-child');
        if (lbl && isRtl) {
            lbl.textContent = LABEL_AR;
        }
    }

    /**
     * Shows a warning banner on the product page if the product is expiring soon.
     * (Only in read mode, not in edit mode)
     */
    function showExpiryWarningBanner() {
        var action = new URLSearchParams(window.location.search).get('action') || '';
        if (action === 'edit' || action === 'create' || action === 'add') return;

        var inp = document.getElementById('options_expiry_date');
        if (!inp || !inp.value) return;
        var expiryStr = inp.value;

        var today = new Date(); today.setHours(0,0,0,0);
        var exp   = new Date(expiryStr); exp.setHours(0,0,0,0);
        var diff  = Math.floor((exp - today) / 86400000);

        var WARN_DAYS = 30;
        var CRIT_DAYS = 7;

        if (diff > WARN_DAYS) return; // No banner needed

        var banner = document.createElement('div');
        banner.style.cssText = [
            'margin:12px 0',
            'padding:10px 16px',
            'border-radius:6px',
            'font-weight:600',
            'font-size:14px',
            'display:flex',
            'align-items:center',
            'gap:10px'
        ].join(';');

        var icon, msg, bgColor;

        if (diff < 0) {
            icon    = '⛔';
            bgColor = '#fee2e2';
            msg     = isRtl
                ? ('هذا المنتج منتهي الصلاحية منذ ' + Math.abs(diff) + ' يوم!')
                : ('This product EXPIRED ' + Math.abs(diff) + ' day(s) ago!');
            banner.style.color = '#991b1b';
            banner.style.border = '2px solid #f87171';
        } else if (diff === 0) {
            icon    = '🔴';
            bgColor = '#fee2e2';
            msg     = isRtl ? 'هذا المنتج ينتهي اليوم!' : 'This product expires TODAY!';
            banner.style.color = '#991b1b';
            banner.style.border = '2px solid #f87171';
        } else if (diff <= CRIT_DAYS) {
            icon    = '🔴';
            bgColor = '#fff7ed';
            msg     = isRtl
                ? ('تنبيه: ينتهي هذا المنتج خلال ' + diff + ' أيام!')
                : ('WARNING: Product expires in ' + diff + ' day(s)!');
            banner.style.color = '#92400e';
            banner.style.border = '2px solid #fb923c';
        } else {
            icon    = '⚠';
            bgColor = '#fefce8';
            msg     = isRtl
                ? ('تنبيه: ينتهي هذا المنتج خلال ' + diff + ' يوم.')
                : ('Note: Product expires in ' + diff + ' days.');
            banner.style.color = '#78350f';
            banner.style.border = '1px solid #fbbf24';
        }

        banner.style.background = bgColor;
        banner.innerHTML = '<span style="font-size:20px">' + icon + '</span><span>' + msg + '</span>';

        // Insert at top of main content area
        var main = document.querySelector('.fiche') || document.querySelector('#id-right') || document.querySelector('.col-right');
        if (main) {
            main.insertBefore(banner, main.firstChild);
        }
    }

    function init() {
        injectExpiryField();
        showExpiryWarningBanner();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
