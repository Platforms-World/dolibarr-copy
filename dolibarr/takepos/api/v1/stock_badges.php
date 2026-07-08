<?php
/**
 * TakePOS API v1 — Stock Badges (Batch)
 *
 * GET /takepos/api/v1/stock_badges.php?product_ids=42,55,67
 *
 * Returns stock quantity, alert threshold, and nearest expiry date
 * for up to 100 products at once. Respects the terminal's warehouse.
 *
 * Auth: Bearer token (standard API v1 auth)
 *
 * Query params:
 *   product_ids   STRING  required  comma-separated product IDs, max 100
 *   terminal_id   INT     optional  overrides session terminal for warehouse lookup
 */
require_once __DIR__ . '/bootstrap.php';

takeposApiRequireMethod(array('GET'));

$auth   = takeposApiAuth($db, 'read', 'takepos.api_layer');
$entity = (int) $auth['entity'];

$raw        = trim((string) GETPOST('product_ids', 'none'));
$terminalId = GETPOSTINT('terminal_id') ?: (isset($_SESSION['takeposterminal']) ? (int)$_SESSION['takeposterminal'] : 0);

if ($raw === '') {
    throw new TakeposApiException('INVALID_PARAMETER', 'product_ids is required (comma-separated list).', 422);
}

// Parse and sanitise product IDs
$ids = array();
foreach (explode(',', $raw) as $v) {
    $i = (int) trim($v);
    if ($i > 0) $ids[] = $i;
}
$ids = array_values(array_unique(array_slice($ids, 0, 100)));

if (empty($ids)) {
    throw new TakeposApiException('INVALID_PARAMETER', 'No valid product IDs provided.', 422);
}

$warehouseId = getDolGlobalInt('CASHDESK_ID_WAREHOUSE' . $terminalId);
$pids        = implode(',', $ids);

// ── Stock quantities ──────────────────────────────────────────────────────────
$result = array();
foreach ($ids as $id) {
    $result[$id] = array('product_id' => $id, 'qty' => 0.0, 'threshold' => 0.0, 'expiry' => null);
}

if ($warehouseId > 0) {
    $sqlStock = 'SELECT fk_product, reel FROM ' . MAIN_DB_PREFIX . 'product_stock'
        . ' WHERE fk_product IN (' . $pids . ') AND fk_entrepot = ' . (int)$warehouseId;
} else {
    $sqlStock = 'SELECT fk_product, COALESCE(SUM(reel),0) AS reel FROM ' . MAIN_DB_PREFIX . 'product_stock'
        . ' WHERE fk_product IN (' . $pids . ') GROUP BY fk_product';
}
$resStock = $db->query($sqlStock);
if ($resStock) {
    while ($obj = $db->fetch_object($resStock)) {
        $pid = (int) $obj->fk_product;
        if (isset($result[$pid])) {
            $result[$pid]['qty'] = (float) $obj->reel;
        }
    }
}

// ── Alert thresholds ──────────────────────────────────────────────────────────
$sqlThr = 'SELECT rowid, COALESCE(seuil_stock_alerte,0) AS thr FROM ' . MAIN_DB_PREFIX . 'product'
    . ' WHERE rowid IN (' . $pids . ')';
$resThr = $db->query($sqlThr);
if ($resThr) {
    while ($obj = $db->fetch_object($resThr)) {
        $pid = (int) $obj->rowid;
        if (isset($result[$pid])) {
            $result[$pid]['threshold'] = (float) $obj->thr;
        }
    }
}

// ── Nearest expiry (batch/lot module) ─────────────────────────────────────────
if (isModEnabled('productbatch')) {
    $batchTable = MAIN_DB_PREFIX . 'product_lot';
    $chk = $db->query("SHOW TABLES LIKE '" . $db->escape($batchTable) . "'");
    if ($chk && $db->num_rows($chk) > 0) {
        if ($warehouseId > 0) {
            $sqlBatch = 'SELECT pl.fk_product,'
                . ' MIN(CASE WHEN pl.eatby IS NOT NULL AND pl.eatby != \'0000-00-00\' THEN pl.eatby ELSE NULL END) AS nearest_eatby,'
                . ' MIN(CASE WHEN pl.sellby IS NOT NULL AND pl.sellby != \'0000-00-00\' THEN pl.sellby ELSE NULL END) AS nearest_sellby'
                . ' FROM ' . $batchTable . ' pl'
                . ' INNER JOIN ' . MAIN_DB_PREFIX . 'product_lot_stock ps'
                . ' ON ps.fk_lot = pl.rowid AND ps.fk_entrepot = ' . (int)$warehouseId . ' AND ps.qty > 0'
                . ' WHERE pl.fk_product IN (' . $pids . ')'
                . ' GROUP BY pl.fk_product';
        } else {
            $sqlBatch = 'SELECT pl.fk_product,'
                . ' MIN(CASE WHEN pl.eatby IS NOT NULL AND pl.eatby != \'0000-00-00\' THEN pl.eatby ELSE NULL END) AS nearest_eatby,'
                . ' MIN(CASE WHEN pl.sellby IS NOT NULL AND pl.sellby != \'0000-00-00\' THEN pl.sellby ELSE NULL END) AS nearest_sellby'
                . ' FROM ' . $batchTable . ' pl'
                . ' WHERE pl.fk_product IN (' . $pids . ')'
                . ' GROUP BY pl.fk_product';
        }
        $resBatch = $db->query($sqlBatch);
        if ($resBatch) {
            while ($obj = $db->fetch_object($resBatch)) {
                $pid = (int) $obj->fk_product;
                if (!isset($result[$pid])) continue;
                $expiry = null;
                if (!empty($obj->nearest_eatby) && $obj->nearest_eatby !== '0000-00-00') {
                    $expiry = substr((string)$obj->nearest_eatby, 0, 10);
                } elseif (!empty($obj->nearest_sellby) && $obj->nearest_sellby !== '0000-00-00') {
                    $expiry = substr((string)$obj->nearest_sellby, 0, 10);
                }
                $result[$pid]['expiry'] = $expiry;
            }
        }
    }
}

takeposApiAuditAccess($db, $auth, 'stock_badges', array(
    'product_count' => count($ids),
    'warehouse_id'  => $warehouseId,
));

takeposApiSuccess(
    array_values($result),
    array('entity' => $entity, 'count' => count($result), 'warehouse_id' => $warehouseId)
);
