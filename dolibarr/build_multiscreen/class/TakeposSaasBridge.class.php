<?php
/**
 * TakePOS bridge with saascore entitlement module.
 */
class TakeposSaasBridge
{
    protected static $bootstrapped = false;
    protected static $saasAvailable = null;
    protected static $accessService = null;
    protected static $registryService = null;

    public static function bootstrap($db)
    {
        if (self::$bootstrapped) {
            return;
        }
        self::$bootstrapped = true;
        self::$saasAvailable = false;

        $bases = array(
            DOL_DOCUMENT_ROOT . '/custom/kafoerpcontrol/class',
            DOL_DOCUMENT_ROOT . '/custom/saascore/class',
        );

        $accessFile = '';
        $registryFile = '';
        foreach ($bases as $base) {
            $candidate = $base . '/SaasAccessService.php';
            if (is_file($candidate)) {
                $accessFile = $candidate;
                $registryFile = $base . '/SaasRegistryService.php';
                break;
            }
        }

        if ($accessFile === '') {
            return;
        }

        require_once $accessFile;
        if (!class_exists('SaasAccessService')) {
            return;
        }

        self::$accessService = new SaasAccessService($db);
        self::$saasAvailable = true;

        if (is_file($registryFile)) {
            require_once $registryFile;
            if (class_exists('SaasRegistryService')) {
                self::$registryService = new SaasRegistryService($db);
                self::registerCatalog();
            }
        }
    }

    protected static function registerCatalog()
    {
        if (!self::$registryService) {
            return;
        }

        self::$registryService->registerModule('takepos', 'TakePOS', 'TakePOS module controlled by saascore', 1);

        $permissions = array(
            'takepos.use' => 'Use TakePOS',
            'takepos.admin' => 'Administer TakePOS',
        );
        foreach ($permissions as $code => $label) {
            self::$registryService->registerPermission($code, $label, 'takepos', $label);
        }

        $features = array(
            'takepos.frontend' => 'POS terminal frontend',
            'takepos.payment' => 'Payments popup',
            'takepos.discount' => 'Line discounts',
            'takepos.freezone' => 'Free lines',
            'takepos.split' => 'Split bills',
            'takepos.receipt' => 'Receipt printing',
            'takepos.send' => 'Send receipt or invoice',
            'takepos.qr' => 'QR and generated images',
            'takepos.restaurant' => 'Restaurant mode',
            'takepos.public_menu' => 'Public QR menu',
            'takepos.auto_order' => 'Public auto order',
            'takepos.smpcb' => 'SMP payment bridge',
            'takepos.admin.setup' => 'Setup page',
            'takepos.admin.bar' => 'Bar settings',
            'takepos.admin.appearance' => 'Appearance settings',
            'takepos.admin.receipt' => 'Receipt settings',
            'takepos.admin.terminal' => 'Terminal settings',
            'takepos.admin.orderprinters' => 'Order printers settings',
            'takepos.admin.printqr' => 'QR printing settings',
            'takepos.admin.other' => 'Other settings',
            'takepos.catalog.add_product' => 'Catalog: add product',
            'takepos.catalog.add_service' => 'Catalog: add service',
            'takepos.catalog.manage_products' => 'Catalog: manage products',
            'takepos.catalog.manage_services' => 'Catalog: manage services',
            'takepos.catalog.stock_overview' => 'Catalog: stock overview',
            'takepos.catalog.add_category' => 'Catalog: add category',
            'takepos.catalog.manage_categories' => 'Catalog: manage categories',
        );
        foreach ($features as $code => $label) {
            self::$registryService->registerFeature($code, $label, 'takepos', $label);
        }

        $limits = array(
            'takepos.terminals' => array('POS terminals', 1, 'Maximum allowed POS terminal number for the tenant'),
        );
        foreach ($limits as $code => $meta) {
            self::$registryService->registerLimit($code, $meta[0], 'takepos', (int) $meta[1], $meta[2]);
        }
    }

    public static function enforceFrontend($db, $user, $featureCode = 'takepos.frontend', $terminal = null, $message = null)
    {
        self::bootstrap($db);
        if (!self::$saasAvailable) {
            return;
        }

        global $conf;
        self::$accessService->enforceModuleEnabled((int) $conf->entity, 'takepos', $message ?: 'TakePOS module not enabled for this tenant');
        if (!empty($featureCode)) {
            self::$accessService->enforceFeatureEnabled((int) $conf->entity, $featureCode, 'TakePOS feature is not enabled for this tenant');
        }
        if (is_object($user) && !empty($user->id)) {
            self::$accessService->enforceUserPermission((int) $user->id, 'takepos.use', 'TakePOS access denied by saascore');
        }
        if ($terminal !== null) {
            self::enforceTerminalLimit($db, (int) $terminal);
        }
    }

    public static function enforceAdmin($db, $user, $featureCode = null, $terminal = null, $message = null)
    {
        self::bootstrap($db);
        if (!self::$saasAvailable) {
            return;
        }

        global $conf;
        self::$accessService->enforceModuleEnabled((int) $conf->entity, 'takepos', $message ?: 'TakePOS module not enabled for this tenant');
        self::$accessService->enforceUserPermission((int) $user->id, 'takepos.admin', 'TakePOS admin access denied by saascore');
        if (!empty($featureCode)) {
            self::$accessService->enforceFeatureEnabled((int) $conf->entity, $featureCode, 'TakePOS admin feature is not enabled for this tenant');
        }
        if ($terminal !== null) {
            self::enforceTerminalLimit($db, (int) $terminal);
        }
    }

    public static function enforcePublic($db, $featureCode, $message = null)
    {
        self::bootstrap($db);
        if (!self::$saasAvailable) {
            return;
        }

        global $conf;
        self::$accessService->enforceModuleEnabled((int) $conf->entity, 'takepos', $message ?: 'TakePOS module not enabled for this tenant');
        if (!empty($featureCode)) {
            self::$accessService->enforceFeatureEnabled((int) $conf->entity, $featureCode, $message ?: 'TakePOS public feature is not enabled for this tenant');
        }
    }


    public static function isFeatureEnabled($db, $featureCode)
    {
        self::bootstrap($db);
        if (!self::$saasAvailable) {
            return true;
        }

        global $conf;
        $featureCode = trim((string) $featureCode);
        if ($featureCode === '') {
            return true;
        }

        return (bool) self::$accessService->isFeatureEnabled((int) $conf->entity, $featureCode);
    }
    public static function enforceTerminalLimit($db, $terminal)
    {
        self::bootstrap($db);
        if (!self::$saasAvailable) {
            return;
        }
        if ($terminal <= 0) {
            return;
        }

        global $conf;
        $limit = (int) self::$accessService->getLimit((int) $conf->entity, 'takepos.terminals');
        if ($limit > 0 && $terminal > $limit) {
            accessforbidden('POS terminal limit exceeded for this tenant');
        }
    }
}


