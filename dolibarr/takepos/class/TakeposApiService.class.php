<?php
require_once __DIR__ . '/TakeposMigration.class.php';
require_once __DIR__ . '/TakeposAudit.class.php';
require_once __DIR__ . '/TakeposUserAccess.class.php';

/**
 * Token and scope-based API auth for TakePOS API v1.
 */
class TakeposApiService
{
    private static function trans($key, $fallback)
    {
        global $langs;

        if (is_object($langs)) {
            $langs->load('takeposcustom@takepos');
            $translated = $langs->trans($key);
            if ($translated !== $key) {
                return $translated;
            }
        }

        return $fallback;
    }

    public static function tableApiToken()
    {
        return MAIN_DB_PREFIX . 'takepos_api_token';
    }

    public static function tableApiLoginAttempt()
    {
        return MAIN_DB_PREFIX . 'takepos_api_login_attempt';
    }

    public static function tokenTtlHours()
    {
        $hours = (int) getDolGlobalInt('TAKEPOS_API_TOKEN_TTL_HOURS');
        if ($hours <= 0) {
            $hours = 12;
        }

        return max(1, min(168, $hours));
    }

    public static function tokenExpirySql($db)
    {
        return "DATE_ADD(" . self::nowSql($db) . ", INTERVAL " . self::tokenTtlHours() . " HOUR)";
    }

    public static function isTokenExpiredValue($dateExpiration)
    {
        if (empty($dateExpiration)) {
            return false;
        }

        $ts = strtotime((string) $dateExpiration);
        if ($ts <= 0) {
            return false;
        }

        return ($ts <= dol_now());
    }

    private static function nowSql($db)
    {
        return "'" . $db->escape(dol_print_date(dol_now(), 'dayhourlog')) . "'";
    }

    private static function safeAudit($db, $user, $eventCode, $severity, $data = array(), $description = '', $objectType = '', $objectId = 0)
    {
        try {
            TakeposAudit::logEvent($db, $user, $eventCode, $severity, $data, $description, $objectType, (int) $objectId);
        } catch (Throwable $e) {
            if (function_exists('dol_syslog')) {
                dol_syslog('[TakePOS][API] Audit failure: ' . $e->getMessage(), LOG_WARNING);
            }
        }
    }

