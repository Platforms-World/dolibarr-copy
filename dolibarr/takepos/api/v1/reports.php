<?php
/*
 * TakePOS API v1 - Reports (analytics)
 * GET : aggregated analytics dataset (KPI cards, sales by hour, payment mix,
 *       top/slow products, refunds, shift reconciliation, store/terminal compare).
 *       Pass ?lookups=1 to get filter lookup values (stores/terminals/cashiers).
 * Component: TakeposAnalyticsService
 */
require_once __DIR__ . '/bootstrap.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposAnalyticsService.class.php';

takeposApiRequireMethod(array('GET'));

$auth = takeposApiAuth($db, 'read', 'takepos.api_layer');
$entity = (int) $auth['entity'];
$user = $auth['user'];

if (GETPOSTINT('lookups') === 1) {
    $lookups = TakeposAnalyticsService::filterLookups($db, $user);
    takeposApiAuditAccess($db, $auth, 'reports.lookups', array());
    takeposApiSuccess($lookups, array('entity' => $entity));
}

$filters = array(
    'date_from' => GETPOST('date_from', 'alphanohtml'),
    'date_to' => GETPOST('date_to', 'alphanohtml'),
    'store_id' => GETPOSTINT('store_id'),
    'terminal_code' => GETPOST('terminal_code', 'alphanohtml'),
    'cashier_id' => GETPOSTINT('cashier_id'),
    'payment_method' => GETPOST('payment_method', 'alphanohtml'),
);

try {
    $dataset = TakeposAnalyticsService::collect($db, $user, $filters);
} catch (Throwable $e) {
    takeposApiError('REPORT_FAILED', $e->getMessage(), 422);
}

takeposApiAuditAccess($db, $auth, 'reports.collect', array('filters' => $filters));
takeposApiSuccess($dataset, array('entity' => $entity, 'filters' => $filters));
