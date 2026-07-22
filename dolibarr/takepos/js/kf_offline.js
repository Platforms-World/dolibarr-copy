/* ==========================================================================
 * kf_offline.js — Kafo POS Offline Layer
 * --------------------------------------------------------------------------
 * ملف جديد ومستقل تماماً. لا يلمس invoice.php ولا ajax/ajax.php.
 * يعمل كـ "طبقة" فوق pos_v3.js:
 *   - يخزّن كتالوج المنتجات محلياً (IndexedDB) بعد كل تحميل ناجح.
 *   - لما ينقطع النت: يبني سلة محلية ويحسب مجموع تقديري من الكاش.
 *   - عند تأكيد الدفع أوفلاين: يخزّن الفاتورة كاملة بطابور محلي (queue).
 *   - لما يرجع النت: يعيد تشغيل نفس نداءات invoice.php
 *     (addline لكل سطر، ثم valid) تماماً متل ما كانت بتصير أونلاين.
 * ========================================================================== */
(function (global) {
  "use strict";

  var DB_NAME = "kafo_pos_offline_v1";
  var DB_VERSION = 1;
  var db = null;

  /* ---------------- IndexedDB bootstrap ---------------- */
  function openDB() {
    return new Promise(function (resolve, reject) {
      if (db) { resolve(db); return; }
      if (!("indexedDB" in global)) { reject(new Error("IndexedDB not supported")); return; }
      var req = indexedDB.open(DB_NAME, DB_VERSION);
      req.onupgradeneeded = function (e) {
        var d = e.target.result;
        if (!d.objectStoreNames.contains("catalog")) {
          d.createObjectStore("catalog", { keyPath: "id" });
        }
        if (!d.objectStoreNames.contains("queue")) {
          var qs = d.createObjectStore("queue", { keyPath: "local_ref" });
          qs.createIndex("status", "status", { unique: false });
        }
      };
      req.onsuccess = function (e) { db = e.target.result; resolve(db); };
      req.onerror = function (e) { reject(e.target.error); };
    });
  }

  function store(name, mode) {
    return openDB().then(function (d) {
      return d.transaction(name, mode).objectStore(name);
    });
  }

  function uuid() {
    return "off-" + Date.now().toString(36) + "-" + Math.random().toString(36).slice(2, 10);
  }

  /* ---------------- product catalog cache ---------------- */
  function cacheCatalog(list) {
    if (!Array.isArray(list) || !list.length) return Promise.resolve();
    return store("catalog", "readwrite").then(function (s) {
      return new Promise(function (resolve) {
        list.forEach(function (p) {
          var id = p.rowid != null ? p.rowid : p.id;
          if (id == null) return;
          s.put({
            id: String(id),
            ref: p.ref || "",
            label: p.label || "",
            price_ttc: p.price_ttc != null ? p.price_ttc : (p.price != null ? p.price : 0),
            price_ht: p.price != null ? p.price : 0,
            cached_at: Date.now()
          });
        });
        s.transaction.oncomplete = function () { resolve(); };
        s.transaction.onerror = function () { resolve(); };
      });
    }).catch(function (e) { console.warn("[KFOffline] cacheCatalog:", e); });
  }

  function getCachedCatalog() {
    return store("catalog", "readonly").then(function (s) {
      return new Promise(function (resolve) {
        var out = [];
        var req = s.openCursor();
        req.onsuccess = function (e) {
          var cur = e.target.result;
          if (cur) { out.push(cur.value); cur.continue(); } else resolve(out);
        };
        req.onerror = function () { resolve(out); };
      });
    }).catch(function () { return []; });
  }

  /* ---------------- connectivity detection ---------------- */
  var _online = true;
  var _listeners = [];
  function isOnline() { return _online; }
  function onStatusChange(fn) { _listeners.push(fn); }
  function setOnline(v) {
    if (v === _online) return;
    _online = v;
    _listeners.forEach(function (fn) { try { fn(v); } catch (e) {} });
    if (v) trySync();
    renderBadge();
  }

  /* فحص اتصال حقيقي (وليس navigator.onLine فقط) عبر endpoint موجود أصلاً */
  function probe() {
    var C = global.KAFO || {};
    if (!C.ajaxUrl) return Promise.resolve(_online);
    var url = C.ajaxUrl + "?action=checkfeature&token=" + encodeURIComponent(C.token || "") + "&_p=" + Date.now();
    var ctrl = (typeof AbortController !== "undefined") ? new AbortController() : null;
    var t = setTimeout(function () { if (ctrl) ctrl.abort(); }, 4000);
    return fetch(url, { credentials: "same-origin", cache: "no-store", signal: ctrl ? ctrl.signal : undefined })
      .then(function (r) { clearTimeout(t); return !!r.ok; })
      .catch(function () { clearTimeout(t); return false; });
  }

  function startWatch() {
    global.addEventListener("online", function () { probe().then(setOnline); });
    global.addEventListener("offline", function () { setOnline(false); });
    probe().then(function (ok) {
      setOnline(ok);
      // مهم: لو صفحة انفتحت وهي أصلاً متصلة ومعها فواتير قديمة بالطابور من جلسة سابقة،
      // setOnline ما بينادي المزامنة لأنه ما في "تغيّر حالة" (كانت متصلة من البداية).
      // فمنجرب المزامنة يدوياً هون كمان، أول ما نتأكد من الاتصال، مش بس عند الانتقال.
      trySync();
    });
    setInterval(function () { probe().then(function (ok) { setOnline(ok); if (ok) trySync(); }); }, 15000);
  }

  /* ---------------- offline sale queue ---------------- */
  function queueSale(sale) {
    sale.local_ref = sale.local_ref || uuid();
    sale.kind = sale.kind || "sale";
    sale.status = "pending";
    sale.retry_count = 0;
    sale.created_at = Date.now();
    return store("queue", "readwrite").then(function (s) {
      return new Promise(function (resolve, reject) {
        var req = s.put(sale);
        req.onsuccess = function () { resolve(sale.local_ref); };
        req.onerror = function (e) { reject(e.target.error); };
      });
    }).then(function (ref) { renderBadge(); return ref; });
  }

  /* يخزّن طلب إلغاء فاتورة (بدل بيع) — يُعاد تشغيله عند رجوع النت بنفس
   * استدعاء invoice.php?action=delete الحقيقي، بحيث الإلغاء يشمل كل أسطر
   * الفاتورة (يلي أونلاين والي انضاف أوفلاين سوا) لأنها فاتورة واحدة. */
  function queueCancel(invoiceId) {
    return queueSale({ kind: "cancel", base_invoice_id: String(invoiceId || "0"), lines: [], payment: null });
  }

  function getQueue(statusFilter) {
    return store("queue", "readonly").then(function (s) {
      return new Promise(function (resolve) {
        var out = [];
        var req = s.openCursor();
        req.onsuccess = function (e) {
          var cur = e.target.result;
          if (cur) {
            if (!statusFilter || cur.value.status === statusFilter) out.push(cur.value);
            cur.continue();
          } else resolve(out);
        };
        req.onerror = function () { resolve(out); };
      });
    });
  }

  function updateQueueEntry(entry) {
    return store("queue", "readwrite").then(function (s) {
      return new Promise(function (resolve) {
        var req = s.put(entry);
        req.onsuccess = function () { resolve(); };
        req.onerror = function () { resolve(); };
      });
    });
  }

  function queueCounts() {
    return getQueue().then(function (all) {
      var out = { pending: 0, syncing: 0, synced: 0, failed: 0, conflict: 0 };
      all.forEach(function (e) { if (out[e.status] != null) out[e.status]++; });
      return out;
    });
  }

  /* ---------------- replay engine ----------------
   * يعيد تشغيل نفس الطلبات اللي كان pos_v3.js رح يبعتها أونلاين:
   * addline لكل سطر (يخلق فاتورة حقيقية برقم حقيقي من دوليبار)،
   * وبعدين valid لإغلاق الفاتورة بنفس طريقة الدفع.
   * السعر/الضريبة الحقيقيين يُحسبوا من السيرفر وقت إعادة التشغيل،
   * مش من الكاش (الكاش بس للعرض التقديري وقت الأوفلاين).
   * -------------------------------------------------------------- */
  function qsEnc(p) {
    return Object.keys(p).map(function (k) { return encodeURIComponent(k) + "=" + encodeURIComponent(p[k]); }).join("&");
  }
  function getText(url) {
    return fetch(url, { credentials: "same-origin", cache: "no-store" })
      .then(function (r) { return r.text().then(function (t) { return { ok: r.ok, status: r.status, text: t }; }); })
      .catch(function (e) { return { ok: false, status: 0, text: "fetch failed: " + (e && e.message) }; });
  }
  function extractInvoiceId(html) {
    try {
      var tmp = document.createElement("div");
      tmp.innerHTML = html;
      var idEl = tmp.querySelector("#invoiceid");
      return (idEl && idEl.value) ? idEl.value : null;
    } catch (e) { return null; }
  }

  function replayCancel(entry) {
    var C = global.KAFO || {};
    entry.status = "syncing";
    return updateQueueEntry(entry).then(function () {
      var invId = entry.base_invoice_id;
      if (!invId || invId === "0") throw new Error("لا يوجد رقم فاتورة حقيقي للإلغاء");
      var url = C.invoiceUrl + "?" + qsEnc({ action: "delete", token: C.token, place: C.place || 0, invoiceid: invId });
      return getText(url).then(function (res) {
        if (!res.ok) throw new Error("delete HTTP " + res.status);
        if (/manager approval required/i.test(res.text)) throw new Error("الإلغاء يحتاج موافقة مدير — يحتاج مراجعة يدوية");
      });
    }).then(function () {
      entry.status = "synced";
      entry.synced_at = Date.now();
      return updateQueueEntry(entry);
    }).catch(function (err) {
      entry.retry_count = (entry.retry_count || 0) + 1;
      entry.last_error = String((err && err.message) || err);
      entry.status = entry.retry_count >= 5 ? "conflict" : "failed";
      return updateQueueEntry(entry);
    });
  }

  function replaySale(entry) {
    if (entry.kind === "cancel") return replayCancel(entry);
    var C = global.KAFO || {};
    entry.status = "syncing";
    return updateQueueEntry(entry).then(function () {
      var invoiceId = (entry.base_invoice_id && String(entry.base_invoice_id) !== "0") ? String(entry.base_invoice_id) : "0";
      var chain = Promise.resolve();
      (entry.lines || []).forEach(function (line) {
        chain = chain.then(function () {
          var url = C.invoiceUrl + "?" + qsEnc({
            action: "addline", token: C.token, place: C.place || 0,
            idproduct: line.idproduct, qty: line.qty, invoiceid: invoiceId
          });
          return getText(url).then(function (res) {
            if (!res.ok) throw new Error("addline HTTP " + res.status);
            var id = extractInvoiceId(res.text);
            if (id) invoiceId = id;
          });
        });
      });
      chain = chain.then(function () {
        if (invoiceId === "0") throw new Error("لم يُنشأ رقم فاتورة أثناء إعادة التشغيل");
        var mode = (entry.payment && entry.payment.method) || "LIQ";
        var acct = (mode === "CB") ? (C.cardAcct || 0) : (C.cashAcct || 0);
        var payUrl = C.invoiceUrl + "?" + qsEnc({
          place: C.place || 0, action: "valid", token: C.token,
          pay: mode, amount: 0, excess: 0, invoiceid: invoiceId, accountid: acct
        });
        return getText(payUrl).then(function (res) {
          if (!res.ok) throw new Error("valid HTTP " + res.status);
          entry.synced_invoice_id = invoiceId;
        }).then(function () {
          // علّم الفاتورة إنها اتزامنت من وضع أوفلاين (يستخدم action=addnote الموجود أصلاً بـ invoice.php)
          var noteUrl = C.invoiceUrl + "?" + qsEnc({
            place: C.place || 0, action: "addnote", token: C.token,
            invoiceid: invoiceId, idline: 0, addnote: "KF_OFFLINE_SYNCED"
          });
          return getText(noteUrl).catch(function () { /* ما منوقف المزامنة لو فشلت العلامة بس */ });
        });
      });
      return chain;
    }).then(function () {
      entry.status = "synced";
      entry.synced_at = Date.now();
      return updateQueueEntry(entry);
    }).catch(function (err) {
      entry.retry_count = (entry.retry_count || 0) + 1;
      entry.last_error = String((err && err.message) || err);
      /* بعد 5 محاولات فاشلة تنحط "conflict" وتحتاج مراجعة يدوية بدل إعادة محاولة تلقائية للأبد */
      entry.status = entry.retry_count >= 5 ? "conflict" : "failed";
      return updateQueueEntry(entry);
    });
  }

  var _syncing = false;
  function trySync() {
    if (_syncing || !isOnline()) return Promise.resolve();
    _syncing = true;
    return getQueue("pending")
      .then(function (list) {
        var chain = Promise.resolve();
        list.forEach(function (entry) { chain = chain.then(function () { return replaySale(entry); }); });
        return chain;
      })
      .then(function () { return getQueue("failed"); })
      .then(function (failedList) {
        var chain = Promise.resolve();
        failedList.forEach(function (entry) { chain = chain.then(function () { return replaySale(entry); }); });
        return chain;
      })
      .then(function () { _syncing = false; renderBadge(); })
      .catch(function () { _syncing = false; renderBadge(); });
  }

  /* ---------------- status badge (self-injected — لا حاجة لتعديل pos.php) ---------------- */
  var badgeEl = null;
  function renderBadge() {
    queueCounts().then(function (c) {
      var pendingTotal = c.pending + c.syncing + c.failed;
      if (!badgeEl) {
        badgeEl = document.createElement("div");
        badgeEl.id = "kfOfflineBadge";
        badgeEl.style.cssText = "position:fixed;bottom:14px;left:14px;z-index:99999;" +
          "font-family:inherit;font-size:12px;padding:8px 12px;border-radius:10px;" +
          "box-shadow:0 4px 14px rgba(0,0,0,.25);cursor:pointer;display:none;color:#fff;max-width:280px;line-height:1.4";
        badgeEl.title = "اضغط لإعادة محاولة المزامنة الآن";
        badgeEl.addEventListener("click", function () { trySync(); });
        document.body.appendChild(badgeEl);
      }
      if (!isOnline()) {
        badgeEl.style.display = "block";
        badgeEl.style.background = "#b45309";
        badgeEl.textContent = "غير متصل — العمل محلياً" + (pendingTotal ? (" (" + pendingTotal + " بانتظار المزامنة)") : "");
      } else if (c.conflict) {
        badgeEl.style.display = "block";
        badgeEl.style.background = "#b91c1c";
        badgeEl.textContent = "تعارض بـ " + c.conflict + " فاتورة — مراجعة يدوية مطلوبة";
      } else if (pendingTotal) {
        badgeEl.style.display = "block";
        badgeEl.style.background = "#0f766e";
        badgeEl.textContent = "جارِ مزامنة " + pendingTotal + " فاتورة…";
      } else {
        badgeEl.style.display = "none";
      }
    });
  }

  /* ---------------- public API ---------------- */
  global.KFOffline = {
    isOnline: isOnline,
    onStatusChange: onStatusChange,
    cacheCatalog: cacheCatalog,
    getCachedCatalog: getCachedCatalog,
    queueSale: queueSale,
    queueCancel: queueCancel,
    getQueue: getQueue,
    queueCounts: queueCounts,
    trySync: trySync,
    renderBadge: renderBadge
  };

  onStatusChange(function () { renderBadge(); });

  function boot() { startWatch(); renderBadge(); setInterval(renderBadge, 5000); }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot);
  else boot();

})(window);
