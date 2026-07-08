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

    $cartService = new PosCartService($db);
    if (!$cartService->clearCart((int) $conf->entity, (int) $user->id)) {
        poscore_json_error($db->lasterror(), 500);
    }

    echo json_encode(array(
        'success' => true,
        'cart' => array('items' => array(), 'subtotal' => 0, 'tax' => 0, 'total' => 0),
    ));
} catch (Throwable $e) {
    poscore_json_error($e->getMessage(), 500);
}
