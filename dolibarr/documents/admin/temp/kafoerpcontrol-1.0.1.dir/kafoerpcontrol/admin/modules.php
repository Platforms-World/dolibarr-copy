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
$actorUserId = (is_object($user) && !empty($user->id) ? (int) $user->id : 0);
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('checkToken') && !checkToken()) {
        accessforbidden('Invalid CSRF token');
    }

    $code = trim(GETPOST('code', 'alpha'));
    $label = trim(GETPOST('label', 'restricthtml'));
    $description = trim(GETPOST('description', 'restricthtml'));
    $is_core = GETPOST('is_core', 'int');
    if ($code !== '') {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."saas_modules(code,label,description,is_core,date_created) VALUES ('".$db->escape($code)."','".$db->escape($label)."','".$db->escape($description)."',".(int)$is_core.",'".dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')."')";
        if ($db->query($sql)) {
            saascoreAuditLogAction(
                $db,
                $actorUserId,
                0,
                'module_catalog_add',
                'module',
                $code,
                null,
                json_encode(array('label' => $label, 'is_core' => (int) $is_core)),
                'Module catalog item added',
                array('context' => 'admin/modules.php')
            );
        }
    }
}
llxHeader('', $langs->trans('ModulesCatalog'));
print load_fiche_titre($langs->trans('ModulesCatalog'), '', 'title_setup');
print dol_get_fiche_head(saascoreAdminPrepareHead(), 'modules', 'kafo-ERP-Control', -1, 'generic');
print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="add">';
print '<table class="noborder centpercent"><tr class="liste_titre"><th>code</th><th>label</th><th>description</th><th>is_core</th><th>&nbsp;</th></tr><tr>';
print '<td><input type="text" name="code"></td><td><input type="text" name="label"></td><td><input type="text" class="minwidth300" name="description"></td><td><input type="checkbox" name="is_core" value="1"></td><td><input class="button" type="submit" value="'.$langs->trans('Add').'"></td></tr></table></form><br>';
$resql = $db->query("SELECT code,label,description,is_core FROM ".MAIN_DB_PREFIX."saas_modules ORDER BY code");
print '<table class="noborder centpercent"><tr class="liste_titre"><th>code</th><th>label</th><th>description</th><th>is_core</th></tr>';
while ($resql && ($obj = $db->fetch_object($resql))) print '<tr class="oddeven"><td>'.dol_escape_htmltag($obj->code).'</td><td>'.dol_escape_htmltag($obj->label).'</td><td>'.dol_escape_htmltag($obj->description).'</td><td>'.(int)$obj->is_core.'</td></tr>';
print '</table>'.dol_get_fiche_end(); llxFooter(); $db->close();
