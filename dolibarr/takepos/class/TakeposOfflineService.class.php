<?php
require_once __DIR__ . '/TakeposAudit.class.php';
require_once __DIR__ . '/TakeposSyncService.class.php';

/**
 * Offline mode facade and queue helpers.
 */
class TakeposOfflineService
{
    public static function isOfflineModeSession()
    {
        return !empty($_SESSION['takepos_offline_mode']);
    }

    public static function canUseOffline($db, $user)
    {
        if (empty($user) || empty($user->id)) {
            return false;
        }
        if (!TakeposAccess::isFeatureEnabledForCurrentEntity($db, 'takepos.offline_mode')) {
            return false;
        }
        if (!empty($user->admin)) {
            return true;
        }
        return TakeposUserAccess::userHasPermission($db, $user, 'takepos.offline.use');
    }

    public static function setOfflineMode($db, $user, $enabled, $source = 'ui')
    {
        $enabled = !empty($enabled);
        $_SESSION['takepos_offline_mode'] = ($enabled ? 1 : 0);

        $event = $enabled ? 'offline_mode_entered' : 'offline_mode_exited';
        $msg = $enabled ? 'Offline mode enabled' : 'Offline mode disabled';
        TakeposAudit::logEvent(
            $db,
            $user,
            $event,
            TakeposAudit::SEVERITY_WARNING,
            array('source' => (string) $source),
            $msg
        );

        return array('offline_mode' => ($enabled ? 1 : 0));
    }

    public static function queueSaleSubmit($db, $user, $invoiceId, $localRef = '')
    {
        $invoiceId = (int) $invoiceId;
        if ($invoiceId <= 0) {
            throw new Exception('Invoice id is required to queue sale.');
        }

        return TakeposSyncService::enqueue(
            $db,
            $user,
            TakeposSyncService::ACTION_SALE_SUBMIT,
            array('invoice_id' => $invoiceId, 'queued_from' => 'pos'),
            $localRef
        );
    }

    public static function queuePaymentMeta($db, $user, $invoiceId, $paymentCode, $amount, $localRef = '')
    {
        $invoiceId = (int) $invoiceId;
        if ($invoiceId <= 0) {
            throw new Exception('Invoice id is required to queue payment metadata.');
        }

        $parsedAmount = null;
        if (!TakeposInputValidator::parsePositiveDecimal($amount, $parsedAmount, true, 8)) {
            throw new Exception('Invalid payment amount for queue.');
        }

        return TakeposSyncService::enqueue(
            $db,
            $user,
            TakeposSyncService::ACTION_PAYMENT_META,
            array(
                'invoice_id' => $invoiceId,
                'payment_code' => trim((string) $paymentCode),
                'amount' => (float) $parsedAmount,
                'queued_from' => 'pos'
            ),
            $localRef
        );
    }

    public static function queueCartSnapshot($db, $user, $snapshot, $localRef = '')
    {
        if (!is_array($snapshot)) {
            $snapshot = array('snapshot' => (string) $snapshot);
        }

        return TakeposSyncService::enqueue(
            $db,
            $user,
            TakeposSyncService::ACTION_CART_SNAPSHOT,
            $snapshot,
            $localRef
        );
    }

    public static function state($db, $user)
    {
        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        return array(
            'offline_mode' => (self::isOfflineModeSession() ? 1 : 0),
            'can_use_offline' => (self::canUseOffline($db, $user) ? 1 : 0),
            'sync_summary' => TakeposSyncService::summary($db, $entity),
        );
    }
}
