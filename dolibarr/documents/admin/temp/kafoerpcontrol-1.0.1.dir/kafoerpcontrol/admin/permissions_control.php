<?php
if (!function_exists('kafoerpcontrolResolveMain')) {
    function kafoerpcontrolResolveMain()
    {
        $candidates = array(
            __DIR__ . '/../../../main.inc.php',
            dirname(__DIR__, 3) . '/main.inc.php',
            dirname(__DIR__, 4) . '/main.inc.php',
            dirname(__DIR__, 5) . '/main.inc.php',
        );

        foreach ($candidates as $candidate) {
            $resolved = realpath($candidate);
            if ($resolved !== false && is_file($resolved)) {
                return $resolved;
            }
        }

        return null;
    }
}

$maininc = kafoerpcontrolResolveMain();
if ($maininc === null) {
    http_response_code(500);
    print 'Unable to locate Dolibarr main.inc.php';
    exit;
}
require_once $maininc;
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
require_once dol_buildpath('/kafoerpcontrol/core/lib/saascore.lib.php', 0);

$langs->loadLangs(array('admin', 'users', 'other', 'kafoerpcontrol@kafoerpcontrol'));

$canManageRights = false;
if (!empty($user->admin)) {
    $canManageRights = true;
} elseif (
    $user->hasRight('kafoerpcontrol', 'nativerightsmanage')
    && ($user->hasRight('user', 'user', 'write') || $user->hasRight('user', 'user', 'creer'))
) {
    $canManageRights = true;
}

if (!$canManageRights) {
    accessforbidden();
}
saascoreTrackAuthenticatedAccess($db, 'permissions_control.php');

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

$form = new Form($db);
$action = GETPOST('action', 'aZ09');
$selectedUserId = GETPOST('userid', 'int');
$saved = GETPOST('saved', 'int');
$error = '';

function kafoPcEscape($value)
{
    return dol_escape_htmltag((string) $value);
}

function kafoPcModuleIsEnabled($moduleCode)
{
    $moduleCode = trim((string) $moduleCode);
    if ($moduleCode === '') {
        return true;
    }

    if (isModEnabled($moduleCode)) {
        return true;
    }

    $moduleLower = strtolower($moduleCode);
    if ($moduleLower !== $moduleCode && isModEnabled($moduleLower)) {
        return true;
    }

    return false;
}

function kafoPcGetSelectableUsers($db, $entity)
{
    $users = array();
    $sql = 'SELECT rowid, login, firstname, lastname, admin, statut, entity';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'user';
    $sql .= ' WHERE entity IN (0, ' . ((int) $entity) . ')';
    $sql .= ' ORDER BY admin DESC, login ASC';

    $resql = $db->query($sql);
    if (!$resql) {
        return $users;
    }

    while ($obj = $db->fetch_object($resql)) {
        $users[(int) $obj->rowid] = $obj;
    }

    return $users;
}

function kafoPcBuildRightCode($module, $perms, $subperms, $rightId)
{
    $parts = array();

    $module = trim((string) $module);
    $perms = trim((string) $perms);
    $subperms = trim((string) $subperms);

    if ($module !== '') {
        $parts[] = $module;
    }
    if ($perms !== '') {
        $parts[] = $perms;
    }
    if ($subperms !== '') {
        $parts[] = $subperms;
    }

    if (empty($parts)) {
        return 'right_' . ((int) $rightId);
    }

    return implode('.', $parts);
}

function kafoPcUserRightsHasEntityColumn($db)
{
    static $hasEntityColumn = null;

    if ($hasEntityColumn !== null) {
        return $hasEntityColumn;
    }

    $sql = 'SHOW COLUMNS FROM ' . MAIN_DB_PREFIX . "user_rights LIKE 'entity'";
    $resql = $db->query($sql);
    $hasEntityColumn = ($resql && $db->num_rows($resql) > 0);

    return $hasEntityColumn;
}

