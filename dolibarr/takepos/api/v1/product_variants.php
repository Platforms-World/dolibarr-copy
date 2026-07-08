<?php
/**
 * TakePOS API v1 — Product Variants (Piece / Box)
 *
 * GET    /takepos/api/v1/product_variants.php
 *        List all variant links.
 *        ?product_id=42  — get the variant for a specific product (returns piece + box info)
 *        ?id=5           — get a single variant by rowid
 *
 * POST   /takepos/api/v1/product_variants.php
 *        Create or update a piece/box link.
 *        Body: { piece_product_id, box_product_id, units_per_box, label_piece, label_box }
 *
 * PATCH  /takepos/api/v1/product_variants.php
 *        Sell by unit type — add either the piece OR the box product to a cart.
 *        Body: { product_id, unit: "piece"|"box", cart_id, qty }
 *        This is the "sell piece/box" endpoint — no need to know the linked product ID.
 *
 * DELETE /takepos/api/v1/product_variants.php?id={rowid}
 *        Remove a variant link.
 *
 * Auth: Bearer token (standard API v1 auth)
 *
 * Table: llx_takepos_product_variants
 *   rowid, fk_product_piece, fk_product_box, units_per_box,
 *   label_piece, label_box, entity
 */
require_once __DIR__ . '/bootstrap.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET');
if (!in_array($method, array('GET', 'POST', 'PATCH', 'DELETE'), true)) {
    takeposApiError('METHOD_NOT_ALLOWED', 'Method not allowed.', 405, null, array(), array('Allow: GET, POST, PATCH, DELETE'));
}

$auth   = takeposApiAuth($db, ($method === 'GET' ? 'read' : 'write'), 'takepos.api_layer');
$entity = (int) $auth['entity'];

