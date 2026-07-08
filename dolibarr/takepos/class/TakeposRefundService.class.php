<?php
require_once __DIR__ . '/TakeposMigration.class.php';
require_once __DIR__ . '/TakeposAudit.class.php';
require_once __DIR__ . '/TakeposInputValidator.class.php';
require_once __DIR__ . '/TakeposUserAccess.class.php';
require_once __DIR__ . '/TakeposStoreService.class.php';
require_once __DIR__ . '/TakeposTerminalService.class.php';
require_once __DIR__ . '/TakeposShiftService.class.php';
require_once __DIR__ . '/TakeposCashService.class.php';
require_once __DIR__ . '/TakeposManagerOverrideService.class.php';
require_once __DIR__ . '/TakeposWebhookService.class.php';

if (defined('DOL_DOCUMENT_ROOT')) {
    require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
    require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
}

/**
 * Returns / refunds service.
 */
class TakeposRefundService
{
    const STATUS_COMPLETED = 'completed';

    const TYPE_FULL = 'full';
    const TYPE_PARTIAL = 'partial';
    const TYPE_ADHOC = 'adhoc';
    const TYPE_EXCHANGE = 'exchange';

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

    public static function tableRefund() { return MAIN_DB_PREFIX . 'takepos_refund'; }
    public static function tableRefundLine() { return MAIN_DB_PREFIX . 'takepos_refund_line'; }
    public static function tableRefundReason() { return MAIN_DB_PREFIX . 'takepos_refund_reason'; }

    private static function safeAudit($db, $user, $eventType, $severity, $data = array(), $description = '', $objectType = '', $objectId = 0, $amount = null)
    {
        try {
            TakeposAudit::logEvent($db, $user, $eventType, $severity, $data, $description, $objectType, $objectId, $amount);
        } catch (Throwable $e) {
            if (function_exists('dol_syslog')) {
                dol_syslog('[TakePOS][Refund] Audit failure: ' . $e->getMessage(), LOG_WARNING);
            }
        }
    }


    private static function safeWebhookEmit($db, $entity, $eventCode, $payload = array(), $user = null) {
        try {
            if (class_exists('TakeposWebhookService') && method_exists('TakeposWebhookService', 'emitEvent')) {
                TakeposWebhookService::emitEvent($db, (int) $entity, (string) $eventCode, (array) $payload, $user);
            }
        } catch (Throwable $e) {
            if (function_exists('dol_syslog')) {
                dol_syslog('[TakePOS][Refund] Webhook emit failure: ' . $e->getMessage(), LOG_WARNING);
            }
        }
    }
    public static function reasonCodeMap()
    {
        return array(
            'damaged' => 'Damaged',
            'wrong_item' => 'Wrong Item',
            'customer_changed_mind' => 'Customer Changed Mind',
            'pricing_error' => 'Pricing Error',
            'duplicate_charge' => 'Duplicate Charge',
            'expired_item' => 'Expired Item',
            'other' => 'Other',
        );
    }

