<?php
if (!defined('NOLOGIN')) define('NOLOGIN', 1);
if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', 1);
if (!defined('NOBROWSERNOTIF')) define('NOBROWSERNOTIF', 1);
if (!defined('NOIPCHECK')) define('NOIPCHECK', 1);

require __DIR__ . '/../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
require_once __DIR__ . '/../class/SaasAccessService.php';
require_once __DIR__ . '/../class/service/KafoApiTokenService.php';
require_once __DIR__ . '/../class/service/KafoAuditLogService.php';

function kafoApiJson($payload, $status = 200)
{
    if (!headers_sent()) {
        http_response_code((int) $status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function kafoApiGetBearerToken()
{
    $headers = array();
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    }
    $authorization = '';
    foreach ($headers as $key => $value) {
        if (strtolower((string) $key) === 'authorization') {
            $authorization = (string) $value;
            break;
        }
    }
    if ($authorization === '' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $authorization = (string) $_SERVER['HTTP_AUTHORIZATION'];
    }
    if (preg_match('/Bearer\s+(.+)$/i', $authorization, $m)) {
        return trim((string) $m[1]);
    }
    if (!empty($_GET['token'])) {
        return trim((string) $_GET['token']);
    }
    return '';
}

function kafoApiReadJsonBody()
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return array();
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : array();
}

function kafoApiRequireAuth($needWrite = false)
{
    global $db;

    $tokenService = new KafoApiTokenService($db);
    $token = kafoApiGetBearerToken();

    if (!$tokenService->validateToken($token)) {
        kafoApiJson(array('success' => false, 'error' => 'Unauthorized'), 401);
    }

    $ip = !empty($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    $tokenService->touchLastUsed($ip);

    $access = new SaasAccessService($db);

    // طالما التوكن صحيح، اسمح بالقراءة والكتابة حسب الحاجة
    // في هذه النسخة: التوكن الثابت يملك write access
    return array(
        'entity_id' => is_object($GLOBALS['conf']) && isset($GLOBALS['conf']->entity) ? (int) $GLOBALS['conf']->entity : 1,
        'token_service' => $tokenService,
        'access' => $access,
        'token_valid' => true,
        'write_allowed' => true,
    );
}
function kafoApiGetUserRows($entityId, $userId = 0)
{
    global $db;

    $sql = "SELECT u.rowid, u.login, u.firstname, u.lastname, u.email, u.admin, u.statut, u.entity
            FROM ".MAIN_DB_PREFIX."user u
            WHERE u.entity IN (0, ".((int) $entityId).")";
    if ($userId > 0) {
        $sql .= " AND u.rowid = ".((int) $userId);
    }
    $sql .= " ORDER BY u.login ASC";

    $rows = array();
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $rows[] = array(
                'id' => (int) $obj->rowid,
                'login' => (string) $obj->login,
                'firstname' => (string) $obj->firstname,
                'lastname' => (string) $obj->lastname,
                'full_name' => trim(((string) $obj->firstname) . ' ' . ((string) $obj->lastname)),
                'email' => (string) $obj->email,
                'admin' => ((int) $obj->admin === 1),
                'status' => (int) $obj->statut,
                'entity' => (int) $obj->entity,
            );
        }
    }

    return $rows;
}
