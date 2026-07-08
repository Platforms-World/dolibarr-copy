<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_request.php';
require_once __DIR__ . '/_context.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposManagerOverrideService.class.php';

takeposApiRequireMethod(array('POST'));

if (!function_exists('takeposApiLoginSafeAudit')) {
    function takeposApiLoginSafeAudit($db, $user, $eventCode, $severity, $data, $description, $objectType = '', $objectId = 0)
    {
        try {
            TakeposAudit::logEvent($db, $user, $eventCode, $severity, (array) $data, (string) $description, (string) $objectType, (int) $objectId);
        } catch (Throwable $e) {
            if (function_exists('dol_syslog')) {
                dol_syslog('[TakePOS][API Login] Audit failure: ' . $e->getMessage(), LOG_WARNING);
            }
        }
    }
}

if (!function_exists('takeposApiLoginClientIp')) {
    function takeposApiLoginClientIp()
    {
        $candidates = array(
            (isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : ''),
            (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? trim((string) strtok((string) $_SERVER['HTTP_X_FORWARDED_FOR'], ',')) : ''),
            (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''),
        );

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return 'unknown';
    }
}

$body = takeposApiRequestBody();
$login = trim((string) takeposApiRequestRequireField($body, 'login'));
$password = (string) takeposApiRequestRequireField($body, 'password');
$label = (!empty($body['label']) ? trim((string) $body['label']) : '');
$requestedScopes = array();

if (isset($body['scopes'])) {
    $requestedScopes = $body['scopes'];
} elseif (!empty($body['scope'])) {
    $requestedScopes = array($body['scope']);
}

if ($login === '' || $password === '') {
    throw new TakeposApiException('INVALID_PARAMETER', 'login and password are required.', 422);
}

$loginEntity = !empty($conf->entity) ? (int) $conf->entity : 1;
$clientIp = takeposApiLoginClientIp();
TakeposApiService::assertLoginAllowed($db, $loginEntity, $login, $clientIp);

$apiUser = TakeposManagerOverrideService::findManagerByLogin($db, $login);
if (!$apiUser || !TakeposManagerOverrideService::validateManagerPassword($apiUser, $password)) {
    TakeposApiService::recordFailedLogin($db, $loginEntity, $login, $clientIp);
    $auditUser = new stdClass();
    $auditUser->id = 0;
    $auditUser->entity = $loginEntity;
    $auditUser->login = 'api-login:' . $login;
    takeposApiLoginSafeAudit($db, $auditUser, 'api_login_failed', TakeposAudit::SEVERITY_WARNING, array('login' => $login), 'API login failed', 'api');
    throw new TakeposApiException('AUTH_FAILED', 'Invalid login or password.', 401);
}

// Preserve the admin flag across getrights(): on some Dolibarr versions
// getrights() for a SuperAdmin (entity=0) resets the admin property to 0
// as a side-effect, which breaks the admin shortcut in grantedScopesForUser()
// and causes write scope to be silently dropped.
$_wasAdmin = !empty($apiUser->admin) ? (int) $apiUser->admin : 0;
if (method_exists($apiUser, 'getrights')) {
    $apiUser->getrights();
}
if ($_wasAdmin && empty($apiUser->admin)) {
    $apiUser->admin = $_wasAdmin;
}

TakeposApiService::clearFailedLogins($db, (int) $apiUser->entity, $login, $clientIp);

$grantedScopes = TakeposApiService::grantedScopesForUser($db, $apiUser);
if (empty($grantedScopes)) {
    takeposApiLoginSafeAudit($db, $apiUser, 'api_login_denied', TakeposAudit::SEVERITY_WARNING, array('login' => $login, 'reason' => 'api_scope_denied'), 'API login denied', 'api');
    throw new TakeposApiException('FORBIDDEN', 'This user is not allowed to use the TakePOS API.', 403);
}

$created = TakeposApiService::createLoginToken($db, $apiUser, $label, $requestedScopes);
$effectiveScopes = TakeposApiService::filterScopesForUser($db, $apiUser, $requestedScopes);

// Effective entity the token actually lives under. createToken() treats a
// non-positive entity (e.g. a SuperAdmin whose user->entity is 0) as invalid
// and falls back to 1, so we mirror that here. Using the raw user->entity (0)
// would scope the context queries to a non-existent company and return nothing.
$tokenEntity = !empty($apiUser->entity) ? (int) $apiUser->entity : 1;

// Check if this user had a previously bound terminal from an earlier set_terminal call.
// If yes, carry it forward to the new token so current_terminal_id stays consistent.
$previousBoundTerminalId = 0;

// Priority 1: use the terminal of the user's active shift (if any).
// This ensures the API context always reflects the terminal the user is
// currently working on, even if they switched terminals since their last login.
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposShiftService.class.php';
$_activeShiftForBind = TakeposShiftService::getActiveShiftForCashier($db, $tokenEntity, (int) $apiUser->id);
if ($_activeShiftForBind && !empty($_activeShiftForBind->fk_terminal)) {
    $previousBoundTerminalId = (int) $_activeShiftForBind->fk_terminal;
}

