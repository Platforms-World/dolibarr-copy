<?php
/**
 * Refund details page.
 */
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');

require '../main.inc.php';

require_once __DIR__ . '/lib/takepos_lang.php';
require_once __DIR__ . '/lib/takepos_help.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/class/TakeposAccess.class.php';
require_once __DIR__ . '/class/TakeposAudit.class.php';
require_once __DIR__ . '/class/TakeposRefundService.class.php';
require_once __DIR__ . '/class/TakeposUserAccess.class.php';

$langs->loadLangs(array('cashdesk', 'main', 'bills', 'takeposcustom@takepos'));
$terminal = isset($_SESSION['takeposterminal']) ? (string) $_SESSION['takeposterminal'] : '1';
$refundAccessDeniedMessage = $langs->trans('TakeposRefundDetailsAccessDenied');
$canAccessRefundDesk = (!empty($user->admin)
    || TakeposUserAccess::userHasAnyPermission($db, $user, array('takepos.refund.view', 'takepos.refund.partial', 'takepos.refund.full')));
if (!$canAccessRefundDesk) {
    accessforbidden($refundAccessDeniedMessage);
}
TakeposAccess::requireFrontendAccess(
    $db,
    $user,
    'takepos.refunds',
    'takepos.use',
    (int) $terminal,
    $refundAccessDeniedMessage,
    array('page' => 'refund_details.php')
);

$entity = !empty($user->entity) ? (int) $user->entity : 1;
$refundId = GETPOSTINT('id');
$refund = TakeposRefundService::getRefundById($db, $entity, $refundId);
if (!$refund) {
    accessforbidden($langs->trans('TakeposRefundNotFound'));
}
$lines = TakeposRefundService::getRefundLines($db, $entity, $refundId);

TakeposAudit::logEvent($db, $user, 'refund_report_opened', TakeposAudit::SEVERITY_INFO, array('view' => 'refund_details', 'refund_id' => $refundId), 'Refund details opened');

llxHeader('', $langs->trans('TakeposRefundDetailsTitle'));
print load_fiche_titre($langs->trans('TakeposRefundDetailsTitle') . ' - ' . dol_escape_htmltag($refund->refund_ref));

print '<div class="fichecenter"><table class="border centpercent">';
print '<tr><td class="titlefield">' . dol_escape_htmltag($langs->trans('TakeposRefundInvoiceRef')) . '</td><td>' . dol_escape_htmltag($refund->refund_ref) . '</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposLoyaltyType')) . '</td><td>' . dol_escape_htmltag($refund->refund_type) . '</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposRefundOriginalInvoice')) . '</td><td>' . dol_escape_htmltag($refund->original_invoice_ref) . ' (#' . ((int) $refund->fk_original_invoice) . ')</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposShiftStore')) . '</td><td>' . dol_escape_htmltag((string) $refund->store_code . ' ' . (string) $refund->store_label) . '</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposShiftTerminal')) . '</td><td>' . dol_escape_htmltag((string) $refund->terminal_code . ' ' . (string) $refund->terminal_label) . '</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposRefundAmount')) . '</td><td class="right">' . price($refund->total_amount) . '</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposRefundPaymentMethod')) . '</td><td>' . dol_escape_htmltag($refund->payment_method) . '</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposRefundReason')) . '</td><td>' . dol_escape_htmltag($refund->reason_code) . '</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposCommonStatus')) . '</td><td>' . dol_escape_htmltag($refund->status) . '</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposShiftCashierUserId')) . '</td><td>' . ((int) $refund->fk_cashier_user) . '</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposRefundApprovedBy')) . '</td><td>' . ((int) $refund->fk_approved_by) . '</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposLoyaltyDate')) . '</td><td>' . dol_escape_htmltag($refund->date_creation) . '</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposRefundNote')) . '</td><td>' . dol_escape_htmltag((string) $refund->note) . '</td></tr>';
print '</table></div>';

print '<br><div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>' . dol_escape_htmltag($langs->trans('TakeposRefundLineId')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposCommonLabel')) . '</th><th>' . dol_escape_htmltag($langs->trans('Product')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposRefundQtyRefunded')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposRefundUnitPrice')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposRefundLineTotal')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposRefundRestock')) . '</th></tr>';
foreach ($lines as $ln) {
    print '<tr class="oddeven">';
    print '<td>' . ((int) $ln->fk_original_line) . '</td>';
    print '<td>' . dol_escape_htmltag((string) $ln->original_line_label) . '</td>';
    print '<td>' . ((int) $ln->fk_product) . '</td>';
    print '<td class="right">' . price($ln->qty_refunded, 0, '', 1, -1, -1, '') . '</td>';
    print '<td class="right">' . price($ln->unit_price) . '</td>';
    print '<td class="right">' . price($ln->line_total) . '</td>';
    print '<td>' . ((int) $ln->restock_flag ? dol_escape_htmltag($langs->trans('TakeposCommonYes')) : dol_escape_htmltag($langs->trans('TakeposCommonNo')) ) . '</td>';
    print '</tr>';
}
print '</table></div>';

print '<br><a class="button" target="_blank" href="' . dol_escape_htmltag(DOL_URL_ROOT . '/takepos/refund_receipt.php?id=' . ((int) $refundId)) . '">' . dol_escape_htmltag($langs->trans('TakeposRefundPrintReceipt')) . '</a>';
print takeposHelpRender($langs, __FILE__);

llxFooter();
$db->close();
