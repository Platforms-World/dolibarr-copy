/* ==========================================================================
 * kf_offline_index.js — تكامل الأوفلاين الخاص بـ index.php  (v4)
 * --------------------------------------------------------------------------
 * مبني على بنية الصف الحقيقية زي ما هي بالضبط بـ invoice.php:
 *   <tr class="drag drop oddeven posinvoiceline" data-fk-product data-qty>
 *     <td class="left">أيقونة + اسم</td>
 *     [أعمدة اختيارية حسب إعدادات المتجر: سعر الوحدة / الإجمالي بدون ضريبة]
 *     <td class="right">نسبة الخصم</td>
 *     <td class="right tpv2-qty-cell">
 *        <span class="tpv2-qty-stepper">
 *          <button class="tpv2-qty-btn tpv2-qty-minus" onclick="takeposV2SetQty(...)">−</button>
 *          <span class="tpv2-qty-value">qty</span>
 *          <button class="tpv2-qty-btn tpv2-qty-plus" onclick="takeposV2SetQty(...)">+</button>
 *        </span>
 *     </td>
 *     <td class="right classfortooltip">الإجمالي شامل الضريبة</td>   <-- آخر خلية دايماً
 *   </tr>
 *
 * القاعدة الذهبية: ما منزيد ولا منشيل ولا عمود واحد من الصف المستنسخ —
 * فقط منبدّل النص جوا الخلايا الموجودة، ومنفكّ onclick الحقيقي (يلي بيتصل
 * بالسيرفر على سطر حقيقي) ونربط بدالتنا المحلية بدلاً عنه.
 * ========================================================================== */
