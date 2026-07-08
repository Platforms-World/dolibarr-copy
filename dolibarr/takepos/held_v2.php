<?php
/**
 * held_v2.php — Kafo POS v2 · الطلبات المعلقة (Held Sales)
 * صفحة مستقلة تعرض وتدير الفواتير المعلقة للطرفية الحالية
 */
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML'))  define('NOREQUIREHTML', '1');
if (!defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX', '1');

require '../main.inc.php';
require_once __DIR__ . '/lib/takepos_lang.php';
require_once __DIR__ . '/class/TakeposAccess.class.php';
require_once __DIR__ . '/class/TakeposAudit.class.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);

$langs->loadLangs(array('cashdesk', 'main', 'bills', 'takeposcustom@takepos'));

$terminal = isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : 0;

TakeposAccess::requireFrontendAccess(
    $db, $user, 'takepos.frontend', 'takepos.use',
    $terminal, $langs->trans('TakeposHistoryAccessDenied'), array('page' => 'held_v2.php')
);
TakeposAudit::logEvent($db, $user, 'held_screen_opened', TakeposAudit::SEVERITY_INFO, array('page' => 'held_v2.php'), 'Held sales v2 screen opened');

$FA    = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css';
$title = $langs->trans('TakeposIndexHeldSales') ?: 'Held Sales';
$head  = '<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="' . $FA . '">';
$arrayofcss = array('/takepos/css/workspace_v2.css');
top_htmlhead($head, $title, 0, 0, array(), $arrayofcss);
?>
<body class="kfv2-body">
<?php
$v2PageTitle = $langs->trans('TakeposIndexHeldSales') ?: 'Held Sales';
$v2PageIcon  = 'fa-list-check';
$v2PageSub   = $langs->trans('TakeposIndexHeldSalesSub') ?: 'Suspended sales for this terminal';
$v2BackUrl   = DOL_URL_ROOT . '/takepos/pos_v2.php';
require __DIR__ . '/topbar_v2.php';
?>

<div class="kfv2-page-body">

    <!-- hidden config -->
    <div id="kfv2-held-cfg"
         data-token="<?php echo dol_escape_htmltag(newToken()); ?>"
         data-endpoint="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/ajax/hold.php'); ?>"
         data-pos-url="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/pos_v2.php'); ?>"></div>

    <!-- KPI bar -->
    <div class="kfv2-kpis" style="grid-template-columns:repeat(3,1fr);margin-bottom:18px">
        <div class="kfv2-kpi">
            <div class="kk">الطلبات المعلقة</div>
            <div class="kv num" id="kf-held-count">—</div>
        </div>
        <div class="kfv2-kpi">
            <div class="kk">الطرفية</div>
            <div class="kv num">T<?php echo (int) $terminal; ?></div>
        </div>
        <div class="kfv2-kpi">
            <div class="kk">المستخدم</div>
            <div class="kv" style="font-size:18px"><?php echo dol_escape_htmltag($user->login ?? ''); ?></div>
        </div>
    </div>

    <!-- Held list -->
    <div class="kfv2-card">
        <div class="kfv2-card-head">
            <i class="fa-solid fa-list-check"></i>
            <?php echo dol_escape_htmltag($langs->trans('TakeposIndexHeldSales') ?: 'Held Sales'); ?>
            <div style="margin-inline-start:auto;display:flex;gap:8px">
                <button class="kfv2-btn kfv2-btn-sm kfv2-btn-outline" id="kf-held-refresh">
                    <i class="fa-solid fa-rotate"></i> تحديث
                </button>
            </div>
        </div>

        <div class="kfv2-msg info kfv2-hidden" id="kf-held-msg"></div>

        <div class="kfv2-table-wrap">
            <table class="kfv2-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>المرجع</th>
                        <th>العنوان / الملاحظة</th>
                        <th class="num">الأصناف</th>
                        <th class="num">المجموع</th>
                        <th>الكاشير</th>
                        <th>وقت التعليق</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="kf-held-tbody">
                    <tr class="empty-row">
                        <td colspan="8">
                            <i class="fa-solid fa-spinner fa-spin" style="color:var(--brand)"></i> جاري التحميل...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /kfv2-page-body -->

<div class="kfv2-toast" id="kfv2-toast"></div>

<!-- Confirm cancel modal -->
<div id="kf-cancel-modal" style="display:none;position:fixed;inset:0;z-index:600;
  background:rgba(13,26,48,.45);display:none;place-items:center">
  <div style="background:#fff;border-radius:16px;padding:28px;max-width:400px;width:90%;
    box-shadow:0 20px 60px rgba(13,26,48,.2);font-family:var(--ff,inherit)">
    <h3 style="margin:0 0 12px;font-size:17px;color:#0f1d33">إلغاء الطلب المعلق</h3>
    <p style="color:#465775;font-size:14px;margin-bottom:20px">
      هل أنت متأكد من إلغاء هذا الطلب المعلق؟ سيتم حذف الفاتورة نهائياً.
    </p>
    <div style="display:flex;gap:10px;justify-content:flex-end">
      <button class="kfv2-btn kfv2-btn-outline" id="kf-cancel-no">تراجع</button>
      <button class="kfv2-btn kfv2-btn-danger" id="kf-cancel-yes">
        <i class="fa-solid fa-trash-can"></i> إلغاء الطلب
      </button>
    </div>
  </div>
</div>

<script>
(function () {
    'use strict';

    var cfg      = document.getElementById('kfv2-held-cfg');
    var token    = cfg.getAttribute('data-token')   || '';
    var endpoint = cfg.getAttribute('data-endpoint') || '';
    var posUrl   = cfg.getAttribute('data-pos-url')  || '';

    var pendingCancelHoldId = 0;

    /* ── helpers ── */
    function $(id) { return document.getElementById(id); }
    function qs(p) {
        var u = new URLSearchParams();
        Object.keys(p || {}).forEach(function (k) {
            if (p[k] !== null && p[k] !== undefined && p[k] !== '') u.append(k, p[k]);
        });
        return u.toString();
    }
    function api(params) {
        return fetch(endpoint + '?' + qs(params), { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.json(); });
    }
    function apiPost(params) {
        var body = new URLSearchParams();
        Object.keys(params || {}).forEach(function (k) { body.append(k, params[k]); });
        return fetch(endpoint + '?action=' + encodeURIComponent(params.action || ''), {
            method: 'POST', credentials: 'same-origin', cache: 'no-store',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (r) { return r.json(); });
    }
    function fmt(v) {
        var n = parseFloat(v || 0); if (!isFinite(n)) n = 0;
        return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    var toastTimer;
    function toast(msg, type) {
        var el = $('kfv2-toast'); if (!el) return;
        el.textContent = msg;
        el.style.background = type === 'error' ? '#dc2626' : type === 'success' ? '#16a34a' : '#0f1d33';
        el.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { el.classList.remove('show'); }, 2800);
    }
    function setMsg(msg, type) {
        var el = $('kf-held-msg'); if (!el) return;
        el.className = 'kfv2-msg ' + (type || 'info');
        el.textContent = msg || '';
        if (!msg) el.classList.add('kfv2-hidden');
        else el.classList.remove('kfv2-hidden');
    }

    /* ── load held list ── */
    function loadHeld() {
        var tbody = $('kf-held-tbody'); if (!tbody) return;
        tbody.innerHTML = '<tr class="empty-row"><td colspan="8"><i class="fa-solid fa-spinner fa-spin" style="color:var(--brand)"></i> جاري التحميل...</td></tr>';

        api({ action: 'list', token: token }).then(function (res) {
            $('kf-held-count').textContent = res.count || 0;

            if (!res.success || !(res.held || []).length) {
                tbody.innerHTML = '<tr class="empty-row"><td colspan="8">'
                    + '<i class="fa-solid fa-inbox" style="font-size:24px;color:var(--text-3);display:block;margin-bottom:8px"></i>'
                    + 'لا توجد طلبات معلقة لهذه الطرفية</td></tr>';
                return;
            }

            tbody.innerHTML = res.held.map(function (h, i) {
                return '<tr>'
                    + '<td class="num">' + (i + 1) + '</td>'
                    + '<td class="num" style="font-weight:700">' + (h.ref || '—') + '</td>'
                    + '<td>' + (h.label ? '<span style="background:var(--brand-soft);color:var(--brand-2);padding:3px 9px;border-radius:6px;font-size:12px;font-weight:700">' + h.label + '</span>' : '<span style="color:var(--text-3)">—</span>') + '</td>'
                    + '<td class="num">' + (h.nb_lines || 0) + '</td>'
                    + '<td class="num" style="font-weight:700">' + fmt(h.total_ttc) + '</td>'
                    + '<td>' + (h.cashier || '') + '</td>'
                    + '<td style="color:var(--text-3);font-size:12px">' + (h.date_hold || '') + '</td>'
                    + '<td style="display:flex;gap:6px;align-items:center">'
                    +   '<button class="kfv2-btn kfv2-btn-sm kfv2-btn-primary kf-resume-btn"'
                    +     ' data-hold-id="' + h.hold_id + '" data-invoice-id="' + h.invoice_id + '">'
                    +     '<i class="fa-solid fa-play"></i> استئناف'
                    +   '</button>'
                    +   '<button class="kfv2-btn kfv2-btn-sm kfv2-btn-danger kf-cancel-btn"'
                    +     ' data-hold-id="' + h.hold_id + '">'
                    +     '<i class="fa-solid fa-trash-can"></i>'
                    +   '</button>'
                    + '</td>'
                    + '</tr>';
            }).join('');

            /* bind resume buttons */
            tbody.querySelectorAll('.kf-resume-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var holdId    = btn.getAttribute('data-hold-id');
                    var invoiceId = btn.getAttribute('data-invoice-id');
                    resumeHeld(holdId, invoiceId);
                });
            });

            /* bind cancel buttons */
            tbody.querySelectorAll('.kf-cancel-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    pendingCancelHoldId = parseInt(btn.getAttribute('data-hold-id') || '0', 10);
                    $('kf-cancel-modal').style.display = 'grid';
                });
            });

        }).catch(function () {
            tbody.innerHTML = '<tr class="empty-row"><td colspan="8" style="color:var(--danger)">فشل تحميل الطلبات المعلقة</td></tr>';
        });
    }

    /* ── resume ── */
    function resumeHeld(holdId, invoiceId) {
        setMsg('جاري استئناف الطلب...', 'info');
        apiPost({ action: 'resume', token: token, hold_id: holdId, current_invoice_id: 0 })
            .then(function (res) {
                if (res && res.success) {
                    toast('تم استئناف الطلب — جاري الانتقال للـ POS', 'success');
                    setTimeout(function () {
                        window.location.href = posUrl + '?resume_invoice=' + encodeURIComponent(res.invoice_id) + '&place=' + encodeURIComponent(res.place || 0);
                    }, 800);
                } else {
                    setMsg((res && res.error) ? res.error : 'فشل الاستئناف', 'error');
                    toast('فشل الاستئناف', 'error');
                }
            }).catch(function () {
                setMsg('خطأ في الاتصال', 'error');
            });
    }

    /* ── cancel confirm modal ── */
    $('kf-cancel-no').addEventListener('click', function () {
        $('kf-cancel-modal').style.display = 'none';
        pendingCancelHoldId = 0;
    });
    $('kf-cancel-yes').addEventListener('click', function () {
        if (!pendingCancelHoldId) return;
        $('kf-cancel-modal').style.display = 'none';
        apiPost({ action: 'cancel_hold', token: token, hold_id: pendingCancelHoldId })
            .then(function (res) {
                if (res && res.success) {
                    toast('تم إلغاء الطلب', 'success');
                    loadHeld();
                } else {
                    toast((res && res.error) ? res.error : 'فشل الإلغاء', 'error');
                }
            });
        pendingCancelHoldId = 0;
    });

    /* ── refresh ── */
    $('kf-held-refresh').addEventListener('click', loadHeld);

    /* ── ESC close modal ── */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') $('kf-cancel-modal').style.display = 'none';
    });

    /* ── init ── */
    loadHeld();

})();
</script>
</body>
<?php llxFooter(); $db->close();
