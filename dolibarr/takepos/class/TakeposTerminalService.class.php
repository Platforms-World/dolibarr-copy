<?php
require_once __DIR__ . '/TakeposMigration.class.php';
require_once __DIR__ . '/TakeposAudit.class.php';
require_once __DIR__ . '/TakeposStoreService.class.php';

/**
 * Terminal governance service.
 */
class TakeposTerminalService
{
    public static function tableTerminal()
    {
        return MAIN_DB_PREFIX . 'takepos_terminal';
    }

    public static function ensureSchema($db)
    {
        TakeposStoreService::ensureSchema($db);

        $table = self::tableTerminal();
        $ok = TakeposMigration::ensureTable($db, $table, "CREATE TABLE " . $table . " ("
            . " rowid INT AUTO_INCREMENT PRIMARY KEY,"
            . " entity INT NOT NULL DEFAULT 1,"
            . " terminal_code VARCHAR(32) NOT NULL,"
            . " label VARCHAR(128) NOT NULL,"
            . " fk_store INT NULL,"
            . " active TINYINT(1) NOT NULL DEFAULT 1,"
            . " last_seen DATETIME NULL,"
            . " metadata_json LONGTEXT NULL,"
            . " date_creation DATETIME NOT NULL,"
            . " tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
            . " UNIQUE KEY uk_takepos_terminal_entity_code (entity, terminal_code),"
            . " KEY idx_takepos_terminal_store (entity, fk_store, active),"
            . " KEY idx_takepos_terminal_active (entity, active)"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if (!$ok) {
            return false;
        }

        $columns = array(
            'entity' => "INT NOT NULL DEFAULT 1",
            'terminal_code' => "VARCHAR(32) NOT NULL",
            'label' => "VARCHAR(128) NOT NULL",
            'fk_store' => "INT NULL",
            'active' => "TINYINT(1) NOT NULL DEFAULT 1",
            'last_seen' => "DATETIME NULL",
            'metadata_json' => "LONGTEXT NULL",
            'date_creation' => "DATETIME NOT NULL",
            'tms' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        );
        foreach ($columns as $column => $definition) {
            if (!TakeposMigration::ensureColumn($db, $table, $column, $definition)) {
                return false;
            }
        }

        if (!TakeposMigration::ensureIndex($db, $table, 'uk_takepos_terminal_entity_code', '(entity, terminal_code)', 'UNIQUE')) {
            return false;
        }
        if (!TakeposMigration::ensureIndex($db, $table, 'idx_takepos_terminal_store', '(entity, fk_store, active)')) {
            return false;
        }
        if (!TakeposMigration::ensureIndex($db, $table, 'idx_takepos_terminal_active', '(entity, active)')) {
            return false;
        }

        return true;
    }
    public static function normalizeTerminalCode($terminalCode)
    {
        $code = strtoupper(trim((string) $terminalCode));
        if ($code === '') {
            return '';
        }

        // Keep Linux-safe, URL-safe code.
        if (!preg_match('/^[A-Z0-9_-]{1,32}$/', $code)) {
            return '';
        }

        return $code;
    }

    public static function getTerminalByCode($db, $entity, $terminalCode)
    {
        self::ensureSchema($db);

        $code = self::normalizeTerminalCode($terminalCode);
        if ($code === '') {
            return null;
        }

        $sql = "SELECT rowid, entity, terminal_code, label, fk_store, active, last_seen";
        $sql .= " FROM " . self::tableTerminal();
        $sql .= " WHERE entity = " . ((int) $entity) . " AND terminal_code = '" . $db->escape($code) . "'";
        $sql .= " LIMIT 1";

        $resql = $db->query($sql);
        return ($resql ? $db->fetch_object($resql) : null);
    }

    /**
     * List terminals.
     *
     * @param bool $masterOnly  When true, exclude branch terminals (fk_branch IS NOT NULL).
     *                          Use this for master-admin dropdowns so branch terminals
     *                          (T-TEST-1-1, T-TEST-2-1, etc.) don't appear in the master
     *                          shift/terminal selection UI.
     *
     * FIX (shift-master-v1): Added $masterOnly parameter.
     */
    public static function listTerminals($db, $entity, $storeId = 0, $activeOnly = false, $masterOnly = false)
    {
        self::ensureSchema($db);

        $sql = "SELECT t.rowid, t.entity, t.terminal_code, t.label, t.fk_store, t.active, t.last_seen, t.fk_branch, s.label AS store_label";
        $sql .= " FROM " . self::tableTerminal() . " t";
        $sql .= " LEFT JOIN " . TakeposStoreService::tableStore() . " s ON s.rowid = t.fk_store AND s.entity = t.entity";
        $sql .= " WHERE t.entity = " . ((int) $entity);
        // Exclude orphaned terminals (fk_branch IS NULL but terminal_code is numeric = leftover from deleted branches)
        $sql .= " AND NOT (t.fk_branch IS NULL AND t.terminal_code REGEXP '^[0-9]+$')";
        // FIX (shift-master-v1): Master-only mode: exclude branch terminals entirely
        if ($masterOnly) {
            $sql .= " AND (t.fk_branch IS NULL)";
        }
        if ((int) $storeId > 0) {
            $sql .= " AND t.fk_store = " . ((int) $storeId);
        }
        if ($activeOnly) {
            $sql .= " AND t.active = 1";
        }
        $sql .= " ORDER BY t.terminal_code ASC";

        $rows = array();
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $rows[] = $obj;
            }
        }

