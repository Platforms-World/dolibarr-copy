<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/custom/poscore/lib/poscore_bootstrap.php';
require_once DOL_DOCUMENT_ROOT . '/custom/poscore/class/pos_cashier.class.php';

$guard = poscore_bootstrap_page('manage_pos_settings', 'poscore->admin');
$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');

if ($action === 'delete' && GETPOST('token') === $_SESSION['newtoken']) {
    $guard->guardOrDeny(array('permission' => 'delete_cashier', 'dol_right' => 'poscore->admin'));
    $cashier = new PosCashier($db);
    if ($cashier->fetch($id) > 0) {
        $res = $cashier->delete($user);
        if ($res > 0) setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
        else setEventMessages($cashier->error, null, 'errors');
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$countRes = $db->query("SELECT COUNT(*) nb FROM " . MAIN_DB_PREFIX . "pos_cashier WHERE entity=" . ((int) $conf->entity));
$currentCount = 0;
if ($countRes && ($obj = $db->fetch_object($countRes))) $currentCount = (int) $obj->nb;
$canCreate = $guard->can(array('permission' => 'create_cashier', 'limit' => 'max_cashiers', 'current_count' => $currentCount, 'dol_right' => 'poscore->admin'));

llxHeader('', $langs->trans('Cashiers'));
print load_fiche_titre($langs->trans('Cashiers'), '', 'user');
$head = poscoreAdminPrepareHead();
print dol_get_fiche_head($head, 'cashiers', $langs->trans('Module105500Name'), -1, 'cash-register');

print '<div class="tabsAction">';
if ($canCreate) print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/poscore/admin/cashier.php?action=create">' . $langs->trans('NewCashier') . '</a>';
print '</div>';

$sql = "SELECT c.rowid, c.ref, c.label, c.user_id, c.terminal_id, c.status, u.login as user_login, t.label as terminal_label";
$sql .= " FROM " . MAIN_DB_PREFIX . "pos_cashier c";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid=c.user_id";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "pos_terminal t ON t.rowid=c.terminal_id";
$sql .= " WHERE c.entity=" . ((int) $conf->entity) . " ORDER BY c.ref";
$resql = $db->query($sql);

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>Ref</th><th>Label</th><th>User</th><th>Terminal</th><th>Status</th><th class="right">Actions</th></tr>';
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        print '<tr class="oddeven">';
        print '<td><a href="' . DOL_URL_ROOT . '/custom/poscore/admin/cashier.php?id=' . ((int) $obj->rowid) . '">' . dol_escape_htmltag($obj->ref) . '</a></td>';
        print '<td>' . dol_escape_htmltag($obj->label) . '</td>';
        print '<td>' . dol_escape_htmltag($obj->user_login ?: '-') . '</td>';
        print '<td>' . dol_escape_htmltag($obj->terminal_label ?: '-') . '</td>';
        print '<td>' . ((int) $obj->status === 1 ? $langs->trans('Enabled') : $langs->trans('Disabled')) . '</td>';
        print '<td class="right"><a class="button button-edit" href="' . DOL_URL_ROOT . '/custom/poscore/admin/cashier.php?id=' . ((int) $obj->rowid) . '">' . $langs->trans('Modify') . '</a> ';
        if ($guard->can(array('permission' => 'delete_cashier', 'dol_right' => 'poscore->admin'))) {
            print '<a class="button button-delete" href="' . $_SERVER['PHP_SELF'] . '?action=delete&id=' . ((int) $obj->rowid) . '&token=' . newToken() . '" onclick="return confirm(\'' . dol_escape_js($langs->trans('ConfirmDeleteObject')) . '\')">' . $langs->trans('Delete') . '</a>';
        }
        print '</td></tr>';
    }
}
print '</table>';
print dol_get_fiche_end();
llxFooter();
$db->close();
