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
require_once dol_buildpath('/kafoerpcontrol/core/lib/saascore.lib.php', 0);

$langs->loadLangs(array('admin', 'kafoerpcontrol@kafoerpcontrol'));
saascoreRequireAdminRight('tenantmanage');
saascoreSyncKnownIntegrations($db);

$entityId = (int) GETPOST('entity_id', 'int');
if ($entityId <= 0) $entityId = (int) $conf->entity;

$selectedUserId = (int) GETPOST('fk_user', 'int');
$action = GETPOST('action', 'alpha');
$message = '';
$error = '';

function saas_h($v)
{
    return dol_escape_htmltag((string) $v);
}

function saas_post_array($key)
{
    if (!isset($_POST[$key]) || !is_array($_POST[$key])) return array();
    return $_POST[$key];
}

function saas_checked_keys(array $input)
{
    $out = array();
    foreach ($input as $k => $v) {
        if (!empty($v)) $out[] = (string) $k;
    }
    return array_values(array_unique($out));
}

function saas_set(array $codes)
{
    $set = array();
    foreach ($codes as $code) $set[(string) $code] = 1;
    return $set;
}

function saas_json_map(array $map)
{
    ksort($map);
    return json_encode($map);
}

function saas_ensure_tables($db)
{
    $sql1 = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."saas_user_permissions (
        rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
        entity_id INTEGER NOT NULL,
        fk_user INTEGER NOT NULL,
        permission_code VARCHAR(64) NOT NULL,
        allowed TINYINT NOT NULL DEFAULT 0,
        date_created DATETIME NOT NULL,
        tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_saas_user_permissions (entity_id, fk_user, permission_code),
        KEY idx_saas_user_permissions_entity (entity_id),
        KEY idx_saas_user_permissions_user (fk_user)
    ) ENGINE=innodb";

    $sql2 = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."saas_audit_log (
        rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
        entity_id INTEGER NOT NULL,
        fk_user INTEGER NULL,
        action_code VARCHAR(64) NOT NULL,
        target_type VARCHAR(64) NOT NULL,
        target_code VARCHAR(64) NULL,
        old_value TEXT NULL,
        new_value TEXT NULL,
        ip_address VARCHAR(64) NULL,
        date_created DATETIME NOT NULL,
        KEY idx_saas_audit_entity (entity_id),
        KEY idx_saas_audit_user (fk_user)
    ) ENGINE=innodb";

    return (bool) ($db->query($sql1) && $db->query($sql2));
}

function saas_audit($db, $entityId, $actorUserId, $actionCode, $targetType, $targetCode, $oldValue, $newValue)
{
    if ((string) $oldValue === (string) $newValue) return true;

    $sql = "INSERT INTO ".MAIN_DB_PREFIX."saas_audit_log(entity_id, fk_user, action_code, target_type, target_code, old_value, new_value, ip_address, date_created)
            VALUES (".((int) $entityId).", ".((int) $actorUserId).", '".$db->escape($actionCode)."', '".$db->escape($targetType)."', '".$db->escape($targetCode)."', '".$db->escape((string) $oldValue)."', '".$db->escape((string) $newValue)."', '".$db->escape(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '')."', '".dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')."')";
    return (bool) $db->query($sql);
}

function saas_catalog_codes($db, $table, $field)
{
    $rows = array();
    $resql = $db->query("SELECT ".$field." as code FROM ".MAIN_DB_PREFIX.$table." ORDER BY ".$field);
    while ($resql && ($obj = $db->fetch_object($resql))) $rows[] = (string) $obj->code;
    return $rows;
}

function saas_permission_catalog($db)
{
    $rows = array();
    $resql = $db->query("SELECT code,module_code FROM ".MAIN_DB_PREFIX."saas_permissions ORDER BY module_code,code");
    while ($resql && ($obj = $db->fetch_object($resql))) {
        $rows[] = array('code' => (string) $obj->code, 'module_code' => (string) $obj->module_code);
    }
    return $rows;
}

function saas_enabled_map($db, $table, $field, $entityId)
{
    $map = array();
    $resql = $db->query("SELECT ".$field." as code, enabled FROM ".MAIN_DB_PREFIX.$table." WHERE entity_id = ".((int) $entityId));
    while ($resql && ($obj = $db->fetch_object($resql))) $map[(string) $obj->code] = ((int) $obj->enabled === 1 ? 1 : 0);
    return $map;
}

