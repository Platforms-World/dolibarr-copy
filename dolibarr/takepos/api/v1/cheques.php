<?php
/*
 * TakePOS API v1 - Supplier Cheques
 * GET   : list cheques (filters + summary) or show one (?id=)
 * POST  : create a cheque
 * PATCH : update an existing cheque (?id= or body id)
 * Component: TakeposChequeService
 */
require_once __DIR__ . '/bootstrap.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposChequeService.class.php';

$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET');
if (!in_array($method, array('GET', 'POST', 'PATCH'), true)) {
    takeposApiError('METHOD_NOT_ALLOWED', 'Method not allowed.', 405, null, array(), array('Allow: GET, POST, PATCH'));
}

$auth = takeposApiAuth($db, ($method === 'GET' ? 'read' : 'write'), 'takepos.api_layer');
$entity = (int) $auth['entity'];
$user = $auth['user'];

TakeposChequeService::ensureSchema($db);

function takeposApiChequePayload($row)
{
    return array(
        'id' => (int) $row->rowid,
        'ref' => (!empty($row->ref) ? (string) $row->ref : null),
        'cheque_number' => (string) $row->cheque_number,
        'bank_name' => (!empty($row->bank_name) ? (string) $row->bank_name : null),
        'amount' => (float) price2num($row->amount, 'MU'),
        'cheque_date' => (!empty($row->cheque_date) ? (string) $row->cheque_date : null),
        'collection_date' => (!empty($row->collection_date) ? (string) $row->collection_date : null),
        'status' => (string) $row->status,
        'status_label' => TakeposChequeService::statusLabel((string) $row->status),
        'due_state' => (!empty($row->due_state) ? (string) $row->due_state : null),
        'due_state_label' => (!empty($row->due_state_label) ? (string) $row->due_state_label : null),
        'supplier_id' => (!empty($row->fk_supplier) ? (int) $row->fk_supplier : null),
        'supplier_name' => (!empty($row->supplier_name) ? (string) $row->supplier_name : null),
        'purchase_id' => (!empty($row->fk_purchase) ? (int) $row->fk_purchase : null),
        'purchase_ref' => (!empty($row->purchase_ref) ? (string) $row->purchase_ref : null),
        'note_private' => (!empty($row->note_private) ? (string) $row->note_private : null),
    );
}

function takeposApiChequePayloadFromBody($body)
{
    return array(
        'cheque_number' => isset($body['cheque_number']) ? (string) $body['cheque_number'] : '',
        'amount' => isset($body['amount']) ? (float) $body['amount'] : 0,
        'cheque_date' => isset($body['cheque_date']) ? (string) $body['cheque_date'] : '',
        'collection_date' => isset($body['collection_date']) ? (string) $body['collection_date'] : '',
        'supplier_id' => isset($body['supplier_id']) ? (int) $body['supplier_id'] : 0,
        'purchase_id' => isset($body['purchase_id']) ? (int) $body['purchase_id'] : 0,
        'bank_name' => isset($body['bank_name']) ? (string) $body['bank_name'] : '',
        'status' => isset($body['status']) ? (string) $body['status'] : 'pending',
        'note_private' => isset($body['note_private']) ? (string) $body['note_private'] : '',
    );
}

if ($method === 'POST' || $method === 'PATCH') {
    if (!TakeposChequeService::canCreate($db, $user)) {
        takeposApiError('FORBIDDEN', 'Cheque create permission is required.', 403);
    }
    $body = takeposApiRequestBody();
    $payload = takeposApiChequePayloadFromBody($body);

    try {
        if ($method === 'PATCH') {
            $chequeId = GETPOSTINT('id');
            if ($chequeId <= 0 && isset($body['id'])) { $chequeId = (int) $body['id']; }
            if ($chequeId <= 0) { takeposApiError('INVALID_PARAMETER', 'id is required for update.', 422); }
            TakeposChequeService::updateCheque($db, $user, $chequeId, $payload);
        } else {
            $chequeId = TakeposChequeService::createCheque($db, $user, $payload);
        }
        $row = TakeposChequeService::getChequeById($db, $entity, (int) $chequeId);
        takeposApiAuditAccess($db, $auth, ($method === 'PATCH' ? 'cheques.update' : 'cheques.create'), array('cheque_id' => (int) $chequeId));
        takeposApiSuccess(takeposApiChequePayload($row), array('entity' => $entity), ($method === 'PATCH' ? 200 : 201));
    } catch (Throwable $e) {
        takeposApiError('CHEQUE_SAVE_FAILED', $e->getMessage(), 422);
    }
}

// GET
if (!TakeposChequeService::canRead($db, $user)) {
    takeposApiError('FORBIDDEN', 'Cheque read permission is required.', 403);
}

$id = GETPOSTINT('id');
if ($id > 0) {
    $row = TakeposChequeService::getChequeById($db, $entity, $id);
    if (!$row) {
        takeposApiError('NOT_FOUND', 'Cheque not found.', 404);
    }
    takeposApiAuditAccess($db, $auth, 'cheques.show', array('cheque_id' => $id));
    takeposApiSuccess(takeposApiChequePayload($row), array('entity' => $entity));
}

$filters = array(
    'status' => GETPOST('status', 'aZ09'),
    'due_window' => GETPOST('due_window', 'aZ09'),
    'supplier_id' => GETPOSTINT('supplier_id'),
);
$limit = GETPOSTINT('limit'); if ($limit <= 0) { $limit = 250; } if ($limit > 1000) { $limit = 1000; }

$list = TakeposChequeService::listCheques($db, $entity, $filters, $limit);
$rows = array();
foreach ($list as $row) {
    $rows[] = takeposApiChequePayload($row);
}
$summary = TakeposChequeService::summarize($list);
$alerts = TakeposChequeService::buildAlerts($summary);

takeposApiAuditAccess($db, $auth, 'cheques.index', array('count' => count($rows)));
takeposApiSuccess($rows, array('entity' => $entity, 'count' => count($rows), 'limit' => $limit, 'summary' => $summary, 'alerts' => $alerts));
