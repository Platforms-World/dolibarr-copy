<?php
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposAccess.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposDashboardService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/lib/takepos_lang.php';

takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
$langs->loadLangs(array('main', 'takeposcustom@takepos'));

TakeposAccess::requireFrontendAccess(
    $db,
    $user,
    'takepos.dashboard.pro',
    'takepos.dashboard.export_pdf',
    isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null,
    $langs->trans('TakeposDashboardExportDenied'),
    array('page' => 'reports/dashboard_pdf.php')
);

$service = new TakeposDashboardService($db, $langs, isset($conf->entity) ? (int) $conf->entity : 1);
$data = $service->getDataset(array(
    'date_from' => GETPOST('date_from', 'alpha'),
    'date_to' => GETPOST('date_to', 'alpha'),
));

$tcpdfPathCandidates = array(
    DOL_DOCUMENT_ROOT . '/includes/tecnickcom/tcpdf/tcpdf.php',
    DOL_DOCUMENT_ROOT . '/core/lib/tcpdf/tcpdf.php',
    DOL_DOCUMENT_ROOT . '/includes/tcpdf/tcpdf.php',
);
$tcpdfPath = '';
foreach ($tcpdfPathCandidates as $candidate) {
    if (file_exists($candidate)) { $tcpdfPath = $candidate; break; }
}
if (!$tcpdfPath) {
    header('Content-Type: text/html; charset=utf-8');
    print '<h2>' . dol_escape_htmltag($langs->trans('TakeposDashboardPdfLibraryMissing')) . '</h2>';
    exit;
}
require_once $tcpdfPath;

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('OpenAI');
$pdf->SetAuthor('OpenAI');
$pdf->SetTitle($langs->trans('TakeposDashboardPdfTitle'));
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 10);
$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, $langs->trans('TakeposDashboardPdfTitle'), 0, 1);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, $langs->trans('TakeposDashboardDateFrom') . ': ' . $data['meta']['date_from'] . '   ' . $langs->trans('TakeposDashboardDateTo') . ': ' . $data['meta']['date_to'], 0, 1);
$pdf->Ln(2);

$kpis = $data['kpis'];
$cheque = $data['cheque_summary'];

$html = '';
$html .= '<table border="0" cellpadding="6"><tr>'
    . '<td width="33%" style="background-color:#f5f4ff;border:1px solid #e8e6ff;"><b>' . dol_escape_htmltag($langs->trans('TakeposDashboardKpiSales')) . '</b><br>' . dol_escape_htmltag(price($kpis['sales_total'])) . '</td>'
    . '<td width="33%" style="background-color:#eefbf4;border:1px solid #d8f4e5;"><b>' . dol_escape_htmltag($langs->trans('TakeposDashboardKpiInvoices')) . '</b><br>' . dol_escape_htmltag((string)$kpis['invoice_count']) . '</td>'
    . '<td width="34%" style="background-color:#eef8ff;border:1px solid #d9efff;"><b>' . dol_escape_htmltag($langs->trans('TakeposDashboardKpiAvgInvoice')) . '</b><br>' . dol_escape_htmltag(price($kpis['avg_invoice'])) . '</td>'
    . '</tr></table>';

$html .= '<br><table border="0" cellpadding="6"><tr>'
    . '<td width="50%" style="background-color:#fff7e9;border:1px solid #ffe5af;"><b>' . dol_escape_htmltag($langs->trans('TakeposDashboardChequePanel')) . '</b><br>'
    . dol_escape_htmltag($langs->trans('TakeposDashboardChequeDueToday')) . ': ' . (int)$cheque['due_today'] . '<br>'
    . dol_escape_htmltag($langs->trans('TakeposDashboardChequeDue7')) . ': ' . (int)$cheque['due_7_days'] . '<br>'
    . dol_escape_htmltag($langs->trans('TakeposDashboardChequeOverdue')) . ': ' . (int)$cheque['overdue'] . '<br>'
    . dol_escape_htmltag($langs->trans('TakeposDashboardChequeBounced')) . ': ' . (int)$cheque['bounced'] . '<br>'
    . dol_escape_htmltag($langs->trans('TakeposDashboardChequePendingAmount')) . ': ' . dol_escape_htmltag(price($cheque['pending_amount'])) . '</td>'
    . '<td width="50%" style="background-color:#fafbff;border:1px solid #ebeef8;"><b>' . dol_escape_htmltag($langs->trans('TakeposDashboardInsights')) . '</b><br><ul>';
foreach ($data['decision_insights'] as $insight) {
    $html .= '<li><b>' . dol_escape_htmltag($insight['title']) . ':</b> ' . dol_escape_htmltag($insight['text']) . '</li>';
}
$html .= '</ul></td></tr></table>';

$html .= '<br><h3>' . dol_escape_htmltag($langs->trans('TakeposDashboardTopProducts')) . '</h3>';
$html .= '<table border="1" cellpadding="4"><tr style="font-weight:bold;background-color:#f6f7fb;">'
    . '<td>' . dol_escape_htmltag($langs->trans('Ref')) . '</td>'
    . '<td>' . dol_escape_htmltag($langs->trans('Label')) . '</td>'
    . '<td>' . dol_escape_htmltag($langs->trans('Qty')) . '</td>'
    . '<td>' . dol_escape_htmltag($langs->trans('AmountHT')) . '</td>'
    . '</tr>';
foreach ($data['top_products'] as $row) {
    $html .= '<tr><td>' . dol_escape_htmltag($row['ref']) . '</td><td>' . dol_escape_htmltag($row['label']) . '</td><td>' . dol_escape_htmltag((string)$row['qty']) . '</td><td>' . dol_escape_htmltag(price($row['amount'])) . '</td></tr>';
}
$html .= '</table>';

$html .= '<br><h3>' . dol_escape_htmltag($langs->trans('TakeposDashboardSuppliers')) . '</h3>';
$html .= '<table border="1" cellpadding="4"><tr style="font-weight:bold;background-color:#f6f7fb;">'
    . '<td>' . dol_escape_htmltag($langs->trans('Supplier')) . '</td>'
    . '<td>' . dol_escape_htmltag($langs->trans('TakeposDashboardChequeCount')) . '</td>'
    . '<td>' . dol_escape_htmltag($langs->trans('AmountTTC')) . '</td>'
    . '</tr>';
foreach ($data['supplier_summary'] as $row) {
    $html .= '<tr><td>' . dol_escape_htmltag($row['supplier']) . '</td><td>' . dol_escape_htmltag((string)$row['cheque_count']) . '</td><td>' . dol_escape_htmltag(price($row['amount'])) . '</td></tr>';
}
$html .= '</table>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('takepos_dashboard_executive_pack.pdf', 'I');
