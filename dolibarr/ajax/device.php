<?php
/**
 * Device layer AJAX controller.
 */
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');
if (!defined('NOREQUIREAJAX')) define('NOREQUIREAJAX', '1');

if (!defined('DOL_DOCUMENT_ROOT')) {
    $mainPath = __DIR__ . '/../../main.inc.php';
    if (!file_exists($mainPath)) {
        $mainPath = __DIR__ . '/../../../main.inc.php';
    }
    require $mainPath;
}

require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposAccess.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposAudit.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposDeviceService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposPrinterService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposTerminalService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposStoreService.class.php';

$langs->loadLangs(array('main', 'cashdesk', 'takeposcustom@takepos'));

function takeposDeviceJson($payload, $httpCode = 200)
{
    if (!headers_sent()) {
        http_response_code((int) $httpCode);
    }
    top_httphead('application/json');
    echo json_encode($payload);
    exit;
}

function takeposDeviceRequireToken($db, $user)
{
    $token = (string) GETPOST('token', 'alpha');
    $sessionToken = isset($_SESSION['newtoken']) ? (string) $_SESSION['newtoken'] : '';
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        TakeposAccess::denyJson($db, $user, $langs->trans('TakeposCommonInvalidCsrfToken'), array(
            'endpoint' => 'ajax/device.php',
            'action' => GETPOST('action', 'aZ09')
        ));
    }
}

$action = GETPOST('action', 'aZ09');
if ($action === '') {
    takeposDeviceJson(array('success' => false, 'error' => 'missing_action', 'message' => $langs->trans('TakeposDeviceMissingAction')), 400);
}

$terminal = isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null;
$entity = !empty($user->entity) ? (int) $user->entity : 1;
$ctx = array('endpoint' => 'ajax/device.php', 'requested_action' => $action);

try {
    if ($action === 'lookups') {
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.device_layer', 'takepos.device.manage', $terminal, $ctx);
        $terminals = TakeposTerminalService::listTerminals($db, $entity, 0, false);
        $stores = TakeposStoreService::listStores($db, $entity, false);
        takeposDeviceJson(array(
            'success' => true,
            'device_types' => TakeposDeviceService::allowedDeviceTypes(),
            'binding_types' => TakeposDeviceService::allowedBindingTypes(),
            'printer_drivers' => TakeposPrinterService::allowedDrivers(),
            'terminals' => $terminals,
            'stores' => $stores,
        ));
    }

    if ($action === 'list_profiles') {
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.device_layer', 'takepos.device.manage', $terminal, $ctx);
        $type = GETPOST('device_type', 'aZ09');
        $rows = TakeposDeviceService::listProfiles($db, $entity, $type, false);
        takeposDeviceJson(array('success' => true, 'rows' => $rows));
    }

    if ($action === 'save_profile') {
        takeposDeviceRequireToken($db, $user);
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.device_layer', 'takepos.device.manage', $terminal, $ctx);

        $profileId = GETPOSTINT('profile_id');
        $deviceCode = GETPOST('device_code', 'aZ09');
        $label = GETPOST('label', 'none');
        $deviceType = GETPOST('device_type', 'aZ09');
        $settingsJson = GETPOST('settings_json', 'none');
        $active = GETPOSTINT('active');

        $savedId = TakeposDeviceService::saveProfile($db, $user, $entity, $profileId, $deviceCode, $label, $deviceType, $settingsJson, $active);
        takeposDeviceJson(array('success' => true, 'profile_id' => $savedId));
    }

    if ($action === 'list_bindings') {
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.device_layer', 'takepos.device.manage', $terminal, $ctx);
        $terminalId = GETPOSTINT('terminal_id');
        $rows = TakeposDeviceService::listBindings($db, $entity, $terminalId, false);
        takeposDeviceJson(array('success' => true, 'rows' => $rows));
    }

    if ($action === 'bind_terminal') {
        takeposDeviceRequireToken($db, $user);
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.device_layer', 'takepos.device.manage', $terminal, $ctx);

        $terminalId = GETPOSTINT('terminal_id');
        $profileId = GETPOSTINT('profile_id');
        $bindingType = GETPOST('binding_type', 'aZ09');
        $priority = GETPOSTINT('priority');
        $active = GETPOSTINT('active');

        $bindingId = TakeposDeviceService::bindProfileToTerminal($db, $user, $entity, $terminalId, $profileId, $bindingType, $priority, $active);
        takeposDeviceJson(array('success' => true, 'binding_id' => $bindingId));
    }

    if ($action === 'list_printers') {
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.printer_profiles', 'takepos.device.manage', $terminal, $ctx);
        $rows = TakeposPrinterService::listProfiles($db, $entity, false);
        takeposDeviceJson(array('success' => true, 'rows' => $rows));
    }

    if ($action === 'save_printer') {
        takeposDeviceRequireToken($db, $user);
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.printer_profiles', 'takepos.device.manage', $terminal, $ctx);

        $profileId = GETPOSTINT('profile_id');
        $profileCode = GETPOST('profile_code', 'aZ09');
        $label = GETPOST('label', 'none');
        $driverType = GETPOST('driver_type', 'aZ09');
        $targetUri = GETPOST('target_uri', 'none');
        $copies = GETPOST('copies', 'none');
        $settingsJson = GETPOST('settings_json', 'none');
        $active = GETPOSTINT('active');

        $savedId = TakeposPrinterService::saveProfile($db, $user, $entity, $profileId, $profileCode, $label, $driverType, $targetUri, $copies, $settingsJson, $active);
        takeposDeviceJson(array('success' => true, 'profile_id' => $savedId));
    }

    if ($action === 'test_printer') {
        takeposDeviceRequireToken($db, $user);
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.printer_profiles', 'takepos.device.test', $terminal, $ctx);

        $profileId = GETPOSTINT('profile_id');
        $terminalId = GETPOSTINT('terminal_id');
        $content = GETPOST('content', 'none');
        $result = TakeposPrinterService::sendTestPrint($db, $user, $entity, $profileId, $content, $terminalId);
        takeposDeviceJson(array('success' => true, 'result' => $result));
    }

    if ($action === 'test_display') {
        takeposDeviceRequireToken($db, $user);
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.customer_display_profiles', 'takepos.device.test', $terminal, $ctx);

        $terminalId = GETPOSTINT('terminal_id');
        $message = GETPOST('message', 'none');
        $result = TakeposDeviceService::sendDisplayTest($db, $user, $entity, $terminalId, $message);
        takeposDeviceJson(array('success' => true, 'result' => $result));
    }

    takeposDeviceJson(array('success' => false, 'error' => 'unsupported_action', 'message' => $langs->trans('TakeposDeviceUnsupportedAction')), 400);
} catch (Throwable $e) {
    takeposDeviceJson(array('success' => false, 'error' => 'runtime_error', 'message' => $e->getMessage()), 500);
}
