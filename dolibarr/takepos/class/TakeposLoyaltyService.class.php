<?php
require_once __DIR__ . '/TakeposMigration.class.php';
require_once __DIR__ . '/TakeposAudit.class.php';
require_once __DIR__ . '/TakeposInputValidator.class.php';
require_once __DIR__ . '/TakeposUserAccess.class.php';

if (defined('DOL_DOCUMENT_ROOT')) {
    require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
}

/**
 * Loyalty points service for TakePOS.
 */
class TakeposLoyaltyService
{
    const TXN_EARN = 'earn';
    const TXN_REDEEM = 'redeem';
    const TXN_ADJUST = 'adjust';

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

    public static function tableAccount()
    {
        return MAIN_DB_PREFIX . 'takepos_loyalty_account';
    }

    public static function tableTxn()
    {
        return MAIN_DB_PREFIX . 'takepos_loyalty_txn';
    }

    private static function safeAudit($db, $user, $eventType, $severity, $data = array(), $description = '', $objectType = '', $objectId = 0)
    {
        try {
            TakeposAudit::logEvent($db, $user, $eventType, $severity, $data, $description, $objectType, $objectId);
        } catch (Throwable $e) {
            if (function_exists('dol_syslog')) {
                dol_syslog('[TakePOS][Loyalty] Audit failure: ' . $e->getMessage(), LOG_WARNING);
            }
        }
    }

