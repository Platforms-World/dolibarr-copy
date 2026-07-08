<?php
/**
 * Store management admin page.
 */
require '../../main.inc.php';
require_once __DIR__ . '/../lib/takepos_help.php';

require_once __DIR__ . '/../lib/takepos_lang.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/../class/TakeposAccess.class.php';
require_once __DIR__ . '/../class/TakeposStoreService.class.php';
require_once __DIR__ . '/../class/TakeposAudit.class.php';

$langs->loadLangs(array('admin', 'main', 'cashdesk', 'takeposcustom@takepos'));

restrictedArea($user, 'takepos', 0, '');
TakeposAccess::requireAdminAccess(
    $db,
    $user,
    'takepos.store_governance',
    'takepos.store.manage',
    isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null,
    $langs->trans('TakeposAdminStoresAccessDenied')
);

$entity = !empty($user->entity) ? (int) $user->entity : 1;
$action = GETPOST('action', 'aZ09');
$message = '';
$messageType = 'mesgs';

try {
    if ($action !== '' && GETPOST('token', 'alpha') !== $_SESSION['newtoken']) {
        throw new Exception($langs->trans('TakeposCommonInvalidSecurityToken'));
    }

    if ($action === 'create') {
        $code = GETPOST('code', 'aZ09');
        $label = GETPOST('label', 'none');
        $description = GETPOST('description', 'none');
        $warehouseId = GETPOSTINT('warehouse_id');

        $newId = TakeposStoreService::createStore($db, $user, $entity, $code, $label, $description, $warehouseId);
        $message = $langs->trans('TakeposAdminStoreCreated', (int) $newId);
    }

    if ($action === 'update') {
        $storeId = GETPOSTINT('store_id');
        $code = GETPOST('code', 'aZ09');
        $label = GETPOST('label', 'none');
        $description = GETPOST('description', 'none');
        $warehouseId = GETPOSTINT('warehouse_id');
        $active = GETPOSTINT('active');

        TakeposStoreService::updateStore($db, $user, $entity, $storeId, $code, $label, $description, $warehouseId, $active);
        $message = $langs->trans('TakeposAdminStoreUpdated');
    }

    if ($action === 'disable') {
        $storeId = GETPOSTINT('store_id');
        $store = TakeposStoreService::getStore($db, $entity, $storeId);
        if (!$store) {
            throw new Exception($langs->trans('TakeposAdminStoreNotFound'));
        }

        TakeposStoreService::updateStore($db, $user, $entity, $storeId, $store->code, $store->label, $store->description, (int) $store->warehouse_id, 0);
        $message = $langs->trans('TakeposAdminStoreDisabled');
    }
} catch (Throwable $e) {
    $message = $e->getMessage();
    $messageType = 'errors';
}

$stores = TakeposStoreService::listStores($db, $entity, false);

$warehouses = array();
if (isModEnabled('stock')) {
    $sqlWh = "SELECT rowid, ref, label FROM " . MAIN_DB_PREFIX . "entrepot WHERE entity = " . ((int) $entity) . " AND status = 1 ORDER BY ref ASC";
    $resWh = $db->query($sqlWh);
    if ($resWh) {
        while ($obj = $db->fetch_object($resWh)) {
            $warehouses[] = $obj;
        }
    }
}

llxHeader('', $langs->trans('TakeposAdminStoresTitle'));
print load_fiche_titre($langs->trans('TakeposAdminStoresTitle'));
print '<div class="tabsAction">';
print '<a class="butAction" href="' . DOL_URL_ROOT . '/takepos/admin/terminal_map.php">' . $langs->trans('TakeposShortcutTerminalMapping') . '</a>';
print '<a class="butAction" href="' . DOL_URL_ROOT . '/takepos/admin/user_store.php">' . $langs->trans('TakeposAdminUserStoreTitle') . '</a>';
print '</div>';

if ($message !== '') {
    setEventMessages($message, null, $messageType);
}

