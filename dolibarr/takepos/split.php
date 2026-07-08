<?php
/* Copyright (C) 2021		Andreu Bisquerra		<jove@bisquerra.com>
 * Copyright (C) 2024		Frederic France			<frederic.france@free.fr>
 * Copyright (C) 2025		MDW						<mdeweerd@users.noreply.github.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/takepos/split.php
 *	\ingroup	takepos
 *	\brief      Page with the content of the popup to split sale
 */

//if (! defined('NOREQUIREUSER'))	define('NOREQUIREUSER', '1');	// Not disabled cause need to load personalized language
//if (! defined('NOREQUIREDB'))		define('NOREQUIREDB', '1');		// Not disabled cause need to load personalized language
//if (! defined('NOREQUIRESOC'))		define('NOREQUIRESOC', '1');
//if (! defined('NOREQUIRETRAN'))		define('NOREQUIRETRAN', '1');
if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
}

// Load Dolibarr environment
require '../main.inc.php';
require_once __DIR__ . '/lib/takepos_help.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/lib/takepos_access_guard.php';
takeposAccessGuardCurrent($db, isset($user) ? $user : null, __FILE__);
// Load $user and permissions
require_once __DIR__.'/class/TakeposAccess.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */
$langs->loadLangs(array("main", "bills", "cashdesk", "banks", "takeposcustom@takepos"));

$action = GETPOST('action', 'aZ09');
$place = (GETPOST('place', 'aZ09') ? GETPOST('place', 'aZ09') : 0);

if (!$user->hasRight('takepos', 'run')) {
    accessforbidden();
}

TakeposAccess::enforceFrontend($db, isset($user) ? $user : null, 'takepos.split', $_SESSION["takeposterminal"]);


/*
 * Actions
 */

function takeposSplitRequestedLineIds()
{
    $lineIds = array();
    $singleLine = GETPOSTINT('line');
    if ($singleLine > 0) {
        $lineIds[] = $singleLine;
    }

    $rawLineIds = GETPOST('line_ids_json', 'none');
    if ($rawLineIds !== '') {
        $decoded = json_decode($rawLineIds, true);
        if (is_array($decoded)) {
            foreach ($decoded as $lineId) {
                $lineId = (int) $lineId;
                if ($lineId > 0) {
                    $lineIds[] = $lineId;
                }
            }
        }
    }

    $lineIds = array_values(array_unique($lineIds));
    return $lineIds;
}

function takeposSplitResolveDraftInvoice($db, $user, $terminal, $refSuffix, $conf, $langs)
{
    $invoice = new Facture($db);
    $ret = $invoice->fetch(0, '(PROV-POS'.$terminal.'-'.$refSuffix.')');
    if ($ret > 0) {
        return array($invoice, (int) $invoice->id);
    }

    $constforcompanyid = 'CASHDESK_ID_THIRDPARTY'.$terminal;
    $invoice->socid = takeposResolveTerminalThirdPartyId($terminal);
    $invoice->date = dol_now();
    $invoice->module_source = 'takepos';
    $invoice->pos_source = $terminal;
    $invoice->entity = !empty($_SESSION["takeposinvoiceentity"]) ? $_SESSION["takeposinvoiceentity"] : $conf->entity;

    if ($invoice->socid <= 0) {
        $langs->load('errors');
        dol_htmloutput_errors($langs->trans("ErrorModuleSetupNotComplete", "TakePos"), array(), 1);
        return array($invoice, 0);
    }

    $invoiceId = $invoice->create($user);
    if ($invoiceId < 0) {
        dol_htmloutput_errors($invoice->error, $invoice->errors, 1);
        return array($invoice, 0);
    }

    $sql = "UPDATE ".MAIN_DB_PREFIX."facture SET ref='(PROV-POS".$terminal."-".$db->escape($refSuffix).")'";
    $sql .= " WHERE rowid = ".((int) $invoiceId);
    $db->query($sql);

    return array($invoice, (int) $invoiceId);
}

