<?php
require_once __DIR__ . '/TakeposMigration.class.php';
require_once __DIR__ . '/TakeposAccess.class.php';
require_once __DIR__ . '/TakeposAudit.class.php';
require_once __DIR__ . '/TakeposStoreService.class.php';
require_once __DIR__ . '/TakeposTerminalService.class.php';
require_once __DIR__ . '/TakeposWebhookService.class.php';
require_once __DIR__ . '/TakeposBranchService.class.php';

/**
 * Shift lifecycle service for TakePOS.
 */
class TakeposShiftService
{
    const STATUS_OPEN = 'open';
    const STATUS_CLOSING_PENDING = 'closing_pending';
    const STATUS_CLOSED = 'closed';
    const STATUS_CANCELLED = 'cancelled';

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


    private static function safeWebhookEmit($db, $entity, $eventCode, $payload = array(), $user = null)
    {
        try {
            if (class_exists('TakeposWebhookService') && method_exists('TakeposWebhookService', 'emitEvent')) {
                TakeposWebhookService::emitEvent($db, (int) $entity, (string) $eventCode, (array) $payload, $user);
            }
        } catch (Throwable $e) {
            if (function_exists('dol_syslog')) {
                dol_syslog('[TakePOS][Shift] Webhook emit failure: ' . $e->getMessage(), LOG_WARNING);
            }
        }
    }
    public static function tableShift()
    {
        return MAIN_DB_PREFIX . 'takepos_shift';
    }

    public static function tableCashMovement()
    {
        return MAIN_DB_PREFIX . 'takepos_cash_movement';
    }

    public static function tableInvoiceShift()
    {
        return MAIN_DB_PREFIX . 'takepos_invoice_shift';
    }

    public static function tablePaymentCurrency()
    {
        return MAIN_DB_PREFIX . 'takepos_payment_currency';
    }

