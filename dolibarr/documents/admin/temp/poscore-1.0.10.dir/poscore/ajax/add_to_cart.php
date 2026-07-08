<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/poscore/class/PosSaasBridge.php';
require_once DOL_DOCUMENT_ROOT.'/custom/poscore/class/PosCartService.php';
header('Content-Type: application/json');
$bridge = new PosSaasBridge($db);
$bridge->ensureAjaxAccess($conf, $user, 'pos_terminal', 'poscore.cashier');
$productId = (int) GETPOST('product_id', 'int');
$qty = (float) GETPOST('qty', 'alpha');
if ($qty <= 0) $qty = 1;
$product = new Product($db);
if ($product->fetch($productId) <= 0 || (int) $product->entity !== (int) $conf->entity) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'error' => 'Invalid product'));
    exit;
}
$sql = "INSERT INTO ".MAIN_DB_PREFIX."poscore_cart (entity, fk_user, fk_product, qty, price_ht, remise_percent)
        VALUES (".((int) $conf->entity).", ".((int) $user->id).", ".((int) $productId).", ".price2num($qty, 'MU').", ".price2num($product->price, 'MU').", 0)
        ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty), price_ht = VALUES(price_ht), remise_percent = VALUES(remise_percent)";
if (!$db->query($sql)) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'error' => $db->lasterror()));
    exit;
}
$cart = new PosCartService($db, $conf, $user);
echo json_encode(array('success' => true, 'cart' => $cart->buildCart()));
