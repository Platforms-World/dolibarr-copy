<?php
/*
 * TakePOS API v1 - Cash Movements
 * GET  : list cash movements for a shift
 * POST : create a cash movement (paid_in, paid_out, safe_drop)
 * Component: TakeposCashService
 */
require_once __DIR__ . '/bootstrap.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposCashService.class.php';

$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET');
if (!in_array($method, array('GET', 'POST'), true)) {
    takeposApiError('METHOD_NOT_ALLOWED', 'Method not allowed.', 405, null, array(), array('Allow: GET, POST'));
}

$auth = takeposApiAuth($db, ($method === 'GET' ? 'read' : 'write'), 'takepos.api_layer');
$entity = (int) $auth['entity'];
$user = $auth['user'];

TakeposCashService::ensureSchema($db);

if ($method === 'POST') {
    $body = takeposApiRequestBody();
    $shiftId = (int) takeposApiRequestRequireField($body, 'shift_id');
    $movementType = strtolower(trim((string) takeposApiRequestRequireField($body, 'movement_type')));
    $amount = isset($body['amount']) ? (float) $body['amount'] : 0.0;
    $reason = isset($body['reason']) ? (string) $body['reason'] : '';
    $note = isset($body['note']) ? (string) $body['note'] : '';
    $approvedBy = isset($body['approved_by']) ? (int) $body['approved_by'] : 0;

    if (!TakeposCashService::isMovementTypeAllowed($movementType)) {
        takeposApiError('INVALID_PARAMETER', 'movement_type must be one of: paid_in, paid_out, safe_drop.', 422);
    }

    try {
        $movementId = TakeposCashService::createMovement($db, $user, $shiftId, $movementType, $amount, $reason, $note, $approvedBy);
    } catch (Throwable $e) {
        takeposApiError('CASH_MOVEMENT_FAILED', $e->getMessage(), 422);
    }

    takeposApiAuditAccess($db, $auth, 'cash_movements.create', array('movement_id' => (int) $movementId, 'shift_id' => $shiftId, 'movement_type' => $movementType));
    takeposApiSuccess(array(
        'id' => (int) $movementId,
        'shift_id' => $shiftId,
        'movement_type' => $movementType,
        'amount' => (float) $amount,
        'reason' => $reason,
    ), array('entity' => $entity), 201);
}

// GET: list by shift
$shiftId = GETPOSTINT('shift_id');
if ($shiftId <= 0) {
    takeposApiError('INVALID_PARAMETER', 'shift_id is required.', 422);
}
$limit = GETPOSTINT('limit');
if ($limit <= 0) { $limit = 300; }
if ($limit > 1000) { $limit = 1000; }

$rows = array();
foreach (TakeposCashService::listMovementsByShift($db, $entity, $shiftId, $limit) as $row) {
    $rows[] = array(
        'id' => (int) $row->rowid,
        'shift_id' => (int) $row->fk_shift,
        'movement_type' => (string) $row->movement_type,
        'amount' => (float) price2num($row->amount, 'MT'),
        'reason_code' => (!empty($row->reason_code) ? (string) $row->reason_code : null),
        'reason_text' => (!empty($row->reason_text) ? (string) $row->reason_text : null),
        'note' => (!empty($row->note) ? (string) $row->note : null),
        'created_at' => (!empty($row->date_creation) ? (string) $row->date_creation : null),
        'created_by' => (!empty($row->fk_created_by) ? (int) $row->fk_created_by : null),
        'approved_by' => (!empty($row->fk_approved_by) ? (int) $row->fk_approved_by : null),
    );
}

takeposApiAuditAccess($db, $auth, 'cash_movements.index', array('shift_id' => $shiftId, 'count' => count($rows)));
takeposApiSuccess($rows, array('entity' => $entity, 'count' => count($rows), 'shift_id' => $shiftId, 'limit' => $limit));
