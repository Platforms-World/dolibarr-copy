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
require_once dol_buildpath('/kafoerpcontrol/class/SaasRoleService.php', 0);
$langs->loadLangs(array('admin', 'kafoerpcontrol@kafoerpcontrol'));
saascoreRequireAdminRight('rolemanage');
saascoreSyncKnownIntegrations($db);
$service = new SaasRoleService($db);
$action = GETPOST('action', 'aZ09');
$entityId = (int) GETPOST('entity_id', 'int'); if ($entityId <= 0) $entityId = (int) $conf->entity;
if ($action === 'add') {
    $code = trim(GETPOST('code', 'alpha'));
    $label = trim(GETPOST('label', 'restricthtml'));
    $description = trim(GETPOST('description', 'restricthtml'));
    $is_system = GETPOST('is_system', 'int');
    if ($code !== '') $service->createRole($entityId, $code, $label, $description, $is_system);
}
llxHeader('', $langs->trans('RolesCatalog'));
print load_fiche_titre($langs->trans('RolesCatalog'), '', 'title_setup');
print dol_get_fiche_head(saascoreAdminPrepareHead(), 'roles', 'kafo-ERP-Control', -1, 'generic');
print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="add">';
print '<div style="margin-bottom:8px;">Entity ID <input type="number" name="entity_id" value="'.$entityId.'"></div>';
print '<table class="noborder centpercent"><tr class="liste_titre"><th>code</th><th>label</th><th>description</th><th>is_system</th><th>&nbsp;</th></tr><tr>';
print '<td><input type="text" name="code"></td><td><input type="text" name="label"></td><td><input type="text" class="minwidth300" name="description"></td><td><input type="checkbox" name="is_system" value="1"></td><td><input class="button" type="submit" value="'.$langs->trans('Add').'"></td></tr></table></form><br>';
$resql = $db->query("SELECT entity_id,code,label,description,is_system FROM ".MAIN_DB_PREFIX."saas_roles WHERE entity_id = ".$entityId." ORDER BY code");
print '<table class="noborder centpercent"><tr class="liste_titre"><th>entity_id</th><th>code</th><th>label</th><th>description</th><th>is_system</th></tr>';
while ($resql && ($obj = $db->fetch_object($resql))) print '<tr class="oddeven"><td>'.(int)$obj->entity_id.'</td><td>'.dol_escape_htmltag($obj->code).'</td><td>'.dol_escape_htmltag($obj->label).'</td><td>'.dol_escape_htmltag($obj->description).'</td><td>'.(int)$obj->is_system.'</td></tr>';
print '</table>'.dol_get_fiche_end(); llxFooter(); $db->close();


