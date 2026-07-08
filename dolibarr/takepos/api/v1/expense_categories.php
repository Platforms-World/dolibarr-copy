<?php
/*
 * TakePOS API v1 - Expense Categories
 * GET  : list categories or show one (?id=)
 * POST : create/update (action=save) or enable/disable (action=status)
 * Component: TakeposExpenseService (category methods)
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

function takeposApiExpenseCategoryPayload($row)
{
    return array(
        'id' => (int) $row->rowid,
        'label' => (string) $row->label,
        'accountancy_code' => (!empty($row->accountancy_code) ? (string) $row->accountancy_code : null),
        'vat_default' => (isset($row->vat_default) ? (float) $row->vat_default : null),
        'pos_visible' => (isset($row->pos_visible) ? (int) $row->pos_visible : 1),
        'active' => (isset($row->active) ? (int) $row->active : 1),
    );
}

if ($method === 'POST') {
    if (!TakeposExpenseService::canAdmin($db, $user)) {
        takeposApiError('FORBIDDEN', 'Expense administration permission is required.', 403);
    }
    $body = takeposApiRequestBody();
    $action = isset($body['action']) ? strtolower(trim((string) $body['action'])) : 'save';

    try {
        if ($action === 'status') {
            $categoryId = (int) takeposApiRequestRequireField($body, 'category_id');
            $active = !empty($body['active']) ? 1 : 0;
            TakeposExpenseService::setCategoryStatus($db, $user, $entity, $categoryId, $active);
            $row = TakeposExpenseService::getCategory($db, $entity, $categoryId);
            takeposApiAuditAccess($db, $auth, 'expense_categories.status', array('category_id' => $categoryId, 'active' => $active));
            takeposApiSuccess($row ? takeposApiExpenseCategoryPayload($row) : array('id' => $categoryId, 'active' => $active), array('entity' => $entity));
        }

        $existingId = isset($body['id']) ? (int) $body['id'] : 0;
        $data = array(
            'label' => isset($body['label']) ? (string) $body['label'] : '',
            'accountancy_code' => isset($body['accountancy_code']) ? (string) $body['accountancy_code'] : '',
            'vat_default' => isset($body['vat_default']) ? (float) $body['vat_default'] : 0,
            'pos_visible' => isset($body['pos_visible']) ? (int) $body['pos_visible'] : 1,
            'active' => isset($body['active']) ? (int) $body['active'] : 1,
        );
        $categoryId = TakeposExpenseService::saveCategory($db, $user, $entity, $data, $existingId);
        $row = TakeposExpenseService::getCategory($db, $entity, (int) $categoryId);
        takeposApiAuditAccess($db, $auth, ($existingId ? 'expense_categories.update' : 'expense_categories.create'), array('category_id' => (int) $categoryId));
        takeposApiSuccess($row ? takeposApiExpenseCategoryPayload($row) : array('id' => (int) $categoryId), array('entity' => $entity), ($existingId ? 200 : 201));
    } catch (Throwable $e) {
        takeposApiError('EXPENSE_CATEGORY_SAVE_FAILED', $e->getMessage(), 422);
    }
}

// GET
if (!TakeposExpenseService::canRead($db, $user)) {
    takeposApiError('FORBIDDEN', 'Expense read permission is required.', 403);
}

$id = GETPOSTINT('id');
if ($id > 0) {
    $row = TakeposExpenseService::getCategory($db, $entity, $id);
    if (!$row) {
        takeposApiError('NOT_FOUND', 'Expense category not found.', 404);
    }
    takeposApiAuditAccess($db, $auth, 'expense_categories.show', array('category_id' => $id));
    takeposApiSuccess(takeposApiExpenseCategoryPayload($row), array('entity' => $entity));
}

$visibleOnly = (GETPOSTINT('visible_only') === 1);
$rows = array();
foreach (TakeposExpenseService::listCategories($db, $entity, $visibleOnly) as $row) {
    $rows[] = takeposApiExpenseCategoryPayload($row);
}

takeposApiAuditAccess($db, $auth, 'expense_categories.index', array('count' => count($rows)));
takeposApiSuccess($rows, array('entity' => $entity, 'count' => count($rows)));
