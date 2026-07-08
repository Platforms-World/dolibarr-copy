<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once dol_buildpath('/kafo_ERP_Control/core/lib/saascore.lib.php', 0);

$langs->loadLangs(array('admin', 'saascore@kafo_ERP_Control'));
saascoreRequireAdminRight('read');
saascoreSyncKnownIntegrations($db);

llxHeader('', $langs->trans('SaaSCoreSetup'));
print load_fiche_titre($langs->trans('SaaSCoreSetup'), '', 'title_setup');
$head = saascoreAdminPrepareHead();
print dol_get_fiche_head($head, 'general', $langs->trans('SaaSCoreSetup'), -1, 'generic');

print '<div class="opacitymedium">';
print '<p>'.$langs->trans('SaaSCoreIntro').'</p>';
print '<ul>';
print '<li>'.$langs->trans('ModulesCatalog').'</li>';
print '<li>'.$langs->trans('FeaturesCatalog').'</li>';
print '<li>'.$langs->trans('LimitsCatalog').'</li>';
print '<li>'.$langs->trans('PermissionsCatalog').'</li>';
print '<li>'.$langs->trans('TenantConfiguration').'</li>';
print '</ul>';
print '</div>';

print dol_get_fiche_end();
llxFooter();
$db->close();


