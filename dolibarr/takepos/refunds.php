<?php
/**
 * Refunds / returns desk.
 */
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');
if (!defined('NOREQUIREAJAX')) define('NOREQUIREAJAX', '1');

require '../main.inc.php';
require_once __DIR__ . '/lib/takepos_help.php';
require_once __DIR__ . '/class/TakeposAccess.class.php';
require_once __DIR__ . '/class/TakeposAudit.class.php';
require_once __DIR__ . '/class/TakeposUserAccess.class.php';

$langs->loadLangs(array('cashdesk', 'main', 'bills', 'takeposcustom@takepos'));

$terminal = isset($_SESSION['takeposterminal']) ? (string) $_SESSION['takeposterminal'] : '1';
$refundAccessDeniedMessage = $langs->trans('TakeposRefundAccessDenied');
$canAccessRefundDesk = (!empty($user->admin)
    || TakeposUserAccess::userHasAnyPermission($db, $user, array('takepos.refund.view', 'takepos.refund.partial', 'takepos.refund.full')));
if (!$canAccessRefundDesk) {
    accessforbidden($refundAccessDeniedMessage);
}
TakeposAccess::requireFeature($db, 'takepos.returns', $user, false, array('page' => 'refunds.php', 'feature' => 'takepos.returns'));
TakeposAccess::requireFrontendAccess(
    $db,
    $user,
    'takepos.refunds',
    'takepos.use',
    (int) $terminal,
    $refundAccessDeniedMessage,
    array('page' => 'refunds.php')
);

TakeposAudit::logEvent($db, $user, 'refund_report_opened', TakeposAudit::SEVERITY_INFO, array('view' => 'refunds_desk'), 'Refund desk opened');

