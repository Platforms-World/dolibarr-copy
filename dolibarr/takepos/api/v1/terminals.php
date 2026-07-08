<?php
require_once __DIR__ . '/bootstrap.php';

function takeposApiFormatTerminalRow($row)
{
    $metadata = json_decode((string) $row->metadata_json, true);
    if (!is_array($metadata)) {
        $metadata = array();
    }

    return array(
        'id' => (int) $row->rowid,
        'terminal_code' => (string) $row->terminal_code,
        'label' => (string) $row->label,
        'store_id' => (!empty($row->fk_store) ? (int) $row->fk_store : null),
        'store_label' => (!empty($row->store_label) ? (string) $row->store_label : null),
        'status' => (int) $row->active,
        'default_customer_id' => (!empty($metadata['default_customer_id']) ? (int) $metadata['default_customer_id'] : null),
        'warehouse_id' => (!empty($metadata['warehouse_id']) ? (int) $metadata['warehouse_id'] : (!empty($row->warehouse_id) ? (int) $row->warehouse_id : null)),
        'last_seen' => (!empty($row->last_seen) ? (string) $row->last_seen : null),
    );
}

takeposApiRequireMethod(array('GET'));

$auth = takeposApiAuth($db, 'read', 'takepos.api_layer');
$entity = (int) $auth['entity'];
$terminalId = GETPOSTINT('id');
$storeId = GETPOSTINT('store_id');
$activeOnly = (GETPOSTINT('active_only') > 0);
$userStoreIds = array();

if (TakeposStoreService::enforceStoreRestrictionEnabled($db) && empty($user->admin)) {
    $userStoreIds = TakeposStoreService::getUserStoreIds($db, $entity, (int) $user->id);
}

TakeposTerminalService::ensureSchema($db);

$sql = 'SELECT t.rowid, t.terminal_code, t.label, t.fk_store, t.active, t.last_seen, t.metadata_json, s.label AS store_label, s.warehouse_id';
$sql .= ' FROM ' . TakeposTerminalService::tableTerminal() . ' t';
$sql .= ' LEFT JOIN ' . TakeposStoreService::tableStore() . ' s ON s.rowid = t.fk_store AND s.entity = t.entity';
$sql .= ' WHERE t.entity = ' . $entity;
if ($terminalId > 0) {
    $sql .= ' AND t.rowid = ' . $terminalId;
}
if ($storeId > 0) {
    $sql .= ' AND t.fk_store = ' . $storeId;
}
if ($activeOnly) {
    $sql .= ' AND t.active = 1';
}
$sql .= ' ORDER BY t.terminal_code ASC, t.rowid ASC';

$rows = array();
$resql = $db->query($sql);
if ($resql) {
    while ($row = $db->fetch_object($resql)) {
        if (!empty($userStoreIds) && !empty($row->fk_store) && !in_array((int) $row->fk_store, $userStoreIds, true)) {
            continue;
        }
        $rows[] = takeposApiFormatTerminalRow($row);
    }
}

if ($terminalId > 0) {
    if (empty($rows)) {
        takeposApiError('NOT_FOUND', 'Terminal not found.', 404);
    }
    takeposApiAuditAccess($db, $auth, 'terminals.show', array('terminal_id' => $terminalId));
    takeposApiSuccess($rows[0], array('entity' => $entity));
}

takeposApiAuditAccess($db, $auth, 'terminals.index', array('store_id' => $storeId, 'active_only' => ($activeOnly ? 1 : 0), 'count' => count($rows)));
takeposApiSuccess($rows, array('entity' => $entity, 'count' => count($rows)));
