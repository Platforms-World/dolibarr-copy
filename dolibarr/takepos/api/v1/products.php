<?php
require_once __DIR__ . '/bootstrap.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposProductImageService.class.php';

function takeposApiProductAliases($db, $entity, $productId)
{
    $aliases = array();
    if (TakeposMigration::tableExists($db, TakeposProductBarcodeService::table())) {
        foreach (TakeposProductBarcodeService::listAliases($db, $entity, $productId) as $alias) {
            $aliases[] = (string) $alias->barcode;
        }
    }
    return array_values(array_unique($aliases));
}

// ── PIECE/BOX VARIANT MAP ──────────────────────────────────────────────────
// Loads all variant links for the given entity in ONE query and returns a
// map keyed by product ID so formatProduct() can do O(1) lookups.
//
// Map entry structure (same for piece side and box side):
//   'role'        => 'piece' | 'box'
//   'piece_id'    => INT
//   'box_id'      => INT
//   'units_per_box' => INT
//   'label_piece' => STRING
//   'label_box'   => STRING
function takeposApiBuildVariantMap($db, $entity)
{
    $map = array();

    // Table may not exist on older deployments — skip silently if missing.
    $table = MAIN_DB_PREFIX . 'takepos_product_variants';
    $check = $db->query("SHOW TABLES LIKE '" . $db->escape($table) . "'");
    if (!$check || $db->num_rows($check) === 0) {
        return $map;
    }

    $sql = "SELECT fk_product_piece, fk_product_box, units_per_box, label_piece, label_box
            FROM " . $table . "
            WHERE entity = " . (int) $entity;

    $res = $db->query($sql);
    if (!$res) {
        return $map;
    }

    while ($row = $db->fetch_object($res)) {
        $pieceId = (int) $row->fk_product_piece;
        $boxId   = (int) $row->fk_product_box;
        $units   = (int) $row->units_per_box;
        $lPiece  = (string) $row->label_piece;
        $lBox    = (string) $row->label_box;

        // Piece side → its badge says "Box"
        $map[$pieceId] = array(
            'role'         => 'piece',
            'piece_id'     => $pieceId,
            'box_id'       => $boxId,
            'units_per_box'=> $units,
            'label_piece'  => $lPiece,
            'label_box'    => $lBox,
        );

        // Box side → its badge says "Piece"
        $map[$boxId] = array(
            'role'         => 'box',
            'piece_id'     => $pieceId,
            'box_id'       => $boxId,
            'units_per_box'=> $units,
            'label_piece'  => $lPiece,
            'label_box'    => $lBox,
        );
    }

    return $map;
}

// ── FORMAT PRODUCT ─────────────────────────────────────────────────────────
// $variantMap is the array returned by takeposApiBuildVariantMap().
// Pass an empty array if you don't need variant data.
function takeposApiFormatProduct($db, $entity, $row, $variantMap = array())
{
    $aliases  = takeposApiProductAliases($db, $entity, (int) $row->rowid);
    $barcodes = array();
    if (empty($row->barcode) === false) {
        $barcodes[] = (string) $row->barcode;
    }
    foreach ($aliases as $alias) {
        if (in_array($alias, $barcodes, true) === false) {
            $barcodes[] = $alias;
        }
    }

    // Variant info (null when product has no piece/box link)
    $productId = (int) $row->rowid;
    $variant   = isset($variantMap[$productId]) ? $variantMap[$productId] : null;

    return array(
        'id'             => $productId,
        'ref'            => (string) $row->ref,
        'label'          => (string) $row->label,
        'description'    => (string) $row->description,
        'price'          => (float)  price2num($row->price,     'MT'),
        'price_ttc'      => (float)  price2num($row->price_ttc, 'MT'),
        'currency'       => (string) getDolGlobalString('MAIN_MONNAIE', (empty($GLOBALS['conf']->currency) ? 'USD' : $GLOBALS['conf']->currency)),
        'barcode'        => (empty($row->barcode) ? null : (string) $row->barcode),
        'barcodes'       => $barcodes,
        'category_id'    => (empty($row->category_id)    ? null : (int)    $row->category_id),
        'category_label' => (empty($row->category_label) ? null : (string) $row->category_label),
        'image_url'      => TakeposProductImageService::buildProductImageUrl($productId),
        'stock'          => (float) price2num($row->stock, 'MS'),
        'status'         => (int)   $row->tosell,

        // ── Piece / Box variant ────────────────────────────────────────────
        // null  → regular product, no piece/box link
        // object→ {
        //   role:          "piece" | "box"
        //   piece_id:      INT   (product ID of the single-unit side)
        //   box_id:        INT   (product ID of the carton/box side)
        //   units_per_box: INT   (how many pieces in one box)
        //   label_piece:   STRING (display label, e.g. "Piece")
        //   label_box:     STRING (display label, e.g. "Box")
        // }
        'variant'        => $variant,
    );
}

// ── REQUEST HANDLING ───────────────────────────────────────────────────────
takeposApiRequireMethod(array('GET'));
$auth   = takeposApiAuth($db, 'read', 'takepos.api_layer');
$entity = (int) $auth['entity'];

$id          = GETPOSTINT('id');
$q           = TakeposInputValidator::normalizeUtf8Text(GETPOST('q',        'none'), 190, true);
$barcode     = TakeposInputValidator::normalizeUtf8Text(GETPOST('barcode',  'none'), 190, false);
$sku         = TakeposInputValidator::normalizeUtf8Text(GETPOST('sku',      'none'), 128, false);
$categoryId  = GETPOSTINT('category_id');
$activeOnly  = (GETPOSTINT('active_only') > 0);
$limit       = GETPOSTINT('limit');
$offset      = GETPOSTINT('offset');

