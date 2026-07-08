<?php
/**
 * Shift management AJAX controller.
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
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposTerminalService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposStoreService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposShiftService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposCashService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposUserAccess.class.php';

/**
 * @var DoliDB $db
 * @var User $user
 */

$langs->loadLangs(array('cashdesk', 'main', 'takeposcustom@takepos'));

function takeposShiftTrans($key, $fallback)
{
    global $langs;

    $translated = $langs->trans($key);
    return ($translated !== $key ? $translated : $fallback);
}

function takeposShiftJson($payload, $httpCode = 200)
{
    $payload = is_array($payload) ? $payload : array();
    $success = !empty($payload['success']);

    if (!array_key_exists('message', $payload)) {
        $payload['message'] = $success ? 'OK' : takeposShiftTrans('TakeposShiftRequestFailed', 'Request failed');
    }
    if (!array_key_exists('errors', $payload)) {
        $payload['errors'] = $success ? array() : array(!empty($payload['error']) ? (string) $payload['error'] : 'request_failed');
    }
    if (!array_key_exists('data', $payload)) {
        $data = array();
        foreach (array('allowed', 'shift', 'summary', 'ledger', 'rows', 'row', 'result', 'state') as $key) {
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

function takeposShiftRequireToken($db, $user)
{
    $token = (string) GETPOST('token', 'alpha');
    $sessionToken = isset($_SESSION['newtoken']) ? (string) $_SESSION['newtoken'] : '';
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        TakeposAccess::denyJson(
            $db,
            $user,
            takeposShiftTrans('TakeposShiftInvalidCsrf', 'Invalid CSRF token.'),
            array('endpoint' => 'ajax/shift.php', 'action' => GETPOST('action', 'aZ09')),
            403
        );
    }
}

function takeposShiftCurrentEntity($user)
{
    return !empty($user->entity) ? (int) $user->entity : 1;
}

$action = GETPOST('action', 'aZ09');
$terminalFromSession = isset($_SESSION['takeposterminal']) ? (string) $_SESSION['takeposterminal'] : '1';
$context = array('endpoint' => 'ajax/shift.php', 'requested_action' => $action);

if ($action === '') {
    takeposShiftJson(array('success' => false, 'error' => 'missing_action', 'message' => takeposShiftTrans('TakeposShiftMissingAction', 'Missing action')), 400);
}

// Generic feature gate for all shift endpoints.
TakeposAccess::requireAjaxAccess(
    $db,
    $user,
    'takepos.shift_management',
    'takepos.use',
    (int) $terminalFromSession,
    $context
);

$entity = takeposShiftCurrentEntity($user);

try {
    if ($action === 'active') {
        $summary = TakeposShiftService::getCurrentActiveShiftSummary($db, $user, $terminalFromSession);
        TakeposAudit::logEvent($db, $user, 'shift_view_opened', TakeposAudit::SEVERITY_INFO, array('view' => 'active', 'terminal' => $terminalFromSession), 'Shift active summary viewed');
        takeposShiftJson(array('success' => true, 'data' => $summary));
    }

    if ($action === 'check_payment' || $action === 'check_sale') {
        $invoiceId = GETPOSTINT('invoice_id');
        if ($action === 'check_sale') {
            list($ok, $message, $summary) = TakeposShiftService::enforceSaleShiftRequirement($db, $user, $terminalFromSession, $invoiceId);
        } else {
            list($ok, $message, $summary) = TakeposShiftService::enforcePaymentShiftRequirement($db, $user, $terminalFromSession, $invoiceId);
        }
        takeposShiftJson(array('success' => true, 'allowed' => $ok ? 1 : 0, 'message' => (string) $message, 'shift' => $summary));
    }

    if ($action === 'open') {
        takeposShiftRequireToken($db, $user);
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.shift_management', 'takepos.shift.open', (int) $terminalFromSession, $context);

        $openingRaw = GETPOST('opening_float', 'none');
        if (!TakeposInputValidator::parsePositiveDecimal($openingRaw, $openingFloat, true, 8)) {
            takeposShiftJson(array('success' => false, 'error' => 'invalid_opening_float', 'message' => takeposShiftTrans('TakeposShiftOpeningFloatInvalid', 'Opening float is invalid.')), 422);
        }

        $storeId = GETPOSTINT('store_id');
        $terminalCode = GETPOST('terminal_code', 'aZ09');
        if ($terminalCode === '') {
            $terminalCode = $terminalFromSession;
        }

        $terminal = TakeposTerminalService::resolveCurrentTerminal($db, $user, $terminalCode);
        if (!$terminal) {
            takeposShiftJson(array('success' => false, 'error' => 'terminal_missing', 'message' => takeposShiftTrans('TakeposShiftTerminalNotFound', 'Terminal not found.')), 404);
        }

        if ($storeId <= 0 && !empty($terminal->fk_store)) {
            $storeId = (int) $terminal->fk_store;
        }

        if (TakeposStoreService::enforceStoreRestrictionEnabled($db) && $storeId > 0 && !TakeposStoreService::userCanAccessStore($db, $user, $storeId, $entity) && empty($user->admin)) {
            TakeposAudit::logEvent($db, $user, 'store_restriction_denied', TakeposAudit::SEVERITY_WARNING, array('store_id' => $storeId, 'action' => 'shift_open'), 'Store restriction denied');
            takeposShiftJson(array('success' => false, 'error' => 'store_access_denied', 'message' => takeposShiftTrans('TakeposShiftStoreDenied', 'You are not allowed to open a shift for this store.')), 403);
        }

        $notes = trim((string) GETPOST('note', 'none'));
        $shiftId = TakeposShiftService::openShift($db, $user, (int) $terminal->rowid, (int) $storeId, (float) $openingFloat, $notes);
        $shift = TakeposShiftService::getShiftById($db, $entity, $shiftId);

        takeposShiftJson(array('success' => true, 'message' => takeposShiftTrans('TakeposShiftOpenedSuccess', 'Shift opened successfully.'), 'shift' => $shift));
    }

    if ($action === 'close') {
        takeposShiftRequireToken($db, $user);
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.shift_management', 'takepos.shift.close', (int) $terminalFromSession, $context);

        $shiftId = GETPOSTINT('shift_id');
        $countedRaw = GETPOST('counted_cash', 'none');
        if (!TakeposInputValidator::parsePositiveDecimal($countedRaw, $countedCash, true, 8)) {
            takeposShiftJson(array('success' => false, 'error' => 'invalid_counted_cash', 'message' => takeposShiftTrans('TakeposShiftCountedCashInvalid', 'Counted cash is invalid.')), 422);
        }

        $shift = TakeposShiftService::getShiftById($db, $entity, $shiftId);
        if (!$shift) {
            takeposShiftJson(array('success' => false, 'error' => 'shift_not_found', 'message' => takeposShiftTrans('TakeposShiftNotFound', 'Shift not found.')), 404);
        }

        $canForceClose = !empty($user->admin) || TakeposUserAccess::userHasPermission($db, $user, 'takepos.shift.force_close');
        if ((int) $shift->fk_cashier_user !== (int) $user->id && !$canForceClose) {
            takeposShiftJson(array('success' => false, 'error' => 'shift_owner_mismatch', 'message' => takeposShiftTrans('TakeposShiftOwnerMismatch', 'You can close only your own shift.')), 403);
        }

        $canOverrideDiff = !empty($user->admin) || TakeposUserAccess::userHasPermission($db, $user, 'takepos.cash.override_difference');
        $notes = trim((string) GETPOST('note', 'none'));
        TakeposAudit::logEvent($db, $user, 'cash_count_started', TakeposAudit::SEVERITY_INFO, array('shift_id' => $shiftId), 'Cash count started', 'shift', $shiftId);
        TakeposShiftService::closeShift($db, $user, $shiftId, (float) $countedCash, $notes, 0, $canOverrideDiff);

        $updated = TakeposShiftService::getShiftById($db, $entity, $shiftId);
        takeposShiftJson(array('success' => true, 'message' => takeposShiftTrans('TakeposShiftClosedSuccess', 'Shift closed successfully.'), 'shift' => $updated));
    }

    if ($action === 'force_close') {
        takeposShiftRequireToken($db, $user);
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.shift_management', 'takepos.shift.force_close', (int) $terminalFromSession, $context);

        $shiftId = GETPOSTINT('shift_id');
        if ($shiftId <= 0) {
            takeposShiftJson(array('success' => false, 'error' => 'invalid_shift_id', 'message' => takeposShiftTrans('TakeposShiftIdRequired', 'Shift ID is required.')), 422);
        }

        $notes = trim((string) GETPOST('note', 'none'));
        TakeposShiftService::forceCloseShift($db, $user, $shiftId, $notes);
        $updated = TakeposShiftService::getShiftById($db, $entity, $shiftId);
        takeposShiftJson(array('success' => true, 'message' => takeposShiftTrans('TakeposShiftForceClosed', 'Shift force-closed.'), 'shift' => $updated));
    }

    if ($action === 'list') {
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.shift_management', 'takepos.shift.review', (int) $terminalFromSession, $context);

        $filters = array(
            'status' => GETPOST('status', 'aZ09'),
            'store_id' => GETPOSTINT('store_id'),
            'terminal_id' => GETPOSTINT('terminal_id'),
        );

        if (TakeposStoreService::enforceStoreRestrictionEnabled($db) && empty($user->admin) && !TakeposUserAccess::userHasPermission($db, $user, 'takepos.store.view_all')) {
            $allowedStoreIds = TakeposStoreService::getUserStoreIds($db, $entity, (int) $user->id);
            if (!empty($filters['store_id']) && !in_array((int) $filters['store_id'], $allowedStoreIds, true)) {
                TakeposAudit::logEvent($db, $user, 'store_restriction_denied', TakeposAudit::SEVERITY_WARNING, array('store_id' => (int) $filters['store_id'], 'action' => 'shift_list'), 'Store restriction denied');
                takeposShiftJson(array('success' => false, 'error' => 'store_access_denied', 'message' => $langs->trans('TakeposReportsStoreAccessDenied')), 403);
            }
            if (empty($filters['store_id']) && count($allowedStoreIds) === 1) {
                $filters['store_id'] = (int) $allowedStoreIds[0];
            }
        }

        $rows = TakeposShiftService::listShifts($db, $entity, $filters, 200);
        TakeposAudit::logEvent($db, $user, 'shift_view_opened', TakeposAudit::SEVERITY_INFO, array('view' => 'list'), 'Shift list viewed');
        takeposShiftJson(array('success' => true, 'rows' => $rows));
    }

    if ($action === 'detail') {
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.shift_management', 'takepos.shift.review', (int) $terminalFromSession, $context);

        $shiftId = GETPOSTINT('shift_id');
        $shift = TakeposShiftService::getShiftById($db, $entity, $shiftId);
        if (!$shift) {
            takeposShiftJson(array('success' => false, 'error' => 'shift_not_found', 'message' => takeposShiftTrans('TakeposShiftNotFound', 'Shift not found.')), 404);
        }

        if (TakeposStoreService::enforceStoreRestrictionEnabled($db) && !empty($shift->fk_store) && empty($user->admin) && !TakeposUserAccess::userHasPermission($db, $user, 'takepos.store.view_all') && !TakeposStoreService::userCanAccessStore($db, $user, (int) $shift->fk_store, $entity)) {
            TakeposAudit::logEvent($db, $user, 'store_restriction_denied', TakeposAudit::SEVERITY_WARNING, array('store_id' => (int) $shift->fk_store, 'action' => 'shift_detail'), 'Store restriction denied');
            takeposShiftJson(array('success' => false, 'error' => 'store_access_denied', 'message' => $langs->trans('TakeposReportsStoreAccessDenied')), 403);
        }

        $summary = TakeposShiftService::buildShiftSummary($db, $entity, $shift);
        $ledger = TakeposCashService::listMovementsByShift($db, $entity, $shiftId, 400);

        TakeposAudit::logEvent($db, $user, 'shift_view_opened', TakeposAudit::SEVERITY_INFO, array('view' => 'detail', 'shift_id' => $shiftId), 'Shift detail viewed', 'shift', $shiftId);
        takeposShiftJson(array('success' => true, 'shift' => $shift, 'summary' => $summary, 'ledger' => $ledger));
    }

    takeposShiftJson(array('success' => false, 'error' => 'unsupported_action', 'message' => takeposShiftTrans('TakeposShiftUnsupportedAction', 'Unsupported action')), 400);
} catch (Throwable $e) {
    takeposShiftJson(array('success' => false, 'error' => 'runtime_error', 'message' => $e->getMessage()), 500);
}
