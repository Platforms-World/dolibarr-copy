<?php
require '../../main.inc.php';
require_once __DIR__ . '/../lib/takepos_help.php';

require_once __DIR__ . '/../lib/takepos_lang.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
require_once __DIR__ . '/../class/TakeposAccess.class.php';
require_once __DIR__ . '/../class/TakeposAudit.class.php';
require_once __DIR__ . '/../class/TakeposUserAccess.class.php';
require_once __DIR__ . '/../class/TakeposUserManager.class.php';
require_once __DIR__ . '/../class/TakeposInputValidator.class.php';

$langs->loadLangs(array('admin', 'users', 'takepos', 'takeposcustom@takepos'));

if (empty($user->id)) {
    accessforbidden();
}
restrictedArea($user, 'takepos', 0, '');

TakeposAccess::requireAdminAccess(
    $db,
    $user,
    'takepos.users.manage',
    'takepos.action.users_manage',
    isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null,
    $langs->trans('TakeposWorkspaceUsersAccessDenied')
);

if (!TakeposUserAccess::canOpenUserManager($db, $user)) {
    accessforbidden($langs->trans('TakeposAdminUsersAuthorizedOnly'));
}

/**
 * Best-effort audit logger for admin rights operations.
 */
function takeposAdminAudit($db, $user, $targetUserId, $actionName, $data = array(), $description = '')
{
    if (!class_exists('TakeposAudit')) {
        return;
    }

    $payload = is_array($data) ? $data : array();
    $payload['target_user_id'] = (int) $targetUserId;
    $payload['action'] = (string) $actionName;

    TakeposAudit::logEvent(
        $db,
        $user,
        'admin_user_permission_changed',
        TakeposAudit::SEVERITY_WARNING,
        $payload,
        $description !== '' ? $description : 'TakePOS user permissions updated',
        'user',
        (int) $targetUserId,
        null
    );
}

/**
 * Parse posted decimal limit strictly.
 */
function takeposParsePostedDecimalLimit($fieldName, $label)
{
    $raw = GETPOST($fieldName, 'none');
    if ($raw === '' || $raw === null) {
        return 0.0;
    }

    $parsed = null;
    if (!TakeposInputValidator::parsePositiveDecimal($raw, $parsed, true, 8)) {
        global $langs;
        throw new Exception($langs->trans('TakeposAdminUsersErrorDecimalValue', $label));
    }

    return (float) $parsed;
}

$manager = new TakeposUserManager($db, $user);
$action = GETPOST('action', 'aZ09');
$mode = GETPOST('mode', 'aZ09');
$id = GETPOSTINT('id');
$currentEntity = !empty($user->entity) ? (int) $user->entity : 1;

$mesg = '';
$mesgType = 'mesgs';

$roleProfiles = TakeposUserAccess::getRoleProfiles();
$posPermissionCodes = TakeposUserAccess::getPosPermissionCodes();
$defaultRoleCode = TakeposUserAccess::ROLE_CASHIER;

