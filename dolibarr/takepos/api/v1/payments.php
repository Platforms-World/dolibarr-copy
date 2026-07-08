<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_request.php';
require_once __DIR__ . '/_invoice_common.php';

takeposApiRequireMethod(array('POST'));

$auth = takeposApiAuth($db, 'write', 'takepos.api_layer');
$entity = (int) $auth['entity'];
$body = takeposApiRequestBody();

$invoiceId = (int) takeposApiRequestRequireField($body, 'invoice_id');
if ($invoiceId <= 0) {
    throw new TakeposApiException('INVALID_PARAMETER', 'invoice_id is required', 422);
}

$invoice = takeposApiRequireTakeposInvoice($db, $entity, $invoiceId);
$terminalId = (!empty($body['terminal_id']) ? (int) $body['terminal_id'] : 0);
$currency = (!empty($body['currency']) ? (string) $body['currency'] : '');
$idempotencyKey = (!empty($body['idempotency_key']) ? (string) $body['idempotency_key'] : '');

if (!empty($body['payments']) && is_array($body['payments'])) {
    $payments = $body['payments'];
} else {
    $method = takeposApiRequestRequireField($body, 'method');
    $amount = takeposApiRequestRequireField($body, 'amount');
    $payments = array(array('method' => $method, 'amount' => $amount));
}

$requestedAmount = TakeposApiPaymentService::paymentTotalAmount($payments);
$replay = TakeposApiIdempotencyService::loadReplayPayload($db, $entity, 'payments', $idempotencyKey, $invoiceId, $requestedAmount);
if ($replay) {
    takeposApiSend($replay['payload'], $replay['http_code']);
}

// FIX (B5): Wrap all payment inserts in a transaction so that a failure on any
// payment in a split-payment request rolls back all previously inserted payments.
// Without this, a partial failure left the invoice in an inconsistent "partially
// paid" state with no rollback path.
$db->begin();

try {
    $result = TakeposApiPaymentService::applyPayments($db, $entity, $invoice, $payments, $terminalId, $currency);
    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    if ($e instanceof TakeposApiException) {
        throw $e;
    }
    throw new TakeposApiException('INTERNAL_ERROR', $e->getMessage(), 500);
}

if (count($result['payment_ids']) > 1) {
    $result['payment_ids'] = array_values($result['payment_ids']);
} else {
    unset($result['payment_ids']);
}

$payload = takeposApiBuildSuccessPayload($result, array('entity' => $entity));
try {
    TakeposApiIdempotencyService::storeResponse($db, $auth['user'], $entity, 'payments', $idempotencyKey, $invoiceId, $requestedAmount, $payload, 200);
} catch (Throwable $e) {
    takeposApiLogError('Idempotency store failed for payments endpoint: ' . $e->getMessage(), LOG_WARNING);
}
takeposApiSend($payload, 200);
