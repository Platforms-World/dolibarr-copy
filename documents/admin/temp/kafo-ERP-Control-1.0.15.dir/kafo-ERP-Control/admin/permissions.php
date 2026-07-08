<?php
require '../../../main.inc.php';
require_once dol_buildpath('/custom/kafo-ERP-Control/core/lib/saascore.lib.php', 0);
$langs->loadLangs(array('admin', 'saascore@kafo-ERP-Control'));
saascoreRequireAdminRight('rolemanage');
saascoreSyncKnownIntegrations($db);
$action = GETPOST('action', 'aZ09');
if ($action === 'add') {
    $code = trim(GETPOST('code', 'alpha'));
    $label = trim(GETPOST('label', 'restricthtml'));
    $module_code = trim(GETPOST('module_code', 'alpha'));
    $description = trim(GETPOST('description', 'restricthtml'));
    if ($code !== '') {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."saas_permissions(code,label,module_code,description,date_created) VALUES ('".$db->escape($code)."','".$db->escape($label)."','".$db->escape($module_code)."','".$db->escape($description)."','".dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')."')";
        $db->query($sql);
    }
}
llxHeader('', $langs->trans('PermissionsCatalog'));
print load_fiche_titre($langs->trans('PermissionsCatalog'), '', 'title_setup');
print dol_get_fiche_head(saascoreAdminPrepareHead(), 'permissions', $langs->trans('kafo-ERP-Control'), -1, 'generic');
print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="add">';
print '<table class="noborder centpercent"><tr class="liste_titre"><th>code</th><th>label</th><th>module_code</th><th>description</th><th>&nbsp;</th></tr><tr>';
print '<td><input type="text" name="code"></td><td><input type="text" name="label"></td><td><input type="text" name="module_code"></td><td><input type="text" class="minwidth300" name="description"></td><td><input class="button" type="submit" value="'.$langs->trans('Add').'"></td></tr></table></form><br>';
$resql = $db->query("SELECT code,label,module_code,description FROM ".MAIN_DB_PREFIX."saas_permissions ORDER BY module_code, code");
print '<table class="noborder centpercent"><tr class="liste_titre"><th>code</th><th>label</th><th>module_code</th><th>description</th></tr>';
while ($resql && ($obj = $db->fetch_object($resql))) print '<tr class="oddeven"><td>'.dol_escape_htmltag($obj->code).'</td><td>'.dol_escape_htmltag($obj->label).'</td><td>'.dol_escape_htmltag($obj->module_code).'</td><td>'.dol_escape_htmltag($obj->description).'</td></tr>';
print '</table>'.dol_get_fiche_end(); llxFooter(); $db->close();


