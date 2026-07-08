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
require_once dol_buildpath('/kafoerpcontrol/core/lib/saascore.lib.php', 0);
require_once dol_buildpath('/kafoerpcontrol/class/SaasRoleService.php', 0);

$langs->loadLangs(array('admin', 'users', 'other', 'kafoerpcontrol@kafoerpcontrol'));
saascoreRequireAdminRight('rolemanage');
saascoreSyncKnownIntegrations($db);

$form = new Form($db);
$service = new SaasRoleService($db);
$action = GETPOST('action', 'aZ09');
$entityId = (int) GETPOST('entity_id', 'int');
if ($entityId <= 0) {
    $entityId = (int) $conf->entity;
}
$selectedRoleCode = trim(GETPOST('role_code', 'alpha'));

function kafoRoleEscape($value)
{
    return dol_escape_htmltag((string) $value);
}

function kafoRoleCheckTokenOrFail()
{
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
}

function kafoRoleGetUsers($db, $entityId)
{
    $rows = array();
    $sql = 'SELECT rowid, login, firstname, lastname, admin, statut';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'user';
    $sql .= ' WHERE entity IN (0, ' . ((int) $entityId) . ')';
    $sql .= ' ORDER BY admin DESC, login ASC';
    $resql = $db->query($sql);
    while ($resql && ($obj = $db->fetch_object($resql))) {
        $rows[(int) $obj->rowid] = $obj;
    }
    return $rows;
}

function kafoRoleGetRoles($db, $entityId)
{
    $rows = array();
    $sql = 'SELECT rowid, entity_id, code, label, description, is_system';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'saas_roles';
    $sql .= ' WHERE entity_id = ' . ((int) $entityId);
    $sql .= ' ORDER BY is_system DESC, code ASC';
    $resql = $db->query($sql);
    while ($resql && ($obj = $db->fetch_object($resql))) {
        $rows[(string) $obj->code] = $obj;
    }
    return $rows;
}

function kafoRoleBuildNativePermissionCode($module, $perms, $subperms, $rightId)
{
    $parts = array();
    $module = trim((string) $module);
    $perms = trim((string) $perms);
    $subperms = trim((string) $subperms);
    if ($module !== '') $parts[] = $module;
    if ($perms !== '') $parts[] = $perms;
    if ($subperms !== '') $parts[] = $subperms;
    if (empty($parts)) return 'native.right_' . ((int) $rightId);
    return 'native.' . implode('.', $parts);
}

function kafoRoleGetAvailablePermissions($db, $entityId)
{
    $rows = array();

    $sql = 'SELECT code, label, module_code, description';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'saas_permissions';
    $sql .= ' ORDER BY module_code ASC, code ASC';
    $resql = $db->query($sql);
    while ($resql && ($obj = $db->fetch_object($resql))) {
        $code = trim((string) $obj->code);
        if ($code === '') continue;
        $rows[$code] = array(
            'code' => $code,
            'module' => trim((string) $obj->module_code),
            'label' => trim((string) $obj->label) !== '' ? trim((string) $obj->label) : $code,
            'description' => trim((string) $obj->description),
            'source' => 'saas',
        );
    }

    $sql = 'SELECT id, module, perms, subperms, libelle';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'rights_def';
    $sql .= ' WHERE entity IN (0, ' . ((int) $entityId) . ')';
    $sql .= ' ORDER BY module ASC, perms ASC, subperms ASC, id ASC';
    $resql = $db->query($sql);
    while ($resql && ($obj = $db->fetch_object($resql))) {
        $code = kafoRoleBuildNativePermissionCode($obj->module, $obj->perms, $obj->subperms, $obj->id);
        if (isset($rows[$code])) continue;
        $rows[$code] = array(
            'code' => $code,
            'module' => trim((string) $obj->module),
            'label' => trim((string) $obj->libelle) !== '' ? trim((string) $obj->libelle) : $code,
            'description' => 'Dolibarr native right',
            'source' => 'native',
        );
    }

    ksort($rows);
    return $rows;
}

