<?php
/*
 * TakePOS API v1 - Offline
 * GET  : current offline state (offline_mode flag, can_use_offline, sync summary)
 * POST : toggle offline mode (action=set_mode, enabled=true/false)
 * Component: TakeposOfflineService
 */
require_once __DIR__ . '/bootstrap.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposOfflineService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposSyncService.class.php';

$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET');
if (!in_array($method, array('GET', 'POST'), true)) {
    takeposApiError('METHOD_NOT_ALLOWED', 'Method not allowed.', 405, null, array(), array('Allow: GET, POST'));
}

$auth = takeposApiAuth($db, ($method === 'GET' ? 'read' : 'write'), 'takepos.api_layer');
$entity = (int) $auth['entity'];
$user = $auth['user'];

if ($method === 'POST') {
    $body = takeposApiRequestBody();
    $action = isset($body['action']) ? strtolower(trim((string) $body['action'])) : 'set_mode';
    if ($action !== 'set_mode') {
        takeposApiError('INVALID_PARAMETER', 'Unknown action. Use set_mode.', 422);
    }
    if (!TakeposOfflineService::canUseOffline($db, $user)) {
        takeposApiError('FORBIDDEN', 'Offline mode is not permitted for this user.', 403);
    }
    $enabled = !empty($body['enabled']);
    try {
        TakeposOfflineService::setOfflineMode($db, $user, $enabled, 'api');
    } catch (Throwable $e) {
        takeposApiError('OFFLINE_SET_FAILED', $e->getMessage(), 422);
    }
    takeposApiAuditAccess($db, $auth, 'offline.set_mode', array('enabled' => ($enabled ? 1 : 0)));
    takeposApiSuccess(TakeposOfflineService::state($db, $user), array('entity' => $entity));
}

// GET
takeposApiAuditAccess($db, $auth, 'offline.state', array());
takeposApiSuccess(TakeposOfflineService::state($db, $user), array('entity' => $entity));
