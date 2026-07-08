<?php
require_once __DIR__ . '/TakeposMigration.class.php';
require_once __DIR__ . '/TakeposAudit.class.php';
require_once __DIR__ . '/TakeposInputValidator.class.php';
require_once __DIR__ . '/TakeposUserAccess.class.php';
require_once __DIR__ . '/TakeposRefundService.class.php';
require_once __DIR__ . '/TakeposCashService.class.php';
require_once __DIR__ . '/TakeposShiftService.class.php';
require_once __DIR__ . '/TakeposManagerOverrideService.class.php';

if (defined('DOL_DOCUMENT_ROOT')) {
    require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
    require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
}

/**
 * Exchange workflow service.
 */
class TakeposExchangeService
{
    const STATUS_COMPLETED = 'completed';

    public static function tableExchange()
    {
        return MAIN_DB_PREFIX . 'takepos_exchange';
    }

    private static function safeAudit($db, $user, $eventType, $severity, $data = array(), $description = '', $objectType = '', $objectId = 0, $amount = null)
    {
        try {
            TakeposAudit::logEvent($db, $user, $eventType, $severity, $data, $description, $objectType, $objectId, $amount);
        } catch (Throwable $e) {
            if (function_exists('dol_syslog')) {
                dol_syslog('[TakePOS][Exchange] Audit failure: ' . $e->getMessage(), LOG_WARNING);
            }
        }
    }

