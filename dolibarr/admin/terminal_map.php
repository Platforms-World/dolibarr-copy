<?php
/**
 * Terminal mapping admin page.
 */
require '../../main.inc.php';

require_once __DIR__ . '/../lib/takepos_lang.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/../class/TakeposAccess.class.php';
require_once __DIR__ . '/../class/TakeposStoreService.class.php';
require_once __DIR__ . '/../class/TakeposTerminalService.class.php';

$langs->loadLangs(array('admin', 'main', 'cashdesk', 'takeposcustom@takepos'));

restrictedArea($user, 'takepos', 0, '');
TakeposAccess::requireAdminAccess(
    $db,
    $user,
    'takepos.terminal_governance',
    'takepos.terminal.manage',
    isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null,
    $langs->trans('TakeposAdminTerminalMappingAccessDenied')
);

$entity = !empty($user->entity) ? (int) $user->entity : 1;
$action = GETPOST('action', 'aZ09');
$message = '';
$messageType = 'mesgs';

try {
    if ($action !== '' && GETPOST('token', 'alpha') !== $_SESSION['newtoken']) {
        throw new Exception($langs->trans('TakeposCommonInvalidSecurityToken'));
    }

    if ($action === 'save_terminal') {
        $code = GETPOST('terminal_code', 'aZ09');
        $label = GETPOST('label', 'none');
        $storeId = GETPOSTINT('store_id');
        $active = GETPOSTINT('active');

        $tid = TakeposTerminalService::registerOrUpdateTerminal($db, $user, $entity, $code, $label, $storeId, $active);
        $message = $langs->trans('TakeposAdminTerminalSaved', (int) $tid);
    }
} catch (Throwable $e) {
    $message = $e->getMessage();
    $messageType = 'errors';
}

$stores = TakeposStoreService::listStores($db, $entity, true);
$terminals = TakeposTerminalService::listTerminals($db, $entity, 0, false);

llxHeader('', $langs->trans('TakeposAdminTerminalMappingTitle'));
print load_fiche_titre($langs->trans('TakeposAdminTerminalMappingTitle'));
print '<div class="tabsAction">';
print '<a class="butAction" href="' . DOL_URL_ROOT . '/takepos/admin/stores.php">' . $langs->trans('TakeposShortcutStores') . '</a>';
print '<a class="butAction" href="' . DOL_URL_ROOT . '/takepos/admin/user_store.php">' . $langs->trans('TakeposAdminUserStoreTitle') . '</a>';
print '</div>';

if ($message !== '') {
    setEventMessages($message, null, $messageType);
}

print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="save_terminal">';
print '<table class="border centpercent">';
print '<tr class="liste_titre"><th colspan="2">' . $langs->trans('TakeposAdminCreateTerminal') . '</th></tr>';
print '<tr><td class="titlefield">' . $langs->trans('TakeposAdminTerminalCode') . '</td><td><input type="text" name="terminal_code" required pattern="[A-Za-z0-9_-]{1,32}" maxlength="32"></td></tr>';
print '<tr><td>' . $langs->trans('TakeposAdminLabel') . '</td><td><input type="text" name="label" required maxlength="128" class="minwidth300"></td></tr>';
print '<tr><td>' . $langs->trans('TakeposCommonStore') . '</td><td><select name="store_id"><option value="0">' . $langs->trans('TakeposAdminUnassigned') . '</option>';
foreach ($stores as $s) {
    print '<option value="' . ((int) $s->rowid) . '">' . dol_escape_htmltag($s->code . ' - ' . $s->label) . '</option>';
}
print '</select></td></tr>';
print '<tr><td>' . $langs->trans('TakeposCommonStatus') . '</td><td><select name="active"><option value="1" selected>' . $langs->trans('TakeposAdminActive') . '</option><option value="0">' . $langs->trans('TakeposAdminDisabled') . '</option></select></td></tr>';
print '</table>';
print '<br><input type="submit" class="button button-save" value="' . $langs->trans('TakeposAdminSaveTerminal') . '">';
print '</form>';

print '<br><div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th>ID</th><th>' . $langs->trans('TakeposAdminTerminalCode') . '</th><th>' . $langs->trans('TakeposAdminLabel') . '</th><th>' . $langs->trans('TakeposCommonStore') . '</th><th>' . $langs->trans('TakeposCommonStatus') . '</th><th>' . $langs->trans('TakeposAdminLastSeen') . '</th><th>' . $langs->trans('TakeposCommonSave') . '</th></tr>';
foreach ($terminals as $t) {
    print '<tr class="oddeven">';
    print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="save_terminal">';
    print '<td>' . ((int) $t->rowid) . '</td>';
    print '<td><input type="text" name="terminal_code" value="' . dol_escape_htmltag($t->terminal_code) . '" required pattern="[A-Za-z0-9_-]{1,32}" maxlength="32"></td>';
    print '<td><input type="text" name="label" value="' . dol_escape_htmltag($t->label) . '" required maxlength="128"></td>';
    print '<td><select name="store_id"><option value="0">' . $langs->trans('TakeposAdminUnassigned') . '</option>';
    foreach ($stores as $s) {
        $sel = ((int) $t->fk_store === (int) $s->rowid ? ' selected' : '');
        print '<option value="' . ((int) $s->rowid) . '"' . $sel . '>' . dol_escape_htmltag($s->code . ' - ' . $s->label) . '</option>';
    }
    print '</select></td>';
    print '<td><select name="active"><option value="1"' . ((int) $t->active === 1 ? ' selected' : '') . '>' . $langs->trans('TakeposAdminActive') . '</option><option value="0"' . ((int) $t->active === 0 ? ' selected' : '') . '>' . $langs->trans('TakeposAdminDisabled') . '</option></select></td>';
    print '<td>' . dol_escape_htmltag((string) $t->last_seen) . '</td>';
    print '<td><input type="submit" class="button" value="' . $langs->trans('TakeposCommonSave') . '"></td>';
    print '</form>';
    print '</tr>';
}
print '</table></div>';

llxFooter();
$db->close();