    public static function ensureSchema($db)
    {
        $account = self::tableAccount();
        $txn = self::tableTxn();

        $ok = true;
        $ok = $ok && TakeposMigration::ensureTable($db, $account, "CREATE TABLE " . $account . " ("
            . " rowid INT AUTO_INCREMENT PRIMARY KEY,"
            . " entity INT NOT NULL DEFAULT 1,"
            . " fk_soc INT NOT NULL,"
            . " points_balance INT NOT NULL DEFAULT 0,"
            . " total_earned INT NOT NULL DEFAULT 0,"
            . " total_redeemed INT NOT NULL DEFAULT 0,"
            . " tier_code VARCHAR(32) NULL,"
            . " last_purchase_date DATETIME NULL,"
            . " purchase_count INT NOT NULL DEFAULT 0,"
            . " notes TEXT NULL,"
            . " date_creation DATETIME NOT NULL,"
            . " tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
            . " UNIQUE KEY uk_takepos_loyalty_account (entity, fk_soc),"
            . " KEY idx_takepos_loyalty_points (entity, points_balance),"
            . " KEY idx_takepos_loyalty_tier (entity, tier_code)"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $ok = $ok && TakeposMigration::ensureTable($db, $txn, "CREATE TABLE " . $txn . " ("
            . " rowid INT AUTO_INCREMENT PRIMARY KEY,"
            . " entity INT NOT NULL DEFAULT 1,"
            . " fk_account INT NOT NULL,"
            . " fk_soc INT NOT NULL,"
            . " txn_type VARCHAR(16) NOT NULL,"
            . " points_delta INT NOT NULL DEFAULT 0,"
            . " amount_base DECIMAL(24,8) NULL,"
            . " source_type VARCHAR(32) NULL,"
            . " source_id INT NULL,"
            . " note VARCHAR(255) NULL,"
            . " fk_user INT NULL,"
            . " date_creation DATETIME NOT NULL,"
            . " tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
            . " KEY idx_takepos_loyalty_txn_account (entity, fk_account, date_creation),"
            . " KEY idx_takepos_loyalty_txn_soc (entity, fk_soc, date_creation),"
            . " KEY idx_takepos_loyalty_txn_type (entity, txn_type, date_creation),"
            . " KEY idx_takepos_loyalty_txn_source (entity, source_type, source_id),"
            . " UNIQUE KEY uk_takepos_loyalty_txn_uniq (entity, txn_type, source_type, source_id, fk_soc)"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if (!$ok) {
            return false;
        }

        $accountCols = array(
            'entity' => "INT NOT NULL DEFAULT 1",
            'fk_soc' => "INT NOT NULL",
            'points_balance' => "INT NOT NULL DEFAULT 0",
            'total_earned' => "INT NOT NULL DEFAULT 0",
            'total_redeemed' => "INT NOT NULL DEFAULT 0",
            'tier_code' => "VARCHAR(32) NULL",
            'last_purchase_date' => "DATETIME NULL",
            'purchase_count' => "INT NOT NULL DEFAULT 0",
            'notes' => "TEXT NULL",
            'date_creation' => "DATETIME NOT NULL",
            'tms' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        );
        foreach ($accountCols as $col => $def) {
            if (!TakeposMigration::ensureColumn($db, $account, $col, $def)) {
                return false;
            }
        }

        $txnCols = array(
            'entity' => "INT NOT NULL DEFAULT 1",
            'fk_account' => "INT NOT NULL",
            'fk_soc' => "INT NOT NULL",
            'txn_type' => "VARCHAR(16) NOT NULL",
            'points_delta' => "INT NOT NULL DEFAULT 0",
            'amount_base' => "DECIMAL(24,8) NULL",
            'source_type' => "VARCHAR(32) NULL",
            'source_id' => "INT NULL",
            'note' => "VARCHAR(255) NULL",
            'fk_user' => "INT NULL",
            'date_creation' => "DATETIME NOT NULL",
            'tms' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        );
        foreach ($txnCols as $col => $def) {
            if (!TakeposMigration::ensureColumn($db, $txn, $col, $def)) {
                return false;
            }
        }

        TakeposMigration::ensureIndex($db, $account, 'uk_takepos_loyalty_account', '(entity, fk_soc)', 'UNIQUE');
        TakeposMigration::ensureIndex($db, $account, 'idx_takepos_loyalty_points', '(entity, points_balance)');
        TakeposMigration::ensureIndex($db, $txn, 'idx_takepos_loyalty_txn_account', '(entity, fk_account, date_creation)');
        TakeposMigration::ensureIndex($db, $txn, 'idx_takepos_loyalty_txn_soc', '(entity, fk_soc, date_creation)');
        TakeposMigration::ensureIndex($db, $txn, 'idx_takepos_loyalty_txn_type', '(entity, txn_type, date_creation)');
        TakeposMigration::ensureIndex($db, $txn, 'idx_takepos_loyalty_txn_source', '(entity, source_type, source_id)');
        TakeposMigration::ensureIndex($db, $txn, 'uk_takepos_loyalty_txn_uniq', '(entity, txn_type, source_type, source_id, fk_soc)', 'UNIQUE');

        return true;
    }

    public static function pointsPerCurrencyAmount()
    {
        $raw = getDolGlobalString('TAKEPOS_LOYALTY_POINTS_PER_CURRENCY', '1');
        $parsed = 1.0;
        if (!TakeposInputValidator::parsePositiveDecimal($raw, $parsed, false, 6)) {
            $parsed = 1.0;
        }
        return (float) $parsed;
    }

    public static function redeemPointsPerCurrencyAmount()
    {
        $raw = getDolGlobalString('TAKEPOS_LOYALTY_REDEEM_POINTS_PER_CURRENCY', '100');
        $parsed = 100.0;
        if (!TakeposInputValidator::parsePositiveDecimal($raw, $parsed, false, 6)) {
            $parsed = 100.0;
        }
        return (float) $parsed;
    }

    public static function settings()
    {
        return array(
            'points_per_currency' => self::pointsPerCurrencyAmount(),
            'redeem_points_per_currency' => self::redeemPointsPerCurrencyAmount(),
        );
    }

    public static function saveSettings($db, $user, $pointsPerCurrency, $redeemPointsPerCurrency)
    {
        $ppc = null;
        if (!TakeposInputValidator::parsePositiveDecimal($pointsPerCurrency, $ppc, false, 6)) {
            throw new Exception(self::trans('TakeposLoyaltyInvalidPointsPerCurrency', 'Invalid points-per-currency value.'));
        }

        $rpc = null;
        if (!TakeposInputValidator::parsePositiveDecimal($redeemPointsPerCurrency, $rpc, false, 6)) {
            throw new Exception(self::trans('TakeposLoyaltyInvalidRedeemConversion', 'Invalid redeem conversion value.'));
        }

        if (!dolibarr_set_const($db, 'TAKEPOS_LOYALTY_POINTS_PER_CURRENCY', (string) $ppc, 'chaine', 0, '', (!empty($user->entity) ? (int) $user->entity : 1))) {
            throw new Exception($db->lasterror());
        }
        if (!dolibarr_set_const($db, 'TAKEPOS_LOYALTY_REDEEM_POINTS_PER_CURRENCY', (string) $rpc, 'chaine', 0, '', (!empty($user->entity) ? (int) $user->entity : 1))) {
            throw new Exception($db->lasterror());
        }

        self::safeAudit($db, $user, 'loyalty_settings_updated', TakeposAudit::SEVERITY_WARNING, array(
            'points_per_currency' => (float) $ppc,
            'redeem_points_per_currency' => (float) $rpc,
        ), 'Loyalty settings updated');

        return true;
    }

    private static function ensureAccount($db, $entity, $customerId)
    {
        self::ensureSchema($db);

        $entity = (int) $entity;
        $customerId = (int) $customerId;
        if ($entity <= 0 || $customerId <= 0) {
            throw new Exception(self::trans('TakeposLoyaltyCustomerAccountRequired', 'Customer is required for loyalty account.'));
        }

        $sql = "SELECT rowid FROM " . self::tableAccount() . " WHERE entity = " . $entity . " AND fk_soc = " . $customerId . " LIMIT 1";
        $resql = $db->query($sql);
        if ($resql && ($obj = $db->fetch_object($resql))) {
            return (int) $obj->rowid;
        }

        $insert = "INSERT INTO " . self::tableAccount() . " (entity, fk_soc, points_balance, total_earned, total_redeemed, purchase_count, date_creation) VALUES ("
            . $entity . ", " . $customerId . ", 0, 0, 0, 0, '" . $db->escape(dol_print_date(dol_now(), 'dayhourlog')) . "')";
        if (!$db->query($insert)) {
            throw new Exception($db->lasterror());
        }

        return (int) $db->last_insert_id(self::tableAccount());
    }

    public static function getAccountByCustomer($db, $entity, $customerId, $createIfMissing = true)
    {
        self::ensureSchema($db);

        $entity = (int) $entity;
        $customerId = (int) $customerId;
        if ($entity <= 0 || $customerId <= 0) {
            return null;
        }

        if ($createIfMissing) {
            self::ensureAccount($db, $entity, $customerId);
        }

        $sql = "SELECT rowid, entity, fk_soc, points_balance, total_earned, total_redeemed, tier_code, last_purchase_date, purchase_count, notes"
            . " FROM " . self::tableAccount()
            . " WHERE entity = " . $entity . " AND fk_soc = " . $customerId
            . " LIMIT 1";
        $resql = $db->query($sql);
        return ($resql ? $db->fetch_object($resql) : null);
    }

    public static function listTransactions($db, $entity, $customerId, $limit = 100)
    {
        self::ensureSchema($db);

        $limit = max(1, min(500, (int) $limit));
        $rows = array();
        $sql = "SELECT rowid, txn_type, points_delta, amount_base, source_type, source_id, note, fk_user, date_creation"
            . " FROM " . self::tableTxn()
            . " WHERE entity = " . ((int) $entity) . " AND fk_soc = " . ((int) $customerId)
            . " ORDER BY rowid DESC LIMIT " . $limit;
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $rows[] = $obj;
            }
        }

        return $rows;
    }

