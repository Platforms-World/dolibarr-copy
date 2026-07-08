<?php
/**
 * Centralized manager override approval endpoint.
 */
if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
}

if (!defined('DOL_DOCUMENT_ROOT')) {
    $mainPath = __DIR__ . '/../../main.inc.php';
    if (!file_exists($mainPath)) {
        $mainPath = __DIR__ . '/../../../main.inc.php';
    }
    require $mainPath;
}

require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposAccess.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposAudit.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposManagerOverrideService.class.php';

$langs->loadLangs(array('cashdesk', 'main', 'takeposcustom@takepos'));

function takeposManagerOverrideJson($payload, $httpCode = 200)
{
    if (!headers_sent()) {
        http_response_code((int) $httpCode);
    }

    if (function_exists('top_httphead')) {
        top_httphead('application/json');
    } else {
        header('Content-Type: application/json; charset=UTF-8');
    }

    echo json_encode($payload);
    exit;
}

$terminalFromSession = isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : 0;
$context = array('endpoint' => 'ajax/manager_override.php');

TakeposAccess::requireAjaxAccess(
    $db,
    $user,
    'takepos.manager_override',
    'takepos.use',
    $terminalFromSession,
    $context
);

$token = (string) GETPOST('token', 'alpha');
$sessionToken = isset($_SESSION['newtoken']) ? (string) $_SESSION['newtoken'] : '';
if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
    TakeposAccess::denyJson(
        $db,
        $user,
        $langs->trans('TakeposCommonInvalidCsrfToken'),
        array('endpoint' => 'ajax/manager_override.php'),
        403
    );
}

try {
    $result = TakeposManagerOverrideService::approveFromPayload($db, $user, array(
        'override_action' => GETPOST('override_action', 'aZ09'),
        'invoice_id' => GETPOSTINT('invoiceid'),
        'line_id' => GETPOSTINT('idline'),
        'requested_number' => GETPOST('requested_number', 'none'),
        'manager_barcode' => GETPOST('manager_barcode', 'none'),
        'manager_login' => GETPOST('manager_login', 'none'),
        'manager_password' => GETPOST('manager_password', 'none'),
    ));

    if (empty($result['success'])) {
        takeposManagerOverrideJson(
            array(
                'status' => 'error',
                'success' => false,
                'message' => (string) $result['message'],
                'error' => isset($result['data']['reason']) ? (string) $result['data']['reason'] : 'manager_override_failed',
            ),
            isset($result['http_code']) ? (int) $result['http_code'] : 403
        );
    }

    takeposManagerOverrideJson(
        array(
            'status' => 'ok',
            'success' => true,
            'message' => (string) $result['message'],
            'override_action' => isset($result['data']['override_action']) ? $result['data']['override_action'] : '',
            'invoice_id' => isset($result['data']['invoice_id']) ? (int) $result['data']['invoice_id'] : 0,
            'line_id' => isset($result['data']['line_id']) ? (int) $result['data']['line_id'] : 0,
            'manager_id' => isset($result['data']['manager_id']) ? (int) $result['data']['manager_id'] : 0,
            'token' => isset($result['data']['token']) ? (string) $result['data']['token'] : '',
        ),
        200
    );
} catch (Throwable $e) {
    TakeposAudit::logEvent(
        $db,
        $user,
        'manager_override_rejected',
        TakeposAudit::SEVERITY_WARNING,
        array('reason' => 'runtime_error', 'error' => $e->getMessage()),
        'Manager override runtime failure'
    );

    takeposManagerOverrideJson(
        array(
            'status' => 'error',
            'success' => false,
            'message' => $langs->trans('TakeposManagerOverrideRuntimeFailed'),
            'error' => 'runtime_error',
        ),
        500
    );
}