(function (global) {
  "use strict";

  var offlineLines = [];      // [{idproduct, qty, label, price_ttc, node}]
  var baseInvoiceId = "0";
  var baseTotal = 0;
  var capturedForThisOfflinePeriod = false;
  var templateRow = null;
  var appendedNodes = [];
  var catalogCache = [];

  function fmt(n) { n = parseFloat(n || 0); return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function parseMoney(txt) { return parseFloat(String(txt || "0").replace(/[^\d.-]/g, "")) || 0; }

  function setCatalog(list) { if (Array.isArray(list)) catalogCache = list; }
  function findProduct(idproduct) {
    for (var i = 0; i < catalogCache.length; i++) {
      var p = catalogCache[i];
      var pid = p.rowid != null ? p.rowid : p.id;
      if (String(pid) === String(idproduct)) return p;
    }
    return null;
  }

  var TEMPLATE_STORAGE_KEY = "kf_offline_row_template_v1";

  /* يُستدعى من loadPosLines كل مرة تنجح أونلاين — يخزّن آخر صف حقيقي كقالب (بالذاكرة + دائم بالمتصفح) */
  function noteRealCartRendered() {
    try {
      var pos = document.getElementById("poslines");
      if (!pos) return;
      var rows = pos.querySelectorAll("tr.posinvoiceline[id]");
      if (rows.length) {
        templateRow = rows[rows.length - 1].cloneNode(true);
        try { localStorage.setItem(TEMPLATE_STORAGE_KEY, templateRow.outerHTML); } catch (e) { /* تخزين محلي غير متاح — لا بأس */ }
      }
    } catch (e) { /* ignore */ }
  }

  /* لو ما في قالب بالذاكرة (أول تحميل صفحة جديد)، جرب رجّعه من التخزين الدائم
   * يلي انحفظ آخر مرة كنا فيها متصلين — هيك أول عملية باليوم، حتى لو صار
   * الجهاز أوفلاين من أول ثانية فتح فيها الصفحة، لسا عندنا قالب نستخدمه. */
  function hydrateTemplateFromStorage() {
    if (templateRow) return;
    try {
      var html = localStorage.getItem(TEMPLATE_STORAGE_KEY);
      if (!html) return;
      var wrapper = document.createElement("table");
      wrapper.innerHTML = "<tbody>" + html + "</tbody>";
      var row = wrapper.querySelector("tr");
      if (row) templateRow = row;
    } catch (e) { /* ignore */ }
  }
  hydrateTemplateFromStorage();

  var baseSubtotal = 0;
  var baseItemCount = 0;

  function captureBaseStateIfNeeded() {
    if (capturedForThisOfflinePeriod) return;
    capturedForThisOfflinePeriod = true;
    try {
      var invEl = document.getElementById("invoiceid");
      baseInvoiceId = (invEl && invEl.value) ? invEl.value : "0";
    } catch (e) { baseInvoiceId = "0"; }
    try {
      var totEl = document.querySelector(".tpv2-grand-total-value");
      baseTotal = totEl ? parseMoney(totEl.textContent) : 0;
    } catch (e) { baseTotal = 0; }
    try {
      var rows = document.querySelectorAll(".tpv2-summary-row");
      if (rows.length) {
        var valEl = rows[0].querySelector(".tpv2-summary-value");
        baseSubtotal = valEl ? parseMoney(valEl.textContent) : 0;
        var labelEl = rows[0].querySelector(".tpv2-summary-label");
        var m = labelEl ? /(\d+)/.exec(labelEl.textContent) : null;
        baseItemCount = m ? parseInt(m[1], 10) : 0;
      }
    } catch (e) { baseSubtotal = 0; baseItemCount = 0; }
  }

  function onBackOnline() { capturedForThisOfflinePeriod = false; }

  function offlineTotal() { return offlineLines.reduce(function (s, l) { return s + l.qty * l.price_ttc; }, 0); }
  function grandTotal() { return baseTotal + offlineTotal(); }

  function updateNumberInText(el, newVal) {
    if (!el) return;
    var replaced = el.textContent.replace(/[\d]+(?:[.,]\d+)?/, fmt(newVal));
    el.textContent = replaced;
  }

  function updateTotalsDisplay() {
    var els = document.querySelectorAll(".tpv2-grand-total-value");
    Array.prototype.forEach.call(els, function (el) { el.textContent = fmt(grandTotal()); });
    var payLabel = document.querySelector(".tpv2-btn-pay .tpv2-btn-label");
    updateNumberInText(payLabel, grandTotal());
    try {
      var rows = document.querySelectorAll(".tpv2-summary-row");
      if (rows.length) {
        var valEl = rows[0].querySelector(".tpv2-summary-value");
        if (valEl) valEl.textContent = fmt(baseSubtotal + offlineTotal());
        var labelEl = rows[0].querySelector(".tpv2-summary-label");
        if (labelEl) labelEl.textContent = labelEl.textContent.replace(/\d+/, String(baseItemCount + offlineLines.length));
      }
    } catch (e) { /* ignore — لا نكسر الشاشة لو تغيّرت البنية */ }
  }

  var footerEl = null;

  function ensureFooter() {
    var existing = document.querySelector(".tpv2-cart-footer");
    if (existing) { footerEl = existing; return; }
    if (footerEl && document.body.contains(footerEl)) return;

    var pos = document.getElementById("poslines");
    if (!pos) return;

    footerEl = document.createElement("div");
    footerEl.className = "tpv2-cart-footer kf-offline-footer";
    footerEl.innerHTML =
        '<div class="tpv2-summary">' +
        '<div class="tpv2-summary-row">' +
        '<span class="tpv2-summary-label">Subtotal (0 items)</span>' +
        '<span class="tpv2-summary-value">0.00</span>' +
        '</div>' +
        '<div class="tpv2-summary-row">' +
        '<span class="tpv2-summary-label">Tax</span>' +
        '<span class="tpv2-summary-value">0.00</span>' +
        '</div>' +
        '</div>' +
        '<div class="tpv2-grand-total">' +
        '<span class="tpv2-grand-total-label">Total Due</span>' +
        '<span class="tpv2-grand-total-value">0.00</span>' +
        '</div>' +
        '<div class="tpv2-cart-actions">' +
        '<button type="button" class="tpv2-btn tpv2-btn-cancel">' +
        '<span class="tpv2-btn-key">F12</span>' +
        '<span class="tpv2-btn-label"><span class="fa fa-trash-alt"></span> Cancel</span>' +
        '</button>' +
        '<button type="button" class="tpv2-btn tpv2-btn-hold">' +
        '<span class="tpv2-btn-key">F7</span>' +
        '<span class="tpv2-btn-label"><span class="fa fa-pause"></span> Hold</span>' +
        '</button>' +
        '<button type="button" class="tpv2-btn tpv2-btn-pay">' +
        '<span class="tpv2-btn-key">F11</span>' +
        '<span class="tpv2-btn-label"><span class="fa fa-credit-card"></span> Pay 0.00</span>' +
        '</button>' +
        '</div>';

    // نربط بنفس الدوال الحقيقية الموجودة أصلاً (New/DirectPayment) — وهاي أصلاً
    // صارت آمنة أوفلاين من تعديل سابق، فما بلزم نعيد كتابة منطق الدفع/الإلغاء
    var cancelBtn = footerEl.querySelector(".tpv2-btn-cancel");
    var payBtn = footerEl.querySelector(".tpv2-btn-pay");
    var holdBtn = footerEl.querySelector(".tpv2-btn-hold");
    if (cancelBtn) cancelBtn.addEventListener("click", function () { if (typeof global.New === "function") global.New(); });
    if (payBtn) payBtn.addEventListener("click", function () { if (typeof global.DirectPayment === "function") global.DirectPayment(); });
    if (holdBtn) holdBtn.addEventListener("click", function () { feedback("التعليق (Hold) مش مدعوم أوفلاين حالياً", "error"); });

    pos.appendChild(footerEl);
  }

  function addProduct(idproduct, qty, unitpriceTtc) {
    ensureFooter();
    captureBaseStateIfNeeded();
    qty = parseFloat(qty) || 1;
    var existing = null;
    for (var i = 0; i < offlineLines.length; i++) {
      if (String(offlineLines[i].idproduct) === String(idproduct)) { existing = offlineLines[i]; break; }
    }
    if (existing) {
      existing.qty += qty;
      applyLineToNode(existing);
    } else {
      var p = findProduct(idproduct) || {};
      var price = unitpriceTtc || p.price_ttc || p.price || 0;
      var displayLabel = (p.ref ? (p.ref + " - ") : "") + (p.label || ("#" + idproduct));
      var line = { idproduct: String(idproduct), qty: qty, label: displayLabel, price_ttc: parseFloat(price) || 0, node: null };
      offlineLines.push(line);
      appendRowForLine(line);
    }
    updateTotalsDisplay();
  }

  /* يبني صف جديد باستنساخ صف حقيقي — بدون أي عمود إضافي، فقط تبديل المحتوى
   * وإعادة ربط زرّي +/- الموجودين أصلاً بمنطقنا المحلي. */
  /* بنية الصف الاحتياطية — مطابقة تماماً لكلاسات invoice.php الحقيقية
   * (posinvoiceline / tpv2-qty-cell / tpv2-qty-stepper / tpv2-qty-btn / classfortooltip).
   * تُستخدم بس لما ما يكون في صف حقيقي شفناه أبداً (لا بالذاكرة ولا بالتخزين الدائم) —
   * أي أول تشغيل للصفحة وهي أوفلاين من الثانية الأولى. */
  function buildFallbackRow() {
    var wrapper = document.createElement("table");
    wrapper.innerHTML = "<tbody><tr class=\"drag drop oddeven posinvoiceline\">" +
        "<td class=\"left\"></td>" +
        "<td class=\"right\"></td>" +
        "<td class=\"right tpv2-qty-cell\">" +
        "<span class=\"tpv2-qty-stepper\">" +
        "<button type=\"button\" class=\"tpv2-qty-btn tpv2-qty-minus\" aria-label=\"-\">−</button>" +
        "<span class=\"tpv2-qty-value\"></span>" +
        "<button type=\"button\" class=\"tpv2-qty-btn tpv2-qty-plus\" aria-label=\"+\">+</button>" +
        "</span>" +
        "</td>" +
        "<td class=\"right classfortooltip\"></td>" +
        "</tr></tbody>";
    return wrapper.querySelector("tr");
  }

  function appendRowForLine(line) {
    var pos = document.getElementById("poslines");
    if (!pos) return;

    var clone = templateRow ? templateRow.cloneNode(true) : buildFallbackRow();
    clone.removeAttribute("id"); // تجنّب تكرار id مع الصف الأصلي
    clone.classList.add("kf-offline-line");
    clone.setAttribute("data-fk-product", line.idproduct);
    clone.setAttribute("data-qty", String(line.qty));

    // اسم المنتج: الاسم القديم (لو مستنسخ) غالباً جوا عنصر متداخل (tooltip)، مش نص مباشر —
    // فبنمسح الخلية بالكامل ونعيد بناءها، مع الحفاظ على الأيقونة إذا وجدت
    var leftCell = clone.querySelector("td.left");
    if (leftCell) {
      var iconNode = leftCell.querySelector("img, .fa, [class*='fa-']");
      leftCell.textContent = "";
      if (iconNode) { leftCell.appendChild(iconNode); leftCell.appendChild(document.createTextNode(" ")); }
      leftCell.appendChild(document.createTextNode(line.label));
    }

    // خلية الكمية: نفس البنية والأزرار الحقيقية، بس نفكّ onclick الأصلي (بيتصل بسطر حقيقي)
    // ونربطها بمنطقنا المحلي
    var qtyValueEl = clone.querySelector(".tpv2-qty-value");
    if (qtyValueEl) qtyValueEl.textContent = String(line.qty);
    var minusBtn = clone.querySelector(".tpv2-qty-minus");
    var plusBtn = clone.querySelector(".tpv2-qty-plus");
    if (minusBtn) {
      minusBtn.removeAttribute("onclick");
      minusBtn.addEventListener("click", function (e) {
        e.stopPropagation();
        if (line.qty <= 1) removeLine(line); else { line.qty -= 1; applyLineToNode(line); }
        updateTotalsDisplay();
      });
    }
    if (plusBtn) {
      plusBtn.removeAttribute("onclick");
      plusBtn.addEventListener("click", function (e) {
        e.stopPropagation();
        line.qty += 1; applyLineToNode(line); updateTotalsDisplay();
      });
    }

    // آخر خلية بالصف = الإجمالي شامل الضريبة (نفس ترتيب invoice.php دايماً)
    var cells = clone.querySelectorAll("td");
    if (cells.length) {
      var totalCell = cells[cells.length - 1];
      totalCell.textContent = fmt(line.qty * line.price_ttc);
      totalCell.classList.add("kfoff-total-cell");
    }

    var tbody = pos.querySelector("table tbody") || pos.querySelector("table") || pos;
    tbody.appendChild(clone);
    line.node = clone;
    appendedNodes.push(clone);
  }

  function applyLineToNode(line) {
    if (!line.node) return;
    var qtyValueEl = line.node.querySelector(".tpv2-qty-value");
    if (qtyValueEl) qtyValueEl.textContent = String(line.qty);
    line.node.setAttribute("data-qty", String(line.qty));
    var totalCell = line.node.querySelector(".kfoff-total-cell");
    if (totalCell) totalCell.textContent = fmt(line.qty * line.price_ttc);
  }

  function removeLine(line) {
    var idx = offlineLines.indexOf(line);
    if (idx > -1) offlineLines.splice(idx, 1);
    if (line.node && line.node.parentNode) line.node.parentNode.removeChild(line.node);
    var ai = appendedNodes.indexOf(line.node);
    if (ai > -1) appendedNodes.splice(ai, 1);
  }

  function feedback(msg, type) {
    if (typeof global.takeposFeedback === "function") global.takeposFeedback(msg, type || "success");
    else alert(msg);
  }

  function clearVisibleCart() {
    var pos = document.getElementById("poslines");
    if (pos) {
      var rows = pos.querySelectorAll("tr.posinvoiceline");
      Array.prototype.forEach.call(rows, function (r) { if (r.parentNode) r.parentNode.removeChild(r); });
    }
    var invEl = document.getElementById("invoiceid");
    if (invEl) invEl.value = "0";
    var els = document.querySelectorAll(".tpv2-grand-total-value");
    Array.prototype.forEach.call(els, function (el) { el.textContent = fmt(0); });
    var payLabel = document.querySelector(".tpv2-btn-pay .tpv2-btn-label");
    updateNumberInText(payLabel, 0);
    try {
      var sumRows = document.querySelectorAll(".tpv2-summary-row");
      if (sumRows.length) {
        var valEl = sumRows[0].querySelector(".tpv2-summary-value");
        if (valEl) valEl.textContent = fmt(0);
        var labelEl = sumRows[0].querySelector(".tpv2-summary-label");
        if (labelEl) labelEl.textContent = labelEl.textContent.replace(/\d+/, "0");
      }
    } catch (e) { /* ignore */ }
  }

  function doPay(mode) {
    captureBaseStateIfNeeded();
    if (!offlineLines.length && (!baseInvoiceId || baseInvoiceId === "0")) { feedback("السلة فارغة", "error"); return; }
    if (!global.KFOffline) { feedback("خطأ: طبقة الأوفلاين غير محمّلة", "error"); return; }
    var sale = {
      base_invoice_id: baseInvoiceId,
      lines: offlineLines.map(function (l) { return { idproduct: l.idproduct, qty: l.qty }; }),
      payment: { method: mode || "LIQ" },
      estimated_total: grandTotal()
    };
    global.KFOffline.queueSale(sale).then(function () {
      feedback("تم حفظ الفاتورة محلياً — ستُزامن تلقائياً عند عودة الاتصال ✓", "success");
      clearVisibleCart();
      reset();
    }).catch(function (e) {
      feedback("فشل حفظ الفاتورة محلياً: " + ((e && e.message) || e), "error");
    });
  }

  function pay(mode) { doPay(mode); }

  function reset() {
    appendedNodes.forEach(function (n) { if (n && n.parentNode) n.parentNode.removeChild(n); });
    appendedNodes = [];
    offlineLines = [];
    baseInvoiceId = "0";
    baseTotal = 0;
    baseSubtotal = 0;
    baseItemCount = 0;
    capturedForThisOfflinePeriod = false;
  }

  function isActive() {
    captureBaseStateIfNeeded();
    return offlineLines.length > 0 || (!!baseInvoiceId && baseInvoiceId !== "0");
  }

  function cancelOffline() {
    captureBaseStateIfNeeded();
    var invId = baseInvoiceId;
    if (invId && invId !== "0" && global.KFOffline) {
      // بيلغي الفاتورة الحقيقية كاملة (بكل أسطرها القديمة أونلاين + أي شي انضاف أوفلاين
      // كان رح يترمى أصلاً لأنه إلغاء) — نفس استدعاء invoice.php?action=delete الحقيقي
      global.KFOffline.queueCancel(invId);
    }
    clearVisibleCart();
    reset();
  }

  global.KFOfflineIndex = {
    setCatalog: setCatalog,
    noteRealCartRendered: noteRealCartRendered,
    onBackOnline: onBackOnline,
    addProduct: addProduct,
    pay: pay,
    cancelOffline: cancelOffline,
    reset: reset,
    clearVisibleCart: clearVisibleCart,
    isActive: isActive,
    total: grandTotal
  };

  if (global.KFOffline && typeof global.KFOffline.onStatusChange === "function") {
    global.KFOffline.onStatusChange(function (online) { if (online) onBackOnline(); });
  }
})(window);