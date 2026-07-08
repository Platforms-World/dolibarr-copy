<?php
/**
 * product_stock_badges.php — Batch stock + expiry data for POS product tiles
 *
 * Called by takepos_stock_badges.js after LoadProducts() populates the cashier
 * product grid. Returns stock quantities (and nearest expiry dates when the
 * productbatch module is enabled) for a list of product IDs.
 *
 * Request:  POST/GET  action=stock_badges  product_ids=1,2,3,...
 * Response: JSON { product_id: { qty: N, expiry: "YYYY-MM-DD"|null } }
 *
 * FIX (stock-branch-v9): New file.
 */

if (!defined('NOREQUIREMENU'))  define('NOREQUIREMENU',  '1');
if (!defined('NOREQUIREHTML'))  define('NOREQUIREHTML',  '1');
if (!defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX',  '1');
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
if (!defined('NOBROWSERNOTIF')) define('NOBROWSERNOTIF', '1');

$mainPath = __DIR__ . '/../../main.inc.php';
if (!file_exists($mainPath)) $mainPath = __DIR__ . '/../../../main.inc.php';
require $mainPath;

require_once DOL_DOCUMENT_ROOT . '/takepos/lib/takepos_access_guard.php';
takeposAccessGuardCurrent($db, isset($user) ? $user : null, __FILE__);

if (!headers_sent()) header('Content-Type: application/json; charset=UTF-8');

if (!$user->hasRight('takepos', 'run')) {
    http_response_code(403);
    echo json_encode(array('error' => 'forbidden'));
    exit;
}

// ── Parse product IDs ─────────────────────────────────────────────────────────
$raw = trim((string) GETPOST('product_ids', 'none'));
if ($raw === '') {
    echo json_encode(array());
    exit;
}

$ids = array();
foreach (explode(',', $raw) as $v) {
    $i = (int) trim($v);
    if ($i > 0) $ids[] = $i;
}
$ids = array_unique(array_slice($ids, 0, 100)); // max 100 at a time

if (empty($ids)) {
    echo json_encode(array());
    exit;
}

$terminal    = isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : 0;
$warehouseId = getDolGlobalInt('CASHDESK_ID_WAREHOUSE' . $terminal);
$pids        = implode(',', $ids);

// ── Stock quantities ──────────────────────────────────────────────────────────
$result = array();
foreach ($ids as $pid) {
    $result[$pid] = array('qty' => null, 'expiry' => null, 'threshold' => 0);
}

// Always sum stock from ALL warehouses regardless of CASHDESK_ID_WAREHOUSE.
// The merchant can have stock in any warehouse — they should see the total.
// Stock deduction on sale uses fk_default_warehouse per product (handled in invoice.php).
$sqlStock = "SELECT fk_product, SUM(reel) AS reel FROM " . MAIN_DB_PREFIX . "product_stock"
    . " WHERE fk_product IN ($pids) GROUP BY fk_product";
$resStock = $db->query($sqlStock);
if ($resStock) {
    while ($obj = $db->fetch_object($resStock)) {
        $pid = (int) $obj->fk_product;
        if (isset($result[$pid])) {
            $result[$pid]['qty'] = round((float) $obj->reel, 2);
        }
    }
}

// ── Alert thresholds ──────────────────────────────────────────────────────────
$sqlThr = "SELECT rowid, COALESCE(seuil_stock_alerte, 0) AS thr FROM " . MAIN_DB_PREFIX . "product"
    . " WHERE rowid IN ($pids)";
$resThr = $db->query($sqlThr);
if ($resThr) {
    while ($obj = $db->fetch_object($resThr)) {
        $pid = (int) $obj->rowid;
        if (isset($result[$pid])) {
            $result[$pid]['threshold'] = (float) $obj->thr;
        }
    }
}

// ── kafo_expiry_date من product_extrafields ───────────────────────────────────
$chkCol = $db->query("SHOW COLUMNS FROM ".MAIN_DB_PREFIX."product_extrafields LIKE 'kafo_expiry_date'");
if ($chkCol && $db->num_rows($chkCol) > 0) {
    $sqlKafo = "SELECT fk_object, kafo_expiry_date FROM ".MAIN_DB_PREFIX."product_extrafields"
        ." WHERE fk_object IN ($pids)"
        ."   AND kafo_expiry_date IS NOT NULL AND kafo_expiry_date != '0000-00-00'";
    $resKafo = $db->query($sqlKafo);
    if ($resKafo) {
        while ($obj = $db->fetch_object($resKafo)) {
            $pid = (int) $obj->fk_object;
            if (isset($result[$pid])) {
                $result[$pid]['expiry'] = substr((string) $obj->kafo_expiry_date, 0, 10);
            }
        }
    }
}

// ── Nearest expiry date (batch module only) ───────────────────────────────────
// Only query if productbatch module is enabled
if (isModEnabled('productbatch')) {
    $batchTable = MAIN_DB_PREFIX . 'product_lot';
    // Check table exists
    $chkBatch = $db->query("SHOW TABLES LIKE '" . $db->escape($batchTable) . "'");
    if ($chkBatch && $db->num_rows($chkBatch) > 0) {
        // Get earliest eatby (consume-by) date per product, only for batches
        // that still have stock in the terminal's warehouse
        if ($warehouseId > 0) {
            $sqlBatch = "SELECT pl.fk_product,"
                . " MIN(CASE WHEN pl.eatby IS NOT NULL AND pl.eatby != '0000-00-00' THEN pl.eatby ELSE NULL END) AS nearest_eatby,"
                . " MIN(CASE WHEN pl.sellby IS NOT NULL AND pl.sellby != '0000-00-00' THEN pl.sellby ELSE NULL END) AS nearest_sellby"
                . " FROM $batchTable pl"
                . " INNER JOIN " . MAIN_DB_PREFIX . "product_lot_stock ps"
                . " ON ps.fk_lot = pl.rowid AND ps.fk_entrepot = $warehouseId AND ps.qty > 0"
                . " WHERE pl.fk_product IN ($pids)"
                . " GROUP BY pl.fk_product";
        } else {
            $sqlBatch = "SELECT pl.fk_product,"
                . " MIN(CASE WHEN pl.eatby IS NOT NULL AND pl.eatby != '0000-00-00' THEN pl.eatby ELSE NULL END) AS nearest_eatby,"
                . " MIN(CASE WHEN pl.sellby IS NOT NULL AND pl.sellby != '0000-00-00' THEN pl.sellby ELSE NULL END) AS nearest_sellby"
                . " FROM $batchTable pl"
                . " WHERE pl.fk_product IN ($pids)"
                . " GROUP BY pl.fk_product";
        }
        $resBatch = $db->query($sqlBatch);
        if ($resBatch) {
            while ($obj = $db->fetch_object($resBatch)) {
                $pid = (int) $obj->fk_product;
                if (!isset($result[$pid])) continue;
                // Prefer eatby (consume by), fall back to sellby (best before)
                $expiry = null;
                if (!empty($obj->nearest_eatby) && $obj->nearest_eatby !== '0000-00-00') {
                    $expiry = substr((string) $obj->nearest_eatby, 0, 10);
                } elseif (!empty($obj->nearest_sellby) && $obj->nearest_sellby !== '0000-00-00') {
                    $expiry = substr((string) $obj->nearest_sellby, 0, 10);
                }
                $result[$pid]['expiry'] = $expiry;
            }
        }
    }
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;