if ($limit  <= 0)  $limit  = 20;
if ($limit  > 100) $limit  = 100;
if ($offset <  0)  $offset = 0;

$warehouseId = GETPOSTINT('warehouse_id');
// FIX (B8): Added optional warehouse_id parameter so callers can get per-warehouse
// stock instead of the global total. Without this, a product showing stock=5 in the
// list might have 0 stock at the terminal's actual warehouse. When warehouse_id is
// omitted the behaviour is unchanged (global SUM across all warehouses).

$sql = 'SELECT p.rowid, p.ref, p.label, p.description, p.price, p.price_ttc, p.barcode, p.tosell,'
    . ($warehouseId > 0
        ? ' COALESCE((SELECT ps.reel FROM '         . MAIN_DB_PREFIX . 'product_stock ps WHERE ps.fk_product = p.rowid AND ps.fk_entrepot = ' . (int) $warehouseId . ' LIMIT 1), 0) AS stock,'
        : ' COALESCE((SELECT SUM(ps.reel) FROM '    . MAIN_DB_PREFIX . 'product_stock ps WHERE ps.fk_product = p.rowid), 0) AS stock,'
    )
    . ' (SELECT MIN(cp.fk_categorie) FROM '         . MAIN_DB_PREFIX . 'categorie_product cp  WHERE cp.fk_product = p.rowid) AS category_id,'
    . ' (SELECT c.label FROM '                      . MAIN_DB_PREFIX . 'categorie c'
    . '  INNER JOIN '                               . MAIN_DB_PREFIX . 'categorie_product cp2 ON cp2.fk_categorie = c.rowid'
    . '  WHERE cp2.fk_product = p.rowid ORDER BY c.rowid ASC LIMIT 1) AS category_label'
    . ' FROM '                                      . MAIN_DB_PREFIX . 'product p'
    . ' WHERE p.entity IN (' . getEntity('product') . ')';

if ($id > 0)         $sql .= ' AND p.rowid = ' . $id;
if ($activeOnly)     $sql .= ' AND p.tosell = 1';
if ($categoryId > 0) $sql .= ' AND EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . 'categorie_product cp3 WHERE cp3.fk_product = p.rowid AND cp3.fk_categorie = ' . $categoryId . ')';
if ($sku !== '')     $sql .= ' AND p.ref = '     . chr(39) . $db->escape($sku)     . chr(39);

if ($barcode !== '') {
    $sql .= ' AND (p.barcode = '  . chr(39) . $db->escape($barcode) . chr(39)
        . ' OR EXISTS (SELECT 1 FROM ' . TakeposProductBarcodeService::table() . ' pb'
        . '   WHERE pb.fk_product = p.rowid AND pb.entity = ' . $entity
        . '   AND pb.barcode = ' . chr(39) . $db->escape($barcode) . chr(39) . '))';
}

if ($q !== '') {
    $sql .= ' AND (p.ref    LIKE ' . chr(39) . '%' . $db->escape($q) . '%' . chr(39)
        . '  OR p.label   LIKE ' . chr(39) . '%' . $db->escape($q) . '%' . chr(39)
        . '  OR p.barcode LIKE ' . chr(39) . '%' . $db->escape($q) . '%' . chr(39)
        . '  OR EXISTS (SELECT 1 FROM ' . TakeposProductBarcodeService::table() . ' pb2'
        . '     WHERE pb2.fk_product = p.rowid AND pb2.entity = ' . $entity
        . '     AND pb2.barcode LIKE ' . chr(39) . '%' . $db->escape($q) . '%' . chr(39) . '))';
}

$sql .= ' ORDER BY p.label ASC, p.rowid ASC';
$sql .= $db->plimit($limit, $offset);

// ── Execute + build variant map in ONE extra query ─────────────────────────
$variantMap = takeposApiBuildVariantMap($db, $entity);

$rows  = array();
$resql = $db->query($sql);
if ($resql) {
    while ($row = $db->fetch_object($resql)) {
        $rows[] = takeposApiFormatProduct($db, $entity, $row, $variantMap);
    }
}

// ── Return results ─────────────────────────────────────────────────────────
if ($id > 0) {
    if (empty($rows)) takeposApiError('NOT_FOUND', 'Product not found.', 404);
    takeposApiAuditAccess($db, $auth, 'products.show', array('product_id' => $id));
    takeposApiSuccess($rows[0], array('entity' => $entity));
}

if ($barcode !== '' && empty($rows))     takeposApiError('PRODUCT_NOT_FOUND', 'No product found for this barcode', 404);
if ($barcode !== '' && count($rows) === 1) {
    takeposApiAuditAccess($db, $auth, 'products.barcode', array('barcode' => $barcode, 'count' => 1));
    takeposApiSuccess($rows[0], array('entity' => $entity));
}

takeposApiAuditAccess($db, $auth, 'products.index', array(
    'q'          => $q,
    'barcode'    => $barcode,
    'sku'        => $sku,
    'category_id'=> $categoryId,
    'active_only'=> ($activeOnly ? 1 : 0),
    'count'      => count($rows),
));
takeposApiSuccess($rows, array('entity' => $entity, 'count' => count($rows), 'limit' => $limit, 'offset' => $offset));