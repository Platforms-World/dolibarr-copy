<?php

/**
 * Centralized audit logger for TakePOS.
 *
 * This logger is intentionally resilient: audit failures must never break POS flows.
 */
class TakeposAudit
{
    const SEVERITY_INFO = 'info';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_CRITICAL = 'critical';

    public static function tableName()
    {
        return MAIN_DB_PREFIX . 'takepos_audit';
    }

    private static function validSeverities()
    {
        return array(self::SEVERITY_INFO, self::SEVERITY_WARNING, self::SEVERITY_CRITICAL);
    }

    private static function syslogError($message)
    {
        if (function_exists('dol_syslog')) {
            dol_syslog('[TakePOS][Audit] ' . $message, LOG_ERR);
        }
    }

    private static function executeSafe($db, $sql)
    {
        $resql = $db->query($sql);
        if (!$resql) {
            self::syslogError($db->lasterror());
        }
        return $resql;
    }

    private static function columnExists($db, $table, $columnName)
    {
        $sql = "SHOW COLUMNS FROM " . $table . " LIKE '" . $db->escape($columnName) . "'";
        $resql = self::executeSafe($db, $sql);
        return ($resql && $db->num_rows($resql) > 0);
    }

    private static function ensureColumn($db, $table, $columnName, $definition)
    {
        if (self::columnExists($db, $table, $columnName)) {
            return true;
        }

        $sql = "ALTER TABLE " . $table . " ADD COLUMN " . $columnName . " " . $definition;
        return (bool) self::executeSafe($db, $sql);
    }

    private static function indexExists($db, $table, $indexName)
    {
        $sql = "SHOW INDEX FROM " . $table . " WHERE Key_name = '" . $db->escape($indexName) . "'";
        $resql = self::executeSafe($db, $sql);
        return ($resql && $db->num_rows($resql) > 0);
    }

    private static function ensureIndex($db, $table, $indexName, $definition)
    {
        if (self::indexExists($db, $table, $indexName)) {
            return true;
        }

        $sql = "ALTER TABLE " . $table . " ADD INDEX " . $indexName . " " . $definition;
        return (bool) self::executeSafe($db, $sql);
    }

