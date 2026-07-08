<?php
/**
 * Exchange AJAX controller.
 */
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');
if (!defined('NOREQUIREAJAX')) define('NOREQUIREAJAX', '1');

if (!defined('DOL_DOCUMENT_ROOT')) {
    $mainPath = __DIR__ . '/../../main.inc.php';
    if (!file_exists($mainPath)) {
        $mainPath = __DIR__ . '/../../../main.inc.php';
    }
    require $mainPath;
}

require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposAccess.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposAudit.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposInputValidator.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposUtf8.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposRefundService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposExchangeService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposUserAccess.class.php';

$langs->loadLangs(array('cashdesk', 'main', 'bills', 'takeposcustom@takepos'));

function takeposExchangeJson($payload, $httpCode = 200)
{
    $payload = is_array($payload) ? $payload : array();
    $success = !empty($payload['success']);

    if (!array_key_exists('message', $payload)) {
        $payload['message'] = $success ? $langs->trans('OK') : $langs->trans('TakeposCommonRequestFailed');
    }
    if (!array_key_exists('errors', $payload)) {
        $payload['errors'] = $success ? array() : array(!empty($payload['error']) ? (string) $payload['error'] : 'request_failed');
    }
    if (!array_key_exists('data', $payload)) {
        $data = array();
        foreach (array('rows', 'result', 'refund', 'lines') as $key) {
            if (array_key_exists($key, $payload)) {
                $data[$key] = $payload[$key];
            }
        }
        $payload['data'] = $data;
    }

    if (!headers_sent()) {
        http_response_code((int) $httpCode);
        header('Content-Type: application/json; charset=UTF-8');
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function takeposExchangeRequireToken($db, $user)
{
    $token = TakeposInputValidator::normalizeUtf8Text(GETPOST('token', 'none'), 128, true);
    $sessionToken = isset($_SESSION['newtoken']) ? (string) $_SESSION['newtoken'] : '';
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        TakeposAccess::denyJson($db, $user, $langs->trans('TakeposCommonInvalidCsrfToken'), array('endpoint' => 'ajax/exchange.php', 'action' => GETPOST('action', 'aZ09')));
    }
}

function takeposExchangeParseJsonArray($raw)
{
    $raw = trim((string) $raw);
    if ($raw === '') return array();
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

TakeposUtf8::bootstrapConnection($db);

$action = GETPOST('action', 'aZ09');
$terminalFromSession = isset($_SESSION['takeposterminal']) ? (string) $_SESSION['takeposterminal'] : '1';
$context = array('endpoint' => 'ajax/exchange.php', 'requested_action' => $action);

if ($action === '') {
    takeposExchangeJson(array('success' => false, 'error' => 'missing_action', 'message' => $langs->trans('TakeposCommonMissingAction')), 400);
}

$canAccessExchangeDesk = (!empty($user->admin)
    || TakeposUserAccess::userHasAnyPermission($db, $user, array('takepos.exchange.process', 'takepos.refund.view')));
if (!$canAccessExchangeDesk) {
    TakeposAccess::denyJson($db, $user, $langs->trans('TakeposExchangeAccessDenied'), $context);
}
TakeposAccess::requireFeature($db, 'takepos.returns', $user, true, $context);
TakeposAccess::requireAjaxAccess($db, $user, 'takepos.exchanges', 'takepos.use', (int) $terminalFromSession, $context);

try {
    if ($action === 'search_invoices') {
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.returns', 'takepos.use', (int) $terminalFromSession, $context);
        $filters = array(
            'invoice_id' => GETPOSTINT('invoice_id'),
            'invoice_ref' => TakeposInputValidator::normalizeUtf8Text(GETPOST('invoice_ref', 'none'), 64, true),
            'date_from' => TakeposInputValidator::normalizeUtf8Text(GETPOST('date_from', 'none'), 10, true),
            'date_to' => TakeposInputValidator::normalizeUtf8Text(GETPOST('date_to', 'none'), 10, true),
        );
        $rows = TakeposRefundService::searchOriginalInvoices($db, $user, $filters, 80);
        takeposExchangeJson(array('success' => true, 'rows' => $rows));
    }

    if ($action === 'refundable_lines') {
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.returns', 'takepos.use', (int) $terminalFromSession, $context);
        $invoiceId = GETPOSTINT('invoice_id');
        $data = TakeposRefundService::listRefundableLines($db, $user, $invoiceId);
        takeposExchangeJson(array('success' => true, 'data' => $data));
    }

    if ($action === 'create_exchange') {
        takeposExchangeRequireToken($db, $user);
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.exchanges', 'takepos.use', (int) $terminalFromSession, $context);

        $returnLines = takeposExchangeParseJsonArray(GETPOST('return_lines_json', 'none'));
        $newLines = takeposExchangeParseJsonArray(GETPOST('new_lines_json', 'none'));
        if ($returnLines === null || $newLines === null) {
            takeposExchangeJson(array('success' => false, 'error' => 'invalid_payload', 'message' => $langs->trans('TakeposExchangeInvalidPayload')), 422);
        }

        $result = TakeposExchangeService::createExchange($db, $user, array(
            'original_invoice_id' => GETPOSTINT('invoice_id'),
            'reason_code' => TakeposInputValidator::normalizeUtf8Text(GETPOST('reason_code', 'none'), 64, true),
            'note' => TakeposInputValidator::normalizeUtf8Text(GETPOST('note', 'none'), 255, false),
            'settlement_method' => GETPOST('settlement_method', 'aZ09'),
            'restock_default' => GETPOSTINT('restock_default'),
            'return_lines' => $returnLines,
            'new_lines' => $newLines,
            'manager_login' => GETPOST('manager_login', 'none'),
            'manager_password' => GETPOST('manager_password', 'none'),
            'manager_barcode' => GETPOST('manager_barcode', 'none'),
        ));

        takeposExchangeJson(array('success' => true, 'message' => $langs->trans('TakeposExchangeProcessedSuccess'), 'result' => $result));
    }

    takeposExchangeJson(array('success' => false, 'error' => 'unsupported_action', 'message' => $langs->trans('TakeposCommonUnsupportedAction')), 400);
} catch (Throwable $e) {
    takeposExchangeJson(array('success' => false, 'error' => 'runtime_error', 'message' => $e->getMessage()), 500);
}
