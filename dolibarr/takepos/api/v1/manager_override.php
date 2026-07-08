<?php
/**
 * TakePOS API v1 — Manager Override
 *
 * POST /takepos/api/v1/manager_override.php
 *
 * Validates manager credentials and approves a privileged action
 * (price override, discount override, void line, etc.).
 * The manager must be a different user from the API token holder.
 *
 * Auth: Bearer token (standard API v1 auth)
 *
 * Body:
 *   override_action    STRING  required  e.g. price_override, discount_override, void_line
 *   invoice_id         INT     required  Invoice the override applies to
 *   manager_login      STRING  required  Manager's Dolibarr login
 *   manager_password   STRING  required  Manager's Dolibarr password
 *   line_id            INT     optional  Specific line being overridden
 *   requested_value    STRING  optional  The override value (price or % requested)
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_request.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposManagerOverrideService.class.php';

takeposApiRequireMethod(array('POST'));

$auth   = takeposApiAuth($db, 'write', 'takepos.api_layer');
$entity = (int) $auth['entity'];
$body   = takeposApiRequestBody();

$overrideAction  = trim((string) takeposApiRequestRequireField($body, 'override_action'));
$invoiceId       = (int)    takeposApiRequestRequireField($body, 'invoice_id');
$managerLogin    = trim((string) takeposApiRequestRequireField($body, 'manager_login'));
$managerPassword = (string) takeposApiRequestRequireField($body, 'manager_password');
$lineId          = !empty($body['line_id'])         ? (int)    $body['line_id']         : 0;
$requestedValue  = !empty($body['requested_value']) ? (string) $body['requested_value'] : '';

if ($overrideAction === '') {
    throw new TakeposApiException('INVALID_PARAMETER', 'override_action is required.', 422);
}
if ($invoiceId <= 0) {
    throw new TakeposApiException('INVALID_PARAMETER', 'invoice_id is required.', 422);
}
if ($managerLogin === '' || $managerPassword === '') {
    throw new TakeposApiException('INVALID_PARAMETER', 'manager_login and manager_password are required.', 422);
}

// Verify manager credentials
$managerUser = TakeposManagerOverrideService::findManagerByLogin($db, $managerLogin);
if (!$managerUser || !TakeposManagerOverrideService::validateManagerPassword($managerUser, $managerPassword)) {
    TakeposAudit::logEvent($db, $auth['user'], 'api_manager_override_rejected', TakeposAudit::SEVERITY_WARNING,
        array('override_action' => $overrideAction, 'invoice_id' => $invoiceId,
              'manager_login' => $managerLogin, 'reason' => 'invalid_credentials'),
        'API manager override rejected: invalid credentials');
    throw new TakeposApiException('INVALID_MANAGER_CREDENTIALS', 'Manager credentials are invalid or insufficient.', 403);
}

// Block self-approval
if ((int) $managerUser->id === (int) $auth['user']->id) {
    throw new TakeposApiException('SELF_APPROVAL_FORBIDDEN', 'The API token holder cannot approve their own override.', 403);
}

// Verify invoice belongs to this entity and is a TakePOS invoice
$sqlInv = 'SELECT rowid, fk_statut, module_source FROM ' . MAIN_DB_PREFIX . 'facture'
    . ' WHERE rowid = ' . (int)$invoiceId . ' AND entity = ' . $entity;
$resInv = $db->query($sqlInv);
if (!$resInv || !$db->num_rows($resInv)) {
    throw new TakeposApiException('NOT_FOUND', 'Invoice not found.', 404);
}
$inv = $db->fetch_object($resInv);
if ((string)$inv->module_source !== 'takepos') {
    throw new TakeposApiException('INVALID_INVOICE', 'Invoice is not a TakePOS invoice.', 422);
}

// Log the approved override
TakeposAudit::logEvent($db, $auth['user'], 'api_manager_override_approved', TakeposAudit::SEVERITY_INFO,
    array('override_action'  => $overrideAction,
          'invoice_id'       => $invoiceId,
          'line_id'          => $lineId,
          'requested_value'  => $requestedValue,
          'manager_id'       => (int) $managerUser->id,
          'manager_login'    => $managerLogin),
    'Manager override approved via API', 'invoice', $invoiceId);

takeposApiAuditAccess($db, $auth, 'manager_override', array(
    'override_action' => $overrideAction,
    'invoice_id'      => $invoiceId,
    'manager_login'   => $managerLogin,
));

takeposApiSuccess(array(
    'approved'        => true,
    'override_action' => $overrideAction,
    'invoice_id'      => $invoiceId,
    'line_id'         => $lineId > 0 ? $lineId : null,
    'requested_value' => $requestedValue !== '' ? $requestedValue : null,
    'manager_id'      => (int) $managerUser->id,
    'manager_login'   => $managerLogin,
), array('entity' => $entity));
