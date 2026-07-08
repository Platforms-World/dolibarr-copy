<?php
require_once __DIR__ . '/TakeposMigration.class.php';
require_once __DIR__ . '/TakeposAudit.class.php';
require_once __DIR__ . '/TakeposInputValidator.class.php';
require_once __DIR__ . '/TakeposUserAccess.class.php';
require_once __DIR__ . '/TakeposStoreService.class.php';
require_once __DIR__ . '/TakeposTerminalService.class.php';
require_once __DIR__ . '/TakeposShiftService.class.php';
require_once __DIR__ . '/TakeposCashService.class.php';

if (defined('DOL_DOCUMENT_ROOT')) {
    require_once DOL_DOCUMENT_ROOT . '/compta/bank/class/paymentvarious.class.php';
}

/**
 * POS expenses service.
 *
 * Tracks POS-side expense records and posts them through Dolibarr
 * various payments so bank/cash accounting stays in the native flow.
 */
class TakeposExpenseService
{
    const STATUS_DRAFT = 0;
    const STATUS_SAVED = 1;
    const STATUS_POSTED = 2;
    const STATUS_CANCELLED = 9;

    const SOURCE_CASH_REGISTER = 'cash_register';
    const SOURCE_PETTY_CASH = 'petty_cash';
    const SOURCE_BANK_ACCOUNT = 'bank_account';

    public static function tableExpense()
    {
        return MAIN_DB_PREFIX . 'takepos_expense';
    }

    public static function tableExpenseCategory()
    {
        return MAIN_DB_PREFIX . 'takepos_expense_category';
    }

    private static function trans($key, $fallback = '')
    {
        global $langs;

        if (is_object($langs)) {
            if (method_exists($langs, 'load')) {
                $langs->load('takeposcustom@takepos');
            }
            $translated = $langs->trans($key);
            if ($translated !== $key) {
                return $translated;
            }
        }

        return ($fallback !== '' ? $fallback : $key);
    }

    private static function safeAudit($db, $user, $eventType, $severity, $data = array(), $description = '', $objectType = '', $objectId = 0, $amount = null)
    {
        try {
            TakeposAudit::logEvent($db, $user, $eventType, $severity, $data, $description, $objectType, $objectId, $amount);
        } catch (Throwable $e) {
            if (function_exists('dol_syslog')) {
                dol_syslog('[TakePOS][Expense] Audit failure: ' . $e->getMessage(), LOG_WARNING);
            }
        }
    }

