<?php
require '../../../main.inc.php';
require_once dol_buildpath('/saascore/core/lib/saascore.lib.php', 0);
$langs->loadLangs(array('admin', 'saascore@saascore'));
saascoreRequireAdminRight('write');
$action = GETPOST('action', 'aZ09');
if ($action === 'add') {
    $code = trim(GETPOST('code', 'alpha'));
    $label = trim(GETPOST('label', 'restricthtml'));
    $description = trim(GETPOST('description', 'restricthtml'));
    $is_core = GETPOST('is_core', 'int');
    if ($code !== '') {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."saas_modules(code,label,description,is_core,date_created) VALUES ('".$db->escape($code)."','".$db->escape($label)."','".$db->escape($description)."',".(int)$is_core.",'".dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')."')";
        $db->query($sql);
    }
}
llxHeader('', $langs->trans('ModulesCatalog'));
print load_fiche_titre($langs->trans('ModulesCatalog'), '', 'title_setup');
print dol_get_fiche_head(saascoreAdminPrepareHead(), 'modules', $langs->trans('SaaSCoreSetup'), -1, 'generic');
print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="add">';
print '<table class="noborder centpercent"><tr class="liste_titre"><th>code</th><th>label</th><th>description</th><th>is_core</th><th>&nbsp;</th></tr><tr>';
print '<td><input type="text" name="code"></td><td><input type="text" name="label"></td><td><input type="text" class="minwidth300" name="description"></td><td><input type="checkbox" name="is_core" value="1"></td><td><input class="button" type="submit" value="'.$langs->trans('Add').'"></td></tr></table></form><br>';
$resql = $db->query("SELECT code,label,description,is_core FROM ".MAIN_DB_PREFIX."saas_modules ORDER BY code");
print '<table class="noborder centpercent"><tr class="liste_titre"><th>code</th><th>label</th><th>description</th><th>is_core</th></tr>';
while ($resql && ($obj = $db->fetch_object($resql))) print '<tr class="oddeven"><td>'.dol_escape_htmltag($obj->code).'</td><td>'.dol_escape_htmltag($obj->label).'</td><td>'.dol_escape_htmltag($obj->description).'</td><td>'.(int)$obj->is_core.'</td></tr>';
print '</table>'.dol_get_fiche_end(); llxFooter(); $db->close();
