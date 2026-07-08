<?php
/**
 * User-store assignment admin page.
 */
require '../../main.inc.php';

require_once __DIR__ . '/../lib/takepos_lang.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/../class/TakeposAccess.class.php';
require_once __DIR__ . '/../class/TakeposStoreService.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';

$langs->loadLangs(array('admin', 'users', 'main', 'cashdesk', 'takeposcustom@takepos'));

restrictedArea($user, 'takepos', 0, '');
TakeposAccess::requireAdminAccess(
    $db,
    $user,
    'takepos.store_governance',
    'takepos.terminal.assign',
    isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null,
    $langs->trans('TakeposAdminUserStoreAccessDenied')
);

$entity = !empty($user->entity) ? (int) $user->entity : 1;
$action = GETPOST('action', 'aZ09');
$targetUserId = GETPOSTINT('target_user_id');
$message = '';
$messageType = 'mesgs';

try {
    if ($action !== '' && GETPOST('token', 'alpha') !== $_SESSION['newtoken']) {
        throw new Exception($langs->trans('TakeposCommonInvalidSecurityToken'));
    }

    if ($action === 'save_assignments') {
        if ($targetUserId <= 0) {
            throw new Exception($langs->trans('TakeposAdminUserSelectPrompt'));
        }

        $storeIds = GETPOST('store_ids', 'array');
        if (!is_array($storeIds)) {
            $storeIds = array();
        }

        TakeposStoreService::setUserStores($db, $user, $entity, $targetUserId, $storeIds, 'cashier');
        $message = $langs->trans('TakeposAdminUserStoreAssignmentsUpdated');
    }
} catch (Throwable $e) {
    $message = $e->getMessage();
    $messageType = 'errors';
}

$stores = TakeposStoreService::listStores($db, $entity, true);
$users = array();
$sqlUsers = "SELECT rowid, login, firstname, lastname, statut, admin FROM " . MAIN_DB_PREFIX . "user WHERE entity IN (0, " . $entity . ") AND login <> '' ORDER BY login ASC";
$resUsers = $db->query($sqlUsers);
if ($resUsers) {
    while ($u = $db->fetch_object($resUsers)) {
        $users[] = $u;
    }
}

$currentAssignments = array();
if ($targetUserId > 0) {
    $currentAssignments = TakeposStoreService::getUserStoreIds($db, $entity, $targetUserId);
}

llxHeader('', $langs->trans('TakeposAdminUserStoreTitle'));
print load_fiche_titre($langs->trans('TakeposAdminUserStoreTitle'));
print '<div class="tabsAction">';
print '<a class="butAction" href="' . DOL_URL_ROOT . '/takepos/admin/stores.php">' . $langs->trans('TakeposShortcutStores') . '</a>';
print '<a class="butAction" href="' . DOL_URL_ROOT . '/takepos/admin/terminal_map.php">' . $langs->trans('TakeposShortcutTerminalMapping') . '</a>';
print '</div>';

if ($message !== '') {
    setEventMessages($message, null, $messageType);
}

print '<form method="GET" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '">';
print '<table class="border centpercent">';
print '<tr><td class="titlefield">' . $langs->trans('TakeposAdminSelectUser') . '</td><td><select name="target_user_id" onchange="this.form.submit();">';
print '<option value="0">' . $langs->trans('TakeposAdminSelectOption') . '</option>';
foreach ($users as $u) {
    $name = trim((string) $u->firstname . ' ' . (string) $u->lastname);
    $label = $u->login . ($name !== '' ? (' - ' . $name) : '') . (!empty($u->admin) ? ' [admin]' : '');
    $sel = ((int) $targetUserId === (int) $u->rowid ? ' selected' : '');
    print '<option value="' . ((int) $u->rowid) . '"' . $sel . '>' . dol_escape_htmltag($label) . '</option>';
}
print '</select></td></tr>';
print '</table>';
print '</form>';

if ($targetUserId > 0) {
    print '<br><form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="save_assignments">';
    print '<input type="hidden" name="target_user_id" value="' . ((int) $targetUserId) . '">';

    print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
    print '<tr class="liste_titre"><th width="80">' . $langs->trans('TakeposAdminAllow') . '</th><th>' . $langs->trans('TakeposCommonStore') . '</th></tr>';
    foreach ($stores as $s) {
        $checked = in_array((int) $s->rowid, $currentAssignments, true);
        print '<tr class="oddeven">';
        print '<td class="center"><input type="checkbox" name="store_ids[]" value="' . ((int) $s->rowid) . '"' . ($checked ? ' checked' : '') . '></td>';
        print '<td>' . dol_escape_htmltag($s->code . ' - ' . $s->label) . '</td>';
        print '</tr>';
    }
    print '</table></div>';

    print '<br><input type="submit" class="button button-save" value="' . $langs->trans('TakeposAdminSaveAssignments') . '">';
    print '</form>';
}

llxFooter();
$db->close();
