<?php
/*
 * TakePOS API v1 - Printers (printer profiles & test prints)
 * GET  : list printer profiles (?active_only=) or show one (?id=)
 * POST : save profile (action=save) or send a test print (action=test)
 * Component: TakeposPrinterService
 */
require_once __DIR__ . '/bootstrap.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposPrinterService.class.php';

$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET');
if (!in_array($method, array('GET', 'POST'), true)) {
    takeposApiError('METHOD_NOT_ALLOWED', 'Method not allowed.', 405, null, array(), array('Allow: GET, POST'));
}

$auth = takeposApiAuth($db, ($method === 'GET' ? 'read' : 'write'), 'takepos.api_layer');
$entity = (int) $auth['entity'];
$user = $auth['user'];

TakeposPrinterService::ensureSchema($db);

function takeposApiPrinterPayload($row)
{
    return array(
        'id' => (int) $row->rowid,
        'profile_code' => (string) $row->profile_code,
        'label' => (!empty($row->label) ? (string) $row->label : null),
        'driver_type' => (string) $row->driver_type,
        'target_uri' => (!empty($row->target_uri) ? (string) $row->target_uri : null),
        'copies' => (isset($row->copies) ? (int) $row->copies : 1),
        'settings' => (!empty($row->settings) ? (string) $row->settings : '{}'),
        'active' => (isset($row->active) ? (int) $row->active : 1),
    );
}

if ($method === 'POST') {
    $body = takeposApiRequestBody();
    $action = isset($body['action']) ? strtolower(trim((string) $body['action'])) : 'save';

    try {
        if ($action === 'test') {
            $printerProfileId = (int) takeposApiRequestRequireField($body, 'profile_id');
            $content = isset($body['content']) ? (string) $body['content'] : '';
            $terminalId = isset($body['terminal_id']) ? (int) $body['terminal_id'] : 0;
            $result = TakeposPrinterService::sendTestPrint($db, $user, $entity, $printerProfileId, $content, $terminalId);
            takeposApiAuditAccess($db, $auth, 'printers.test', array('profile_id' => $printerProfileId, 'terminal_id' => $terminalId));
            takeposApiSuccess(array('sent' => true, 'result' => $result), array('entity' => $entity));
        }

        // save profile
        $profileId = isset($body['id']) ? (int) $body['id'] : 0;
        $profileCode = (string) takeposApiRequestRequireField($body, 'profile_code');
        $label = isset($body['label']) ? (string) $body['label'] : '';
        $driverType = (string) takeposApiRequestRequireField($body, 'driver_type');
        $targetUri = isset($body['target_uri']) ? (string) $body['target_uri'] : '';
        $copies = isset($body['copies']) ? (int) $body['copies'] : 1;
        $settings = isset($body['settings']) ? (is_array($body['settings']) ? json_encode($body['settings']) : (string) $body['settings']) : '{}';
        $active = isset($body['active']) ? (int) $body['active'] : 1;

        $savedId = TakeposPrinterService::saveProfile($db, $user, $entity, $profileId, $profileCode, $label, $driverType, $targetUri, $copies, $settings, $active);
        $row = TakeposPrinterService::getProfileById($db, $entity, (int) $savedId);
        takeposApiAuditAccess($db, $auth, ($profileId ? 'printers.update' : 'printers.create'), array('profile_id' => (int) $savedId));
        takeposApiSuccess($row ? takeposApiPrinterPayload($row) : array('id' => (int) $savedId), array('entity' => $entity), ($profileId ? 200 : 201));
    } catch (Throwable $e) {
        takeposApiError('PRINTER_SAVE_FAILED', $e->getMessage(), 422);
    }
}

// GET
$id = GETPOSTINT('id');
if ($id > 0) {
    $row = TakeposPrinterService::getProfileById($db, $entity, $id);
    if (!$row) {
        takeposApiError('NOT_FOUND', 'Printer profile not found.', 404);
    }
    takeposApiAuditAccess($db, $auth, 'printers.show', array('profile_id' => $id));
    takeposApiSuccess(takeposApiPrinterPayload($row), array('entity' => $entity));
}

$activeOnly = (GETPOSTINT('active_only') === 1);
$rows = array();
foreach (TakeposPrinterService::listProfiles($db, $entity, $activeOnly) as $row) {
    $rows[] = takeposApiPrinterPayload($row);
}

takeposApiAuditAccess($db, $auth, 'printers.index', array('count' => count($rows)));
takeposApiSuccess($rows, array('entity' => $entity, 'count' => count($rows), 'allowed_drivers' => TakeposPrinterService::allowedDrivers()));
