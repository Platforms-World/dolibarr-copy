<?php
/**
 * Access and guard helpers for TakePOS internal user management.
 */
class TakeposUserAccess
{
    const ROLE_CASHIER = 'cashier';
    const ROLE_SUPERVISOR = 'supervisor';
    const ROLE_MANAGER = 'manager';

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

    public static function tableExists($db, $suffix)
    {
        static $cache = array();

        if (!is_object($db)) {
            return false;
        }

        $cacheKey = spl_object_hash($db) . '|' . strtolower((string) $suffix);
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $table = MAIN_DB_PREFIX . $suffix;
        $resql = $db->query("SHOW TABLES LIKE '" . $db->escape($table) . "'");
        $cache[$cacheKey] = (bool) ($resql && $db->num_rows($resql) > 0);

        return $cache[$cacheKey];
    }

    public static function getPosPermissionCodes()
    {
        return array(
            'takepos.use',
            'takepos.admin',
            'takepos.action.line_delete',
            'takepos.action.price_override',
            'takepos.action.discount',
            'takepos.action.invoice_cancel',
            'takepos.action.reports_view',
            'takepos.action.users_manage',
            'takepos.refund.full',
            'takepos.refund.partial',
            'takepos.refund.without_original',
            'takepos.refund.approve',
            'takepos.refund.restock_control',
            'takepos.exchange.process',
            'takepos.refund.view',
            'takepos.refund.export',
            'takepos.analytics.view',
            'takepos.analytics.export',
            'takepos.customer.view',
            'takepos.loyalty.view',
            'takepos.loyalty.earn',
            'takepos.loyalty.redeem',
            'takepos.loyalty.adjust',
            'takepos.offline.use',
            'takepos.sync.manage',
            'takepos.sync.retry',
            'takepos.sync.resolve_conflict',
            'takepos.device.manage',
            'takepos.device.test',
            'takepos.api.read',
            'takepos.api.write',
            'takepos.webhook.manage',
            'takepos.shift.open',
            'takepos.shift.close',
            'takepos.shift.force_close',
            'takepos.shift.review',
            'takepos.cash.paidin',
            'takepos.cash.paidout',
            'takepos.cash.safedrop',
            'takepos.cash.count',
            'takepos.cash.reconcile',
            'takepos.cash.override_difference',
            'takepos.expense.read',
            'takepos.expense.create',
            'takepos.expense.post',
            'takepos.expense.admin',
            'takepos.purchase.read',
            'takepos.purchase.create',
            'takepos.cheque.read',
            'takepos.cheque.create',
            'takepos.cheque.status_update',
            'takepos.cheque.print',
            'takepos.store.manage',
            'takepos.terminal.manage',
            'takepos.terminal.assign',
            'takepos.store.view_all',
            'takepos.override.line_delete',
            'takepos.override.price',
            'takepos.override.discount',
            'takepos.override.cancel',
        );
    }

    public static function normalizeFeatureCode($featureCode)
    {
        $code = strtolower(trim((string) $featureCode));
        if ($code === '') {
            return '';
        }

        $map = array(
            'takepos.discount' => 'discounts',
            'takepos.discounts' => 'discounts',
            'takepos.price_override' => 'price_override',
            'takepos.line_delete' => 'line_delete',
            'takepos.invoice_cancel' => 'invoice_cancel',
            'takepos.split' => 'split_bill',
            'takepos.customer_display' => 'customer_display',
            'takepos.reports' => 'reports',
            'takepos.admin.users' => 'admin_users',
            'takepos.users.manage' => 'admin_users',
            'takepos.terminal_management' => 'terminal_management',
            'takepos.admin.terminal' => 'terminal_management',
            'takepos.manager_override' => 'manager_override',
            'takepos.frontend' => 'frontend',
            'takepos.payment' => 'payment',
            'takepos.freezone' => 'freezone',
            'takepos.receipt' => 'receipt',
            'takepos.send' => 'send',
            'takepos.public_menu' => 'public_menu',
            'takepos.auto_order' => 'auto_order',
            'takepos.shift_management' => 'shift_management',
            'takepos.cash_control' => 'cash_control',
            'takepos.store_governance' => 'store_governance',
            'takepos.terminal_governance' => 'terminal_governance',
            'takepos.audit.log' => 'audit_log',
            'takepos.dashboard.view' => 'dashboard_view',
            'takepos.returns' => 'returns',
            'takepos.refunds' => 'refunds',
            'takepos.exchanges' => 'exchanges',
            'takepos.analytics' => 'analytics',
            'takepos.kpi_dashboard' => 'kpi_dashboard',
            'takepos.crm' => 'crm',
            'takepos.loyalty' => 'loyalty',
            'takepos.offline_mode' => 'offline_mode',
            'takepos.sync_queue' => 'sync_queue',
            'takepos.device_layer' => 'device_layer',
            'takepos.printer_profiles' => 'printer_profiles',
            'takepos.customer_display_profiles' => 'customer_display_profiles',
            'takepos.api_layer' => 'api_layer',
            'takepos.webhooks' => 'webhooks',
            'takepos.purchases' => 'purchases',
            'takepos.purchase' => 'purchases',
            'takepos.cheques' => 'cheques',
            'takepos.cheque' => 'cheques',
        );

        return isset($map[$code]) ? $map[$code] : $code;
    }

