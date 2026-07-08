<?php
/**
 * TakePOS access helper.
 *
 * Enforces the current tenant feature model from kafoerpcontrol when it is
 * active, while keeping Dolibarr native rights as the standalone fallback.
 */
class TakeposAccess
{
    protected static function ensureSupportClassesLoaded()
    {
        $supportFiles = array(
            __DIR__ . '/TakeposAudit.class.php',
            __DIR__ . '/TakeposUserAccess.class.php',
            __DIR__ . '/TakeposUtf8.class.php',
        );

        foreach ($supportFiles as $file) {
            if (is_file($file)) {
                require_once $file;
            }
        }
    }

    protected static function normalizeFeature($featureCode)
    {
        return trim((string) $featureCode);
    }

    protected static function baseContext($user, $featureCode, $permissionCode, $terminal)
    {
        return array(
            'feature' => self::normalizeFeature($featureCode),
            'permission' => trim((string) $permissionCode),
            'terminal' => ((int) $terminal > 0 ? (int) $terminal : null),
            'user_id' => (!empty($user->id) ? (int) $user->id : 0),
        );
    }

    protected static function enrichContext($base, $extra)
    {
        $base = is_array($base) ? $base : array();
        $extra = is_array($extra) ? $extra : array();

        foreach ($extra as $key => $value) {
            $base[$key] = $value;
        }

        return $base;
    }

    protected static function logDenied($db, $user, $reason, $context = array())
    {
        self::ensureSupportClassesLoaded();

        if (class_exists('TakeposAudit') && is_object($db)) {
            try {
                TakeposAudit::logEvent(
                    $db,
                    $user,
                    'access_denied',
                    TakeposAudit::SEVERITY_WARNING,
                    is_array($context) ? $context : array(),
                    (string) $reason
                );
            } catch (Throwable $e) {
                if (function_exists('dol_syslog')) {
                    dol_syslog('[TakePOS][Access] Audit failure: ' . $e->getMessage(), LOG_WARNING);
                }
            }
        }
    }

    protected static function bootstrap($db)
    {
        self::ensureSupportClassesLoaded();

        if (class_exists('TakeposUtf8') && is_object($db)) {
            TakeposUtf8::bootstrapConnection($db);
        }
    }

    protected static function resolvePermissionCode($permissionCode, $default)
    {
        $permissionCode = trim((string) $permissionCode);
        if ($permissionCode === '') {
            $permissionCode = trim((string) $default);
        }

        return $permissionCode;
    }

    protected static function currentEntity($user = null)
    {
        global $conf;

        if (is_object($user) && !empty($user->entity)) {
            return (int) $user->entity;
        }

        return !empty($conf->entity) ? (int) $conf->entity : 1;
    }

    protected static function moduleEnabled($db, $user = null)
    {
        self::ensureSupportClassesLoaded();

        if (!class_exists('TakeposUserAccess')) {
            return true;
        }

        return TakeposUserAccess::moduleEnabledForEntity($db, self::currentEntity($user), 'takepos');
    }

    protected static function featureEnabled($db, $featureCode, $user = null)
    {
        $featureCode = trim((string) $featureCode);
        if ($featureCode === '') {
            return true;
        }

        self::ensureSupportClassesLoaded();

        if (!class_exists('TakeposUserAccess')) {
            return true;
        }

        return TakeposUserAccess::featureEnabledForEntity($db, self::currentEntity($user), $featureCode);
    }

    protected static function requireEnabledFeature($db, $user, $featureCode, $message, $context = array(), $ajax = false)
    {
        self::bootstrap($db);

        if (!self::moduleEnabled($db, $user)) {
            if ($ajax) {
                self::denyJson($db, $user, $message, $context);
            }
            self::denyAccess($db, $user, $message, $context);
        }

        if (!self::featureEnabled($db, $featureCode, $user)) {
            if ($ajax) {
                self::denyJson($db, $user, $message, $context);
            }
            self::denyAccess($db, $user, $message, $context);
        }

        return true;
    }

    protected static function hasPermission($db, $user, $permissionCode)
    {
        if (empty($user) || !is_object($user) || empty($user->id)) {
            return false;
        }

        if (!empty($user->admin)) {
            return true;
        }

        $permissionCode = trim((string) $permissionCode);
        if ($permissionCode === '') {
            return !empty($user->rights->takepos->run);
        }

        self::ensureSupportClassesLoaded();

        if (class_exists('TakeposUserAccess')) {
            return TakeposUserAccess::userHasPermission($db, $user, $permissionCode);
        }

        if ($permissionCode === 'takepos.use') {
            return !empty($user->rights->takepos->run);
        }
        if ($permissionCode === 'takepos.admin') {
            return !empty($user->rights->takepos->config);
        }

        return false;
    }