    public static function ensureSchema($db)
    {
        TakeposStoreService::ensureSchema($db);
        TakeposTerminalService::ensureSchema($db);
        TakeposShiftService::ensureSchema($db);
        TakeposCashService::ensureSchema($db);

        $expenseTable = self::tableExpense();
        $categoryTable = self::tableExpenseCategory();

        $ok = true;
        $ok = $ok && TakeposMigration::ensureTable($db, $categoryTable, "CREATE TABLE " . $categoryTable . " ("
            . " rowid INT AUTO_INCREMENT PRIMARY KEY,"
            . " entity INT NOT NULL DEFAULT 1,"
            . " label VARCHAR(128) NOT NULL,"
            . " accountancy_code VARCHAR(64) NULL,"
            . " vat_default DECIMAL(8,4) NOT NULL DEFAULT 0,"
            . " active TINYINT(1) NOT NULL DEFAULT 1,"
            . " pos_visible TINYINT(1) NOT NULL DEFAULT 1,"
            . " datec DATETIME NOT NULL,"
            . " tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
            . " UNIQUE KEY uk_takepos_expense_category_label (entity, label),"
            . " KEY idx_takepos_expense_category_visible (entity, active, pos_visible)"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $ok = $ok && TakeposMigration::ensureTable($db, $expenseTable, "CREATE TABLE " . $expenseTable . " ("
            . " rowid INT AUTO_INCREMENT PRIMARY KEY,"
            . " entity INT NOT NULL DEFAULT 1,"
            . " ref VARCHAR(64) NOT NULL,"
            . " date_expense DATETIME NOT NULL,"
            . " fk_user INT NOT NULL,"
            . " fk_terminal INT NULL,"
            . " fk_store INT NULL,"
            . " fk_shift INT NULL,"
            . " fk_category INT NOT NULL,"
            . " description VARCHAR(255) NOT NULL,"
            . " amount_ht DECIMAL(24,8) NOT NULL DEFAULT 0,"
            . " amount_tva DECIMAL(24,8) NOT NULL DEFAULT 0,"
            . " amount_ttc DECIMAL(24,8) NOT NULL DEFAULT 0,"
            . " vat_rate DECIMAL(8,4) NOT NULL DEFAULT 0,"
            . " payment_source VARCHAR(32) NOT NULL DEFAULT 'cash_register',"
            . " note_private TEXT NULL,"
            . " external_ref VARCHAR(128) NULL,"
            . " status SMALLINT NOT NULL DEFAULT 1,"
            . " accountancy_code VARCHAR(64) NULL,"
            . " fk_bank_account INT NULL,"
            . " fk_payment_various INT NULL,"
            . " fk_bank_line INT NULL,"
            . " fk_cash_movement INT NULL,"
            . " fk_posted_user INT NULL,"
            . " date_posted DATETIME NULL,"
            . " import_key VARCHAR(64) NULL,"
            . " datec DATETIME NOT NULL,"
            . " tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
            . " UNIQUE KEY uk_takepos_expense_ref (entity, ref),"
            . " KEY idx_takepos_expense_entity_date (entity, date_expense),"
            . " KEY idx_takepos_expense_terminal (entity, fk_terminal),"
            . " KEY idx_takepos_expense_store (entity, fk_store),"
            . " KEY idx_takepos_expense_shift (entity, fk_shift),"
            . " KEY idx_takepos_expense_user (entity, fk_user),"
            . " KEY idx_takepos_expense_status (entity, status),"
            . " KEY idx_takepos_expense_category (entity, fk_category)"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if (!$ok) {
            return false;
        }

        $categoryColumns = array(
            'entity' => "INT NOT NULL DEFAULT 1",
            'label' => "VARCHAR(128) NOT NULL",
            'accountancy_code' => "VARCHAR(64) NULL",
            'vat_default' => "DECIMAL(8,4) NOT NULL DEFAULT 0",
            'active' => "TINYINT(1) NOT NULL DEFAULT 1",
            'pos_visible' => "TINYINT(1) NOT NULL DEFAULT 1",
            'datec' => "DATETIME NOT NULL",
            'tms' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        );
        foreach ($categoryColumns as $column => $definition) {
            if (!TakeposMigration::ensureColumn($db, $categoryTable, $column, $definition)) {
                return false;
            }
        }

        $expenseColumns = array(
            'entity' => "INT NOT NULL DEFAULT 1",
            'ref' => "VARCHAR(64) NOT NULL",
            'date_expense' => "DATETIME NOT NULL",
            'fk_user' => "INT NOT NULL",
            'fk_terminal' => "INT NULL",
            'fk_store' => "INT NULL",
            'fk_shift' => "INT NULL",
            'fk_category' => "INT NOT NULL",
            'description' => "VARCHAR(255) NOT NULL",
            'amount_ht' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
            'amount_tva' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
            'amount_ttc' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
            'vat_rate' => "DECIMAL(8,4) NOT NULL DEFAULT 0",
            'payment_source' => "VARCHAR(32) NOT NULL DEFAULT 'cash_register'",
            'note_private' => "TEXT NULL",
            'external_ref' => "VARCHAR(128) NULL",
            'status' => "SMALLINT NOT NULL DEFAULT 1",
            'accountancy_code' => "VARCHAR(64) NULL",
            'fk_bank_account' => "INT NULL",
            'fk_payment_various' => "INT NULL",
            'fk_bank_line' => "INT NULL",
            'fk_cash_movement' => "INT NULL",
            'fk_posted_user' => "INT NULL",
            'date_posted' => "DATETIME NULL",
            'import_key' => "VARCHAR(64) NULL",
            'datec' => "DATETIME NOT NULL",
            'tms' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        );
        foreach ($expenseColumns as $column => $definition) {
            if (!TakeposMigration::ensureColumn($db, $expenseTable, $column, $definition)) {
                return false;
            }
        }

        if (!TakeposMigration::ensureIndex($db, $categoryTable, 'uk_takepos_expense_category_label', '(entity, label)', 'UNIQUE')) {
            return false;
        }
        if (!TakeposMigration::ensureIndex($db, $categoryTable, 'idx_takepos_expense_category_visible', '(entity, active, pos_visible)')) {
            return false;
        }
        if (!TakeposMigration::ensureIndex($db, $expenseTable, 'uk_takepos_expense_ref', '(entity, ref)', 'UNIQUE')) {
            return false;
        }
        if (!TakeposMigration::ensureIndex($db, $expenseTable, 'idx_takepos_expense_entity_date', '(entity, date_expense)')) {
            return false;
        }
        if (!TakeposMigration::ensureIndex($db, $expenseTable, 'idx_takepos_expense_terminal', '(entity, fk_terminal)')) {
            return false;
        }
        if (!TakeposMigration::ensureIndex($db, $expenseTable, 'idx_takepos_expense_store', '(entity, fk_store)')) {
            return false;
        }
        if (!TakeposMigration::ensureIndex($db, $expenseTable, 'idx_takepos_expense_shift', '(entity, fk_shift)')) {
            return false;
        }
        if (!TakeposMigration::ensureIndex($db, $expenseTable, 'idx_takepos_expense_user', '(entity, fk_user)')) {
            return false;
        }
        if (!TakeposMigration::ensureIndex($db, $expenseTable, 'idx_takepos_expense_status', '(entity, status)')) {
            return false;
        }
        if (!TakeposMigration::ensureIndex($db, $expenseTable, 'idx_takepos_expense_category', '(entity, fk_category)')) {
            return false;
        }

        self::ensureDefaultCategories($db);
        return true;
    }

    public static function listPaymentSources()
    {
        return array(
            self::SOURCE_CASH_REGISTER => self::trans('TakeposExpensePaymentSourceCashRegister', 'POS Cash Register'),
            self::SOURCE_PETTY_CASH => self::trans('TakeposExpensePaymentSourcePettyCash', 'Petty Cash'),
            self::SOURCE_BANK_ACCOUNT => self::trans('TakeposExpensePaymentSourceBankAccount', 'Bank Account'),
        );
    }

    public static function normalizePaymentSource($value)
    {
        $value = strtolower(trim((string) $value));
        $allowed = array_keys(self::listPaymentSources());
        if (!in_array($value, $allowed, true)) {
            return self::SOURCE_CASH_REGISTER;
        }
        return $value;
    }

    public static function statusLabel($status)
    {
        $map = array(
            self::STATUS_DRAFT => self::trans('TakeposExpenseStatusDraft', 'Draft'),
            self::STATUS_SAVED => self::trans('TakeposExpenseStatusSaved', 'Saved'),
            self::STATUS_POSTED => self::trans('TakeposExpenseStatusPosted', 'Posted'),
            self::STATUS_CANCELLED => self::trans('TakeposExpenseStatusCancelled', 'Cancelled'),
        );
        return isset($map[(int) $status]) ? $map[(int) $status] : self::trans('TakeposExpenseStatusUnknown', 'Unknown');
    }

    public static function listStatuses()
    {
        return array(
            self::STATUS_DRAFT => self::statusLabel(self::STATUS_DRAFT),
            self::STATUS_SAVED => self::statusLabel(self::STATUS_SAVED),
            self::STATUS_POSTED => self::statusLabel(self::STATUS_POSTED),
            self::STATUS_CANCELLED => self::statusLabel(self::STATUS_CANCELLED),
        );
    }

    public static function paymentSourceLabel($paymentSource)
    {
        $sources = self::listPaymentSources();
        $paymentSourceKey = strtolower(trim((string) $paymentSource));
        return isset($sources[$paymentSourceKey]) ? $sources[$paymentSourceKey] : (string) $paymentSource;
    }

    public static function postedStateLabel($expenseRow)
    {
        return self::isExpensePosted($expenseRow)
            ? self::trans('TakeposExpensePostedStatePosted', 'Posted')
            : self::trans('TakeposExpensePostedStateNotPosted', 'Not Posted');
    }

    public static function isExpensePosted($expenseRow)
    {
        if (!is_object($expenseRow)) {
            return false;
        }

        return (
            (int) $expenseRow->status === self::STATUS_POSTED
            || !empty($expenseRow->fk_payment_various)
            || !empty($expenseRow->fk_bank_line)
            || !empty($expenseRow->date_posted)
        );
    }

    public static function canPostExpenseRecord($db, $user, $expenseRow)
    {
        return (self::canPost($db, $user) || self::canAdmin($db, $user))
            && !self::isExpensePosted($expenseRow)
            && (!is_object($expenseRow) || (int) $expenseRow->status !== self::STATUS_CANCELLED);
    }

    private static function hasNativeExpenseRight($user, $modules, $rights)
    {
        if (!is_object($user) || empty($user->rights)) {
            return false;
        }

        foreach ((array) $modules as $module) {
            if (empty($user->rights->{$module})) {
                continue;
            }

            foreach ((array) $rights as $right) {
                if (!empty($user->rights->{$module}->{$right})) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function userCan($db, $user, $permissionCode)
    {
        if (!empty($user->admin)) {
            return true;
        }

        $permissionCode = trim((string) $permissionCode);
        $expenseModules = array('expensereport', 'expensereports');

        if ($permissionCode === 'takepos.expense.read') {
            return self::hasNativeExpenseRight($user, $expenseModules, array('read', 'lire', 'create', 'creer', 'validate', 'valider', 'approve', 'approuver'))
                || TakeposUserAccess::userHasPermission($db, $user, $permissionCode);
        }

        if ($permissionCode === 'takepos.expense.create') {
            return self::hasNativeExpenseRight($user, $expenseModules, array('create', 'creer', 'write', 'validate', 'valider', 'approve', 'approuver'))
                || TakeposUserAccess::userHasPermission($db, $user, $permissionCode);
        }

        if ($permissionCode === 'takepos.expense.post') {
            return self::hasNativeExpenseRight($user, $expenseModules, array('validate', 'valider', 'approve', 'approuver', 'create', 'creer'))
                || TakeposUserAccess::userHasPermission($db, $user, $permissionCode);
        }

        if ($permissionCode === 'takepos.expense.admin') {
            return self::hasNativeExpenseRight($user, $expenseModules, array('validate', 'valider', 'approve', 'approuver', 'create', 'creer', 'write'))
                || TakeposUserAccess::userHasPermission($db, $user, $permissionCode);
        }

        return TakeposUserAccess::userHasPermission($db, $user, $permissionCode);
    }

    public static function canRead($db, $user)
    {
        return self::userCan($db, $user, 'takepos.expense.read');
    }

    public static function canCreate($db, $user)
    {
        return self::userCan($db, $user, 'takepos.expense.create');
    }

    public static function canPost($db, $user)
    {
        return self::userCan($db, $user, 'takepos.expense.post');
    }

    public static function canAdmin($db, $user)
    {
        return self::userCan($db, $user, 'takepos.expense.admin');
    }

    public static function listCategories($db, $entity, $visibleOnly = true)
    {
        self::ensureSchema($db);

        $sql = "SELECT rowid, label, accountancy_code, vat_default, active, pos_visible, datec, tms";
        $sql .= " FROM " . self::tableExpenseCategory();
        $sql .= " WHERE entity = " . ((int) $entity);
        if ($visibleOnly) {
            $sql .= " AND active = 1 AND pos_visible = 1";
        }
        $sql .= " ORDER BY label ASC";

        $rows = array();
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $rows[] = $obj;
            }
        }

        return $rows;
    }

    public static function getCategory($db, $entity, $categoryId)
    {
        self::ensureSchema($db);

        $sql = "SELECT rowid, label, accountancy_code, vat_default, active, pos_visible, datec, tms";
        $sql .= " FROM " . self::tableExpenseCategory();
        $sql .= " WHERE entity = " . ((int) $entity) . " AND rowid = " . ((int) $categoryId);
        $sql .= " LIMIT 1";

        $resql = $db->query($sql);
        return ($resql ? $db->fetch_object($resql) : null);
    }

    private static function categoryExistsByNormalizedLabel($db, $entity, $label, $excludeCategoryId = 0)
    {
        $normalizedLabel = TakeposInputValidator::normalizeCompareText($label, 128);
        if ($normalizedLabel === '') {
            return false;
        }

        $sql = "SELECT rowid, label";
        $sql .= " FROM " . self::tableExpenseCategory();
        $sql .= " WHERE entity = " . ((int) $entity);
        if ((int) $excludeCategoryId > 0) {
            $sql .= " AND rowid <> " . ((int) $excludeCategoryId);
        }

        $resql = $db->query($sql);
        if (!$resql) {
            throw new Exception($db->lasterror());
        }

        while ($obj = $db->fetch_object($resql)) {
            if (TakeposInputValidator::normalizeCompareText($obj->label, 128) === $normalizedLabel) {
                return true;
            }
        }

        return false;
    }

    public static function saveCategory($db, $user, $entity, $data, $existingCategoryId = 0)
    {
        self::ensureSchema($db);

        $entity = ((int) $entity > 0 ? (int) $entity : (!empty($user->entity) ? (int) $user->entity : 1));
        $existingCategoryId = (int) $existingCategoryId;

        if (!self::canAdmin($db, $user)) {
            self::safeAudit($db, $user, 'expense_category_save_rejected', TakeposAudit::SEVERITY_WARNING, array('reason' => 'permission_denied', 'category_id' => $existingCategoryId), 'Expense category save rejected');
            throw new Exception(self::trans('TakeposExpenseAdminPermissionRequired', 'Expense admin permission is required.'));
        }

        $label = TakeposInputValidator::normalizeUtf8Text(isset($data['label']) ? $data['label'] : '', 128, true);
        $accountancyCode = TakeposInputValidator::normalizeUtf8Text(isset($data['accountancy_code']) ? $data['accountancy_code'] : '', 64, true);
        $active = (!empty($data['active']) ? 1 : 0);
        $posVisible = (!empty($data['pos_visible']) ? 1 : 0);
        $vatDefault = null;

        if ($label === '') {
            throw new Exception(self::trans('TakeposExpenseErrorCategoryLabelRequired', 'Category label is required.'));
        }
        if ($accountancyCode === '') {
            throw new Exception(self::trans('TakeposExpenseErrorAccountCodeRequired', 'Accounting account code is required.'));
        }
        if (!TakeposInputValidator::parseDecimal(isset($data['vat_default']) ? $data['vat_default'] : null, $vatDefault, false, 4)) {
            throw new Exception(self::trans('TakeposExpenseErrorDefaultVatDecimal', 'Default VAT rate must be a valid decimal value.'));
        }
        if ((float) $vatDefault < 0) {
            throw new Exception(self::trans('TakeposExpenseErrorDefaultVatRange', 'Default VAT rate must be zero or greater.'));
        }
        if (self::categoryExistsByNormalizedLabel($db, $entity, $label, $existingCategoryId)) {
            throw new Exception(self::trans('TakeposExpenseErrorCategoryDuplicate', 'An expense category with the same label already exists.'));
        }

        $now = dol_print_date(dol_now(), 'dayhourlog');
        if ($existingCategoryId > 0) {
            $existing = self::getCategory($db, $entity, $existingCategoryId);
            if (!$existing) {
                throw new Exception(self::trans('TakeposExpenseErrorCategoryNotFound', 'Expense category not found.'));
            }

            $sql = "UPDATE " . self::tableExpenseCategory() . " SET";
            $sql .= " label='" . $db->escape($label) . "'";
            $sql .= ", accountancy_code='" . $db->escape($accountancyCode) . "'";
            $sql .= ", vat_default=" . ((float) $vatDefault);
            $sql .= ", active=" . ((int) $active);
            $sql .= ", pos_visible=" . ((int) $posVisible);
            $sql .= " WHERE entity = " . $entity . " AND rowid = " . $existingCategoryId;

            if (!$db->query($sql)) {
                self::safeAudit($db, $user, 'expense_category_save_rejected', TakeposAudit::SEVERITY_WARNING, array('reason' => 'db_error', 'error' => $db->lasterror(), 'category_id' => $existingCategoryId), 'Expense category save rejected');
                throw new Exception($db->lasterror());
            }

            self::safeAudit(
                $db,
                $user,
                'expense_category_updated',
                TakeposAudit::SEVERITY_INFO,
                array(
                    'category_id' => $existingCategoryId,
                    'label' => $label,
                    'accountancy_code' => $accountancyCode,
                    'vat_default' => (float) $vatDefault,
                    'active' => (int) $active,
                    'pos_visible' => (int) $posVisible,
                ),
                'Expense category updated',
                'expense_category',
                $existingCategoryId
            );

            return $existingCategoryId;
        }

        $sql = "INSERT INTO " . self::tableExpenseCategory() . " (entity, label, accountancy_code, vat_default, active, pos_visible, datec)";
        $sql .= " VALUES (" . $entity . ", '" . $db->escape($label) . "', '" . $db->escape($accountancyCode) . "', " . ((float) $vatDefault) . ", " . ((int) $active) . ", " . ((int) $posVisible) . ", '" . $db->escape($now) . "')";
        if (!$db->query($sql)) {
            self::safeAudit($db, $user, 'expense_category_save_rejected', TakeposAudit::SEVERITY_WARNING, array('reason' => 'db_error', 'error' => $db->lasterror()), 'Expense category save rejected');
            throw new Exception($db->lasterror());
        }

        $categoryId = (int) $db->last_insert_id(self::tableExpenseCategory());
        self::safeAudit(
            $db,
            $user,
            'expense_category_created',
            TakeposAudit::SEVERITY_INFO,
            array(
                'category_id' => $categoryId,
                'label' => $label,
                'accountancy_code' => $accountancyCode,
                'vat_default' => (float) $vatDefault,
                'active' => (int) $active,
                'pos_visible' => (int) $posVisible,
            ),
            'Expense category created',
            'expense_category',
            $categoryId
        );

        return $categoryId;
    }

    public static function setCategoryStatus($db, $user, $entity, $categoryId, $active)
    {
        self::ensureSchema($db);

        $entity = ((int) $entity > 0 ? (int) $entity : (!empty($user->entity) ? (int) $user->entity : 1));
        $categoryId = (int) $categoryId;
        $active = ((int) $active > 0 ? 1 : 0);

        if (!self::canAdmin($db, $user)) {
            self::safeAudit($db, $user, 'expense_category_status_rejected', TakeposAudit::SEVERITY_WARNING, array('reason' => 'permission_denied', 'category_id' => $categoryId), 'Expense category status update rejected');
            throw new Exception(self::trans('TakeposExpenseAdminPermissionRequired', 'Expense admin permission is required.'));
        }

        $category = self::getCategory($db, $entity, $categoryId);
        if (!$category) {
            throw new Exception(self::trans('TakeposExpenseErrorCategoryNotFound', 'Expense category not found.'));
        }

        $sql = "UPDATE " . self::tableExpenseCategory() . " SET active = " . $active . " WHERE entity = " . $entity . " AND rowid = " . $categoryId;
        if (!$db->query($sql)) {
            self::safeAudit($db, $user, 'expense_category_status_rejected', TakeposAudit::SEVERITY_WARNING, array('reason' => 'db_error', 'error' => $db->lasterror(), 'category_id' => $categoryId), 'Expense category status update rejected');
            throw new Exception($db->lasterror());
        }

        self::safeAudit(
            $db,
            $user,
            ($active ? 'expense_category_enabled' : 'expense_category_disabled'),
            TakeposAudit::SEVERITY_INFO,
            array('category_id' => $categoryId, 'active' => $active, 'label' => (string) $category->label),
            ($active ? 'Expense category enabled' : 'Expense category disabled'),
            'expense_category',
            $categoryId
        );

        return true;
    }

    public static function ensureDefaultCategories($db, $entity = null)
    {
        $entity = ((int) $entity > 0 ? (int) $entity : (!empty($GLOBALS['conf']->entity) ? (int) $GLOBALS['conf']->entity : 1));
        $defaults = array(
            array('label' => 'Cleaning Expense', 'accountancy_code' => '611000', 'vat_default' => 0),
            array('label' => 'Maintenance Expense', 'accountancy_code' => '615000', 'vat_default' => 0),
            array('label' => 'Office Supplies', 'accountancy_code' => '606300', 'vat_default' => 0),
            array('label' => 'Fuel / Transport', 'accountancy_code' => '625100', 'vat_default' => 0),
            array('label' => 'Petty Cash Expense', 'accountancy_code' => '658000', 'vat_default' => 0),
            array('label' => 'Store Misc Expense', 'accountancy_code' => '658000', 'vat_default' => 0),
        );

        foreach ($defaults as $row) {
            $sql = "SELECT rowid FROM " . self::tableExpenseCategory();
            $sql .= " WHERE entity = " . $entity . " AND label = '" . $db->escape($row['label']) . "'";
            $sql .= " LIMIT 1";
            $resql = $db->query($sql);
            if ($resql && $db->fetch_object($resql)) {
                continue;
            }

            $insert = "INSERT INTO " . self::tableExpenseCategory() . " (entity, label, accountancy_code, vat_default, active, pos_visible, datec) VALUES (";
            $insert .= $entity . ", '" . $db->escape($row['label']) . "', '" . $db->escape($row['accountancy_code']) . "', ";
            $insert .= ((float) $row['vat_default']) . ", 1, 1, '" . $db->escape(dol_print_date(dol_now(), 'dayhourlog')) . "')";
            $db->query($insert);
        }
    }

    private static function generateExpenseRef($entity)
    {
        return 'EXP-POS-' . dol_print_date(dol_now(), '%Y%m%d-%H%M%S') . '-' . strtoupper(substr(sha1(uniqid((string) $entity, true)), 0, 4));
    }

    private static function normalizeDateInput($rawValue, $allowControlledDate)
    {
        if (!$allowControlledDate) {
            return dol_now();
        }

        $rawValue = trim((string) $rawValue);
        if ($rawValue === '') {
            return dol_now();
        }

        $timestamp = strtotime($rawValue);
        if ($timestamp === false || $timestamp <= 0) {
            return dol_now();
        }

        return $timestamp;
    }

    private static function splitVatAmounts($amountTtc, $vatRate)
    {
        $amountTtc = (float) price2num((string) $amountTtc, 'MU');
        $vatRate = (float) price2num((string) $vatRate, 'MU');

        if ($vatRate <= 0) {
            return array(
                'amount_ht' => $amountTtc,
                'amount_tva' => 0.0,
                'amount_ttc' => $amountTtc,
            );
        }

        $amountHt = round($amountTtc / (1 + ($vatRate / 100)), 8);
        $amountTva = round($amountTtc - $amountHt, 8);

        return array(
            'amount_ht' => $amountHt,
            'amount_tva' => $amountTva,
            'amount_ttc' => $amountTtc,
        );
    }

    public static function listAccessibleTerminals($db, $user, $activeOnly = true)
    {
        self::ensureSchema($db);

        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        $terminals = TakeposTerminalService::listTerminals($db, $entity, 0, $activeOnly);
        if (!TakeposStoreService::enforceStoreRestrictionEnabled($db) || !empty($user->admin) || TakeposUserAccess::userHasPermission($db, $user, 'takepos.store.view_all')) {
            return $terminals;
        }

        $allowed = array();
        foreach ($terminals as $terminal) {
            if (empty($terminal->fk_store) || TakeposStoreService::userCanAccessStore($db, $user, (int) $terminal->fk_store, $entity)) {
                $allowed[] = $terminal;
            }
        }

        return $allowed;
    }

    public static function listBankAccounts($db, $entity)
    {
        if (!isModEnabled('bank')) {
            return array();
        }

        $rows = array();
        $sql = "SELECT rowid, ref, label";
        $sql .= " FROM " . MAIN_DB_PREFIX . "bank_account";
        $sql .= " WHERE entity IN (" . getEntity('bank_account') . ")";
        $sql .= " AND clos = 0";
        $sql .= " ORDER BY ref ASC, label ASC";

        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $rows[] = $obj;
            }
        }

        return $rows;
    }

    public static function getExpenseById($db, $entity, $expenseId)
    {
        self::ensureSchema($db);

        $sql = "SELECT e.*, c.label AS category_label, c.accountancy_code AS category_accountancy_code,";
        $sql .= " t.terminal_code, t.label AS terminal_label,";
        $sql .= " s.label AS store_label,";
        $sql .= " sh.shift_ref,";
        $sql .= " u.login AS user_login,";
        $sql .= " bu.login AS posted_user_login,";
        $sql .= " ba.ref AS bank_account_ref, ba.label AS bank_account_label";
        $sql .= " FROM " . self::tableExpense() . " e";
        $sql .= " LEFT JOIN " . self::tableExpenseCategory() . " c ON c.rowid = e.fk_category";
        $sql .= " LEFT JOIN " . TakeposTerminalService::tableTerminal() . " t ON t.rowid = e.fk_terminal";
        $sql .= " LEFT JOIN " . TakeposStoreService::tableStore() . " s ON s.rowid = e.fk_store";
        $sql .= " LEFT JOIN " . TakeposShiftService::tableShift() . " sh ON sh.rowid = e.fk_shift";
        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid = e.fk_user";
        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "user bu ON bu.rowid = e.fk_posted_user";
        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "bank_account ba ON ba.rowid = e.fk_bank_account";
        $sql .= " WHERE e.entity = " . ((int) $entity) . " AND e.rowid = " . ((int) $expenseId);
        $sql .= " LIMIT 1";

        $resql = $db->query($sql);
        return ($resql ? $db->fetch_object($resql) : null);
    }

    private static function buildExpenseLedgerBaseSql($db, $entity, $viewerUser, $filters = array())
    {
        $entity = (int) $entity;
        $sql = " FROM " . self::tableExpense() . " e";
        $sql .= " LEFT JOIN " . self::tableExpenseCategory() . " c ON c.rowid = e.fk_category";
        $sql .= " LEFT JOIN " . TakeposTerminalService::tableTerminal() . " t ON t.rowid = e.fk_terminal";
        $sql .= " LEFT JOIN " . TakeposStoreService::tableStore() . " s ON s.rowid = e.fk_store";
        $sql .= " LEFT JOIN " . TakeposShiftService::tableShift() . " sh ON sh.rowid = e.fk_shift";
        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid = e.fk_user";
        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "user bu ON bu.rowid = e.fk_posted_user";
        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "bank_account ba ON ba.rowid = e.fk_bank_account";
        $sql .= " WHERE e.entity = " . $entity;

        if (is_object($viewerUser)
            && !empty($viewerUser->id)
            && empty($viewerUser->admin)
            && !TakeposUserAccess::userHasPermission($db, $viewerUser, 'takepos.store.view_all')
            && TakeposStoreService::enforceStoreRestrictionEnabled($db)
        ) {
            $allowedStoreIds = TakeposStoreService::getUserStoreIds($db, $entity, (int) $viewerUser->id);
            if (empty($allowedStoreIds)) {
                $sql .= " AND (e.fk_store IS NULL OR e.fk_store = 0)";
            } else {
                $cleanStoreIds = array();
                foreach ($allowedStoreIds as $allowedStoreId) {
                    $allowedStoreId = (int) $allowedStoreId;
                    if ($allowedStoreId > 0) {
                        $cleanStoreIds[] = $allowedStoreId;
                    }
                }
                if (!empty($cleanStoreIds)) {
                    $sql .= " AND (e.fk_store IS NULL OR e.fk_store = 0 OR e.fk_store IN (" . implode(',', $cleanStoreIds) . "))";
                }
            }
        }

        if (!empty($filters['date_from'])) {
            $timestamp = strtotime((string) $filters['date_from'] . ' 00:00:00');
            if ($timestamp !== false && $timestamp > 0) {
                $sql .= " AND e.date_expense >= '" . $db->escape(dol_print_date($timestamp, 'dayhourlog')) . "'";
            }
        }
        if (!empty($filters['date_to'])) {
            $timestamp = strtotime((string) $filters['date_to'] . ' 23:59:59');
            if ($timestamp !== false && $timestamp > 0) {
                $sql .= " AND e.date_expense <= '" . $db->escape(dol_print_date($timestamp, 'dayhourlog')) . "'";
            }
        }
        if (!empty($filters['fk_category'])) {
            $sql .= " AND e.fk_category = " . ((int) $filters['fk_category']);
        }
        if (!empty($filters['fk_terminal'])) {
            $sql .= " AND e.fk_terminal = " . ((int) $filters['fk_terminal']);
        }
        if (!empty($filters['fk_user'])) {
            $sql .= " AND e.fk_user = " . ((int) $filters['fk_user']);
        }
        if (!empty($filters['status']) || (string) $filters['status'] === '0') {
            $sql .= " AND e.status = " . ((int) $filters['status']);
        }
        if (!empty($filters['posting_state'])) {
            if ((string) $filters['posting_state'] === 'posted') {
                $sql .= " AND (e.status = " . self::STATUS_POSTED . " OR e.fk_payment_various IS NOT NULL OR e.fk_bank_line IS NOT NULL OR e.date_posted IS NOT NULL)";
            } elseif ((string) $filters['posting_state'] === 'not_posted') {
                $sql .= " AND (e.status <> " . self::STATUS_POSTED . " AND e.fk_payment_various IS NULL AND e.fk_bank_line IS NULL AND e.date_posted IS NULL)";
            }
        }
        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            if ($search !== '') {
                $searchLike = "'%" . $db->escape($search) . "%'";
                $sql .= " AND (";
                $sql .= "e.ref LIKE " . $searchLike;
                $sql .= " OR e.description LIKE " . $searchLike;
                $sql .= " OR e.external_ref LIKE " . $searchLike;
                $sql .= " OR e.note_private LIKE " . $searchLike;
                $sql .= " OR c.label LIKE " . $searchLike;
                $sql .= ")";
            }
        }

        return $sql;
    }

