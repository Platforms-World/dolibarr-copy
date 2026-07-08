<?php
// FIX (B1): Changed from _bootstrap.php shim to bootstrap.php directly.
// FIX (B2): Replaced all takeposApiJson() calls with standard takeposApiSuccess()
//           / takeposApiError() / TakeposApiException contract so error responses
//           match every other v1 endpoint: { success, error: { code, message }, request_id }.
require_once __DIR__ . '/bootstrap.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposCustomerService.class.php';

takeposApiRequireMethod(array('GET'));

$auth = takeposApiAuth($db, 'read', 'takepos.api_layer');
$entity = (int) $auth['entity'];

if (!TakeposUserAccess::featureEnabledForEntityStrict($db, $entity, 'takepos.loyalty')) {
    throw new TakeposApiException('FEATURE_DISABLED', 'Loyalty feature is disabled for this entity.', 403);
}

$customerId = GETPOSTINT('customer_id');
$limit = GETPOSTINT('limit');
if ($limit <= 0) {
    $limit = 100;
}
if ($limit > 500) {
    $limit = 500;
}

if ($customerId > 0) {
    $summary = TakeposCustomerService::customerSummary($db, $entity, $customerId);
    if (!$summary) {
        throw new TakeposApiException('NOT_FOUND', 'Customer not found.', 404);
    }

    $txns = TakeposLoyaltyService::listTransactions($db, $entity, $customerId, $limit);

    takeposApiAuditAccess($db, $auth, 'loyalty.customer', array('customer_id' => $customerId, 'transactions' => count($txns)));

    takeposApiSuccess(array(
        'customer_id' => (int) $customerId,
        'summary'     => $summary,
        'transactions' => $txns,
    ), array('entity' => $entity));
}

TakeposLoyaltyService::ensureSchema($db);
$sql = 'SELECT a.fk_soc AS customer_id, a.points_balance, a.total_earned, a.total_redeemed, a.tier_code, a.last_purchase_date, a.purchase_count'
    . ' FROM ' . TakeposLoyaltyService::tableAccount() . ' a'
    . ' WHERE a.entity = ' . $entity
    . ' ORDER BY a.points_balance DESC, a.rowid DESC'
    . ' LIMIT ' . ((int) $limit);
$rows = array();
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $rows[] = $obj;
    }
}

takeposApiAuditAccess($db, $auth, 'loyalty.index', array('customer_id' => 0, 'count' => count($rows)));

takeposApiSuccess($rows, array('entity' => $entity, 'count' => count($rows), 'limit' => $limit));