    protected static function requireUser($db, $user, $featureCode, $permissionCode, $message, $context = array(), $ajax = false)
    {
        self::requireEnabledFeature($db, $user, $featureCode, $message, $context, $ajax);

        if (!self::hasPermission($db, $user, $permissionCode)) {
            if ($ajax) {
                self::denyJson($db, $user, $message, $context);
            }
            self::denyAccess($db, $user, $message, $context);
        }

        return true;
    }

    public static function denyAccess($db, $user, $reason, $context = array(), $httpCode = 403)
    {
        $reason = trim((string) $reason);
        if ($reason === '') {
            $reason = 'Access denied';
        }

        self::logDenied($db, $user, $reason, $context);

        if (!headers_sent()) {
            http_response_code((int) $httpCode);
        }

        accessforbidden($reason);
    }

    public static function denyJson($db, $user, $reason, $context = array(), $httpCode = 403)
    {
        $reason = trim((string) $reason);
        if ($reason === '') {
            $reason = 'Access denied';
        }

        self::logDenied($db, $user, $reason, $context);

        if (!headers_sent()) {
            http_response_code((int) $httpCode);
            header('Content-Type: application/json; charset=UTF-8');
        }

        echo json_encode(
            array(
                'success' => false,
                'message' => $reason,
                'error' => 'access_denied',
                'context' => (is_array($context) ? $context : array()),
            ),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }

    public static function requirePublicFeature($db, $featureCode = null, $message = null, $context = array())
    {
        $message = trim((string) $message) !== '' ? trim((string) $message) : 'Access denied';
        return self::requireEnabledFeature($db, null, $featureCode, $message, $context, false);
    }

    public static function requireFrontendAccess($db, $user, $featureCode = null, $permissionCode = 'takepos.use', $terminal = null, $message = null, $context = array())
    {
        $permissionCode = self::resolvePermissionCode($permissionCode, 'takepos.use');
        $base = self::baseContext($user, $featureCode, $permissionCode, $terminal);
        $context = self::enrichContext($base, $context);
        $message = trim((string) $message) !== '' ? trim((string) $message) : 'Access denied';

        return self::requireUser($db, $user, $featureCode, $permissionCode, $message, $context, false);
    }

    public static function requireAdminAccess($db, $user, $featureCode = null, $permissionCode = 'takepos.admin', $terminal = null, $message = null, $context = array())
    {
        $permissionCode = self::resolvePermissionCode($permissionCode, 'takepos.admin');
        $base = self::baseContext($user, $featureCode, $permissionCode, $terminal);
        $context = self::enrichContext($base, $context);
        $message = trim((string) $message) !== '' ? trim((string) $message) : 'Access denied';

        return self::requireUser($db, $user, $featureCode, $permissionCode, $message, $context, false);
    }

    public static function requireAjaxAccess($db, $user, $featureCode = null, $permissionCode = 'takepos.use', $terminal = null, $context = array())
    {
        $permissionCode = self::resolvePermissionCode($permissionCode, 'takepos.use');
        $base = self::baseContext($user, $featureCode, $permissionCode, $terminal);
        $context = self::enrichContext($base, $context);

        return self::requireUser($db, $user, $featureCode, $permissionCode, 'Access denied', $context, true);
    }

    public static function requirePublicAccess($db, $featureCode = null, $context = array())
    {
        return self::requirePublicFeature($db, $featureCode, null, $context);
    }

    public static function enforceFrontend($db, $user, $featureCode = 'takepos.frontend', $terminal = null, $message = null)
    {
        return self::requireFrontendAccess($db, $user, $featureCode, 'takepos.use', $terminal, $message);
    }

    public static function enforceAdmin($db, $user, $featureCode = null, $terminal = null, $message = null)
    {
        return self::requireAdminAccess($db, $user, $featureCode, 'takepos.admin', $terminal, $message);
    }

    public static function enforcePublic($db, $featureCode, $message = null)
    {
        return self::requirePublicFeature($db, $featureCode, $message);
    }

    public static function isFeatureEnabled($db, $featureCode)
    {
        self::bootstrap($db);

        if (!self::moduleEnabled($db, null)) {
            return false;
        }

        return self::featureEnabled($db, $featureCode, null);
    }

    public static function isFeatureEnabledForCurrentEntity($db, $featureCode)
    {
        return self::isFeatureEnabled($db, $featureCode);
    }

    public static function requireFeature($db, $featureCode, $user = null, $ajax = false, $context = array())
    {
        return self::requireEnabledFeature($db, $user, $featureCode, 'Access denied', $context, $ajax);
    }

    public static function enforceTerminalLimit($db, $terminal, $user = null)
    {
        return true;
    }
}
