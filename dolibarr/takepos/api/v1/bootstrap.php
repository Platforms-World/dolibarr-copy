<?php
if (!defined('NOLOGIN'))         { define('NOLOGIN',         1);   }
if (!defined('NOCSRFCHECK'))     { define('NOCSRFCHECK',     1);   }
if (!defined('NOIPCHECK'))       { define('NOIPCHECK',       1);   }
if (!defined('NOTOKENRENEWAL'))  { define('NOTOKENRENEWAL',  '1'); }
if (!defined('NOREQUIREMENU'))   { define('NOREQUIREMENU',   '1'); }
if (!defined('NOREQUIREHTML'))   { define('NOREQUIREHTML',   '1'); }
if (!defined('NOREQUIREAJAX'))   { define('NOREQUIREAJAX',   '1'); }
if (!defined('NOBROWSERNOTIF'))  { define('NOBROWSERNOTIF',  1);   }

require_once __DIR__ . '/_response.php';
takeposApiPrepareResponse();

@ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) {
    @mb_internal_encoding('UTF-8');
}

if (!defined('DOL_DOCUMENT_ROOT')) {
    $mainPath = __DIR__ . '/../../../main.inc.php';
    if (!file_exists($mainPath)) {
        $mainPath = __DIR__ . '/../../../../main.inc.php';
    }
    require $mainPath;
}

require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposApiException.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposMigration.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposAudit.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposApiService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposApiIdempotencyService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposWebhookService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposStoreService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposTerminalService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposShiftService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposRefundService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposLoyaltyService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposProductBarcodeService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposInputValidator.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposUserAccess.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposUtf8.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposApiCheckoutService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposApiPaymentService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposApiRefundService.class.php';
require_once __DIR__ . '/_auth.php';

// ── Force UTF-8 database connection ──────────────────────────────────────────
if (!function_exists('takeposApiForceUtf8Connection')) {
    function takeposApiForceUtf8Connection($db)
    {
        if (!is_object($db)) {
            return;
        }

        $queries = array(
            'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
            'SET CHARACTER SET utf8mb4',
            "SET SESSION character_set_client     = 'utf8mb4'",
            "SET SESSION character_set_results    = 'utf8mb4'",
            "SET SESSION character_set_connection = 'utf8mb4'",
            "SET SESSION collation_connection     = 'utf8mb4_unicode_ci'",
        );
        foreach ($queries as $sql) {
            @$db->query($sql);
        }

        foreach (array('db', 'dbh', 'link', 'mysqli', 'pdo') as $property) {
            if (!isset($db->$property)) {
                continue;
            }
            $native = $db->$property;
            if (is_object($native) && class_exists('mysqli') && $native instanceof mysqli) {
                @$native->set_charset('utf8mb4');
            }
            if (is_object($native) && class_exists('PDO') && $native instanceof PDO) {
                try {
                    $native->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
                } catch (Throwable $e) {
                    // ignore — best-effort
                }
            }
        }
    }
}

TakeposUtf8::bootstrapConnection($db);
takeposApiForceUtf8Connection($db);

// ── Audit helper ──────────────────────────────────────────────────────────────
if (!function_exists('takeposApiAuditAccess')) {
    function takeposApiAuditAccess($db, $auth, $endpoint, $extra = array())
    {
        $auditUser = new stdClass();
        if (isset($auth['user']) && is_object($auth['user'])) {
            $auditUser = clone $auth['user'];
        }
        if (empty($auditUser->id)) {
            $auditUser->id = 0;
        }
        if (empty($auditUser->entity)) {
            $auditUser->entity = (isset($auth['entity']) ? (int) $auth['entity'] : 1);
        }
        if (empty($auditUser->login)) {
            $auditUser->login = 'api:' . (empty($auth['token']['label']) ? 'unknown' : (string) $auth['token']['label']);
        }

        $data = array(
            'api'          => true,
            'endpoint'     => (string) $endpoint,
            'token_id'     => (empty($auth['token']['id'])    ? 0  : (int)    $auth['token']['id']),
            'token_label'  => (empty($auth['token']['label']) ? '' : (string) $auth['token']['label']),
            'scope'        => (empty($auth['scopes'])         ? array() : (array) $auth['scopes']),
        );
        if (is_array($extra) && $extra) {
            $data = array_merge($data, $extra);
        }

        TakeposAudit::logEvent(
            $db,
            $auditUser,
            'api_endpoint_accessed',
            TakeposAudit::SEVERITY_INFO,
            $data,
            'TakePOS API endpoint accessed',
            'api'
        );
    }
}

if (empty($mysoc) || !is_object($mysoc)) {
    require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
    $mysoc = new Societe($db);
    $mysoc->fetch(getDolGlobalInt('MAIN_INFO_SOCIETE_ID', 1));
}

// ── Role-based API access check ───────────────────────────────────────────────
// Call this after takeposApiAuth() in any endpoint that needs role enforcement.
// Usage: takeposApiEnforceRoleAccess($db, $auth);
if (!function_exists('takeposApiEnforceRoleAccess')) {
    function takeposApiEnforceRoleAccess($db, $auth, $endpoint = null) {
        if (empty($auth['user']) || !is_object($auth['user'])) {
            return;
        }
        if ($endpoint === null) {
            $endpoint = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
        }
        takeposApiCheckRolePermission($db, $auth['user'], $endpoint);
    }
}
// ── End Role-based API access check ──────────────────────────────────────────
