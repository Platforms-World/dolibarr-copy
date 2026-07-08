<?php
/*
 * TakePOS API v1 - Purchases (supplier goods receipts)
 * GET  : list recent purchases, show one (?id=), or lookups (?lookups=1)
 * POST : create (action=create) or update (action=update) a purchase receipt
 * Component: TakeposPurchaseService
 */
require_once __DIR__ . '/bootstrap.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposPurchaseService.class.php';

$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET');
if (!in_array($method, array('GET', 'POST'), true)) {
    takeposApiError('METHOD_NOT_ALLOWED', 'Method not allowed.', 405, null, array(), array('Allow: GET, POST'));
}

$auth = takeposApiAuth($db, ($method === 'GET' ? 'read' : 'write'), 'takepos.api_layer');
$entity = (int) $auth['entity'];
$user = $auth['user'];

TakeposPurchaseService::ensureSchema($db);

function takeposApiPurchasePayload($db, $entity, $row, $withLines = false)
{
    $data = array(
        'id' => (int) $row->rowid,
        'ref' => (string) $row->ref,
        'purchase_date' => (!empty($row->purchase_date) ? (string) $row->purchase_date : null),
        'supplier_id' => (!empty($row->fk_supplier) ? (int) $row->fk_supplier : null),
        'supplier_name' => (!empty($row->supplier_name) ? (string) $row->supplier_name : null),
        'warehouse_id' => (!empty($row->fk_warehouse) ? (int) $row->fk_warehouse : null),
        'warehouse_label' => (!empty($row->warehouse_label) ? (string) $row->warehouse_label : null),
        'external_ref' => (!empty($row->external_ref) ? (string) $row->external_ref : null),
        'supplier_invoice_ref' => (!empty($row->supplier_invoice_ref) ? (string) $row->supplier_invoice_ref : null),
        'note_private' => (!empty($row->note_private) ? (string) $row->note_private : null),
        'status' => (isset($row->status) ? (int) $row->status : null),
        'total_ht' => (float) price2num(isset($row->total_ht) ? $row->total_ht : 0, 'MT'),
        'total_tva' => (float) price2num(isset($row->total_tva) ? $row->total_tva : 0, 'MT'),
        'total_ttc' => (float) price2num(isset($row->total_ttc) ? $row->total_ttc : 0, 'MT'),
    );
    if ($withLines) {
        $lines = array();
        foreach (TakeposPurchaseService::listPurchaseLines($db, $entity, (int) $row->rowid) as $line) {
            $lines[] = array(
                'id' => (int) $line->rowid,
                'product_id' => (!empty($line->fk_product) ? (int) $line->fk_product : null),
                'product_ref' => (!empty($line->product_ref) ? (string) $line->product_ref : null),
                'product_label' => (!empty($line->product_label) ? (string) $line->product_label : null),
                'qty' => (float) $line->qty,
                'buy_price_ht' => (float) price2num($line->buy_price_ht, 'MT'),
                'tva_tx' => (float) $line->tva_tx,
                'total_ht' => (float) price2num($line->total_ht, 'MT'),
                'total_ttc' => (float) price2num($line->total_ttc, 'MT'),
            );
        }
        $data['lines'] = $lines;
    }
    return $data;
}