try {
    if (!empty($action) && GETPOST('token') !== $_SESSION['newtoken']) {
        throw new Exception($langs->trans('TakeposCommonInvalidSecurityToken'));
    }

    if ($action === 'create_user') {
        $newId = $manager->createUser(array(
            'login' => GETPOST('login', 'alphanohtml'),
            'firstname' => GETPOST('firstname', 'none'),
            'lastname' => GETPOST('lastname', 'none'),
            'email' => GETPOST('email', 'alphanohtml'),
            'password' => GETPOST('password', 'none'),
        ), GETPOST('rights', 'array'));

        $roleCode = GETPOST('role_code', 'aZ09');
        if (!isset($roleProfiles[$roleCode])) {
            $roleCode = $defaultRoleCode;
        }

        $posPerms = GETPOST('pos_permissions', 'array');
        if (!is_array($posPerms) || empty($posPerms)) {
            $posPerms = TakeposUserAccess::getDefaultPermissionsForRole($roleCode);
        }

        $maxDiscountPercent = takeposParsePostedDecimalLimit('max_discount_percent', $langs->trans('TakeposAdminUsersMaxDiscountPercent'));
        $maxDiscountAmount = takeposParsePostedDecimalLimit('max_discount_amount', $langs->trans('TakeposAdminUsersMaxDiscountAmount'));
        $maxPriceDelta = takeposParsePostedDecimalLimit('max_price_override_delta', $langs->trans('TakeposAdminUsersMaxPriceOverrideDelta'));

        TakeposUserAccess::saveUserPermissionCodes($db, (int) $newId, $currentEntity, $posPerms);
        TakeposUserAccess::saveUserLimits($db, (int) $newId, $currentEntity, $roleCode, $maxDiscountPercent, $maxDiscountAmount, $maxPriceDelta);

        takeposAdminAudit($db, $user, (int) $newId, 'create_user', array(
            'role_code' => $roleCode,
            'permissions' => array_values((array) $posPerms),
            'max_discount_percent' => (float) $maxDiscountPercent,
            'max_discount_amount' => (float) $maxDiscountAmount,
            'max_price_override_delta' => (float) $maxPriceDelta,
        ), 'TakePOS user created and POS permissions assigned');

        $mesg = $langs->trans('TakeposAdminUsersCreateSuccess', (int) $newId);
        $mode = 'edit';
        $id = (int) $newId;
    }

    if ($action === 'update_profile') {
        $manager->updateUserProfile($id, array(
            'login' => GETPOST('login', 'alphanohtml'),
            'firstname' => GETPOST('firstname', 'none'),
            'lastname' => GETPOST('lastname', 'none'),
            'email' => GETPOST('email', 'alphanohtml'),
        ));
        $mesg = $langs->trans('TakeposAdminUsersProfileUpdatedSuccess');
        $mode = 'edit';
    }

    if ($action === 'update_rights') {
        $manager->updateRights($id, GETPOST('rights', 'array'));

        $roleCode = GETPOST('role_code', 'aZ09');
        if (!isset($roleProfiles[$roleCode])) {
            $roleCode = $defaultRoleCode;
        }

        $posPerms = GETPOST('pos_permissions', 'array');
        if (!is_array($posPerms)) {
            $posPerms = array();
        }

        $maxDiscountPercent = takeposParsePostedDecimalLimit('max_discount_percent', $langs->trans('TakeposAdminUsersMaxDiscountPercent'));
        $maxDiscountAmount = takeposParsePostedDecimalLimit('max_discount_amount', $langs->trans('TakeposAdminUsersMaxDiscountAmount'));
        $maxPriceDelta = takeposParsePostedDecimalLimit('max_price_override_delta', $langs->trans('TakeposAdminUsersMaxPriceOverrideDelta'));

        TakeposUserAccess::saveUserPermissionCodes($db, (int) $id, $currentEntity, $posPerms);
        TakeposUserAccess::saveUserLimits($db, (int) $id, $currentEntity, $roleCode, $maxDiscountPercent, $maxDiscountAmount, $maxPriceDelta);

        takeposAdminAudit($db, $user, (int) $id, 'update_rights', array(
            'role_code' => $roleCode,
            'permissions' => array_values((array) $posPerms),
            'max_discount_percent' => (float) $maxDiscountPercent,
            'max_discount_amount' => (float) $maxDiscountAmount,
            'max_price_override_delta' => (float) $maxPriceDelta,
        ), 'TakePOS user permissions and limits updated');

        $mesg = $langs->trans('TakeposAdminUsersRightsUpdatedSuccess');
        $mode = 'edit';
    }

    if ($action === 'change_password') {
        $manager->updatePassword($id, GETPOST('new_password', 'none'));
        $mesg = $langs->trans('TakeposAdminUsersPasswordChangedSuccess');
        $mode = 'edit';
    }

    if ($action === 'disable_user') {
        $manager->setStatus($id, false);
        $mesg = $langs->trans('TakeposAdminUsersDisabledSuccess');
        $mode = 'edit';
    }

    if ($action === 'enable_user') {
        $manager->setStatus($id, true);
        $mesg = $langs->trans('TakeposAdminUsersEnabledSuccess');
        $mode = 'edit';
    }
} catch (Throwable $e) {
    $mesg = $e->getMessage();
    $loginExistsMessage = $langs->trans('TakeposAdminUsersErrorLoginExists');
    if (stripos($mesg, 'Login already exists') !== false || ($loginExistsMessage !== 'TakeposAdminUsersErrorLoginExists' && stripos($mesg, $loginExistsMessage) !== false)) {
        $existing = $manager->findUserByLogin(GETPOST('login', 'alphanohtml'));
        if ($existing) {
            $mesg .= ' ' . $langs->trans('TakeposAdminUsersExistingUserInfo', (int) $existing->id, dol_escape_htmltag($existing->login));
        }
    }
    $mesgType = 'errors';
}

