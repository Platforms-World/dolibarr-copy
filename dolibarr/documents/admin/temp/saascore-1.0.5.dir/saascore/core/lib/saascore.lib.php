<?php
function saascoreAdminPrepareHead()
{
    global $langs;
    $h = 0;
    $head = array();
    $head[$h++] = array(dol_buildpath('/saascore/admin/index.php', 1), $langs->trans('General'), 'general');
    $head[$h++] = array(dol_buildpath('/saascore/admin/modules.php', 1), $langs->trans('ModulesCatalog'), 'modules');
    $head[$h++] = array(dol_buildpath('/saascore/admin/features.php', 1), $langs->trans('FeaturesCatalog'), 'features');
    $head[$h++] = array(dol_buildpath('/saascore/admin/limits.php', 1), $langs->trans('LimitsCatalog'), 'limits');
    $head[$h++] = array(dol_buildpath('/saascore/admin/permissions.php', 1), $langs->trans('PermissionsCatalog'), 'permissions');
    $head[$h++] = array(dol_buildpath('/saascore/admin/bundles.php', 1), $langs->trans('BundlesCatalog'), 'bundles');
    $head[$h++] = array(dol_buildpath('/saascore/admin/roles.php', 1), $langs->trans('RolesCatalog'), 'roles');
    $head[$h++] = array(dol_buildpath('/saascore/admin/tenant.php', 1), $langs->trans('TenantConfiguration'), 'tenant');
    return $head;
}

function saascoreRequireAdminRight($right = 'read')
{
    global $user;
    if (!$user->admin && !$user->hasRight('saascore', $right)) {
        accessforbidden();
    }
}
