<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/custom/poscore/lib/poscore_bootstrap.php';
require_once DOL_DOCUMENT_ROOT . '/custom/poscore/class/service/PosAccessGuard.php';
require_once DOL_DOCUMENT_ROOT . '/custom/poscore/core/lib/poscore.lib.php';
require_once DOL_DOCUMENT_ROOT . '/custom/poscore/class/pos_terminal.class.php';

$guard = poscore_bootstrap_page('manage_pos_settings', 'poscore->admin');
$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');

if (in_array($action, array('delete', 'activate', 'deactivate'), true) && GETPOST('token') === $_SESSION['newtoken']) {
    $terminal = new PosTerminal($db);
    if ($terminal->fetch($id) > 0) {
        if ($action === 'delete') {
            $guard->guardOrDeny(array('permission' => 'delete_terminal', 'dol_right' => 'poscore->admin'));
            $res = $terminal->delete($user);
        } elseif ($action === 'activate') {
            $countRes = $db->query("SELECT COUNT(*) nb FROM " . MAIN_DB_PREFIX . "pos_terminal WHERE entity=" . ((int) $conf->entity) . " AND status=1");
            $activeCount = 0;
            if ($countRes && ($obj = $db->fetch_object($countRes))) $activeCount = (int) $obj->nb;
            $guard->guardOrDeny(array('permission' => 'activate_terminal', 'feature' => 'terminal_status_control', 'limit' => 'max_active_terminals', 'current_count' => $activeCount, 'dol_right' => 'poscore->admin'));
            $terminal->status = 1;
            $res = $terminal->update($user);
        } else {
            $guard->guardOrDeny(array('permission' => 'deactivate_terminal', 'feature' => 'terminal_status_control', 'dol_right' => 'poscore->admin'));
            $terminal->status = 0;
            $res = $terminal->update($user);
        }
        if ($res > 0) setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
        else setEventMessages($terminal->error, null, 'errors');
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$countRes = $db->query("SELECT COUNT(*) nb FROM " . MAIN_DB_PREFIX . "pos_terminal WHERE entity=" . ((int) $conf->entity));
$currentCount = 0;
if ($countRes && ($obj = $db->fetch_object($countRes))) $currentCount = (int) $obj->nb;
$canCreate = $guard->can(array('permission' => 'create_terminal', 'limit' => 'max_terminals', 'current_count' => $currentCount, 'dol_right' => 'poscore->admin'));

llxHeader('', $langs->trans('Terminals'));
print load_fiche_titre($langs->trans('Terminals'), '', 'cash-register');
$head = poscoreAdminPrepareHead();
print dol_get_fiche_head($head, 'terminals', $langs->trans('Module105500Name'), -1, 'cash-register');

print '<div class="tabsAction">';
if ($canCreate) {
    print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/poscore/admin/terminal.php?action=create">' . $langs->trans('NewTerminal') . '</a>';
}
print '</div>';

$sql = "SELECT t.rowid, t.ref, t.label, t.warehouse_id, t.status, t.is_default, rp.label as receipt_profile_label";
$sql .= " FROM " . MAIN_DB_PREFIX . "pos_terminal t";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "pos_receipt_profile rp ON rp.rowid=t.receipt_profile_id";
$sql .= " WHERE t.entity=" . ((int) $conf->entity);
$sql .= " ORDER BY t.ref";
$resql = $db->query($sql);

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>Ref</th><th>Label</th><th>Warehouse</th><th>Receipt profile</th><th>Status</th><th class="right">Actions</th>';
print '</tr>';
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        print '<tr class="oddeven">';
        print '<td><a href="' . DOL_URL_ROOT . '/custom/poscore/admin/terminal.php?id=' . ((int) $obj->rowid) . '">' . dol_escape_htmltag($obj->ref) . '</a></td>';
        print '<td>' . dol_escape_htmltag($obj->label) . '</td>';
        print '<td>' . (!empty($obj->warehouse_id) ? (int) $obj->warehouse_id : '-') . '</td>';
        print '<td>' . dol_escape_htmltag($obj->receipt_profile_label ?: '-') . '</td>';
        print '<td>' . ((int) $obj->status === 1 ? $langs->trans('Enabled') : $langs->trans('Disabled')) . '</td>';
        print '<td class="right">';
        print '<a class="button button-edit" href="' . DOL_URL_ROOT . '/custom/poscore/admin/terminal.php?id=' . ((int) $obj->rowid) . '">' . $langs->trans('Modify') . '</a> ';
        if ((int) $obj->status === 1 && $guard->can(array('permission' => 'deactivate_terminal', 'feature' => 'terminal_status_control', 'dol_right' => 'poscore->admin'))) {
            print '<a class="button button-edit" href="' . $_SERVER['PHP_SELF'] . '?action=deactivate&id=' . ((int) $obj->rowid) . '&token=' . newToken() . '">' . $langs->trans('Disable') . '</a> ';
        }
        if ((int) $obj->status === 0 && $guard->can(array('permission' => 'activate_terminal', 'feature' => 'terminal_status_control', 'dol_right' => 'poscore->admin'))) {
            print '<a class="button button-edit" href="' . $_SERVER['PHP_SELF'] . '?action=activate&id=' . ((int) $obj->rowid) . '&token=' . newToken() . '">' . $langs->trans('Enable') . '</a> ';
        }
        if ($guard->can(array('permission' => 'delete_terminal', 'dol_right' => 'poscore->admin'))) {
            print '<a class="button button-delete" href="' . $_SERVER['PHP_SELF'] . '?action=delete&id=' . ((int) $obj->rowid) . '&token=' . newToken() . '" onclick="return confirm(\'' . dol_escape_js($langs->trans('ConfirmDeleteObject')) . '\')">' . $langs->trans('Delete') . '</a>';
        }
        print '</td>';
        print '</tr>';
    }
}
print '</table>';

print dol_get_fiche_end();
llxFooter();
$db->close();
