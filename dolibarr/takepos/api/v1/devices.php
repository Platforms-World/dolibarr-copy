<?php
/*
 * TakePOS API v1 - Devices (peripheral profiles & terminal bindings)
 * GET  : list device profiles (?device_type=, ?active_only=), show one (?id=),
 *        list bindings (?bindings=1&terminal_id=), terminal summary (?terminal_code=)
 * POST : save profile (action=save), bind to terminal (action=bind),
 *        send customer-display test (action=display_test)
 * Component: TakeposDeviceService
 */
require_once __DIR__ . '/bootstrap.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposDeviceService.class.php';

$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET');
if (!in_array($method, array('GET', 'POST'), true)) {
    takeposApiError('METHOD_NOT_ALLOWED', 'Method not allowed.', 405, null, array(), array('Allow: GET, POST'));
}

$auth = takeposApiAuth($db, ($method === 'GET' ? 'read' : 'write'), 'takepos.api_layer');
$entity = (int) $auth['entity'];
$user = $auth['user'];

TakeposDeviceService::ensureSchema($db);

function takeposApiDevicePayload($row)
{
    return array(
        'id' => (int) $row->rowid,
        'device_code' => (string) $row->device_code,
        'label' => (!empty($row->label) ? (string) $row->label : null),
        'device_type' => (string) $row->device_type,
        'settings' => (!empty($row->settings) ? (string) $row->settings : '{}'),
        'active' => (isset($row->active) ? (int) $row->active : 1),
    );
}

if ($method === 'POST') {
    $body = takeposApiRequestBody();
    $action = isset($body['action']) ? strtolower(trim((string) $body['action'])) : 'save';

    try {
        if ($action === 'bind') {
            $terminalId = (int) takeposApiRequestRequireField($body, 'terminal_id');
            $profileId = (int) takeposApiRequestRequireField($body, 'profile_id');
            $bindingType = (string) takeposApiRequestRequireField($body, 'binding_type');
            $priority = isset($body['priority']) ? (int) $body['priority'] : 1;
            $active = isset($body['active']) ? (int) $body['active'] : 1;
            $bindingId = TakeposDeviceService::bindProfileToTerminal($db, $user, $entity, $terminalId, $profileId, $bindingType, $priority, $active);
            takeposApiAuditAccess($db, $auth, 'devices.bind', array('terminal_id' => $terminalId, 'profile_id' => $profileId, 'binding_type' => $bindingType));
            takeposApiSuccess(array('binding_id' => (int) $bindingId, 'terminal_id' => $terminalId, 'profile_id' => $profileId, 'binding_type' => $bindingType), array('entity' => $entity), 201);
        }

        if ($action === 'display_test') {
            $terminalId = (int) takeposApiRequestRequireField($body, 'terminal_id');
            $message = isset($body['message']) ? (string) $body['message'] : '';
            $result = TakeposDeviceService::sendDisplayTest($db, $user, $entity, $terminalId, $message);
            takeposApiAuditAccess($db, $auth, 'devices.display_test', array('terminal_id' => $terminalId));
            takeposApiSuccess(array('sent' => true, 'result' => $result), array('entity' => $entity));
        }

        // save profile
        $profileId = isset($body['id']) ? (int) $body['id'] : 0;
        $deviceCode = (string) takeposApiRequestRequireField($body, 'device_code');
        $label = isset($body['label']) ? (string) $body['label'] : '';
        $deviceType = (string) takeposApiRequestRequireField($body, 'device_type');
        $settings = isset($body['settings']) ? (is_array($body['settings']) ? json_encode($body['settings']) : (string) $body['settings']) : '{}';
        $active = isset($body['active']) ? (int) $body['active'] : 1;

        $savedId = TakeposDeviceService::saveProfile($db, $user, $entity, $profileId, $deviceCode, $label, $deviceType, $settings, $active);
        $row = TakeposDeviceService::getProfileById($db, $entity, (int) $savedId);
        takeposApiAuditAccess($db, $auth, ($profileId ? 'devices.update' : 'devices.create'), array('profile_id' => (int) $savedId));
        takeposApiSuccess($row ? takeposApiDevicePayload($row) : array('id' => (int) $savedId), array('entity' => $entity), ($profileId ? 200 : 201));
    } catch (Throwable $e) {
        takeposApiError('DEVICE_SAVE_FAILED', $e->getMessage(), 422);
    }
}

// GET
$terminalCode = GETPOST('terminal_code', 'alphanohtml');
if ($terminalCode !== '') {
    $summary = TakeposDeviceService::terminalDeviceSummary($db, $entity, $terminalCode);
    takeposApiAuditAccess($db, $auth, 'devices.terminal_summary', array('terminal_code' => $terminalCode));
    takeposApiSuccess($summary, array('entity' => $entity));
}

if (GETPOSTINT('bindings') === 1) {
    $terminalId = GETPOSTINT('terminal_id');
    $activeOnly = (GETPOSTINT('active_only') === 1);
    $rows = TakeposDeviceService::listBindings($db, $entity, $terminalId, $activeOnly);
    takeposApiAuditAccess($db, $auth, 'devices.bindings', array('terminal_id' => $terminalId, 'count' => count($rows)));
    takeposApiSuccess($rows, array('entity' => $entity, 'count' => count($rows)));
}

$id = GETPOSTINT('id');
if ($id > 0) {
    $row = TakeposDeviceService::getProfileById($db, $entity, $id);
    if (!$row) {
        takeposApiError('NOT_FOUND', 'Device profile not found.', 404);
    }
    takeposApiAuditAccess($db, $auth, 'devices.show', array('profile_id' => $id));
    takeposApiSuccess(takeposApiDevicePayload($row), array('entity' => $entity));
}

$deviceType = GETPOST('device_type', 'alphanohtml');
$activeOnly = (GETPOSTINT('active_only') === 1);
$rows = array();
foreach (TakeposDeviceService::listProfiles($db, $entity, $deviceType, $activeOnly) as $row) {
    $rows[] = takeposApiDevicePayload($row);
}

takeposApiAuditAccess($db, $auth, 'devices.index', array('count' => count($rows)));
takeposApiSuccess($rows, array(
    'entity' => $entity,
    'count' => count($rows),
    'allowed_device_types' => TakeposDeviceService::allowedDeviceTypes(),
    'allowed_binding_types' => TakeposDeviceService::allowedBindingTypes(),
));
