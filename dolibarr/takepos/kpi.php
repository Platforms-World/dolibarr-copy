<?php
/**
 * KPI dashboard page.
 */
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');
if (!defined('NOREQUIREAJAX')) define('NOREQUIREAJAX', '1');

if (!defined('DOL_DOCUMENT_ROOT')) {
    $mainPath = __DIR__ . '/../main.inc.php';
    if (!file_exists($mainPath)) {
        $mainPath = __DIR__ . '/../../main.inc.php';
    }
    require $mainPath;
}

require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposAccess.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposAudit.class.php';

$langs->loadLangs(array('cashdesk', 'main', 'bills', 'takeposcustom@takepos'));

TakeposAccess::requireFrontendAccess(
    $db,
    $user,
    'takepos.kpi_dashboard',
    'takepos.analytics.view',
    isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null,
    $langs->trans('TakeposWorkspaceKpiAccessDenied'),
    array('page' => 'kpi.php')
);

TakeposAudit::logEvent($db, $user, 'kpi_dashboard_opened', TakeposAudit::SEVERITY_INFO, array('source' => 'kpi_page'), 'KPI dashboard opened');

$title = $langs->trans('TakeposKpiPageTitle');
$head = '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
$arrayofcss = array('/takepos/css/workspace.css');
$arrayofjs = array('/takepos/js/kpi.js');
$disablejs = 0;
$disablehead = 0;
$kpiI18n = array(
    'Loading' => $langs->trans('TakeposReportsLoading'),
    'LoadingFilters' => $langs->trans('TakeposKpiLoadingFilters'),
    'Running' => $langs->trans('TakeposKpiRunning'),
    'InvalidJson' => $langs->trans('TakeposKpiInvalidJson'),
    'UnexpectedEmpty' => $langs->trans('TakeposKpiUnexpectedEmpty'),
    'Rejected' => $langs->trans('TakeposKpiRejected'),
    'All' => $langs->trans('TakeposReportsAll'),
    'GrossSales' => $langs->trans('TakeposKpiGrossSales'),
    'NetSales' => $langs->trans('TakeposKpiNetSales'),
    'RefundAmount' => $langs->trans('TakeposKpiRefundAmount'),
    'RefundCount' => $langs->trans('TakeposKpiRefundCount'),
    'AvgBasket' => $langs->trans('TakeposKpiAvgBasket'),
    'Tickets' => $langs->trans('TakeposKpiTickets'),
    'TopCashier' => $langs->trans('TakeposKpiTopCashier'),
    'TopStore' => $langs->trans('TakeposKpiTopStore'),
    'DiscrepancyCount' => $langs->trans('TakeposKpiDiscrepancyCount'),
    'VoidCount' => $langs->trans('TakeposKpiVoidCount'),
    'NoData' => $langs->trans('TakeposKpiNoData'),
    'Hour' => $langs->trans('TakeposKpiHour'),
    'Amount' => $langs->trans('TakeposKpiAmount'),
    'Cashier' => $langs->trans('TakeposReportsCashier'),
    'Code' => $langs->trans('TakeposKpiCode'),
    'Label' => $langs->trans('TakeposKpiLabel'),
    'Ref' => $langs->trans('TakeposReportsProductRef'),
    'Qty' => $langs->trans('TakeposKpiQty'),
    'Terminal' => $langs->trans('TakeposReportsTerminal'),
    'Status' => $langs->trans('TakeposReportsStatus'),
    'Open' => $langs->trans('TakeposKpiOpen'),
    'Close' => $langs->trans('TakeposKpiClose'),
    'Expected' => $langs->trans('TakeposKpiExpected'),
    'Counted' => $langs->trans('TakeposKpiCounted'),
    'Difference' => $langs->trans('TakeposKpiDifference'),
    'ResetFilters' => $langs->trans('TakeposExpenseLedgerResetFilters'),
    'LoadFiltersFailed' => $langs->trans('TakeposKpiLoadFiltersFailed'),
    'Loaded' => $langs->trans('TakeposKpiLoaded'),
    'RunFailed' => $langs->trans('TakeposKpiRunFailed')
);

