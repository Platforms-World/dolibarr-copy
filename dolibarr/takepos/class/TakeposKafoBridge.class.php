<?php
require_once __DIR__ . '/TakeposUserAccess.class.php';

/**
 * Registers the current TakePOS catalog into kafoerpcontrol when the
 * kafo admin sync runs.
 */
class TakeposKafoBridge
{
    protected static function humanizeCode($code)
    {
        $code = trim((string) $code);
        if ($code === '') {
            return 'TakePOS';
        }

        if (strpos($code, 'takepos.') === 0) {
            $code = substr($code, 8);
        }

        $parts = preg_split('/[._]+/', $code);
        $parts = array_filter(array_map('trim', (array) $parts), 'strlen');
        $parts = array_map(
            function ($part) {
                return ucfirst(strtolower((string) $part));
            },
            $parts
        );

        return (!empty($parts) ? implode(' ', $parts) : 'TakePOS');
    }

    protected static function featureCatalog()
    {
        return array(
            'takepos.frontend' => 'POS frontend',
            'takepos.payment' => 'Payments',
            'takepos.discount' => 'Line discounts',
            'takepos.freezone' => 'Free-text product',
            'takepos.split' => 'Split sale',
            'takepos.receipt' => 'Receipt printing',
            'takepos.send' => 'Send receipt or invoice',
            'takepos.qr' => 'QR and generated images',
            'takepos.restaurant' => 'Restaurant mode',
            'takepos.public_menu' => 'Public menu',
            'takepos.auto_order' => 'Auto order',
            'takepos.smpcb' => 'SMP payment bridge',
            'takepos.customer_display' => 'Customer display',
            'takepos.manager_override' => 'Manager override',
            'takepos.admin' => 'TakePOS admin area',
            'takepos.admin.setup' => 'Setup page',
            'takepos.admin.bar' => 'Bar settings',
            'takepos.admin.appearance' => 'Appearance settings',
            'takepos.admin.receipt' => 'Receipt settings',
            'takepos.admin.terminal' => 'Terminal settings',
            'takepos.admin.orderprinters' => 'Order printer settings',
            'takepos.admin.printqr' => 'QR print settings',
            'takepos.admin.other' => 'Other settings',
            'takepos.catalog.add_product' => 'Catalog add product',
            'takepos.catalog.add_service' => 'Catalog add service',
            'takepos.catalog.manage_products' => 'Catalog manage products',
            'takepos.catalog.manage_services' => 'Catalog manage services',
            'takepos.catalog.stock_overview' => 'Catalog stock overview',
            'takepos.catalog.add_category' => 'Catalog add category',
            'takepos.catalog.manage_categories' => 'Catalog manage categories',
            'takepos.shift_management' => 'Shift management',
            'takepos.cash_control' => 'Cash control',
            'takepos.returns' => 'Returns',
            'takepos.refunds' => 'Refunds',
            'takepos.exchanges' => 'Exchanges',
            'takepos.analytics' => 'Analytics',
            'takepos.kpi_dashboard' => 'KPI dashboard',
            'takepos.dashboard.pro' => 'Dashboard Pro',
            'takepos.crm' => 'CRM',
            'takepos.loyalty' => 'Loyalty',
            'takepos.offline_mode' => 'Offline mode',
            'takepos.sync_queue' => 'Sync queue',
            'takepos.device_layer' => 'Device layer',
            'takepos.printer_profiles' => 'Printer profiles',
            'takepos.customer_display_profiles' => 'Customer display profiles',
            'takepos.api_layer' => 'API layer',
            'takepos.webhooks' => 'Webhooks',
            'takepos.purchases' => 'Purchases',
            'takepos.cheques' => 'Cheques',
            'takepos.users.manage' => 'POS user manager',
            'takepos.store_governance' => 'Store governance',
            'takepos.terminal_governance' => 'Terminal governance',
            'takepos.audit.log' => 'Audit log',
        );
    }

    protected static function permissionCatalog()
    {
        $catalog = array();

        foreach (TakeposUserAccess::getPosPermissionCodes() as $code) {
            $catalog[$code] = self::humanizeCode($code);
        }

        return $catalog;
    }

    public static function bootstrap($db)
    {
        if (!is_object($db)) {
            return false;
        }

        $registryFile = dol_buildpath('/kafoerpcontrol/class/SaasRegistryService.php', 0);
        if (!is_file($registryFile)) {
            return false;
        }

        require_once $registryFile;
        if (!class_exists('SaasRegistryService')) {
            return false;
        }

        $registry = new SaasRegistryService($db);
        $ok = (bool) $registry->registerModule('takepos', 'TakePOS', 'TakePOS module controlled by kafo-ERP-Control', 1);

        foreach (self::permissionCatalog() as $code => $label) {
            $ok = (bool) $registry->registerPermission($code, $label, 'takepos', $label) && $ok;
        }

        foreach (self::featureCatalog() as $code => $label) {
            $ok = (bool) $registry->registerFeature($code, $label, 'takepos', $label) && $ok;
        }

        $ok = (bool) $registry->registerLimit('takepos.terminals', 'POS terminals', 'takepos', 1, 'Maximum allowed POS terminal number for the tenant') && $ok;

        return $ok;
    }
}