    public static function getCanonicalFeatureMap()
    {
        return array(
            'frontend' => 'takepos.frontend',
            'payment' => 'takepos.payment',
            'discounts' => 'takepos.discount',
            'price_override' => 'takepos.price_override',
            'line_delete' => 'takepos.line_delete',
            'invoice_cancel' => 'takepos.invoice_cancel',
            'split_bill' => 'takepos.split',
            'customer_display' => 'takepos.customer_display',
            'reports' => 'takepos.reports',
            'admin_users' => 'takepos.users.manage',
            'terminal_management' => 'takepos.admin.terminal',
            'manager_override' => 'takepos.manager_override',
            'freezone' => 'takepos.freezone',
            'receipt' => 'takepos.receipt',
            'send' => 'takepos.send',
            'public_menu' => 'takepos.public_menu',
            'auto_order' => 'takepos.auto_order',
            'shift_management' => 'takepos.shift_management',
            'cash_control' => 'takepos.cash_control',
            'store_governance' => 'takepos.store_governance',
            'terminal_governance' => 'takepos.terminal_governance',
            'audit_log' => 'takepos.audit.log',
            'dashboard_view' => 'takepos.dashboard.view',
            'returns' => 'takepos.returns',
            'refunds' => 'takepos.refunds',
            'exchanges' => 'takepos.exchanges',
            'analytics' => 'takepos.analytics',
            'kpi_dashboard' => 'takepos.kpi_dashboard',
            'crm' => 'takepos.crm',
            'loyalty' => 'takepos.loyalty',
            'offline_mode' => 'takepos.offline_mode',
            'sync_queue' => 'takepos.sync_queue',
            'device_layer' => 'takepos.device_layer',
            'printer_profiles' => 'takepos.printer_profiles',
            'customer_display_profiles' => 'takepos.customer_display_profiles',
            'api_layer' => 'takepos.api_layer',
            'webhooks' => 'takepos.webhooks',
            'purchases' => 'takepos.purchases',
            'cheques' => 'takepos.cheques',
        );
    }

    public static function toPhysicalFeatureCode($featureCode)
    {
        $normalized = self::normalizeFeatureCode($featureCode);
        $map = self::getCanonicalFeatureMap();
        return isset($map[$normalized]) ? $map[$normalized] : (string) $featureCode;
    }

    public static function getGlobalValue($db, $name, $entity = null)
    {
        global $conf;
        if (isset($conf->global->{$name}) && $conf->global->{$name} !== '') {
            return $conf->global->{$name};
        }

        if (!self::tableExists($db, 'const')) {
            return null;
        }

        $sql = "SELECT value FROM " . MAIN_DB_PREFIX . "const WHERE name='" . $db->escape($name) . "'";
        if ($entity !== null) {
            $sql .= " AND entity IN (0, " . ((int) $entity) . ") ORDER BY entity DESC";
        } else {
            $sql .= " ORDER BY entity DESC";
        }
        $sql .= " LIMIT 1";
        $resql = $db->query($sql);
        if ($resql && ($obj = $db->fetch_object($resql))) {
            return $obj->value;
        }

        return null;
    }