if ($action == "split" && $user->hasRight('takepos', 'run')) {
    $lineIds = takeposSplitRequestedLineIds();
    $split = GETPOSTINT('split');
    $invoice = null;
    $placeid = 0;
    $terminal = $_SESSION["takeposterminal"];

    if (!empty($lineIds) && $split == 1) {
        list($invoice, $placeid) = takeposSplitResolveDraftInvoice($db, $user, $terminal, 'SPLIT', $conf, $langs);
        if ($placeid > 0) {
            $sql = "UPDATE ".MAIN_DB_PREFIX."facturedet SET fk_facture = ".((int) $placeid)." WHERE rowid IN (".implode(',', array_map('intval', $lineIds)).")";
            $db->query($sql);
        }
    } elseif (!empty($lineIds) && $split == 0) {
        if ($place == "SPLIT") {
            $place = "0";
        }
        list($invoice, $placeid) = takeposSplitResolveDraftInvoice($db, $user, $terminal, (string) $place, $conf, $langs);
        if ($placeid > 0) {
            $sql = "UPDATE ".MAIN_DB_PREFIX."facturedet SET fk_facture = ".((int) $placeid)." WHERE rowid IN (".implode(',', array_map('intval', $lineIds)).")";
            $db->query($sql);
        }
    }

    if ($invoice !== null) {
        $invoice->fetch(0, '(PROV-POS'.$terminal.'-SPLIT)');
        $invoice->update_price();

        $invoice->fetch(0, '(PROV-POS'.$terminal.'-'.$place.')');
        $invoice->update_price();
    }
}


/*
 * View
 */

$invoice = new Facture($db);
if (isset($invoiceid) && $invoiceid > 0) {
    $invoice->fetch($invoiceid);
} else {
    $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."facture where ref='(PROV-POS".$_SESSION["takeposterminal"]."-".$place.")'";
    $resql = $db->query($sql);
    $obj = $db->fetch_object($resql);
    if ($obj) {
        $invoiceid = $obj->rowid;
    }
    if (!isset($invoiceid)) {
        $invoiceid = 0; // Invoice does not exist yet
    } else {
        $invoice->fetch($invoiceid);
    }
}

$arrayofcss = array('/takepos/css/pos.css.php');
if (getDolGlobalInt('TAKEPOS_COLOR_THEME') == 1) {
    $arrayofcss[] = '/takepos/css/colorful.css';
}
$arrayofjs = array();

$head = '';
$title = '';
$disablejs = 0;
$disablehead = 0;

top_htmlhead($head, $title, $disablejs, $disablehead, $arrayofjs, $arrayofcss);

// Define list of possible payments
$arrayOfValidPaymentModes = array();
$arrayOfValidBankAccount = array();