function kafoPcGetNativeRights($db, $entity)
{
    $rights = array();
    $moduleLabels = array();

    $sql = 'SELECT rd.id, rd.module, rd.perms, rd.subperms, rd.libelle';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'rights_def as rd';
    $sql .= ' WHERE rd.entity IN (0, ' . ((int) $entity) . ')';
    $sql .= ' ORDER BY rd.module ASC, rd.perms ASC, rd.subperms ASC, rd.id ASC';

    $resql = $db->query($sql);
    if (!$resql) {
        return array($rights, $moduleLabels);
    }

    while ($obj = $db->fetch_object($resql)) {
        $moduleCode = trim((string) $obj->module);
        if (!kafoPcModuleIsEnabled($moduleCode)) {
            continue;
        }

        $rightId = (int) $obj->id;
        $moduleKey = ($moduleCode !== '' ? $moduleCode : 'core');
        if (!isset($moduleLabels[$moduleKey])) {
            $moduleLabels[$moduleKey] = ($moduleCode !== '' ? $moduleCode : 'Core');
        }

        $label = trim((string) $obj->libelle);
        if ($label === '') {
            $label = kafoPcBuildRightCode($moduleCode, $obj->perms, $obj->subperms, $rightId);
        }

        $rights[$rightId] = array(
            'id' => $rightId,
            'module' => $moduleKey,
            'module_raw' => $moduleCode,
            'code' => kafoPcBuildRightCode($moduleCode, $obj->perms, $obj->subperms, $rightId),
            'label' => $label,
        );
    }

    asort($moduleLabels);

    return array($rights, $moduleLabels);
}

function kafoPcGetUserDirectRights($db, $userId, $entityId, array $availableIds)
{
    $map = array();
    if ($userId <= 0 || empty($availableIds)) {
        return $map;
    }

    $sql = 'SELECT ur.fk_id';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'user_rights as ur';
    $sql .= ' WHERE ur.fk_user = ' . ((int) $userId);
    if (kafoPcUserRightsHasEntityColumn($db)) {
        $sql .= ' AND ur.entity IN (0, ' . ((int) $entityId) . ')';
    }
    $sql .= ' AND ur.fk_id IN (' . implode(',', array_map('intval', $availableIds)) . ')';

    $resql = $db->query($sql);
    if (!$resql) {
        return $map;
    }

    while ($obj = $db->fetch_object($resql)) {
        $map[(int) $obj->fk_id] = 1;
    }

    return $map;
}

function kafoPcGetUserGroupRights($db, $userId, array $availableIds)
{
    $map = array();
    if ($userId <= 0 || empty($availableIds)) {
        return $map;
    }

    $sql = 'SELECT DISTINCT ugr.fk_id';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'usergroup_rights as ugr';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'usergroup_user as ugu ON ugu.fk_usergroup = ugr.fk_usergroup';
    $sql .= ' WHERE ugu.fk_user = ' . ((int) $userId);
    $sql .= ' AND ugr.fk_id IN (' . implode(',', array_map('intval', $availableIds)) . ')';

    $resql = $db->query($sql);
    if (!$resql) {
        return $map;
    }

    while ($obj = $db->fetch_object($resql)) {
        $map[(int) $obj->fk_id] = 1;
    }

    return $map;
}

function kafoPcGetSaasRoleCodesByUser($db, $entityId, $userId)
{
    $roles = array();
    if ($userId <= 0) {
        return $roles;
    }

    $sql = 'SELECT role_code';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'saas_user_roles';
    $sql .= ' WHERE entity_id = ' . ((int) $entityId) . ' AND fk_user = ' . ((int) $userId);
    $sql .= ' ORDER BY role_code ASC';

    $resql = $db->query($sql);
    if (!$resql) {
        return $roles;
    }

    while ($obj = $db->fetch_object($resql)) {
        $roles[] = (string) $obj->role_code;
    }

    return $roles;
}