function kafoRoleGetAssignedPermissions($db, $entityId, $roleCode)
{
    $map = array();
    if ($roleCode === '') return $map;
    $sql = 'SELECT permission_code, allowed';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'saas_role_permissions';
    $sql .= ' WHERE entity_id = ' . ((int) $entityId);
    $sql .= " AND role_code = '" . $db->escape($roleCode) . "'";
    $resql = $db->query($sql);
    while ($resql && ($obj = $db->fetch_object($resql))) {
        $map[(string) $obj->permission_code] = ((int) $obj->allowed === 1 ? 1 : 0);
    }
    return $map;
}

function kafoRoleGetAssignedUsers($db, $entityId, $roleCode)
{
    $map = array();
    if ($roleCode === '') return $map;
    $sql = 'SELECT fk_user';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'saas_user_roles';
    $sql .= ' WHERE entity_id = ' . ((int) $entityId);
    $sql .= " AND role_code = '" . $db->escape($roleCode) . "'";
    $resql = $db->query($sql);
    while ($resql && ($obj = $db->fetch_object($resql))) {
        $map[(int) $obj->fk_user] = 1;
    }
    return $map;
}

$roles = kafoRoleGetRoles($db, $entityId);
$users = kafoRoleGetUsers($db, $entityId);
$permissions = kafoRoleGetAvailablePermissions($db, $entityId);

if ($selectedRoleCode === '' && !empty($roles)) {
    $tmp = array_keys($roles);
    $selectedRoleCode = (string) reset($tmp);
}

