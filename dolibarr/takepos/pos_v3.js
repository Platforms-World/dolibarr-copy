/* ==========================================================================
 * pos_v3.js — Kafo POS (Path B · Step 1)  [diagnostic build v2]
 * Reuses backend:  ajax/ajax.php (getProducts/getProductsAll/search)
 *                  invoice.php   (addline/deleteline/updateprice/delete)
 * ========================================================================== */
(function () {
  "use strict";
  var C = window.KAFO || {};

  /* ---- on-screen error reporter (so failures are visible) ---- */
  function showErr(where, msg) {
    var box = document.getElementById("kfErr");
    if (!box) { box = document.createElement("div"); box.id = "kfErr"; box.className = "kf-err"; document.body.appendChild(box); }
    var row = document.createElement("div");
    row.className = "kf-err-row";
    row.innerHTML = '<b>' + esc(where) + ':</b> ' + esc(msg) +
        ' <a href="#" onclick="this.parentNode.remove();return false">✕</a>';
    box.appendChild(row);
    box.classList.add("show");
    console.error("[KAFO] " + where + ": " + msg);
  }
  window.onerror = function (m, src, line, col) {
    showErr("JS error", m + " @ line " + line + ":" + col);
    return false;
  };

  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  function esc(s) { return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) { return ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]; }); }
  function qs(p) { return Object.keys(p).map(function (k) { return encodeURIComponent(k) + "=" + encodeURIComponent(p[k]); }).join("&"); }
  function fmt(n) { n = parseFloat(n || 0); return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

  var grid = $("#kfGrid"), loading = $("#kfLoading"), chips = $("#kfChips"),
      searchInput = $("#kfSearch"), poslines = $("#poslines"),
      cartLines = $("#kfCartLines"), invoiceLabel = $("#kfInvoiceLabel"),
      catName = $("#kfCatName"), countEl = $("#kfCount");

  var currentCat = 0, currentInvoiceId = "0", searchTimer = null;

  /* generic fetch that always resolves with {ok,status,text} */
  function getText(url) {
    return fetch(url, { credentials: "same-origin" })
        .then(function (r) { return r.text().then(function (t) { return { ok: r.ok, status: r.status, text: t }; }); })
        .catch(function (e) { return { ok: false, status: 0, text: "fetch failed: " + e.message }; });
  }

  /* tolerant product JSON parser */
  function parseProducts(txt) {
    var data = null;
    try { data = JSON.parse(txt); } catch (e) { return { list: null, raw: txt }; }
    if (Array.isArray(data)) return { list: data, raw: null };
    if (data && typeof data === "object") {
      for (var k in data) { if (Array.isArray(data[k])) return { list: data[k], raw: null }; }
    }
    return { list: null, raw: txt };
  }

  /* ---------- products ---------- */
  function loadProducts(cat) {
    currentCat = cat;
    loading.classList.remove("hide"); grid.innerHTML = "";
    var action = (cat && cat !== 0 && cat !== "0") ? "getProducts" : "getProductsAll";
    var url = C.ajaxUrl + "?" + qs({ action: action, token: C.token, term: C.term, thirdpartyid: 0, category: cat || 0 });
    console.log("[KAFO] products:", url);
    getText(url).then(function (res) {
      loading.classList.add("hide");
      if (!res.ok) { showErr("Products HTTP " + res.status, snippet(res.text)); grid.innerHTML = errBlock("HTTP " + res.status, res.text); return; }
      var p = parseProducts(res.text);
      if (!p.list) { showErr("Products not JSON", snippet(p.raw)); grid.innerHTML = errBlock("الخادم لم يُرجِع JSON", p.raw); return; }
      renderProducts(p.list);
    });
  }

  function renderProducts(list) {
    grid.innerHTML = "";
    if (!list.length) { grid.innerHTML = '<div class="kf-loading">لا توجد منتجات في هذا التصنيف</div>'; countEl.textContent = ""; return; }
    countEl.textContent = list.length + " منتج";
    var frag = document.createDocumentFragment();
    list.forEach(function (p) {
      var id = p.rowid || p.id;
      var priceNum = fmt(p.price_ttc != null ? p.price_ttc : (p.price != null ? p.price : 0));
      var el = document.createElement("div");
      el.className = "kf-card"; el.setAttribute("data-id", id);
      el.innerHTML =
          '<div class="top"><span class="ref">#' + esc(p.ref || id) + '</span><span class="cat"></span></div>' +
          '<div class="nm">' + esc(p.label || "") + '</div>' +
          '<div class="foot"><div class="price"><b>' + priceNum + '</b><span>' + esc(C.currency || "") + '</span></div></div>';
      el.addEventListener("click", function () { addProduct(id, 1); flash(el); });
      frag.appendChild(el);
    });
    grid.appendChild(frag);
  }
  function flash(el) { el.style.borderColor = "var(--pay)"; setTimeout(function () { el.style.borderColor = ""; }, 250); }
  function snippet(t) { return (t || "").replace(/\s+/g, " ").trim().slice(0, 200) || "(empty response)"; }
  function errBlock(title, raw) { return '<div class="kf-loading" style="text-align:start"><b style="color:var(--danger)">' + esc(title) + '</b><br><span style="font-size:11px;color:var(--text3)">' + esc(snippet(raw)) + '</span></div>'; }

  /* ---------- search ---------- */
  function doSearch(term) {
    if (!term) { loadProducts(currentCat); return; }
    loading.classList.remove("hide"); grid.innerHTML = "";
    var url = C.ajaxUrl + "?" + qs({ action: "search", token: C.token, term: C.term, search_term: term });
    getText(url).then(function (res) {
      loading.classList.add("hide");
      if (!res.ok) { grid.innerHTML = errBlock("Search HTTP " + res.status, res.text); return; }
      var p = parseProducts(res.text);
      if (!p.list) { grid.innerHTML = errBlock("بحث: ليس JSON", p.raw); return; }
      renderProducts(p.list);
    });
  }

  /* ---------- cart (invoice.php) ---------- */
  function looksLikeError(html) {
    var h = (html || "").toLowerCase();
    return h.indexOf("login") > -1 && h.indexOf("password") > -1 || h.indexOf("erreur") > -1 || h.indexOf("fatal error") > -1;
  }
  function loadCart(invoiceid) {
    var url = C.invoiceUrl + "?" + qs({ token: C.token, place: C.place, invoiceid: invoiceid || 0 });
    console.log("[KAFO] cart:", url);
    getText(url).then(function (res) { applyCartResponse("Cart", res); });
  }
  function addProduct(idproduct, qty) {
    var url = C.invoiceUrl + "?" + qs({ action: "addline", token: C.token, place: C.place, idproduct: idproduct, qty: qty || 1, invoiceid: currentInvoiceId });
    console.log("[KAFO] addline:", url);
    getText(url).then(function (res) { applyCartResponse("AddLine", res); });
  }
  function lineOp(action, idline, number) {
    var p = { action: action, token: C.token, place: C.place, idline: idline, invoiceid: currentInvoiceId };
    if (number != null) p.number = number;
    getText(C.invoiceUrl + "?" + qs(p)).then(function (res) { applyCartResponse(action, res); });
  }

  function dbg(label, res, extra) {
    if (!C.debug) return;
    var box = document.getElementById("kfDbg");
    if (!box) { box = document.createElement("div"); box.id = "kfDbg"; box.className = "kf-dbg"; document.body.appendChild(box); }
    box.textContent = "[" + label + "] status=" + (res ? res.status : "?") +
        " len=" + (res ? (res.text || "").length : "?") + (extra ? " | " + extra : "");
  }

  function applyCartResponse(label, res) {
    if (!res.ok) { showErr(label + " HTTP " + res.status, snippet(res.text)); dbg(label, res, "HTTP ERROR"); return; }
    if (looksLikeError(res.text)) { showErr(label + " error page", snippet(res.text)); dbg(label, res, "ERROR PAGE"); }
    poslines.innerHTML = res.text;
    var idEl = poslines.querySelector("#invoiceid");
    if (idEl && idEl.value) currentInvoiceId = idEl.value;
    invoiceLabel.textContent = (currentInvoiceId && currentInvoiceId !== "0") ? ("#" + currentInvoiceId) : "#—";
    var rows = renderCleanCart();
    dbg(label, res, "rows=" + rows + " invId=" + currentInvoiceId + " total=" + (grabGrand() || "-"));
  }

  function txtOf(root, sel) { var e = root.querySelector(sel); return e ? e.textContent.replace(/\s+/g, " ").trim() : ""; }
  function grabGrand() { var g = poslines.querySelector(".tpv2-grand-total-value"); return g ? g.textContent.trim() : ""; }

  function renderCleanCart() {
    var rows = $$(".posinvoiceline", poslines).filter(function (tr) { return tr.id && tr.id !== ""; });
    if (!rows.length) {
      cartLines.innerHTML = '<div class="kf-empty"><i class="fa-solid fa-basket-shopping"></i><span>السلة فارغة</span></div>';
      setTotal(""); return 0;
    }
    var html = "";
    rows.forEach(function (tr) {
      var idline = tr.id;
      // product name = text of the first <td> (class "left"); strip ref bold if present
      var nameCell = tr.querySelector("td.left") || tr.querySelector("td");
      var name = nameCell ? nameCell.textContent.replace(/\s+/g, " ").trim() : "";
      // qty from data-qty attribute (reliable)
      var qty = tr.getAttribute("data-qty") || "1";
      qty = String(parseFloat(qty) || qty);
      // line total = last cell with a number
      var cells = $$("td", tr);
      var lineTot = "";
      for (var i = cells.length - 1; i >= 0; i--) {
        var t = cells[i].textContent.replace(/\s+/g, " ").trim();
        if (/[0-9]/.test(t)) { lineTot = t; break; }
      }
      html +=
          '<div class="kf-line" data-line="' + esc(idline) + '">' +
          '<div class="nm"><div class="box"><i class="fa-solid fa-box"></i></div>' +
          '<div class="info"><b>' + esc(name) + '</b><small class="num">#' + esc(idline) + '</small></div></div>' +
          '<div class="qty"><button data-act="dec">−</button><input class="num" value="' + esc(qty) + '"><button data-act="inc">+</button></div>' +
          '<div class="pr"><span class="num">' + esc(lineTot) + '</span><a class="del" data-act="del"><i class="fa-solid fa-trash-can"></i></a></div>' +
          '</div>';
    });
    cartLines.innerHTML = html;
    bindCart();
    setTotal(grabGrand());
    return rows.length;
  }
  function bindCart() {
    $$(".kf-line", cartLines).forEach(function (line) {
      var idline = line.getAttribute("data-line");
      var input = line.querySelector(".qty input");
      line.querySelectorAll("[data-act]").forEach(function (b) {
        b.addEventListener("click", function () {
          var act = b.getAttribute("data-act"), cur = parseFloat(input.value) || 1;
          if (act === "inc") lineOp("updateqty", idline, cur + 1);
          else if (act === "dec") { cur <= 1 ? lineOp("deleteline", idline) : lineOp("updateqty", idline, cur - 1); }
          else if (act === "del") lineOp("deleteline", idline);
        });
      });
      input.addEventListener("change", function () { lineOp("updateqty", idline, parseFloat(input.value) || 1); });
    });
  }
  function setTotal(grand) {
    var clean = (grand || "").replace(/[^\d.,-]/g, "") || "0.00";
    var t = $("#kfTotal"), pa = $("#kfPayAmt");
    if (t) t.textContent = clean; if (pa) pa.textContent = clean;
  }

  /* ---------- events ---------- */
  if (chips) chips.addEventListener("click", function (e) {
    var b = e.target.closest(".chip"); if (!b) return;
    $$(".chip", chips).forEach(function (c) { c.classList.remove("on"); });
    b.classList.add("on");
    if (catName) catName.textContent = b.textContent.trim();
    if (searchInput) searchInput.value = "";
    loadProducts(b.getAttribute("data-cat"));
  });
  if (searchInput) searchInput.addEventListener("input", function () {
    clearTimeout(searchTimer); var v = searchInput.value.trim();
    searchTimer = setTimeout(function () { doSearch(v); }, 250);
  });
  var cancelBtn = $("#kfCancel");
  if (cancelBtn) cancelBtn.addEventListener("click", function () { if (confirm("إلغاء السلة الحالية؟")) { lineOp("delete", 0); setTimeout(function(){ currentInvoiceId="0"; }, 400); } });

  // payment open
  var payBtn = $("#kfPay"), railPay = $("#kfRailPay");
  if (payBtn) payBtn.addEventListener("click", openPay);
  if (railPay) railPay.addEventListener("click", openPay);
  // New sale (rail first button)
  var railNew = document.querySelector(".kf-rail .rkey");
  if (railNew) railNew.addEventListener("click", function () { if (confirm("بدء بيع جديد؟")) newSale(); });
  // payment modal controls
  var payClose = $("#kfPayClose"); if (payClose) payClose.addEventListener("click", closePay);
  var payOv = $("#kfPayOv"); if (payOv) payOv.addEventListener("click", function (e) { if (e.target === payOv) closePay(); });
  $$(".kf-pm").forEach(function (b) {
    b.addEventListener("click", function () {
      payMethod = b.getAttribute("data-method");
      $$(".kf-pm").forEach(function (x) { x.classList.remove("on"); });
      b.classList.add("on");
      $("#kfCashWrap").style.display = (payMethod === "CASH") ? "" : "none";
    });
  });
  var recv = $("#kfReceived"); if (recv) recv.addEventListener("input", updateChange);
  var quick = $("#kfQuick");
  if (quick) quick.addEventListener("click", function (e) {
    var b = e.target.closest("button"); if (!b) return;
    var q = b.getAttribute("data-q"), due = curTotalNum(), inp = $("#kfReceived");
    if (q === "exact") inp.value = due.toFixed(2);
    else inp.value = ((parseFloat(inp.value) || 0) + parseFloat(q)).toFixed(2);
    updateChange();
  });

  var keypad = $("#kfKeypad");
  if (keypad) keypad.addEventListener("click", function (e) {
    var b = e.target.closest("button"); if (!b) return;
    var k = b.getAttribute("data-k"), inp = $("#kfReceived");
    if (k === "back") inp.value = inp.value.slice(0, -1);
    else if (k === ".") { if (inp.value.indexOf(".") < 0) inp.value += "."; }
    else inp.value += b.textContent.trim();
    updateChange();
  });
  var payConfirm = $("#kfPayConfirm");
  if (payConfirm) payConfirm.addEventListener("click", function () { validateSale(payMethod); });
  // direct cash/card from rail
  $$(".kf-rail .rkey").forEach(function (b) {
    var label = (b.textContent || "").trim();
    if (b.querySelector(".fa-coins")) b.addEventListener("click", function () { directPay("CASH"); });
    if (b.querySelector(".fa-credit-card")) b.addEventListener("click", function () { directPay("CB"); });
  });
  function directPay(mode) {
    if (!currentInvoiceId || currentInvoiceId === "0") { toast("السلة فارغة"); return; }
    if (!confirm(mode === "CB" ? "دفع بالبطاقة؟" : "دفع نقدي؟")) return;    validateSale(mode);
  }



  /* ---------- payment ---------- */
  var payMethod = "LIQ";
  function curTotalNum() {
    var t = ($("#kfTotal") ? $("#kfTotal").textContent : "0").replace(/[^\d.]/g, "");
    return parseFloat(t) || 0;
  }
  function openPay() {
    if (!currentInvoiceId || currentInvoiceId === "0") { toast("السلة فارغة"); return; }
    var due = curTotalNum();
    if (due <= 0) { toast("لا يوجد مبلغ مستحق"); return; }
    payMethod = "LIQ";
    $$(".kf-pm").forEach(function (b) { b.classList.toggle("on", b.getAttribute("data-method") === "LIQ"); });
    $("#kfCashWrap").style.display = "";
    $("#kfPayDue").textContent = due.toFixed(2);
    $("#kfReceived").value = due.toFixed(2);
    updateChange();
    $("#kfPayOv").classList.add("show");
    setTimeout(function () { $("#kfReceived").focus(); $("#kfReceived").select(); }, 50);
  }
  function closePay() { $("#kfPayOv").classList.remove("show"); }
  function updateChange() {
    var due = curTotalNum();
    var rec = parseFloat(($("#kfReceived").value || "").replace(/[^\d.]/g, "")) || 0;
    var change = rec - due;
    $("#kfChange").textContent = (change >= 0 ? change : 0).toFixed(2);
    $("#kfPayConfirmAmt").textContent = due.toFixed(2);
  }
  function validateSale(mode) {
    if (!currentInvoiceId || currentInvoiceId === "0") { toast("السلة فارغة"); return; }
    var acct = (mode === "CB") ? (C.cardAcct || 0) : (C.cashAcct || 0);    var url = C.invoiceUrl + "?" + qs({
      place: C.place, action: "valid", token: C.token,
      pay: mode, amount: 0, excess: 0, invoiceid: currentInvoiceId, accountid: acct
    });
    console.log("[KAFO] pay:", url);
    var confirmBtn = $("#kfPayConfirm"); if (confirmBtn) confirmBtn.disabled = true;
    getText(url).then(function (res) {
      if (confirmBtn) confirmBtn.disabled = false;
      dbg("Pay", res, "mode=" + mode);
      var failed = !res.ok || /(ui-state-error|fielderror|fatal error|erreur)/i.test(res.text);
      if (failed) { showErr("Payment failed", snippet(res.text)); toast("فشل الدفع"); return; }
      closePay();
      toast("تم الدفع بنجاح ✓");
      newSale();
    });
  }
  /* ---------- rail nav buttons (History, Held, Shift, More) ---------- */
  var U = (C.urls || {});

  /* ── Rail nav → kfModalOpen ── */
  var _railModal = [
    ['kfRailHistory', 'سجل المبيعات',    'fa-clock-rotate-left', '#1d4ed8'],
    ['kfRailHeld',    'الطلبات المعلقة',  'fa-list-check',        '#9333ea'],
    ['kfRailShift',   'الورديات',         'fa-business-time',     '#0891b2'],
    ['kfRailRefund',  'الاسترجاع',        'fa-rotate-left',       '#dc2626'],
    ['kfRailReports', 'التقارير',         'fa-chart-line',        '#16a34a'],
  ];
  _railModal.forEach(function (r) {
    var btn = document.getElementById(r[0]);
    if (!btn) return;
    btn.addEventListener('click', function () {
      var url = btn.getAttribute('data-url') || '';
      if (!url) return;
      /* highlight active */
      document.querySelectorAll('.kfr-btn').forEach(function(b){ b.classList.remove('kfr-active'); });
      btn.classList.add('kfr-active');
      if (typeof kfModalOpen === 'function') kfModalOpen(url, r[1], r[2], r[3]);
      else window.open(url, '_blank');
    });
  });

  /* clear active on modal close */
  document.addEventListener('kfModalClosed', function () {
    document.querySelectorAll('.kfr-btn').forEach(function(b){ b.classList.remove('kfr-active'); });
  });

  /* New sale */
  var btnNew = document.getElementById('kfRailNew');
  if (btnNew) btnNew.addEventListener('click', function () {
    if (confirm('بدء بيع جديد؟')) newSale();
  });

  /* Hold */
  var btnHold = document.getElementById('kfRailHold');
  if (btnHold) btnHold.addEventListener('click', function () {
    if (!currentInvoiceId || currentInvoiceId === '0') { toast('السلة فارغة'); return; }
    var label = prompt('عنوان الطلب المعلق (اختياري):', '') ;
    if (label === null) return; /* cancelled */
    var ep = (window.KAFO && window.KAFO.holdUrl) || (window.location.origin + '/takepos/ajax/hold.php');
    fetch(ep + '?action=hold&token=' + encodeURIComponent(window.KAFO && window.KAFO.token || '') + '&invoiceid=' + encodeURIComponent(currentInvoiceId) + '&label=' + encodeURIComponent(label), { credentials:'same-origin', cache:'no-store' })
      .then(function(r){ return r.json(); })
      .then(function(res){
        if (res && res.success) { toast('تم تعليق الطلب ✓', 'success'); newSale(); }
        else { toast((res && res.error) ? res.error : 'فشل التعليق', 'error'); }
      });
  });

  /* Discount — open reduction page */
  var btnDiscount = document.getElementById('kfRailDiscount');
  if (btnDiscount) btnDiscount.addEventListener('click', function () {
    if (!currentInvoiceId || currentInvoiceId === '0') { toast('السلة فارغة'); return; }
    var url = (window.KAFO && window.KAFO.reductionUrl) ||
              (window.location.origin + '/takepos/reduction.php?invoiceid=' + currentInvoiceId);
    if (typeof kfModalOpen === 'function') kfModalOpen(url, 'خصم الفاتورة', 'fa-percent', '#d97706');
    else window.open(url, '_blank');
  });

  /* Customer */
  var btnCust = document.getElementById('kfRailCustomer');
  if (btnCust) btnCust.addEventListener('click', function () {
    var url = (window.KAFO && window.KAFO.customerUrl) ||
              (window.location.origin + '/takepos/customer_select.php?place=' + (window.KAFO && window.KAFO.place || '0'));
    if (typeof kfModalOpen === 'function') kfModalOpen(url, 'اختيار العميل', 'fa-user', '#0891b2');
    else window.open(url, '_blank');
  });

  /* More — drawer */
  var btnMore = document.getElementById('kfRailMore');
  if (btnMore) btnMore.addEventListener('click', function () {
    if (typeof kfDrawerOpen === 'function') kfDrawerOpen();
  });

  /* F-key shortcuts */
  document.addEventListener('keydown', function (e) {
    if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA')) return;
    var map = {
      'F1':  'kfRailNew',     'F2': 'kfRailPay',
      'F3':  'kfRailCash',    'F4': 'kfRailCard',
      'F5':  'kfRailHold',    'F6': 'kfRailDiscount',
      'F7':  'kfRailCustomer','F8': 'kfRailHistory',
      'F9':  'kfRailHeld',    'F10':'kfRailShift',
      'F11': 'kfRailRefund',  'F12':'kfRailReports',
    };
    if (map[e.key]) {
      var el = document.getElementById(map[e.key]);
      if (el) { e.preventDefault(); el.click(); }
    }
  });

  /* ── Language switch ── */
  function kfSwitchLang() {
    /* Read URLs injected directly from PHP in the button's data attributes */
    var btn = document.getElementById('kfTopbarLang') || document.getElementById('kfRailLang');
    var urlAr = btn ? btn.getAttribute('data-url-ar') : null;
    var urlEn = btn ? btn.getAttribute('data-url-en') : null;
    var isAr  = btn ? btn.getAttribute('data-is-ar') === '1' : true;
    var url   = isAr ? urlEn : urlAr;
    if (url) { window.location.href = url; }
  }
  var _langBtns = ['kfTopbarLang','kfRailLang'];
  _langBtns.forEach(function(id){
    var b = document.getElementById(id);
    if (b) b.addEventListener('click', kfSwitchLang);
  });

  /* ---------- toast ---------- */
  var toastTimer = null;
  function toast(msg) {
    var el = document.getElementById("kfToast");
    if (!el) { el = document.createElement("div"); el.id = "kfToast"; el.className = "kf-toast"; document.body.appendChild(el); }
    el.textContent = msg;
    el.classList.add("show");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { el.classList.remove("show"); }, 2400);
  }

  function newSale() {    currentInvoiceId = "0";
    cartLines.innerHTML = '<div class="kf-empty"><i class="fa-solid fa-basket-shopping"></i><span>السلة فارغة</span></div>';
    invoiceLabel.textContent = "#—";
    setTotal("");
    loadCart(0);
    loadProducts(currentCat);
  }

  /* ---------- boot ---------- */
  if (!C.ajaxUrl) { showErr("Boot", "window.KAFO config missing — pos.php did not output config."); return; }
  if (!C.term || C.term === "0") {
    showErr("Terminal", "لا توجد طرفية مختارة في الجلسة. افتح index.php واختر طرفية أولاً ثم ارجع.");
  }

  /* ── resume held sale from URL param ── */
  try {
    var _urlP = new URLSearchParams(window.location.search);
    var _resumeId = _urlP.get('resume_invoice');
    if (_resumeId && parseInt(_resumeId, 10) > 0) {
      currentInvoiceId = String(parseInt(_resumeId, 10));
      invoiceLabel.textContent = '#' + currentInvoiceId;
      loadCart(currentInvoiceId);
      loadProducts(0);
      toast('تم استئناف الطلب #' + currentInvoiceId + ' ✓');
    } else {
      loadCart(0);
      loadProducts(0);
    }
  } catch (e) {
    loadCart(0);
    loadProducts(0);
  }
})();