<?php
/**
 * shifts_v2.php — Kafo POS v2 · إدارة الورديات
 * نسخة جديدة بتصميم v2 — لا تمس shifts.php الأصلية
 */
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML'))  define('NOREQUIREHTML', '1');
if (!defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX', '1');

require '../main.inc.php';
require_once __DIR__ . '/lib/takepos_help.php';
require_once __DIR__ . '/class/TakeposAccess.class.php';
require_once __DIR__ . '/class/TakeposAudit.class.php';
require_once __DIR__ . '/class/TakeposUserAccess.class.php';
require_once __DIR__ . '/class/TakeposStoreService.class.php';
require_once __DIR__ . '/class/TakeposTerminalService.class.php';
require_once __DIR__ . '/class/TakeposShiftService.class.php';
require_once __DIR__ . '/class/TakeposBranchService.class.php';

$langs->loadLangs(array('cashdesk', 'main', 'admin', 'takeposcustom@takepos'));

$terminal = isset($_SESSION['takeposterminal']) ? (string) $_SESSION['takeposterminal'] : '1';
TakeposAccess::requireFrontendAccess(
    $db, $user, 'takepos.shift_management', 'takepos.use',
    (int) $terminal, $langs->trans('TakeposShiftAccessDenied'), array('page' => 'shifts_v2.php')
);
TakeposAudit::logEvent($db, $user, 'shift_view_opened', TakeposAudit::SEVERITY_INFO, array('view' => 'v2'), 'Shift v2 page opened');

$entity   = !empty($user->entity) ? (int) $user->entity : 1;
$stores   = TakeposStoreService::listStores($db, $entity, true);

if (TakeposBranchService::isBranchUser($db, (int) $user->id)) {
    $branch = TakeposBranchService::getBranchByUserId($db, (int) $user->id);
    $terminals = $branch ? array_values(TakeposBranchService::getTerminalsForBranchId($db, (int) $branch->rowid)) : array();
} else {
    $terminals = TakeposTerminalService::listTerminals($db, $entity, 0, true, true);
}

$canReview     = !empty($user->admin) || TakeposUserAccess::userHasPermission($db, $user, 'takepos.shift.review');
$canForceClose = !empty($user->admin) || TakeposUserAccess::userHasPermission($db, $user, 'takepos.shift.force_close');

$FA  = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css';
$title = $langs->trans('TakeposShiftTitle');
$head  = '<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<link rel="stylesheet" href="' . $FA . '">';
$arrayofcss = array('/takepos/css/workspace_v2.css');
top_htmlhead($head, $title, 0, 0, array(), $arrayofcss);
?>
<body class="kfv2-body">
<?php
$v2PageTitle = $langs->trans('TakeposShiftPageTitle');
$v2PageIcon  = 'fa-business-time';
$v2PageSub   = $langs->trans('TakeposShiftManagement');
$v2BackUrl   = DOL_URL_ROOT . '/takepos/pos_v2.php';
require __DIR__ . '/topbar_v2.php';
?>

<div class="kfv2-page-body">

    <!-- hidden config -->
    <div id="kfv2-shift-cfg"
         data-token="<?php echo dol_escape_htmltag(newToken()); ?>"
         data-shift="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/ajax/shift.php'); ?>"
         data-cash="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/ajax/cash.php'); ?>"
         data-review="<?php echo $canReview ? '1' : '0'; ?>"
         data-force="<?php echo $canForceClose ? '1' : '0'; ?>"></div>

    <!-- Active Shift Hero -->
    <div id="kfv2-hero" class="kfv2-shift-hero">
        <div>
            <div class="ref" id="hero-ref"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonLoading')); ?></div>
            <div class="meta" id="hero-meta"></div>
        </div>
        <div class="kfv2-shift-stats" id="hero-stats"></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;flex-wrap:wrap">

        <!-- Open Shift -->
        <div class="kfv2-card">
            <div class="kfv2-card-head"><i class="fa-solid fa-door-open"></i> <?php echo dol_escape_htmltag($langs->trans('TakeposShiftOpenShift')); ?></div>
            <div class="kfv2-card-body">
                <div class="kfv2-form-grid" style="grid-template-columns:1fr 1fr">
                    <div class="kfv2-field">
                        <label><?php echo dol_escape_htmltag($langs->trans('TakeposShiftTerminal')); ?></label>
                        <select id="open_terminal_code">
                            <?php
                            $hasT = false;
                            foreach ($terminals as $t) {
                                $hasT = true;
                                $sel  = ((string) $t->terminal_code === strtoupper($terminal)) ? ' selected' : '';
                                echo '<option value="' . dol_escape_htmltag($t->terminal_code) . '"' . $sel . '>'
                                   . dol_escape_htmltag($t->terminal_code . ' - ' . $t->label) . '</option>';
                            }
                            if (!$hasT) {
                                echo '<option value="' . dol_escape_htmltag($terminal) . '">' . dol_escape_htmltag($langs->trans('TakeposShiftTerminal') . ' ' . $terminal) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="kfv2-field">
                        <label><?php echo dol_escape_htmltag($langs->trans('TakeposCommonStore')); ?></label>
                        <select id="open_store_id">
                            <option value=""><?php echo dol_escape_htmltag($langs->trans('TakeposShiftAutoFromTerminal')); ?></option>
                            <?php foreach ($stores as $s): ?>
                                <option value="<?php echo (int) $s->rowid; ?>"><?php echo dol_escape_htmltag($s->code . ' - ' . $s->label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="kfv2-field">
                        <label><?php echo dol_escape_htmltag($langs->trans('TakeposShiftOpeningFloat')); ?></label>
                        <input type="number" id="opening_float" min="0" step="0.01" value="0.00">
                    </div>
                    <div class="kfv2-field">
                        <label><?php echo dol_escape_htmltag($langs->trans('TakeposShiftOpenNote')); ?></label>
                        <input type="text" id="open_note" maxlength="255" placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposCommonOptional')); ?>">
                    </div>
                </div>
                <div class="kfv2-actions">
                    <button class="kfv2-btn kfv2-btn-primary" id="btn_open_shift">
                        <i class="fa-solid fa-door-open"></i> <?php echo dol_escape_htmltag($langs->trans('TakeposShiftOpenAction')); ?>
                    </button>
                </div>
                <div class="kfv2-msg" id="msg_open"></div>
            </div>
        </div>

        <!-- Cash Movement -->
        <div class="kfv2-card">
            <div class="kfv2-card-head"><i class="fa-solid fa-money-bill-transfer"></i> <?php echo dol_escape_htmltag($langs->trans('TakeposShiftCashMovement')); ?></div>
            <div class="kfv2-card-body">
                <div class="kfv2-form-grid" style="grid-template-columns:1fr 1fr">
                    <div class="kfv2-field">
                        <label><?php echo dol_escape_htmltag($langs->trans('TakeposShiftMovementType')); ?></label>
                        <select id="cash_movement_type">
                            <option value="paid_in"><?php echo dol_escape_htmltag($langs->trans('TakeposShiftPaidIn')); ?></option>
                            <option value="paid_out"><?php echo dol_escape_htmltag($langs->trans('TakeposShiftPaidOut')); ?></option>
                            <option value="safe_drop"><?php echo dol_escape_htmltag($langs->trans('TakeposShiftSafeDrop')); ?></option>
                        </select>
                    </div>
                    <div class="kfv2-field">
                        <label><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseAmount')); ?></label>
                        <input type="number" id="cash_movement_amount" min="0.01" step="0.01" value="0.00">
                    </div>
                    <div class="kfv2-field">
                        <label><?php echo dol_escape_htmltag($langs->trans('TakeposCommonReason')); ?></label>
                        <input type="text" id="cash_movement_reason" maxlength="64" placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposShiftReasonCodeText')); ?>">
                    </div>
                    <div class="kfv2-field">
                        <label><?php echo dol_escape_htmltag($langs->trans('TakeposCommonNote')); ?></label>
                        <input type="text" id="cash_movement_note" maxlength="255" placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposCommonOptional')); ?>">
                    </div>
                </div>
                <div class="kfv2-actions">
                    <button class="kfv2-btn kfv2-btn-outline" id="btn_add_movement">
                        <i class="fa-solid fa-floppy-disk"></i> <?php echo dol_escape_htmltag($langs->trans('TakeposShiftRecordMovement')); ?>
                    </button>
                </div>
                <div class="kfv2-msg" id="msg_movement"></div>
            </div>
        </div>

    </div>

    <!-- Close Shift -->
    <div class="kfv2-card">
        <div class="kfv2-card-head">
            <i class="fa-solid fa-door-closed"></i> <?php echo dol_escape_htmltag($langs->trans('TakeposShiftCloseShift')); ?>
            <div style="margin-inline-start:auto;display:flex;gap:10px">
                <button class="kfv2-btn kfv2-btn-sm kfv2-btn-success" id="btn_close_shift">
                    <i class="fa-solid fa-check"></i> <?php echo dol_escape_htmltag($langs->trans('TakeposShiftCloseActiveShift')); ?>
                </button>
                <?php if ($canForceClose): ?>
                <button class="kfv2-btn kfv2-btn-sm kfv2-btn-danger" id="btn_force_close_shift">
                    <i class="fa-solid fa-triangle-exclamation"></i> <?php echo dol_escape_htmltag($langs->trans('TakeposShiftForceClose')); ?>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="kfv2-card-body">
            <div class="kfv2-form-grid" style="grid-template-columns:1fr 1fr">
                <div class="kfv2-field">
                    <label><?php echo dol_escape_htmltag($langs->trans('TakeposShiftCountedCash')); ?></label>
                    <input type="number" id="counted_cash" min="0" step="0.01" value="0.00">
                </div>
                <div class="kfv2-field">
                    <label><?php echo dol_escape_htmltag($langs->trans('TakeposShiftCloseNote')); ?></label>
                    <input type="text" id="close_note" maxlength="255" placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposCommonOptional')); ?>">
                </div>
            </div>
            <div class="kfv2-msg" id="msg_close"></div>
        </div>
    </div>

    <!-- Shift List -->
    <?php if ($canReview): ?>
    <div class="kfv2-card">
        <div class="kfv2-card-head"><i class="fa-solid fa-list"></i> <?php echo dol_escape_htmltag($langs->trans('TakeposShiftList')); ?></div>
        <div class="kfv2-table-wrap">
            <table class="kfv2-table" id="shift_list_table">
                <thead><tr>
                    <th>#</th><th><?php echo dol_escape_htmltag($langs->trans('TakeposShiftRef')); ?></th>
                    <th><?php echo dol_escape_htmltag($langs->trans('TakeposCommonStatus')); ?></th>
                    <th><?php echo dol_escape_htmltag($langs->trans('TakeposReportsCashier')); ?></th>
                    <th><?php echo dol_escape_htmltag($langs->trans('TakeposShiftTerminal')); ?></th>
                    <th><?php echo dol_escape_htmltag($langs->trans('TakeposCommonStore')); ?></th>
                    <th><?php echo dol_escape_htmltag($langs->trans('TakeposShiftOpened')); ?></th>
                    <th><?php echo dol_escape_htmltag($langs->trans('TakeposShiftClosed')); ?></th>
                    <th class="num"><?php echo dol_escape_htmltag($langs->trans('TakeposShiftExpectedCash')); ?></th>
                    <th class="num"><?php echo dol_escape_htmltag($langs->trans('TakeposShiftCountedCash')); ?></th>
                    <th class="num"><?php echo dol_escape_htmltag($langs->trans('TakeposShiftDifference')); ?></th>
                    <th></th>
                </tr></thead>
                <tbody id="shift_list_body">
                    <tr class="empty-row"><td colspan="12"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonLoading')); ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /kfv2-page-body -->

<div class="kfv2-toast" id="kfv2-toast"></div>

<script>
(function () {
    'use strict';

    var cfg   = document.getElementById('kfv2-shift-cfg');
    var token = cfg.getAttribute('data-token') || '';
    var shiftEP = cfg.getAttribute('data-shift') || '';
    var cashEP  = cfg.getAttribute('data-cash')  || '';
    var canReview     = cfg.getAttribute('data-review') === '1';
    var canForceClose = cfg.getAttribute('data-force')  === '1';
    var activeShift   = null;

    var i18n = <?php echo json_encode(array(
        'noActive'          => $langs->trans('TakeposShiftNoActive'),
        'noActiveToClose'   => $langs->trans('TakeposShiftNoActiveToClose'),
        'terminal'          => $langs->trans('TakeposShiftTerminal'),
        'store'             => $langs->trans('TakeposCommonStore'),
        'opened'            => $langs->trans('TakeposShiftOpenedAt'),
        'opening'           => $langs->trans('TakeposShiftOpeningFloat'),
        'expected'          => $langs->trans('TakeposShiftExpectedCash'),
        'cashSales'         => $langs->trans('TakeposShiftTotalCashSales'),
        'cardSales'         => $langs->trans('TakeposShiftCardSales'),
        'confirmClose'      => $langs->trans('TakeposShiftConfirmClose'),
        'confirmForceClose' => $langs->trans('TakeposShiftConfirmForceClose'),
        'details'           => $langs->trans('TakeposShiftDetails'),
        'detailsUrl'        => DOL_URL_ROOT . '/takepos/shift_details.php?id=',
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    /* ── helpers ── */
    function $(id) { return document.getElementById(id); }
    function qs(p) {
        var u = new URLSearchParams();
        Object.keys(p || {}).forEach(function (k) { if (p[k] !== null && p[k] !== undefined && p[k] !== '') u.append(k, p[k]); });
        return u.toString();
    }
    function api(ep, p) {
        return fetch(ep + '?' + qs(p), { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.json(); });
    }
    function fmt(v) {
        var n = parseFloat(v || 0); if (!isFinite(n)) n = 0;
        return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function setMsg(id, msg, type) {
        var el = $(id); if (!el) return;
        el.className = 'kfv2-msg ' + (type || 'info');
        el.textContent = msg || '';
    }
    var toastTimer;
    function toast(msg, type) {
        var el = $('kfv2-toast'); if (!el) return;
        el.textContent = msg;
        el.style.background = type === 'error' ? '#dc2626' : type === 'success' ? '#16a34a' : '#0f1d33';
        el.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { el.classList.remove('show'); }, 2600);
    }

    /* ── hero render ── */
    function renderHero() {
        var refEl   = $('hero-ref');
        var metaEl  = $('hero-meta');
        var statsEl = $('hero-stats');
        if (!refEl) return;
        if (!activeShift) {
            refEl.innerHTML = '<span style="font-size:16px;color:var(--text-2)">' + i18n.noActive + '</span>';
            metaEl.innerHTML = '';
            statsEl.innerHTML = '';
            return;
        }
        var stateHtml = '<span class="state"><i class="fa-solid fa-circle"></i> ' + (activeShift.status || '') + '</span>';
        refEl.innerHTML = activeShift.shift_ref + stateHtml;
        metaEl.innerHTML = i18n.terminal + ': <b>' + (activeShift.terminal_code || '') + '</b> &nbsp;·&nbsp; '
            + i18n.store   + ': <b>' + (activeShift.store_label  || '—') + '</b> &nbsp;·&nbsp; '
            + i18n.opened  + ': <b>' + (activeShift.date_open    || '—') + '</b>';
        statsEl.innerHTML = [
            [fmt(activeShift.opening_float),    i18n.opening],
            [fmt(activeShift.expected_cash),     i18n.expected],
            [fmt(activeShift.total_cash_sales),  i18n.cashSales],
            [fmt(activeShift.total_card_sales),  i18n.cardSales],
        ].map(function (s) {
            return '<div><div class="sv num">' + s[0] + '</div><div class="sk">' + s[1] + '</div></div>';
        }).join('');
    }

    /* ── load active shift ── */
    function loadActive() {
        return api(shiftEP, { action: 'active' }).then(function (res) {
            activeShift = (res && res.success) ? (res.data || null) : null;
            renderHero();
        }).catch(function () { activeShift = null; renderHero(); });
    }

    /* ── load list ── */
    function loadList() {
        if (!canReview) return;
        api(shiftEP, { action: 'list' }).then(function (res) {
            var tbody = $('shift_list_body'); if (!tbody) return;
            if (!res || !res.success || !(res.rows || []).length) {
                tbody.innerHTML = '<tr class="empty-row"><td colspan="12">—</td></tr>';
                return;
            }
            tbody.innerHTML = res.rows.map(function (r) {
                var isOpen  = r.status === 'open';
                var diff    = parseFloat(r.cash_difference || 0);
                var diffCls = diff < 0 ? ' neg' : '';
                return '<tr>'
                    + '<td class="num">' + (r.rowid || '') + '</td>'
                    + '<td class="num">' + (r.shift_ref || '') + '</td>'
                    + '<td><span class="kfv2-pill ' + (isOpen ? 'open' : 'closed') + '">'
                    +   '<i class="fa-solid fa-circle"></i>' + (r.status || '') + '</span></td>'
                    + '<td>' + (r.cashier_login || '') + '</td>'
                    + '<td>' + (r.terminal_code  || '') + '</td>'
                    + '<td>' + (r.store_label    || '') + '</td>'
                    + '<td class="num">' + (r.date_open   || '') + '</td>'
                    + '<td class="num">' + (r.date_close  || '—') + '</td>'
                    + '<td class="num">' + fmt(r.expected_cash)  + '</td>'
                    + '<td class="num">' + fmt(r.counted_cash)   + '</td>'
                    + '<td class="num' + diffCls + '">' + fmt(diff) + '</td>'
                    + '<td><a class="kfv2-lnk" href="' + i18n.detailsUrl + encodeURIComponent(r.rowid) + '">'
                    +   '<i class="fa-solid fa-eye"></i> ' + i18n.details + '</a></td>'
                    + '</tr>';
            }).join('');
        });
    }

    /* ── open shift ── */
    $('btn_open_shift').addEventListener('click', function () {
        api(shiftEP, {
            action: 'open', token: token,
            terminal_code: $('open_terminal_code').value,
            store_id:      $('open_store_id').value,
            opening_float: $('opening_float').value,
            note:          $('open_note').value,
        }).then(function (res) {
            setMsg('msg_open', res && res.message, res && res.success ? 'success' : 'error');
            toast(res && res.message, res && res.success ? 'success' : 'error');
            if (res && res.success) $('open_note').value = '';
            loadActive().then(loadList);
        });
    });

    /* ── cash movement ── */
    $('btn_add_movement').addEventListener('click', function () {
        api(cashEP, {
            action:        'create_movement', token: token,
            shift_id:      activeShift && activeShift.shift_id ? activeShift.shift_id : '',
            movement_type: $('cash_movement_type').value,
            amount:        $('cash_movement_amount').value,
            reason:        $('cash_movement_reason').value,
            note:          $('cash_movement_note').value,
        }).then(function (res) {
            setMsg('msg_movement', res && res.message, res && res.success ? 'success' : 'error');
            toast(res && res.message, res && res.success ? 'success' : 'error');
            loadActive();
        });
    });

    /* ── close shift ── */
    $('btn_close_shift').addEventListener('click', function () {
        if (!activeShift || !activeShift.shift_id) { toast(i18n.noActiveToClose, 'error'); return; }
        if (!confirm(i18n.confirmClose)) return;
        api(shiftEP, {
            action: 'close', token: token,
            shift_id:     activeShift.shift_id,
            counted_cash: $('counted_cash').value,
            note:         $('close_note').value,
        }).then(function (res) {
            setMsg('msg_close', res && res.message, res && res.success ? 'success' : 'error');
            toast(res && res.message, res && res.success ? 'success' : 'error');
            loadActive().then(loadList);
        });
    });

    /* ── force close ── */
    <?php if ($canForceClose): ?>
    $('btn_force_close_shift').addEventListener('click', function () {
        if (!activeShift || !activeShift.shift_id) { toast(i18n.noActiveToClose, 'error'); return; }
        if (!confirm(i18n.confirmForceClose)) return;
        api(shiftEP, {
            action: 'force_close', token: token,
            shift_id: activeShift.shift_id,
            note:     $('close_note').value,
        }).then(function (res) {
            setMsg('msg_close', res && res.message, res && res.success ? 'success' : 'error');
            toast(res && res.message, res && res.success ? 'success' : 'error');
            loadActive().then(loadList);
        });
    });
    <?php endif; ?>

    /* ── preset movement type from URL ── */
    try {
        var mt = new URLSearchParams(window.location.search).get('movement_type') || '';
        if (['paid_in','paid_out','safe_drop'].indexOf(mt) >= 0) {
            $('cash_movement_type').value = mt;
            setTimeout(function () {
                $('cash_movement_amount').focus();
                $('cash_movement_amount').select();
            }, 150);
        }
    } catch (e) {}

    /* ── init ── */
    loadActive().then(loadList);
})();
</script>
<?php echo takeposHelpRender($langs, __FILE__); ?>
</body>
<?php llxFooter(); $db->close();
