<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_request.php';
require_once __DIR__ . '/_invoice_common.php';

takeposApiRequireMethod(array('POST'));

$auth = takeposApiAuth($db, 'write', 'takepos.api_layer');
$entity = (int) $auth['entity'];
$body = takeposApiRequestBody();

$cartId = (int) takeposApiRequestRequireField($body, 'cart_id');
if ($cartId <= 0) {
    throw new TakeposApiException('INVALID_PARAMETER', 'cart_id is required', 422);
}

$invoice = takeposApiRequireTakeposInvoice($db, $entity, $cartId);

$db->begin();

try {
    $invoice = TakeposApiCheckoutService::validateCart($db, $entity, $invoice);
    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    if ($e instanceof TakeposApiException) {
        throw $e;
    }
    throw new TakeposApiException('INTERNAL_ERROR', $e->getMessage(), 500);
}

takeposApiSuccess(
    array(
        'invoice_id' => (int) $invoice->id,
        'status' => 'validated',
        'total_ttc' => (float) price2num($invoice->total_ttc, 'MT'),
    ),
    array('entity' => $entity)
);