    public static function ensureSchema($db)
    {
        $table = self::tableApiToken();
        $loginTable = self::tableApiLoginAttempt();

        $ok = TakeposMigration::ensureTable($db, $table, "CREATE TABLE " . $table . " ("
            . " rowid INT AUTO_INCREMENT PRIMARY KEY,"
            . " entity INT NOT NULL DEFAULT 1,"
            . " token_label VARCHAR(128) NOT NULL,"
            . " token_hash VARCHAR(128) NOT NULL,"
            . " scope_csv VARCHAR(255) NOT NULL,"
            . " active TINYINT(1) NOT NULL DEFAULT 1,"
            . " fk_created_by INT NULL,"
            . " date_creation DATETIME NOT NULL,"
            . " date_last_use DATETIME NULL,"
            . " date_expiration DATETIME NULL,"
            . " tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
            . " UNIQUE KEY uk_takepos_api_hash (entity, token_hash),"
            . " KEY idx_takepos_api_active (entity, active),"
            . " KEY idx_takepos_api_last_use (entity, date_last_use),"
            . " KEY idx_takepos_api_expiration (entity, date_expiration)"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $ok = $ok && TakeposMigration::ensureTable($db, $loginTable, "CREATE TABLE " . $loginTable . " ("
                . " rowid INT AUTO_INCREMENT PRIMARY KEY,"
                . " entity INT NOT NULL DEFAULT 1,"
                . " login_value VARCHAR(190) NOT NULL,"
                . " ip_address VARCHAR(64) NOT NULL,"
                . " attempt_count INT NOT NULL DEFAULT 0,"
                . " window_start DATETIME NOT NULL,"
                . " locked_until DATETIME NULL,"
                . " date_last_attempt DATETIME NOT NULL,"
                . " tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
                . " UNIQUE KEY uk_takepos_api_login_attempt (entity, login_value, ip_address),"
                . " KEY idx_takepos_api_login_lock (entity, locked_until),"
                . " KEY idx_takepos_api_login_last (entity, date_last_attempt)"
                . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if (!$ok) {
            return false;
        }

        $columns = array(
            'entity' => "INT NOT NULL DEFAULT 1",
            'token_label' => "VARCHAR(128) NOT NULL",
            'token_hash' => "VARCHAR(128) NOT NULL",
            'scope_csv' => "VARCHAR(255) NOT NULL",
            'active' => "TINYINT(1) NOT NULL DEFAULT 1",
            'fk_created_by' => "INT NULL",
            'fk_terminal' => "INT NULL",
            'fk_shift' => "INT NULL",
            'date_creation' => "DATETIME NOT NULL",
            'date_last_use' => "DATETIME NULL",
            'date_expiration' => "DATETIME NULL",
            'tms' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        );
        foreach ($columns as $column => $definition) {
            if (!TakeposMigration::ensureColumn($db, $table, $column, $definition)) {
                return false;
            }
        }

        TakeposMigration::ensureIndex($db, $table, 'uk_takepos_api_hash', '(entity, token_hash)', 'UNIQUE');
        TakeposMigration::ensureIndex($db, $table, 'idx_takepos_api_active', '(entity, active)');
        TakeposMigration::ensureIndex($db, $table, 'idx_takepos_api_last_use', '(entity, date_last_use)');
        TakeposMigration::ensureIndex($db, $table, 'idx_takepos_api_expiration', '(entity, date_expiration)');

        $loginColumns = array(
            'entity' => "INT NOT NULL DEFAULT 1",
            'login_value' => "VARCHAR(190) NOT NULL",
            'ip_address' => "VARCHAR(64) NOT NULL",
            'attempt_count' => "INT NOT NULL DEFAULT 0",
            'window_start' => "DATETIME NOT NULL",
            'locked_until' => "DATETIME NULL",
            'date_last_attempt' => "DATETIME NOT NULL",
            'tms' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        );
        foreach ($loginColumns as $column => $definition) {
            if (!TakeposMigration::ensureColumn($db, $loginTable, $column, $definition)) {
                return false;
            }
        }

        TakeposMigration::ensureIndex($db, $loginTable, 'uk_takepos_api_login_attempt', '(entity, login_value, ip_address)', 'UNIQUE');
        TakeposMigration::ensureIndex($db, $loginTable, 'idx_takepos_api_login_lock', '(entity, locked_until)');
        TakeposMigration::ensureIndex($db, $loginTable, 'idx_takepos_api_login_last', '(entity, date_last_attempt)');

        return true;
    }

    public static function allowedScopes()
    {
        return array('read', 'write', '*');
    }

    public static function normalizeScopes($scopes)
    {
        if (is_string($scopes)) {
            $scopes = explode(',', $scopes);
        }

        $clean = array();
        foreach ((array) $scopes as $scope) {
            $scope = strtolower(trim((string) $scope));
            if ($scope === '') {
                continue;
            }
            if (!in_array($scope, self::allowedScopes(), true)) {
                continue;
            }
            $clean[] = $scope;
        }

        $clean = array_values(array_unique($clean));
        if (empty($clean)) {
            $clean[] = 'read';
        }

        return $clean;
    }

    public static function grantedScopesForUser($db, $user)
    {
        if (empty($user) || !is_object($user)) {
            return array();
        }

        $entity = !empty($user->entity) ? (int) $user->entity : (!empty($GLOBALS['conf']->entity) ? (int) $GLOBALS['conf']->entity : 1);
        if ($entity <= 0) {
            $entity = 1;
        }

        if (!TakeposUserAccess::moduleEnabledForEntityStrict($db, $entity, 'takepos')) {
            return array();
        }
        if (!TakeposUserAccess::featureEnabledForEntityStrict($db, $entity, 'takepos.api_layer')) {
            return array();
        }

        if (!empty($user->admin)) {
            return array('read', 'write');
        }

        $canWrite = TakeposUserAccess::userHasPermission($db, $user, 'takepos.api.write');
        $canRead = ($canWrite || TakeposUserAccess::userHasPermission($db, $user, 'takepos.api.read'));

        $scopes = array();
        if ($canRead) {
            $scopes[] = 'read';
        }
        if ($canWrite) {
            $scopes[] = 'write';
        }

        return self::normalizeScopes($scopes);
    }

    public static function userCanUseScope($db, $user, $requiredScope = 'read')
    {
        $requiredScope = strtolower(trim((string) $requiredScope));
        if ($requiredScope === '' || $requiredScope === '*') {
            $requiredScope = 'read';
        }

        $granted = self::grantedScopesForUser($db, $user);
        if (empty($granted)) {
            return false;
        }

        if ($requiredScope === 'read') {
            return in_array('read', $granted, true) || in_array('write', $granted, true) || in_array('*', $granted, true);
        }

        return in_array($requiredScope, $granted, true) || in_array('*', $granted, true);
    }

    public static function filterScopesForUser($db, $user, $requestedScopes = array())
    {
        $granted = self::grantedScopesForUser($db, $user);
        if (empty($granted)) {
            return array();
        }

        // normalizeScopes() adds a 'read' fallback for empty input, so we must
        // check the raw requested list BEFORE normalizing to detect "no preference"
        // vs "explicitly asked for read only". When nothing was requested, return
        // the full set of granted scopes so admins automatically get write.
        $rawRequested = (array) $requestedScopes;
        $rawRequested = array_filter(array_map('trim', array_map('strval', $rawRequested)));
        if (empty($rawRequested)) {
            return $granted;
        }

        $requested = self::normalizeScopes($requestedScopes);
        if (empty($requested)) {
            return $granted;
        }

        $effective = array();
        foreach ($requested as $scope) {
            if (in_array($scope, $granted, true)) {
                $effective[] = $scope;
                continue;
            }
            if ($scope === 'read' && in_array('write', $granted, true)) {
                $effective[] = 'read';
            }
        }

        if (in_array('write', $effective, true) && !in_array('read', $effective, true)) {
            $effective[] = 'read';
        }

        return self::normalizeScopes($effective);
    }

    public static function sanitizeLoginValue($login)
    {
        return substr(trim((string) $login), 0, 190);
    }

    public static function sanitizeIpAddress($ipAddress)
    {
        $ipAddress = trim((string) $ipAddress);
        return substr($ipAddress, 0, 64);
    }

    public static function assertLoginAllowed($db, $entity, $login, $ipAddress)
    {
        self::ensureSchema($db);

        $login = self::sanitizeLoginValue($login);
        $ipAddress = self::sanitizeIpAddress($ipAddress);
        if ($login === '') {
            return true;
        }

        $sql = "SELECT locked_until FROM " . self::tableApiLoginAttempt()
            . " WHERE entity = " . ((int) $entity)
            . " AND login_value = '" . $db->escape($login) . "'"
            . " AND ip_address = '" . $db->escape($ipAddress) . "'"
            . " LIMIT 1";
        $resql = $db->query($sql);
        $row = ($resql ? $db->fetch_object($resql) : null);

        if ($row && !empty($row->locked_until) && strtotime((string) $row->locked_until) > dol_now()) {
            throw new TakeposApiException('RATE_LIMITED', 'Too many failed login attempts. Please wait before retrying.', 429);
        }

        return true;
    }

    public static function recordFailedLogin($db, $entity, $login, $ipAddress)
    {
        self::ensureSchema($db);

        $entity = (int) $entity;
        $login = self::sanitizeLoginValue($login);
        $ipAddress = self::sanitizeIpAddress($ipAddress);
        if ($login === '') {
            return false;
        }

        $windowSeconds = 900;
        $maxAttempts = 5;
        $lockSeconds = 900;
        $nowTs = dol_now();
        $nowSql = dol_print_date($nowTs, 'dayhourlog');
        $windowReset = dol_print_date($nowTs - $windowSeconds, 'dayhourlog');
        $lockUntil = dol_print_date($nowTs + $lockSeconds, 'dayhourlog');

        $sql = "SELECT rowid, attempt_count, window_start, locked_until"
            . " FROM " . self::tableApiLoginAttempt()
            . " WHERE entity = " . $entity
            . " AND login_value = '" . $db->escape($login) . "'"
            . " AND ip_address = '" . $db->escape($ipAddress) . "'"
            . " LIMIT 1";
        $resql = $db->query($sql);
        $row = ($resql ? $db->fetch_object($resql) : null);

        if (!$row) {
            $insert = "INSERT INTO " . self::tableApiLoginAttempt()
                . " (entity, login_value, ip_address, attempt_count, window_start, locked_until, date_last_attempt) VALUES ("
                . $entity . ", '"
                . $db->escape($login) . "', '"
                . $db->escape($ipAddress) . "', 1, '"
                . $db->escape($nowSql) . "', NULL, '"
                . $db->escape($nowSql) . "')";
            return (bool) $db->query($insert);
        }

        $attemptCount = (int) $row->attempt_count;
        $windowStart = !empty($row->window_start) ? strtotime((string) $row->window_start) : 0;
        if ($windowStart <= 0 || $windowStart < strtotime($windowReset)) {
            $attemptCount = 0;
            $windowStart = $nowTs;
        }

        $attemptCount++;
        $lockedValue = ($attemptCount >= $maxAttempts ? "'" . $db->escape($lockUntil) . "'" : 'NULL');

        $update = "UPDATE " . self::tableApiLoginAttempt() . " SET"
            . " attempt_count = " . $attemptCount
            . ", window_start = '" . $db->escape(dol_print_date($windowStart, 'dayhourlog')) . "'"
            . ", locked_until = " . $lockedValue
            . ", date_last_attempt = '" . $db->escape($nowSql) . "'"
            . " WHERE rowid = " . ((int) $row->rowid);

        return (bool) $db->query($update);
    }

    public static function clearFailedLogins($db, $entity, $login, $ipAddress)
    {
        self::ensureSchema($db);

        $login = self::sanitizeLoginValue($login);
        $ipAddress = self::sanitizeIpAddress($ipAddress);
        if ($login === '') {
            return false;
        }

        $sql = "DELETE FROM " . self::tableApiLoginAttempt()
            . " WHERE entity = " . ((int) $entity)
            . " AND login_value = '" . $db->escape($login) . "'"
            . " AND ip_address = '" . $db->escape($ipAddress) . "'";

        return (bool) $db->query($sql);
    }

    public static function hashToken($plainToken)
    {
        return hash('sha256', (string) $plainToken);
    }

    public static function generatePlainToken($entity)
    {
        $prefix = 'tp3_' . ((int) $entity) . '_';
        return $prefix . bin2hex(random_bytes(24));
    }

    public static function createToken($db, $user, $entity, $label, $scopes)
    {
        self::ensureSchema($db);

        $entity = (int) $entity;
        if ($entity <= 0) {
            $entity = !empty($user->entity) ? (int) $user->entity : 1;
        }

        $label = trim((string) $label);
        if ($label === '') {
            throw new Exception(self::trans('TakeposApiTokenLabelRequired', 'Token label is required.'));
        }

        $scopesList = self::normalizeScopes($scopes);
        $scopeCsv = implode(',', $scopesList);

        $plainToken = self::generatePlainToken($entity);
        $hash = self::hashToken($plainToken);

        $sql = "INSERT INTO " . self::tableApiToken() . " (entity, token_label, token_hash, scope_csv, active, fk_created_by, date_creation, date_expiration) VALUES ("
            . $entity . ", '" . $db->escape($label) . "', '" . $db->escape($hash) . "', '" . $db->escape($scopeCsv) . "', 1, "
            . (!empty($user->id) ? (int) $user->id : 'NULL') . ", " . self::nowSql($db) . ", " . self::tokenExpirySql($db) . ")";
        if (!$db->query($sql)) {
            throw new Exception($db->lasterror());
        }

        $tokenId = (int) $db->last_insert_id(self::tableApiToken());

        self::safeAudit(
            $db,
            $user,
            'api_token_created',
            TakeposAudit::SEVERITY_WARNING,
            array('token_id' => $tokenId, 'token_label' => $label, 'scopes' => $scopesList),
            'API token created',
            'api_token',
            $tokenId
        );

        return array(
            'token_id' => $tokenId,
            'token_label' => $label,
            'token' => $plainToken,
            'scopes' => $scopesList,
            'date_expiration' => dol_print_date(dol_now() + (self::tokenTtlHours() * 3600), 'dayhourlog'),
        );
    }

    public static function createLoginToken($db, $user, $label = '', $requestedScopes = array())
    {
        if (empty($user) || !is_object($user) || empty($user->id)) {
            throw new Exception(self::trans('TakeposApiInvalidUser', 'Invalid API user context.'));
        }

        $effectiveScopes = self::filterScopesForUser($db, $user, $requestedScopes);
        if (empty($effectiveScopes)) {
            throw new Exception(self::trans('TakeposApiScopeDenied', 'API token scope denied for this user.'));
        }

        $label = trim((string) $label);
        if ($label === '') {
            $label = 'API Login ' . (empty($user->login) ? ((int) $user->id) : (string) $user->login) . ' ' . dol_print_date(dol_now(), '%Y%m%d-%H%M%S');
        }

        return self::createToken($db, $user, !empty($user->entity) ? (int) $user->entity : 1, $label, $effectiveScopes);
    }

    /**
     * Bind a token to a specific terminal and shift (set_terminal endpoint).
     * Adds fk_terminal / fk_shift columns on first use via ensureSchema().
     */
    public static function bindTokenTerminal($db, $entity, $tokenId, $terminalId, $shiftId = 0)
    {
        $entity     = (int) $entity;
        $tokenId    = (int) $tokenId;
        $terminalId = (int) $terminalId;
        $shiftId    = (int) $shiftId;

        if ($tokenId <= 0 || $terminalId <= 0) {
            throw new Exception('bindTokenTerminal: invalid tokenId or terminalId');
        }

        $table = self::tableApiToken();

        // Ensure the two columns exist — use direct ALTER TABLE so this works
        // even if ensureSchema() was already called earlier in the request
        // (TakeposMigration may cache its column checks per request).
        @$db->query("ALTER TABLE " . $table . " ADD COLUMN fk_terminal INT NULL");
        @$db->query("ALTER TABLE " . $table . " ADD COLUMN fk_shift INT NULL");

        $sql = "UPDATE " . $table
            . " SET fk_terminal = " . $terminalId
            . ", fk_shift = " . ($shiftId > 0 ? $shiftId : 'NULL')
            . " WHERE rowid = " . $tokenId
            . " AND entity = " . $entity;

        $res = $db->query($sql);
        if (!$res) {
            throw new Exception('bindTokenTerminal DB error: ' . $db->lasterror());
        }

        return true;
    }

    public static function listTokens($db, $entity)
    {
        self::ensureSchema($db);

        $sql = "SELECT rowid, entity, token_label, scope_csv, active, fk_created_by, date_creation, date_last_use, date_expiration"
            . " FROM " . self::tableApiToken()
            . " WHERE entity = " . ((int) $entity)
            . " ORDER BY rowid DESC";

        $rows = array();
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $rows[] = $obj;
            }
        }

        return $rows;
    }

