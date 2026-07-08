<?php
require '../../../main.inc.php';
require_once dol_buildpath('/saascore/core/lib/saascore.lib.php', 0);
require_once dol_buildpath('/saascore/class/SaasTenantService.php', 0);
$langs->loadLangs(array('admin', 'saascore@saascore'));
saascoreRequireAdminRight('tenantmanage');
$service = new SaasTenantService($db);
$entityId = (int) GETPOST('entity_id', 'int'); if ($entityId <= 0) $entityId = (int) $conf->entity;
$action = GETPOST('action', 'aZ09');
if ($action === 'save') {
    $modules = GETPOST('modules', 'array');
    $features = GETPOST('features', 'array');
    $limits = GETPOST('limits', 'array');
    $db->begin();
    $resql = $db->query("SELECT code FROM ".MAIN_DB_PREFIX."saas_modules ORDER BY code");
    while ($resql && ($obj = $db->fetch_object($resql))) $service->setTenantModule($entityId, $obj->code, !empty($modules[$obj->code]) ? 1 : 0);
    $resql = $db->query("SELECT code FROM ".MAIN_DB_PREFIX."saas_features ORDER BY code");
    while ($resql && ($obj = $db->fetch_object($resql))) $service->setTenantFeature($entityId, $obj->code, !empty($features[$obj->code]) ? 1 : 0);
    $resql = $db->query("SELECT code FROM ".MAIN_DB_PREFIX."saas_limits ORDER BY code");
    while ($resql && ($obj = $db->fetch_object($resql))) $service->setTenantLimit($entityId, $obj->code, isset($limits[$obj->code]) ? (int)$limits[$obj->code] : 0);
    $db->commit();
}
llxHeader('', $langs->trans('TenantConfiguration'));
print load_fiche_titre($langs->trans('TenantConfiguration'), '', 'title_setup');
print dol_get_fiche_head(saascoreAdminPrepareHead(), 'tenant', $langs->trans('SaaSCoreSetup'), -1, 'generic');
print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save">';
print '<div style="margin-bottom:10px;">Entity ID <input type="number" name="entity_id" value="'.$entityId.'"></div>';
print '<h3>'.$langs->trans('ModulesCatalog').'</h3>';
$resql = $db->query("SELECT m.code,m.label,COALESCE(tm.enabled,0) enabled FROM ".MAIN_DB_PREFIX."saas_modules m LEFT JOIN ".MAIN_DB_PREFIX."saas_tenant_modules tm ON tm.module_code=m.code AND tm.entity_id=".$entityId." ORDER BY m.code");
while ($resql && ($obj = $db->fetch_object($resql))) print '<div><label><input type="checkbox" name="modules['.$obj->code.']" value="1" '.(!empty($obj->enabled)?'checked':'').'> '.dol_escape_htmltag($obj->label).' ('.dol_escape_htmltag($obj->code).')</label></div>';
print '<h3>'.$langs->trans('FeaturesCatalog').'</h3>';
$resql = $db->query("SELECT f.code,f.label,COALESCE(tf.enabled,0) enabled FROM ".MAIN_DB_PREFIX."saas_features f LEFT JOIN ".MAIN_DB_PREFIX."saas_tenant_features tf ON tf.feature_code=f.code AND tf.entity_id=".$entityId." ORDER BY f.module_code,f.code");
while ($resql && ($obj = $db->fetch_object($resql))) print '<div><label><input type="checkbox" name="features['.$obj->code.']" value="1" '.(!empty($obj->enabled)?'checked':'').'> '.dol_escape_htmltag($obj->label).' ('.dol_escape_htmltag($obj->code).')</label></div>';
print '<h3>'.$langs->trans('LimitsCatalog').'</h3>';
$resql = $db->query("SELECT l.code,l.label,COALESCE(tl.value,l.default_value) current_value FROM ".MAIN_DB_PREFIX."saas_limits l LEFT JOIN ".MAIN_DB_PREFIX."saas_tenant_limits tl ON tl.limit_code=l.code AND tl.entity_id=".$entityId." ORDER BY l.module_code,l.code");
while ($resql && ($obj = $db->fetch_object($resql))) print '<div style="margin-bottom:8px;"><label>'.dol_escape_htmltag($obj->label).' ('.dol_escape_htmltag($obj->code).')</label><br><input type="number" min="0" name="limits['.$obj->code.']" value="'.(int)$obj->current_value.'"></div>';
print '<div style="margin-top:14px;"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div></form>';
print dol_get_fiche_end(); llxFooter(); $db->close();
