<?php
/**
 * TakePOS protected workspace launcher.
 * Opens core/admin pages through a single permission-aware entry point.
 */

require '../main.inc.php';

require_once __DIR__ . '/lib/takepos_lang.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__.'/class/TakeposAccess.class.php';
require_once __DIR__.'/class/TakeposUserAccess.class.php';
require_once __DIR__.'/class/TakeposBranchService.class.php';

$langs->loadLangs(array('cashdesk', 'products', 'categories', 'admin', 'takeposcustom@takepos'));

$key = GETPOST('key', 'aZ09');

$map = array(
    'admin_users' => array(
        'feature' => 'takepos.users.manage',
        'url' => DOL_URL_ROOT.'/takepos/admin/users.php',
        'custom' => 'users_manager',
        'message' => $langs->trans('TakeposWorkspaceUsersAccessDenied')
    ),
    'add_product' => array(
        'feature' => 'takepos.catalog.add_product',
        'url' => DOL_URL_ROOT.'/product/card.php?action=create&token='.newToken().'&type=0',
        'check' => function($user) { return ($user->admin || $user->hasRight('produit', 'creer')); },
        'message' => $langs->trans('TakeposWorkspaceCreateProductsDenied')
    ),
    'add_service' => array(
        'feature' => 'takepos.catalog.add_service',
        'url' => DOL_URL_ROOT.'/product/card.php?action=create&token='.newToken().'&type=1',
        'check' => function($user) { return ($user->admin || $user->hasRight('service', 'creer')); },
        'message' => $langs->trans('TakeposWorkspaceCreateServicesDenied')
    ),
    'manage_products' => array(
        'feature' => 'takepos.catalog.manage_products',
        'url' => DOL_URL_ROOT.(TakeposBranchService::isBranchUser($db, (int)$user->id)
                ? '/takepos/admin/branch_products.php?branch_id='.((int)TakeposBranchService::getBranchByUserId($db, (int)$user->id)->rowid)
                : '/product/list.php?type=0'),
        'check' => function($user) { return ($user->admin || $user->hasRight('produit', 'lire')); },
        'message' => $langs->trans('TakeposWorkspaceReadProductsDenied')
    ),
    'product_barcodes' => array(
        'feature' => 'takepos.catalog.manage_products',
        'url' => DOL_URL_ROOT.'/takepos/product_barcodes.php',
        'check' => function($user) { return ($user->admin || $user->hasRight('produit', 'lire')); },
        'message' => $langs->trans('TakeposWorkspaceProductBarcodesDenied')
    ),
    'tax_rates' => array(
        'feature' => 'takepos.catalog.manage_products',
        'url' => DOL_URL_ROOT.'/takepos/tax_rates.php',
        'check' => function($user) { return ($user->admin || $user->hasRight('produit', 'creer')); },
        'message' => $langs->trans('TakeposWorkspaceTaxRatesDenied')
    ),
    'manage_services' => array(
        'feature' => 'takepos.catalog.manage_services',
        'permission' => 'takepos.catalog.manage_services',
        'url' => DOL_URL_ROOT.'/product/list.php?type=1',
        'check' => function($user) { return ($user->admin || $user->hasRight('service', 'creer')); },
        'message' => $langs->trans('TakeposWorkspaceReadServicesDenied')
    ),
    'stock_overview' => array(
        'feature' => 'takepos.catalog.manage_products',
        'url' => DOL_URL_ROOT.'/takepos/stock_overview.php',
        'check' => function($user) { return ($user->admin || $user->hasRight('produit', 'lire') || $user->hasRight('takepos', 'run')); },
        'message' => $langs->trans('TakeposWorkspaceStockAccessDenied')
    ),
    // FEATURE (add-stock-popup): audit view of every POS-driven stock-in
    'stock_adjustments' => array(
        'feature' => 'takepos.audit.log',
        'url' => DOL_URL_ROOT.'/takepos/audit/stock_adjustments.php',
        'check' => function($user) { return ($user->admin || $user->hasRight('takepos', 'run')); },
        'message' => $langs->trans('TakeposAuditLogAccessDenied')
    ),
    // FIX (stock-branch-v2): Cross-branch stock view — all branches side by side
    'stock_all_branches' => array(
        'feature' => 'takepos.catalog.manage_products',
        'url' => DOL_URL_ROOT.'/takepos/stock_all_branches.php',
        'check' => function($user) { return ($user->admin || $user->hasRight('produit', 'lire') || $user->hasRight('takepos', 'run')); },
        'message' => $langs->trans('TakeposWorkspaceStockAccessDenied')
    ),
    // FIX (stock-branch-v4): Inter-branch stock transfer
    'stock_transfer' => array(
        'feature' => 'takepos.store_governance',
        'url' => DOL_URL_ROOT.'/takepos/stock_transfer.php',
        'check' => function($user) { return ($user->admin || $user->hasRight('produit', 'creer')); },
        'message' => $langs->trans('TakeposTransferAccessDenied')
    ),
    // FIX (stock-branch-v7): Sales vs. purchases reconciliation report
    'stock_reconciliation' => array(
        'feature' => 'takepos.analytics',
        'url' => DOL_URL_ROOT.'/takepos/stock_reconciliation.php',
        'check' => function($user) { return ($user->admin || $user->hasRight('produit', 'lire')); },
        'message' => $langs->trans('TakeposReconAccessDenied')
    ),
    // FIX (stock-branch-v8): Physical inventory count
    'stock_count' => array(
        'feature' => 'takepos.store_governance',
        'url' => DOL_URL_ROOT.'/takepos/stock_count.php',
        'check' => function($user) { return ($user->admin || $user->hasRight('produit', 'creer')); },
        'message' => $langs->trans('TakeposCountAccessDenied')
    ),
    'add_category' => array(
        'feature' => 'takepos.catalog.add_category',
        'url' => DOL_URL_ROOT.'/categories/card.php?action=create&token='.newToken().'&type=0',
        'check' => function($user) { return ($user->admin || $user->hasRight('categorie', 'creer')); },
        'message' => $langs->trans('TakeposWorkspaceCreateCategoriesDenied')
    ),
    'manage_categories' => array(
        'feature' => 'takepos.catalog.manage_categories',
        'url' => DOL_URL_ROOT.'/categories/index.php?type=0',
        'check' => function($user) { return ($user->admin || $user->hasRight('categorie', 'lire')); },
        'message' => $langs->trans('TakeposWorkspaceManageCategoriesDenied')
    ),
    'shift_ops' => array(
        'feature' => 'takepos.shift_management',
        'url' => DOL_URL_ROOT.'/takepos/shifts.php',
        'message' => $langs->trans('TakeposWorkspaceShiftAccessDenied')
    ),
    'refund_lookup' => array(
        'feature' => 'takepos.refunds',
        'features_all' => array('takepos.refunds', 'takepos.returns'),
        'permissions_any' => array('takepos.refund.view', 'takepos.refund.partial', 'takepos.refund.full'),
        'url' => DOL_URL_ROOT.'/takepos/refunds.php',
        'message' => $langs->trans('TakeposWorkspaceRefundAccessDenied')
    ),
    'exchange_ops' => array(
        'feature' => 'takepos.exchanges',
        'features_all' => array('takepos.exchanges', 'takepos.returns'),
        'permissions_any' => array('takepos.exchange.process', 'takepos.refund.view'),
        'url' => DOL_URL_ROOT.'/takepos/exchange.php',
        'message' => $langs->trans('TakeposWorkspaceExchangeAccessDenied')
    ),
    'kpi_dashboard' => array(
        'feature' => 'takepos.kpi_dashboard',
        'permission' => 'takepos.analytics.view',
        'url' => DOL_URL_ROOT.'/takepos/kpi.php',
        'message' => $langs->trans('TakeposWorkspaceKpiAccessDenied')
    ),
    'dashboard_pro' => array(
        'feature' => 'takepos.dashboard.pro',
        'permission' => 'takepos.dashboard.view',
        'url' => DOL_URL_ROOT.'/takepos/dashboard.php',
        'custom' => 'dashboard_pro',
        'message' => $langs->trans('TakeposDashboardAccessDenied')
    ),
    'sync_queue' => array(
        'feature' => 'takepos.sync_queue',
        'permission' => 'takepos.sync.manage',
        'url' => DOL_URL_ROOT.'/takepos/sync_queue.php',
        'message' => $langs->trans('TakeposWorkspaceSyncAccessDenied')
    ),
    'loyalty_desk' => array(
        'feature' => 'takepos.crm',
        'permission' => 'takepos.customer.view',
        'url' => DOL_URL_ROOT.'/takepos/loyalty.php',
        'message' => $langs->trans('TakeposWorkspaceLoyaltyAccessDenied')
    ),
    'expenses_ops' => array(
        'feature' => 'takepos.cash_control',
        'url' => DOL_URL_ROOT.'/takepos/expenses.php',
        'custom' => 'expenses_ops',
        'message' => $langs->trans('TakeposWorkspaceExpensesAccessDenied')
    ),
    'expense_ledger' => array(
        'feature' => 'takepos.cash_control',
        'url' => DOL_URL_ROOT.'/takepos/expense_ledger.php',
        'custom' => 'expense_ledger',
        'message' => $langs->trans('TakeposWorkspaceExpenseLedgerAccessDenied')
    ),
    'purchase_ops' => array(
        'feature' => 'takepos.purchases',
        'url' => DOL_URL_ROOT.'/takepos/purchases.php',
        'custom' => 'purchase_ops',
        'message' => $langs->trans('TakeposWorkspacePurchaseAccessDenied')
    ),
    'cheque_ops' => array(
        'feature' => 'takepos.cheques',
        'url' => DOL_URL_ROOT.'/takepos/cheques.php',
        'custom' => 'cheque_ops',
        'message' => $langs->trans('TakeposWorkspaceChequesAccessDenied')
    ),
    'admin_expense_categories' => array(
        'feature' => 'takepos.cash_control',
        'url' => DOL_URL_ROOT.'/takepos/admin/expense_categories.php',
        'custom' => 'expense_categories_admin',
        'message' => $langs->trans('TakeposWorkspaceExpenseCategoriesAccessDenied')
    ),
    'admin_stores' => array(
        'feature' => 'takepos.store_governance',
        'url' => DOL_URL_ROOT.'/takepos/admin/stores.php',
        'admin_permission' => 'takepos.store.manage',
        'message' => $langs->trans('TakeposWorkspaceStoresAccessDenied')
    ),
    'admin_branches' => array(
        'feature' => 'takepos.store_governance',
        'url' => DOL_URL_ROOT.'/takepos/admin/branches.php',
        'admin_permission' => 'takepos.store.manage',
        'message' => 'Access denied – branch management requires admin access.'
    ),
    'admin_product_variants' => array(
        'feature' => 'takepos.run',
        'url' => DOL_URL_ROOT.'/takepos/admin/product_variants.php',
        'admin' => true,
        'message' => 'Access denied – admin access required for piece/box variants.'
    ),
    'admin_terminal_map' => array(
        'feature' => 'takepos.terminal_governance',
        'url' => DOL_URL_ROOT.'/takepos/admin/terminal_map.php',
        'admin_permission' => 'takepos.terminal.manage',
        'message' => $langs->trans('TakeposWorkspaceTerminalMapAccessDenied')
    ),
    'admin_user_store' => array(
        'feature' => 'takepos.store_governance',
        'url' => DOL_URL_ROOT.'/takepos/admin/user_store.php',
        'admin_permission' => 'takepos.terminal.assign',
        'message' => $langs->trans('TakeposWorkspaceUserStoreAccessDenied')
    ),
    'admin_setup' => array(
        'feature' => 'takepos.admin.setup',
        'url' => DOL_URL_ROOT.'/takepos/admin/setup.php',
        'admin' => true,
        'message' => $langs->trans('TakeposWorkspaceSetupAccessDenied')
    ),
    'admin_terminal' => array(
        'feature' => 'takepos.admin.terminal',
        'url' => DOL_URL_ROOT.'/takepos/admin/terminal.php?terminal=1',
        'admin' => true,
        'message' => $langs->trans('TakeposWorkspaceTerminalSettingsAccessDenied')
    ),
    'admin_receipt' => array(
        'feature' => 'takepos.admin.receipt',
        'url' => DOL_URL_ROOT.'/takepos/admin/receipt.php',
        'admin' => true,
        'message' => $langs->trans('TakeposWorkspaceReceiptSettingsAccessDenied')
    ),
    'admin_appearance' => array(
        'feature' => 'takepos.admin.appearance',
        'url' => DOL_URL_ROOT.'/takepos/admin/appearance.php',
        'admin' => true,
        'message' => $langs->trans('TakeposWorkspaceAppearanceAccessDenied')
    ),
    'admin_bar' => array(
        'feature' => 'takepos.admin.bar',
        'url' => DOL_URL_ROOT.'/takepos/admin/bar.php',
        'admin' => true,
        'message' => $langs->trans('TakeposWorkspaceBarAccessDenied')
    ),
    'admin_orderprinters' => array(
        'feature' => 'takepos.admin.orderprinters',
        'url' => DOL_URL_ROOT.'/takepos/admin/orderprinters.php',
        'admin' => true,
        'message' => $langs->trans('TakeposWorkspaceOrderPrintersAccessDenied')
    ),
    'admin_other' => array(
        'feature' => 'takepos.admin.other',
        'url' => DOL_URL_ROOT.'/takepos/admin/other.php',
        'admin' => true,
        'message' => $langs->trans('TakeposWorkspaceOtherSettingsAccessDenied')
    ),
    'admin_loyalty' => array(
        'feature' => 'takepos.loyalty',
        'url' => DOL_URL_ROOT.'/takepos/admin/loyalty.php',
        'admin' => true,
        'message' => $langs->trans('TakeposWorkspaceLoyaltySettingsAccessDenied')
    ),
    'admin_devices' => array(
        'feature' => 'takepos.device_layer',
        'url' => DOL_URL_ROOT.'/takepos/admin/devices.php',
        'custom' => 'device_settings',
        'message' => $langs->trans('TakeposWorkspaceDeviceSettingsAccessDenied')
    ),
    'admin_printers' => array(
        'feature' => 'takepos.printer_profiles',
        'url' => DOL_URL_ROOT.'/takepos/admin/printers.php',
        'custom' => 'printer_profiles',
        'message' => $langs->trans('TakeposWorkspacePrinterProfilesAccessDenied')
    ),
    'admin_api_webhooks' => array(
        'feature' => 'takepos.api_layer',
        'url' => DOL_URL_ROOT.'/takepos/admin/api_webhooks.php',
        'custom' => 'api_webhooks',
        'message' => $langs->trans('TakeposWorkspaceApiWebhooksAccessDenied')
    ),
    'admin_printqr' => array(
        'feature' => 'takepos.admin.printqr',
        'url' => DOL_URL_ROOT.'/takepos/admin/printqr.php',
        'admin' => true,
        'message' => $langs->trans('TakeposWorkspaceQrSettingsAccessDenied')
    ),
);