top_htmlhead($head, $title, $disablejs, $disablehead, $arrayofjs, $arrayofcss);
?>
<body class="takepos-workspace-reports-body">
<div class="takepos-workspace-reports-page">
    <h2 class="takepos-workspace-title"><?php echo dol_escape_htmltag($langs->trans('TakeposKpiTitle')); ?></h2>

    <script>window.takeposKpiI18n=<?php echo json_encode($kpiI18n, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;</script>
    <div id="takepos-kpi-config"
         data-endpoint="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/ajax/get_kpi.php'); ?>"></div>
    <div id="takepos-kpi-feedback" class="takepos-workspace-feedback hidden" role="status" aria-live="polite"></div>
    <div id="takepos-kpi-loading" class="takepos-workspace-loading hidden"><?php echo dol_escape_htmltag($langs->trans('TakeposReportsLoading')); ?></div>

    <section class="takepos-workspace-panel takepos-workspace-filter-panel">
        <h3><?php echo dol_escape_htmltag($langs->trans('TakeposCommonFilters')); ?></h3>
        <div class="takepos-workspace-filter-grid">
            <div><label for="date_from"><?php echo dol_escape_htmltag($langs->trans('TakeposReportsDateFrom')); ?></label><input type="date" id="date_from"></div>
            <div><label for="date_to"><?php echo dol_escape_htmltag($langs->trans('TakeposReportsDateTo')); ?></label><input type="date" id="date_to"></div>
            <div><label for="cashier_id"><?php echo dol_escape_htmltag($langs->trans('TakeposReportsCashier')); ?></label><select id="cashier_id"><option value=""><?php echo dol_escape_htmltag($langs->trans('TakeposReportsAll')); ?></option></select></div>
            <div><label for="terminal_code"><?php echo dol_escape_htmltag($langs->trans('TakeposReportsTerminal')); ?></label><select id="terminal_code"><option value=""><?php echo dol_escape_htmltag($langs->trans('TakeposReportsAll')); ?></option></select></div>
            <div><label for="store_id"><?php echo dol_escape_htmltag($langs->trans('TakeposReportsStore')); ?></label><select id="store_id"><option value=""><?php echo dol_escape_htmltag($langs->trans('TakeposReportsAll')); ?></option></select></div>
            <div><label for="payment_method"><?php echo dol_escape_htmltag($langs->trans('TakeposReportsPaymentMethod')); ?></label><select id="payment_method"><option value=""><?php echo dol_escape_htmltag($langs->trans('TakeposReportsAll')); ?></option></select></div>
        </div>
        <div class="takepos-workspace-filter-actions">
            <button type="button" id="btn_run" class="button button-save"><?php echo dol_escape_htmltag($langs->trans('TakeposKpiRun')); ?></button>
            <button type="button" id="btn_reset" class="button button-cancel"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseLedgerResetFilters')); ?></button>
            <button type="button" id="btn_export" class="button"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseLedgerExportCsv')); ?></button>
        </div>
    </section>

    <section class="takepos-workspace-panel">
        <h3><?php echo dol_escape_htmltag($langs->trans('TakeposKpiCards')); ?></h3>
        <div class="takepos-workspace-summary-cards" id="kpi_cards"></div>
    </section>

    <section class="takepos-workspace-panel">
        <h3><?php echo dol_escape_htmltag($langs->trans('TakeposKpiSalesByHour')); ?></h3>
        <div class="takepos-workspace-table-wrap"><table id="table_sales_hour" class="takepos-workspace-table"></table></div>
    </section>

    <section class="takepos-workspace-panel">
        <h3><?php echo dol_escape_htmltag($langs->trans('TakeposKpiTicketsByCashier')); ?></h3>
        <div class="takepos-workspace-table-wrap"><table id="table_cashier" class="takepos-workspace-table"></table></div>
    </section>

    <section class="takepos-workspace-panel">
        <h3><?php echo dol_escape_htmltag($langs->trans('TakeposKpiPaymentMix')); ?></h3>
        <div class="takepos-workspace-table-wrap"><table id="table_paymix" class="takepos-workspace-table"></table></div>
    </section>

    <section class="takepos-workspace-panel">
        <h3><?php echo dol_escape_htmltag($langs->trans('TakeposKpiTopProducts')); ?></h3>
        <div class="takepos-workspace-table-wrap"><table id="table_top_products" class="takepos-workspace-table"></table></div>
    </section>

    <section class="takepos-workspace-panel">
        <h3><?php echo dol_escape_htmltag($langs->trans('TakeposKpiTerminalPerformance')); ?></h3>
        <div class="takepos-workspace-table-wrap"><table id="table_terminal" class="takepos-workspace-table"></table></div>
    </section>

    <section class="takepos-workspace-panel">
        <h3><?php echo dol_escape_htmltag($langs->trans('TakeposKpiShiftReconciliation')); ?></h3>
        <div class="takepos-workspace-table-wrap"><table id="table_shift" class="takepos-workspace-table"></table></div>
    </section>
</div>
</body>
<?php
llxFooter();
$db->close();
