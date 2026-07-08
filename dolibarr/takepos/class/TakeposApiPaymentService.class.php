<?php
require_once __DIR__ . '/TakeposApiCheckoutService.class.php';
require_once __DIR__ . '/TakeposRefundService.class.php';

if (defined('DOL_DOCUMENT_ROOT')) {
    require_once DOL_DOCUMENT_ROOT . '/compta/paiement/class/paiement.class.php';
    require_once DOL_DOCUMENT_ROOT . '/takepos/lib/takepos_help.php';
}

class TakeposApiPaymentService
{
    public static function normalizeMethod($method)
    {
        $method = strtolower(trim((string) $method));
        if (!in_array($method, array('cash', 'card'), true)) {
            throw new TakeposApiException('INVALID_PARAMETER', 'Unsupported payment method.', 422);
        }

        return $method;
    }

    public static function paymentCode($method)
    {
        return (self::normalizeMethod($method) === 'cash' ? 'LIQ' : 'CB');
    }

    public static function bankCode($method)
    {
        return (self::normalizeMethod($method) === 'cash' ? 'CASH' : 'CB');
    }

    public static function resolvePaiementId($db, $method)
    {
        $code = self::paymentCode($method);
        $sql = 'SELECT id FROM ' . MAIN_DB_PREFIX . 'c_paiement WHERE entity IN (' . getEntity('c_paiement') . ') AND code = ' . chr(39) . $db->escape($code) . chr(39) . ' LIMIT 1';
        $resql = $db->query($sql);
        $obj = ($resql ? $db->fetch_object($resql) : null);
        if (!$obj || empty($obj->id)) {
            throw new TakeposApiException('PAYMENT_METHOD_NOT_CONFIGURED', 'Payment method is not configured in Dolibarr.', 422);
        }

        return (int) $obj->id;
    }

    public static function resolveBankAccountId($method, $terminalCode)
    {
        $bankAccountId = takeposResolveTerminalBankAccountId(self::bankCode($method), (string) $terminalCode);
        if ((int) $bankAccountId <= 0) {
            throw new TakeposApiException('PAYMENT_METHOD_NOT_CONFIGURED', 'Bank account is not configured for this payment method.', 422);
        }

        return (int) $bankAccountId;
    }

    public static function refundedAmount($db, $entity, $invoiceId)
    {
        TakeposRefundService::ensureSchema($db);
        $sql = 'SELECT COALESCE(SUM(total_amount), 0) AS amount FROM ' . TakeposRefundService::tableRefund()
            . ' WHERE entity = ' . ((int) $entity)
            . ' AND fk_original_invoice = ' . ((int) $invoiceId)
            . ' AND status = ' . chr(39) . TakeposRefundService::STATUS_COMPLETED . chr(39);
        $resql = $db->query($sql);
        $obj = ($resql ? $db->fetch_object($resql) : null);

        return ($obj ? (float) price2num($obj->amount, 'MT') : 0.0);
    }

    public static function invoiceSettlement($db, $entity, $invoice)
    {
        $total = (float) price2num($invoice->total_ttc, 'MT');
        $grossRemaining = max(0.0, (float) price2num($invoice->getRemainToPay(), 'MT'));
        $grossPaid = max(0.0, $total - $grossRemaining);
        $refunded = self::refundedAmount($db, $entity, (int) $invoice->id);
        $paid = max(0.0, $grossPaid - $refunded);
        $remaining = max(0.0, $total - $paid);
        $status = 'unpaid';

        if ($refunded > 0.000001) {
            if ($grossPaid > 0.000001 && $paid <= 0.000001) {
                $status = 'refunded';
            } elseif ($remaining > 0.000001) {
                $status = 'partial_refund';
            } else {
                $status = 'paid';
            }
        } else {
            if ($paid <= 0.000001) {
                $status = 'unpaid';
            } elseif ($remaining > 0.000001) {
                $status = 'partial';
            } else {
                $status = 'paid';
            }
        }

        return array(
            'paid_amount' => (float) $paid,
            'remaining_amount' => (float) $remaining,
            'payment_status' => $status,
            'gross_paid_amount' => (float) $grossPaid,
            'refunded_amount' => (float) $refunded,
        );
    }

