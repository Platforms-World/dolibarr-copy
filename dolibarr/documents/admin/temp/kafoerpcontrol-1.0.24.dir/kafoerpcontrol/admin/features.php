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
saascoreRequireAdminRight('write');
saascoreSyncKnownIntegrations($db);
$action = GETPOST('action', 'aZ09');
if ($action === 'add') {
    $code = trim(GETPOST('code', 'alpha'));
    $label = trim(GETPOST('label', 'restricthtml'));
    $module_code = trim(GETPOST('module_code', 'alpha'));
    $description = trim(GETPOST('description', 'restricthtml'));
    if ($code !== '') {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."saas_features(code,label,module_code,description,date_created) VALUES ('".$db->escape($code)."','".$db->escape($label)."','".$db->escape($module_code)."','".$db->escape($description)."','".dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')."')";
        $db->query($sql);
    }
}
llxHeader('', $langs->trans('FeaturesCatalog'));
print load_fiche_titre($langs->trans('FeaturesCatalog'), '', 'title_setup');
print dol_get_fiche_head(saascoreAdminPrepareHead(), 'features', 'kafo-ERP-Control', -1, 'generic');
print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="add">';
print '<table class="noborder centpercent"><tr class="liste_titre"><th>code</th><th>label</th><th>module_code</th><th>description</th><th>&nbsp;</th></tr><tr>';
print '<td><input type="text" name="code"></td><td><input type="text" name="label"></td><td><input type="text" name="module_code"></td><td><input type="text" class="minwidth300" name="description"></td><td><input class="button" type="submit" value="'.$langs->trans('Add').'"></td></tr></table></form><br>';
$resql = $db->query("SELECT code,label,module_code,description FROM ".MAIN_DB_PREFIX."saas_features ORDER BY module_code, code");
print '<table class="noborder centpercent"><tr class="liste_titre"><th>code</th><th>label</th><th>module_code</th><th>description</th></tr>';
while ($resql && ($obj = $db->fetch_object($resql))) print '<tr class="oddeven"><td>'.dol_escape_htmltag($obj->code).'</td><td>'.dol_escape_htmltag($obj->label).'</td><td>'.dol_escape_htmltag($obj->module_code).'</td><td>'.dol_escape_htmltag($obj->description).'</td></tr>';
print '</table>'.dol_get_fiche_end(); llxFooter(); $db->close();