function kafoPcGetLastPermissionChangeDate($db, $userId)
{
    if ($userId <= 0) {
        return '';
    }

    $sql = 'SELECT COALESCE(MAX(datec), MAX(date_created)) as lastdate';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'saas_audit_log';
    $sql .= ' WHERE COALESCE(fk_user_target, fk_user_actor, fk_user) = ' . ((int) $userId);
    $sql .= " AND COALESCE(action_type, action_code) IN ('permission_add', 'permission_remove', 'role_add', 'role_remove')";

    $resql = $db->query($sql);
    if (!$resql) {
        return '';
    }

    $obj = $db->fetch_object($resql);
    if (!is_object($obj) || empty($obj->lastdate)) {
        return '';
    }

    return (string) $obj->lastdate;
}

function kafoPcGetUserDisplayName($u)
{
    $fullName = trim((string) $u->firstname . ' ' . (string) $u->lastname);
    if ($fullName === '') {
        return (string) $u->login;
    }

    return $fullName;
}

function kafoPcCategorizeRight(array $right)
{
    $code = strtolower((string) $right['code']);
    $module = strtolower((string) $right['module_raw']);

    if (strpos($code, 'feature') !== false || strpos($module, 'feature') !== false) {
        return 'features';
    }
    if (
        strpos($code, 'role') !== false
        || strpos($code, 'group') !== false
        || strpos($module, 'group') !== false
        || strpos($module, 'role') !== false
    ) {
        return 'roles';
    }
    if ($module !== '' && $module !== 'core') {
        return 'modules';
    }

    return 'special';
}

$users = kafoPcGetSelectableUsers($db, $conf->entity);
if ($selectedUserId <= 0 && !empty($users)) {
    $userIds = array_keys($users);
    $selectedUserId = (int) reset($userIds);
}

if ($selectedUserId > 0 && !isset($users[$selectedUserId])) {
    $selectedUserId = 0;
    $error = $langs->trans('ErrorBadValueForParameter', 'userid');
}