    public static function requirePayableInvoice($db, $entity, $invoice)
    {
        if (!is_object($invoice) || empty($invoice->id)) {
            throw new TakeposApiException('NOT_FOUND', 'Invoice not found.', 404);
        }
        if ((string) $invoice->module_source !== 'takepos') {
            throw new TakeposApiException('NOT_FOUND', 'Invoice not found.', 404);
        }
        if ((int) $invoice->entity !== (int) $entity) {
            throw new TakeposApiException('NOT_FOUND', 'Invoice not found.', 404);
        }
        if ((int) $invoice->status === Facture::STATUS_DRAFT) {
            throw new TakeposApiException('INVALID_CART_STATE', 'Invoice is still in draft state.', 409);
        }

        $settlement = self::invoiceSettlement($db, $entity, $invoice);
        if ($settlement['payment_status'] === 'paid') {
            throw new TakeposApiException('INVOICE_ALREADY_PAID', 'Invoice is already fully paid.', 409);
        }

        return $settlement;
    }

    private static function normalizedPayments($payments)
    {
        $normalized = array();
        foreach ((array) $payments as $one) {
            if (!is_array($one)) {
                continue;
            }

            $amount = (float) price2num(isset($one['amount']) ? $one['amount'] : 0, 'MU');
            if ($amount <= 0) {
                continue;
            }

            $method = self::normalizeMethod(isset($one['method']) ? $one['method'] : '');
            $normalized[] = array(
                'method' => $method,
                'amount' => $amount,
            );
        }

        return $normalized;
    }

    public static function paymentTotalAmount($payments)
    {
        $total = 0.0;
        foreach (self::normalizedPayments($payments) as $one) {
            $total += (float) $one['amount'];
        }

        return (float) $total;
    }

    public static function assertPaymentReady($db, $entity, $invoice, $method, $terminalId = 0, $currency = '')
    {
        $terminal = TakeposApiCheckoutService::resolveTerminalForInvoice($db, $entity, $invoice);
        if ((int) $terminalId > 0 && (int) $terminal->rowid !== (int) $terminalId) {
            throw new TakeposApiException('INVALID_PARAMETER', 'terminal_id does not match invoice terminal.', 422);
        }

        $terminalCode = (string) $terminal->terminal_code;
        $bankAccountId = self::resolveBankAccountId($method, $terminalCode);
        $paiementId = self::resolvePaiementId($db, $method);
        $activeShiftId = 0;

        if (TakeposUserAccess::featureEnabledForEntityStrict($db, $entity, 'takepos.shift_management')
            && TakeposShiftService::requireOpenShiftForPayments()
        ) {
            $shift = TakeposShiftService::getActiveShiftForTerminal($db, $entity, (int) $terminal->rowid);
            if (!$shift || empty($shift->rowid)) {
                throw new TakeposApiException('INVALID_CART_STATE', 'No open shift found for this terminal.', 409);
            }
            $activeShiftId = (int) $shift->rowid;
        }

        $invoiceCurrency = (!empty($invoice->multicurrency_code) ? $invoice->multicurrency_code : getDolGlobalString('MAIN_MONNAIE', 'USD'));
        if ($currency !== '' && strcasecmp($currency, $invoiceCurrency) !== 0) {
            throw new TakeposApiException('INVALID_PARAMETER', 'currency does not match invoice currency.', 422);
        }

        return array(
            'terminal' => $terminal,
            'terminal_code' => $terminalCode,
            'bank_account_id' => $bankAccountId,
            'paiement_id' => $paiementId,
            'shift_id' => $activeShiftId,
            'invoice_currency' => $invoiceCurrency,
        );
    }

