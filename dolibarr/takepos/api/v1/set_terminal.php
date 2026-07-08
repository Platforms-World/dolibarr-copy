<?php
/*
 * TakePOS API v1 - Set current terminal / shift  (setTerminal)
 *
 * The web POS keeps the selected register in $_SESSION['takeposterminal'].
 * The REST API is stateless, so instead the selection is remembered against
 * the bearer token. This endpoint lets a client switch the "current" terminal
 * (and bind the active shift on it) and returns the resulting terminal, store,
 * warehouse and shift context.
 *
 * POST  /takepos/api/v1/set_terminal.php   · scope: write
 *
 * Body:
 *   terminal_id    int     Terminal rowid to switch to.        (terminal_id OR terminal_code required)
 *   terminal_code  string  Terminal code to switch to (e.g. "1", "T-MAIN-1").
 *   shift_id       int     Optional. Bind this exact open shift (must be on the terminal).
 *                          When omitted, the currently open shift on the terminal is auto-bound.
 *
 * Response data:
 *   terminal        object  The selected terminal.
 *   store           object  The terminal's store (or null).
 *   warehouse       object  The resolved warehouse (or null).
 *   shift           object  The bound open shift (or null).
 *   shift_required  bool    Whether payments require an open shift (i.e. you should open one).
 *   token           object  { id, terminal_id, shift_id } — the persisted binding.
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_request.php';
require_once __DIR__ . '/_context.php';

if (!function_exists('takeposApiShiftPayload')) {
    function takeposApiShiftPayload($row)
    {
        return array(
            'id' => (int) $row->rowid,
            'shift_ref' => (!empty($row->shift_ref) ? (string) $row->shift_ref : null),
            'terminal_id' => (int) $row->fk_terminal,
            'terminal_label' => (!empty($row->terminal_label) ? (string) $row->terminal_label : null),
            'user_id' => (int) $row->fk_cashier_user,
            'user_name' => (!empty($row->cashier_login) ? (string) $row->cashier_login : null),
            'store_id' => (!empty($row->fk_store) ? (int) $row->fk_store : null),
            'store_label' => (!empty($row->store_label) ? (string) $row->store_label : null),
            'status' => (string) $row->status,
            'opened_at' => (!empty($row->date_open) ? (string) $row->date_open : null),
            'closed_at' => (!empty($row->date_close) ? (string) $row->date_close : null),
            'opening_amount' => (float) price2num($row->opening_float, 'MT'),
            'closing_amount' => (isset($row->counted_cash) && $row->counted_cash !== null ? (float) price2num($row->counted_cash, 'MT') : null),
        );
    }
}

takeposApiRequireMethod(array('POST'));

$auth = takeposApiAuth($db, 'write', 'takepos.api_layer');
$entity = (int) $auth['entity'];
$user = $auth['user'];

$body = takeposApiRequestBody();

$terminalId = isset($body['terminal_id']) ? (int) $body['terminal_id'] : 0;
$terminalCode = isset($body['terminal_code']) ? trim((string) $body['terminal_code']) : '';
$requestedShiftId = isset($body['shift_id']) ? (int) $body['shift_id'] : 0;

if ($terminalId <= 0 && $terminalCode === '') {
    takeposApiError('INVALID_PARAMETER', 'terminal_id or terminal_code is required.', 422);
}

// Verify terminal PIN if set (checked before resolving terminal to fail fast)
// We check after we know the terminal_id; for terminal_code we check after resolution below.
$_pinToCheck = isset($body['terminal_pin']) ? (string) $body['terminal_pin'] : '';

TakeposTerminalService::ensureSchema($db);

// --- Resolve the terminal -------------------------------------------------
$terminalRow = null;
if ($terminalId > 0) {
    $sql = 'SELECT t.rowid, t.terminal_code, t.label, t.fk_store, t.active, t.last_seen, t.metadata_json, s.label AS store_label, s.warehouse_id';
    $sql .= ' FROM ' . TakeposTerminalService::tableTerminal() . ' t';
    $sql .= ' LEFT JOIN ' . TakeposStoreService::tableStore() . ' s ON s.rowid = t.fk_store AND s.entity = t.entity';
    $sql .= ' WHERE t.entity = ' . $entity . ' AND t.rowid = ' . $terminalId . ' LIMIT 1';
    $resql = $db->query($sql);
    $terminalRow = ($resql ? $db->fetch_object($resql) : null);
} else {
    $code = TakeposTerminalService::normalizeTerminalCode($terminalCode);
    if ($code === '') {
        takeposApiError('INVALID_PARAMETER', 'terminal_code format is invalid.', 422);
    }
    $sql = 'SELECT t.rowid, t.terminal_code, t.label, t.fk_store, t.active, t.last_seen, t.metadata_json, s.label AS store_label, s.warehouse_id';
    $sql .= ' FROM ' . TakeposTerminalService::tableTerminal() . ' t';
    $sql .= ' LEFT JOIN ' . TakeposStoreService::tableStore() . ' s ON s.rowid = t.fk_store AND s.entity = t.entity';
    $sql .= " WHERE t.entity = " . $entity . " AND t.terminal_code = '" . $db->escape($code) . "' LIMIT 1";
    $resql = $db->query($sql);
    $terminalRow = ($resql ? $db->fetch_object($resql) : null);
}

if (!$terminalRow) {
    takeposApiError('NOT_FOUND', 'Terminal not found.', 404);
}
if (empty($terminalRow->active)) {
    takeposApiError('INVALID_PARAMETER', 'Terminal is inactive.', 422);
}

$resolvedTerminalId = (int) $terminalRow->rowid;
$terminalStoreId = (!empty($terminalRow->fk_store) ? (int) $terminalRow->fk_store : 0);


// --- Verify terminal PIN --------------------------------------------------
// PIN stored as TAKEPOS_TERMINAL_PIN_{terminal_code_number} not rowid
$_terminalCode = (string) $terminalRow->terminal_code;
$_terminalNum = preg_replace('/^.*?(\d+)$/', '$1', $_terminalCode);
$_terminalPinHash = '';
$_pinSql = "SELECT value FROM ".MAIN_DB_PREFIX."const WHERE name = 'TAKEPOS_TERMINAL_PIN_".$_terminalNum."' AND entity = ".$entity." LIMIT 1";
$_pinRes = $db->query($_pinSql);
if ($_pinRes && $db->num_rows($_pinRes) > 0) {
    $_pinObj = $db->fetch_object($_pinRes);
    $_terminalPinHash = (string) $_pinObj->value;
}
if (!empty($_terminalPinHash)) {
    if ($_pinToCheck === '') {
        takeposApiError('PIN_REQUIRED', 'This terminal requires a PIN. Provide terminal_pin in the request body.', 403);
    }
    if (!password_verify($_pinToCheck, $_terminalPinHash)) {
        takeposApiError('WRONG_PIN', 'Incorrect terminal PIN.', 403);
    }
}

// --- Enforce store access -------------------------------------------------
if ($terminalStoreId > 0 && !TakeposStoreService::userCanAccessStore($db, $user, $terminalStoreId, $entity)) {
    takeposApiError('FORBIDDEN', 'You are not allowed to use this terminal\'s store.', 403);
}

// --- Resolve / validate the shift to bind ---------------------------------
$shiftRow = null;
try {
    if ($requestedShiftId > 0) {
        $candidate = TakeposShiftService::getShiftById($db, $entity, $requestedShiftId);
        if (!$candidate) {
            takeposApiError('NOT_FOUND', 'Shift not found.', 404);
        }
        if ((int) $candidate->fk_terminal !== $resolvedTerminalId) {
            takeposApiError('INVALID_PARAMETER', 'Shift does not belong to the selected terminal.', 422);
        }
        if (!in_array((string) $candidate->status, array(TakeposShiftService::STATUS_OPEN, TakeposShiftService::STATUS_CLOSING_PENDING), true)) {
            takeposApiError('SHIFT_NOT_OPEN', 'Shift is not open.', 422);
        }
        $shiftRow = $candidate;
    } else {
        // Auto-bind the active shift on this terminal, if one is open.
        $active = TakeposShiftService::getActiveShiftForTerminal($db, $entity, $resolvedTerminalId);
        if ($active) {
            $shiftRow = TakeposShiftService::getShiftById($db, $entity, (int) $active->rowid);
        }
    }
} catch (TakeposApiException $e) {
    throw $e;
} catch (Throwable $e) {
    takeposApiError('SHIFT_OPERATION_FAILED', $e->getMessage(), 422);
}

$boundShiftId = ($shiftRow ? (int) $shiftRow->rowid : 0);

// --- Persist the selection against the token ------------------------------
try {
    TakeposApiService::bindTokenTerminal($db, $entity, (int) $auth['token']['id'], $resolvedTerminalId, $boundShiftId);
    TakeposTerminalService::touchLastSeen($db, $resolvedTerminalId);
} catch (Throwable $e) {
    takeposApiError('INTERNAL_ERROR', 'Failed to set the current terminal.', 500);
}

// --- Build the response context -------------------------------------------
$terminalPayload = takeposApiContextFormatTerminal($terminalRow);

$storePayload = null;
if ($terminalStoreId > 0) {
    $store = TakeposStoreService::getStore($db, $entity, $terminalStoreId);
    if ($store) {
        $storePayload = array(
            'id' => (int) $store->rowid,
            'code' => (string) $store->code,
            'label' => (string) $store->label,
            'description' => (isset($store->description) ? (string) $store->description : ''),
            'warehouse_id' => (!empty($store->warehouse_id) ? (int) $store->warehouse_id : null),
            'status' => (int) $store->active,
        );
    }
}

$warehousePayload = null;
$warehouseId = (!empty($terminalPayload['warehouse_id']) ? (int) $terminalPayload['warehouse_id'] : 0);
if ($warehouseId <= 0 && $storePayload && !empty($storePayload['warehouse_id'])) {
    $warehouseId = (int) $storePayload['warehouse_id'];
}
if ($warehouseId > 0) {
    $warehouses = takeposApiFetchWarehouses($db, array($warehouseId));
    if (!empty($warehouses)) {
        $warehousePayload = $warehouses[0];
    }
}

$shiftRequired = TakeposShiftService::requireOpenShiftForPayments();

takeposApiAuditAccess($db, $auth, 'set_terminal', array(
    'terminal_id' => $resolvedTerminalId,
    'store_id' => $terminalStoreId,
    'shift_id' => $boundShiftId,
));

// Build full context scoped to the newly bound terminal so the client
// gets everything it needs in one response — no need to call auth_login again.
$context = null;
try {
    $context = takeposApiBuildPosContext($db, $entity, $user, $resolvedTerminalId);
} catch (Throwable $e) {
    takeposApiLogError('set_terminal context build failed: ' . $e->getMessage(), LOG_WARNING);
}

takeposApiSuccess(array(
    'terminal' => $terminalPayload,
    'store' => $storePayload,
    'warehouse' => $warehousePayload,
    'shift' => ($shiftRow ? takeposApiShiftPayload($shiftRow) : null),
    'shift_required' => (bool) $shiftRequired,
    'context' => $context,
    'token' => array(
        'id' => (int) $auth['token']['id'],
        'terminal_id' => $resolvedTerminalId,
        'shift_id' => ($boundShiftId > 0 ? $boundShiftId : null),
    ),
), array('entity' => $entity));