list($rights, $moduleLabels) = kafoPcGetNativeRights($db, $conf->entity);
$allRightIds = array_keys($rights);

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenOk = true;
    if (function_exists('checkToken')) {
        $tokenOk = checkToken();
    } elseif (function_exists('newToken')) {
        $postedToken = GETPOST('token', 'alphanohtml');
        $sessionToken = isset($_SESSION['newtoken']) ? $_SESSION['newtoken'] : '';
        $tokenOk = (!empty($postedToken) && !empty($sessionToken) && hash_equals((string) $sessionToken, (string) $postedToken));
    }
    if (!$tokenOk) {
        accessforbidden('Invalid CSRF token');
    }

    $postedUserId = GETPOST('fk_user', 'int');
    if ($postedUserId <= 0 || !isset($users[$postedUserId])) {
        $error = $langs->trans('ErrorBadValueForParameter', 'fk_user');
        $selectedUserId = 0;
    } else {
        $submitted = array();
        $serializedRights = GETPOST('rights_serialized', 'alphanohtml');
        if ($serializedRights !== '') {
            foreach (explode(',', $serializedRights) as $rid) {
                $rid = (int) trim($rid);
                if ($rid > 0) {
                    $submitted[$rid] = 1;
                }
            }
        }

        $targetDirectMap = array();
        foreach ($submitted as $rightId => $isChecked) {
            $rightId = (int) $rightId;
            if ($rightId > 0 && isset($rights[$rightId]) && !empty($isChecked)) {
                $targetDirectMap[$rightId] = 1;
            }
        }

        $currentDirectMap = kafoPcGetUserDirectRights($db, $postedUserId, $conf->entity, $allRightIds);
        $toAdd = array();
        $toDelete = array();

        foreach ($targetDirectMap as $rightId => $enabled) {
            if (empty($currentDirectMap[$rightId])) {
                $toAdd[] = (int) $rightId;
            }
        }
        foreach ($currentDirectMap as $rightId => $enabled) {
            if (empty($targetDirectMap[$rightId])) {
                $toDelete[] = (int) $rightId;
            }
        }

        $ok = true;
        $db->begin();

        foreach ($toAdd as $rightId) {
            $sql = 'INSERT INTO ' . MAIN_DB_PREFIX . 'user_rights (';
            if (kafoPcUserRightsHasEntityColumn($db)) {
                $sql .= 'entity, ';
            }
            $sql .= 'fk_user, fk_id)';
            $sql .= ' SELECT ';
            if (kafoPcUserRightsHasEntityColumn($db)) {
                $sql .= ((int) $conf->entity) . ', ';
            }
            $sql .= ((int) $postedUserId) . ', ' . ((int) $rightId);
            $sql .= ' FROM DUAL WHERE NOT EXISTS (';
            $sql .= 'SELECT 1 FROM ' . MAIN_DB_PREFIX . 'user_rights';
            $sql .= ' WHERE fk_user = ' . ((int) $postedUserId) . ' AND fk_id = ' . ((int) $rightId);
            if (kafoPcUserRightsHasEntityColumn($db)) {
                $sql .= ' AND entity IN (0, ' . ((int) $conf->entity) . ')';
            }
            $sql .= ')';

            if (!$db->query($sql)) {
                $ok = false;
                break;
            }
        }

        if ($ok && !empty($toDelete)) {
            $sql = 'DELETE FROM ' . MAIN_DB_PREFIX . 'user_rights';
            $sql .= ' WHERE fk_user = ' . ((int) $postedUserId);
            if (kafoPcUserRightsHasEntityColumn($db)) {
                $sql .= ' AND entity IN (0, ' . ((int) $conf->entity) . ')';
            }
            $sql .= ' AND fk_id IN (' . implode(',', array_map('intval', $toDelete)) . ')';
            if (!$db->query($sql)) {
                $ok = false;
            }
        }

        if ($ok) {
            $db->commit();

            $actorUserId = (is_object($user) && !empty($user->id) ? (int) $user->id : 0);
            foreach ($toAdd as $rightId) {
                if (!isset($rights[$rightId])) {
                    continue;
                }
                $rightCode = $rights[$rightId]['code'];
                saascoreAuditLogAction(
                    $db,
                    $actorUserId,
                    $postedUserId,
                    'permission_add',
                    'user',
                    $rightCode,
                    null,
                    '1',
                    'Direct native permission granted to user',
                    array('right_id' => $rightId, 'right_code' => $rightCode)
                );
            }
            foreach ($toDelete as $rightId) {
                if (!isset($rights[$rightId])) {
                    continue;
                }
                $rightCode = $rights[$rightId]['code'];
                saascoreAuditLogAction(
                    $db,
                    $actorUserId,
                    $postedUserId,
                    'permission_remove',
                    'user',
                    $rightCode,
                    '1',
                    null,
                    'Direct native permission removed from user',
                    array('right_id' => $rightId, 'right_code' => $rightCode)
                );
            }

            $location = $_SERVER['PHP_SELF'] . '?userid=' . ((int) $postedUserId) . '&saved=1';
            header('Location: ' . $location);
            exit;
        }

        $db->rollback();
        $error = $db->lasterror();
        setEventMessages($langs->trans('ErrorFailedToSave'), array($error), 'errors');
        $selectedUserId = $postedUserId;
    }
}

$directRightsMap = array();
$groupRightsMap = array();
$userSummary = array(
    'name' => '',
    'login' => '',
    'roles_count' => 0,
    'enabled_count' => 0,
    'direct_count' => 0,
    'inherited_count' => 0,
    'last_modified' => '',
);

if ($selectedUserId > 0 && isset($users[$selectedUserId])) {
    $directRightsMap = kafoPcGetUserDirectRights($db, $selectedUserId, $conf->entity, $allRightIds);
    $groupRightsMap = kafoPcGetUserGroupRights($db, $selectedUserId, $allRightIds);

    $roleCodes = kafoPcGetSaasRoleCodesByUser($db, $conf->entity, $selectedUserId);

    $enabledUnion = $directRightsMap;
    foreach ($groupRightsMap as $rid => $v) {
        $enabledUnion[(int) $rid] = 1;
    }

    $inheritedOnly = 0;
    foreach ($groupRightsMap as $rid => $v) {
        if (empty($directRightsMap[$rid])) {
            $inheritedOnly++;
        }
    }

    $summaryUser = $users[$selectedUserId];
    $userSummary['name'] = kafoPcGetUserDisplayName($summaryUser);
    $userSummary['login'] = (string) $summaryUser->login;
    $userSummary['roles_count'] = count($roleCodes);
    $userSummary['enabled_count'] = count($enabledUnion);
    $userSummary['direct_count'] = count($directRightsMap);
    $userSummary['inherited_count'] = $inheritedOnly;
    $userSummary['last_modified'] = kafoPcGetLastPermissionChangeDate($db, $selectedUserId);
}

