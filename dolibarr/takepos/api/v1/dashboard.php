<?php
/*
 * TakePOS API v1 - Dashboard
 * GET : executive dashboard dataset (KPIs, sales trend, top products,
 *       supplier summary, inventory alerts, cheque summary, decision insights).
 * Component: TakeposDashboardService
 */
require_once __DIR__ . '/bootstrap.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposDashboardService.class.php';

takeposApiRequireMethod(array('GET'));

$auth = takeposApiAuth($db, 'read', 'takepos.api_layer');
$entity = (int) $auth['entity'];

$filters = array(
    'date_from' => GETPOST('date_from', 'alphanohtml'),
    'date_to' => GETPOST('date_to', 'alphanohtml'),
);

global $langs;
try {
    $service = new TakeposDashboardService($db, $langs, $entity);
    $dataset = $service->getDataset($filters);
} catch (Throwable $e) {
    takeposApiError('DASHBOARD_FAILED', $e->getMessage(), 422);
}

takeposApiAuditAccess($db, $auth, 'dashboard.dataset', array('filters' => $filters));
takeposApiSuccess($dataset, array('entity' => $entity));