?>
<body class="takepossplitphp">
<style>
    /* ── Reset & base ─────────────────────────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body.takepossplitphp {
        font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
        background: #f0f2f7;
        color: #1e293b;
        height: 100vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        font-size: 13px;
    }

    /* ── Top bar ──────────────────────────────────────────────────────────────── */
    .sp-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 16px;
        background: #1e293b;
        color: #fff;
        flex-shrink: 0;
        gap: 12px;
    }
    .sp-topbar-title {
        font-size: 14px;
        font-weight: 700;
        letter-spacing: .3px;
        white-space: nowrap;
    }
    .sp-topbar-actions { display: flex; align-items: center; gap: 8px; }
    .sp-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 14px;
        border: none;
        border-radius: 7px;
        font-size: 12.5px;
        font-weight: 600;
        cursor: pointer;
        transition: background .13s, transform .1s;
        white-space: nowrap;
    }
    .sp-btn:active { transform: scale(.97); }
    .sp-btn-refresh { background: rgba(255,255,255,.12); color: #fff; }
    .sp-btn-refresh:hover { background: rgba(255,255,255,.22); }
    .sp-feedback {
        font-size: 12px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 5px;
        display: none;
    }
    .sp-feedback.is-error   { display:block; background:#fef2f2; color:#b91c1c; border:1px solid #fca5a5; }
    .sp-feedback.is-success { display:block; background:#f0fdf4; color:#15803d; border:1px solid #86efac; }

    /* ── Two-panel layout ─────────────────────────────────────────────────────── */
    .sp-panels {
        display: flex;
        flex: 1 1 0;
        min-height: 0;
    }

    /* ── Each panel ───────────────────────────────────────────────────────────── */
    .sp-panel {
        flex: 1 1 0;
        min-width: 0;
        display: flex;
        flex-direction: column;
        background: #fff;
        border: 1px solid #e2e8f0;
    }
    .sp-panel + .sp-panel { border-left: none; }

    .sp-panel-head {
        display: flex;
        align-items: center;
        padding: 10px 14px;
        background: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
        flex-shrink: 0;
        gap: 8px;
    }
    .sp-panel-label {
        font-size: 10.5px;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: .7px;
        display: block;
        line-height: 1;
        margin-bottom: 3px;
    }
    .sp-panel-title {
        font-size: 15px;
        font-weight: 700;
        color: #1e293b;
        display: block;
    }

    /* ── Instruction hint strip ──────────────────────────────────────────────── */
    .sp-hint {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        padding: 6px 12px;
        background: #fffbeb;
        border-bottom: 1px solid #fde68a;
        font-size: 11.5px;
        color: #92400e;
        font-weight: 500;
        flex-shrink: 0;
    }

    /* ── Move button column ──────────────────────────────────────────────────── */
    .sp-divider {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 12px;
        width: 60px;
        flex-shrink: 0;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
        border-left: 1px solid #e2e8f0;
        border-right: 1px solid #e2e8f0;
    }
    .sp-move-btn {
        width: 42px;
        height: 42px;
        border: none;
        border-radius: 50%;
        cursor: pointer;
        font-size: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background .13s, transform .12s, box-shadow .13s;
    }
    .sp-move-btn:active { transform: scale(.9); }
    .sp-move-right {
        background: #2563eb;
        color: #fff;
        box-shadow: 0 4px 12px rgba(37,99,235,.4);
    }
    .sp-move-right:hover { background: #1d4ed8; }
    .sp-move-left {
        background: #fff;
        color: #64748b;
        border: 1.5px solid #cbd5e1 !important;
        box-shadow: 0 2px 6px rgba(0,0,0,.07);
    }
    .sp-move-left:hover { background: #f1f5f9; color: #1e293b; }
    .sp-divider-label {
        font-size: 9px;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: .5px;
    }

    /* ── Invoice scroll area ─────────────────────────────────────────────────── */
    .sp-invoice-wrap {
        flex: 1 1 0;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
        position: relative;
    }
    .sp-invoice-wrap::-webkit-scrollbar { width: 4px; }
    .sp-invoice-wrap::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

    /* ── Hide invoice.php chrome — exact class names from DevTools ───────────── */
    /* These rows/elements render inside .sp-invoice-wrap but belong to main POS  */
    .sp-invoice-wrap .tpv2-cart-header-row,   /* "Sales Cart / #160 / icons" row  */
    .sp-invoice-wrap .tpv2-customer-row,      /* "T / Customer / TakePOS generic" */
    .sp-invoice-wrap .tpv2-cart-footer        /* Subtotal + Grand total + Pay btn  */
    { display: none !important; }

    /* Keep invoice table clean */
    .sp-invoice-wrap .invoice { padding: 0 !important; }
    .sp-invoice-wrap .div-table-responsive-no-min { overflow: visible !important; }
    .sp-invoice-wrap table.postablelines { width: 100% !important; }

    /* Row selection */
    .sp-invoice-wrap .posinvoiceline {
        cursor: pointer !important;
        transition: background .1s;
        user-select: none;
    }
    .sp-invoice-wrap .posinvoiceline:hover td { background: #f0f4ff !important; }
    .sp-invoice-wrap .posinvoiceline.takepos-split-selected td {
        background: #dbeafe !important;
    }
    .sp-invoice-wrap .posinvoiceline.takepos-split-selected {
        outline: 2px solid #2563eb;
        outline-offset: -1px;
    }

    /* ── Panel footer: Pay / total strip ─────────────────────────────────────── */
    .sp-panel-footer {
        flex-shrink: 0;
        border-top: 2px solid #e2e8f0;
        background: #f8fafc;
        padding: 10px 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .sp-footer-total {
        flex: 1;
        font-size: 14px;
        font-weight: 700;
        color: #1e293b;
    }
    .sp-footer-total-label {
        font-size: 10px;
        font-weight: 600;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: .5px;
        display: block;
        margin-bottom: 1px;
    }
    .sp-footer-total-value {
        font-size: 18px;
        font-weight: 800;
        color: #1e293b;
        font-variant-numeric: tabular-nums;
    }
    .sp-pay-btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 18px;
        background: #16a34a;
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 3px 8px rgba(22,163,74,.3);
        transition: background .13s, transform .1s, box-shadow .13s;
        white-space: nowrap;
    }
    .sp-pay-btn:hover { background: #15803d; box-shadow: 0 4px 12px rgba(22,163,74,.4); }
    .sp-pay-btn:active { transform: scale(.97); }
    .sp-pay-btn .fa { font-size: 14px; }

    /* Selected count badge in panel head */
    .sp-selected-badge {
        margin-left: auto;
        background: #2563eb;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 10px;
        display: none;
    }
    .sp-selected-badge.has-selection { display: inline-block; }

    .sp-cash-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 9px 14px;
        background: #1e3a8a;
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 12.5px;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 3px 8px rgba(30,58,138,.25);
        transition: background .13s, transform .1s;
        white-space: nowrap;
        flex-shrink: 0;
    }
    .sp-cash-btn:hover { background: #1e40af; }
    .sp-cash-btn:active { transform: scale(.97); }
    .sp-cash-btn .fa { font-size: 13px; }

    /* ── Empty / loading state ───────────────────────────────────────────────── */
    .sp-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 120px;
        color: #94a3b8;
        gap: 8px;
        padding: 20px;
        text-align: center;
    }
    .sp-empty-icon { font-size: 26px; opacity: .3; }
    .sp-empty-text { font-size: 12px; }
</style>

<script>
    /* Resolve the real POS window — split.php loads inside a Colorbox iframe,
       so window.parent is Colorbox, and window.parent.parent is the actual POS page. */
    function getTakePosWindow() {
        try {
            // If loaded directly (not in iframe) parent === self
            if (window.parent && window.parent !== window && typeof window.parent.place !== 'undefined') {
                return window.parent;           // direct iframe in POS
            }
            if (window.parent && window.parent.parent && typeof window.parent.parent.place !== 'undefined') {
                return window.parent.parent;    // inside Colorbox (iframe inside iframe)
            }
        } catch(e) { /* cross-origin, ignore */ }
        return window.parent || window;
    }

    var splitI18n = {
        moveToSplit: <?php echo json_encode($langs->trans("Move") . ' ' . $langs->trans("TakeposUiSplitSale")); ?>,
        moveBack: <?php echo json_encode($langs->trans("Move") . ' ' . $langs->trans("Back")); ?>,
        refresh: <?php echo json_encode($langs->trans("TakeposCommonRefresh")); ?>,
        selectLine: <?php echo json_encode($langs->trans("TakeposCommonSelect") . ' ' . $langs->trans("Line")); ?>,
        confirmToSplit: <?php echo json_encode($langs->trans("Confirm") . ' ' . $langs->trans("TakeposUiSplitSale") . '?'); ?>,
        confirmToOrigin: <?php echo json_encode($langs->trans("Confirm") . ' ' . $langs->trans("Back") . '?'); ?>,
        moveFailed: <?php echo json_encode($langs->trans("Error")); ?>,
        moveSuccess: <?php echo json_encode($langs->trans("Modified")); ?>
    };

    function setSplitFeedback(message, isSuccess) {
        var node = document.getElementById('split-feedback');
        if (!node) {
            return;
        }
        node.textContent = message || '';
        node.className = 'takepos-split-feedback' + (message ? (isSuccess ? ' is-success' : ' is-error') : '');
    }

    function getSelectedLineIds(containerId) {
        var lineIds = [];
        /* Search inside .sp-invoice-wrap if present, else the container itself */
        var scope = document.querySelector('#' + containerId + ' .sp-invoice-wrap') ||
            document.getElementById(containerId);
        if (!scope) return lineIds;
        scope.querySelectorAll('.posinvoiceline.takepos-split-selected').forEach(function(line) {
            var lineId = parseInt(line.id || '0', 10);
            if (lineId > 0) {
                lineIds.push(lineId);
            }
        });
        return lineIds;
    }

    function updateBadge(panelId) {
        var wrap = document.querySelector('#' + panelId + ' .sp-invoice-wrap') ||
            document.getElementById(panelId);
        if (!wrap) return;
        /* badge lives in the panel head, identified by data-badge attr */
        var badge = document.querySelector('[data-badge="' + panelId + '"]');
        if (!badge) return;
        var count = wrap.querySelectorAll('.takepos-split-selected').length;
        badge.textContent = count + ' selected';
        badge.classList.toggle('has-selection', count > 0);
    }

    function bindSplitPanel(containerId) {
        var wrap = document.querySelector('#' + containerId + ' .sp-invoice-wrap') ||
            document.getElementById(containerId);
        if (!wrap) return;

        wrap.querySelectorAll('.posinvoiceline').forEach(function(line) {
            line.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                line.classList.toggle('takepos-split-selected');
                updateBadge(containerId);
            });
        });

        wrap.addEventListener('wheel', function(e) { e.stopPropagation(); }, { passive: true });
        wrap.addEventListener('touchmove', function(e) { e.stopPropagation(); }, { passive: true });
    }

    function updatePanelFooter(panelId) {
        /* Read total from the rendered invoice inside the panel */
        var wrap = document.querySelector('#' + panelId + ' .sp-invoice-wrap');
        if (!wrap) return;
        /* invoice.php renders the grand total in .tpv2-grand-total-value */
        var totalEl = wrap.querySelector('.tpv2-grand-total-value');
        var footer  = document.getElementById(panelId + '-footer');
        var totalSpan = document.getElementById(panelId + '-total');
        if (!footer) return;
        if (totalEl && totalEl.textContent.trim() !== '') {
            if (totalSpan) totalSpan.textContent = totalEl.textContent.trim();
            footer.style.display = 'flex';
        } else {
            footer.style.display = 'none';
        }
    }

    function splitPanelPay(panelId) {
        /* Read invoiceid from the hidden input rendered by invoice.php */
        var wrap = document.querySelector('#' + panelId + ' .sp-invoice-wrap');
        if (!wrap) { setSplitFeedback('Panel not found', false); return; }

        var hiddenInvoiceId = wrap.querySelector('input[name="invoiceid"]');
        var invoiceId = hiddenInvoiceId ? parseInt(hiddenInvoiceId.value, 10) : 0;
        if (invoiceId <= 0) { setSplitFeedback('No invoice to pay', false); return; }

        /* place: SPLIT invoice uses 'SPLIT', original uses current POS place */
        var placeVal = (panelId === 'splitplace') ? 'SPLIT' : getTakePosWindow().place;

        /* Open pay.php in a Colorbox on top of the split screen — exactly what
           CloseBill() does in the main POS. Shows all payment methods:
           cash, card, cheque, delayed, etc. Does NOT disrupt the main POS view. */
        $.colorbox({
            href: 'pay.php?place=' + encodeURIComponent(placeVal) + '&invoiceid=' + invoiceId,
            width: '80%',
            height: '90%',
            transition: 'none',
            iframe: true,
            title: '',
            onClosed: function () {
                /* After payment completes, reload both panels and refresh main POS cart */
                loadSplitPanels();
                var pos = getTakePosWindow();
                if (typeof pos.Refresh === 'function') {
                    pos.Refresh();
                }
            }
        });
    }

    function splitPanelDirectPay(panelId, payCode) {
        /* Direct payment — calls invoice.php?action=valid exactly like the POS cash button.
           No popup. Pays immediately with the given payment code (LIQ = cash, CB = card). */
        var wrap = document.querySelector('#' + panelId + ' .sp-invoice-wrap');
        if (!wrap) { setSplitFeedback('Panel not found', false); return; }

        var hiddenInvoiceId = wrap.querySelector('input[name="invoiceid"]');
        var invoiceId = hiddenInvoiceId ? parseInt(hiddenInvoiceId.value, 10) : 0;
        if (invoiceId <= 0) { setSplitFeedback('No invoice to pay', false); return; }

        var placeVal = (panelId === 'splitplace') ? 'SPLIT' : getTakePosWindow().place;

        /* Confirm before paying directly */
        if (!window.confirm('<?php echo dol_escape_js($langs->trans("TakeposRefundConfirmProcess")); ?>')) return;

        var btn = document.querySelector('#' + panelId + '-footer .sp-cash-btn');
        if (btn) { btn.disabled = true; btn.style.opacity = '0.6'; }

        var url = 'invoice.php?action=valid'
            + '&place=' + encodeURIComponent(placeVal)
            + '&invoiceid=' + invoiceId
            + '&pay=' + encodeURIComponent(payCode)
            + '&amount=0&excess=0'
            + '&token=<?php echo newToken(); ?>';

        $.ajax({
            url: url,
            method: 'GET',
            success: function(response) {
                var hasError = /(ui-state-error|fielderror|class="error")/i.test(response || '');
                if (hasError) {
                    setSplitFeedback('<?php echo dol_escape_js($langs->trans("Error")); ?>', false);
                    if (btn) { btn.disabled = false; btn.style.opacity = ''; }
                } else {
                    setSplitFeedback('<?php echo dol_escape_js($langs->trans("Modified")); ?>', true);
                    loadSplitPanels();
                    var pos = getTakePosWindow();
                    if (typeof pos.Refresh === 'function') pos.Refresh();
                }
            },
            error: function() {
                setSplitFeedback('<?php echo dol_escape_js($langs->trans("Error")); ?>', false);
                if (btn) { btn.disabled = false; btn.style.opacity = ''; }
            }
        });
    }

    function loadSplitPanels() {
        $("#currentplace .splitsale").load("invoice.php?place="+getTakePosWindow().place+"&invoiceid="+getTakePosWindow().invoiceid, function() {
            bindSplitPanel('currentplace');
            updatePanelFooter('currentplace');
        });
        $("#splitplace .splitsale").load("invoice.php?place=SPLIT", function() {
            bindSplitPanel('splitplace');
            updatePanelFooter('splitplace');
        });
    }

    function submitSplitMove(containerId, split, confirmMessage) {
        var lineIds = getSelectedLineIds(containerId);
        if (!lineIds.length) {
            setSplitFeedback(splitI18n.selectLine, false);
            return;
        }
        if (!window.confirm(confirmMessage)) {
            return;
        }

        setSplitFeedback('', false);
        var splitPostData = new URLSearchParams();
        splitPostData.append('action', 'split');
        splitPostData.append('token', '<?php echo newToken(); ?>');
        splitPostData.append('line_ids_json', JSON.stringify(lineIds));
        splitPostData.append('split', split);
        splitPostData.append('place', '<?php echo dol_escape_js((string) $place); ?>');

        $.ajax({
            url: "split.php",
            method: "POST",
            data: splitPostData.toString(),
            contentType: "application/x-www-form-urlencoded",
            context: document.body
        }).done(function() {
            setSplitFeedback(splitI18n.moveSuccess, true);
            loadSplitPanels();
            updatePanelFooter('currentplace');
            updatePanelFooter('splitplace');
            if (typeof getTakePosWindow().Refresh === 'function') {
                getTakePosWindow().Refresh();
            }
        }).fail(function() {
            setSplitFeedback(splitI18n.moveFailed, false);
        });
    }

    $( document ).ready(function() {
        if (getTakePosWindow().place=='SPLIT') {
            getTakePosWindow().place=0;
            getTakePosWindow().invoiceid=0;
            getTakePosWindow().Refresh();
        }
        $("#headersplit1").text("<?php echo $langs->trans("Place");?> " + getTakePosWindow().place);
        $("#headersplit2").text("<?php echo $langs->trans("SplitSale");?>");
        $("#btn-move-to-split").on('click', function() {
            submitSplitMove('currentplace', 1, splitI18n.confirmToSplit);
        });
        $("#btn-move-back").on('click', function() {
            submitSplitMove('splitplace', 0, splitI18n.confirmToOrigin);
        });
        $("#btn-refresh-split").on('click', loadSplitPanels);
        loadSplitPanels();
    });
</script>

<!-- ── Top bar ─────────────────────────────────────────────────────────────── -->
<div class="sp-topbar">
    <div class="sp-topbar-title">
        <span class="fa fa-cut" style="margin-right:7px;opacity:.8"></span>
        <?php echo dol_escape_htmltag($langs->trans("TakeposUiSplitSale")); ?>
    </div>
    <div class="sp-topbar-actions">
        <div id="split-feedback" class="sp-feedback"></div>
        <button type="button" class="sp-btn sp-btn-refresh" id="btn-refresh-split">
            <span class="fa fa-sync-alt"></span>
            <span><?php echo dol_escape_htmltag($langs->trans("TakeposCommonRefresh")); ?></span>
        </button>
    </div>
</div>

<!-- ── Two panels + divider ───────────────────────────────────────────────── -->
<div class="sp-panels">

    <!-- Left: original invoice -->
    <div class="sp-panel" id="currentplace">
        <div class="sp-panel-head">
            <div>
                <span class="sp-panel-label"><?php echo dol_escape_htmltag($langs->trans("Place")); ?></span>
                <span class="sp-panel-title" id="headersplit1">—</span>
            </div>
            <span class="sp-selected-badge" data-badge="currentplace"></span>
        </div>
        <div class="sp-hint">
            <span class="fa fa-hand-pointer" style="opacity:.6"></span>
            <?php echo dol_escape_htmltag($langs->trans("TakeposCommonSelect")); ?> <?php echo dol_escape_htmltag($langs->trans("Line")); ?>
        </div>
        <div class="sp-invoice-wrap">
            <div class="splitsale">
                <div class="sp-empty">
                    <span class="sp-empty-icon fa fa-spinner fa-spin"></span>
                </div>
            </div>
        </div>
        <div class="sp-panel-footer" id="currentplace-footer" style="display:none">
            <div class="sp-footer-total">
                <span class="sp-footer-total-label"><?php echo dol_escape_htmltag($langs->trans("TotalTTCShort")); ?></span>
                <span class="sp-footer-total-value" id="currentplace-total">—</span>
            </div>
            <button type="button" class="sp-cash-btn" onclick="splitPanelDirectPay('currentplace','LIQ')">
                <span class="fa fa-coins"></span>
                <?php echo dol_escape_htmltag($langs->trans("TakeposUiCash")); ?>
            </button>
            <button type="button" class="sp-pay-btn" onclick="splitPanelPay('currentplace')">
                <span class="fa fa-cash-register"></span>
                <?php echo dol_escape_htmltag($langs->trans("TakeposUiPayment")); ?>
            </button>
        </div>
    </div>

    <!-- Middle: move buttons -->
    <div class="sp-divider">
        <button type="button" class="sp-move-btn sp-move-right" id="btn-move-to-split"
                title="<?php echo dol_escape_htmltag($langs->trans("Move")); ?> →">
            <span class="fa fa-arrow-right"></span>
        </button>
        <span class="sp-divider-label">Move</span>
        <button type="button" class="sp-move-btn sp-move-left" id="btn-move-back"
                title="← <?php echo dol_escape_htmltag($langs->trans("Back")); ?>">
            <span class="fa fa-arrow-left"></span>
        </button>
    </div>

    <!-- Right: split invoice -->
    <div class="sp-panel" id="splitplace">
        <div class="sp-panel-head">
            <div>
                <span class="sp-panel-label"><?php echo dol_escape_htmltag($langs->trans("SplitSale")); ?></span>
                <span class="sp-panel-title" id="headersplit2">—</span>
            </div>
            <span class="sp-selected-badge" data-badge="splitplace"></span>
        </div>
        <div class="sp-hint">
            <span class="fa fa-hand-pointer" style="opacity:.6"></span>
            <?php echo dol_escape_htmltag($langs->trans("TakeposCommonSelect")); ?> <?php echo dol_escape_htmltag($langs->trans("Line")); ?>
        </div>
        <div class="sp-invoice-wrap">
            <div class="splitsale">
                <div class="sp-empty">
                    <span class="sp-empty-icon fa fa-receipt"></span>
                    <span class="sp-empty-text"><?php echo dol_escape_htmltag($langs->trans("Empty")); ?></span>
                </div>
            </div>
        </div>
        <div class="sp-panel-footer" id="splitplace-footer" style="display:none">
            <div class="sp-footer-total">
                <span class="sp-footer-total-label"><?php echo dol_escape_htmltag($langs->trans("TotalTTCShort")); ?></span>
                <span class="sp-footer-total-value" id="splitplace-total">—</span>
            </div>
            <button type="button" class="sp-cash-btn" onclick="splitPanelDirectPay('splitplace','LIQ')">
                <span class="fa fa-coins"></span>
                <?php echo dol_escape_htmltag($langs->trans("TakeposUiCash")); ?>
            </button>
            <button type="button" class="sp-pay-btn" onclick="splitPanelPay('splitplace')">
                <span class="fa fa-cash-register"></span>
                <?php echo dol_escape_htmltag($langs->trans("TakeposUiPayment")); ?>
            </button>
        </div>
    </div>

</div>

<?php echo takeposHelpRender($langs, __FILE__); ?>
</body>
</html>