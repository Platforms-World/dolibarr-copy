<?php
/**
 * TakePOS API v1 — Stock Check
 *
 * GET  /takepos/api/v1/stock_check.php?product_id=42&qty=5&cart_id=162
 *      Check stock for a single product. cart_id subtracts what is already
 *      in that cart so you do not double-count.
 *
 * POST /takepos/api/v1/stock_check.php
 *      Body: { "cart_id": 162 }
 *      Bulk check — validates every product line in the cart.
 *
 * Auth: Bearer token (standard API v1 auth)
 */
require_once __DIR__ . '/bootstrap.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET');
if (!in_array($method, array('GET', 'POST'), true)) {
    takeposApiError('METHOD_NOT_ALLOWED', 'Method not allowed.', 405, null, array(), array('Allow: GET, POST'));
}

$auth   = takeposApiAuth($db, 'read', 'takepos.api_layer');
$entity = (int) $auth['entity'];

// FIX (B3): API v1 is stateless — $_SESSION is never populated for Bearer-token
// callers. The old fallback always resolved to terminal=0 → warehouse=0, causing
// stock checks to aggregate ALL warehouses instead of the terminal's warehouse.
// terminal_id is now read from the GET parameter. When absent or 0 the check
// falls back to global stock (sum of all warehouses), same as before.
$terminal    = GETPOSTINT('terminal_id');
$warehouseId = ($terminal > 0) ? getDolGlobalInt('CASHDESK_ID_WAREHOUSE' . $terminal) : 0;

// ── Helper: get stock for a product at the terminal warehouse ──────────────────
function takeposApiGetStock($db, $productId, $warehouseId)
{
    if ($warehouseId > 0) {
        $sql = 'SELECT reel FROM ' . MAIN_DB_PREFIX . 'product_stock'
            . ' WHERE fk_product = ' . (int)$productId
            . ' AND fk_entrepot = ' . (int)$warehouseId;
    } else {
        $sql = 'SELECT COALESCE(SUM(reel),0) AS reel FROM ' . MAIN_DB_PREFIX . 'product_stock'
            . ' WHERE fk_product = ' . (int)$productId;
    }
    $res = $db->query($sql);
    if ($res && $db->num_rows($res)) {
        return (float) $db->fetch_object($res)->reel;
    }
    return 0.0;
}

// ── Helper: qty already in cart for a product ─────────────────────────────────
function takeposApiQtyInCart($db, $cartId, $productId, $excludeLineId = 0)
{
    if ($cartId <= 0) return 0.0;
    $sql = 'SELECT COALESCE(SUM(qty),0) AS inbasket FROM ' . MAIN_DB_PREFIX . 'facturedet'
        . ' WHERE fk_facture = ' . (int)$cartId
        . ' AND fk_product = ' . (int)$productId;
    if ($excludeLineId > 0) {
        $sql .= ' AND rowid != ' . (int)$excludeLineId;
    }
    $res = $db->query($sql);
    if ($res) {
        return (float) $db->fetch_object($res)->inbasket;
    }
    return 0.0;
}

// ── Disabled globally ─────────────────────────────────────────────────────────
if (getDolGlobalInt('TAKEPOS_PRODUCT_IN_STOCK') != 1) {
    takeposApiSuccess(array('allowed' => true, 'reason' => 'stock_check_disabled'), array('entity' => $entity));
}

// ════════════════════════════════════════════════════════════════════════════
// POST — bulk check of an entire cart
// ════════════════════════════════════════════════════════════════════════════
if ($method === 'POST') {
    require_once __DIR__ . '/_request.php';
    $body   = takeposApiRequestBody();
    $cartId = (int) takeposApiRequestRequireField($body, 'cart_id');

    $sqlLines = 'SELECT fd.fk_product, SUM(fd.qty) AS total_qty, p.label, p.ref,'
        . ' p.fk_product_type AS product_type, p.no_incdec'
        . ' FROM ' . MAIN_DB_PREFIX . 'facturedet fd'
        . ' INNER JOIN ' . MAIN_DB_PREFIX . 'product p ON p.rowid = fd.fk_product'
        . ' WHERE fd.fk_facture = ' . (int)$cartId
        . ' AND fd.fk_product > 0'
        . ' GROUP BY fd.fk_product, p.label, p.ref, p.fk_product_type, p.no_incdec';

    $resLines = $db->query($sqlLines);
    if (!$resLines) {
        throw new TakeposApiException('INTERNAL_ERROR', 'Failed to load cart lines.', 500);
    }

    $failures = array();
    while ($line = $db->fetch_object($resLines)) {
        if ((int)$line->product_type === 1 || !empty($line->no_incdec)) {
            continue; // services always pass
        }
        $pid        = (int) $line->fk_product;
        $qtyNeeded  = (float) $line->total_qty;
        $stock      = takeposApiGetStock($db, $pid, $warehouseId);
        if ($stock < $qtyNeeded) {
            $failures[] = array(
                'product_id' => $pid,
                'ref'        => $line->ref,
                'label'      => $line->label,
                'qty_needed' => $qtyNeeded,
                'qty_avail'  => $stock,
            );
        }
    }

    takeposApiAuditAccess($db, $auth, 'stock_check.bulk', array('cart_id' => $cartId, 'failures' => count($failures)));

    if (!empty($failures)) {
        takeposApiSuccess(array(
            'allowed'  => false,
            'failures' => $failures,
        ), array('entity' => $entity));
    }
    takeposApiSuccess(array('allowed' => true), array('entity' => $entity));
}

// ════════════════════════════════════════════════════════════════════════════
// GET — single product check
// ════════════════════════════════════════════════════════════════════════════
$productId = GETPOSTINT('product_id');
$qtyWanted = (float) GETPOST('qty', 'none');
$cartId    = GETPOSTINT('cart_id');
$lineId    = GETPOSTINT('line_id'); // optional — exclude this line from basket count (for edit-qty)

if ($qtyWanted <= 0) $qtyWanted = 1;

if ($productId <= 0) {
    throw new TakeposApiException('INVALID_PARAMETER', 'product_id is required.', 422);
}

$prod = new Product($db);
if ($prod->fetch($productId) <= 0) {
    throw new TakeposApiException('NOT_FOUND', 'Product not found.', 404);
}

// Services always pass
if ((int)$prod->type === 1 || !empty($prod->no_incdec)) {
    takeposApiSuccess(array(
        'allowed'      => true,
        'reason'       => 'service_product',
        'product_id'   => $productId,
        'warehouse_id' => $warehouseId,
    ), array('entity' => $entity));
}

$stockAvailable = takeposApiGetStock($db, $productId, $warehouseId);
$qtyInCart      = takeposApiQtyInCart($db, $cartId, $productId, $lineId);
$stockFree      = $stockAvailable - $qtyInCart;
$allowed        = ($stockFree >= $qtyWanted);

takeposApiAuditAccess($db, $auth, 'stock_check.single', array(
    'product_id'   => $productId,
    'qty_wanted'   => $qtyWanted,
    'cart_id'      => $cartId,
    'allowed'      => $allowed,
));

takeposApiSuccess(array(
    'allowed'         => $allowed,
    'product_id'      => $productId,
    'warehouse_id'    => $warehouseId,
    'stock_available' => $stockAvailable,
    'qty_in_cart'     => $qtyInCart,
    'stock_free'      => $stockFree,
    'qty_wanted'      => $qtyWanted,
), array('entity' => $entity));
