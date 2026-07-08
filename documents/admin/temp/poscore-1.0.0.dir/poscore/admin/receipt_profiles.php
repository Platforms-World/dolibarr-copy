<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/custom/poscore/lib/poscore_bootstrap.php';
require_once DOL_DOCUMENT_ROOT . '/custom/poscore/class/pos_receipt_profile.class.php';

$guard = poscore_bootstrap_page('manage_receipt_settings', 'poscore->admin', 'advanced_receipt_settings');
$action = GETPOST('action', 'aZ09');

if ($action === 'create' && GETPOST('token') === $_SESSION['newtoken']) {
    $countRes = $db->query("SELECT COUNT(*) nb FROM " . MAIN_DB_PREFIX . "pos_receipt_profile WHERE entity=" . ((int) $conf->entity));
    $currentCount = 0;
    if ($countRes && ($obj = $db->fetch_object($countRes))) $currentCount = (int) $obj->nb;
    $guard->guardOrDeny(array('permission' => 'manage_receipt_settings', 'feature' => 'advanced_receipt_settings', 'limit' => 'max_receipt_templates', 'current_count' => $currentCount, 'dol_right' => 'poscore->admin'));

    $profile = new PosReceiptProfile($db);
    $profile->code = trim(GETPOST('code', 'alpha'));
    $profile->label = trim(GETPOST('label', 'alphanohtml'));
    $profile->is_default = GETPOSTINT('is_default') ? 1 : 0;
    $profile->status = GETPOSTINT('status') ? 1 : 0;
    $profile->settings_json = GETPOST('settings_json', 'restricthtml');
    $res = $profile->create($user);
    if ($res > 0) setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
    else setEventMessages($profile->error, null, 'errors');
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

llxHeader('', $langs->trans('ReceiptProfiles'));
print load_fiche_titre($langs->trans('ReceiptProfiles'), '', 'bill');
$head = poscoreAdminPrepareHead();
print dol_get_fiche_head($head, 'receipt_profiles', $langs->trans('Module105500Name'), -1, 'cash-register');

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="create">';
print '<table class="border centpercent">';
print '<tr><td class="titlefield">Code</td><td><input type="text" name="code"></td></tr>';
print '<tr><td>Label</td><td><input class="minwidth400" type="text" name="label"></td></tr>';
print '<tr><td>Default</td><td><input type="checkbox" name="is_default" value="1"></td></tr>';
print '<tr><td>Status</td><td><input type="checkbox" name="status" value="1" checked></td></tr>';
print '<tr><td>Settings JSON</td><td><textarea name="settings_json" rows="6" class="quatrevingtpercent">{"header":"Thank you","footer":"Visit again"}</textarea></td></tr>';
print '</table>';
print '<div class="tabsAction"><input type="submit" class="button button-save" value="' . $langs->trans('Create') . '"></div>';
print '</form>';

$sql = "SELECT rowid, code, label, is_default, status, datec FROM " . MAIN_DB_PREFIX . "pos_receipt_profile WHERE entity=" . ((int) $conf->entity) . " ORDER BY code";
$resql = $db->query($sql);
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>Code</th><th>Label</th><th>Default</th><th>Status</th><th>Date</th></tr>';
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        print '<tr class="oddeven">';
        print '<td>' . dol_escape_htmltag($obj->code) . '</td>';
        print '<td>' . dol_escape_htmltag($obj->label) . '</td>';
        print '<td>' . ((int) $obj->is_default === 1 ? 'Yes' : 'No') . '</td>';
        print '<td>' . ((int) $obj->status === 1 ? 'Active' : 'Inactive') . '</td>';
        print '<td>' . dol_print_date($db->jdate($obj->datec), 'dayhour') . '</td>';
        print '</tr>';
    }
}
print '</table>';
print dol_get_fiche_end();
llxFooter();
$db->close();
