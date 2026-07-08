<?php
require_once __DIR__ . '/TakeposMigration.class.php';
require_once __DIR__ . '/TakeposAudit.class.php';
require_once __DIR__ . '/TakeposShiftService.class.php';

/**
 * Cash movement and reconciliation helper service.
 */
class TakeposCashService
{
    const TYPE_PAID_IN = 'paid_in';
    const TYPE_PAID_OUT = 'paid_out';
    const TYPE_SAFE_DROP = 'safe_drop';

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

    public static function tableCashMovement()
    {
        return MAIN_DB_PREFIX . 'takepos_cash_movement';
    }

    public static function ensureSchema($db)
    {
        TakeposShiftService::ensureSchema($db);

        $table = self::tableCashMovement();
        $ok = TakeposMigration::ensureTable($db, $table, "CREATE TABLE " . $table . " ("
            . " rowid INT AUTO_INCREMENT PRIMARY KEY,"
            . " entity INT NOT NULL DEFAULT 1,"
            . " fk_shift INT NOT NULL,"
            . " movement_type VARCHAR(32) NOT NULL,"
            . " amount DECIMAL(24,8) NOT NULL,"
            . " reason_code VARCHAR(64) NULL,"
            . " reason_text VARCHAR(255) NULL,"
            . " note TEXT NULL,"
            . " fk_created_by INT NOT NULL,"
            . " fk_approved_by INT NULL,"
            . " date_creation DATETIME NOT NULL,"
            . " tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
            . " KEY idx_takepos_cash_entity_shift (entity, fk_shift),"
            . " KEY idx_takepos_cash_type_date (entity, movement_type, date_creation),"
            . " KEY idx_takepos_cash_created_by (entity, fk_created_by)"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if (!$ok) {
            return false;
        }

        $columns = array(
            'entity' => "INT NOT NULL DEFAULT 1",
            'fk_shift' => "INT NOT NULL",
            'movement_type' => "VARCHAR(32) NOT NULL",
            'amount' => "DECIMAL(24,8) NOT NULL",
            'reason_code' => "VARCHAR(64) NULL",
            'reason_text' => "VARCHAR(255) NULL",
            'note' => "TEXT NULL",
            'fk_created_by' => "INT NOT NULL",
            'fk_approved_by' => "INT NULL",
            'date_creation' => "DATETIME NOT NULL",
            'tms' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        );
        foreach ($columns as $column => $definition) {
            if (!TakeposMigration::ensureColumn($db, $table, $column, $definition)) {
                return false;
            }
        }

        if (!TakeposMigration::ensureIndex($db, $table, 'idx_takepos_cash_entity_shift', '(entity, fk_shift)')) {
            return false;
        }
        if (!TakeposMigration::ensureIndex($db, $table, 'idx_takepos_cash_type_date', '(entity, movement_type, date_creation)')) {
            return false;
        }
        if (!TakeposMigration::ensureIndex($db, $table, 'idx_takepos_cash_created_by', '(entity, fk_created_by)')) {
            return false;
        }

        return true;
    }
    public static function isMovementTypeAllowed($type)
    {
        return in_array((string) $type, array(self::TYPE_PAID_IN, self::TYPE_PAID_OUT, self::TYPE_SAFE_DROP), true);
    }

    public static function createMovement($db, $user, $shiftId, $movementType, $amount, $reason, $note = '', $approvedBy = 0)
    {
        self::ensureSchema($db);

        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        $shiftId = (int) $shiftId;
        $movementType = trim((string) $movementType);
        $amount = (float) $amount;
        $reason = trim((string) $reason);

        TakeposAudit::logEvent(
            $db,
            $user,
            'cash_movement_attempt',
            TakeposAudit::SEVERITY_INFO,
            array('shift_id' => $shiftId, 'movement_type' => $movementType, 'amount' => $amount),
            'Cash movement requested'
        );

        if (!self::isMovementTypeAllowed($movementType)) {
            TakeposAudit::logEvent($db, $user, 'cash_movement_rejected', TakeposAudit::SEVERITY_WARNING, array('reason' => 'invalid_type', 'movement_type' => $movementType), 'Cash movement rejected');
            throw new Exception(self::trans('TakeposCashInvalidMovementType', 'Invalid cash movement type.'));
        }

        if ($amount <= 0) {
            TakeposAudit::logEvent($db, $user, 'cash_movement_rejected', TakeposAudit::SEVERITY_WARNING, array('reason' => 'invalid_amount', 'amount' => $amount), 'Cash movement rejected');
            throw new Exception(self::trans('TakeposExpenseErrorAmountPositive', 'Cash movement amount must be greater than zero.'));
        }

        $shift = TakeposShiftService::getShiftById($db, $entity, $shiftId);
        if (!$shift || !in_array((string) $shift->status, array(TakeposShiftService::STATUS_OPEN, TakeposShiftService::STATUS_CLOSING_PENDING), true)) {
            TakeposAudit::logEvent($db, $user, 'cash_movement_rejected', TakeposAudit::SEVERITY_WARNING, array('reason' => 'shift_not_active', 'shift_id' => $shiftId), 'Cash movement rejected');
            throw new Exception(self::trans('TakeposCashShiftRequired', 'Active shift is required for cash movement.'));
        }

        $sql = "INSERT INTO " . self::tableCashMovement() . " (entity, fk_shift, movement_type, amount, reason_code, reason_text, note, fk_created_by, fk_approved_by, date_creation) VALUES (";
        $sql .= $entity . ", " . $shiftId . ", '" . $db->escape($movementType) . "', " . $amount . ", ";
        $sql .= ($reason !== '' ? "'" . $db->escape($reason) . "'" : 'NULL') . ", ";
        $sql .= ($reason !== '' ? "'" . $db->escape($reason) . "'" : 'NULL') . ", ";
        $sql .= ($note !== '' ? "'" . $db->escape($note) . "'" : 'NULL') . ", ";
        $sql .= ((int) $user->id) . ", " . ((int) $approvedBy > 0 ? (int) $approvedBy : 'NULL') . ", '" . $db->escape(dol_print_date(dol_now(), 'dayhourlog')) . "')";

        if (!$db->query($sql)) {
            TakeposAudit::logEvent($db, $user, 'cash_movement_rejected', TakeposAudit::SEVERITY_WARNING, array('reason' => 'db_error', 'error' => $db->lasterror()), 'Cash movement rejected');
            throw new Exception($db->lasterror());
        }

        $movementId = (int) $db->last_insert_id(self::tableCashMovement());

        TakeposAudit::logEvent(
            $db,
            $user,
            'cash_movement_created',
            TakeposAudit::SEVERITY_INFO,
            array('movement_id' => $movementId, 'shift_id' => $shiftId, 'movement_type' => $movementType, 'amount' => $amount),
            'Cash movement created',
            'shift',
            $shiftId,
            $amount
        );

        return $movementId;
    }

    public static function listMovementsByShift($db, $entity, $shiftId, $limit = 300)
    {
        self::ensureSchema($db);

        $limit = max(1, min(1000, (int) $limit));
        $sql = "SELECT rowid, fk_shift, movement_type, amount, reason_code, reason_text, note, fk_created_by, fk_approved_by, date_creation";
        $sql .= " FROM " . self::tableCashMovement();
        $sql .= " WHERE entity = " . ((int) $entity) . " AND fk_shift = " . ((int) $shiftId);
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
}