    public static function customerLoyaltySummary($db, $entity, $customerId)
    {
        $account = self::getAccountByCustomer($db, (int) $entity, (int) $customerId, true);
        if (!$account) {
            return null;
        }

        return array(
            'customer_id' => (int) $account->fk_soc,
            'points_balance' => (int) $account->points_balance,
            'total_earned' => (int) $account->total_earned,
            'total_redeemed' => (int) $account->total_redeemed,
            'tier_code' => !empty($account->tier_code) ? (string) $account->tier_code : '',
            'last_purchase_date' => !empty($account->last_purchase_date) ? (string) $account->last_purchase_date : '',
            'purchase_count' => (int) $account->purchase_count,
            'settings' => self::settings(),
        );
    }

    private static function hasEarnTxnForInvoice($db, $entity, $customerId, $invoiceId)
    {
        $sql = "SELECT rowid FROM " . self::tableTxn()
            . " WHERE entity = " . ((int) $entity)
            . " AND fk_soc = " . ((int) $customerId)
            . " AND txn_type = '" . self::TXN_EARN . "'"
            . " AND source_type = 'invoice'"
            . " AND source_id = " . ((int) $invoiceId)
            . " LIMIT 1";
        $resql = $db->query($sql);
        return ($resql && $db->fetch_object($resql));
    }

