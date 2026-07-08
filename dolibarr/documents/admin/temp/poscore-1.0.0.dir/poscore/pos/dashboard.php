<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/custom/poscore/lib/poscore_bootstrap.php';

$guard = poscore_bootstrap_page('view_pos_dashboard', 'poscore->read');

$entity = (int) $conf->entity;
$counts = array('terminals' => 0, 'cashiers' => 0, 'open_shifts' => 0, 'receipt_profiles' => 0);
$queries = array(
    'terminals' => "SELECT COUNT(*) nb FROM " . MAIN_DB_PREFIX . "pos_terminal WHERE entity=$entity",
    'cashiers' => "SELECT COUNT(*) nb FROM " . MAIN_DB_PREFIX . "pos_cashier WHERE entity=$entity",
    'open_shifts' => "SELECT COUNT(*) nb FROM " . MAIN_DB_PREFIX . "pos_shift WHERE entity=$entity AND status=0",
    'receipt_profiles' => "SELECT COUNT(*) nb FROM " . MAIN_DB_PREFIX . "pos_receipt_profile WHERE entity=$entity"
);
foreach ($queries as $k => $sql) {
    $r = $db->query($sql);
    if ($r && ($o = $db->fetch_object($r))) $counts[$k] = (int) $o->nb;
}

llxHeader('', $langs->trans('POSDashboard'));
print load_fiche_titre($langs->trans('POSDashboard'), '', 'cash-register');
print '<div class="fichecenter"><div class="fichethirdleft">';
print '<table class="border centpercent">';
print '<tr><td class="titlefield">Terminals</td><td>' . $counts['terminals'] . '</td></tr>';
print '<tr><td>Cashiers</td><td>' . $counts['cashiers'] . '</td></tr>';
print '<tr><td>Open shifts</td><td>' . $counts['open_shifts'] . '</td></tr>';
print '<tr><td>Receipt profiles</td><td>' . $counts['receipt_profiles'] . '</td></tr>';
print '</table>';
print '</div></div>';
print '<div class="tabsAction">';
if ($guard->can(array('permission' => 'manage_pos_settings', 'dol_right' => 'poscore->admin'))) {
    print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/poscore/admin/terminal_list.php">Manage terminals</a>';
    print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/poscore/admin/cashier_list.php">Manage cashiers</a>';
}
print '</div>';
llxFooter();
$db->close();