$categoryLabels = array(
    'modules' => 'Modules',
    'roles' => 'Roles',
    'features' => 'Features',
    'special' => 'Special access',
);

$rightsByCategory = array(
    'modules' => array(),
    'roles' => array(),
    'features' => array(),
    'special' => array(),
);

foreach ($rights as $right) {
    $category = kafoPcCategorizeRight($right);
    if (!isset($rightsByCategory[$category])) {
        $category = 'special';
    }
    $rightsByCategory[$category][] = $right;
}

$pageSubtitle = $langs->trans('PermissionsControl');
if ($selectedUserId > 0 && isset($users[$selectedUserId])) {
    $pageSubtitle = 'Permissions for user: ' . $userSummary['name'] . ' (' . $userSummary['login'] . ')';
}

llxHeader('', $langs->trans('PermissionsControl'));
print load_fiche_titre($pageSubtitle, '', 'title_setup');
print dol_get_fiche_head(saascoreAdminPrepareHead(), 'permissionscontrol', 'kafo-ERP-Control', -1, 'generic');

if ($saved === 1) {
    setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
}
if ($error !== '') {
    setEventMessages($error, null, 'errors');
}

print '<form method="GET" action="' . kafoPcEscape($_SERVER['PHP_SELF']) . '" id="kafo-user-selector-form">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">' . $langs->trans('User') . '</th></tr>';
print '<tr class="oddeven"><td class="titlefield">' . $langs->trans('User') . '</td><td>';

$userOptions = array();
foreach ($users as $id => $u) {
    $label = $u->login;
    if (!empty($u->firstname) || !empty($u->lastname)) {
        $label .= ' - ' . trim($u->firstname . ' ' . $u->lastname);
    }
    if (!empty($u->admin)) {
        $label .= ' (' . $langs->trans('Administrator') . ')';
    }
    if ((int) $u->statut === 0) {
        $label .= ' [' . $langs->trans('Disabled') . ']';
    }
    $userOptions[(int) $id] = $label;
}

print $form->selectarray('userid', $userOptions, $selectedUserId, 1, 0, 0, '', 0, 0, 0, 'onchange="this.form.submit()"', 'minwidth300');
print ' <input type="submit" class="button" value="' . $langs->trans('Refresh') . '">';
print '</td></tr>';
print '</table>';
print '</form>';

if (empty($users)) {
    print '<div class="opacitymedium" style="margin-top:10px;">' . kafoPcEscape($langs->trans('NoRecordFound')) . '</div>';
    print dol_get_fiche_end();
    llxFooter();
    $db->close();
    exit;
}

if ($selectedUserId <= 0 || !isset($users[$selectedUserId])) {
    print '<div class="opacitymedium" style="margin-top:10px;">No valid user selected.</div>';
    print dol_get_fiche_end();
    llxFooter();
    $db->close();
    exit;
}

print '<br>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">User Summary</th></tr>';
print '<tr class="oddeven"><td class="titlefield">Name</td><td>' . kafoPcEscape($userSummary['name']) . '</td></tr>';
print '<tr class="oddeven"><td class="titlefield">Login</td><td>' . kafoPcEscape($userSummary['login']) . '</td></tr>';
print '<tr class="oddeven"><td class="titlefield">Assigned roles count</td><td>' . ((int) $userSummary['roles_count']) . '</td></tr>';
print '<tr class="oddeven"><td class="titlefield">Enabled permissions count</td><td>' . ((int) $userSummary['enabled_count']) . '</td></tr>';
print '<tr class="oddeven"><td class="titlefield">Direct permissions count</td><td>' . ((int) $userSummary['direct_count']) . '</td></tr>';
print '<tr class="oddeven"><td class="titlefield">Inherited permissions count</td><td>' . ((int) $userSummary['inherited_count']) . '</td></tr>';
if ($userSummary['last_modified'] !== '') {
    print '<tr class="oddeven"><td class="titlefield">Last modified</td><td>' . kafoPcEscape(dol_print_date(dol_stringtotime($userSummary['last_modified']), 'dayhour')) . '</td></tr>';
} else {
    print '<tr class="oddeven"><td class="titlefield">Last modified</td><td>-</td></tr>';
}
print '</table>';

