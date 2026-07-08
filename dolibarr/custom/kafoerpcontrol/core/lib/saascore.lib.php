<?php
function saascoreAdminPrepareHead()
{
    global $langs;
    $h = 0;
    $head = array();
    $head[$h++] = array(dol_buildpath('/kafoerpcontrol/admin/index.php', 1), $langs->trans('General'), 'general');
    $head[$h++] = array(dol_buildpath('/kafoerpcontrol/admin/modules.php', 1), $langs->trans('ModulesCatalog'), 'modules');
    $head[$h++] = array(dol_buildpath('/kafoerpcontrol/admin/features.php', 1), $langs->trans('FeaturesCatalog'), 'features');
    $head[$h++] = array(dol_buildpath('/kafoerpcontrol/admin/limits.php', 1), $langs->trans('LimitsCatalog'), 'limits');
    $head[$h++] = array(dol_buildpath('/kafoerpcontrol/admin/permissions.php', 1), $langs->trans('PermissionsCatalog'), 'permissions');
    $head[$h++] = array(dol_buildpath('/kafoerpcontrol/admin/permissions_control.php', 1), $langs->trans('PermissionsControl'), 'permissionscontrol');
    $head[$h++] = array(dol_buildpath('/kafoerpcontrol/admin/bundles.php', 1), $langs->trans('BundlesCatalog'), 'bundles');
    $head[$h++] = array(dol_buildpath('/kafoerpcontrol/admin/roles.php', 1), $langs->trans('RolesCatalog'), 'roles');
    $head[$h++] = array(dol_buildpath('/kafoerpcontrol/admin/api.php', 1), $langs->trans('API'), 'api');
    $head[$h++] = array(dol_buildpath('/kafoerpcontrol/admin/auditlog.php', 1), $langs->trans('AuditLog'), 'auditlog');
    $head[$h++] = array(dol_buildpath('/kafoerpcontrol/admin/tenant.php', 1), $langs->trans('TenantConfiguration'), 'tenant');
    require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposBranchService.class.php';
    global $db, $user;
    if (!isset($user) || !is_object($user) || empty($user->id) || !TakeposBranchService::isBranchUser($db, (int) $user->id)) {
        $head[$h][0] = DOL_URL_ROOT.'/takepos/admin/branches.php';
        $head[$h][1] = 'Branches';
        $head[$h][2] = 'branches';
        $h++;
    }

    return $head;
}

function kafoerpcontrolModuleEnabled()
{
    return isModEnabled('kafoerpcontrol');
}

function saascoreGetAuditLogService($db)
{
    static $services = array();

    if (!is_object($db)) {
        return null;
    }

    $serviceKey = spl_object_hash($db);
    if (isset($services[$serviceKey])) {
        return $services[$serviceKey];
    }

    $serviceFile = dol_buildpath('/kafoerpcontrol/class/service/KafoAuditLogService.php', 0);
    if (!is_file($serviceFile)) {
        return null;
    }

    require_once $serviceFile;
    if (!class_exists('KafoAuditLogService')) {
        return null;
    }

    $services[$serviceKey] = new KafoAuditLogService($db);
    return $services[$serviceKey];
}

function saascoreAuditLogAction($db, $actorUserId, $targetUserId, $actionType, $objectType, $objectKey, $oldValue = null, $newValue = null, $description = '', $extra = array())
{
    $service = saascoreGetAuditLogService($db);
    if (!is_object($service)) {
        return false;
    }

    try {
        return (bool) $service->logAction($actorUserId, $targetUserId, $actionType, $objectType, $objectKey, $oldValue, $newValue, $description, $extra);
    } catch (Throwable $e) {
        return false;
    }
}

function saascoreTrackAuthenticatedAccess($db, $contextPage = '')
{
    global $user;

    if (!is_object($user) || empty($user->id)) {
        return false;
    }

    $service = saascoreGetAuditLogService($db);
    if (!is_object($service) || !method_exists($service, 'logLoginSuccess')) {
        return false;
    }

    return (bool) $service->logLoginSuccess((int) $user->id, (string) $user->login, (string) $contextPage, 1200);
}

function saascoreRequireAdminRight($right = 'read')
{
    global $user, $db;

    $contextPage = '';
    if (!empty($_SERVER['PHP_SELF'])) {
        $contextPage = basename((string) $_SERVER['PHP_SELF']);
    }

    if (!$user->admin && !$user->hasRight('kafoerpcontrol', $right)) {
        saascoreAuditLogAction(
            $db,
            (is_object($user) && !empty($user->id) ? (int) $user->id : 0),
            0,
            'admin_access_denied',
            'admin_page',
            $contextPage,
            null,
            $right,
            'Denied access to kafo-ERP-Control admin page',
            array('required_right' => $right, 'context_page' => $contextPage)
        );
        accessforbidden();
    }

    saascoreTrackAuthenticatedAccess($db, $contextPage);
}

function saascoreRegisterTakeposCatalog($db)
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $registryFile = dol_buildpath('/kafoerpcontrol/class/SaasRegistryService.php', 0);
    if (!is_file($registryFile)) {
        return;
    }

    require_once $registryFile;
    if (!class_exists('SaasRegistryService')) {
        return;
    }

    $registry = new SaasRegistryService($db);
    $registry->registerModule('takepos', 'TakePOS', 'TakePOS module controlled by kafo-ERP-Control', 1);

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

    $auditService = saascoreGetAuditLogService($db);
    if (is_object($auditService) && method_exists($auditService, 'ensureSchema')) {
        try {
            $auditService->ensureSchema();
        } catch (Throwable $e) {
            // Keep admin pages stable if audit schema sync has an issue.
        }
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
