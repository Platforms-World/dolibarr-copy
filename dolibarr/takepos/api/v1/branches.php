<?php
/*
 * TakePOS API v1 - Branches (multi-branch management)
 * GET  : list branches (?active_only=), show one (?id=), branch users (?id=&users=1)
 * POST : create (action=create), update (action=update), assign user
 *        (action=assign), remove user (action=remove), reset password (action=reset_password)
 * Component: TakeposBranchService
 */
require_once __DIR__ . '/bootstrap.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposBranchService.class.php';

$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET');
if (!in_array($method, array('GET', 'POST'), true)) {
    takeposApiError('METHOD_NOT_ALLOWED', 'Method not allowed.', 405, null, array(), array('Allow: GET, POST'));
}

$auth = takeposApiAuth($db, ($method === 'GET' ? 'read' : 'write'), 'takepos.api_layer');
$entity = (int) $auth['entity'];
$user = $auth['user'];

TakeposBranchService::ensureSchema($db);

function takeposApiBranchPayload($row)
{
    return array(
        'id' => (int) $row->rowid,
        'code' => (!empty($row->branch_code) ? (string) $row->branch_code : null),
        'label' => (!empty($row->label) ? (string) $row->label : null),
        'description' => (!empty($row->description) ? (string) $row->description : null),
        'warehouse_id' => (!empty($row->fk_warehouse) ? (int) $row->fk_warehouse : null),
        'store_id' => (!empty($row->fk_store) ? (int) $row->fk_store : null),
        'active' => (isset($row->active) ? (int) $row->active : 1),
    );
}

if ($method === 'POST') {
    $body = takeposApiRequestBody();
    $action = isset($body['action']) ? strtolower(trim((string) $body['action'])) : 'create';

    try {
        if ($action === 'create') {
            $code = (string) takeposApiRequestRequireField($body, 'code');
            $label = (string) takeposApiRequestRequireField($body, 'label');
            $description = isset($body['description']) ? (string) $body['description'] : '';
            $warehouseId = isset($body['warehouse_id']) ? (int) $body['warehouse_id'] : 0;
            $storeId = isset($body['store_id']) ? (int) $body['store_id'] : 0;
            $result = TakeposBranchService::createBranch($db, $user, $entity, $code, $label, $description, $warehouseId, $storeId);
            takeposApiAuditAccess($db, $auth, 'branches.create', array('code' => $code));
            takeposApiSuccess($result, array('entity' => $entity), 201);
        }
        if ($action === 'update') {
            $branchId = (int) takeposApiRequestRequireField($body, 'id');
            $label = isset($body['label']) ? (string) $body['label'] : '';
            $description = isset($body['description']) ? (string) $body['description'] : '';
            $warehouseId = isset($body['warehouse_id']) ? (int) $body['warehouse_id'] : 0;
            $storeId = isset($body['store_id']) ? (int) $body['store_id'] : 0;
            $active = isset($body['active']) ? (int) $body['active'] : 1;
            TakeposBranchService::updateBranch($db, $user, $entity, $branchId, $label, $description, $warehouseId, $storeId, $active);
            $row = TakeposBranchService::getBranch($db, $entity, $branchId);
            takeposApiAuditAccess($db, $auth, 'branches.update', array('branch_id' => $branchId));
            takeposApiSuccess($row ? takeposApiBranchPayload($row) : array('id' => $branchId), array('entity' => $entity));
        }
        if ($action === 'assign') {
            $branchId = (int) takeposApiRequestRequireField($body, 'branch_id');
            $targetUserId = (int) takeposApiRequestRequireField($body, 'user_id');
            $role = isset($body['role']) ? (string) $body['role'] : 'cashier';
            $result = TakeposBranchService::assignUserToBranch($db, $user, $entity, $branchId, $targetUserId, $role);
            takeposApiAuditAccess($db, $auth, 'branches.assign', array('branch_id' => $branchId, 'user_id' => $targetUserId));
            takeposApiSuccess(array('branch_id' => $branchId, 'user_id' => $targetUserId, 'role' => $role, 'result' => $result), array('entity' => $entity));
        }
        if ($action === 'remove') {
            $branchId = (int) takeposApiRequestRequireField($body, 'branch_id');
            $targetUserId = (int) takeposApiRequestRequireField($body, 'user_id');
            $result = TakeposBranchService::removeUserFromBranch($db, $user, $entity, $branchId, $targetUserId);
            takeposApiAuditAccess($db, $auth, 'branches.remove', array('branch_id' => $branchId, 'user_id' => $targetUserId));
            takeposApiSuccess(array('branch_id' => $branchId, 'user_id' => $targetUserId, 'removed' => true, 'result' => $result), array('entity' => $entity));
        }
        if ($action === 'reset_password') {
            $branchId = (int) takeposApiRequestRequireField($body, 'branch_id');
            $result = TakeposBranchService::resetBranchPassword($db, $user, $entity, $branchId);
            takeposApiAuditAccess($db, $auth, 'branches.reset_password', array('branch_id' => $branchId));
            takeposApiSuccess($result, array('entity' => $entity));
        }

        takeposApiError('INVALID_PARAMETER', 'Unknown action.', 422);
    } catch (Throwable $e) {
        takeposApiError('BRANCH_OPERATION_FAILED', $e->getMessage(), 422);
    }
}

// GET
$id = GETPOSTINT('id');
if ($id > 0) {
    $row = TakeposBranchService::getBranch($db, $entity, $id);
    if (!$row) {
        takeposApiError('NOT_FOUND', 'Branch not found.', 404);
    }
    if (GETPOSTINT('users') === 1) {
        takeposApiAuditAccess($db, $auth, 'branches.users', array('branch_id' => $id));
        takeposApiSuccess(TakeposBranchService::getBranchUsers($db, $entity, $id), array('entity' => $entity, 'branch_id' => $id));
    }
    takeposApiAuditAccess($db, $auth, 'branches.show', array('branch_id' => $id));
    takeposApiSuccess(takeposApiBranchPayload($row), array('entity' => $entity));
}

$activeOnly = (GETPOSTINT('active_only') === 1);
$rows = array();
foreach (TakeposBranchService::listBranches($db, $entity, $activeOnly) as $row) {
    $rows[] = takeposApiBranchPayload($row);
}

takeposApiAuditAccess($db, $auth, 'branches.index', array('count' => count($rows)));
takeposApiSuccess($rows, array('entity' => $entity, 'count' => count($rows)));