print '<br>';

print '<form method="POST" action="' . kafoPcEscape($_SERVER['PHP_SELF']) . '?userid=' . ((int) $selectedUserId) . '" id="kafo-native-rights-form">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="save">';
print '<input type="hidden" name="fk_user" value="' . ((int) $selectedUserId) . '">';
print '<input type="hidden" name="rights_serialized" id="rights_serialized" value="">';

print '<div class="tabsAction" style="margin:8px 0">';
print '<input type="submit" class="button button-save" value="' . kafoPcEscape($langs->trans('Save')) . '">';
print '</div>';

print '<div class="inline-block" style="margin-right:8px">';
print '<input type="text" id="right-search" class="flat minwidth300" placeholder="' . kafoPcEscape($langs->trans('Search')) . '">';
print '</div>';

print '<div class="inline-block" style="margin-right:8px">';
print '<select id="right-module-filter" class="flat">';
print '<option value="">' . kafoPcEscape($langs->trans('All')) . '</option>';
foreach ($moduleLabels as $moduleKey => $moduleLabel) {
    print '<option value="' . kafoPcEscape($moduleKey) . '">' . kafoPcEscape($moduleLabel) . '</option>';
}
print '</select>';
print '</div>';

print '<div class="inline-block" style="margin-right:8px">';
print '<select id="right-status-filter" class="flat">';
print '<option value="all">' . kafoPcEscape($langs->trans('All')) . '</option>';
print '<option value="enabled">' . kafoPcEscape($langs->trans('Enabled')) . '</option>';
print '<option value="disabled">' . kafoPcEscape($langs->trans('Disabled')) . '</option>';
print '</select>';
print '</div>';

print '<div class="inline-block" style="margin-right:8px">';
print '<select id="right-category-filter" class="flat">';
print '<option value="all">All categories</option>';
foreach ($categoryLabels as $categoryKey => $categoryLabel) {
    print '<option value="' . kafoPcEscape($categoryKey) . '">' . kafoPcEscape($categoryLabel) . '</option>';
}
print '</select>';
print '</div>';

print '<div class="inline-block" style="margin-right:8px">';
print '<button type="button" class="button" id="check-visible">' . $langs->trans('SelectAll') . '</button>';
print '</div>';
print '<div class="inline-block" style="margin-right:8px">';
print '<button type="button" class="button" id="uncheck-visible">' . $langs->trans('UnselectAll') . '</button>';
print '</div>';
print '<div class="inline-block" style="margin-right:8px">';
print '<button type="button" class="button" id="reset-filters">' . $langs->trans('Reset') . '</button>';
print '</div>';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent" id="native-rights-table">';
print '<tr class="liste_titre">';
print '<th>Category</th>';
print '<th>' . $langs->trans('Module') . '</th>';
print '<th>' . $langs->trans('Code') . '</th>';
print '<th>' . $langs->trans('Label') . '</th>';
print '<th>' . $langs->trans('Status') . '</th>';
print '<th>' . $langs->trans('Grant') . '</th>';
print '</tr>';