    private static function resolveLedgerSort($sortField, $sortOrder)
    {
        $allowedSorts = array(
            'date' => 'e.date_expense',
            'ref' => 'e.ref',
            'user' => 'u.login',
            'category' => 'c.label',
            'amount_ttc' => 'e.amount_ttc',
            'status' => 'e.status',
        );

        $sortField = strtolower(trim((string) $sortField));
        $sortOrder = strtoupper(trim((string) $sortOrder));
        if (!isset($allowedSorts[$sortField])) {
            $sortField = 'date';
        }
        if ($sortOrder !== 'ASC' && $sortOrder !== 'DESC') {
            $sortOrder = 'DESC';
        }

        return array($allowedSorts[$sortField], $sortOrder, $sortField);
    }

    private static function buildExpenseLedgerListSql($db, $entity, $viewerUser = null, $filters = array(), $sortField = 'date', $sortOrder = 'DESC', $limit = null, $offset = 0)
    {
        list($orderBy, $resolvedOrder) = self::resolveLedgerSort($sortField, $sortOrder);

        $sql = "SELECT e.rowid, e.ref, e.date_expense, e.description, e.amount_ht, e.amount_tva, e.amount_ttc, e.vat_rate,";
        $sql .= " e.payment_source, e.status, e.date_posted, e.accountancy_code, e.fk_terminal, e.fk_store, e.fk_shift, e.fk_user,";
        $sql .= " e.fk_payment_various, e.fk_bank_line, e.fk_cash_movement, e.fk_posted_user, e.external_ref, e.note_private,";
        $sql .= " c.label AS category_label,";
        $sql .= " t.terminal_code, t.label AS terminal_label,";
        $sql .= " s.label AS store_label,";
        $sql .= " sh.shift_ref,";
        $sql .= " u.login AS user_login, u.firstname AS user_firstname, u.lastname AS user_lastname,";
        $sql .= " bu.login AS posted_user_login,";
        $sql .= " ba.ref AS bank_account_ref, ba.label AS bank_account_label";
        $sql .= self::buildExpenseLedgerBaseSql($db, $entity, $viewerUser, $filters);
        $sql .= " ORDER BY " . $orderBy . " " . $resolvedOrder . ", e.rowid DESC";

        if ($limit !== null) {
            $limit = max(1, min(200, (int) $limit));
            $offset = max(0, (int) $offset);
            $sql .= " LIMIT " . $limit . " OFFSET " . $offset;
        }

        return $sql;
    }

