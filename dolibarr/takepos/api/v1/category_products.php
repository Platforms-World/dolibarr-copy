<?php
/**
 * TakePOS API v1 — Category Products
 *
 * GET /takepos/api/v1/category_products.php?category_id=5
 *   List all products that belong to a given category.
 *
 * Auth: Bearer token (standard API v1 auth, scope: read / takepos.api_layer)
 *
 * Query params:
 *   category_id   INT     required  category to list products for
 *   active_only   INT     optional  1 = return only on-sale products (p.tosell = 1)
 *   warehouse_id  INT     optional  if set, return per-warehouse stock instead of global total
 *   limit         INT     optional  page size (1-100, default 50)
 *   offset        INT     optional  pagination offset (default 0)
 *
 * Response:
 * {
 *   "success": true,
 *   "data": [
 *     {
 *       "id": 42,
 *       "ref": "PROD-042",
 *       "label": "Espresso",
 *       "description": "Single shot",
 *       "price": 2.00,
 *       "price_ttc": 2.20,
 *       "currency": "USD",
 *       "barcode": "5901234123457",
 *       "barcodes": ["5901234123457"],
 *       "category_id": 5,
 *       "category_label": "Hot Drinks",
 *       "image_url": "https://…/product_42.jpg",
 *       "stock": 100.0,
 *       "status": 1
 *     }
 *   ],
 *   "meta": { "entity": 1, "category_id": 5, "count": 1, "limit": 50, "offset": 0 }
 * }
 */
require_once __DIR__ . '/bootstrap.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposProductImageService.class.php';

takeposApiRequireMethod(array('GET'));

$auth   = takeposApiAuth($db, 'read', 'takepos.api_layer');
$entity = (int) $auth['entity'];

// ── Input validation ──────────────────────────────────────────────────────────
$categoryId  = GETPOSTINT('category_id');
$activeOnly  = (GETPOSTINT('active_only') > 0);
$warehouseId = GETPOSTINT('warehouse_id');
$limit       = GETPOSTINT('limit');
$offset      = GETPOSTINT('offset');

if ($limit <= 0)  $limit  = 50;
if ($limit > 100) $limit  = 100;
if ($offset < 0)  $offset = 0;

if ($categoryId <= 0) {
    throw new TakeposApiException('INVALID_PARAMETER', 'category_id is required and must be a positive integer.', 422);
}

// Verify the category exists
$sqlCat  = 'SELECT rowid, label FROM ' . MAIN_DB_PREFIX . 'categorie';
$sqlCat .= ' WHERE rowid = ' . $categoryId;
$sqlCat .= ' AND entity IN (' . getEntity('categorie') . ')';
$sqlCat .= ' AND type = 0';
$resCat  = $db->query($sqlCat);
if (!$resCat || $db->num_rows($resCat) === 0) {
    takeposApiError('NOT_FOUND', 'Category not found.', 404);
}
$catRow      = $db->fetch_object($resCat);
$categoryLabel = (string) $catRow->label;

// ── SQL: products in category ─────────────────────────────────────────────────
// Reuse the same product projection used by products.php for consistency.
$sql  = 'SELECT p.rowid, p.ref, p.label, p.description, p.price, p.price_ttc, p.barcode, p.tosell,';

if ($warehouseId > 0) {
    $sql .= ' COALESCE((SELECT ps.reel FROM ' . MAIN_DB_PREFIX . 'product_stock ps'
        .  ' WHERE ps.fk_product = p.rowid AND ps.fk_entrepot = ' . (int) $warehouseId . ' LIMIT 1), 0) AS stock,';
} else {
    $sql .= ' COALESCE((SELECT SUM(ps.reel) FROM ' . MAIN_DB_PREFIX . 'product_stock ps'
        .  ' WHERE ps.fk_product = p.rowid), 0) AS stock,';
}

// Primary category for each product (same subquery as products.php)
$sql .= ' (SELECT MIN(cp.fk_categorie) FROM ' . MAIN_DB_PREFIX . 'categorie_product cp'
    .  ' WHERE cp.fk_product = p.rowid) AS category_id,';
$sql .= ' (SELECT c.label FROM ' . MAIN_DB_PREFIX . 'categorie c'
    .  ' INNER JOIN ' . MAIN_DB_PREFIX . 'categorie_product cp2 ON cp2.fk_categorie = c.rowid'
    .  ' WHERE cp2.fk_product = p.rowid ORDER BY c.rowid ASC LIMIT 1) AS category_label';
$sql .= ' FROM ' . MAIN_DB_PREFIX . 'product p';
$sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'categorie_product cjoin'
    .  ' ON cjoin.fk_product = p.rowid AND cjoin.fk_categorie = ' . $categoryId;
$sql .= ' WHERE p.entity IN (' . getEntity('product') . ')';

if ($activeOnly) {
    $sql .= ' AND p.tosell = 1';
}

$sql .= ' ORDER BY p.label ASC, p.rowid ASC';
$sql .= $db->plimit($limit, $offset);

// ── Format helper (mirrors products.php) ─────────────────────────────────────
function takeposCatApiFormatProduct($db, $entity, $row)
{
    // Collect alias barcodes when the alias table exists
    $aliases  = array();
    if (TakeposMigration::tableExists($db, TakeposProductBarcodeService::table())) {
        foreach (TakeposProductBarcodeService::listAliases($db, $entity, (int) $row->rowid) as $alias) {
            $aliases[] = (string) $alias->barcode;
        }
    }
    $aliases  = array_values(array_unique($aliases));

    $barcodes = array();
    if (!empty($row->barcode)) {
        $barcodes[] = (string) $row->barcode;
    }
    foreach ($aliases as $alias) {
        if (!in_array($alias, $barcodes, true)) {
            $barcodes[] = $alias;
        }
    }

    return array(
        'id'             => (int)   $row->rowid,
        'ref'            => (string) $row->ref,
        'label'          => (string) $row->label,
        'description'    => (string) $row->description,
        'price'          => (float)  price2num($row->price, 'MT'),
        'price_ttc'      => (float)  price2num($row->price_ttc, 'MT'),
        'currency'       => (string) getDolGlobalString('MAIN_MONNAIE', (empty($GLOBALS['conf']->currency) ? 'USD' : $GLOBALS['conf']->currency)),
        'barcode'        => (empty($row->barcode) ? null : (string) $row->barcode),
        'barcodes'       => $barcodes,
        'category_id'    => (empty($row->category_id)    ? null : (int)    $row->category_id),
        'category_label' => (empty($row->category_label) ? null : (string) $row->category_label),
        'image_url'      => TakeposProductImageService::buildProductImageUrl((int) $row->rowid),
        'stock'          => (float)  price2num($row->stock, 'MS'),
        'status'         => (int)    $row->tosell,
    );
}

// ── Execute ───────────────────────────────────────────────────────────────────
$rows  = array();
$resql = $db->query($sql);
if ($resql) {
    while ($row = $db->fetch_object($resql)) {
        $rows[] = takeposCatApiFormatProduct($db, $entity, $row);
    }
}

// ── Response ──────────────────────────────────────────────────────────────────
takeposApiAuditAccess($db, $auth, 'category_products.index', array(
    'category_id'  => $categoryId,
    'active_only'  => ($activeOnly ? 1 : 0),
    'warehouse_id' => ($warehouseId > 0 ? $warehouseId : null),
    'count'        => count($rows),
));
takeposApiSuccess($rows, array(
    'entity'         => $entity,
    'category_id'    => $categoryId,
    'category_label' => $categoryLabel,
    'count'          => count($rows),
    'limit'          => $limit,
    'offset'         => $offset,
));