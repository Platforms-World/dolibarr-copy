<?php
if (!defined('NOLOGIN')) define('NOLOGIN', 1);
if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', 1);
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', 1);
if (!defined('NOIPCHECK')) define('NOIPCHECK', 1);
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', 1);
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', 1);
if (!defined('NOSCANPOSTFORINJECTION')) define('NOSCANPOSTFORINJECTION', 1);
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
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('success' => false, 'error' => 'Unable to locate Dolibarr main.inc.php'));
    exit;
}
require_once $maininc;
require_once dol_buildpath('/kafoerpcontrol/class/SaasApiAuthService.php', 0);
header('Content-Type: application/json; charset=utf-8');

function kafoApiRespond($data, $status = 200)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function kafoApiRequestData()
{
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    return is_array($json) ? $json : array();
}

$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
$input = kafoApiRequestData();
$action = GETPOST('action', 'aZ09');
if ($action === '' && isset($input['action'])) {
    $action = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $input['action']);
}
$token = '';
if (!empty($_SERVER['HTTP_X_API_TOKEN'])) {
    $token = trim((string) $_SERVER['HTTP_X_API_TOKEN']);
} elseif (!empty($_SERVER['REDIRECT_HTTP_X_API_TOKEN'])) {
    $token = trim((string) $_SERVER['REDIRECT_HTTP_X_API_TOKEN']);
} elseif (!empty($_SERVER['HTTP_API_TOKEN'])) {
    $token = trim((string) $_SERVER['HTTP_API_TOKEN']);
} elseif (!empty($_SERVER['REDIRECT_HTTP_API_TOKEN'])) {
    $token = trim((string) $_SERVER['REDIRECT_HTTP_API_TOKEN']);
} elseif (function_exists('getallheaders')) {
    $headers = getallheaders();
    if (!empty($headers['X-API-Token'])) {
        $token = trim((string) $headers['X-API-Token']);
    } elseif (!empty($headers['x-api-token'])) {
        $token = trim((string) $headers['x-api-token']);
    } elseif (!empty($headers['API-Token'])) {
        $token = trim((string) $headers['API-Token']);
    } elseif (!empty($headers['api-token'])) {
        $token = trim((string) $headers['api-token']);
    }
}
if ($token === '' && ($t = GETPOST('api_token', 'alphanohtml')) !== '') {
    $token = trim((string) $t);
} elseif ($token === '' && isset($input['api_token'])) {
    $token = trim((string) $input['api_token']);
}

$apiAuth = new SaasApiAuthService($db);
$auth = $apiAuth->authenticate($conf->entity, $token);
if (!$auth) {
    kafoApiRespond(array('success' => false, 'error' => 'Invalid API token'), 401);
}

$needRead = array('catalog', 'user_permissions', 'me', 'ping');
$needWrite = array('permission_set');
$needUpdate = array('permission_set');

if (in_array($action, $needRead, true) && empty($auth['can_read'])) {
    kafoApiRespond(array('success' => false, 'error' => 'Read permission denied'), 403);
}
if ($method === 'POST' && in_array($action, $needWrite, true) && empty($auth['can_write'])) {
    kafoApiRespond(array('success' => false, 'error' => 'Write permission denied'), 403);
}
if (in_array($action, $needUpdate, true) && empty($auth['can_update'])) {
    kafoApiRespond(array('success' => false, 'error' => 'Update permission denied'), 403);
}

if ($action === '' || $action === 'ping') {
    kafoApiRespond(array('success' => true, 'action' => 'ping', 'token_label' => $auth['label'], 'token_type' => $auth['type']));
}

if ($action === 'me') {
    kafoApiRespond(array('success' => true, 'token' => array(
        'label' => $auth['label'],
        'type' => $auth['type'],
        'can_read' => (int) $auth['can_read'],
        'can_write' => (int) $auth['can_write'],
        'can_update' => (int) $auth['can_update'],
    )));
}