function saas_limit_map($db, $entityId)
{
    $map = array();
    $resql = $db->query("SELECT limit_code, value FROM ".MAIN_DB_PREFIX."saas_tenant_limits WHERE entity_id = ".((int) $entityId));
    while ($resql && ($obj = $db->fetch_object($resql))) $map[(string) $obj->limit_code] = (int) $obj->value;
    return $map;
}

function saas_user_perm_map($db, $entityId, $userId)
{
    $map = array();
    if ((int) $userId <= 0) return $map;
    $resql = $db->query("SELECT permission_code, allowed FROM ".MAIN_DB_PREFIX."saas_user_permissions WHERE entity_id = ".((int) $entityId)." AND fk_user = ".((int) $userId));
    while ($resql && ($obj = $db->fetch_object($resql))) if ((int) $obj->allowed === 1) $map[(string) $obj->permission_code] = 1;
    return $map;
}

function saas_user_roles_map($db, $entityId, $userId)
{
    $map = array();
    if ((int) $userId <= 0) return $map;
    $resql = $db->query("SELECT role_code FROM ".MAIN_DB_PREFIX."saas_user_roles WHERE entity_id = ".((int) $entityId)." AND fk_user = ".((int) $userId));
    while ($resql && ($obj = $db->fetch_object($resql))) $map[(string) $obj->role_code] = 1;
    return $map;
}

function saas_role_perm_allow_map($db, $entityId, $userId)
{
    $map = array();
    if ((int) $userId <= 0) return $map;
    $sql = "SELECT rp.permission_code, MAX(rp.allowed) as allowed
            FROM ".MAIN_DB_PREFIX."saas_user_roles ur
            INNER JOIN ".MAIN_DB_PREFIX."saas_role_permissions rp ON rp.entity_id = ur.entity_id AND rp.role_code = ur.role_code
            WHERE ur.entity_id = ".((int) $entityId)." AND ur.fk_user = ".((int) $userId)."
            GROUP BY rp.permission_code";
    $resql = $db->query($sql);
    while ($resql && ($obj = $db->fetch_object($resql))) if ((int) $obj->allowed === 1) $map[(string) $obj->permission_code] = 1;
    return $map;
}

function saas_roles_catalog($db, $entityId)
{
    $rows = array();
    $resql = $db->query("SELECT code,label,description,is_system FROM ".MAIN_DB_PREFIX."saas_roles WHERE entity_id = ".((int) $entityId)." ORDER BY is_system DESC, code ASC");
    while ($resql && ($obj = $db->fetch_object($resql))) $rows[] = $obj;
    return $rows;
}

function saas_bundles_catalog($db)
{
    $rows = array();
    $resql = $db->query("SELECT code,label,description,is_active FROM ".MAIN_DB_PREFIX."saas_bundles ORDER BY code");
    while ($resql && ($obj = $db->fetch_object($resql))) $rows[] = $obj;
    return $rows;
}

function saas_tenant_bundle_map($db, $entityId)
{
    $map = array();
    $resql = $db->query("SELECT bundle_code,is_primary FROM ".MAIN_DB_PREFIX."saas_tenant_bundles WHERE entity_id = ".((int) $entityId));
    while ($resql && ($obj = $db->fetch_object($resql))) $map[(string) $obj->bundle_code] = ((int) $obj->is_primary === 1 ? 1 : 0);
    return $map;
}

function saas_get_users($db, $entityId)
{
    $rows = array();
    $resql = $db->query("SELECT rowid,login,firstname,lastname,admin,statut,entity FROM ".MAIN_DB_PREFIX."user WHERE entity IN (0, ".((int) $entityId).") ORDER BY admin DESC, login ASC");
    while ($resql && ($obj = $db->fetch_object($resql))) $rows[] = $obj;
    return $rows;
}

function saas_save_enabled_codes($db, $table, $field, $entityId, array $selectedCodes, array $catalogCodes)
{
    $entityId = (int) $entityId;
    $selectedSet = saas_set($selectedCodes);

    if (!$db->query("DELETE FROM ".MAIN_DB_PREFIX.$table." WHERE entity_id = ".$entityId)) return false;

    foreach ($catalogCodes as $code) {
        $code = (string) $code;
        if (empty($selectedSet[$code])) continue;
        $sql = "INSERT INTO ".MAIN_DB_PREFIX.$table."(entity_id, ".$field.", enabled, date_created)
                VALUES (".$entityId.", '".$db->escape($code)."', 1, '".dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')."')";
        if (!$db->query($sql)) return false;
    }
    return true;
}

