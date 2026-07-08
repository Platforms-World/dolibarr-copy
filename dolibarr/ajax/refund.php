<?php
/**
 * Refund AJAX controller.
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
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposRefundService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposUserAccess.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposUtf8.class.php';

$langs->loadLangs(array('cashdesk', 'main', 'bills', 'takeposcustom@takepos'));

function takeposRefundJson($payload, $httpCode = 200)
{
    $payload = is_array($payload) ? $payload : array();
    $success = !empty($payload['success']);

    if (!array_key_exists('message', $payload)) {
        $payload['message'] = $success ? $langs->trans('OK') : $langs->trans('TakeposShiftRequestFailed');
    }
    if (!array_key_exists('errors', $payload)) {
        $payload['errors'] = $success ? array() : array(!empty($payload['error']) ? (string) $payload['error'] : 'request_failed');
    }
    if (!array_key_exists('data', $payload)) {
        $data = array();
        foreach (array('rows', 'result', 'refund', 'lines') as $key) {
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

function takeposRefundRequireToken($db, $user)
{
    $token = TakeposInputValidator::normalizeUtf8Text(GETPOST('token', 'none'), 128, true);
    $sessionToken = isset($_SESSION['newtoken']) ? (string) $_SESSION['newtoken'] : '';
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        TakeposAccess::denyJson($db, $user, $langs->trans('TakeposCommonInvalidCsrfToken'), array('endpoint' => 'ajax/refund.php', 'action' => GETPOST('action', 'aZ09')));
    }
}

function takeposRefundParseLinesJson($jsonRaw)
{
    $jsonRaw = trim((string) $jsonRaw);
    if ($jsonRaw === '') {
        return array();
    }

    $decoded = json_decode($jsonRaw, true);
    if (!is_array($decoded)) {
        return null;
    }

    return $decoded;
}

TakeposUtf8::bootstrapConnection($db);

$action = GETPOST('action', 'aZ09');
$terminalFromSession = isset($_SESSION['takeposterminal']) ? (string) $_SESSION['takeposterminal'] : '1';
$context = array('endpoint' => 'ajax/refund.php', 'requested_action' => $action);

if ($action === '') {
    takeposRefundJson(array('success' => false, 'error' => 'missing_action', 'message' => $langs->trans('TakeposRefundMissingAction')), 400);
}

$canAccessRefundDesk = (!empty($user->admin)
    || TakeposUserAccess::userHasAnyPermission($db, $user, array('takepos.refund.view', 'takepos.refund.partial', 'takepos.refund.full')));
if (!$canAccessRefundDesk) {
    TakeposAccess::denyJson($db, $user, $langs->trans('TakeposRefundAccessDenied'), $context);
}
TakeposAccess::requireFeature($db, 'takepos.returns', $user, true, $context);
TakeposAccess::requireAjaxAccess($db, $user, 'takepos.refunds', 'takepos.use', (int) $terminalFromSession, $context);
$entity = !empty($user->entity) ? (int) $user->entity : 1;

try {
    if ($action === 'reasons') {
        $rows = TakeposRefundService::listReasonCodes($db, $entity);
        takeposRefundJson(array('success' => true, 'rows' => $rows));
    }

    if ($action === 'search_invoices') {
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.returns', 'takepos.use', (int) $terminalFromSession, $context);

        $filters = array(
            'invoice_id' => GETPOSTINT('invoice_id'),
            'invoice_ref' => TakeposInputValidator::normalizeUtf8Text(GETPOST('invoice_ref', 'none'), 64, true),
            'customer_id' => GETPOSTINT('customer_id'),
            'date_from' => TakeposInputValidator::normalizeUtf8Text(GETPOST('date_from', 'none'), 10, true),
            'date_to' => TakeposInputValidator::normalizeUtf8Text(GETPOST('date_to', 'none'), 10, true),
        );

        $rows = TakeposRefundService::searchOriginalInvoices($db, $user, $filters, 80);
        takeposRefundJson(array('success' => true, 'rows' => $rows));
    }

    if ($action === 'refundable_lines') {
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.returns', 'takepos.use', (int) $terminalFromSession, $context);
        $invoiceId = GETPOSTINT('invoice_id');
        $data = TakeposRefundService::listRefundableLines($db, $user, $invoiceId);
        takeposRefundJson(array('success' => true, 'data' => $data));
    }

    if ($action === 'create_refund') {
        takeposRefundRequireToken($db, $user);
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.refunds', 'takepos.use', (int) $terminalFromSession, $context);

        $refundType = GETPOST('refund_type', 'aZ09');
        $invoiceId = GETPOSTINT('invoice_id');
        $reasonCode = TakeposInputValidator::normalizeUtf8Text(GETPOST('reason_code', 'none'), 64, true);
        $note = TakeposInputValidator::normalizeUtf8Text(GETPOST('note', 'none'), 255, false);
        $paymentMethod = GETPOST('payment_method', 'aZ09');
        $adhocAmount = GETPOST('adhoc_amount', 'none');
        $restockDefault = GETPOSTINT('restock_default');
        $lines = takeposRefundParseLinesJson(GETPOST('lines_json', 'none'));
        if ($lines === null) {
            takeposRefundJson(array('success' => false, 'error' => 'invalid_lines_json', 'message' => $langs->trans('TakeposRefundInvalidLinesPayload')), 422);
        }

        $result = TakeposRefundService::createRefund($db, $user, array(
            'refund_type' => $refundType,
            'original_invoice_id' => $invoiceId,
            'reason_code' => $reasonCode,
            'note' => $note,
            'payment_method' => $paymentMethod,
            'adhoc_amount' => $adhocAmount,
            'restock_default' => $restockDefault,
            'lines' => $lines,
            'manager_barcode' => GETPOST('manager_barcode', 'none'),
            'manager_login' => GETPOST('manager_login', 'none'),
            'manager_password' => GETPOST('manager_password', 'none'),
        ));

        takeposRefundJson(array('success' => true, 'message' => $langs->trans('TakeposRefundCreatedSuccess'), 'result' => $result));
    }

    if ($action === 'list_refunds') {
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.refunds', 'takepos.use', (int) $terminalFromSession, $context);
        $filters = array(
            'invoice_id' => GETPOSTINT('invoice_id'),
            'refund_ref' => TakeposInputValidator::normalizeUtf8Text(GETPOST('refund_ref', 'none'), 64, true),
            'date_from' => TakeposInputValidator::normalizeUtf8Text(GETPOST('date_from', 'none'), 10, true),
            'date_to' => TakeposInputValidator::normalizeUtf8Text(GETPOST('date_to', 'none'), 10, true),
        );
        $rows = TakeposRefundService::listRefunds($db, $entity, $filters, 250);
        takeposRefundJson(array('success' => true, 'rows' => $rows));
    }

    if ($action === 'detail') {
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.refunds', 'takepos.use', (int) $terminalFromSession, $context);
        $refundId = GETPOSTINT('refund_id');
        $refund = TakeposRefundService::getRefundById($db, $entity, $refundId);
        if (!$refund) {
            takeposRefundJson(array('success' => false, 'error' => 'refund_not_found', 'message' => $langs->trans('TakeposRefundNotFound')), 404);
        }
        $lines = TakeposRefundService::getRefundLines($db, $entity, $refundId);
        takeposRefundJson(array('success' => true, 'refund' => $refund, 'lines' => $lines));
    }

    if ($action === 'export_csv') {
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.refunds', 'takepos.refund.export', (int) $terminalFromSession, $context);
        $rows = TakeposRefundService::listRefunds($db, $entity, array(), 1000);

        TakeposAudit::logEvent($db, $user, 'analytics_exported', TakeposAudit::SEVERITY_INFO, array('source' => 'refunds_export'), 'Refund analytics exported');

        $csv = array();
        $csv[] = $langs->trans('TakeposRefundExportCsvHeaders');
        foreach ($rows as $r) {
            $line = array(
                (string) $r->refund_ref,
                (string) $r->refund_type,
                (string) $r->original_invoice_ref,
                (string) price2num($r->total_amount, 'MU'),
                (string) $r->payment_method,
                (string) $r->reason_code,
                (string) $r->status,
                (string) $r->date_creation,
            );
            $escaped = array();
            foreach ($line as $v) {
                $escaped[] = '"' . str_replace('"', '""', (string) $v) . '"';
            }
            $csv[] = implode(',', $escaped);
        }

        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="takepos_refunds_' . date('Ymd_His') . '.csv"');
        }
        echo "\xEF\xBB\xBF";
        echo implode("\n", $csv);
        exit;
    }

    takeposRefundJson(array('success' => false, 'error' => 'unsupported_action', 'message' => $langs->trans('TakeposRefundUnsupportedAction')), 400);
} catch (Throwable $e) {
    takeposRefundJson(array('success' => false, 'error' => 'runtime_error', 'message' => $e->getMessage()), 500);
}