        return $rows;
    }

    public static function registerOrUpdateTerminal($db, $user, $entity, $terminalCode, $label, $storeId = 0, $active = 1)
    {
        self::ensureSchema($db);

        $code = self::normalizeTerminalCode($terminalCode);
        if ($code === '') {
            throw new Exception('Terminal code format is invalid.');
        }

        $label = trim((string) $label);
        if ($label === '') {
            $label = 'Terminal ' . $code;
        }

        $existing = self::getTerminalByCode($db, $entity, $code);

        if ((int) $storeId > 0) {
            $store = TakeposStoreService::getStore($db, $entity, (int) $storeId);
            if (!$store || empty($store->active)) {
                throw new Exception('Selected store is invalid or inactive.');
            }
        }

        if ($existing) {
            $previousStore = (int) $existing->fk_store;
            $sql = "UPDATE " . self::tableTerminal() . " SET"
                . " label='" . $db->escape($label) . "'"
                . ", fk_store=" . ((int) $storeId > 0 ? (int) $storeId : 'NULL')
                . ", active=" . ((int) $active > 0 ? 1 : 0)
                . " WHERE rowid = " . ((int) $existing->rowid);
            if (!$db->query($sql)) {
                throw new Exception($db->lasterror());
            }

            $event = ($previousStore !== (int) $storeId ? 'terminal_reassigned' : 'terminal_assigned');
            TakeposAudit::logEvent(
                $db,
                $user,
                $event,
                TakeposAudit::SEVERITY_WARNING,
                array('terminal_id' => (int) $existing->rowid, 'terminal_code' => $code, 'store_id' => (int) $storeId),
                'Terminal mapping updated',
                'terminal',
                (int) $existing->rowid
            );

            return (int) $existing->rowid;
        }

        $sql = "INSERT INTO " . self::tableTerminal() . " (entity, terminal_code, label, fk_store, active, date_creation) VALUES (";
        $sql .= ((int) $entity) . ", '" . $db->escape($code) . "', '" . $db->escape($label) . "', ";
        $sql .= ((int) $storeId > 0 ? (int) $storeId : 'NULL') . ", " . ((int) $active > 0 ? 1 : 0) . ", '" . $db->escape(dol_print_date(dol_now(), 'dayhourlog')) . "')";
        if (!$db->query($sql)) {
            throw new Exception($db->lasterror());
        }

        $terminalId = (int) $db->last_insert_id(self::tableTerminal());

        TakeposAudit::logEvent(
            $db,
            $user,
            'terminal_created',
            TakeposAudit::SEVERITY_INFO,
            array('terminal_id' => $terminalId, 'terminal_code' => $code, 'store_id' => (int) $storeId),
            'Terminal created',
            'terminal',
            $terminalId
        );

        if ((int) $storeId > 0) {
            TakeposAudit::logEvent(
                $db,
                $user,
                'terminal_assigned',
                TakeposAudit::SEVERITY_INFO,
                array('terminal_id' => $terminalId, 'terminal_code' => $code, 'store_id' => (int) $storeId),
                'Terminal assigned to store',
                'terminal',
                $terminalId
            );
        }

        return $terminalId;
    }

    public static function resolveCurrentTerminal($db, $user, $terminalCodeFromSession)
    {
        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        $code = self::normalizeTerminalCode($terminalCodeFromSession);
        if ($code === '') {
            $code = '1';
        }

        $terminal = self::getTerminalByCode($db, $entity, $code);
        if (!$terminal) {
            // Don't auto-register purely numeric terminal codes — these are
            // legacy session values (old terminal rowids) from deleted terminals.
            // Only register proper terminal codes like T-TEST-1, 1, etc.
            if (!is_numeric($code) || (int)$code <= 10) {
                $defaultLabel = getDolGlobalString('TAKEPOS_TERMINAL_NAME_' . $code, 'Terminal ' . $code);
                self::registerOrUpdateTerminal($db, $user, $entity, $code, $defaultLabel, 0, 1);
                $terminal = self::getTerminalByCode($db, $entity, $code);
            }
        }

        if ($terminal) {
            self::touchLastSeen($db, (int) $terminal->rowid);
        }

        return $terminal;
    }

    public static function touchLastSeen($db, $terminalId)
    {
        if ((int) $terminalId <= 0) {
            return false;
        }

        $sql = "UPDATE " . self::tableTerminal() . " SET last_seen='" . $db->escape(dol_print_date(dol_now(), 'dayhourlog')) . "'";
        $sql .= " WHERE rowid = " . ((int) $terminalId);
        return (bool) $db->query($sql);
    }
}
