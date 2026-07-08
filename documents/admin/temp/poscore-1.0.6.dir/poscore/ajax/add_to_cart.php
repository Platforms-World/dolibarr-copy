<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/poscore/class/service/PosSaasBridge.php';
require_once DOL_DOCUMENT_ROOT.'/custom/poscore/class/service/PosCartService.php';

header('Content-Type: application/json');

function poscore_json_error($message, $code = 403)
{
    http_response_code($code);
    echo json_encode(array('success' => false, 'error' => $message));
    exit;
}

try {
    $bridge = new PosSaasBridge($db);
    $bridge->requireAccess('pos_terminal', 'poscore.cashier', 'post');

    $productId = (int) GETPOST('product_id', 'int');
    $qty = (float) GETPOST('qty', 'alpha');

    $cartService = new PosCartService($db);
    $result = $cartService->addProduct((int) $conf->entity, (int) $user->id, $productId, $qty > 0 ? $qty : 1);
    if (empty($result['success'])) {
        poscore_json_error($result['error'], 400);
    }

    echo json_encode($result);
} catch (Throwable $e) {
    poscore_json_error($e->getMessage(), 500);
}
