<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/custom/poscore/lib/poscore_bootstrap.php';

$guard = poscore_bootstrap_page('view_shift_summary', 'poscore->read', 'shift_management');

llxHeader('', $langs->trans('Shifts'));
print load_fiche_titre($langs->trans('Shifts'), '', 'clock');
$head = poscoreAdminPrepareHead();
print dol_get_fiche_head($head, 'shifts', $langs->trans('Module105500Name'), -1, 'cash-register');

$sql = "SELECT s.rowid, s.shift_ref, s.terminal_id, s.cashier_id, s.user_id, s.opened_at, s.closed_at, s.status, s.opening_amount, s.closing_amount, s.variance_amount";
$sql .= " FROM " . MAIN_DB_PREFIX . "pos_shift s WHERE s.entity=" . ((int) $conf->entity) . " ORDER BY s.opened_at DESC";
$resql = $db->query($sql);

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>Shift ref</th><th>Terminal ID</th><th>Cashier ID</th><th>User ID</th><th>Opened</th><th>Closed</th><th>Status</th><th>Opening</th><th>Closing</th><th>Variance</th></tr>';
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        print '<tr class="oddeven">';
        print '<td>' . dol_escape_htmltag($obj->shift_ref) . '</td>';
        print '<td>' . ((int) $obj->terminal_id) . '</td>';
        print '<td>' . ((int) $obj->cashier_id) . '</td>';
        print '<td>' . ((int) $obj->user_id) . '</td>';
        print '<td>' . dol_print_date($db->jdate($obj->opened_at), 'dayhour') . '</td>';
        print '<td>' . (!empty($obj->closed_at) ? dol_print_date($db->jdate($obj->closed_at), 'dayhour') : '-') . '</td>';
        print '<td>' . ((int) $obj->status === 0 ? 'Open' : 'Closed') . '</td>';
        print '<td>' . price($obj->opening_amount) . '</td>';
        print '<td>' . price($obj->closing_amount) . '</td>';
        print '<td>' . price($obj->variance_amount) . '</td>';
        print '</tr>';
    }
}
print '</table>';
print dol_get_fiche_end();
llxFooter();
$db->close();
