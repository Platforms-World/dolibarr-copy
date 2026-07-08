<?php
/**
 * Shift detail page.
 */
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}

require '../main.inc.php';

require_once __DIR__ . '/lib/takepos_lang.php';
require_once __DIR__ . '/lib/takepos_help.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/class/TakeposAccess.class.php';
require_once __DIR__ . '/class/TakeposAudit.class.php';
require_once __DIR__ . '/class/TakeposShiftService.class.php';
require_once __DIR__ . '/class/TakeposCashService.class.php';

$langs->loadLangs(array('cashdesk', 'main', 'takeposcustom@takepos'));

$terminal = isset($_SESSION['takeposterminal']) ? (string) $_SESSION['takeposterminal'] : '1';
TakeposAccess::requireFrontendAccess(
    $db,
    $user,
    'takepos.shift_management',
    'takepos.shift.review',
    (int) $terminal,
    $langs->trans('TakeposShiftDetailsAccessDenied'),
    array('page' => 'shift_details.php')
);

$shiftId = GETPOSTINT('id');
$entity = !empty($user->entity) ? (int) $user->entity : 1;
$shift = TakeposShiftService::getShiftById($db, $entity, $shiftId);
if (!$shift) {
    accessforbidden($langs->trans('TakeposShiftNotFound'));
}

$summary = TakeposShiftService::buildShiftSummary($db, $entity, $shift);
$ledger = TakeposCashService::listMovementsByShift($db, $entity, $shiftId, 500);

TakeposAudit::logEvent($db, $user, 'shift_view_opened', TakeposAudit::SEVERITY_INFO, array('view' => 'details_page', 'shift_id' => $shiftId), 'Shift detail page opened', 'shift', $shiftId);

llxHeader('', $langs->trans('TakeposShiftDetailsTitle'));
print load_fiche_titre($langs->trans('TakeposShiftDetailsTitle') . ' - ' . dol_escape_htmltag($shift->shift_ref));

print '<div class="fichecenter"><table class="border centpercent">';
print '<tr><td class="titlefield">' . dol_escape_htmltag($langs->trans('TakeposShiftRef')) . '</td><td>' . dol_escape_htmltag($shift->shift_ref) . '</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposCommonStatus')) . '</td><td>' . dol_escape_htmltag($shift->status) . '</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposShiftTerminal')) . '</td><td>' . dol_escape_htmltag($shift->terminal_code . ' - ' . $shift->terminal_label) . '</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposShiftStore')) . '</td><td>' . dol_escape_htmltag($shift->store_label) . '</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposShiftCashierUserId')) . '</td><td>' . ((int) $shift->fk_cashier_user) . '</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposShiftOpened')) . '</td><td>' . dol_escape_htmltag($shift->date_open) . '</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposShiftClosed')) . '</td><td>' . dol_escape_htmltag((string) $shift->date_close) . '</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposShiftOpeningFloat')) . '</td><td class="right">' . price($shift->opening_float) . '</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposShiftTotalCashSales')) . '</td><td class="right">' . price($summary['total_cash_sales']) . '</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposShiftTotalCardSales')) . '</td><td class="right">' . price($summary['total_card_sales']) . '</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposShiftTotalOtherSales')) . '</td><td class="right">' . price($summary['total_other_sales']) . '</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposShiftTotalPaidIn')) . '</td><td class="right">' . price($summary['total_paid_in']) . '</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposShiftTotalPaidOut')) . '</td><td class="right">' . price($summary['total_paid_out']) . '</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposShiftTotalSafeDrop')) . '</td><td class="right">' . price($summary['total_safe_drop']) . '</td></tr>';
print '<tr><td><strong>' . dol_escape_htmltag($langs->trans('TakeposShiftExpectedCash')) . '</strong></td><td class="right"><strong>' . price($summary['expected_cash']) . '</strong></td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposShiftCountedCash')) . '</td><td class="right">' . price($shift->counted_cash) . '</td></tr>';
print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposShiftDifference')) . '</td><td class="right">' . price($shift->cash_difference) . '</td></tr>';
print '</table></div>';

print '<br><div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>' . dol_escape_htmltag($langs->trans('TakeposLoyaltyId')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposLoyaltyDate')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposLoyaltyType')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposRefundAmount')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposRefundReason')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposRefundNote')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposShiftCreatedBy')) . '</th></tr>';
foreach ($ledger as $row) {
    print '<tr class="oddeven">';
    print '<td>' . ((int) $row->rowid) . '</td>';
    print '<td>' . dol_escape_htmltag($row->date_creation) . '</td>';
    print '<td>' . dol_escape_htmltag($row->movement_type) . '</td>';
    print '<td class="right">' . price($row->amount) . '</td>';
    print '<td>' . dol_escape_htmltag($row->reason_text) . '</td>';
    print '<td>' . dol_escape_htmltag($row->note) . '</td>';
    print '<td>' . ((int) $row->fk_created_by) . '</td>';
    print '</tr>';
}
print '</table></div>';

print takeposHelpRender($langs, __FILE__);

llxFooter();
$db->close();
