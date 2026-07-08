<?php
require_once __DIR__ . '/TakeposMigration.class.php';
require_once __DIR__ . '/TakeposAudit.class.php';

/**
 * Store governance service (stores + user-store assignments).
 */
class TakeposStoreService
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

    public static function tableStore()
    {
        return MAIN_DB_PREFIX . 'takepos_store';
    }

    public static function tableUserStore()
    {
        return MAIN_DB_PREFIX . 'takepos_user_store';
    }

    public static function ensureSchema($db)
    {
        $ok = true;

        $storeTable = self::tableStore();
        $ok = $ok && TakeposMigration::ensureTable($db, $storeTable, "CREATE TABLE " . $storeTable . " ("
            . " rowid INT AUTO_INCREMENT PRIMARY KEY,"
            . " entity INT NOT NULL DEFAULT 1,"
            . " code VARCHAR(32) NOT NULL,"
            . " label VARCHAR(128) NOT NULL,"
            . " description TEXT NULL,"
            . " warehouse_id INT NULL,"
            . " active TINYINT(1) NOT NULL DEFAULT 1,"
            . " date_creation DATETIME NOT NULL,"
            . " tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
            . " UNIQUE KEY uk_takepos_store_entity_code (entity, code),"
            . " KEY idx_takepos_store_entity_active (entity, active)"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $userStoreTable = self::tableUserStore();
        $ok = $ok && TakeposMigration::ensureTable($db, $userStoreTable, "CREATE TABLE " . $userStoreTable . " ("
            . " rowid INT AUTO_INCREMENT PRIMARY KEY,"
            . " entity INT NOT NULL DEFAULT 1,"
            . " fk_user INT NOT NULL,"
            . " fk_store INT NOT NULL,"
            . " role_in_store VARCHAR(32) NULL,"
            . " active TINYINT(1) NOT NULL DEFAULT 1,"
            . " date_creation DATETIME NOT NULL,"
            . " tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
            . " UNIQUE KEY uk_takepos_user_store (entity, fk_user, fk_store),"
            . " KEY idx_takepos_user_store_store (entity, fk_store, active),"
            . " KEY idx_takepos_user_store_user (entity, fk_user, active)"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if (!$ok) {
            return false;
        }

        $storeColumns = array(
            'entity' => "INT NOT NULL DEFAULT 1",
            'code' => "VARCHAR(32) NOT NULL",
            'label' => "VARCHAR(128) NOT NULL",
            'description' => "TEXT NULL",
            'warehouse_id' => "INT NULL",
            'active' => "TINYINT(1) NOT NULL DEFAULT 1",
            'date_creation' => "DATETIME NOT NULL",
            'tms' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        );
        foreach ($storeColumns as $column => $definition) {
            if (!TakeposMigration::ensureColumn($db, $storeTable, $column, $definition)) {
                return false;
            }
        }

        if (!TakeposMigration::ensureIndex($db, $storeTable, 'uk_takepos_store_entity_code', '(entity, code)', 'UNIQUE')) {
            return false;
        }
        if (!TakeposMigration::ensureIndex($db, $storeTable, 'idx_takepos_store_entity_active', '(entity, active)')) {
            return false;
        }

        $userStoreColumns = array(
            'entity' => "INT NOT NULL DEFAULT 1",
            'fk_user' => "INT NOT NULL",
            'fk_store' => "INT NOT NULL",
            'role_in_store' => "VARCHAR(32) NULL",
            'active' => "TINYINT(1) NOT NULL DEFAULT 1",
            'date_creation' => "DATETIME NOT NULL",
            'tms' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        );
        foreach ($userStoreColumns as $column => $definition) {
            if (!TakeposMigration::ensureColumn($db, $userStoreTable, $column, $definition)) {
                return false;
            }
        }

        if (!TakeposMigration::ensureIndex($db, $userStoreTable, 'uk_takepos_user_store', '(entity, fk_user, fk_store)', 'UNIQUE')) {
            return false;
        }
        if (!TakeposMigration::ensureIndex($db, $userStoreTable, 'idx_takepos_user_store_store', '(entity, fk_store, active)')) {
            return false;
        }
        if (!TakeposMigration::ensureIndex($db, $userStoreTable, 'idx_takepos_user_store_user', '(entity, fk_user, active)')) {
            return false;
        }

        return true;
    }
    public static function listStores($db, $entity, $activeOnly = false)
    {
        self::ensureSchema($db);

        $sql = "SELECT rowid, entity, code, label, description, warehouse_id, active, date_creation, tms";
        $sql .= " FROM " . self::tableStore();
        $sql .= " WHERE entity = " . ((int) $entity);
        if ($activeOnly) {
            $sql .= " AND active = 1";
        }
        $sql .= " ORDER BY code ASC";

        $rows = array();
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $rows[] = $obj;
            }
        }

        return $rows;
    }

    public static function getStore($db, $entity, $storeId)
    {
        self::ensureSchema($db);

        $sql = "SELECT rowid, entity, code, label, description, warehouse_id, active";
        $sql .= " FROM " . self::tableStore();
        $sql .= " WHERE entity = " . ((int) $entity) . " AND rowid = " . ((int) $storeId);
        $sql .= " LIMIT 1";

        $resql = $db->query($sql);
        return ($resql ? $db->fetch_object($resql) : null);
    }

    public static function createStore($db, $user, $entity, $code, $label, $description = '', $warehouseId = 0)
    {
        self::ensureSchema($db);

        $code = strtoupper(trim((string) $code));
        $label = trim((string) $label);
        if ($code === '' || $label === '') {
            throw new Exception(self::trans('TakeposStoreErrorCodeNameRequired', 'Store code and name are required.'));
        }
        if (!preg_match('/^[A-Z0-9_-]{2,32}$/', $code)) {
            throw new Exception(self::trans('TakeposStoreErrorCodeFormatInvalid', 'Store code format is invalid.'));
        }

        $sql = "INSERT INTO " . self::tableStore() . " (entity, code, label, description, warehouse_id, active, date_creation) VALUES (";
        $sql .= ((int) $entity) . ", '" . $db->escape($code) . "', '" . $db->escape($label) . "', ";
        $sql .= ($description !== '' ? "'" . $db->escape($description) . "'" : 'NULL') . ", ";
        $sql .= ((int) $warehouseId > 0 ? (int) $warehouseId : 'NULL') . ", 1, '" . $db->escape(dol_print_date(dol_now(), 'dayhourlog')) . "')";

        if (!$db->query($sql)) {
            throw new Exception($db->lasterror());
        }

        $storeId = (int) $db->last_insert_id(self::tableStore());

        TakeposAudit::logEvent(
            $db,
            $user,
            'store_created',
            TakeposAudit::SEVERITY_INFO,
            array('store_id' => $storeId, 'store_code' => $code, 'store_label' => $label),
            self::trans('TakeposStoreCreatedAudit', 'Store created'),
            'store',
            $storeId
        );

        return $storeId;
    }

    public static function updateStore($db, $user, $entity, $storeId, $code, $label, $description = '', $warehouseId = 0, $active = 1)
    {
        self::ensureSchema($db);

        $store = self::getStore($db, $entity, $storeId);
        if (!$store) {
            throw new Exception(self::trans('TakeposAdminStoreNotFound', 'Store not found.'));
        }

        $code = strtoupper(trim((string) $code));
        $label = trim((string) $label);
        if ($code === '' || $label === '') {
            throw new Exception(self::trans('TakeposStoreErrorCodeNameRequired', 'Store code and name are required.'));
        }

        $sql = "UPDATE " . self::tableStore() . " SET";
        $sql .= " code='" . $db->escape($code) . "'";
        $sql .= ", label='" . $db->escape($label) . "'";
        $sql .= ", description=" . ($description !== '' ? "'" . $db->escape($description) . "'" : 'NULL');
        $sql .= ", warehouse_id=" . ((int) $warehouseId > 0 ? (int) $warehouseId : 'NULL');
        $sql .= ", active=" . ((int) $active > 0 ? 1 : 0);
        $sql .= " WHERE entity = " . ((int) $entity) . " AND rowid = " . ((int) $storeId);

        if (!$db->query($sql)) {
            throw new Exception($db->lasterror());
        }

        TakeposAudit::logEvent(
            $db,
            $user,
            ((int) $active > 0 ? 'store_updated' : 'store_disabled'),
            TakeposAudit::SEVERITY_WARNING,
            array('store_id' => (int) $storeId, 'store_code' => $code, 'store_label' => $label),
            ((int) $active > 0 ? self::trans('TakeposStoreUpdatedAudit', 'Store updated') : self::trans('TakeposStoreDisabledAudit', 'Store disabled')),
            'store',
            (int) $storeId
        );

        return true;
    }

    public static function setUserStores($db, $user, $entity, $targetUserId, $storeIds, $roleInStore = '')
    {
        self::ensureSchema($db);

        $clean = array();
        foreach ((array) $storeIds as $sid) {
            $sid = (int) $sid;
            if ($sid > 0) {
                $clean[] = $sid;
            }
        }
        $clean = array_values(array_unique($clean));

        $db->begin();

        $sqlDelete = "DELETE FROM " . self::tableUserStore() . " WHERE entity = " . ((int) $entity) . " AND fk_user = " . ((int) $targetUserId);
        if (!$db->query($sqlDelete)) {
            $db->rollback();
            throw new Exception($db->lasterror());
        }

        foreach ($clean as $storeId) {
            $store = self::getStore($db, $entity, $storeId);
            if (!$store || empty($store->active)) {
                continue;
            }

            $sql = "INSERT INTO " . self::tableUserStore() . " (entity, fk_user, fk_store, role_in_store, active, date_creation) VALUES (";
            $sql .= ((int) $entity) . ", " . ((int) $targetUserId) . ", " . ((int) $storeId) . ", ";
            $sql .= ($roleInStore !== '' ? "'" . $db->escape($roleInStore) . "'" : 'NULL') . ", 1, '" . $db->escape(dol_print_date(dol_now(), 'dayhourlog')) . "')";
            if (!$db->query($sql)) {
                $db->rollback();
                throw new Exception($db->lasterror());
            }
        }

        $db->commit();

        TakeposAudit::logEvent(
            $db,
            $user,
            'user_store_assignment_changed',
            TakeposAudit::SEVERITY_WARNING,
            array('target_user_id' => (int) $targetUserId, 'store_ids' => $clean),
            self::trans('TakeposStoreUserAssignmentUpdated', 'User store assignment updated'),
            'user',
            (int) $targetUserId
        );

        return true;
    }

    public static function getUserStoreIds($db, $entity, $userId)
    {
        self::ensureSchema($db);

        $sql = "SELECT us.fk_store";
        $sql .= " FROM " . self::tableUserStore() . " us";
        $sql .= " INNER JOIN " . self::tableStore() . " s ON s.rowid = us.fk_store AND s.entity = us.entity";
        $sql .= " WHERE us.entity = " . ((int) $entity);
        $sql .= " AND us.fk_user = " . ((int) $userId);
        $sql .= " AND us.active = 1";
        $sql .= " AND s.active = 1";

        $ids = array();
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $ids[] = (int) $obj->fk_store;
            }
        }

        return array_values(array_unique($ids));
    }

    public static function userCanAccessStore($db, $user, $storeId, $entity = null)
    {
        if ((int) $storeId <= 0) {
            return true;
        }

        if (!is_object($user) || empty($user->id)) {
            return false;
        }

        if (!empty($user->admin)) {
            return true;
        }

        $entity = ($entity !== null ? (int) $entity : (!empty($user->entity) ? (int) $user->entity : 1));
        $allowed = self::getUserStoreIds($db, $entity, (int) $user->id);

        return in_array((int) $storeId, $allowed, true);
    }

    public static function enforceStoreRestrictionEnabled($db)
    {
        $value = getDolGlobalString('TAKEPOS_ENFORCE_STORE_RESTRICTIONS', '0');
        return ((string) $value === '1' || strtolower((string) $value) === 'yes');
    }
}
