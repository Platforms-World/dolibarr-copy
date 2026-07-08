<?php
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');
if (!defined('NOREQUIREAJAX')) define('NOREQUIREAJAX', '1');
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposAccess.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposDashboardService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/lib/takepos_lang.php';

takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
$langs->loadLangs(array('main', 'takeposcustom@takepos'));

TakeposAccess::requireAjaxAccess(
    $db,
    $user,
    'takepos.dashboard.pro',
    'takepos.dashboard.view',
    isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null,
    array('page' => 'ajax/dashboard_data.php')
);

header('Content-Type: application/json; charset=utf-8');

$service = new TakeposDashboardService($db, $langs, isset($conf->entity) ? (int) $conf->entity : 1);
$payload = $service->getDataset(array(
    'date_from' => GETPOST('date_from', 'alpha'),
    'date_to' => GETPOST('date_to', 'alpha'),
));

echo json_encode(array('success' => true, 'data' => $payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
