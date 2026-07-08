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
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once dol_buildpath('/kafoerpcontrol/core/lib/saascore.lib.php', 0);

$langs->loadLangs(array('admin', 'kafoerpcontrol@kafoerpcontrol'));
saascoreRequireAdminRight('read');
saascoreSyncKnownIntegrations($db);

llxHeader('', 'kafo-ERP-Control');
print load_fiche_titre('kafo-ERP-Control', '', 'title_setup');
$head = saascoreAdminPrepareHead();
print dol_get_fiche_head($head, 'general', 'kafo-ERP-Control', -1, 'generic');

print '<div class="opacitymedium">';
print '<p>'.$langs->trans('kafo-ERP-ControlIntro').'</p>';
print '<ul>';
print '<li>'.$langs->trans('ModulesCatalog').'</li>';
print '<li>'.$langs->trans('FeaturesCatalog').'</li>';
print '<li>'.$langs->trans('LimitsCatalog').'</li>';
print '<li>'.$langs->trans('PermissionsCatalog').'</li>';
print '<li>'.$langs->trans('PermissionsControl').'</li>';
print '<li>'.$langs->trans('TenantConfiguration').'</li>';
print '</ul>';
print '</div>';

print dol_get_fiche_end();
llxFooter();
$db->close();