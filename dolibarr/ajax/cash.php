<?php
/**
 * Cash movement AJAX controller.
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
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposInputValidator.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposShiftService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposCashService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposTerminalService.class.php';

$langs->loadLangs(array('cashdesk', 'main', 'takeposcustom@takepos'));

function takeposCashJson($payload, $httpCode = 200)
{
    global $langs;

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
        foreach (array('movement_id', 'ledger', 'rows') as $key) {
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

function takeposCashRequireToken($db, $user)
{
    global $langs;

    $token = (string) GETPOST('token', 'alpha');
    $sessionToken = isset($_SESSION['newtoken']) ? (string) $_SESSION['newtoken'] : '';
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        TakeposAccess::denyJson($db, $user, $langs->trans('TakeposCommonInvalidCsrfToken'), array('endpoint' => 'ajax/cash.php', 'action' => GETPOST('action', 'aZ09')));
    }
}

$action = GETPOST('action', 'aZ09');
$terminalFromSession = isset($_SESSION['takeposterminal']) ? (string) $_SESSION['takeposterminal'] : '1';
$context = array('endpoint' => 'ajax/cash.php', 'requested_action' => $action);

if ($action === '') {
    takeposCashJson(array('success' => false, 'error' => 'missing_action', 'message' => $langs->trans('TakeposCommonMissingAction')), 400);
}

TakeposAccess::requireAjaxAccess($db, $user, 'takepos.cash_control', 'takepos.use', (int) $terminalFromSession, $context);
$entity = !empty($user->entity) ? (int) $user->entity : 1;

try {
    if ($action === 'create_movement') {
        takeposCashRequireToken($db, $user);

        $movementType = GETPOST('movement_type', 'aZ09');
        $permission = 'takepos.cash.paidin';
        if ($movementType === TakeposCashService::TYPE_PAID_OUT) {
            $permission = 'takepos.cash.paidout';
        } elseif ($movementType === TakeposCashService::TYPE_SAFE_DROP) {
            $permission = 'takepos.cash.safedrop';
        }

        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.cash_control', $permission, (int) $terminalFromSession, $context);

        $amountRaw = GETPOST('amount', 'none');
        if (!TakeposInputValidator::parsePositiveDecimal($amountRaw, $amount, false, 8)) {
            takeposCashJson(array('success' => false, 'error' => 'invalid_amount', 'message' => $langs->trans('TakeposCashAmountPositiveDecimal')), 422);
        }

        $shiftId = GETPOSTINT('shift_id');
        if ($shiftId <= 0) {
            $summary = TakeposShiftService::getCurrentActiveShiftSummary($db, $user, $terminalFromSession);
            if (!$summary || empty($summary['shift_id'])) {
                if (TakeposShiftService::requireShiftForCashMovements()) {
                    takeposCashJson(array('success' => false, 'error' => 'shift_required', 'message' => $langs->trans('TakeposCashShiftRequired')), 409);
                }
                takeposCashJson(array('success' => false, 'error' => 'shift_missing', 'message' => $langs->trans('TakeposCashShiftMissing')), 409);
            }
            $shiftId = (int) $summary['shift_id'];
        }

        $reason = trim((string) GETPOST('reason', 'none'));
        $note = trim((string) GETPOST('note', 'none'));

        $movementId = TakeposCashService::createMovement($db, $user, $shiftId, $movementType, (float) $amount, $reason, $note);
        $ledger = TakeposCashService::listMovementsByShift($db, $entity, $shiftId, 100);

        takeposCashJson(array('success' => true, 'message' => $langs->trans('TakeposCashMovementRecorded'), 'movement_id' => $movementId, 'ledger' => $ledger));
    }

    if ($action === 'ledger') {
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.cash_control', 'takepos.cash.reconcile', (int) $terminalFromSession, $context);

        $shiftId = GETPOSTINT('shift_id');
        if ($shiftId <= 0) {
            takeposCashJson(array('success' => false, 'error' => 'invalid_shift_id', 'message' => $langs->trans('TakeposCashShiftIdRequired')), 422);
        }

        $rows = TakeposCashService::listMovementsByShift($db, $entity, $shiftId, 500);
        takeposCashJson(array('success' => true, 'rows' => $rows));
    }

    takeposCashJson(array('success' => false, 'error' => 'unsupported_action', 'message' => $langs->trans('TakeposCommonUnsupportedAction')), 400);
} catch (Throwable $e) {
    takeposCashJson(array('success' => false, 'error' => 'runtime_error', 'message' => $e->getMessage()), 500);
}
