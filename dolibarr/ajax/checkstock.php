<?php
/**
 * TakePOS Stock Check AJAX handler.
 *
 * Returns available stock for a product at the configured warehouse for this terminal.
 * Respects:
 *   - TAKEPOS_PRODUCT_IN_STOCK global setting
 *   - CASHDESK_ID_WAREHOUSE{terminal} per-terminal warehouse
 *   - Service/non-stock products (type != 0) always return allowed=true
 *
 * Action: checkstock
 * Params: product_id, qty (requested), invoiceid (optional - to count lines already in basket)
 */

if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
}
if (!defined('NOBROWSERNOTIF')) {
    define('NOBROWSERNOTIF', '1');
}

$mainPath = __DIR__ . '/../../main.inc.php';
if (!file_exists($mainPath)) {
    $mainPath = __DIR__ . '/../../../main.inc.php';
}
require $mainPath;

require_once DOL_DOCUMENT_ROOT . '/takepos/lib/takepos_access_guard.php';
takeposAccessGuardCurrent($db, isset($user) ? $user : null, __FILE__);
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
$langs->loadLangs(array('takeposcustom@takepos'));

/**
 * @var Conf      $conf
 * @var DoliDB    $db
 * @var Translate $langs
 * @var User      $user
 */

if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
}

if (!$user->hasRight('takepos', 'run')) {
    http_response_code(403);
    echo json_encode(array('allowed' => true)); // fail-open on auth error
    exit;
}

$action    = GETPOST('action', 'aZ09');
$productId = GETPOSTINT('product_id');
$qtyWanted = (float) GETPOST('qty', 'none');
$invoiceid = GETPOSTINT('invoiceid');

if ($qtyWanted <= 0) {
    $qtyWanted = 1;
}

// ACTION: check_invoice - pre-payment bulk stock check
// Validates all product lines in an invoice against available stock.
if ($action === 'check_invoice') {
    if (getDolGlobalInt('TAKEPOS_PRODUCT_IN_STOCK') != 1) {
        echo json_encode(array('allowed' => true, 'reason' => 'stock_check_disabled'));
        exit;
    }

    if ($invoiceid <= 0) {
        echo json_encode(array('allowed' => true, 'reason' => 'no_invoice'));
        exit;
    }

    $terminal    = isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : 0;
    $warehouseId = getDolGlobalInt('CASHDESK_ID_WAREHOUSE' . $terminal);

    // Fetch all product lines for the invoice, grouped by product
    $sqlLines = "SELECT fd.fk_product, SUM(fd.qty) AS total_qty, p.label, p.ref, p.fk_product_type AS product_type, p.no_incdec"
        . " FROM " . MAIN_DB_PREFIX . "facturedet fd"
        . " INNER JOIN " . MAIN_DB_PREFIX . "product p ON p.rowid = fd.fk_product"
        . " WHERE fd.fk_facture = " . ((int) $invoiceid)
        . " AND fd.fk_product > 0"
        . " GROUP BY fd.fk_product, p.label, p.ref, p.fk_product_type, p.no_incdec";

    $resLines = $db->query($sqlLines);
    if (!$resLines) {
        // Can't check - fail open
        echo json_encode(array('allowed' => true, 'reason' => 'query_error'));
        exit;
    }

    $failures = array();
    while ($line = $db->fetch_object($resLines)) {
        // Skip services and non-stocked items
        if ((int) $line->product_type === 1 || !empty($line->no_incdec)) {
            continue;
        }

        $pid      = (int) $line->fk_product;
        $qtyNeeded = (float) $line->total_qty;

        // Get stock
        $stockAvail = 0;
        if ($warehouseId > 0) {
            $sqlS = "SELECT reel FROM " . MAIN_DB_PREFIX . "product_stock"
                . " WHERE fk_product = " . $pid . " AND fk_entrepot = " . $warehouseId;
            $resS = $db->query($sqlS);
            if ($resS && $db->num_rows($resS)) {
                $objS = $db->fetch_object($resS);
                $stockAvail = (float) $objS->reel;
            }
        } else {
            $sqlS = "SELECT COALESCE(SUM(reel),0) AS t FROM " . MAIN_DB_PREFIX . "product_stock WHERE fk_product = " . $pid;
            $resS = $db->query($sqlS);
            if ($resS) {
                $objS = $db->fetch_object($resS);
                $stockAvail = $objS ? (float) $objS->t : 0;
            }
        }

        if ($stockAvail < $qtyNeeded) {
            $failures[] = array(
                'product_id'  => $pid,
                'ref'         => $line->ref,
                'label'       => $line->label,
                'qty_needed'  => $qtyNeeded,
                'qty_avail'   => $stockAvail,
            );
        }
    }

    if (count($failures) > 0) {
        $msgs = array();
        foreach ($failures as $f) {
            $msgs[] = ($f['ref'] ? $f['ref'] . ' - ' : '') . $f['label']
                . ': ' . $langs->trans('TakeposStockNeededAvailable', $f['qty_needed'], $f['qty_avail']);
        }
        echo json_encode(array(
            'allowed'  => false,
            'message'  => $langs->trans('TakeposStockInsufficient') . ': ' . implode(' | ', $msgs),
            'failures' => $failures,
        ));
    } else {
        echo json_encode(array('allowed' => true));
    }
    exit;
}

