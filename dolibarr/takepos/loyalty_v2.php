<?php
/**
 * Loyalty / CRM desk page.
 */
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');
if (!defined('NOREQUIREAJAX')) define('NOREQUIREAJAX', '1');

require '../main.inc.php';

require_once __DIR__ . '/lib/takepos_lang.php';
require_once __DIR__ . '/lib/takepos_help.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/class/TakeposAccess.class.php';
require_once __DIR__ . '/class/TakeposAudit.class.php';

$langs->loadLangs(array('cashdesk', 'main', 'bills', 'takeposcustom@takepos'));

$terminal = isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null;
TakeposAccess::requireFrontendAccess($db, $user, 'takepos.crm', 'takepos.customer.view', $terminal, $langs->trans('TakeposLoyaltyAccessDenied'), array('page' => 'loyalty_v2.php'));
TakeposAudit::logEvent($db, $user, 'customer_lookup_opened', TakeposAudit::SEVERITY_INFO, array('view' => 'loyalty_desk'), 'Loyalty desk opened');

$canRedeem = (!empty($user->admin) || !empty($user->rights->takepos->run));
$loyaltyTitlePlain = html_entity_decode((string) $langs->trans('TakeposLoyaltyTitle'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$head = '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
$arrayofcss = array('/takepos/css/workspace_v2.css');

top_htmlhead($head, $langs->trans('TakeposLoyaltyPageTitle'), 0, 0, array(), $arrayofcss);
?>
<body class="kfv2-body">
<?php
$v2PageTitle = $langs->trans('TakeposLoyaltyPageTitle');
$v2PageIcon  = 'fa-id-card';
$v2BackUrl   = DOL_URL_ROOT . '/takepos/pos_v2.php';
require __DIR__ . '/topbar_v2.php';
?>
<style>#php-debugbar,.phpdebugbar,.php-debugbar,.debugbar,.debug-bar,.debugbar-container,.sf-toolbar,#sfwdt,div[id*="debugbar"],div[class*="debugbar"]{display:none !important;visibility:hidden !important;opacity:0 !important;pointer-events:none !important;}</style>
<div class="kfv2-page-body" style="max-width:1460px;margin:0 auto;padding:22px 26px 48px">
    <h2 style="display:none"><?php echo htmlspecialchars($loyaltyTitlePlain, ENT_QUOTES, 'UTF-8'); ?></h2>

    <div id="takepos-loyalty-config"
         data-token="<?php echo dol_escape_htmltag(newToken()); ?>"
         data-endpoint="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/ajax/loyalty_v2.php'); ?>"
         data-can-admin="<?php echo (!empty($user->admin) ? '1' : '0'); ?>"></div>

    <section class="kfv2-card-block">
        <div class="kfv2-card-block-head"><h3 style="margin:0;font-size:14.5px"><?php echo dol_escape_htmltag($langs->trans('TakeposLoyaltyCustomerLookup')); ?></h3></div><div class="kfv2-card-block-body">
        <div class="kfv2-form-grid">
            <div><label for="loyalty_search"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonSearch')); ?></label><input type="text" id="loyalty_search" placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposLoyaltySearchPlaceholder')); ?>"></div>
            <div style="align-self:end;"><button type="button" id="btn_lookup" class="kfv2-btn kfv2-btn-primary"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonSearch')); ?></button></div>
        </div>
        <div class="kfv2-table-wrap"><table id="table_customers" class="kfv2-table"></table></div>
    </section>

    <section class="kfv2-card-block">
        <div class="kfv2-card-block-head"><h3 style="margin:0;font-size:14.5px"><?php echo dol_escape_htmltag($langs->trans('TakeposLoyaltyCustomerSummary')); ?></h3></div><div class="kfv2-card-block-body">
        <div id="loyalty_summary_cards" class="kfv2-kpis"></div>
    </section>

    <section class="kfv2-card-block">
        <div class="kfv2-card-block-head"><h3 style="margin:0;font-size:14.5px"><?php echo dol_escape_htmltag($langs->trans('TakeposLoyaltyActions')); ?></h3></div><div class="kfv2-card-block-body">
        <div class="kfv2-form-grid">
            <div><label for="redeem_invoice_id"><?php echo dol_escape_htmltag($langs->trans('TakeposLoyaltyInvoiceId')); ?></label><input type="number" id="redeem_invoice_id" min="1"></div>
            <div><label for="redeem_points"><?php echo dol_escape_htmltag($langs->trans('TakeposLoyaltyRedeemPoints')); ?></label><input type="number" id="redeem_points" min="1"></div>
            <div><label for="redeem_note"><?php echo dol_escape_htmltag($langs->trans('TakeposLoyaltyRedeemNote')); ?></label><input type="text" id="redeem_note"></div>
            <div style="align-self:end;"><button type="button" id="btn_redeem" class="kfv2-btn kfv2-btn-primary"><?php echo dol_escape_htmltag($langs->trans('TakeposLoyaltyRedeemOnInvoice')); ?></button></div>

            <div><label for="adjust_points_delta"><?php echo dol_escape_htmltag($langs->trans('TakeposLoyaltyAdjustPoints')); ?></label><input type="number" id="adjust_points_delta"></div>
            <div><label for="adjust_note"><?php echo dol_escape_htmltag($langs->trans('TakeposLoyaltyAdjustNote')); ?></label><input type="text" id="adjust_note"></div>
            <div style="align-self:end;"><button type="button" id="btn_adjust" class="kfv2-btn kfv2-btn-outline"><?php echo dol_escape_htmltag($langs->trans('TakeposLoyaltyAdjustPointsAction')); ?></button></div>
        </div>
        <div id="loyalty_message" class="opacitymedium" style="margin-top:8px;"></div>
    </section>

    <section class="kfv2-card-block" id="settings_panel" style="display:none;">
        <div class="kfv2-card-block-head"><h3 style="margin:0;font-size:14.5px"><?php echo dol_escape_htmltag($langs->trans('TakeposLoyaltySettings')); ?></h3></div><div class="kfv2-card-block-body">
        <div class="kfv2-form-grid">
            <div><label for="points_per_currency"><?php echo dol_escape_htmltag($langs->trans('TakeposLoyaltyPointsPerCurrency')); ?></label><input type="number" step="0.000001" id="points_per_currency"></div>
            <div><label for="redeem_points_per_currency"><?php echo dol_escape_htmltag($langs->trans('TakeposLoyaltyRedeemPointsPerCurrency')); ?></label><input type="number" step="0.000001" id="redeem_points_per_currency"></div>
            <div style="align-self:end;"><button type="button" id="btn_save_settings" class="kfv2-btn kfv2-btn-outline"><?php echo dol_escape_htmltag($langs->trans('TakeposLoyaltySaveSettings')); ?></button></div>
        </div>
    </section>

    <section class="kfv2-card-block">
        <div class="kfv2-card-block-head"><h3 style="margin:0;font-size:14.5px"><?php echo dol_escape_htmltag($langs->trans('TakeposLoyaltyRecentTickets')); ?></h3></div><div class="kfv2-card-block-body">
        <div class="kfv2-table-wrap"><table id="table_tickets" class="kfv2-table"></table></div>
    </section>

    <section class="kfv2-card-block">
        <div class="kfv2-card-block-head"><h3 style="margin:0;font-size:14.5px"><?php echo dol_escape_htmltag($langs->trans('TakeposLoyaltyTransactions')); ?></h3></div><div class="kfv2-card-block-body">
        <div class="kfv2-table-wrap"><table id="table_txn" class="kfv2-table"></table></div>
    </section>
