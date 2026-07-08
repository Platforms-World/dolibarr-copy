<?php
/**
 * Loyalty / CRM AJAX controller.
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
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposUtf8.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposCustomerService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposLoyaltyService.class.php';

TakeposUtf8::bootstrapConnection($db);
$langs->loadLangs(array('main', 'cashdesk', 'takeposcustom@takepos'));

function takeposLoyaltyJson($payload, $httpCode = 200)
{
    if (!headers_sent()) {
        http_response_code((int) $httpCode);
    }
    top_httphead('application/json');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function takeposLoyaltyRequireToken($db, $user)
{
    $token = TakeposInputValidator::normalizeUtf8Text(GETPOST('token', 'none'), 128, true);
    $sessionToken = isset($_SESSION['newtoken']) ? (string) $_SESSION['newtoken'] : '';
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        TakeposAccess::denyJson($db, $user, $langs->trans('TakeposCommonInvalidCsrfToken'), array('endpoint' => 'ajax/loyalty.php', 'action' => GETPOST('action', 'aZ09')));
    }
}

$action = GETPOST('action', 'aZ09');
if ($action === '') {
    takeposLoyaltyJson(array('success' => false, 'error' => 'missing_action', 'message' => $langs->trans('TakeposLoyaltyMissingAction')), 400);
}

$terminal = isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null;
$entity = !empty($user->entity) ? (int) $user->entity : 1;
$ctx = array('endpoint' => 'ajax/loyalty.php', 'requested_action' => $action);

try {
    if ($action === 'lookup') {
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.crm', 'takepos.customer.view', $terminal, $ctx);
        $q = TakeposInputValidator::normalizeUtf8Text(GETPOST('q', 'none'), 190, true);
        $rows = TakeposCustomerService::searchCustomers($db, $entity, $q, 50);
        TakeposAudit::logEvent($db, $user, 'customer_lookup_opened', TakeposAudit::SEVERITY_INFO, array('query' => (string) $q), 'Customer lookup opened');
        takeposLoyaltyJson(array('success' => true, 'rows' => $rows));
    }

    if ($action === 'customer_summary') {
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.crm', 'takepos.customer.view', $terminal, $ctx);
        $customerId = GETPOSTINT('customer_id');
        $summary = TakeposCustomerService::customerSummary($db, $entity, $customerId);
        if (!$summary) {
            takeposLoyaltyJson(array('success' => false, 'error' => 'customer_not_found', 'message' => $langs->trans('TakeposLoyaltyCustomerNotFound')), 404);
        }
        takeposLoyaltyJson(array('success' => true, 'summary' => $summary));
    }

    if ($action === 'history') {
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.crm', 'takepos.customer.view', $terminal, $ctx);
        $customerId = GETPOSTINT('customer_id');
        $tickets = TakeposCustomerService::recentTickets($db, $entity, $customerId, 40);
        $txns = TakeposLoyaltyService::listTransactions($db, $entity, $customerId, 80);
        takeposLoyaltyJson(array('success' => true, 'tickets' => $tickets, 'transactions' => $txns));
    }

    if ($action === 'settings') {
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.loyalty', 'takepos.loyalty.view', $terminal, $ctx);
        takeposLoyaltyJson(array('success' => true, 'settings' => TakeposLoyaltyService::settings()));
    }

    if ($action === 'save_settings') {
        takeposLoyaltyRequireToken($db, $user);
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.loyalty', 'takepos.loyalty.adjust', $terminal, $ctx);
        if (empty($user->admin)) {
            takeposLoyaltyJson(array('success' => false, 'error' => 'admin_required', 'message' => $langs->trans('TakeposLoyaltyAdminRequired')), 403);
        }

        TakeposLoyaltyService::saveSettings(
            $db,
            $user,
            GETPOST('points_per_currency', 'none'),
            GETPOST('redeem_points_per_currency', 'none')
        );
        takeposLoyaltyJson(array('success' => true, 'message' => $langs->trans('TakeposLoyaltySettingsSavedMessage'), 'settings' => TakeposLoyaltyService::settings()));
    }

    if ($action === 'redeem_invoice') {
        takeposLoyaltyRequireToken($db, $user);
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.loyalty', 'takepos.loyalty.redeem', $terminal, $ctx);
        $invoiceId = GETPOSTINT('invoice_id');
        $points = GETPOST('points', 'none');
        $note = GETPOST('note', 'restricthtml');
        $result = TakeposLoyaltyService::redeemOnInvoice($db, $user, $invoiceId, $points, $note);
        takeposLoyaltyJson(array('success' => true, 'result' => $result));
    }

    if ($action === 'adjust_points') {
        takeposLoyaltyRequireToken($db, $user);
        TakeposAccess::requireAjaxAccess($db, $user, 'takepos.loyalty', 'takepos.loyalty.adjust', $terminal, $ctx);
        $customerId = GETPOSTINT('customer_id');
        $delta = GETPOST('points_delta', 'none');
        $note = GETPOST('note', 'restricthtml');
        $ok = TakeposLoyaltyService::adjustPoints($db, $user, $customerId, $delta, $note);
        takeposLoyaltyJson(array('success' => (bool) $ok));
    }

    takeposLoyaltyJson(array('success' => false, 'error' => 'unsupported_action', 'message' => $langs->trans('TakeposLoyaltyUnsupportedAction')), 400);
} catch (Throwable $e) {
    takeposLoyaltyJson(array('success' => false, 'error' => 'runtime_error', 'message' => $e->getMessage()), 500);
}