if ($selectedRoleCode !== '' && !isset($roles[$selectedRoleCode])) {
    $selectedRoleCode = '';
    setEventMessages('Selected role was not found.', null, 'errors');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    kafoRoleCheckTokenOrFail();

    if ($action === 'create_role') {
        $code = trim(GETPOST('new_role_code', 'alpha'));
        $label = trim(GETPOST('new_role_label', 'restricthtml'));
        $description = trim(GETPOST('new_role_description', 'restricthtml'));
        $isSystem = GETPOST('new_role_is_system', 'int');
        if ($code === '' || $label === '') {
            setEventMessages('Role code and label are required.', null, 'errors');
        } else {
            if ($service->createRole($entityId, $code, $label, $description, $isSystem)) {
                setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
                $selectedRoleCode = $code;
            } else {
                setEventMessages($db->lasterror(), null, 'errors');
            }
        }
    } elseif ($action === 'update_role') {
        $roleCode = trim(GETPOST('role_code', 'alpha'));
        if (!isset($roles[$roleCode])) {
            setEventMessages('Role not found.', null, 'errors');
        } else {
            $label = trim(GETPOST('role_label', 'restricthtml'));
            $description = trim(GETPOST('role_description', 'restricthtml'));
            $isSystem = GETPOST('role_is_system', 'int');
            if ($label === '') {
                setEventMessages('Role label is required.', null, 'errors');
            } else {
                $sql = 'UPDATE ' . MAIN_DB_PREFIX . 'saas_roles';
                $sql .= " SET label = '" . $db->escape($label) . "', description = '" . $db->escape($description) . "', is_system = " . ((int) $isSystem);
                $sql .= " WHERE entity_id = " . ((int) $entityId) . " AND code = '" . $db->escape($roleCode) . "'";
                if ($db->query($sql)) {
                    setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
                    $selectedRoleCode = $roleCode;
                } else {
                    setEventMessages($db->lasterror(), null, 'errors');
                }
            }
        }
    } elseif ($action === 'delete_role') {
        $roleCode = trim(GETPOST('role_code', 'alpha'));
        if (!isset($roles[$roleCode])) {
            setEventMessages('Role not found.', null, 'errors');
        } else {
            $currentUsers = kafoRoleGetAssignedUsers($db, $entityId, $roleCode);
            $db->begin();
            $ok = true;
            $sqls = array(
                'DELETE FROM ' . MAIN_DB_PREFIX . 'saas_user_roles WHERE entity_id = ' . ((int) $entityId) . " AND role_code = '" . $db->escape($roleCode) . "'",
                'DELETE FROM ' . MAIN_DB_PREFIX . 'saas_role_permissions WHERE entity_id = ' . ((int) $entityId) . " AND role_code = '" . $db->escape($roleCode) . "'",
                'DELETE FROM ' . MAIN_DB_PREFIX . 'saas_roles WHERE entity_id = ' . ((int) $entityId) . " AND code = '" . $db->escape($roleCode) . "'",
            );
            foreach ($sqls as $sql) {
                if (!$db->query($sql)) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $db->commit();
                if (!$syncService->syncUsers($entityId, array_keys($currentUsers))) {
                    setEventMessages($db->lasterror(), null, 'errors');
                } else {
                    setEventMessages($langs->trans('SetupSaved') . ' - native rights synchronized.', null, 'mesgs');
                }
                $selectedRoleCode = '';
            } else {
                $db->rollback();
                setEventMessages($db->lasterror(), null, 'errors');
            }
        }
    } elseif ($action === 'save_role_permissions') {
        $roleCode = trim(GETPOST('role_code', 'alpha'));
        if (!isset($roles[$roleCode])) {
            setEventMessages('Role not found.', null, 'errors');
        } else {
            $submitted = GETPOST('role_permissions', 'array');
            if (!is_array($submitted)) $submitted = array();
            $target = array();
            foreach ($submitted as $code => $flag) {
                $code = trim((string) $code);
                if ($code !== '' && isset($permissions[$code]) && !empty($flag)) {
                    $target[$code] = 1;
                }
            }
            $current = kafoRoleGetAssignedPermissions($db, $entityId, $roleCode);
            $toAdd = array();
            $toDelete = array();
            foreach ($target as $code => $v) if (!isset($current[$code]) || (int)$current[$code] !== 1) $toAdd[] = $code;
            foreach ($current as $code => $allowed) if (!isset($target[$code])) $toDelete[] = $code;

            $db->begin();
            $ok = true;
            foreach ($toAdd as $code) {
                if (!$service->setRolePermission($entityId, $roleCode, $code, 1)) {
                    $ok = false; break;
                }
            }
            if ($ok && !empty($toDelete)) {
                $sql = 'DELETE FROM ' . MAIN_DB_PREFIX . 'saas_role_permissions';
                $sql .= ' WHERE entity_id = ' . ((int) $entityId);
                $sql .= " AND role_code = '" . $db->escape($roleCode) . "'";
                $escaped = array();
                foreach ($toDelete as $code) $escaped[] = "'" . $db->escape($code) . "'";
                $sql .= ' AND permission_code IN (' . implode(',', $escaped) . ')';
                if (!$db->query($sql)) $ok = false;
            }
            if ($ok) {
                $db->commit();
                $affectedUsers = array_keys(kafoRoleGetAssignedUsers($db, $entityId, $roleCode));
                if (!$syncService->syncUsers($entityId, $affectedUsers)) {
                    setEventMessages($db->lasterror(), null, 'errors');
                } else {
                    setEventMessages($langs->trans('SetupSaved') . ' - native rights synchronized.', null, 'mesgs');
                }
                $selectedRoleCode = $roleCode;
            } else {
                $db->rollback();
                setEventMessages($db->lasterror(), null, 'errors');
            }
        }
    } elseif ($action === 'save_role_users') {
        $roleCode = trim(GETPOST('role_code', 'alpha'));
        if (!isset($roles[$roleCode])) {
            setEventMessages('Role not found.', null, 'errors');
        } else {
            $submitted = GETPOST('role_users', 'array');
            if (!is_array($submitted)) $submitted = array();
            $target = array();
            foreach ($submitted as $userId => $flag) {
                $userId = (int) $userId;
                if ($userId > 0 && isset($users[$userId]) && !empty($flag)) {
                    $target[$userId] = 1;
                }
            }
            $current = kafoRoleGetAssignedUsers($db, $entityId, $roleCode);
            $toAdd = array();
            $toDelete = array();
            foreach ($target as $userId => $v) if (empty($current[$userId])) $toAdd[] = $userId;
            foreach ($current as $userId => $v) if (empty($target[$userId])) $toDelete[] = (int) $userId;

            $db->begin();
            $ok = true;
            foreach ($toAdd as $userId) {
                if (!$service->assignRoleToUser($entityId, $userId, $roleCode)) {
                    $ok = false; break;
                }
            }
            if ($ok && !empty($toDelete)) {
                $sql = 'DELETE FROM ' . MAIN_DB_PREFIX . 'saas_user_roles';
                $sql .= ' WHERE entity_id = ' . ((int) $entityId);
                $sql .= " AND role_code = '" . $db->escape($roleCode) . "'";
                $sql .= ' AND fk_user IN (' . implode(',', array_map('intval', $toDelete)) . ')';
                if (!$db->query($sql)) $ok = false;
            }
            if ($ok) {
                $db->commit();
                $affectedUsers = array_unique(array_merge(array_map('intval', array_keys($current)), array_map('intval', array_keys($target))));
                if (!$syncService->syncUsers($entityId, $affectedUsers)) {
                    setEventMessages($db->lasterror(), null, 'errors');
                } else {
                    setEventMessages($langs->trans('SetupSaved') . ' - native rights synchronized.', null, 'mesgs');
                }
                $selectedRoleCode = $roleCode;
            } else {
                $db->rollback();
                setEventMessages($db->lasterror(), null, 'errors');
            }
        }
    }

    $roles = kafoRoleGetRoles($db, $entityId);
    $users = kafoRoleGetUsers($db, $entityId);
    $permissions = kafoRoleGetAvailablePermissions($db, $entityId);
    if ($selectedRoleCode !== '' && !isset($roles[$selectedRoleCode])) {
        $selectedRoleCode = '';
    }
    if ($selectedRoleCode === '' && !empty($roles)) {
        $tmp = array_keys($roles);
        $selectedRoleCode = (string) reset($tmp);
    }
}