    public static function ensureSchema($db)
    {
        TakeposRefundService::ensureSchema($db);

        $table = self::tableExchange();
        $ok = TakeposMigration::ensureTable($db, $table, "CREATE TABLE " . $table . " ("
            . " rowid INT AUTO_INCREMENT PRIMARY KEY,"
            . " entity INT NOT NULL DEFAULT 1,"
            . " fk_original_invoice INT NOT NULL,"
            . " fk_refund INT NULL,"
            . " fk_new_invoice INT NULL,"
            . " exchange_ref VARCHAR(64) NOT NULL,"
            . " return_total DECIMAL(24,8) NOT NULL DEFAULT 0,"
            . " new_sale_total DECIMAL(24,8) NOT NULL DEFAULT 0,"
            . " net_difference DECIMAL(24,8) NOT NULL DEFAULT 0,"
            . " status VARCHAR(24) NOT NULL DEFAULT 'completed',"
            . " fk_approved_by INT NULL,"
            . " date_creation DATETIME NOT NULL,"
            . " tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
            . " UNIQUE KEY uk_takepos_exchange_ref (entity, exchange_ref),"
            . " KEY idx_takepos_exchange_original (entity, fk_original_invoice),"
            . " KEY idx_takepos_exchange_refund (entity, fk_refund),"
            . " KEY idx_takepos_exchange_new_invoice (entity, fk_new_invoice),"
            . " KEY idx_takepos_exchange_status (entity, status)"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if (!$ok) {
            return false;
        }

        $cols = array(
            'entity' => "INT NOT NULL DEFAULT 1",
            'fk_original_invoice' => "INT NOT NULL",
            'fk_refund' => "INT NULL",
            'fk_new_invoice' => "INT NULL",
            'exchange_ref' => "VARCHAR(64) NOT NULL",
            'return_total' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
            'new_sale_total' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
            'net_difference' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
            'status' => "VARCHAR(24) NOT NULL DEFAULT 'completed'",
            'fk_approved_by' => "INT NULL",
            'date_creation' => "DATETIME NOT NULL",
            'tms' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        );
        foreach ($cols as $c => $d) {
            if (!TakeposMigration::ensureColumn($db, $table, $c, $d)) {
                return false;
            }
        }

        return true;
    }

    private static function generateExchangeRef($entity)
    {
        $rand = strtoupper(substr(sha1(uniqid('', true)), 0, 6));
        return 'EX-' . ((int) $entity) . '-' . dol_print_date(dol_now(), '%Y%m%d-%H%M%S') . '-' . $rand;
    }

    private static function parseNewSaleLines($raw)
    {
        $rows = array();
        foreach ((array) $raw as $line) {
            if (!is_array($line)) {
                continue;
            }
            $productId = isset($line['product_id']) ? (int) $line['product_id'] : 0;
            if ($productId <= 0) {
                continue;
            }

            $qty = null;
            if (!TakeposInputValidator::parsePositiveDecimal(isset($line['qty']) ? $line['qty'] : '', $qty, false, 8)) {
                throw new Exception('Invalid exchange sale quantity for product #' . $productId);
            }

            $unitPrice = null;
            if (!TakeposInputValidator::parsePositiveDecimal(isset($line['unit_price']) ? $line['unit_price'] : '', $unitPrice, true, 8)) {
                throw new Exception('Invalid exchange sale price for product #' . $productId);
            }

            $rows[] = array(
                'product_id' => $productId,
                'qty' => (float) $qty,
                'unit_price' => (float) $unitPrice,
            );
        }

        if (empty($rows)) {
            throw new Exception('At least one replacement line is required for exchange.');
        }

        return $rows;
    }

    private static function createReplacementInvoice($db, $user, $originalInvoice, $newLines)
    {
        $invoice = new Facture($db);
        $invoice->socid = (int) $originalInvoice->socid;
        $invoice->type = Facture::TYPE_STANDARD;
        $invoice->date = dol_now();
        $invoice->module_source = 'takepos';
        $invoice->pos_source = (string) $originalInvoice->pos_source;

        $resCreate = $invoice->create($user);
        if ($resCreate <= 0) {
            throw new Exception(!empty($invoice->error) ? $invoice->error : 'Unable to create replacement invoice.');
        }

        foreach ($newLines as $line) {
            $product = new Product($db);
            if ($product->fetch((int) $line['product_id']) <= 0) {
                throw new Exception('Product not found for exchange line #' . ((int) $line['product_id']));
            }

            $desc = !empty($product->label) ? $product->label : ('Product #' . ((int) $line['product_id']));
            $resAdd = $invoice->addline(
                $desc,
                (float) $line['unit_price'],
                (float) $line['qty'],
                isset($product->tva_tx) ? (float) $product->tva_tx : 0,
                0,
                0,
                (int) $line['product_id']
            );
            if ($resAdd <= 0) {
                throw new Exception(!empty($invoice->error) ? $invoice->error : 'Unable to add exchange sale line.');
            }
        }

        $invoice->fetch($invoice->id);
        return $invoice;
    }

    public static function createExchange($db, $user, $payload = array())
    {
        self::ensureSchema($db);

        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        $invoiceId = isset($payload['original_invoice_id']) ? (int) $payload['original_invoice_id'] : 0;
        if ($invoiceId <= 0) {
            throw new Exception('Original invoice is required for exchange.');
        }

        self::safeAudit($db, $user, 'exchange_attempt', TakeposAudit::SEVERITY_WARNING, array('invoice_id' => $invoiceId), 'Exchange attempt');

        $hasPermission = (!empty($user->admin) || TakeposUserAccess::userHasPermission($db, $user, 'takepos.exchange.process'));
        $approvedBy = 0;

        if (!$hasPermission) {
            if (!TakeposAccess::isFeatureEnabledForCurrentEntity($db, 'takepos.manager_override')) {
                throw new Exception('Manager approval is required for exchange.');
            }

            $approval = TakeposManagerOverrideService::approveFromPayload($db, $user, array(
                'override_action' => 'exchange_process',
                'invoice_id' => $invoiceId,
                'line_id' => 0,
                'requested_number' => '0',
                'manager_barcode' => isset($payload['manager_barcode']) ? $payload['manager_barcode'] : '',
                'manager_login' => isset($payload['manager_login']) ? $payload['manager_login'] : '',
                'manager_password' => isset($payload['manager_password']) ? $payload['manager_password'] : '',
            ));
            if (empty($approval['success'])) {
                self::safeAudit($db, $user, 'exchange_rejected', TakeposAudit::SEVERITY_WARNING, array('invoice_id' => $invoiceId, 'reason' => isset($approval['data']['reason']) ? $approval['data']['reason'] : 'manager_rejected'), 'Exchange rejected by manager approval');
                throw new Exception(isset($approval['message']) ? $approval['message'] : 'Manager approval failed.');
            }
            $approvedBy = isset($approval['data']['manager_id']) ? (int) $approval['data']['manager_id'] : 0;
            TakeposManagerOverrideService::consumeSession($db, 'exchange_flow_used');
        }

        $originalInvoice = new Facture($db);
        if ($originalInvoice->fetch($invoiceId) <= 0) {
            throw new Exception('Original invoice not found.');
        }
        if ((int) $originalInvoice->entity !== $entity) {
            throw new Exception('Invoice entity mismatch.');
        }

        $newSaleLines = self::parseNewSaleLines(isset($payload['new_lines']) ? $payload['new_lines'] : array());

        $db->begin();
        try {
            $refund = TakeposRefundService::createRefund($db, $user, array(
                'refund_type' => TakeposRefundService::TYPE_EXCHANGE,
                'original_invoice_id' => $invoiceId,
                'reason_code' => isset($payload['reason_code']) ? $payload['reason_code'] : 'other',
                'note' => isset($payload['note']) ? $payload['note'] : '',
                'payment_method' => 'OTHER',
                'lines' => isset($payload['return_lines']) ? $payload['return_lines'] : array(),
                'restock_default' => isset($payload['restock_default']) ? $payload['restock_default'] : 0,
                'manager_barcode' => isset($payload['manager_barcode']) ? $payload['manager_barcode'] : '',
                'manager_login' => isset($payload['manager_login']) ? $payload['manager_login'] : '',
                'manager_password' => isset($payload['manager_password']) ? $payload['manager_password'] : '',
            ));

            $replacement = self::createReplacementInvoice($db, $user, $originalInvoice, $newSaleLines);
            $replacementTotal = (float) $replacement->total_ttc;
            $returnTotal = (float) $refund['total_amount'];
            $netDifference = $replacementTotal - $returnTotal;

            $settlementMethod = strtoupper(trim((string) (isset($payload['settlement_method']) ? $payload['settlement_method'] : 'CASH')));
            if ($settlementMethod === 'LIQ') {
                $settlementMethod = 'CASH';
            }

            if ($netDifference < 0 && $settlementMethod === 'CASH') {
                $refundDue = abs($netDifference);
                if (TakeposAccess::isFeatureEnabledForCurrentEntity($db, 'takepos.cash_control')) {
                    $terminalCode = isset($_SESSION['takeposterminal']) ? (string) $_SESSION['takeposterminal'] : '1';
                    $summary = TakeposShiftService::getCurrentActiveShiftSummary($db, $user, $terminalCode);
                    if (!$summary || empty($summary['shift_id'])) {
                        if (TakeposShiftService::requireShiftForCashMovements()) {
                            throw new Exception('Active shift is required for cash exchange refund movement.');
                        }
                    } else {
                        TakeposCashService::createMovement($db, $user, (int) $summary['shift_id'], TakeposCashService::TYPE_PAID_OUT, (float) $refundDue, 'exchange_refund', 'Exchange net refund for invoice #' . $invoiceId);
                    }
                }
            }

            $exchangeRef = self::generateExchangeRef($entity);
            $now = dol_print_date(dol_now(), 'dayhourlog');
            $sql = "INSERT INTO " . self::tableExchange() . " (entity, fk_original_invoice, fk_refund, fk_new_invoice, exchange_ref, return_total, new_sale_total, net_difference, status, fk_approved_by, date_creation) VALUES ("
                . $entity . ", "
                . $invoiceId . ", "
                . ((int) $refund['refund_id']) . ", "
                . ((int) $replacement->id) . ", "
                . "'" . $db->escape($exchangeRef) . "', "
                . ((float) $returnTotal) . ", "
                . ((float) $replacementTotal) . ", "
                . ((float) $netDifference) . ", "
                . "'" . self::STATUS_COMPLETED . "', "
                . ($approvedBy > 0 ? $approvedBy : 'NULL') . ", "
                . "'" . $db->escape($now) . "')";
            if (!$db->query($sql)) {
                throw new Exception($db->lasterror());
            }
            $exchangeId = (int) $db->last_insert_id(self::tableExchange());

            $db->commit();

            self::safeAudit($db, $user, 'exchange_success', TakeposAudit::SEVERITY_INFO, array(
                'exchange_id' => $exchangeId,
                'exchange_ref' => $exchangeRef,
                'invoice_id' => $invoiceId,
                'refund_id' => (int) $refund['refund_id'],
                'new_invoice_id' => (int) $replacement->id,
                'return_total' => $returnTotal,
                'new_sale_total' => $replacementTotal,
                'net_difference' => $netDifference,
                'approved_by' => $approvedBy,
            ), 'Exchange completed', 'exchange', $exchangeId, $netDifference);

            return array(
                'exchange_id' => $exchangeId,
                'exchange_ref' => $exchangeRef,
                'refund_id' => (int) $refund['refund_id'],
                'new_invoice_id' => (int) $replacement->id,
                'return_total' => (float) $returnTotal,
                'new_sale_total' => (float) $replacementTotal,
                'net_difference' => (float) $netDifference,
            );
        } catch (Throwable $e) {
            $db->rollback();
            self::safeAudit($db, $user, 'exchange_rejected', TakeposAudit::SEVERITY_WARNING, array('invoice_id' => $invoiceId, 'reason' => $e->getMessage()), 'Exchange rejected');
            throw $e;
        }
    }
}