// FIX (B6+B7): Replaced unconditional CREATE TABLE (ran on every request,
// causing InnoDB metadata lock contention under load) with a one-time check.
// Also fixed charset: utf8 → utf8mb4 so Arabic label_piece/label_box values
// are stored correctly instead of being silently truncated at 4-byte chars.
// The canonical DDL lives in sql/takepos_variants_install.sql; this is a
// safety net for installs that skipped the migration.
if (!TakeposMigration::tableExists($db, MAIN_DB_PREFIX . 'takepos_product_variants')) {
    $db->query(
        'CREATE TABLE IF NOT EXISTS ' . MAIN_DB_PREFIX . 'takepos_product_variants ('
        . ' rowid            INT NOT NULL AUTO_INCREMENT PRIMARY KEY,'
        . ' fk_product_piece INT NOT NULL,'
        . ' fk_product_box   INT NOT NULL,'
        . ' units_per_box    INT NOT NULL DEFAULT 1,'
        . " label_piece      VARCHAR(100) NOT NULL DEFAULT 'Piece',"
        . " label_box        VARCHAR(100) NOT NULL DEFAULT 'Box',"
        . ' entity           INT NOT NULL DEFAULT 1,'
        . ' UNIQUE KEY uq_piece_box (fk_product_piece, fk_product_box, entity)'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

// ════════════════════════════════════════════════════════════════════════════
// PATCH — sell by unit type (add piece or box product to cart)
// ════════════════════════════════════════════════════════════════════════════
if ($method === 'PATCH') {
    require_once __DIR__ . '/_request.php';
    require_once __DIR__ . '/_invoice_common.php';
    require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
    require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
    require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposApiCheckoutService.class.php';

    $body      = takeposApiRequestBody();
    $productId = (int)    takeposApiRequestRequireField($body, 'product_id');
    $unit      = strtolower(trim((string) takeposApiRequestRequireField($body, 'unit')));
    $cartId    = (int)    takeposApiRequestRequireField($body, 'cart_id');
    $qty       = !empty($body['qty']) ? (float) $body['qty'] : 1.0;

    if (!in_array($unit, array('piece', 'box'), true)) {
        throw new TakeposApiException('INVALID_PARAMETER', 'unit must be "piece" or "box".', 422);
    }
    if ($qty <= 0) {
        throw new TakeposApiException('INVALID_PARAMETER', 'qty must be greater than zero.', 422);
    }

    // Look up the variant — product can be either side
    $sqlV = 'SELECT v.fk_product_piece, v.fk_product_box, v.units_per_box, v.label_piece, v.label_box,'
        . ' pp.ref AS piece_ref, pp.label AS piece_name,'
        . ' pb.ref AS box_ref, pb.label AS box_name'
        . ' FROM ' . MAIN_DB_PREFIX . 'takepos_product_variants v'
        . ' LEFT JOIN ' . MAIN_DB_PREFIX . 'product pp ON pp.rowid = v.fk_product_piece'
        . ' LEFT JOIN ' . MAIN_DB_PREFIX . 'product pb ON pb.rowid = v.fk_product_box'
        . ' WHERE v.entity = ' . $entity
        . ' AND (v.fk_product_piece = ' . (int)$productId . ' OR v.fk_product_box = ' . (int)$productId . ')'
        . ' LIMIT 1';
    $resV = $db->query($sqlV);
    if (!$resV || !$db->num_rows($resV)) {
        throw new TakeposApiException('NOT_FOUND', 'No piece/box variant found for product ' . $productId . '.', 404);
    }
    $variant = $db->fetch_object($resV);

    // Resolve which product to add based on requested unit
    $targetProductId = ($unit === 'piece')
        ? (int) $variant->fk_product_piece
        : (int) $variant->fk_product_box;

    // Load cart
    $invoice = takeposApiRequireTakeposInvoice($db, $entity, $cartId);
    takeposApiAssertDraftInvoice($invoice);
    $terminal = TakeposApiCheckoutService::resolveTerminalForInvoice($db, $entity, $invoice);

    // Load target product
    $product = new Product($db);
    if ($product->fetch($targetProductId) <= 0) {
        throw new TakeposApiException('NOT_FOUND', 'Target product not found.', 404);
    }

    // Stock check
    TakeposApiCheckoutService::assertProductStockAvailableForInvoice($db, $entity, $invoice, $terminal, $product, $qty);

    // Get customer for pricing
    $customer = new Societe($db);
    if (!empty($invoice->socid)) {
        $customer->fetch((int) $invoice->socid);
    }
    $priceData    = $product->getSellPrice($mysoc, $customer, 0);
    $priceBaseType= (!empty($priceData['price_base_type']) ? (string) $priceData['price_base_type'] : 'HT');
    $tvaTx        = (float) $priceData['tva_tx'];
    $tvaNpr       = (!empty($priceData['tva_npr']) ? (int) $priceData['tva_npr'] : 0);
    $localtax1    = get_localtax($tvaTx, 1, $customer, $mysoc, $tvaNpr);
    $localtax2    = get_localtax($tvaTx, 2, $customer, $mysoc, $tvaNpr);
    $price        = (float) price2num($priceData['pu_ht'],  'MU');
    $priceTtc     = (float) price2num($priceData['pu_ttc'], 'MU');

    $res = $invoice->addline(
        $product->description, $price, $qty, $tvaTx, $localtax1, $localtax2,
        $targetProductId, 0, '', 0, 0, 0, 0, $priceBaseType, $priceTtc,
        $product->type, -1, 0, '', 0, 0, 0, '', array(), 100, 0, null, 0
    );
    if ($res <= 0) {
        throw new TakeposApiException('INTERNAL_ERROR', !empty($invoice->error) ? $invoice->error : 'Failed to add item.', 500);
    }

    $invoice = takeposApiRequireTakeposInvoice($db, $entity, $cartId);

    takeposApiAuditAccess($db, $auth, 'product_variants.sell', array(
        'product_id'       => $productId,
        'unit'             => $unit,
        'target_product_id'=> $targetProductId,
        'cart_id'          => $cartId,
        'qty'              => $qty,
    ));

    takeposApiSuccess(array(
        'unit_sold'         => $unit,
        'product_added_id'  => $targetProductId,
        'product_added_ref' => (string) $product->ref,
        'qty'               => $qty,
        'variant' => array(
            'piece_id'     => (int)    $variant->fk_product_piece,
            'piece_ref'    => (string) $variant->piece_ref,
            'box_id'       => (int)    $variant->fk_product_box,
            'box_ref'      => (string) $variant->box_ref,
            'units_per_box'=> (int)    $variant->units_per_box,
        ),
        'cart' => takeposApiInvoiceSnapshot($db, $entity, $invoice, true),
    ), array('entity' => $entity), 201);
}

// ════════════════════════════════════════════════════════════════════════════
// DELETE — remove a variant link
// ════════════════════════════════════════════════════════════════════════════
if ($method === 'DELETE') {
    $id = GETPOSTINT('id');
    if ($id <= 0) {
        throw new TakeposApiException('INVALID_PARAMETER', 'id is required.', 422);
    }
    $sql = 'DELETE FROM ' . MAIN_DB_PREFIX . 'takepos_product_variants'
         . ' WHERE rowid = ' . (int)$id . ' AND entity = ' . $entity;
    if (!$db->query($sql)) {
        throw new TakeposApiException('INTERNAL_ERROR', 'Delete failed: ' . $db->lasterror(), 500);
    }
    if ($db->affected_rows($db->db) === 0) {
        throw new TakeposApiException('NOT_FOUND', 'Variant not found.', 404);
    }
    takeposApiAuditAccess($db, $auth, 'product_variants.delete', array('variant_id' => $id));
    takeposApiSuccess(array('id' => $id, 'deleted' => true), array('entity' => $entity));
}

// ════════════════════════════════════════════════════════════════════════════
// POST — create a new variant link
// ════════════════════════════════════════════════════════════════════════════
if ($method === 'POST') {
    require_once __DIR__ . '/_request.php';
    $body = takeposApiRequestBody();

    $pieceId     = (int)    takeposApiRequestRequireField($body, 'piece_product_id');
    $boxId       = (int)    takeposApiRequestRequireField($body, 'box_product_id');
    $unitsPerBox = !empty($body['units_per_box']) ? max(1, (int)$body['units_per_box']) : 1;
    $labelPiece  = !empty($body['label_piece'])  ? trim((string)$body['label_piece'])  : 'Piece';
    $labelBox    = !empty($body['label_box'])    ? trim((string)$body['label_box'])    : 'Box';

    if ($pieceId <= 0 || $boxId <= 0) {
        throw new TakeposApiException('INVALID_PARAMETER', 'piece_product_id and box_product_id are required.', 422);
    }
    if ($pieceId === $boxId) {
        throw new TakeposApiException('INVALID_PARAMETER', 'piece_product_id and box_product_id must be different.', 422);
    }

    // Verify both products exist
    foreach (array('piece' => $pieceId, 'box' => $boxId) as $role => $pid) {
        $p = new Product($db);
        if ($p->fetch($pid) <= 0) {
            throw new TakeposApiException('NOT_FOUND', ucfirst($role) . ' product ' . $pid . ' not found.', 404);
        }
    }

    $sql = 'INSERT INTO ' . MAIN_DB_PREFIX . 'takepos_product_variants'
         . ' (fk_product_piece, fk_product_box, units_per_box, label_piece, label_box, entity)'
         . ' VALUES ('
         . (int)$pieceId . ', ' . (int)$boxId . ', ' . (int)$unitsPerBox . ', '
         . "'" . $db->escape($labelPiece) . "', '" . $db->escape($labelBox) . "', " . $entity . ')'
         . ' ON DUPLICATE KEY UPDATE'
         . ' units_per_box = VALUES(units_per_box),'
         . ' label_piece   = VALUES(label_piece),'
         . ' label_box     = VALUES(label_box)';

    if (!$db->query($sql)) {
        throw new TakeposApiException('INTERNAL_ERROR', 'Insert failed: ' . $db->lasterror(), 500);
    }

    $newId = $db->last_insert_id(MAIN_DB_PREFIX . 'takepos_product_variants', 'rowid');

    takeposApiAuditAccess($db, $auth, 'product_variants.create', array(
        'piece_id' => $pieceId, 'box_id' => $boxId, 'units_per_box' => $unitsPerBox,
    ));

    takeposApiSuccess(array(
        'id'              => (int) $newId,
        'piece_product_id'=> $pieceId,
        'box_product_id'  => $boxId,
        'units_per_box'   => $unitsPerBox,
        'label_piece'     => $labelPiece,
        'label_box'       => $labelBox,
    ), array('entity' => $entity), 201);
}

// ════════════════════════════════════════════════════════════════════════════
// GET — list all or single by product_id
// ════════════════════════════════════════════════════════════════════════════
$productId = GETPOSTINT('product_id');
$id        = GETPOSTINT('id');

$sql = 'SELECT v.rowid, v.fk_product_piece, v.fk_product_box, v.units_per_box, v.label_piece, v.label_box,'
     . ' pp.ref AS piece_ref, pp.label AS piece_label, pp.price_ttc AS piece_price_ttc,'
     . ' pb.ref AS box_ref,   pb.label AS box_label,   pb.price_ttc AS box_price_ttc'
     . ' FROM ' . MAIN_DB_PREFIX . 'takepos_product_variants v'
     . ' LEFT JOIN ' . MAIN_DB_PREFIX . 'product pp ON pp.rowid = v.fk_product_piece'
     . ' LEFT JOIN ' . MAIN_DB_PREFIX . 'product pb ON pb.rowid = v.fk_product_box'
     . ' WHERE v.entity = ' . $entity;

if ($id > 0) {
    $sql .= ' AND v.rowid = ' . (int)$id;
} elseif ($productId > 0) {
    $sql .= ' AND (v.fk_product_piece = ' . (int)$productId . ' OR v.fk_product_box = ' . (int)$productId . ')';
}
$sql .= ' ORDER BY pp.ref ASC';

$resql = $db->query($sql);
if (!$resql) {
    throw new TakeposApiException('INTERNAL_ERROR', 'Query failed.', 500);
}

$rows = array();
while ($obj = $db->fetch_object($resql)) {
    $rows[] = array(
        'id'               => (int)   $obj->rowid,
        'piece_product_id' => (int)   $obj->fk_product_piece,
        'piece_ref'        => (string)$obj->piece_ref,
        'piece_label'      => (string)$obj->piece_label,
        'piece_price_ttc'  => (float) $obj->piece_price_ttc,
        'box_product_id'   => (int)   $obj->fk_product_box,
        'box_ref'          => (string)$obj->box_ref,
        'box_label'        => (string)$obj->box_label,
        'box_price_ttc'    => (float) $obj->box_price_ttc,
        'units_per_box'    => (int)   $obj->units_per_box,
        'label_piece'      => (string)$obj->label_piece,
        'label_box'        => (string)$obj->label_box,
    );
}

takeposApiAuditAccess($db, $auth, 'product_variants.index', array('count' => count($rows)));

if ($id > 0 || $productId > 0) {
    if (empty($rows)) {
        throw new TakeposApiException('NOT_FOUND', 'Variant not found.', 404);
    }
    takeposApiSuccess($rows[0], array('entity' => $entity));
}

takeposApiSuccess($rows, array('entity' => $entity, 'count' => count($rows)));
