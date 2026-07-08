<?php
require_once __DIR__ . '/TakeposAudit.class.php';
require_once __DIR__ . '/TakeposInputValidator.class.php';
require_once __DIR__ . '/TakeposUserAccess.class.php';

if (defined('DOL_DOCUMENT_ROOT')) {
    require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
    require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
}

/**
 * Central manager override workflow service.
 *
 * Security notes:
 * - Override approvals are single-use and short-lived.
 * - Approval context is bound to action + invoice + line + cashier.
 * - Any validation/runtime failure returns deny.
 */
class TakeposManagerOverrideService
{
    const OVERRIDE_TTL_SECONDS = 300;

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

    public static function actionMeta($actionType)
    {
        $map = array(
            'delete_line' => array(
                'permission' => 'takepos.action.line_delete',
                'override_permission' => 'takepos.override.line_delete',
                'feature' => 'takepos.line_delete',
                'label' => 'line deletion',
            ),
            'price_override' => array(
                'permission' => 'takepos.action.price_override',
                'override_permission' => 'takepos.override.price',
                'feature' => 'takepos.price_override',
                'label' => 'price override',
            ),
            'discount' => array(
                'permission' => 'takepos.action.discount',
                'override_permission' => 'takepos.override.discount',
                'feature' => 'takepos.discount',
                'label' => 'discount override',
            ),
            'invoice_cancel' => array(
                'permission' => 'takepos.action.invoice_cancel',
                'override_permission' => 'takepos.override.cancel',
                'feature' => 'takepos.invoice_cancel',
                'label' => 'invoice cancelation',
            ),
            'refund_full' => array(
                'permission' => 'takepos.refund.full',
                'override_permission' => 'takepos.refund.approve',
                'feature' => 'takepos.refunds',
                'label' => 'full refund approval',
            ),
            'refund_partial' => array(
                'permission' => 'takepos.refund.partial',
                'override_permission' => 'takepos.refund.approve',
                'feature' => 'takepos.refunds',
                'label' => 'partial refund approval',
            ),
            'refund_without_original' => array(
                'permission' => 'takepos.refund.without_original',
                'override_permission' => 'takepos.refund.approve',
                'feature' => 'takepos.refunds',
                'label' => 'refund without original approval',
            ),
            'exchange_process' => array(
                'permission' => 'takepos.exchange.process',
                'override_permission' => 'takepos.refund.approve',
                'feature' => 'takepos.exchanges',
                'label' => 'exchange approval',
            ),
        );

        $actionType = trim((string) $actionType);
        return isset($map[$actionType]) ? $map[$actionType] : array();
    }

    public static function denyReasonAllowsOverride($denyReason)
    {
        return in_array((string) $denyReason, array(
            'permission_denied',
            'ordered_line_denied',
            'price_limit_exceeded',
            'discount_percent_limit_exceeded',
            'discount_amount_limit_exceeded',
        ), true);
    }

