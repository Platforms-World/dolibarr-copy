<?php
/*
 * TakePOS API v1 - Exchanges
 * POST : process an exchange (return original lines + sell replacement lines,
 *        settling the net difference). Component: TakeposExchangeService
 */
require_once __DIR__ . '/bootstrap.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposExchangeService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposManagerOverrideService.class.php';

takeposApiRequireMethod(array('POST'));

$auth = takeposApiAuth($db, 'write', 'takepos.api_layer');
$entity = (int) $auth['entity'];
$user = $auth['user'];

TakeposExchangeService::ensureSchema($db);

$body = takeposApiRequestBody();
$originalInvoiceId = (int) takeposApiRequestRequireField($body, 'original_invoice_id');

$payload = array(
    'original_invoice_id' => $originalInvoiceId,
    'return_lines' => isset($body['return_lines']) && is_array($body['return_lines']) ? $body['return_lines'] : array(),
    'new_lines' => isset($body['new_lines']) && is_array($body['new_lines']) ? $body['new_lines'] : array(),
    'reason_code' => isset($body['reason_code']) ? (string) $body['reason_code'] : 'other',
    'note' => isset($body['note']) ? (string) $body['note'] : '',
    'restock_default' => isset($body['restock_default']) ? (int) $body['restock_default'] : 0,
    'settlement_method' => isset($body['settlement_method']) ? (string) $body['settlement_method'] : 'CASH',
    'manager_login' => isset($body['manager_login']) ? (string) $body['manager_login'] : '',
    'manager_password' => isset($body['manager_password']) ? (string) $body['manager_password'] : '',
    'manager_barcode' => isset($body['manager_barcode']) ? (string) $body['manager_barcode'] : '',
);

try {
    $result = TakeposExchangeService::createExchange($db, $user, $payload);
} catch (Throwable $e) {
    takeposApiError('EXCHANGE_FAILED', $e->getMessage(), 422);
}

takeposApiAuditAccess($db, $auth, 'exchanges.create', array(
    'original_invoice_id' => $originalInvoiceId,
    'exchange_id' => (isset($result['exchange_id']) ? (int) $result['exchange_id'] : 0),
));
takeposApiSuccess($result, array('entity' => $entity), 201);
