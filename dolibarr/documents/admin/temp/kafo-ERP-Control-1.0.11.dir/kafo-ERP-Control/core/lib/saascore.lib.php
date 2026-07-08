<?php
function saascoreAdminPrepareHead()
{
    global $langs;
    $h = 0;
    $head = array();
    $head[$h++] = array(dol_buildpath('/kafo_ERP_Control/admin/index.php', 1), $langs->trans('General'), 'general');
    $head[$h++] = array(dol_buildpath('/kafo_ERP_Control/admin/modules.php', 1), $langs->trans('ModulesCatalog'), 'modules');
    $head[$h++] = array(dol_buildpath('/kafo_ERP_Control/admin/features.php', 1), $langs->trans('FeaturesCatalog'), 'features');
    $head[$h++] = array(dol_buildpath('/kafo_ERP_Control/admin/limits.php', 1), $langs->trans('LimitsCatalog'), 'limits');
    $head[$h++] = array(dol_buildpath('/kafo_ERP_Control/admin/permissions.php', 1), $langs->trans('PermissionsCatalog'), 'permissions');
    $head[$h++] = array(dol_buildpath('/kafo_ERP_Control/admin/bundles.php', 1), $langs->trans('BundlesCatalog'), 'bundles');
    $head[$h++] = array(dol_buildpath('/kafo_ERP_Control/admin/roles.php', 1), $langs->trans('RolesCatalog'), 'roles');
    $head[$h++] = array(dol_buildpath('/kafo_ERP_Control/admin/tenant.php', 1), $langs->trans('TenantConfiguration'), 'tenant');
    return $head;
}

function saascoreRequireAdminRight($right = 'read')
{
    global $user;
    if (!$user->admin && !$user->hasRight('saascore', $right)) {
        accessforbidden();
    }
}

function saascoreRegisterTakeposCatalog($db)
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $registryFile = dol_buildpath('/kafo_ERP_Control/class/SaasRegistryService.php', 0);
    if (!is_file($registryFile)) {
        return;
    }

    require_once $registryFile;
    if (!class_exists('SaasRegistryService')) {
        return;
    }

    $registry = new SaasRegistryService($db);
    $registry->registerModule('takepos', 'TakePOS', 'TakePOS module controlled by saascore', 1);

    $permissions = array(
        'takepos.use' => 'Use TakePOS',
        'takepos.admin' => 'Administer TakePOS',
    );
    foreach ($permissions as $code => $label) {
        $registry->registerPermission($code, $label, 'takepos', $label);
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
        $registry->registerFeature($code, $label, 'takepos', $label);
    }

    $registry->registerLimit('takepos.terminals', 'POS terminals', 'takepos', 1, 'Maximum allowed POS terminal number for the tenant');
}

function saascoreSyncKnownIntegrations($db)
{
    if (!is_object($db)) {
        return;
    }

    // Always ensure TakePOS control catalog exists in saascore, even if bridge loading fails.
    saascoreRegisterTakeposCatalog($db);

    // Optional: also let TakePOS bridge register anything extra when available.
    $bridgeFile = DOL_DOCUMENT_ROOT.'/takepos/class/TakeposSaasBridge.class.php';
    if (!is_file($bridgeFile)) {
        return;
    }

    require_once $bridgeFile;
    if (!class_exists('TakeposSaasBridge') || !method_exists('TakeposSaasBridge', 'bootstrap')) {
        return;
    }

    try {
        TakeposSaasBridge::bootstrap($db);
    } catch (Exception $e) {
        // Keep admin pages stable even if integration sync has an issue.
    }
}