    public static function ensureSchema($db)
    {
        TakeposStoreService::ensureSchema($db);
        TakeposTerminalService::ensureSchema($db);

        $r = self::tableRefund();
        $rl = self::tableRefundLine();
        $rr = self::tableRefundReason();

        $ok = true;
        $ok = $ok && TakeposMigration::ensureTable($db, $r, "CREATE TABLE " . $r . " ("
                . " rowid INT AUTO_INCREMENT PRIMARY KEY,"
                . " entity INT NOT NULL DEFAULT 1,"
                . " fk_original_invoice INT NULL,"
                . " fk_refund_invoice INT NULL,"
                . " fk_store INT NULL,"
                . " fk_terminal INT NULL,"
                . " fk_cashier_user INT NOT NULL,"
                . " refund_ref VARCHAR(64) NOT NULL,"
                . " refund_type VARCHAR(24) NOT NULL,"
                . " total_amount DECIMAL(24,8) NOT NULL DEFAULT 0,"
                . " payment_method VARCHAR(32) NULL,"
                . " reason_code VARCHAR(64) NULL,"
                . " note TEXT NULL,"
                . " status VARCHAR(24) NOT NULL DEFAULT 'completed',"
                . " fk_approved_by INT NULL,"
                . " date_creation DATETIME NOT NULL,"
                . " tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
                . " UNIQUE KEY uk_takepos_refund_ref (entity, refund_ref),"
                . " KEY idx_takepos_refund_entity_date (entity, date_creation),"
                . " KEY idx_takepos_refund_original (entity, fk_original_invoice),"
                . " KEY idx_takepos_refund_store (entity, fk_store),"
                . " KEY idx_takepos_refund_terminal (entity, fk_terminal),"
                . " KEY idx_takepos_refund_cashier (entity, fk_cashier_user),"
                . " KEY idx_takepos_refund_status (entity, status)"
                . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $ok = $ok && TakeposMigration::ensureTable($db, $rl, "CREATE TABLE " . $rl . " ("
                . " rowid INT AUTO_INCREMENT PRIMARY KEY,"
                . " entity INT NOT NULL DEFAULT 1,"
                . " fk_refund INT NOT NULL,"
                . " fk_original_line INT NULL,"
                . " fk_product INT NULL,"
                . " qty_refunded DECIMAL(24,8) NOT NULL DEFAULT 0,"
                . " unit_price DECIMAL(24,8) NOT NULL DEFAULT 0,"
                . " line_total DECIMAL(24,8) NOT NULL DEFAULT 0,"
                . " restock_flag TINYINT(1) NOT NULL DEFAULT 0,"
                . " date_creation DATETIME NOT NULL,"
                . " tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
                . " KEY idx_takepos_refund_line_refund (entity, fk_refund),"
                . " KEY idx_takepos_refund_line_original (entity, fk_original_line),"
                . " KEY idx_takepos_refund_line_product (entity, fk_product)"
                . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $ok = $ok && TakeposMigration::ensureTable($db, $rr, "CREATE TABLE " . $rr . " ("
                . " rowid INT AUTO_INCREMENT PRIMARY KEY,"
                . " entity INT NOT NULL DEFAULT 1,"
                . " code VARCHAR(64) NOT NULL,"
                . " label VARCHAR(128) NOT NULL,"
                . " active TINYINT(1) NOT NULL DEFAULT 1,"
                . " date_creation DATETIME NOT NULL,"
                . " tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
                . " UNIQUE KEY uk_takepos_refund_reason (entity, code),"
                . " KEY idx_takepos_refund_reason_active (entity, active)"
                . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if (!$ok) return false;

        $cols = array(
            $r => array(
                'entity' => "INT NOT NULL DEFAULT 1",
                'fk_original_invoice' => "INT NULL",
                'fk_refund_invoice' => "INT NULL",
                'fk_store' => "INT NULL",
                'fk_terminal' => "INT NULL",
                'fk_cashier_user' => "INT NOT NULL",
                'refund_ref' => "VARCHAR(64) NOT NULL",
                'refund_type' => "VARCHAR(24) NOT NULL",
                'total_amount' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
                'payment_method' => "VARCHAR(32) NULL",
                'reason_code' => "VARCHAR(64) NULL",
                'note' => "TEXT NULL",
                'status' => "VARCHAR(24) NOT NULL DEFAULT 'completed'",
                'fk_approved_by' => "INT NULL",
                'date_creation' => "DATETIME NOT NULL",
                'tms' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
            ),
            $rl => array(
                'entity' => "INT NOT NULL DEFAULT 1",
                'fk_refund' => "INT NOT NULL",
                'fk_original_line' => "INT NULL",
                'fk_product' => "INT NULL",
                'qty_refunded' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
                'unit_price' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
                'line_total' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
                'restock_flag' => "TINYINT(1) NOT NULL DEFAULT 0",
                'date_creation' => "DATETIME NOT NULL",
                'tms' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
            ),
            $rr => array(
                'entity' => "INT NOT NULL DEFAULT 1",
                'code' => "VARCHAR(64) NOT NULL",
                'label' => "VARCHAR(128) NOT NULL",
                'active' => "TINYINT(1) NOT NULL DEFAULT 1",
                'date_creation' => "DATETIME NOT NULL",
                'tms' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
            ),
        );
        foreach ($cols as $table => $tableCols) {
            foreach ($tableCols as $c => $d) {
                if (!TakeposMigration::ensureColumn($db, $table, $c, $d)) return false;
            }
        }

        self::ensureReasonDefaults($db);
        return true;
    }

    public static function ensureReasonDefaults($db, $entity = null)
    {
        $entity = ((int) $entity > 0 ? (int) $entity : (!empty($GLOBALS['conf']->entity) ? (int) $GLOBALS['conf']->entity : 1));
        foreach (self::reasonCodeMap() as $code => $label) {
            $q = "SELECT rowid FROM " . self::tableRefundReason() . " WHERE entity = " . $entity . " AND code = '" . $db->escape($code) . "' LIMIT 1";
            $res = $db->query($q);
            if ($res && $db->fetch_object($res)) continue;
            $db->query("INSERT INTO " . self::tableRefundReason() . " (entity, code, label, active, date_creation) VALUES (" . $entity . ", '" . $db->escape($code) . "', '" . $db->escape($label) . "', 1, '" . $db->escape(dol_print_date(dol_now(), 'dayhourlog')) . "')");
        }
    }

    public static function listReasonCodes($db, $entity)
    {
        self::ensureSchema($db);
        $rows = array();
        $sql = "SELECT code, label FROM " . self::tableRefundReason() . " WHERE entity = " . ((int) $entity) . " AND active = 1 ORDER BY code ASC";
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $rows[] = array('code' => (string) $obj->code, 'label' => (string) $obj->label);
            }
        }
        return $rows;
    }

    private static function normalizePaymentMethod($paymentMethod)
    {
        $pm = strtoupper(trim((string) $paymentMethod));
        if ($pm === 'LIQ' || $pm === '') $pm = 'CASH';
        return $pm;
    }

    private static function managerApprovalThreshold()
    {
        return (float) price2num(getDolGlobalString('TAKEPOS_REFUND_MANAGER_THRESHOLD_AMOUNT', '0'), 'MU');
    }

    private static function generateRefundRef($entity)
    {
        $rand = strtoupper(substr(sha1(uniqid('', true)), 0, 6));
        return 'RF-' . ((int) $entity) . '-' . dol_print_date(dol_now(), '%Y%m%d-%H%M%S') . '-' . $rand;
    }

    private static function userHas($db, $user, $permissionCode)
    {
        return (!empty($user->admin) || TakeposUserAccess::userHasPermission($db, $user, $permissionCode));
    }

    public static function getInvoiceStoreId($db, $entity, $invoice)
    {
        if (!is_object($invoice) || empty($invoice->pos_source)) return 0;
        $sql = "SELECT fk_store FROM " . TakeposTerminalService::tableTerminal() . " WHERE entity = " . ((int) $entity) . " AND terminal_code = '" . $db->escape((string) $invoice->pos_source) . "' AND active = 1 LIMIT 1";
        $resql = $db->query($sql);
        if ($resql && ($obj = $db->fetch_object($resql)) && !empty($obj->fk_store)) return (int) $obj->fk_store;
        return 0;
    }

    private static function canAccessStore($db, $user, $storeId, $entity)
    {
        if ((int) $storeId <= 0) return true;
        if (!TakeposStoreService::enforceStoreRestrictionEnabled($db)) return true;
        if (!empty($user->admin) || TakeposUserAccess::userHasPermission($db, $user, 'takepos.store.view_all')) return true;
        return TakeposStoreService::userCanAccessStore($db, $user, (int) $storeId, (int) $entity);
    }

    public static function searchOriginalInvoices($db, $user, $filters = array(), $limit = 50)
    {
        self::ensureSchema($db);
        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        $limit = max(1, min(300, (int) $limit));

        // Build the user-supplied filter clauses once (id / ref / customer / dates).
        // These apply regardless of which "source" strategy we use below.
        $userClauses = array();
        if (!empty($filters['invoice_id'])) {
            $idVal = (int) $filters['invoice_id'];
            $idStr = $db->escape((string) $idVal);
            // Match internal rowid OR the ref containing that number (cashiers
            // often type the receipt ref number into the ID box).
            $userClauses[] = "(f.rowid = " . $idVal . " OR f.ref LIKE '%" . $idStr . "%')";
        }
        if (!empty($filters['invoice_ref'])) {
            $userClauses[] = "f.ref LIKE '%" . $db->escape((string) $filters['invoice_ref']) . "%'";
        }
        if (!empty($filters['customer_id'])) $userClauses[] = "f.fk_soc = " . ((int) $filters['customer_id']);
        if (!empty($filters['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $filters['date_from'])) $userClauses[] = "COALESCE(f.datef, f.datec) >= '" . $db->escape($filters['date_from']) . " 00:00:00'";
        if (!empty($filters['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $filters['date_to'])) $userClauses[] = "COALESCE(f.datef, f.datec) <= '" . $db->escape($filters['date_to']) . " 23:59:59'";

        // FIX (refund-search-v2): some POS invoices have pos_source set but an empty
        // or different module_source (depends on Dolibarr version / how the invoice
        // was created). The old query hard-required module_source='takepos' and so
        // returned nothing. We now try progressively broader "source" strategies and
        // use the first that yields rows:
        //   1. module_source = 'takepos'                 (cleanly tagged POS invoices)
        //   2. pos_source IS NOT NULL AND pos_source<>''  (older POS invoices)
        //   3. no source filter at all                    (last-resort: user typed an
        //                                                   explicit ref/id, trust it)
        $sourceStrategies = array(
            "f.module_source = 'takepos'",
            "(f.pos_source IS NOT NULL AND f.pos_source <> '')",
        );
        // Only allow the no-source-filter fallback when the user gave an explicit
        // id or ref (so we don't dump the entire invoice table for an empty search).
        $hasExplicitId  = !empty($filters['invoice_id']) || !empty($filters['invoice_ref']);
        if ($hasExplicitId) {
            $sourceStrategies[] = "1=1";
        }

        $rows = array();
        foreach ($sourceStrategies as $sourceClause) {
            $where = array("f.entity = " . $entity, "f.fk_statut <> 0", $sourceClause);
            foreach ($userClauses as $c) $where[] = $c;

            $sql = "SELECT f.rowid, f.ref, COALESCE(f.datef, f.datec) AS invoice_date, f.total_ttc, f.pos_source, f.module_source, s.nom AS customer_name"
                . " FROM " . MAIN_DB_PREFIX . "facture f"
                . " LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = f.fk_soc"
                . " WHERE " . implode(' AND ', $where)
                . " ORDER BY f.rowid DESC LIMIT " . $limit;

            $resql = $db->query($sql);
            if ($resql) {
                while ($obj = $db->fetch_object($resql)) {
                    $tmp = new stdClass();
                    $tmp->pos_source = (string) $obj->pos_source;
                    $storeId = self::getInvoiceStoreId($db, $entity, $tmp);
                    // Admins / view-all users never get filtered out by store access.
                    if (!self::canAccessStore($db, $user, $storeId, $entity)) continue;
                    $rows[] = array(
                        'invoice_id' => (int) $obj->rowid,
                        'invoice_ref' => (string) $obj->ref,
                        'invoice_date' => (string) $obj->invoice_date,
                        'total_ttc' => (float) $obj->total_ttc,
                        'customer_name' => (string) $obj->customer_name,
                        'store_id' => (int) $storeId,
                        'terminal_code' => (string) $obj->pos_source,
                    );
                }
            }
            // First strategy that returns anything wins — don't broaden further.
            if (!empty($rows)) break;
        }
        return $rows;
    }

    public static function refundedQtyByOriginalLine($db, $entity, $invoiceId)
    {
        self::ensureSchema($db);
        $map = array();
        $sql = "SELECT rl.fk_original_line, SUM(rl.qty_refunded) AS qty_refunded"
            . " FROM " . self::tableRefundLine() . " rl"
            . " INNER JOIN " . self::tableRefund() . " r ON r.rowid = rl.fk_refund AND r.entity = rl.entity"
            . " WHERE r.entity = " . ((int) $entity)
            . " AND r.fk_original_invoice = " . ((int) $invoiceId)
            . " AND r.status = '" . self::STATUS_COMPLETED . "'"
            . " AND rl.fk_original_line IS NOT NULL GROUP BY rl.fk_original_line";
        $resql = $db->query($sql);
        if ($resql) while ($obj = $db->fetch_object($resql)) $map[(int) $obj->fk_original_line] = (float) $obj->qty_refunded;
        return $map;
    }

    public static function listRefundableLines($db, $user, $invoiceId)
    {
        self::ensureSchema($db);
        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        $invoiceId = (int) $invoiceId;
        if ($invoiceId <= 0) throw new Exception(self::trans('TakeposRefundInvoiceRequired', 'Invoice is required.'));

        $invoice = new Facture($db);
        if ($invoice->fetch($invoiceId) <= 0) throw new Exception(self::trans('TakeposRefundInvoiceNotFound', 'Invoice not found.'));
        if (method_exists($invoice, 'fetch_lines')) {
            $invoice->fetch_lines();
        }
        if ((int) $invoice->entity !== $entity) throw new Exception(self::trans('TakeposRefundInvoiceEntityMismatch', 'Invoice entity mismatch.'));
        // FIX (refund-search-v2): accept an invoice as a POS sale if it's tagged by
        // module_source OR by pos_source. Some POS invoices (older ones, or created
        // through certain flows) only have pos_source set. Requiring module_source
        // strictly made those un-refundable even though the search found them.
        $isPosInvoice = ((string) $invoice->module_source === 'takepos')
            || (!empty($invoice->pos_source));
        if (!$isPosInvoice) throw new Exception(self::trans('TakeposRefundTakeposOnly', 'Refund only for TakePOS invoices.'));

        $storeId = self::getInvoiceStoreId($db, $entity, $invoice);
        if (!self::canAccessStore($db, $user, $storeId, $entity)) {
            self::safeAudit($db, $user, 'store_restriction_denied', TakeposAudit::SEVERITY_WARNING, array('store_id' => $storeId, 'action' => 'refund_lines', 'invoice_id' => $invoiceId), 'Store restriction denied');
            throw new Exception(self::trans('TakeposRefundStoreAccessDenied', 'Store access denied for this invoice.'));
        }

        $already = self::refundedQtyByOriginalLine($db, $entity, $invoiceId);
        $lines = array();
        foreach ((array) $invoice->lines as $line) {
            $lineId = isset($line->id) ? (int) $line->id : (isset($line->rowid) ? (int) $line->rowid : 0);
            if ($lineId <= 0) continue;
            $soldQty = (float) $line->qty;
            if ($soldQty <= 0) continue;
            $refQty = isset($already[$lineId]) ? (float) $already[$lineId] : 0.0;
            $remainingQty = max(0.0, $soldQty - $refQty);
            $unitTtc = ($soldQty > 0 ? ((float) $line->total_ttc / $soldQty) : (float) $line->subprice);
            $lines[] = array(
                'line_id' => $lineId,
                'product_id' => isset($line->fk_product) ? (int) $line->fk_product : 0,
                'label' => !empty($line->product_label) ? (string) $line->product_label : (!empty($line->desc) ? (string) $line->desc : ''),
                'qty_sold' => $soldQty,
                'qty_refunded' => $refQty,
                'qty_refundable' => $remainingQty,
                'unit_price_ttc' => (float) $unitTtc,
            );
        }

        return array(
            'invoice_id' => (int) $invoice->id,
            'invoice_ref' => (string) $invoice->ref,
            'invoice_date' => (string) dol_print_date($invoice->date, 'dayhourlog'),
            'total_ttc' => (float) $invoice->total_ttc,
            'store_id' => (int) $storeId,
            'terminal_code' => (string) $invoice->pos_source,
            'lines' => $lines,
        );
    }

    private static function parseRequestedLines($rawLines)
    {
        $rows = array();
        foreach ((array) $rawLines as $one) {
            if (!is_array($one)) continue;
            $lineId = isset($one['line_id']) ? (int) $one['line_id'] : 0;
            if ($lineId <= 0) continue;
            $qty = null;
            if (!TakeposInputValidator::parsePositiveDecimal(isset($one['qty']) ? $one['qty'] : '', $qty, false, 8)) throw new Exception(sprintf(self::trans('TakeposRefundQtyInvalid', 'Invalid refund quantity for line #%s.'), $lineId));
            $rows[$lineId] = array('line_id' => $lineId, 'qty' => (float) $qty, 'restock_flag' => (!empty($one['restock_flag']) ? 1 : 0));
        }
        return $rows;
    }

    private static function registerCashImpactIfNeeded($db, $user, $totalAmount, $paymentMethod, $refundRef)
    {
        if (self::normalizePaymentMethod($paymentMethod) !== 'CASH') return;
        if (!TakeposAccess::isFeatureEnabledForCurrentEntity($db, 'takepos.cash_control')) return;

        $terminalCode = isset($_SESSION['takeposterminal']) ? (string) $_SESSION['takeposterminal'] : '1';
        $summary = TakeposShiftService::getCurrentActiveShiftSummary($db, $user, $terminalCode);
        if (!$summary || empty($summary['shift_id'])) {
            if (TakeposShiftService::requireShiftForCashMovements()) throw new Exception(self::trans('TakeposRefundCashShiftRequired', 'Active shift is required for cash refund movement.'));
            return;
        }
        if ((int) $summary['cashier_user_id'] !== (int) $user->id && empty($user->admin)) throw new Exception(self::trans('TakeposRefundCashierShiftMismatch', 'Active shift belongs to another cashier.'));

        TakeposCashService::createMovement($db, $user, (int) $summary['shift_id'], TakeposCashService::TYPE_PAID_OUT, (float) $totalAmount, 'refund', 'Refund ' . $refundRef);
    }

    private static function applyRestockForLine($db, $user, $warehouseId, $productId, $qty, $refundRef)
    {
        if ((int) $productId <= 0 || (float) $qty <= 0) return;
        // If no warehouse is configured, skip restock silently instead of blocking the refund.
        if ((int) $warehouseId <= 0) {
            dol_syslog('[TakePOS][Refund] Restock skipped: no warehouse configured for this store (product_id=' . $productId . ')', LOG_WARNING);
            return;
        }

        $product = new Product($db);
        if ($product->fetch((int) $productId) <= 0) throw new Exception(self::trans('TakeposRefundRestockProductLoadFailed', 'Unable to load product for restock.'));
        if ((int) $product->type === 1) return;

        $res = $product->correct_stock($user, (int) $warehouseId, (float) $qty, 0, 'POS Refund ' . $refundRef, 0);
        if ($res < 0) throw new Exception(!empty($product->error) ? $product->error : self::trans('TakeposRefundRestockFailed', 'Stock restock failed.'));
    }

    public static function createRefund($db, $user, $payload = array())
    {
        self::ensureSchema($db);
        $entity = !empty($user->entity) ? (int) $user->entity : 1;

        $mode = strtolower(trim((string) (isset($payload['refund_type']) ? $payload['refund_type'] : self::TYPE_PARTIAL)));
        if (!in_array($mode, array(self::TYPE_FULL, self::TYPE_PARTIAL, self::TYPE_ADHOC, self::TYPE_EXCHANGE), true)) $mode = self::TYPE_PARTIAL;

        $invoiceId = isset($payload['original_invoice_id']) ? (int) $payload['original_invoice_id'] : 0;
        $reasonCode = strtolower(trim((string) (isset($payload['reason_code']) ? $payload['reason_code'] : 'other')));
        $note = trim((string) (isset($payload['note']) ? $payload['note'] : ''));
        $paymentMethod = self::normalizePaymentMethod(isset($payload['payment_method']) ? $payload['payment_method'] : 'CASH');

        self::safeAudit($db, $user, 'refund_attempt', TakeposAudit::SEVERITY_WARNING, array('refund_type' => $mode, 'invoice_id' => $invoiceId, 'reason_code' => $reasonCode, 'payment_method' => $paymentMethod), 'Refund attempt');

        $storeId = 0;
        $terminalId = 0;
        $warehouseId = 0;
        $lineRows = array();
        $totalAmount = 0.0;
        $permissionCode = ($mode === self::TYPE_FULL ? 'takepos.refund.full' : 'takepos.refund.partial');

        if ($mode === self::TYPE_ADHOC) {
            $permissionCode = 'takepos.refund.without_original';
            $adhoc = null;
            if (!TakeposInputValidator::parsePositiveDecimal(isset($payload['adhoc_amount']) ? $payload['adhoc_amount'] : '', $adhoc, false, 8) || (float) $adhoc <= 0) throw new Exception(self::trans('TakeposRefundAdhocInvalid', 'Adhoc refund amount is invalid.'));
            $totalAmount = (float) $adhoc;
        } else {
            if ($invoiceId <= 0) throw new Exception(self::trans('TakeposRefundOriginalInvoiceRequired', 'Original invoice is required.'));
            $refundable = self::listRefundableLines($db, $user, $invoiceId);
            $storeId = !empty($refundable['store_id']) ? (int) $refundable['store_id'] : 0;
            if ($storeId > 0) {
                $store = TakeposStoreService::getStore($db, $entity, $storeId);
                if ($store && !empty($store->warehouse_id)) $warehouseId = (int) $store->warehouse_id;
            }
            if (!empty($refundable['terminal_code'])) {
                $term = TakeposTerminalService::getTerminalByCode($db, $entity, (string) $refundable['terminal_code']);
                if ($term) $terminalId = (int) $term->rowid;
            }

            $lineMap = array();
            foreach ((array) $refundable['lines'] as $ln) $lineMap[(int) $ln['line_id']] = $ln;

            if ($mode === self::TYPE_FULL) {
                foreach ($lineMap as $lineId => $ln) {
                    if ((float) $ln['qty_refundable'] <= 0) continue;
                    $qty = (float) $ln['qty_refundable'];
                    $lineTotal = (float) $ln['unit_price_ttc'] * $qty;
                    $lineRows[] = array('line_id' => $lineId, 'product_id' => (int) $ln['product_id'], 'qty' => $qty, 'unit_price' => (float) $ln['unit_price_ttc'], 'line_total' => $lineTotal, 'restock_flag' => (!empty($payload['restock_default']) ? 1 : 0));
                    $totalAmount += $lineTotal;
                }
            } else {
                $requested = self::parseRequestedLines(isset($payload['lines']) ? $payload['lines'] : array());
                foreach ($requested as $lineId => $req) {
                    if (!isset($lineMap[$lineId])) throw new Exception(sprintf(self::trans('TakeposRefundLineNotInInvoice', 'Line #%s does not belong to source invoice.'), $lineId));
                    $ln = $lineMap[$lineId];
                    $maxQty = (float) $ln['qty_refundable'];
                    if ((float) $req['qty'] > $maxQty + 0.000001) {
                        self::safeAudit($db, $user, 'refund_duplicate_blocked', TakeposAudit::SEVERITY_WARNING, array('invoice_id' => $invoiceId, 'line_id' => $lineId, 'requested_qty' => (float) $req['qty'], 'max_refundable_qty' => $maxQty), 'Duplicate refund blocked');
                        throw new Exception(sprintf(self::trans('TakeposRefundQtyExceeds', 'Requested quantity exceeds refundable quantity for line #%s.'), $lineId));
                    }
                    $lineTotal = (float) $ln['unit_price_ttc'] * (float) $req['qty'];
                    $lineRows[] = array('line_id' => $lineId, 'product_id' => (int) $ln['product_id'], 'qty' => (float) $req['qty'], 'unit_price' => (float) $ln['unit_price_ttc'], 'line_total' => $lineTotal, 'restock_flag' => (int) $req['restock_flag']);
                    $totalAmount += $lineTotal;
                }
            }

            if ($totalAmount <= 0 || empty($lineRows)) {
                self::safeAudit($db, $user, 'refund_duplicate_blocked', TakeposAudit::SEVERITY_WARNING, array('invoice_id' => $invoiceId), 'Refund blocked: no refundable quantity');
                throw new Exception(self::trans('TakeposRefundNoRefundableQty', 'No refundable quantity remains for selected invoice.'));
            }
        }

        $directAllowed = self::userHas($db, $user, $permissionCode);
        $needsManager = !$directAllowed;
        $th = self::managerApprovalThreshold();
        if (!$needsManager && $th > 0 && $totalAmount > $th && !self::userHas($db, $user, 'takepos.refund.approve')) $needsManager = true;

        $approvedBy = 0;
        if ($needsManager) {
            if (!TakeposAccess::isFeatureEnabledForCurrentEntity($db, 'takepos.manager_override')) throw new Exception(self::trans('TakeposRefundManagerFeatureDisabled', 'Manager approval is required, but override feature is disabled.'));
            $approval = TakeposManagerOverrideService::approveFromPayload($db, $user, array(
                'override_action' => ($mode === self::TYPE_FULL ? 'refund_full' : 'refund_partial'),
                'invoice_id' => ($invoiceId > 0 ? $invoiceId : 1),
                'line_id' => 0,
                'requested_number' => (string) $totalAmount,
                'manager_barcode' => isset($payload['manager_barcode']) ? $payload['manager_barcode'] : '',
                'manager_login' => isset($payload['manager_login']) ? $payload['manager_login'] : '',
                'manager_password' => isset($payload['manager_password']) ? $payload['manager_password'] : '',
            ));
            if (empty($approval['success'])) {
                self::safeAudit($db, $user, 'refund_manager_rejected', TakeposAudit::SEVERITY_WARNING, array('invoice_id' => $invoiceId, 'reason' => isset($approval['data']['reason']) ? $approval['data']['reason'] : 'approval_failed'), 'Refund manager override rejected');
                throw new Exception(isset($approval['message']) ? $approval['message'] : self::trans('TakeposRefundManagerApprovalFailed', 'Manager approval failed.'));
            }
            $approvedBy = isset($approval['data']['manager_id']) ? (int) $approval['data']['manager_id'] : 0;
            TakeposManagerOverrideService::consumeSession($db, 'refund_flow_used');
            self::safeAudit($db, $user, 'refund_manager_approved', TakeposAudit::SEVERITY_INFO, array('invoice_id' => $invoiceId, 'manager_id' => $approvedBy, 'amount' => $totalAmount), 'Refund manager override approved');
        }

        $hasRestock = false;
        foreach ($lineRows as $r) {
            if (!empty($r['restock_flag'])) { $hasRestock = true; break; }
        }
        if ($hasRestock && !$needsManager && !self::userHas($db, $user, 'takepos.refund.restock_control')) throw new Exception(self::trans('TakeposRefundRestockPermissionRequired', 'Restock requires permission: takepos.refund.restock_control'));

        $refundRef = self::generateRefundRef($entity);
        $now = dol_print_date(dol_now(), 'dayhourlog');

        $db->begin();
        try {
            $sql = "INSERT INTO " . self::tableRefund() . " (entity, fk_original_invoice, fk_store, fk_terminal, fk_cashier_user, refund_ref, refund_type, total_amount, payment_method, reason_code, note, status, fk_approved_by, date_creation) VALUES ("
                . $entity . ", "
                . ($invoiceId > 0 ? $invoiceId : 'NULL') . ", "
                . ($storeId > 0 ? $storeId : 'NULL') . ", "
                . ($terminalId > 0 ? $terminalId : 'NULL') . ", "
                . ((int) $user->id) . ", "
                . "'" . $db->escape($refundRef) . "', "
                . "'" . $db->escape($mode) . "', "
                . ((float) $totalAmount) . ", "
                . "'" . $db->escape($paymentMethod) . "', "
                . "'" . $db->escape($reasonCode !== '' ? $reasonCode : 'other') . "', "
                . ($note !== '' ? "'" . $db->escape($note) . "'" : 'NULL') . ", "
                . "'" . self::STATUS_COMPLETED . "', "
                . ($approvedBy > 0 ? $approvedBy : 'NULL') . ", "
                . "'" . $db->escape($now) . "')";
            if (!$db->query($sql)) throw new Exception($db->lasterror());
            $refundId = (int) $db->last_insert_id(self::tableRefund());

            foreach ($lineRows as $r) {
                $ins = "INSERT INTO " . self::tableRefundLine() . " (entity, fk_refund, fk_original_line, fk_product, qty_refunded, unit_price, line_total, restock_flag, date_creation) VALUES ("
                    . $entity . ", " . $refundId . ", "
                    . (!empty($r['line_id']) ? (int) $r['line_id'] : 'NULL') . ", "
                    . (!empty($r['product_id']) ? (int) $r['product_id'] : 'NULL') . ", "
                    . ((float) $r['qty']) . ", "
                    . ((float) $r['unit_price']) . ", "
                    . ((float) $r['line_total']) . ", "
                    . (!empty($r['restock_flag']) ? 1 : 0) . ", "
                    . "'" . $db->escape($now) . "')";
                if (!$db->query($ins)) throw new Exception($db->lasterror());
                if (!empty($r['restock_flag'])) self::applyRestockForLine($db, $user, $warehouseId, (int) $r['product_id'], (float) $r['qty'], $refundRef);
            }

            self::registerCashImpactIfNeeded($db, $user, (float) $totalAmount, $paymentMethod, $refundRef);

            // Create Dolibarr credit note (avoir) linked to the original invoice
            $creditNoteId = 0;
            if ($invoiceId > 0 && !empty($lineRows)) {
                try {
                    $creditNoteId = self::createDolibarrCreditNote($db, $user, $invoiceId, $lineRows, $terminalId, $refundRef);
                } catch (Throwable $_cnEx) {
                    // Non-fatal: log the failure but don't roll back the refund record
                    dol_syslog('[TakePOS][Refund] Credit note creation failed: ' . $_cnEx->getMessage(), LOG_WARNING);
                }
            }

            $db->commit();

            self::safeAudit($db, $user, ($mode === self::TYPE_PARTIAL ? 'refund_partial_success' : 'refund_success'), TakeposAudit::SEVERITY_INFO, array('refund_id' => $refundId, 'refund_ref' => $refundRef, 'refund_type' => $mode, 'invoice_id' => $invoiceId, 'approved_by' => $approvedBy, 'line_count' => count($lineRows), 'payment_method' => $paymentMethod), 'Refund completed', 'refund', $refundId, (float) $totalAmount);
            self::safeWebhookEmit($db, $entity, 'refund_completed', array(
                'refund_id' => $refundId,
                'refund_ref' => $refundRef,
                'refund_type' => $mode,
                'original_invoice_id' => (int) $invoiceId,
                'store_id' => (int) $storeId,
                'terminal_id' => (int) $terminalId,
                'cashier_user_id' => (int) $user->id,
                'approved_by' => (int) $approvedBy,
                'total_amount' => (float) $totalAmount,
                'payment_method' => (string) $paymentMethod,
            ), $user);

            return array('refund_id' => $refundId, 'refund_ref' => $refundRef, 'total_amount' => (float) $totalAmount, 'approved_by' => $approvedBy, 'credit_note_id' => $creditNoteId);
        } catch (Throwable $e) {
            $db->rollback();
            self::safeAudit($db, $user, 'refund_rejected', TakeposAudit::SEVERITY_WARNING, array('refund_type' => $mode, 'invoice_id' => $invoiceId, 'reason' => $e->getMessage()), 'Refund rejected');
            throw $e;
        }
    }

    public static function listRefunds($db, $entity, $filters = array(), $limit = 200)
    {
        self::ensureSchema($db);
        $limit = max(1, min(500, (int) $limit));
        $sql = "SELECT r.rowid, r.refund_ref, r.refund_type, r.total_amount, r.payment_method, r.reason_code, r.status, r.fk_original_invoice, r.fk_store, r.fk_terminal, r.fk_cashier_user, r.fk_approved_by, r.date_creation, f.ref AS original_invoice_ref"
            . " FROM " . self::tableRefund() . " r"
            . " LEFT JOIN " . MAIN_DB_PREFIX . "facture f ON f.rowid = r.fk_original_invoice"
            . " WHERE r.entity = " . ((int) $entity);
        if (!empty($filters['invoice_id'])) $sql .= " AND r.fk_original_invoice = " . ((int) $filters['invoice_id']);
        if (!empty($filters['refund_ref'])) $sql .= " AND r.refund_ref LIKE '%" . $db->escape((string) $filters['refund_ref']) . "%'";
        if (!empty($filters['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $filters['date_from'])) $sql .= " AND r.date_creation >= '" . $db->escape($filters['date_from']) . " 00:00:00'";
        if (!empty($filters['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $filters['date_to'])) $sql .= " AND r.date_creation <= '" . $db->escape($filters['date_to']) . " 23:59:59'";
        $sql .= " ORDER BY r.rowid DESC LIMIT " . $limit;

        $rows = array();
        $resql = $db->query($sql);
        if ($resql) while ($obj = $db->fetch_object($resql)) $rows[] = $obj;
        return $rows;
    }

    public static function getRefundById($db, $entity, $refundId)
    {
        self::ensureSchema($db);
        $sql = "SELECT r.*, f.ref AS original_invoice_ref, t.terminal_code, t.label AS terminal_label, s.code AS store_code, s.label AS store_label"
            . " FROM " . self::tableRefund() . " r"
            . " LEFT JOIN " . MAIN_DB_PREFIX . "facture f ON f.rowid = r.fk_original_invoice"
            . " LEFT JOIN " . TakeposTerminalService::tableTerminal() . " t ON t.rowid = r.fk_terminal"
            . " LEFT JOIN " . TakeposStoreService::tableStore() . " s ON s.rowid = r.fk_store"
            . " WHERE r.entity = " . ((int) $entity) . " AND r.rowid = " . ((int) $refundId) . " LIMIT 1";
        $resql = $db->query($sql);
        return ($resql ? $db->fetch_object($resql) : null);
    }

    public static function getRefundLines($db, $entity, $refundId)
    {
        self::ensureSchema($db);
        $rows = array();
        $sql = "SELECT rl.rowid, rl.fk_original_line, rl.fk_product, rl.qty_refunded, rl.unit_price, rl.line_total, rl.restock_flag, fd.label AS original_line_label"
            . " FROM " . self::tableRefundLine() . " rl"
            . " LEFT JOIN " . MAIN_DB_PREFIX . "facturedet fd ON fd.rowid = rl.fk_original_line"
            . " WHERE rl.entity = " . ((int) $entity) . " AND rl.fk_refund = " . ((int) $refundId)
            . " ORDER BY rl.rowid ASC";
        $resql = $db->query($sql);
        if ($resql) while ($obj = $db->fetch_object($resql)) $rows[] = $obj;
        return $rows;
    }
    /**
     * Create a validated Dolibarr credit note (avoir) linked to the original invoice.
     * Only lines included in the refund are copied, with their quantities and prices
     * negated so the credit note exactly mirrors the refunded amount.
     *
     * @param DoliDB $db
     * @param User   $user
     * @param int    $originalInvoiceId
     * @param array  $lineRows          Refund lines: [line_id, product_id, qty, unit_price, line_total, restock_flag]
     * @param int    $terminalId
     * @param string $refundRef
     * @return int  Credit note rowid (0 on failure)
     */
    private static function createDolibarrCreditNote($db, $user, $originalInvoiceId, $lineRows, $terminalId, $refundRef)
    {
        require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';

        // Load the original invoice
        $origInvoice = new Facture($db);
        if ($origInvoice->fetch((int) $originalInvoiceId) <= 0) {
            dol_syslog('[TakePOS][CreditNote] Cannot load original invoice ' . $originalInvoiceId, LOG_WARNING);
            return 0;
        }
        if ($origInvoice->fetch_lines() < 0) {
            dol_syslog('[TakePOS][CreditNote] Cannot load lines for invoice ' . $originalInvoiceId, LOG_WARNING);
            return 0;
        }

        // Build a map of original lines by line_id for quick lookup
        $origLineMap = array();
        foreach ($origInvoice->lines as $ol) {
            $origLineMap[(int) $ol->id] = $ol;
        }

        // Build a map of refunded lines keyed by line_id
        $refundLineMap = array();
        foreach ($lineRows as $r) {
            $refundLineMap[(int) $r['line_id']] = $r;
        }

        // Create the credit note header
        $creditNote = new Facture($db);
        $creditNote->socid        = $origInvoice->socid;
        $creditNote->date         = dol_now();
        $creditNote->module_source = 'takepos';
        $creditNote->pos_source   = (string) $terminalId;
        $creditNote->type         = Facture::TYPE_CREDIT_NOTE;
        $creditNote->fk_facture_source = (int) $originalInvoiceId;
        $creditNote->note_public  = 'POS Refund ' . $refundRef;

        $cnId = $creditNote->create($user);
        if ($cnId <= 0) {
            dol_syslog('[TakePOS][CreditNote] create() failed: ' . $creditNote->error, LOG_WARNING);
            return 0;
        }

        // Add only the refunded lines, with negated amounts
        foreach ($refundLineMap as $lineId => $refRow) {
            if (!isset($origLineMap[$lineId])) {
                continue;
            }
            $ol  = $origLineMap[$lineId];
            $qty = (float) $refRow['qty']; // positive qty — credit note lines use positive qty, Dolibarr handles sign via type

            // Determine the proportion of the original line being refunded
            $origQty = abs((float) $ol->qty);
            $ratio   = ($origQty > 0) ? ($qty / $origQty) : 1.0;

            // Build amounts (negated for credit note)
            $totalHt  = -abs((float) $ol->total_ht)  * $ratio;
            $totalTva = -abs((float) $ol->total_tva) * $ratio;
            $totalTtc = -abs((float) $ol->total_ttc) * $ratio;
            $subprice = -abs((float) $ol->subprice);

            $newLine = new FactureLigne($db);
            $newLine->fk_facture     = $cnId;
            $newLine->fk_product     = (int) $ol->fk_product;
            $newLine->product_type   = (int) $ol->product_type;
            $newLine->desc           = $ol->desc;
            $newLine->label          = $ol->label;
            $newLine->qty            = $qty;
            $newLine->subprice       = $subprice;
            $newLine->tva_tx         = (float) $ol->tva_tx;
            $newLine->localtax1_tx   = (float) $ol->localtax1_tx;
            $newLine->localtax2_tx   = (float) $ol->localtax2_tx;
            $newLine->remise_percent = (float) $ol->remise_percent;
            $newLine->total_ht       = $totalHt;
            $newLine->total_tva      = $totalTva;
            $newLine->total_ttc      = $totalTtc;
            $newLine->total_localtax1 = -abs((float) $ol->total_localtax1) * $ratio;
            $newLine->total_localtax2 = -abs((float) $ol->total_localtax2) * $ratio;
            $newLine->rang           = (int) $ol->rang;
            $newLine->info_bits      = (int) $ol->info_bits;
            $newLine->fk_unit        = $ol->fk_unit;

            $res = $newLine->insert(0, 1);
            if ($res < 0) {
                dol_syslog('[TakePOS][CreditNote] insert line failed for line_id=' . $lineId . ': ' . $newLine->error, LOG_WARNING);
            }
        }

        $creditNote->update_price(1);

        // Validate the credit note
        $terminalCode = (string) $terminalId;
        $warehouseConst = 'CASHDESK_ID_WAREHOUSE' . $terminalCode;
        $warehouseId = getDolGlobalInt($warehouseConst);

        if (isModEnabled('stock') && $warehouseId > 0) {
            $savConst = getDolGlobalString('STOCK_CALCULATE_ON_BILL');
            global $conf;
            $conf->global->STOCK_CALCULATE_ON_BILL = 1;
            $creditNote->validate($user, '', $warehouseId, 0, 0);
            $conf->global->STOCK_CALCULATE_ON_BILL = $savConst;
        } else {
            $creditNote->validate($user);
        }

        dol_syslog('[TakePOS][CreditNote] Created credit note id=' . $cnId . ' for invoice id=' . $originalInvoiceId . ' refund=' . $refundRef, LOG_INFO);
        return $cnId;
    }

}