// Priority 2: fall back to the terminal from the user's most recent active token.
if ($previousBoundTerminalId <= 0) {
    $prevSql = 'SELECT fk_terminal FROM ' . TakeposApiService::tableApiToken()
        . ' WHERE fk_created_by = ' . ((int) $apiUser->id)
        . ' AND entity = ' . $tokenEntity
        . ' AND active = 1'
        . ' AND fk_terminal IS NOT NULL AND fk_terminal > 0'
        . ' AND rowid != ' . ((int) $created['token_id'])
        . ' ORDER BY rowid DESC LIMIT 1';
    $prevRes = $db->query($prevSql);
    if ($prevRes && ($prevRow = $db->fetch_object($prevRes)) && !empty($prevRow->fk_terminal)) {
        $previousBoundTerminalId = (int) $prevRow->fk_terminal;
    }
}

// Bind the new token to the resolved terminal
if ($previousBoundTerminalId > 0) {
    TakeposApiService::bindTokenTerminal($db, $tokenEntity, (int) $created['token_id'], $previousBoundTerminalId, 0);
}

takeposApiLoginSafeAudit($db, $apiUser, 'api_login_token_issued', TakeposAudit::SEVERITY_INFO, array(
    'token_id' => (int) $created['token_id'],
    'token_label' => (string) $created['token_label'],
    'scopes' => $effectiveScopes,
), 'API login token issued', 'api_token', (int) $created['token_id']);

// Build the POS context (stores / terminals / warehouses) for this user so the
// client can populate terminal/store selectors right after login. Failure here
// must never block issuing the token, so it is isolated in a try/catch.
$context = null;
try {
    $context = takeposApiBuildPosContext($db, $tokenEntity, $apiUser, $previousBoundTerminalId);
} catch (Throwable $e) {
    takeposApiLogError('auth_login context build failed: ' . $e->getMessage(), LOG_WARNING);
    $context = array(
        'store_restriction' => 0,
        'default_store_id' => null,
        'default_terminal_id' => null,
        'current_terminal_id' => null,
        'stores' => array(),
        'terminals' => array(),
        'warehouses' => array(),
        'user_warehouses' => array(),
        'active_shift' => null,
        'open_invoices' => array(),
        'open_invoices_count' => 0,
    );
}

// ── Load user role permissions for mobile UI ─────────────────────────────────
$userRolePermissions = array();
$userRoleCode = null;
try {
    $kafoRoleTable = MAIN_DB_PREFIX . 'takepos_role_permissions';
    $kafoTableCheck = $db->query("SHOW TABLES LIKE '" . $db->escape($kafoRoleTable) . "'");
    if ($kafoTableCheck && $db->num_rows($kafoTableCheck) > 0) {
        // Get user's assigned role
        $kafoRoleRes = $db->query(
            "SELECT permission_code FROM " . $kafoRoleTable
            . " WHERE entity = " . $tokenEntity
            . " AND role_code = '__user_" . (int)$apiUser->id . "' LIMIT 1"
        );
        if ($kafoRoleRes && ($kafoRoleObj = $db->fetch_object($kafoRoleRes))) {
            $userRoleCode = $kafoRoleObj->permission_code;

            // Load all permissions for this role
            $kafoPermsRes = $db->query(
                "SELECT permission_code FROM " . $kafoRoleTable
                . " WHERE entity = " . $tokenEntity
                . " AND role_code = '" . $db->escape($userRoleCode) . "'"
            );
            if ($kafoPermsRes) {
                while ($kafoPermObj = $db->fetch_object($kafoPermsRes)) {
                    $userRolePermissions[] = $kafoPermObj->permission_code;
                }
            }
        }
    }
} catch (Throwable $e) {
    // non-blocking
}
// ── End role permissions ──────────────────────────────────────────────────────

takeposApiSuccess(array(
    'token' => (string) $created['token'],
    'token_id' => (int) $created['token_id'],
    'token_label' => (string) $created['token_label'],
    'scopes' => array_values($effectiveScopes),
    'user' => array(
        'id' => (int) $apiUser->id,
        'login' => (string) $apiUser->login,
        'firstname' => (isset($apiUser->firstname) ? (string) $apiUser->firstname : ''),
        'lastname' => (isset($apiUser->lastname) ? (string) $apiUser->lastname : ''),
        'entity' => (int) $apiUser->entity,
        'admin' => !empty($apiUser->admin) ? 1 : 0,
    ),
    'permissions' => array(
        'read' => in_array('read', $effectiveScopes, true),
        'write' => in_array('write', $effectiveScopes, true),
    ),
    'context' => $context,
    'role' => array(
        'role_code' => $userRoleCode,
        'permissions' => $userRolePermissions,
    ),
), array('entity' => $tokenEntity), 201);