// ACTION: checkstock (single product)
// Stock check disabled globally - always allow
if (getDolGlobalInt('TAKEPOS_PRODUCT_IN_STOCK') != 1) {
    echo json_encode(array('allowed' => true, 'reason' => 'stock_check_disabled'));
    exit;
}

if ($productId <= 0) {
    echo json_encode(array('allowed' => true, 'reason' => 'no_product'));
    exit;
}

// Load product
$prod = new Product($db);
if ($prod->fetch($productId) <= 0) {
    echo json_encode(array('allowed' => true, 'reason' => 'product_not_found'));
    exit;
}

// Service or non-stocked products - always allow
// Dolibarr product types: 0=product, 1=service
if ((int) $prod->type === 1 || !empty($prod->no_incdec)) {
    echo json_encode(array('allowed' => true, 'reason' => 'service_product', 'stock' => null));
    exit;
}

$terminal   = isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : 0;
$warehouseId = getDolGlobalInt('CASHDESK_ID_WAREHOUSE' . $terminal);

// Calculate available stock
$stockAvailable = 0;
if ($warehouseId > 0) {
    // Per-warehouse stock
    $sqlStock = "SELECT reel FROM " . MAIN_DB_PREFIX . "product_stock"
        . " WHERE fk_product = " . ((int) $productId)
        . " AND fk_entrepot = " . ((int) $warehouseId);
    $resStock = $db->query($sqlStock);
    if ($resStock && $db->num_rows($resStock) > 0) {
        $objStock = $db->fetch_object($resStock);
        $stockAvailable = (float) $objStock->reel;
    }
} else {
    // All warehouses total
    $sqlStock = "SELECT COALESCE(SUM(reel), 0) AS total FROM " . MAIN_DB_PREFIX . "product_stock"
        . " WHERE fk_product = " . ((int) $productId);
    $resStock = $db->query($sqlStock);
    if ($resStock) {
        $objStock = $db->fetch_object($resStock);
        $stockAvailable = $objStock ? (float) $objStock->total : 0;
    }
}

// Subtract what's already in the current basket for this product
$qtyInBasket = 0;
if ($invoiceid > 0) {
    $sqlBasket = "SELECT COALESCE(SUM(qty), 0) AS inbasket FROM " . MAIN_DB_PREFIX . "facturedet"
        . " WHERE fk_facture = " . ((int) $invoiceid)
        . " AND fk_product = " . ((int) $productId);
    $resBasket = $db->query($sqlBasket);
    if ($resBasket) {
        $objB = $db->fetch_object($resBasket);
        $qtyInBasket = $objB ? (float) $objB->inbasket : 0;
    }
}

$stockAfterBasket = $stockAvailable - $qtyInBasket;
$allowed = ($stockAfterBasket >= $qtyWanted);

echo json_encode(array(
    'allowed'          => $allowed,
    'stock_available'  => $stockAvailable,
    'qty_in_basket'    => $qtyInBasket,
    'stock_free'       => $stockAfterBasket,
    'qty_wanted'       => $qtyWanted,
    'product_id'       => $productId,
    'warehouse_id'     => $warehouseId,
));
exit;
