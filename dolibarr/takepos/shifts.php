<?php
/**
 * Shift operations page.
 */
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
}

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
    $db,
    $user,
    'takepos.shift_management',
    'takepos.use',
    (int) $terminal,
    $langs->trans('TakeposShiftAccessDenied'),
    array('page' => 'shifts.php')
);

TakeposAudit::logEvent($db, $user, 'shift_view_opened', TakeposAudit::SEVERITY_INFO, array('view' => 'page'), 'Shift page opened');

$entity = !empty($user->entity) ? (int) $user->entity : 1;
//echo '<!-- DEBUG user=' . $user->id . ' login=' . $user->login . ' isBranch=' . (TakeposBranchService::isBranchUser($db, (int)$user->id) ? 'YES' : 'NO') . ' -->';

$stores = TakeposStoreService::listStores($db, $entity, true);

// For branch users — only show their own branch terminals
if (TakeposBranchService::isBranchUser($db, (int) $user->id)) {
    $branch = TakeposBranchService::getBranchByUserId($db, (int) $user->id);
    if ($branch) {
        $branchTerms = TakeposBranchService::getTerminalsForBranchId($db, (int) $branch->rowid);
        $terminals = array_values($branchTerms);
    } else {
        $terminals = array();
    }
} else {
    // FIX (shift-master-v1): Master/admin users only see NON-branch terminals.
    // Branch terminals (fk_branch IS NOT NULL) are managed exclusively by branch
    // users — showing them in the master dropdown caused master admins to
    // accidentally open shifts on branch terminals, which then blocked all
    // master terminal sales with "Active shift is open on another terminal."
    $terminals = TakeposTerminalService::listTerminals($db, $entity, 0, true, true);
}
$canReview = !empty($user->admin) || TakeposUserAccess::userHasPermission($db, $user, 'takepos.shift.review');
$canForceClose = !empty($user->admin) || TakeposUserAccess::userHasPermission($db, $user, 'takepos.shift.force_close');