    public static function listExpenseUsers($db, $entity, $viewerUser = null)
    {
        self::ensureSchema($db);

        $sql = "SELECT DISTINCT u.rowid, u.login, u.firstname, u.lastname";
        $sql .= self::buildExpenseLedgerBaseSql($db, $entity, $viewerUser, array());
        $sql .= " ORDER BY u.login ASC";

        $rows = array();
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                if ((int) $obj->rowid > 0) {
                    $rows[] = $obj;
                }
            }
        }

        return $rows;
    }

    public static function countExpenses($db, $entity, $viewerUser = null, $filters = array())
    {
        self::ensureSchema($db);

        $sql = "SELECT COUNT(*) AS nb";
        $sql .= self::buildExpenseLedgerBaseSql($db, $entity, $viewerUser, $filters);
        $resql = $db->query($sql);
        if ($resql && ($obj = $db->fetch_object($resql))) {
            return (int) $obj->nb;
        }

        return 0;
    }

    public static function summarizeExpenses($db, $entity, $viewerUser = null, $filters = array())
    {
        self::ensureSchema($db);

        $sql = "SELECT";
        $sql .= " COUNT(*) AS total_count,";
        $sql .= " COALESCE(SUM(e.amount_ht), 0) AS total_amount_ht,";
        $sql .= " COALESCE(SUM(e.amount_tva), 0) AS total_amount_tva,";
        $sql .= " COALESCE(SUM(e.amount_ttc), 0) AS total_amount_ttc,";
        $sql .= " SUM(CASE WHEN (e.status = " . self::STATUS_POSTED . " OR e.fk_payment_various IS NOT NULL OR e.fk_bank_line IS NOT NULL OR e.date_posted IS NOT NULL) THEN 1 ELSE 0 END) AS posted_count,";
        $sql .= " SUM(CASE WHEN (e.status <> " . self::STATUS_POSTED . " AND e.fk_payment_various IS NULL AND e.fk_bank_line IS NULL AND e.date_posted IS NULL) THEN 1 ELSE 0 END) AS not_posted_count,";
        $sql .= " SUM(CASE WHEN e.payment_source IN ('" . $db->escape(self::SOURCE_CASH_REGISTER) . "','" . $db->escape(self::SOURCE_PETTY_CASH) . "') THEN 1 ELSE 0 END) AS cash_source_count";
        $sql .= self::buildExpenseLedgerBaseSql($db, $entity, $viewerUser, $filters);

        $resql = $db->query($sql);
        return ($resql ? $db->fetch_object($resql) : null);
    }

    public static function listExpenses($db, $entity, $filters = array(), $limit = 50, $offset = 0, $sortField = 'date', $sortOrder = 'DESC', $viewerUser = null)
    {
        self::ensureSchema($db);

        $sql = self::buildExpenseLedgerListSql($db, $entity, $viewerUser, $filters, $sortField, $sortOrder, $limit, $offset);

        $rows = array();
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $rows[] = $obj;
            }
        }

        return $rows;
    }

    private static function normalizeCsvText($value)
    {
        $text = trim((string) $value);
        $text = str_replace(array("\r\n", "\r", "\n"), ' ', $text);
        return preg_replace('/\s+/u', ' ', $text);
    }

    public static function streamExpensesCsv($db, $entity, $viewerUser = null, $filters = array(), $sortField = 'date', $sortOrder = 'DESC', $outputHandle = null)
    {
        self::ensureSchema($db);

        $closeHandle = false;
        if (!is_resource($outputHandle)) {
            $outputHandle = fopen('php://output', 'w');
            $closeHandle = true;
        }

        fwrite($outputHandle, chr(0xEF) . chr(0xBB) . chr(0xBF));

        $headers = array(
            self::trans('TakeposExpenseDate', 'Expense Date'),
            self::trans('TakeposExpenseReference', 'Reference'),
            self::trans('TakeposExpenseUser', 'User'),
            self::trans('TakeposExpenseTerminal', 'Terminal / Register'),
            self::trans('TakeposExpenseStore', 'Store'),
            self::trans('TakeposExpenseShift', 'Shift'),
            self::trans('TakeposExpenseCategory', 'Category'),
            self::trans('TakeposExpenseDescription', 'Description'),
            self::trans('TakeposExpenseAmountHt', 'Amount HT'),
            self::trans('TakeposExpenseVatAmount', 'VAT Amount'),
            self::trans('TakeposExpenseAmountTtc', 'Amount TTC'),
            self::trans('TakeposCommonStatus', 'Status'),
            self::trans('TakeposExpensePostedState', 'Posted State'),
            self::trans('TakeposExpenseAccountingAccount', 'Accounting Account'),
            self::trans('TakeposExpensePaymentSource', 'Payment Source'),
            self::trans('TakeposExpenseExternalReference', 'External Reference'),
            self::trans('TakeposExpensePostedBy', 'Posted By'),
            self::trans('TakeposExpenseDatePosted', 'Posted At'),
            self::trans('TakeposExpenseVariousPaymentIdRef', 'Payment Various ID / Ref'),
            self::trans('TakeposExpenseBankLineId', 'Bank Line ID'),
        );
        fputcsv($outputHandle, $headers);

        $sql = self::buildExpenseLedgerListSql($db, $entity, $viewerUser, $filters, $sortField, $sortOrder, null, 0);
        $resql = $db->query($sql);
        if (!$resql) {
            if ($closeHandle) {
                fclose($outputHandle);
            }
            throw new Exception($db->lasterror());
        }

        while ($row = $db->fetch_object($resql)) {
            $userLabel = trim((string) $row->user_login);
            if ($userLabel === '') {
                $userLabel = trim((string) $row->user_firstname . ' ' . (string) $row->user_lastname);
            }
            $terminalLabel = trim((string) $row->terminal_code . ' - ' . (string) $row->terminal_label);
            $csvRow = array(
                (string) $row->date_expense,
                (string) $row->ref,
                ($userLabel !== '' ? $userLabel : (self::trans('TakeposExpenseUserIdPrefix', 'User #') . ((int) $row->fk_user))),
                ($terminalLabel !== '' ? $terminalLabel : ''),
                (string) $row->store_label,
                (!empty($row->shift_ref) ? (string) $row->shift_ref : (!empty($row->fk_shift) ? ('#' . ((int) $row->fk_shift)) : '')),
                (string) $row->category_label,
                self::normalizeCsvText($row->description),
                price2num((string) $row->amount_ht, 'MU'),
                price2num((string) $row->amount_tva, 'MU'),
                price2num((string) $row->amount_ttc, 'MU'),
                self::statusLabel((int) $row->status),
                self::postedStateLabel($row),
                (string) $row->accountancy_code,
                self::paymentSourceLabel($row->payment_source),
                self::normalizeCsvText($row->external_ref),
                (string) $row->posted_user_login,
                (string) $row->date_posted,
                (!empty($row->fk_payment_various) ? self::trans('TakeposExpensePaymentVariousShort', 'PV #') . ((int) $row->fk_payment_various) : ''),
                (!empty($row->fk_bank_line) ? (int) $row->fk_bank_line : ''),
            );
            fputcsv($outputHandle, $csvRow);
        }

        if ($closeHandle) {
            fclose($outputHandle);
        }

        return true;
    }

    public static function getCurrentTerminal($db, $user, $sessionTerminalToken)
    {
        self::ensureSchema($db);
        return TakeposTerminalService::resolveCurrentTerminal($db, $user, $sessionTerminalToken);
    }

    private static function validateTerminalAccess($db, $user, $terminalRow)
    {
        if (!$terminalRow || empty($terminalRow->rowid)) {
            throw new Exception(self::trans('TakeposExpenseErrorTerminalRequired', 'Terminal is required.'));
        }

        if (!TakeposStoreService::enforceStoreRestrictionEnabled($db) || empty($terminalRow->fk_store)) {
            return true;
        }

        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        if (!empty($user->admin) || TakeposUserAccess::userHasPermission($db, $user, 'takepos.store.view_all')) {
            return true;
        }

        if (!TakeposStoreService::userCanAccessStore($db, $user, (int) $terminalRow->fk_store, $entity)) {
            self::safeAudit($db, $user, 'store_restriction_denied', TakeposAudit::SEVERITY_WARNING, array('store_id' => (int) $terminalRow->fk_store, 'action' => 'expense_terminal'), 'Expense store restriction denied');
            throw new Exception(self::trans('TakeposExpenseErrorStoreTerminalDenied', 'You are not allowed to use the selected store terminal.'));
        }

        return true;
    }

    private static function findTerminalById($db, $entity, $terminalId)
    {
        $sql = "SELECT rowid, entity, terminal_code, label, fk_store, active";
        $sql .= " FROM " . TakeposTerminalService::tableTerminal();
        $sql .= " WHERE entity = " . ((int) $entity) . " AND rowid = " . ((int) $terminalId);
        $sql .= " LIMIT 1";
        $resql = $db->query($sql);
        return ($resql ? $db->fetch_object($resql) : null);
    }

    private static function resolveActiveShiftId($db, $user, $terminalId)
    {
        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        $shift = TakeposShiftService::getActiveShiftForCashier($db, $entity, (int) $user->id, (int) $terminalId);
        if ($shift && !empty($shift->rowid)) {
            return (int) $shift->rowid;
        }

        $shift = TakeposShiftService::getActiveShiftForTerminal($db, $entity, (int) $terminalId);
        if ($shift && !empty($shift->rowid)) {
            return (int) $shift->rowid;
        }

        return 0;
    }

    public static function saveExpense($db, $user, $data, $existingExpenseId = 0)
    {
        self::ensureSchema($db);

        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        $existingExpenseId = (int) $existingExpenseId;
        $isAdminExpense = self::canAdmin($db, $user);

        self::safeAudit($db, $user, 'expense_save_attempt', TakeposAudit::SEVERITY_INFO, array('expense_id' => $existingExpenseId), 'POS expense save requested');

        if (!self::canCreate($db, $user) && !$isAdminExpense) {
            self::safeAudit($db, $user, 'expense_save_rejected', TakeposAudit::SEVERITY_WARNING, array('reason' => 'permission_denied'), 'POS expense save rejected');
            throw new Exception(self::trans('TakeposExpenseErrorCreatePermission', 'Expense create permission is required.'));
        }

        if (!TakeposInputValidator::parsePositiveInteger(isset($data['fk_terminal']) ? $data['fk_terminal'] : null, $terminalId, false)) {
            self::safeAudit($db, $user, 'expense_save_rejected', TakeposAudit::SEVERITY_WARNING, array('reason' => 'invalid_terminal'), 'POS expense save rejected');
            throw new Exception(self::trans('TakeposExpenseErrorTerminalRequired', 'Terminal is required.'));
        }

        $terminal = self::findTerminalById($db, $entity, $terminalId);
        if (!$terminal || ((int) $terminal->active) !== 1) {
            self::safeAudit($db, $user, 'expense_save_rejected', TakeposAudit::SEVERITY_WARNING, array('reason' => 'terminal_inactive', 'terminal_id' => $terminalId), 'POS expense save rejected');
            throw new Exception(self::trans('TakeposExpenseErrorSelectedTerminalInvalid', 'Selected terminal is invalid or inactive.'));
        }
        self::validateTerminalAccess($db, $user, $terminal);

        if (!TakeposInputValidator::parsePositiveInteger(isset($data['fk_category']) ? $data['fk_category'] : null, $categoryId, false)) {
            self::safeAudit($db, $user, 'expense_save_rejected', TakeposAudit::SEVERITY_WARNING, array('reason' => 'invalid_category'), 'POS expense save rejected');
            throw new Exception(self::trans('TakeposExpenseErrorCategoryRequired', 'Expense category is required.'));
        }

        $category = self::getCategory($db, $entity, $categoryId);
        if (!$category || empty($category->active) || empty($category->pos_visible)) {
            self::safeAudit($db, $user, 'expense_save_rejected', TakeposAudit::SEVERITY_WARNING, array('reason' => 'category_inactive', 'category_id' => $categoryId), 'POS expense save rejected');
            throw new Exception(self::trans('TakeposExpenseErrorSelectedCategoryInvalid', 'Selected expense category is invalid or inactive.'));
        }

        $description = TakeposInputValidator::normalizeUtf8Text(isset($data['description']) ? $data['description'] : '', 255, true);
        if ($description === '') {
            self::safeAudit($db, $user, 'expense_save_rejected', TakeposAudit::SEVERITY_WARNING, array('reason' => 'missing_description'), 'POS expense save rejected');
            throw new Exception(self::trans('TakeposExpenseErrorDescriptionRequired', 'Description is required.'));
        }

        if (!TakeposInputValidator::parsePositiveDecimal(isset($data['amount_ttc']) ? $data['amount_ttc'] : null, $amountTtc, false, 8) || (float) $amountTtc <= 0) {
            self::safeAudit($db, $user, 'expense_save_rejected', TakeposAudit::SEVERITY_WARNING, array('reason' => 'invalid_amount'), 'POS expense save rejected');
            throw new Exception(self::trans('TakeposExpenseErrorAmountPositive', 'Amount must be greater than zero.'));
        }

        if (!TakeposInputValidator::parseDecimal(isset($data['vat_rate']) ? $data['vat_rate'] : null, $vatRate, false, 4)) {
            self::safeAudit($db, $user, 'expense_save_rejected', TakeposAudit::SEVERITY_WARNING, array('reason' => 'invalid_vat'), 'POS expense save rejected');
            throw new Exception(self::trans('TakeposExpenseErrorVatDecimal', 'VAT rate must be a valid decimal value.'));
        }
        if ((float) $vatRate < 0 || (float) $vatRate > 100) {
            self::safeAudit($db, $user, 'expense_save_rejected', TakeposAudit::SEVERITY_WARNING, array('reason' => 'vat_out_of_range', 'vat_rate' => $vatRate), 'POS expense save rejected');
            throw new Exception(self::trans('TakeposExpenseErrorVatRange', 'VAT rate must be between 0 and 100.'));
        }

        $paymentSource = self::normalizePaymentSource(isset($data['payment_source']) ? $data['payment_source'] : '');
        $notePrivate = TakeposInputValidator::normalizeUtf8Text(isset($data['note_private']) ? $data['note_private'] : '', 0, false);
        $externalRef = TakeposInputValidator::normalizeUtf8Text(isset($data['external_ref']) ? $data['external_ref'] : '', 128, true);
        $dateExpenseTs = self::normalizeDateInput(isset($data['date_expense']) ? $data['date_expense'] : '', $isAdminExpense);
        $dateExpense = dol_print_date($dateExpenseTs, 'dayhourlog');
        $amounts = self::splitVatAmounts($amountTtc, $vatRate);

        $bankAccountId = 0;
        if (!empty($data['fk_bank_account']) && TakeposInputValidator::parsePositiveInteger($data['fk_bank_account'], $bankAccountId, false)) {
            $bankAccountId = (int) $bankAccountId;
        }

        $storeId = !empty($terminal->fk_store) ? (int) $terminal->fk_store : 0;
        $shiftId = self::resolveActiveShiftId($db, $user, (int) $terminal->rowid);
        $accountancyCode = trim((string) $category->accountancy_code);
        $now = dol_print_date(dol_now(), 'dayhourlog');

        if ($existingExpenseId > 0) {
            $existing = self::getExpenseById($db, $entity, $existingExpenseId);
            if (!$existing) {
                throw new Exception(self::trans('TakeposExpenseErrorRecordNotFound', 'Expense record not found.'));
            }
            // Posted expenses stay immutable to prevent drift from already-created
            // accounting and bank records.
            if ((int) $existing->status === self::STATUS_POSTED) {
                throw new Exception(self::trans('TakeposExpenseErrorPostedImmutable', 'Posted expense cannot be edited once accounting posting exists.'));
            }
            if ((int) $existing->fk_user !== (int) $user->id && !$isAdminExpense) {
                throw new Exception(self::trans('TakeposExpenseErrorEditOtherUser', 'You are not allowed to edit another user expense.'));
            }

            $sql = "UPDATE " . self::tableExpense() . " SET";
            $sql .= " date_expense='" . $db->escape($dateExpense) . "'";
            $sql .= ", fk_terminal=" . ((int) $terminal->rowid);
            $sql .= ", fk_store=" . ($storeId > 0 ? $storeId : 'NULL');
            $sql .= ", fk_shift=" . ($shiftId > 0 ? $shiftId : 'NULL');
            $sql .= ", fk_category=" . ((int) $categoryId);
            $sql .= ", description='" . $db->escape($description) . "'";
            $sql .= ", amount_ht=" . ((float) $amounts['amount_ht']);
            $sql .= ", amount_tva=" . ((float) $amounts['amount_tva']);
            $sql .= ", amount_ttc=" . ((float) $amounts['amount_ttc']);
            $sql .= ", vat_rate=" . ((float) $vatRate);
            $sql .= ", payment_source='" . $db->escape($paymentSource) . "'";
            $sql .= ", note_private=" . ($notePrivate !== '' ? "'" . $db->escape($notePrivate) . "'" : 'NULL');
            $sql .= ", external_ref=" . ($externalRef !== '' ? "'" . $db->escape($externalRef) . "'" : 'NULL');
            $sql .= ", accountancy_code=" . ($accountancyCode !== '' ? "'" . $db->escape($accountancyCode) . "'" : 'NULL');
            $sql .= ", fk_bank_account=" . ($bankAccountId > 0 ? $bankAccountId : 'NULL');
            $sql .= ", status=" . self::STATUS_SAVED;
            $sql .= " WHERE entity = " . $entity . " AND rowid = " . $existingExpenseId;

            if (!$db->query($sql)) {
                self::safeAudit($db, $user, 'expense_save_rejected', TakeposAudit::SEVERITY_WARNING, array('reason' => 'db_error', 'error' => $db->lasterror()), 'POS expense save rejected');
                throw new Exception($db->lasterror());
            }

            self::safeAudit($db, $user, 'expense_save_success', TakeposAudit::SEVERITY_INFO, array('expense_id' => $existingExpenseId, 'ref' => (string) $existing->ref, 'amount_ttc' => (float) $amounts['amount_ttc'], 'payment_source' => $paymentSource), 'POS expense updated', 'expense', $existingExpenseId, (float) $amounts['amount_ttc']);
            return $existingExpenseId;
        }

        $ref = self::generateExpenseRef($entity);
        $importKey = substr(sha1($ref . '|' . dol_now() . '|' . (int) $user->id), 0, 40);

        $sql = "INSERT INTO " . self::tableExpense() . " (entity, ref, date_expense, fk_user, fk_terminal, fk_store, fk_shift, fk_category, description, amount_ht, amount_tva, amount_ttc, vat_rate, payment_source, note_private, external_ref, status, accountancy_code, fk_bank_account, import_key, datec) VALUES (";
        $sql .= $entity . ", '" . $db->escape($ref) . "', '" . $db->escape($dateExpense) . "', " . ((int) $user->id) . ", ";
        $sql .= ((int) $terminal->rowid) . ", " . ($storeId > 0 ? $storeId : 'NULL') . ", " . ($shiftId > 0 ? $shiftId : 'NULL') . ", ";
        $sql .= ((int) $categoryId) . ", '" . $db->escape($description) . "', ";
        $sql .= ((float) $amounts['amount_ht']) . ", " . ((float) $amounts['amount_tva']) . ", " . ((float) $amounts['amount_ttc']) . ", ";
        $sql .= ((float) $vatRate) . ", '" . $db->escape($paymentSource) . "', ";
        $sql .= ($notePrivate !== '' ? "'" . $db->escape($notePrivate) . "'" : 'NULL') . ", ";
        $sql .= ($externalRef !== '' ? "'" . $db->escape($externalRef) . "'" : 'NULL') . ", ";
        $sql .= self::STATUS_SAVED . ", ";
        $sql .= ($accountancyCode !== '' ? "'" . $db->escape($accountancyCode) . "'" : 'NULL') . ", ";
        $sql .= ($bankAccountId > 0 ? $bankAccountId : 'NULL') . ", ";
        $sql .= "'" . $db->escape($importKey) . "', '" . $db->escape($now) . "')";

        if (!$db->query($sql)) {
            self::safeAudit($db, $user, 'expense_save_rejected', TakeposAudit::SEVERITY_WARNING, array('reason' => 'db_error', 'error' => $db->lasterror()), 'POS expense save rejected');
            throw new Exception($db->lasterror());
        }

        $expenseId = (int) $db->last_insert_id(self::tableExpense());
        self::safeAudit($db, $user, 'expense_save_success', TakeposAudit::SEVERITY_INFO, array('expense_id' => $expenseId, 'ref' => $ref, 'amount_ttc' => (float) $amounts['amount_ttc'], 'payment_source' => $paymentSource), 'POS expense saved', 'expense', $expenseId, (float) $amounts['amount_ttc']);
        return $expenseId;
    }

    private static function resolvePostingBankAccountId($expense, $sessionTerminalToken)
    {
        $paymentSource = self::normalizePaymentSource($expense->payment_source);
        if ($paymentSource === self::SOURCE_BANK_ACCOUNT && (int) $expense->fk_bank_account > 0) {
            return (int) $expense->fk_bank_account;
        }

        if ($paymentSource === self::SOURCE_BANK_ACCOUNT) {
            throw new Exception(self::trans('TakeposExpenseErrorBankSourceRequired', 'Bank source account is required for bank-account expense posting.'));
        }

        $terminalToken = trim(!empty($expense->terminal_code) ? (string) $expense->terminal_code : (string) $sessionTerminalToken);
        if ($terminalToken === '') {
            $terminalToken = '1';
        }

        $bankAccountId = (int) getDolGlobalInt('CASHDESK_ID_BANKACCOUNT_CASH' . $terminalToken);
        if ($bankAccountId <= 0) {
            throw new Exception(self::trans('TakeposExpenseErrorCashBankNotConfigured', 'No POS cash bank account is configured for the current terminal.'));
        }

        return $bankAccountId;
    }

    private static function resolvePaymentTypeId($db, $paymentSource)
    {
        $code = (self::normalizePaymentSource($paymentSource) === self::SOURCE_BANK_ACCOUNT ? 'VIR' : 'LIQ');
        $sql = "SELECT id FROM " . MAIN_DB_PREFIX . "c_paiement";
        $sql .= " WHERE code = '" . $db->escape($code) . "'";
        $sql .= " AND entity IN (" . getEntity('c_paiement') . ")";
        $sql .= " AND active = 1";
        $sql .= " ORDER BY entity DESC LIMIT 1";
        $resql = $db->query($sql);
        if ($resql && ($obj = $db->fetch_object($resql))) {
            return (int) $obj->id;
        }

        throw new Exception(self::trans('TakeposExpenseErrorPaymentModeUnavailable', 'Unable to resolve payment mode for expense posting.'));
    }

    private static function buildPostingLabel($expense)
    {
        $label = trim((string) $expense->ref . ' - ' . (string) $expense->description);
        if (function_exists('dol_string_nohtmltag')) {
            $label = dol_string_nohtmltag($label);
        }
        if (function_exists('dol_trunc')) {
            $label = dol_trunc($label, 120);
        }
        return $label;
    }

    public static function postExpense($db, $user, $expenseId, $sessionTerminalToken)
    {
        self::ensureSchema($db);

        if (!class_exists('PaymentVarious')) {
            throw new Exception(self::trans('TakeposExpenseErrorPaymentClassUnavailable', 'Dolibarr various payment class is unavailable.'));
        }
        if (!isModEnabled('bank')) {
            throw new Exception(self::trans('TakeposExpenseErrorBankModuleRequired', 'Dolibarr bank module must be enabled before posting POS expenses.'));
        }
        if (!self::canPost($db, $user) && !self::canAdmin($db, $user)) {
            self::safeAudit($db, $user, 'expense_post_rejected', TakeposAudit::SEVERITY_WARNING, array('expense_id' => (int) $expenseId, 'reason' => 'permission_denied'), 'POS expense post rejected');
            throw new Exception(self::trans('TakeposExpensePostingPermissionRequired', 'Expense posting permission is required.'));
        }

        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        $expenseId = (int) $expenseId;
        $expense = self::getExpenseById($db, $entity, $expenseId);
        if (!$expense) {
            throw new Exception(self::trans('TakeposExpenseErrorRecordNotFound', 'Expense record not found.'));
        }

        self::safeAudit($db, $user, 'expense_post_attempt', TakeposAudit::SEVERITY_INFO, array('expense_id' => $expenseId, 'ref' => (string) $expense->ref), 'POS expense post requested', 'expense', $expenseId, (float) $expense->amount_ttc);

        if ((int) $expense->status === self::STATUS_POSTED || (int) $expense->fk_payment_various > 0 || (int) $expense->fk_bank_line > 0) {
            self::safeAudit($db, $user, 'expense_post_rejected', TakeposAudit::SEVERITY_WARNING, array('expense_id' => $expenseId, 'reason' => 'already_posted'), 'POS expense post rejected', 'expense', $expenseId, (float) $expense->amount_ttc);
            throw new Exception(self::trans('TakeposExpenseErrorAlreadyPosted', 'This expense is already posted.'));
        }
        if ((int) $expense->status === self::STATUS_CANCELLED) {
            self::safeAudit($db, $user, 'expense_post_rejected', TakeposAudit::SEVERITY_WARNING, array('expense_id' => $expenseId, 'reason' => 'cancelled_expense'), 'POS expense post rejected', 'expense', $expenseId, (float) $expense->amount_ttc);
            throw new Exception(self::trans('TakeposExpenseErrorCancelledCannotPost', 'Cancelled expense cannot be posted.'));
        }

        $category = self::getCategory($db, $entity, (int) $expense->fk_category);
        if (!$category || empty($category->active)) {
            throw new Exception(self::trans('TakeposExpenseErrorSelectedCategoryInvalid', 'Selected expense category is invalid or inactive.'));
        }

        $accountancyCode = trim((string) $expense->accountancy_code);
        if ($accountancyCode === '') {
            $accountancyCode = trim((string) $category->accountancy_code);
        }
        if ($accountancyCode === '') {
            self::safeAudit($db, $user, 'expense_post_rejected', TakeposAudit::SEVERITY_WARNING, array('expense_id' => $expenseId, 'reason' => 'missing_accountancy_code', 'category_id' => (int) $expense->fk_category), 'POS expense post rejected', 'expense', $expenseId, (float) $expense->amount_ttc);
            throw new Exception(self::trans('TakeposExpenseErrorCategoryMissingAccount', 'Expense category has no accounting account mapping. Save is allowed, posting is blocked until mapping is configured.'));
        }

        $bankAccountId = self::resolvePostingBankAccountId($expense, $sessionTerminalToken);
        $paymentTypeId = self::resolvePaymentTypeId($db, $expense->payment_source);
        $cashMovementId = 0;
        $shiftId = (int) $expense->fk_shift;
        $paymentSource = self::normalizePaymentSource($expense->payment_source);

        if (in_array($paymentSource, array(self::SOURCE_CASH_REGISTER, self::SOURCE_PETTY_CASH), true)) {
            if ($shiftId <= 0) {
                $shiftId = self::resolveActiveShiftId($db, $user, (int) $expense->fk_terminal);
            }
            if ($shiftId <= 0 && TakeposShiftService::requireShiftForCashMovements()) {
                self::safeAudit($db, $user, 'expense_post_rejected', TakeposAudit::SEVERITY_WARNING, array('expense_id' => $expenseId, 'reason' => 'shift_required_for_cash_source'), 'POS expense post rejected', 'expense', $expenseId, (float) $expense->amount_ttc);
                throw new Exception(self::trans('TakeposExpenseErrorShiftRequired', 'Active shift is required before posting a cash-source POS expense.'));
            }
        }

        $paymentVarious = new PaymentVarious($db);
        $paymentVarious->datep = strtotime((string) $expense->date_expense);
        $paymentVarious->datev = strtotime((string) $expense->date_expense);
        $paymentVarious->sens = 0;
        $paymentVarious->amount = (float) $expense->amount_ttc;
        $paymentVarious->type_payment = $paymentTypeId;
        $paymentVarious->num_payment = (string) $expense->external_ref;
        $paymentVarious->label = self::buildPostingLabel($expense);
        $paymentVarious->note = (string) $expense->note_private;
        $paymentVarious->accountancy_code = $accountancyCode;
        $paymentVarious->fk_account = $bankAccountId;

        $paymentVariousId = $paymentVarious->create($user);
        if ($paymentVariousId <= 0) {
            $errorMessage = !empty($paymentVarious->error) ? $paymentVarious->error : self::trans('TakeposExpenseErrorUnknownPosting', 'Unknown Dolibarr payment posting error.');
            self::safeAudit($db, $user, 'expense_post_rejected', TakeposAudit::SEVERITY_WARNING, array('expense_id' => $expenseId, 'reason' => 'payment_various_create_failed', 'error' => $errorMessage), 'POS expense post rejected', 'expense', $expenseId, (float) $expense->amount_ttc);
            throw new Exception($errorMessage);
        }

        $paymentVarious->fetch($paymentVariousId, $user);
        $bankLineId = !empty($paymentVarious->fk_bank) ? (int) $paymentVarious->fk_bank : 0;

        if ($shiftId > 0 && in_array($paymentSource, array(self::SOURCE_CASH_REGISTER, self::SOURCE_PETTY_CASH), true)) {
            try {
                $cashMovementId = TakeposCashService::createMovement(
                    $db,
                    $user,
                    $shiftId,
                    TakeposCashService::TYPE_PAID_OUT,
                    (float) $expense->amount_ttc,
                    'expense',
                    'Expense ' . (string) $expense->ref . ' - ' . (string) $expense->description
                );
            } catch (Throwable $e) {
                // Keep accounting posting authoritative even if the operational
                // cash ledger link could not be created afterwards.
                self::safeAudit(
                    $db,
                    $user,
                    'expense_cash_link_failed',
                    TakeposAudit::SEVERITY_WARNING,
                    array(
                        'expense_id' => $expenseId,
                        'shift_id' => $shiftId,
                        'error' => $e->getMessage(),
                    ),
                    'POS expense cash ledger link failed',
                    'expense',
                    $expenseId,
                    (float) $expense->amount_ttc
                );
            }
        }

        $sql = "UPDATE " . self::tableExpense() . " SET";
        $sql .= " status = " . self::STATUS_POSTED;
        $sql .= ", accountancy_code = '" . $db->escape($accountancyCode) . "'";
        $sql .= ", fk_bank_account = " . ((int) $bankAccountId);
        $sql .= ", fk_payment_various = " . ((int) $paymentVariousId);
        $sql .= ", fk_bank_line = " . ($bankLineId > 0 ? $bankLineId : 'NULL');
        $sql .= ", fk_cash_movement = " . ($cashMovementId > 0 ? $cashMovementId : 'NULL');
        $sql .= ", fk_shift = " . ($shiftId > 0 ? $shiftId : 'NULL');
        $sql .= ", fk_posted_user = " . ((int) $user->id);
        $sql .= ", date_posted = '" . $db->escape(dol_print_date(dol_now(), 'dayhourlog')) . "'";
        $sql .= " WHERE entity = " . $entity . " AND rowid = " . $expenseId;

        if (!$db->query($sql)) {
            throw new Exception($db->lasterror());
        }

        self::safeAudit($db, $user, 'expense_post_success', TakeposAudit::SEVERITY_INFO, array('expense_id' => $expenseId, 'ref' => (string) $expense->ref, 'payment_various_id' => (int) $paymentVariousId, 'bank_line_id' => (int) $bankLineId, 'cash_movement_id' => (int) $cashMovementId, 'accountancy_code' => $accountancyCode), 'POS expense posted', 'expense', $expenseId, (float) $expense->amount_ttc);

        return array(
            'payment_various_id' => (int) $paymentVariousId,
            'bank_line_id' => (int) $bankLineId,
            'cash_movement_id' => (int) $cashMovementId,
            'shift_id' => (int) $shiftId,
            'bank_account_id' => (int) $bankAccountId,
        );
    }
}
