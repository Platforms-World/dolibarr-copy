<?php
require_once __DIR__ . '/TakeposMigration.class.php';
require_once __DIR__ . '/TakeposAudit.class.php';
require_once __DIR__ . '/TakeposInputValidator.class.php';
require_once __DIR__ . '/TakeposDeviceService.class.php';

/**
 * Receipt printer profile service.
 */
class TakeposPrinterService
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

    public static function tablePrinterProfile()
    {
        return MAIN_DB_PREFIX . 'takepos_printer_profile';
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
                dol_syslog('[TakePOS][Printer] Audit failure: ' . $e->getMessage(), LOG_WARNING);
            }
        }
    }

    public static function allowedDrivers()
    {
        return array('raw', 'network', 'cups', 'browser');
    }

    public static function normalizeProfileCode($code)
    {
        return TakeposDeviceService::normalizeCode($code);
    }

    public static function normalizeDriverType($driver)
    {
        $driver = strtolower(trim((string) $driver));
        if (!in_array($driver, self::allowedDrivers(), true)) {
            return 'raw';
        }

        return $driver;
    }

    public static function ensureSchema($db)
    {
        TakeposDeviceService::ensureSchema($db);

        $table = self::tablePrinterProfile();
        $ok = TakeposMigration::ensureTable($db, $table, "CREATE TABLE " . $table . " ("
            . " rowid INT AUTO_INCREMENT PRIMARY KEY,"
            . " entity INT NOT NULL DEFAULT 1,"
            . " profile_code VARCHAR(48) NOT NULL,"
            . " label VARCHAR(128) NOT NULL,"
            . " driver_type VARCHAR(32) NOT NULL DEFAULT 'raw',"
            . " target_uri VARCHAR(255) NULL,"
            . " copies INT NOT NULL DEFAULT 1,"
            . " settings_json LONGTEXT NULL,"
            . " fk_device_profile INT NULL,"
            . " active TINYINT(1) NOT NULL DEFAULT 1,"
            . " date_creation DATETIME NOT NULL,"
            . " tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
            . " UNIQUE KEY uk_takepos_printer_code (entity, profile_code),"
            . " KEY idx_takepos_printer_active (entity, active),"
            . " KEY idx_takepos_printer_device (entity, fk_device_profile)"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if (!$ok) {
            return false;
        }

        $columns = array(
            'entity' => "INT NOT NULL DEFAULT 1",
            'profile_code' => "VARCHAR(48) NOT NULL",
            'label' => "VARCHAR(128) NOT NULL",
            'driver_type' => "VARCHAR(32) NOT NULL DEFAULT 'raw'",
            'target_uri' => "VARCHAR(255) NULL",
            'copies' => "INT NOT NULL DEFAULT 1",
            'settings_json' => "LONGTEXT NULL",
            'fk_device_profile' => "INT NULL",
            'active' => "TINYINT(1) NOT NULL DEFAULT 1",
            'date_creation' => "DATETIME NOT NULL",
            'tms' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        );
        foreach ($columns as $column => $definition) {
            if (!TakeposMigration::ensureColumn($db, $table, $column, $definition)) {
                return false;
            }
        }

        TakeposMigration::ensureIndex($db, $table, 'uk_takepos_printer_code', '(entity, profile_code)', 'UNIQUE');
        TakeposMigration::ensureIndex($db, $table, 'idx_takepos_printer_active', '(entity, active)');
        TakeposMigration::ensureIndex($db, $table, 'idx_takepos_printer_device', '(entity, fk_device_profile)');

        return true;
    }

    public static function listProfiles($db, $entity, $activeOnly = false)
    {
        self::ensureSchema($db);

        $sql = "SELECT rowid, entity, profile_code, label, driver_type, target_uri, copies, settings_json, fk_device_profile, active, date_creation"
            . " FROM " . self::tablePrinterProfile()
            . " WHERE entity = " . ((int) $entity);
        if ($activeOnly) {
            $sql .= " AND active = 1";
        }
        $sql .= " ORDER BY profile_code ASC";

        $rows = array();
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $rows[] = $obj;
            }
        }

        return $rows;
    }

    public static function getProfileById($db, $entity, $profileId)
    {
        self::ensureSchema($db);

        $sql = "SELECT rowid, entity, profile_code, label, driver_type, target_uri, copies, settings_json, fk_device_profile, active"
            . " FROM " . self::tablePrinterProfile()
            . " WHERE entity = " . ((int) $entity)
            . " AND rowid = " . ((int) $profileId)
            . " LIMIT 1";
        $resql = $db->query($sql);
        return ($resql ? $db->fetch_object($resql) : null);
    }

    private static function normalizeCopies($copies)
    {
        $parsed = null;
        if (!TakeposInputValidator::parsePositiveInteger($copies, $parsed, false)) {
            return 1;
        }

        $parsed = (int) $parsed;
        if ($parsed <= 0) {
            $parsed = 1;
        }
        if ($parsed > 20) {
            $parsed = 20;
        }

        return $parsed;
    }

    public static function saveProfile($db, $user, $entity, $profileId, $profileCode, $label, $driverType, $targetUri = '', $copies = 1, $settings = '{}', $active = 1)
    {
        self::ensureSchema($db);

        $entity = (int) $entity;
        if ($entity <= 0) {
            $entity = !empty($user->entity) ? (int) $user->entity : 1;
        }

        $profileCode = self::normalizeProfileCode($profileCode);
        if ($profileCode === '') {
            throw new Exception(self::trans('TakeposPrinterProfileCodeInvalid', 'Printer profile code is invalid.'));
        }

        $label = trim((string) $label);
        if ($label === '') {
            throw new Exception(self::trans('TakeposPrinterProfileLabelRequired', 'Printer profile label is required.'));
        }

        $driverType = self::normalizeDriverType($driverType);
        $targetUri = trim((string) $targetUri);
        $copies = self::normalizeCopies($copies);

        $deviceProfileId = TakeposDeviceService::saveProfile(
            $db,
            $user,
            $entity,
            0,
            $profileCode,
            $label,
            TakeposDeviceService::TYPE_PRINTER,
            $settings,
            $active
        );

        $settingsJson = '{}';
        if (is_array($settings)) {
            $settingsJson = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($settingsJson === false) {
                $settingsJson = '{}';
            }
        } else {
            $raw = trim((string) $settings);
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $settingsJson = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if ($settingsJson === false) {
                        $settingsJson = '{}';
                    }
                }
            }
        }

        $active = ((int) $active > 0 ? 1 : 0);
        $profileId = (int) $profileId;

        if ($profileId > 0) {
            $current = self::getProfileById($db, $entity, $profileId);
            if (!$current) {
                throw new Exception(self::trans('TakeposPrinterProfileNotFound', 'Printer profile not found.'));
            }

            $sql = "UPDATE " . self::tablePrinterProfile() . " SET"
                . " profile_code = '" . $db->escape($profileCode) . "'"
                . ", label = '" . $db->escape($label) . "'"
                . ", driver_type = '" . $db->escape($driverType) . "'"
                . ", target_uri = " . ($targetUri !== '' ? "'" . $db->escape($targetUri) . "'" : 'NULL')
                . ", copies = " . $copies
                . ", settings_json = '" . $db->escape($settingsJson) . "'"
                . ", fk_device_profile = " . ((int) $deviceProfileId)
                . ", active = " . $active
                . " WHERE rowid = " . $profileId
                . " AND entity = " . $entity;
            if (!$db->query($sql)) {
                throw new Exception($db->lasterror());
            }
        } else {
            $sql = "INSERT INTO " . self::tablePrinterProfile() . " (entity, profile_code, label, driver_type, target_uri, copies, settings_json, fk_device_profile, active, date_creation) VALUES ("
                . $entity . ", '" . $db->escape($profileCode) . "', '" . $db->escape($label) . "', '" . $db->escape($driverType) . "', "
                . ($targetUri !== '' ? "'" . $db->escape($targetUri) . "'" : 'NULL') . ", "
                . $copies . ", '" . $db->escape($settingsJson) . "', " . ((int) $deviceProfileId) . ", " . $active . ", " . self::nowSql($db) . ")";
            if (!$db->query($sql)) {
                throw new Exception($db->lasterror());
            }
            $profileId = (int) $db->last_insert_id(self::tablePrinterProfile());
        }

        return $profileId;
    }

    public static function sendTestPrint($db, $user, $entity, $printerProfileId, $content, $terminalId = 0)
    {
        self::ensureSchema($db);

        $profile = self::getProfileById($db, (int) $entity, (int) $printerProfileId);
        if (!$profile || empty($profile->active)) {
            throw new Exception(self::trans('TakeposPrinterProfileInvalidInactive', 'Printer profile is invalid or inactive.'));
        }

        $payload = trim((string) $content);
        if ($payload === '') {
            $payload = 'TakePOS printer test';
        }

        self::safeAudit(
            $db,
            $user,
            'printer_test_sent',
            TakeposAudit::SEVERITY_INFO,
            array(
                'printer_profile_id' => (int) $profile->rowid,
                'profile_code' => (string) $profile->profile_code,
                'driver_type' => (string) $profile->driver_type,
                'target_uri' => (string) $profile->target_uri,
                'terminal_id' => (int) $terminalId,
                'content_preview' => substr($payload, 0, 120),
            ),
            'Printer test sent',
            'printer_profile',
            (int) $profile->rowid
        );

        return array(
            'printer_profile_id' => (int) $profile->rowid,
            'profile_code' => (string) $profile->profile_code,
            'driver_type' => (string) $profile->driver_type,
            'target_uri' => (string) $profile->target_uri,
            'terminal_id' => (int) $terminalId,
            'queued' => true,
        );
    }
}
