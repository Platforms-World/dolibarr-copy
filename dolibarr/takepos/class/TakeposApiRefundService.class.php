<?php
require_once __DIR__ . '/TakeposApiPaymentService.class.php';
require_once __DIR__ . '/TakeposRefundService.class.php';

class TakeposApiRefundService
{
    public static function generateRefundRef($entity)
    {
        return 'API-RF-' . ((int) $entity) . '-' . dol_print_date(dol_now(), '%Y%m%d-%H%M%S');
    }

    private static function safeAudit($db, $user, $eventCode, $severity, $data = array(), $description = '', $objectType = '', $objectId = 0)
    {
        try {
            TakeposAudit::logEvent($db, $user, $eventCode, $severity, (array) $data, (string) $description, (string) $objectType, (int) $objectId);
        } catch (Throwable $e) {
            if (function_exists('dol_syslog')) {
                dol_syslog('[TakePOS][API Refund] Audit failure: ' . $e->getMessage(), LOG_WARNING);
            }
        }
    }

    public static function createBasicRefund($db, $entity, $invoice, $amount, $reason)
    {
        $amount = (float) price2num($amount, 'MU');
        $reason = trim((string) $reason);
        $actor = (!empty($GLOBALS['user']) && is_object($GLOBALS['user']) ? $GLOBALS['user'] : new stdClass());
        if (!isset($actor->id)) {
            $actor->id = 0;
        }
        if (empty($actor->entity)) {
            $actor->entity = (int) $entity;
        }

        if ($amount <= 0) {
            throw new TakeposApiException('INVALID_PARAMETER', 'amount must be greater than zero.', 422);
        }
        if (!is_object($invoice) || empty($invoice->id) || (string) $invoice->module_source !== 'takepos' || (int) $invoice->entity !== (int) $entity) {
            throw new TakeposApiException('NOT_FOUND', 'Invoice not found.', 404);
        }
        if ((int) $invoice->status === Facture::STATUS_DRAFT) {
            throw new TakeposApiException('INVALID_PARAMETER', 'Refund is not allowed on a draft invoice.', 409);
        }

        TakeposRefundService::ensureSchema($db);
        $settlement = TakeposApiPaymentService::invoiceSettlement($db, $entity, $invoice);
        if ((float) $settlement['paid_amount'] <= 0.000001) {
            throw new TakeposApiException('INSUFFICIENT_PAYMENT', 'Invoice has no refundable paid amount.', 409);
        }
        if ($amount - (float) $settlement['paid_amount'] > 0.000001) {
            throw new TakeposApiException('INSUFFICIENT_PAYMENT', 'Refund amount exceeds paid amount.', 409);
        }

        $storeId = (int) TakeposRefundService::getInvoiceStoreId($db, $entity, $invoice);
        $terminalId = 0;
        if (!empty($invoice->pos_source)) {
            $terminal = TakeposTerminalService::getTerminalByCode($db, $entity, (string) $invoice->pos_source);
            if ($terminal) {
                $terminalId = (int) $terminal->rowid;
            }
        }

        $refundType = ($amount + 0.000001 >= (float) $settlement['paid_amount'] ? TakeposRefundService::TYPE_FULL : TakeposRefundService::TYPE_PARTIAL);
        $refundRef = self::generateRefundRef($entity);
        $userId = (!empty($actor->id) ? (int) $actor->id : 0);
        $now = dol_print_date(dol_now(), 'dayhourlog');

        self::safeAudit(
            $db,
            $actor,
            'api_refund_attempt',
            TakeposAudit::SEVERITY_WARNING,
            array('invoice_id' => (int) $invoice->id, 'amount' => (float) $amount, 'refund_type' => $refundType),
            'API refund attempt',
            'invoice',
            (int) $invoice->id
        );

        $db->begin();

        try {
            $sql = 'INSERT INTO ' . TakeposRefundService::tableRefund()
                . ' (entity, fk_original_invoice, fk_store, fk_terminal, fk_cashier_user, refund_ref, refund_type, total_amount, payment_method, reason_code, note, status, date_creation) VALUES ('
                . ((int) $entity) . ', '
                . ((int) $invoice->id) . ', '
                . ($storeId > 0 ? $storeId : 'NULL') . ', '
                . ($terminalId > 0 ? $terminalId : 'NULL') . ', '
                . $userId . ', '
                . chr(39) . $db->escape($refundRef) . chr(39) . ', '
                . chr(39) . $db->escape($refundType) . chr(39) . ', '
                . ((float) $amount) . ', '
                . chr(39) . 'API' . chr(39) . ', '
                . chr(39) . 'other' . chr(39) . ', '
                . ($reason !== '' ? chr(39) . $db->escape($reason) . chr(39) : 'NULL') . ', '
                . chr(39) . TakeposRefundService::STATUS_COMPLETED . chr(39) . ', '
                . chr(39) . $db->escape($now) . chr(39) . ')';

            if (!$db->query($sql)) {
                throw new Exception($db->lasterror());
            }

            $refundId = (int) $db->last_insert_id(TakeposRefundService::tableRefund());
            $db->commit();

            self::safeAudit(
                $db,
                $actor,
                'api_refund_success',
                TakeposAudit::SEVERITY_INFO,
                array('refund_id' => $refundId, 'invoice_id' => (int) $invoice->id, 'amount' => (float) $amount, 'refund_type' => $refundType),
                'API refund completed',
                'refund',
                $refundId
            );
        } catch (Throwable $e) {
            $db->rollback();
            self::safeAudit(
                $db,
                $actor,
                'api_refund_rejected',
                TakeposAudit::SEVERITY_WARNING,
                array('invoice_id' => (int) $invoice->id, 'amount' => (float) $amount, 'error' => $e->getMessage()),
                'API refund rejected',
                'invoice',
                (int) $invoice->id
            );

            if ($e instanceof TakeposApiException) {
                throw $e;
            }
            throw new TakeposApiException('INTERNAL_ERROR', $e->getMessage(), 500);
        }

        $invoice->fetch((int) $invoice->id);
        if (method_exists($invoice, 'fetch_lines')) {
            $invoice->fetch_lines();
        }
        $settlement = TakeposApiPaymentService::invoiceSettlement($db, $entity, $invoice);

        return array(
            'refund_id' => $refundId,
            'refund_ref' => $refundRef,
            'invoice_id' => (int) $invoice->id,
            'amount' => (float) $amount,
            'paid_amount' => (float) $settlement['paid_amount'],
            'remaining_amount' => (float) $settlement['remaining_amount'],
            'payment_status' => $settlement['payment_status'],
        );
    }
}