$title = $langs->trans('TakeposShiftTitle');
$head = '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
$arrayofcss = array('/takepos/css/workspace.css');
$disablejs = 0;
$disablehead = 0;
top_htmlhead($head, $title, $disablejs, $disablehead, array(), $arrayofcss);
?>
    <body class="takepos-workspace-reports-body">
    <div class="takepos-workspace-reports-page">
        <h2 class="takepos-workspace-title"><?php echo dol_escape_htmltag($langs->trans('TakeposShiftPageTitle')); ?></h2>

        <div id="takepos-shift-config"
             data-token="<?php echo dol_escape_htmltag(newToken()); ?>"
             data-shift-endpoint="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/ajax/shift.php'); ?>"
             data-cash-endpoint="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/ajax/cash.php'); ?>"
             data-can-review="<?php echo $canReview ? '1' : '0'; ?>"
             data-can-force-close="<?php echo $canForceClose ? '1' : '0'; ?>"></div>

        <section class="takepos-workspace-panel">
            <h3><?php echo dol_escape_htmltag($langs->trans('TakeposShiftActiveShift')); ?></h3>
            <div id="active_shift_box" class="takepos-workspace-card" style="max-width:none;">
                <div class="takepos-workspace-card-label"><?php echo dol_escape_htmltag($langs->trans('TakeposShiftCurrentState')); ?></div>
                <div class="takepos-workspace-card-value" id="active_shift_status"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonLoading')); ?></div>
                <div id="active_shift_meta" style="margin-top:10px;font-size:13px;"></div>
            </div>
        </section>

        <section class="takepos-workspace-panel">
            <h3><?php echo dol_escape_htmltag($langs->trans('TakeposShiftOpenShift')); ?></h3>
            <div class="takepos-workspace-filter-grid">
                <div>
                    <label for="open_terminal_code"><?php echo dol_escape_htmltag($langs->trans('TakeposShiftTerminal')); ?></label>
                    <select id="open_terminal_code">
                        <?php
                        $hasTerminal = false;
                        foreach ($terminals as $t) {
                            $hasTerminal = true;
                            $sel = ((string) $t->terminal_code === strtoupper($terminal) ? ' selected' : '');
                            print '<option value="' . dol_escape_htmltag($t->terminal_code) . '"' . $sel . '>' . dol_escape_htmltag($t->terminal_code . ' - ' . $t->label) . '</option>';
                        }
                        if (!$hasTerminal) {
                            print '<option value="' . dol_escape_htmltag($terminal) . '">' . dol_escape_htmltag($langs->trans('TakeposShiftTerminal') . ' ' . $terminal) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div>
                    <label for="open_store_id"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonStore')); ?></label>
                    <select id="open_store_id">
                        <option value=""><?php echo dol_escape_htmltag($langs->trans('TakeposShiftAutoFromTerminal')); ?></option>
                        <?php foreach ($stores as $s) { ?>
                            <option value="<?php echo (int) $s->rowid; ?>"><?php echo dol_escape_htmltag($s->code . ' - ' . $s->label); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div>
                    <label for="opening_float"><?php echo dol_escape_htmltag($langs->trans('TakeposShiftOpeningFloat')); ?></label>
                    <input type="number" id="opening_float" min="0" step="0.01" value="0.00">
                </div>
                <div>
                    <label for="open_note"><?php echo dol_escape_htmltag($langs->trans('TakeposShiftOpenNote')); ?></label>
                    <input type="text" id="open_note" maxlength="255" placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposCommonOptional')); ?>">
                </div>
            </div>
            <div class="takepos-workspace-filter-actions">
                <button type="button" id="btn_open_shift" class="button button-save"><?php echo dol_escape_htmltag($langs->trans('TakeposShiftOpenAction')); ?></button>
            </div>
        </section>

        <section class="takepos-workspace-panel">
            <h3><?php echo dol_escape_htmltag($langs->trans('TakeposShiftCashMovement')); ?></h3>
            <div class="takepos-workspace-filter-grid">
                <div>
                    <label for="cash_movement_type"><?php echo dol_escape_htmltag($langs->trans('TakeposShiftMovementType')); ?></label>
                    <select id="cash_movement_type">
                        <option value="paid_in"><?php echo dol_escape_htmltag($langs->trans('TakeposShiftPaidIn')); ?></option>
                        <option value="paid_out"><?php echo dol_escape_htmltag($langs->trans('TakeposShiftPaidOut')); ?></option>
                        <option value="safe_drop"><?php echo dol_escape_htmltag($langs->trans('TakeposShiftSafeDrop')); ?></option>
                    </select>
                </div>
                <div>
                    <label for="cash_movement_amount"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseAmount')); ?></label>
                    <input type="number" id="cash_movement_amount" min="0.01" step="0.01" value="0.00">
                </div>
                <div>
                    <label for="cash_movement_reason"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonReason')); ?></label>
                    <input type="text" id="cash_movement_reason" maxlength="64" placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposShiftReasonCodeText')); ?>">
                </div>
                <div>
                    <label for="cash_movement_note"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonNote')); ?></label>
                    <input type="text" id="cash_movement_note" maxlength="255" placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposCommonOptional')); ?>">
                </div>
            </div>
            <div class="takepos-workspace-filter-actions">
                <button type="button" id="btn_add_movement" class="button"><?php echo dol_escape_htmltag($langs->trans('TakeposShiftRecordMovement')); ?></button>
            </div>
            <div id="cash_movement_msg" style="margin-top:8px;"></div>
        </section>

        <section class="takepos-workspace-panel">
            <h3><?php echo dol_escape_htmltag($langs->trans('TakeposShiftCloseShift')); ?></h3>
            <div class="takepos-workspace-filter-grid">
                <div>
                    <label for="counted_cash"><?php echo dol_escape_htmltag($langs->trans('TakeposShiftCountedCash')); ?></label>
                    <input type="number" id="counted_cash" min="0" step="0.01" value="0.00">
                </div>
                <div>
                    <label for="close_note"><?php echo dol_escape_htmltag($langs->trans('TakeposShiftCloseNote')); ?></label>
                    <input type="text" id="close_note" maxlength="255" placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposCommonOptional')); ?>">
                </div>
            </div>
            <div class="takepos-workspace-filter-actions">
                <button type="button" id="btn_close_shift" class="button button-save"><?php echo dol_escape_htmltag($langs->trans('TakeposShiftCloseActiveShift')); ?></button>
                <?php if ($canForceClose) { ?>
                    <button type="button" id="btn_force_close_shift" class="button button-cancel"><?php echo dol_escape_htmltag($langs->trans('TakeposShiftForceClose')); ?></button>
                <?php } ?>
            </div>
            <div id="shift_close_msg" style="margin-top:8px;"></div>
        </section>

        <?php if ($canReview) { ?>
            <section class="takepos-workspace-panel">
                <h3><?php echo dol_escape_htmltag($langs->trans('TakeposShiftList')); ?></h3>
                <div class="takepos-workspace-table-wrap">
                    <table id="shift_list_table" class="takepos-workspace-table"></table>
                </div>
            </section>
        <?php } ?>
    </div>

    <script>
        (function () {
            'use strict';
            var cfg = document.getElementById('takepos-shift-config');
            if (!cfg) return;

            var token = cfg.getAttribute('data-token') || '';
            var shiftEndpoint = cfg.getAttribute('data-shift-endpoint') || '';
            var cashEndpoint = cfg.getAttribute('data-cash-endpoint') || '';
            var canReview = cfg.getAttribute('data-can-review') === '1';
            var canForceClose = cfg.getAttribute('data-can-force-close') === '1';
            var activeShift = null;
            var presetMovementType = '';
            try {
                var pageParams = new URLSearchParams(window.location.search || '');
                var requestedMovementType = String(pageParams.get('movement_type') || '').toLowerCase();
                if (requestedMovementType === 'paid_in' || requestedMovementType === 'paid_out' || requestedMovementType === 'safe_drop') {
                    presetMovementType = requestedMovementType;
                }
            } catch (e) {
                presetMovementType = '';
            }
            var i18n = <?php echo json_encode(array(
                'noActive' => $langs->trans('TakeposShiftNoActive'),
                'paymentRequired' => $langs->trans('TakeposShiftPaymentRequired'),
                'terminal' => $langs->trans('TakeposShiftTerminal'),
                'store' => $langs->trans('TakeposShiftStore'),
                'opened' => $langs->trans('TakeposShiftOpenedAt'),
                'opening' => $langs->trans('TakeposShiftOpeningFloat'),
                'expected' => $langs->trans('TakeposShiftExpectedCash'),
                'cashSales' => $langs->trans('TakeposShiftTotalCashSales'),
                'cardSales' => $langs->trans('TakeposShiftCardSales'),
                'loadListFailed' => $langs->trans('TakeposShiftLoadListFailed'),
                'id' => $langs->trans('TakeposLoyaltyId'),
                'ref' => $langs->trans('TakeposShiftRef'),
                'status' => $langs->trans('TakeposCommonStatus'),
                'cashier' => $langs->trans('TakeposReportsCashier'),
                'terminalHeader' => $langs->trans('TakeposShiftTerminal'),
                'storeHeader' => $langs->trans('TakeposCommonStore'),
                'openHeader' => $langs->trans('TakeposShiftOpened'),
                'closeHeader' => $langs->trans('TakeposShiftClosed'),
                'expectedHeader' => $langs->trans('TakeposShiftExpectedCash'),
                'countedHeader' => $langs->trans('TakeposShiftCountedCash'),
                'diffHeader' => $langs->trans('TakeposShiftDifference'),
                'details' => $langs->trans('TakeposShiftDetails'),
                'openFailed' => $langs->trans('TakeposShiftOpenFailedMessage'),
                'movementFailed' => $langs->trans('TakeposShiftMovementFailedMessage'),
                'noActiveToClose' => $langs->trans('TakeposShiftNoActiveToClose'),
                'closeFailed' => $langs->trans('TakeposShiftCloseFailed'),
                'forceCloseFailed' => $langs->trans('TakeposShiftForceCloseFailedMessage'),
                'activeOtherTerminal' => $langs->trans('TakeposShiftActiveOnAnotherTerminal'),
                'activeOtherTerminalFallback' => $langs->trans('TakeposShiftActiveOnAnotherTerminalFallback'),
                'confirmClose' => $langs->trans('TakeposShiftConfirmClose'),
                'confirmForceClose' => $langs->trans('TakeposShiftConfirmForceClose')
            ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

            function byId(id) { return document.getElementById(id); }

            function qs(params) {
                var usp = new URLSearchParams();
                Object.keys(params || {}).forEach(function (k) {
                    if (params[k] !== null && params[k] !== undefined && params[k] !== '') usp.append(k, params[k]);
                });
                return usp.toString();
            }

            function callJson(url, params) {
                return fetch(url + '?' + qs(params || {}), { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.json(); });
            }

            function fmt(v) {
                var n = parseFloat(v || 0);
                if (!isFinite(n)) n = 0;
                return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function setMsg(nodeId, message, ok) {
                var el = byId(nodeId);
                if (!el) return;
                el.style.color = ok ? '#2d7d2d' : '#a94442';
                el.textContent = message || '';
            }

            function renderActive() {
                var statusNode = byId('active_shift_status');
                var metaNode = byId('active_shift_meta');
                if (!statusNode || !metaNode) return;

                if (!activeShift) {
                    statusNode.textContent = i18n.noActive;
                    metaNode.innerHTML = i18n.paymentRequired;
                    return;
                }

                statusNode.textContent = activeShift.shift_ref + ' (' + activeShift.status + ')';
                if (String(activeShift.is_fallback_cashier_shift || '0') === '1') {
                    statusNode.textContent += ' - ' + (i18n.activeOtherTerminal || i18n.activeOtherTerminalFallback);
                }
                var html = '';
                html += i18n.terminal + ': <strong>' + (activeShift.terminal_code || '') + '</strong> | ';
                html += i18n.store + ': <strong>' + (activeShift.store_label || '-') + '</strong> | ';
                html += i18n.opened + ': <strong>' + (activeShift.date_open || '') + '</strong><br>';
                html += i18n.opening + ': <strong>' + fmt(activeShift.opening_float) + '</strong> | ';
                html += i18n.expected + ': <strong>' + fmt(activeShift.expected_cash) + '</strong> | ';
                html += i18n.cashSales + ': <strong>' + fmt(activeShift.total_cash_sales) + '</strong> | ';
                html += i18n.cardSales + ': <strong>' + fmt(activeShift.total_card_sales) + '</strong>';
                if (String(activeShift.is_fallback_cashier_shift || '0') === '1' && activeShift.terminal_code) {
                    var termSelect = byId('open_terminal_code');
                    if (termSelect) termSelect.value = activeShift.terminal_code;
                }
                metaNode.innerHTML = html;
            }

            function loadActive() {
                return callJson(shiftEndpoint, { action: 'active' }).then(function (res) {
                    activeShift = (res && res.success) ? (res.data || null) : null;
                    renderActive();
                    return activeShift;
                }).catch(function () {
                    activeShift = null;
                    renderActive();
                });
            }

            function loadList() {
                if (!canReview) return;
                callJson(shiftEndpoint, { action: 'list' }).then(function (res) {
                    var table = byId('shift_list_table');
                    if (!table) return;
                    if (!res || !res.success) {
                        table.innerHTML = '<tbody><tr><td>' + i18n.loadListFailed + '</td></tr></tbody>';
                        return;
                    }
                    var rows = res.rows || [];
                    var html = '<thead><tr>'
                        + '<th>' + i18n.id + '</th><th>' + i18n.ref + '</th><th>' + i18n.status + '</th><th>' + i18n.cashier + '</th><th>' + i18n.terminalHeader + '</th><th>' + i18n.storeHeader + '</th><th>' + i18n.openHeader + '</th><th>' + i18n.closeHeader + '</th><th>' + i18n.expectedHeader + '</th><th>' + i18n.countedHeader + '</th><th>' + i18n.diffHeader + '</th><th>' + i18n.details + '</th>'
                        + '</tr></thead><tbody>';
                    rows.forEach(function (r) {
                        html += '<tr>';
                        html += '<td>' + (r.rowid || '') + '</td>';
                        html += '<td>' + (r.shift_ref || '') + '</td>';
                        html += '<td>' + (r.status || '') + '</td>';
                        html += '<td>' + (r.cashier_login || '') + '</td>';
                        html += '<td>' + (r.terminal_code || '') + '</td>';
                        html += '<td>' + (r.store_label || '') + '</td>';
                        html += '<td>' + (r.date_open || '') + '</td>';
                        html += '<td>' + (r.date_close || '') + '</td>';
                        html += '<td class="num">' + fmt(r.expected_cash) + '</td>';
                        html += '<td class="num">' + fmt(r.counted_cash) + '</td>';
                        html += '<td class="num">' + fmt(r.cash_difference) + '</td>';
                        html += '<td><a class="button" href="<?php echo dol_escape_js(DOL_URL_ROOT . '/takepos/shift_details.php?id='); ?>' + encodeURIComponent(r.rowid) + '">' + i18n.details + '</a></td>';
                        html += '</tr>';
                    });
                    html += '</tbody>';
                    table.innerHTML = html;
                });
            }

            if (presetMovementType && byId('cash_movement_type')) {
                byId('cash_movement_type').value = presetMovementType;
                setTimeout(function () {
                    var amountField = byId('cash_movement_amount');
                    if (amountField) {
                        amountField.focus();
                        amountField.select();
                    }
                }, 150);
            }

            byId('btn_open_shift').addEventListener('click', function () {
                callJson(shiftEndpoint, {
                    action: 'open',
                    token: token,
                    terminal_code: byId('open_terminal_code').value,
                    store_id: byId('open_store_id').value,
                    opening_float: byId('opening_float').value,
                    note: byId('open_note').value
                }).then(function (res) {
                    setMsg('shift_close_msg', (res && res.message) ? res.message : i18n.openFailed, !!(res && res.success));
                    if (res && res.success) {
                        if (window.parent && typeof window.parent.TakeposOnShiftOpened === 'function') {
                            try { window.parent.TakeposOnShiftOpened(res.shift || null); } catch (e) {}
                        }
                        byId('open_note').value = '';
                    }
                    loadActive().then(loadList);
                });
            });

            byId('btn_add_movement').addEventListener('click', function () {
                var shiftId = activeShift && activeShift.shift_id ? activeShift.shift_id : '';
                callJson(cashEndpoint, {
                    action: 'create_movement',
                    token: token,
                    shift_id: shiftId,
                    movement_type: byId('cash_movement_type').value,
                    amount: byId('cash_movement_amount').value,
                    reason: byId('cash_movement_reason').value,
                    note: byId('cash_movement_note').value
                }).then(function (res) {
                    setMsg('cash_movement_msg', (res && res.message) ? res.message : i18n.movementFailed, !!(res && res.success));
                    loadActive();
                });
            });

            byId('btn_close_shift').addEventListener('click', function () {
                var shiftId = activeShift && activeShift.shift_id ? activeShift.shift_id : '';
                if (!shiftId) {
                    setMsg('shift_close_msg', i18n.noActiveToClose, false);
                    return;
                }
                if (!confirm(i18n.confirmClose)) {
                    return;
                }
                callJson(shiftEndpoint, {
                    action: 'close',
                    token: token,
                    shift_id: shiftId,
                    counted_cash: byId('counted_cash').value,
                    note: byId('close_note').value
                }).then(function (res) {
                    setMsg('shift_close_msg', (res && res.message) ? res.message : i18n.closeFailed, !!(res && res.success));
                    if (res && res.success && window.parent && typeof window.parent.TakeposOnShiftClosed === 'function') {
                        try { window.parent.TakeposOnShiftClosed(res.shift || null); } catch (e) {}
                    }
                    loadActive().then(loadList);
                });
            });

            if (canForceClose) {
                byId('btn_force_close_shift').addEventListener('click', function () {
                    var shiftId = activeShift && activeShift.shift_id ? activeShift.shift_id : '';
                    if (!shiftId) {
                        setMsg('shift_close_msg', i18n.noActiveToClose, false);
                        return;
                    }
                    if (!confirm(i18n.confirmForceClose)) {
                        return;
                    }
                    callJson(shiftEndpoint, {
                        action: 'force_close',
                        token: token,
                        shift_id: shiftId,
                        note: byId('close_note').value
                    }).then(function (res) {
                        setMsg('shift_close_msg', (res && res.message) ? res.message : i18n.forceCloseFailed, !!(res && res.success));
                        if (res && res.success && window.parent && typeof window.parent.TakeposOnShiftClosed === 'function') {
                            try { window.parent.TakeposOnShiftClosed(res.shift || null); } catch (e) {}
                        }
                        loadActive().then(loadList);
                    });
                });
            }

            loadActive().then(loadList);
        })();
    </script>
    <?php echo takeposHelpRender($langs, __FILE__); ?>
    </body>
<?php
llxFooter();
$db->close();