<?php
require_once DOL_DOCUMENT_ROOT . '/custom/poscore/class/service/PosAccessGuard.php';
require_once DOL_DOCUMENT_ROOT . '/custom/poscore/core/lib/poscore.lib.php';

function poscore_bootstrap_page($permission, $dolRight = 'poscore->admin', $feature = null, $limit = null, $currentCount = null)
{
    global $db, $user, $conf, $langs;

    $langs->loadLangs(array('poscore@poscore', 'admin', 'stocks', 'users'));

    $guard = new PosAccessGuard($db, $user, $conf);
    $guard->guardOrDeny(array(
        'permission' => $permission,
        'feature' => $feature,
        'limit' => $limit,
        'current_count' => $currentCount,
        'dol_right' => $dolRight
    ));

    return $guard;
}