    public static function setTokenActive($db, $user, $entity, $tokenId, $active)
    {
        self::ensureSchema($db);

        $sql = "UPDATE " . self::tableApiToken()
            . " SET active = " . ((int) $active > 0 ? 1 : 0)
            . " WHERE entity = " . ((int) $entity)
            . " AND rowid = " . ((int) $tokenId);
        if (!$db->query($sql)) {
            throw new Exception($db->lasterror());
        }

        self::safeAudit(
            $db,
            $user,
            'api_token_updated',
            TakeposAudit::SEVERITY_WARNING,
            array('token_id' => (int) $tokenId, 'active' => ((int) $active > 0 ? 1 : 0)),
            'API token status updated',
            'api_token',
            (int) $tokenId
        );

        return true;
    }

    private static function requestHeaders()
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                return $headers;
            }
        }

        $headers = array();
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    private static function extractTokenFromRequest()
    {
        $headers = self::requestHeaders();

        foreach ($headers as $name => $value) {
            if (strcasecmp((string) $name, 'X-TAKEPOS-TOKEN') === 0) {
                return trim((string) $value);
            }
            if (strcasecmp((string) $name, 'Authorization') === 0) {
                $raw = trim((string) $value);
                if (stripos($raw, 'Bearer ') === 0) {
                    return trim(substr($raw, 7));
                }
            }
        }

        // Security hardening: do not accept API tokens from query/body by default.
        // Query-string tokens are frequently leaked through access logs, browser history,
        // reverse proxies, analytics and referrers. If legacy compatibility is required,
        // it can be re-enabled explicitly with TAKEPOS_API_ALLOW_QUERY_TOKEN = 1.
        if (defined('TAKEPOS_API_ALLOW_QUERY_TOKEN') && (int) TAKEPOS_API_ALLOW_QUERY_TOKEN === 1) {
            $q = GETPOST('token', 'none');
            if ($q !== '') {
                return trim((string) $q);
            }
        }

        return '';
    }

    private static function rowByPlainToken($db, $plainToken)
    {
        $hash = self::hashToken($plainToken);
        $sql = "SELECT rowid, entity, token_label, scope_csv, active, date_expiration FROM " . self::tableApiToken()
            . " WHERE token_hash = '" . $db->escape($hash) . "'"
            . " LIMIT 1";
        $resql = $db->query($sql);
        return ($resql ? $db->fetch_object($resql) : null);
    }

    public static function authenticate($db, $requiredScope = 'read', $requiredFeature = 'takepos.api_layer')
    {
        self::ensureSchema($db);

        $token = self::extractTokenFromRequest();
        if ($token === '') {
            throw new Exception(self::trans('TakeposApiMissingToken', 'Missing API token.'));
        }

        $row = self::rowByPlainToken($db, $token);
        if (!$row || empty($row->active)) {
            throw new Exception(self::trans('TakeposApiTokenInvalidInactive', 'API token is invalid or inactive.'));
        }
        if (self::isTokenExpiredValue(isset($row->date_expiration) ? $row->date_expiration : null)) {
            throw new Exception(self::trans('TakeposApiTokenExpired', 'API token has expired.'));
        }

        $entity = (int) $row->entity;
        $scopes = self::normalizeScopes((string) $row->scope_csv);

        $requiredScope = strtolower(trim((string) $requiredScope));
        if ($requiredScope === '') {
            $requiredScope = 'read';
        }
        if (!in_array('*', $scopes, true) && !in_array($requiredScope, $scopes, true)) {
            throw new Exception(self::trans('TakeposApiScopeDenied', 'API token scope denied for this endpoint.'));
        }

        if (!TakeposUserAccess::moduleEnabledForEntityStrict($db, $entity, 'takepos')) {
            throw new Exception(self::trans('TakeposApiModuleDisabled', 'TakePOS module is disabled for this entity.'));
        }
        if ($requiredFeature !== '' && !TakeposUserAccess::featureEnabledForEntityStrict($db, $entity, $requiredFeature)) {
            throw new Exception(self::trans('TakeposApiFeatureDisabled', 'Required API feature is disabled for this entity.'));
        }

        $sql = "UPDATE " . self::tableApiToken()
            . " SET date_last_use = " . self::nowSql($db)
            . " WHERE rowid = " . ((int) $row->rowid)
            . " AND entity = " . $entity;
        $db->query($sql);

        return array(
            'token_id' => (int) $row->rowid,
            'entity' => $entity,
            'token_label' => (string) $row->token_label,
            'scopes' => $scopes,
        );
    }
}