    public static function applyPayments($db, $entity, $invoice, $payments, $terminalId = 0, $currency = '', $manageTransaction = true)
    {
        $settlement = self::requirePayableInvoice($db, $entity, $invoice);
        $normalizedPayments = self::normalizedPayments($payments);
        $firstMethod = (!empty($normalizedPayments[0]['method']) ? (string) $normalizedPayments[0]['method'] : 'cash');
        $paymentContext = self::assertPaymentReady($db, $entity, $invoice, $firstMethod, $terminalId, $currency);
        $terminalCode = (string) $paymentContext['terminal_code'];
        $activeShiftId = (int) $paymentContext['shift_id'];

        $requestedTotal = self::paymentTotalAmount($normalizedPayments);
        if ($requestedTotal <= 0) {
            throw new TakeposApiException('INSUFFICIENT_PAYMENT', 'Payment amount must be greater than zero.', 422);
        }
        if ($requestedTotal - (float) $settlement['remaining_amount'] > 0.000001) {
            throw new TakeposApiException('INVALID_PARAMETER', 'Payment exceeds remaining amount.', 422);
        }

        $actor = TakeposApiCheckoutService::actorUser($entity);
        $paymentIds = array();
        $lastPaiementId = 0;

        if ($manageTransaction) {
            $db->begin();
        }

        try {
            foreach ($normalizedPayments as $one) {
                $amount = (float) $one['amount'];
                $method = (string) $one['method'];
                $oneContext = self::assertPaymentReady($db, $entity, $invoice, $method, $terminalId, $currency);
                $bankAccountId = (int) $oneContext['bank_account_id'];
                $paiementId = (int) $oneContext['paiement_id'];

                $payment = new Paiement($db);
                $payment->datepaye = dol_now();
                $payment->fk_account = $bankAccountId;
                $payment->amounts[$invoice->id] = $amount;
                $payment->paiementid = $paiementId;
                $payment->num_payment = $invoice->ref;

                $res = $payment->create($actor);
                if ($res < 0) {
                    throw new TakeposApiException('INTERNAL_ERROR', !empty($payment->error) ? $payment->error : 'Failed to create payment.', 500);
                }

                $resBank = $payment->addPaymentToBank($actor, 'payment', '(CustomerInvoicePayment)', $bankAccountId, '', '');
                if ($resBank < 0) {
                    throw new TakeposApiException('INTERNAL_ERROR', !empty($payment->error) ? $payment->error : 'Failed to add payment to bank.', 500);
                }

                $paymentIds[] = (int) $res;
                $lastPaiementId = (int) $paiementId;
            }

            $invoice->fetch((int) $invoice->id);
            if (method_exists($invoice, 'fetch_lines')) {
                $invoice->fetch_lines();
            }

            if ((float) price2num($invoice->getRemainToPay(), 'MT') <= 0.000001) {
                $invoice->setPaid($actor);
                if ($lastPaiementId > 0) {
                    $invoice->setPaymentMethods($lastPaiementId);
                }
                $invoice->fetch((int) $invoice->id);
                if (method_exists($invoice, 'fetch_lines')) {
                    $invoice->fetch_lines();
                }
            }

            if ($manageTransaction) {
                $db->commit();
            }
        } catch (Throwable $e) {
            if ($manageTransaction) {
                $db->rollback();
            }

            if ($e instanceof TakeposApiException) {
                throw $e;
            }
            throw new TakeposApiException('INTERNAL_ERROR', $e->getMessage(), 500);
        }

        $settlement = self::invoiceSettlement($db, $entity, $invoice);
        return array(
            'payment_ids' => $paymentIds,
            'payment_id' => (!empty($paymentIds) ? (int) $paymentIds[count($paymentIds) - 1] : 0),
            'invoice_id' => (int) $invoice->id,
            'paid_amount' => (float) $settlement['paid_amount'],
            'remaining' => (float) $settlement['remaining_amount'],
            'status' => $settlement['payment_status'],
            'shift_id' => ($activeShiftId > 0 ? $activeShiftId : null),
        );
    }

    public static function applyFullPayment($db, $entity, $invoice, $method, $terminalId = 0, $manageTransaction = true)
    {
        $settlement = self::requirePayableInvoice($db, $entity, $invoice);
        if ((float) $settlement['remaining_amount'] <= 0.000001) {
            throw new TakeposApiException('INVOICE_ALREADY_PAID', 'Invoice is already fully paid.', 409);
        }

        return self::applyPayments(
            $db,
            $entity,
            $invoice,
            array(array('method' => $method, 'amount' => (float) $settlement['remaining_amount'])),
            $terminalId,
            '',
            $manageTransaction
        );
    }
}
