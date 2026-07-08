<?php
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');

require '../main.inc.php';
require_once __DIR__ . '/class/TakeposAccess.class.php';
require_once __DIR__ . '/class/TakeposDashboardService.class.php';
require_once __DIR__ . '/lib/takepos_lang.php';
require_once __DIR__ . '/lib/takepos_help.php';

takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
$langs->loadLangs(array('main', 'cashdesk', 'products', 'suppliers', 'takeposcustom@takepos'));

TakeposAccess::requireFrontendAccess(
    $db,
    $user,
    'takepos.dashboard.pro',
    'takepos.dashboard.view',
    isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null,
    $langs->trans('TakeposDashboardAccessDenied'),
    array('page' => 'dashboard.php')
);

$service = new TakeposDashboardService($db, $langs, isset($conf->entity) ? (int) $conf->entity : 1);
$defaultFrom = GETPOST('date_from', 'alpha') ?: date('Y-m-01');
$defaultTo = GETPOST('date_to', 'alpha') ?: date('Y-m-d');
$data = $service->getDataset(array('date_from' => $defaultFrom, 'date_to' => $defaultTo));

$title = $langs->trans('TakeposDashboardTitle');
$arrayofcss = array('/takepos/css/dashboard_exec_v4.css?v=20260319');
$arrayofjs = array('/takepos/js/dashboard_exec_v4.js?v=20260319');
$head = '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';

