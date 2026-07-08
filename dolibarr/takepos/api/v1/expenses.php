<?php
/*
 * TakePOS API v1 - Expenses
 * GET  : list expenses (filters) or show one (?id=)
 * POST : create an expense (action=create) or post/commit one (action=post)
 * Component: TakeposExpenseService
 */
require_once __DIR__ . '/bootstrap.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposExpenseService.class.php';

$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET');
if (!in_array($method, array('GET', 'POST'), true)) {
    takeposApiError('METHOD_NOT_ALLOWED', 'Method not allowed.', 405, null, array(), array('Allow: GET, POST'));
}

$auth = takeposApiAuth($db, ($method === 'GET' ? 'read' : 'write'), 'takepos.api_layer');
$entity = (int) $auth['entity'];
$user = $auth['user'];

TakeposExpenseService::ensureSchema($db);

function takeposApiExpensePayload($row)
{
    return array(
        'id' => (int) $row->rowid,
        'ref' => (!empty($row->ref) ? (string) $row->ref : null),
        'label' => (!empty($row->label) ? (string) $row->label : null),
        'description' => (!empty($row->description) ? (string) $row->description : null),
        'amount_ttc' => (float) price2num(isset($row->amount_ttc) ? $row->amount_ttc : 0, 'MT'),
        'vat_rate' => (isset($row->vat_rate) ? (float) $row->vat_rate : null),
        'category_id' => (!empty($row->fk_category) ? (int) $row->fk_category : null),
        'category_label' => (!empty($row->category_label) ? (string) $row->category_label : null),
        'terminal_id' => (!empty($row->fk_terminal) ? (int) $row->fk_terminal : null),
        'payment_source' => (!empty($row->payment_source) ? (string) $row->payment_source : null),
        'status' => (isset($row->status) ? (int) $row->status : null),
        'status_label' => TakeposExpenseService::statusLabel(isset($row->status) ? (int) $row->status : 0),
        'date_expense' => (!empty($row->date_expense) ? (string) $row->date_expense : null),
        'created_by' => (!empty($row->fk_user_creat) ? (int) $row->fk_user_creat : null),
    );
}

if ($method === 'POST') {
    if (!TakeposExpenseService::canCreate($db, $user)) {
        takeposApiError('FORBIDDEN', 'Expense create permission is required.', 403);
    }
    $body = takeposApiRequestBody();
    $action = isset($body['action']) ? strtolower(trim((string) $body['action'])) : 'create';

    try {
        if ($action === 'post') {
            if (!TakeposExpenseService::canPost($db, $user)) {
                takeposApiError('FORBIDDEN', 'Expense post permission is required.', 403);
            }
            $expenseId = (int) takeposApiRequestRequireField($body, 'expense_id');
            $terminalToken = isset($body['terminal_token']) ? (string) $body['terminal_token'] : '';
            $result = TakeposExpenseService::postExpense($db, $user, $expenseId, $terminalToken);
            takeposApiAuditAccess($db, $auth, 'expenses.post', array('expense_id' => $expenseId));
            $row = TakeposExpenseService::getExpenseById($db, $entity, $expenseId);
            takeposApiSuccess($row ? takeposApiExpensePayload($row) : array('id' => $expenseId, 'posted' => true), array('entity' => $entity));
        }

        // create / update
        $existingId = isset($body['id']) ? (int) $body['id'] : 0;
        $data = array(
            'fk_terminal' => isset($body['terminal_id']) ? (int) $body['terminal_id'] : 0,
            'fk_category' => isset($body['category_id']) ? (int) $body['category_id'] : 0,
            'description' => isset($body['description']) ? (string) $body['description'] : '',
            'label' => isset($body['label']) ? (string) $body['label'] : '',
            'amount_ttc' => isset($body['amount_ttc']) ? (float) $body['amount_ttc'] : 0,
            'vat_rate' => isset($body['vat_rate']) ? (float) $body['vat_rate'] : 0,
            'payment_source' => isset($body['payment_source']) ? (string) $body['payment_source'] : '',
            'fk_bank_account' => isset($body['bank_account_id']) ? (int) $body['bank_account_id'] : 0,
            'date_expense' => isset($body['date_expense']) ? (string) $body['date_expense'] : '',
            'external_ref' => isset($body['external_ref']) ? (string) $body['external_ref'] : '',
            'note_private' => isset($body['note_private']) ? (string) $body['note_private'] : '',
        );
        $expenseId = TakeposExpenseService::saveExpense($db, $user, $data, $existingId);
        $row = TakeposExpenseService::getExpenseById($db, $entity, (int) $expenseId);
        takeposApiAuditAccess($db, $auth, ($existingId ? 'expenses.update' : 'expenses.create'), array('expense_id' => (int) $expenseId));
        takeposApiSuccess($row ? takeposApiExpensePayload($row) : array('id' => (int) $expenseId), array('entity' => $entity), ($existingId ? 200 : 201));
    } catch (Throwable $e) {
        takeposApiError('EXPENSE_SAVE_FAILED', $e->getMessage(), 422);
    }
}

// GET
if (!TakeposExpenseService::canRead($db, $user)) {
    takeposApiError('FORBIDDEN', 'Expense read permission is required.', 403);
}

$id = GETPOSTINT('id');
if ($id > 0) {
    $row = TakeposExpenseService::getExpenseById($db, $entity, $id);
    if (!$row) {
        takeposApiError('NOT_FOUND', 'Expense not found.', 404);
    }
    takeposApiAuditAccess($db, $auth, 'expenses.show', array('expense_id' => $id));
    takeposApiSuccess(takeposApiExpensePayload($row), array('entity' => $entity));
}

$filters = array(
    'date_from' => GETPOST('date_from', 'alphanohtml'),
    'date_to' => GETPOST('date_to', 'alphanohtml'),
    'category_id' => GETPOSTINT('category_id'),
    'terminal_id' => GETPOSTINT('terminal_id'),
    'status' => GETPOST('status', 'alphanohtml'),
);
$limit = GETPOSTINT('limit'); if ($limit <= 0) { $limit = 50; } if ($limit > 500) { $limit = 500; }
$offset = GETPOSTINT('offset'); if ($offset < 0) { $offset = 0; }

$rows = array();
foreach (TakeposExpenseService::listExpenses($db, $entity, $filters, $limit, $offset, 'date', 'DESC', $user) as $row) {
    $rows[] = takeposApiExpensePayload($row);
}
$summary = TakeposExpenseService::summarizeExpenses($db, $entity, $user, $filters);

takeposApiAuditAccess($db, $auth, 'expenses.index', array('count' => count($rows)));
takeposApiSuccess($rows, array('entity' => $entity, 'count' => count($rows), 'limit' => $limit, 'offset' => $offset, 'summary' => $summary));
