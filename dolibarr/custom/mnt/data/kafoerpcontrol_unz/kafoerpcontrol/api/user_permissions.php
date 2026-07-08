<?php
require __DIR__ . '/bootstrap.php';
$method = strtoupper($_SERVER['REQUEST_METHOD']);
$needWrite = in_array($method, array('POST', 'PUT', 'PATCH'), true);
$ctx = kafoApiRequireAuth($needWrite);
$entityId = (int) $ctx['entity_id'];
$access = $ctx['access'];

if ($method === 'GET') {
    $userId = GETPOSTINT('user_id');
    if ($userId <= 0) {
        kafoApiJson(array('success' => false, 'error' => 'user_id is required'), 400);
    }
    kafoApiJson(array(
        'success' => true,
        'entity_id' => $entityId,
        'user_id' => $userId,
        'roles' => $access->getUserRoles($userId, $entityId),
        'direct_permissions' => $access->getUserDirectPermissions($userId, $entityId),
        'effective_permissions' => $access->getUserEffectivePermissions($userId, $entityId),
    ));
}

if (!in_array($method, array('POST', 'PUT', 'PATCH'), true)) {
    kafoApiJson(array('success' => false, 'error' => 'Method not allowed'), 405);
}

$body = kafoApiReadJsonBody();
$userId = isset($body['user_id']) ? (int) $body['user_id'] : 0;
$permissions = isset($body['permissions']) && is_array($body['permissions']) ? $body['permissions'] : array();
$replace = !empty($body['replace']);
if ($userId <= 0) {
    kafoApiJson(array('success' => false, 'error' => 'user_id is required'), 400);
}
if (empty($permissions)) {
    kafoApiJson(array('success' => false, 'error' => 'permissions array is required'), 400);
}

$actorUserId = is_object($user) ? (int) $user->id : 0;
$oldDirect = $access->getUserDirectPermissions($userId, $entityId);
$result = $access->setUserDirectPermissions($userId, $permissions, $entityId, $replace);
if (!$result['success']) {
    kafoApiJson(array('success' => false, 'error' => $result['error']), 500);
}

$audit = new KafoAuditLogService($db);
$audit->logAction(
    $actorUserId,
    $userId,
    'api_permissions_update',
    'user_permission',
    (string) $userId,
    $oldDirect,
    $access->getUserDirectPermissions($userId, $entityId),
    'User direct permissions updated via API',
    array('context' => 'api/user_permissions.php', 'replace' => $replace)
);

kafoApiJson(array(
    'success' => true,
    'entity_id' => $entityId,
    'user_id' => $userId,
    'updated' => $result['updated'],
    'deleted' => $result['deleted'],
    'direct_permissions' => $access->getUserDirectPermissions($userId, $entityId),
    'effective_permissions' => $access->getUserEffectivePermissions($userId, $entityId),
));
