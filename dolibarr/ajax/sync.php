<?php
/**
 * Offline/sync queue AJAX controller.
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
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposInputValidator.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposUserAccess.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposSyncService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposOfflineService.class.php';

$langs->loadLangs(array('cashdesk', 'main', 'takeposcustom@takepos'));

function takeposSyncJson($payload, $httpCode = 200)
{
    global $langs;
    $payload = is_array($payload) ? $payload : array();
    $success = !empty($payload['success']);

    if (!array_key_exists('message', $payload)) {
        if (isset($langs) && is_object($langs)) {
            $payload['message'] = $success ? $langs->trans('OK') : $langs->trans('TakeposCommonRequestFailed');
        } else {
            $payload['message'] = $success ? 'OK' : 'Request failed';
        }
    }
    if (!array_key_exists('errors', $payload)) {
        $payload['errors'] = $success ? array() : array(!empty($payload['error']) ? (string) $payload['error'] : 'request_failed');
    }
    if (!array_key_exists('data', $payload)) {
        $data = array();
        foreach (array('state', 'result', 'row', 'rows', 'summary') as $key) {
            if (array_key_exists($key, $payload)) {
                $data[$key] = $payload[$key];
            }
        }
        $payload['data'] = $data;
    }

    if (!headers_sent()) {
        http_response_code((int) $httpCode);
        header('Content-Type: application/json; charset=UTF-8');
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function takeposSyncRequireToken($db, $user)
{
    global $langs;
    $token = (string) GETPOST('token', 'alpha');
    $sessionToken = isset($_SESSION['newtoken']) ? (string) $_SESSION['newtoken'] : '';
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        $msg = (isset($langs) && is_object($langs)) ? $langs->trans('TakeposCommonInvalidCsrfToken') : 'Invalid CSRF token';
        TakeposAccess::denyJson($db, $user, $msg, array('endpoint' => 'ajax/sync.php', 'action' => GETPOST('action', 'aZ09')));
    }
}

$action = GETPOST('action', 'aZ09');
if ($action === '') {
    takeposSyncJson(array('success' => false, 'error' => 'missing_action', 'message' => $langs->trans('TakeposCommonMissingAction')), 400);
}

$terminal = isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null;
$entity = !empty($user->entity) ? (int) $user->entity : 1;
$baseContext = array('endpoint' => 'ajax/sync.php', 'requested_action' => $action);

try {
    if ($action === 'status') {
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.offline_mode', 'takepos.offline.use', $terminal, $baseContext);
        $state = TakeposOfflineService::state($db, $user);
        takeposSyncJson(array('success' => true, 'state' => $state));
    }

    if ($action === 'set_mode') {
        takeposSyncRequireToken($db, $user);
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.offline_mode', 'takepos.offline.use', $terminal, $baseContext);
        $enabled = GETPOSTINT('enabled') > 0;
        $state = TakeposOfflineService::setOfflineMode($db, $user, $enabled, 'ajax');
        takeposSyncJson(array('success' => true, 'state' => $state));
    }

    if ($action === 'enqueue_sale') {
        takeposSyncRequireToken($db, $user);
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.offline_mode', 'takepos.offline.use', $terminal, $baseContext);
        $invoiceId = GETPOSTINT('invoice_id');
        $localRef = GETPOST('local_ref', 'aZ09');
        $result = TakeposOfflineService::queueSaleSubmit($db, $user, $invoiceId, $localRef);
        takeposSyncJson(array('success' => true, 'result' => $result));
    }

    if ($action === 'enqueue_payment') {
        takeposSyncRequireToken($db, $user);
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.offline_mode', 'takepos.offline.use', $terminal, $baseContext);
        $invoiceId = GETPOSTINT('invoice_id');
        $paymentCode = GETPOST('payment_code', 'aZ09');
        $amount = GETPOST('amount', 'none');
        $localRef = GETPOST('local_ref', 'aZ09');
        $result = TakeposOfflineService::queuePaymentMeta($db, $user, $invoiceId, $paymentCode, $amount, $localRef);
        takeposSyncJson(array('success' => true, 'result' => $result));
    }

    if ($action === 'enqueue_cart') {
        takeposSyncRequireToken($db, $user);
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.offline_mode', 'takepos.offline.use', $terminal, $baseContext);
        $snapshotRaw = GETPOST('snapshot_json', 'none');
        $decoded = json_decode((string) $snapshotRaw, true);
        if (!is_array($decoded)) {
            $decoded = array('snapshot_raw' => (string) $snapshotRaw);
        }
        $localRef = GETPOST('local_ref', 'aZ09');
        $result = TakeposOfflineService::queueCartSnapshot($db, $user, $decoded, $localRef);
        takeposSyncJson(array('success' => true, 'result' => $result));
    }

    if ($action === 'ticket_status') {
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.sync_queue', 'takepos.use', $terminal, $baseContext);
        $localRef = GETPOST('local_ref', 'aZ09');
        $row = TakeposSyncService::statusForLocalRef($db, $entity, $localRef);
        takeposSyncJson(array('success' => true, 'row' => $row));
    }

    if ($action === 'list') {
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.sync_queue', 'takepos.sync.manage', $terminal, $baseContext);
        $filters = array(
            'status' => GETPOST('status', 'aZ09'),
            'action_type' => GETPOST('action_type', 'aZ09'),
        );
        $rows = TakeposSyncService::listQueue($db, $entity, $filters, 300);
        $summary = TakeposSyncService::summary($db, $entity);
        takeposSyncJson(array('success' => true, 'rows' => $rows, 'summary' => $summary));
    }

    if ($action === 'process_one') {
        takeposSyncRequireToken($db, $user);
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.sync_queue', 'takepos.sync.manage', $terminal, $baseContext);
        $queueId = GETPOSTINT('queue_id');
        $result = TakeposSyncService::processQueueEntry($db, $user, $queueId, false);
        takeposSyncJson(array('success' => true, 'result' => $result));
    }

    if ($action === 'process_pending') {
        takeposSyncRequireToken($db, $user);
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.sync_queue', 'takepos.sync.manage', $terminal, $baseContext);
        $limit = GETPOSTINT('limit');
        if ($limit <= 0) {
            $limit = 20;
        }
        $result = TakeposSyncService::processPending($db, $user, $limit);
        takeposSyncJson(array('success' => true, 'result' => $result));
    }

    if ($action === 'retry') {
        takeposSyncRequireToken($db, $user);
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.sync_queue', 'takepos.sync.retry', $terminal, $baseContext);
        $queueId = GETPOSTINT('queue_id');
        $result = TakeposSyncService::retry($db, $user, $queueId);
        takeposSyncJson(array('success' => true, 'result' => $result));
    }

    if ($action === 'resolve_conflict') {
        takeposSyncRequireToken($db, $user);
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.sync_queue', 'takepos.sync.resolve_conflict', $terminal, $baseContext);
        $queueId = GETPOSTINT('queue_id');
        $note = GETPOST('note', 'restricthtml');
        $result = TakeposSyncService::resolveConflict($db, $user, $queueId, $note);
        takeposSyncJson(array('success' => true, 'result' => $result));
    }

    takeposSyncJson(array('success' => false, 'error' => 'unsupported_action', 'message' => $langs->trans('TakeposCommonUnsupportedAction')), 400);
} catch (Throwable $e) {
    takeposSyncJson(array('success' => false, 'error' => 'runtime_error', 'message' => $e->getMessage()), 500);
}