print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="create">';
print '<table class="border centpercent">';
print '<tr class="liste_titre"><th colspan="2">' . $langs->trans('TakeposAdminCreateStore') . '</th></tr>';
print '<tr><td class="titlefield">' . $langs->trans('TakeposAdminStoreCode') . '</td><td><input type="text" name="code" required pattern="[A-Za-z0-9_-]{2,32}" maxlength="32"></td></tr>';
print '<tr><td>' . $langs->trans('TakeposAdminLabel') . '</td><td><input type="text" name="label" required maxlength="128" class="minwidth300"></td></tr>';
print '<tr><td>' . $langs->trans('TakeposAdminDescription') . '</td><td><input type="text" name="description" maxlength="255" class="minwidth300"></td></tr>';
print '<tr><td>' . $langs->trans('TakeposAdminWarehouse') . '</td><td><select name="warehouse_id"><option value="0">' . $langs->trans('TakeposCommonNone') . '</option>';
foreach ($warehouses as $wh) {
    print '<option value="' . ((int) $wh->rowid) . '">' . dol_escape_htmltag(trim($wh->ref . ' - ' . $wh->label, ' -')) . '</option>';
}
print '</select></td></tr>';
print '</table>';
print '<br><input type="submit" class="button button-save" value="' . $langs->trans('TakeposAdminCreateStoreAction') . '">';
print '</form>';

print '<br><div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>ID</th><th>' . $langs->trans('TakeposAdminStoreCode') . '</th><th>' . $langs->trans('TakeposAdminLabel') . '</th><th>' . $langs->trans('TakeposAdminDescription') . '</th><th>' . $langs->trans('TakeposAdminWarehouse') . '</th><th>' . $langs->trans('TakeposAdminStoreStatus') . '</th><th>' . $langs->trans('TakeposAdminUpdate') . '</th><th>' . $langs->trans('TakeposAdminDisable') . '</th></tr>';
foreach ($stores as $store) {
    print '<tr class="oddeven">';
    print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="update">';
    print '<input type="hidden" name="store_id" value="' . ((int) $store->rowid) . '">';
    print '<td>' . ((int) $store->rowid) . '</td>';
    print '<td><input type="text" name="code" value="' . dol_escape_htmltag($store->code) . '" required pattern="[A-Za-z0-9_-]{2,32}" maxlength="32"></td>';
    print '<td><input type="text" name="label" value="' . dol_escape_htmltag($store->label) . '" required maxlength="128"></td>';
    print '<td><input type="text" name="description" value="' . dol_escape_htmltag($store->description) . '" maxlength="255"></td>';
    print '<td><select name="warehouse_id"><option value="0">' . $langs->trans('TakeposCommonNone') . '</option>';
    foreach ($warehouses as $wh) {
        $sel = ((int) $store->warehouse_id === (int) $wh->rowid ? ' selected' : '');
        print '<option value="' . ((int) $wh->rowid) . '"' . $sel . '>' . dol_escape_htmltag(trim($wh->ref . ' - ' . $wh->label, ' -')) . '</option>';
    }
    print '</select></td>';
    print '<td><select name="active"><option value="1"' . ((int) $store->active === 1 ? ' selected' : '') . '>' . $langs->trans('TakeposAdminActive') . '</option><option value="0"' . ((int) $store->active === 0 ? ' selected' : '') . '>' . $langs->trans('TakeposAdminDisabled') . '</option></select></td>';
    print '<td><input type="submit" class="button" value="' . $langs->trans('TakeposCommonSave') . '"></td>';
    print '</form>';
    print '<td>';
    if ((int) $store->active === 1) {
        print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '" onsubmit="return confirm(\'' . dol_escape_js($langs->trans('TakeposAdminDisableStoreConfirm')) . '\');">';
        print '<input type="hidden" name="token" value="' . newToken() . '">';
        print '<input type="hidden" name="action" value="disable">';
        print '<input type="hidden" name="store_id" value="' . ((int) $store->rowid) . '">';
        print '<input type="submit" class="button button-cancel" value="' . $langs->trans('TakeposAdminDisable') . '">';
        print '</form>';
    }
    print '</td>';
    print '</tr>';
}
print '</table></div>';

print takeposHelpRender($langs, __FILE__);

llxFooter();
$db->close();
