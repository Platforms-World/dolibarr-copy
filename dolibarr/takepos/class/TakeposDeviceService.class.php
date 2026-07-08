<?php
require_once __DIR__ . '/TakeposMigration.class.php';
require_once __DIR__ . '/TakeposAudit.class.php';
require_once __DIR__ . '/TakeposInputValidator.class.php';
require_once __DIR__ . '/TakeposTerminalService.class.php';

/**
 * Hardware device profile and terminal binding service.
 */
class TakeposDeviceService
{
    const TYPE_SCANNER = 'barcode_scanner';
    const TYPE_PRINTER = 'receipt_printer';
    const TYPE_DISPLAY = 'customer_display';
    const TYPE_CASH_DRAWER = 'cash_drawer';
    const TYPE_PAYMENT_TERMINAL = 'payment_terminal';
    const TYPE_SCALE = 'weighing_scale';
    const TYPE_OTHER = 'other';

    const BINDING_PRINTER = 'printer';
    const BINDING_DISPLAY = 'display';
    const BINDING_SCANNER = 'scanner';
    const BINDING_CASH_DRAWER = 'cash_drawer';
    const BINDING_PAYMENT_TERMINAL = 'payment_terminal';
    const BINDING_SCALE = 'scale';

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

    public static function tableProfile()
    {
        return MAIN_DB_PREFIX . 'takepos_device_profile';
    }