    public static function ensureSchema($db)
    {
        TakeposTerminalService::ensureSchema($db);

        $table = self::tableShift();
        $ok = TakeposMigration::ensureTable($db, $table, "CREATE TABLE " . $table . " ("
            . " rowid INT AUTO_INCREMENT PRIMARY KEY,"
            . " entity INT NOT NULL DEFAULT 1,"
            . " fk_store INT NULL,"
            . " fk_terminal INT NOT NULL,"
            . " fk_cashier_user INT NOT NULL,"
            . " shift_ref VARCHAR(64) NOT NULL,"
            . " status VARCHAR(20) NOT NULL DEFAULT 'open',"
            . " opening_float DECIMAL(24,8) NOT NULL DEFAULT 0,"
            . " expected_cash DECIMAL(24,8) NOT NULL DEFAULT 0,"
            . " counted_cash DECIMAL(24,8) NULL,"
            . " cash_difference DECIMAL(24,8) NULL,"
            . " total_cash_sales DECIMAL(24,8) NOT NULL DEFAULT 0,"
            . " total_card_sales DECIMAL(24,8) NOT NULL DEFAULT 0,"
            . " total_other_sales DECIMAL(24,8) NOT NULL DEFAULT 0,"
            . " total_paid_in DECIMAL(24,8) NOT NULL DEFAULT 0,"
            . " total_paid_out DECIMAL(24,8) NOT NULL DEFAULT 0,"
            . " total_safe_drop DECIMAL(24,8) NOT NULL DEFAULT 0,"
            . " date_open DATETIME NOT NULL,"
            . " date_close DATETIME NULL,"
            . " fk_opened_by INT NOT NULL,"
            . " fk_closed_by INT NULL,"
            . " fk_approved_by INT NULL,"
            . " notes TEXT NULL,"
            . " date_creation DATETIME NOT NULL,"
            . " tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
            . " KEY idx_takepos_shift_entity_status (entity, status),"
            . " KEY idx_takepos_shift_entity_terminal_status (entity, fk_terminal, status),"
            . " KEY idx_takepos_shift_entity_cashier_status (entity, fk_cashier_user, status),"
            . " KEY idx_takepos_shift_entity_store (entity, fk_store),"
            . " KEY idx_takepos_shift_open_close (date_open, date_close),"
            . " UNIQUE KEY uk_takepos_shift_ref (entity, shift_ref)"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if (!$ok) {
            return false;
        }

        $columns = array(
            'entity' => "INT NOT NULL DEFAULT 1",
            'fk_store' => "INT NULL",
            'fk_terminal' => "INT NOT NULL",
            'fk_cashier_user' => "INT NOT NULL",
            'shift_ref' => "VARCHAR(64) NOT NULL",
            'status' => "VARCHAR(20) NOT NULL DEFAULT 'open'",
            'opening_float' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
            'expected_cash' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
            'counted_cash' => "DECIMAL(24,8) NULL",
            'cash_difference' => "DECIMAL(24,8) NULL",
            'total_cash_sales' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
            'total_card_sales' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
            'total_other_sales' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
            'total_paid_in' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
            'total_paid_out' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
            'total_safe_drop' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
            'date_open' => "DATETIME NOT NULL",
            'date_close' => "DATETIME NULL",
            'fk_opened_by' => "INT NOT NULL",
            'fk_closed_by' => "INT NULL",
            'fk_approved_by' => "INT NULL",
            'notes' => "TEXT NULL",
            'date_creation' => "DATETIME NOT NULL",
            'tms' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        );
        foreach ($columns as $column => $definition) {
            if (!TakeposMigration::ensureColumn($db, $table, $column, $definition)) {
                return false;
            }
        }

        if (!TakeposMigration::ensureIndex($db, $table, 'idx_takepos_shift_entity_status', '(entity, status)')) {
            return false;
        }
        if (!TakeposMigration::ensureIndex($db, $table, 'idx_takepos_shift_entity_terminal_status', '(entity, fk_terminal, status)')) {
            return false;
        }
        if (!TakeposMigration::ensureIndex($db, $table, 'idx_takepos_shift_entity_cashier_status', '(entity, fk_cashier_user, status)')) {
            return false;
        }
        if (!TakeposMigration::ensureIndex($db, $table, 'idx_takepos_shift_entity_store', '(entity, fk_store)')) {
            return false;
        }
        if (!TakeposMigration::ensureIndex($db, $table, 'idx_takepos_shift_open_close', '(date_open, date_close)')) {
            return false;
        }
        if (!TakeposMigration::ensureIndex($db, $table, 'uk_takepos_shift_ref', '(entity, shift_ref)', 'UNIQUE')) {
            return false;
        }

        $invoiceShiftTable = self::tableInvoiceShift();
        if (!TakeposMigration::ensureTable($db, $invoiceShiftTable, "CREATE TABLE " . $invoiceShiftTable . " ("
            . " rowid INT AUTO_INCREMENT PRIMARY KEY,"
            . " entity INT NOT NULL DEFAULT 1,"
            . " fk_invoice INT NOT NULL,"
            . " fk_shift INT NOT NULL,"
            . " fk_terminal INT NULL,"
            . " fk_cashier_user INT NULL,"
            . " terminal_code VARCHAR(64) NULL,"
            . " date_creation DATETIME NOT NULL,"
            . " tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
            . " UNIQUE KEY uk_takepos_invoice_shift_invoice (entity, fk_invoice),"
            . " KEY idx_takepos_invoice_shift_shift (entity, fk_shift),"
            . " KEY idx_takepos_invoice_shift_terminal (entity, fk_terminal),"
            . " KEY idx_takepos_invoice_shift_cashier (entity, fk_cashier_user)"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) {
            return false;
        }
        $invoiceShiftColumns = array(
            'entity' => "INT NOT NULL DEFAULT 1",
            'fk_invoice' => "INT NOT NULL",
            'fk_shift' => "INT NOT NULL",
            'fk_terminal' => "INT NULL",
            'fk_cashier_user' => "INT NULL",
            'terminal_code' => "VARCHAR(64) NULL",
            'date_creation' => "DATETIME NOT NULL",
            'tms' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        );
        foreach ($invoiceShiftColumns as $column => $definition) {
            if (!TakeposMigration::ensureColumn($db, $invoiceShiftTable, $column, $definition)) {
                return false;
            }
        }
        if (!TakeposMigration::ensureIndex($db, $invoiceShiftTable, 'uk_takepos_invoice_shift_invoice', '(entity, fk_invoice)', 'UNIQUE')) {
            return false;
        }
        if (!TakeposMigration::ensureIndex($db, $invoiceShiftTable, 'idx_takepos_invoice_shift_shift', '(entity, fk_shift)')) {
            return false;
        }
        if (!TakeposMigration::ensureIndex($db, $invoiceShiftTable, 'idx_takepos_invoice_shift_terminal', '(entity, fk_terminal)')) {
            return false;
        }
        if (!TakeposMigration::ensureIndex($db, $invoiceShiftTable, 'idx_takepos_invoice_shift_cashier', '(entity, fk_cashier_user)')) {
            return false;
        }

        $paymentCurrencyTable = self::tablePaymentCurrency();
        if (!TakeposMigration::ensureTable($db, $paymentCurrencyTable, "CREATE TABLE " . $paymentCurrencyTable . " ("
            . " rowid INT AUTO_INCREMENT PRIMARY KEY,"
            . " entity INT NOT NULL DEFAULT 1,"
            . " fk_invoice INT NOT NULL,"
            . " fk_paiement INT NOT NULL,"
            . " payment_code VARCHAR(32) NULL,"
            . " base_currency VARCHAR(8) NOT NULL,"
            . " payment_currency VARCHAR(8) NOT NULL,"
            . " payment_rate DECIMAL(24,8) NOT NULL DEFAULT 1,"
            . " amount_base DECIMAL(24,8) NOT NULL DEFAULT 0,"
            . " amount_foreign DECIMAL(24,8) NOT NULL DEFAULT 0,"
            . " excess_base DECIMAL(24,8) NOT NULL DEFAULT 0,"
            . " excess_foreign DECIMAL(24,8) NOT NULL DEFAULT 0,"
            . " fk_user_author INT NULL,"
            . " date_creation DATETIME NOT NULL,"
            . " tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
            . " KEY idx_takepos_payment_currency_invoice (entity, fk_invoice),"
            . " KEY idx_takepos_payment_currency_payment (entity, fk_paiement),"
            . " KEY idx_takepos_payment_currency_code (entity, payment_currency)"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) {
            return false;
        }
        $paymentCurrencyColumns = array(
            'entity' => "INT NOT NULL DEFAULT 1",
            'fk_invoice' => "INT NOT NULL",
            'fk_paiement' => "INT NOT NULL",
            'payment_code' => "VARCHAR(32) NULL",
            'base_currency' => "VARCHAR(8) NOT NULL",
            'payment_currency' => "VARCHAR(8) NOT NULL",
            'payment_rate' => "DECIMAL(24,8) NOT NULL DEFAULT 1",
            'amount_base' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
            'amount_foreign' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
            'excess_base' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
            'excess_foreign' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
            'fk_user_author' => "INT NULL",
            'date_creation' => "DATETIME NOT NULL",
            'tms' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        );
        foreach ($paymentCurrencyColumns as $column => $definition) {
            if (!TakeposMigration::ensureColumn($db, $paymentCurrencyTable, $column, $definition)) {
                return false;
            }
        }
        if (!TakeposMigration::ensureIndex($db, $paymentCurrencyTable, 'idx_takepos_payment_currency_invoice', '(entity, fk_invoice)')) {
            return false;
        }
        if (!TakeposMigration::ensureIndex($db, $paymentCurrencyTable, 'idx_takepos_payment_currency_payment', '(entity, fk_paiement)')) {
            return false;
        }
        if (!TakeposMigration::ensureIndex($db, $paymentCurrencyTable, 'idx_takepos_payment_currency_code', '(entity, payment_currency)')) {
            return false;
        }

        $factureTable = MAIN_DB_PREFIX . 'facture';
        if (TakeposMigration::tableExists($db, $factureTable)) {
            if (!TakeposMigration::ensureColumn($db, $factureTable, 'fk_takepos_shift', 'INT NULL')) {
                return false;
            }
            if (!TakeposMigration::ensureIndex($db, $factureTable, 'idx_facture_takepos_shift', '(fk_takepos_shift)')) {
                return false;
            }
        }

        return true;
    }
    public static function requireOpenShiftForPayments()
    {
        $value = getDolGlobalString('TAKEPOS_REQUIRE_OPEN_SHIFT_FOR_PAYMENTS', '1');
        return ((string) $value === '1' || strtolower((string) $value) === 'yes');
    }

    public static function requireShiftForCashMovements()
    {
        $value = getDolGlobalString('TAKEPOS_REQUIRE_SHIFT_FOR_CASH_MOVEMENTS', '1');
        return ((string) $value === '1' || strtolower((string) $value) === 'yes');
    }

    public static function discrepancyThresholdAmount()
    {
        return (float) price2num(getDolGlobalString('TAKEPOS_DISCREPANCY_THRESHOLD_AMOUNT', '0'), 'MT');
    }

    public static function blindCloseEnabled()
    {
        $value = getDolGlobalString('TAKEPOS_BLIND_CLOSE_ENABLED', '0');
        return ((string) $value === '1' || strtolower((string) $value) === 'yes');
    }

    public static function generateShiftRef($entity, $terminalCode)
    {
        $datePart = dol_print_date(dol_now(), '%Y%m%d-%H%M%S');
        $term = preg_replace('/[^A-Z0-9_-]/', '', strtoupper((string) $terminalCode));
        if ($term === '') {
            $term = 'T1';
        }
        return 'SH-' . ((int) $entity) . '-' . $term . '-' . $datePart;
    }

    public static function getShiftById($db, $entity, $shiftId)
    {
        self::ensureSchema($db);

        $sql = "SELECT s.*, t.terminal_code, t.label AS terminal_label, st.label AS store_label";
        $sql .= " FROM " . self::tableShift() . " s";
        $sql .= " LEFT JOIN " . TakeposTerminalService::tableTerminal() . " t ON t.rowid = s.fk_terminal";
        $sql .= " LEFT JOIN " . TakeposStoreService::tableStore() . " st ON st.rowid = s.fk_store";
        $sql .= " WHERE s.entity = " . ((int) $entity) . " AND s.rowid = " . ((int) $shiftId);
        $sql .= " LIMIT 1";

        $resql = $db->query($sql);
        return ($resql ? $db->fetch_object($resql) : null);
    }

    public static function getActiveShiftForTerminal($db, $entity, $terminalId)
    {
        self::ensureSchema($db);

        $sql = "SELECT s.*, t.terminal_code, t.label AS terminal_label, st.label AS store_label";
        $sql .= " FROM " . self::tableShift() . " s";
        $sql .= " LEFT JOIN " . TakeposTerminalService::tableTerminal() . " t ON t.rowid = s.fk_terminal";
        $sql .= " LEFT JOIN " . TakeposStoreService::tableStore() . " st ON st.rowid = s.fk_store";
        $sql .= " WHERE s.entity = " . ((int) $entity) . " AND s.fk_terminal = " . ((int) $terminalId);
        $sql .= " AND s.status IN ('" . self::STATUS_OPEN . "', '" . self::STATUS_CLOSING_PENDING . "')";
        $sql .= " ORDER BY s.rowid DESC LIMIT 1";

        $resql = $db->query($sql);
        return ($resql ? $db->fetch_object($resql) : null);
    }

    public static function countActiveShiftsForTerminal($db, $entity, $terminalId)
    {
        self::ensureSchema($db);

        $sql = "SELECT COUNT(rowid) AS nb";
        $sql .= " FROM " . self::tableShift();
        $sql .= " WHERE entity = " . ((int) $entity) . " AND fk_terminal = " . ((int) $terminalId);
        $sql .= " AND status IN ('" . self::STATUS_OPEN . "', '" . self::STATUS_CLOSING_PENDING . "')";

        $resql = $db->query($sql);
        if (!$resql) {
            return 0;
        }

        $obj = $db->fetch_object($resql);
        return ($obj ? (int) $obj->nb : 0);
    }

    /**
     * FIX (shift-master-v1): Added $excludeBranchTerminals parameter.
     * When true (used for master-admin shift checks), shifts on branch terminals
     * are excluded from the fallback cashier-shift lookup. Without this, the
     * master admin's shift gate would find an open shift on a branch terminal
     * (opened by accident from the old dropdown) and return
     * "Active shift is open on another terminal", blocking all master POS sales.
     */
    public static function getActiveShiftForCashier($db, $entity, $cashierUserId, $terminalId = 0, $excludeBranchTerminals = false)
    {
        self::ensureSchema($db);

        $sql = "SELECT rowid, entity, fk_store, fk_terminal, fk_cashier_user, shift_ref, status, opening_float, date_open";
        $sql .= " FROM " . self::tableShift();
        $sql .= " WHERE entity = " . ((int) $entity) . " AND fk_cashier_user = " . ((int) $cashierUserId);
        if ((int) $terminalId > 0) {
            $sql .= " AND fk_terminal = " . ((int) $terminalId);
        }
        // FIX (shift-master-v1): Exclude branch terminals for master users
        if ($excludeBranchTerminals) {
            $termTable = MAIN_DB_PREFIX . 'takepos_terminal';
            $chk = $db->query("SHOW TABLES LIKE '" . $db->escape($termTable) . "'");
            if ($chk && $db->num_rows($chk) > 0) {
                $sql .= " AND fk_terminal NOT IN (SELECT rowid FROM " . $termTable . " WHERE fk_branch IS NOT NULL AND entity = " . ((int) $entity) . ")";
            }
        }
        $sql .= " AND status IN ('" . self::STATUS_OPEN . "', '" . self::STATUS_CLOSING_PENDING . "')";
        $sql .= " ORDER BY rowid DESC LIMIT 1";

        $resql = $db->query($sql);
        return ($resql ? $db->fetch_object($resql) : null);
    }

    public static function openShift($db, $user, $terminalId, $storeId, $openingFloat, $notes = '')
    {
        self::ensureSchema($db);

        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        $terminalId = (int) $terminalId;
        $storeId = (int) $storeId;
        $openingFloat = (float) $openingFloat;

        TakeposAudit::logEvent(
            $db,
            $user,
            'shift_open_attempt',
            TakeposAudit::SEVERITY_INFO,
            array('terminal_id' => $terminalId, 'store_id' => $storeId, 'opening_float' => $openingFloat),
            'Shift open requested'
        );

        if ($terminalId <= 0) {
            TakeposAudit::logEvent($db, $user, 'shift_open_rejected', TakeposAudit::SEVERITY_WARNING, array('reason' => 'invalid_terminal'), 'Shift open rejected');
            throw new Exception(self::trans('TakeposShiftTerminalRequired', 'Terminal is required.'));
        }

        if ($openingFloat < 0) {
            TakeposAudit::logEvent($db, $user, 'shift_open_rejected', TakeposAudit::SEVERITY_WARNING, array('reason' => 'invalid_opening_float'), 'Shift open rejected');
            throw new Exception(self::trans('TakeposShiftOpeningFloatPositive', 'Opening float must be zero or positive.'));
        }

        $shiftId = 0;
        $terminal = null;
        $db->begin();

        try {
            $sqlTerminal = "SELECT rowid, terminal_code, fk_store, active";
            $sqlTerminal .= " FROM " . TakeposTerminalService::tableTerminal();
            $sqlTerminal .= " WHERE rowid = " . $terminalId . " AND entity = " . $entity;
            $sqlTerminal .= " LIMIT 1 FOR UPDATE";
            $resql = $db->query($sqlTerminal);
            if ($resql) {
                $terminal = $db->fetch_object($resql);
            }
            if (!$terminal || empty($terminal->active)) {
                TakeposAudit::logEvent($db, $user, 'shift_open_rejected', TakeposAudit::SEVERITY_WARNING, array('reason' => 'terminal_inactive', 'terminal_id' => $terminalId), 'Shift open rejected');
                throw new Exception(self::trans('TakeposShiftTerminalInactive', 'Terminal is invalid or inactive.'));
            }

            $sqlUserLock = "SELECT rowid FROM " . MAIN_DB_PREFIX . "user WHERE rowid = " . ((int) $user->id) . " LIMIT 1 FOR UPDATE";
            $db->query($sqlUserLock);

            if ($storeId <= 0 && !empty($terminal->fk_store)) {
                $storeId = (int) $terminal->fk_store;
            }

            $existingTerminalShift = self::getActiveShiftForTerminal($db, $entity, $terminalId);
            if ($existingTerminalShift) {
                // If the existing shift belongs to the same cashier or is a branch terminal,
                // just return the existing shift instead of blocking with an error.
                if ((int)$existingTerminalShift->fk_cashier_user === (int)$user->id
                    || TakeposBranchService::isBranchUser($db, (int)$user->id)) {
                    $db->rollback();
                    return (array) $existingTerminalShift;
                }
                TakeposAudit::logEvent($db, $user, 'shift_open_rejected', TakeposAudit::SEVERITY_WARNING, array('reason' => 'duplicate_terminal_active_shift', 'terminal_id' => $terminalId, 'existing_shift_id' => (int) $existingTerminalShift->rowid), 'Shift open rejected');
                throw new Exception(self::trans('TakeposShiftTerminalAlreadyActive', 'Terminal already has an active shift.'));
            }

            // FIX (shift-master-v1): Exclude shifts on branch terminals when checking
            // if the cashier already has an open shift. Without this, a master admin
            // who accidentally opened a shift on a branch terminal would be permanently
            // blocked from opening a shift on their own master terminal.
            $isBranchUserForOpen = TakeposBranchService::isBranchUser($db, (int) $user->id);
            $excludeBranchForOpen = !$isBranchUserForOpen;
            $existingCashierShift = self::getActiveShiftForCashier($db, $entity, (int) $user->id, 0, $excludeBranchForOpen);
            if ($existingCashierShift) {
                TakeposAudit::logEvent($db, $user, 'shift_open_rejected', TakeposAudit::SEVERITY_WARNING, array('reason' => 'cashier_already_has_active_shift', 'existing_shift_id' => (int) $existingCashierShift->rowid), 'Shift open rejected');
                throw new Exception(self::trans('TakeposShiftCashierAlreadyActive', 'Cashier already has an active shift.'));
            }

            $shiftRef = self::generateShiftRef($entity, $terminal->terminal_code);
            $now = dol_print_date(dol_now(), 'dayhourlog');

            $sql = "INSERT INTO " . self::tableShift() . " (entity, fk_store, fk_terminal, fk_cashier_user, shift_ref, status, opening_float, expected_cash, total_cash_sales, total_card_sales, total_other_sales, total_paid_in, total_paid_out, total_safe_drop, date_open, fk_opened_by, notes, date_creation) VALUES (";
            $sql .= $entity . ", ";
            $sql .= ($storeId > 0 ? $storeId : 'NULL') . ", ";
            $sql .= $terminalId . ", ";
            $sql .= ((int) $user->id) . ", ";
            $sql .= "'" . $db->escape($shiftRef) . "', '" . self::STATUS_OPEN . "', ";
            $sql .= $openingFloat . ", " . $openingFloat . ", 0, 0, 0, 0, 0, 0, ";
            $sql .= "'" . $db->escape($now) . "', " . ((int) $user->id) . ", ";
            $sql .= ($notes !== '' ? "'" . $db->escape($notes) . "'" : 'NULL') . ", '" . $db->escape($now) . "')";

            if (!$db->query($sql)) {
                TakeposAudit::logEvent($db, $user, 'shift_open_rejected', TakeposAudit::SEVERITY_WARNING, array('reason' => 'db_error', 'error' => $db->lasterror()), 'Shift open rejected');
                throw new Exception($db->lasterror());
            }

            $shiftId = (int) $db->last_insert_id(self::tableShift());
            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }

        TakeposAudit::logEvent(
            $db,
            $user,
            'shift_open_success',
            TakeposAudit::SEVERITY_INFO,
            array('shift_id' => $shiftId, 'shift_ref' => $shiftRef, 'terminal_id' => $terminalId, 'store_id' => $storeId, 'opening_float' => $openingFloat),
            'Shift opened',
            'shift',
            $shiftId
        );

        self::safeWebhookEmit($db, $entity, 'shift_opened', array(
            'shift_id' => $shiftId,
            'shift_ref' => $shiftRef,
            'terminal_id' => $terminalId,
            'store_id' => $storeId,
            'cashier_user_id' => (int) $user->id,
            'opening_float' => (float) $openingFloat,
        ), $user);

        return $shiftId;
    }

    public static function listShifts($db, $entity, $filters = array(), $limit = 100)
    {
        self::ensureSchema($db);

        $limit = max(1, min(500, (int) $limit));
        $sql = "SELECT s.rowid, s.shift_ref, s.status, s.fk_terminal, s.fk_store, s.fk_cashier_user, s.opening_float, s.expected_cash, s.counted_cash, s.cash_difference, s.total_cash_sales, s.total_card_sales, s.total_other_sales, s.total_paid_in, s.total_paid_out, s.total_safe_drop, s.date_open, s.date_close, t.terminal_code, t.label AS terminal_label, st.label AS store_label, u.login AS cashier_login";
        $sql .= " FROM " . self::tableShift() . " s";
        $sql .= " LEFT JOIN " . TakeposTerminalService::tableTerminal() . " t ON t.rowid = s.fk_terminal";
        $sql .= " LEFT JOIN " . TakeposStoreService::tableStore() . " st ON st.rowid = s.fk_store";
        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid = s.fk_cashier_user";
        $sql .= " WHERE s.entity = " . ((int) $entity);

        if (!empty($filters['status'])) {
            $sql .= " AND s.status = '" . $db->escape($filters['status']) . "'";
        }
        if (!empty($filters['store_id'])) {
            $sql .= " AND s.fk_store = " . ((int) $filters['store_id']);
        }
        if (!empty($filters['terminal_id'])) {
            $sql .= " AND s.fk_terminal = " . ((int) $filters['terminal_id']);
        }

        $sql .= " ORDER BY s.rowid DESC LIMIT " . $limit;

        $rows = array();
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $rows[] = $obj;
            }
        }

        return $rows;
    }

    public static function computeSalesTotals($db, $entity, $shift)
    {
        $result = array(
            'total_cash_sales' => 0.0,
            'total_card_sales' => 0.0,
            'total_other_sales' => 0.0,
        );

        if (!is_object($shift)) {
            return $result;
        }

        $dateOpen = !empty($shift->date_open) ? (string) $shift->date_open : dol_print_date(dol_now(), 'dayhourlog');
        $dateClose = !empty($shift->date_close) ? (string) $shift->date_close : dol_print_date(dol_now(), 'dayhourlog');
        $terminalCode = isset($shift->terminal_code) ? (string) $shift->terminal_code : '';
        $shiftId = isset($shift->rowid) ? (int) $shift->rowid : 0;
        $linkTable = self::tableInvoiceShift();
        $useShiftLink = false;

        if ($shiftId > 0 && TakeposMigration::tableExists($db, $linkTable)) {
            $sqlLinkedCount = "SELECT COUNT(rowid) AS nb FROM " . $linkTable
                . " WHERE entity = " . ((int) $entity)
                . " AND fk_shift = " . $shiftId;
            $resLinkedCount = $db->query($sqlLinkedCount);
            if ($resLinkedCount && ($objLinkedCount = $db->fetch_object($resLinkedCount)) && (int) $objLinkedCount->nb > 0) {
                $useShiftLink = true;
            }
        }

        $sql = "SELECT"
            . " COALESCE(SUM(CASE WHEN cp.code = 'LIQ' THEN pf.amount ELSE 0 END), 0) AS total_cash_sales,"
            . " COALESCE(SUM(CASE WHEN cp.code IN ('CB','CARD','STRIPETERMINAL','SUMUP') THEN pf.amount ELSE 0 END), 0) AS total_card_sales,"
            . " COALESCE(SUM(CASE WHEN cp.code NOT IN ('LIQ','CB','CARD','STRIPETERMINAL','SUMUP') THEN pf.amount ELSE 0 END), 0) AS total_other_sales"
            . " FROM " . MAIN_DB_PREFIX . "paiement_facture pf"
            . " INNER JOIN " . MAIN_DB_PREFIX . "paiement p ON p.rowid = pf.fk_paiement"
            . " INNER JOIN " . MAIN_DB_PREFIX . "c_paiement cp ON cp.id = p.fk_paiement"
            . " INNER JOIN " . MAIN_DB_PREFIX . "facture f ON f.rowid = pf.fk_facture";

        if ($useShiftLink) {
            $sql .= " INNER JOIN " . $linkTable . " tis ON tis.fk_invoice = f.rowid AND tis.entity = f.entity";
        }

        $sql .= " WHERE f.entity = " . ((int) $entity)
            . " AND f.module_source = 'takepos'";

        if ($useShiftLink) {
            $sql .= " AND tis.fk_shift = " . $shiftId;
        } else {
            $sql .= " AND p.datep >= '" . $db->escape($dateOpen) . "'"
                . " AND p.datep <= '" . $db->escape($dateClose) . "'"
                . " AND f.fk_user_author = " . ((int) $shift->fk_cashier_user);
            if ($terminalCode !== '') {
                $sql .= " AND f.pos_source = '" . $db->escape($terminalCode) . "'";
            }
        }

        $resql = $db->query($sql);
        if ($resql && ($obj = $db->fetch_object($resql))) {
            $result['total_cash_sales'] = (float) $obj->total_cash_sales;
            $result['total_card_sales'] = (float) $obj->total_card_sales;
            $result['total_other_sales'] = (float) $obj->total_other_sales;
        }

        return $result;
    }

    public static function computeCashMovementTotals($db, $entity, $shiftId)
    {
        $result = array(
            'total_paid_in' => 0.0,
            'total_paid_out' => 0.0,
            'total_safe_drop' => 0.0,
        );

        if (!TakeposMigration::tableExists($db, self::tableCashMovement())) {
            return $result;
        }

        $sql = "SELECT"
            . " COALESCE(SUM(CASE WHEN movement_type = 'paid_in' THEN amount ELSE 0 END), 0) AS total_paid_in,"
            . " COALESCE(SUM(CASE WHEN movement_type = 'paid_out' THEN amount ELSE 0 END), 0) AS total_paid_out,"
            . " COALESCE(SUM(CASE WHEN movement_type = 'safe_drop' THEN amount ELSE 0 END), 0) AS total_safe_drop"
            . " FROM " . self::tableCashMovement()
            . " WHERE entity = " . ((int) $entity)
            . " AND fk_shift = " . ((int) $shiftId);

        $resql = $db->query($sql);
        if ($resql && ($obj = $db->fetch_object($resql))) {
            $result['total_paid_in'] = (float) $obj->total_paid_in;
            $result['total_paid_out'] = (float) $obj->total_paid_out;
            $result['total_safe_drop'] = (float) $obj->total_safe_drop;
        }

        return $result;
    }

    public static function computeExpectedCash($openingFloat, $salesTotals, $movementTotals)
    {
        return (float) $openingFloat
            + (float) $salesTotals['total_cash_sales']
            + (float) $movementTotals['total_paid_in']
            - (float) $movementTotals['total_paid_out']
            - (float) $movementTotals['total_safe_drop'];
    }

    public static function buildShiftSummary($db, $entity, $shift)
    {
        $salesTotals = self::computeSalesTotals($db, $entity, $shift);
        $movementTotals = self::computeCashMovementTotals($db, $entity, (int) $shift->rowid);
        $expected = self::computeExpectedCash((float) $shift->opening_float, $salesTotals, $movementTotals);

        return array(
            'total_cash_sales' => (float) $salesTotals['total_cash_sales'],
            'total_card_sales' => (float) $salesTotals['total_card_sales'],
            'total_other_sales' => (float) $salesTotals['total_other_sales'],
            'total_paid_in' => (float) $movementTotals['total_paid_in'],
            'total_paid_out' => (float) $movementTotals['total_paid_out'],
            'total_safe_drop' => (float) $movementTotals['total_safe_drop'],
            'expected_cash' => (float) $expected,
        );
    }

    private static function releaseHeldSalesForShift($db, $entity, $shiftId, $user = null)
    {
        $heldTable = MAIN_DB_PREFIX . 'takepos_held_sale';
        if (!TakeposMigration::tableExists($db, $heldTable)) {
            return true;
        }

        if (!TakeposMigration::columnExists($db, $heldTable, 'fk_shift')) {
            return true;
        }

        $sql = "SELECT rowid, fk_invoice FROM " . $heldTable
            . " WHERE entity = " . ((int) $entity)
            . " AND fk_shift = " . ((int) $shiftId)
            . " AND status = 1";
        $resql = $db->query($sql);
        if (!$resql) {
            return false;
        }

        if (!class_exists('Facture')) {
            require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
        }
        if (!is_object($user) && isset($GLOBALS['user']) && is_object($GLOBALS['user'])) {
            $user = $GLOBALS['user'];
        }

        $heldIds = array();
        while ($obj = $db->fetch_object($resql)) {
            $heldIds[] = (int) $obj->rowid;
            $invoiceId = (int) $obj->fk_invoice;
            if ($invoiceId <= 0 || !is_object($user)) {
                continue;
            }
            $invoice = new Facture($db);
            if ($invoice->fetch($invoiceId) > 0 && (int) $invoice->status === Facture::STATUS_DRAFT) {
                $invoice->delete($user);
            }
        }

        if (empty($heldIds)) {
            return true;
        }

        $sqlUpdate = "UPDATE " . $heldTable
            . " SET status = 0, date_update = '" . $db->idate(dol_now()) . "'"
            . " WHERE rowid IN (" . implode(',', array_map('intval', $heldIds)) . ")";

        return (bool) $db->query($sqlUpdate);
    }

    public static function closeShift($db, $user, $shiftId, $countedCash, $notes = '', $approvedBy = 0, $allowLargeDifference = false)
    {
        self::ensureSchema($db);

        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        $shift = self::getShiftById($db, $entity, (int) $shiftId);
        if (!$shift) {
            throw new Exception(self::trans('TakeposShiftNotFound', 'Shift not found.'));
        }

        if (!in_array((string) $shift->status, array(self::STATUS_OPEN, self::STATUS_CLOSING_PENDING), true)) {
            throw new Exception(self::trans('TakeposShiftNotOpen', 'Shift is not open.'));
        }

        TakeposAudit::logEvent(
            $db,
            $user,
            'shift_close_attempt',
            TakeposAudit::SEVERITY_INFO,
            array('shift_id' => (int) $shift->rowid, 'counted_cash' => (float) $countedCash),
            'Shift close requested',
            'shift',
            (int) $shift->rowid
        );

        $summary = self::buildShiftSummary($db, $entity, $shift);
        $expectedCash = (float) $summary['expected_cash'];
        $countedCash = (float) $countedCash;
        if ($countedCash < 0) {
            TakeposAudit::logEvent(
                $db,
                $user,
                'shift_close_rejected',
                TakeposAudit::SEVERITY_WARNING,
                array('shift_id' => (int) $shift->rowid, 'reason' => 'negative_counted_cash', 'counted_cash' => $countedCash),
                'Shift close rejected due to invalid counted cash',
                'shift',
                (int) $shift->rowid
            );
            throw new Exception(self::trans('TakeposShiftCountedCashPositive', 'Counted cash must be zero or positive.'));
        }
        $difference = $countedCash - $expectedCash;

        $threshold = self::discrepancyThresholdAmount();
        if ($threshold > 0 && abs($difference) > $threshold && !$allowLargeDifference) {
            TakeposAudit::logEvent(
                $db,
                $user,
                'cash_difference_rejected',
                TakeposAudit::SEVERITY_WARNING,
                array('shift_id' => (int) $shift->rowid, 'difference' => $difference, 'threshold' => $threshold),
                'Cash difference requires manager approval',
                'shift',
                (int) $shift->rowid
            );
            throw new Exception(self::trans('TakeposShiftDifferenceApprovalRequired', 'Cash difference exceeds allowed threshold and requires manager approval.'));
        }

        if ($threshold > 0 && abs($difference) > $threshold && $allowLargeDifference) {
            TakeposAudit::logEvent(
                $db,
                $user,
                'cash_difference_approved',
                TakeposAudit::SEVERITY_WARNING,
                array('shift_id' => (int) $shift->rowid, 'difference' => $difference, 'threshold' => $threshold),
                'Cash difference approved by authorized user',
                'shift',
                (int) $shift->rowid
            );
        }

        if (abs($difference) > 0.00001) {
            TakeposAudit::logEvent(
                $db,
                $user,
                'cash_difference_detected',
                TakeposAudit::SEVERITY_WARNING,
                array('shift_id' => (int) $shift->rowid, 'difference' => $difference),
                'Cash difference detected',
                'shift',
                (int) $shift->rowid
            );
        }

        $now = dol_print_date(dol_now(), 'dayhourlog');
        $sql = "UPDATE " . self::tableShift() . " SET"
            . " status='" . self::STATUS_CLOSED . "'"
            . ", expected_cash=" . $expectedCash
            . ", counted_cash=" . $countedCash
            . ", cash_difference=" . $difference
            . ", total_cash_sales=" . ((float) $summary['total_cash_sales'])
            . ", total_card_sales=" . ((float) $summary['total_card_sales'])
            . ", total_other_sales=" . ((float) $summary['total_other_sales'])
            . ", total_paid_in=" . ((float) $summary['total_paid_in'])
            . ", total_paid_out=" . ((float) $summary['total_paid_out'])
            . ", total_safe_drop=" . ((float) $summary['total_safe_drop'])
            . ", date_close='" . $db->escape($now) . "'"
            . ", fk_closed_by=" . ((int) $user->id)
            . ", fk_approved_by=" . ((int) $approvedBy > 0 ? (int) $approvedBy : 'NULL')
            . ", notes=" . ($notes !== '' ? "'" . $db->escape($notes) . "'" : 'NULL')
            . " WHERE rowid = " . ((int) $shift->rowid);

        if (!$db->query($sql)) {
            TakeposAudit::logEvent($db, $user, 'shift_close_rejected', TakeposAudit::SEVERITY_WARNING, array('shift_id' => (int) $shift->rowid, 'error' => $db->lasterror()), 'Shift close rejected', 'shift', (int) $shift->rowid);
            throw new Exception($db->lasterror());
        }

        if (!self::releaseHeldSalesForShift($db, $entity, (int) $shift->rowid, $user)) {
            TakeposAudit::logEvent(
                $db,
                $user,
                'shift_close_warning',
                TakeposAudit::SEVERITY_WARNING,
                array('shift_id' => (int) $shift->rowid, 'warning' => 'held_sales_cleanup_failed', 'error' => $db->lasterror()),
                'Shift closed but held sales cleanup failed',
                'shift',
                (int) $shift->rowid
            );
        }

        TakeposAudit::logEvent(
            $db,
            $user,
            'cash_count_completed',
            TakeposAudit::SEVERITY_INFO,
            array('shift_id' => (int) $shift->rowid, 'counted_cash' => $countedCash, 'expected_cash' => $expectedCash, 'difference' => $difference),
            'Cash count completed',
            'shift',
            (int) $shift->rowid
        );

        TakeposAudit::logEvent(
            $db,
            $user,
            'shift_reconciliation_completed',
            TakeposAudit::SEVERITY_INFO,
            array('shift_id' => (int) $shift->rowid, 'expected_cash' => $expectedCash, 'counted_cash' => $countedCash, 'difference' => $difference),
            'Shift reconciliation completed',
            'shift',
            (int) $shift->rowid
        );

        TakeposAudit::logEvent(
            $db,
            $user,
            'shift_close_success',
            TakeposAudit::SEVERITY_INFO,
            array('shift_id' => (int) $shift->rowid, 'difference' => $difference),
            'Shift closed',
            'shift',
            (int) $shift->rowid
        );

        self::safeWebhookEmit($db, $entity, 'shift_closed', array(
            'shift_id' => (int) $shift->rowid,
            'shift_ref' => (string) $shift->shift_ref,
            'terminal_id' => (int) $shift->fk_terminal,
            'store_id' => (int) $shift->fk_store,
            'cashier_user_id' => (int) $shift->fk_cashier_user,
            'difference' => (float) $difference,
            'expected_cash' => (float) $expectedCash,
            'counted_cash' => (float) $countedCash,
        ), $user);

        return true;
    }

    public static function forceCloseShift($db, $user, $shiftId, $notes = '')
    {
        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        $shift = self::getShiftById($db, $entity, (int) $shiftId);
        if (!$shift) {
            throw new Exception(self::trans('TakeposShiftNotFound', 'Shift not found.'));
        }

        $summary = self::buildShiftSummary($db, $entity, $shift);
        $counted = (float) $summary['expected_cash'];
        self::closeShift($db, $user, (int) $shiftId, $counted, $notes, (int) $user->id, true);

        TakeposAudit::logEvent(
            $db,
            $user,
            'shift_force_close',
            TakeposAudit::SEVERITY_CRITICAL,
            array('shift_id' => (int) $shiftId),
            'Shift force-closed',
            'shift',
            (int) $shiftId
        );

        return true;
    }

    protected static function buildShiftSummaryPayload($db, $entity, $full, $terminalContextCode = '', $isFallbackCashierShift = false)
    {
        if (!$full) {
            return null;
        }

        $summary = self::buildShiftSummary($db, $entity, $full);
        return array(
            'shift_id' => (int) $full->rowid,
            'shift_ref' => (string) $full->shift_ref,
            'status' => (string) $full->status,
            'cashier_user_id' => (int) $full->fk_cashier_user,
            'terminal_id' => (int) $full->fk_terminal,
            'terminal_code' => (string) $full->terminal_code,
            'terminal_label' => (string) $full->terminal_label,
            'store_id' => (int) $full->fk_store,
            'store_label' => (string) $full->store_label,
            'date_open' => (string) $full->date_open,
            'opening_float' => (float) $full->opening_float,
            'expected_cash' => (float) $summary['expected_cash'],
            'total_cash_sales' => (float) $summary['total_cash_sales'],
            'total_card_sales' => (float) $summary['total_card_sales'],
            'total_other_sales' => (float) $summary['total_other_sales'],
            'total_paid_in' => (float) $summary['total_paid_in'],
            'total_paid_out' => (float) $summary['total_paid_out'],
            'total_safe_drop' => (float) $summary['total_safe_drop'],
            'requested_terminal_code' => (string) $terminalContextCode,
            'is_fallback_cashier_shift' => $isFallbackCashierShift ? 1 : 0,
            'same_terminal' => ((string) $terminalContextCode === '' || strcasecmp((string) $full->terminal_code, (string) $terminalContextCode) === 0) ? 1 : 0,
        );
    }

    public static function getCurrentActiveShiftSummary($db, $user, $terminalCode)
    {
        // PERFORMANCE: getCurrentActiveShiftSummary is called multiple times per
        // request — once from the strict shift gate at the top of invoice.php, and
        // again from action handlers (addline, payment, etc). Each invocation runs
        // ~5 DB queries: resolveCurrentTerminal, countActiveShiftsForTerminal,
        // getActiveShiftForTerminal, getShiftById, buildShiftSummaryPayload.
        // Caching by (entity|userId|terminalCode) for the duration of this PHP
        // request alone removes the duplicated work without risking stale data
        // (the next HTTP request still re-checks).
        static $cache = array();
        $entityForCache = !empty($user->entity) ? (int) $user->entity : 1;
        $userIdForCache = !empty($user->id)     ? (int) $user->id     : 0;
        $cacheKey = $entityForCache . '|' . $userIdForCache . '|' . strtoupper(trim((string) $terminalCode));
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        $terminalContextCode = strtoupper(trim((string) $terminalCode));
        $terminal = TakeposTerminalService::resolveCurrentTerminal($db, $user, $terminalCode);
        if ($terminal) {
            $activeShiftCount = self::countActiveShiftsForTerminal($db, $entity, (int) $terminal->rowid);
            $shift = self::getActiveShiftForTerminal($db, $entity, (int) $terminal->rowid);
            if ($shift) {
                $full = self::getShiftById($db, $entity, (int) $shift->rowid);
                if ($full) {
                    $payload = self::buildShiftSummaryPayload($db, $entity, $full, $terminalContextCode, false);
                    $payload['active_shift_count_on_terminal'] = $activeShiftCount;
                    $payload['has_terminal_shift_conflict'] = ($activeShiftCount > 1 ? 1 : 0);
                    $cache[$cacheKey] = $payload;
                    return $payload;
                }
            }
        }

        // FIX (shift-master-v1): For master users (non-branch), exclude shifts that
        // are on branch terminals. This prevents "Active shift on another terminal"
        // errors when the master admin previously opened a shift on a branch terminal
        // by mistake (old bug: dropdown showed branch terminals to master admin).
        $isBranchUserForShift = false;
        if (!empty($user->id) && empty($user->admin)) {
            require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposBranchService.class.php';
            $isBranchUserForShift = TakeposBranchService::isBranchUser($db, (int) $user->id);
        }
        $excludeBranchTerminals = !$isBranchUserForShift;

        $cashierShift = self::getActiveShiftForCashier($db, $entity, (int) $user->id, 0, $excludeBranchTerminals);
        if (!$cashierShift) {
            $cache[$cacheKey] = null;
            return null;
        }

        $full = self::getShiftById($db, $entity, (int) $cashierShift->rowid);
        if (!$full) {
            $cache[$cacheKey] = null;
            return null;
        }

        $result = self::buildShiftSummaryPayload($db, $entity, $full, $terminalContextCode, true);
        $cache[$cacheKey] = $result;
        return $result;
    }

    public static function enforcePaymentShiftRequirement($db, $user, $terminalCode, $invoiceId = 0)
    {
        // If feature is disabled, keep backward compatibility.
        if (!TakeposAccess::isFeatureEnabledForCurrentEntity($db, 'takepos.shift_management')) {
            return array(true, '', null);
        }

        if (!self::requireOpenShiftForPayments()) {
            return array(true, '', null);
        }

        $summary = self::getCurrentActiveShiftSummary($db, $user, $terminalCode);
        if ($summary) {
            if (!empty($summary['has_terminal_shift_conflict'])) {
                TakeposAudit::logEvent(
                    $db,
                    $user,
                    'shift_required_block',
                    TakeposAudit::SEVERITY_WARNING,
                    array('reason' => 'multiple_active_shifts_on_terminal', 'shift_id' => (int) $summary['shift_id'], 'invoice_id' => (int) $invoiceId, 'terminal' => (string) $terminalCode, 'active_shift_count' => (int) $summary['active_shift_count_on_terminal']),
                    'Payment blocked because terminal has multiple active shifts'
                );
                return array(false, self::trans('TakeposShiftMultipleActiveOnTerminal', 'Multiple active shifts were found on this terminal. Close the extra shift before continuing.'), $summary);
            }
            if ((int) $summary['cashier_user_id'] !== (int) $user->id) {
                TakeposAudit::logEvent(
                    $db,
                    $user,
                    'shift_required_block',
                    TakeposAudit::SEVERITY_WARNING,
                    array('reason' => 'cashier_not_owner_of_active_shift', 'shift_id' => (int) $summary['shift_id'], 'invoice_id' => (int) $invoiceId),
                    'Payment blocked due to shift ownership'
                );
                return array(false, 'Active shift belongs to another cashier.', $summary);
            }
            if (!empty($summary['is_fallback_cashier_shift'])) {
                // Branch users share terminals — allow payment even if shift is on another terminal
                if (TakeposBranchService::isBranchUser($db, (int) $user->id)) {
                    return array(true, '', $summary);
                }
                TakeposAudit::logEvent(
                    $db,
                    $user,
                    'shift_required_block',
                    TakeposAudit::SEVERITY_WARNING,
                    array('reason' => 'active_shift_on_other_terminal', 'shift_id' => (int) $summary['shift_id'], 'invoice_id' => (int) $invoiceId, 'shift_terminal' => (string) $summary['terminal_code'], 'requested_terminal' => (string) $terminalCode),
                    'Payment blocked because active shift is on another terminal'
                );
                return array(false, self::trans('TakeposShiftActiveOnAnotherTerminal', 'Active shift is open on another terminal.'), $summary);
            }

            return array(true, '', $summary);
        }

        TakeposAudit::logEvent(
            $db,
            $user,
            'shift_required_block',
            TakeposAudit::SEVERITY_WARNING,
            array('reason' => 'no_active_shift', 'invoice_id' => (int) $invoiceId, 'terminal' => (string) $terminalCode),
            'Payment blocked because no active shift was found'
        );

        return array(false, 'Open shift is required before payment.', null);
    }

    public static function enforceSaleShiftRequirement($db, $user, $terminalCode, $invoiceId = 0)
    {
        // Keep backward compatibility when shift management is not enabled at all.
        if (!TakeposAccess::isFeatureEnabledForCurrentEntity($db, 'takepos.shift_management')) {
            return array(true, '', null);
        }

        $summary = self::getCurrentActiveShiftSummary($db, $user, $terminalCode);
        if ($summary) {
            if (!empty($summary['has_terminal_shift_conflict'])) {
                TakeposAudit::logEvent(
                    $db,
                    $user,
                    'shift_required_block',
                    TakeposAudit::SEVERITY_WARNING,
                    array('reason' => 'multiple_active_shifts_on_terminal', 'shift_id' => (int) $summary['shift_id'], 'invoice_id' => (int) $invoiceId, 'terminal' => (string) $terminalCode, 'active_shift_count' => (int) $summary['active_shift_count_on_terminal']),
                    'Sale blocked because terminal has multiple active shifts'
                );
                return array(false, self::trans('TakeposShiftMultipleActiveOnTerminal', 'Multiple active shifts were found on this terminal. Close the extra shift before continuing.'), $summary);
            }
            if ((int) $summary['cashier_user_id'] !== (int) $user->id) {
                TakeposAudit::logEvent(
                    $db,
                    $user,
                    'shift_required_block',
                    TakeposAudit::SEVERITY_WARNING,
                    array('reason' => 'cashier_not_owner_of_active_shift', 'shift_id' => (int) $summary['shift_id'], 'invoice_id' => (int) $invoiceId),
                    'Sale blocked due to shift ownership'
                );
                return array(false, self::trans('TakeposShiftBelongsAnotherCashier', 'Active shift belongs to another cashier.'), $summary);
            }
            if (!empty($summary['is_fallback_cashier_shift'])) {
                // Branch users share terminals — allow sale even if shift is on another terminal
                if (TakeposBranchService::isBranchUser($db, (int) $user->id)) {
                    return array(true, '', $summary);
                }
                TakeposAudit::logEvent(
                    $db,
                    $user,
                    'shift_required_block',
                    TakeposAudit::SEVERITY_WARNING,
                    array('reason' => 'active_shift_on_other_terminal', 'shift_id' => (int) $summary['shift_id'], 'invoice_id' => (int) $invoiceId, 'shift_terminal' => (string) $summary['terminal_code'], 'requested_terminal' => (string) $terminalCode),
                    'Sale blocked because active shift is on another terminal'
                );
                return array(false, self::trans('TakeposShiftActiveOnAnotherTerminal', 'Active shift is open on another terminal.'), $summary);
            }

            return array(true, '', $summary);
        }

        TakeposAudit::logEvent(
            $db,
            $user,
            'shift_required_block',
            TakeposAudit::SEVERITY_WARNING,
            array('reason' => 'no_active_shift', 'invoice_id' => (int) $invoiceId, 'terminal' => (string) $terminalCode),
            'Sale blocked because no active shift was found'
        );

        return array(false, self::trans('TakeposShiftOpenRequiredBeforeSale', 'Open shift is required before sale.'), null);
    }
}