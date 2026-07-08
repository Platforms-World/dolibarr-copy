<?php
require '../../main.inc.php';
require_once __DIR__ . '/../class/TakeposAccess.class.php';
require_once __DIR__ . '/../class/TakeposAudit.class.php';
$langs->loadLangs(array('admin', 'takepos', 'takeposcustom@takepos'));
restrictedArea($user, 'takepos', 0, '');
TakeposAudit::ensureTable($db);
if (empty($user->admin) && empty($user->rights->takepos->run)) accessforbidden($langs->trans('TakeposAuditDashboardAccessDenied'));
TakeposAccess::requireFrontendAccess(
    $db,
    $user,
    'takepos.dashboard.view',
    'takepos.use',
    isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null,
    $langs->trans('TakeposAuditDashboardAccessDenied'),
    array('page' => 'audit/dashboard.php')
);
$m = TakeposAudit::getDashboardMetrics($db);
llxHeader('', $langs->trans('TakeposAuditDashboardTitle'));
print load_fiche_titre($langs->trans('TakeposAuditDashboardTitle'));
print '<div class="tabsAction"><a class="butAction" href="' . DOL_URL_ROOT . '/takepos/audit/list.php">' . dol_escape_htmltag($langs->trans('TakeposAuditOpenLog')) . '</a></div>';
print '<div class="fichecenter"><div class="fichehalfleft">';
print '<table class="border centpercent">';
print '<tr><td class="titlefield">' . dol_escape_htmltag($langs->trans('TakeposAuditEventsToday')) . '</td><td class="right">' . ((int)$m['audit_today']) . '</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposAuditEventsLast7Days')) . '</td><td class="right">' . ((int)$m['audit_7d']) . '</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposAuditSalesToday')) . '</td><td class="right">' . ((int)$m['sales_today']) . '</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposAuditSalesAmountToday')) . '</td><td class="right">' . price($m['sales_amount_today']) . '</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposAuditCashiersActiveToday')) . '</td><td class="right">' . ((int)$m['cashiers_today']) . '</td></tr>';
print '</table>';
print '<br><table class="noborder centpercent"><tr class="liste_titre"><th colspan="3">' . dol_escape_htmltag($langs->trans('TakeposAuditTopCashiers7Days')) . '</th></tr><tr class="liste_titre"><th>' . dol_escape_htmltag($langs->trans('TakeposAuditCashier')) . '</th><th class="right">' . dol_escape_htmltag($langs->trans('TakeposAuditSales')) . '</th><th class="right">' . dol_escape_htmltag($langs->trans('TakeposCommonAmount')) . '</th></tr>';
foreach ($m['top_cashiers'] as $row) print '<tr class="oddeven"><td>' . dol_escape_htmltag($row->cashier) . '</td><td class="right">' . ((int)$row->sales_count) . '</td><td class="right">' . price($row->amount_ttc) . '</td></tr>';
print '</table></div><div class="fichehalfright">';
print '<table class="noborder centpercent"><tr class="liste_titre"><th colspan="3">' . dol_escape_htmltag($langs->trans('TakeposAuditTopProducts30Days')) . '</th></tr><tr class="liste_titre"><th>' . dol_escape_htmltag($langs->trans('TakeposAuditProduct')) . '</th><th class="right">' . dol_escape_htmltag($langs->trans('TakeposCommonQuantity')) . '</th><th class="right">' . dol_escape_htmltag($langs->trans('TakeposCommonAmount')) . '</th></tr>';
foreach ($m['top_products'] as $row) print '<tr class="oddeven"><td>' . dol_escape_htmltag($row->product_label) . '</td><td class="right">' . ((float)$row->qty_total) . '</td><td class="right">' . price($row->amount_ttc) . '</td></tr>';
print '</table><br><table class="noborder centpercent"><tr class="liste_titre"><th colspan="4">' . dol_escape_htmltag($langs->trans('TakeposAuditRecentSales')) . '</th></tr><tr class="liste_titre"><th>' . dol_escape_htmltag($langs->trans('TakeposExpenseReference')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposCommonDate')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposAuditCashier')) . '</th><th class="right">' . dol_escape_htmltag($langs->trans('TakeposCommonAmount')) . '</th></tr>';
foreach ($m['recent_sales'] as $row) print '<tr class="oddeven"><td>' . dol_escape_htmltag($row->ref) . '</td><td>' . dol_escape_htmltag($row->datef) . '</td><td>' . dol_escape_htmltag($row->cashier) . '</td><td class="right">' . price($row->total_ttc) . '</td></tr>';
print '</table></div></div><div class="clearboth"></div>';
llxFooter();
