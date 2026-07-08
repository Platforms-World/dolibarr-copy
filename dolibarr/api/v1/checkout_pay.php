<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_request.php';
require_once __DIR__ . '/_invoice_common.php';

takeposApiRequireMethod(array('POST'));

$auth = takeposApiAuth($db, 'write', 'takepos.api_layer');
$entity = (int) $auth['entity'];
$body = takeposApiRequestBody();

$cartId = (int) takeposApiRequestRequireField($body, 'cart_id');
$method = takeposApiRequestRequireField($body, 'method');
$terminalId = (!empty($body['terminal_id']) ? (int) $body['terminal_id'] : 0);
$idempotencyKey = (!empty($body['idempotency_key']) ? (string) $body['idempotency_key'] : '');

if ($cartId <= 0) {
    throw new TakeposApiException('INVALID_PARAMETER', 'cart_id is required', 422);
}

$invoice = takeposApiRequireTakeposInvoice($db, $entity, $cartId);
$existingIdempotency = TakeposApiIdempotencyService::findRecord($db, $entity, 'checkout_pay', $idempotencyKey);
if ($existingIdempotency) {
    if ((int) $existingIdempotency->invoice_id > 0 && (int) $existingIdempotency->invoice_id !== (int) $invoice->id) {
        throw new TakeposApiException('IDEMPOTENCY_KEY_CONFLICT', 'Idempotency key was already used for another invoice.', 409);
    }

    $payload = json_decode((string) $existingIdempotency->response_json, true);
    if (is_array($payload) && !empty($payload)) {
        takeposApiSend($payload, !empty($existingIdempotency->http_code) ? (int) $existingIdempotency->http_code : 200);
    }
}

$requestedAmount = ((int) $invoice->status === Facture::STATUS_DRAFT)
    ? (float) price2num($invoice->total_ttc, 'MT')
    : (float) TakeposApiPaymentService::invoiceSettlement($db, $entity, $invoice)['remaining_amount'];

$replay = TakeposApiIdempotencyService::loadReplayPayload($db, $entity, 'checkout_pay', $idempotencyKey, $cartId, $requestedAmount);
if ($replay) {
    takeposApiSend($replay['payload'], $replay['http_code']);
}

$db->begin();

try {
    TakeposApiPaymentService::assertPaymentReady($db, $entity, $invoice, $method, $terminalId);

    if ((int) $invoice->status === Facture::STATUS_DRAFT) {
        $invoice = TakeposApiCheckoutService::validateCart($db, $entity, $invoice);
    }

    $result = TakeposApiPaymentService::applyFullPayment($db, $entity, $invoice, $method, $terminalId, false);
    $invoice = takeposApiRequireTakeposInvoice($db, $entity, (int) $result['invoice_id']);
    $payloadData = takeposApiInvoiceSnapshot($db, $entity, $invoice, true);
    $payloadData['payment_id'] = (int) $result['payment_id'];
    if (isset($result['shift_id'])) {
        $payloadData['shift_id'] = $result['shift_id'];
    }

    $payload = takeposApiBuildSuccessPayload($payloadData, array('entity' => $entity));
    try {
        TakeposApiIdempotencyService::storeResponse($db, $auth['user'], $entity, 'checkout_pay', $idempotencyKey, (int) $invoice->id, $requestedAmount, $payload, 200);
    } catch (Throwable $e) {
        takeposApiLogError('Idempotency store failed for checkout_pay endpoint: ' . $e->getMessage(), LOG_WARNING);
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    if ($e instanceof TakeposApiException) {
        throw $e;
    }
    throw new TakeposApiException('INTERNAL_ERROR', $e->getMessage(), 500);
}

takeposApiSend($payload, 200);