    protected static function boolFromValue($value, $default = false)
    {
        if ($value === null || $value === '') {
            return (bool) $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $text = strtolower(trim((string) $value));
        if ($text === '') {
            return (bool) $default;
        }

        if (in_array($text, array('1', 'true', 'yes', 'on'), true)) {
            return true;
        }

        if (in_array($text, array('0', 'false', 'no', 'off'), true)) {
            return false;
        }

        return ((int) $value > 0);
    }

    protected static function dbCacheKey($db)
    {
        return (is_object($db) ? spl_object_hash($db) : 'no-db');
    }

    public static function kafoerpcontrolEnabled()
    {
        global $conf;

        if (function_exists('isModEnabled') && isModEnabled('kafoerpcontrol')) {
            return true;
        }

        return !empty($conf->global->MAIN_MODULE_KAFOERPCONTROL);
    }

    protected static function failClosed($db)
    {
        static $cache = array();

        $cacheKey = self::dbCacheKey($db);
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $default = self::kafoerpcontrolEnabled();
        $value = self::getGlobalValue($db, 'KAFOERP_FAIL_CLOSED');
        if ($value === null || $value === '') {
            $legacyFailClosedConst = strtoupper(implode('', array('saas', 'core'))) . '_FAIL_CLOSED';
            $value = self::getGlobalValue($db, $legacyFailClosedConst);
        }
        $cache[$cacheKey] = self::boolFromValue($value, $default);

        return $cache[$cacheKey];
    }

    protected static function resolveTenantToggleDecision($db, $entity, $tableSuffix, $fieldName, $code)
    {
        static $cache = array();

        $entity = ((int) $entity > 0 ? (int) $entity : 1);
        $code = strtolower(trim((string) $code));
        if ($code === '') {
            return array('resolved' => true, 'enabled' => false, 'source' => 'invalid');
        }

        $cacheKey = self::dbCacheKey($db) . '|' . $entity . '|' . $tableSuffix . '|' . $fieldName . '|' . $code;
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        if (!self::kafoerpcontrolEnabled()) {
            $cache[$cacheKey] = array('resolved' => true, 'enabled' => true, 'source' => 'kafo_inactive');
            return $cache[$cacheKey];
        }

        $default = !self::failClosed($db);
        if (!is_object($db) || !self::tableExists($db, $tableSuffix)) {
            $cache[$cacheKey] = array('resolved' => true, 'enabled' => $default, 'source' => 'schema_missing');
            return $cache[$cacheKey];
        }

        $sql = "SELECT enabled";
        $sql .= " FROM " . MAIN_DB_PREFIX . $tableSuffix;
        $sql .= " WHERE entity_id IN (0, " . $entity . ")";
        $sql .= " AND " . $fieldName . " = '" . $db->escape($code) . "'";
        $sql .= " ORDER BY entity_id DESC";
        $sql .= " LIMIT 1";

        $resql = $db->query($sql);
        if ($resql && ($obj = $db->fetch_object($resql))) {
            $cache[$cacheKey] = array(
                'resolved' => true,
                'enabled' => ((int) $obj->enabled === 1),
                'source' => 'tenant_toggle',
            );
            return $cache[$cacheKey];
        }

        $cache[$cacheKey] = array('resolved' => true, 'enabled' => $default, 'source' => 'default');
        return $cache[$cacheKey];
    }

    protected static function resolvePermissionDecision($db, $entity, $userId, $permissionCode)
    {
        static $cache = array();

        $entity = ((int) $entity > 0 ? (int) $entity : 1);
        $userId = (int) $userId;
        $permissionCode = strtolower(trim((string) $permissionCode));

        if ($userId <= 0 || $permissionCode === '') {
            return array('resolved' => true, 'allowed' => false, 'source' => 'invalid');
        }

        $cacheKey = self::dbCacheKey($db) . '|' . $entity . '|' . $userId . '|' . $permissionCode;
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        if (!self::kafoerpcontrolEnabled()) {
            $cache[$cacheKey] = array('resolved' => false, 'allowed' => false, 'source' => 'kafo_inactive');
            return $cache[$cacheKey];
        }

        $default = !self::failClosed($db);
        if (!is_object($db)) {
            $cache[$cacheKey] = array('resolved' => true, 'allowed' => $default, 'source' => 'no_db');
            return $cache[$cacheKey];
        }

        if (self::tableExists($db, 'saas_user_permissions')) {
            $sql = "SELECT allowed";
            $sql .= " FROM " . MAIN_DB_PREFIX . "saas_user_permissions";
            $sql .= " WHERE entity_id IN (0, " . $entity . ")";
            $sql .= " AND fk_user = " . $userId;
            $sql .= " AND permission_code = '" . $db->escape($permissionCode) . "'";
            $sql .= " ORDER BY entity_id DESC";
            $sql .= " LIMIT 1";

            $resql = $db->query($sql);
            if ($resql && ($obj = $db->fetch_object($resql))) {
                $isAllowed = ((int) $obj->allowed === 1);
                $cache[$cacheKey] = array(
                    'resolved' => true,
                    'allowed' => $isAllowed,
                    'source' => ($isAllowed ? 'direct_allow' : 'direct_deny'),
                );
                return $cache[$cacheKey];
            }
        }

        if (self::tableExists($db, 'saas_user_roles') && self::tableExists($db, 'saas_role_permissions')) {
            $sql = "SELECT";
            $sql .= " SUM(CASE WHEN rp.allowed = 1 THEN 1 ELSE 0 END) AS allow_count,";
            $sql .= " SUM(CASE WHEN rp.allowed = 0 THEN 1 ELSE 0 END) AS deny_count";
            $sql .= " FROM " . MAIN_DB_PREFIX . "saas_user_roles AS ur";
            $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "saas_role_permissions AS rp";
            $sql .= " ON rp.entity_id = ur.entity_id";
            $sql .= " AND rp.role_code = ur.role_code";
            $sql .= " WHERE ur.entity_id IN (0, " . $entity . ")";
            $sql .= " AND ur.fk_user = " . $userId;
            $sql .= " AND rp.permission_code = '" . $db->escape($permissionCode) . "'";

            $resql = $db->query($sql);
            if ($resql && ($obj = $db->fetch_object($resql))) {
                $allowCount = (int) $obj->allow_count;
                $denyCount = (int) $obj->deny_count;

                if ($denyCount > 0) {
                    $cache[$cacheKey] = array('resolved' => true, 'allowed' => false, 'source' => 'role_deny');
                    return $cache[$cacheKey];
                }

                if ($allowCount > 0) {
                    $cache[$cacheKey] = array('resolved' => true, 'allowed' => true, 'source' => 'role_allow');
                    return $cache[$cacheKey];
                }
            }
        }

        $cache[$cacheKey] = array(
            'resolved' => true,
            'allowed' => $default,
            'source' => ($default ? 'default_allow' : 'default_deny'),
        );
        return $cache[$cacheKey];
    }

    public static function moduleEnabledForEntity($db, $entity, $moduleCode = 'takepos')
    {
        $moduleCode = strtolower(trim((string) $moduleCode));
        if ($moduleCode === '') {
            $moduleCode = 'takepos';
        }

        $decision = self::resolveTenantToggleDecision($db, $entity, 'saas_tenant_modules', 'module_code', $moduleCode);
        return !empty($decision['enabled']);
    }

    public static function moduleEnabledForEntityStrict($db, $entity, $moduleCode = 'takepos')
    {
        return self::moduleEnabledForEntity($db, $entity, $moduleCode);
    }

    public static function featureEnabledForEntity($db, $entity, $featureCode)
    {
        $physicalCode = strtolower(trim((string) self::toPhysicalFeatureCode($featureCode)));
        if ($physicalCode === '') {
            return true;
        }

        $decision = self::resolveTenantToggleDecision($db, $entity, 'saas_tenant_features', 'feature_code', $physicalCode);
        return !empty($decision['enabled']);
    }

    public static function featureEnabledForEntityStrict($db, $entity, $featureCode)
    {
        return self::featureEnabledForEntity($db, $entity, $featureCode);
    }

    public static function mapPermissionToLegacyRight($permissionCode)
    {
        $permissionCode = strtolower(trim((string) $permissionCode));
        $map = array(
            'takepos.use' => array('takepos', 'run'),
            'takepos.admin' => array('takepos', 'config'),
            'takepos.action.line_delete' => array('takepos', 'editlines'),
            'takepos.action.price_override' => array('takepos', 'editlines'),
            'takepos.action.discount' => array('takepos', 'editlines'),
            'takepos.action.invoice_cancel' => array('takepos', 'editlines'),
            'takepos.action.reports_view' => array('takepos', 'run'),
            'takepos.action.users_manage' => array('takepos', 'config'),
            'takepos.refund.full' => array('takepos', 'editlines'),
            'takepos.refund.partial' => array('takepos', 'editlines'),
            'takepos.refund.without_original' => array('takepos', 'editlines'),
            'takepos.refund.approve' => array('takepos', 'editlines'),
            'takepos.refund.restock_control' => array('takepos', 'editlines'),
            'takepos.exchange.process' => array('takepos', 'editlines'),
            'takepos.refund.view' => array('takepos', 'run'),
            'takepos.refund.export' => array('takepos', 'run'),
            'takepos.analytics.view' => array('takepos', 'run'),
            'takepos.analytics.export' => array('takepos', 'run'),
            'takepos.dashboard.view' => array('takepos', 'run'),
            'takepos.customer.view' => array('takepos', 'run'),
            'takepos.loyalty.view' => array('takepos', 'run'),
            'takepos.loyalty.earn' => array('takepos', 'editlines'),
            'takepos.loyalty.redeem' => array('takepos', 'editlines'),
            'takepos.loyalty.adjust' => array('takepos', 'config'),
            'takepos.offline.use' => array('takepos', 'run'),
            'takepos.sync.manage' => array('takepos', 'run'),
            'takepos.sync.retry' => array('takepos', 'run'),
            'takepos.sync.resolve_conflict' => array('takepos', 'config'),
            'takepos.device.manage' => array('takepos', 'config'),
            'takepos.device.test' => array('takepos', 'run'),
            'takepos.api.read' => array('takepos', 'run'),
            'takepos.api.write' => array('takepos', 'config'),
            'takepos.webhook.manage' => array('takepos', 'config'),
            'takepos.shift.open' => array('takepos', 'run'),
            'takepos.shift.close' => array('takepos', 'run'),
            'takepos.shift.force_close' => array('takepos', 'config'),
            'takepos.shift.review' => array('takepos', 'run'),
            'takepos.cash.paidin' => array('takepos', 'run'),
            'takepos.cash.paidout' => array('takepos', 'run'),
            'takepos.cash.safedrop' => array('takepos', 'run'),
            'takepos.cash.count' => array('takepos', 'run'),
            'takepos.cash.reconcile' => array('takepos', 'run'),
            'takepos.cash.override_difference' => array('takepos', 'config'),
            'takepos.expense.read' => array('takepos', 'run'),
            'takepos.expense.create' => array('takepos', 'run'),
            'takepos.expense.post' => array('takepos', 'config'),
            'takepos.expense.admin' => array('takepos', 'config'),
            'takepos.catalog.manage_services' => array('service', 'creer'),
            'takepos.catalog.add_service' => array('service', 'creer'),
            'takepos.purchase.read' => array('produit', 'lire'),
            'takepos.purchase.create' => array('produit', 'creer'),
            'takepos.cheque.read' => array('produit', 'lire'),
            'takepos.cheque.create' => array('produit', 'creer'),
            'takepos.cheque.status_update' => array('produit', 'creer'),
            'takepos.cheque.print' => array('produit', 'lire'),
            'takepos.store.manage' => array('takepos', 'config'),
            'takepos.terminal.manage' => array('takepos', 'config'),
            'takepos.terminal.assign' => array('takepos', 'config'),
            'takepos.store.view_all' => array('takepos', 'config'),
            'takepos.override.line_delete' => array('takepos', 'editlines'),
            'takepos.override.price' => array('takepos', 'editlines'),
            'takepos.override.discount' => array('takepos', 'editlines'),
            'takepos.override.cancel' => array('takepos', 'editlines'),
        );

        return isset($map[$permissionCode]) ? $map[$permissionCode] : null;
    }

    public static function userHasPermission($db, $user, $permissionCode, $allowLegacyFallback = true)
    {
        if (empty($user) || empty($user->id)) {
            return false;
        }
        if (!empty($user->admin)) {
            return true;
        }

        $permissionCode = strtolower(trim((string) $permissionCode));
        if ($permissionCode === '') {
            return false;
        }

        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        $decision = self::resolvePermissionDecision($db, $entity, (int) $user->id, $permissionCode);
        if (!empty($decision['resolved']) && $decision['source'] !== 'kafo_inactive') {
            return !empty($decision['allowed']);
        }

        if (!$allowLegacyFallback) {
            return false;
        }

        $legacy = self::mapPermissionToLegacyRight($permissionCode);
        if (is_array($legacy) && count($legacy) === 2) {
            return !empty($user->rights->{$legacy[0]}->{$legacy[1]});
        }

        return false;
    }

    public static function userHasAnyPermission($db, $user, $permissionCodes, $allowLegacyFallback = true)
    {
        foreach ((array) $permissionCodes as $permissionCode) {
            if (self::userHasPermission($db, $user, $permissionCode, $allowLegacyFallback)) {
                return true;
            }
        }

        return false;
    }

    public static function listUserPermissionCodes($db, $userId, $entity = null)
    {
        $result = array();
        if (!self::tableExists($db, 'saas_user_permissions')) {
            return $result;
        }

        $table = MAIN_DB_PREFIX . 'saas_user_permissions';
        $cols = array();
        $resCols = $db->query("SHOW COLUMNS FROM " . $table);
        if ($resCols) {
            while ($obj = $db->fetch_object($resCols)) {
                $cols[] = strtolower((string) $obj->Field);
            }
        }
        if (empty($cols)) {
            return $result;
        }

        $userCol = in_array('fk_user', $cols, true) ? 'fk_user' : (in_array('user_id', $cols, true) ? 'user_id' : '');
        if ($userCol === '' || !in_array('permission_code', $cols, true)) {
            return $result;
        }

        $sql = "SELECT permission_code";
        if (in_array('allowed', $cols, true)) {
            $sql .= ", allowed";
        } else {
            $sql .= ", 1 as allowed";
        }
        $sql .= " FROM " . $table . " WHERE " . $userCol . " = " . ((int) $userId);
        if (in_array('entity_id', $cols, true)) {
            $entity = (int) $entity;
            if ($entity > 0) {
                $sql .= " AND entity_id IN (0, " . $entity . ")";
            }
        }

        $resql = $db->query($sql);
        if (!$resql) {
            return $result;
        }

        while ($obj = $db->fetch_object($resql)) {
            if ((int) $obj->allowed === 1) {
                $result[] = strtolower(trim((string) $obj->permission_code));
            }
        }

        return array_values(array_unique($result));
    }

    public static function saveUserPermissionCodes($db, $userId, $entity, $permissionCodes)
    {
        if (!self::tableExists($db, 'saas_user_permissions')) {
            return false;
        }

        $table = MAIN_DB_PREFIX . 'saas_user_permissions';
        $cols = array();
        $resCols = $db->query("SHOW COLUMNS FROM " . $table);
        if ($resCols) {
            while ($obj = $db->fetch_object($resCols)) {
                $cols[] = strtolower((string) $obj->Field);
            }
        }
        if (empty($cols)) {
            return false;
        }

        $userCol = in_array('fk_user', $cols, true) ? 'fk_user' : (in_array('user_id', $cols, true) ? 'user_id' : '');
        if ($userCol === '' || !in_array('permission_code', $cols, true)) {
            return false;
        }

        $cleanCodes = array();
        $allowedCodes = self::getPosPermissionCodes();
        foreach ((array) $permissionCodes as $code) {
            $code = strtolower(trim((string) $code));
            if ($code !== '' && in_array($code, $allowedCodes, true)) {
                $cleanCodes[] = $code;
            }
        }
        $cleanCodes = array_values(array_unique($cleanCodes));

        $entity = (int) $entity;
        if ($entity <= 0) {
            $entity = 1;
        }

        $where = $userCol . " = " . ((int) $userId);
        if (in_array('entity_id', $cols, true)) {
            $where .= " AND entity_id = " . $entity;
        }

        if (!$db->query("DELETE FROM " . $table . " WHERE " . $where)) {
            return false;
        }

        foreach ($cleanCodes as $code) {
            $fields = array();
            $values = array();
            if (in_array('entity_id', $cols, true)) {
                $fields[] = 'entity_id';
                $values[] = $entity;
            }
            $fields[] = $userCol;
            $values[] = (int) $userId;
            $fields[] = 'permission_code';
            $values[] = "'" . $db->escape($code) . "'";
            if (in_array('allowed', $cols, true)) {
                $fields[] = 'allowed';
                $values[] = 1;
            }
            if (in_array('date_created', $cols, true)) {
                $fields[] = 'date_created';
                $values[] = "'" . $db->escape(date('Y-m-d H:i:s')) . "'";
            }
            if (in_array('tms', $cols, true)) {
                $fields[] = 'tms';
                $values[] = "'" . $db->escape(date('Y-m-d H:i:s')) . "'";
            }

            $sql = "INSERT INTO " . $table . " (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
            if (!$db->query($sql)) {
                return false;
            }
        }

        return true;
    }

    public static function getUserLimits($db, $userId, $entity = null)
    {
        $limits = array(
            'role_code' => self::ROLE_CASHIER,
            'max_discount_percent' => 0.0,
            'max_discount_amount' => 0.0,
            'max_price_override_delta' => 0.0,
        );

        if (!self::tableExists($db, 'takepos_user_limits')) {
            return $limits;
        }

        $table = MAIN_DB_PREFIX . 'takepos_user_limits';
        $sql = "SELECT role_code, max_discount_percent, max_discount_amount, max_price_override_delta"
            . " FROM " . $table
            . " WHERE fk_user = " . ((int) $userId);
        if ($entity !== null) {
            $sql .= " AND entity = " . ((int) $entity);
        }
        $sql .= " ORDER BY rowid DESC LIMIT 1";

        $resql = $db->query($sql);
        if ($resql && ($obj = $db->fetch_object($resql))) {
            $limits['role_code'] = !empty($obj->role_code) ? strtolower(trim((string) $obj->role_code)) : self::ROLE_CASHIER;
            $limits['max_discount_percent'] = isset($obj->max_discount_percent) ? (float) $obj->max_discount_percent : 0.0;
            $limits['max_discount_amount'] = isset($obj->max_discount_amount) ? (float) $obj->max_discount_amount : 0.0;
            $limits['max_price_override_delta'] = isset($obj->max_price_override_delta) ? (float) $obj->max_price_override_delta : 0.0;
        }

        return $limits;
    }

    public static function saveUserLimits($db, $userId, $entity, $roleCode, $maxDiscountPercent, $maxDiscountAmount, $maxPriceOverrideDelta)
    {
        if (!self::tableExists($db, 'takepos_user_limits')) {
            return false;
        }

        $entity = (int) $entity;
        if ($entity <= 0) {
            $entity = 1;
        }
        $roleCode = strtolower(trim((string) $roleCode));
        if (!in_array($roleCode, array(self::ROLE_CASHIER, self::ROLE_SUPERVISOR, self::ROLE_MANAGER), true)) {
            $roleCode = self::ROLE_CASHIER;
        }

        $table = MAIN_DB_PREFIX . 'takepos_user_limits';
        $sql = "INSERT INTO " . $table . " (entity, fk_user, role_code, max_discount_percent, max_discount_amount, max_price_override_delta, datec, tms) VALUES ("
            . $entity . ", " . ((int) $userId) . ", '" . $db->escape($roleCode) . "', "
            . ((float) $maxDiscountPercent) . ", " . ((float) $maxDiscountAmount) . ", " . ((float) $maxPriceOverrideDelta) . ", "
            . "'" . $db->escape(dol_print_date(dol_now(), 'dayhourlog')) . "', "
            . "'" . $db->escape(dol_print_date(dol_now(), 'dayhourlog')) . "')"
            . " ON DUPLICATE KEY UPDATE role_code=VALUES(role_code), max_discount_percent=VALUES(max_discount_percent), max_discount_amount=VALUES(max_discount_amount), max_price_override_delta=VALUES(max_price_override_delta), tms=VALUES(tms)";

        return (bool) $db->query($sql);
    }

    public static function getRoleProfiles()
    {
        return array(
            self::ROLE_CASHIER => array(
                'label' => self::trans('TakeposAdminUsersRoleCashier', 'Cashier'),
                'permissions' => array(
                    'takepos.use',
                    'takepos.shift.open',
                    'takepos.shift.close',
                    'takepos.cash.paidin',
                    'takepos.cash.paidout',
                    'takepos.cash.safedrop',
                    'takepos.cash.count',
                    'takepos.expense.read',
                    'takepos.expense.create',
                ),
            ),
            self::ROLE_SUPERVISOR => array(
                'label' => self::trans('TakeposAdminUsersRoleSupervisor', 'Supervisor'),
                'permissions' => array(
                    'takepos.use',
                    'takepos.action.line_delete',
                    'takepos.action.discount',
                    'takepos.action.price_override',
                    'takepos.action.reports_view',
                    'takepos.refund.partial',
                    'takepos.refund.view',
                    'takepos.exchange.process',
                    'takepos.analytics.view',
                    'takepos.customer.view',
                    'takepos.loyalty.view',
                    'takepos.loyalty.redeem',
                    'takepos.offline.use',
                    'takepos.sync.retry',
                    'takepos.override.line_delete',
                    'takepos.override.discount',
                    'takepos.override.price',
                    'takepos.expense.read',
                    'takepos.expense.create',
                    'takepos.expense.post',
                    'takepos.purchase.read',
                    'takepos.purchase.create',
                    'takepos.cheque.read',
                    'takepos.cheque.create',
                    'takepos.cheque.status_update',
                    'takepos.cheque.print',
                ),
            ),
            self::ROLE_MANAGER => array(
                'label' => self::trans('TakeposAdminUsersRoleManager', 'Manager'),
                'permissions' => self::getPosPermissionCodes(),
            ),
        );
    }

    public static function getDefaultPermissionsForRole($roleCode)
    {
        $roleCode = strtolower(trim((string) $roleCode));
        $profiles = self::getRoleProfiles();
        if (!isset($profiles[$roleCode])) {
            $roleCode = self::ROLE_CASHIER;
        }
        return $profiles[$roleCode]['permissions'];
    }

    public static function getMainOwnerUserId($db, $entity)
    {
        $constants = array(
            'KAFOERP_MAIN_OWNER_USER_ID_ENTITY_' . ((int) $entity),
            'KAFOERP_MAIN_OWNER_USER_ID',
            'TAKEPOS_MAIN_OWNER_USER_ID_ENTITY_' . ((int) $entity),
            'TAKEPOS_MAIN_OWNER_USER_ID',
        );

        foreach ($constants as $constantName) {
            $val = self::getGlobalValue($db, $constantName, $entity);
            if ($val !== null && $val !== '') {
                return (int) $val;
            }
        }

        return 0;
    }

    public static function canOpenUserManager($db, $user)
    {
        if (empty($user->id)) {
            return false;
        }
        if (!self::moduleEnabledForEntity($db, $user->entity, 'takepos')) {
            return false;
        }
        if (!self::featureEnabledForEntity($db, $user->entity, 'admin_users')) {
            return false;
        }
        if (!empty($user->admin)) {
            return true;
        }

        if (self::userHasPermission($db, $user, 'takepos.action.users_manage')) {
            return true;
        }

        $ownerId = self::getMainOwnerUserId($db, $user->entity);
        if ($ownerId > 0 && (int) $user->id === $ownerId) {
            return true;
        }

        return false;
    }

    public static function actorGrantableRightIds($db, $actor)
    {
        $ids = array();
        $defs = self::getOperationalRightDefinitions($db);

        foreach ($defs as $def) {
            if (!self::isDelegableRight($def)) {
                continue;
            }

            if (!empty($actor->admin)) {
                $ids[] = (int) $def['id'];
                continue;
            }

            if (self::actorHasRightDefinition($actor, $def)) {
                $ids[] = (int) $def['id'];
            }
        }

        return array_values(array_unique($ids));
    }

    public static function getTakeposRightDefinitions($db)
    {
        return self::getOperationalRightDefinitions($db);
    }

    public static function getOperationalRightDefinitions($db)
    {
        $rows = array();
        if (!self::tableExists($db, 'rights_def')) {
            return $rows;
        }

        $allowMap = array(
            'takepos' => array('*'),
            'produit' => array('lire', 'creer'),
            'service' => array('lire', 'creer'),
            'categorie' => array('lire', 'creer'),
        );

        $pk = self::getRightsDefPrimaryKey($db);
        $sql = "SELECT " . $pk . " AS right_id, module, perms, subperms FROM " . MAIN_DB_PREFIX . "rights_def"
            . " WHERE module IN ('takepos','produit','service','categorie')"
            . " ORDER BY module ASC, right_id ASC";
        $resql = $db->query($sql);
        if (!$resql) {
            return $rows;
        }

        while ($obj = $db->fetch_object($resql)) {
            $module = (string) $obj->module;
            $perms = strtolower((string) $obj->perms);
            if (empty($allowMap[$module])) {
                continue;
            }
            if ($allowMap[$module][0] !== '*' && !in_array($perms, $allowMap[$module], true)) {
                continue;
            }

            $row = array(
                'id' => (int) $obj->right_id,
                'module' => $module,
                'perms' => (string) $obj->perms,
                'subperms' => (string) $obj->subperms,
            );
            $row['label'] = self::formatRightLabel($row);
            $row['delegable'] = self::isDelegableRight($row);
            $row['recommended'] = self::isRecommendedOperationalRight($row);
            $rows[] = $row;
        }

        return $rows;
    }

    public static function isRecommendedOperationalRight($def)
    {
        $module = strtolower((string) ($def['module'] ?? ''));
        $perms = strtolower((string) ($def['perms'] ?? ''));

        if ($module === 'takepos' && in_array($perms, array('run', 'editlines', 'editorderdedlines'), true)) {
            return true;
        }
        if ($module === 'produit' && in_array($perms, array('lire', 'creer'), true)) {
            return true;
        }
        if ($module === 'service' && in_array($perms, array('lire', 'creer'), true)) {
            return true;
        }
        if ($module === 'categorie' && in_array($perms, array('lire', 'creer'), true)) {
            return true;
        }

        return false;
    }

    public static function getRightsDefPrimaryKey($db)
    {
        $table = MAIN_DB_PREFIX . 'rights_def';
        $resql = $db->query("SHOW COLUMNS FROM " . $table . " LIKE 'rowid'");
        if ($resql && $db->num_rows($resql) > 0) {
            return 'rowid';
        }
        return 'id';
    }

    public static function isDelegableRight($def)
    {
        $label = strtolower(trim(($def['module'] ?? '') . ' ' . ($def['perms'] ?? '') . ' ' . ($def['subperms'] ?? '')));
        $blocked = array('user', 'users', 'manageusers', 'createusers', 'adminusers', 'rights');
        foreach ($blocked as $token) {
            if ($token !== '' && strpos($label, $token) !== false) {
                return false;
            }
        }
        return true;
    }

    public static function actorHasRightDefinition($actor, $def)
    {
        $module = strtolower((string) ($def['module'] ?? ''));
        $perms = strtolower((string) ($def['perms'] ?? ''));
        $sub = strtolower((string) ($def['subperms'] ?? ''));

        if ($module === '' || !isset($actor->rights->{$module})) {
            return false;
        }

        $cursor = $actor->rights->{$module};
        if ($perms === '') {
            return false;
        }

        if (!isset($cursor->{$perms})) {
            return false;
        }

        if ($sub === '') {
            if (is_object($cursor->{$perms})) {
                return count((array) $cursor->{$perms}) > 0;
            }
            return !empty($cursor->{$perms});
        }

        if (!is_object($cursor->{$perms})) {
            return false;
        }

        return !empty($cursor->{$perms}->{$sub});
    }

    public static function formatRightLabel($def)
    {
        $module = strtolower(trim((string) ($def['module'] ?? '')));
        $perms = trim((string) ($def['perms'] ?? ''));
        $subperms = trim((string) ($def['subperms'] ?? ''));

        $moduleLabels = array(
            'takepos' => self::trans('TakeposAdminUsersRightModuleTakepos', 'TakePOS'),
            'produit' => self::trans('TakeposAdminUsersRightModuleProducts', 'Products'),
            'service' => self::trans('TakeposAdminUsersRightModuleServices', 'Services'),
            'categorie' => self::trans('TakeposAdminUsersRightModuleCategories', 'Categories'),
        );
        $permLabels = array(
            'run' => self::trans('TakeposAdminUsersRightUsePos', 'Use POS'),
            'editlines' => self::trans('TakeposAdminUsersRightEditSalesLines', 'Edit sales lines'),
            'editorderdedlines' => self::trans('TakeposAdminUsersRightEditOrderedSalesLines', 'Edit ordered sales lines'),
            'lire' => self::trans('TakeposAdminUsersRightRead', 'Read'),
            'creer' => self::trans('TakeposAdminUsersRightCreate', 'Create'),
        );

        $parts = array();
        $parts[] = isset($moduleLabels[$module]) ? $moduleLabels[$module] : ucfirst($module);
        if ($perms !== '') {
            $parts[] = isset($permLabels[strtolower($perms)]) ? $permLabels[strtolower($perms)] : $perms;
        }
        if ($subperms !== '') {
            $parts[] = $subperms;
        }
        return implode(' / ', $parts);
    }
}



