<?php
/**
 * KPI dashboard AJAX provider.
 */
if (!ob_get_level()) {
    ob_start();
}

if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');
if (!defined('NOREQUIREAJAX')) define('NOREQUIREAJAX', '1');
if (!defined('NOBROWSERNOTIF')) define('NOBROWSERNOTIF', '1');

if (!defined('DOL_DOCUMENT_ROOT')) {
    $mainPath = __DIR__ . '/../../main.inc.php';
    if (!file_exists($mainPath)) {
        $mainPath = __DIR__ . '/../../../main.inc.php';
    }
    require $mainPath;
}

require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposAccess.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposAudit.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposAnalyticsService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposInputValidator.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposUtf8.class.php';

$langs->loadLangs(array('cashdesk', 'main', 'bills'));

function takeposKpiJson($success, $message = '', $data = array(), $errors = array(), $httpCode = 200, $extra = array())
{
    // Never leak buffered warnings/notices before JSON payload.
    if (ob_get_level() && ob_get_length() > 0) {
        ob_clean();
    }

    if (!headers_sent()) {
        http_response_code((int) $httpCode);
        header('Content-Type: application/json; charset=UTF-8');
    }

    $payload = array(
        'success' => (bool) $success,
        'message' => (string) $message,
        'data' => is_array($data) ? $data : array(),
        'errors' => is_array($errors) ? array_values($errors) : array((string) $errors),
    );

    if (!empty($extra) && is_array($extra)) {
        $payload = array_merge($payload, $extra);
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

TakeposUtf8::bootstrapConnection($db);

$action = GETPOST('action', 'aZ09');
if ($action === '') {
    $action = 'run';
}

$terminal = isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null;
TakeposAccess::requireAjaxAccess(
    $db,
    $user,
    'takepos.analytics',
    'takepos.analytics.view',
    $terminal,
    array('endpoint' => 'ajax/get_kpi.php', 'requested_action' => $action)
);

try {
    if ($action === 'filters') {
        $lookups = TakeposAnalyticsService::filterLookups($db, $user);
        // Keep compatibility with old frontend contract by exposing root-level filter keys too.
        takeposKpiJson(true, 'KPI filters loaded.', array('filters' => $lookups), array(), 200, array('filters' => $lookups));
    }

    $filters = array(
        'date_from' => TakeposInputValidator::normalizeUtf8Text(GETPOST('date_from', 'none'), 10, true),
        'date_to' => TakeposInputValidator::normalizeUtf8Text(GETPOST('date_to', 'none'), 10, true),
        'cashier_id' => GETPOSTINT('cashier_id'),
        'terminal_code' => TakeposInputValidator::normalizeUtf8Text(GETPOST('terminal_code', 'none'), 64, true),
        'store_id' => GETPOSTINT('store_id'),
        'payment_method' => TakeposInputValidator::normalizeUtf8Text(GETPOST('payment_method', 'none'), 32, true),
    );

    if ($action === 'export_csv') {
        TakeposAccess::requireAjaxAccess(
            $db,
            $user,
            'takepos.analytics',
            'takepos.analytics.export',
            $terminal,
            array('endpoint' => 'ajax/get_kpi.php', 'requested_action' => 'export_csv')
        );

        $data = TakeposAnalyticsService::collect($db, $user, $filters);

        TakeposAudit::logEvent($db, $user, 'analytics_exported', TakeposAudit::SEVERITY_INFO, array('source' => 'kpi_dashboard'), 'KPI analytics exported');

        $csv = array();
        $csv[] = 'Metric,Value';
        foreach ((array) $data['cards'] as $k => $v) {
            $csv[] = '"' . str_replace('"', '""', (string) $k) . '","' . str_replace('"', '""', (string) $v) . '"';
        }

        if (!headers_sent()) {
            if (ob_get_level() && ob_get_length() > 0) {
                ob_clean();
            }
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="takepos_kpi_' . date('Ymd_His') . '.csv"');
        }
        echo "\xEF\xBB\xBF";
        echo implode("\n", $csv);
        exit;
    }

    $data = TakeposAnalyticsService::collect($db, $user, $filters);
    TakeposAudit::logEvent($db, $user, 'analytics_opened', TakeposAudit::SEVERITY_INFO, array('source' => 'kpi_dashboard', 'action' => $action), 'KPI analytics opened');

    takeposKpiJson(true, 'KPI data loaded.', $data);
} catch (Throwable $e) {
    takeposKpiJson(false, 'Unable to load KPI data.', array(), array($e->getMessage()), 500);
}
