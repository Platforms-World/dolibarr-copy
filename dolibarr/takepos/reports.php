<?php
/**
 * POS Reports screen.
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

if (!defined('DOL_DOCUMENT_ROOT')) {
    $mainPath = __DIR__ . '/../main.inc.php';
    if (!file_exists($mainPath)) {
        $mainPath = __DIR__ . '/../../main.inc.php';
    }
    require $mainPath;
}
require_once DOL_DOCUMENT_ROOT . '/takepos/lib/takepos_lang.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposAccess.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposAudit.class.php';

$langs->loadLangs(array('cashdesk', 'bills', 'main', 'takeposcustom@takepos'));

TakeposAccess::requireFrontendAccess(
    $db,
    $user,
    'takepos.reports',
    'takepos.action.reports_view',
    isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null,
    $langs->trans('TakeposReportsAccessDenied'),
    array('page' => 'reports.php')
);

TakeposAudit::logEvent(
    $db,
    $user,
    'open_reports',
    TakeposAudit::SEVERITY_INFO,
    array('source' => 'reports_page'),
    'POS reports screen opened'
);

$form = new Form($db);
$disablejs = 0;
$disablehead = 0;
$arrayofjs = array('/takepos/js/reports.js');
$arrayofcss = array('/takepos/css/workspace.css');
$title = $langs->trans('TakeposReportsPageTitle');
$head = '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
top_htmlhead($head, $title, $disablejs, $disablehead, $arrayofjs, $arrayofcss);
$defaultDateFrom = dol_print_date(dol_get_first_hour(dol_now()), '%Y-%m-%d');
$defaultDateTo   = dol_print_date(dol_get_last_hour(dol_now()), '%Y-%m-%d');
$currentLangCode = takeposCurrentLangCode($langs, isset($user) ? $user : null);
$dateFormatHint = 'YYYY-MM-DD';
$reportsI18n = array(
    'Loading' => $langs->trans('TakeposReportsLoading'),
    'LoadingFilters' => $langs->trans('TakeposReportsLoadingFilters'),
    'InvalidJson' => $langs->trans('TakeposReportsInvalidJson'),
    'UnexpectedEmpty' => $langs->trans('TakeposReportsUnexpectedEmpty'),
    'Rejected' => $langs->trans('TakeposReportsRejected'),
    'All' => $langs->trans('TakeposReportsAll'),
    'NoDataAvailable' => $langs->trans('TakeposReportsNoDataAvailable'),
    'NoMatchingRows' => $langs->trans('TakeposReportsNoMatchingRows'),
    'TotalInvoices' => $langs->trans('TakeposReportsTotalInvoices'),
    'TotalQuantity' => $langs->trans('TakeposReportsTotalQuantitySold'),
    'Subtotal' => $langs->trans('TakeposExpenseAmountHt'),
    'Tax' => $langs->trans('TakeposReportsTotalTax'),
    'Discount' => $langs->trans('TakeposReportsTotalDiscount'),
    'TotalSales' => $langs->trans('TakeposReportsTotalFinalSales'),
    'CashierName' => $langs->trans('TakeposReportsCashierName'),
    'NumberOfInvoices' => $langs->trans('TakeposReportsInvoiceCount'),
    'QuantitySold' => $langs->trans('TakeposReportsTotalQuantitySold'),
    'AverageInvoiceValue' => $langs->trans('TakeposExpenseLedgerTotalTtc'),
    'TerminalName' => $langs->trans('TakeposReportsTerminalName'),
    'Invoices' => $langs->trans('TakeposReportsTotalInvoices'),
    'Quantity' => $langs->trans('Qty'),
    'ProductRef' => $langs->trans('TakeposReportsProductRef'),
    'ProductLabel' => $langs->trans('TakeposReportsProductLabel'),
    'AveragePrice' => $langs->trans('TakeposReportsAveragePrice'),
    'InvoiceRef' => $langs->trans('TakeposReportsInvoiceRef'),
    'Date' => $langs->trans('TakeposReportsDate'),
    'Cashier' => $langs->trans('TakeposReportsCashier'),
    'Terminal' => $langs->trans('TakeposReportsTerminal'),
    'Store' => $langs->trans('TakeposReportsStore'),
    'Customer' => $langs->trans('TakeposReportsCustomer'),
    'Total' => $langs->trans('TakeposReportsTotal'),
    'PaymentMethod' => $langs->trans('TakeposReportsPaymentMethod'),
    'Status' => $langs->trans('TakeposReportsStatus'),
    'LoadFiltersFailed' => $langs->trans('TakeposReportsLoadingFiltersFailed'),
    'GeneratingReport' => $langs->trans('TakeposReportsGenerate'),
    'NoRecordsFound' => $langs->trans('TakeposExpenseLedgerNoResults'),
    'GeneratedSuccess' => $langs->trans('TakeposReportsGenerated'),
    'GeneratedDetailedRows' => $langs->trans('TakeposReportsGenerated'),
    'FiltersReset' => $langs->trans('TakeposExpenseLedgerResetFilters'),
    'GenerateFailed' => $langs->trans('TakeposReportsGenerateFailed'),
    'Cheques' => $langs->trans('TakeposReportsCheques'),
    'Receivables' => $langs->trans('TakeposReportsReceivables'),
    'Payables' => $langs->trans('TakeposReportsPayables'),
    'ProductVelocity' => $langs->trans('TakeposReportsProductVelocity'),
    'StockMoves' => $langs->trans('TakeposReportsStockMoves'),
    'NearExpiry' => $langs->trans('TakeposReportsNearExpiry'),
    'ChequeNumber' => $langs->trans('TakeposReportsChequeNumber'),
    'Supplier' => $langs->trans('Supplier'),
    'Bank' => $langs->trans('Bank'),
    'Amount' => $langs->trans('Amount'),
    'ChequeDate' => $langs->trans('TakeposChequeDate'),
    'CollectionDate' => $langs->trans('TakeposChequeDueDate'),
    'Reminder' => $langs->trans('TakeposReportsReminder'),
    'DueDate' => $langs->trans('DateMaxPayment'),
    'Paid' => $langs->trans('AlreadyPaid'),
    'Remaining' => $langs->trans('RemainToPay'),
    'SupplierInvoiceRef' => $langs->trans('SupplierInvoice'),
    'QtyPerDay' => $langs->trans('TakeposReportsQtyPerDay'),
    'MovementClass' => $langs->trans('TakeposReportsMovementClass'),
    'MovementDate' => $langs->trans('Date'),
    'Warehouse' => $langs->trans('Warehouse'),
    'QtyMovement' => $langs->trans('TakeposReportsQtyMovement'),
    'MovementType' => $langs->trans('Type'),
    'MovementLabel' => $langs->trans('Label'),
    'InventoryCode' => $langs->trans('TakeposReportsInventoryCode'),
    'User' => $langs->trans('User'),
    'Batch' => $langs->trans('Batch'),
    'ExpiryDate' => $langs->trans('ExpirationDate'),
    'ExpiryStatus' => $langs->trans('Status')
);

print '<style>#php-debugbar,.phpdebugbar,.php-debugbar,.debugbar,.debug-bar,.debugbar-container,.sf-toolbar,#sfwdt,div[id*="debugbar"],div[class*="debugbar"]{display:none !important;visibility:hidden !important;opacity:0 !important;pointer-events:none !important;}</style>';
print '<script>function takeposHideWorkspaceDebugBars(){var sels=["#php-debugbar",".phpdebugbar",".php-debugbar",".debugbar",".debug-bar",".debugbar-container",".sf-toolbar","#sfwdt","div[id*=\"debugbar\"]","div[class*=\"debugbar\"]"];try{document.querySelectorAll(sels.join(",")).forEach(function(el){el.remove();});}catch(e){}}document.addEventListener("DOMContentLoaded",takeposHideWorkspaceDebugBars);window.addEventListener("load",takeposHideWorkspaceDebugBars);setTimeout(takeposHideWorkspaceDebugBars,300);</script>';
print '<body class="takepos-workspace-reports-body">';
print '<div class="takepos-workspace-reports-page">';
print '<div style="display:flex;align-items:center;gap:14px;margin-bottom:8px;">';
print '<h2 class="takepos-workspace-title" style="margin:0">' . dol_escape_htmltag($langs->trans('TakeposReportsTitle')) . '</h2>';
print '<a href="' . dol_escape_htmltag(DOL_URL_ROOT . '/takepos/index.php?langs=' . rawurlencode($currentLangCode)) . '" class="button" style="margin-left:auto;font-size:0.9em;">'
    . '<span class="fa fa-arrow-left"></span> ' . dol_escape_htmltag($langs->trans('TakeposCommonBackToPos')) . '</a>';
print '</div>';
print '<script>window.takeposReportsI18n=' . json_encode($reportsI18n, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>';
print '<div id="takepos-workspace-reports-config"'
    . ' data-endpoint="' . dol_escape_htmltag(DOL_URL_ROOT . '/takepos/ajax/get_reports.php') . '"'
    . ' data-default-date-from="' . dol_escape_htmltag($defaultDateFrom) . '"'
    . ' data-default-date-to="' . dol_escape_htmltag($defaultDateTo) . '"'
    . '></div>';
print '<div id="takepos-workspace-report-feedback" class="takepos-workspace-feedback hidden" role="status" aria-live="polite"></div>';
print '<div id="takepos-workspace-report-loading" class="takepos-workspace-loading hidden">' . dol_escape_htmltag($langs->trans('TakeposReportsLoading')) . '</div>';

print '<section class="takepos-workspace-panel takepos-workspace-filter-panel">';
print '<h3>' . dol_escape_htmltag($langs->trans('TakeposReportsFilterArea')) . '</h3>';
print '<div class="takepos-workspace-filter-grid">';
print '<div><label for="date_from">' . dol_escape_htmltag($langs->trans('TakeposReportsDateFrom')) . ' (' . dol_escape_htmltag($dateFormatHint) . ')</label><input type="text" id="date_from" inputmode="numeric" pattern="\d{4}-\d{2}-\d{2}" placeholder="' . dol_escape_htmltag($dateFormatHint) . '"></div>';
print '<div><label for="date_to">' . dol_escape_htmltag($langs->trans('TakeposReportsDateTo')) . ' (' . dol_escape_htmltag($dateFormatHint) . ')</label><input type="text" id="date_to" inputmode="numeric" pattern="\d{4}-\d{2}-\d{2}" placeholder="' . dol_escape_htmltag($dateFormatHint) . '"></div>';
print '<div><label for="cashier_id">' . dol_escape_htmltag($langs->trans('TakeposReportsCashier')) . '</label><select id="cashier_id"><option value="">' . dol_escape_htmltag($langs->trans('TakeposReportsAll')) . '</option></select></div>';
print '<div><label for="terminal_id">' . dol_escape_htmltag($langs->trans('TakeposReportsTerminal')) . '</label><select id="terminal_id"><option value="">' . dol_escape_htmltag($langs->trans('TakeposReportsAll')) . '</option></select></div>';
print '<div><label for="store_id">' . dol_escape_htmltag($langs->trans('TakeposReportsStore')) . '</label><select id="store_id"><option value="">' . dol_escape_htmltag($langs->trans('TakeposReportsAll')) . '</option></select></div>';
print '<div><label for="product_search">' . dol_escape_htmltag($langs->trans('TakeposReportsProduct')) . '</label><input list="product_list" id="product_search" placeholder="' . dol_escape_htmltag($langs->trans('TakeposReportsSearchProduct')) . '"><datalist id="product_list"></datalist><input type="hidden" id="product_id"></div>';
print '<div><label for="customer_search">' . dol_escape_htmltag($langs->trans('TakeposReportsCustomer')) . '</label><input list="customer_list" id="customer_search" placeholder="' . dol_escape_htmltag($langs->trans('TakeposReportsSearchCustomer')) . '"><datalist id="customer_list"></datalist><input type="hidden" id="customer_id"></div>';
print '<div><label for="invoice_status">' . dol_escape_htmltag($langs->trans('TakeposReportsInvoiceStatus')) . '</label><select id="invoice_status"><option value="">' . dol_escape_htmltag($langs->trans('TakeposReportsAll')) . '</option></select></div>';
print '<div><label for="payment_method">' . dol_escape_htmltag($langs->trans('TakeposReportsPaymentMethod')) . '</label><select id="payment_method"><option value="">' . dol_escape_htmltag($langs->trans('TakeposReportsAll')) . '</option></select></div>';
print '</div>';
print '<div class="takepos-workspace-filter-actions">';
print '<button type="button" id="btn_generate" class="button button-save">' . dol_escape_htmltag($langs->trans('TakeposReportsGenerate')) . '</button>';
print '<button type="button" id="btn_reset" class="button button-cancel">' . dol_escape_htmltag($langs->trans('TakeposReportsReset')) . '</button>';
print '<button type="button" id="btn_export" class="button">' . dol_escape_htmltag($langs->trans('TakeposExpenseLedgerExportCsv')) . '</button>';
print '<button type="button" id="btn_print" class="button">' . dol_escape_htmltag($langs->trans('TakeposReportsPrint')) . '</button>';
print '<button type="button" id="btn_kpi" class="button" onclick="window.location.href=\'' . dol_escape_htmltag(DOL_URL_ROOT . '/takepos/kpi.php?langs=' . rawurlencode($currentLangCode)) . '\'">' . dol_escape_htmltag($langs->trans('TakeposReportsKpi')) . '</button>';
print '</div>';
print '</section>';

print '<section class="takepos-workspace-panel">';
print '<h3>' . dol_escape_htmltag($langs->trans('TakeposReportsSummaryArea')) . '</h3>';
print '<div class="takepos-workspace-summary-cards">';
$summaryCards = array(
    'total_invoices' => $langs->trans('TakeposReportsTotalInvoices'),
    'total_qty' => $langs->trans('TakeposReportsTotalQuantitySold'),
    'subtotal_ht' => $langs->trans('TakeposReportsTotalSalesBeforeTax'),
    'total_tax' => $langs->trans('TakeposReportsTotalTax'),
    'total_discount' => $langs->trans('TakeposReportsTotalDiscount'),
    'total_ttc' => $langs->trans('TakeposReportsTotalFinalSales'),
);
foreach ($summaryCards as $id => $label) {
    print '<div class="takepos-workspace-card"><div class="takepos-workspace-card-label">' . dol_escape_htmltag($label) . '</div><div class="takepos-workspace-card-value" id="card_' . $id . '">0</div></div>';
}
print '</div>';
print '</section>';

print '<section class="takepos-workspace-panel">';
print '<h3>' . dol_escape_htmltag($langs->trans('TakeposReportsTableResultsArea')) . '</h3>';
print '<div class="takepos-workspace-tabs">';
print '<button type="button" class="takepos-workspace-tab active" data-report="summary">' . dol_escape_htmltag($langs->trans('TakeposReportsTabSummary')) . '</button>';
print '<button type="button" class="takepos-workspace-tab" data-report="cashier">' . dol_escape_htmltag($langs->trans('TakeposReportsTabCashier')) . '</button>';
print '<button type="button" class="takepos-workspace-tab" data-report="terminal">' . dol_escape_htmltag($langs->trans('TakeposReportsTabTerminal')) . '</button>';
print '<button type="button" class="takepos-workspace-tab" data-report="product">' . dol_escape_htmltag($langs->trans('TakeposReportsTabProduct')) . '</button>';
print '<button type="button" class="takepos-workspace-tab" data-report="detailed">' . dol_escape_htmltag($langs->trans('TakeposReportsTabDetailed')) . '</button>';
print '<button type="button" class="takepos-workspace-tab" data-report="cheques">' . dol_escape_htmltag($langs->trans('TakeposReportsCheques')) . '</button>';
print '<button type="button" class="takepos-workspace-tab" data-report="receivables">' . dol_escape_htmltag($langs->trans('TakeposReportsReceivables')) . '</button>';
print '<button type="button" class="takepos-workspace-tab" data-report="payables">' . dol_escape_htmltag($langs->trans('TakeposReportsPayables')) . '</button>';
print '<button type="button" class="takepos-workspace-tab" data-report="product_velocity">' . dol_escape_htmltag($langs->trans('TakeposReportsProductVelocity')) . '</button>';
print '<button type="button" class="takepos-workspace-tab" data-report="stock_moves">' . dol_escape_htmltag($langs->trans('TakeposReportsStockMoves')) . '</button>';
print '<button type="button" class="takepos-workspace-tab" data-report="near_expiry">' . dol_escape_htmltag($langs->trans('TakeposReportsNearExpiry')) . '</button>';
print '</div>';
print '<div class="takepos-workspace-table-wrap"><table id="table_summary" class="takepos-workspace-table"></table></div>';
print '<div class="takepos-workspace-table-wrap hidden"><table id="table_cashier" class="takepos-workspace-table"></table></div>';
print '<div class="takepos-workspace-table-wrap hidden"><table id="table_terminal" class="takepos-workspace-table"></table></div>';
print '<div class="takepos-workspace-table-wrap hidden"><table id="table_product" class="takepos-workspace-table"></table></div>';
print '<div class="takepos-workspace-table-wrap hidden"><table id="table_detailed" class="takepos-workspace-table"></table></div>';
print '<div class="takepos-workspace-table-wrap hidden"><table id="table_cheques" class="takepos-workspace-table"></table></div>';
print '<div class="takepos-workspace-table-wrap hidden"><table id="table_receivables" class="takepos-workspace-table"></table></div>';
print '<div class="takepos-workspace-table-wrap hidden"><table id="table_payables" class="takepos-workspace-table"></table></div>';
print '<div class="takepos-workspace-table-wrap hidden"><table id="table_product_velocity" class="takepos-workspace-table"></table></div>';
// Issue #13 fix: warn if stock module is not enabled
$stockModuleEnabled = isModEnabled('stock');
print '<div class="takepos-workspace-table-wrap hidden">';
if (!$stockModuleEnabled) {
    print '<div style="padding:20px;background:#fff3e0;border:1px solid #ff9800;border-radius:8px;direction:rtl;text-align:right;font-size:14px;color:#e65100;">';
    print '<strong>⚠️ ' . dol_escape_htmltag($langs->trans('TakeposModuleRequired', 'مطلوب تفعيل وحدة')) . ':</strong> ';
    print dol_escape_htmltag($langs->trans('TakeposStockModuleRequiredMsg', 'يجب تفعيل وحدة "المخزون" (Stock) من: الإعدادات → الوحدات/التطبيقات → مخزون'));
    print '</div>';
}
print '<table id="table_stock_moves" class="takepos-workspace-table"></table></div>';
// Issue #13 fix: warn if product batch module is not enabled
$batchModuleEnabled = isModEnabled('productbatch') || getDolGlobalInt('PRODUCTBATCH_ACTIVATED') == 1;
print '<div class="takepos-workspace-table-wrap hidden">';
if (!$batchModuleEnabled) {
    print '<div style="padding:20px;background:#fff3e0;border:1px solid #ff9800;border-radius:8px;direction:rtl;text-align:right;font-size:14px;color:#e65100;">';
    print '<strong>⚠️ ' . dol_escape_htmltag($langs->trans('TakeposModuleRequired', 'مطلوب تفعيل وحدة')) . ':</strong> ';
    print dol_escape_htmltag($langs->trans('TakepoBatchModuleRequiredMsg', 'يجب تفعيل "إدارة الدفعات/الأرقام التسلسلية" من: الإعدادات → المنتجات → تفعيل إدارة الدفعات'));
    print '</div>';
}
print '<table id="table_near_expiry" class="takepos-workspace-table"></table></div>';
print '</section>';
print '</div>';
print '</body>';

llxFooter();
$db->close();