$hasRights = false;
foreach (array('modules', 'roles', 'features', 'special') as $category) {
    if (empty($rightsByCategory[$category])) {
        continue;
    }

    $hasRights = true;
    $categoryLabel = isset($categoryLabels[$category]) ? $categoryLabels[$category] : $category;
    print '<tr class="liste_titre category-separator" data-category="' . kafoPcEscape($category) . '"><td colspan="6"><strong>' . kafoPcEscape($categoryLabel) . '</strong></td></tr>';

    $lastModule = null;
    foreach ($rightsByCategory[$category] as $right) {
        $moduleKey = $right['module'];
        if ($lastModule !== $moduleKey) {
            $lastModule = $moduleKey;
            print '<tr class="liste_titre module-separator" data-category="' . kafoPcEscape($category) . '" data-module="' . kafoPcEscape($moduleKey) . '">';
            print '<td colspan="6">';
            print '<strong>' . kafoPcEscape($moduleLabels[$moduleKey]) . '</strong>';
            print ' <button type="button" class="button button-small module-check" data-module="' . kafoPcEscape($moduleKey) . '">' . $langs->trans('SelectAll') . '</button>';
            print ' <button type="button" class="button button-small module-uncheck" data-module="' . kafoPcEscape($moduleKey) . '">' . $langs->trans('UnselectAll') . '</button>';
            print '</td></tr>';
        }

        $rid = (int) $right['id'];
        $isDirect = !empty($directRightsMap[$rid]);
        $isFromGroup = !empty($groupRightsMap[$rid]);
        $isEnabled = ($isDirect || $isFromGroup);
        $isInheritedOnly = (!$isDirect && $isFromGroup);

        $statusLabel = $langs->trans('Disabled');
        if ($isDirect) {
            $statusLabel = $langs->trans('Enabled') . ' (' . $langs->trans('ByUser') . ')';
        } elseif ($isFromGroup) {
            $statusLabel = $langs->trans('Enabled') . ' (' . $langs->trans('ByGroup') . ' / Read only)';
        }

        $searchBlob = strtolower($category . ' ' . $right['module'] . ' ' . $right['code'] . ' ' . $right['label'] . ' ' . $rid);

        print '<tr class="oddeven right-row" data-category="' . kafoPcEscape($category) . '" data-module="' . kafoPcEscape($moduleKey) . '" data-enabled="' . ($isEnabled ? '1' : '0') . '" data-search="' . kafoPcEscape($searchBlob) . '">';
        print '<td>' . kafoPcEscape($categoryLabel) . '</td>';
        print '<td>' . kafoPcEscape($moduleLabels[$moduleKey]) . '</td>';
        print '<td>' . kafoPcEscape($right['code']) . '</td>';
        print '<td>' . kafoPcEscape($right['label']) . '</td>';
        print '<td>' . kafoPcEscape($statusLabel) . '</td>';
        print '<td><input type="checkbox" class="right-checkbox" name="rights[' . $rid . ']" value="' . $rid . '" ' . ($isDirect ? 'checked' : '') . ' ' . ($isInheritedOnly ? 'disabled' : '') . '></td>';
        print '</tr>';
    }
}

if (!$hasRights) {
    print '<tr class="oddeven"><td colspan="6">' . kafoPcEscape($langs->trans('NoRecordFound')) . '</td></tr>';
}

print '</table>';
print '</div>';

print '<div class="tabsAction" style="margin-top:12px">';
print '<input type="submit" class="button button-save" value="' . kafoPcEscape($langs->trans('Save')) . '">';
print '</div>';

print '</form>';

