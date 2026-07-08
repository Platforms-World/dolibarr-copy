<?php
require '../../../main.inc.php';
require_once dol_buildpath('/saascore/core/lib/saascore.lib.php', 0);
require_once dol_buildpath('/saascore/class/SaasTenantService.php', 0);

$langs->loadLangs(array('admin', 'saascore@saascore'));
saascoreRequireAdminRight('tenantmanage');

$service = new SaasTenantService($db);
$entityId = (int) GETPOST('entity_id', 'int');
if ($entityId <= 0) $entityId = (int) $conf->entity;

$selectedUserId = (int) GETPOST('fk_user', 'int');
$action = GETPOST('action', 'aZ09');
$message = '';
$error = '';

function saascore_h($v)
{
    return dol_escape_htmltag((string) $v);
}

function saascore_get_user_permission_map($db, $entityId, $userId)
{
    $map = array();
    if ($userId <= 0) return $map;

    $sql = "SELECT permission_code, allowed FROM ".MAIN_DB_PREFIX."saas_user_permissions WHERE entity_id = ".((int) $entityId)." AND fk_user = ".((int) $userId);
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $map[$obj->permission_code] = (int) $obj->allowed;
        }
    }

    return $map;
}

function saascore_set_user_permission($db, $entityId, $userId, $permissionCode, $allowed)
{
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."saas_user_permissions(entity_id, fk_user, permission_code, allowed, date_created)
            VALUES (".((int) $entityId).", ".((int) $userId).", '".$db->escape($permissionCode)."', ".((int) $allowed).", '".dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')."')
            ON DUPLICATE KEY UPDATE allowed = VALUES(allowed), tms = CURRENT_TIMESTAMP";
    return $db->query($sql);
}

function saascore_get_users_for_entity($db, $entityId)
{
    $rows = array();
    $sql = "SELECT rowid, login, firstname, lastname, admin, statut, entity
            FROM ".MAIN_DB_PREFIX."user
            WHERE entity IN (0, ".((int) $entityId).")
            ORDER BY admin DESC, login ASC";
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $rows[] = $obj;
        }
    }
    return $rows;
}

if ($action === 'save') {
    $modules = GETPOST('modules', 'array');
    $features = GETPOST('features', 'array');
    $limits = GETPOST('limits', 'array');
    $userPermissions = GETPOST('user_permissions', 'array');

    $db->begin();
    $saveOk = true;

    $resql = $db->query("SELECT code FROM ".MAIN_DB_PREFIX."saas_modules ORDER BY code");
    while ($resql && ($obj = $db->fetch_object($resql))) {
        if (!$service->setTenantModule($entityId, $obj->code, !empty($modules[$obj->code]) ? 1 : 0)) {
            $saveOk = false;
        }
    }

    $resql = $db->query("SELECT code FROM ".MAIN_DB_PREFIX."saas_features ORDER BY code");
    while ($resql && ($obj = $db->fetch_object($resql))) {
        if (!$service->setTenantFeature($entityId, $obj->code, !empty($features[$obj->code]) ? 1 : 0)) {
            $saveOk = false;
        }
    }

    $resql = $db->query("SELECT code FROM ".MAIN_DB_PREFIX."saas_limits ORDER BY code");
    while ($resql && ($obj = $db->fetch_object($resql))) {
        if (!$service->setTenantLimit($entityId, $obj->code, isset($limits[$obj->code]) ? (int) $limits[$obj->code] : 0)) {
            $saveOk = false;
        }
    }

    if ($selectedUserId > 0) {
        $resql = $db->query("SELECT code FROM ".MAIN_DB_PREFIX."saas_permissions ORDER BY module_code, code");
        while ($resql && ($obj = $db->fetch_object($resql))) {
            if (!saascore_set_user_permission($db, $entityId, $selectedUserId, $obj->code, !empty($userPermissions[$obj->code]) ? 1 : 0)) {
                $saveOk = false;
            }
        }
    }

    if ($saveOk) {
        $db->commit();
        $message = $langs->trans('SetupSaved');
    } else {
        $db->rollback();
        $error = $langs->trans('Error');
    }
}

