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
    global $langs;

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
    global $langs;

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
        // create_refund MUST be called via HTTP POST.
        // Sending lines_json (JSON with {, [, " chars) as a GET query-string parameter
        // is always blocked by Dolibarr's SQL/script injection scanner with a 403 error.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            takeposRefundJson(array(
                'success' => false,
                'error'   => 'method_not_allowed',
                'message' => 'create_refund requires HTTP POST. Send all parameters (including lines_json and token) in the POST body, not the query string.',
            ), 405);
        }

        takeposRefundRequireToken($db, $user);
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.refunds', 'takepos.use', (int) $terminalFromSession, $context);

        // Support both application/x-www-form-urlencoded and application/json bodies.
        $postBody = array();
        $contentType = isset($_SERVER['CONTENT_TYPE']) ? strtolower($_SERVER['CONTENT_TYPE']) : '';
        if (strpos($contentType, 'application/json') !== false) {
            $rawBody = file_get_contents('php://input');
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $postBody = $decoded;
            }
        }

        // Read from decoded JSON body first, then fall back to $_POST.
        $getField = function ($key, $filter = 'none') use ($postBody) {
            if (array_key_exists($key, $postBody)) {
                return (string) $postBody[$key];
            }
            return GETPOST($key, $filter);
        };
        $getFieldInt = function ($key) use ($postBody) {
            if (array_key_exists($key, $postBody)) {
                return (int) $postBody[$key];
            }
            return GETPOSTINT($key);
        };

        $refundType     = $getField('refund_type', 'aZ09');
        $invoiceId      = $getFieldInt('invoice_id');
        $reasonCode     = TakeposInputValidator::normalizeUtf8Text($getField('reason_code'), 64, true);
        $note           = TakeposInputValidator::normalizeUtf8Text($getField('note'), 255, false);
        $paymentMethod  = $getField('payment_method', 'aZ09');
        $adhocAmount    = $getField('adhoc_amount');
        $restockDefault = $getFieldInt('restock_default');

        // lines_json: if body was JSON and lines_json is already an array, re-encode it;
        // otherwise read the raw string from the POST field.
        if (array_key_exists('lines_json', $postBody) && is_array($postBody['lines_json'])) {
            $linesRaw = json_encode($postBody['lines_json']);
        } else {
            $linesRaw = $getField('lines_json');
        }
        $lines = takeposRefundParseLinesJson($linesRaw);
        if ($lines === null) {
            takeposRefundJson(array('success' => false, 'error' => 'invalid_lines_json', 'message' => $langs->trans('TakeposRefundInvalidLinesPayload')), 422);
        }

        $result = TakeposRefundService::createRefund($db, $user, array(
            'refund_type'         => $refundType,
            'original_invoice_id' => $invoiceId,
            'reason_code'         => $reasonCode,
            'note'                => $note,
            'payment_method'      => $paymentMethod,
            'adhoc_amount'        => $adhocAmount,
            'restock_default'     => $restockDefault,
            'lines'               => $lines,
            'manager_barcode'     => $getField('manager_barcode'),
            'manager_login'       => $getField('manager_login'),
            'manager_password'    => $getField('manager_password'),
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
    // DEBUG: include file + line + a short trace so the exact crash location is
    // visible in the AJAX response. Safe to leave in; it only appears on errors.
    takeposRefundJson(array(
        'success' => false,
        'error'   => 'runtime_error',
        'message' => $e->getMessage(),
        'where'   => basename($e->getFile()) . ':' . $e->getLine(),
        'trace'   => array_slice(explode("\n", $e->getTraceAsString()), 0, 6),
    ), 500);
}