print '<script>
(function () {
  var searchInput = document.getElementById("right-search");
  var moduleFilter = document.getElementById("right-module-filter");
  var statusFilter = document.getElementById("right-status-filter");
  var categoryFilter = document.getElementById("right-category-filter");
  var rows = document.querySelectorAll("#native-rights-table .right-row");
  var moduleSeparators = document.querySelectorAll("#native-rights-table .module-separator");
  var categorySeparators = document.querySelectorAll("#native-rights-table .category-separator");

  function rowIsVisible(row) {
    return row.style.display !== "none";
  }

  function applyFilters() {
    var search = (searchInput.value || "").toLowerCase();
    var moduleValue = moduleFilter.value || "";
    var statusValue = statusFilter.value || "all";
    var categoryValue = categoryFilter.value || "all";
    var visibleByModule = {};
    var visibleByCategory = {};

    for (var i = 0; i < rows.length; i++) {
      var row = rows[i];
      var rowSearch = row.getAttribute("data-search") || "";
      var rowModule = row.getAttribute("data-module") || "";
      var rowCategory = row.getAttribute("data-category") || "";
      var rowEnabled = row.getAttribute("data-enabled") || "0";

      var matchSearch = !search || rowSearch.indexOf(search) !== -1;
      var matchModule = !moduleValue || rowModule === moduleValue;
      var matchCategory = (categoryValue === "all" || rowCategory === categoryValue);
      var matchStatus = true;

      if (statusValue === "enabled") {
        matchStatus = rowEnabled === "1";
      } else if (statusValue === "disabled") {
        matchStatus = rowEnabled === "0";
      }

      var show = matchSearch && matchModule && matchStatus && matchCategory;
      row.style.display = show ? "" : "none";
      if (show) {
        visibleByModule[rowCategory + "|" + rowModule] = true;
        visibleByCategory[rowCategory] = true;
      }
    }

    for (var j = 0; j < moduleSeparators.length; j++) {
      var separator = moduleSeparators[j];
      var moduleName = separator.getAttribute("data-module") || "";
      var categoryName = separator.getAttribute("data-category") || "";
      separator.style.display = visibleByModule[categoryName + "|" + moduleName] ? "" : "none";
    }

    for (var k = 0; k < categorySeparators.length; k++) {
      var categorySeparator = categorySeparators[k];
      var currentCategory = categorySeparator.getAttribute("data-category") || "";
      categorySeparator.style.display = visibleByCategory[currentCategory] ? "" : "none";
    }
  }

  function toggleVisible(state) {
    for (var i = 0; i < rows.length; i++) {
      var row = rows[i];
      if (!rowIsVisible(row)) {
        continue;
      }
      var cb = row.querySelector(".right-checkbox");
      if (cb && !cb.disabled) {
        cb.checked = state;
      }
    }
  }

  function toggleModule(moduleName, state) {
    for (var i = 0; i < rows.length; i++) {
      var row = rows[i];
      if ((row.getAttribute("data-module") || "") !== moduleName) {
        continue;
      }
      if (!rowIsVisible(row)) {
        continue;
      }
      var cb = row.querySelector(".right-checkbox");
      if (cb && !cb.disabled) {
        cb.checked = state;
      }
    }
  }

  document.getElementById("check-visible").addEventListener("click", function () { toggleVisible(true); });
  document.getElementById("uncheck-visible").addEventListener("click", function () { toggleVisible(false); });
  document.getElementById("reset-filters").addEventListener("click", function () {
    searchInput.value = "";
    moduleFilter.value = "";
    statusFilter.value = "all";
    categoryFilter.value = "all";
    applyFilters();
  });

  var moduleCheck = document.querySelectorAll(".module-check");
  for (var m = 0; m < moduleCheck.length; m++) {
    moduleCheck[m].addEventListener("click", function () {
      toggleModule(this.getAttribute("data-module"), true);
    });
  }

  var moduleUncheck = document.querySelectorAll(".module-uncheck");
  for (var n = 0; n < moduleUncheck.length; n++) {
    moduleUncheck[n].addEventListener("click", function () {
      toggleModule(this.getAttribute("data-module"), false);
    });
  }

  searchInput.addEventListener("input", applyFilters);
  moduleFilter.addEventListener("change", applyFilters);
  statusFilter.addEventListener("change", applyFilters);
  categoryFilter.addEventListener("change", applyFilters);

  var form = document.getElementById("kafo-native-rights-form");
  if (form) {
    form.addEventListener("submit", function () {
      var ids = [];
      var checkboxes = form.querySelectorAll(".right-checkbox");
      for (var p = 0; p < checkboxes.length; p++) {
        var cb = checkboxes[p];
        if (cb.checked && !cb.disabled) {
          ids.push(cb.value || "");
        }
      }
      var target = document.getElementById("rights_serialized");
      if (target) {
        target.value = ids.filter(Boolean).join(",");
      }
    });
  }

  applyFilters();
})();
</script>';

print dol_get_fiche_end();
llxFooter();
$db->close();