$assignedPermissions = kafoRoleGetAssignedPermissions($db, $entityId, $selectedRoleCode);
$assignedUsers = kafoRoleGetAssignedUsers($db, $entityId, $selectedRoleCode);

llxHeader('', $langs->trans('RolesCatalog'));
print load_fiche_titre('Permissions Groups / Roles', '', 'title_setup');
print dol_get_fiche_head(saascoreAdminPrepareHead(), 'roles', 'kafo-ERP-Control', -1, 'generic');

print '<div class="fichecenter">';
print '<div class="info" style="margin-bottom:12px;">Users assigned to roles are synchronized to Dolibarr native user rights automatically. Direct native rights are replaced by the union of native permissions granted through roles.</div>';

print '<form method="GET" action="' . kafoRoleEscape($_SERVER['PHP_SELF']) . '" style="margin-bottom:12px;">';
print '<input type="hidden" name="entity_id" value="' . ((int) $entityId) . '">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">Select Role Group</th></tr>';
print '<tr class="oddeven"><td class="titlefield">Role</td><td>';
$roleOptions = array();
foreach ($roles as $code => $row) {
    $roleOptions[$code] = $row->code . ' - ' . $row->label;
}
print $form->selectarray('role_code', $roleOptions, $selectedRoleCode, 1, 0, 0, '', 0, 0, 0, '', 'minwidth300');
print ' <input type="submit" class="button" value="' . $langs->trans('Select') . '">';
print '</td></tr></table></form>';

print '<form method="POST" action="' . kafoRoleEscape($_SERVER['PHP_SELF']) . '" style="margin-bottom:16px;">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="create_role">';
print '<input type="hidden" name="entity_id" value="' . ((int) $entityId) . '">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="5">Create New Group</th></tr>';
print '<tr class="liste_titre"><th>Code</th><th>Label</th><th>Description</th><th>System</th><th>&nbsp;</th></tr>';
print '<tr class="oddeven">';
print '<td><input type="text" name="new_role_code" class="minwidth150"></td>';
print '<td><input type="text" name="new_role_label" class="minwidth150"></td>';
print '<td><input type="text" name="new_role_description" class="minwidth300"></td>';
print '<td><input type="checkbox" name="new_role_is_system" value="1"></td>';
print '<td><input type="submit" class="button button-add" value="Create"></td>';
print '</tr></table></form>';