    public static function ensureTable($db)
    {
        // PERFORMANCE FIX:
        // ensureTable was being called on every logEvent(), and there are 127+
        // logEvent call sites. Each call ran:
        //   - CREATE TABLE IF NOT EXISTS (forces schema parse + metadata lock)
        //   - SHOW COLUMNS x2 (ensureColumn)
        //   - SHOW INDEX x1  (ensureIndex)
        // That is 4 metadata round-trips per audit log on a busy server.
        // Multiplied by every action that audits (open screen, addline, deleteline...)
        // this dominated the 4.85s "Waiting for server response" the user observed.
        //
        // Two-level cache to fix it:
        //   1) Per-PHP-process: static flag — skip after first success in this request.
        //   2) Across requests: a session flag that persists until logout, since the
        //      schema cannot change between requests under normal operation.
        //      The session check itself is in-memory once $_SESSION is loaded so it
        //      is essentially free.
        // Admins who run the migration after a Dolibarr upgrade can clear the flag
        // by signing out and back in (or by deleting $_SESSION['takepos_audit_schema_ok']).

        static $perProcessOk = null;
        if ($perProcessOk === true) {
            return true;
        }

        if (isset($_SESSION['takepos_audit_schema_ok']) && $_SESSION['takepos_audit_schema_ok'] === 1) {
            $perProcessOk = true;
            return true;
        }

        try {
            $table = self::tableName();
            $sql = "CREATE TABLE IF NOT EXISTS " . $table . " ("
                . " rowid INT AUTO_INCREMENT PRIMARY KEY,"
                . " entity INT NOT NULL DEFAULT 1,"
                . " fk_user INT NULL,"
                . " login VARCHAR(128) NULL,"
                . " terminal INT NULL,"
                . " event_code VARCHAR(80) NOT NULL,"
                . " severity VARCHAR(16) NOT NULL DEFAULT 'info',"
                . " object_type VARCHAR(64) NULL,"
                . " object_id INT NULL,"
                . " amount_ttc DECIMAL(24,8) NULL,"
                . " description TEXT NULL,"
                . " ip_address VARCHAR(64) NULL,"
                . " request_uri VARCHAR(255) NULL,"
                . " user_agent VARCHAR(255) NULL,"
                . " extra_json LONGTEXT NULL,"
                . " datec DATETIME NOT NULL,"
                . " tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
                . " KEY idx_takepos_audit_entity_date (entity, datec),"
                . " KEY idx_takepos_audit_user_date (fk_user, datec),"
                . " KEY idx_takepos_audit_event_date (event_code, datec),"
                . " KEY idx_takepos_audit_object (object_type, object_id),"
                . " KEY idx_takepos_audit_severity_date (severity, datec)"
                . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            if (!self::executeSafe($db, $sql)) {
                $perProcessOk = false;
                return false;
            }

            // Backward-compatible column/index upgrades for existing installs.
            self::ensureColumn($db, $table, 'severity', "VARCHAR(16) NOT NULL DEFAULT 'info' AFTER event_code");
            self::ensureColumn($db, $table, 'request_uri', "VARCHAR(255) NULL AFTER ip_address");
            self::ensureIndex($db, $table, 'idx_takepos_audit_severity_date', "(severity, datec)");

            $perProcessOk = true;
            // Persist across requests for this session — schema does not change in normal use.
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['takepos_audit_schema_ok'] = 1;
            }
            return true;
        } catch (Throwable $e) {
            self::syslogError($e->getMessage());
            return false;
        }
    }

    /**
     * Log an audit event.
     *
     * Preferred signature:
     * logEvent($db, $user, $eventType, $severity = 'info', $data = array(), $description = '', $objectType = '', $objectId = 0, $amountTtc = null)
     *
     * Backward compatibility signature (legacy):
     * logEvent($db, $user, $eventCode, $description, $objectType, $objectId, $amountTtc, $extra)
     */
    public static function logEvent($db, $user, $eventType, $severity = 'info', $data = array(), $description = '', $objectType = '', $objectId = 0, $amountTtc = null)
    {
        try {
            $validSeverities = self::validSeverities();
            $severityCandidate = strtolower(trim((string) $severity));

            // Legacy signature detection.
            if (!in_array($severityCandidate, $validSeverities, true)) {
                $legacyDescription = (string) $severity;
                $legacyObjectType = (string) $data;
                $legacyObjectId = (int) $description;
                $legacyAmount = $objectType;
                $legacyExtra = is_array($objectId) ? $objectId : array();

                $severity = self::SEVERITY_INFO;
                $data = $legacyExtra;
                $description = $legacyDescription;
                $objectType = $legacyObjectType;
                $objectId = $legacyObjectId;
                $amountTtc = (is_numeric($legacyAmount) || $legacyAmount === null || $legacyAmount === '') ? $legacyAmount : null;
            } else {
                $severity = $severityCandidate;
                if (!is_array($data)) {
                    $data = array('value' => $data);
                }
            }

            if (!self::ensureTable($db)) {
                return false;
            }

            global $conf;
            $entity = !empty($conf->entity) ? (int) $conf->entity : (!empty($user->entity) ? (int) $user->entity : 1);
            $fkUser = !empty($user->id) ? (int) $user->id : 0;
            $login = !empty($user->login) ? (string) $user->login : '';
            $terminal = !empty($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : 0;
            $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
            $requestUri = isset($_SERVER['REQUEST_URI']) ? substr((string) $_SERVER['REQUEST_URI'], 0, 255) : '';
            $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : '';
            $eventType = trim((string) $eventType);
            if ($eventType === '') {
                $eventType = 'unknown';
            }
            $json = !empty($data) ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
            if ($json === false) {
                $json = null;
            }
            $amountSql = ($amountTtc === null || $amountTtc === '') ? 'NULL' : ((float) $amountTtc);

            $sql = "INSERT INTO " . self::tableName()
                . " (entity, fk_user, login, terminal, event_code, severity, object_type, object_id, amount_ttc, description, ip_address, request_uri, user_agent, extra_json, datec) VALUES ("
                . $entity . ", "
                . ($fkUser > 0 ? $fkUser : 'NULL') . ", "
                . ($login !== '' ? "'" . $db->escape($login) . "'" : 'NULL') . ", "
                . ($terminal > 0 ? $terminal : 'NULL') . ", "
                . "'" . $db->escape($eventType) . "', "
                . "'" . $db->escape($severity) . "', "
                . ($objectType !== '' ? "'" . $db->escape((string) $objectType) . "'" : 'NULL') . ", "
                . ((int) $objectId > 0 ? (int) $objectId : 'NULL') . ", "
                . $amountSql . ", "
                . ($description !== '' ? "'" . $db->escape((string) $description) . "'" : 'NULL') . ", "
                . ($ip !== '' ? "'" . $db->escape($ip) . "'" : 'NULL') . ", "
                . ($requestUri !== '' ? "'" . $db->escape($requestUri) . "'" : 'NULL') . ", "
                . ($userAgent !== '' ? "'" . $db->escape($userAgent) . "'" : 'NULL') . ", "
                . ($json !== null ? "'" . $db->escape($json) . "'" : 'NULL') . ", "
                . "'" . $db->escape(dol_print_date(dol_now(), 'dayhourlog')) . "')";

            if (!$db->query($sql)) {
                self::syslogError($db->lasterror());
                return false;
            }

            return true;
        } catch (Throwable $e) {
            self::syslogError($e->getMessage());
            return false;
        }
    }

    public static function fetchRows($db, $limit = 200, $filters = array())
    {
        self::ensureTable($db);
        global $conf;

        $limit = max(1, min(1000, (int) $limit));
        $sql = "SELECT rowid, entity, fk_user, login, terminal, event_code, severity, object_type, object_id, amount_ttc, description, ip_address, request_uri, extra_json, datec"
            . " FROM " . self::tableName()
            . " WHERE entity = " . ((int) $conf->entity);
        if (!empty($filters['event_code'])) {
            $sql .= " AND event_code='" . $db->escape($filters['event_code']) . "'";
        }
        if (!empty($filters['fk_user'])) {
            $sql .= " AND fk_user = " . ((int) $filters['fk_user']);
        }
        if (!empty($filters['severity'])) {
            $sql .= " AND severity = '" . $db->escape($filters['severity']) . "'";
        }
        $sql .= " ORDER BY rowid DESC LIMIT " . $limit;

        $rows = array();
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $rows[] = $obj;
            }
        }

        return $rows;
    }

    public static function getDashboardMetrics($db)
    {
        self::ensureTable($db);
        global $conf;

        $entity = (int) $conf->entity;
        $m = array(
            'audit_today' => 0,
            'audit_7d' => 0,
            'sales_today' => 0,
            'sales_amount_today' => 0.0,
            'cashiers_today' => 0,
            'top_cashiers' => array(),
            'recent_sales' => array(),
            'top_products' => array(),
        );

        $table = self::tableName();
        $q = $db->query("SELECT COUNT(*) AS nb FROM " . $table . " WHERE entity=" . $entity . " AND datec >= DATE(NOW())");
        if ($q && ($o = $db->fetch_object($q))) {
            $m['audit_today'] = (int) $o->nb;
        }
        $q = $db->query("SELECT COUNT(*) AS nb FROM " . $table . " WHERE entity=" . $entity . " AND datec >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        if ($q && ($o = $db->fetch_object($q))) {
            $m['audit_7d'] = (int) $o->nb;
        }

        $fact = MAIN_DB_PREFIX . 'facture';
        $det = MAIN_DB_PREFIX . 'facturedet';
        $prod = MAIN_DB_PREFIX . 'product';
        $usr = MAIN_DB_PREFIX . 'user';

        $res = $db->query("SHOW TABLES LIKE '" . $db->escape($fact) . "'");
        if ($res && $db->num_rows($res) > 0) {
            $q = $db->query("SELECT COUNT(*) AS nb, COALESCE(SUM(total_ttc),0) AS totalamt FROM " . $fact . " WHERE entity=" . $entity . " AND datef >= CURDATE()");
            if ($q && ($o = $db->fetch_object($q))) {
                $m['sales_today'] = (int) $o->nb;
                $m['sales_amount_today'] = (float) $o->totalamt;
            }
            $q = $db->query("SELECT COUNT(DISTINCT fk_user_author) AS nb FROM " . $fact . " WHERE entity=" . $entity . " AND datef >= CURDATE() AND fk_user_author IS NOT NULL");
            if ($q && ($o = $db->fetch_object($q))) {
                $m['cashiers_today'] = (int) $o->nb;
            }
            $q = $db->query("SELECT COALESCE(u.login, CONCAT('User#', f.fk_user_author)) AS cashier, COUNT(*) AS sales_count, COALESCE(SUM(f.total_ttc),0) AS amount_ttc FROM " . $fact . " f LEFT JOIN " . $usr . " u ON u.rowid=f.fk_user_author WHERE f.entity=" . $entity . " AND f.datef >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY f.fk_user_author, u.login ORDER BY amount_ttc DESC LIMIT 10");
            if ($q) {
                while ($o = $db->fetch_object($q)) {
                    $m['top_cashiers'][] = $o;
                }
            }
            $q = $db->query("SELECT f.rowid, f.ref, f.datef, f.total_ttc, COALESCE(u.login, CONCAT('User#', f.fk_user_author)) AS cashier FROM " . $fact . " f LEFT JOIN " . $usr . " u ON u.rowid=f.fk_user_author WHERE f.entity=" . $entity . " ORDER BY f.rowid DESC LIMIT 20");
            if ($q) {
                while ($o = $db->fetch_object($q)) {
                    $m['recent_sales'][] = $o;
                }
            }

            $res2 = $db->query("SHOW TABLES LIKE '" . $db->escape($det) . "'");
            if ($res2 && $db->num_rows($res2) > 0) {
                $q = $db->query("SELECT COALESCE(p.label, fd.description, fd.label, CONCAT('Product#', fd.fk_product)) AS product_label, SUM(fd.qty) AS qty_total, SUM(fd.total_ttc) AS amount_ttc FROM " . $det . " fd INNER JOIN " . $fact . " f ON f.rowid=fd.fk_facture LEFT JOIN " . $prod . " p ON p.rowid=fd.fk_product WHERE f.entity=" . $entity . " AND f.datef >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY product_label ORDER BY qty_total DESC LIMIT 10");
                if ($q) {
                    while ($o = $db->fetch_object($q)) {
                        $m['top_products'][] = $o;
                    }
                }
            }
        }

        return $m;
    }
}