    public static function tableBinding()
    {
        return MAIN_DB_PREFIX . 'takepos_terminal_device';
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
                dol_syslog('[TakePOS][Device] Audit failure: ' . $e->getMessage(), LOG_WARNING);
            }
        }
    }

    public static function allowedDeviceTypes()
    {
        return array(
            self::TYPE_SCANNER,
            self::TYPE_PRINTER,
            self::TYPE_DISPLAY,
            self::TYPE_CASH_DRAWER,
            self::TYPE_PAYMENT_TERMINAL,
            self::TYPE_SCALE,
            self::TYPE_OTHER,
        );
    }

    public static function allowedBindingTypes()
    {
        return array(
            self::BINDING_PRINTER,
            self::BINDING_DISPLAY,
            self::BINDING_SCANNER,
            self::BINDING_CASH_DRAWER,
            self::BINDING_PAYMENT_TERMINAL,
            self::BINDING_SCALE,
        );
    }

    public static function normalizeCode($code)
    {
        $clean = strtoupper(trim((string) $code));
        if ($clean === '') {
            return '';
        }

        if (!preg_match('/^[A-Z0-9_-]{2,48}$/', $clean)) {
            return '';
        }

        return $clean;
    }

    public static function normalizeDeviceType($type)
    {
        $type = strtolower(trim((string) $type));
        if (!in_array($type, self::allowedDeviceTypes(), true)) {
            return self::TYPE_OTHER;
        }

        return $type;
    }

    public static function normalizeBindingType($bindingType)
    {
        $bindingType = strtolower(trim((string) $bindingType));
        if (!in_array($bindingType, self::allowedBindingTypes(), true)) {
            return '';
        }

        return $bindingType;
    }

    public static function ensureSchema($db)
    {
        TakeposTerminalService::ensureSchema($db);

        $profileTable = self::tableProfile();
        $bindingTable = self::tableBinding();

        $ok = true;
        $ok = $ok && TakeposMigration::ensureTable($db, $profileTable, "CREATE TABLE " . $profileTable . " ("
            . " rowid INT AUTO_INCREMENT PRIMARY KEY,"
            . " entity INT NOT NULL DEFAULT 1,"
            . " device_code VARCHAR(48) NOT NULL,"
            . " label VARCHAR(128) NOT NULL,"
            . " device_type VARCHAR(32) NOT NULL,"
            . " settings_json LONGTEXT NULL,"
            . " active TINYINT(1) NOT NULL DEFAULT 1,"
            . " date_creation DATETIME NOT NULL,"
            . " tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
            . " UNIQUE KEY uk_takepos_device_code (entity, device_code),"
            . " KEY idx_takepos_device_type (entity, device_type, active),"
            . " KEY idx_takepos_device_active (entity, active)"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $ok = $ok && TakeposMigration::ensureTable($db, $bindingTable, "CREATE TABLE " . $bindingTable . " ("
            . " rowid INT AUTO_INCREMENT PRIMARY KEY,"
            . " entity INT NOT NULL DEFAULT 1,"
            . " fk_terminal INT NOT NULL,"
            . " fk_device_profile INT NOT NULL,"
            . " binding_type VARCHAR(32) NOT NULL,"
            . " priority INT NOT NULL DEFAULT 1,"
            . " active TINYINT(1) NOT NULL DEFAULT 1,"
            . " date_creation DATETIME NOT NULL,"
            . " tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
            . " UNIQUE KEY uk_takepos_terminal_binding (entity, fk_terminal, binding_type, priority),"
            . " KEY idx_takepos_terminal_binding_profile (entity, fk_device_profile, active),"
            . " KEY idx_takepos_terminal_binding_terminal (entity, fk_terminal, active)"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if (!$ok) {
            return false;
        }

        $profileColumns = array(
            'entity' => "INT NOT NULL DEFAULT 1",
            'device_code' => "VARCHAR(48) NOT NULL",
            'label' => "VARCHAR(128) NOT NULL",
            'device_type' => "VARCHAR(32) NOT NULL",
            'settings_json' => "LONGTEXT NULL",
            'active' => "TINYINT(1) NOT NULL DEFAULT 1",
            'date_creation' => "DATETIME NOT NULL",
            'tms' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        );
        foreach ($profileColumns as $column => $definition) {
            if (!TakeposMigration::ensureColumn($db, $profileTable, $column, $definition)) {
                return false;
            }
        }

        $bindingColumns = array(
            'entity' => "INT NOT NULL DEFAULT 1",
            'fk_terminal' => "INT NOT NULL",
            'fk_device_profile' => "INT NOT NULL",
            'binding_type' => "VARCHAR(32) NOT NULL",
            'priority' => "INT NOT NULL DEFAULT 1",
            'active' => "TINYINT(1) NOT NULL DEFAULT 1",
            'date_creation' => "DATETIME NOT NULL",
            'tms' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        );
        foreach ($bindingColumns as $column => $definition) {
            if (!TakeposMigration::ensureColumn($db, $bindingTable, $column, $definition)) {
                return false;
            }
        }

        TakeposMigration::ensureIndex($db, $profileTable, 'uk_takepos_device_code', '(entity, device_code)', 'UNIQUE');
        TakeposMigration::ensureIndex($db, $profileTable, 'idx_takepos_device_type', '(entity, device_type, active)');
        TakeposMigration::ensureIndex($db, $profileTable, 'idx_takepos_device_active', '(entity, active)');
        TakeposMigration::ensureIndex($db, $bindingTable, 'uk_takepos_terminal_binding', '(entity, fk_terminal, binding_type, priority)', 'UNIQUE');
        TakeposMigration::ensureIndex($db, $bindingTable, 'idx_takepos_terminal_binding_profile', '(entity, fk_device_profile, active)');
        TakeposMigration::ensureIndex($db, $bindingTable, 'idx_takepos_terminal_binding_terminal', '(entity, fk_terminal, active)');

        return true;
    }

    public static function getProfileById($db, $entity, $profileId)
    {
        self::ensureSchema($db);

        $sql = "SELECT rowid, entity, device_code, label, device_type, settings_json, active, date_creation"
            . " FROM " . self::tableProfile()
            . " WHERE entity = " . ((int) $entity)
            . " AND rowid = " . ((int) $profileId)
            . " LIMIT 1";
        $resql = $db->query($sql);
        return ($resql ? $db->fetch_object($resql) : null);
    }

    public static function listProfiles($db, $entity, $deviceType = '', $activeOnly = false)
    {
        self::ensureSchema($db);

        $sql = "SELECT rowid, entity, device_code, label, device_type, settings_json, active, date_creation"
            . " FROM " . self::tableProfile()
            . " WHERE entity = " . ((int) $entity);

        if ($deviceType !== '') {
            $sql .= " AND device_type = '" . $db->escape(self::normalizeDeviceType($deviceType)) . "'";
        }
        if ($activeOnly) {
            $sql .= " AND active = 1";
        }

        $sql .= " ORDER BY device_type ASC, device_code ASC";

        $rows = array();
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $rows[] = $obj;
            }
        }

        return $rows;
    }

    private static function normalizeSettingsJson($settings)
    {
        if (is_array($settings)) {
            $json = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return ($json === false ? '{}' : $json);
        }

        $raw = trim((string) $settings);
        if ($raw === '') {
            return '{}';
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return '{}';
        }

        $json = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return ($json === false ? '{}' : $json);
    }

    public static function saveProfile($db, $user, $entity, $profileId, $deviceCode, $label, $deviceType, $settings = '{}', $active = 1)
    {
        self::ensureSchema($db);

        $entity = (int) $entity;
        if ($entity <= 0) {
            $entity = !empty($user->entity) ? (int) $user->entity : 1;
        }

        $deviceCode = self::normalizeCode($deviceCode);
        if ($deviceCode === '') {
            throw new Exception(self::trans('TakeposDeviceCodeInvalid', 'Device code is invalid. Allowed format: A-Z, 0-9, _, - (2-48 chars).'));
        }

        $label = trim((string) $label);
        if ($label === '') {
            throw new Exception(self::trans('TakeposDeviceLabelRequired', 'Device label is required.'));
        }

        $deviceType = self::normalizeDeviceType($deviceType);
        $settingsJson = self::normalizeSettingsJson($settings);
        $active = ((int) $active > 0 ? 1 : 0);

        $profileId = (int) $profileId;
        if ($profileId > 0) {
            $current = self::getProfileById($db, $entity, $profileId);
            if (!$current) {
                throw new Exception(self::trans('TakeposDeviceProfileNotFound', 'Device profile not found.'));
            }

            $sql = "UPDATE " . self::tableProfile() . " SET"
                . " device_code = '" . $db->escape($deviceCode) . "'"
                . ", label = '" . $db->escape($label) . "'"
                . ", device_type = '" . $db->escape($deviceType) . "'"
                . ", settings_json = '" . $db->escape($settingsJson) . "'"
                . ", active = " . $active
                . " WHERE rowid = " . $profileId
                . " AND entity = " . $entity;
            if (!$db->query($sql)) {
                throw new Exception($db->lasterror());
            }
        } else {
            $sql = "INSERT INTO " . self::tableProfile() . " (entity, device_code, label, device_type, settings_json, active, date_creation) VALUES ("
                . $entity . ", '" . $db->escape($deviceCode) . "', '" . $db->escape($label) . "', '" . $db->escape($deviceType) . "', '" . $db->escape($settingsJson) . "', " . $active . ", " . self::nowSql($db) . ")";
            if (!$db->query($sql)) {
                throw new Exception($db->lasterror());
            }
            $profileId = (int) $db->last_insert_id(self::tableProfile());
        }

        self::safeAudit(
            $db,
            $user,
            'device_profile_updated',
            TakeposAudit::SEVERITY_WARNING,
            array(
                'profile_id' => $profileId,
                'device_code' => $deviceCode,
                'device_type' => $deviceType,
                'active' => $active,
            ),
            'Device profile updated',
            'device_profile',
            $profileId
        );

        return $profileId;
    }

    public static function bindProfileToTerminal($db, $user, $entity, $terminalId, $profileId, $bindingType, $priority = 1, $active = 1)
    {
        self::ensureSchema($db);

        $entity = (int) $entity;
        $terminalId = (int) $terminalId;
        $profileId = (int) $profileId;
        $priority = (int) $priority;
        if ($priority <= 0) {
            $priority = 1;
        }

        $bindingType = self::normalizeBindingType($bindingType);
        if ($bindingType === '') {
            throw new Exception(self::trans('TakeposDeviceBindingTypeInvalid', 'Binding type is invalid.'));
        }

        $terminalSql = "SELECT rowid, terminal_code FROM " . TakeposTerminalService::tableTerminal()
            . " WHERE entity = " . $entity . " AND rowid = " . $terminalId . " LIMIT 1";
        $terminalRes = $db->query($terminalSql);
        $terminal = ($terminalRes ? $db->fetch_object($terminalRes) : null);
        if (!$terminal) {
            throw new Exception(self::trans('TakeposDeviceBindingTerminalNotFound', 'Terminal not found for device binding.'));
        }

        $profile = self::getProfileById($db, $entity, $profileId);
        if (!$profile) {
            throw new Exception(self::trans('TakeposDeviceBindingProfileNotFound', 'Device profile not found for binding.'));
        }

        $active = ((int) $active > 0 ? 1 : 0);

        $existsSql = "SELECT rowid FROM " . self::tableBinding()
            . " WHERE entity = " . $entity
            . " AND fk_terminal = " . $terminalId
            . " AND binding_type = '" . $db->escape($bindingType) . "'"
            . " AND priority = " . $priority
            . " LIMIT 1";
        $existsRes = $db->query($existsSql);
        $existing = ($existsRes ? $db->fetch_object($existsRes) : null);

        if ($existing) {
            $sql = "UPDATE " . self::tableBinding() . " SET"
                . " fk_device_profile = " . $profileId
                . ", active = " . $active
                . " WHERE rowid = " . ((int) $existing->rowid);
            if (!$db->query($sql)) {
                throw new Exception($db->lasterror());
            }
            $bindingId = (int) $existing->rowid;
        } else {
            $sql = "INSERT INTO " . self::tableBinding() . " (entity, fk_terminal, fk_device_profile, binding_type, priority, active, date_creation) VALUES ("
                . $entity . ", " . $terminalId . ", " . $profileId . ", '" . $db->escape($bindingType) . "', " . $priority . ", " . $active . ", " . self::nowSql($db) . ")";
            if (!$db->query($sql)) {
                throw new Exception($db->lasterror());
            }
            $bindingId = (int) $db->last_insert_id(self::tableBinding());
        }

        self::safeAudit(
            $db,
            $user,
            'device_binding_changed',
            TakeposAudit::SEVERITY_WARNING,
            array(
                'binding_id' => $bindingId,
                'terminal_id' => $terminalId,
                'terminal_code' => (string) $terminal->terminal_code,
                'profile_id' => $profileId,
                'binding_type' => $bindingType,
                'priority' => $priority,
                'active' => $active,
            ),
            'Device binding changed',
            'terminal',
            $terminalId
        );

        return $bindingId;
    }

    public static function listBindings($db, $entity, $terminalId = 0, $activeOnly = false)
    {
        self::ensureSchema($db);

        $sql = "SELECT b.rowid, b.entity, b.fk_terminal, b.fk_device_profile, b.binding_type, b.priority, b.active, b.date_creation,"
            . " t.terminal_code, t.label AS terminal_label,"
            . " p.device_code, p.label AS profile_label, p.device_type, p.settings_json"
            . " FROM " . self::tableBinding() . " b"
            . " INNER JOIN " . TakeposTerminalService::tableTerminal() . " t ON t.rowid = b.fk_terminal AND t.entity = b.entity"
            . " INNER JOIN " . self::tableProfile() . " p ON p.rowid = b.fk_device_profile AND p.entity = b.entity"
            . " WHERE b.entity = " . ((int) $entity);

        if ((int) $terminalId > 0) {
            $sql .= " AND b.fk_terminal = " . ((int) $terminalId);
        }
        if ($activeOnly) {
            $sql .= " AND b.active = 1";
        }

        $sql .= " ORDER BY t.terminal_code ASC, b.binding_type ASC, b.priority ASC";

        $rows = array();
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $rows[] = $obj;
            }
        }

        return $rows;
    }

    public static function terminalDeviceSummary($db, $entity, $terminalCode)
    {
        self::ensureSchema($db);

        $terminal = TakeposTerminalService::getTerminalByCode($db, (int) $entity, (string) $terminalCode);
        if (!$terminal) {
            return array();
        }

        $rows = self::listBindings($db, (int) $entity, (int) $terminal->rowid, true);
        $summary = array();
        foreach ($rows as $row) {
            $type = (string) $row->binding_type;
            if (!isset($summary[$type])) {
                $summary[$type] = array();
            }
            $summary[$type][] = array(
                'binding_id' => (int) $row->rowid,
                'priority' => (int) $row->priority,
                'profile_id' => (int) $row->fk_device_profile,
                'device_code' => (string) $row->device_code,
                'label' => (string) $row->profile_label,
                'device_type' => (string) $row->device_type,
            );
        }

        return $summary;
    }

    public static function sendDisplayTest($db, $user, $entity, $terminalId, $message)
    {
        self::ensureSchema($db);

        $terminalId = (int) $terminalId;
        if ($terminalId <= 0) {
            throw new Exception(self::trans('TakeposDeviceDisplayTerminalRequired', 'Terminal is required for display test.'));
        }

        $msg = trim((string) $message);
        if ($msg === '') {
            $msg = 'TakePOS display test';
        }

        $bindings = self::listBindings($db, (int) $entity, $terminalId, true);
        $displayProfiles = array();
        foreach ($bindings as $binding) {
            if ((string) $binding->binding_type === self::BINDING_DISPLAY) {
                $displayProfiles[] = array(
                    'binding_id' => (int) $binding->rowid,
                    'profile_id' => (int) $binding->fk_device_profile,
                    'device_code' => (string) $binding->device_code,
                    'profile_label' => (string) $binding->profile_label,
                );
            }
        }

        self::safeAudit(
            $db,
            $user,
            'display_test_sent',
            TakeposAudit::SEVERITY_INFO,
            array(
                'terminal_id' => $terminalId,
                'message' => $msg,
                'targets' => $displayProfiles,
            ),
            'Display test sent',
            'terminal',
            $terminalId
        );

        return array(
            'terminal_id' => $terminalId,
            'message' => $msg,
            'target_profiles' => $displayProfiles,
        );
    }
}
