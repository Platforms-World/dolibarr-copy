<?php
require __DIR__ . '/bootstrap.php';
$ctx = kafoApiRequireAuth(false);
$entityId = (int) $ctx['entity_id'];
$userId = GETPOSTINT('user_id');
$includePermissions = GETPOST('include_permissions', 'alpha') === '1';
$includeRoles = GETPOST('include_roles', 'alpha') === '1';

$rows = kafoApiGetUserRows($entityId, $userId);
if ($includePermissions || $includeRoles) {
    require_once __DIR__ . '/../class/SaasAccessService.php';
    $access = new SaasAccessService($db);
    foreach ($rows as &$row) {
        if ($includeRoles) {
            $row['roles'] = $access->getUserRoles((int) $row['id'], $entityId);
        }
        if ($includePermissions) {
            $row['direct_permissions'] = $access->getUserDirectPermissions((int) $row['id'], $entityId);
            $row['effective_permissions'] = $access->getUserEffectivePermissions((int) $row['id'], $entityId);
        }
    }
    unset($row);
}

kafoApiJson(array(
    'success' => true,
    'entity_id' => $entityId,
    'count' => count($rows),
    'users' => $rows,
));