$defs = $manager->getRightDefinitions();
$grantable = $manager->getGrantableRightIds();
$target = null;
$targetRights = array();
$targetEntityWarning = false;
$targetPosPermissions = array();
$targetLimits = array(
    'role_code' => $defaultRoleCode,
    'max_discount_percent' => 0,
    'max_discount_amount' => 0,
    'max_price_override_delta' => 0,
);
if ($mode === 'edit' && $id > 0) {
    $target = $manager->getUserById($id);
    if ($target) {
        $targetRights = $manager->getUserAssignedRightIds($id);
        $targetEntityWarning = ((int) $target->entity === 0);
        $targetPosPermissions = TakeposUserAccess::listUserPermissionCodes($db, (int) $id, $currentEntity);
        $targetLimits = TakeposUserAccess::getUserLimits($db, (int) $id, $currentEntity);
    }
}
$rows = $manager->getUsers();

llxHeader('', $langs->trans('TakeposAdminUsersTitle'));
print load_fiche_titre($langs->trans('TakeposAdminUsersTitle'));

if ($mesg !== '') {
    setEventMessages($mesg, null, $mesgType);
}

print '<div class="info">';
print dol_escape_htmltag($langs->trans('TakeposAdminUsersRestrictedInfo'));
print '</div>';

print '<div class="tabsAction">';
print '<a class="butAction" href="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '">' . dol_escape_htmltag($langs->trans('TakeposAdminUsersListButton')) . '</a>';
print '<a class="butAction" href="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '?mode=create">' . dol_escape_htmltag($langs->trans('TakeposAdminUsersCreateButton')) . '</a>';
print '</div>';

