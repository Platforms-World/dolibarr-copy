<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/poscore/class/PosSaasBridge.php';
require_once DOL_DOCUMENT_ROOT.'/custom/poscore/class/PosCartService.php';
header('Content-Type: application/json');
$bridge = new PosSaasBridge($db);
$bridge->ensureAjaxAccess($conf, $user, 'pos_terminal', 'poscore.cashier');
$productId = (int) GETPOST('product_id', 'int');
if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'error' => 'Invalid product id'));
    exit;
}
$sql = "DELETE FROM ".MAIN_DB_PREFIX."poscore_cart WHERE entity = ".((int) $conf->entity)." AND fk_user = ".((int) $user->id)." AND fk_product = ".((int) $productId);
if (!$db->query($sql)) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'error' => $db->lasterror()));
    exit;
}
$cart = new PosCartService($db, $conf, $user);
echo json_encode(array('success' => true, 'cart' => $cart->buildCart()));