if ($selectedRoleCode !== '' && isset($roles[$selectedRoleCode])) {
    $roleRow = $roles[$selectedRoleCode];

    print '<form method="POST" action="' . kafoRoleEscape($_SERVER['PHP_SELF']) . '" style="margin-bottom:16px;">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="update_role">';
    print '<input type="hidden" name="entity_id" value="' . ((int) $entityId) . '">';
    print '<input type="hidden" name="role_code" value="' . kafoRoleEscape($selectedRoleCode) . '">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre"><th colspan="4">Edit Group: ' . kafoRoleEscape($selectedRoleCode) . '</th></tr>';
    print '<tr class="liste_titre"><th>Code</th><th>Label</th><th>Description</th><th>System</th></tr>';
    print '<tr class="oddeven">';
    print '<td><strong>' . kafoRoleEscape($roleRow->code) . '</strong></td>';
    print '<td><input type="text" name="role_label" value="' . kafoRoleEscape($roleRow->label) . '" class="minwidth200"></td>';
    print '<td><input type="text" name="role_description" value="' . kafoRoleEscape($roleRow->description) . '" class="minwidth300"></td>';
    print '<td><input type="checkbox" name="role_is_system" value="1"' . ((int) $roleRow->is_system === 1 ? ' checked' : '') . '></td>';
    print '</tr>';
    print '<tr class="oddeven"><td colspan="4">';
    print '<input type="submit" class="button button-save" value="Save Group">';
    print '</td></tr></table></form>';

    print '<form method="POST" action="' . kafoRoleEscape($_SERVER['PHP_SELF']) . '" onsubmit="return confirm(\'Delete this group and all its assignments?\');" style="margin-bottom:16px;">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="delete_role">';
    print '<input type="hidden" name="entity_id" value="' . ((int) $entityId) . '">';
    print '<input type="hidden" name="role_code" value="' . kafoRoleEscape($selectedRoleCode) . '">';
    print '<input type="submit" class="button button-delete" value="Delete Group">';
    print '</form>';

    print '<form method="POST" action="' . kafoRoleEscape($_SERVER['PHP_SELF']) . '" style="margin-bottom:16px;">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="save_role_permissions">';
    print '<input type="hidden" name="entity_id" value="' . ((int) $entityId) . '">';
    print '<input type="hidden" name="role_code" value="' . kafoRoleEscape($selectedRoleCode) . '">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre"><th colspan="5">Group Permissions</th></tr>';
    print '<tr class="liste_titre"><th>Enable</th><th>Code</th><th>Label</th><th>Module</th><th>Source</th></tr>';
    foreach ($permissions as $code => $perm) {
        print '<tr class="oddeven">';
        print '<td style="width:60px;"><input type="checkbox" name="role_permissions[' . kafoRoleEscape($code) . ']" value="1"' . (!empty($assignedPermissions[$code]) ? ' checked' : '') . '></td>';
        print '<td>' . kafoRoleEscape($code) . '</td>';
        print '<td>' . kafoRoleEscape($perm['label']) . '</td>';
        print '<td>' . kafoRoleEscape($perm['module'] !== '' ? $perm['module'] : '-') . '</td>';
        print '<td>' . kafoRoleEscape($perm['source']) . '</td>';
        print '</tr>';
    }
    print '<tr class="oddeven"><td colspan="5"><input type="submit" class="button button-save" value="Save Permissions"></td></tr>';
    print '</table></form>';

    print '<form method="POST" action="' . kafoRoleEscape($_SERVER['PHP_SELF']) . '">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="save_role_users">';
    print '<input type="hidden" name="entity_id" value="' . ((int) $entityId) . '">';
    print '<input type="hidden" name="role_code" value="' . kafoRoleEscape($selectedRoleCode) . '">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre"><th colspan="4">Users in Group</th></tr>';
    print '<tr class="liste_titre"><th>Add</th><th>Login</th><th>Name</th><th>Status</th></tr>';
    foreach ($users as $userId => $u) {
        $fullName = trim($u->firstname . ' ' . $u->lastname);
        if ($fullName === '') $fullName = '-';
        $status = ((int) $u->statut > 0 ? 'Active' : 'Disabled');
        if (!empty($u->admin)) $status .= ' / Admin';
        print '<tr class="oddeven">';
        print '<td style="width:60px;"><input type="checkbox" name="role_users[' . ((int) $userId) . ']" value="1"' . (!empty($assignedUsers[$userId]) ? ' checked' : '') . '></td>';
        print '<td>' . kafoRoleEscape($u->login) . '</td>';
        print '<td>' . kafoRoleEscape($fullName) . '</td>';
        print '<td>' . kafoRoleEscape($status) . '</td>';
        print '</tr>';
    }
    print '<tr class="oddeven"><td colspan="4"><input type="submit" class="button button-save" value="Save Users"></td></tr>';
    print '</table></form>';
} else {
    print '<div class="opacitymedium">No role group found yet. Create one from the form above.</div>';
}

print '</div>';
print dol_get_fiche_end();
llxFooter();
$db->close();
