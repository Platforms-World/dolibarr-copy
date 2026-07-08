<?php
/**
 * Shared DB migration helpers for TakePOS.
 *
 * Uses schema inspection (SHOW TABLES/COLUMNS/INDEX) to stay compatible
 * with MySQL/MariaDB variants where ALTER ... IF NOT EXISTS is unreliable.
 */
class TakeposMigration
{
    private static function syslogError($message)
    {
        if (function_exists('dol_syslog')) {
            dol_syslog('[TakePOS][Migration] ' . $message, LOG_ERR);
        }
    }

    // PERFORMANCE: per-process caches so we don't re-issue the same SHOW TABLES /
    // SHOW COLUMNS / SHOW INDEX query a dozen times per request. The schema
    // doesn't change between calls, so once we've answered "yes this exists" we
    // remember it for the rest of the request. If the schema is changed by an
    // admin (a Dolibarr upgrade, a manual ALTER), the next request gets a fresh
    // cache anyway since these are static-per-process.
    //
    // Layer 2 — session cache. PHP-FPM resets the static cache on every HTTP
    // request, but the schema rarely changes between requests, so we ALSO
    // store positive answers in $_SESSION. That collapses the dozens of
    // SHOW TABLES / SHOW COLUMNS / SHOW INDEX queries that every "ensureSchema"
    // service does on every endpoint call (sync.php, shift.php, addline, ...)
    // down to a single cheap session lookup. To force a re-check after a
    // Dolibarr upgrade, the user signs out and back in, OR an admin can set
    // TAKEPOS_SCHEMA_CACHE_VERSION higher in conf to bust every session.
    private static $tableExistsCache  = array();
    private static $columnExistsCache = array();
    private static $indexExistsCache  = array();

    private static function sessionVersion()
    {
        // Bump this constant (or the optional global) to force every active
        // session to re-verify the schema after a Dolibarr / module upgrade.
        $base = 1;
        if (function_exists('getDolGlobalInt')) {
            $override = (int) getDolGlobalInt('TAKEPOS_SCHEMA_CACHE_VERSION');
            if ($override > 0) {
                return $override;
            }
        }
        return $base;
    }

    private static function sessionAvailable()
    {
        return (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE);
    }

    private static function sessionCheck($bucket, $key)
    {
        if (!self::sessionAvailable()) {
            return false;
        }
        $ver = self::sessionVersion();
        return !empty($_SESSION['takepos_schema_cache'][$ver][$bucket][$key]);
    }

    private static function sessionRemember($bucket, $key)
    {
        if (!self::sessionAvailable()) {
            return;
        }
        $ver = self::sessionVersion();
        if (!isset($_SESSION['takepos_schema_cache']) || !is_array($_SESSION['takepos_schema_cache'])) {
            $_SESSION['takepos_schema_cache'] = array();
        }
        if (!isset($_SESSION['takepos_schema_cache'][$ver]) || !is_array($_SESSION['takepos_schema_cache'][$ver])) {
            // Drop older versions to keep session tidy
            $_SESSION['takepos_schema_cache'] = array($ver => array());
        }
        if (!isset($_SESSION['takepos_schema_cache'][$ver][$bucket]) || !is_array($_SESSION['takepos_schema_cache'][$ver][$bucket])) {
            $_SESSION['takepos_schema_cache'][$ver][$bucket] = array();
        }
        $_SESSION['takepos_schema_cache'][$ver][$bucket][$key] = 1;
    }

    public static function tableExists($db, $table)
    {
        if (isset(self::$tableExistsCache[$table])) {
            return self::$tableExistsCache[$table];
        }
        if (self::sessionCheck('table', $table)) {
            self::$tableExistsCache[$table] = true;
            return true;
        }
        $resql = $db->query("SHOW TABLES LIKE '" . $db->escape($table) . "'");
        $exists = ($resql && $db->num_rows($resql) > 0);
        if ($exists) {
            self::$tableExistsCache[$table] = true;
            self::sessionRemember('table', $table);
        }
        return $exists;
    }

    public static function columnExists($db, $table, $column)
    {
        $key = $table . '|' . $column;
        if (isset(self::$columnExistsCache[$key])) {
            return self::$columnExistsCache[$key];
        }
        if (self::sessionCheck('column', $key)) {
            self::$columnExistsCache[$key] = true;
            return true;
        }
        $resql = $db->query("SHOW COLUMNS FROM " . $table . " LIKE '" . $db->escape($column) . "'");
        $exists = ($resql && $db->num_rows($resql) > 0);
        if ($exists) {
            self::$columnExistsCache[$key] = true;
            self::sessionRemember('column', $key);
        }
        return $exists;
    }

    public static function indexExists($db, $table, $index)
    {
        $key = $table . '|' . $index;
        if (isset(self::$indexExistsCache[$key])) {
            return self::$indexExistsCache[$key];
        }
        if (self::sessionCheck('index', $key)) {
            self::$indexExistsCache[$key] = true;
            return true;
        }
        $resql = $db->query("SHOW INDEX FROM " . $table . " WHERE Key_name = '" . $db->escape($index) . "'");
        $exists = ($resql && $db->num_rows($resql) > 0);
        if ($exists) {
            self::$indexExistsCache[$key] = true;
            self::sessionRemember('index', $key);
        }
        return $exists;
    }

    public static function execute($db, $sql)
    {
        $resql = $db->query($sql);
        if (!$resql) {
            self::syslogError($db->lasterror());
        }
        return $resql;
    }

    public static function ensureTable($db, $table, $createSql)
    {
        if (self::tableExists($db, $table)) {
            return true;
        }

        if (!self::execute($db, $createSql)) {
            return false;
        }

        return self::tableExists($db, $table);
    }

    public static function ensureColumn($db, $table, $column, $definition)
    {
        if (self::columnExists($db, $table, $column)) {
            return true;
        }

        return (bool) self::execute($db, "ALTER TABLE " . $table . " ADD COLUMN " . $column . " " . $definition);
    }

    public static function ensureIndex($db, $table, $index, $definition, $type = 'INDEX')
    {
        if (self::indexExists($db, $table, $index)) {
            return true;
        }

        $type = strtoupper(trim((string) $type));
        if ($type === '') {
            $type = 'INDEX';
        }

        return (bool) self::execute($db, "ALTER TABLE " . $table . " ADD " . $type . " " . $index . " " . $definition);
    }
}