top_htmlhead($head, $title, 0, 0, $arrayofjs, $arrayofcss);
?>
<body class="tpdb-body tpdb-exec-v4">
<div class="tpdb-shell">
    <!-- EXECUTIVE DASHBOARD V4 CACHE-BUSTER -->
    <div id="takepos-dashboard-config"
         data-endpoint="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/ajax/dashboard_data.php'); ?>"
         data-export-pdf="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/reports/dashboard_pdf.php'); ?>"></div>

    <header class="tpdb-topbar">
        <div>
            <div class="tpdb-eyebrow"><?php echo dol_escape_htmltag($langs->trans('TakeposShortcutDashboardPro')); ?></div>
            <h1><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardTitle')); ?></h1>
            <p><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardSubtitle')); ?></p>
        </div>
        <div class="tpdb-toolbar">
            <div class="tpdb-field">
                <label for="tp_date_from"><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardDateFrom')); ?></label>
                <input type="date" id="tp_date_from" value="<?php echo dol_escape_htmltag($data['meta']['date_from']); ?>">
            </div>
            <div class="tpdb-field">
                <label for="tp_date_to"><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardDateTo')); ?></label>
                <input type="date" id="tp_date_to" value="<?php echo dol_escape_htmltag($data['meta']['date_to']); ?>">
            </div>
            <button type="button" id="tp_refresh_dashboard" class="tpdb-btn tpdb-btn-primary"><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardRefresh')); ?></button>
            <button type="button" id="tp_export_dashboard" class="tpdb-btn"><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardExportPdf')); ?></button>
            <a class="tpdb-btn" href="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/index.php'); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardBackToPos')); ?></a>
        </div>
    </header>

    <div id="tp_dashboard_feedback" class="tpdb-feedback hidden"></div>

    <main class="tpdb-grid">
        <section class="tpdb-card tpdb-hero">
            <div class="tpdb-hero-copy">
                <div class="tpdb-card-kicker"><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardInsights')); ?></div>
                <h2 id="tp_hero_title"><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardTitle')); ?></h2>
                <p id="tp_hero_subtitle"><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardSubtitle')); ?></p>
                <div class="tpdb-hero-stats" id="tp_hero_stats"></div>
            </div>
            <div class="tpdb-hero-art">
                <div class="orb orb-a"></div>
                <div class="orb orb-b"></div>
                <div class="orb orb-c"></div>
            </div>
        </section>

        <section class="tpdb-card tpdb-linecard">
            <div class="tpdb-card-head">
                <div>
                    <div class="tpdb-card-kicker"><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardKpiAvgInvoice')); ?></div>
                    <h3 id="tp_avg_sales_value">0</h3>
                    <p id="tp_avg_sales_hint">-</p>
                </div>
            </div>
            <div id="tp_sales_line_chart" class="tpdb-chart tpdb-chart-line"></div>
        </section>

        <section class="tpdb-card tpdb-overview">
            <div class="tpdb-card-head">
                <div>
                    <div class="tpdb-card-kicker"><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardKpiSales')); ?></div>
                    <h3 id="tp_sales_overview_value">0</h3>
                </div>
                <div class="tpdb-delta" id="tp_sales_overview_delta">0%</div>
            </div>
            <div class="tpdb-split-stats">
                <div>
                    <span><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardKpiInvoices')); ?></span>
                    <strong id="tp_invoice_count">0</strong>
                </div>
                <div>
                    <span><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardKpiCustomers')); ?></span>
                    <strong id="tp_customer_count">0</strong>
                </div>
            </div>
            <div class="tpdb-progress"><span id="tp_progress_a"></span><span id="tp_progress_b"></span></div>
        </section>

        <section class="tpdb-card tpdb-bars">
            <div class="tpdb-card-head">
                <div>
                    <h3><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardSalesTrend')); ?></h3>
                    <p><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardSalesTrendHint')); ?></p>
                </div>
            </div>
            <div class="tpdb-bars-wrap">
                <div class="tpdb-bars-main">
                    <div class="tpdb-big-number" id="tp_bar_total">0</div>
                    <div class="tpdb-pill" id="tp_bar_delta">0%</div>
                    <div class="tpdb-muted" id="tp_bar_caption">-</div>
                </div>
                <div id="tp_sales_bars_chart" class="tpdb-chart tpdb-chart-bars"></div>
            </div>
            <div class="tpdb-mini-metrics">
                <div><span><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardKpiSales')); ?></span><strong id="tp_metric_sales">0</strong><em class="tpdb-accent-1"></em></div>
                <div><span><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardKpiAvgInvoice')); ?></span><strong id="tp_metric_avg">0</strong><em class="tpdb-accent-2"></em></div>
                <div><span><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardKpiPendingCheques')); ?></span><strong id="tp_metric_pending">0</strong><em class="tpdb-accent-3"></em></div>
            </div>
        </section>

        <section class="tpdb-card tpdb-gauge">
            <div class="tpdb-card-head">
                <div>
                    <h3><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardChequePanel')); ?></h3>
                    <p><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardUpcomingCheques')); ?></p>
                </div>
            </div>
            <div class="tpdb-gauge-wrap">
                <div class="tpdb-ticket-metrics">
                    <div><span><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardChequeDueToday')); ?></span><strong id="tp_due_today">0</strong></div>
                    <div><span><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardChequeDue7')); ?></span><strong id="tp_due_7">0</strong></div>
                    <div><span><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardChequeOverdue')); ?></span><strong id="tp_due_overdue">0</strong></div>
                    <div><span><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardChequeBounced')); ?></span><strong id="tp_due_bounced">0</strong></div>
                </div>
                <div class="tpdb-gauge-chart-wrap">
                    <div id="tp_gauge_chart" class="tpdb-chart tpdb-chart-gauge"></div>
                    <div class="tpdb-gauge-label"><span><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardKpiPendingCheques')); ?></span><strong id="tp_pending_ratio">0%</strong></div>
                </div>
            </div>
        </section>

        <section class="tpdb-card tpdb-list-card">
            <div class="tpdb-card-head">
                <div>
                    <h3><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardSuppliers')); ?></h3>
                    <p><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardChequePanel')); ?></p>
                </div>
            </div>
            <div id="tp_supplier_list" class="tpdb-list"></div>
        </section>

        <section class="tpdb-card tpdb-earnings">
            <div class="tpdb-card-head">
                <div>
                    <h3><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardKpiCustomers')); ?></h3>
                    <p><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardTopProducts')); ?></p>
                </div>
            </div>
            <div class="tpdb-earnings-score"><span id="tp_score_value">0%</span><em id="tp_score_delta">0%</em></div>
            <div id="tp_compare_chart" class="tpdb-chart tpdb-chart-compare"></div>
            <div class="tpdb-summary-rows">
                <div><span><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardKpiSales')); ?></span><strong id="tp_sum_sales">0</strong><small id="tp_sum_sales_delta">0</small></div>
                <div><span><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardKpiInvoices')); ?></span><strong id="tp_sum_invoices">0</strong><small id="tp_sum_invoices_delta">0</small></div>
            </div>
        </section>

        <section class="tpdb-card tpdb-status-card">
            <div class="tpdb-card-head">
                <div>
                    <h3><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardInsights')); ?></h3>
                    <p><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardSubtitle')); ?></p>
                </div>
            </div>
            <div id="tp_insights_list" class="tpdb-status-list"></div>
        </section>

        <!-- FIX (stock-branch-v5): Low-stock alert widget with Create Purchase button -->
        <section class="tpdb-card tpdb-list-card" style="grid-column: 1 / -1">
            <div class="tpdb-card-head">
                <div>
                    <h3>&#128230; <?php echo dol_escape_htmltag($langs->trans('TakeposDashboardLowStockTitle')); ?></h3>
                    <p><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardLowStockSubtitle')); ?></p>
                </div>
                <a href="<?php echo DOL_URL_ROOT; ?>/takepos/stock_overview.php?low_only=1"
                   style="font-size:12px;color:#1d4ed8;text-decoration:none">
                    <?php echo dol_escape_htmltag($langs->trans('TakeposStockLowOnly')); ?> &rarr;
                </a>
            </div>
            <div id="tp_low_stock_alerts" class="tpdb-list">
                <p style="color:#9ca3af;font-size:12px"><?php echo dol_escape_htmltag($langs->trans('TakeposDashboardLoading')); ?></p>
            </div>
        </section>
    </main>
