<?php
/**
 * Exchange desk page.
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
$exchangeAccessDeniedMessage = $langs->trans('TakeposExchangeAccessDenied');
$canAccessExchangeDesk = (!empty($user->admin)
    || TakeposUserAccess::userHasAnyPermission($db, $user, array('takepos.exchange.process', 'takepos.refund.view')));
if (!$canAccessExchangeDesk) {
    accessforbidden($exchangeAccessDeniedMessage);
}
TakeposAccess::requireFeature($db, 'takepos.returns', $user, false, array('page' => 'exchange.php', 'feature' => 'takepos.returns'));
TakeposAccess::requireFrontendAccess($db, $user, 'takepos.exchanges', 'takepos.use', (int) $terminal, $exchangeAccessDeniedMessage, array('page' => 'exchange.php'));
TakeposAudit::logEvent($db, $user, 'refund_report_opened', TakeposAudit::SEVERITY_INFO, array('view' => 'exchange_desk'), 'Exchange desk opened');

$title = $langs->trans('TakeposExchangePageTitle');
$head = '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
$arrayofcss = array('/takepos/css/workspace.css');
top_htmlhead($head, $title, 0, 0, array(), $arrayofcss);
$exchangeI18n = array(
    'id' => $langs->trans('TakeposLoyaltyId'),
    'ref' => $langs->trans('TakeposExpenseRef'),
    'date' => $langs->trans('TakeposLoyaltyDate'),
    'customer' => $langs->trans('TakeposRefundCustomer'),
    'total' => $langs->trans('TakeposLoyaltyTotal'),
    'action' => $langs->trans('TakeposRefundAction'),
    'select' => $langs->trans('TakeposLoyaltySelectButton'),
    'lineId' => $langs->trans('TakeposRefundLineId'),
    'label' => $langs->trans('TakeposCommonLabel'),
    'refundableQty' => $langs->trans('TakeposRefundRefundable'),
    'returnQty' => $langs->trans('TakeposRefundRefundQty'),
    'restock' => $langs->trans('TakeposRefundRestock'),
    'productId' => $langs->trans('TakeposExchangeProductId'),
    'qty' => $langs->trans('Qty'),
    'unitPrice' => $langs->trans('TakeposRefundUnitPrice'),
    'lineTotal' => $langs->trans('TakeposRefundLineTotal'),
    'remove' => $langs->trans('TakeposExchangeRemove'),
    'invoiceMeta' => $langs->trans('TakeposRefundInvoiceMeta'),
    'unableLoadLines' => $langs->trans('TakeposExchangeUnableLoadLines'),
    'enterValidLine' => $langs->trans('TakeposExchangeEnterValidLine'),
    'selectInvoiceFirst' => $langs->trans('TakeposExchangeSelectInvoiceFirst'),
    'addReplacementFirst' => $langs->trans('TakeposExchangeAddReplacementFirst'),
    'completeMessage' => $langs->trans('TakeposExchangeCompleteMessage'),
    'noData' => $langs->trans('TakeposReportsNoDataAvailable'),
    'exchangeFailed' => $langs->trans('TakeposExchangeFailed'),
    'confirmProcess' => $langs->trans('TakeposExchangeConfirmProcess'),
);
?>
<body class="takepos-workspace-reports-body">
<div class="takepos-workspace-reports-page">
    <h2 class="takepos-workspace-title"><?php echo dol_escape_htmltag($langs->trans('TakeposExchangeTitle')); ?></h2>

    <div id="takepos-exchange-config"
         data-token="<?php echo dol_escape_htmltag(newToken()); ?>"
         data-endpoint="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/ajax/exchange.php'); ?>"></div>
    <script>window.takeposExchangeI18n=<?php echo json_encode($exchangeI18n, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;</script>

    <section class="takepos-workspace-panel">
        <h3><?php echo dol_escape_htmltag($langs->trans('TakeposExchangeLookupTitle')); ?></h3>
        <div class="takepos-workspace-filter-grid">
            <div><label for="lookup_invoice_id"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundInvoiceId')); ?></label><input type="number" id="lookup_invoice_id" min="1"></div>
            <div><label for="lookup_invoice_ref"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundInvoiceRef')); ?></label><input type="text" id="lookup_invoice_ref" maxlength="64"></div>
            <div><label for="lookup_date_from"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundDateFrom')); ?></label><input type="date" id="lookup_date_from"></div>
            <div><label for="lookup_date_to"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundDateTo')); ?></label><input type="date" id="lookup_date_to"></div>
        </div>
        <div class="takepos-workspace-filter-actions"><button type="button" id="btn_lookup" class="button button-save"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonSearch')); ?></button></div>
        <div class="takepos-workspace-table-wrap"><table id="table_lookup" class="takepos-workspace-table"></table></div>
    </section>

    <section class="takepos-workspace-panel">
        <h3><?php echo dol_escape_htmltag($langs->trans('TakeposExchangeReturnedLines')); ?></h3>
        <div id="exchange_meta"><?php echo dol_escape_htmltag($langs->trans('TakeposExchangeSelectInvoice')); ?></div>
        <div class="takepos-workspace-table-wrap"><table id="table_returns" class="takepos-workspace-table"></table></div>
    </section>

    <section class="takepos-workspace-panel">
        <h3><?php echo dol_escape_htmltag($langs->trans('TakeposExchangeReplacementLines')); ?></h3>
        <div class="takepos-workspace-filter-grid">
            <div><label for="new_product_id"><?php echo dol_escape_htmltag($langs->trans('TakeposExchangeProductId')); ?></label><input type="number" id="new_product_id" min="1"></div>
            <div><label for="new_qty"><?php echo dol_escape_htmltag($langs->trans('Qty')); ?></label><input type="number" id="new_qty" min="0.001" step="0.001" value="1"></div>
            <div><label for="new_unit_price"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundUnitPrice')); ?></label><input type="number" id="new_unit_price" min="0" step="0.01" value="0"></div>
            <div><label>&nbsp;</label><button type="button" id="btn_add_new_line" class="button"><?php echo dol_escape_htmltag($langs->trans('Add')); ?></button></div>
        </div>
        <div class="takepos-workspace-table-wrap"><table id="table_new_lines" class="takepos-workspace-table"></table></div>
    </section>

    <section class="takepos-workspace-panel">
        <h3><?php echo dol_escape_htmltag($langs->trans('TakeposExchangeFinalize')); ?></h3>
        <div class="takepos-workspace-filter-grid">
            <div><label for="exchange_reason"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundReason')); ?></label><input type="text" id="exchange_reason" value="other"></div>
            <div><label for="exchange_note"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundNote')); ?></label><input type="text" id="exchange_note"></div>
            <div><label for="settlement_method"><?php echo dol_escape_htmltag($langs->trans('TakeposExchangeSettlement')); ?></label><select id="settlement_method"><option value="CASH"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundCash')); ?></option><option value="CARD"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundCard')); ?></option><option value="OTHER"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundOther')); ?></option></select></div>
            <div><label for="restock_default"><?php echo dol_escape_htmltag($langs->trans('TakeposExchangeDefaultRestock')); ?></label><select id="restock_default"><option value="0"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonNo')); ?></option><option value="1"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonYes')); ?></option></select></div>
        </div>
        <div class="takepos-workspace-filter-grid">
            <div><label for="manager_login"><?php echo dol_escape_htmltag($langs->trans('TakeposExchangeManagerLogin')); ?></label><input type="text" id="manager_login"></div>
            <div><label for="manager_password"><?php echo dol_escape_htmltag($langs->trans('TakeposExchangeManagerPassword')); ?></label><input type="password" id="manager_password"></div>
            <div><label for="manager_barcode"><?php echo dol_escape_htmltag($langs->trans('TakeposExchangeManagerBarcode')); ?></label><input type="text" id="manager_barcode"></div>
        </div>
        <div class="takepos-workspace-filter-actions"><button type="button" id="btn_process_exchange" class="button button-save"><?php echo dol_escape_htmltag($langs->trans('TakeposExchangeProcess')); ?></button></div>
        <div id="exchange_msg"></div>
    </section>
</div>

<script>
(function () {
    'use strict';
    var cfg = document.getElementById('takepos-exchange-config');
    if (!cfg) return;

    var token = cfg.getAttribute('data-token') || '';
    var endpoint = cfg.getAttribute('data-endpoint') || '';
    var i18n = window.takeposExchangeI18n || {};
    var selectedInvoiceId = 0;
    var newSaleLines = [];

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
    function safe(v) { return (v === null || v === undefined) ? '' : String(v); }
    function fmt(v) { var n = parseFloat(v || 0); if (!isFinite(n)) n = 0; return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    function setMsg(text, ok) {
        var n = byId('exchange_msg');
        n.style.color = ok ? '#2d7d2d' : '#a94442';
        n.textContent = text || '';
    }

    function renderLookup(rows) {
        var t = byId('table_lookup');
        var html = '<thead><tr><th>' + safe(i18n.id) + '</th><th>' + safe(i18n.ref) + '</th><th>' + safe(i18n.date) + '</th><th>' + safe(i18n.customer) + '</th><th>' + safe(i18n.total) + '</th><th>' + safe(i18n.action) + '</th></tr></thead><tbody>';
        if (!(rows || []).length) {
            html += '<tr><td colspan="6" class="takepos-workspace-empty">' + safe(i18n.noData) + '</td></tr>';
        } else {
            (rows || []).forEach(function (r) {
                html += '<tr><td>' + safe(r.invoice_id) + '</td><td>' + safe(r.invoice_ref) + '</td><td>' + safe(r.invoice_date) + '</td><td>' + safe(r.customer_name) + '</td><td class="num">' + fmt(r.total_ttc) + '</td><td><button type="button" class="button" data-invoice="' + safe(r.invoice_id) + '">' + safe(i18n.select) + '</button></td></tr>';
            });
        }
        html += '</tbody>';
        t.innerHTML = html;
        t.querySelectorAll('button[data-invoice]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                selectedInvoiceId = parseInt(btn.getAttribute('data-invoice') || '0', 10);
                loadReturnLines();
            });
        });
    }

    function renderReturnLines(lines) {
        var t = byId('table_returns');
        var html = '<thead><tr><th>' + safe(i18n.lineId) + '</th><th>' + safe(i18n.label) + '</th><th>' + safe(i18n.refundableQty) + '</th><th>' + safe(i18n.returnQty) + '</th><th>' + safe(i18n.restock) + '</th></tr></thead><tbody>';
        if (!(lines || []).length) {
            html += '<tr><td colspan="5" class="takepos-workspace-empty">' + safe(i18n.noData) + '</td></tr>';
        } else {
            (lines || []).forEach(function (r) {
                html += '<tr><td>' + safe(r.line_id) + '</td><td>' + safe(r.label) + '</td><td class="num">' + fmt(r.qty_refundable) + '</td><td><input type="number" class="return-qty" data-line="' + safe(r.line_id) + '" min="0" step="0.001" max="' + safe(r.qty_refundable) + '" value="0"></td><td><input type="checkbox" class="return-restock" data-line="' + safe(r.line_id) + '"></td></tr>';
            });
        }
        html += '</tbody>';
        t.innerHTML = html;
    }

    function collectReturnLines() {
        var rows = [];
        byId('table_returns').querySelectorAll('.return-qty').forEach(function (input) {
            var qty = parseFloat(input.value || '0');
            if (isFinite(qty) && qty > 0) {
                var lineId = parseInt(input.getAttribute('data-line') || '0', 10);
                var restockNode = byId('table_returns').querySelector('.return-restock[data-line="' + lineId + '"]');
                rows.push({ line_id: lineId, qty: qty, restock_flag: restockNode && restockNode.checked ? 1 : 0 });
            }
        });
        return rows;
    }

    function renderNewSaleLines() {
        var t = byId('table_new_lines');
        var html = '<thead><tr><th>#</th><th>' + safe(i18n.productId) + '</th><th>' + safe(i18n.qty) + '</th><th>' + safe(i18n.unitPrice) + '</th><th>' + safe(i18n.lineTotal) + '</th><th>' + safe(i18n.action) + '</th></tr></thead><tbody>';
        newSaleLines.forEach(function (r, idx) {
            html += '<tr><td>' + (idx + 1) + '</td><td>' + safe(r.product_id) + '</td><td class="num">' + fmt(r.qty) + '</td><td class="num">' + fmt(r.unit_price) + '</td><td class="num">' + fmt((parseFloat(r.qty)||0)*(parseFloat(r.unit_price)||0)) + '</td><td><button type="button" class="button" data-remove="' + idx + '">' + safe(i18n.remove) + '</button></td></tr>';
        });
        html += '</tbody>';
        t.innerHTML = html;
        t.querySelectorAll('button[data-remove]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var idx = parseInt(btn.getAttribute('data-remove') || '-1', 10);
                if (idx >= 0) {
                    newSaleLines.splice(idx, 1);
                    renderNewSaleLines();
                }
            });
        });
    }

    function loadReturnLines() {
        if (!selectedInvoiceId) return;
        callJson({ action: 'refundable_lines', invoice_id: selectedInvoiceId }).then(function (res) {
            if (!res || !res.success) {
                setMsg(res && res.message ? res.message : i18n.unableLoadLines, false);
                return;
            }
            byId('exchange_meta').textContent = (i18n.invoiceMeta || 'Invoice: %s | Date: %s | Terminal: %s').replace('%s', safe(res.data && res.data.invoice_ref)).replace('%s', safe(res.data && res.data.invoice_date)).replace('%s', safe(res.data && res.data.terminal_code));
            renderReturnLines((res.data && res.data.lines) ? res.data.lines : []);
        });
    }

    byId('btn_lookup').addEventListener('click', function () {
        callJson({ action: 'search_invoices', invoice_id: byId('lookup_invoice_id').value, invoice_ref: byId('lookup_invoice_ref').value, date_from: byId('lookup_date_from').value, date_to: byId('lookup_date_to').value }).then(function (res) {
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

    byId('btn_add_new_line').addEventListener('click', function () {
        var productId = parseInt(byId('new_product_id').value || '0', 10);
        var qty = parseFloat(byId('new_qty').value || '0');
        var unitPrice = parseFloat(byId('new_unit_price').value || '0');
        if (!productId || !isFinite(qty) || qty <= 0 || !isFinite(unitPrice) || unitPrice < 0) {
            setMsg(i18n.enterValidLine, false);
            return;
        }
        newSaleLines.push({ product_id: productId, qty: qty, unit_price: unitPrice });
        renderNewSaleLines();
    });

    ['new_product_id', 'new_qty', 'new_unit_price'].forEach(function (id) {
        byId(id).addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') {
                return;
            }
            event.preventDefault();
            byId('btn_add_new_line').click();
        });
    });

    byId('btn_process_exchange').addEventListener('click', function () {
        if (!selectedInvoiceId) {
            setMsg(i18n.selectInvoiceFirst, false);
            return;
        }
        if (!newSaleLines.length) {
            setMsg(i18n.addReplacementFirst, false);
            return;
        }
        if (!confirm(i18n.confirmProcess)) {
            return;
        }

        callJson({
            action: 'create_exchange',
            token: token,
            invoice_id: selectedInvoiceId,
            reason_code: byId('exchange_reason').value,
            note: byId('exchange_note').value,
            settlement_method: byId('settlement_method').value,
            restock_default: byId('restock_default').value,
            manager_login: byId('manager_login').value,
            manager_password: byId('manager_password').value,
            manager_barcode: byId('manager_barcode').value,
            return_lines_json: JSON.stringify(collectReturnLines()),
            new_lines_json: JSON.stringify(newSaleLines)
        }).then(function (res) {
            if (res && res.success) {
                var d = res.result || {};
                var template = i18n.completeMessage || 'Exchange complete. Ref %s | Net difference %s';
                setMsg(template.replace('%s', safe(d.exchange_ref)).replace('%s', fmt(d.net_difference)), true);
            } else {
                setMsg(res && res.message ? res.message : i18n.exchangeFailed, false);
            }
        });
    });

    renderNewSaleLines();
})();
</script>
<?php echo takeposHelpRender($langs, __FILE__); ?>
</body>
<?php
llxFooter();
$db->close();
