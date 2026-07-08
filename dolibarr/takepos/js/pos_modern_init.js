// FIX (I15): All Arabic/translatable strings now read from window.takeposLabels
// which is populated by index.php via PHP:
//   window.takeposLabels = { cartTitle: '...', customer: '...', ... };
// Falls back to English when the label is missing.
/**
 * TakePOS Modern UI — DOM Restructuring
 * Remaps Dolibarr's original HTML structure to the new design layout.
 *
 * Original:
 *   .container > .header(.topnav) + #takepos-main-layout >
 *     .row1withhead(.div1 .div2 .div3) + .row2withhead(.div4 .div5)
 *
 * New:
 *   .container >
 *     .topnav (appbar — kept as-is, just restyled)
 *     #takepos-main-layout >
 *       .tp-layout >
 *         .tp-cart        (was .div1 = poslines)
 *         .tp-catalog     (was .div4 + .div5)
 *         .tp-right-panel (was .div2 + .div3)
 */
(function () {
  'use strict';

  function restructureTakePOS() {
    var container   = document.querySelector('body.bodytakepos .container');
    var mainLayout  = document.getElementById('takepos-main-layout');
    if (!container || !mainLayout) return;

    var row1 = mainLayout.querySelector('.row1withhead, .row1');
    var row2 = mainLayout.querySelector('.row2withhead, .row2');
    if (!row1 || !row2) return;

    var div1 = row1.querySelector('.div1');   // cart/ticket
    var div2 = row1.querySelector('.div2');   // numpad
    var div3 = row1.querySelector('.div3');   // action buttons
    var div4 = row2.querySelector('.div4');   // categories
    var div5 = row2.querySelector('.div5');   // products

    if (!div1 || !div2 || !div3 || !div4 || !div5) return;

    // 1. Build .tp-layout grid: [cart | catalog | sidebar]
    var tpLayout = document.createElement('div');
    tpLayout.className = 'tp-layout';

    // ── Cart panel (left column) ──
    var tpCart = document.createElement('div');
    tpCart.className = 'tp-cart';

    // Cart header bar
    var tpCartHeader = document.createElement('div');
    tpCartHeader.className = 'tp-cart-header';
    tpCartHeader.innerHTML =
      '<h2>' + ((window.takeposLabels && window.takeposLabels.cartTitle) || 'Cart') + '</h2>' +
      '<span class="tp-invoice-badge" id="tp-invoice-badge">#</span>';
    tpCart.appendChild(tpCartHeader);

    // Customer row
    var tpCustomer = document.createElement('div');
    tpCustomer.className = 'tp-customer-row';
    tpCustomer.id = 'tp-customer-row';
    tpCustomer.onclick = function () { if (typeof Customer === 'function') Customer(); };
    tpCustomer.innerHTML =
      '<span style="width:26px;height:26px;background:var(--primary);color:#fff;' +
      'border-radius:50%;display:flex;align-items:center;justify-content:center;' +
      'font-size:12px;font-weight:700;flex-shrink:0">👤</span>' +
      '<div style="flex:1;min-width:0">' +
        '<div style="font-size:11px;color:var(--text-muted)">' + ((window.takeposLabels && window.takeposLabels.customer) || 'Customer') + '</div>' +
        '<div id="tp-customer-name" style="font-size:13px;font-weight:600;color:var(--text)">' + ((window.takeposLabels && window.takeposLabels.walkInCustomer) || 'Walk-in Customer') + '</div>' +
      '</div>';
    tpCart.appendChild(tpCustomer);

    // Cart lines header
    var tpLinesHead = document.createElement('div');
    tpLinesHead.style.cssText =
      'display:grid;grid-template-columns:1fr 70px 90px;padding:6px 16px;' +
      'font-size:10.5px;color:var(--text-soft);font-weight:600;' +
      'background:var(--bg-soft);border-bottom:1px solid var(--border);' +
      'text-transform:uppercase;letter-spacing:0.5px;flex-shrink:0;';
    tpLinesHead.innerHTML =
      '<span>' + ((window.takeposLabels && window.takeposLabels.product) || 'Product') + '</span>' +
      '<span style="text-align:center">' + ((window.takeposLabels && window.takeposLabels.qty) || 'Qty') + '</span>' +
      '<span style="text-align:right">' + ((window.takeposLabels && window.takeposLabels.total) || 'Total') + '</span>';
    tpCart.appendChild(tpLinesHead);

    // Move div1 (poslines) content — keep div1 itself, just reparent
    div1.className = 'div1'; // keep for JS compatibility
    tpCart.appendChild(div1);

    tpLayout.appendChild(tpCart);

    // ── Catalog (center column: categories + products) ──
    var tpCatalog = document.createElement('div');
    tpCatalog.className = 'tp-catalog';

    // Toolbar bar
    var tpToolbar = document.createElement('div');
    tpToolbar.className = 'tp-catalog-toolbar';
    tpToolbar.innerHTML =
      '<span style="display:flex;align-items:center;gap:6px;color:var(--text-muted)">' +
        '<span>🏠</span><span>الكتالوج</span>' +
        '<span style="color:var(--text-soft)">›</span>' +
        '<span id="tp-current-cat" style="color:var(--text);font-weight:600">جميع المنتجات</span>' +
      '</span>' +
      '<div style="flex:1"></div>' +
      '<span id="tp-search-feedback" style="font-size:11.5px;color:var(--text-muted)"></span>';
    tpCatalog.appendChild(tpToolbar);

    // Move div4 (categories)
    div4.className = 'div4';
    tpCatalog.appendChild(div4);

    // Move div5 (products)
    div5.className = 'div5';
    tpCatalog.appendChild(div5);

    tpLayout.appendChild(tpCatalog);

    // ── Right column: numpad + action buttons, stacked vertically ──
    var tpRightCol = document.createElement('div');
    tpRightCol.className = 'tp-right-panel';
    tpRightCol.style.cssText =
      'display:flex;flex-direction:column;height:100%;overflow:hidden;' +
      'background:var(--bg-soft);border-right:1px solid var(--border);width:56px;';

    // Build sidebar from div3 action buttons
    var tpSidebar = document.createElement('div');
    tpSidebar.className = 'tp-sidebar';

    // Move action buttons from div3 into sidebar
    var actionBtns = div3.querySelectorAll('button');
    var actionCount = 0;
    actionBtns.forEach(function (btn) {
      // Add divider every 3 buttons
      if (actionCount > 0 && actionCount % 3 === 0) {
        var divider = document.createElement('div');
        divider.className = 'tp-sidebar-divider';
        tpSidebar.appendChild(divider);
      }
      tpSidebar.appendChild(btn);
      actionCount++;
    });

    tpRightCol.appendChild(tpSidebar);
    tpLayout.appendChild(tpRightCol);

    // 2. Clear mainLayout and rebuild
    mainLayout.innerHTML = '';
    mainLayout.appendChild(tpLayout);

    // 3. Add shortcuts launcher button (preserved from original)
    var existingLauncher = document.getElementById('takepos-shortcuts-launcher');
    if (existingLauncher && !document.body.contains(existingLauncher)) {
      document.body.appendChild(existingLauncher);
    }

    // 4. Signal CSS that restructuring is done
    document.body.classList.add('tp-ready');

    // 5. Watch #invoiceid to update invoice badge
    var invoiceBadge = document.getElementById('tp-invoice-badge');
    if (invoiceBadge) {
      var updateBadge = function () {
        var inv = document.getElementById('invoiceid');
        if (inv && inv.value) {
          invoiceBadge.textContent = '#' + inv.value;
        }
      };
      setInterval(updateBadge, 1000);
      updateBadge();
    }

    // 6. Watch #idcustomer / #customerandsales to update customer name
    var updateCustomerName = function () {
      var nameEl = document.getElementById('tp-customer-name');
      if (!nameEl) return;
      var customerAnchor = document.querySelector('#customerandsales a#customer');
      if (customerAnchor) {
        var txt = customerAnchor.textContent.trim();
        if (txt) nameEl.textContent = txt;
      }
    };
    setInterval(updateCustomerName, 800);

    console.log('[TakePOS Modern] DOM restructured successfully');
  }

  // Run after page is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      setTimeout(restructureTakePOS, 0);
    });
  } else {
    setTimeout(restructureTakePOS, 0);
  }
})();
