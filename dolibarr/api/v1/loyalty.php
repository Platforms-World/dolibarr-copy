<?php
require_once __DIR__ . '/_bootstrap.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposCustomerService.class.php';

$auth = takeposApiAuth($db, 'read', 'takepos.api_layer');
$entity = (int) $auth['entity'];

if (!TakeposUserAccess::featureEnabledForEntityStrict($db, $entity, 'takepos.loyalty')) {
    takeposApiJson(array('success' => false, 'error' => 'feature_disabled', 'message' => 'Loyalty feature is disabled for this entity.'), 403);
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
        takeposApiJson(array('success' => false, 'error' => 'not_found', 'message' => 'Customer not found'), 404);
    }

    $txns = TakeposLoyaltyService::listTransactions($db, $entity, $customerId, $limit);

    takeposApiAuditAccess($db, $auth, 'loyalty', array('customer_id' => $customerId, 'transactions' => count($txns)));

    takeposApiJson(array(
        'success' => true,
        'entity' => $entity,
        'customer_id' => $customerId,
        'summary' => $summary,
        'transactions' => $txns,
    ));
}

TakeposLoyaltyService::ensureSchema($db);
$sql = "SELECT a.fk_soc AS customer_id, a.points_balance, a.total_earned, a.total_redeemed, a.tier_code, a.last_purchase_date, a.purchase_count"
    . " FROM " . TakeposLoyaltyService::tableAccount() . " a"
    . " WHERE a.entity = " . $entity
    . " ORDER BY a.points_balance DESC, a.rowid DESC"
    . " LIMIT " . ((int) $limit);
$rows = array();
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $rows[] = $obj;
    }
}

takeposApiAuditAccess($db, $auth, 'loyalty', array('customer_id' => 0, 'count' => count($rows)));

takeposApiJson(array(
    'success' => true,
    'entity' => $entity,
    'count' => count($rows),
    'rows' => $rows,
));