    public static function isFeatureEnabled($db)
    {
        try {
            return TakeposAccess::isFeatureEnabledForCurrentEntity($db, 'takepos.manager_override');
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function safeAudit($db, $user, $eventType, $severity, $data = array(), $description = '', $objectType = '', $objectId = 0, $amountTtc = null)
    {
        try {
            TakeposAudit::logEvent($db, $user, $eventType, $severity, $data, $description, $objectType, $objectId, $amountTtc);
        } catch (Throwable $e) {
            if (function_exists('dol_syslog')) {
                dol_syslog('[TakePOS][ManagerOverride] Audit failure: ' . $e->getMessage(), LOG_WARNING);
            }
        }
    }

    public static function resolveTargetLineId($db, $invoiceId, $preferredLine)
    {
        $invoiceId = (int) $invoiceId;
        $preferredLine = (int) $preferredLine;
        if ($invoiceId <= 0) {
            return 0;
        }
        if ($preferredLine > 0) {
            return $preferredLine;
        }

        $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "facturedet";
        $sql .= " WHERE fk_facture = " . $invoiceId;
        $sql .= " ORDER BY rowid DESC";
        $sql .= " LIMIT 1";
        $resql = $db->query($sql);
        if ($resql) {
            $obj = $db->fetch_object($resql);
            if ($obj) {
                return (int) $obj->rowid;
            }
        }

        return 0;
    }

    public static function findInvoiceLineById($invoice, $lineId)
    {
        $lineId = (int) $lineId;
        if ($lineId <= 0 || empty($invoice->lines) || !is_array($invoice->lines)) {
            return null;
        }

        foreach ($invoice->lines as $line) {
            if ((int) $line->id === $lineId || (isset($line->rowid) && (int) $line->rowid === $lineId)) {
                return $line;
            }
        }

        return null;
    }

    public static function findManagerByBarcode($db, $barcode)
    {
        $barcode = trim((string) $barcode);
        if ($barcode === '') {
            return null;
        }

        $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "user";
        $sql .= " WHERE entity IN (" . getEntity('user') . ")";
        $sql .= " AND statut = 1";
        $sql .= " ORDER BY rowid ASC";
        $resql = $db->query($sql);
        if (!$resql) {
            return null;
        }

        while ($obj = $db->fetch_object($resql)) {
            $tmpUser = new User($db);
            if ($tmpUser->fetch((int) $obj->rowid) <= 0) {
                continue;
            }

            if (!empty($tmpUser->login) && hash_equals((string) $tmpUser->login, $barcode)) {
                return $tmpUser;
            }

            if (isset($tmpUser->barcode) && $tmpUser->barcode !== '' && hash_equals((string) $tmpUser->barcode, $barcode)) {
                return $tmpUser;
            }

            if (method_exists($tmpUser, 'fetch_optionals')) {
                $tmpUser->fetch_optionals();
                $possibleKeys = array('options_barcode', 'options_card', 'options_cardcode', 'options_manager_card');
                foreach ($possibleKeys as $key) {
                    if (!empty($tmpUser->array_options[$key]) && hash_equals((string) $tmpUser->array_options[$key], $barcode)) {
                        return $tmpUser;
                    }
                }
            }
        }

        return null;
    }

    public static function findManagerByLogin($db, $login)
    {
        $login = trim((string) $login);
        if ($login === '') {
            return null;
        }

        $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "user";
        $sql .= " WHERE entity IN (" . getEntity('user') . ")";
        $sql .= " AND statut = 1";
        $sql .= " AND login = '" . $db->escape($login) . "'";
        $sql .= " LIMIT 1";
        $resql = $db->query($sql);
        if (!$resql) {
            return null;
        }
        $obj = $db->fetch_object($resql);
        if (!$obj) {
            return null;
        }

        $tmpUser = new User($db);
        if ($tmpUser->fetch((int) $obj->rowid) > 0) {
            return $tmpUser;
        }

        return null;
    }

    public static function validateManagerPassword($managerUser, $password)
    {
        $password = (string) $password;
        if ($password === '' || empty($managerUser->login)) {
            return false;
        }

        $login = (string) $managerUser->login;
        $entity = isset($managerUser->entity) ? (int) $managerUser->entity : 0;

        if (function_exists('checkLoginPassEntity')) {
            $calls = array(
                function () use ($login, $password, $entity) { return checkLoginPassEntity($login, $password, $entity, 1); },
                function () use ($login, $password, $entity) { return checkLoginPassEntity($login, $password, $entity); },
                function () use ($login, $password) { return checkLoginPassEntity($login, $password); },
            );
            foreach ($calls as $call) {
                try {
                    $res = $call();
                    if ((int) $res > 0) {
                        return true;
                    }
                } catch (Throwable $e) {
                }
            }
        }

        $hashCandidates = array(
            isset($managerUser->pass_indatabase_crypted) ? (string) $managerUser->pass_indatabase_crypted : '',
            isset($managerUser->pass_crypted) ? (string) $managerUser->pass_crypted : '',
            isset($managerUser->pass_indatabase) ? (string) $managerUser->pass_indatabase : '',
            isset($managerUser->pass) ? (string) $managerUser->pass : '',
        );
        foreach ($hashCandidates as $hash) {
            if ($hash === '') {
                continue;
            }
            if (function_exists('dol_verifyHash')) {
                try {
                    if (dol_verifyHash($password, $hash, 'auto')) {
                        return true;
                    }
                } catch (Throwable $e) {
                }
            }
            if (function_exists('password_verify') && password_verify($password, $hash)) {
                return true;
            }
        }

        return false;
    }

    public static function managerCanApproveForAction($db, $managerUser, $actionType, $line = null)
    {
        if (empty($managerUser) || empty($managerUser->id)) {
            return false;
        }
        if (!self::isFeatureEnabled($db)) {
            return false;
        }

        $meta = self::actionMeta($actionType);
        if (empty($meta)) {
            return false;
        }

        if (!empty($managerUser->admin)) {
            return true;
        }

        $hasPermission = TakeposUserAccess::userHasPermission($db, $managerUser, $meta['override_permission']);
        if (!$hasPermission) {
            return false;
        }

        if ($actionType === 'delete_line' && is_object($line) && isset($line->special_code) && (string) $line->special_code === '4' && !$managerUser->hasRight('takepos', 'editorderedlines')) {
            return false;
        }

        return true;
    }

    private static function overrideTableExists($db)
    {
        return TakeposUserAccess::tableExists($db, 'takepos_override_session');
    }

    public static function storeSession($db, $override)
    {
        $now = dol_now();
        $token = '';
        try {
            $token = bin2hex(random_bytes(16));
        } catch (Throwable $e) {
            $token = sha1(uniqid('', true));
        }

        $record = array(
            'authorized' => 1,
            'token' => $token,
            'manager_id' => (int) $override['manager_id'],
            'action' => (string) $override['action'],
            'invoice_id' => (int) $override['invoice_id'],
            'line_id' => (int) $override['line_id'],
            'cashier_id' => (int) $override['cashier_id'],
            'requested_number' => ($override['requested_number'] === '' || $override['requested_number'] === null ? null : (float) $override['requested_number']),
            'created_at' => $now,
            'expires_at' => $now + self::OVERRIDE_TTL_SECONDS,
        );

        $_SESSION['takepos_manager_override'] = $record;

        if (self::overrideTableExists($db)) {
            global $conf;
            $sql = "INSERT INTO " . MAIN_DB_PREFIX . "takepos_override_session (entity, session_token, action_code, fk_invoice, fk_line, fk_cashier, fk_manager, requested_number, date_approved, date_expires, used) VALUES (";
            $sql .= ((int) $conf->entity) . ", '" . $db->escape($token) . "', '" . $db->escape($record['action']) . "', " . ((int) $record['invoice_id']) . ", " . ((int) $record['line_id'] > 0 ? (int) $record['line_id'] : 'NULL') . ", " . ((int) $record['cashier_id']) . ", " . ((int) $record['manager_id']) . ", " . ($record['requested_number'] === null ? 'NULL' : (float) $record['requested_number']) . ", '" . $db->idate($now) . "', '" . $db->idate($record['expires_at']) . "', 0)";
            $db->query($sql);
        }

        return $record;
    }

    public static function consumeSession($db = null, $usedReason = 'consumed')
    {
        $token = '';
        if (!empty($_SESSION['takepos_manager_override']) && is_array($_SESSION['takepos_manager_override']) && !empty($_SESSION['takepos_manager_override']['token'])) {
            $token = (string) $_SESSION['takepos_manager_override']['token'];
        }

        if ($db && $token !== '' && self::overrideTableExists($db)) {
            $sql = "UPDATE " . MAIN_DB_PREFIX . "takepos_override_session";
            $sql .= " SET used = 1, date_used = '" . $db->idate(dol_now()) . "', used_reason = '" . $db->escape($usedReason) . "'";
            $sql .= " WHERE session_token = '" . $db->escape($token) . "' AND used = 0";
            $db->query($sql);
        }

        unset($_SESSION['takepos_manager_override']);
    }

    public static function hasValidSessionForAction($db, $actionType, $invoiceId, $lineId, $cashierId, $requestedNumber = null, $consume = false, &$overrideData = null)
    {
        if (empty($_SESSION['takepos_manager_override']) || !is_array($_SESSION['takepos_manager_override'])) {
            return false;
        }

        $override = $_SESSION['takepos_manager_override'];
        $now = dol_now();

        if (empty($override['authorized'])) {
            return false;
        }
        if (empty($override['action']) || (string) $override['action'] !== (string) $actionType) {
            return false;
        }
        if (empty($override['invoice_id']) || (int) $override['invoice_id'] !== (int) $invoiceId) {
            return false;
        }
        if ((int) $lineId > 0 && (int) $override['line_id'] !== (int) $lineId) {
            return false;
        }
        if (empty($override['cashier_id']) || (int) $override['cashier_id'] !== (int) $cashierId) {
            return false;
        }
        if (empty($override['created_at']) || (int) $override['created_at'] <= 0) {
            return false;
        }
        if (empty($override['expires_at']) || (int) $override['expires_at'] < $now) {
            return false;
        }

        if ($requestedNumber !== null && in_array((string) $actionType, array('price_override', 'discount'), true)) {
            if (!array_key_exists('requested_number', $override) || $override['requested_number'] === null) {
                return false;
            }
            if (abs((float) $override['requested_number'] - (float) $requestedNumber) > 0.00001) {
                return false;
            }
        }

        if (self::overrideTableExists($db) && !empty($override['token'])) {
            $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "takepos_override_session";
            $sql .= " WHERE session_token = '" . $db->escape((string) $override['token']) . "'";
            $sql .= " AND action_code = '" . $db->escape((string) $actionType) . "'";
            $sql .= " AND fk_invoice = " . ((int) $invoiceId);
            if ((int) $lineId > 0) {
                $sql .= " AND fk_line = " . ((int) $lineId);
            }
            $sql .= " AND fk_cashier = " . ((int) $cashierId);
            $sql .= " AND used = 0";
            $sql .= " AND date_expires >= '" . $db->idate($now) . "'";
            $sql .= " LIMIT 1";
            $resql = $db->query($sql);
            if (!$resql || !$db->fetch_object($resql)) {
                return false;
            }
        }

        $overrideData = $override;
        if ($consume) {
            self::consumeSession($db, 'action_used');
        }

        return true;
    }

    /**
     * Handle manager approval payload and return normalized response data.
     *
     * @return array{success:bool,message:string,http_code:int,data:array}
     */
    public static function approveFromPayload($db, $cashierUser, $payload = array())
    {
        $barcode = trim((string) (isset($payload['manager_barcode']) ? $payload['manager_barcode'] : ''));
        $managerLogin = trim((string) (isset($payload['manager_login']) ? $payload['manager_login'] : ''));
        $managerPassword = (string) (isset($payload['manager_password']) ? $payload['manager_password'] : '');
        $overrideAction = trim((string) (isset($payload['override_action']) ? $payload['override_action'] : ''));
        if ($overrideAction === '') {
            $overrideAction = 'delete_line';
        }
        $overrideInvoiceId = isset($payload['invoice_id']) ? (int) $payload['invoice_id'] : 0;
        $overrideLineId = isset($payload['line_id']) ? (int) $payload['line_id'] : 0;
        $requestedNumberRaw = isset($payload['requested_number']) ? (string) $payload['requested_number'] : '';

        if (!self::isFeatureEnabled($db)) {
            return array('success' => false, 'message' => self::trans('TakeposManagerOverrideFeatureDisabled', 'Manager override feature is disabled.'), 'http_code' => 403, 'data' => array('reason' => 'feature_disabled'));
        }

        $requestedNumber = null;
        if ($requestedNumberRaw !== '') {
            $parsedRequestedNumber = null;
            if (!TakeposInputValidator::parseDecimal($requestedNumberRaw, $parsedRequestedNumber, true, 8)) {
                self::safeAudit($db, $cashierUser, 'manager_override_rejected', TakeposAudit::SEVERITY_WARNING, array('override_action' => $overrideAction, 'invoice_id' => $overrideInvoiceId, 'reason' => 'invalid_requested_number', 'requested_number_raw' => $requestedNumberRaw), 'Invalid numeric payload for manager override');
                return array('success' => false, 'message' => self::trans('TakeposManagerOverrideInvalidNumber', 'Invalid numeric value for requested amount/percent.'), 'http_code' => 422, 'data' => array('reason' => 'invalid_requested_number'));
            }
            $requestedNumber = (float) $parsedRequestedNumber;
        }

        self::safeAudit($db, $cashierUser, 'manager_override_requested', TakeposAudit::SEVERITY_WARNING, array('override_action' => $overrideAction, 'invoice_id' => $overrideInvoiceId, 'line_id' => $overrideLineId, 'requested_number' => $requestedNumber), 'Manager override requested');

        $meta = self::actionMeta($overrideAction);
        if (empty($meta)) {
            self::safeAudit($db, $cashierUser, 'manager_override_rejected', TakeposAudit::SEVERITY_WARNING, array('override_action' => $overrideAction), 'Unsupported override action');
            return array('success' => false, 'message' => self::trans('TakeposManagerOverrideUnsupportedAction', 'Unsupported override action.'), 'http_code' => 422, 'data' => array('reason' => 'unsupported_action'));
        }

        if ($overrideInvoiceId <= 0) {
            self::safeAudit($db, $cashierUser, 'manager_override_rejected', TakeposAudit::SEVERITY_WARNING, array('override_action' => $overrideAction, 'invoice_id' => $overrideInvoiceId), 'Invalid invoice context');
            return array('success' => false, 'message' => self::trans('TakeposManagerOverrideInvoiceNotFound', 'Invoice not found for manager approval.'), 'http_code' => 404, 'data' => array('reason' => 'invalid_invoice'));
        }

        $invoiceForOverride = new Facture($db);
        if ($invoiceForOverride->fetch($overrideInvoiceId) <= 0) {
            self::safeAudit($db, $cashierUser, 'manager_override_rejected', TakeposAudit::SEVERITY_WARNING, array('override_action' => $overrideAction, 'invoice_id' => $overrideInvoiceId), 'Invoice fetch failed for manager override');
            return array('success' => false, 'message' => self::trans('TakeposManagerOverrideInvoiceNotFound', 'Invoice not found for manager approval.'), 'http_code' => 404, 'data' => array('reason' => 'invoice_missing'));
        }

        $overrideLine = null;
        if (in_array($overrideAction, array('delete_line', 'price_override', 'discount'), true)) {
            $overrideLineId = self::resolveTargetLineId($db, $overrideInvoiceId, $overrideLineId);
            if ($overrideLineId <= 0) {
                self::safeAudit($db, $cashierUser, 'manager_override_rejected', TakeposAudit::SEVERITY_WARNING, array('override_action' => $overrideAction, 'invoice_id' => $overrideInvoiceId), 'Invoice line missing for override');
                return array('success' => false, 'message' => self::trans('TakeposManagerOverrideLineMissing', 'No invoice line found to approve.'), 'http_code' => 404, 'data' => array('reason' => 'line_missing'));
            }
            $overrideLine = self::findInvoiceLineById($invoiceForOverride, $overrideLineId);
        }

        $managerUser = null;
        if ($barcode !== '') {
            $managerUser = self::findManagerByBarcode($db, $barcode);
        }
        if (!$managerUser && $managerLogin !== '' && $managerPassword !== '') {
            $managerUser = self::findManagerByLogin($db, $managerLogin);
            if ($managerUser && !self::validateManagerPassword($managerUser, $managerPassword)) {
                $managerUser = null;
            }
        }

        if (!$managerUser) {
            self::safeAudit($db, $cashierUser, 'manager_override_rejected', TakeposAudit::SEVERITY_WARNING, array('override_action' => $overrideAction, 'invoice_id' => $overrideInvoiceId), 'Invalid manager credentials');
            return array('success' => false, 'message' => self::trans('TakeposManagerOverrideInvalidCredentials', 'Invalid manager credentials.'), 'http_code' => 403, 'data' => array('reason' => 'invalid_credentials'));
        }

        if ((int) $managerUser->id === (int) $cashierUser->id) {
            self::safeAudit($db, $cashierUser, 'manager_override_rejected', TakeposAudit::SEVERITY_WARNING, array('override_action' => $overrideAction, 'invoice_id' => $overrideInvoiceId, 'manager_id' => (int) $managerUser->id), 'Self-approval is not allowed');
            return array('success' => false, 'message' => self::trans('TakeposManagerOverrideSelfApprovalDenied', 'Manager approval must come from a different user.'), 'http_code' => 403, 'data' => array('reason' => 'self_approval_denied'));
        }

        if (!self::managerCanApproveForAction($db, $managerUser, $overrideAction, $overrideLine)) {
            self::safeAudit($db, $cashierUser, 'manager_override_rejected', TakeposAudit::SEVERITY_WARNING, array('override_action' => $overrideAction, 'invoice_id' => $overrideInvoiceId, 'manager_id' => (int) $managerUser->id), 'Manager does not have permission to approve this action');
            return array('success' => false, 'message' => self::trans('TakeposManagerOverridePermissionDenied', 'Manager does not have permission to approve this action.'), 'http_code' => 403, 'data' => array('reason' => 'manager_permission_denied'));
        }

        $stored = self::storeSession($db, array(
            'action' => $overrideAction,
            'invoice_id' => (int) $overrideInvoiceId,
            'line_id' => (int) $overrideLineId,
            'cashier_id' => (int) $cashierUser->id,
            'manager_id' => (int) $managerUser->id,
            'requested_number' => $requestedNumber,
        ));

        self::safeAudit($db, $cashierUser, 'manager_override_approved', TakeposAudit::SEVERITY_INFO, array('override_action' => $overrideAction, 'invoice_id' => $overrideInvoiceId, 'line_id' => (int) $overrideLineId, 'manager_id' => (int) $managerUser->id, 'requested_number' => $requestedNumber), 'Manager override approved');

        return array(
            'success' => true,
            'message' => self::trans('TakeposManagerOverrideGranted', 'Manager approval granted.'),
            'http_code' => 200,
            'data' => array(
                'override_action' => $overrideAction,
                'invoice_id' => (int) $overrideInvoiceId,
                'line_id' => (int) $overrideLineId,
                'manager_id' => (int) $managerUser->id,
                'token' => isset($stored['token']) ? $stored['token'] : '',
            ),
        );
    }
}