if (empty($map[$key])) {
    accessforbidden($langs->trans('TakeposWorkspaceUnknownKey'));
}

$entry = $map[$key];

restrictedArea($user, 'takepos', 0, '');

if (!empty($entry['custom']) && $entry['custom'] === 'users_manager') {
    if (!TakeposUserAccess::canOpenUserManager($db, $user)) {
        accessforbidden($entry['message']);
    }
    TakeposAccess::requireAdminAccess($db, $user, $entry['feature'], 'takepos.action.users_manage', null, $entry['message']);
} elseif (!empty($entry['custom']) && $entry['custom'] === 'device_settings') {
    TakeposAccess::requireAdminAccess($db, $user, $entry['feature'], 'takepos.device.manage', null, $entry['message']);
} elseif (!empty($entry['custom']) && $entry['custom'] === 'printer_profiles') {
    TakeposAccess::requireAdminAccess($db, $user, $entry['feature'], 'takepos.device.manage', null, $entry['message']);
} elseif (!empty($entry['custom']) && $entry['custom'] === 'api_webhooks') {
    if (empty($user->admin) && !TakeposUserAccess::userHasAnyPermission($db, $user, array('takepos.api.read', 'takepos.api.write', 'takepos.webhook.manage'))) {
        accessforbidden($entry['message']);
    }
} elseif (!empty($entry['custom']) && $entry['custom'] === 'expenses_ops') {
    require_once __DIR__.'/class/TakeposExpenseService.class.php';
    if (!TakeposExpenseService::canRead($db, $user)) {
        accessforbidden($entry['message']);
    }
    TakeposAccess::requireFrontendAccess($db, $user, $entry['feature'], 'takepos.use', isset($_SESSION['takeposterminal']) ? $_SESSION['takeposterminal'] : null, $entry['message']);
} elseif (!empty($entry['custom']) && $entry['custom'] === 'expense_ledger') {
    require_once __DIR__.'/class/TakeposExpenseService.class.php';
    if (!TakeposExpenseService::canRead($db, $user)) {
        accessforbidden($entry['message']);
    }
    TakeposAccess::requireFrontendAccess($db, $user, $entry['feature'], 'takepos.use', isset($_SESSION['takeposterminal']) ? $_SESSION['takeposterminal'] : null, $entry['message']);
} elseif (!empty($entry['custom']) && $entry['custom'] === 'expense_categories_admin') {
    require_once __DIR__.'/class/TakeposExpenseService.class.php';
    if (!TakeposExpenseService::canAdmin($db, $user)) {
        accessforbidden($entry['message']);
    }
    TakeposAccess::requireFrontendAccess($db, $user, $entry['feature'], 'takepos.use', isset($_SESSION['takeposterminal']) ? $_SESSION['takeposterminal'] : null, $entry['message']);
} elseif (!empty($entry['custom']) && $entry['custom'] === 'purchase_ops') {
    require_once __DIR__.'/class/TakeposUserAccess.class.php';
    if (!TakeposUserAccess::userHasPermission($db, $user, 'takepos.purchase.read') && !$user->hasRight('produit', 'lire')) {
        accessforbidden($entry['message']);
    }
    TakeposAccess::requireFrontendAccess($db, $user, $entry['feature'], 'takepos.purchase.read', isset($_SESSION['takeposterminal']) ? $_SESSION['takeposterminal'] : null, $entry['message']);
} elseif (!empty($entry['custom']) && $entry['custom'] === 'cheque_ops') {
    if (!TakeposUserAccess::userHasPermission($db, $user, 'takepos.cheque.read') && !$user->hasRight('produit', 'lire')) {
        accessforbidden($entry['message']);
    }
    TakeposAccess::requireFrontendAccess($db, $user, $entry['feature'], 'takepos.cheque.read', isset($_SESSION['takeposterminal']) ? $_SESSION['takeposterminal'] : null, $entry['message']);
} elseif (!empty($entry['admin_permission'])) {
    TakeposAccess::requireAdminAccess($db, $user, $entry['feature'], $entry['admin_permission'], null, $entry['message']);
} elseif (!empty($entry['admin'])) {
    if (empty($user->admin)) {
        accessforbidden($entry['message']);
    }
    TakeposAccess::requireAdminAccess($db, $user, $entry['feature'], 'takepos.admin', null, $entry['message']);
} else {
    $check = isset($entry['check']) ? $entry['check'] : null;
    $featuresAll = (!empty($entry['features_all']) && is_array($entry['features_all']) ? $entry['features_all'] : array());
    foreach ($featuresAll as $featureCode) {
        TakeposAccess::requireFeature($db, $featureCode, $user, false, array('workspace_key' => $key, 'feature' => $featureCode));
    }

    $permissionsAny = (!empty($entry['permissions_any']) && is_array($entry['permissions_any']) ? $entry['permissions_any'] : array());
    if (!empty($permissionsAny)) {
        if (!TakeposUserAccess::userHasAnyPermission($db, $user, $permissionsAny)) {
            accessforbidden($entry['message']);
        }
        $permission = 'takepos.use';
    } else {
        $permission = !empty($entry['permission']) ? (string) $entry['permission'] : 'takepos.use';
    }

    if (is_callable($check) && !$check($user)) {
        accessforbidden($entry['message']);
    }
    TakeposAccess::requireFrontendAccess($db, $user, $entry['feature'], $permission, isset($_SESSION['takeposterminal']) ? $_SESSION['takeposterminal'] : null, $entry['message']);
}

header('Location: '.$entry['url']);
exit;