if ($method === 'POST') {
    if (!TakeposPurchaseService::canCreate($db, $user)) {
        takeposApiError('FORBIDDEN', 'Purchase create permission is required.', 403);
    }
    $body = takeposApiRequestBody();
    $action = isset($body['action']) ? strtolower(trim((string) $body['action'])) : 'create';

    $rawLines = isset($body['lines']) && is_array($body['lines']) ? $body['lines'] : array();
    $lines = array();
    foreach ($rawLines as $ln) {
        $lines[] = array(
            'product_id' => isset($ln['product_id']) ? (int) $ln['product_id'] : 0,
            'product_ref' => isset($ln['product_ref']) ? (string) $ln['product_ref'] : '',
            'product_label' => isset($ln['product_label']) ? (string) $ln['product_label'] : '',
            'qty' => isset($ln['qty']) ? (float) $ln['qty'] : 0,
            'buy_price_ht' => isset($ln['buy_price_ht']) ? (float) $ln['buy_price_ht'] : 0,
            'tva_tx' => isset($ln['tva_tx']) ? (float) $ln['tva_tx'] : 0,
            'total_ht' => isset($ln['total_ht']) ? (float) $ln['total_ht'] : 0,
            'total_tva' => isset($ln['total_tva']) ? (float) $ln['total_tva'] : 0,
            'total_ttc' => isset($ln['total_ttc']) ? (float) $ln['total_ttc'] : 0,
            'note_line' => isset($ln['note_line']) ? (string) $ln['note_line'] : '',
        );
    }

    $data = array(
        'supplier_id' => isset($body['supplier_id']) ? (int) $body['supplier_id'] : 0,
        'warehouse_id' => isset($body['warehouse_id']) ? (int) $body['warehouse_id'] : 0,
        'purchase_date' => isset($body['purchase_date']) ? (string) $body['purchase_date'] : '',
        'external_ref' => isset($body['external_ref']) ? (string) $body['external_ref'] : '',
        'supplier_invoice_ref' => isset($body['supplier_invoice_ref']) ? (string) $body['supplier_invoice_ref'] : '',
        'note_private' => isset($body['note_private']) ? (string) $body['note_private'] : '',
        'total_ht' => isset($body['total_ht']) ? (float) $body['total_ht'] : 0,
        'total_tva' => isset($body['total_tva']) ? (float) $body['total_tva'] : 0,
        'total_ttc' => isset($body['total_ttc']) ? (float) $body['total_ttc'] : 0,
        'lines' => $lines,
    );

    try {
        if ($action === 'update') {
            $purchaseId = (int) takeposApiRequestRequireField($body, 'id');
            $purchaseId = TakeposPurchaseService::updatePurchase($db, $user, $purchaseId, $data);
        } else {
            $purchaseId = TakeposPurchaseService::createPurchase($db, $user, $data);
        }
        $row = TakeposPurchaseService::getPurchaseById($db, $entity, (int) $purchaseId);
        takeposApiAuditAccess($db, $auth, 'purchases.' . $action, array('purchase_id' => (int) $purchaseId));
        takeposApiSuccess(takeposApiPurchasePayload($db, $entity, $row, true), array('entity' => $entity), ($action === 'update' ? 200 : 201));
    } catch (Throwable $e) {
        takeposApiError('PURCHASE_SAVE_FAILED', $e->getMessage(), 422);
    }
}

// GET
if (!TakeposPurchaseService::canRead($db, $user)) {
    takeposApiError('FORBIDDEN', 'Purchase read permission is required.', 403);
}

if (GETPOSTINT('lookups') === 1) {
    takeposApiSuccess(array(
        'warehouses' => TakeposPurchaseService::listWarehouses($db, $entity),
        'suppliers' => TakeposPurchaseService::listSuppliers($db, $entity),
        'products' => TakeposPurchaseService::listBuyableProducts($db, $entity),
    ), array('entity' => $entity));
}

$id = GETPOSTINT('id');
if ($id > 0) {
    $row = TakeposPurchaseService::getPurchaseById($db, $entity, $id);
    if (!$row) {
        takeposApiError('NOT_FOUND', 'Purchase receipt not found.', 404);
    }
    takeposApiAuditAccess($db, $auth, 'purchases.show', array('purchase_id' => $id));
    takeposApiSuccess(takeposApiPurchasePayload($db, $entity, $row, true), array('entity' => $entity));
}

$limit = GETPOSTINT('limit'); if ($limit <= 0) { $limit = 50; } if ($limit > 500) { $limit = 500; }
$rows = array();
foreach (TakeposPurchaseService::listRecentPurchases($db, $entity, $limit) as $row) {
    $rows[] = takeposApiPurchasePayload($db, $entity, $row, false);
}

takeposApiAuditAccess($db, $auth, 'purchases.index', array('count' => count($rows)));
takeposApiSuccess($rows, array('entity' => $entity, 'count' => count($rows), 'limit' => $limit));