function saas_save_limits($db, $entityId, array $postedLimits, array $limitCodes)
{
    $entityId = (int) $entityId;
    if (!$db->query("DELETE FROM ".MAIN_DB_PREFIX."saas_tenant_limits WHERE entity_id = ".$entityId)) return false;

    foreach ($limitCodes as $code) {
        $value = isset($postedLimits[$code]) ? max(0, (int) $postedLimits[$code]) : 0;
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."saas_tenant_limits(entity_id, limit_code, value, date_created)
                VALUES (".$entityId.", '".$db->escape((string) $code)."', ".$value.", '".dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')."')";
        if (!$db->query($sql)) return false;
    }
    return true;
}

function saas_save_tenant_bundles($db, $entityId, array $bundleCodes, $primaryCode)
{
    $entityId = (int) $entityId;
    $bundleCodes = array_values(array_unique(array_filter(array_map('strval', $bundleCodes))));
    $primaryCode = trim((string) $primaryCode);

    if (!$db->query("DELETE FROM ".MAIN_DB_PREFIX."saas_tenant_bundles WHERE entity_id = ".$entityId)) return false;

    if (!empty($bundleCodes) && ($primaryCode === '' || !in_array($primaryCode, $bundleCodes, true))) {
        $primaryCode = $bundleCodes[0];
    }

    foreach ($bundleCodes as $bundleCode) {
        $isPrimary = ($bundleCode === $primaryCode ? 1 : 0);
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."saas_tenant_bundles(entity_id, bundle_code, is_primary, date_created)
                VALUES (".$entityId.", '".$db->escape($bundleCode)."', ".$isPrimary.", '".dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')."')";
        if (!$db->query($sql)) return false;
    }

    return true;
}

function saas_save_user_roles($db, $entityId, $userId, array $roleCodes)
{
    $entityId = (int) $entityId;
    $userId = (int) $userId;
    if ($userId <= 0) return true;

    if (!$db->query("DELETE FROM ".MAIN_DB_PREFIX."saas_user_roles WHERE entity_id = ".$entityId." AND fk_user = ".$userId)) return false;

    foreach ($roleCodes as $roleCode) {
        $roleCode = trim((string) $roleCode);
        if ($roleCode === '') continue;
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."saas_user_roles(entity_id, fk_user, role_code, date_created)
                VALUES (".$entityId.", ".$userId.", '".$db->escape($roleCode)."', '".dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')."')";
        if (!$db->query($sql)) return false;
    }

    return true;
}