$title = $langs->trans('TakeposRefundTitle');
$head = '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
$arrayofcss = array('/takepos/css/workspace.css');
top_htmlhead($head, $title, 0, 0, array(), $arrayofcss);
?>
    <body class="takepos-workspace-reports-body">
    <div class="takepos-workspace-reports-page">
        <h2 class="takepos-workspace-title"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundPageTitle')); ?></h2>

        <div id="takepos-refund-config"
             data-token="<?php echo dol_escape_htmltag(newToken()); ?>"
             data-endpoint="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/ajax/refund.php'); ?>"
             data-details-url="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/refund_details.php?id='); ?>"
             data-receipt-url="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/refund_receipt.php?id='); ?>"></div>

        <section class="takepos-workspace-panel">
            <h3><?php echo dol_escape_htmltag($langs->trans('TakeposRefundInvoiceLookup')); ?></h3>
            <div class="takepos-workspace-filter-grid">
                <div><label for="lookup_invoice_id"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundInvoiceId')); ?></label><input type="text" id="lookup_invoice_id" maxlength="64" placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposRefundInvoiceIdPlaceholder') !== 'TakeposRefundInvoiceIdPlaceholder' ? $langs->trans('TakeposRefundInvoiceIdPlaceholder') : 'e.g. 159 or 0091'); ?>"></div>
                <div><label for="lookup_invoice_ref"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundInvoiceRef')); ?></label><input type="text" id="lookup_invoice_ref" maxlength="64" placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposRefundInvoiceRefPlaceholder') !== 'TakeposRefundInvoiceRefPlaceholder' ? $langs->trans('TakeposRefundInvoiceRefPlaceholder') : 'e.g. TC1-2605-0091'); ?>"></div>
                <div><label for="lookup_date_from"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundDateFrom')); ?></label><input type="date" id="lookup_date_from"></div>
                <div><label for="lookup_date_to"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundDateTo')); ?></label><input type="date" id="lookup_date_to"></div>
            </div>
            <div class="takepos-workspace-filter-actions">
                <button type="button" id="btn_lookup" class="button button-save"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundSearchInvoices')); ?></button>
            </div>
            <div class="takepos-workspace-table-wrap"><table id="table_lookup" class="takepos-workspace-table"></table></div>
        </section>

        <section class="takepos-workspace-panel">
            <h3><?php echo dol_escape_htmltag($langs->trans('TakeposRefundWizard')); ?></h3>
            <div id="refund_invoice_meta" style="margin-bottom:10px;"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundSelectInvoice')); ?></div>
            <div class="takepos-workspace-table-wrap"><table id="table_lines" class="takepos-workspace-table"></table></div>

            <div class="takepos-workspace-filter-grid" style="margin-top:10px;">
                <div>
                    <label for="refund_type"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundType')); ?></label>
                    <select id="refund_type">
                        <option value="partial"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundPartial')); ?></option>
                        <option value="full"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundFull')); ?></option>
                    </select>
                </div>
                <div>
                    <label for="refund_payment_method"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundPaymentMethod')); ?></label>
                    <select id="refund_payment_method">
                        <option value="CASH"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundCash')); ?></option>
                        <option value="CB"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundCard')); ?></option>
                        <option value="OTHER"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundOther')); ?></option>
                    </select>
                </div>
                <div>
                    <label for="refund_reason"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundReason')); ?></label>
                    <select id="refund_reason"></select>
                </div>
                <div>
                    <label for="refund_note"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundNote')); ?></label>
                    <input type="text" id="refund_note" maxlength="255">
                </div>
            </div>

            <div class="takepos-workspace-filter-grid" style="margin-top:10px;">
                <div><label for="manager_login"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundManagerLogin')); ?></label><input type="text" id="manager_login" maxlength="128"></div>
                <div><label for="manager_password"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundManagerPassword')); ?></label><input type="password" id="manager_password" maxlength="128"></div>
                <div><label for="manager_barcode"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundManagerBarcode')); ?></label><input type="text" id="manager_barcode" maxlength="128"></div>
                <div><label for="refund_restock_default"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundDefaultRestock')); ?></label><select id="refund_restock_default"><option value="0"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonNo')); ?></option><option value="1"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonYes')); ?></option></select></div>
            </div>

            <div class="takepos-workspace-filter-actions">
                <button type="button" id="btn_process_refund" class="button button-save"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundProcess')); ?></button>
            </div>
            <div id="refund_msg"></div>
        </section>

        <section class="takepos-workspace-panel">
            <h3><?php echo dol_escape_htmltag($langs->trans('TakeposRefundRecentRefunds')); ?></h3>
            <div class="takepos-workspace-filter-actions">
                <button type="button" id="btn_refresh_refunds" class="button"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundRefresh')); ?></button>
                <button type="button" id="btn_export_refunds" class="button"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundExportCsv')); ?></button>
            </div>
            <div class="takepos-workspace-table-wrap"><table id="table_refunds" class="takepos-workspace-table"></table></div>
        </section>
    </div>

    <script>
        (function () {
            'use strict';
            var cfg = document.getElementById('takepos-refund-config');
            if (!cfg) return;

            var token = cfg.getAttribute('data-token') || '';
            var endpoint = cfg.getAttribute('data-endpoint') || '';
            var detailsUrl = cfg.getAttribute('data-details-url') || '';
            var receiptUrl = cfg.getAttribute('data-receipt-url') || '';
            var selectedInvoiceId = 0;
            var selectedLines = [];
            var i18n = <?php echo json_encode(array(
                'id' => $langs->trans('TakeposLoyaltyId'),
                'ref' => $langs->trans('TakeposRefundInvoiceRef'),
                'date' => $langs->trans('TakeposLoyaltyDate'),
                'customer' => $langs->trans('TakeposRefundCustomer'),
                'total' => $langs->trans('TakeposRefundAmount'),
                'store' => $langs->trans('TakeposCommonStore'),
                'terminal' => $langs->trans('TakeposCommonTerminal'),
                'action' => $langs->trans('TakeposRefundAction'),
                'select' => $langs->trans('TakeposCommonSelect'),
                'loadLinesFailed' => $langs->trans('TakeposRefundLoadLinesFailed'),
                'invoiceMeta' => $langs->trans('TakeposRefundInvoiceMeta'),
                'lineId' => $langs->trans('TakeposRefundLineId'),
                'label' => $langs->trans('TakeposCommonLabel'),
                'sold' => $langs->trans('TakeposRefundSold'),
                'alreadyRefunded' => $langs->trans('TakeposRefundAlreadyRefunded'),
                'refundable' => $langs->trans('TakeposRefundRefundable'),
                'refundQty' => $langs->trans('TakeposRefundRefundQty'),
                'unitTtc' => $langs->trans('TakeposRefundUnitTtc'),
                'restock' => $langs->trans('TakeposRefundRestock'),
                'loadRefundsFailed' => $langs->trans('TakeposRefundLoadRefundsFailed'),
                'type' => $langs->trans('TakeposCommonType'),
                'invoice' => $langs->trans('TakeposRefundInvoice'),
                'amount' => $langs->trans('TakeposRefundAmount'),
                'payment' => $langs->trans('TakeposRefundPaymentMethod'),
                'reason' => $langs->trans('TakeposRefundReason'),
                'status' => $langs->trans('TakeposCommonStatus'),
                'details' => $langs->trans('TakeposRefundDetails'),
                'print' => $langs->trans('TakeposRefundPrint'),
                'noData' => $langs->trans('TakeposReportsNoDataAvailable'),
                'selectInvoiceFirst' => $langs->trans('TakeposRefundSelectInvoice'),
                'confirmProcess' => $langs->trans('TakeposRefundConfirmProcess'),
                'failed' => $langs->trans('TakeposRefundFailed')
            ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

            function byId(id) { return document.getElementById(id); }
            function qs(params) { var usp = new URLSearchParams(); Object.keys(params || {}).forEach(function (k) { if (params[k] !== '' && params[k] !== null && params[k] !== undefined) usp.append(k, params[k]); }); return usp.toString(); }
            function parseJsonPayload(txt) {
                var cleaned = (txt || '').replace(/^\uFEFF/, '').trim();
                try {
                    return JSON.parse(cleaned);
                } catch (e) {
                    var first = cleaned.indexOf('{');
                    var last = cleaned.lastIndexOf('}');
                    if (first >= 0 && last > first) {
                        return JSON.parse(cleaned.slice(first, last + 1));
                    }
                    throw e;
                }
            }
            function callJson(params) { return fetch(endpoint + '?' + qs(params), { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.text().then(function (txt) { return parseJsonPayload(txt); }); }); }
            // POST variant — used for mutating actions (create_refund) to avoid
            // Dolibarr's SQL/script injection scanner blocking JSON in GET params.
            function callJsonPost(params) {
                var body = new URLSearchParams();
                Object.keys(params || {}).forEach(function (k) {
                    if (params[k] !== '' && params[k] !== null && params[k] !== undefined) {
                        body.append(k, params[k]);
                    }
                });
                return fetch(endpoint + '?action=' + encodeURIComponent(params.action || ''), {
                    method: 'POST',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString()
                }).then(function (r) { return r.text().then(function (txt) { return parseJsonPayload(txt); }); });
            }
            function safe(v) { return (v === null || v === undefined) ? '' : String(v); }
            function fmt(v) { var n = parseFloat(v || 0); if (!isFinite(n)) n = 0; return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

            function setMsg(text, ok) {
                var n = byId('refund_msg');
                n.style.color = ok ? '#2d7d2d' : '#a94442';
                n.textContent = text || '';
            }

            function loadReasons() {
                callJson({ action: 'reasons' }).then(function (res) {
                    var s = byId('refund_reason');
                    s.innerHTML = '';
                    (res.rows || []).forEach(function (r) {
                        var o = document.createElement('option');
                        o.value = r.code;
                        o.textContent = r.label + ' (' + r.code + ')';
                        s.appendChild(o);
                    });
                });
            }

            function renderLookup(rows) {
                var t = byId('table_lookup');
                var html = '<thead><tr><th>' + i18n.id + '</th><th>' + i18n.ref + '</th><th>' + i18n.date + '</th><th>' + i18n.customer + '</th><th>' + i18n.total + '</th><th>' + i18n.store + '</th><th>' + i18n.terminal + '</th><th>' + i18n.action + '</th></tr></thead><tbody>';
                if (!(rows || []).length) {
                    html += '<tr><td colspan="8" class="takepos-workspace-empty">' + safe(i18n.noData) + '</td></tr>';
                } else {
                    (rows || []).forEach(function (r) {
                        html += '<tr>'
                            + '<td>' + safe(r.invoice_id) + '</td>'
                            + '<td>' + safe(r.invoice_ref) + '</td>'
                            + '<td>' + safe(r.invoice_date) + '</td>'
                            + '<td>' + safe(r.customer_name) + '</td>'
                            + '<td class="num">' + fmt(r.total_ttc) + '</td>'
                            + '<td>' + safe(r.store_id) + '</td>'
                            + '<td>' + safe(r.terminal_code) + '</td>'
                            + '<td><button type="button" class="button" data-invoice="' + safe(r.invoice_id) + '">' + i18n.select + '</button></td>'
                            + '</tr>';
                    });
                }
                html += '</tbody>';
                t.innerHTML = html;

                t.querySelectorAll('button[data-invoice]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        selectedInvoiceId = parseInt(btn.getAttribute('data-invoice') || '0', 10);
                        loadRefundableLines();
                    });
                });
            }

            function loadRefundableLines() {
                if (!selectedInvoiceId) return;
                callJson({ action: 'refundable_lines', invoice_id: selectedInvoiceId }).then(function (res) {
                    if (!res || !res.success) {
                        setMsg(res && res.message ? res.message : i18n.loadLinesFailed, false);
                        return;
                    }
                    var data = res.data || {};
                    selectedLines = data.lines || [];
                    byId('refund_invoice_meta').textContent = i18n.invoiceMeta.replace('%s', safe(data.invoice_ref)).replace('%s', safe(data.invoice_date)).replace('%s', safe(data.terminal_code));
                    renderLines(selectedLines);
                });
            }

            function renderLines(lines) {
                var t = byId('table_lines');
                var html = '<thead><tr><th>' + i18n.lineId + '</th><th>' + i18n.label + '</th><th>' + i18n.sold + '</th><th>' + i18n.alreadyRefunded + '</th><th>' + i18n.refundable + '</th><th>' + i18n.refundQty + '</th><th>' + i18n.unitTtc + '</th><th>' + i18n.restock + '</th></tr></thead><tbody>';
                if (!(lines || []).length) {
                    html += '<tr><td colspan="8" class="takepos-workspace-empty">' + safe(i18n.noData) + '</td></tr>';
                } else {
                    (lines || []).forEach(function (r) {
                        html += '<tr>'
                            + '<td>' + safe(r.line_id) + '</td>'
                            + '<td>' + safe(r.label) + '</td>'
                            + '<td class="num">' + fmt(r.qty_sold) + '</td>'
                            + '<td class="num">' + fmt(r.qty_refunded) + '</td>'
                            + '<td class="num">' + fmt(r.qty_refundable) + '</td>'
                            + '<td><input type="number" class="refund-qty" data-line="' + safe(r.line_id) + '" min="0" step="0.001" max="' + safe(r.qty_refundable) + '" value="' + safe(r.qty_refundable) + '"></td>'
                            + '<td class="num">' + fmt(r.unit_price_ttc) + '</td>'
                            + '<td><input type="checkbox" class="refund-restock" data-line="' + safe(r.line_id) + '"></td>'
                            + '</tr>';
                    });
                }
                html += '</tbody>';
                t.innerHTML = html;
            }

            function collectLines() {
                var lines = [];
                byId('table_lines').querySelectorAll('.refund-qty').forEach(function (input) {
                    var rawVal = String(input.value || '').trim();
                    var qty = parseFloat(rawVal);
                    if (!isFinite(qty) || isNaN(qty)) qty = 0;
                    if (qty > 0) {
                        var lineId = parseInt(input.getAttribute('data-line') || '0', 10);
                        var restockNode = byId('table_lines').querySelector('.refund-restock[data-line="' + lineId + '"]');
                        lines.push({ line_id: lineId, qty: qty, restock_flag: restockNode && restockNode.checked ? 1 : 0 });
                    }
                });
                return lines;
            }

            function loadRecentRefunds() {
                callJson({ action: 'list_refunds' }).then(function (res) {
                    var t = byId('table_refunds');
                    if (!res || !res.success) {
                        t.innerHTML = '<tbody><tr><td>' + i18n.loadRefundsFailed + '</td></tr></tbody>';
                        return;
                    }
                    var html = '<thead><tr><th>' + i18n.id + '</th><th>' + i18n.ref + '</th><th>' + i18n.type + '</th><th>' + i18n.invoice + '</th><th>' + i18n.amount + '</th><th>' + i18n.payment + '</th><th>' + i18n.reason + '</th><th>' + i18n.status + '</th><th>' + i18n.date + '</th><th>' + i18n.action + '</th></tr></thead><tbody>';
                    (res.rows || []).forEach(function (r) {
                        html += '<tr>'
                            + '<td>' + safe(r.rowid) + '</td>'
                            + '<td>' + safe(r.refund_ref) + '</td>'
                            + '<td>' + safe(r.refund_type) + '</td>'
                            + '<td>' + safe(r.original_invoice_ref) + '</td>'
                            + '<td class="num">' + fmt(r.total_amount) + '</td>'
                            + '<td>' + safe(r.payment_method) + '</td>'
                            + '<td>' + safe(r.reason_code) + '</td>'
                            + '<td>' + safe(r.status) + '</td>'
                            + '<td>' + safe(r.date_creation) + '</td>'
                            + '<td><a class="button" href="' + detailsUrl + encodeURIComponent(r.rowid) + '">' + i18n.details + '</a> <a class="button" href="' + receiptUrl + encodeURIComponent(r.rowid) + '" target="_blank">' + i18n.print + '</a></td>'
                            + '</tr>';
                    });
                    html += '</tbody>';
                    t.innerHTML = html;
                });
            }

            byId('btn_lookup').addEventListener('click', function () {
                // Smart routing: the "Invoice ID" box officially expects the internal rowid,
                // but cashiers often type the receipt ref (e.g. "TC1-2605-0091") there. If the
                // value contains anything other than digits, treat it as a ref so the search
                // still works. Pure-digit values go through as invoice_id (which the server
                // also matches against the ref tail).
                var idBoxVal  = (byId('lookup_invoice_id').value || '').trim();
                var refBoxVal = (byId('lookup_invoice_ref').value || '').trim();
                var idParam   = '';
                var refParam  = refBoxVal;
                if (idBoxVal !== '') {
                    if (/^\d+$/.test(idBoxVal)) {
                        idParam = idBoxVal;            // pure number → invoice_id
                    } else if (refParam === '') {
                        refParam = idBoxVal;           // has letters/dashes → treat as ref
                    }
                }
                callJson({ action: 'search_invoices', invoice_id: idParam, invoice_ref: refParam, date_from: byId('lookup_date_from').value, date_to: byId('lookup_date_to').value }).then(function (res) {
                    renderLookup((res && res.rows) ? res.rows : []);
                });
            });

            ['lookup_invoice_id', 'lookup_invoice_ref', 'lookup_date_from', 'lookup_date_to'].forEach(function (id) {
                byId(id).addEventListener('keydown', function (event) {
                    if (event.key !== 'Enter') {
                        return;
                    }
                    event.preventDefault();
                    byId('btn_lookup').click();
                });
            });

            var _refundInProgress = false;
            byId('btn_process_refund').addEventListener('click', function () {
                if (!selectedInvoiceId) {
                    setMsg(i18n.selectInvoiceFirst, false);
                    return;
                }
                if (_refundInProgress) {
                    return; // prevent double-click
                }
                var lines = collectLines();
                if (!confirm(i18n.confirmProcess)) {
                    return;
                }
                // Disable button immediately to prevent duplicate submissions
                _refundInProgress = true;
                var btn = byId('btn_process_refund');
                btn.disabled = true;
                btn.style.opacity = '0.6';
                callJsonPost({
                    action: 'create_refund',
                    token: token,
                    refund_type: byId('refund_type').value,
                    invoice_id: selectedInvoiceId,
                    reason_code: byId('refund_reason').value,
                    note: byId('refund_note').value,
                    payment_method: byId('refund_payment_method').value,
                    restock_default: byId('refund_restock_default').value,
                    manager_login: byId('manager_login').value,
                    manager_password: byId('manager_password').value,
                    manager_barcode: byId('manager_barcode').value,
                    lines_json: JSON.stringify(lines)
                }).then(function (res) {
                    if (res && res.success) {
                        setMsg(res.message + ' (' + safe(res.result && res.result.refund_ref) + ')', true);
                        loadRefundableLines();
                        loadRecentRefunds();
                        // Keep button disabled after success (invoice fully/partially refunded)
                    } else {
                        setMsg(res && res.message ? res.message : i18n.failed, false);
                        // Re-enable on failure so cashier can retry
                        _refundInProgress = false;
                        btn.disabled = false;
                        btn.style.opacity = '';
                    }
                }).catch(function () {
                    _refundInProgress = false;
                    btn.disabled = false;
                    btn.style.opacity = '';
                });
            });

            byId('btn_refresh_refunds').addEventListener('click', function () { loadRecentRefunds(); });
            byId('btn_export_refunds').addEventListener('click', function () { window.location.href = endpoint + '?' + qs({ action: 'export_csv' }); });

            loadReasons();
            loadRecentRefunds();
        })();
    </script>
    <?php echo takeposHelpRender($langs, __FILE__); ?>
    </body>
<?php
llxFooter();
$db->close();