$users = saascore_get_users_for_entity($db, $entityId);
if ($selectedUserId <= 0 && !empty($users)) {
    $selectedUserId = (int) $users[0]->rowid;
}
$userPermissionMap = saascore_get_user_permission_map($db, $entityId, $selectedUserId);

llxHeader('', $langs->trans('TenantConfiguration'));
print load_fiche_titre($langs->trans('TenantConfiguration'), '', 'title_setup');
print dol_get_fiche_head(saascoreAdminPrepareHead(), 'tenant', $langs->trans('SaaSCoreSetup'), -1, 'generic');

if ($message !== '') {
    print info_admin($message, 1);
}
if ($error !== '') {
    print info_admin($error, 0);
}

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
@media (max-width:900px){.saas-filter{grid-template-columns:1fr}}
</style>';

print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" class="saas-userbox">';
print '<div class="saas-filter">';
print '<div><strong>Entity ID</strong></div>';
print '<div><input type="number" class="flat" name="entity_id" value="'.((int) $entityId).'"></div>';
print '<div><strong>المستخدم</strong></div>';
print '<div><select class="flat minwidth300" name="fk_user" onchange="this.form.submit()">';
foreach ($users as $u) {
    $label = trim($u->login.' - '.$u->firstname.' '.$u->lastname);
    $type = ((int) $u->admin === 1 ? ' [رئيسي/Admin]' : ' [فرعي]');
    print '<option value="'.((int) $u->rowid).'" '.(((int) $u->rowid === (int) $selectedUserId) ? 'selected' : '').'>'.saascore_h($label.$type).'</option>';
}
print '</select></div>';
print '</div>';
print '</form>';

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';
print '<input type="hidden" name="entity_id" value="'.((int) $entityId).'">';
print '<input type="hidden" name="fk_user" value="'.((int) $selectedUserId).'">';

print '<div class="saas-grid">';