if ($mode === 'create') {
    $selectedRoleCode = GETPOST('role_code', 'aZ09');
    if (!isset($roleProfiles[$selectedRoleCode])) {
        $selectedRoleCode = $defaultRoleCode;
    }
    $defaultPosPerms = GETPOST('pos_permissions', 'array');
    if (!is_array($defaultPosPerms) || empty($defaultPosPerms)) {
        $defaultPosPerms = TakeposUserAccess::getDefaultPermissionsForRole($selectedRoleCode);
    }

    print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '?mode=create">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="create_user">';

    print '<table class="border centpercent">';
    print '<tr><td class="titlefield">' . dol_escape_htmltag($langs->trans('TakeposCommonLogin')) . '</td><td><input type="text" class="minwidth300" name="login" required></td></tr>';
    print '<tr><td>' . dol_escape_htmltag($langs->trans('FirstName')) . '</td><td><input type="text" class="minwidth300" name="firstname"></td></tr>';
    print '<tr><td>' . dol_escape_htmltag($langs->trans('LastName')) . '</td><td><input type="text" class="minwidth300" name="lastname" required></td></tr>';
    print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposCommonEmail')) . '</td><td><input type="email" class="minwidth300" name="email"></td></tr>';
    print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposCommonPassword')) . '</td><td><input type="password" class="minwidth300" name="password" required></td></tr>';
    print '</table>';

    print '<br>';
    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre"><th width="90">' . dol_escape_htmltag($langs->trans('TakeposAdminUsersGrant')) . '</th><th width="100">' . dol_escape_htmltag($langs->trans('TakeposAdminUsersRightId')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposAdminUsersRightLabel')) . '</th></tr>';
    foreach ($defs as $def) {
        $allowed = in_array((int) $def['id'], $grantable, true) && !empty($def['delegable']);
        print '<tr class="oddeven">';
        print '<td class="center">' . ($allowed ? '<input type="checkbox" name="rights[]" value="' . ((int) $def['id']) . '"' . (!empty($def['recommended']) ? ' checked' : '') . '>' : '-') . '</td>';
        print '<td>' . ((int) $def['id']) . '</td>';
        print '<td>' . dol_escape_htmltag($def['label']) . ($allowed ? '' : ' <span class="opacitymedium">(' . dol_escape_htmltag($langs->trans('TakeposAdminUsersNotGrantable')) . ')</span>') . '</td>';
        print '</tr>';
    }
    print '</table>';
    print '</div>';

    print '<br><table class="border centpercent">';
    print '<tr><td class="titlefield">' . dol_escape_htmltag($langs->trans('TakeposAdminUsersPosRole')) . '</td><td><select name="role_code">';
    foreach ($roleProfiles as $roleCode => $roleInfo) {
        $selected = ($selectedRoleCode === $roleCode ? ' selected' : '');
        print '<option value="' . dol_escape_htmltag($roleCode) . '"' . $selected . '>' . dol_escape_htmltag($roleInfo['label']) . '</option>';
    }
    print '</select></td></tr>';
    print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposAdminUsersMaxDiscountPercent')) . '</td><td><input type="number" step="0.01" min="0" name="max_discount_percent" value="0"></td></tr>';
    print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposAdminUsersMaxDiscountAmount')) . '</td><td><input type="number" step="0.01" min="0" name="max_discount_amount" value="0"></td></tr>';
    print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposAdminUsersMaxPriceOverrideDelta')) . '</td><td><input type="number" step="0.01" min="0" name="max_price_override_delta" value="0"></td></tr>';
    print '</table>';

    print '<br><div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre"><th width="90">' . dol_escape_htmltag($langs->trans('TakeposAdminUsersAllow')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposAdminUsersRuntimePermission')) . '</th></tr>';
    foreach ($posPermissionCodes as $permCode) {
        $checked = in_array($permCode, $defaultPosPerms, true);
        print '<tr class="oddeven">';
        print '<td class="center"><input type="checkbox" name="pos_permissions[]" value="' . dol_escape_htmltag($permCode) . '"' . ($checked ? ' checked' : '') . '></td>';
        print '<td>' . dol_escape_htmltag($permCode) . '</td>';
        print '</tr>';
    }
    print '</table>';
    print '</div>';

    print '<br><input type="submit" class="button button-save" value="' . dol_escape_htmltag($langs->trans('TakeposAdminUsersCreateButton')) . '">';
    print '</form>';
} elseif ($mode === 'edit' && $target) {
    if (!empty($targetEntityWarning)) {
        print '<div class="warning">' . dol_escape_htmltag($langs->trans('TakeposAdminUsersGlobalEntityWarning')) . '</div>';
    }

    print '<div class="fichecenter">';
    print '<div class="fichehalfleft">';

    print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '?mode=edit&id=' . ((int) $target->id) . '">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="update_profile">';
    print '<input type="hidden" name="id" value="' . ((int) $target->id) . '">';
    print '<table class="border centpercent">';
    print '<tr><td class="titlefield">' . dol_escape_htmltag($langs->trans('TakeposCommonLogin')) . '</td><td><input type="text" class="minwidth300" name="login" value="' . dol_escape_htmltag($target->login) . '" required></td></tr>';
    print '<tr><td>' . dol_escape_htmltag($langs->trans('FirstName')) . '</td><td><input type="text" class="minwidth300" name="firstname" value="' . dol_escape_htmltag($target->firstname) . '"></td></tr>';
    print '<tr><td>' . dol_escape_htmltag($langs->trans('LastName')) . '</td><td><input type="text" class="minwidth300" name="lastname" value="' . dol_escape_htmltag($target->lastname) . '" required></td></tr>';
    print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposCommonEmail')) . '</td><td><input type="email" class="minwidth300" name="email" value="' . dol_escape_htmltag($target->email) . '"></td></tr>';
    print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposCommonStatus')) . '</td><td>' . (((int) $target->statut === 1) ? dol_escape_htmltag($langs->trans('TakeposCommonEnabled')) : dol_escape_htmltag($langs->trans('TakeposCommonDisabled'))) . '</td></tr>';
    print '<tr><td>' . dol_escape_htmltag($langs->trans('Admin')) . '</td><td>' . (!empty($target->admin) ? dol_escape_htmltag($langs->trans('TakeposCommonYes')) : dol_escape_htmltag($langs->trans('TakeposCommonNo'))) . '</td></tr>';
    print '<tr><td>' . dol_escape_htmltag($langs->trans('Entity')) . '</td><td>' . ((int) $target->entity) . '</td></tr>';
    print '</table>';
    print '<br><input type="submit" class="button button-save" value="' . dol_escape_htmltag($langs->trans('TakeposAdminUsersSaveProfile')) . '">';
    print '</form>';

    print '<br><br>';
    print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '?mode=edit&id=' . ((int) $target->id) . '">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="change_password">';
    print '<input type="hidden" name="id" value="' . ((int) $target->id) . '">';
    print '<table class="border centpercent">';
    print '<tr><td class="titlefield">' . dol_escape_htmltag($langs->trans('TakeposAdminUsersNewPassword')) . '</td><td><input type="password" class="minwidth300" name="new_password" required></td></tr>';
    print '</table>';
    print '<br><input type="submit" class="button" value="' . dol_escape_htmltag($langs->trans('TakeposAdminUsersChangePassword')) . '">';
    print '</form>';

    print '<br><br>';
    if ((int) $target->statut === 1) {
        print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '?mode=edit&id=' . ((int) $target->id) . '" onsubmit="return confirm(\'' . dol_escape_js($langs->trans('TakeposAdminUsersDisableConfirm')) . '\');">';
        print '<input type="hidden" name="token" value="' . newToken() . '">';
        print '<input type="hidden" name="action" value="disable_user">';
        print '<input type="hidden" name="id" value="' . ((int) $target->id) . '">';
        print '<input type="submit" class="button button-delete" value="' . dol_escape_htmltag($langs->trans('TakeposAdminUsersDisableUser')) . '">';
        print '</form>';
    } else {
        print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '?mode=edit&id=' . ((int) $target->id) . '">';
        print '<input type="hidden" name="token" value="' . newToken() . '">';
        print '<input type="hidden" name="action" value="enable_user">';
        print '<input type="hidden" name="id" value="' . ((int) $target->id) . '">';
        print '<input type="submit" class="button button-save" value="' . dol_escape_htmltag($langs->trans('TakeposAdminUsersEnableUser')) . '">';
        print '</form>';
    }

    print '</div>';
    print '<div class="fichehalfright">';

    print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '?mode=edit&id=' . ((int) $target->id) . '">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="update_rights">';
    print '<input type="hidden" name="id" value="' . ((int) $target->id) . '">';

    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre"><th width="90">' . dol_escape_htmltag($langs->trans('TakeposAdminUsersAssign')) . '</th><th width="100">' . dol_escape_htmltag($langs->trans('TakeposAdminUsersRightId')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposAdminUsersRightLabel')) . '</th></tr>';
    foreach ($defs as $def) {
        $allowed = in_array((int) $def['id'], $grantable, true) && !empty($def['delegable']);
        $checked = in_array((int) $def['id'], $targetRights, true);
        print '<tr class="oddeven">';
        if ($allowed) {
            print '<td class="center"><input type="checkbox" name="rights[]" value="' . ((int) $def['id']) . '"' . ($checked ? ' checked' : '') . '></td>';
        } else {
            print '<td class="center">' . ($checked ? dol_escape_htmltag($langs->trans('TakeposAdminUsersLocked')) : '-') . '</td>';
        }
        print '<td>' . ((int) $def['id']) . '</td>';
        print '<td>' . dol_escape_htmltag($def['label']) . ($allowed ? '' : ' <span class="opacitymedium">(' . dol_escape_htmltag($langs->trans('TakeposAdminUsersAboveScope')) . ')</span>') . '</td>';
        print '</tr>';
    }
    print '</table>';
    print '</div>';

    $selectedRoleCode = isset($targetLimits['role_code']) ? (string) $targetLimits['role_code'] : $defaultRoleCode;
    if (!isset($roleProfiles[$selectedRoleCode])) {
        $selectedRoleCode = $defaultRoleCode;
    }

    print '<br><table class="border centpercent">';
    print '<tr><td class="titlefield">' . dol_escape_htmltag($langs->trans('TakeposAdminUsersPosRole')) . '</td><td><select name="role_code">';
    foreach ($roleProfiles as $roleCode => $roleInfo) {
        $selected = ($selectedRoleCode === $roleCode ? ' selected' : '');
        print '<option value="' . dol_escape_htmltag($roleCode) . '"' . $selected . '>' . dol_escape_htmltag($roleInfo['label']) . '</option>';
    }
    print '</select></td></tr>';
    print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposAdminUsersMaxDiscountPercent')) . '</td><td><input type="number" step="0.01" min="0" name="max_discount_percent" value="' . price(isset($targetLimits['max_discount_percent']) ? (float) $targetLimits['max_discount_percent'] : 0, 0, '', 1) . '"></td></tr>';
    print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposAdminUsersMaxDiscountAmount')) . '</td><td><input type="number" step="0.01" min="0" name="max_discount_amount" value="' . price(isset($targetLimits['max_discount_amount']) ? (float) $targetLimits['max_discount_amount'] : 0, 0, '', 1) . '"></td></tr>';
    print '<tr><td>' . dol_escape_htmltag($langs->trans('TakeposAdminUsersMaxPriceOverrideDelta')) . '</td><td><input type="number" step="0.01" min="0" name="max_price_override_delta" value="' . price(isset($targetLimits['max_price_override_delta']) ? (float) $targetLimits['max_price_override_delta'] : 0, 0, '', 1) . '"></td></tr>';
    print '</table>';

    print '<br><div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre"><th width="90">' . dol_escape_htmltag($langs->trans('TakeposAdminUsersAllow')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposAdminUsersRuntimePermission')) . '</th></tr>';
    foreach ($posPermissionCodes as $permCode) {
        $checked = in_array($permCode, $targetPosPermissions, true);
        print '<tr class="oddeven">';
        print '<td class="center"><input type="checkbox" name="pos_permissions[]" value="' . dol_escape_htmltag($permCode) . '"' . ($checked ? ' checked' : '') . '></td>';
        print '<td>' . dol_escape_htmltag($permCode) . '</td>';
        print '</tr>';
    }
    print '</table>';
    print '</div>';

    print '<br><input type="submit" class="button button-save" value="' . dol_escape_htmltag($langs->trans('TakeposAdminUsersSaveRights')) . '">';
    print '</form>';
    print '</div>';
    print '</div><div class="clearboth"></div>';
} else {
    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre"><th>' . dol_escape_htmltag($langs->trans('TakeposCommonId')) . '</th><th>' . dol_escape_htmltag($langs->trans('Entity')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposCommonLogin')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposCommonName')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposCommonEmail')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposCommonStatus')) . '</th><th>' . dol_escape_htmltag($langs->trans('Admin')) . '</th><th>' . dol_escape_htmltag($langs->trans('TakeposAdminUsersManage')) . '</th></tr>';
    foreach ($rows as $row) {
        $name = trim($row->firstname . ' ' . $row->lastname);
        print '<tr class="oddeven">';
        print '<td>' . ((int) $row->rowid) . '</td>';
        print '<td>' . ((int) $row->entity) . '</td>';
        print '<td>' . dol_escape_htmltag($row->login) . '</td>';
        print '<td>' . dol_escape_htmltag($name) . '</td>';
        print '<td>' . dol_escape_htmltag($row->email) . '</td>';
        print '<td>' . (((int) $row->statut === 1) ? dol_escape_htmltag($langs->trans('TakeposCommonEnabled')) : dol_escape_htmltag($langs->trans('TakeposCommonDisabled'))) . '</td>';
        print '<td>' . (!empty($row->admin) ? dol_escape_htmltag($langs->trans('TakeposCommonYes')) : dol_escape_htmltag($langs->trans('TakeposCommonNo'))) . '</td>';
        print '<td><a class="butActionSmall" href="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '?mode=edit&id=' . ((int) $row->rowid) . '">' . dol_escape_htmltag($langs->trans('TakeposAdminUsersManage')) . '</a></td>';
        print '</tr>';
    }
    print '</table>';
    print '</div>';
}

print takeposHelpRender($langs, __FILE__);

llxFooter();
$db->close();
