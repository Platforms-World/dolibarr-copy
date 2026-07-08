<?php
/**
 * reports_v2.php — Kafo POS v2 · التقارير
 * نسخة جديدة بتصميم v2 — يستخدم reports.js الأصلي بدون تعديل
 */
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML'))  define('NOREQUIREHTML', '1');
if (!defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX', '1');

if (!defined('DOL_DOCUMENT_ROOT')) {
    $p = __DIR__ . '/../main.inc.php';
    require (file_exists($p) ? $p : __DIR__ . '/../../main.inc.php');
}
require_once DOL_DOCUMENT_ROOT . '/takepos/lib/takepos_lang.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposAccess.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposAudit.class.php';

$langs->loadLangs(array('cashdesk', 'bills', 'main', 'takeposcustom@takepos'));
TakeposAccess::requireFrontendAccess($db,$user,'takepos.reports','takepos.action.reports_view',
    isset($_SESSION['takeposterminal'])?(int)$_SESSION['takeposterminal']:null,
    $langs->trans('TakeposReportsAccessDenied'),array('page'=>'reports_v2.php'));
TakeposAudit::logEvent($db,$user,'open_reports',TakeposAudit::SEVERITY_INFO,array('source'=>'reports_v2'),'POS reports v2 opened');

$defaultDateFrom = dol_print_date(dol_get_first_hour(dol_now()), '%Y-%m-%d');
$defaultDateTo   = dol_print_date(dol_get_last_hour(dol_now()),  '%Y-%m-%d');
$currentLangCode = takeposCurrentLangCode($langs, $user);

/* ── reportsI18n — must match reports.js expectations ── */
$reportsI18n = array(
    'All'             => $langs->trans('TakeposReportsAll'),
    'DateFrom'        => $langs->trans('TakeposReportsDateFrom'),
    'DateTo'          => $langs->trans('TakeposReportsDateTo'),
    'Generate'        => $langs->trans('TakeposReportsGenerate'),
    'Reset'           => $langs->trans('TakeposReportsReset'),
    'Loading'         => $langs->trans('TakeposReportsLoading'),
    'NoData'          => $langs->trans('TakeposReportsNoDataAvailable'),
    'TotalInvoices'   => $langs->trans('TakeposReportsTotalInvoices'),
    'TotalQty'        => $langs->trans('TakeposReportsTotalQuantitySold'),
    'SubtotalHt'      => $langs->trans('TakeposReportsTotalSalesBeforeTax'),
    'TotalTax'        => $langs->trans('TakeposReportsTotalTax'),
    'TotalDiscount'   => $langs->trans('TakeposReportsTotalDiscount'),
    'TotalTtc'        => $langs->trans('TakeposReportsTotalFinalSales'),
    'Date'            => $langs->trans('TakeposExpenseDate'),
    'Ref'             => $langs->trans('TakeposExpenseRef'),
    'Customer'        => $langs->trans('Customer'),
    'Cashier'         => $langs->trans('TakeposReportsCashier'),
    'Terminal'        => $langs->trans('TakeposReportsTerminal'),
    'Store'           => $langs->trans('TakeposCommonStore'),
    'PayMethod'       => $langs->trans('TakeposReportsPaymentMethod'),
    'Product'         => $langs->trans('TakeposReportsProduct'),
    'Qty'             => $langs->trans('TakeposReportsQty'),
    'UnitHt'          => $langs->trans('TakeposReportsUnitHt'),
    'TotalHt'         => $langs->trans('TakeposReportsTotalHt'),
    'TotalTtcShort'   => $langs->trans('TakeposReportsTotalTtcShort'),
    'Discount'        => $langs->trans('TakeposReportsTotalDiscount'),
    'Tax'             => $langs->trans('TakeposReportsTotalTax'),
    'Status'          => $langs->trans('TakeposCommonStatus'),
    'Actions'         => $langs->trans('TakeposCommonActions'),
    'Open'            => $langs->trans('TakeposCommonOpen'),
    'Receipt'         => $langs->trans('Receipt'),
    'ReceiptUrl'      => DOL_URL_ROOT . '/takepos/receipt.php?facid=',
    'InvoiceUrl'      => DOL_URL_ROOT . '/compta/facture/card.php?facid=',
    'KpiUrl'          => DOL_URL_ROOT . '/takepos/kpi.php?langs=' . rawurlencode($currentLangCode),
    'ExportCsv'       => $langs->trans('TakeposExpenseLedgerExportCsv'),
    'Print'           => $langs->trans('TakeposReportsPrint'),
    'Kpi'             => $langs->trans('TakeposReportsKpi'),
    'CheckNumber'     => $langs->trans('TakeposReportsCheckNumber'),
    'BankName'        => $langs->trans('TakeposReportsBankName'),
    'DueDate'         => $langs->trans('TakeposReportsDueDate'),
    'Outstanding'     => $langs->trans('TakeposReportsOutstanding'),
    'SocId'           => $langs->trans('TakeposReportsSocId'),
    'SocName'         => $langs->trans('TakeposReportsSocName'),
    'Balance'         => $langs->trans('TakeposReportsBalance'),
    'InvoiceCount'    => $langs->trans('TakeposReportsInvoiceCount'),
    'Velocity'        => $langs->trans('TakeposReportsVelocity'),
    'StockMove'       => $langs->trans('TakeposReportsStockMove'),
    'MoveType'        => $langs->trans('TakeposReportsMoveType'),
    'Batch'           => $langs->trans('TakeposReportsBatch'),
    'ExpiryDate'      => $langs->trans('ExpirationDate'),
    'ExpiryStatus'    => $langs->trans('Status'),
);

$FA    = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css';
$title = $langs->trans('TakeposReportsPageTitle');
$head  = '<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="'.$FA.'">';
$arrayofcss = array('/takepos/css/workspace_v2.css');
$arrayofjs  = array('/takepos/js/reports.js');
top_htmlhead($head, $title, 0, 0, $arrayofjs, $arrayofcss);

$stockModuleEnabled = isModEnabled('stock');
$batchModuleEnabled = isModEnabled('productbatch') || getDolGlobalInt('PRODUCTBATCH_ACTIVATED') == 1;
?>
<body class="kfv2-body">
<?php
$v2PageTitle = $langs->trans('TakeposReportsTitle');
$v2PageIcon  = 'fa-chart-line';
$v2BackUrl   = DOL_URL_ROOT . '/takepos/pos_v2.php';
require __DIR__ . '/topbar_v2.php';
?>

<div class="kfv2-page-body">

    <!-- i18n + config for reports.js — must keep same IDs -->
    <script>window.takeposReportsI18n=<?php echo json_encode($reportsI18n, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;</script>
    <div id="takepos-workspace-reports-config"
         data-endpoint="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/ajax/get_reports.php'); ?>"
         data-default-date-from="<?php echo dol_escape_htmltag($defaultDateFrom); ?>"
         data-default-date-to="<?php echo dol_escape_htmltag($defaultDateTo); ?>"></div>

    <!-- feedback — same IDs as original for reports.js -->
    <div id="takepos-workspace-report-feedback" class="kfv2-msg kfv2-hidden" role="status" aria-live="polite"></div>
    <div id="takepos-workspace-report-loading" class="kfv2-msg info kfv2-hidden">
        <i class="fa-solid fa-spinner fa-spin"></i> <?php echo dol_escape_htmltag($langs->trans('TakeposReportsLoading')); ?>
    </div>

    <!-- Filter Panel -->
    <div class="kfv2-filter-panel">
        <h3><i class="fa-solid fa-sliders"></i> <?php echo dol_escape_htmltag($langs->trans('TakeposReportsFilterArea')); ?></h3>
        <div class="kfv2-form-grid">
            <div class="kfv2-field"><label><?php echo dol_escape_htmltag($langs->trans('TakeposReportsDateFrom')); ?></label><input type="text" id="date_from" placeholder="YYYY-MM-DD"></div>
            <div class="kfv2-field"><label><?php echo dol_escape_htmltag($langs->trans('TakeposReportsDateTo')); ?></label><input type="text" id="date_to" placeholder="YYYY-MM-DD"></div>
            <div class="kfv2-field"><label><?php echo dol_escape_htmltag($langs->trans('TakeposReportsCashier')); ?></label><select id="cashier_id"><option value=""><?php echo dol_escape_htmltag($langs->trans('TakeposReportsAll')); ?></option></select></div>
            <div class="kfv2-field"><label><?php echo dol_escape_htmltag($langs->trans('TakeposReportsTerminal')); ?></label><select id="terminal_id"><option value=""><?php echo dol_escape_htmltag($langs->trans('TakeposReportsAll')); ?></option></select></div>
            <div class="kfv2-field"><label><?php echo dol_escape_htmltag($langs->trans('TakeposReportsStore')); ?></label><select id="store_id"><option value=""><?php echo dol_escape_htmltag($langs->trans('TakeposReportsAll')); ?></option></select></div>
            <div class="kfv2-field"><label><?php echo dol_escape_htmltag($langs->trans('TakeposReportsProduct')); ?></label><input list="product_list" id="product_search" placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposReportsSearchProduct')); ?>"><datalist id="product_list"></datalist><input type="hidden" id="product_id"></div>
            <div class="kfv2-field"><label><?php echo dol_escape_htmltag($langs->trans('TakeposReportsCustomer')); ?></label><input list="customer_list" id="customer_search" placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposReportsSearchCustomer')); ?>"><datalist id="customer_list"></datalist><input type="hidden" id="customer_id"></div>
            <div class="kfv2-field"><label><?php echo dol_escape_htmltag($langs->trans('TakeposReportsInvoiceStatus')); ?></label><select id="invoice_status"><option value=""><?php echo dol_escape_htmltag($langs->trans('TakeposReportsAll')); ?></option></select></div>
            <div class="kfv2-field"><label><?php echo dol_escape_htmltag($langs->trans('TakeposReportsPaymentMethod')); ?></label><select id="payment_method"><option value=""><?php echo dol_escape_htmltag($langs->trans('TakeposReportsAll')); ?></option></select></div>
        </div>
        <div class="kfv2-actions">
            <button type="button" id="btn_generate" class="kfv2-btn kfv2-btn-primary"><i class="fa-solid fa-chart-bar"></i> <?php echo dol_escape_htmltag($langs->trans('TakeposReportsGenerate')); ?></button>
            <button type="button" id="btn_reset"    class="kfv2-btn kfv2-btn-danger"><i class="fa-solid fa-rotate"></i> <?php echo dol_escape_htmltag($langs->trans('TakeposReportsReset')); ?></button>
            <button type="button" id="btn_export"   class="kfv2-btn kfv2-btn-outline"><i class="fa-solid fa-file-csv"></i> <?php echo dol_escape_htmltag($langs->trans('TakeposExpenseLedgerExportCsv')); ?></button>
            <button type="button" id="btn_print"    class="kfv2-btn kfv2-btn-outline"><i class="fa-solid fa-print"></i> <?php echo dol_escape_htmltag($langs->trans('TakeposReportsPrint')); ?></button>
            <button type="button" id="btn_kpi"      class="kfv2-btn kfv2-btn-outline" onclick="window.location.href='<?php echo dol_escape_htmltag(DOL_URL_ROOT.'/takepos/kpi.php?langs='.rawurlencode($currentLangCode)); ?>'"><i class="fa-solid fa-gauge-high"></i> <?php echo dol_escape_htmltag($langs->trans('TakeposReportsKpi')); ?></button>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="kfv2-kpis">
        <?php foreach (array(
            'total_invoices' => $langs->trans('TakeposReportsTotalInvoices'),
            'total_qty'      => $langs->trans('TakeposReportsTotalQuantitySold'),
            'subtotal_ht'    => $langs->trans('TakeposReportsTotalSalesBeforeTax'),
            'total_tax'      => $langs->trans('TakeposReportsTotalTax'),
            'total_discount' => $langs->trans('TakeposReportsTotalDiscount'),
            'total_ttc'      => $langs->trans('TakeposReportsTotalFinalSales'),
        ) as $id => $label): ?>
        <div class="kfv2-kpi">
            <div class="kk"><?php echo dol_escape_htmltag($label); ?></div>
            <div class="kv num" id="card_<?php echo $id; ?>">0</div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Tables + Tabs -->
    <div class="kfv2-card">
        <div class="kfv2-card-head"><i class="fa-solid fa-table"></i> <?php echo dol_escape_htmltag($langs->trans('TakeposReportsTableResultsArea')); ?></div>
        <div class="kfv2-card-body" style="padding-top:0">
            <div class="kfv2-tabs" style="padding-top:16px">
                <?php
                $tabs = array(
                    'summary'         => $langs->trans('TakeposReportsTabSummary'),
                    'cashier'         => $langs->trans('TakeposReportsTabCashier'),
                    'terminal'        => $langs->trans('TakeposReportsTabTerminal'),
                    'product'         => $langs->trans('TakeposReportsTabProduct'),
                    'detailed'        => $langs->trans('TakeposReportsTabDetailed'),
                    'cheques'         => $langs->trans('TakeposReportsCheques'),
                    'receivables'     => $langs->trans('TakeposReportsReceivables'),
                    'payables'        => $langs->trans('TakeposReportsPayables'),
                    'product_velocity'=> $langs->trans('TakeposReportsProductVelocity'),
                    'stock_moves'     => $langs->trans('TakeposReportsStockMoves'),
                    'near_expiry'     => $langs->trans('TakeposReportsNearExpiry'),
                );
                $first = true;
                foreach ($tabs as $key => $label):
                ?>
                <button type="button" class="kfv2-tab takepos-workspace-tab<?php echo $first ? ' active' : ''; ?>" data-report="<?php echo dol_escape_htmltag($key); ?>">
                    <?php echo dol_escape_htmltag($label); ?>
                </button>
                <?php $first = false; endforeach; ?>
            </div>

            <?php foreach (array_keys($tabs) as $i => $key): ?>
            <div class="kfv2-table-wrap<?php echo $i > 0 ? ' hidden' : ''; ?>">
                <?php if ($key === 'stock_moves' && !$stockModuleEnabled): ?>
                <div class="kfv2-msg warn"><i class="fa-solid fa-triangle-exclamation"></i> يجب تفعيل وحدة المخزون (Stock) من الإعدادات</div>
                <?php endif; ?>
                <?php if ($key === 'near_expiry' && !$batchModuleEnabled): ?>
                <div class="kfv2-msg warn"><i class="fa-solid fa-triangle-exclamation"></i> يجب تفعيل إدارة الدفعات/الأرقام التسلسلية من الإعدادات</div>
                <?php endif; ?>
                <table id="table_<?php echo $key; ?>" class="kfv2-table takepos-workspace-table"></table>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div><!-- /kfv2-page-body -->

<script>
/* bridge: reports.js uses .takepos-workspace-tab and .takepos-workspace-table classes */
/* kfv2-tab is also applied above, so the toggle logic in reports.js still works */
/* Additional CSS bridge for reports.js class names */
document.addEventListener('DOMContentLoaded', function () {
    /* reports.js toggles .hidden on .takepos-workspace-table-wrap — we added kfv2-table-wrap above
       but reports.js will look for .takepos-workspace-table-wrap. We need to alias. */
    document.querySelectorAll('.kfv2-table-wrap').forEach(function (el) {
        el.classList.add('takepos-workspace-table-wrap');
    });
    document.querySelectorAll('.kfv2-tab').forEach(function (el) {
        el.classList.add('takepos-workspace-tab');
    });
    document.getElementById('takepos-workspace-report-feedback') &&
        document.getElementById('takepos-workspace-report-feedback').classList.add('takepos-workspace-feedback');
    document.getElementById('takepos-workspace-report-loading') &&
        document.getElementById('takepos-workspace-report-loading').classList.add('takepos-workspace-loading');
});
</script>

</body>
<?php llxFooter(); $db->close();
