<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once dol_buildpath('/custom/kafo-ERP-Control/core/lib/saascore.lib.php', 0);

$langs->loadLangs(array('admin', 'saascore@kafo-ERP-Control'));
saascoreRequireAdminRight('read');
saascoreSyncKnownIntegrations($db);

llxHeader('', $langs->trans('kafo-ERP-Control'));
print load_fiche_titre($langs->trans('kafo-ERP-Control'), '', 'title_setup');
$head = saascoreAdminPrepareHead();
print dol_get_fiche_head($head, 'general', $langs->trans('kafo-ERP-Control'), -1, 'generic');

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