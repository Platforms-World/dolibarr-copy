<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_request.php';
require_once __DIR__ . '/_invoice_common.php';

function takeposApiRefundAllowedStoreIds($db, $auth, $entity)
{
    if (!TakeposStoreService::enforceStoreRestrictionEnabled($db) || !empty($auth['user']->admin)) {
        return array();
    }

    return TakeposStoreService::getUserStoreIds($db, $entity, (int) $auth['user']->id);
}

function takeposApiRefundAssertStoreAccess($allowedStoreIds, $storeId)
{
    if (empty($allowedStoreIds)) {
        return;
    }
    if ((int) $storeId > 0 && !in_array((int) $storeId, $allowedStoreIds, true)) {
        throw new TakeposApiException('FORBIDDEN', 'Refund store access is denied for this user.', 403);
    }
}

function takeposApiRefundRowPayload($db, $entity, $row)
{
    $data = array(
        'id' => (int) $row->rowid,
        'refund_ref' => (!empty($row->refund_ref) ? (string) $row->refund_ref : null),
        'invoice_id' => (!empty($row->fk_original_invoice) ? (int) $row->fk_original_invoice : null),
        'invoice_ref' => (!empty($row->original_invoice_ref) ? (string) $row->original_invoice_ref : null),
        'amount' => (float) price2num($row->total_amount, 'MT'),
        'reason' => (!empty($row->note) ? (string) $row->note : null),
        'status' => (!empty($row->status) ? (string) $row->status : null),
        'created_at' => (!empty($row->date_creation) ? (string) $row->date_creation : null),
    );

    if (!empty($row->fk_original_invoice)) {
        $invoice = takeposApiFetchInvoice($db, (int) $row->fk_original_invoice);
        if ($invoice) {
            $settlement = TakeposApiPaymentService::invoiceSettlement($db, $entity, $invoice);
            $data['payment_status'] = $settlement['payment_status'];
            $data['paid_amount'] = (float) $settlement['paid_amount'];
            $data['remaining_amount'] = (float) $settlement['remaining_amount'];
        }
    }

    return $data;
}

$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET');
if (!in_array($method, array('GET', 'POST'), true)) {
    takeposApiError('METHOD_NOT_ALLOWED', 'Method not allowed.', 405, null, array(), array('Allow: GET, POST'));
}

$auth = takeposApiAuth($db, ($method === 'GET' ? 'read' : 'write'), 'takepos.api_layer');
$entity = (int) $auth['entity'];
if (!TakeposUserAccess::featureEnabledForEntityStrict($db, $entity, 'takepos.refunds')) {
    throw new TakeposApiException('FORBIDDEN', 'Refunds feature is disabled for this entity.', 403);
}

$allowedStoreIds = takeposApiRefundAllowedStoreIds($db, $auth, $entity);

if ($method === 'POST') {
    $body = takeposApiRequestBody();
    $invoiceId = (int) takeposApiRequestRequireField($body, 'invoice_id');
    $amount = takeposApiRequestRequireField($body, 'amount');
    $reason = (!empty($body['reason']) ? (string) $body['reason'] : '');
    $invoice = takeposApiRequireTakeposInvoice($db, $entity, $invoiceId);
    $terminal = TakeposApiCheckoutService::resolveTerminalForInvoice($db, $entity, $invoice);
    takeposApiRefundAssertStoreAccess($allowedStoreIds, !empty($terminal->fk_store) ? (int) $terminal->fk_store : 0);
    $result = TakeposApiRefundService::createBasicRefund($db, $entity, $invoice, $amount, $reason);
    takeposApiSuccess($result, array('entity' => $entity), 201);
}

$refundId = GETPOSTINT('id');
if ($refundId > 0) {
    $row = TakeposRefundService::getRefundById($db, $entity, $refundId);
    if (!$row) {
        throw new TakeposApiException('NOT_FOUND', 'Refund not found.', 404);
    }
    takeposApiRefundAssertStoreAccess($allowedStoreIds, !empty($row->fk_store) ? (int) $row->fk_store : 0);

    $data = takeposApiRefundRowPayload($db, $entity, $row);
    $lines = array();
    foreach (TakeposRefundService::getRefundLines($db, $entity, $refundId) as $line) {
        $lines[] = array(
            'id' => (int) $line->rowid,
            'original_line_id' => (!empty($line->fk_original_line) ? (int) $line->fk_original_line : null),
            'product_id' => (!empty($line->fk_product) ? (int) $line->fk_product : null),
            'qty_refunded' => (float) price2num($line->qty_refunded, 'MS'),
            'unit_price' => (float) price2num($line->unit_price, 'MT'),
            'line_total' => (float) price2num($line->line_total, 'MT'),
        );
    }
    $data['lines'] = $lines;

    takeposApiSuccess($data, array('entity' => $entity));
}

$invoiceId = GETPOSTINT('invoice_id');
$limit = GETPOSTINT('limit');
if ($limit <= 0) {
    $limit = 50;
}
if ($limit > 200) {
    $limit = 200;
}

$filters = array();
if ($invoiceId > 0) {
    $invoice = takeposApiRequireTakeposInvoice($db, $entity, $invoiceId);
    $terminal = TakeposApiCheckoutService::resolveTerminalForInvoice($db, $entity, $invoice);
    takeposApiRefundAssertStoreAccess($allowedStoreIds, !empty($terminal->fk_store) ? (int) $terminal->fk_store : 0);
    $filters['invoice_id'] = $invoiceId;
}

$rows = array();
foreach (TakeposRefundService::listRefunds($db, $entity, $filters, $limit) as $row) {
    if (!empty($allowedStoreIds) && !empty($row->fk_store) && !in_array((int) $row->fk_store, $allowedStoreIds, true)) {
        continue;
    }
    $rows[] = takeposApiRefundRowPayload($db, $entity, $row);
}

takeposApiSuccess($rows, array('entity' => $entity, 'count' => count($rows), 'limit' => $limit));
