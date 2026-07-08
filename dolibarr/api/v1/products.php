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
 
function takeposApiFormatProduct($db, $entity, $row) 
{ 
    $aliases = takeposApiProductAliases($db, $entity, (int) $row->rowid); 
    $barcodes = array(); 
    if (empty($row->barcode) === false) { 
        $barcodes[] = (string) $row->barcode; 
    } 
    foreach ($aliases as $alias) { 
        if (in_array($alias, $barcodes, true) === false) { 
            $barcodes[] = $alias; 
        } 
    } 
 
    return array( 
        'id' => (int) $row->rowid, 
        'ref' => (string) $row->ref, 
        'label' => (string) $row->label, 
        'description' => (string) $row->description, 
        'price' => (float) price2num($row->price, 'MT'), 
        'price_ttc' => (float) price2num($row->price_ttc, 'MT'), 
        'currency' => (string) getDolGlobalString('MAIN_MONNAIE', (empty($GLOBALS['conf']->currency) ? 'USD' : $GLOBALS['conf']->currency)), 
        'barcode' => (empty($row->barcode) ? null : (string) $row->barcode), 
        'barcodes' => $barcodes, 
        'category_id' => (empty($row->category_id) ? null : (int) $row->category_id), 
        'category_label' => (empty($row->category_label) ? null : (string) $row->category_label), 
        'image_url' => TakeposProductImageService::buildProductImageUrl((int) $row->rowid), 
        'stock' => (float) price2num($row->stock, 'MS'), 
        'status' => (int) $row->tosell 
    ); 
} 
 
takeposApiRequireMethod(array('GET')); 
$auth = takeposApiAuth($db, 'read', 'takepos.api_layer'); 
$entity = (int) $auth['entity']; 
$id = GETPOSTINT('id'); 
$q = TakeposInputValidator::normalizeUtf8Text(GETPOST('q', 'none'), 190, true); 
$barcode = TakeposInputValidator::normalizeUtf8Text(GETPOST('barcode', 'none'), 190, false); 
$sku = TakeposInputValidator::normalizeUtf8Text(GETPOST('sku', 'none'), 128, false); 
$categoryId = GETPOSTINT('category_id'); 
$activeOnly = (GETPOSTINT('active_only') > 0); 
$limit = GETPOSTINT('limit'); 
$offset = GETPOSTINT('offset'); 
if ($limit <= 0) $limit = 20; 
if ($limit > 100) $limit = 100; 
if ($offset < 0) $offset = 0;
 
$sql = 'SELECT p.rowid, p.ref, p.label, p.description, p.price, p.price_ttc, p.barcode, p.tosell, COALESCE((SELECT SUM(ps.reel) FROM ' . MAIN_DB_PREFIX . 'product_stock ps WHERE ps.fk_product = p.rowid),0) AS stock, (SELECT MIN(cp.fk_categorie) FROM ' . MAIN_DB_PREFIX . 'categorie_product cp WHERE cp.fk_product = p.rowid) AS category_id, (SELECT c.label FROM ' . MAIN_DB_PREFIX . 'categorie c INNER JOIN ' . MAIN_DB_PREFIX . 'categorie_product cp2 ON cp2.fk_categorie = c.rowid WHERE cp2.fk_product = p.rowid ORDER BY c.rowid ASC LIMIT 1) AS category_label FROM ' . MAIN_DB_PREFIX . 'product p WHERE p.entity IN (' . getEntity('product') . ')'; 
if ($id > 0) $sql .= ' AND p.rowid = ' . $id; 
if ($activeOnly) $sql .= ' AND p.tosell = 1'; 
if ($categoryId > 0) $sql .= ' AND EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . 'categorie_product cp3 WHERE cp3.fk_product = p.rowid AND cp3.fk_categorie = ' . $categoryId . ')'; 
if ($sku !== '') $sql .= ' AND p.ref = ' . chr(39) . $db->escape($sku) . chr(39); 
if ($barcode !== '') $sql .= ' AND (p.barcode = ' . chr(39) . $db->escape($barcode) . chr(39) . ' OR EXISTS (SELECT 1 FROM ' . TakeposProductBarcodeService::table() . ' pb WHERE pb.fk_product = p.rowid AND pb.entity = ' . $entity . ' AND pb.barcode = ' . chr(39) . $db->escape($barcode) . chr(39) . '))'; 
if ($q !== '') $sql .= ' AND (p.ref LIKE ' . chr(39) . '%' . $db->escape($q) . '%' . chr(39) . ' OR p.label LIKE ' . chr(39) . '%' . $db->escape($q) . '%' . chr(39) . ' OR p.barcode LIKE ' . chr(39) . '%' . $db->escape($q) . '%' . chr(39) . ' OR EXISTS (SELECT 1 FROM ' . TakeposProductBarcodeService::table() . ' pb2 WHERE pb2.fk_product = p.rowid AND pb2.entity = ' . $entity . ' AND pb2.barcode LIKE ' . chr(39) . '%' . $db->escape($q) . '%' . chr(39) . '))'; 
$sql .= ' ORDER BY p.label ASC, p.rowid ASC'; 
$sql .= $db->plimit($limit, $offset); 
 
$rows = array(); 
$resql = $db->query($sql); 
if ($resql) { 
    while ($row = $db->fetch_object($resql)) { 
        $rows[] = takeposApiFormatProduct($db, $entity, $row); 
    } 
} 
 
if ($id > 0) { 
    if (empty($rows)) takeposApiError('NOT_FOUND', 'Product not found.', 404); 
    takeposApiAuditAccess($db, $auth, 'products.show', array('product_id' => $id)); 
    takeposApiSuccess($rows[0], array('entity' => $entity)); 
} 
if ($barcode !== '' and empty($rows)) takeposApiError('PRODUCT_NOT_FOUND', 'No product found for this barcode', 404); 
if ($barcode !== '' and count($rows) === 1) { 
    takeposApiAuditAccess($db, $auth, 'products.barcode', array('barcode' => $barcode, 'count' => 1)); 
    takeposApiSuccess($rows[0], array('entity' => $entity)); 
} 
 
takeposApiAuditAccess($db, $auth, 'products.index', array('q' => $q, 'barcode' => $barcode, 'sku' => $sku, 'category_id' => $categoryId, 'active_only' => ($activeOnly ? 1 : 0), 'count' => count($rows))); 
takeposApiSuccess($rows, array('entity' => $entity, 'count' => count($rows), 'limit' => $limit, 'offset' => $offset)); 
