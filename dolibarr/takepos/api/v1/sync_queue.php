<?php
/*
 * TakePOS API v1 - Sync Queue (offline synchronization)
 * GET  : list queue entries (?status=, ?action_type=) or summary (?summary=1) or one (?id=)
 * POST : enqueue (action=enqueue), process one (action=process), process pending
 *        (action=process_pending), retry (action=retry), resolve conflict (action=resolve)
 * Component: TakeposSyncService
 */
require_once __DIR__ . '/bootstrap.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposSyncService.class.php';

$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET');
if (!in_array($method, array('GET', 'POST'), true)) {
    takeposApiError('METHOD_NOT_ALLOWED', 'Method not allowed.', 405, null, array(), array('Allow: GET, POST'));
}

$auth = takeposApiAuth($db, ($method === 'GET' ? 'read' : 'write'), 'takepos.api_layer');
$entity = (int) $auth['entity'];
$user = $auth['user'];

TakeposSyncService::ensureSchema($db);

function takeposApiSyncPayload($row)
{
    return array(
        'id' => (int) $row->rowid,
        'action_type' => (string) $row->action_type,
        'status' => (string) $row->status,
        'local_ref' => (!empty($row->local_ref) ? (string) $row->local_ref : null),
        'idempotency_key' => (!empty($row->idempotency_key) ? (string) $row->idempotency_key : null),
        'retry_count' => (isset($row->retry_count) ? (int) $row->retry_count : 0),
        'last_error' => (!empty($row->last_error) ? (string) $row->last_error : null),
        'conflict_note' => (!empty($row->conflict_note) ? (string) $row->conflict_note : null),
        'created_at' => (!empty($row->date_creation) ? (string) $row->date_creation : null),
        'last_attempt_at' => (!empty($row->date_last_attempt) ? (string) $row->date_last_attempt : null),
        'synced_at' => (!empty($row->date_synced) ? (string) $row->date_synced : null),
    );
}

if ($method === 'POST') {
    $body = takeposApiRequestBody();
    $action = isset($body['action']) ? strtolower(trim((string) $body['action'])) : 'enqueue';

    try {
        if ($action === 'process') {
            $queueId = (int) takeposApiRequestRequireField($body, 'queue_id');
            $forceFromFailed = !empty($body['force_from_failed']);
            $result = TakeposSyncService::processQueueEntry($db, $user, $queueId, $forceFromFailed);
            takeposApiAuditAccess($db, $auth, 'sync_queue.process', array('queue_id' => $queueId));
            takeposApiSuccess(array('queue_id' => $queueId, 'result' => $result), array('entity' => $entity));
        }
        if ($action === 'process_pending') {
            $limit = isset($body['limit']) ? (int) $body['limit'] : 20;
            $result = TakeposSyncService::processPending($db, $user, $limit);
            takeposApiAuditAccess($db, $auth, 'sync_queue.process_pending', array('limit' => $limit));
            takeposApiSuccess(array('result' => $result), array('entity' => $entity));
        }
        if ($action === 'retry') {
            $queueId = (int) takeposApiRequestRequireField($body, 'queue_id');
            $result = TakeposSyncService::retry($db, $user, $queueId);
            takeposApiAuditAccess($db, $auth, 'sync_queue.retry', array('queue_id' => $queueId));
            takeposApiSuccess(array('queue_id' => $queueId, 'result' => $result), array('entity' => $entity));
        }
        if ($action === 'resolve') {
            $queueId = (int) takeposApiRequestRequireField($body, 'queue_id');
            $note = isset($body['note']) ? (string) $body['note'] : '';
            $result = TakeposSyncService::resolveConflict($db, $user, $queueId, $note);
            takeposApiAuditAccess($db, $auth, 'sync_queue.resolve', array('queue_id' => $queueId));
            takeposApiSuccess(array('queue_id' => $queueId, 'result' => $result), array('entity' => $entity));
        }

        // enqueue
        $actionType = (string) takeposApiRequestRequireField($body, 'action_type');
        $payload = isset($body['payload']) && is_array($body['payload']) ? $body['payload'] : array();
        $localRef = isset($body['local_ref']) ? (string) $body['local_ref'] : '';
        $idempotencyKey = isset($body['idempotency_key']) ? (string) $body['idempotency_key'] : '';
        $queueId = TakeposSyncService::enqueue($db, $user, $actionType, $payload, $localRef, $idempotencyKey);
        $row = TakeposSyncService::getById($db, $entity, (int) $queueId);
        takeposApiAuditAccess($db, $auth, 'sync_queue.enqueue', array('queue_id' => (int) $queueId, 'action_type' => $actionType));
        takeposApiSuccess($row ? takeposApiSyncPayload($row) : array('id' => (int) $queueId), array('entity' => $entity), 201);
    } catch (Throwable $e) {
        takeposApiError('SYNC_QUEUE_FAILED', $e->getMessage(), 422);
    }
}

// GET
if (GETPOSTINT('summary') === 1) {
    $summary = TakeposSyncService::summary($db, $entity);
    takeposApiAuditAccess($db, $auth, 'sync_queue.summary', array());
    takeposApiSuccess($summary, array('entity' => $entity));
}

$id = GETPOSTINT('id');
if ($id > 0) {
    $row = TakeposSyncService::getById($db, $entity, $id);
    if (!$row) {
        takeposApiError('NOT_FOUND', 'Queue entry not found.', 404);
    }
    takeposApiAuditAccess($db, $auth, 'sync_queue.show', array('queue_id' => $id));
    takeposApiSuccess(takeposApiSyncPayload($row), array('entity' => $entity));
}

$filters = array(
    'status' => GETPOST('status', 'aZ09'),
    'action_type' => GETPOST('action_type', 'aZ09'),
);
$limit = GETPOSTINT('limit'); if ($limit <= 0) { $limit = 200; } if ($limit > 1000) { $limit = 1000; }

$rows = array();
foreach (TakeposSyncService::listQueue($db, $entity, $filters, $limit) as $row) {
    $rows[] = takeposApiSyncPayload($row);
}

takeposApiAuditAccess($db, $auth, 'sync_queue.index', array('count' => count($rows)));
takeposApiSuccess($rows, array('entity' => $entity, 'count' => count($rows), 'limit' => $limit));
