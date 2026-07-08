<?php
if (!defined('TAKEPOS_API_V1_AUTH_INCLUDED')) {
    define('TAKEPOS_API_V1_AUTH_INCLUDED', 1);

    require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';

    function takeposApiRequestHeaders()
    {
        $headers = array();
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                return $headers;
            }
        }

        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (is_array($headers) and $headers) {
                return $headers;
            }
        }

        foreach ($_SERVER as $key => $value) {
            if (strpos((string) $key, 'HTTP_') === 0) {
                $name = str_replace('_', '-', substr((string) $key, 5));
                $headers[$name] = $value;
            }
        }

        if (empty($headers['Authorization']) and empty($headers['AUTHORIZATION']) and empty($_SERVER['HTTP_AUTHORIZATION']) === false) {
            $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
        }
        if (empty($headers['Authorization']) and empty($headers['AUTHORIZATION']) and empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) === false) {
            $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        return $headers;
    }

    function takeposApiExtractBearerToken()
    {
        $header = '';
        foreach (takeposApiRequestHeaders() as $name => $value) {
            if (strcasecmp((string) $name, 'Authorization') === 0) {
                $header = trim((string) $value);
                break;
            }
        }

        if ($header === '') {
            throw new TakeposApiException('TOKEN_MISSING', 'Authorization Bearer token is required', 401);
        }
        if (preg_match('/Bearer\s+(.+)$/i', $header, $matches)) {
            $token = trim((string) $matches[1]);
            if ($token !== '') {
                return $token;
            }
        }

        throw new TakeposApiException('AUTH_FAILED', 'Unauthorized', 401);
    }

    function takeposApiLoadContextUser($db, $userId)
    {
        $userId = (int) $userId;
        if ($userId === 0) {
            return null;
        }

        $apiUser = new User($db);
        $result = $apiUser->fetch($userId);
        if ((int) $result === 0 or (int) $result === -1) {
            throw new TakeposApiException('AUTH_FAILED', 'Unauthorized', 401);
        }
        if (method_exists($apiUser, 'getrights')) {
            $apiUser->getrights();
        }

        return $apiUser;
    }

    function takeposApiBuildSyntheticUser($entity, $tokenLabel)
    {
        $apiUser = new stdClass();
        $apiUser->id = 0;
        $apiUser->entity = (int) $entity;
        $apiUser->login = 'api:' . ($tokenLabel === '' ? 'unknown' : $tokenLabel);
        $apiUser->admin = 0;
        $apiUser->socid = 0;
        return $apiUser;
    }

    function takeposApiApplyUserContext($apiUser, $entity)
    {
        global $user, $conf;

        $user = $apiUser;
        if (is_object($user)) {
            $user->entity = (empty($user->entity) ? (int) $entity : (int) $user->entity);
        }
        if (is_object($conf)) {
            $conf->entity = (int) $entity;
        }
    }

    function takeposApiAuth($db, $requiredScope = 'read', $requiredFeature = 'takepos.api_layer')
    {
        $requiredScope = strtolower(trim((string) $requiredScope));
        if ($requiredScope === '') {
            $requiredScope = 'read';
        }

        TakeposApiService::ensureSchema($db);
        $token = takeposApiExtractBearerToken();
        $hash = TakeposApiService::hashToken($token);

        $sql = 'SELECT rowid, entity, token_label, scope_csv, active, fk_created_by, fk_terminal, fk_shift, date_expiration FROM ' . TakeposApiService::tableApiToken() . ' WHERE token_hash = ' . chr(39) . $db->escape($hash) . chr(39) . ' LIMIT 1';
        $resql = $db->query($sql);
        if ($resql === false) {
            throw new TakeposApiException('INTERNAL_ERROR', 'Failed to validate API token.', 500);
        }

        $row = $db->fetch_object($resql);
        if ($row === null or $row === false) {
            throw new TakeposApiException('AUTH_FAILED', 'Unauthorized', 401);
        }
        if (empty($row->active)) {
            throw new TakeposApiException('TOKEN_DISABLED', 'Token is disabled.', 401);
        }
        if (TakeposApiService::isTokenExpiredValue(isset($row->date_expiration) ? $row->date_expiration : null)) {
            throw new TakeposApiException('TOKEN_EXPIRED', 'Token expired', 401);
        }

        $entity = (int) $row->entity;
        $scopes = TakeposApiService::normalizeScopes((string) $row->scope_csv);
        if (in_array('*', $scopes, true) === false and in_array($requiredScope, $scopes, true) === false) {
            throw new TakeposApiException('FORBIDDEN', 'Insufficient token scope.', 403);
        }

        if (TakeposUserAccess::moduleEnabledForEntityStrict($db, $entity, 'takepos') === false) {
            throw new TakeposApiException('FORBIDDEN', 'TakePOS module is disabled for this entity.', 403);
        }
        if ($requiredFeature !== '' and TakeposUserAccess::featureEnabledForEntityStrict($db, $entity, $requiredFeature) === false) {
            throw new TakeposApiException('FORBIDDEN', 'Required API feature is disabled for this entity.', 403);
        }

        $apiUser = takeposApiLoadContextUser($db, (int) $row->fk_created_by);
        if ($apiUser === null) {
            $apiUser = takeposApiBuildSyntheticUser($entity, (string) $row->token_label);
        }
        if (!empty($apiUser->id) && !TakeposApiService::userCanUseScope($db, $apiUser, $requiredScope)) {
            throw new TakeposApiException('FORBIDDEN', 'User permission does not allow this API scope.', 403);
        }
        takeposApiApplyUserContext($apiUser, $entity);

        $updateSql = 'UPDATE ' . TakeposApiService::tableApiToken() . ' SET date_last_use = ' . chr(39) . $db->escape(dol_print_date(dol_now(), 'dayhourlog')) . chr(39) . ' WHERE rowid = ' . ((int) $row->rowid) . ' AND entity = ' . $entity;
        $db->query($updateSql);

        $authResult = array(
            'entity' => $entity,
            'user' => $apiUser,
            'token' => array(
                'id' => (int) $row->rowid,
                'label' => (string) $row->token_label,
                'active' => (int) $row->active,
                'user_id' => (int) $row->fk_created_by,
                'scopes' => $scopes,
                'terminal_id' => (!empty($row->fk_terminal) ? (int) $row->fk_terminal : 0),
                'shift_id'    => (!empty($row->fk_shift)    ? (int) $row->fk_shift    : 0),
            ),
            'permissions' => array(
                'read' => (in_array('*', $scopes, true) or in_array('read', $scopes, true)),
                'write' => (in_array('*', $scopes, true) or in_array('write', $scopes, true))
            ),
            'scopes' => $scopes
        );
        // Make auth available globally so _context.php can read the bound terminal
        $GLOBALS['_takepos_auth'] = $authResult;

        // ── Auto role-based endpoint check ───────────────────────────────────
        // Runs for every authenticated API call automatically.
        // Admin users and users without a role assigned are not affected.
        if (!empty($apiUser->id) && empty($apiUser->admin)) {
            $kafoEndpoint = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
            takeposApiCheckRolePermission($db, $apiUser, $kafoEndpoint);
        }
        // ─────────────────────────────────────────────────────────────────────

        return $authResult;
    }

    /**
     * Map API endpoint filenames to role UI permission codes.
     */
    function takeposApiGetRequiredRolePermission($endpoint) {
        $map = array(
            // Products
            'products.php'          => 'ui.shortcut.manage_products',
            'product_variants.php'  => 'ui.shortcut.piece_box',
            'categories.php'        => null, // always allowed
            'category_products.php' => null,
            // Stock
            'stock_check.php'       => 'ui.shortcut.stock_overview',
            'stock_add.php'         => 'ui.shortcut.stock_adjustments',
            'stock_badges.php'      => 'ui.shortcut.stock_overview',
            // Cart / Sales
            'carts.php'             => null, // always allowed
            'cart_items.php'        => null,
            'cart_debug.php'        => null,
            'checkout.php'          => null,
            'checkout_pay.php'      => null,
            // Invoices
            'invoices.php'          => 'ui.action.history',
            'invoices_validate.php' => null,
            'payments.php'          => null,
            // Shifts / Cash
            'shifts.php'            => 'ui.action.shift_desk',
            'shift_operations.php'  => 'ui.action.shift_desk',
            'cash_movements.php'    => 'ui.action.paid_in',
            // Refunds / Exchange
            'refunds.php'           => 'ui.shortcut.refund_desk',
            'exchanges.php'         => 'ui.shortcut.exchange_desk',
            // Reports
            'reports.php'           => 'ui.action.reports',
            'dashboard.php'         => 'ui.shortcut.kpi_dashboard',
            // Expenses / Purchases
            'expenses.php'          => 'ui.shortcut.expenses',
            'expense_categories.php'=> 'ui.shortcut.expense_ledger',
            'purchases.php'         => 'ui.shortcut.purchases',
            // Cheques
            'cheques.php'           => 'ui.shortcut.cheques',
            // Loyalty
            'loyalty.php'           => 'ui.shortcut.loyalty_desk',
            // Held Sales
            'held_sales.php'        => 'ui.action.held',
            // Others - always allowed
            'ping.php'              => null,
            'auth_login.php'        => null,
            'set_terminal.php'      => null,
            'terminals.php'         => null,
            'stores.php'            => null,
            'branches.php'          => null,
            'offline.php'           => null,
            'sync_queue.php'        => null,
            'manager_override.php'  => null,
            'ctx_check.php'         => null,
            'printers.php'          => null,
            'devices.php'           => null,
            'users.php'             => null,
        );
        $base = basename((string) $endpoint);
        return array_key_exists($base, $map) ? $map[$base] : null;
    }

    /**
     * Check if the authenticated user's role allows access to this endpoint.
     * Returns true if allowed, throws TakeposApiException if denied.
     */
    function takeposApiCheckRolePermission($db, $apiUser, $endpoint) {
        // Admins bypass role checks
        if (!empty($apiUser->admin)) {
            return true;
        }

        $requiredPerm = takeposApiGetRequiredRolePermission($endpoint);
        if ($requiredPerm === null) {
            return true; // no restriction for this endpoint
        }

        $entity = !empty($apiUser->entity) ? (int) $apiUser->entity : 1;
        $userId = !empty($apiUser->id) ? (int) $apiUser->id : 0;
        if ($userId <= 0) {
            return true; // synthetic/token user — allow
        }

        $rolePermTable = MAIN_DB_PREFIX . 'takepos_role_permissions';
        $tableCheck = $db->query("SHOW TABLES LIKE '" . $db->escape($rolePermTable) . "'");
        if (!$tableCheck || $db->num_rows($tableCheck) === 0) {
            return true; // table doesn't exist — allow
        }

        // Get user's assigned role
        $roleRes = $db->query(
            "SELECT permission_code FROM " . $rolePermTable
            . " WHERE entity = " . $entity
            . " AND role_code = '__user_" . $userId . "' LIMIT 1"
        );
        if (!$roleRes || !($roleObj = $db->fetch_object($roleRes))) {
            return true; // no role assigned — allow (default open)
        }
        $userRoleCode = $roleObj->permission_code;

        // Check if role has the required permission
        $permRes = $db->query(
            "SELECT COUNT(*) AS cnt FROM " . $rolePermTable
            . " WHERE entity = " . $entity
            . " AND role_code = '" . $db->escape($userRoleCode) . "'"
            . " AND permission_code = '" . $db->escape($requiredPerm) . "'"
        );
        if ($permRes && ($permObj = $db->fetch_object($permRes)) && (int)$permObj->cnt > 0) {
            return true;
        }

        throw new TakeposApiException('FORBIDDEN', 'Your role does not have permission to access this endpoint.', 403);
    }

}