function saas_save_user_permissions($db, $entityId, $userId, array $allowCodes)
{
    $entityId = (int) $entityId;
    $userId = (int) $userId;
    if ($userId <= 0) return true;

    // Explicit allow overrides only. Unchecked = inherit from roles/default behavior.
    if (!$db->query("DELETE FROM ".MAIN_DB_PREFIX."saas_user_permissions WHERE entity_id = ".$entityId." AND fk_user = ".$userId)) return false;

    foreach ($allowCodes as $code) {
        $code = trim((string) $code);
        if ($code === '') continue;
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."saas_user_permissions(entity_id, fk_user, permission_code, allowed, date_created)
                VALUES (".$entityId.", ".$userId.", '".$db->escape($code)."', 1, '".dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')."')";
        if (!$db->query($sql)) return false;
    }

    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
    if (function_exists('checkToken') && !checkToken()) {
        accessforbidden('Invalid CSRF token');
    }
    $modulesPost = saas_post_array('modules');
    $featuresPost = saas_post_array('features');
    $limitsPost = saas_post_array('limits');
    $bundlesPost = saas_post_array('bundles');
    $rolesPost = saas_post_array('user_roles');
    $userPermPost = saas_post_array('user_permissions');
    $primaryBundle = GETPOST('primary_bundle', 'alpha');

    $moduleCatalog = saas_catalog_codes($db, 'saas_modules', 'code');
    $featureCatalog = saas_catalog_codes($db, 'saas_features', 'code');
    $limitCatalog = saas_catalog_codes($db, 'saas_limits', 'code');
    $permCatalog = saas_permission_catalog($db);
    $bundleCatalogObjects = saas_bundles_catalog($db);
    $roleCatalogObjects = saas_roles_catalog($db, $entityId);

    $bundleCatalog = array();
    foreach ($bundleCatalogObjects as $obj) $bundleCatalog[] = (string) $obj->code;
    $roleCatalog = array();
    foreach ($roleCatalogObjects as $obj) $roleCatalog[] = (string) $obj->code;

    $selectedModules = array();
    foreach (saas_checked_keys($modulesPost) as $code) if (in_array($code, $moduleCatalog, true)) $selectedModules[] = $code;
    $selectedModuleSet = saas_set($selectedModules);

    $selectedFeatures = array();
    foreach (saas_checked_keys($featuresPost) as $code) if (in_array($code, $featureCatalog, true)) $selectedFeatures[] = $code;

    $selectedBundles = array();
    foreach (saas_checked_keys($bundlesPost) as $code) if (in_array($code, $bundleCatalog, true)) $selectedBundles[] = $code;

    $selectedRoles = array();
    foreach (saas_checked_keys($rolesPost) as $code) if (in_array($code, $roleCatalog, true)) $selectedRoles[] = $code;

    $availablePermSet = array();
    foreach ($permCatalog as $item) {
        $moduleCode = (string) $item['module_code'];
        if ($moduleCode !== '' && empty($selectedModuleSet[$moduleCode])) continue;
        $availablePermSet[(string) $item['code']] = 1;
    }

    $selectedUserPerms = array();
    foreach (saas_checked_keys($userPermPost) as $code) if (!empty($availablePermSet[$code])) $selectedUserPerms[] = $code;

    $oldModules = saas_enabled_map($db, 'saas_tenant_modules', 'module_code', $entityId);
    $oldFeatures = saas_enabled_map($db, 'saas_tenant_features', 'feature_code', $entityId);
    $oldLimits = saas_limit_map($db, $entityId);
    $oldBundles = saas_tenant_bundle_map($db, $entityId);
    $oldRoles = saas_user_roles_map($db, $entityId, $selectedUserId);
    $oldUserPerms = saas_user_perm_map($db, $entityId, $selectedUserId);

    $db->begin();
    $ok = true;
    $errs = array();

    if (!saas_ensure_tables($db)) {
        $ok = false;
        $errs[] = $db->lasterror();
    }
    if ($ok && !saas_save_enabled_codes($db, 'saas_tenant_modules', 'module_code', $entityId, $selectedModules, $moduleCatalog)) {
        $ok = false;
        $errs[] = $db->lasterror();
    }
    if ($ok && !saas_save_enabled_codes($db, 'saas_tenant_features', 'feature_code', $entityId, $selectedFeatures, $featureCatalog)) {
        $ok = false;
        $errs[] = $db->lasterror();
    }
    if ($ok && !saas_save_limits($db, $entityId, $limitsPost, $limitCatalog)) {
        $ok = false;
        $errs[] = $db->lasterror();
    }
    if ($ok && !saas_save_tenant_bundles($db, $entityId, $selectedBundles, $primaryBundle)) {
        $ok = false;
        $errs[] = $db->lasterror();
    }
    if ($ok && !saas_save_user_roles($db, $entityId, $selectedUserId, $selectedRoles)) {
        $ok = false;
        $errs[] = $db->lasterror();
    }
    if ($ok && !saas_save_user_permissions($db, $entityId, $selectedUserId, $selectedUserPerms)) {
        $ok = false;
        $errs[] = $db->lasterror();
    }

    if ($ok) {
        $newModules = saas_enabled_map($db, 'saas_tenant_modules', 'module_code', $entityId);
        $newFeatures = saas_enabled_map($db, 'saas_tenant_features', 'feature_code', $entityId);
        $newLimits = saas_limit_map($db, $entityId);
        $newBundles = saas_tenant_bundle_map($db, $entityId);
        $newRoles = saas_user_roles_map($db, $entityId, $selectedUserId);
        $newUserPerms = saas_user_perm_map($db, $entityId, $selectedUserId);

        $actorUserId = (is_object($user) && !empty($user->id) ? (int) $user->id : 0);
        $entityTarget = 'entity:'.$entityId;
        $userTarget = 'entity:'.$entityId.':user:'.$selectedUserId;

        if (!saas_audit($db, $entityId, $actorUserId, 'tenant.save.modules', 'tenant', $entityTarget, saas_json_map($oldModules), saas_json_map($newModules))) {
            $ok = false;
            $errs[] = $db->lasterror();
        }
        if ($ok && !saas_audit($db, $entityId, $actorUserId, 'tenant.save.features', 'tenant', $entityTarget, saas_json_map($oldFeatures), saas_json_map($newFeatures))) {
            $ok = false;
            $errs[] = $db->lasterror();
        }
        if ($ok && !saas_audit($db, $entityId, $actorUserId, 'tenant.save.limits', 'tenant', $entityTarget, saas_json_map($oldLimits), saas_json_map($newLimits))) {
            $ok = false;
            $errs[] = $db->lasterror();
        }
        if ($ok && !saas_audit($db, $entityId, $actorUserId, 'tenant.save.bundles', 'tenant', $entityTarget, saas_json_map($oldBundles), saas_json_map($newBundles))) {
            $ok = false;
            $errs[] = $db->lasterror();
        }
        if ($ok && !saas_audit($db, $entityId, $actorUserId, 'tenant.save.user_roles', 'user', $userTarget, saas_json_map($oldRoles), saas_json_map($newRoles))) {
            $ok = false;
            $errs[] = $db->lasterror();
        }
        if ($ok && !saas_audit($db, $entityId, $actorUserId, 'tenant.save.user_permissions', 'user', $userTarget, saas_json_map($oldUserPerms), saas_json_map($newUserPerms))) {
            $ok = false;
            $errs[] = $db->lasterror();
        }
    }

    if ($ok) {
        $db->commit();
        $message = 'Tenant configuration saved successfully.';
    } else {
        $db->rollback();
        $error = 'Save failed: '.implode(' | ', array_filter($errs));
        if ($error === 'Save failed: ') $error = 'Save failed due to unknown error.';
    }
}

