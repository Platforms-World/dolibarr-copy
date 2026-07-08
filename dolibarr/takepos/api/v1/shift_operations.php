<?php
/*
 * TakePOS API v1 - Shift Operations
 * Write operations for cash-register shifts (open / close / force-close).
 * The existing shifts.php endpoint is read-only; this endpoint exposes the
 * lifecycle actions of TakeposShiftService over the unified JSON API.
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_request.php';

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
            'closing_amount' => ($row->counted_cash !== null ? (float) price2num($row->counted_cash, 'MT') : null),
        );
    }
}

takeposApiRequireMethod(array('POST'));

$auth = takeposApiAuth($db, 'write', 'takepos.api_layer');
$entity = (int) $auth['entity'];
$user = $auth['user'];

$body = takeposApiRequestBody();
$action = isset($body['action']) ? strtolower(trim((string) $body['action'])) : '';
if ($action === '') {
    takeposApiError('INVALID_PARAMETER', 'action is required (open, close, force_close).', 422);
}

try {
    if ($action === 'open') {
        $terminalId = (int) takeposApiRequestRequireField($body, 'terminal_id');
        $storeId = isset($body['store_id']) ? (int) $body['store_id'] : 0;
        $openingFloat = isset($body['opening_float']) ? (float) $body['opening_float'] : 0.0;
        $notes = isset($body['notes']) ? (string) $body['notes'] : '';

        // Verify terminal PIN if one is set
        // Get terminal_code from DB (PIN stored by code number, not rowid)
        $_terminalPinHash2 = '';
        $_termCodeRes = $db->query("SELECT terminal_code FROM ".MAIN_DB_PREFIX."takepos_terminal WHERE rowid = ".(int)$terminalId." AND entity = ".$entity." LIMIT 1");
        if ($_termCodeRes && $db->num_rows($_termCodeRes) > 0) {
            $_termCodeObj = $db->fetch_object($_termCodeRes);
            $_termNum2 = preg_replace('/^.*?(\d+)$/', '$1', (string)$_termCodeObj->terminal_code);
            $_pinSql2 = "SELECT value FROM ".MAIN_DB_PREFIX."const WHERE name = 'TAKEPOS_TERMINAL_PIN_".$_termNum2."' AND entity = ".$entity." LIMIT 1";
            $_pinRes2 = $db->query($_pinSql2);
            if ($_pinRes2 && $db->num_rows($_pinRes2) > 0) {
                $_pinObj2 = $db->fetch_object($_pinRes2);
                $_terminalPinHash2 = (string) $_pinObj2->value;
            }
        }
        if (!empty($_terminalPinHash2)) {
            $providedPin = isset($body['terminal_pin']) ? (string) $body['terminal_pin'] : '';
            if ($providedPin === '') {
                takeposApiError('PIN_REQUIRED', 'This terminal requires a PIN. Provide terminal_pin in the request body.', 403);
            }
            if (!password_verify($providedPin, $_terminalPinHash2)) {
                takeposApiError('WRONG_PIN', 'Incorrect terminal PIN.', 403);
            }
        }

        // openShift() returns int (new shift) OR array (existing shift reused)
        $result   = TakeposShiftService::openShift($db, $user, $terminalId, $storeId, $openingFloat, $notes);
        $existed  = is_array($result);
        $shiftId  = $existed ? (int) $result['rowid'] : (int) $result;

        $row = TakeposShiftService::getShiftById($db, $entity, $shiftId);
        if (!$row) {
            takeposApiError('INTERNAL_ERROR', 'Shift created but could not be retrieved.', 500);
        }
        takeposApiAuditAccess($db, $auth, 'shift_operations.open', array('shift_id' => $shiftId, 'terminal_id' => $terminalId, 'reused' => $existed));
        $payload = takeposApiShiftPayload($row);
        $payload['already_open'] = $existed;
        takeposApiSuccess($payload, array('entity' => $entity), ($existed ? 200 : 201));
    }

    if ($action === 'close') {
        $shiftId = (int) takeposApiRequestRequireField($body, 'shift_id');
        $countedCash = isset($body['counted_cash']) ? (float) $body['counted_cash'] : 0.0;
        $notes = isset($body['notes']) ? (string) $body['notes'] : '';
        $approvedBy = isset($body['approved_by']) ? (int) $body['approved_by'] : 0;
        $allowLargeDifference = !empty($body['allow_large_difference']);

        TakeposShiftService::closeShift($db, $user, $shiftId, $countedCash, $notes, $approvedBy, $allowLargeDifference);
        $row = TakeposShiftService::getShiftById($db, $entity, $shiftId);
        $summary = TakeposShiftService::buildShiftSummary($db, $entity, $row);
        $payload = takeposApiShiftPayload($row);
        $payload['summary'] = $summary;
        takeposApiAuditAccess($db, $auth, 'shift_operations.close', array('shift_id' => $shiftId));
        takeposApiSuccess($payload, array('entity' => $entity));
    }

    if ($action === 'force_close') {
        $shiftId = (int) takeposApiRequestRequireField($body, 'shift_id');
        $notes = isset($body['notes']) ? (string) $body['notes'] : '';

        TakeposShiftService::forceCloseShift($db, $user, $shiftId, $notes);
        $row = TakeposShiftService::getShiftById($db, $entity, $shiftId);
        takeposApiAuditAccess($db, $auth, 'shift_operations.force_close', array('shift_id' => $shiftId));
        takeposApiSuccess(takeposApiShiftPayload($row), array('entity' => $entity));
    }

    takeposApiError('INVALID_PARAMETER', 'Unknown action. Use open, close or force_close.', 422);
} catch (TakeposApiException $e) {
    throw $e;
} catch (Throwable $e) {
    takeposApiError('SHIFT_OPERATION_FAILED', $e->getMessage(), 422);
}