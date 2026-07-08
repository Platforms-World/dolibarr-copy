<?php
/**
 * ajax/product_variants.php
 *
 * Returns piece/box variant info for a given product ID.
 * Called from the POS JS when a product tile is clicked.
 *
 * POST params:
 *   product_id  INT   – the product the cashier just tapped
 *   token       STR   – CSRF token
 *
 * Response (JSON):
 *   { has_variant: false }
 *   — or —
 *   {
 *     has_variant: true,
 *     piece_id:    INT,
 *     piece_ref:   STRING,
 *     piece_label: STRING,
 *     piece_price_ttc: FLOAT,
 *     piece_price_ttc_formated: STRING,
 *     box_id:      INT,
 *     box_ref:     STRING,
 *     box_label:   STRING,
 *     box_price_ttc: FLOAT,
 *     box_price_ttc_formated: STRING,
 *     units_per_box: INT,
 *     label_piece: STRING,   (popup button text)
 *     label_box:   STRING,   (popup button text)
 *     clicked_role: "piece"|"box"   (which side the clicked product is)
 *   }
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

if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
}

if (!$user->hasRight('takepos', 'run')) {
    echo json_encode(array('has_variant' => false));
    exit;
}

$productId = GETPOSTINT('product_id');
if ($productId <= 0) {
    echo json_encode(array('has_variant' => false));
    exit;
}

// Look up variant — the product may be either the piece or the box side
$sql = "SELECT v.fk_product_piece, v.fk_product_box, v.units_per_box, v.label_piece, v.label_box,
               pp.ref AS piece_ref, pp.label AS piece_name, pp.price_ttc AS piece_price_ttc,
               pb.ref AS box_ref,   pb.label AS box_name,   pb.price_ttc AS box_price_ttc
        FROM " . MAIN_DB_PREFIX . "takepos_product_variants v
        LEFT JOIN " . MAIN_DB_PREFIX . "product pp ON pp.rowid = v.fk_product_piece
        LEFT JOIN " . MAIN_DB_PREFIX . "product pb ON pb.rowid = v.fk_product_box
        WHERE v.entity = " . (int)$conf->entity . "
          AND (v.fk_product_piece = " . (int)$productId . "
               OR v.fk_product_box = " . (int)$productId . ")
        LIMIT 1";

$resql = $db->query($sql);
if (!$resql || !$db->num_rows($resql)) {
    echo json_encode(array('has_variant' => false));
    exit;
}

$obj = $db->fetch_object($resql);

// Multi-price support
$pricelevel = 1;
if (getDolGlobalString('PRODUIT_MULTIPRICES') && isset($_SESSION['takeposterminal'])) {
    // Use default level 1; could be extended per-customer
}

// Format prices
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

$piecePriceTtc = (float) $obj->piece_price_ttc;
$boxPriceTtc   = (float) $obj->box_price_ttc;

echo json_encode(array(
    'has_variant'             => true,
    'piece_id'                => (int)   $obj->fk_product_piece,
    'piece_ref'               => $obj->piece_ref,
    'piece_label'             => $obj->piece_name,
    'piece_price_ttc'         => $piecePriceTtc,
    'piece_price_ttc_formated'=> price($piecePriceTtc, 1, $langs, 1, -1, -1, $conf->currency),
    'box_id'                  => (int)   $obj->fk_product_box,
    'box_ref'                 => $obj->box_ref,
    'box_label'               => $obj->box_name,
    'box_price_ttc'           => $boxPriceTtc,
    'box_price_ttc_formated'  => price($boxPriceTtc, 1, $langs, 1, -1, -1, $conf->currency),
    'units_per_box'           => (int)   $obj->units_per_box,
    'label_piece'             => $obj->label_piece,
    'label_box'               => $obj->label_box,
    'clicked_role'            => ((int)$obj->fk_product_piece === $productId) ? 'piece' : 'box',
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