print '<div class="saas-card">';
print '<h3>الموديولات</h3>';
print '<div class="saas-muted">تفعيل أو إيقاف الموديول على مستوى الـ Tenant.</div>';
$resql = $db->query("SELECT m.code,m.label,m.description,COALESCE(tm.enabled,0) enabled
                     FROM ".MAIN_DB_PREFIX."saas_modules m
                     LEFT JOIN ".MAIN_DB_PREFIX."saas_tenant_modules tm ON tm.module_code = m.code AND tm.entity_id = ".((int) $entityId)."
                     ORDER BY m.code");
while ($resql && ($obj = $db->fetch_object($resql))) {
    print '<div class="saas-row"><label><input type="checkbox" name="modules['.saascore_h($obj->code).']" value="1" '.(!empty($obj->enabled) ? 'checked' : '').'> <strong>'.saascore_h($obj->label).'</strong> <span class="saas-muted">('.saascore_h($obj->code).')</span><br><span class="saas-muted">'.saascore_h($obj->description).'</span></label></div>';
}
print '</div>';

print '<div class="saas-card">';
print '<h3>الميزات</h3>';
print '<div class="saas-muted">ميزات الموديولات التابعة للـ Tenant.</div>';
$resql = $db->query("SELECT f.code,f.label,f.module_code,f.description,COALESCE(tf.enabled,0) enabled
                     FROM ".MAIN_DB_PREFIX."saas_features f
                     LEFT JOIN ".MAIN_DB_PREFIX."saas_tenant_features tf ON tf.feature_code = f.code AND tf.entity_id = ".((int) $entityId)."
                     ORDER BY f.module_code,f.code");
while ($resql && ($obj = $db->fetch_object($resql))) {
    print '<div class="saas-row"><label><input type="checkbox" name="features['.saascore_h($obj->code).']" value="1" '.(!empty($obj->enabled) ? 'checked' : '').'> <strong>'.saascore_h($obj->label).'</strong> <span class="saas-muted">('.saascore_h($obj->code).')</span><br><span class="saas-muted">Module: '.saascore_h($obj->module_code).' | '.saascore_h($obj->description).'</span></label></div>';
}
print '</div>';

print '<div class="saas-card">';
print '<h3>الحدود</h3>';
print '<div class="saas-muted">القيم الرقمية مثل عدد الكاشير أو الحدود الأخرى.</div>';
$resql = $db->query("SELECT l.code,l.label,l.module_code,l.description,COALESCE(tl.value,l.default_value) current_value
                     FROM ".MAIN_DB_PREFIX."saas_limits l
                     LEFT JOIN ".MAIN_DB_PREFIX."saas_tenant_limits tl ON tl.limit_code = l.code AND tl.entity_id = ".((int) $entityId)."
                     ORDER BY l.module_code,l.code");
while ($resql && ($obj = $db->fetch_object($resql))) {
    print '<div class="saas-row"><div><strong>'.saascore_h($obj->label).'</strong> <span class="saas-muted">('.saascore_h($obj->code).')</span></div>';
    print '<div class="saas-muted">Module: '.saascore_h($obj->module_code).' | '.saascore_h($obj->description).'</div>';
    print '<div style="margin-top:6px;"><input type="number" min="0" class="flat minwidth100" name="limits['.saascore_h($obj->code).']" value="'.((int) $obj->current_value).'"></div></div>';
}
print '</div>';

print '<div class="saas-card">';
print '<h3>صلاحيات المستخدم المحدد</h3>';
print '<div class="saas-muted">هنا تختار المستخدم الرئيسي أو الفرعي وتفعل/توقف صلاحيات الموديولات المضافة بكبسة Checkbox واحدة ثم Save.</div>';
print '<div class="saas-actions"><div><strong>User ID:</strong> '.((int) $selectedUserId).'</div><div><button type="button" class="button" onclick="saasToggleAll(true)">تفعيل الكل</button> <button type="button" class="button" onclick="saasToggleAll(false)">إيقاف الكل</button></div></div>';
$resql = $db->query("SELECT p.code,p.label,p.module_code,p.description,
                            COALESCE(up.allowed,0) allowed,
                            COALESCE(tm.enabled,0) module_enabled
                     FROM ".MAIN_DB_PREFIX."saas_permissions p
                     LEFT JOIN ".MAIN_DB_PREFIX."saas_user_permissions up ON up.permission_code = p.code AND up.entity_id = ".((int) $entityId)." AND up.fk_user = ".((int) $selectedUserId)."
                     LEFT JOIN ".MAIN_DB_PREFIX."saas_tenant_modules tm ON tm.module_code = p.module_code AND tm.entity_id = ".((int) $entityId)."
                     WHERE p.module_code IS NULL OR p.module_code = '' OR COALESCE(tm.enabled,0) = 1
                     ORDER BY p.module_code, p.code");
$currentModule = '';
while ($resql && ($obj = $db->fetch_object($resql))) {
    $moduleCode = (string) $obj->module_code;
    if ($currentModule !== $moduleCode) {
        $currentModule = $moduleCode;
        print '<div style="margin-top:10px;padding-top:10px;border-top:2px solid #eef1f6"><strong>'.saascore_h($moduleCode !== '' ? $moduleCode : 'general').'</strong></div>';
    }
    print '<div class="saas-row"><label><input class="saas-user-perm" type="checkbox" name="user_permissions['.saascore_h($obj->code).']" value="1" '.(!empty($obj->allowed) ? 'checked' : '').'> <strong>'.saascore_h($obj->label).'</strong> <span class="saas-muted">('.saascore_h($obj->code).')</span><br><span class="saas-muted">'.saascore_h($obj->description).'</span></label></div>';
}
print '</div>';

print '</div>';
print '<div style="margin-top:14px;"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
print '</form>';
print '<script>
function saasToggleAll(state){
  var boxes=document.querySelectorAll(".saas-user-perm");
  for(var i=0;i<boxes.length;i++){boxes[i].checked=state;}
}
</script>';

print dol_get_fiche_end();
llxFooter();
$db->close();