$users = saas_get_users($db, $entityId);
if ($selectedUserId <= 0 && !empty($users)) $selectedUserId = (int) $users[0]->rowid;

$moduleMap = saas_enabled_map($db, 'saas_tenant_modules', 'module_code', $entityId);
$featureMap = saas_enabled_map($db, 'saas_tenant_features', 'feature_code', $entityId);
$limitMap = saas_limit_map($db, $entityId);
$bundleMap = saas_tenant_bundle_map($db, $entityId);
$userPermMap = saas_user_perm_map($db, $entityId, $selectedUserId);
$userRoleMap = saas_user_roles_map($db, $entityId, $selectedUserId);
$rolePermAllowMap = saas_role_perm_allow_map($db, $entityId, $selectedUserId);
$rolesCatalog = saas_roles_catalog($db, $entityId);
$bundlesCatalog = saas_bundles_catalog($db);

llxHeader('', $langs->trans('TenantConfiguration'));
print load_fiche_titre($langs->trans('TenantConfiguration'), '', 'title_setup');
print dol_get_fiche_head(saascoreAdminPrepareHead(), 'tenant', 'kafo-ERP-Control', -1, 'generic');

if ($message !== '') print info_admin($message, 1);
if ($error !== '') print info_admin($error, 0);

print '<style>
.saas-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:14px;margin-top:12px}
.saas-card{border:1px solid #d8dce6;border-radius:8px;padding:14px;background:#fff}
.saas-card h3{margin:0 0 12px 0;padding-bottom:8px;border-bottom:1px solid #eceff5}
.saas-row{padding:7px 0;border-bottom:1px solid #f3f4f7}
.saas-row:last-child{border-bottom:0}
.saas-muted{color:#666;font-size:12px}
.saas-actions{display:flex;gap:8px;align-items:center;justify-content:space-between;margin:10px 0 0 0}
.saas-filter{display:grid;grid-template-columns:160px 1fr 160px 1fr;gap:10px;align-items:center}
.saas-userbox{padding:10px;background:#f7f8fb;border:1px solid #e6e9f0;border-radius:8px;margin-bottom:10px}
.saas-pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;line-height:1.5;background:#eef2f7;color:#334}
.saas-pill.direct{background:#dff7e8;color:#145a2a}
.saas-pill.role{background:#e4edff;color:#1f3f7a}
.saas-pill.none{background:#f3f4f6;color:#666}
.saas-disabled{opacity:.45}
@media (max-width:900px){.saas-filter{grid-template-columns:1fr}}
</style>';

print '<form method="GET" action="'.saas_h($_SERVER['PHP_SELF']).'" class="saas-userbox">';
print '<div class="saas-filter">';
print '<div><strong>Entity ID</strong></div>';
print '<div><input type="number" class="flat" name="entity_id" value="'.((int) $entityId).'"></div>';
print '<div><strong>Selected User</strong></div>';
print '<div><select class="flat minwidth300" name="fk_user" onchange="this.form.submit()">';
foreach ($users as $u) {
    $label = trim($u->login.' - '.$u->firstname.' '.$u->lastname);
    $type = ((int) $u->admin === 1 ? ' [Admin]' : ' [User]');
    print '<option value="'.((int) $u->rowid).'" '.(((int) $u->rowid === (int) $selectedUserId) ? 'selected' : '').'>'.saas_h($label.$type).'</option>';
}
print '</select></div>';
print '</div>';
print '</form>';

print '<form method="POST" action="'.saas_h($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';
print '<input type="hidden" name="entity_id" value="'.((int) $entityId).'">';
print '<input type="hidden" name="fk_user" value="'.((int) $selectedUserId).'">';

print '<div class="saas-grid">';

print '<div class="saas-card">';
print '<h3>Tenant Modules</h3><div class="saas-muted">Enable modules available for this tenant.</div>';
$resql = $db->query("SELECT m.code,m.label,m.description,COALESCE(tm.enabled,0) enabled
                     FROM ".MAIN_DB_PREFIX."saas_modules m
                     LEFT JOIN ".MAIN_DB_PREFIX."saas_tenant_modules tm ON tm.module_code = m.code AND tm.entity_id = ".((int) $entityId)."
                     ORDER BY m.code");
while ($resql && ($obj = $db->fetch_object($resql))) {
    $checked = (!empty($obj->enabled) || !empty($moduleMap[$obj->code])) ? 'checked' : '';
    print '<div class="saas-row"><label><input class="saas-module" type="checkbox" data-module="'.saas_h($obj->code).'" name="modules['.saas_h($obj->code).']" value="1" '.$checked.'> <strong>'.saas_h($obj->label).'</strong> <span class="saas-muted">('.saas_h($obj->code).')</span><br><span class="saas-muted">'.saas_h($obj->description).'</span></label></div>';
}
print '</div>';

print '<div class="saas-card">';
print '<h3>Tenant Features</h3><div class="saas-muted">Features are auto-disabled when their module is off.</div>';
$resql = $db->query("SELECT f.code,f.label,f.module_code,f.description,COALESCE(tf.enabled,0) enabled
                     FROM ".MAIN_DB_PREFIX."saas_features f
                     LEFT JOIN ".MAIN_DB_PREFIX."saas_tenant_features tf ON tf.feature_code = f.code AND tf.entity_id = ".((int) $entityId)."
                     ORDER BY f.module_code,f.code");
while ($resql && ($obj = $db->fetch_object($resql))) {
    $moduleCode = (string) $obj->module_code;
    $moduleEnabled = ($moduleCode === '' || !empty($moduleMap[$moduleCode]));
    $checked = (!empty($obj->enabled) || !empty($featureMap[$obj->code])) ? 'checked' : '';
    $disabled = ($moduleEnabled ? '' : 'disabled');
    $rowClass = ($moduleEnabled ? '' : ' saas-disabled');

    print '<div class="saas-row'.$rowClass.'"><label><input class="saas-feature" data-module="'.saas_h($moduleCode).'" type="checkbox" name="features['.saas_h($obj->code).']" value="1" '.$checked.' '.$disabled.'> <strong>'.saas_h($obj->label).'</strong> <span class="saas-muted">('.saas_h($obj->code).')</span><br><span class="saas-muted">Module: '.saas_h($moduleCode !== '' ? $moduleCode : 'general').' | '.saas_h($obj->description).'</span></label></div>';
}
print '</div>';

print '<div class="saas-card">';
print '<h3>Tenant Limits</h3><div class="saas-muted">Numeric limits for tenant capabilities.</div>';
$resql = $db->query("SELECT l.code,l.label,l.module_code,l.description,COALESCE(tl.value,l.default_value) current_value
                     FROM ".MAIN_DB_PREFIX."saas_limits l
                     LEFT JOIN ".MAIN_DB_PREFIX."saas_tenant_limits tl ON tl.limit_code = l.code AND tl.entity_id = ".((int) $entityId)."
                     ORDER BY l.module_code,l.code");
while ($resql && ($obj = $db->fetch_object($resql))) {
    $currentValue = isset($limitMap[$obj->code]) ? (int) $limitMap[$obj->code] : (int) $obj->current_value;
    print '<div class="saas-row"><div><strong>'.saas_h($obj->label).'</strong> <span class="saas-muted">('.saas_h($obj->code).')</span></div>';
    print '<div class="saas-muted">Module: '.saas_h($obj->module_code !== '' ? $obj->module_code : 'general').' | '.saas_h($obj->description).'</div>';
    print '<div style="margin-top:6px;"><input type="number" min="0" class="flat minwidth100" name="limits['.saas_h($obj->code).']" value="'.$currentValue.'"></div></div>';
}
print '</div>';

print '<div class="saas-card">';
print '<h3>Tenant Bundles</h3><div class="saas-muted">Assign bundles and choose one primary bundle.</div>';
if (empty($bundlesCatalog)) {
    print '<div class="saas-row"><span class="saas-muted">No bundles defined yet.</span></div>';
} else {
    foreach ($bundlesCatalog as $bundle) {
        $code = (string) $bundle->code;
        $isChecked = !empty($bundleMap[$code]);
        $isPrimary = ($isChecked && (int) $bundleMap[$code] === 1);

        print '<div class="saas-row"><label><input class="saas-bundle" type="checkbox" name="bundles['.saas_h($code).']" value="1" '.($isChecked ? 'checked' : '').'> <strong>'.saas_h($bundle->label).'</strong> <span class="saas-muted">('.saas_h($code).')</span></label>';
        print '<div class="saas-muted">'.saas_h($bundle->description).'</div>';
        print '<div style="margin-top:6px;"><label><input class="saas-bundle-primary" type="radio" name="primary_bundle" value="'.saas_h($code).'" '.($isPrimary ? 'checked' : '').'> Primary bundle</label></div>';
        print '</div>';
    }
}
print '</div>';

print '<div class="saas-card">';
print '<h3>User Role Assignments</h3><div class="saas-muted">Assign roles to the selected user.</div>';
if ((int) $selectedUserId <= 0) {
    print '<div class="saas-row"><span class="saas-muted">No user selected.</span></div>';
} elseif (empty($rolesCatalog)) {
    print '<div class="saas-row"><span class="saas-muted">No roles defined for this entity.</span></div>';
} else {
    foreach ($rolesCatalog as $role) {
        $code = (string) $role->code;
        $checked = !empty($userRoleMap[$code]) ? 'checked' : '';
        print '<div class="saas-row"><label><input type="checkbox" name="user_roles['.saas_h($code).']" value="1" '.$checked.'> <strong>'.saas_h($role->label).'</strong> <span class="saas-muted">('.saas_h($code).')</span></label>';
        print '<div class="saas-muted">'.saas_h($role->description).((int) $role->is_system === 1 ? ' | system role' : '').'</div>';
        print '</div>';
    }
}
print '</div>';

print '<div class="saas-card">';
print '<h3>User Permission Overrides</h3><div class="saas-muted">Checked permissions are explicit direct allows. Unchecked permissions inherit from roles/default policy.</div>';
print '<div class="saas-actions"><div><strong>User ID:</strong> '.((int) $selectedUserId).'</div><div><button type="button" class="button" onclick="saasToggleUserPerms(true)">Select All</button> <button type="button" class="button" onclick="saasToggleUserPerms(false)">Unselect All</button></div></div>';
$resql = $db->query("SELECT p.code,p.label,p.module_code,p.description FROM ".MAIN_DB_PREFIX."saas_permissions p ORDER BY p.module_code,p.code");
$currentModule = '';
while ($resql && ($obj = $db->fetch_object($resql))) {
    $moduleCode = (string) $obj->module_code;
    if ($currentModule !== $moduleCode) {
        $currentModule = $moduleCode;
        print '<div style="margin-top:10px;padding-top:10px;border-top:2px solid #eef1f6"><strong>'.saas_h($moduleCode !== '' ? $moduleCode : 'general').'</strong></div>';
    }

    $moduleEnabled = ($moduleCode === '' || !empty($moduleMap[$moduleCode]));
    $checked = !empty($userPermMap[$obj->code]) ? 'checked' : '';
    $disabled = ($moduleEnabled ? '' : 'disabled');
    $rowClass = ($moduleEnabled ? '' : ' saas-disabled');

    $sourceClass = 'none';
    $sourceText = 'No inherited grant';
    if (!empty($userPermMap[$obj->code])) {
        $sourceClass = 'direct';
        $sourceText = 'Direct allow';
    } elseif (!empty($rolePermAllowMap[$obj->code])) {
        $sourceClass = 'role';
        $sourceText = 'Allowed by role';
    }

    print '<div class="saas-row'.$rowClass.'"><label><input class="saas-user-perm" data-module="'.saas_h($moduleCode).'" type="checkbox" name="user_permissions['.saas_h($obj->code).']" value="1" '.$checked.' '.$disabled.'> <strong>'.saas_h($obj->label).'</strong> <span class="saas-muted">('.saas_h($obj->code).')</span></label>';
    print '<div class="saas-muted">'.saas_h($obj->description).'</div>';
    print '<div style="margin-top:4px;"><span class="saas-pill '.$sourceClass.'">'.saas_h($sourceText).'</span></div>';
    print '</div>';
}
print '</div>';

print '</div>';
print '<div style="margin-top:14px;"><input type="submit" class="button button-save" value="Save Tenant Configuration"></div>';
print '</form>';

print '<script>
function saasToggleUserPerms(state){
  var boxes=document.querySelectorAll(".saas-user-perm");
  for(var i=0;i<boxes.length;i++){ if(!boxes[i].disabled){ boxes[i].checked=state; } }
}

function saasSyncBundlePrimary(){
  var radios=document.querySelectorAll(".saas-bundle-primary");
  for(var i=0;i<radios.length;i++){
    if(radios[i].checked){
      var code=radios[i].value;
      var box=document.querySelector(".saas-bundle[name=\\"bundles["+code+"]\\"]");
      if(box){ box.checked=true; }
    }
  }
}

function saasSyncModuleDependencies(){
  var moduleMap={};
  var moduleBoxes=document.querySelectorAll(".saas-module");
  for(var i=0;i<moduleBoxes.length;i++) moduleMap[moduleBoxes[i].getAttribute("data-module")]=moduleBoxes[i].checked;

  var featureBoxes=document.querySelectorAll(".saas-feature");
  for(var j=0;j<featureBoxes.length;j++){
    var moduleCode=featureBoxes[j].getAttribute("data-module");
    var enabled=(!moduleCode || moduleMap[moduleCode]);
    featureBoxes[j].disabled=!enabled;
    if(!enabled) featureBoxes[j].checked=false;
    var row=featureBoxes[j].closest(".saas-row");
    if(row){ if(enabled) row.classList.remove("saas-disabled"); else row.classList.add("saas-disabled"); }
  }

  var permBoxes=document.querySelectorAll(".saas-user-perm");
  for(var k=0;k<permBoxes.length;k++){
    var permModule=permBoxes[k].getAttribute("data-module");
    var permEnabled=(!permModule || moduleMap[permModule]);
    permBoxes[k].disabled=!permEnabled;
    if(!permEnabled) permBoxes[k].checked=false;
    var permRow=permBoxes[k].closest(".saas-row");
    if(permRow){ if(permEnabled) permRow.classList.remove("saas-disabled"); else permRow.classList.add("saas-disabled"); }
  }
}

(function(){
  var moduleBoxes=document.querySelectorAll(".saas-module");
  for(var i=0;i<moduleBoxes.length;i++) moduleBoxes[i].addEventListener("change", saasSyncModuleDependencies);

  var primary=document.querySelectorAll(".saas-bundle-primary");
  for(var j=0;j<primary.length;j++) primary[j].addEventListener("change", saasSyncBundlePrimary);

  saasSyncModuleDependencies();
})();
</script>';

print dol_get_fiche_end();
llxFooter();
$db->close();