</div>
<script>
window.takeposDashboardInitial = <?php echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
window.takeposDashboardLabels = {
    NoData: <?php echo json_encode($langs->trans('TakeposDashboardNoData')); ?>,
    LoadFailed: <?php echo json_encode($langs->trans('TakeposDashboardLoadFailed')); ?>,
    ExportDenied: <?php echo json_encode($langs->trans('TakeposDashboardExportDenied')); ?>,
    SalesLabel: <?php echo json_encode($langs->trans('TakeposDashboardKpiSales')); ?>,
    InvoiceLabel: <?php echo json_encode($langs->trans('TakeposDashboardKpiInvoices')); ?>,
    CustomerLabel: <?php echo json_encode($langs->trans('TakeposDashboardKpiCustomers')); ?>,
    PendingChequeLabel: <?php echo json_encode($langs->trans('TakeposDashboardKpiPendingCheques')); ?>,
    OverdueChequeLabel: <?php echo json_encode($langs->trans('TakeposDashboardKpiOverdueCheques')); ?>,
    LowStockLabel: <?php echo json_encode($langs->trans('TakeposDashboardKpiLowStock')); ?>,
    AvgInvoiceLabel: <?php echo json_encode($langs->trans('TakeposDashboardKpiAvgInvoice')); ?>,
    // FIX (stock-branch-v5): labels for low-stock alert widget + purchase prefill link
    LowStockNone: <?php echo json_encode($langs->trans('TakeposDashboardLowStockNone')); ?>,
    CreatePurchase: <?php echo json_encode($langs->trans('TakeposDashboardCreatePurchase')); ?>,
    RefLabel: <?php echo json_encode($langs->trans('Ref')); ?>,
    ProductLabel: <?php echo json_encode($langs->trans('Product')); ?>,
    PurchaseUrl: <?php echo json_encode(DOL_URL_ROOT . '/takepos/purchases.php?'); ?>
};
window.takeposDashboardCanExport = true;
</script>
<?php echo takeposHelpRender($langs, __FILE__); ?>
</body>
</html>
