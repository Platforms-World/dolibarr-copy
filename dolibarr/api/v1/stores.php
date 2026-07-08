<?php
require_once __DIR__ . '/bootstrap.php';

takeposApiRequireMethod(array('GET'));

$auth = takeposApiAuth($db, 'read', 'takepos.api_layer');
$entity = (int) $auth['entity'];
$storeId = GETPOSTINT('id');
$activeOnly = (GETPOSTINT('active_only') > 0);
$userStoreIds = array();

if (TakeposStoreService::enforceStoreRestrictionEnabled($db) && empty($user->admin)) {
    $userStoreIds = TakeposStoreService::getUserStoreIds($db, $entity, (int) $user->id);
}

if ($storeId > 0) {
    $store = TakeposStoreService::getStore($db, $entity, $storeId);
    if (!$store) {
        takeposApiError('NOT_FOUND', 'Store not found.', 404);
    }
    if (!empty($userStoreIds) && !in_array((int) $store->rowid, $userStoreIds, true)) {
        takeposApiError('FORBIDDEN', 'Store access is denied for this user.', 403);
    }

    takeposApiAuditAccess($db, $auth, 'stores.show', array('store_id' => $storeId));
    takeposApiSuccess(array(
        'id' => (int) $store->rowid,
        'code' => (string) $store->code,
        'label' => (string) $store->label,
        'description' => (string) $store->description,
        'warehouse_id' => (!empty($store->warehouse_id) ? (int) $store->warehouse_id : null),
        'status' => (int) $store->active,
    ), array('entity' => $entity));
}

$rows = array();
foreach (TakeposStoreService::listStores($db, $entity, $activeOnly) as $store) {
    if (!empty($userStoreIds) && !in_array((int) $store->rowid, $userStoreIds, true)) {
        continue;
    }

    $rows[] = array(
        'id' => (int) $store->rowid,
        'code' => (string) $store->code,
        'label' => (string) $store->label,
        'description' => (string) $store->description,
        'warehouse_id' => (!empty($store->warehouse_id) ? (int) $store->warehouse_id : null),
        'status' => (int) $store->active,
    );
}

takeposApiAuditAccess($db, $auth, 'stores.index', array('active_only' => ($activeOnly ? 1 : 0), 'count' => count($rows)));
takeposApiSuccess($rows, array('entity' => $entity, 'count' => count($rows)));