</div>

<script>
(function () {
    'use strict';
    function takeposHideLoyaltyDebugBars(){var sels=['#php-debugbar','.phpdebugbar','.php-debugbar','.debugbar','.debug-bar','.debugbar-container','.sf-toolbar','#sfwdt','div[id*="debugbar"]','div[class*="debugbar"]'];try{document.querySelectorAll(sels.join(',')).forEach(function(el){el.remove();});}catch(e){}}
    takeposHideLoyaltyDebugBars();
    window.addEventListener('load', takeposHideLoyaltyDebugBars);
    setTimeout(takeposHideLoyaltyDebugBars, 300);

    var cfg = document.getElementById('takepos-loyalty-config');
    if (!cfg) return;

    var endpoint = cfg.getAttribute('data-endpoint') || '';
    var token = cfg.getAttribute('data-token') || '';
    var canAdmin = parseInt(cfg.getAttribute('data-can-admin') || '0', 10) === 1;
    var selectedCustomerId = 0;
    var i18n = <?php echo json_encode(array(
        'id' => $langs->trans('TakeposLoyaltyId'),
        'code' => $langs->trans('TakeposLoyaltyCode'),
        'name' => $langs->trans('TakeposLoyaltyName'),
        'email' => $langs->trans('TakeposLoyaltyEmail'),
        'phone' => $langs->trans('TakeposLoyaltyPhone'),
        'select' => $langs->trans('TakeposLoyaltySelectButton'),
        'customer' => $langs->trans('TakeposLoyaltyCustomer'),
        'selectCustomer' => $langs->trans('TakeposLoyaltySelectCustomer'),
        'pointsBalance' => $langs->trans('TakeposLoyaltyPointsBalance'),
        'totalEarned' => $langs->trans('TakeposLoyaltyTotalEarned'),
        'totalRedeemed' => $langs->trans('TakeposLoyaltyTotalRedeemed'),
        'purchaseCount' => $langs->trans('TakeposLoyaltyPurchaseCount'),
        'grossSales' => $langs->trans('TakeposLoyaltyGrossSales'),
        'invoice' => $langs->trans('TakeposLoyaltyInvoice'),
        'date' => $langs->trans('TakeposLoyaltyDate'),
        'total' => $langs->trans('TakeposLoyaltyTotal'),
        'status' => $langs->trans('TakeposCommonStatus'),
        'paid' => $langs->trans('TakeposLoyaltyPaid'),
        'terminal' => $langs->trans('TakeposLoyaltyTerminal'),
        'type' => $langs->trans('TakeposLoyaltyType'),
        'points' => $langs->trans('TakeposLoyaltyPoints'),
        'amount' => $langs->trans('TakeposLoyaltyAmount'),
        'source' => $langs->trans('TakeposLoyaltySource'),
        'note' => $langs->trans('TakeposCommonNote'),
        'unableLoadSummary' => $langs->trans('TakeposLoyaltyUnableLoadSummary'),
        'lookupFailed' => $langs->trans('TakeposLoyaltyLookupFailed'),
        'redeemFailed' => $langs->trans('TakeposLoyaltyRedeemFailed'),
        'redeemedSuccess' => $langs->trans('TakeposLoyaltyRedeemedSuccessAmount'),
        'selectFirst' => $langs->trans('TakeposLoyaltySelectFirst'),
        'adjustFailed' => $langs->trans('TakeposLoyaltyAdjustFailed'),
        'adjustedSuccess' => $langs->trans('TakeposLoyaltyAdjustedSuccess'),
        'saveSettingsFailed' => $langs->trans('TakeposLoyaltySaveSettingsFailed'),
        'settingsSaved' => $langs->trans('TakeposLoyaltySettingsSaved'),
        'linkedToSale' => $langs->trans('TakeposLoyaltyCustomerLinkedToSale'),
        'linkFailed' => $langs->trans('TakeposLoyaltyUnableLinkCustomerToSale'),
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    function byId(id) { return document.getElementById(id); }
    function safe(v) { return (v === null || v === undefined) ? '' : String(v).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'}[c] || c; }); }
    function fmt(v) { var n = parseFloat(v || 0); if (!isFinite(n)) n = 0; return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function qs(params) { var u = new URLSearchParams(); Object.keys(params || {}).forEach(function (k) { if (params[k] !== '' && params[k] !== null && params[k] !== undefined) u.append(k, params[k]); }); return u.toString(); }
    function call(params) { return fetch(endpoint + '?' + qs(params), { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.json(); }); }

    function setMessage(msg, good) {
        var n = byId('loyalty_message');
        if (!n) return;
        n.style.color = good ? '#1f7a45' : '#8b1f1f';
        n.textContent = msg || '';
    }

    function linkSelectedCustomerToSale(customerId, customerName) {
        if (!customerId) return;
        var label = customerName || ('#' + customerId);
        try {
            if (window.parent && typeof window.parent.ChangeThirdparty === 'function') {
                window.parent.ChangeThirdparty(customerId, customerName || '');
                setMessage(String(i18n.linkedToSale || '').replace('%s', label), true);
                return;
            }
        } catch (e) {
            setMessage(i18n.linkFailed || '', false);
            return;
        }
        setMessage(String(i18n.linkedToSale || '').replace('%s', label), true);
    }

    function renderCustomers(rows) {
        var t = byId('table_customers');
        var html = '<thead><tr><th>' + safe(i18n.id) + '</th><th>' + safe(i18n.code) + '</th><th>' + safe(i18n.name) + '</th><th>' + safe(i18n.email) + '</th><th>' + safe(i18n.phone) + '</th><th></th></tr></thead><tbody>';
        (rows || []).forEach(function (r) {
            html += '<tr>'
                + '<td>' + safe(r.id) + '</td>'
                + '<td>' + safe(r.code) + '</td>'
                + '<td>' + safe(r.name) + '</td>'
                + '<td>' + safe(r.email) + '</td>'
                + '<td>' + safe(r.phone) + '</td>'
                + '<td><button type="button" class="button btn-select-customer" data-id="' + safe(r.id) + '" data-name="' + safe(r.name) + '">' + safe(i18n.select) + '</button></td>'
                + '</tr>';
        });
        html += '</tbody>';
        t.innerHTML = html;

        t.querySelectorAll('.btn-select-customer').forEach(function (btn) {
            btn.addEventListener('click', function () {
                selectedCustomerId = parseInt(btn.getAttribute('data-id') || '0', 10);
                linkSelectedCustomerToSale(selectedCustomerId, btn.getAttribute('data-name') || '');
                loadCustomer();
            });
        });
    }

    function renderSummary(summary) {
        var cards = byId('loyalty_summary_cards');
        if (!cards) return;
        if (!summary || !summary.customer || !summary.loyalty) {
            cards.innerHTML = '<div class="kfv2-kpi"><div class="kk">' + safe(i18n.customer) + '</div><div class="kv num">' + safe(i18n.selectCustomer) + '</div></div>';
            return;
        }

        cards.innerHTML = ''
            + '<div class="kfv2-kpi"><div class="kk">' + safe(i18n.customer) + '</div><div class="kv num">' + safe(summary.customer.name) + '</div></div>'
            + '<div class="kfv2-kpi"><div class="kk">' + safe(i18n.pointsBalance) + '</div><div class="kv num">' + safe(summary.loyalty.points_balance) + '</div></div>'
            + '<div class="kfv2-kpi"><div class="kk">' + safe(i18n.totalEarned) + '</div><div class="kv num">' + safe(summary.loyalty.total_earned) + '</div></div>'
            + '<div class="kfv2-kpi"><div class="kk">' + safe(i18n.totalRedeemed) + '</div><div class="kv num">' + safe(summary.loyalty.total_redeemed) + '</div></div>'
            + '<div class="kfv2-kpi"><div class="kk">' + safe(i18n.purchaseCount) + '</div><div class="kv num">' + safe(summary.purchase ? summary.purchase.purchase_count : 0) + '</div></div>'
            + '<div class="kfv2-kpi"><div class="kk">' + safe(i18n.grossSales) + '</div><div class="kv num">' + fmt(summary.purchase ? summary.purchase.gross_sales : 0) + '</div></div>';
    }

    function renderTickets(rows) {
        var t = byId('table_tickets');
        var html = '<thead><tr><th>' + safe(i18n.invoice) + '</th><th>' + safe(i18n.date) + '</th><th>' + safe(i18n.total) + '</th><th>' + safe(i18n.status) + '</th><th>' + safe(i18n.paid) + '</th><th>' + safe(i18n.terminal) + '</th></tr></thead><tbody>';
        (rows || []).forEach(function (r) {
            html += '<tr>'
                + '<td>' + safe(r.invoice_ref || r.invoice_id) + '</td>'
                + '<td>' + safe(r.invoice_date) + '</td>'
                + '<td>' + fmt(r.total_ttc) + '</td>'
                + '<td>' + safe(r.status) + '</td>'
                + '<td>' + safe(r.paid) + '</td>'
                + '<td>' + safe(r.terminal) + '</td>'
                + '</tr>';
        });
        html += '</tbody>';
        t.innerHTML = html;
    }

    function renderTxn(rows) {
        var t = byId('table_txn');
        var html = '<thead><tr><th>' + safe(i18n.id) + '</th><th>' + safe(i18n.type) + '</th><th>' + safe(i18n.points) + '</th><th>' + safe(i18n.amount) + '</th><th>' + safe(i18n.source) + '</th><th>' + safe(i18n.date) + '</th><th>' + safe(i18n.note) + '</th></tr></thead><tbody>';
        (rows || []).forEach(function (r) {
            html += '<tr>'
                + '<td>' + safe(r.rowid) + '</td>'
                + '<td>' + safe(r.txn_type) + '</td>'
                + '<td>' + safe(r.points_delta) + '</td>'
                + '<td>' + fmt(r.amount_base) + '</td>'
                + '<td>' + safe(r.source_type) + '#' + safe(r.source_id) + '</td>'
                + '<td>' + safe(r.date_creation) + '</td>'
                + '<td>' + safe(r.note) + '</td>'
                + '</tr>';
        });
        html += '</tbody>';
        t.innerHTML = html;
    }

    function loadCustomer() {
        if (!selectedCustomerId) {
            renderSummary(null);
            renderTickets([]);
            renderTxn([]);
            return;
        }

        call({ action: 'customer_summary', customer_id: selectedCustomerId }).then(function (res) {
            if (!res || !res.success) {
                setMessage((res && res.message) ? res.message : i18n.unableLoadSummary, false);
                return;
            }
            renderSummary(res.summary || null);
        });

        call({ action: 'history', customer_id: selectedCustomerId }).then(function (res) {
            if (!res || !res.success) return;
            renderTickets(res.tickets || []);
            renderTxn(res.transactions || []);
        });
    }

    function lookup() {
        call({ action: 'lookup', q: byId('loyalty_search').value }).then(function (res) {
            if (!res || !res.success) {
                setMessage((res && res.message) ? res.message : i18n.lookupFailed, false);
                return;
            }
            renderCustomers(res.rows || []);
        });
    }

    function loadSettings() {
        if (!canAdmin) return;
        byId('settings_panel').style.display = '';
        call({ action: 'settings' }).then(function (res) {
            if (!res || !res.success || !res.settings) return;
            byId('points_per_currency').value = safe(res.settings.points_per_currency);
            byId('redeem_points_per_currency').value = safe(res.settings.redeem_points_per_currency);
        });
    }

    byId('btn_lookup').addEventListener('click', lookup);
    byId('loyalty_search').addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter') lookup();
    });

    byId('btn_redeem').addEventListener('click', function () {
        call({
            action: 'redeem_invoice',
            token: token,
            invoice_id: byId('redeem_invoice_id').value,
            points: byId('redeem_points').value,
            note: byId('redeem_note').value
        }).then(function (res) {
            if (!res || !res.success) {
                setMessage((res && res.message) ? res.message : i18n.redeemFailed, false);
                return;
            }
            setMessage(i18n.redeemedSuccess.replace('%s', fmt(res.result ? res.result.amount : 0)), true);
            loadCustomer();
        });
    });

    byId('btn_adjust').addEventListener('click', function () {
        if (!selectedCustomerId) {
            setMessage(i18n.selectFirst, false);
            return;
        }
        call({
            action: 'adjust_points',
            token: token,
            customer_id: selectedCustomerId,
            points_delta: byId('adjust_points_delta').value,
            note: byId('adjust_note').value
        }).then(function (res) {
            if (!res || !res.success) {
                setMessage((res && res.message) ? res.message : i18n.adjustFailed, false);
                return;
            }
            setMessage(i18n.adjustedSuccess, true);
            loadCustomer();
        });
    });

    byId('btn_save_settings').addEventListener('click', function () {
        call({
            action: 'save_settings',
            token: token,
            points_per_currency: byId('points_per_currency').value,
            redeem_points_per_currency: byId('redeem_points_per_currency').value
        }).then(function (res) {
            if (!res || !res.success) {
                setMessage((res && res.message) ? res.message : i18n.saveSettingsFailed, false);
                return;
            }
            setMessage(i18n.settingsSaved, true);
        });
    });

    lookup();
    loadSettings();
})();
</script>
<?php echo takeposHelpRender($langs, __FILE__); ?>
</body>
<?php
llxFooter();
$db->close();