if ($action === 'catalog') {
    $type = GETPOST('type', 'aZ09');
    if ($type === '' && isset($input['type'])) {
        $type = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $input['type']);
    }
    $map = array(
        'modules' => array('table' => MAIN_DB_PREFIX.'saas_modules', 'key' => 'code'),
        'features' => array('table' => MAIN_DB_PREFIX.'saas_features', 'key' => 'code'),
        'limits' => array('table' => MAIN_DB_PREFIX.'saas_limits', 'key' => 'code'),
        'permissions' => array('table' => MAIN_DB_PREFIX.'saas_permissions', 'key' => 'code'),
    );
    if (!isset($map[$type])) {
        kafoApiRespond(array('success' => false, 'error' => 'Unknown catalog type'), 400);
    }
    $rows = array();
    $sql = "SELECT * FROM ".$map[$type]['table']." ORDER BY ".$map[$type]['key']." ASC";
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $rows[] = $obj;
        }
    }
    kafoApiRespond(array('success' => true, 'type' => $type, 'rows' => $rows));
}

if ($action === 'user_permissions') {
    $fkUser = GETPOST('fk_user', 'int');
    if ($fkUser <= 0 && isset($input['fk_user'])) {
        $fkUser = (int) $input['fk_user'];
    }
    if ($fkUser <= 0) {
        kafoApiRespond(array('success' => false, 'error' => 'fk_user is required'), 400);
    }
    $rows = array();
    $sql = "SELECT rowid, entity_id, fk_user, permission_code, allowed, date_created, tms FROM ".MAIN_DB_PREFIX."saas_user_permissions WHERE entity_id = ".((int) $conf->entity)." AND fk_user = ".((int) $fkUser)." ORDER BY permission_code ASC";
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $rows[] = $obj;
        }
    }
    kafoApiRespond(array('success' => true, 'fk_user' => $fkUser, 'rows' => $rows));
}

if ($action === 'permission_set') {
    if ($method !== 'POST') {
        kafoApiRespond(array('success' => false, 'error' => 'POST required'), 405);
    }
    $fkUser = isset($input['fk_user']) ? (int) $input['fk_user'] : GETPOST('fk_user', 'int');
    $permissionCode = isset($input['permission_code']) ? trim((string) $input['permission_code']) : trim(GETPOST('permission_code', 'alphanohtml'));
    $allowed = isset($input['allowed']) ? (int) $input['allowed'] : (int) GETPOST('allowed', 'int');

    if ($fkUser <= 0 || $permissionCode === '') {
        kafoApiRespond(array('success' => false, 'error' => 'fk_user and permission_code are required'), 400);
    }

    $sqlCheck = "SELECT rowid FROM ".MAIN_DB_PREFIX."saas_permissions WHERE code = '".$db->escape($permissionCode)."' LIMIT 1";
    $resCheck = $db->query($sqlCheck);
    if (!$resCheck || !$db->fetch_object($resCheck)) {
        kafoApiRespond(array('success' => false, 'error' => 'Unknown permission_code'), 400);
    }

    $sql = "INSERT INTO ".MAIN_DB_PREFIX."saas_user_permissions(entity_id, fk_user, permission_code, allowed, date_created)
            VALUES (".((int) $conf->entity).", ".((int) $fkUser).", '".$db->escape($permissionCode)."', ".($allowed ? 1 : 0).", '".dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')."')
            ON DUPLICATE KEY UPDATE allowed = VALUES(allowed), tms = CURRENT_TIMESTAMP";
    if (!$db->query($sql)) {
        kafoApiRespond(array('success' => false, 'error' => $db->lasterror()), 500);
    }

    kafoApiRespond(array(
        'success' => true,
        'saved' => array(
            'entity_id' => (int) $conf->entity,
            'fk_user' => $fkUser,
            'permission_code' => $permissionCode,
            'allowed' => $allowed ? 1 : 0,
        )
    ));
}

kafoApiRespond(array('success' => false, 'error' => 'Unknown action'), 400);