    private static function customerExists($db, $entity, $customerId)
    {
        $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "societe"
            . " WHERE rowid = " . ((int) $customerId)
            . " AND entity IN (" . ((int) $entity) . ")"
            . " LIMIT 1";
        $resql = $db->query($sql);
        return ($resql && $db->fetch_object($resql));
    }

    public static function autoEarnForInvoice($db, $user, $invoiceId)
    {
        self::ensureSchema($db);

        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        $invoiceId = (int) $invoiceId;
        if ($invoiceId <= 0) {
            return array('awarded_points' => 0, 'skipped' => 'invalid_invoice');
        }

        if (!TakeposAccess::isFeatureEnabledForCurrentEntity($db, 'takepos.loyalty')) {
            return array('awarded_points' => 0, 'skipped' => 'feature_disabled');
        }
        if (empty($user->admin) && !TakeposUserAccess::userHasPermission($db, $user, 'takepos.loyalty.earn')) {
            return array('awarded_points' => 0, 'skipped' => 'permission_denied');
        }

        $sql = "SELECT rowid, entity, fk_soc, total_ttc, paye, fk_statut"
            . " FROM " . MAIN_DB_PREFIX . "facture"
            . " WHERE rowid = " . $invoiceId . " AND entity = " . $entity
            . " LIMIT 1";
        $resql = $db->query($sql);
        $invoice = ($resql ? $db->fetch_object($resql) : null);
        if (!$invoice) {
            return array('awarded_points' => 0, 'skipped' => 'invoice_not_found');
        }

        $customerId = (int) $invoice->fk_soc;
        if ($customerId <= 0 || !self::customerExists($db, $entity, $customerId)) {
            return array('awarded_points' => 0, 'skipped' => 'no_customer');
        }
        if (self::hasEarnTxnForInvoice($db, $entity, $customerId, $invoiceId)) {
            return array('awarded_points' => 0, 'skipped' => 'already_earned');
        }

        $isPaid = ((int) $invoice->paye === 1 || (int) $invoice->fk_statut >= 2);
        if (!$isPaid) {
            return array('awarded_points' => 0, 'skipped' => 'invoice_not_paid');
        }

        $totalAmount = (float) $invoice->total_ttc;
        if ($totalAmount <= 0) {
            return array('awarded_points' => 0, 'skipped' => 'non_positive_total');
        }

        $rate = self::pointsPerCurrencyAmount();
        $points = (int) floor($totalAmount * $rate);
        if ($points <= 0) {
            return array('awarded_points' => 0, 'skipped' => 'computed_zero');
        }

        $accountId = self::ensureAccount($db, $entity, $customerId);

        $db->begin();
        try {
            $insertTxn = "INSERT INTO " . self::tableTxn() . " (entity, fk_account, fk_soc, txn_type, points_delta, amount_base, source_type, source_id, note, fk_user, date_creation) VALUES ("
                . $entity . ", " . ((int) $accountId) . ", " . $customerId . ", '" . self::TXN_EARN . "', " . $points . ", " . $totalAmount . ", 'invoice', " . $invoiceId . ", 'Auto earn on payment', " . ((int) $user->id > 0 ? (int) $user->id : 'NULL') . ", '" . $db->escape(dol_print_date(dol_now(), 'dayhourlog')) . "')";
            if (!$db->query($insertTxn)) {
                throw new Exception($db->lasterror());
            }

            $updateAccount = "UPDATE " . self::tableAccount() . " SET"
                . " points_balance = points_balance + " . $points
                . ", total_earned = total_earned + " . $points
                . ", purchase_count = purchase_count + 1"
                . ", last_purchase_date = '" . $db->escape(dol_print_date(dol_now(), 'dayhourlog')) . "'"
                . " WHERE rowid = " . ((int) $accountId);
            if (!$db->query($updateAccount)) {
                throw new Exception($db->lasterror());
            }

            $db->commit();

            self::safeAudit($db, $user, 'loyalty_points_earned', TakeposAudit::SEVERITY_INFO, array(
                'customer_id' => $customerId,
                'invoice_id' => $invoiceId,
                'points' => $points,
                'amount' => $totalAmount,
            ), 'Loyalty points earned', 'invoice', $invoiceId);

            return array('awarded_points' => $points, 'customer_id' => $customerId);
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    public static function redeemOnInvoice($db, $user, $invoiceId, $points, $note = '')
    {
        self::ensureSchema($db);

        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        $invoiceId = (int) $invoiceId;
        if ($invoiceId <= 0) {
            throw new Exception(self::trans('TakeposLoyaltyInvoiceRequired', 'Invoice is required for loyalty redemption.'));
        }

        $parsedPoints = null;
        if (!TakeposInputValidator::parsePositiveInteger($points, $parsedPoints, false) || (int) $parsedPoints <= 0) {
            throw new Exception(self::trans('TakeposLoyaltyInvalidPointsValue', 'Invalid loyalty points value.'));
        }
        $points = (int) $parsedPoints;

        $invoice = new Facture($db);
        if ($invoice->fetch($invoiceId) <= 0) {
            throw new Exception(self::trans('TakeposLoyaltyInvoiceNotFound', 'Invoice not found.'));
        }
        if ((int) $invoice->entity !== $entity) {
            throw new Exception(self::trans('TakeposLoyaltyInvoiceEntityMismatch', 'Invoice entity mismatch.'));
        }
        if ((int) $invoice->statut !== Facture::STATUS_DRAFT) {
            throw new Exception(self::trans('TakeposLoyaltyDraftOnly', 'Loyalty redemption is only allowed on draft invoices.'));
        }

        $customerId = (int) $invoice->socid;
        if ($customerId <= 0) {
            throw new Exception(self::trans('TakeposLoyaltyInvoiceCustomerRequired', 'Customer is required on invoice before redeeming points.'));
        }

        $account = self::getAccountByCustomer($db, $entity, $customerId, true);
        if (!$account || (int) $account->points_balance < $points) {
            self::safeAudit($db, $user, 'loyalty_redeem_rejected', TakeposAudit::SEVERITY_WARNING, array(
                'customer_id' => $customerId,
                'invoice_id' => $invoiceId,
                'requested_points' => $points,
                'balance' => ($account ? (int) $account->points_balance : 0),
            ), 'Loyalty redemption rejected');
            throw new Exception(self::trans('TakeposLoyaltyBalanceExceeded', 'Requested points exceed available balance.'));
        }

        $redeemRate = self::redeemPointsPerCurrencyAmount();
        $amount = ((float) $points) / $redeemRate;
        if ($amount <= 0) {
            throw new Exception(self::trans('TakeposLoyaltyComputedAmountInvalid', 'Computed loyalty redemption amount is invalid.'));
        }

        $lineDesc = 'Loyalty Redemption (' . $points . ' pts)';

        $db->begin();
        try {
            $lineRes = $invoice->addline($lineDesc, -1 * $amount, 1, 0, 0, 0, 0, 0, '', 0, 0, 0, 'HT');
            if ($lineRes <= 0) {
                throw new Exception(!empty($invoice->error) ? $invoice->error : self::trans('TakeposLoyaltyAddRedemptionLineFailed', 'Unable to add loyalty redemption line.'));
            }

            $insertTxn = "INSERT INTO " . self::tableTxn() . " (entity, fk_account, fk_soc, txn_type, points_delta, amount_base, source_type, source_id, note, fk_user, date_creation) VALUES ("
                . $entity . ", " . ((int) $account->rowid) . ", " . $customerId . ", '" . self::TXN_REDEEM . "', " . (-1 * $points) . ", " . (-1 * $amount) . ", 'invoice', " . $invoiceId . ", '" . $db->escape($note !== '' ? $note : 'Redeem at POS') . "', " . ((int) $user->id > 0 ? (int) $user->id : 'NULL') . ", '" . $db->escape(dol_print_date(dol_now(), 'dayhourlog')) . "')";
            if (!$db->query($insertTxn)) {
                throw new Exception($db->lasterror());
            }

            $updateAccount = "UPDATE " . self::tableAccount() . " SET"
                . " points_balance = points_balance - " . $points
                . ", total_redeemed = total_redeemed + " . $points
                . " WHERE rowid = " . ((int) $account->rowid);
            if (!$db->query($updateAccount)) {
                throw new Exception($db->lasterror());
            }

            $db->commit();

            self::safeAudit($db, $user, 'loyalty_points_redeemed', TakeposAudit::SEVERITY_INFO, array(
                'customer_id' => $customerId,
                'invoice_id' => $invoiceId,
                'points' => $points,
                'amount' => $amount,
            ), 'Loyalty points redeemed', 'invoice', $invoiceId);

            return array('customer_id' => $customerId, 'points' => $points, 'amount' => $amount);
        } catch (Throwable $e) {
            $db->rollback();
            self::safeAudit($db, $user, 'loyalty_redeem_rejected', TakeposAudit::SEVERITY_WARNING, array(
                'invoice_id' => $invoiceId,
                'customer_id' => $customerId,
                'points' => $points,
                'reason' => $e->getMessage(),
            ), 'Loyalty redeem rejected');
            throw $e;
        }
    }

    public static function adjustPoints($db, $user, $customerId, $pointsDelta, $note = '')
    {
        self::ensureSchema($db);

        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        $customerId = (int) $customerId;
        if ($customerId <= 0) {
            throw new Exception(self::trans('TakeposLoyaltyCustomerRequired', 'Customer is required.'));
        }

        $parsedDelta = null;
        if (!TakeposInputValidator::parseInteger($pointsDelta, $parsedDelta, true) || (int) $parsedDelta === 0) {
            throw new Exception(self::trans('TakeposLoyaltyInvalidAdjustmentValue', 'Invalid points adjustment value.'));
        }
        $pointsDelta = (int) $parsedDelta;

        $account = self::getAccountByCustomer($db, $entity, $customerId, true);
        if (!$account) {
            throw new Exception(self::trans('TakeposLoyaltyAccountNotFound', 'Loyalty account not found.'));
        }

        if ($pointsDelta < 0 && ((int) $account->points_balance + $pointsDelta) < 0) {
            throw new Exception(self::trans('TakeposLoyaltyAdjustmentNegative', 'Adjustment would make points negative.'));
        }

        $db->begin();
        try {
            $insertTxn = "INSERT INTO " . self::tableTxn() . " (entity, fk_account, fk_soc, txn_type, points_delta, amount_base, source_type, source_id, note, fk_user, date_creation) VALUES ("
                . $entity . ", " . ((int) $account->rowid) . ", " . $customerId . ", '" . self::TXN_ADJUST . "', " . $pointsDelta . ", NULL, 'manual', NULL, '" . $db->escape($note !== '' ? $note : 'Manual adjustment') . "', " . ((int) $user->id > 0 ? (int) $user->id : 'NULL') . ", '" . $db->escape(dol_print_date(dol_now(), 'dayhourlog')) . "')";
            if (!$db->query($insertTxn)) {
                throw new Exception($db->lasterror());
            }

            $updateAccount = "UPDATE " . self::tableAccount() . " SET points_balance = points_balance + " . $pointsDelta
                . " WHERE rowid = " . ((int) $account->rowid);
            if (!$db->query($updateAccount)) {
                throw new Exception($db->lasterror());
            }

            $db->commit();

            self::safeAudit($db, $user, 'loyalty_points_adjusted', TakeposAudit::SEVERITY_WARNING, array(
                'customer_id' => $customerId,
                'points_delta' => $pointsDelta,
                'note' => (string) $note,
            ), 'Loyalty points adjusted', 'customer', $customerId);

            return true;
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }
}
