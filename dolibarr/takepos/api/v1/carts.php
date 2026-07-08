<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_request.php';
require_once __DIR__ . '/_invoice_common.php';
require_once __DIR__ . '/_held_common.php';

$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET');
if (!in_array($method, array('GET', 'POST'), true)) {
    takeposApiError('METHOD_NOT_ALLOWED', 'Method not allowed.', 405, null, array(), array('Allow: GET, POST'));
}

$auth = takeposApiAuth($db, ($method === 'GET' ? 'read' : 'write'), 'takepos.api_layer');
$entity = (int) $auth['entity'];

if ($method === 'POST') {
    $body = takeposApiRequestBody();
    $action = strtolower(trim((string) GETPOST('action', 'alpha')));

    if ($action === 'cancel') {
        $cartId = (int) takeposApiRequestRequireField($body, 'cart_id');
        $invoice = takeposApiRequireTakeposInvoice($db, $entity, $cartId);
        TakeposApiCheckoutService::resolveTerminalForInvoice($db, $entity, $invoice);
        takeposApiHeldCleanupInvoice($db, $entity, $cartId);
        takeposApiDeleteCartInvoice($db, $entity, $invoice);
        takeposApiSuccess(array('id' => $cartId, 'status' => 'cancelled'), array('entity' => $entity));
    }

    $terminalId = (int) takeposApiRequestRequireField($body, 'terminal_id');
    $terminal = takeposApiRequireTerminal($db, $entity, $terminalId, true);
    $thirdpartyId = (!empty($body['thirdparty_id']) ? (int) $body['thirdparty_id'] : takeposApiResolveDefaultThirdpartyId((string) $terminal->terminal_code));
    if ($thirdpartyId <= 0) {
        throw new TakeposApiException('INVALID_PARAMETER', 'thirdparty_id is required for this terminal.', 422);
    }

    $invoice = takeposApiCreateCartInvoice($db, $entity, $terminal, $thirdpartyId);
    takeposApiSuccess(takeposApiInvoiceSnapshot($db, $entity, $invoice, true), array('entity' => $entity), 201);
}

$cartId = GETPOSTINT('id');
if ($cartId > 0) {
    $invoice = takeposApiRequireTakeposInvoice($db, $entity, $cartId);
    TakeposApiCheckoutService::resolveTerminalForInvoice($db, $entity, $invoice);
    takeposApiSuccess(takeposApiInvoiceSnapshot($db, $entity, $invoice, true), array('entity' => $entity));
}

$terminalId = GETPOSTINT('terminal_id');
$status = GETPOST('status', 'aZ09');
$limit = GETPOSTINT('limit');
if ($limit <= 0) {
    $limit = 50;
}
if ($limit > 200) {
    $limit = 200;
}
$offset = GETPOSTINT('offset');
if ($offset < 0) {
    $offset = 0;
}

$where = ' WHERE entity = ' . ((int) $entity) . ' AND module_source = ' . chr(39) . 'takepos' . chr(39);
if ($terminalId > 0) {
    $terminal = takeposApiRequireTerminal($db, $entity, $terminalId, false);
    $where .= ' AND pos_source = ' . chr(39) . $db->escape($terminal->terminal_code) . chr(39);
} elseif (TakeposStoreService::enforceStoreRestrictionEnabled($db) && !empty($auth['user']->id) && empty($auth['user']->admin)) {
    $allowedStoreIds = TakeposStoreService::getUserStoreIds($db, $entity, (int) $auth['user']->id);
    if (empty($allowedStoreIds)) {
        $where .= ' AND 1 = 0';
    } else {
        $storeList = implode(',', array_map('intval', $allowedStoreIds));
        $where .= ' AND EXISTS (SELECT 1 FROM ' . TakeposTerminalService::tableTerminal() . ' tt'
            . ' WHERE tt.entity = ' . ((int) $entity)
            . ' AND tt.terminal_code = ' . MAIN_DB_PREFIX . 'facture.pos_source'
            . ' AND tt.active = 1'
            . ' AND tt.fk_store IN (' . $storeList . '))';
    }
}

$status = strtolower(trim((string) $status));
if ($status === 'draft') {
    $where .= ' AND fk_statut = 0';
} elseif ($status === 'validated') {
    $where .= ' AND fk_statut > 0';
} elseif ($status === 'paid') {
    $where .= ' AND paye = 1';
} elseif ($status !== '' && $status !== 'all') {
    throw new TakeposApiException('INVALID_PARAMETER', 'Invalid status filter.', 422);
}

$countSql = 'SELECT COUNT(rowid) AS nb FROM ' . MAIN_DB_PREFIX . 'facture' . $where;
$countRes = $db->query($countSql);
$total = 0;
if ($countRes && ($countObj = $db->fetch_object($countRes))) {
    $total = (int) $countObj->nb;
}

$sql = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . 'facture' . $where . ' ORDER BY rowid DESC LIMIT ' . $offset . ', ' . $limit;
$rows = array();
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $invoice = takeposApiFetchInvoice($db, (int) $obj->rowid);
        if ($invoice) {
            $rows[] = takeposApiInvoiceSnapshot($db, $entity, $invoice, true);
        }
    }
}

takeposApiSuccess($rows, array('entity' => $entity, 'count' => count($rows), 'total' => $total, 'limit' => $limit, 'offset' => $offset));
