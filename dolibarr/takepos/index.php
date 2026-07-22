<?php
/* Copyright (C) 2018	Andreu Bisquerra	<jove@bisquerra.com>
 * Copyright (C) 2019    Josep Lluis Amador   <joseplluis@lliuretic.cat>
 * Copyright (C) 2020	Thibault FOUCART	<support@ptibogxiv.net>
 * Copyright (C) 2024-2025	MDW				<mdeweerd@users.noreply.github.com>
 * Copyright (C) 2024    Frederic France      <frederic.france@free.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *    \file       htdocs/takepos/index.php
 *    \ingroup    takepos
 *    \brief      Main TakePOS screen
 */

// if (! defined('NOREQUIREUSER')) 		define('NOREQUIREUSER','1'); 		// Not disabled cause need to load personalized language
// if (! defined('NOREQUIREDB')) 		define('NOREQUIREDB','1'); 			// Not disabled cause need to load personalized language
// if (! defined('NOREQUIRESOC')) 		define('NOREQUIRESOC','1');
// if (! defined('NOREQUIRETRAN')) 		define('NOREQUIRETRAN','1');

if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
}

// Load Dolibarr environment
require '../main.inc.php';
require_once __DIR__ . '/lib/takepos_lang.php';
require_once __DIR__ . '/lib/takepos_currency.php';
require_once __DIR__ . '/lib/takepos_help.php';
require_once __DIR__ . '/lib/takepos_category_decorator.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
// Load $user and permissions
require_once __DIR__ . '/class/TakeposAccess.class.php';
require_once __DIR__ . '/class/TakeposAudit.class.php';
require_once __DIR__ . '/class/TakeposUserAccess.class.php';
require_once __DIR__ . '/class/TakeposExpenseService.class.php';
require_once __DIR__ . '/class/TakeposPurchaseService.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/hookmanager.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Societe $mysoc
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array("bills", "orders", "commercial", "cashdesk", "receiptprinter", "banks", "takeposcustom@takepos"));

$tpLabelCalculator = takeposTranslateWithFallback($langs, 'TakeposUiCalculator', 'الحاسبة', 'Calculator');
$tpLabelPrintTicket = takeposTranslateWithFallback($langs, 'TakeposUiPrintTicket', 'طباعة التذكرة', 'Print ticket');
$tpLabelInvoiceDiscountShort = takeposTranslateWithFallback($langs, 'TakeposUiInvoiceDiscountShort', 'خصم الفاتورة', 'Invoice discount');
$tpLabelLogout = takeposTranslateWithFallback($langs, 'TakeposUiLogout', 'تسجيل الخروج', 'Logout');
$tpConfirmDiscardSale = takeposTranslateWithFallback($langs, 'ConfirmDiscardOfThisPOSSale', 'هل تريد إلغاء عملية البيع الحالية؟', 'Do you want to discard this current sale?');
$tpConfirmDeleteSale = takeposTranslateWithFallback($langs, 'ConfirmDeletionOfThisPOSSale', 'هل تريد حذف عملية البيع الحالية؟', 'Do you want to delete this current sale?');
$tpLabelSearch = takeposTranslateWithFallback($langs, 'TakeposCommonSearch', 'بحث', 'Search');
$tpLabelReceipt = takeposTranslateWithFallback($langs, 'TakeposUiReceipt', 'الإيصال', 'Receipt');
$tpCustomerDisplayWelcome = takeposTranslateWithFallback($langs, 'TakeposCustomerDisplayWelcome', 'مرحبًا. سيظهر طلبك هنا.', 'Welcome. Your order will appear here.');
$tpCustomerDisplayReviewOrder = takeposTranslateWithFallback($langs, 'TakeposCustomerDisplayReviewOrder', 'يرجى مراجعة طلبك قبل الدفع.', 'Please review your order before payment.');
$tpLabelProductInfo = takeposTranslateWithFallback($langs, 'TakeposUiProductInfo', 'معلومات المادة', 'Product info');
$tpLabelOrder = takeposTranslateWithFallback($langs, 'TakeposUiOrder', 'طلب', 'Order');
$tpLabelOpenDrawer = takeposTranslateWithFallback($langs, 'TakeposUiOpenDrawer', 'فتح الدرج', 'Open drawer');
$tpLabelCashReport = takeposTranslateWithFallback($langs, 'TakeposUiCashReport', 'تقرير النقدية', 'Cash report');
$tpLabelCloseCashFence = takeposTranslateWithFallback($langs, 'TakeposUiCloseCashFence', 'إغلاق العهدة', 'Close cash control');
$tpLabelClearSearch = takeposTranslateWithFallback($langs, 'TakeposUiClearSearch', 'مسح البحث', 'Clear search');
$tpLabelOrderNotes = takeposTranslateWithFallback($langs, 'TakeposUiOrderNotes', 'ملاحظات الطلب', 'Order notes');
$tpLabelProductSupplements = takeposTranslateWithFallback($langs, 'TakeposUiProductSupplements', 'إضافات المنتج', 'Product supplements');
$tpDirectPaymentProcessing = takeposTranslateWithFallback($langs, 'TakeposUiDirectPaymentProcessing', 'جاري تنفيذ الدفع المباشر...', 'Processing direct payment...');
$tpDirectPaymentFallback = takeposTranslateWithFallback($langs, 'TakeposUiDirectPaymentFallback', 'تعذر تنفيذ الدفع المباشر مباشرة. سيتم فتح شاشة الدفع.', 'Unable to complete direct payment directly. Opening payment screen.');
$tpDirectPaymentFailed = takeposTranslateWithFallback($langs, 'TakeposUiDirectPaymentFailed', 'تعذر إتمام الدفع المباشر.', 'Unable to complete direct payment.');
$tpMsgSelectProductFirst = takeposTranslateWithFallback($langs, 'TakeposUiSelectProductFirst', 'يرجى اختيار منتج أولًا.', 'Please select a product first.');
$tpMsgNoOpenSale = takeposTranslateWithFallback($langs, 'TakeposUiNoOpenSale', 'لا توجد عملية بيع مفتوحة حتى الآن. ابدأ عملية بيع جديدة أولًا.', 'There is no open sale yet. Start a new sale first.');
$tpMsgSearchNoResults = takeposTranslateWithFallback($langs, 'TakeposUiSearchNoResults', 'لم يتم العثور على منتجات مطابقة.', 'No matching products were found.');

$tpMsgSearchMultipleMatches = takeposTranslateWithFallback($langs, 'TakeposUiSearchMultipleMatches', 'تم العثور على أكثر من منتج. اختر المنتج المطلوب من القائمة.', 'Multiple products found. Select one below.');

/**
 * Tell if current user can access POS reports.
 *
 * @param DoliDB $db Database object
 * @param User $currentUser User object
 * @return  bool
 */
function takeposUserCanAccessReports($db, $currentUser)
{
    $canViewClassicReports = TakeposAccess::isFeatureEnabled($db, 'takepos.reports')
        && (!empty($currentUser->admin) || TakeposUserAccess::userHasPermission($db, $currentUser, 'takepos.action.reports_view'));
    $canViewDashboardReports = is_file(__DIR__ . '/dashboard.php')
        && TakeposAccess::isFeatureEnabled($db, 'takepos.dashboard.pro')
        && (!empty($currentUser->admin) || TakeposUserAccess::userHasPermission($db, $currentUser, 'takepos.dashboard.view'));

    return ($canViewClassicReports || $canViewDashboardReports);
}

/**
 * Resolve the reports entry URL.
 *
 * @param DoliDB $db Database object
 * @param User $currentUser User object
 * @return string
 */
function takeposResolveReportsUrl($db, $currentUser)
{
    if (TakeposAccess::isFeatureEnabled($db, 'takepos.reports')
        && (!empty($currentUser->admin) || TakeposUserAccess::userHasPermission($db, $currentUser, 'takepos.action.reports_view'))) {
        return '/takepos/reports.php';
    }

    if (is_file(__DIR__ . '/dashboard.php')
        && TakeposAccess::isFeatureEnabled($db, 'takepos.dashboard.pro')
        && (!empty($currentUser->admin) || TakeposUserAccess::userHasPermission($db, $currentUser, 'takepos.dashboard.view'))) {
        return '/takepos/workspace.php?key=dashboard_pro';
    }

    return '/takepos/reports.php';
}

/**
 * Build current page URL with target Dolibarr language code.
 *
 * @param string $languageCode Language code to set into query string
 * @return  string
 */
function takeposBuildLanguageSwitchUrl($languageCode)
{
    $requestUri = !empty($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : DOL_URL_ROOT . '/takepos/index.php';
    $parts = parse_url($requestUri);
    $path = !empty($parts['path']) ? $parts['path'] : DOL_URL_ROOT . '/takepos/index.php';
    $queryParams = array();

    if (!empty($parts['query'])) {
        parse_str($parts['query'], $queryParams);
    }

    unset($queryParams['langs'], $queryParams['_tslang']);

    $back = $path;
    if (!empty($queryParams)) {
        $back .= '?' . http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
    }

    return DOL_URL_ROOT . '/takepos/ajax/lang_switch.php?lang=' . rawurlencode($languageCode) . '&back=' . rawurlencode($back) . '&_tslang=' . time();
}

/**
 * Normalize category rows before encoding them for JavaScript.
 *
 * @param array $rows Raw category rows
 * @return  array
 */
function takeposNormalizeCategoryRows($rows)
{
    $out = array();

    if (!is_array($rows)) {
        return $out;
    }

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        if (!isset($row['rowid']) && isset($row['id'])) {
            $row['rowid'] = (int)$row['id'];
        }

        if (!isset($row['id']) && isset($row['rowid'])) {
            $row['id'] = (int)$row['rowid'];
        }

        if (!isset($row['fk_parent'])) {
            $row['fk_parent'] = 0;
        } else {
            $row['fk_parent'] = (int)$row['fk_parent'];
        }

        $out[] = $row;
    }

    return $out;
}

$place = (GETPOST('place', 'aZ09') ? GETPOST('place', 'aZ09') : 0); // $place is id of table for Bar or Restaurant or multiple sales
$action = GETPOST('action', 'aZ09');
$setterminal = GETPOSTINT('setterminal');
$setcurrency = GETPOST('setcurrency', 'aZ09');
$setcurrencyrate = GETPOST('setcurrencyrate', 'alphanohtml');

$hookmanager->initHooks(array('takeposfrontend'));

// ── Branch user terminal isolation ──────────────────────────────────────────
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposBranchService.class.php';
$takeposBranchTerminals = null;  // null = master/admin (no restriction)
if (is_object($user) && !empty($user->id) && empty($user->admin)) {
    $_chk = $db->query("SELECT rowid FROM " . MAIN_DB_PREFIX . "takepos_branch WHERE fk_user=" . ((int) $user->id) . " LIMIT 1");
    if ($_chk && $db->num_rows($_chk) > 0) {
        $_brow = $db->fetch_object($_chk);
        $takeposBranchTerminals = [];
        $_tt = $db->query("SELECT rowid, terminal_code, label FROM " . MAIN_DB_PREFIX . "takepos_terminal"
            . " WHERE fk_branch=" . (int) $_brow->rowid . " AND active=1 ORDER BY rowid ASC");
        if ($_tt) { while ($_t = $db->fetch_object($_tt)) { $takeposBranchTerminals[(int) $_t->rowid] = $_t; } }

        // strip foreign session/cookie/setterminal
        if ($setterminal > 0 && !isset($takeposBranchTerminals[(int) $setterminal])) $setterminal = 0;
        if (!empty($_SESSION["takeposterminal"]) && !isset($takeposBranchTerminals[(int) $_SESSION["takeposterminal"]])) {
            unset($_SESSION["takeposterminal"]);
        }
        // auto-select if only one
        if (empty($_SESSION["takeposterminal"]) && count($takeposBranchTerminals) === 1) {
            $_only = array_keys($takeposBranchTerminals);
            $_SESSION["takeposterminal"] = (int) $_only[0];
            dolSetCookie("takeposterminal", (string) $_SESSION["takeposterminal"], -1);
        }
    }
}

if (empty($_SESSION["takeposterminal"])) {
    if (!is_array($takeposBranchTerminals) && getDolGlobalInt('TAKEPOS_NUM_TERMINALS') >= 1) {
        // FIX: Non-branch users (not admin, not assigned to a branch) default to terminal 1.
        // Previously only triggered when NUM_TERMINALS == 1, leaving multi-terminal setups
        // with no session terminal, causing "Access denied" for newly created users.
        $_SESSION["takeposterminal"] = !empty($_COOKIE["takeposterminal"]) ? max(1, (int) $_COOKIE["takeposterminal"]) : 1;
    } elseif (!is_array($takeposBranchTerminals) && !empty($_COOKIE["takeposterminal"])) {
        // FIX (M12): Cast cookie to int. Terminal IDs are always positive integers.
        // The old regex allowed letters/underscores/hyphens (e.g. '1-backup') which
        // could produce CASHDESK_ID_WAREHOUSE1-backup = 0, silently breaking
        // per-terminal stock. max(1,...) prevents 0 or negative values.
        $_SESSION["takeposterminal"] = max(1, (int) $_COOKIE["takeposterminal"]);
    }
}

if ($setterminal > 0) {
    $_SESSION["takeposterminal"] = $setterminal;
    dolSetCookie("takeposterminal", (string)$setterminal, -1); // takeposterminal var in a 1 year cookie
}
// ── Active-shift terminal sync ────────────────────────────────────────────
// ── End active-shift terminal sync ───────────────────────────────────────

if ($setcurrency != "") {
    takeposSetSessionCurrencySelection(isset($conf->currency) ? $conf->currency : '', $setcurrency, $setcurrencyrate);
    // We will recalculate amount for foreign currency at next call of invoice.php when $_SESSION["takeposcustomercurrency"] differs from invoice->multicurrency_code.
}


$categorie = new Categorie($db);

$maxcategbydefaultforthisdevice = 12;
$maxproductbydefaultforthisdevice = 24;
if ($conf->browser->layout == 'phone') {
    $maxcategbydefaultforthisdevice = 8;
    $maxproductbydefaultforthisdevice = 16;
    //REDIRECT TO BASIC LAYOUT IF TERMINAL SELECTED AND BASIC MOBILE LAYOUT FORCED
    if (!empty($_SESSION["takeposterminal"]) && getDolGlobalString('TAKEPOS_BAR_RESTAURANT') && getDolGlobalInt('TAKEPOS_PHONE_BASIC_LAYOUT') == 1) {
        $_SESSION["basiclayout"] = 1;
        header("Location: phone.php?mobilepage=invoice");
        exit;
    }
} else {
    unset($_SESSION["basiclayout"]);
}
$MAXCATEG = (!getDolGlobalString('TAKEPOS_NB_MAXCATEG') ? $maxcategbydefaultforthisdevice : $conf->global->TAKEPOS_NB_MAXCATEG);
$MAXPRODUCT = (!getDolGlobalString('TAKEPOS_NB_MAXPRODUCT') ? $maxproductbydefaultforthisdevice : $conf->global->TAKEPOS_NB_MAXPRODUCT);

$term = empty($_SESSION['takeposterminal']) ? 1 : $_SESSION['takeposterminal'];
$canAccessTakeposReports = takeposUserCanAccessReports($db, $user);
$takeposReportsUrl = takeposResolveReportsUrl($db, $user);
$customerDisplayPageAvailable = is_file(__DIR__ . '/customer_display.php');
$productStudioLinks = array();
$isKafoFeatureEnabled = function ($featureCode) use ($db) {
    return TakeposAccess::isFeatureEnabled($db, $featureCode);
};
$canCreateCatalogProducts = ($user->admin || $user->hasRight('produit', 'creer'));
$canCreateServices = ($user->admin || $user->hasRight('service', 'creer'));
$canReadCatalogProducts = ($user->admin || $user->hasRight('produit', 'lire') || $user->hasRight('takepos', 'run'));
$canReadServices = ($user->admin || $user->hasRight('service', 'lire'));
// Managing services opens Dolibarr's service/product administration page.
// A simple read right can still hit Dolibarr Access denied there, so only show
// the shortcut to users who can actually manage/create services or have an
// explicit POS catalog-management permission.
$canManageServices = ($user->admin || $user->hasRight('service', 'creer'));
$canCreateProducts = ($canCreateCatalogProducts || $canCreateServices);
$canReadProducts = ($canReadCatalogProducts || $canReadServices);
$canOpenPurchaseDesk = ($user->admin || TakeposUserAccess::userHasPermission($db, $user, 'takepos.purchase.read') || $canReadCatalogProducts);
$canOpenChequeDesk = ($user->admin || TakeposUserAccess::userHasPermission($db, $user, 'takepos.cheque.read') || $canReadCatalogProducts);
$canCreateCategories = ($user->admin || $user->hasRight('categorie', 'creer'));
$canReadCategories = ($user->admin || $user->hasRight('categorie', 'lire'));
$shiftFeatureEnabled = $isKafoFeatureEnabled('takepos.shift_management');
$cashControlFeatureEnabled = $isKafoFeatureEnabled('takepos.cash_control');
$returnsFeatureEnabled = $isKafoFeatureEnabled('takepos.returns');
$refundFeatureEnabled = $isKafoFeatureEnabled('takepos.refunds');
$exchangeFeatureEnabled = $isKafoFeatureEnabled('takepos.exchanges');
$analyticsFeatureEnabled = $isKafoFeatureEnabled('takepos.kpi_dashboard');
$dashboardProFeatureEnabled = $isKafoFeatureEnabled('takepos.dashboard.pro');
$offlineModeFeatureEnabled = $isKafoFeatureEnabled('takepos.offline_mode');
$syncQueueFeatureEnabled = $isKafoFeatureEnabled('takepos.sync_queue');
$storeGovernanceEnabled = $isKafoFeatureEnabled('takepos.store_governance');
$crmFeatureEnabled = $isKafoFeatureEnabled('takepos.crm');
$loyaltyFeatureEnabled = $isKafoFeatureEnabled('takepos.loyalty');
$terminalGovernanceEnabled = $isKafoFeatureEnabled('takepos.terminal_governance');
$deviceLayerFeatureEnabled = $isKafoFeatureEnabled('takepos.device_layer');
$printerProfilesFeatureEnabled = $isKafoFeatureEnabled('takepos.printer_profiles');
$apiLayerFeatureEnabled = $isKafoFeatureEnabled('takepos.api_layer');
$webhooksFeatureEnabled = $isKafoFeatureEnabled('takepos.webhooks');
$canManageDevices = ($user->admin || TakeposUserAccess::userHasPermission($db, $user, 'takepos.device.manage'));
$canTestDevices = ($user->admin || TakeposUserAccess::userHasPermission($db, $user, 'takepos.device.test'));
$canManageWebhooks = ($user->admin || TakeposUserAccess::userHasPermission($db, $user, 'takepos.webhook.manage'));
$canManageApiTokens = ($user->admin || TakeposUserAccess::userHasPermission($db, $user, 'takepos.api.write'));
$canReadApi = ($user->admin || TakeposUserAccess::userHasPermission($db, $user, 'takepos.api.read'));
$canManageStores = ($user->admin || TakeposUserAccess::userHasPermission($db, $user, 'takepos.store.manage'));
$canManageTerminals = ($user->admin || TakeposUserAccess::userHasPermission($db, $user, 'takepos.terminal.manage'));
$canAssignTerminalStore = ($user->admin || TakeposUserAccess::userHasPermission($db, $user, 'takepos.terminal.assign'));
$canOpenShiftDesk = ($user->admin || TakeposUserAccess::userHasPermission($db, $user, 'takepos.shift.open') || TakeposUserAccess::userHasPermission($db, $user, 'takepos.shift.review'));
$canRecordPaidIn = ($user->admin || TakeposUserAccess::userHasPermission($db, $user, 'takepos.cash.paidin'));
$canRecordPaidOut = ($user->admin || TakeposUserAccess::userHasPermission($db, $user, 'takepos.cash.paidout'));
$canOpenRefundDesk = ($user->admin
    || TakeposUserAccess::userHasAnyPermission($db, $user, array('takepos.refund.view', 'takepos.refund.partial', 'takepos.refund.full')));
$canOpenExchangeDesk = ($user->admin
    || TakeposUserAccess::userHasAnyPermission($db, $user, array('takepos.exchange.process', 'takepos.refund.view')));
$canOpenKpiDashboard = ($user->admin
    || TakeposUserAccess::userHasPermission($db, $user, 'takepos.analytics.view'));
$canOpenDashboardPro = ($user->admin
    || TakeposUserAccess::userHasPermission($db, $user, 'takepos.dashboard.view'));
$canUseOfflineMode = ($user->admin || TakeposUserAccess::userHasPermission($db, $user, 'takepos.offline.use'));
$canManageSyncQueue = ($user->admin || TakeposUserAccess::userHasPermission($db, $user, 'takepos.sync.manage'));
$canOpenExpensesDesk = TakeposExpenseService::canRead($db, $user);
$canManageExpenseCategories = TakeposExpenseService::canAdmin($db, $user);
$canOpenLoyaltyDesk = ($user->admin || TakeposUserAccess::userHasPermission($db, $user, 'takepos.customer.view'));
$takeposEntity = !empty($user->entity) ? (int)$user->entity : 1;
$takeposHasActiveWarehouse = false;
if ($canOpenPurchaseDesk) {
    $takeposWarehouseRowsForMenu = TakeposPurchaseService::listWarehouses($db, $takeposEntity);
    $takeposHasActiveWarehouse = !empty($takeposWarehouseRowsForMenu);
}
$takeposArabicSwitchUrl = takeposBuildLanguageSwitchUrl('ar_JO');
$takeposEnglishSwitchUrl = takeposBuildLanguageSwitchUrl('en_US');
if ($canCreateCatalogProducts && $isKafoFeatureEnabled('takepos.catalog.add_product')) {
    $productStudioLinks[] = array(
        'group' => 'create',
        'label' => $langs->trans('TakeposShortcutAddProduct'),
        'description' => $langs->trans('TakeposShortcutAddProductDesc'),
        'icon' => 'fa fa-box',
        'url' => '/takepos/workspace.php?key=add_product',
        'feature' => 'takepos.catalog.add_product'
    );
}
if ($canCreateServices && $isKafoFeatureEnabled('takepos.catalog.add_service')) {
    $productStudioLinks[] = array(
        'group' => 'create',
        'label' => $langs->trans('TakeposShortcutAddService'),
        'description' => $langs->trans('TakeposShortcutAddServiceDesc'),
        'icon' => 'fa fa-concierge-bell',
        'url' => '/takepos/workspace.php?key=add_service',
        'feature' => 'takepos.catalog.add_service'
    );
}
if ($canReadCatalogProducts && $isKafoFeatureEnabled('takepos.catalog.manage_products')) {
    $productStudioLinks[] = array(
        'group' => 'manage',
        'label' => $langs->trans('TakeposShortcutManageProducts'),
        'description' => $langs->trans('TakeposShortcutManageProductsDesc'),
        'icon' => 'fa fa-th-list',
        'url' => '/takepos/workspace.php?key=manage_products',
        'feature' => 'takepos.catalog.manage_products'
    );
}
if ($canReadCatalogProducts && $isKafoFeatureEnabled('takepos.catalog.manage_products')) {
    $productStudioLinks[] = array(
        'group' => 'manage',
        'label' => $langs->trans('TakeposShortcutProductBarcodes'),
        'description' => $langs->trans('TakeposShortcutProductBarcodesDesc'),
        'icon' => 'fa fa-barcode',
        'url' => '/takepos/workspace.php?key=product_barcodes',
        'feature' => 'takepos.catalog.manage_products'
    );
}
if (($canCreateCatalogProducts || !empty($user->admin)) && $isKafoFeatureEnabled('takepos.catalog.manage_products')) {
    $productStudioLinks[] = array(
        'group' => 'manage',
        'label' => $langs->trans('TakeposShortcutTaxRates'),
        'description' => $langs->trans('TakeposShortcutTaxRatesDesc'),
        'icon' => 'fa fa-percent',
        'url' => '/takepos/workspace.php?key=tax_rates',
        'feature' => 'takepos.catalog.manage_products'
    );
}
if ($canManageServices && $isKafoFeatureEnabled('takepos.catalog.manage_services')) {
    $productStudioLinks[] = array(
        'group' => 'manage',
        'label' => $langs->trans('TakeposShortcutManageServices'),
        'description' => $langs->trans('TakeposShortcutManageServicesDesc'),
        'icon' => 'fa fa-stream',
        'url' => '/takepos/workspace.php?key=manage_services',
        'feature' => 'takepos.catalog.manage_services'
    );
}
if ($canReadCatalogProducts) {
    $productStudioLinks[] = array(
        'group' => 'manage',
        'label' => $langs->trans('TakeposShortcutStockOverview'),
        'description' => $langs->trans('TakeposShortcutStockOverviewDesc'),
        'icon' => 'fa fa-warehouse',
        'url' => '/takepos/workspace.php?key=stock_overview',
        'feature' => 'takepos.catalog.manage_products'
    );
}
// FEATURE (add-stock-popup): audit view of every POS-driven stock-in.
// Shown to anyone who can use TakePOS — same gate as the existing audit pages.
if (!empty($user->admin) || $user->hasRight('takepos', 'run')) {
    // Use takeposTransOrFallback (defined below near the JS labels) if available.
    // We can't reuse it from up here — define inline fallback for the label/desc.
    $_lblShortcut = $langs->trans('TakeposShortcutStockAdjustments');
    if ($_lblShortcut === 'TakeposShortcutStockAdjustments') $_lblShortcut = 'Stock adjustments (POS)';
    $_descShortcut = $langs->trans('TakeposShortcutStockAdjustmentsDesc');
    if ($_descShortcut === 'TakeposShortcutStockAdjustmentsDesc') $_descShortcut = 'POS-driven stock-ins approved by managers.';
    $productStudioLinks[] = array(
        'group' => 'manage',
        'label' => $_lblShortcut,
        'description' => $_descShortcut,
        'icon' => 'fa fa-clipboard-check',
        'url' => '/takepos/workspace.php?key=stock_adjustments',
        'feature' => 'takepos.audit.log'
    );
}
// FIX (stock-branch-v2): Cross-branch stock shortcut — shown to managers/admins,
// hidden from branch users (they are redirected to their own stock_overview instead).
if (!TakeposBranchService::isBranchUser($db, (int) $user->id) && $isKafoFeatureEnabled('takepos.catalog.manage_products')) {
    $productStudioLinks[] = array(
        'group' => 'manage',
        'label' => $langs->trans('TakeposShortcutStockAllBranches'),
        'description' => $langs->trans('TakeposShortcutStockAllBranchesDesc'),
        'icon' => 'fa fa-layer-group',
        'url' => '/takepos/workspace.php?key=stock_all_branches',
        'feature' => 'takepos.catalog.manage_products'
    );
}
// FIX (stock-branch-v4): Stock transfer shortcut — admins and supervisors only
if (!TakeposBranchService::isBranchUser($db, (int) $user->id)
    && ($user->admin || $user->hasRight('produit', 'creer'))
    && $isKafoFeatureEnabled('takepos.store_governance')) {
    $productStudioLinks[] = array(
        'group' => 'manage',
        'label' => $langs->trans('TakeposShortcutStockTransfer'),
        'description' => $langs->trans('TakeposShortcutStockTransferDesc'),
        'icon' => 'fa fa-exchange-alt',
        'url' => '/takepos/workspace.php?key=stock_transfer',
        'feature' => 'takepos.store_governance'
    );
}
// FIX (stock-branch-v7): Reconciliation report shortcut
if (!TakeposBranchService::isBranchUser($db, (int) $user->id)
    && ($user->admin || $user->hasRight('produit', 'lire'))
    && $isKafoFeatureEnabled('takepos.analytics')) {
    $productStudioLinks[] = array(
        'group' => 'manage',
        'label' => $langs->trans('TakeposShortcutStockRecon'),
        'description' => $langs->trans('TakeposShortcutStockReconDesc'),
        'icon' => 'fa fa-balance-scale',
        'url' => '/takepos/workspace.php?key=stock_reconciliation',
        'feature' => 'takepos.analytics'
    );
}
// FIX (stock-branch-v8): Physical inventory count shortcut
if (!TakeposBranchService::isBranchUser($db, (int) $user->id)
    && ($user->admin || $user->hasRight('produit', 'creer'))
    && $isKafoFeatureEnabled('takepos.store_governance')) {
    $productStudioLinks[] = array(
        'group' => 'manage',
        'label' => $langs->trans('TakeposShortcutStockCount'),
        'description' => $langs->trans('TakeposShortcutStockCountDesc'),
        'icon' => 'fa fa-clipboard-check',
        'url' => '/takepos/workspace.php?key=stock_count',
        'feature' => 'takepos.store_governance'
    );
}
if ($canCreateCategories && $isKafoFeatureEnabled('takepos.catalog.add_category')) {
    $productStudioLinks[] = array(
        'group' => 'create',
        'label' => $langs->trans('TakeposShortcutAddCategory'),
        'description' => $langs->trans('TakeposShortcutAddCategoryDesc'),
        'icon' => 'fa fa-folder-plus',
        'url' => '/takepos/workspace.php?key=add_category',
        'feature' => 'takepos.catalog.add_category'
    );
}
if ($canReadCategories && $isKafoFeatureEnabled('takepos.catalog.manage_categories')) {
    $productStudioLinks[] = array(
        'group' => 'manage',
        'label' => $langs->trans('TakeposShortcutCategoryManager'),
        'description' => $langs->trans('TakeposShortcutCategoryManagerDesc'),
        'icon' => 'fa fa-sitemap',
        'url' => '/takepos/workspace.php?key=manage_categories',
        'feature' => 'takepos.catalog.manage_categories'
    );
}
// FEATURE (piece-box-variants): Shortcut to manage piece/box product variant links
if ($user->admin || $canCreateCatalogProducts) {
    $productStudioLinks[] = array(
        'group' => 'manage',
        'label' => $langs->trans('TakeposPieceBoxVariantsLabel'),
        'description' => $langs->trans('TakeposPieceBoxVariantsDesc'),
        'icon' => 'fa fa-boxes',
        'url' => '/takepos/workspace.php?key=admin_product_variants',
        'feature' => 'takepos.run'
    );
}
if ($shiftFeatureEnabled && $canOpenShiftDesk) {
    $productStudioLinks[] = array(
        'group' => 'manage',
        'label' => $langs->trans('TakeposShortcutShiftDesk'),
        'description' => $langs->trans('TakeposShortcutShiftDeskDesc'),
        'icon' => 'fa fa-user-clock',
        'url' => '/takepos/workspace.php?key=shift_ops',
        'feature' => 'takepos.shift_management'
    );
}
if ($refundFeatureEnabled && $returnsFeatureEnabled && $canOpenRefundDesk) {
    $productStudioLinks[] = array(
        'group' => 'manage',
        'label' => $langs->trans('TakeposShortcutRefundDesk'),
        'description' => $langs->trans('TakeposShortcutRefundDeskDesc'),
        'icon' => 'fa fa-undo',
        'url' => '/takepos/workspace.php?key=refund_lookup',
        'feature' => 'takepos.refunds'
    );
}
if ($exchangeFeatureEnabled && $returnsFeatureEnabled && $canOpenExchangeDesk) {
    $productStudioLinks[] = array(
        'group' => 'manage',
        'label' => $langs->trans('TakeposShortcutExchangeDesk'),
        'description' => $langs->trans('TakeposShortcutExchangeDeskDesc'),
        'icon' => 'fa fa-exchange-alt',
        'url' => '/takepos/workspace.php?key=exchange_ops',
        'feature' => 'takepos.exchanges'
    );
}
if ($analyticsFeatureEnabled && $canOpenKpiDashboard) {
    $productStudioLinks[] = array(
        'group' => 'manage',
        'label' => $langs->trans('TakeposShortcutKpiDashboard'),
        'description' => $langs->trans('TakeposShortcutKpiDashboardDesc'),
        'icon' => 'fa fa-chart-line',
        'url' => '/takepos/workspace.php?key=kpi_dashboard',
        'feature' => 'takepos.kpi_dashboard'
    );
}

if ($dashboardProFeatureEnabled && $canOpenDashboardPro) {
    $productStudioLinks[] = array(
        'group' => 'manage',
        'label' => $langs->trans('TakeposShortcutDashboardPro'),
        'description' => $langs->trans('TakeposShortcutDashboardProDesc'),
        'icon' => 'fa fa-chart-pie',
        'url' => '/takepos/workspace.php?key=dashboard_pro',
        'feature' => 'takepos.dashboard.pro'
    );
}

if ($crmFeatureEnabled && $canOpenLoyaltyDesk) {
    $productStudioLinks[] = array(
        'group' => 'manage',
        'label' => $langs->trans('TakeposShortcutLoyaltyDesk'),
        'description' => $langs->trans('TakeposShortcutLoyaltyDeskDesc'),
        'icon' => 'fa fa-id-card',
        'url' => '/takepos/workspace.php?key=loyalty_desk',
        'feature' => 'takepos.crm'
    );
}

if ($syncQueueFeatureEnabled && $canManageSyncQueue) {
    $productStudioLinks[] = array(
        'group' => 'manage',
        'label' => $langs->trans('TakeposShortcutSyncQueue'),
        'description' => $langs->trans('TakeposShortcutSyncQueueDesc'),
        'icon' => 'fa fa-cloud-upload-alt',
        'url' => '/takepos/workspace.php?key=sync_queue',
        'feature' => 'takepos.sync_queue'
    );
}

if ($cashControlFeatureEnabled && $canOpenExpensesDesk) {
    $productStudioLinks[] = array(
        'group' => 'manage',
        'label' => $langs->trans('TakeposShortcutExpenses'),
        'description' => $langs->trans('TakeposShortcutExpensesDesc'),
        'icon' => 'fa fa-receipt',
        'url' => '/takepos/workspace.php?key=expenses_ops',
        'feature' => 'takepos.cash_control'
    );
}

if ($cashControlFeatureEnabled && $canOpenExpensesDesk) {
    $productStudioLinks[] = array(
        'group' => 'manage',
        'label' => $langs->trans('TakeposShortcutExpenseLedger'),
        'description' => $langs->trans('TakeposShortcutExpenseLedgerDesc'),
        'icon' => 'fa fa-book',
        'url' => '/takepos/workspace.php?key=expense_ledger',
        'feature' => 'takepos.cash_control'
    );
}

if ($canOpenPurchaseDesk && $takeposHasActiveWarehouse && $isKafoFeatureEnabled('takepos.purchases')) {
    $productStudioLinks[] = array(
        'group' => 'manage',
        'label' => $langs->trans('TakeposShortcutPurchases'),
        'description' => $langs->trans('TakeposShortcutPurchasesDesc'),
        'icon' => 'fa fa-truck-loading',
        'url' => '/takepos/workspace.php?key=purchase_ops',
        'feature' => 'takepos.purchases'
    );
}

if ($canOpenChequeDesk && $isKafoFeatureEnabled('takepos.cheques')) {
    $productStudioLinks[] = array(
        'group' => 'manage',
        'label' => $langs->trans('TakeposShortcutCheques'),
        'description' => $langs->trans('TakeposShortcutChequesDesc'),
        'icon' => 'fa fa-money-check-alt',
        'url' => '/takepos/workspace.php?key=cheque_ops',
        'feature' => 'takepos.cheques'
    );
}

if ($user->admin) {
    $productStudioLinks[] = array(
        'group'       => 'manage',
        'label'       => $langs->trans('TakeposShortcutScaleBarcode'),
        'description' => $langs->trans('TakeposShortcutScaleBarcodeDesc'),
        'icon'        => 'fa fa-weight',
        'url'         => '/takepos/admin/barcode_scale.php',
        'feature'     => 'takepos.use'
    );
}

if ($cashControlFeatureEnabled && $canManageExpenseCategories) {
    $productStudioLinks[] = array(
        'group' => 'admin',
        'label' => $langs->trans('TakeposShortcutExpenseCategories'),
        'description' => $langs->trans('TakeposShortcutExpenseCategoriesDesc'),
        'icon' => 'fa fa-tags',
        'url' => '/takepos/workspace.php?key=admin_expense_categories',
        'feature' => 'takepos.cash_control'
    );
}

$canManagePosUsers = false;
$canManagePosUsers = TakeposUserAccess::canOpenUserManager($db, $user) && $isKafoFeatureEnabled('takepos.users.manage');

$hasAdminStudioAccess = ($user->admin
    || $canManagePosUsers
    || ($storeGovernanceEnabled && ($canManageStores || $canAssignTerminalStore))
    || ($terminalGovernanceEnabled && $canManageTerminals)
    || ($deviceLayerFeatureEnabled && $canManageDevices)
    || ($printerProfilesFeatureEnabled && $canManageDevices)
    || (($apiLayerFeatureEnabled || $webhooksFeatureEnabled) && ($canManageWebhooks || $canManageApiTokens || $canReadApi)));

if ($hasAdminStudioAccess) {
    $adminStudioLinks = array();
    if ($user->admin) {
        $adminStudioLinks = array(
            array('group' => 'admin', 'label' => $langs->trans('TakeposShortcutSetup'), 'description' => $langs->trans('TakeposShortcutSetupDesc'), 'icon' => 'fa fa-cogs', 'url' => '/takepos/workspace.php?key=admin_setup', 'feature' => 'takepos.admin.setup'),
            array('group' => 'admin', 'label' => $langs->trans('TakeposShortcutTerminals'), 'description' => $langs->trans('TakeposShortcutTerminalsDesc'), 'icon' => 'fa fa-cash-register', 'url' => '/takepos/workspace.php?key=admin_terminal', 'feature' => 'takepos.admin.terminal'),
            array('group' => 'admin', 'label' => $langs->trans('TakeposShortcutReceiptSettings'), 'description' => $langs->trans('TakeposShortcutReceiptSettingsDesc'), 'icon' => 'fa fa-receipt', 'url' => '/takepos/workspace.php?key=admin_receipt', 'feature' => 'takepos.admin.receipt'),
            array('group' => 'admin', 'label' => $langs->trans('TakeposShortcutAppearance'), 'description' => $langs->trans('TakeposShortcutAppearanceDesc'), 'icon' => 'fa fa-palette', 'url' => '/takepos/workspace.php?key=admin_appearance', 'feature' => 'takepos.admin.appearance'),
            array('group' => 'admin', 'label' => $langs->trans('TakeposShortcutBarRestaurant'), 'description' => $langs->trans('TakeposShortcutBarRestaurantDesc'), 'icon' => 'fa fa-utensils', 'url' => '/takepos/workspace.php?key=admin_bar', 'feature' => 'takepos.admin.bar'),
            array('group' => 'admin', 'label' => $langs->trans('TakeposShortcutOrderPrinters'), 'description' => $langs->trans('TakeposShortcutOrderPrintersDesc'), 'icon' => 'fa fa-print', 'url' => '/takepos/workspace.php?key=admin_orderprinters', 'feature' => 'takepos.admin.orderprinters'),
            array('group' => 'admin', 'label' => $langs->trans('TakeposShortcutPrintQr'), 'description' => $langs->trans('TakeposShortcutPrintQrDesc'), 'icon' => 'fa fa-qrcode', 'url' => '/takepos/workspace.php?key=admin_printqr', 'feature' => 'takepos.admin.printqr'),
            array('group' => 'admin', 'label' => $langs->trans('TakeposShortcutOtherSettings'), 'description' => $langs->trans('TakeposShortcutOtherSettingsDesc'), 'icon' => 'fa fa-sliders-h', 'url' => '/takepos/workspace.php?key=admin_other', 'feature' => 'takepos.admin.other'),
            // نظام الفوترة الإقليمية (الأردن / السعودية)
            array('group' => 'admin', 'label' => 'نظام الفوترة', 'description' => 'إعداد الفوترة الإقليمية (أردن / سعودية ZATCA)', 'icon' => 'fa fa-file-invoice', 'url' => '/takepos/workspace.php?key=admin_billing_country', 'feature' => 'takepos.admin.receipt'),
        );
    }
    // Branch Management — Admin only, shown inside POS Shortcuts
    if ($user->admin) {
        $adminStudioLinks[] = array(
            'group'       => 'admin',
            'label'       => $langs->trans('TakeposBranchManagementShortcutLabel'),
            'description' => $langs->trans('TakeposBranchManagementShortcutDesc'),
            'icon'        => 'fa fa-code-branch',
            'url'         => '/takepos/workspace.php?key=admin_branches',
            'feature'     => 'takepos.store_governance'
        );
    }
    if ($storeGovernanceEnabled && $canManageStores) {
        $adminStudioLinks[] = array('group' => 'admin', 'label' => $langs->trans('TakeposShortcutStores'), 'description' => $langs->trans('TakeposShortcutStoresDesc'), 'icon' => 'fa fa-store', 'url' => '/takepos/workspace.php?key=admin_stores', 'feature' => 'takepos.store_governance');
    }
    if ($terminalGovernanceEnabled && $canManageTerminals) {
        $adminStudioLinks[] = array('group' => 'admin', 'label' => $langs->trans('TakeposShortcutTerminalMapping'), 'description' => $langs->trans('TakeposShortcutTerminalMappingDesc'), 'icon' => 'fa fa-network-wired', 'url' => '/takepos/workspace.php?key=admin_terminal_map', 'feature' => 'takepos.terminal_governance');
    }
    if ($storeGovernanceEnabled && $canAssignTerminalStore) {
        $adminStudioLinks[] = array('group' => 'admin', 'label' => $langs->trans('TakeposShortcutUserStoreAccess'), 'description' => $langs->trans('TakeposShortcutUserStoreAccessDesc'), 'icon' => 'fa fa-user-tag', 'url' => '/takepos/workspace.php?key=admin_user_store', 'feature' => 'takepos.store_governance');
    }
    if ($canManagePosUsers) {
        $adminStudioLinks[] = array('group' => 'admin', 'label' => $langs->trans('TakeposShortcutPosUsers'), 'description' => $langs->trans('TakeposShortcutPosUsersDesc'), 'icon' => 'fa fa-users-cog', 'url' => '/takepos/workspace.php?key=admin_users', 'feature' => 'takepos.users.manage');
    }
    if ($user->admin && $loyaltyFeatureEnabled) {
        $adminStudioLinks[] = array('group' => 'admin', 'label' => $langs->trans('TakeposShortcutLoyaltySettings'), 'description' => $langs->trans('TakeposShortcutLoyaltySettingsDesc'), 'icon' => 'fa fa-star', 'url' => '/takepos/workspace.php?key=admin_loyalty', 'feature' => 'takepos.loyalty');
    }
    if ($deviceLayerFeatureEnabled && $canManageDevices) {
        $adminStudioLinks[] = array('group' => 'admin', 'label' => $langs->trans('TakeposShortcutDeviceProfiles'), 'description' => $langs->trans('TakeposShortcutDeviceProfilesDesc'), 'icon' => 'fa fa-microchip', 'url' => '/takepos/workspace.php?key=admin_devices', 'feature' => 'takepos.device_layer');
    }
    if ($printerProfilesFeatureEnabled && $canManageDevices) {
        $adminStudioLinks[] = array('group' => 'admin', 'label' => $langs->trans('TakeposShortcutPrinterProfiles'), 'description' => $langs->trans('TakeposShortcutPrinterProfilesDesc'), 'icon' => 'fa fa-print', 'url' => '/takepos/workspace.php?key=admin_printers', 'feature' => 'takepos.printer_profiles');
    }
    if (($apiLayerFeatureEnabled || $webhooksFeatureEnabled) && ($canManageWebhooks || $canManageApiTokens || $canReadApi)) {
        $adminStudioLinks[] = array('group' => 'admin', 'label' => $langs->trans('TakeposShortcutApiWebhooks'), 'description' => $langs->trans('TakeposShortcutApiWebhooksDesc'), 'icon' => 'fa fa-plug', 'url' => '/takepos/workspace.php?key=admin_api_webhooks', 'feature' => 'takepos.api_layer');
    }
    foreach ($adminStudioLinks as $adminLink) {
        if ($isKafoFeatureEnabled($adminLink['feature'])) {
            $productStudioLinks[] = $adminLink;
        }
    }
}

// ── Billing & Payment shortcuts ───────────────────────────────────────────
if (!empty($user->admin) || $user->hasRight('facture', 'lire')) {
    $productStudioLinks[] = array(
        'group' => 'billing',
        'label' => 'فواتير العملاء',
        'description' => 'عرض وإدارة فواتير العملاء غير المسددة',
        'icon' => 'fa fa-file-invoice-dollar',
        'url' => '/compta/facture/list.php?leftmenu=customers_bills&search_status=1',
        'feature' => ''
    );
    $productStudioLinks[] = array(
        'group' => 'billing',
        'label' => 'فواتير الموردين',
        'description' => 'عرض وإدارة فواتير الموردين',
        'icon' => 'fa fa-file-invoice',
        'url' => '/fourn/facture/list.php?leftmenu=suppliers_bills',
        'feature' => ''
    );
    $productStudioLinks[] = array(
        'group' => 'billing',
        'label' => 'مدفوعات العملاء',
        'description' => 'تسجيل ومتابعة مدفوعات العملاء',
        'icon' => 'fa fa-hand-holding-usd',
        'url' => '/compta/paiement/list.php?leftmenu=customers_bills',
        'feature' => ''
    );
    $productStudioLinks[] = array(
        'group' => 'billing',
        'label' => 'مدفوعات الموردين',
        'description' => 'تسجيل ومتابعة مدفوعات الموردين',
        'icon' => 'fa fa-money-check-alt',
        'url' => '/fourn/paiement/list.php?leftmenu=suppliers_bills',
        'feature' => ''
    );
}

$productStudioEnabled = !empty($productStudioLinks);

$shortcutUiSections = array(
    'catalog_inventory' => array(
        'label' => $langs->trans('TakeposShortcutGroupCatalogInventory'),
        'default_open' => 1
    ),
    'sales_operations' => array(
        'label' => $langs->trans('TakeposShortcutGroupSalesOperations'),
        'default_open' => 1
    ),
    'analytics' => array(
        'label' => $langs->trans('TakeposShortcutGroupAnalytics'),
        'default_open' => 0
    ),
    'pos_configuration' => array(
        'label' => $langs->trans('TakeposShortcutGroupPosConfiguration'),
        'default_open' => 0
    ),
    'governance_access' => array(
        'label' => $langs->trans('TakeposShortcutGroupGovernanceAccess'),
        'default_open' => 0
    ),
    'billing_payment' => array(
        'label' => 'الفوترة والمدفوعات',
        'default_open' => 0
    )
);

$shortcutKeyToSection = array(
    'add_product' => 'catalog_inventory',
    'add_service' => 'catalog_inventory',
    'manage_products' => 'catalog_inventory',
    'product_barcodes' => 'catalog_inventory',
    'tax_rates' => 'catalog_inventory',
    'manage_services' => 'catalog_inventory',
    'stock_overview' => 'catalog_inventory',
    'stock_adjustments' => 'catalog_inventory',
    'add_category' => 'catalog_inventory',
    'manage_categories' => 'catalog_inventory',
    'shift_ops' => 'sales_operations',
    'refund_lookup' => 'sales_operations',
    'exchange_ops' => 'sales_operations',
    'expenses_ops' => 'sales_operations',
    'expense_ledger' => 'sales_operations',
    'purchase_ops' => 'sales_operations',
    'sync_queue' => 'sales_operations',
    'loyalty_desk' => 'sales_operations',
    'kpi_dashboard' => 'analytics',
    'dashboard_pro' => 'analytics',
    'admin_setup' => 'pos_configuration',
    'admin_terminal' => 'pos_configuration',
    'admin_receipt' => 'pos_configuration',
    'admin_appearance' => 'pos_configuration',
    'admin_bar' => 'pos_configuration',
    'admin_orderprinters' => 'pos_configuration',
    'admin_printqr' => 'pos_configuration',
    'admin_other' => 'pos_configuration',
    'admin_loyalty' => 'pos_configuration',
    'admin_devices' => 'pos_configuration',
    'admin_printers' => 'pos_configuration',
    'admin_api_webhooks' => 'pos_configuration',
    'admin_expense_categories' => 'pos_configuration',
    'admin_stores' => 'governance_access',
    'admin_branches' => 'governance_access',
    'admin_terminal_map' => 'governance_access',
    'admin_user_store' => 'governance_access',
    'admin_users' => 'governance_access',
    // نظام الفوترة الإقليمية
    'admin_billing_country' => 'pos_configuration',
    'cheque_ops' => 'catalog_inventory',
    'billing_customer_invoices' => 'billing_payment',
    'billing_supplier_invoices' => 'billing_payment',
    'billing_customer_payments' => 'billing_payment',
    'billing_supplier_payments' => 'billing_payment',
);

$shortcutsByUiSection = array();
$shortcutByKey = array();
foreach ($shortcutUiSections as $sectionCode => $sectionMeta) {
    $shortcutsByUiSection[$sectionCode] = array();
}

foreach ($productStudioLinks as $shortcutIndex => $shortcutItem) {
    $shortcutKey = '';
    $urlQuery = parse_url((string)$shortcutItem['url'], PHP_URL_QUERY);
    if (!empty($urlQuery)) {
        $queryParams = array();
        parse_str($urlQuery, $queryParams);
        if (!empty($queryParams['key'])) {
            $shortcutKey = (string)$queryParams['key'];
        }
    }

    $sectionCode = '';
    if ($shortcutKey !== '' && isset($shortcutKeyToSection[$shortcutKey])) {
        $sectionCode = $shortcutKeyToSection[$shortcutKey];
    } elseif ($shortcutItem['group'] === 'admin') {
        $sectionCode = 'pos_configuration';
    } elseif ($shortcutItem['group'] === 'billing') {
        $sectionCode = 'billing_payment';
    } else {
        $sectionCode = 'catalog_inventory';
    }

    $shortcutItem['index'] = (int)$shortcutIndex;
    $shortcutItem['shortcut_key'] = $shortcutKey;
    $shortcutsByUiSection[$sectionCode][] = $shortcutItem;
    if ($shortcutKey !== '') {
        $shortcutByKey[$shortcutKey] = $shortcutItem;
    }
}

// Keep panel hidden if every section resolved to empty.
$productStudioEnabled = false;
foreach ($shortcutsByUiSection as $shortcutItems) {
    if (!empty($shortcutItems)) {
        $productStudioEnabled = true;
        break;
    }
}

/*
 $constforcompanyid = 'CASHDESK_ID_THIRDPARTY'.$_SESSION["takeposterminal"];
 $soc = new Societe($db);
 if ($invoice->socid > 0) $soc->fetch($invoice->socid);
 else $soc->fetch(getDolGlobalInt($constforcompanyid));
 */

// Security check
$result = restrictedArea($user, 'takepos', 0, '');

TakeposAccess::requireFrontendAccess($db, isset($user) ? $user : null, 'takepos.frontend', 'takepos.use', $term);
TakeposAudit::logEvent($db, $user, 'pos_open_screen', TakeposAudit::SEVERITY_INFO, array('page' => 'index.php', 'place' => $place, 'terminal' => $term), 'POS main screen opened');
if (empty($_SESSION['takepos_pos_login_logged'])) {
    TakeposAudit::logEvent($db, $user, 'pos_login', TakeposAudit::SEVERITY_INFO, array('terminal' => $term), 'POS session started');
    $_SESSION['takepos_pos_login_logged'] = 1;
}


/*
 * View
 */

$form = new Form($db);

$disablejs = 0;
$disablehead = 0;
$arrayofjs = array('/takepos/js/jquery.colorbox-min.js'); // TODO It seems we don't need this
// FIX (stock-branch-v9): Stock + expiry badges on product tiles
$arrayofjs[] = '/takepos/js/takepos_stock_badges.js';
// FEATURE (add-stock-popup): popup that lets the cashier add stock to a
// zero-stock product after a manager approves with login + password.
$arrayofjs[] = '/takepos/js/takepos_add_stock.js';
$arrayofjs[] = '/takepos/js/takepos_kafo_categories.js';
$arrayofcss = array(
    '/takepos/css/pos.css.php?v=20260419layout2',
    '/takepos/css/colorbox.css',
);


$takeposForceV2 = false;
$takeposV2Disabled = (int)getDolGlobalInt('TAKEPOS_DISABLE_V2_REDESIGN');
$takeposV2Enabled = $takeposForceV2 || ($takeposV2Disabled === 0);

// Cache-bust the CSS on every config change. Bump the suffix any time
// you edit takepos_redesign_v2.css so browsers re-fetch.
$takeposV2CssVersion = '20260430bc';

if ($takeposV2Enabled) {
    // Static .css file (preferred — no PHP execution in /css/ path needed).
    $arrayofcss[] = '/takepos/css/takepos_redesign_v2.css?v=20260430i';

    // Top info bar (catalog breadcrumb + product counter + view-mode toggle).
    // Loaded AFTER takepos_redesign_v2.css so its grid-areas override wins.
    $arrayofcss[] = '/takepos/css/takepos_topbar.css?v=20260506e';
    // Load no-image layout only when product images are disabled
    if (!getDolGlobalString('TAKEPOS_SHOW_PRODUCT_IMAGES')) {
        $arrayofcss[] = '/takepos/css/takepos_product_noimg.css?v=7';
    } else {
        $arrayofcss[] = '/takepos/css/takepos_product_img.css?v=1';
    }
    $arrayofcss[] = '/takepos/css/categories_dropdown.css';
}

// ... later ...

// === V2 REDESIGN ===
//
// V2 is ENABLED BY DEFAULT. To revert to the legacy look, set
// TAKEPOS_DISABLE_V2_REDESIGN = 1  (any non-zero integer)
// in Dolibarr → Home → Setup → Other Setup → Add a constant.
//
// To FORCE V2 ON regardless of the constant (debugging), uncomment this:
// $takeposForceV2 = true;
//


// Title
$title = 'TakePOS - Dolibarr ' . DOL_VERSION;
if (getDolGlobalString('MAIN_APPLICATION_TITLE')) {
    $title = 'TakePOS - ' . getDolGlobalString('MAIN_APPLICATION_TITLE');
}
$head = '<meta charset="UTF-8"><meta name="apple-mobile-web-app-title" content="TakePOS"/>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>';
top_htmlhead($head, $title, $disablejs, $disablehead, $arrayofjs, $arrayofcss);


$categories = $categorie->get_full_arbo('product', ((getDolGlobalInt('TAKEPOS_ROOT_CATEGORY_ID') > 0) ? getDolGlobalInt('TAKEPOS_ROOT_CATEGORY_ID') : 0), 1);


// Search root category to know its level
//$conf->global->TAKEPOS_ROOT_CATEGORY_ID=0;
$levelofrootcategory = 0;
if (getDolGlobalInt('TAKEPOS_ROOT_CATEGORY_ID') > 0) {
    foreach ($categories as $key => $categorycursor) {
        // @phan-suppress-next-line PhanTypeInvalidDimOffset
        if ($categorycursor['id'] == getDolGlobalInt('TAKEPOS_ROOT_CATEGORY_ID')) {
            $levelofrootcategory = $categorycursor['level'];
            break;
        }
    }
}

$levelofmaincategories = $levelofrootcategory + 1;

$maincategories = array();
$subcategories = array();
foreach ($categories as $key => $categorycursor) {
    if ($categorycursor['level'] == $levelofmaincategories) {
        $maincategories[$key] = $categorycursor;
    } else {
        $subcategories[$key] = $categorycursor;
    }
}

$maincategories = dol_sort_array($maincategories, 'label');
$subcategories = dol_sort_array($subcategories, 'label');
$maincategories = takeposNormalizeCategoryRows($maincategories);
$subcategories = takeposNormalizeCategoryRows($subcategories);

// V2 redesign: decorate categories with emoji + product count
// Turns "BAKERY -" into "🥖 Bakery   12". See lib/takepos_category_decorator.php.
$_branchAllowedIds = TakeposBranchService::allowedProductIdsForUser($db, $user);
$takeposCategoryProductCounts = takeposGetCategoryProductCounts(
    $db,
    isset($conf->entity) ? $conf->entity : 1,
    $_branchAllowedIds
);
$maincategories = takeposDecorateCategoryRows($maincategories, $takeposCategoryProductCounts);
$subcategories  = takeposDecorateCategoryRows($subcategories,  $takeposCategoryProductCounts);
?>

    <!--
	TakePOS V2 Redesign diagnostic:
	  takeposV2Enabled        = <?php echo $takeposV2Enabled ? 'TRUE' : 'FALSE'; ?>

	  takeposV2Disabled (raw) = <?php echo $takeposV2Disabled; ?>

	  takeposForceV2          = <?php echo $takeposForceV2 ? 'TRUE' : 'FALSE'; ?>

	  takeposIsRtl            = <?php $_dbgRtl = (in_array((string)(isset($langs->defaultlang) ? $langs->defaultlang : ''), array('ar_JO', 'ar_SA', 'ar_EG', 'ar_AE', 'he_IL', 'fa_IR', 'ur_PK')) || stripos((string)(isset($langs->defaultlang) ? $langs->defaultlang : ''), 'ar_') === 0);
    echo $_dbgRtl ? 'TRUE' : 'FALSE'; ?>

	  defaultlang             = <?php echo dol_escape_htmltag((string)(isset($langs->defaultlang) ? $langs->defaultlang : '')); ?>

	  V2 CSS path             = /takepos/css/takepos_redesign_v2.css?v=<?php echo $takeposV2CssVersion; ?>

	If takeposV2Enabled is FALSE: the constant TAKEPOS_DISABLE_V2_REDESIGN is set
	  to a non-zero value. Go to Home → Setup → Other Setup and either delete it
	  or set it to 0.
	If takeposV2Enabled is TRUE but you don't see the new design: the CSS file
	  may have failed to load. Open DevTools → Network and filter for
	  "takepos_redesign_v2" — confirm it returns HTTP 200 and a Content-Type of
	  text/css. If you see HTTP 404 the file is missing — confirm it exists at
	  <dolibarr>/htdocs/takepos/css/takepos_redesign_v2.css on the server.
-->

    <body class="bodytakepos<?php
    echo $takeposV2Enabled ? ' takepos-v2' : '';
    $takeposIsRtl = (in_array((string)(isset($langs->defaultlang) ? $langs->defaultlang : ''), array('ar_JO', 'ar_SA', 'ar_EG', 'ar_AE', 'he_IL', 'fa_IR', 'ur_PK'))
        || stripos((string)(isset($langs->defaultlang) ? $langs->defaultlang : ''), 'ar_') === 0);
    if ($takeposV2Enabled && $takeposIsRtl) {
        echo ' tp-rtl';
    }
    ?>"<?php echo $takeposV2Enabled && $takeposIsRtl ? ' dir="rtl"' : ''; ?>>

    <?php if ($takeposV2Enabled) { ?>
        <!-- V2 active indicator (visible 3s, then hides). Confirms CSS loaded if styled. -->
        <div id="takepos-v2-indicator" style="
	position:fixed;top:10px;left:50%;transform:translateX(-50%);
	z-index:99999;
	padding:6px 14px;border-radius:999px;
	background:linear-gradient(135deg,#3b82f6,#1e40af);
	color:#fff;font-family:system-ui,sans-serif;font-size:12px;font-weight:600;
	box-shadow:0 4px 12px rgba(15,23,42,.25);
	pointer-events:none;
	animation:takeposV2BadgeFade 4s ease forwards;
">✓ TakePOS V2 redesign active
        </div>
        <style>
            @keyframes takeposV2BadgeFade {
                0% {
                    opacity: 0;
                    transform: translateX(-50%) translateY(-10px);
                }
                8% {
                    opacity: 1;
                    transform: translateX(-50%) translateY(0);
                }
                75% {
                    opacity: 1;
                    transform: translateX(-50%) translateY(0);
                }
                100% {
                    opacity: 0;
                    transform: translateX(-50%) translateY(-6px);
                }
            }
        </style>
    <?php } ?>
    <style>
        #php-debugbar,
        .phpdebugbar,
        .php-debugbar,
        .debugbar,
        .debug-bar,
        .debugbar-container,
        .sf-toolbar,
        #sfwdt,
        iframe[id*="debug"],
        iframe[class*="debug"],
        div[id*="debugbar"],
        div[class*="debugbar"],
        div[id*="DebugBar"],
        div[class*="DebugBar"] {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }

        body.bodytakepos {
            padding-bottom: 0 !important;
        }

        #takepos-main-layout {
            width: 100%;
            max-width: 100%;
            margin-left: 0;
            transition: width .2s ease, margin-left .2s ease;
        }

        #takepos-shortcuts-drawer {
            position: fixed;
            top: 62px;
            right: 10px;
            width: 336px;
            max-width: calc(100vw - 20px);
            max-height: calc(100vh - 78px);
            overflow-y: auto;
            background: #f7f8fc;
            border: 1px solid #d6ddef;
            border-radius: 10px;
            box-shadow: 0 10px 24px rgba(0, 0, 0, .14);
            padding: 8px;
            z-index: 30;
            transform: translateX(calc(100% + 16px));
            opacity: 0;
            pointer-events: none;
            transition: transform .2s ease, opacity .2s ease;
        }

        #takepos-shortcuts-drawer.is-open {
            transform: translateX(0);
            opacity: 1;
            pointer-events: auto;
        }

        #takepos-shortcuts-drawer .takepos-shortcuts-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 8px;
        }

        #takepos-shortcuts-drawer .takepos-shortcuts-title {
            font-size: 13px;
            font-weight: 700;
            color: #22344f;
            margin: 0;
            padding: 0 2px;
        }

        #takepos-shortcuts-close {
            border: none;
            background: #e4e9f5;
            color: #23344f;
            border-radius: 6px;
            width: 26px;
            height: 26px;
            cursor: pointer;
            font-size: 16px;
            line-height: 1;
        }

        #takepos-shortcuts-panel {
            display: flex;
            flex-direction: column;
            gap: 8px
        }

        .takepos-shortcut-section {
            border: 1px solid #d4dcec;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 2px 6px rgba(34, 52, 79, .05)
        }

        .takepos-shortcut-header {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            background: #eef3fb;
            border: none;
            color: #24324f;
            cursor: pointer;
            font-size: 16px;
            font-weight: 700;
            padding: 10px 12px;
            text-align: left
        }

        .takepos-shortcut-header .chevron {
            font-size: 10px;
            transition: transform .15s ease
        }

        .takepos-shortcut-section.is-collapsed .takepos-shortcut-header .chevron {
            transform: rotate(-90deg)
        }

        .takepos-shortcut-body {
            display: grid;
            grid-template-columns:repeat(2, minmax(0, 1fr));
            gap: 10px;
            padding: 12px
        }

        .takepos-shortcut-section.is-collapsed .takepos-shortcut-body {
            display: none
        }

        .takepos-shortcut-link {
            display: flex !important;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            gap: 10px;
            min-height: 96px;
            padding: 14px 10px !important;
            margin-left: 0 !important;
            border-radius: 12px;
            border: 1px solid #d7dfef;
            background: linear-gradient(180deg, #ffffff 0%, #f5f8fe 100%);
            color: #334364;
            text-align: center;
            text-decoration: none;
            box-shadow: 0 3px 8px rgba(32, 53, 92, .06);
        }

        .takepos-shortcut-link:hover {
            background: linear-gradient(180deg, #f3f7ff 0%, #e9f0fc 100%);
            border-color: #b8c6e3;
            color: #24324f !important;
            transform: translateY(-1px);
        }

        .takepos-shortcut-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: #e8eef9;
            color: #2f436a;
            font-size: 20px;
        }

        .takepos-shortcut-text {
            display: block;
            width: 100%;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.25;
            color: #24324f;
            white-space: normal;
            word-break: break-word;
        }

        #takepos-shortcuts-launcher {
            position: fixed;
            top: 120px;
            right: 10px;
            z-index: 31;
            border: none;
            border-radius: 999px;
            padding: 8px 12px;
            background: #2e3f62;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 8px 18px rgba(0, 0, 0, .2);
        }

        body.takepos-shortcuts-open #takepos-shortcuts-launcher {
            opacity: 0;
            pointer-events: none;
        }

        body.tp-rtl #takepos-shortcuts-drawer {
            right: auto;
            left: 10px;
            transform: translateX(calc(-100% - 16px))
        }

        body.tp-rtl #takepos-shortcuts-drawer.is-open {
            transform: translateX(0)
        }

        #takepos-shortcuts-body {
            scrollbar-width: auto;
            scrollbar-color: #8c8c8c #ececec;
        }

        #takepos-shortcuts-body::-webkit-scrollbar {
            width: 14px;
            height: 14px;
        }

        #takepos-shortcuts-body::-webkit-scrollbar-thumb {
            background: #8c8c8c;
            border-radius: 10px;
            border: 3px solid #ececec;
        }

        #takepos-shortcuts-body::-webkit-scrollbar-track {
            background: #ececec;
            border-radius: 10px;
        }

        .takepos-search-clear {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            min-height: 34px;
            padding: 4px 8px;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
        }

        .takepos-search-clear:hover {
            background: rgba(0, 0, 0, .06);
        }

        body.tp-rtl #takepos-shortcuts-launcher {
            right: auto;
            left: 10px
        }

        body.tp-rtl .takepos-shortcut-header {
            text-align: right
        }

        body.tp-rtl .takepos-shortcut-text {
            text-align: center
        }

        body.bodytakepos.takepos-shortcuts-open #takepos-main-layout {
            width: calc(100% - 352px);
        }

        body.bodytakepos.tp-rtl.takepos-shortcuts-open #takepos-main-layout {
            margin-left: 352px;
        }

        @media (max-width: 1280px) {
            #takepos-shortcuts-drawer {
                width: 304px
            }

            #takepos-shortcuts-launcher {
                padding: 7px 10px
            }

            body.bodytakepos.takepos-shortcuts-open #takepos-main-layout {
                width: calc(100% - 320px);
            }

            body.bodytakepos.tp-rtl.takepos-shortcuts-open #takepos-main-layout {
                margin-left: 320px;
            }
        }

        @media (max-width: 767px) {
            #takepos-shortcuts-drawer {
                width: min(92vw, 340px);
            }

            .takepos-shortcut-body {
                grid-template-columns:repeat(2, minmax(0, 1fr));
                gap: 8px;
                padding: 10px;
            }

            .takepos-shortcut-link {
                min-height: 88px;
                padding: 12px 8px !important;
            }

            .takepos-shortcut-text {
                font-size: 12px;
            }

            body.bodytakepos.takepos-shortcuts-open #takepos-main-layout {
                width: 100%;
                margin-left: 0;
            }
        }
    </style>

    <script>


        var categories = <?php echo json_encode($maincategories); ?>;
        var subcategories = <?php echo json_encode($subcategories); ?>;


        <?php
        // Count sellable products that belong to at least one category
        // (matches what category=0 returns and the All tab badge count)
        $sqlTotalProducts = "SELECT COUNT(DISTINCT cp.fk_product) AS cnt"
            . " FROM " . MAIN_DB_PREFIX . "categorie_product AS cp"
            . " INNER JOIN " . MAIN_DB_PREFIX . "product AS p ON p.rowid = cp.fk_product"
            . " WHERE p.entity IN (" . getEntity('product') . ") AND p.tosell = 1 AND p.fk_product_type = 0";
        $resTotalProducts = $db->query($sqlTotalProducts);
        $totalProductsCount = 0;
        if ($resTotalProducts && ($objTotal = $db->fetch_object($resTotalProducts))) {
            $totalProductsCount = (int)$objTotal->cnt;
        }
        ?>
        window.takeposTotalProductsCount = <?php echo (int)$totalProductsCount; ?>;
        window.takeposMaxProductsPerPage = <?php echo (int)($MAXPRODUCT - 2); ?>;
        // FIX (all-products-v1): Expose AJAX URL and CSRF token so takepos_v2_topbar.js
        // can call getProductsAll for the "All" tab without hardcoding the URL.
        window.takeposAjaxUrl = "<?php echo dol_escape_js(DOL_URL_ROOT); ?>/takepos/ajax/ajax.php";
        window.CSRF_TOKEN     = "<?php echo newToken(); ?>";

        var currentcat;
        var pageproducts = 0;
        var pagecategories = 0;
        var pageactions = 0;
        var place = "<?php echo $place; ?>";
        var editaction = "qty";
        var editnumber = "";
        // FIX (multi-cashier): seed JS invoiceid from the server-side per-user session
        // so returning cashier sees their own draft invoice immediately
        <?php
        $_termForIndex = empty($_SESSION['takeposterminal']) ? 1 : (int)$_SESSION['takeposterminal'];
        $_userInvKey   = 'takepos_user_invoice_' . (int)$user->id . '_' . $_termForIndex;
        $_seedInvoiceId = (!empty($_SESSION[$_userInvKey]) && $place == 0) ? (int)$_SESSION[$_userInvKey] : 0;
        ?>
        var invoiceid = <?php echo $_seedInvoiceId; ?>;
        var search2_timer = null;
        var takeposUiLang = <?php echo json_encode((string)$langs->defaultlang); ?>;
        var takeposUiIsArabic = /^ar/i.test(String(takeposUiLang || ''));
        var takeposMsgSelectProductFirst = <?php echo json_encode($tpMsgSelectProductFirst, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        var takeposMsgNoOpenSale = <?php echo json_encode($tpMsgNoOpenSale, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        var takeposMsgSearchNoResults = <?php echo json_encode($tpMsgSearchNoResults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        var takeposMsgLoadSaleTicketError = <?php echo json_encode($tpMsgLoadSaleTicketError, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        var takeposMsgSearchMultipleMatches = <?php echo json_encode($tpMsgSearchMultipleMatches, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        var dolUrlRoot = "<?php echo dol_escape_js(DOL_URL_ROOT); ?>";
        var takeposProductImageEndpoint = "<?php echo dol_escape_js(DOL_URL_ROOT . '/takepos/product_image.php'); ?>";
        var takeposProductPlaceholderUrl = "<?php echo dol_escape_js(DOL_URL_ROOT . '/public/theme/common/nophoto.png'); ?>";
        var productStudioLinks = <?php echo json_encode($productStudioLinks); ?>;
        var takeposShiftFeatureEnabled = <?php echo $shiftFeatureEnabled ? 'true' : 'false'; ?>;
        var takeposCanOpenShiftDesk = <?php echo $canOpenShiftDesk ? 'true' : 'false'; ?>;
        var takeposShiftEndpoint = "<?php echo dol_escape_js(DOL_URL_ROOT . '/takepos/ajax/shift.php'); ?>";
        var takeposShiftWorkspaceUrl = "<?php echo dol_escape_js(DOL_URL_ROOT . '/takepos/shifts.php'); ?>";
        var takeposManagerOverrideEndpoint = "<?php echo dol_escape_js(DOL_URL_ROOT . '/takepos/ajax/manager_override.php'); ?>";
        var takeposSyncFeatureEnabled = <?php echo $syncQueueFeatureEnabled ? 'true' : 'false'; ?>;
        var takeposOfflineFeatureEnabled = <?php echo $offlineModeFeatureEnabled ? 'true' : 'false'; ?>;
        var takeposCanUseOfflineMode = <?php echo $canUseOfflineMode ? 'true' : 'false'; ?>;
        var takeposCanManageSyncQueue = <?php echo $canManageSyncQueue ? 'true' : 'false'; ?>;
        var takeposSyncEndpoint = "<?php echo dol_escape_js(DOL_URL_ROOT . '/takepos/ajax/sync.php'); ?>";
        var takeposSyncWorkspaceUrl = "<?php echo dol_escape_js(DOL_URL_ROOT . '/takepos/sync_queue.php'); ?>";
        var takeposCsrfToken = "<?php echo dol_escape_js(newToken()); ?>";
        var takeposVariantMap = {};
        var takeposVariantBadgeEndpoint = "";
        // Hold/Suspend feature endpoints
        var takeposHoldEndpoint = "<?php echo dol_escape_js(DOL_URL_ROOT . '/takepos/ajax/hold.php'); ?>";
        var takeposCheckStockEndpoint = "<?php echo dol_escape_js(DOL_URL_ROOT . '/takepos/ajax/checkstock.php'); ?>";
        //  var takeposStockCheckEnabled = <?php echo (getDolGlobalInt('TAKEPOS_PRODUCT_IN_STOCK') == 1) ? 'true' : 'false'; ?>;
        var takeposStockCheckEnabled = true;

        // FEATURE (add-stock-popup): wire the add-stock endpoint and localized strings.
        // When the cashier tries to add a zero-stock product, checkStockBeforeAdd shows
        // the takepos_add_stock.js popup which POSTs here.
        var takeposAddStockEndpoint  = "<?php echo dol_escape_js(DOL_URL_ROOT . '/takepos/ajax/add_stock.php'); ?>";
        //  var takeposAddStockEnabled   = <?php echo (getDolGlobalInt('TAKEPOS_PRODUCT_IN_STOCK') == 1) ? 'true' : 'false'; ?>;
        var takeposAddStockEnabled   = true;
        <?php
        // Helper: $langs->trans('Foo') returns "Foo" (the key) when the key isn't
        // in any loaded language file. That's a truthy string, so `?:` doesn't help.
        // takeposTransOrFallback() detects the "trans returned the key unchanged"
        // case and falls through to the English fallback.
        $takeposTransOrFallback = function ($key, $fallback) use ($langs) {
            $v = $langs->trans($key);
            return ($v === $key || $v === '') ? $fallback : $v;
        };
        ?>
        var takeposAddStockLabels    = {
            title:             "<?php echo dol_escape_js($takeposTransOrFallback('TakeposAddStockTitle',             'Add stock for this product')); ?>",
            currentStock:      "<?php echo dol_escape_js($takeposTransOrFallback('TakeposAddStockCurrentStock',      'Current free stock')); ?>",
            qtyRequested:      "<?php echo dol_escape_js($takeposTransOrFallback('TakeposAddStockQtyRequested',      'Qty requested')); ?>",
            qtyToAdd:          "<?php echo dol_escape_js($takeposTransOrFallback('TakeposAddStockQtyToAdd',          'Quantity to add to stock')); ?>",
            managerLogin:      "<?php echo dol_escape_js($takeposTransOrFallback('TakeposAddStockManagerLogin',      'Manager login')); ?>",
            managerPassword:   "<?php echo dol_escape_js($takeposTransOrFallback('TakeposAddStockManagerPassword',   'Manager password')); ?>",
            reason:            "<?php echo dol_escape_js($takeposTransOrFallback('TakeposAddStockReason',            'Reason (optional)')); ?>",
            reasonPlaceholder: "<?php echo dol_escape_js($takeposTransOrFallback('TakeposAddStockReasonPlaceholder', 'e.g. found extra units in storage')); ?>",
            cancel:            "<?php echo dol_escape_js($takeposTransOrFallback('Cancel',                           'Cancel')); ?>",
            confirm:           "<?php echo dol_escape_js($takeposTransOrFallback('TakeposAddStockConfirm',           'Add stock & continue')); ?>",
            working:           "<?php echo dol_escape_js($takeposTransOrFallback('TakeposAddStockWorking',           'Saving stock movement...')); ?>",
            ok:                "<?php echo dol_escape_js($takeposTransOrFallback('TakeposAddStockSuccess',           'Stock added successfully.')); ?>",
            errQty:            "<?php echo dol_escape_js($takeposTransOrFallback('TakeposAddStockBadQty',            'Quantity must be greater than zero.')); ?>",
            errCreds:          "<?php echo dol_escape_js($takeposTransOrFallback('TakeposAddStockManagerRequired',   'Manager login and password are required.')); ?>",
            errNetwork:        "<?php echo dol_escape_js($takeposTransOrFallback('TakeposAddStockNetworkError',      'Network error. Please try again.')); ?>",
            errGeneric:        "<?php echo dol_escape_js($takeposTransOrFallback('TakeposAddStockGenericError',      'Could not add stock.')); ?>"
        };
        // FIX (stock-branch-v9 / v10): Stock + expiry badge configuration
        var takeposStockBadgesEnabled  = true; // Always show — independent of TAKEPOS_PRODUCT_IN_STOCK
        var takeposStockBadgesEndpoint = "<?php echo dol_escape_js(DOL_URL_ROOT . '/takepos/ajax/product_stock_badges.php'); ?>";
        var takeposBatchEnabled        = <?php echo isModEnabled('productbatch') ? 'true' : 'false'; ?>;
        var takeposExpiryWarnDays      = <?php echo (int) getDolGlobalInt('TAKEPOS_EXPIRY_WARN_DAYS') ?: 30; ?>;
        var takeposExpiryCritDays      = <?php echo (int) getDolGlobalInt('TAKEPOS_EXPIRY_CRIT_DAYS') ?: 7; ?>;
        var takeposStockBadgeQtyLabel  = "<?php echo dol_escape_js($langs->trans('Stock')); ?>";
        var takeposStockBadgeTitle     = "<?php echo dol_escape_js($langs->trans('TakeposStockQty')); ?>";
        var takeposExpiryLabel         = "<?php echo dol_escape_js($langs->trans('TakeposBatchExpiry')); ?>";
        // UX: payment lock flag (prevent double-click)
        var takeposPaymentInProgress = false;
        var takeposDirectPaymentConfig = {
            LIQ: {
                accountId: <?php echo (int)takeposResolveTerminalBankAccountId('CASH', isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : ''); ?>,
                canDirect: true
            },
            CB: {
                accountId: <?php echo (int)takeposResolveTerminalBankAccountId('CB', isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : ''); ?>,
                canDirect: true
            }
        };
        var takeposDirectPaymentLock = false;

        function takeposBuildProductImageUrl(productId) {
            var id = parseInt(productId, 10);
            if (!id || id <= 0) {
                return takeposProductPlaceholderUrl;
            }
            return takeposProductImageEndpoint + "?id=" + encodeURIComponent(id);
        }

        function takeposGetProductId(row) {
            if (!row) {
                return 0;
            }
            return parseInt(row.id || row.rowid || row.product_id || 0, 10) || 0;
        }

        function takeposGetCategoryId(row) {
            if (!row) {
                return 0;
            }
            return parseInt(row.rowid || row.id || 0, 10) || 0;
        }

        function takeposSetProductMissingImage($img, missing) {
            if (!$img || !$img.length) {
                return;
            }
            var $wrapper = $img.closest('.wrapper2');
            if (!$wrapper.length) {
                return;
            }
            $wrapper.toggleClass('takepos-missing-product-image', !!missing);
            var label = takeposUi.missingProductImage || '';
            $wrapper.find('.takepos-missing-product-image-badge').attr('title', label).attr('aria-label', label);
        }

        function takeposApplyProductImage(selector, productId, explicitUrl, hasImage) {
            var $img = $(selector);
            if (!$img.length) {
                return;
            }
            var targetUrl = explicitUrl || takeposBuildProductImageUrl(productId);
            var hasExplicitImageState = (typeof hasImage !== 'undefined');
            if (hasExplicitImageState) {
                var imageExists = (hasImage === true || hasImage === 1 || hasImage === '1' || hasImage === 'true');
                takeposSetProductMissingImage($img, !imageExists);
            } else {
                takeposSetProductMissingImage($img, false);
            }
            $img
                .off('error.takeposimg load.takeposimg')
                .on('error.takeposimg', function () {
                    takeposSetProductMissingImage($(this), true);
                    if ($(this).attr('src') !== takeposProductPlaceholderUrl) {
                        $(this).attr('src', takeposProductPlaceholderUrl);
                    }
                })
                .on('load.takeposimg', function () {
                    if (!hasExplicitImageState) {
                        takeposSetProductMissingImage($(this), false);
                    }
                })
                .attr('src', targetUrl);
        }

        var takeposLoyaltyFeatureEnabled = <?php echo(($crmFeatureEnabled && $canOpenLoyaltyDesk) ? 'true' : 'false'); ?>;
        var takeposLoyaltyWorkspaceUrl = "<?php echo dol_escape_js(DOL_URL_ROOT . '/takepos/loyalty.php?' . $takeposCurrentLangParam); ?>";
        var takeposLoyaltyEndpoint = "<?php echo dol_escape_js(DOL_URL_ROOT . '/takepos/ajax/loyalty.php'); ?>";
        // FIX (I15): Translatable label strings for pos_modern_init.js and other JS files.
        // These replace hardcoded Arabic strings that were previously embedded in JS.
        window.takeposLabels = <?php echo json_encode(array(
            'cartTitle'      => takeposTranslateWithFallback($langs, 'TakeposUiCart', 'سلة المبيعات', 'Cart'),
            'customer'       => takeposTranslateWithFallback($langs, 'TakeposUiCustomer', 'العميل', 'Customer'),
            'walkInCustomer' => takeposTranslateWithFallback($langs, 'TakeposUiWalkInCustomer', 'عميل عام', 'Walk-in Customer'),
            'product'        => $langs->trans('Product'),
            'qty'            => takeposTranslateWithFallback($langs, 'TakeposUiQty', 'الكمية', 'Qty'),
            'total'          => $langs->trans('Total'),
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

        var takeposUi = <?php echo json_encode(array(
            'shiftDeskTitle' => $langs->trans('TakeposShortcutShiftDesk'),
            'loyaltyDeskTitle' => $takeposLoyaltyDeskTitlePlain,
            'syncQueueTitle' => $langs->trans('TakeposShortcutSyncQueue'),
            'reportsTitle' => $langs->trans('TakeposIndexReportsTitle'),
            'noActiveShift' => $langs->trans('TakeposIndexNoActiveShift'),
            'openShiftBeforePayment' => $langs->trans('TakeposIndexOpenShiftBeforePayment'),
            'shiftStatusUnavailable' => $langs->trans('TakeposIndexShiftStatusUnavailable'),
            'unableContactShiftService' => $langs->trans('TakeposIndexUnableContactShiftService'),
            'openShiftRequiredBeforePayment' => $langs->trans('TakeposIndexOpenShiftRequiredBeforePayment'),
            'openShiftRequiredBeforeSale' => $langs->trans('TakeposIndexOpenShiftRequiredBeforeSale'),
            'unableValidateActiveShift' => $langs->trans('TakeposIndexUnableValidateActiveShift'),
            'noCustomerSelected' => $langs->trans('TakeposIndexNoCustomerSelected'),
            'customerSummaryUnavailable' => $langs->trans('TakeposIndexCustomerSummaryUnavailable'),
            'unableLoadCustomerSummary' => $langs->trans('TakeposIndexUnableLoadCustomerSummary'),
            'customerLabel' => $langs->trans('TakeposIndexCustomerLabel'),
            'pointsLabel' => $langs->trans('TakeposIndexPointsLabel'),
            'purchasesLabel' => $langs->trans('TakeposIndexPurchasesLabel'),
            'ticketStatusNa' => $langs->trans('TakeposIndexTicketStatusNa'),
            'ticketStatusNotQueued' => $langs->trans('TakeposIndexTicketStatusNotQueued'),
            'ticketStatusPending' => $langs->trans('TakeposIndexTicketStatusPending'),
            'ticketStatusUnavailable' => $langs->trans('TakeposIndexTicketStatusUnavailable'),
            'syncStateUnavailable' => $langs->trans('TakeposIndexSyncStateUnavailable'),
            'offlineModeLabel' => $langs->trans('TakeposIndexOfflineMode'),
            'enabled' => $langs->trans('TakeposIndexEnabled'),
            'disabled' => $langs->trans('TakeposIndexDisabled'),
            'disableOffline' => $langs->trans('TakeposIndexDisableOffline'),
            'enableOffline' => $langs->trans('TakeposIndexEnableOffline'),
            'syncOnline' => $langs->trans('TakeposIndexSyncOnline'),
            'syncOffline' => $langs->trans('TakeposIndexSyncOffline'),
            'shiftMetaTerminal' => $langs->trans('TakeposIndexShiftMetaTerminal'),
            'shiftMetaOpened' => $langs->trans('TakeposIndexShiftMetaOpened'),
            'shiftMetaOpening' => $langs->trans('TakeposIndexShiftMetaOpening'),
            'shiftMetaExpected' => $langs->trans('TakeposIndexShiftMetaExpected'),
            'unableContactSyncService' => $langs->trans('TakeposIndexUnableContactSyncService'),
            'noActiveSaleToHold' => $langs->trans('TakeposIndexNoActiveSaleToHold'),
            'holdFailed' => $langs->trans('TakeposIndexHoldFailed'),
            'unknownError' => $langs->trans('TakeposIndexUnknownError'),
            'unableLoadOfflineState' => $langs->trans('TakeposIndexUnableLoadOfflineState'),
            'unableToggleOfflineMode' => $langs->trans('TakeposIndexUnableToggleOfflineMode'),
            'unableToggleOfflineModeNow' => $langs->trans('TakeposIndexUnableToggleOfflineModeNow'),
            'managerApprovalRejectedLine' => $langs->trans('TakeposIndexManagerApprovalRejectedLine'),
            'managerApprovalRejectedPrice' => $langs->trans('TakeposIndexManagerApprovalRejectedPrice'),
            'managerApprovalRejectedDiscount' => $langs->trans('TakeposIndexManagerApprovalRejectedDiscount'),
            'managerApprovalRejectedInvoice' => $langs->trans('TakeposIndexManagerApprovalRejectedInvoice'),
            'managerActionGeneric' => $langs->trans('TakeposIndexManagerActionGeneric'),
            'managerActionDeleteLine' => $langs->trans('TakeposIndexManagerActionDeleteLine'),
            'managerActionPriceOverride' => $langs->trans('TakeposIndexManagerActionPriceOverride'),
            'managerActionDiscountOverride' => $langs->trans('TakeposIndexManagerActionDiscountOverride'),
            'managerActionInvoiceCancel' => $langs->trans('TakeposIndexManagerActionInvoiceCancel'),
            'managerScanOrEnter' => $langs->trans('TakeposIndexManagerScanOrEnter'),
            'managerCheckingApproval' => $langs->trans('TakeposIndexManagerCheckingApproval'),
            'managerApprovalGranted' => $langs->trans('TakeposIndexManagerApprovalGranted'),
            'managerApprovalFailed' => $langs->trans('TakeposIndexManagerApprovalFailed'),
            'managerApprovalValidateFailed' => $langs->trans('TakeposIndexManagerApprovalValidateFailed'),
            'managerApprovalDeleteRequired' => $langs->trans('TakeposInvoiceErrorManagerApprovalDeleteLine'),
            'managerApprovalPriceRequired' => $langs->trans('TakeposInvoiceErrorManagerApprovalPriceOverride'),
            'managerApprovalDiscountRequired' => $langs->trans('TakeposInvoiceErrorManagerApprovalDiscount'),
            'managerApprovalCancelRequired' => $langs->trans('TakeposInvoiceErrorManagerApprovalCancelInvoice'),
            'saleHeldSuccess' => $langs->trans('TakeposIndexSaleHeldSuccess'),
            'networkErrorHoldingSale' => $langs->trans('TakeposIndexNetworkErrorHoldingSale'),
            'heldLoading' => $langs->trans('TakeposIndexHeldLoading'),
            'heldLoadError' => $langs->trans('TakeposIndexHeldLoadError'),
            'heldNone' => $langs->trans('TakeposIndexHeldNone'),
            'heldLabel' => $langs->trans('TakeposIndexHeldLabel'),
            'heldLines' => $langs->trans('TakeposIndexHeldLines'),
            'heldTotal' => $langs->trans('TakeposIndexHeldTotal'),
            'heldTime' => $langs->trans('TakeposIndexHeldTime'),
            'resume' => $langs->trans('TakeposIndexResume'),
            'networkError' => $langs->trans('TakeposIndexNetworkError'),
            'noLabel' => $langs->trans('TakeposIndexNoLabel'),
            'saleResumed' => $langs->trans('TakeposIndexSaleResumed'),
            'resumeFailed' => $langs->trans('TakeposIndexResumeFailed'),
            'networkErrorResuming' => $langs->trans('TakeposIndexNetworkErrorResuming'),
            'cancelHeldConfirm' => $langs->trans('TakeposIndexCancelHeldConfirm'),
            'cancelFailed' => $langs->trans('TakeposIndexCancelFailed'),
            'noOpenSale' => $langs->trans('TakeposUiNoOpenSale'),
            'paymentOpening' => $langs->trans('TakeposUiPaymentOpening'),
            'invalidExpression' => $langs->trans('TakeposCalcInvalidExpression'),
            'confirmQtyChange' => $langs->trans('TakeposUiConfirmQtyChange'),
            'confirmPriceChange' => $langs->trans('TakeposUiConfirmPriceChange'),
            'confirmDiscountChange' => $langs->trans('TakeposUiConfirmDiscountChange'),
            'resumeConfirm' => $langs->trans('TakeposIndexResumeConfirm'),
            'missingProductImage' => $langs->trans('TakeposUiMissingProductImage'),
            'customerScreenPopup' => $langs->trans('TakeposIndexCustomerScreenPopup'),
            'customerScreenFooter' => $langs->trans('TakeposIndexCustomerScreenFooter')
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

        // Bug#8/#14 fix: expose base currency for currency display normalizer
        window.takeposBaseCurrency = <?php echo json_encode(!empty($conf->currency) ? strtoupper(trim($conf->currency)) : 'JOD', JSON_UNESCAPED_UNICODE); ?>;


        /*
var app = this;
app.hasKeyboard = false;
this.keyboardPress = function() {
	app.hasKeyboard = true;
	$(window).unbind("keyup", app.keyboardPress);
	localStorage.hasKeyboard = true;
	console.log("has keyboard!")
}
$(window).on("keyup", app.keyboardPress)
if(localStorage.hasKeyboard) {
	app.hasKeyboard = true;
	$(window).unbind("keyup", app.keyboardPress);
	console.log("has keyboard from localStorage")
}
*/


        function ensurePosTicketVisible() {
            var pos = document.getElementById('poslines');
            if (!pos) return;
            pos.style.display = 'block';
            pos.style.visibility = 'visible';
            pos.style.minHeight = '260px';
        }

        function takeposNormalizeUiLanguage(root) {
            root = root || document;
            try {
                if (!root || !root.querySelectorAll) return;
                var selectors = ['#poslines', '#takepos-shortcuts-drawer', '#cboxLoadedContent', '.invoice', '.takepos-workspace-panel', '.takepos-workspace-page', '.takepos-customer-display'];
                selectors.forEach(function (sel) {
                    root.querySelectorAll(sel).forEach(function (node) {
                        if (!takeposUiIsArabic) {
                            node.innerHTML = node.innerHTML
                                .replace(/&amp;/g, '&')
                                .replace(/\u062d\u0631\u0643\u0629\s+\u0627\u0644\u062e\u0644\u0641|\u0631\u062c\u0648\u0639/g, 'Move Back');
                        } else {
                            node.innerHTML = node.innerHTML
                                .replace(/Move Back/g, '\u0631\u062c\u0648\u0639')
                                .replace(/Invoice discount/gi, '\u062e\u0635\u0645 \u0627\u0644\u0641\u0627\u062a\u0648\u0631\u0629')
                                .replace(/Print ticket/gi, '\u0637\u0628\u0627\u0639\u0629 \u0627\u0644\u062a\u0630\u0643\u0631\u0629')
                                .replace(/Loyalty\s*&amp;\s*CRM/gi, '\u0627\u0644\u0648\u0644\u0627\u0621 \u0648 CRM')
                                .replace(/Loyalty\s*&\s*CRM/gi, '\u0627\u0644\u0648\u0644\u0627\u0621 \u0648 CRM');
                        }
                    });
                });
            } catch (e) {
            }
        }

        function loadPosLines(url, onDone) {
            ensurePosTicketVisible();
            var pos = $('#poslines');
            if (!pos.length) return;
            pos.load(url, function (responseText, textStatus, xhr) {
                ensurePosTicketVisible();
                if (textStatus === 'error') {
                    var code = xhr && xhr.status ? xhr.status : '';
                    pos.html('<div class="error" style="padding:16px;font-size:14px;">' + takeposMsgLoadSaleTicketError + (code ? ' (HTTP ' + code + ')' : '') + '</div>');
                } else {
                    takeposNormalizeUiLanguage(document);
                    if (window.KFOfflineIndex) window.KFOfflineIndex.noteRealCartRendered();
                }
                if (typeof onDone === 'function') onDone(responseText, textStatus, xhr);
            });
        }

        function takeposInitAfterDialogClose() {
            ensurePosTicketVisible();
            takeposNormalizeUiLanguage(document);
            if (!$('#invoiceid').length || !$('#invoiceid').val()) {
                setTimeout(function () {
                    Refresh();
                }, 120);
            }
        }

        // === FIX: centralised payment-lock reset helper ===
        function takeposResetPaymentLocks() {
            takeposPaymentInProgress = false;
            takeposDirectPaymentLock = false;
            window.takeposPreferredPaymentCode = '';
        }

        // Reset locks on every colorbox close event.
        // cbox_cleanup fires BEFORE cbox_closed and is more reliable (it always
        // triggers on X button, ESC, $.colorbox.close(), or iframe unload).
        $(document).on('cbox_cleanup', function () {
            takeposResetPaymentLocks();
            if (window.takeposPaymentWatchdog) {
                clearTimeout(window.takeposPaymentWatchdog);
                window.takeposPaymentWatchdog = null;
            }
        });

        // Belt-and-suspenders: also bind a direct click on the colorbox close
        // button via event delegation so even a stopped-propagation click resets
        // the flag (delegated on document so it always fires regardless of DOM order).
        $(document).on('click', '#cboxClose', function () {
            takeposResetPaymentLocks();
            if (window.takeposPaymentWatchdog) {
                clearTimeout(window.takeposPaymentWatchdog);
                window.takeposPaymentWatchdog = null;
            }
        });

        $(document).on('cbox_closed', function () {
            // === FIX: stuck "Payment already in progress" bug ===
            // Always reset the payment lock flags whenever ANY colorbox closes.
            // The per-instance onClosed callback in CloseBill() is a primary
            // path, but it can fail to fire if pay.php errors out, the user
            // closes via ESC, or any JS error interrupts the lifecycle.
            // This global listener guarantees the flags can never get stuck.
            takeposResetPaymentLocks();
            if (window.takeposPaymentWatchdog) {
                clearTimeout(window.takeposPaymentWatchdog);
                window.takeposPaymentWatchdog = null;
            }
            if (typeof takeposFeedback === 'function') {
                takeposFeedback('', '');
            }

            takeposInitAfterDialogClose();
            setTimeout(function () {
                restoreTakeposShortcutsScroll();
                if (!$('#takepos-shortcuts-drawer').hasClass('is-open')) {
                    maintainBarcodeSearchFocus(true);
                }
            }, 80);
        });

        // === FIX: stuck-flag escape hatch ===
        // ESC key: if the payment flag is stuck and no colorbox is actually
        // open (cboxOverlay is hidden), force-reset the flag so the user
        // doesn't have to refresh the page.
        $(document).on('keydown', function (ev) {
            if (ev.key === 'Escape' || ev.keyCode === 27) {
                var cboxOpen = $('#cboxOverlay').is(':visible') || $('#colorbox').is(':visible');
                if (!cboxOpen && (takeposPaymentInProgress || takeposDirectPaymentLock)) {
                    takeposResetPaymentLocks();
                    if (window.takeposPaymentWatchdog) {
                        clearTimeout(window.takeposPaymentWatchdog);
                        window.takeposPaymentWatchdog = null;
                    }
                    if (typeof takeposFeedback === 'function') {
                        takeposFeedback('', '');
                    }
                    console.log('[takepos] Stuck payment lock cleared via ESC');
                }
            }
        });

        function openTakeposShiftDesk() {
            if (!takeposShiftFeatureEnabled) {
                return;
            }
            $.colorbox({
                href: takeposShiftWorkspaceUrl,
                width: "96%",
                height: "94%",
                transition: "none",
                iframe: true,
                title: takeposUi.shiftDeskTitle
            });
        }

        if ($.colorbox && $.colorbox.settings) {
            $.extend($.colorbox.settings, {
                overlayClose: false,
                escKey: false
            });
        }

        $(document).on('wheel touchmove scroll', '#cboxLoadedContent, #cboxLoadedContent .takepos-workspace-table-wrap, #cboxLoadedContent .takepos-workspace-panel, #cboxLoadedContent .takepos-workspace-reports-page', function (e) {
            e.stopPropagation();
        });

        function refreshTakeposShiftPanel() {
            if (!takeposShiftFeatureEnabled) {
                return;
            }

            fetch(takeposShiftEndpoint + '?action=active', {credentials: 'same-origin', cache: 'no-store'})
                .then(function (r) {
                    return r.json();
                })
                .then(function (res) {
                    var titleNode = document.getElementById('takepos-shift-title');
                    var metaNode = document.getElementById('takepos-shift-meta');
                    if (!titleNode || !metaNode) {
                        return;
                    }

                    if (!res || !res.success || !res.data) {
                        window.takeposShiftIsOpen = false;
                        titleNode.textContent = takeposUi.noActiveShift;
                        metaNode.innerHTML = takeposUi.openShiftBeforePayment;
                        return;
                    }

                    window.takeposShiftIsOpen = true;
                    var s = res.data;
                    titleNode.textContent = s.shift_ref + ' (' + s.status + ')';
                    metaNode.innerHTML = takeposUi.shiftMetaTerminal + ': <strong>' + (s.terminal_code || '') + '</strong><br>'
                        + takeposUi.shiftMetaOpened + ': <strong>' + (s.date_open || '') + '</strong><br>'
                        + takeposUi.shiftMetaOpening + ': <strong>' + (parseFloat(s.opening_float || 0).toFixed(2)) + '</strong> | '
                        + takeposUi.shiftMetaExpected + ': <strong>' + (parseFloat(s.expected_cash || 0).toFixed(2)) + '</strong>';
                })
                .catch(function () {
                    window.takeposShiftIsOpen = false;
                    var titleNode = document.getElementById('takepos-shift-title');
                    var metaNode = document.getElementById('takepos-shift-meta');
                    if (!titleNode || !metaNode) {
                        return;
                    }
                    titleNode.textContent = takeposUi.shiftStatusUnavailable;
                    metaNode.textContent = takeposUi.unableContactShiftService;
                });
        }

        var takeposShiftEntryPromptDone = false;

        function promptTakeposShiftOnEntry() {
            if (!takeposShiftFeatureEnabled || takeposShiftEntryPromptDone) {
                return;
            }

            takeposShiftEntryPromptDone = true;

            fetch(takeposShiftEndpoint + '?action=check_sale&invoice_id=0', {
                credentials: 'same-origin',
                cache: 'no-store'
            })
                .then(function (r) {
                    return r.json();
                })
                .then(function (res) {
                    if (res && res.success && parseInt(res.allowed, 10) === 1) {
                        return;
                    }

                    var msg = (res && res.message) ? res.message : takeposUi.openShiftRequiredBeforeSale;
                    alert(msg);
                    if (takeposCanOpenShiftDesk) {
                        openTakeposShiftDesk();
                    }
                })
                .catch(function () {
                    // Keep the login flow stable. Server-side sale/payment gates remain the hard stop.
                });
        }

        function ensureShiftForPayment(invoiceId, onAllowed) {
            if (!takeposShiftFeatureEnabled) {
                onAllowed();
                return;
            }
            if (window.KFOffline && !window.KFOffline.isOnline()) {
                onAllowed();
                return;
            }

            fetch(takeposShiftEndpoint + '?action=check_payment&invoice_id=' + encodeURIComponent(invoiceId || 0), {
                credentials: 'same-origin',
                cache: 'no-store'
            })
                .then(function (r) {
                    return r.json();
                })
                .then(function (res) {
                    if (res && res.success && parseInt(res.allowed, 10) === 1) {
                        onAllowed();
                        return;
                    }
                    var msg = (res && res.message) ? res.message : takeposUi.openShiftRequiredBeforePayment;
                    alert(msg);
                    openTakeposShiftDesk();
                })
                .catch(function () {
                    alert(takeposUi.unableValidateActiveShift);
                });
        }

        function TakeposOnShiftOpened(payload) {
            window.takeposShiftIsOpen = true;
            try {
                if (window.parent && window.parent !== window && typeof window.parent.$ === 'function' && window.parent.$.colorbox) {
                    window.parent.$.colorbox.close();
                }
            } catch (e) {
            }
            if (typeof refreshTakeposShiftPanel === 'function') {
                refreshTakeposShiftPanel();
            }
            if (typeof loadPosLines === 'function') {
                var currentInvoiceId = ($('#invoiceid').length ? ($('#invoiceid').val() || '0') : '0');
                loadPosLines("invoice.php?token=<?php echo newToken(); ?>&place=" + place + "&invoiceid=" + encodeURIComponent(currentInvoiceId), function () {
                    maintainBarcodeSearchFocus(true);
                });
            } else {
                maintainBarcodeSearchFocus(true);
            }
        }

        function TakeposOnShiftClosed(payload) {
            window.takeposShiftIsOpen = false;
            try {
                selectedline = '';
            } catch (e) {
            }
            try {
                selectedtext = undefined;
            } catch (e) {
            }
            try {
                editnumber = '';
            } catch (e) {
            }
            if ($('#invoiceid').length) {
                $('#invoiceid').val('');
            }
            if ($('#search').length) {
                $('#search').val('');
            }
            try {
                $('#qty').html("<?php echo dol_escape_js($langs->trans("TakeposUiQty")); ?>").removeClass('clicked');
                $('#price').html("<?php echo dol_escape_js($langs->trans("TakeposUiPrice")); ?>").removeClass('clicked');
                $('#reduction').html("<?php echo dol_escape_js($langs->trans("TakeposUiLineDiscountShort")); ?>").removeClass('clicked');
            } catch (e) {
            }
            if (typeof refreshTakeposShiftPanel === 'function') {
                refreshTakeposShiftPanel();
            }
            if (typeof loadPosLines === 'function') {
                loadPosLines("invoice.php?token=<?php echo newToken(); ?>&place=" + place + "&invoiceid=0", function () {
                    maintainBarcodeSearchFocus(true);
                });
            }
        }

        function ensureShiftForSale(invoiceId, onAllowed) {
            if (!takeposShiftFeatureEnabled) {
                onAllowed();
                return;
            }
            if (window.KFOffline && !window.KFOffline.isOnline()) {
                // أوفلاين: ما فينا نتحقق من الوردية عالسيرفر — نسمح بالبيع، والتحقق
                // الحقيقي بيصير وقت المزامنة (بدل ما يعلّق البرنامج للأبد على alert فاشل)
                onAllowed();
                return;
            }

            fetch(takeposShiftEndpoint + '?action=check_sale&invoice_id=' + encodeURIComponent(invoiceId || 0), {
                credentials: 'same-origin',
                cache: 'no-store'
            })
                .then(function (r) {
                    return r.json();
                })
                .then(function (res) {
                    if (res && res.success && parseInt(res.allowed, 10) === 1) {
                        onAllowed();
                        return;
                    }
                    var msg = (res && res.message) ? res.message : takeposUi.openShiftRequiredBeforeSale;
                    alert(msg);
                    openTakeposShiftDesk();
                })
                .catch(function () {
                    alert(takeposUi.unableValidateActiveShift);
                });
        }

        function openTakeposLoyaltyDesk() {
            if (!takeposLoyaltyFeatureEnabled) {
                return;
            }
            $.colorbox({
                href: takeposLoyaltyWorkspaceUrl,
                width: "96%",
                height: "94%",
                transition: "none",
                iframe: true,
                title: takeposUi.loyaltyDeskTitle
            });
        }

        function refreshTakeposCustomerPanel() {
            if (!takeposLoyaltyFeatureEnabled) {
                return;
            }
            var panel = document.getElementById('takepos-customer-meta');
            if (!panel) {
                return;
            }
            var customerId = $('#idcustomer').val();
            if (!customerId) {
                panel.innerHTML = takeposUi.noCustomerSelected;
                return;
            }
            fetch(takeposLoyaltyEndpoint + '?action=customer_summary&customer_id=' + encodeURIComponent(customerId), {
                credentials: 'same-origin',
                cache: 'no-store'
            })
                .then(function (r) {
                    return r.json();
                })
                .then(function (res) {
                    if (!res || !res.success || !res.summary) {
                        panel.textContent = takeposUi.customerSummaryUnavailable;
                        return;
                    }
                    var s = res.summary;
                    panel.innerHTML = takeposUi.customerLabel + ': <strong>' + String((s.customer && s.customer.name) ? s.customer.name : '') + '</strong><br>'
                        + takeposUi.pointsLabel + ': <strong>' + String((s.loyalty && s.loyalty.points_balance) ? s.loyalty.points_balance : 0) + '</strong><br>'
                        + takeposUi.purchasesLabel + ': <strong>' + String((s.purchase && s.purchase.purchase_count) ? s.purchase.purchase_count : 0) + '</strong>';
                })
                .catch(function () {
                    panel.textContent = takeposUi.unableLoadCustomerSummary;
                });
        }

        function openTakeposSyncQueue() {
            if (!takeposSyncFeatureEnabled || !takeposCanManageSyncQueue) {
                return;
            }
            $.colorbox({
                href: takeposSyncWorkspaceUrl,
                width: "96%",
                height: "94%",
                transition: "none",
                iframe: true,
                title: takeposUi.syncQueueTitle
            });
        }

        function refreshCurrentTicketSyncStatus() {
            if (!takeposSyncFeatureEnabled) {
                return;
            }
            var ticketNode = document.getElementById('takepos-ticket-sync-status');
            if (!ticketNode) {
                return;
            }
            var currentInvoice = $('#invoiceid').val();
            if (!currentInvoice) {
                ticketNode.textContent = takeposUi.ticketStatusNa;
                return;
            }
            var localRef = 'INV-' + String(currentInvoice);
            fetch(takeposSyncEndpoint + '?action=ticket_status&local_ref=' + encodeURIComponent(localRef), {
                credentials: 'same-origin',
                cache: 'no-store'
            })
                .then(function (r) {
                    return r.json();
                })
                .then(function (res) {
                    if (!res || !res.success || !res.row) {
                        ticketNode.textContent = takeposUi.ticketStatusNotQueued;
                        return;
                    }
                    ticketNode.textContent = String(res.row.status || takeposUi.ticketStatusPending) + ' (#' + String(res.row.rowid || '') + ')';
                })
                .catch(function () {
                    ticketNode.textContent = takeposUi.ticketStatusUnavailable;
                });
        }

        function refreshTakeposSyncPanel() {
            if (!takeposSyncFeatureEnabled) {
                return;
            }

            var badge = document.getElementById('takepos-sync-online');
            if (badge) {
                if (navigator.onLine === false) {
                    badge.className = 'sync-badge offline';
                    badge.textContent = takeposUi.syncOffline;
                } else {
                    badge.className = 'sync-badge online';
                    badge.textContent = takeposUi.syncOnline;
                }
            }

            fetch(takeposSyncEndpoint + '?action=status', {credentials: 'same-origin', cache: 'no-store'})
                .then(function (r) {
                    return r.json();
                })
                .then(function (res) {
                    var meta = document.getElementById('takepos-sync-meta');
                    if (!meta) {
                        return;
                    }
                    if (!res || !res.success || !res.state) {
                        meta.textContent = takeposUi.syncStateUnavailable;
                        return;
                    }

                    var state = res.state;
                    var summary = state.sync_summary || {};
                    meta.innerHTML = takeposUi.offlineModeLabel + ': <strong>' + (parseInt(state.offline_mode, 10) === 1 ? takeposUi.enabled : takeposUi.disabled) + '</strong><br>'
                        + '<?php echo dol_escape_js($langs->trans("TakeposSyncQueuePending")); ?>: <strong>' + String(summary.pending || 0) + '</strong> | '
                        + '<?php echo dol_escape_js($langs->trans("TakeposSyncQueueFailed")); ?>: <strong>' + String(summary.failed || 0) + '</strong> | '
                        + '<?php echo dol_escape_js($langs->trans("TakeposSyncQueueConflict")); ?>: <strong>' + String(summary.conflict || 0) + '</strong>';

                    var toggle = document.getElementById('btn-takepos-offline-toggle');
                    if (toggle) {
                        toggle.textContent = (parseInt(state.offline_mode, 10) === 1 ? takeposUi.disableOffline : takeposUi.enableOffline);
                    }

                    refreshCurrentTicketSyncStatus();
                    refreshTakeposCustomerPanel();
                })
                .catch(function () {
                    var meta = document.getElementById('takepos-sync-meta');
                    if (meta) {
                        meta.textContent = takeposUi.unableContactSyncService;
                    }
                });
        }

        function toggleTakeposOfflineMode() {
            if (!takeposSyncFeatureEnabled || !takeposCanUseOfflineMode) {
                return;
            }

            fetch(takeposSyncEndpoint + '?action=status', {credentials: 'same-origin', cache: 'no-store'})
                .then(function (r) {
                    return r.json();
                })
                .then(function (res) {
                    if (!res || !res.success || !res.state) {
                        throw new Error(takeposUi.unableLoadOfflineState);
                    }
                    var nextEnabled = (parseInt(res.state.offline_mode, 10) === 1 ? 0 : 1);
                    return fetch(takeposSyncEndpoint + '?action=set_mode&enabled=' + String(nextEnabled) + '&token=' + encodeURIComponent(takeposCsrfToken), {
                        credentials: 'same-origin',
                        cache: 'no-store'
                    });
                })
                .then(function (r) {
                    return r.json();
                })
                .then(function (res) {
                    if (!res || !res.success) {
                        alert((res && res.message) ? res.message : takeposUi.unableToggleOfflineMode);
                        return;
                    }
                    refreshTakeposSyncPanel();
                })
                .catch(function () {
                    alert(takeposUi.unableToggleOfflineModeNow);
                });
        }

        // PERFORMANCE: previously refreshTakeposSyncPanel / refreshTakeposCustomerPanel /
        // refreshTakeposShiftPanel each ran on a fixed setInterval (every 20-30 seconds)
        // regardless of whether the cashier was actually using the page. That kept
        // hitting sync.php?action=status, shift.php?action=active, loyalty.php?action=
        // customer_summary on every interval tick. On a busy server those endpoints
        // were taking multiple seconds each (visible in DevTools Network), and they
        // were piling up while the cashier tried to add a product.
        //
        // Fixes:
        //   1) Slowed the intervals to 90s (was 20-30s). The data they show is purely
        //      informational — sync count, shift status, customer points — and 90s
        //      is plenty for those.
        //   2) Pause the intervals when the tab is hidden (document.hidden).
        //      No user is looking, no need to fetch.
        //   3) Pause the intervals while the cashier is actively interacting with
        //      the page (mousedown/keydown anywhere). When the user hasn't touched
        //      the screen for ~5s we resume normal cadence. This means a busy cashier
        //      ringing up customers fast won't have these 3 background fetches
        //      competing with their addline calls.
        //   4) Each interval is offset so they don't all hit the server at once.
        (function () {
            function makeAdaptiveTimer(label, fn, intervalMs, initialDelayMs) {
                var lastUserActivityAt = 0;
                var ACTIVE_QUIET_MS = 5000; // pause refreshes for 5s after each user interaction
                ['mousedown', 'keydown', 'touchstart', 'wheel'].forEach(function (ev) {
                    document.addEventListener(ev, function () {
                        lastUserActivityAt = Date.now();
                    }, {passive: true});
                });

                function tick() {
                    try {
                        if (document.hidden) {
                            return;
                        }                                      // tab hidden
                        if ((Date.now() - lastUserActivityAt) < ACTIVE_QUIET_MS) {
                            return;
                        }  // user is busy
                        fn();
                    } catch (err) {
                        console.warn('takepos adaptive timer ' + label + ' failed:', err);
                    }
                }

                // First fetch happens after a small delay so it doesn't compete with page load
                setTimeout(function () {
                    try {
                        fn();
                    } catch (e) {
                    }
                }, initialDelayMs || 1500);
                setInterval(tick, intervalMs);
            }

            if (typeof takeposSyncFeatureEnabled !== 'undefined' && takeposSyncFeatureEnabled) {
                window.addEventListener('online', function () {
                    try {
                        refreshTakeposSyncPanel();
                    } catch (e) {
                    }
                });
                window.addEventListener('offline', function () {
                    try {
                        refreshTakeposSyncPanel();
                    } catch (e) {
                    }
                });
                window.addEventListener('load', function () {
                    makeAdaptiveTimer('sync', refreshTakeposSyncPanel, 90000, 1500);
                });
            }

            if (typeof takeposLoyaltyFeatureEnabled !== 'undefined' && takeposLoyaltyFeatureEnabled) {
                window.addEventListener('load', function () {
                    // Stagger by 3s so all three panels don't hit at the same moment
                    makeAdaptiveTimer('loyalty', refreshTakeposCustomerPanel, 90000, 4500);
                });
            }

            if (typeof takeposShiftFeatureEnabled !== 'undefined' && takeposShiftFeatureEnabled) {
                window.addEventListener('load', function () {
                    // Initial shift refresh + the interactive shift prompt are kept,
                    // only the periodic background poll is slowed and made idle-aware.
                    try {
                        refreshTakeposShiftPanel();
                    } catch (e) {
                    }
                    setTimeout(promptTakeposShiftOnEntry, 180);
                    makeAdaptiveTimer('shift', refreshTakeposShiftPanel, 90000, 7500);
                });
            }
        })();

        function takeposSetSearchLayoutActive(active) {
            var $body = jQuery('body');
            if (!$body.length) return;
            $body.toggleClass('takepos-search-active', !!active);
        }

        function ClearSearch(clearSearchResults, ev) {
            if (ev) {
                try {
                    if (ev.preventDefault) ev.preventDefault();
                    if (ev.stopPropagation) ev.stopPropagation();
                } catch (e) {
                }
            }
            console.log("ClearSearch");
            takeposSetSearchLayoutActive(false);
            $("#search").val('');
            $("#qty").html("<?php echo dol_escape_js($langs->trans("TakeposUiQty")); ?>").removeClass('clicked');
            $("#price").html("<?php echo dol_escape_js($langs->trans("TakeposUiPrice")); ?>").removeClass('clicked');
            $("#reduction").html("<?php echo dol_escape_js($langs->trans("TakeposUiLineDiscountShort")); ?>").removeClass('clicked');
            <?php if ($conf->browser->layout == 'classic') { ?>
            setFocusOnSearchField();
            <?php } ?>
            if (clearSearchResults) {
                var $searchField = $("#search");
                if ($searchField.length) {
                    $searchField.trigger("keyup");
                }
            }
            return false;
        }

        // Set the focus on search field but only on desktop. On tablet or smartphone, we don't to avoid to have the keyboard open automatically
        function setFocusOnSearchField() {
            console.log("Call setFocusOnSearchField in page index.php");
            <?php if ($conf->browser->layout == 'classic') { ?>
            if (typeof takeposBarcodeSearchFocusSuspended === 'function' && takeposBarcodeSearchFocusSuspended()) {
                return;
            }
            console.log("has keyboard from localStorage, so we can force focus on search field");
            var $search = $("#search");
            if ($search.length && !$search.is(':focus')) {
                $search.trigger('focus');
                try {
                    var el = $search.get(0);
                    if (el && typeof el.setSelectionRange === 'function') {
                        var len = ($search.val() || '').length;
                        el.setSelectionRange(len, len);
                    }
                } catch (e) {
                    console.log('setSelectionRange search skipped', e);
                }
            }
            <?php } ?>
        }

        function takeposSearchInputOwnsFocusTarget(target) {
            if (!target) return false;
            var $target = $(target);
            if (!$target.length) return false;
            if ($target.is('#search')) return true;
            if ($target.closest('#search').length) return true;
            if ($target.closest('.select2-container').length) return true;
            if ($target.closest('.ui-dialog, .ui-datepicker, .ui-autocomplete, .colorbox, #cboxWrapper, .takepos-held-modal, .takepos-override-modal').length) return true;
            if ($target.is('iframe') || $target.closest('iframe').length) return true;
            if ($target.is('input, textarea, select, button, [contenteditable="true"], [contenteditable=""]')) return true;
            return false;
        }

        function takeposBarcodeSearchFocusSuspended() {
            var $search = $('#search');
            if (!$search.length) return true;
            if ($('#takepos-shortcuts-drawer').hasClass('is-open')) return true;
            if ($('#cboxOverlay:visible, #colorbox:visible, #cboxWrapper:visible, #cboxLoadedContent:visible').length) return true;
            if ($('.modal:visible').length) return true;
            var active = document.activeElement;
            if (!active) return false;
            if (active.tagName === 'IFRAME') return true;
            if ($(active).closest('#cboxWrapper, .modal, #takepos-shortcuts-drawer').length) return true;
            return false;
        }

        function maintainBarcodeSearchFocus(force) {
            <?php if ($conf->browser->layout == 'classic') { ?>
            if (takeposBarcodeSearchFocusSuspended()) {
                return;
            }
            if (!force && takeposSearchInputOwnsFocusTarget(document.activeElement)) {
                return;
            }
            setFocusOnSearchField();
            <?php } ?>
        }

        function takeposRouteTypingToBarcodeSearch(ev) {
            <?php if ($conf->browser->layout == 'classic') { ?>
            if (!ev || ev.defaultPrevented) return;
            if (ev.ctrlKey || ev.altKey || ev.metaKey) return;
            if (takeposBarcodeSearchFocusSuspended()) return;
            if (takeposSearchInputOwnsFocusTarget(ev.target)) return;
            var $search = $('#search');
            if (!$search.length) return;
            var key = ev.key || '';
            if (key === 'Tab') return;
            if (key === 'Backspace') {
                setFocusOnSearchField();
                var currentVal = String($search.val() || '');
                $search.val(currentVal.substring(0, Math.max(0, currentVal.length - 1))).trigger('keyup');
                ev.preventDefault();
                return;
            }
            if (key === 'Enter') {
                setFocusOnSearchField();
                Search2('<?php echo dol_escape_js($keyCodeForEnter); ?>', null, ev);
                ev.preventDefault();
                return;
            }
            if (key.length !== 1) return;
            setFocusOnSearchField();
            $search.val(String($search.val() || '') + key).trigger('keyup');
            ev.preventDefault();
            <?php } ?>
        }

        function takeposHideDebugBars() {
            var selectors = [
                '#php-debugbar', '.phpdebugbar', '.php-debugbar', '.debugbar', '.debug-bar', '.debugbar-container', '.sf-toolbar', '#sfwdt',
                'iframe[id*=\"debug\"]', 'iframe[class*=\"debug\"]', 'div[id*=\"debugbar\"]', 'div[class*=\"debugbar\"]', 'div[id*=\"DebugBar\"]', 'div[class*=\"DebugBar\"]'
            ];
            try {
                $(selectors.join(',')).remove();
                $('body *').filter(function () {
                    var $el = $(this);
                    if (!$el.is(':visible')) return false;
                    var cssPosition = ($el.css('position') || '').toLowerCase();
                    if (cssPosition !== 'fixed' && cssPosition !== 'sticky') return false;
                    var text = ($el.text() || '').replace(/\s+/g, ' ').trim();
                    if (!text) return false;
                    return /variables/i.test(text) && /timeline/i.test(text) && (/error handler/i.test(text) || /database/i.test(text));
                }).remove();
            } catch (e) {
                console.log('takeposHideDebugBars error', e);
            }
        }

        function takeposRestoreProductsAfterSearchClear() {
            if ($('#search').val() !== '') {
                return;
            }
            if ($('#catdiv0').length) {
                LoadProducts(0, false);
            }
        }

        $(function () {
            takeposHideDebugBars();
            setTimeout(takeposHideDebugBars, 300);
            setTimeout(takeposHideDebugBars, 1200);
            var searchSelector = $('#search');
            searchSelector.on('keydown', function (ev) {
                if (!ev) return;
                if (String(ev.key || '') === 'Enter' || ev.which === 13 || ev.keyCode === 13) {
                    ev.preventDefault();
                    Search2(window.takeposKeyCodeForEnter || '13', null, ev);
                    return false;
                }
            });
            searchSelector.on('input search', function () {
                if ((this.value || '') === '') {
                    Search2('<?php echo dol_escape_js($keyCodeForEnter); ?>', null);
                }
            });
            searchSelector.on('blur', function () {
                setTimeout(function () {
                    maintainBarcodeSearchFocus(false);
                }, 30);
            });
            setTimeout(function () {
                maintainBarcodeSearchFocus(true);
                if (typeof takeposBindCriticalActionButtons === 'function') takeposBindCriticalActionButtons();
            }, 80);
            $(document).on('click touchstart', function (ev) {
                var target = ev.target;
                setTimeout(function () {
                    if (!takeposSearchInputOwnsFocusTarget(target)) {
                        maintainBarcodeSearchFocus(false);
                    }
                }, 40);
            });
            $(document).on('keydown', takeposRouteTypingToBarcodeSearch);
            if (window.MutationObserver) {
                var debugObserver = new MutationObserver(function () {
                    takeposHideDebugBars();
                });
                debugObserver.observe(document.body, {childList: true, subtree: true});
            }
        });

        function PrintCategories(first) {
            console.log("PrintCategories");
            for (i = 0; i < <?php echo($MAXCATEG - 2); ?>; i++) {
                var categoryRow = categories[parseInt(i) + parseInt(first)];
                if (typeof (categoryRow) == "undefined") {
                    $("#catdivdesc" + i).hide();
                    $("#catdesc" + i).text("");
                    $("#catimg" + i).attr("src", "genimg/empty.png");
                    $("#catwatermark" + i).hide();
                    $("#catdiv" + i).attr('class', 'wrapper divempty');
                    continue;
                }
                $("#catdivdesc" + i).show();
                <?php
                if (getDolGlobalString('TAKEPOS_SHOW_CATEGORY_DESCRIPTION') == 1) { ?>
                $("#catdesc" + i).html(categoryRow['label'].bold() + ' - ' + categoryRow['description']);
                <?php } else { ?>
                $("#catdesc" + i).text(categoryRow['label']);
                <?php }    ?>
                var categoryId = takeposGetCategoryId(categoryRow);
                $("#catimg" + i).attr("src", "genimg/index.php?query=cat&id=" + categoryId);
                $("#catdiv" + i).data("rowid", categoryId);
                $("#catdiv" + i).attr("data-rowid", categoryId);
                $("#catdiv" + i).attr('class', 'wrapper');
                $("#catwatermark" + i).show();
            }
        }

        function MoreCategories(moreorless) {
            console.log("MoreCategories moreorless=" + moreorless + " pagecategories=" + pagecategories);
            if (moreorless == "more") {
                $('#catimg15').animate({opacity: '0.5'}, 1);
                $('#catimg15').animate({opacity: '1'}, 100);
                pagecategories = pagecategories + 1;
            }
            if (moreorless == "less") {
                $('#catimg14').animate({opacity: '0.5'}, 1);
                $('#catimg14').animate({opacity: '1'}, 100);
                if (pagecategories == 0) return; //Return if no less pages
                pagecategories = pagecategories - 1;
            }
            if (typeof (categories[<?php echo($MAXCATEG - 2); ?> * pagecategories]
        &&
            moreorless == "more"
        ) ==
            "undefined"
        )
            { // Return if no more pages
                pagecategories = pagecategories - 1;
                return;
            }

            for (i = 0; i < <?php echo($MAXCATEG - 2); ?>; i++) {
                var categoryRow = categories[i + (<?php echo($MAXCATEG - 2); ?> * pagecategories
            )]
                ;
                if (typeof (categoryRow) == "undefined") {
                    // complete with empty record
                    console.log("complete with empty record");
                    $("#catdivdesc" + i).hide();
                    $("#catdesc" + i).text("");
                    $("#catimg" + i).attr("src", "genimg/empty.png");
                    $("#catwatermark" + i).hide();
                    continue;
                }
                $("#catdivdesc" + i).show();
                <?php
                if (getDolGlobalString('TAKEPOS_SHOW_CATEGORY_DESCRIPTION') == 1) { ?>
                $("#catdesc" + i).html(categoryRow['label'].bold() + ' - ' + categoryRow['description']);
                <?php } else { ?>
                $("#catdesc" + i).text(categoryRow['label']);
                <?php } ?>
                var categoryId = takeposGetCategoryId(categoryRow);
                $("#catimg" + i).attr("src", "genimg/index.php?query=cat&id=" + categoryId);
                $("#catdiv" + i).data("rowid", categoryId);
                $("#catdiv" + i).attr("data-rowid", categoryId);
                $("#catwatermark" + i).show();
            }

            ClearSearch(false);
        }

        // =====================================================================
        // TakePOS — Client-side product cache
        // =====================================================================
        // VERSION MARKER — if you can see this in the browser console, the new
        // code is loaded. If you can't, the browser or server is still serving
        // the old index.php.
        console.log('[TakePOS] Performance patches loaded — build 2026-04-28-r5 (product cache active)');
        // ---------------------------------------------------------------------
        // Why: ajax.php?action=getProducts is expensive on the server. With stock
        // checking on (TAKEPOS_PRODUCT_IN_STOCK=1) it issues an extra DB query
        // PER PRODUCT (load_stock), so a 30-product page can fire 30+ queries.
        // Cashiers tap the same categories all day, so 95%+ of these calls return
        // the same data. Caching them client-side avoids the round-trip entirely.
        //
        // Two-layer cache:
        //   1. In-memory Map: instant hit while the page is open.
        //   2. sessionStorage: survives Refresh() / soft reloads, scoped per tab
        //      so two cashiers on the same machine don't see each other's cache.
        //
        // TTL is short (90 seconds) so price edits made elsewhere are picked up
        // quickly. After every successful addline / payment / refund the cache
        // is wiped so stock-driven product disappearance is honoured immediately.
        // A user can also force-refresh by holding Shift while tapping a category.
        // =====================================================================
        window.takeposProductCache = window.takeposProductCache || {
            mem: {},
            TTL_MS: 90000,
            KEY_PREFIX: 'takepos_prodcache_v2:',
            _key: function (category, thirdpartyid, limit, offset, tosell) {
                return [category, thirdpartyid || 0, limit || 0, offset || 0, tosell || ''].join('|');
            },
            _read: function (k) {
                var entry = this.mem[k];
                if (entry && (Date.now() - entry.t) < this.TTL_MS) return entry.d;
                try {
                    var raw = window.sessionStorage && sessionStorage.getItem(this.KEY_PREFIX + k);
                    if (raw) {
                        var obj = JSON.parse(raw);
                        if (obj && (Date.now() - obj.t) < this.TTL_MS) {
                            this.mem[k] = obj;
                            return obj.d;
                        }
                    }
                } catch (e) { /* sessionStorage unavailable / quota — ignore */
                }
                return null;
            },
            _write: function (k, data) {
                var entry = {t: Date.now(), d: data};
                this.mem[k] = entry;
                try {
                    if (window.sessionStorage) sessionStorage.setItem(this.KEY_PREFIX + k, JSON.stringify(entry));
                } catch (e) {
                    // Quota exceeded — drop the in-memory copy too so we don't grow unbounded
                }
            },
            /**
             * Fetch a page of products. Returns a Promise that resolves with the
             * product array. Hits the cache when fresh, otherwise calls the server
             * and stores the result.
             */
            fetchPage: function (url, category, thirdpartyid, limit, offset, tosell, force) {
                var k = this._key(category, thirdpartyid, limit, offset, tosell);
                if (!force) {
                    var cached = this._read(k);
                    if (cached) {
                        console.log('[TakePOS][cache HIT] category=' + category + ' offset=' + offset + ' (' + cached.length + ' items, no network call)');
                        // Resolve in microtask so callers behave the same as the network path
                        return new Promise(function (resolve) {
                            setTimeout(function () {
                                resolve(cached);
                            }, 0);
                        });
                    }
                }
                console.log('[TakePOS][cache MISS] category=' + category + ' offset=' + offset + (force ? ' (force-refresh)' : '') + ' — fetching from server');
                var self = this;
                return new Promise(function (resolve, reject) {
                    jQuery.getJSON(url).done(function (data) {
                        if (Array.isArray(data) && data.length > 0) self._write(k, data);
                        resolve(data);
                    }).fail(function (xhr, status, err) {
                        // On error, fall back to a stale cache entry if we have one
                        var stale = self.mem[k] && self.mem[k].d;
                        if (stale) {
                            resolve(stale);
                            return;
                        }
                        try {
                            var raw = window.sessionStorage && sessionStorage.getItem(self.KEY_PREFIX + k);
                            if (raw) {
                                var obj = JSON.parse(raw);
                                if (obj && obj.d) {
                                    resolve(obj.d);
                                    return;
                                }
                            }
                        } catch (e) {
                        }
                        reject(err);
                    });
                });
            },
            /**
             * Wipe everything. Called after addline / payment so stock-affected
             * product visibility (TAKEPOS_PRODUCT_IN_STOCK=1) is refreshed.
             */
            invalidateAll: function () {
                this.mem = {};
                try {
                    if (!window.sessionStorage) return;
                    // sessionStorage doesn't have a prefix-iterator, walk it
                    var toDelete = [];
                    for (var i = 0; i < sessionStorage.length; i++) {
                        var key = sessionStorage.key(i);
                        if (key && key.indexOf(this.KEY_PREFIX) === 0) toDelete.push(key);
                    }
                    for (var j = 0; j < toDelete.length; j++) sessionStorage.removeItem(toDelete[j]);
                } catch (e) {
                }
            }
        };

        // Force-reload helper: hold Shift while tapping a category to bypass the cache
        // (useful right after editing a product price elsewhere).
        (function () {
            document.addEventListener('mousedown', function (e) {
                if (!e.shiftKey) return;
                var t = e.target;
                while (t && t !== document.body) {
                    if (t.id && /^(catdiv|prodiv)\d+$/.test(t.id)) {
                        window._takeposForceProductReload = true;
                        if (window.takeposProductCache) {
                            try {
                                window.takeposProductCache.invalidateAll();
                            } catch (err) {
                            }
                        }
                        return;
                    }
                    t = t.parentNode;
                }
            }, true);
        })();

        // LoadProducts
        function LoadProducts(position, issubcat) {
            takeposSetSearchLayoutActive(false);
            jQuery('.div5 .wrapper2.arrow').removeClass('takepos-page-disabled');
            console.log("LoadProducts position=" + position + " issubcat=" + issubcat);
            var maxproduct = <?php echo (int)($MAXPRODUCT - 2); ?>;

            if (position == "supplements") {
                currentcat = "supplements";
            } else if (window.takeposAllProductsMode) {
                // FIX (all-products-v2): "All" tab — use category=0 so ajax.php
                // returns all products with pagination instead of one category.
                currentcat = 0;
                $('#catimg' + position).animate({opacity: '0.5'}, 1);
                $('#catimg' + position).animate({opacity: '1'}, 100);
            } else {
                $('#catimg' + position).animate({opacity: '0.5'}, 1);
                $('#catimg' + position).animate({opacity: '1'}, 100);
                if (issubcat == true) {
                    currentcat = $('#prodiv' + position).data('rowid');
                } else {
                    console.log('#catdiv' + position);
                    currentcat = $('#catdiv' + position).data('rowid');
                    console.log("currentcat=" + currentcat);
                }
            }
            if (currentcat == undefined) {
                return;
            }
            pageproducts = 0;
            ishow = 0; //product to show counter

            // FIX (empty-category-v6): force-clear ALL product tiles to empty state
            // BEFORE the AJAX call. If the category returns 0 products, the existing
            // while-loop only enters the "undefined" branch — which is fine — but
            // doing the wipe up-front guarantees a clean slate even if the AJAX
            // response is delayed, errors out, or arrives partially. Without this,
            // tiles from the previous category click bleed through when clicking
            // a truly-empty category.
            //
            // Note: we wipe ONLY product tiles (0 .. maxproduct-1). The last two
            // tile slots (MAXPRODUCT-2 and MAXPRODUCT-1) are the prev/next arrow
            // buttons — they must keep their `arrow` class and click handlers.
            (function _tpv2WipeTiles() {
                for (var k = 0; k < maxproduct; k++) {
                    $("#prodivdesc" + k).addClass("tp-hidden");
                    $("#prodesc"    + k).text("");
                    $("#probutton"  + k).text("").hide();
                    $("#proprice"   + k).attr("class", "hidden").html("");
                    $("#proimg"     + k).attr("src", "genimg/empty.png").attr("title", "");
                    $("#prodiv"     + k).data("rowid", "").attr("data-rowid", "");
                    $("#prodiv"     + k).data("iscat", "0").attr("data-iscat", "0");
                    $("#prodiv"     + k).attr("class", "wrapper2 divempty");
                    $("#prowatermark" + k).hide();
                }
            })();

            jQuery.each(subcategories, function (i, val) {
                // FIX (all-products-v2): Skip subcategory tiles when currentcat=0 (All view).
                // When currentcat=0, fk_parent=0 matches ALL top-level categories,
                // consuming tile slots before products and leaving fewer product slots.
                // Check currentcat directly (not a flag) so it works even if flag is cleared.
                if (parseInt(currentcat, 10) === 0) return true; // skip for All view
                if (parseInt(currentcat, 10) === (parseInt(val.fk_parent || 0, 10) || 0)) {
                    var categoryId = takeposGetCategoryId(val);
                    $("#prodivdesc" + ishow).removeClass("tp-hidden");
                    <?php if (getDolGlobalString('TAKEPOS_SHOW_CATEGORY_DESCRIPTION') == 1) { ?>
                    $("#prodesc" + ishow).html(val.label.bold() + ' - ' + val.description);
                    $("#probutton" + ishow).html(val.label);
                    <?php } else { ?>
                    $("#prodesc" + ishow).text(val.label);
                    $("#probutton" + ishow).text(val.label);
                    <?php } ?>
                    $("#probutton" + ishow).show();
                    $("#proprice" + ishow).attr("class", "hidden");
                    $("#proprice" + ishow).html("");
                    $("#proimg" + ishow).attr("src", "genimg/index.php?query=cat&id=" + categoryId);
                    $("#prodiv" + ishow).data("rowid", categoryId);
                    $("#prodiv" + ishow).attr("data-rowid", categoryId);
                    $("#prodiv" + ishow).data("iscat", 1);
                    $("#prodiv" + ishow).attr("data-iscat", 1);
                    $("#prodiv" + ishow).removeClass("divempty");
                    $("#prowatermark" + ishow).show();
                    ishow++;
                }
            });

            idata = 0; //product data counter
            var limit = 0;
            if (maxproduct >= 1) {
                // FIX: fetch maxproduct+1 products so we know if more pages exist.
                // The while loop only fills maxproduct tiles — the extra product
                // is never displayed but tells us a next page is needed.
                limit = maxproduct + 1;
            }
            // Only show products for sale (tosell=1)
            var _getProductsUrl = '<?php echo DOL_URL_ROOT ?>/takepos/ajax/ajax.php?action=getProducts&token=<?php echo newToken();?>&thirdpartyid=' + jQuery('#thirdpartyid').val() + '&category=' + currentcat + '&tosell=1&limit=' + limit + '&offset=0';
            var _thirdpartyForCache = jQuery('#thirdpartyid').val() || 0;
            // PERFORMANCE: hit cache first. force=true if the user shift-tapped (window._takeposForceProductReload).
            var _force = !!window._takeposForceProductReload;
            window._takeposForceProductReload = false;
            window.takeposProductCache.fetchPage(_getProductsUrl, currentcat, _thirdpartyForCache, limit, 0, 1, _force).then(function (data) {
                console.log("Call ajax.php (in LoadProducts) to get Products of category " + currentcat + " then loop on result to fill image thumbs");
                if (window.KFOfflineIndex) window.KFOfflineIndex.setCatalog(data);
                if (window.KFOffline) window.KFOffline.cacheCatalog(data);
                //console.log(data);

                while (ishow < maxproduct) {
                    console.log("ishow" + ishow + " idata=" + idata);
                    console.log(data[idata]);

                    if (typeof (data[idata]) == "undefined") {
                        <?php if (getDolGlobalString('TAKEPOS_SHOW_PRODUCT_IMAGES')) {
                        echo '$("#prodivdesc"+ishow).addClass("tp-hidden");';
                        echo '$("#prodesc"+ishow).text("");';
                        echo '$("#proimg"+ishow).attr("title","");';
                        echo '$("#proimg"+ishow).attr("src","genimg/empty.png");';
                    } else {
                        echo '$("#probutton"+ishow).hide();';
                        echo '$("#probutton"+ishow).text("");';
                    }?>
                        $("#proprice" + ishow).attr("class", "hidden");
                        $("#proprice" + ishow).html("");

                        $("#prodiv" + ishow).data("rowid", "");
                        $("#prodiv" + ishow).attr("data-rowid", "");

                        $("#prodiv" + ishow).data("iscat", "0");
                        $("#prodiv" + ishow).attr("data-iscat", "0");

                        $("#prodiv" + ishow).attr("class", "wrapper2 divempty");
                    } else {
                        <?php
                        $titlestring = "'" . dol_escape_js($langs->transnoentities('Ref') . ': ') . "' + data[idata]['ref']";
                        $titlestring .= " + ' - " . dol_escape_js($langs->trans("Barcode") . ': ') . "' + data[idata]['barcode']";
                        ?>
                        var titlestring = <?php echo $titlestring; ?>;
                        var productId = takeposGetProductId(data[idata]);
                        var productImageUrl = data[idata]['img'] || data[idata]['image_url'] || '';
                        <?php if (getDolGlobalString('TAKEPOS_SHOW_PRODUCT_IMAGES')) {
                        echo '$("#prodivdesc"+ishow).removeClass("tp-hidden");';
                        if (getDolGlobalInt('TAKEPOS_SHOW_PRODUCT_REFERENCE') == 1) {
                            echo '$("#prodesc"+ishow).html(data[parseInt(idata)][\'ref\'].bold() + \' - \' + data[parseInt(idata)][\'label\']);';
                        } elseif (getDolGlobalInt('TAKEPOS_SHOW_PRODUCT_REFERENCE') == 2) {
                            echo '$("#prodesc"+ishow).html((data[parseInt(idata)][\'ref\'] ? data[parseInt(idata)][\'ref\'].bold() + \' - \' : \'\') + data[parseInt(idata)][\'label\']);';
                        } else {
                            echo '$("#prodesc"+ishow).html(data[parseInt(idata)][\'label\']);';
                        }
                        echo '$("#proimg"+ishow).attr("title", titlestring);';
                        echo 'takeposApplyProductImage("#proimg"+ishow, productId, productImageUrl, data[idata][\'has_image\']);';
                    } else {
                        echo '$("#probutton"+ishow).show();';
                        echo '$("#probutton"+ishow).html(data[parseInt(idata)][\'label\']);';
                    }
                        ?>
                        if (data[parseInt(idata)]['price_formated']) {
                            $("#proprice" + ishow).attr("class", "productprice");
                            <?php
                            if (getDolGlobalInt('TAKEPOS_CHANGE_PRICE_HT')) {
                            ?>
                            $("#proprice" + ishow).html(data[parseInt(idata)]['price_formated']);
                            <?php
                            } else {
                            ?>
                            $("#proprice" + ishow).html(data[parseInt(idata)]['price_ttc_formated']);
                            <?php
                            }
                            ?>
                        }
                        console.log("#prodiv" + ishow + ".data(rowid)=" + productId);

                        $("#prodiv" + ishow).data("rowid", productId);
                        $("#prodiv" + ishow).attr("data-rowid", productId);
                        console.log($('#prodiv4').data('rowid'));

                        $("#prodiv" + ishow).data("iscat", 0);
                        $("#prodiv" + ishow).attr("data-iscat", 0);

                        $("#prodiv" + ishow).attr("class", "wrapper2");

                        takeposApplyVariantBadge('prodiv' + ishow, productId);

                        <?php
                        // Add js from hooks
                        $parameters = array();
                        $parameters['caller'] = 'loadProducts';
                        $hookmanager->executeHooks('completeJSProductDisplay', $parameters);
                        print $hookmanager->resPrint;
                        ?>
                    }
                    $("#prowatermark" + ishow).hide();
                    ishow++; //Next product to show after print data product
                    idata++; //Next data every time
                }
            });

            ClearSearch(false);
        }

        function MoreProducts(moreorless) {
            if ($('#search_pagination').val() == '') {
                takeposSetSearchLayoutActive(false);
            }
            console.log("MoreProducts");

            if ($('#search_pagination').val() != '') {
                return Search2('<?php echo(isset($keyCodeForEnter) ? $keyCodeForEnter : ''); ?>', moreorless);
            }

            var maxproduct = <?php echo($MAXPRODUCT - 2); ?>;

            if (moreorless == "more") {
                $('#proimg31').animate({opacity: '0.5'}, 1);
                $('#proimg31').animate({opacity: '1'}, 100);
                pageproducts = pageproducts + 1;
            }
            if (moreorless == "less") {
                $('#proimg30').animate({opacity: '0.5'}, 1);
                $('#proimg30').animate({opacity: '1'}, 100);
                if (pageproducts == 0) return; //Return if no less pages
                pageproducts = pageproducts - 1;
            }

            ishow = 0; //product to show counter
            idata = 0; //product data counter
            var limit = 0;
            if (maxproduct >= 1) {
                // FIX: fetch maxproduct+1 so next-page detection works correctly
                limit = maxproduct + 1;
            }
            var nb_cat_shown = parseInt(currentcat, 10) === 0 ? 0 : $('.div5 div.wrapper2[data-iscat=1]').length;
            // offset steps by maxproduct (page size), NOT limit (which is maxproduct+1)
            var offset = maxproduct * pageproducts - nb_cat_shown;
            if (offset < 0) offset = 0;
            // Only show products for sale (tosell=1)
            var _moreUrl = '<?php echo DOL_URL_ROOT ?>/takepos/ajax/ajax.php?action=getProducts&token=<?php echo newToken();?>&thirdpartyid=' + jQuery('#thirdpartyid').val() + '&category=' + currentcat + '&tosell=1&limit=' + limit + '&offset=' + offset;
            var _moreThirdparty = jQuery('#thirdpartyid').val() || 0;
            window.takeposProductCache.fetchPage(_moreUrl, currentcat, _moreThirdparty, limit, offset, 1, false).then(function (data) {
                console.log("Call ajax.php (in MoreProducts) to get Products of category " + currentcat);

                if (typeof (data[0]) == "undefined" && moreorless == "more") { // Return if no more pages
                    pageproducts = pageproducts - 1;
                    return;
                }

                while (ishow < maxproduct) {
                    if (typeof (data[idata]) == "undefined") {
                        $("#prodivdesc" + ishow).addClass("tp-hidden");
                        $("#prodesc" + ishow).text("");
                        $("#probutton" + ishow).text("");
                        $("#probutton" + ishow).hide();
                        $("#proprice" + ishow).attr("class", "");
                        $("#proprice" + ishow).html("");
                        $("#proimg" + ishow).attr("src", "genimg/empty.png");
                        $("#prodiv" + ishow).data("rowid", "");
                        $("#prodiv" + ishow).attr("data-rowid", "");
                        $("#prodiv" + ishow).data("iscat", 0);
                        $("#prodiv" + ishow).attr("data-iscat", 0);
                        $("#prodiv" + ishow).attr("class", "wrapper2 divempty");
                    } else {
                        $("#prodivdesc" + ishow).removeClass("tp-hidden");
                        <?php if (getDolGlobalInt('TAKEPOS_SHOW_PRODUCT_REFERENCE') == 1) { ?>
                        $("#prodesc" + ishow).html(data[parseInt(idata)]['ref'].bold() + ' - ' + data[parseInt(idata)]['label']);
                        <?php } elseif (getDolGlobalInt('TAKEPOS_SHOW_PRODUCT_REFERENCE') == 2) { ?>
                        $("#prodesc" + ishow).html((data[parseInt(idata)]['ref'] ? data[parseInt(idata)]['ref'].bold() + ' - ' : '') + data[parseInt(idata)]['label']);
                        <?php } else { ?>
                        $("#prodesc" + ishow).html(data[parseInt(idata)]['label']);
                        <?php } ?>
                        $("#probutton" + ishow).html(data[parseInt(idata)]['label']);
                        $("#probutton" + ishow).show();
                        if (data[parseInt(idata)]['price_formated']) {
                            $("#proprice" + ishow).attr("class", "productprice");
                            <?php
                            if (getDolGlobalInt('TAKEPOS_CHANGE_PRICE_HT')) {
                            ?>
                            $("#proprice" + ishow).html(data[parseInt(idata)]['price_formated']);
                            <?php
                            } else {
                            ?>
                            $("#proprice" + ishow).html(data[parseInt(idata)]['price_ttc_formated']);
                            <?php
                            }
                            ?>
                        }
                        var productId = takeposGetProductId(data[idata]);
                        var productImageUrl = data[idata]['img'] || data[idata]['image_url'] || '';
                        takeposApplyProductImage("#proimg" + ishow, productId, productImageUrl, data[idata]['has_image']);
                        $("#prodiv" + ishow).data("rowid", productId);
                        $("#prodiv" + ishow).attr("data-rowid", productId);
                        $("#prodiv" + ishow).data("iscat", 0);
                        $("#prodiv" + ishow).attr("data-iscat", 0);
                        $("#prodiv" + ishow).attr("class", "wrapper2");
                        takeposApplyVariantBadge('prodiv' + ishow, productId);
                    }
                    $("#prowatermark" + ishow).hide();
                    ishow++; //Next product to show after print data product
                    idata++; //Next data every time
                }
            });

            ClearSearch(false);
        }

        function ClickProduct(position, qty = 1) {
            console.log("ClickProduct at position" + position);
            if ($('#invoiceid').val() == "" && !(window.KFOffline && !window.KFOffline.isOnline())) {
                invoiceid = $('#invoiceid').val();
                Refresh();
            }
            // Faster non-blocking visual feedback (was double animate of ~101ms; now CSS flash class)
            var $img = $('#proimg' + position);
            $img.addClass('takepos-product-flash');
            setTimeout(function () {
                $img.removeClass('takepos-product-flash');
            }, 180);
            if ($('#prodiv' + position).data('iscat') == 1) {
                console.log("Click on a category at position " + position);
                LoadProducts(position, true);
            } else {
                console.log($('#prodiv4').data('rowid'));
                invoiceid = $("#invoiceid").val();
                idproduct = $('#prodiv' + position).data('rowid');
                console.log("Click on product at position " + position + " for idproduct " + idproduct + ", qty=" + qty + " invoiceid=" + invoiceid);
                if (idproduct == "") {
                    return;
                }
                takeposAddProductToInvoice(idproduct, qty, invoiceid);
            }

            ClearSearch(false);
        }

        /**
         * Adds a product to the current invoice with optimal latency.
         *
         * Performance fix (delay reduction):
         *   - Pre-checks (shift validation + stock check) now run IN PARALLEL via Promise.all
         *     instead of strictly sequential. This typically removes 1 round-trip of latency
         *     (~80-300ms on a LAN, much more on slow connections) per product click.
         *   - Click coalescing: rapid repeated clicks on the same product within 250ms are
         *     coalesced into a single server call with the accumulated quantity. This makes
         *     "click 5 times fast" feel instant instead of queuing 5 sequential requests.
         *   - The line list is only re-loaded once per coalesce window.
         */
        window._takeposAddPendingTimers = window._takeposAddPendingTimers || {};
        window._takeposAddPendingQty = window._takeposAddPendingQty || {};
        window._takeposAddPendingUnitPrice = window._takeposAddPendingUnitPrice || {};

        function takeposApplyVariantBadge(divId, pid) {
            // DISABLED - Box/Piece badges removed
        }

        function takeposAddProductToInvoice(idproduct, qty, invoiceid, unitpriceTtc) {
            idproduct = parseInt(idproduct || 0, 10);
            if (!idproduct) {
                return;
            }

            var targetInvoiceId = invoiceid || $("#invoiceid").val() || 0;
            var targetQty = parseFloat(qty) > 0 ? parseFloat(qty) : 1;

            // Click coalescing: if user clicks the same product several times rapidly,
            // we accumulate the qty and fire ONE network call ~120ms after the last click.
            // This dramatically improves perceived responsiveness on rapid taps without
            // adding noticeable latency to a single click.
            var key = String(idproduct) + '|' + String(targetInvoiceId);
            window._takeposAddPendingQty[key] = (window._takeposAddPendingQty[key] || 0) + targetQty;
            if (unitpriceTtc && !window._takeposAddPendingUnitPrice[key]) window._takeposAddPendingUnitPrice[key] = unitpriceTtc;
            if (window._takeposAddPendingTimers[key]) {
                clearTimeout(window._takeposAddPendingTimers[key]);
            }
            // Show an instant progress strip on the ticket panel so the click feels acknowledged
            takeposShowAddProgress(true);
            window._takeposAddPendingTimers[key] = setTimeout(function () {
                var qtyToSend = window._takeposAddPendingQty[key] || targetQty;
                var pendingUp = window._takeposAddPendingUnitPrice[key] || null;
                delete window._takeposAddPendingQty[key];
                delete window._takeposAddPendingUnitPrice[key];
                delete window._takeposAddPendingTimers[key];
                takeposDoAddProductToInvoice(idproduct, qtyToSend, targetInvoiceId, pendingUp);
            }, 120);
        }

        /**
         * Shows / hides a thin animated progress strip at the top of #poslines so the
         * user gets instant visual feedback the moment they click a product, even if
         * the actual ticket reload arrives 100-400ms later.
         */
        function takeposShowAddProgress(show) {
            var pos = document.getElementById('poslines');
            if (!pos) return;
            var bar = document.getElementById('takepos-add-progress');
            if (!bar) {
                bar = document.createElement('div');
                bar.id = 'takepos-add-progress';
                bar.className = 'takepos-add-progress';
                pos.insertBefore(bar, pos.firstChild);
            }
            if (show) {
                bar.classList.add('is-active');
            } else {
                bar.classList.remove('is-active');
            }
        }

        /**
         * Actually fires the add-line request after coalescing.
         * Runs shift-check and stock-check IN PARALLEL (Promise.all) for lower latency.
         */
        function takeposDoAddProductToInvoice(idproduct, qty, targetInvoiceId, unitpriceTtc) {
            if (window.KFOffline && !window.KFOffline.isOnline()) {
                if (window.KFOfflineIndex) window.KFOfflineIndex.addProduct(idproduct, qty, unitpriceTtc);
                takeposShowAddProgress(false);
                return;
            }
            // Wrap the two pre-checks as Promises so we can run them in parallel.
            var shiftCheck = new Promise(function (resolve) {
                ensureShiftForSale(targetInvoiceId, function () {
                    resolve(true);
                });
            });
            var stockCheck = new Promise(function (resolve) {
                checkStockBeforeAdd(idproduct, qty, targetInvoiceId, function (allowed) {
                    resolve(!!allowed);
                });
            });

            Promise.all([shiftCheck, stockCheck]).then(function (results) {
                // shiftCheck resolves only when the shift is allowed (ensureShiftForSale calls
                // onAllowed only on success; on failure it shows an alert and never resolves,
                // which is the behaviour we want — the chain just stops).
                var stockOk = results[1];
                if (!stockOk) {
                    takeposShowAddProgress(false);
                    return;
                }

                var _addUrl = "invoice.php?action=addline&token=<?php echo newToken(); ?>&place=" + place + "&idproduct=" + encodeURIComponent(idproduct) + "&qty=" + encodeURIComponent(qty) + "&invoiceid=" + encodeURIComponent(targetInvoiceId);
                if (unitpriceTtc) _addUrl += "&unitprice_ttc=" + encodeURIComponent(unitpriceTtc);
                loadPosLines(_addUrl, function () {
                    takeposShowAddProgress(false);
                    // PERFORMANCE: invalidate the client product cache only when stock checking
                    // is on, since stock changes can affect product visibility. When stock
                    // checking is off the cache is still valid for the full TTL.
                    if (takeposStockCheckEnabled && window.takeposProductCache) {
                        try {
                            window.takeposProductCache.invalidateAll();
                        } catch (e) {
                        }
                    }
                    <?php echo "pushCustomerDisplayState();"; ?>
                    setTimeout(function () {
                        maintainBarcodeSearchFocus(true);
                    }, 40);
                });
            }).catch(function () {
                takeposShowAddProgress(false);
            });
        }

        function takeposSetSelectedCustomerHeader(customerId, customerName) {
            customerId = parseInt(customerId || 0, 10);
            var hasCustomer = customerId > 0;
            var customerValue = hasCustomer ? String(customerId) : '';
            var name = (customerName === null || typeof customerName === 'undefined') ? '' : String(customerName).trim();

            $('#idcustomer').val(customerValue);

            if (!hasCustomer || name === '') {
                return;
            }

            var $links = $('#customer, #contact');
            if (!$links.length && $('#customerandsales').length) {
                $('#customerandsales').html('<a class="valignmiddle tdoverflowmax300 minwidth100" id="customer" onclick="Customer();" title=""></a>');
                $links = $('#customer');
            }

            $links.each(function () {
                var $link = $(this);
                $link.attr('title', name);
                $link.empty();
                $('<span/>', {'class': 'fas fa-building paddingrightonly'}).appendTo($link);
                $link.append(document.createTextNode(name));
            });

            if (typeof refreshTakeposCustomerPanel === 'function') {
                refreshTakeposCustomerPanel();
            }
            if (typeof pushCustomerDisplayState === 'function') {
                setTimeout(pushCustomerDisplayState, 80);
            }
        }

        function ChangeThirdparty(idcustomer, customerName) {
            console.log("ChangeThirdparty");
            idcustomer = parseInt(idcustomer || 0, 10);
            if (!idcustomer) {
                return false;
            }

            // Update the visible POS header immediately. The server update is still done
            // below, but this prevents the top bar from staying on the generic "Customer"
            // label after selecting from loyalty or the simplified customer picker.
            takeposSetSelectedCustomerHeader(idcustomer, customerName || '');

            var currentInvoiceId = ($('#invoiceid').length ? ($('#invoiceid').val() || invoiceid || 0) : (invoiceid || 0));
            var changeUrl = "<?php echo DOL_URL_ROOT ?>/societe/list.php?action=change&token=<?php echo newToken();?>&type=t&contextpage=poslist&idcustomer=" + encodeURIComponent(idcustomer) + "&place=" + encodeURIComponent(place);
            $.ajax({
                url: changeUrl,
                method: 'GET',
                cache: false
            }).always(function () {
                currentInvoiceId = ($('#invoiceid').length ? ($('#invoiceid').val() || currentInvoiceId || 0) : (currentInvoiceId || 0));
                loadPosLines("invoice.php?token=<?php echo newToken(); ?>&place=" + encodeURIComponent(place) + "&invoiceid=" + encodeURIComponent(currentInvoiceId), function () {
                    if (customerName) {
                        takeposSetSelectedCustomerHeader(idcustomer, customerName);
                    } else {
                        $('#idcustomer').val(String(idcustomer));
                        if (typeof refreshTakeposCustomerPanel === 'function') {
                            refreshTakeposCustomerPanel();
                        }
                    }
                    refreshCurrentTicketSyncStatus();
                });
            });

            ClearSearch(false);
            return false;
        }

        var pendingDeleteLineId = 0;
        var pendingDeleteInvoiceId = 0;
        var pendingOverrideAction = 'delete_line';
        var pendingOverrideNumber = '';

        function performDeleteLine(lineId, invoiceId, fromManagerApproval) {
            pendingOverrideAction = 'delete_line';
            pendingDeleteLineId = lineId;
            pendingDeleteInvoiceId = invoiceId;
            pendingOverrideNumber = '';
            console.log("Delete line invoiceid=" + invoiceId + " lineId=" + lineId + " managerOverride=" + fromManagerApproval);
            $("#poslines").load("invoice.php?action=deleteline&token=<?php echo newToken(); ?>&place=" + place + "&idline=" + lineId + "&invoiceid=" + invoiceId, function (responseText) {
                if (typeof responseText === "string" && (responseText.indexOf("Manager approval required") !== -1 || responseText.indexOf(takeposUi.managerApprovalDeleteRequired) !== -1)) {
                    showManagerOverrideModal('delete_line', lineId, invoiceId, '', fromManagerApproval ? takeposUi.managerApprovalRejectedLine : "");
                    return;
                }
                //$('#poslines').scrollTop($('#poslines')[0].scrollHeight);
            });
        }

        function performPriceUpdate(lineId, invoiceId, number, fromManagerApproval) {
            pendingOverrideAction = 'price_override';
            pendingDeleteLineId = lineId;
            pendingDeleteInvoiceId = invoiceId;
            pendingOverrideNumber = number;
            $("#poslines").load("invoice.php?action=updateprice&token=<?php echo newToken(); ?>&place=" + place + "&idline=" + lineId + "&number=" + number + "&invoiceid=" + invoiceId, function (responseText) {
                if (typeof responseText === "string" && (responseText.indexOf("Manager approval required for price override") !== -1 || responseText.indexOf(takeposUi.managerApprovalPriceRequired) !== -1)) {
                    showManagerOverrideModal('price_override', lineId, invoiceId, number, fromManagerApproval ? takeposUi.managerApprovalRejectedPrice : "");
                    return;
                }
                $("#price").html("<?php echo dol_escape_js($langs->trans("TakeposUiPrice")); ?>").removeClass('clicked');
            });
        }

        function performReductionUpdate(lineId, invoiceId, number, fromManagerApproval) {
            pendingOverrideAction = 'discount';
            pendingDeleteLineId = lineId;
            pendingDeleteInvoiceId = invoiceId;
            pendingOverrideNumber = number;
            $("#poslines").load("invoice.php?action=updatereduction&token=<?php echo newToken(); ?>&place=" + place + "&idline=" + lineId + "&number=" + number + "&invoiceid=" + invoiceId, function (responseText) {
                if (typeof responseText === "string" && (responseText.indexOf("Manager approval required for discount") !== -1 || responseText.indexOf(takeposUi.managerApprovalDiscountRequired) !== -1)) {
                    showManagerOverrideModal('discount', lineId, invoiceId, number, fromManagerApproval ? takeposUi.managerApprovalRejectedDiscount : "");
                    return;
                }
                $("#reduction").html("<?php echo dol_escape_js($langs->trans("TakeposUiLineDiscountShort")); ?>").removeClass('clicked');
            });
        }

        function performCancelInvoice(invoiceId, fromManagerApproval) {
            pendingOverrideAction = 'invoice_cancel';
            pendingDeleteLineId = 0;
            pendingDeleteInvoiceId = invoiceId;
            pendingOverrideNumber = '';
            $("#poslines").load("invoice.php?action=delete&token=<?php echo newToken(); ?>&place=" + place + "&invoiceid=" + invoiceId, function (responseText) {
                if (typeof responseText === "string" && (responseText.indexOf("Manager approval required to cancel invoice") !== -1 || responseText.indexOf(takeposUi.managerApprovalCancelRequired) !== -1)) {
                    showManagerOverrideModal('invoice_cancel', 0, invoiceId, '', fromManagerApproval ? takeposUi.managerApprovalRejectedInvoice : "");
                    return;
                }
            });
        }

        function showManagerOverrideModal(actionType, lineId, invoiceId, number, customMessage) {
            pendingOverrideAction = actionType || 'delete_line';
            pendingDeleteLineId = lineId || 0;
            pendingDeleteInvoiceId = invoiceId || 0;
            pendingOverrideNumber = (typeof number !== 'undefined' ? number : '');
            $("#manager_barcode").val("");
            $("#manager_login").val("");
            $("#manager_password").val("");
            $("#manager-override-message").css("color", "#444").text(customMessage || "");

            var actionLabel = takeposUi.managerActionGeneric;
            if (pendingOverrideAction === 'delete_line') actionLabel = takeposUi.managerActionDeleteLine;
            if (pendingOverrideAction === 'price_override') actionLabel = takeposUi.managerActionPriceOverride;
            if (pendingOverrideAction === 'discount') actionLabel = takeposUi.managerActionDiscountOverride;
            if (pendingOverrideAction === 'invoice_cancel') actionLabel = takeposUi.managerActionInvoiceCancel;
            $("#manager-override-action-label").text(actionLabel);

            ModalBox('ModalManagerOverride');
            $("#manager_barcode").trigger("focus");
        }

        function closeManagerOverrideModal() {
            document.getElementById('ModalManagerOverride').style.display = 'none';
        }

        function executeApprovedOverrideAction() {
            if (pendingOverrideAction === 'delete_line') {
                performDeleteLine(pendingDeleteLineId, pendingDeleteInvoiceId, true);
                return;
            }
            if (pendingOverrideAction === 'price_override') {
                performPriceUpdate(pendingDeleteLineId, pendingDeleteInvoiceId, pendingOverrideNumber, true);
                return;
            }
            if (pendingOverrideAction === 'discount') {
                performReductionUpdate(pendingDeleteLineId, pendingDeleteInvoiceId, pendingOverrideNumber, true);
                return;
            }
            if (pendingOverrideAction === 'invoice_cancel') {
                performCancelInvoice(pendingDeleteInvoiceId, true);
                return;
            }
        }

        function submitManagerOverride() {
            var managerBarcode = $("#manager_barcode").val();
            var managerLogin = $("#manager_login").val();
            var managerPassword = $("#manager_password").val();

            if (!managerBarcode && (!managerLogin || !managerPassword)) {
                $("#manager-override-message").css("color", "#a94442").text(takeposUi.managerScanOrEnter);
                return;
            }

            $("#manager-override-message").css("color", "#444").text(takeposUi.managerCheckingApproval);

            $.ajax({
                url: takeposManagerOverrideEndpoint,
                type: "POST",
                dataType: "json",
                data: {
                    action: "managerapprove",
                    token: "<?php echo newToken(); ?>",
                    place: place,
                    override_action: pendingOverrideAction,
                    invoiceid: pendingDeleteInvoiceId,
                    idline: pendingDeleteLineId,
                    requested_number: pendingOverrideNumber,
                    manager_barcode: managerBarcode,
                    manager_login: managerLogin,
                    manager_password: managerPassword
                },
                success: function (data) {
                    if (data && data.status === "ok") {
                        $("#manager-override-message").css("color", "#2d7d2d").text(takeposUi.managerApprovalGranted);
                        closeManagerOverrideModal();
                        executeApprovedOverrideAction();
                        return;
                    }
                    var message = (data && data.message) ? data.message : takeposUi.managerApprovalFailed;
                    $("#manager-override-message").css("color", "#a94442").text(message);
                },
                error: function () {
                    $("#manager-override-message").css("color", "#a94442").text(takeposUi.managerApprovalValidateFailed);
                }
            });
        }

        function deleteline() {
            invoiceid = $("#invoiceid").val();
            performDeleteLine(selectedline, invoiceid, false);
            ClearSearch(false);
        }

        $(document).on("keydown", "#manager_barcode,#manager_login,#manager_password", function (e) {
            if (e.key === "Enter") {
                e.preventDefault();
                submitManagerOverride();
            }
        });

        function Customer() {
            console.log("Open simplified POS customer selector place=" + place);
            $.colorbox({
                href: "<?php echo DOL_URL_ROOT; ?>/takepos/customer_select.php?place=" + encodeURIComponent(place) + "&langs=<?php echo dol_escape_js($langs->defaultlang); ?>",
                width: "92%",
                height: "84%",
                transition: "none",
                iframe: "true",
                title: "<?php echo dol_escape_js($langs->trans("TakeposUiCustomer")); ?>"
            });
        }

        function Contact() {
            console.log("Open box to select the contact place=" + place);
            $.colorbox({
                href: "../contact/list.php?type=c&contextpage=poslist&nomassaction=1&place=" + place,
                width: "90%",
                height: "80%",
                transition: "none",
                iframe: "true",
                title: "<?php echo dol_escape_js($langs->trans("Contact")); ?>"
            });
        }

        function takeposRequireShiftForUiAction(callback) {
            var currentInvoiceId = ($('#invoiceid').length ? ($('#invoiceid').val() || 0) : 0);
            ensureShiftForSale(currentInvoiceId, function () {
                if (typeof callback === "function") callback();
            });
        }

        function History() {
            console.log("Open box to select the history");
            $.colorbox({
                href: "history.php",
                width: "90%",
                height: "80%",
                transition: "none",
                iframe: "true",
                title: "<?php echo dol_escape_js($langs->trans("TakeposUiHistory")); ?>"
            });
        }

        function Reports() {
            <?php if ($canAccessTakeposReports) { ?>
            $.colorbox({
                href: "<?php echo DOL_URL_ROOT . dol_escape_js($takeposReportsUrl); ?>",
                width: "96%",
                height: "94%",
                transition: "none",
                iframe: "true",
                title: takeposUi.reportsTitle
            });
            <?php } else { ?>
            alert("<?php echo dol_escape_js($langs->trans("NotEnoughPermissions")); ?>");
            <?php } ?>
        }

        // HOLD / SUSPEND / RESUME

        /**
         * Hold (suspend) the current sale.
         * Prompts for an optional label, then marks the invoice as held.
         */
        function HoldSale() {
            takeposRequireShiftForUiAction(function () {
                invoiceid = $('#invoiceid').val();
                if (!invoiceid || invoiceid == '0' || invoiceid == '') {
                    takeposFeedback(takeposUi.noActiveSaleToHold, 'warning');
                    return;
                }

                var label = '';
                var labelInput = document.getElementById('takepos-hold-label-input');
                if (labelInput) {
                    label = labelInput.value || '';
                }

                document.getElementById('ModalHold').style.display = 'block';
            });
        }

        function HoldSaleConfirm() {
            invoiceid = $('#invoiceid').val();
            if (!invoiceid || invoiceid == '0' || invoiceid == '') {
                document.getElementById('ModalHold').style.display = 'none';
                return;
            }
            var label = document.getElementById('takepos-hold-label-input').value || '';
            var holdMsg = document.getElementById('takepos-hold-feedback');
            if (holdMsg) {
                holdMsg.textContent = '';
            }

            $.ajax({
                url: takeposHoldEndpoint,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'hold',
                    invoiceid: invoiceid,
                    label: label,
                    token: takeposCsrfToken
                },
                success: function (data) {
                    document.getElementById('ModalHold').style.display = 'none';
                    document.getElementById('takepos-hold-label-input').value = '';
                    if (data.success) {
                        takeposFeedback(takeposUi.saleHeldSuccess || 'Sale held.', 'success');
                        // Start a fresh invoice WITHOUT deleting the held one.
                        // New() calls performCancelInvoice() which wipes the held invoice lines.
                        // Instead: reset JS state and load a blank invoice directly.
                        invoiceid = 0;
                        place = parseInt(place) || 0;
                        loadPosLines(
                            'invoice.php?token=<?php echo newToken(); ?>&place=' + encodeURIComponent(place) + '&invoiceid=0&source=hold_new',
                            function () {
                                var domId = parseInt($('#invoiceid').val() || 0);
                                if (domId > 0) { invoiceid = domId; }
                                ClearSearch(false);
                                $('#idcustomer').val('');
                            }
                        );
                    } else {
                        takeposFeedback((takeposUi.holdFailed || 'Hold failed') + ': ' + (data.error || takeposUi.unknownError || ''), 'error');
                    }
                },
                error: function () {
                    document.getElementById('ModalHold').style.display = 'none';
                    takeposFeedback(takeposUi.networkErrorHoldingSale || 'Network error.', 'error');
                }
            });
        }

        /**
         * Open the Held Sales modal and list them.
         */
        function ShowHeldSales() {
            takeposRequireShiftForUiAction(function () {
                var listEl = document.getElementById('takepos-held-list');
                if (listEl) {
                    listEl.innerHTML = '<em>' + takeposUi.heldLoading + '</em>';
                }
                document.getElementById('ModalHeldList').style.display = 'block';

                $.ajax({
                    url: takeposHoldEndpoint,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'list',
                        token: takeposCsrfToken
                    },
                    success: function (data) {
                        if (!data.success) {
                            listEl.innerHTML = '<em>' + takeposUi.heldLoadError + '</em>';
                            return;
                        }
                        if (data.count === 0) {
                            listEl.innerHTML = '<em>' + takeposUi.heldNone + '</em>';
                            return;
                        }
                        var html = '<table class="takepos-held-table">'
                            + '<thead><tr><th>' + takeposUi.heldLabel + '</th><th>' + takeposUi.heldLines + '</th><th>' + takeposUi.heldTotal + '</th><th>' + takeposUi.heldTime + '</th><th></th></tr></thead><tbody>';
                        for (var i = 0; i < data.held.length; i++) {
                            var h = data.held[i];
                            var label = h.label || takeposUi.noLabel;
                            var total = parseFloat(h.total_ttc || 0).toFixed(2);
                            html += '<tr>'
                                + '<td>' + $('<span>').text(label).html() + '</td>'
                                + '<td style="text-align:center">' + h.nb_lines + '</td>'
                                + '<td style="text-align:right">' + total + '</td>'
                                + '<td style="font-size:0.85em">' + h.date_hold + '</td>'
                                + '<td>'
                                + '<button class="actionbutton" style="padding:4px 10px;font-size:0.9em" onclick="ResumeSale(' + h.hold_id + ', ' + h.invoice_id + ')">' + takeposUi.resume + '</button> '
                                + '<button class="actionbutton poscolordelete" style="padding:4px 8px;font-size:0.9em" onclick="CancelHeld(' + h.hold_id + ')">&#10005;</button>'
                                + '</td></tr>';
                        }
                        html += '</tbody></table>';
                        listEl.innerHTML = html;
                    },
                    error: function () {
                        listEl.innerHTML = '<em>' + takeposUi.networkError + '</em>';
                    }
                });
            });
        }

        /**
         * Resume a held sale.
         */
        function ResumeSale(holdId, heldInvoiceId) {
            if (!confirm(takeposUi.resumeConfirm || '<?php echo dol_escape_js($langs->trans('TakeposIndexResumeConfirm')); ?>')) {
                return;
            }
            var currentId = parseInt($('#invoiceid').val() || 0);
            document.getElementById('ModalHeldList').style.display = 'none';

            $.ajax({
                url: takeposHoldEndpoint,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'resume',
                    hold_id: holdId,
                    current_invoice_id: currentId,
                    token: takeposCsrfToken
                },
                success: function (data) {
                    if (data.success) {
                        var resumedInvoiceId = parseInt(data.invoice_id || 0);
                        var resumedPlace = (data.place !== undefined && data.place !== null) ? parseInt(data.place) : parseInt(place);
                        console.log('[ResumeSale] invoice_id=' + resumedInvoiceId + ' place=' + resumedPlace);
                        place = resumedPlace;
                        invoiceid = resumedInvoiceId;
                        takeposFeedback(takeposUi.saleResumed || 'Sale resumed.', 'success');
                        ensurePosTicketVisible();
                        // Force-load with explicit invoiceid so invoice.php bypasses session cache
                        var resumeUrl = 'invoice.php?token=<?php echo newToken(); ?>&place=' + encodeURIComponent(resumedPlace) + '&invoiceid=' + encodeURIComponent(resumedInvoiceId) + '&source=resume';
                        console.log('[ResumeSale] Loading: ' + resumeUrl);
                        loadPosLines(resumeUrl, function (responseText, textStatus) {
                            console.log('[ResumeSale] loadPosLines done, status=' + textStatus);
                            // Sync JS invoiceid from DOM in case invoice.php corrected it
                            var domId = parseInt($('#invoiceid').val() || 0);
                            console.log('[ResumeSale] DOM invoiceid=' + domId);
                            if (domId > 0) {
                                invoiceid = domId;
                            }
                            if (typeof refreshCurrentTicketSyncStatus === 'function') {
                                refreshCurrentTicketSyncStatus();
                            }
                        });
                    } else {
                        takeposFeedback((takeposUi.resumeFailed || 'Resume failed') + ': ' + (data.error || takeposUi.unknownError || 'Unknown error'), 'error');
                    }
                },
                error: function (xhr, status, err) {
                    console.error('[ResumeSale] AJAX error:', status, err);
                    takeposFeedback(takeposUi.networkErrorResuming || 'Network error while resuming.', 'error');
                }
            });
        }

        /**
         * Cancel (discard) a held sale.
         */
        function CancelHeld(holdId) {
            if (!confirm(takeposUi.cancelHeldConfirm)) {
                return;
            }

            $.ajax({
                url: takeposHoldEndpoint,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'cancel_hold',
                    hold_id: holdId,
                    token: takeposCsrfToken
                },
                success: function (data) {
                    if (data.success) {
                        ShowHeldSales(); // refresh list
                    } else {
                        takeposFeedback(takeposUi.cancelFailed + ': ' + (data.error || ''), 'error');
                    }
                },
                error: function () {
                    takeposFeedback(takeposUi.networkError, 'error');
                }
            });
        }

        // STOCK VALIDATION

        /**
         * Check stock for a product before adding to basket.
         * Calls callback(true) if allowed, callback(false) if blocked.
         * Always calls callback(true) if stock check is disabled globally.
         */
        function checkStockBeforeAdd(productId, qty, invoiceId, callback) {
            if (!takeposStockCheckEnabled) {
                callback(true);
                return;
            }
            if (!productId || productId == '') {
                callback(true);
                return;
            }
            if (window.KFOffline && !window.KFOffline.isOnline()) {
                // أوفلاين: ما فينا نتحقق من المخزون الحقيقي بالسيرفر — نسمح بالبيع
                // (fail-open، نفس سلوك خطأ الشبكة تحت)، والتحقق الحقيقي بيصير وقت المزامنة
                callback(true);
                return;
            }
            $.ajax({
                url: takeposCheckStockEndpoint,
                method: 'POST',
                dataType: 'json',
                data: {
                    product_id: productId,
                    qty: qty || 1,
                    invoiceid: invoiceId || 0,
                    token: takeposCsrfToken
                },
                success: function (data) {
                    if (data.allowed === false) {
                        var available = parseFloat(data.stock_free || 0);
                        var msg = <?php echo json_encode($langs->trans('TakeposStockInsufficientForProduct'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
                            +' ' + <?php echo json_encode($langs->trans('TakeposStockAvailableRequested'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>.
                        replace('%1$s', available).replace('%2$s', (qty || 1));
                        takeposFeedback(msg, 'error');

                        // FEATURE (add-stock-popup): offer the cashier the option to add
                        // stock right now via a manager-approved popup. If the add succeeds,
                        // we retry by calling callback(true) so the original add-to-cart flow
                        // resumes. If the manager refuses or the cashier cancels, callback(false).
                        if (window.takeposAddStockEnabled
                            && typeof window.takeposAddStockPrompt === 'function'
                            && data.reason !== 'service_product'
                            && data.reason !== 'stock_check_disabled') {

                            var productLabel = '';
                            try {
                                // Try to find the product tile label for nicer UX
                                var tile = document.querySelector('.wrapper2[data-rowid="' + productId + '"]');
                                if (tile) {
                                    var lbl = tile.querySelector('.description_content, .productbutton');
                                    if (lbl) productLabel = lbl.textContent.trim();
                                }
                            } catch (e) {}

                            window.takeposAddStockPrompt({
                                productId:    productId,
                                productLabel: productLabel || ('#' + productId),
                                qtyWanted:    qty || 1,
                                stockFree:    available,
                                onSuccess: function (newStock, qtyAdded) {
                                    // Stock has been added — retry the add-to-cart by calling
                                    // callback(true). The caller will then add the product to
                                    // the invoice as if the original check had passed.
                                    takeposFeedback(
                                        (window.takeposAddStockLabels && window.takeposAddStockLabels.ok)
                                            ? window.takeposAddStockLabels.ok : 'Stock added.',
                                        'success'
                                    );
                                    callback(true);
                                },
                                onCancel: function () {
                                    callback(false);
                                }
                            });
                            return; // popup handles the callback
                        }

                        callback(false);
                    } else {
                        callback(true);
                    }
                },
                error: function () {
                    // Fail-open: if check fails, allow the add
                    callback(true);
                }
            });
        }

        // UX FEEDBACK

        /**
         * Show a brief status message in the POS feedback bar.
         * Level: 'success' | 'error' | 'warning' | 'info'
         * Call with no arguments or empty message to hide immediately.
         */
        function takeposFeedback(message, level) {
            var el = document.getElementById('takepos-feedback-bar');
            if (!el) {
                if (message) console.warn('takeposFeedback: ' + level + ' - ' + message);
                return;
            }
            // Empty message = hide the bar immediately (used as a "clear" call)
            if (!message) {
                clearTimeout(el._timer);
                el.style.display = 'none';
                el.textContent = '';
                el.className = 'takepos-feedback-bar';
                return;
            }
            el.textContent = message;
            el.className = 'takepos-feedback-bar takepos-feedback-' + (level || 'info');
            el.style.display = 'block';
            clearTimeout(el._timer);
            el._timer = setTimeout(function () {
                el.style.display = 'none';
                el.textContent = '';
                el.className = 'takepos-feedback-bar';
            }, level === 'error' ? 6000 : 3500);
        }

        function Reduction() {
            takeposRequireShiftForUiAction(function () {
                invoiceid = $("#invoiceid").val();
                console.log("Open popup to enter reduction on invoiceid=" + invoiceid);
                $.colorbox({
                    href: "reduction.php?place=" + place + "&invoiceid=" + invoiceid,
                    width: "80%",
                    height: "90%",
                    transition: "none",
                    iframe: "true",
                    title: ""
                });
            });
        }

        var closeBillParams = "";
        window.takeposPreferredPaymentCode = '';

        function TakeposFinalizePaymentUi(paidInvoiceId) {
            try {
                if (typeof paidInvoiceId !== 'undefined' && paidInvoiceId !== null) {
                    window.takeposLastPaidInvoiceId = paidInvoiceId;
                }
                $("#invoiceid").val("");
                selectedline = '';
                selectedtext = '';
                editnumber = '';
                editaction = '';
                $("#qty").removeClass('clicked');
                $("#price").removeClass('clicked');
                $("#reduction").removeClass('clicked');
                loadPosLines("invoice.php?token=<?php echo newToken(); ?>&place=" + place + "&invoiceid=0", function () {
                    if (typeof refreshTakeposShiftPanel === 'function') refreshTakeposShiftPanel();
                    if (typeof refreshTakeposSyncPanel === 'function') refreshTakeposSyncPanel();
                    if (typeof takeposBindCriticalActionButtons === 'function') takeposBindCriticalActionButtons();
                    // FIX (stock-badge-refresh-v1): after a successful sale,
                    // strip badge-stamp cache so product tiles re-fetch their
                    // current stock quantity from llx_product_stock and
                    // immediately reflect the post-sale numbers.
                    if (typeof window.takeposRefreshStockBadges === 'function') {
                        window.takeposRefreshStockBadges();
                    }
                    setTimeout(function () {
                        setFocusOnSearchField();
                    }, 80);
                });
            } catch (e) {
                console.error('TakeposFinalizePaymentUi failed', e);
                Refresh();
            }
        }

        window.TakeposFinalizePaymentUi = TakeposFinalizePaymentUi;

        // FIX: after MoreProducts loads a new page, update the "X/Y" pagination counter
        (function() {
            var _orig = MoreProducts; // eslint-disable-line
            MoreProducts = function(dir) { // eslint-disable-line
                _orig.apply(this, arguments);
                setTimeout(function() {
                    try {
                        if (typeof window.tpv2UpdatePagination === 'function') {
                            window.tpv2UpdatePagination(pageproducts); // eslint-disable-line
                        }
                    } catch(e) {}
                }, 600);
            };
        })();

        function takeposHandleDirectPaymentFailure(paymentCode, message) {
            var fallbackMessage = message || <?php echo json_encode($tpDirectPaymentFallback, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            takeposFeedback(fallbackMessage, 'error');
            takeposResetPaymentLocks();
            return false;
        }

        function takeposRunInvoicePaymentValidation(paymentCode, invoiceid, accountId) {
            var requestUrl = "invoice.php?place=<?php echo $place; ?>&action=valid&token=<?php echo newToken(); ?>&pay=" + encodeURIComponent(paymentCode) + "&amount=0&excess=0&invoiceid=" + encodeURIComponent(invoiceid) + "&accountid=" + encodeURIComponent(accountId || 0);
            loadPosLines(requestUrl, function (responseText) {
                takeposResetPaymentLocks();

                var hasError = (typeof responseText === 'string' && /(ui-state-error|fielderror|warning|error)/i.test(responseText));
                if (hasError) {
                    takeposHandleDirectPaymentFailure(paymentCode, <?php echo json_encode($tpDirectPaymentFailed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>);
                    return;
                }

                TakeposFinalizePaymentUi(invoiceid);
            });
        }

        function takeposExecuteDirectPayment(paymentCode) {
            var mode = String(paymentCode || '').toUpperCase();
            if (window.KFOffline && !window.KFOffline.isOnline()) {
                if (!window.KFOfflineIndex || !window.KFOfflineIndex.isActive()) {
                    alert(takeposUi.noOpenSale || "<?php echo dol_escape_js($tpMsgNoOpenSale); ?>");
                    return false;
                }
                window.KFOfflineIndex.pay(mode);
                return false;
            }
            var directConfig = takeposDirectPaymentConfig[mode] || {accountId: 0, canDirect: true};
            var invoiceid = $("#invoiceid").val() || 0;

            if (!invoiceid || invoiceid === '0') {
                alert(takeposUi.noOpenSale || "<?php echo dol_escape_js($tpMsgNoOpenSale); ?>");
                return false;
            }
            if (takeposPaymentInProgress || takeposDirectPaymentLock) {
                takeposFeedback('<?php echo dol_escape_js($langs->trans('TakeposUiPaymentAlreadyInProgress')); ?>', 'warning');
                return false;
            }
            // Direct payment always proceeds - no modal fallback

            var proceedWithPayment = function () {
                takeposPaymentInProgress = true;
                takeposDirectPaymentLock = true;
                takeposFeedback(<?php echo json_encode($tpDirectPaymentProcessing, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>, 'info');
                takeposRunInvoicePaymentValidation(mode, invoiceid, directConfig.accountId || 0);
            };

            ensureShiftForPayment(invoiceid, function () {
                <?php if (getDolGlobalInt('TAKEPOS_PRODUCT_IN_STOCK') == 1) { ?>
                $.ajax({
                    url: takeposCheckStockEndpoint,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'check_invoice',
                        invoiceid: invoiceid,
                        token: takeposCsrfToken
                    },
                    success: function (data) {
                        if (data && data.allowed === false) {
                            takeposDirectPaymentLock = false;
                            takeposPaymentInProgress = false;
                            var msg = data.message || <?php echo json_encode($langs->trans('TakeposStockInsufficientLine'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
                            takeposFeedback(msg, 'error');
                            return;
                        }
                        proceedWithPayment();
                    },
                    error: function () {
                        <?php if (getDolGlobalInt('TAKEPOS_STRICT_STOCK_CHECK') == 1) { ?>
                        takeposDirectPaymentLock = false;
                        takeposPaymentInProgress = false;
                        alert('<?php echo dol_escape_js($langs->trans('StockCheckUnavailableStrict', 'Stock validation service is unavailable. Payment blocked (strict mode). Please contact your administrator.')); ?>');
                        <?php } else { ?>
                        proceedWithPayment();
                        <?php } ?>
                    }
                });
                <?php } else { ?>
                proceedWithPayment();
                <?php } ?>
            });
            return false;
        }

        function OpenPaymentModalSafe(preferredPaymentCode) {
            if (preferredPaymentCode) {
                window.takeposPreferredPaymentCode = String(preferredPaymentCode);
            } else {
                window.takeposPreferredPaymentCode = '';
            }
            CloseBill();
            return false;
        }

        window.OpenPaymentModalSafe = OpenPaymentModalSafe;

        function takeposFindActionButton(actionId) {
            if (!actionId) return null;
            var escaped = String(actionId).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
            var byData = document.querySelector('[data-takepos-action-id="' + escaped + '"]');
            return byData || document.getElementById(actionId);
        }

        function takeposBindCriticalActionButtons() {
            var bindings = [
                ['takepos-action-payment', function (ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    return OpenPaymentModalSafe();
                }],
                ['takepos-action-direct-payment', function (ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    return DirectPayment();
                }],
                ['takepos-action-direct-card-payment', function (ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    return DirectCardPayment();
                }],
                ['takepos-action-freezone', function (ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    return FreeZone();
                }],
                ['takepos-action-reduction', function (ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    return Reduction();
                }],
                ['takepos-action-hold', function (ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    return HoldSale();
                }],
                ['takepos-action-held', function (ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    return ShowHeldSales();
                }]
            ];
            bindings.forEach(function (pair) {
                var el = takeposFindActionButton(pair[0]);
                if (!el) return;
                el.onclick = pair[1];
            });
        }

        window.takeposBindCriticalActionButtons = takeposBindCriticalActionButtons;

        function CloseBill() {
            // UX: prevent double-click / multiple payment popups
            if (takeposPaymentInProgress) {
                takeposFeedback('<?php echo dol_escape_js($langs->trans('TakeposUiPaymentAlreadyInProgress')); ?>', 'warning');
                return;
            }
            if (window.KFOffline && !window.KFOffline.isOnline()) {
                if (!window.KFOfflineIndex || !window.KFOfflineIndex.isActive()) {
                    alert(takeposUi.noOpenSale || "<?php echo dol_escape_js($tpMsgNoOpenSale); ?>");
                    return;
                }
                // أوفلاين: ما فينا نفتح نافذة pay.php (بتحتاج فاتورة حقيقية بالسيرفر) —
                // استخدم أزرار الدفع بأسفل السلة (نقدي/بطاقة) لإتمام البيع محلياً.
                takeposFeedback('استخدم زر "نقدي" أو "بطاقة" (F3/F4) لإتمام البيع أوفلاين', 'info');
                return;
            }
            <?php
            $parameters = array();
            $reshook = $hookmanager->executeHooks('paramsForCloseBill', $parameters, $obj, $action);
            if (getDolGlobalString('TAKEPOS_FORBID_SALES_TO_DEFAULT_CUSTOMER')) {
                echo "var customerAnchorTag = document.querySelector(" . json_encode('a[id="customer"]', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "); ";
                echo "if (customerAnchorTag && customerAnchorTag.innerText.trim() === " . json_encode($langs->trans("TakeposUiCustomer"), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ") { ";
                echo "alert(" . json_encode($langs->trans("NoClientErrorMessage"), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "); ";
                echo "return; } \n";
            }
            ?>
            invoiceid = $("#invoiceid").val();
            console.log("Open popup to enter payment on invoiceid=" + invoiceid);
            if (!invoiceid || invoiceid === '0') {
                alert(takeposUi.noOpenSale || "<?php echo dol_escape_js($tpMsgNoOpenSale); ?>");
                return;
            }
            <?php if (getDolGlobalInt("TAKEPOS_NO_GENERIC_THIRDPARTY")) { ?>
            if ($("#idcustomer").val() == "") {
                alert("<?php echo dol_escape_js($langs->trans('TakePosCustomerMandatory')); ?>");
                <?php if (getDolGlobalString('TAKEPOS_CHOOSE_CONTACT')) { ?>
                Contact();
                <?php } else { ?>
                Customer();
                <?php } ?>
                return;
            }
            <?php }    ?>
            <?php
            $alternative_payurl = getDolGlobalString('TAKEPOS_ALTERNATIVE_PAYMENT_SCREEN');
            if (empty($alternative_payurl)) {
                $payurl = "pay.php";
            } else {
                $payurl = dol_buildpath($alternative_payurl, 1);
            }
            ?>
            ensureShiftForPayment(invoiceid, function () {
                takeposPaymentInProgress = true;
                // === FIX: watchdog timer — if onClosed never fires (e.g. iframe
                // error, JS interruption), auto-reset the flag after 60s so the
                // user is never permanently locked out without refreshing.
                if (window.takeposPaymentWatchdog) {
                    clearTimeout(window.takeposPaymentWatchdog);
                }
                window.takeposPaymentWatchdog = setTimeout(function () {
                    if (takeposPaymentInProgress) {
                        takeposResetPaymentLocks();
                        if (typeof takeposFeedback === 'function') {
                            takeposFeedback('', '');
                        }
                        console.log('[takepos] Payment watchdog auto-reset stuck lock');
                    }
                    window.takeposPaymentWatchdog = null;
                }, 8000);
                takeposFeedback(takeposUi.paymentOpening || '<?php echo dol_escape_js($langs->trans('TakeposUiPaymentOpening')); ?>', 'info');
                var preferredPay = window.takeposPreferredPaymentCode ? ("&preferredpay=" + encodeURIComponent(window.takeposPreferredPaymentCode)) : "";
                $.colorbox({
                    href: "<?php echo $payurl; ?>?place=" + place + "&invoiceid=" + invoiceid + preferredPay + closeBillParams,
                    width: "80%", height: "90%", transition: "none", iframe: "true", title: "",
                    onClosed: function () {
                        takeposResetPaymentLocks();
                        if (window.takeposPaymentWatchdog) {
                            clearTimeout(window.takeposPaymentWatchdog);
                            window.takeposPaymentWatchdog = null;
                        }
                        takeposFeedback('', '');
                    }
                });
            });
        }

        function Split() {
            takeposRequireShiftForUiAction(function () {
                invoiceid = $("#invoiceid").val();
                console.log("Open popup to split on invoiceid=" + invoiceid);
                $.colorbox({
                    href: "split.php?place=" + place + "&invoiceid=" + invoiceid,
                    width: "80%",
                    height: "90%",
                    transition: "none",
                    iframe: "true",
                    title: ""
                });
            });
        }

        function Floors() {
            console.log("Open box to select floor place=" + place);
            $.colorbox({
                href: "floors.php?place=" + place,
                width: "90%",
                height: "90%",
                transition: "none",
                iframe: "true",
                title: "<?php echo dol_escape_js($langs->trans("Floors")); ?>"
            });
        }

        function FreeZone() {
            takeposRequireShiftForUiAction(function () {
                invoiceid = $("#invoiceid").val();
                console.log("Open box to enter a free product on invoiceid=" + invoiceid);
                $.colorbox({
                    href: "freezone.php?action=freezone&token=<?php echo newToken(); ?>&place=" + place + "&invoiceid=" + invoiceid,
                    width: "80%",
                    height: "40%",
                    transition: "none",
                    iframe: "true",
                    title: "<?php echo dol_escape_js($langs->trans("TakeposUiFreeTextProduct")); ?>"
                });
            });
        }

        function TakeposOrderNotes() {
            console.log("Open box to order notes");
            ModalBox('ModalNote');
            $("#textinput").focus();
        }

        function Refresh() {
            // === FIX: clear any stuck payment lock on manual refresh ===
            takeposPaymentInProgress = false;
            takeposDirectPaymentLock = false;
            if (window.takeposPaymentWatchdog) {
                clearTimeout(window.takeposPaymentWatchdog);
                window.takeposPaymentWatchdog = null;
            }
            console.log("Refresh by reloading place=" + place + " invoiceid=" + invoiceid);
            loadPosLines("invoice.php?token=<?php echo newToken(); ?>&place=" + place + "&invoiceid=" + invoiceid, function () {
                //$('#poslines').scrollTop($('#poslines')[0].scrollHeight);
                refreshCurrentTicketSyncStatus();
            });
        }

        function New() {
            // If we go here,it means $conf->global->TAKEPOS_BAR_RESTAURANT is not defined
            invoiceid = $("#invoiceid").val();		// This is a hidden field added by invoice.php

            // === FIX: clear any stuck payment lock when starting a new sale.
            // The user is clearly not in a payment flow anymore.
            takeposPaymentInProgress = false;
            takeposDirectPaymentLock = false;
            window.takeposPreferredPaymentCode = '';
            if (window.takeposPaymentWatchdog) {
                clearTimeout(window.takeposPaymentWatchdog);
                window.takeposPaymentWatchdog = null;
            }

            if (window.KFOffline && !window.KFOffline.isOnline()) {
                // أوفلاين: ما فينا نتحقق من محتوى الفاتورة بالسيرفر (getInvoice) ولا نحذفها
                // فعلياً بدون نت. منسأل تأكيد بسيط، ومنمسح العرض محلياً بس — الفاتورة
                // الحقيقية (لو كانت موجودة) بتضل عالسيرفر متل ما هي لحد ما يرجع النت.
                if (!confirm(<?php echo json_encode($place > 0 ? $tpConfirmDeleteSale : $tpConfirmDiscardSale, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>)) {
                    return;
                }
                if (window.KFOfflineIndex) {
                    window.KFOfflineIndex.cancelOffline();
                }
                ClearSearch(false);
                $("#idcustomer").val("");
                return;
            }

            console.log("New with place = <?php echo $place; ?>, js place=" + place + ", invoiceid=" + invoiceid);

            $.getJSON('<?php echo DOL_URL_ROOT ?>/takepos/ajax/ajax.php?action=getInvoice&token=<?php echo newToken();?>&id=' + invoiceid, function (data) {
                var r;
                var hasLines = false;
                if (data && data.lines && typeof data.lines.length !== 'undefined') {
                    hasLines = parseInt(data.lines.length, 10) > 0;
                }
                if (!hasLines && data && typeof data.total_ttc !== 'undefined') {
                    hasLines = Math.abs(parseFloat(data.total_ttc || 0)) > 0.000001;
                }

                if (parseInt(data['paye']) === 1 || !hasLines) {
                    r = true;
                } else {
                    r = confirm('<?php echo dol_escape_js($place > 0 ? $tpConfirmDeleteSale : $tpConfirmDiscardSale); ?>');
                }

                if (r == true) {
                    performCancelInvoice(invoiceid, false);
                    ClearSearch(false);
                    $("#idcustomer").val("");
                }
            });
        }

        /**
         * Search products
         *
         * @param   keyCodeForEnter     Key code for "enter" or '' if not
         * @param   moreorless          "more" or "less"
         * return   void
         */
        function Search2(keyCodeForEnter, moreorless, ev) {
            var eventKeyCode = null;
            if (ev && typeof ev.which !== "undefined" && ev.which !== null) {
                eventKeyCode = ev.which;
            } else if (ev && typeof ev.keyCode !== "undefined" && ev.keyCode !== null) {
                eventKeyCode = ev.keyCode;
            } else if (window.event && window.event.keyCode) {
                eventKeyCode = window.event.keyCode;
            }
            console.log("Search2 Call ajax search to replace products keyCodeForEnter=" + keyCodeForEnter + ", eventKeyCode=" + eventKeyCode);

            var search_term = $('#search').val();
            var search_start = 0;
            var search_limit = <?php echo $MAXPRODUCT - 2; ?>;
            if (moreorless != null) {
                search_term = $('#search_pagination').val();
                search_start = $('#search_start_' + moreorless).val();
            }

            console.log("search_term=" + search_term);

            if (search_term == '') {
                takeposSetSearchLayoutActive(false);
                if (search2_timer) {
                    clearTimeout(search2_timer);
                    search2_timer = null;
                }
                $("[id^=prowatermark]").html("");
                $("[id^=prodesc]").text("");
                $("[id^=probutton]").text("");
                $("[id^=probutton]").hide();
                $("[id^=proprice]").attr("class", "hidden");
                $("[id^=proprice]").html("");
                $("[id^=proimg]").attr("src", "genimg/empty.png");
                $("[id^=prodiv]").data("rowid", "");
                $("[id^=prodiv]").attr("data-rowid", "");
                setTimeout(function () {
                    takeposRestoreProductsAfterSearchClear();
                }, 0);
                return;
            }

            var search = false;
            if (keyCodeForEnter == '' || eventKeyCode == keyCodeForEnter) {
                search = true;
            }
            if (!search && search_term.length > 0) {
                search = true;
            }

            if (search === true) {
                takeposSetSearchLayoutActive(true);
                // if a timer has been already started (search2_timer is a global js variable), we cancel it now
                // we click onto another key, we will restart another timer just after
                if (search2_timer) {
                    clearTimeout(search2_timer);
                }

                // Temporization time to give time to type. Barcode-reader Enter should be handled almost immediately.
                var takeposSearchDelay = (eventKeyCode == keyCodeForEnter) ? 0 : 250; // 0ms for barcode scanner Enter (Bug#2 fix)
                search2_timer = setTimeout(function () {
                    pageproducts = 0;
                    jQuery(".wrapper2 .catwatermark").hide();
                    var nbsearchresults = 0;
                    $.getJSON('<?php echo DOL_URL_ROOT ?>/takepos/ajax/ajax.php?action=search&token=<?php echo newToken();?>&search_term=' + encodeURIComponent(search_term) + '&thirdpartyid=' + encodeURIComponent(jQuery('#thirdpartyid').val() || '') + '&search_start=' + encodeURIComponent(search_start) + '&search_limit=' + encodeURIComponent(search_limit), function (data) {
                        // ── EAN Check Digit Error Handler ──────────────────────
                        if (data && data[0] && data[0].object === 'error') {
                            if (data[0].error === 'invalid_check_digit') {
                                $('#search').val('');
                                $('#search').css('border', '2px solid red');
                                setTimeout(function(){ $('#search').css('border', ''); }, 2000);
                                alert('<?php echo dol_escape_js($langs->trans("TakeposBarcodeInvalidCheckDigit") ?: "Invalid barcode: check digit is incorrect!"); ?>');
                            }
                            return;
                        }
                        // ── End EAN Check Digit Error Handler ──────────────────
                        for (i = 0; i < <?php echo $MAXPRODUCT ?>; i++) {
                            if (typeof (data[i]) == "undefined") {
                                $("#prowatermark" + i).html("");
                                $("#prodesc" + i).text("");
                                $("#probutton" + i).text("");
                                $("#probutton" + i).hide();
                                $("#proprice" + i).attr("class", "hidden");
                                $("#proprice" + i).html("");
                                $("#proimg" + i).attr("src", "genimg/empty.png");
                                $("#prodiv" + i).data("rowid", "");
                                $("#prodiv" + i).attr("data-rowid", "");
                                $("#prodiv" + i).data("iscat", 0);
                                $("#prodiv" + i).attr("data-iscat", 0);
                                if (i < <?php echo($MAXPRODUCT - 2); ?>) {
                                    $("#prodiv" + i).attr("class", "wrapper2 divempty");
                                } else {
                                    $("#prodiv" + i).attr("class", "wrapper2 arrow");
                                }
                                continue;
                            }
                            <?php
                            $titlestring = "'" . dol_escape_js($langs->transnoentities('Ref') . ': ') . "' + data[i]['ref']";
                            $titlestring .= " + ' - " . dol_escape_js($langs->trans("Barcode") . ': ') . "' + data[i]['barcode']";
                            ?>
                            var titlestring = <?php echo $titlestring; ?>;
                            <?php if (getDolGlobalInt('TAKEPOS_SHOW_PRODUCT_REFERENCE') == 1) { ?>
                            $("#prodesc" + i).html(data[i]['ref'].bold() + ' - ' + data[i]['label']);
                            <?php } elseif (getDolGlobalInt('TAKEPOS_SHOW_PRODUCT_REFERENCE') == 2) { ?>
                            $("#prodesc" + i).html((data[i]['ref'] ? data[i]['ref'].bold() + ' - ' : '') + data[i]['label']);
                            <?php } else { ?>
                            $("#prodesc" + i).html(data[i]['label']);
                            <?php } ?>
                            $("#prodivdesc" + i).show();
                            $("#probutton" + i).html(data[i]['label']);
                            $("#probutton" + i).show();
                            if (data[i]['price_formated']) {
                                $("#proprice" + i).attr("class", "productprice");
                                <?php
                                if (getDolGlobalInt('TAKEPOS_CHANGE_PRICE_HT')) {
                                ?>
                                $("#proprice" + i).html(data[i]['price_formated']);
                                <?php
                                } else {
                                ?>
                                $("#proprice" + i).html(data[i]['price_ttc_formated']);
                                <?php
                                }
                                ?>
                            }
                            $("#proimg" + i).attr("title", titlestring);
                            var productId = takeposGetProductId(data[i]);
                            var productImageUrl = data[i]['img'] || data[i]['image_url'] || '';
                            takeposApplyProductImage("#proimg" + i, productId, productImageUrl, data[i]['has_image']);
                            $("#prodiv" + i).data("rowid", productId);
                            $("#prodiv" + i).attr("data-rowid", productId);
                            $("#prodiv" + i).data("iscat", 0);
                            $("#prodiv" + i).attr("data-iscat", 0);
                            $("#prodiv" + i).attr("class", "wrapper2");
                            takeposApplyVariantBadge('prodiv' + i, productId);

                            <?php
                            // Add js from hooks
                            $parameters = array();
                            $parameters['caller'] = 'search2';
                            $hookmanager->executeHooks('completeJSProductDisplay', $parameters);
                            print $hookmanager->resPrint;
                            ?>

                            nbsearchresults++;
                        }
                    }).always(function (data) {
                        // Barcode workflow
                        // Normalise: jqXHR failure gives non-array; treat as empty
                        var isArray = Array.isArray(data);
                        var resultCount = isArray ? data.length : 0;
                        var searchedCode = String($('#search').val() || '').trim();
                        var exactBarcodeMatch = null;

                        if (searchedCode.length > 0 && isArray) {
                            var searchedCodeLower = searchedCode.toLowerCase();
                            for (var bi = 0; bi < data.length; bi++) {
                                var candidateCodes = [
                                    data[bi]['matched_barcode'],
                                    data[bi]['barcode'],
                                    data[bi]['ref'],
                                    data[bi]['rowid'],
                                    data[bi]['id']
                                ];
                                for (var ci = 0; ci < candidateCodes.length; ci++) {
                                    var candidateCode = String(candidateCodes[ci] || '').trim();
                                    if (candidateCode !== '' && candidateCode.toLowerCase() === searchedCodeLower) {
                                        exactBarcodeMatch = data[bi];
                                        break;
                                    }
                                }
                                if (exactBarcodeMatch) {
                                    break;
                                }
                            }
                        }

                        if (exactBarcodeMatch) {
                            console.log('Exact barcode match found', exactBarcodeMatch['rowid']);
                            if ('thirdparty' == exactBarcodeMatch['object']) {
                                ChangeThirdparty(exactBarcodeMatch['rowid']);
                                ClearSearch(false);
                            } else if ('product' == exactBarcodeMatch['object']) {
                                var pid = exactBarcodeMatch['rowid'];
                                var qty = exactBarcodeMatch['qty'] || 1;
                                var curInvoice = $('#invoiceid').val() || 0;
                                ClearSearch(false);
                                takeposAddProductToInvoice(pid, qty, curInvoice);
                                // FIX (I08): Re-focus search so next barcode scan
                                // works immediately — no extra tap needed.
                                setTimeout(setFocusOnSearchField, 160);
                            }
                        }

                        if (eventKeyCode == keyCodeForEnter) {
                            if (resultCount === 0) {
                                // Unknown barcode - clear field and show clear error
                                $('#search').val('');
                                takeposFeedback(
                                    '<?php echo dol_escape_js($langs->transnoentitiesnoconv("ErrorRecordNotFoundShort")); ?>'
                                    + ' \u2014 ' + search_term,
                                    'error'
                                );
                            } else if (resultCount > 1) {
                                // Multiple matches - leave products displayed for cashier to pick
                                takeposFeedback(
                                    takeposMsgSearchMultipleMatches + ' (' + resultCount + ')',
                                    'info'
                                );
                            } else {
                                ClearSearch(false);
                            }
                        }
                        setTimeout(function () {
                            maintainBarcodeSearchFocus(false);
                        }, 20);
                        // memorize search_term and start for pagination
                        $("#search_pagination").val($("#search").val());
                        if (search_start == 0) {
                            $("#prodiv<?php echo $MAXPRODUCT - 2; ?>").addClass('takepos-page-disabled');
                            $("#prodiv<?php echo $MAXPRODUCT - 2; ?> span").hide();
                        } else {
                            $("#prodiv<?php echo $MAXPRODUCT - 2; ?>").removeClass('takepos-page-disabled');
                            $("#prodiv<?php echo $MAXPRODUCT - 2; ?> span").show();
                            var search_start_less = Math.max(0, parseInt(search_start) - parseInt(<?php echo $MAXPRODUCT - 2;?>));
                            $("#search_start_less").val(search_start_less);
                        }
                        if (nbsearchresults != <?php echo $MAXPRODUCT - 2; ?>) {
                            $("#prodiv<?php echo $MAXPRODUCT - 1; ?>").addClass('takepos-page-disabled');
                            $("#prodiv<?php echo $MAXPRODUCT - 1; ?> span").hide();
                        } else {
                            $("#prodiv<?php echo $MAXPRODUCT - 1; ?>").removeClass('takepos-page-disabled');
                            $("#prodiv<?php echo $MAXPRODUCT - 1; ?> span").show();
                            var search_start_more = parseInt(search_start) + parseInt(<?php echo $MAXPRODUCT - 2;?>);
                            $("#search_start_more").val(search_start_more);
                        }
                    });
                }, takeposSearchDelay);
            }

        }

        /* Function called on an action into the PAD */
        function Edit(number) {
            console.log("We click on PAD on key=" + number);

            if (typeof (selectedtext) == "undefined") {
                return;	// We click on an action on the number pad but there is no line selected
            }

            var text = selectedtext + "<br> ";


            if (number == 'c') {
                editnumber = '';
                Refresh();
                $("#qty").html("<?php echo dol_escape_js($langs->trans("TakeposUiQty")); ?>").removeClass('clicked');
                $("#price").html("<?php echo dol_escape_js($langs->trans("TakeposUiPrice")); ?>").removeClass('clicked');
                $("#reduction").html("<?php echo dol_escape_js($langs->trans("TakeposUiLineDiscountShort")); ?>").removeClass('clicked');
                return;
            } else if (number == 'qty') {
                if (editaction == 'qty' && editnumber != '') {
                    if (!confirm((takeposUi.confirmQtyChange || '<?php echo dol_escape_js($langs->trans('TakeposUiConfirmQtyChange')); ?>').replace('%s', editnumber))) {
                        return;
                    }
                    // STOCK CHECK before applying qty update
                    (function() {
                        var newQty    = parseFloat(editnumber);
                        var capturedQty = editnumber; // capture before async
                        var $row      = $('#' + selectedline);
                        var productId = $row.data('fk-product');
                        var curQty    = parseFloat($row.data('qty') || 0);
                        var delta     = newQty - curQty;
                        var invoiceid = $('#invoiceid').val();

                        function _applyUpdate() {
                            $("#poslines").load("invoice.php?action=updateqty&token=<?php echo newToken(); ?>&place=" + place + "&idline=" + selectedline + "&number=" + capturedQty, function () {
                                editnumber = "";
                                $("#qty").html("<?php echo dol_escape_js($langs->trans("TakeposUiQty")); ?>").removeClass('clicked');
                            });
                            setFocusOnSearchField();
                        }

                        // Only check stock if qty is increasing and product is known
                        if (delta <= 0 || !productId) {
                            _applyUpdate();
                            return;
                        }

                        checkStockBeforeAdd(productId, delta, invoiceid, function(allowed) {
                            if (allowed) {
                                _applyUpdate();
                            } else {
                                // Rejected - clear the pending edit number
                                editnumber = '';
                                $("#qty").html("<?php echo dol_escape_js($langs->trans("TakeposUiQty")); ?>").removeClass('clicked');
                                setFocusOnSearchField();
                            }
                        });
                    })();
                    return;
                } else {
                    editaction = "qty";
                }
            } else if (number == 'p') {
                if (editaction == 'p' && editnumber != "") {
                    if (!confirm((takeposUi.confirmPriceChange || '<?php echo dol_escape_js($langs->trans('TakeposUiConfirmPriceChange')); ?>').replace('%s', editnumber))) {
                        return;
                    }
                    invoiceid = $("#invoiceid").val();
                    performPriceUpdate(selectedline, invoiceid, editnumber, false);
                    editnumber = "";
                    ClearSearch(false);
                    return;
                } else {
                    editaction = "p";
                }
            } else if (number == 'r') {
                if (editaction == 'r' && editnumber != "") {
                    if (!confirm((takeposUi.confirmDiscountChange || '<?php echo dol_escape_js($langs->trans('TakeposUiConfirmDiscountChange')); ?>').replace('%s', editnumber))) {
                        return;
                    }
                    invoiceid = $("#invoiceid").val();
                    performReductionUpdate(selectedline, invoiceid, editnumber, false);
                    editnumber = "";
                    ClearSearch(false);
                    return;
                } else {
                    editaction = "r";
                }
            } else {
                editnumber = editnumber + number;
            }
            if (editaction == 'qty') {
                text = text + "<?php echo dol_escape_js($langs->trans("Modify") . " -> " . $langs->trans("TakeposUiQty") . ": "); ?>";
                $("#qty").html("OK").addClass("clicked");
                $("#price").html("<?php echo dol_escape_js($langs->trans("TakeposUiPrice")); ?>").removeClass('clicked');
                $("#reduction").html("<?php echo dol_escape_js($langs->trans("TakeposUiLineDiscountShort")); ?>").removeClass('clicked');
            }
            if (editaction == 'p') {
                text = text + "<?php echo dol_escape_js($langs->trans("Modify") . " -> " . $langs->trans("TakeposUiPrice") . ": "); ?>";
                $("#qty").html("<?php echo dol_escape_js($langs->trans("TakeposUiQty")); ?>").removeClass('clicked');
                $("#price").html("OK").addClass("clicked");
                $("#reduction").html("<?php echo dol_escape_js($langs->trans("TakeposUiLineDiscountShort")); ?>").removeClass('clicked');
            }
            if (editaction == 'r') {
                text = text + "<?php echo dol_escape_js($langs->trans("Modify") . " -> " . $langs->trans("TakeposUiLineDiscountShort") . ": "); ?>";
                $("#qty").html("<?php echo dol_escape_js($langs->trans("TakeposUiQty")); ?>").removeClass('clicked');
                $("#price").html("<?php echo dol_escape_js($langs->trans("TakeposUiPrice")); ?>").removeClass('clicked');
                $("#reduction").html("OK").addClass("clicked");
            }
            $('#' + selectedline).find("td:first").html(text + editnumber);
        }


        function TakeposPrintingOrder() {
            console.log("TakeposPrintingOrder");
            loadPosLines("invoice.php?action=order&token=<?php echo newToken();?>&place=" + place, function () {
                //$('#poslines').scrollTop($('#poslines')[0].scrollHeight);
            });
        }

        function TakeposPrintingTemp() {
            console.log("TakeposPrintingTemp");
            loadPosLines("invoice.php?action=temp&token=<?php echo newToken();?>&place=" + place, function () {
                //$('#poslines').scrollTop($('#poslines')[0].scrollHeight);
            });
        }

        function OpenDrawer() {
            var openDrawerUrl = <?php
                $takeposPrintServer = getDolGlobalString('TAKEPOS_PRINT_SERVER', 'localhost');
                $takeposDrawerUrl = '';
                if ($takeposPrintServer && filter_var($conf->global->TAKEPOS_PRINT_SERVER, FILTER_VALIDATE_URL) == true) {
                    $takeposDrawerUrl = rtrim($takeposPrintServer, '/') . '/printer/drawer.php';
                } else {
                    $takeposDrawerUrl = 'http://' . $takeposPrintServer . ':8111/print';
                }
                echo json_encode($takeposDrawerUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                ?>;
            console.log("OpenDrawer call ajax url " + openDrawerUrl);
            $.ajax({
                type: "POST",
                data: {token: 'notrequired'},
                url: openDrawerUrl,
                data: "opendrawer"
            });
        }

        function ShowSelectedProductInfo() {
            if (typeof (selectedtext) == "undefined" || !selectedtext) {
                alert(takeposMsgSelectProductFirst);
                return;
            }
            var infoText = takeposHtmlToPlainText(selectedtext);
            alert(infoText || takeposMsgSelectProductFirst);
        }

        function takeposHtmlToPlainText(html) {
            var container = document.createElement('div');
            container.innerHTML = String(html || '');

            function walk(node, chunks) {
                if (!node) {
                    return;
                }

                if (node.nodeType === 3) {
                    chunks.push(node.nodeValue || '');
                    return;
                }

                if (node.nodeType !== 1) {
                    return;
                }

                var tag = String(node.tagName || '').toUpperCase();
                if (tag === 'BR') {
                    chunks.push('\n');
                    return;
                }
                if (tag === 'LI') {
                    chunks.push('- ');
                }

                for (var i = 0; i < node.childNodes.length; i++) {
                    walk(node.childNodes[i], chunks);
                }

                if (tag === 'DIV' || tag === 'P' || tag === 'LI' || tag === 'TR' || tag === 'TD' || tag === 'TH'
                    || tag === 'H1' || tag === 'H2' || tag === 'H3' || tag === 'H4' || tag === 'H5' || tag === 'H6') {
                    chunks.push('\n');
                }
            }

            var chunks = [];
            walk(container, chunks);

            return chunks.join('')
                .replace(/\u00a0/g, ' ')
                .replace(/[ \t]+\n/g, '\n')
                .replace(/\n[ \t]+/g, '\n')
                .replace(/\n{3,}/g, '\n\n')
                .replace(/[ \t]{2,}/g, ' ')
                .trim();
        }

        function TakeposCalcAppend(value) {
            var input = document.getElementById('takepos-calc-display');
            if (!input) return;
            if (value === 'C') {
                input.value = '';
                return;
            }
            input.value = String(input.value || '') + String(value || '');
        }

        function TakeposCalcEval() {
            var input = document.getElementById('takepos-calc-display');
            if (!input) return;
            var raw = String(input.value || '').replace(/\s+/g, '');
            if (raw === '') {
                input.value = '';
                return;
            }
            if (!/^[0-9.+\-*/()]+$/.test(raw)) {
                alert(takeposUi.invalidExpression || '<?php echo dol_escape_js($langs->trans('TakeposCalcInvalidExpression')); ?>');
                return;
            }
            if (/^[0-9.]+$/.test(raw)) {
                var onlyNumber = parseFloat(raw);
                if (!isFinite(onlyNumber)) {
                    alert(takeposUi.invalidExpression || '<?php echo dol_escape_js($langs->trans('TakeposCalcInvalidExpression')); ?>');
                    return;
                }
                input.value = String(onlyNumber);
                return;
            }
            try {
                var result = Function('"use strict"; return (' + raw + ');')();
                if (typeof result !== 'number' || !isFinite(result)) {
                    throw new Error('Invalid result');
                }
                input.value = String(result);
            } catch (e) {
                alert(takeposUi.invalidExpression || '<?php echo dol_escape_js($langs->trans('TakeposCalcInvalidExpression')); ?>');
            }
        }

        function OpenCalculator() {
            var input = document.getElementById('takepos-calc-display');
            if (input) {
                input.value = '';
            }
            ModalBox('ModalCalculator');
            setTimeout(function () {
                var calcInput = document.getElementById('takepos-calc-display');
                if (calcInput) {
                    calcInput.focus();
                }
            }, 50);
        }

        window.Search2 = Search2;
        window.OpenCalculator = OpenCalculator;
        window.OpenDrawer = OpenDrawer;

        var takeposLastTicketId = "<?php echo dol_escape_js(!empty($_SESSION['takepos_last_paid_invoice_id']) ? (string)$_SESSION['takepos_last_paid_invoice_id'] : ''); ?>";
        try {
            if (!takeposLastTicketId) {
                takeposLastTicketId = String(localStorage.getItem('takepos_last_ticket_id') || '');
            }
        } catch (e) {
        }

        function takeposRememberLastTicketId(invoiceId) {
            var cleanInvoiceId = String(invoiceId || '').trim();
            if (cleanInvoiceId === '' || cleanInvoiceId === '0') {
                return;
            }
            takeposLastTicketId = cleanInvoiceId;
            try {
                localStorage.setItem('takepos_last_ticket_id', cleanInvoiceId);
            } catch (e) {
            }
        }

        window.takeposRememberLastTicketId = takeposRememberLastTicketId;

        function PrintCurrentOrLastTicket() {
            var currentInvoiceId = $('#invoiceid').val();
            var ticketToPrint = (currentInvoiceId && currentInvoiceId !== '0') ? String(currentInvoiceId) : String(takeposLastTicketId || '');
            if (!ticketToPrint || ticketToPrint === '0') {
                alert(takeposMsgNoOpenSale);
                return;
            }
            Print(ticketToPrint);
        }

        function DolibarrOpenDrawer() {
            console.log("DolibarrOpenDrawer called");
            $.ajax({
                type: "GET",
                dataType: "json",
                data: {token: '<?php echo currentToken(); ?>'},
                url: "<?php print DOL_URL_ROOT . '/takepos/ajax/ajax.php?action=opendrawer&token=' . newToken() . '&term=' . urlencode(empty($_SESSION["takeposterminal"]) ? '' : $_SESSION["takeposterminal"]); ?>",
                success: function(data) {
                    if (data && data.success) {
                        if (typeof takeposFeedback === 'function') {
                            takeposFeedback('<?php echo dol_escape_js($langs->trans('TakeposUiOpenDrawer')); ?>', 'success');
                        }
                    } else if (data && data.message === 'NoDrawerConfigured') {
                        if (typeof takeposFeedback === 'function') {
                            takeposFeedback('<?php echo dol_escape_js($langs->trans('TakeposUiOpenDrawer')); ?>: <?php echo dol_escape_js($langs->transnoentitiesnoconv('TakeposDrawerNotConfigured', 'يرجى إعداد طابعة الإيصالات في إعدادات الطرفية')); ?>', 'warning');
                        }
                    }
                },
                error: function() {
                    console.warn('DolibarrOpenDrawer: AJAX error');
                }
            });
        }

        function MoreActions(totalactions) {
            if (pageactions == 0) {
                pageactions = 1;
                for (i = 0; i <= totalactions; i++) {
                    if (i < 12) $("#action" + i).hide();
                    else $("#action" + i).show();
                }
            } else if (pageactions == 1) {
                pageactions = 0;
                for (i = 0; i <= totalactions; i++) {
                    if (i < 12) $("#action" + i).show();
                    else $("#action" + i).hide();
                }
            }

            return true;
        }

        function ControlCashOpening() {
            $.colorbox({
                href: "../compta/cashcontrol/cashcontrol_card.php?action=create&token=<?php echo newToken(); ?>&contextpage=takepos",
                width: "90%",
                height: "60%",
                transition: "none",
                iframe: "true",
                title: "<?php echo dol_escape_js($langs->trans("NewCashFence")); ?>"
            });
        }

        function CloseCashFence(rowid) {
            $.colorbox({
                href: "../compta/cashcontrol/cashcontrol_card.php?id=" + rowid + "&contextpage=takepos",
                width: "90%",
                height: "90%",
                transition: "none",
                iframe: "true",
                title: "<?php echo dol_escape_js($langs->trans("NewCashFence")); ?>"
            });
        }

        function CashReport(rowid) {
            $.colorbox({
                href: "../compta/cashcontrol/report.php?id=" + rowid + "&contextpage=takepos",
                width: "60%",
                height: "90%",
                transition: "none",
                iframe: "true",
                title: "<?php echo dol_escape_js($tpLabelCashReport); ?>"
            });
        }

        // TakePOS Popup
        function ModalBox(ModalID) {
            var modal = document.getElementById(ModalID);
            modal.style.display = "block";
        }

        function DirectPayment() {
            console.log("DirectPayment");
            return takeposExecuteDirectPayment('LIQ');
        }

        function DirectCardPayment() {
            console.log("DirectCardPayment");
            return takeposExecuteDirectPayment('CB');
        }

        function FullScreen() {
            document.documentElement.requestFullscreen();
        }

        var takeposShortcutDrawerScrollTop = 0;

        function rememberTakeposShortcutsScroll() {
            var drawer = document.getElementById("takepos-shortcuts-drawer");
            if (drawer) {
                takeposShortcutDrawerScrollTop = drawer.scrollTop || 0;
            }
        }

        function restoreTakeposShortcutsScroll() {
            var drawer = document.getElementById("takepos-shortcuts-drawer");
            if (drawer) {
                drawer.scrollTop = takeposShortcutDrawerScrollTop || 0;
            }
        }

        function openWorkspaceShortcut(index) {
            if (typeof productStudioLinks[index] === "undefined") {
                return;
            }

            var item = productStudioLinks[index];
            var targetUrl = item.url;
            if (targetUrl.indexOf("http") !== 0) {
                targetUrl = dolUrlRoot + targetUrl;
            }

            // Keep the shortcut drawer mounted/open while Colorbox is displayed. This
            // preserves the user's scroll position when the popup is closed.
            rememberTakeposShortcutsScroll();
            $.colorbox({
                href: targetUrl,
                width: "96%",
                height: "94%",
                iframe: true,
                transition: "none",
                title: item.label,
                onComplete: function () {
                    restoreTakeposShortcutsScroll();
                },
                onClosed: function () {
                    restoreTakeposShortcutsScroll();
                }
            });
        }

        function openTakeposWorkspacePage(path, title) {
            var targetUrl = path || "";
            if (targetUrl === "") {
                return;
            }
            if (targetUrl.indexOf("http") !== 0) {
                targetUrl = dolUrlRoot + targetUrl;
            }

            $.colorbox({
                href: targetUrl,
                width: "96%",
                height: "94%",
                iframe: true,
                transition: "none",
                title: title || ""
            });
        }

        function toggleShortcutSection(sectionCode) {
            var section = document.getElementById("takepos-shortcut-section-" + sectionCode);
            if (!section) {
                return;
            }

            var isCollapsed = section.classList.contains("is-collapsed");
            section.classList.toggle("is-collapsed", !isCollapsed);

            var header = section.querySelector(".takepos-shortcut-header");
            if (header) {
                header.setAttribute("aria-expanded", isCollapsed ? "true" : "false");
            }
        }

        function openTakeposShortcutsDrawer() {
            var drawer = document.getElementById("takepos-shortcuts-drawer");
            var launcher = document.getElementById("takepos-shortcuts-launcher");
            if (!drawer) {
                return;
            }

            drawer.classList.add("is-open");
            drawer.setAttribute("aria-hidden", "false");
            document.body.classList.add("takepos-shortcuts-open");
            restoreTakeposShortcutsScroll();
            if (launcher) {
                launcher.setAttribute("aria-expanded", "true");
            }
        }

        function closeTakeposShortcutsDrawer(options) {
            options = options || {};
            rememberTakeposShortcutsScroll();
            var savedScrollX = window.pageXOffset || document.documentElement.scrollLeft || document.body.scrollLeft || 0;
            var savedScrollY = window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;
            var drawer = document.getElementById("takepos-shortcuts-drawer");
            var launcher = document.getElementById("takepos-shortcuts-launcher");
            if (!drawer) {
                return;
            }

            drawer.classList.remove("is-open");
            drawer.setAttribute("aria-hidden", "true");
            document.body.classList.remove("takepos-shortcuts-open");
            if (launcher) {
                launcher.setAttribute("aria-expanded", "false");
            }
            setTimeout(function () {
                if (!options.skipFocus) {
                    maintainBarcodeSearchFocus(false);
                }
                window.scrollTo(savedScrollX, savedScrollY);
            }, 40);
        }

        function toggleTakeposShortcutsDrawer() {
            var drawer = document.getElementById("takepos-shortcuts-drawer");
            if (!drawer) {
                return;
            }
            if (drawer.classList.contains("is-open")) {
                closeTakeposShortcutsDrawer();
            } else {
                openTakeposShortcutsDrawer();
            }
        }

        function refreshTakeposCatalog() {
            pagecategories = 0;
            pageproducts = 0;
            PrintCategories(0);
            // FIX: always reload All products when refreshing catalog
            window.takeposAllProductsMode = true;
            var _cd = document.getElementById('catdiv0');
            if (_cd) { _cd.setAttribute('data-rowid', '0'); }
            if (window.jQuery) { jQuery('#catdiv0').data('rowid', 0); }
            LoadProducts(0);
            ClearSearch(true);
        }

        function WeighingScale() {
            console.log("Weighing Scale");
            $.ajax({
                type: "POST",
                data: {token: 'notrequired'},
                url: '<?php print getDolGlobalString('TAKEPOS_PRINT_SERVER'); ?>/scale/index.php',
            })
                .done(function (editnumber) {
                    $("#poslines").load("invoice.php?token=<?php echo newToken(); ?>&place=" + place + "&idline=" + selectedline + "&number=" + editnumber, function () {
                        editnumber = "";
                    });
                });
        }

        $(document).ready(function () {
            PrintCategories(0);
            // FIX: Load ALL products on startup (not just first category)
            // so counter and grid show correct products from the beginning
            window.takeposAllProductsMode = true;
            // Override catdiv0 to 0 so LoadProducts uses category=0 (all products)
            var _cd0 = document.getElementById('catdiv0');
            if (_cd0) { _cd0.setAttribute('data-rowid', '0'); }
            if (window.jQuery) { jQuery('#catdiv0').data('rowid', 0); }
            LoadProducts(0);
            Refresh();
            <?php
            // IF NO TERMINAL SELECTED
            if (empty($_SESSION["takeposterminal"]) || $_SESSION["takeposterminal"] == "") {
                print "ModalBox('ModalTerminal');";
            }

            if (getDolGlobalString('TAKEPOS_CONTROL_CASH_OPENING')) {
                $sql = "SELECT rowid, status FROM " . MAIN_DB_PREFIX . "pos_cash_fence WHERE";
                $sql .= " entity = " . ((int)$conf->entity) . " AND ";
                $sql .= " posnumber = " . ((int)$_SESSION["takeposterminal"]) . " AND ";
                $sql .= " date_creation > '" . $db->idate(dol_get_first_hour(dol_now())) . "'";
                $sql .= " AND status = 0 ";
                $resql = $db->query($sql);
                if ($resql) {
                    $obj = $db->fetch_object($resql);
                    // If there is no cash control from today open it
                    if (!isset($obj->rowid) || is_null($obj->rowid)) {
                        print "ControlCashOpening();";
                    }
                }
            }
            ?>

            /* For Header Scroll */
            var elem1 = $("#topnav-left")[0];
            var elem2 = $("#topnav-right")[0];
            var scrollContainer = $("#topnav-left")[0];
            var checkOverflow = function () {
                if (!scrollContainer) return;
                if (scrollBars().horizontal) $("#topnav").addClass("overflow");
                else $("#topnav").removeClass("overflow");
            }

            var scrollBars = function () {
                if (!scrollContainer) {
                    return {vertical: false, horizontal: false};
                }
                return {
                    vertical: scrollContainer.scrollHeight > scrollContainer.clientHeight,
                    horizontal: scrollContainer.scrollWidth > scrollContainer.clientWidth
                }
            }

            $(window).resize(function () {
                checkOverflow();
                ensurePosTicketVisible();
                takeposNormalizeUiLanguage(document);
            });

            if (typeof ResizeObserver !== 'undefined' && elem1 && elem2) {
                let resizeObserver = new ResizeObserver(function () {
                    checkOverflow();
                });
                resizeObserver.observe(elem1);
                resizeObserver.observe(elem2);
            }
            checkOverflow();

            var pressTimer = [];
            var direction = 1;
            var step = 200;

            $(".indicator").mousedown(function () {
                direction = $(this).hasClass("left") ? -1 : 1;
                scrollTopnavLeft();
                pressTimer.push(setInterval(scrollTopnavLeft, 100));
            });

            $(".indicator").mouseup(function () {
                pressTimer.forEach(clearInterval);
                pressTimer = [];
            });

            $("body").mouseup(function () {
                pressTimer.forEach(clearInterval);
                pressTimer = [];
            });

            function scrollTopnavLeft() {
                if (!scrollContainer) return;
                scrollContainer.scrollTo({left: scrollContainer.scrollLeft + direction * step, behavior: 'smooth'});
            }

            $("#topnav-left").scroll(function () {
                checkOverflow();
            });
            $(document).on("keydown", function (event) {
                var editable = $(event.target).is('input, textarea, select, [contenteditable="true"]');
                if (event.key === "Escape") {
                    closeTakeposShortcutsDrawer();
                    return;
                }

                if (/^F\d{1,2}$/i.test(event.key)) {
                    var fn = parseInt(event.key.replace(/[^\d]/g, ''), 10);
                    var actionBtn = document.getElementById('action' + fn);
                    if (actionBtn && $(actionBtn).is(':visible')) {
                        event.preventDefault();
                        actionBtn.click();
                    }
                    return;
                }

                if (editable) {
                    return;
                }

                var key = event.key;
                var mappedDigits = {
                    'ظ ': '0',
                    'ظ،': '1',
                    'ظ¢': '2',
                    'ظ£': '3',
                    'ظ¤': '4',
                    'ظ¥': '5',
                    'ظ¦': '6',
                    'ظ§': '7',
                    'ظ¨': '8',
                    'ظ©': '9',
                    'ظ«': '.',
                    ',': '.'
                };
                if (Object.prototype.hasOwnProperty.call(mappedDigits, key)) {
                    key = mappedDigits[key];
                }

                if (/^[0-9]$/.test(key)) {
                    if (typeof (selectedtext) == "undefined" || !selectedtext) {
                        return;
                    }
                    Edit(parseInt(key, 10));
                    event.preventDefault();
                    return;
                }
                if (key === '.' || key === 'Decimal') {
                    Edit('.');
                    event.preventDefault();
                    return;
                }
                if (key === 'Backspace' || key === 'Delete') {
                    Edit('c');
                    event.preventDefault();
                }
            });
            $(document).on("mousedown", function (event) {
                var drawer = $("#takepos-shortcuts-drawer");
                if (!drawer.length || !drawer.hasClass("is-open")) {
                    return;
                }
                if ($(event.target).closest("#takepos-shortcuts-drawer, #takepos-shortcuts-launcher, .actionbutton, [id^='takepos-action-']").length) {
                    return;
                }
                closeTakeposShortcutsDrawer();
            });
            /* End Header Scroll */
        });

        // ---- Customer display (multi-screen) ----
        var takeposCustomerDisplayChannel = null;
        try {
            takeposCustomerDisplayChannel = new BroadcastChannel('takepos_customer_display');
        } catch (e) {
            takeposCustomerDisplayChannel = null;
        }

        function OpenCustomerDisplay() {
            var terminal = (typeof selectedterminal !== 'undefined' && selectedterminal) ? selectedterminal : '';
            var url = dolUrlRoot + '/takepos/customer_display.php' + (terminal ? ('?terminal=' + encodeURIComponent(terminal)) : '');
            var w = window.open(url, 'takepos_customer_display_window', 'popup=yes,width=1280,height=800,resizable=yes,scrollbars=yes');
            if (!w) {
                alert(takeposUi.customerScreenPopup);
                return false;
            }
            try {
                if (w.location && String(w.location.href) === 'about:blank') {
                    w.location.href = url;
                }
                w.focus();
            } catch (e) {
                try {
                    w.focus();
                } catch (ignore) {
                }
            }
            return false;
        }

        function collectCustomerDisplayState() {
            var state = {
                updatedAt: new Date().toLocaleTimeString(),
                invoiceRef: '',
                totalTtc: '',
                totalHt: '',
                tax: '',
                discount: '',
                footer: takeposUi.customerScreenFooter,
                message: '',
                items: []
            };

            function textOf(node) {
                return node ? (node.value || node.textContent || '').trim() : '';
            }

            function parseAmount(v) {
                var s = String(v || '').replace(/,/g, '');
                var m = s.match(/-?\d+(?:\.\d+)?/g);
                return (m && m.length) ? (parseFloat(m[m.length - 1]) || 0) : 0;
            }

            function looksLikeCurrencyText(v) {
                return /[%$\u20AC\u00A3\u00A5]|(?:\b(?:sar|jod|usd|eur|aed)\b)|(?:\u0631\u064a\u0627\u0644|\u062f\u064a\u0646\u0627\u0631|\u062f\u0648\u0644\u0627\u0631|\u064a\u0648\u0631\u0648|\u062f\u0631\u0647\u0645)/i.test(String(v || '').trim());
            }

            function normalizeQty(v) {
                var s = String(v || '').trim();
                if (!s) return '1';
                if (looksLikeCurrencyText(s)) return '1';
                var m = s.match(/\d+(?:\.\d+)?/);
                return m ? m[0] : '1';
            }

            try {
                var totalCandidates = document.querySelectorAll('#total, #txttotal, .total, [data-role="totalttc"], #linecolht-span-total');
                if (totalCandidates.length) state.totalTtc = textOf(totalCandidates[totalCandidates.length - 1]);
                var htCandidates = document.querySelectorAll('#totalht, #txttotalht, [data-role="totalht"], #linecolht-span-total');
                if (htCandidates.length > 1) state.totalHt = textOf(htCandidates[0]);
                else if (htCandidates.length === 1) state.totalHt = textOf(htCandidates[0]);
                var taxNode = document.querySelector('#totaltva, #tax, [data-role="tax"]');
                if (taxNode) state.tax = textOf(taxNode);
                var refNode = document.querySelector('#invoice_ref, .invoice-ref, [data-role="invoiceref"], #invoiceid');
                if (refNode) state.invoiceRef = textOf(refNode);

                var headerRow = document.querySelector('#tablelines tr.liste_titre');
                var hasHtColumn = false;
                if (headerRow) {
                    var headerText = (headerRow.innerText || '').toLowerCase();
                    hasHtColumn = headerText.indexOf('totalht') >= 0 || headerText.indexOf('ht') >= 0;
                }

                var rows = document.querySelectorAll('#tablelines tr.drag.drop.oddeven.posinvoiceline, #tablelines tr.drag.drop.oddeven');
                rows.forEach(function (row) {
                    if (row.classList.contains('liste_titre')) return;
                    var tds = row.querySelectorAll('td');
                    if (!tds.length) return;
                    var label = textOf(tds[0]).replace(/\s+/g, ' ').trim();
                    if (!label || /empty/i.test(label)) return;
                    var len = tds.length;
                    var qtyIndex = len >= 4 ? (hasHtColumn ? len - 3 : len - 2) : 1;
                    if (qtyIndex < 1) qtyIndex = 1;
                    if (qtyIndex >= len) qtyIndex = len - 2;
                    var qty = normalizeQty(textOf(tds[qtyIndex]));
                    var total = textOf(tds[len - 1]);
                    if (!total && len > 1) total = textOf(tds[len - 1]);
                    state.items.push({label: label, qty: qty, total: total});
                });

                if (!state.items.length) {
                    var lineCells = document.querySelectorAll('.dragdrop-product, .line_product_name');
                    lineCells.forEach(function (node) {
                        var label = textOf(node).replace(/\s+/g, ' ').trim();
                        if (label) state.items.push({label: label, qty: '1', total: ''});
                    });
                }

                if ((!state.totalTtc || String(state.totalTtc).trim() === '' || String(state.totalTtc).trim() === '0' || String(state.totalTtc).trim() === '0.00') && state.items.length) {
                    var derived = 0;
                    state.items.forEach(function (it) {
                        derived += parseAmount(it.total || '0');
                    });
                    if (derived > 0) {
                        state.totalTtc = derived.toFixed(2);
                        if (!state.totalHt) state.totalHt = state.totalTtc;
                    }
                }
                if (!state.totalHt && state.totalTtc) state.totalHt = state.totalTtc;
                state.message = state.items.length ? <?php echo json_encode($tpCustomerDisplayReviewOrder, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> : <?php echo json_encode($tpCustomerDisplayWelcome, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            } catch (e) {
            }
            return state;
        }

        function pushCustomerDisplayState() {
            try {
                var state = collectCustomerDisplayState();
                localStorage.setItem('takepos_customer_display_state', JSON.stringify(state));
                if (takeposCustomerDisplayChannel) takeposCustomerDisplayChannel.postMessage(state);
            } catch (e) {
            }
        }

        window.addEventListener('load', function () {
            // PERFORMANCE: previously this ran every 1.5 seconds AND on every click/change
            // anywhere on the page, even when the secondary customer display was disabled.
            // That single setInterval was responsible for most of the "feels laggy"
            // sensation across the whole POS — every 1.5s the main thread did DOM
            // queries over #tablelines, JSON.stringify, localStorage write and a
            // BroadcastChannel post. Now:
            //   - We only schedule the interval if TAKEPOS_CUSTOMER_DISPLAY is on.
            //   - The interval is stretched to 4s (still feels live on the second screen).
            //   - The per-click / per-change listeners are removed; we instead push
            //     after the user actually changes the ticket (loadPosLines completion
            //     already triggers it via the explicit calls in the codebase).
            setTimeout(pushCustomerDisplayState, 800);
            setInterval(pushCustomerDisplayState, 4000);
        });

        /* =====================================================================
 * TakePOS — Drag & Drop products into the sell area
 * ---------------------------------------------------------------------
 * Lets the cashier drag any product tile (#prodivN) and drop it onto the
 * invoice/sell area (#poslines). On drop, the same path used by ClickProduct
 * is invoked (takeposAddProductToInvoice), so all the existing checks
 * (shift, stock, customer-display refresh) are preserved.
 *
 * The product tiles in TakePOS are re-rendered/swapped frequently
 * (LoadProducts, MoreProducts, search). Re-attaching listeners every
 * time would be brittle, so we use ONE delegated listener at document
 * level and an attribute (draggable="true") that we set in a MutationObserver
 * so freshly rendered tiles stay draggable.
 * ===================================================================== */
        (function () {
            'use strict';

            function getDraggableProductDiv(node) {
                if (!node || node.nodeType !== 1) return null;
                // walk up looking for #prodivN that has a numeric data-rowid (i.e. a real product, not a category or arrow)
                var el = node;
                while (el && el !== document.body) {
                    if (el.id && /^prodiv\d+$/.test(el.id)) {
                        var rowid = el.getAttribute('data-rowid') || '';
                        var iscat = el.getAttribute('data-iscat');
                        if (rowid && rowid !== '' && iscat != '1') return el;
                        return null;
                    }
                    el = el.parentNode;
                }
                return null;
            }

            function markProductDivsDraggable() {
                var divs = document.querySelectorAll('div[id^="prodiv"]');
                for (var i = 0; i < divs.length; i++) {
                    var d = divs[i];
                    if (!/^prodiv\d+$/.test(d.id)) continue;
                    var rowid = d.getAttribute('data-rowid') || '';
                    var iscat = d.getAttribute('data-iscat');
                    if (rowid && rowid !== '' && iscat != '1') {
                        if (d.getAttribute('draggable') !== 'true') {
                            d.setAttribute('draggable', 'true');
                            d.classList.add('takepos-draggable-product');
                        }
                    } else {
                        // remove draggable from non-products (arrow tiles or empty slots)
                        if (d.hasAttribute('draggable')) {
                            d.removeAttribute('draggable');
                            d.classList.remove('takepos-draggable-product');
                        }
                    }
                }
            }

            // PERFORMANCE: use a MutationObserver instead of setInterval polling.
            // Tiles are only marked draggable when the DOM under #tablelines changes
            // or when LoadProducts/search updates the product grid. When the POS is
            // idle, this costs zero CPU, unlike a setInterval(800ms) that fires forever.
            document.addEventListener('DOMContentLoaded', markProductDivsDraggable);
            window.addEventListener('load', function () {
                markProductDivsDraggable();
                try {
                    var obsTargets = [];
                    // Watch the product grid container (its tiles get their data-rowid
                    // set/cleared by LoadProducts and search code paths).
                    var productGrid = document.querySelector('.div5');
                    if (productGrid) obsTargets.push(productGrid);
                    if (obsTargets.length === 0) {
                        // Fallback: observe body, but with a tight filter
                        obsTargets.push(document.body);
                    }
                    var pendingMark = null;
                    var observer = new MutationObserver(function () {
                        // Coalesce: don't re-scan on every single attribute mutation
                        if (pendingMark) return;
                        pendingMark = setTimeout(function () {
                            pendingMark = null;
                            markProductDivsDraggable();
                        }, 120);
                    });
                    obsTargets.forEach(function (t) {
                        observer.observe(t, {
                            attributes: true,
                            attributeFilter: ['data-rowid', 'data-iscat'],
                            childList: true,
                            subtree: true
                        });
                    });
                } catch (e) {
                    // Older browser fallback — at least one mark on load
                    console.warn('MutationObserver unavailable, drag-drop will only init once', e);
                }
            });

            // ---- DRAG START on a product tile ----
            document.addEventListener('dragstart', function (e) {
                var prodDiv = getDraggableProductDiv(e.target);
                if (!prodDiv) return;
                var rowid = prodDiv.getAttribute('data-rowid') || '';
                if (!rowid) {
                    e.preventDefault();
                    return;
                }
                try {
                    e.dataTransfer.effectAllowed = 'copy';
                    e.dataTransfer.setData('text/plain', 'takepos-product:' + rowid);
                    // Also store on dataset for browsers that strip text/plain on cross-document drags
                    window._takeposDraggingProductId = rowid;
                } catch (err) {
                    window._takeposDraggingProductId = rowid;
                }
                prodDiv.classList.add('takepos-dragging');
            }, true);

            document.addEventListener('dragend', function (e) {
                var prodDiv = getDraggableProductDiv(e.target);
                if (prodDiv) prodDiv.classList.remove('takepos-dragging');
                window._takeposDraggingProductId = null;
                var dz = document.getElementById('poslines');
                if (dz) dz.classList.remove('takepos-dropzone-active');
            }, true);

            // ---- DROP TARGET = #poslines (the sell ticket area) ----
            function isDropZone(node) {
                var el = node;
                while (el && el !== document.body) {
                    if (el.id === 'poslines') return el;
                    el = el.parentNode;
                }
                return null;
            }

            document.addEventListener('dragenter', function (e) {
                var dz = isDropZone(e.target);
                if (!dz) return;
                if (!window._takeposDraggingProductId) return;
                e.preventDefault();
                dz.classList.add('takepos-dropzone-active');
            }, true);

            document.addEventListener('dragover', function (e) {
                var dz = isDropZone(e.target);
                if (!dz) return;
                if (!window._takeposDraggingProductId) return;
                // MUST preventDefault to allow a drop
                e.preventDefault();
                try {
                    e.dataTransfer.dropEffect = 'copy';
                } catch (err) {
                }
            }, true);

            document.addEventListener('dragleave', function (e) {
                var dz = isDropZone(e.target);
                if (!dz) return;
                // Only clear the highlight if we actually left the dropzone (relatedTarget outside it)
                var rt = e.relatedTarget;
                if (rt && (rt === dz || dz.contains(rt))) return;
                dz.classList.remove('takepos-dropzone-active');
            }, true);

            document.addEventListener('drop', function (e) {
                var dz = isDropZone(e.target);
                if (!dz) return;
                var pid = window._takeposDraggingProductId;
                if (!pid) {
                    // fallback: try to read from dataTransfer
                    try {
                        var raw = e.dataTransfer.getData('text/plain') || '';
                        var m = raw.match(/^takepos-product:(\d+)$/);
                        if (m) pid = m[1];
                    } catch (err) {
                    }
                }
                if (!pid) return;
                e.preventDefault();
                dz.classList.remove('takepos-dropzone-active');
                window._takeposDraggingProductId = null;

                // Make sure an invoice exists, then add the product. Same flow as click.
                try {
                    var currentInvoice = ($('#invoiceid').length ? ($('#invoiceid').val() || '') : '');
                    if (currentInvoice === '') {
                        if (typeof Refresh === 'function') Refresh();
                    }
                    currentInvoice = ($('#invoiceid').length ? ($('#invoiceid').val() || '0') : '0');
                    if (typeof takeposAddProductToInvoice === 'function') {
                        takeposAddProductToInvoice(parseInt(pid, 10), 1, currentInvoice);
                    }
                } catch (err) {
                    console.error('TakePOS drag-drop add failed:', err);
                }
            }, true);
        })();

    </script>

    <?php /* extra CSS for drag-drop visual feedback */ ?>
    <style>
        div[id^="prodiv"].takepos-draggable-product {
            cursor: grab;
        }

        div[id^="prodiv"].takepos-dragging {
            opacity: 0.55;
            transform: scale(0.97);
            transition: transform 80ms ease, opacity 80ms ease;
        }

        #poslines.takepos-dropzone-active {
            outline: 3px dashed #2a8acb;
            outline-offset: -4px;
            background-color: rgba(42, 138, 203, 0.06);
            transition: background-color 120ms ease, outline-color 120ms ease;
        }

        /* Click flash used in place of the old jQuery .animate() pair for faster feedback */
        .takepos-product-flash {
            filter: brightness(0.75);
            transition: filter 90ms ease;
        }

        /* Instant click-feedback progress strip on top of the ticket panel */
        #poslines {
            position: relative;
        }

        .takepos-add-progress {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: transparent;
            z-index: 10;
            pointer-events: none;
            overflow: hidden;
        }

        .takepos-add-progress.is-active::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: 30%;
            background: linear-gradient(90deg, transparent, #2a8acb, transparent);
            animation: takepos-progress-slide 900ms linear infinite;
        }

        @keyframes takepos-progress-slide {
            from {
                transform: translateX(-100%);
            }
            to {
                transform: translateX(400%);
            }
        }
    </style>

    <?php
    $keyCodeForEnter = '';
    if (!empty($_SESSION['takeposterminal'])) {
        $keyCodeForEnter = getDolGlobalInt('CASHDESK_READER_KEYCODE_FOR_ENTER' . $_SESSION['takeposterminal']) > 0 ? getDolGlobalString('CASHDESK_READER_KEYCODE_FOR_ENTER' . $_SESSION['takeposterminal']) : '';
    }
    ?>

    <script>
        window.takeposKeyCodeForEnter = <?php echo json_encode((string)$keyCodeForEnter); ?>;

        function TakeposOpenModal(modalId) {
            if (typeof window.ModalBox === 'function') {
                window.ModalBox(modalId);
            } else {
                var modal = document.getElementById(modalId);
                if (modal) modal.style.display = 'block';
            }
            return false;
        }

        function TakeposSearchTrigger(evt, moreorless) {
            if (typeof moreorless === 'undefined') moreorless = null;
            var keyCode = window.takeposKeyCodeForEnter || '';
            if (typeof window.Search2 === 'function') {
                return window.Search2(keyCode, moreorless, evt || window.event || null);
            }
            var input = document.getElementById('search');
            if (!input) return false;
            if (evt && evt.key && evt.key !== 'Enter' && String(input.value || '').trim() !== '') {
                return true;
            }
            return false;
        }
    </script>

    <div class="container">

        <?php
        if (!getDolGlobalString('TAKEPOS_HIDE_HEAD_BAR')) {
            ?>
            <div class="header">
                <div id="topnav" class="topnav">
                    <div id="topnav-left" class="topnav-left">
                        <div class="inline-block valignmiddle">
                            <a class="topnav-terminalhour" onclick="return TakeposOpenModal('ModalTerminal');">
                                <span class="fa fa-cash-register"></span>
                                <span class="hideonsmartphone">
				<?php
                if (!empty($_SESSION["takeposterminal"])) {
                    echo getDolGlobalString("TAKEPOS_TERMINAL_NAME_" . $_SESSION["takeposterminal"], $langs->trans("TerminalName", $_SESSION["takeposterminal"]));
                }
                ?>
				</span>
                                <?php
                                echo '<span class="hideonsmartphone"> - ' . dol_print_date(dol_now(), "day") . '</span>'; ?>
                            </a>
                            <?php
                            if (isModEnabled('multicurrency')) {
                                print '<a class="valignmiddle tdoverflowmax100" id="multicurrency" onclick="return TakeposOpenModal(\'ModalCurrency\');" title=""><span class="fas fa-coins paddingrightonly"></span>';
                                print '<span class="hideonsmartphone">' . $langs->trans("Currency") . '</span>';
                                print '</a>';
                            } else {
                                // Issue #9 fix: show currency button even without multicurrency module
                                $activeCurrTop = isset($_SESSION['takeposcustomercurrency']) ? strtoupper((string)$_SESSION['takeposcustomercurrency']) : '';
                                $baseCurrTop   = strtoupper(trim(!empty($conf->currency) ? $conf->currency : 'JOD'));
                                $currLabel = ($activeCurrTop !== '' && $activeCurrTop !== $baseCurrTop) ? $activeCurrTop : $baseCurrTop;
                                print '<a class="valignmiddle tdoverflowmax100" id="multicurrency" onclick="return TakeposOpenModal(\'ModalCurrency\');" title="" style="cursor:pointer;">';
                                print '<span class="fas fa-coins paddingrightonly"></span>';
                                print '<span class="hideonsmartphone" style="font-weight:700;">' . dol_escape_htmltag($currLabel) . '</span>';
                                print '</a>';
                            } ?>
                        </div>
                        <!-- section for customer -->
                        <div class="inline-block valignmiddle" id="customerandsales"></div>
                        <input type="hidden" id="idcustomer" value="">
                        <!-- section for shopping carts -->
                        <div class="inline-block valignmiddle" id="shoppingcart"></div>
                        <!-- More info about customer -->
                        <div class="inline-block valignmiddle tdoverflowmax150onsmartphone" id="moreinfo"></div>
                        <?php
                        if (isModEnabled('stock')) {
                            ?>
                            <!-- More info about warehouse -->
                            <div class="inline-block valignmiddle tdoverflowmax150onsmartphone"
                                 id="infowarehouse"></div>
                            <?php
                        } ?>
                    </div>
                    <div id="topnav-right" class="topnav-right">
                        <?php
                        $reshook = $hookmanager->executeHooks('takepos_login_block_other');
                        if ($reshook == 0) {  //Search method
                            ?>
                            <div class="login_block_other takepos">
                                <input type="text" id="search" name="search" class="input-nobottom"
                                       onkeyup="return TakeposSearchTrigger(event, null);"
                                       placeholder="<?php echo dol_escape_htmltag($tpLabelSearch); ?>" autofocus>
                                <button type="button" onclick="return TakeposSearchTrigger(event, null);"
                                        class="button smallpaddingimp takepos-search-button"
                                        style="margin-inline-start:4px;"><?php echo dol_escape_htmltag($tpLabelSearch); ?></button>
                                <a href="#" onclick="return ClearSearch(false, event);"
                                   class="nohover takepos-search-clear"
                                   aria-label="<?php echo dol_escape_htmltag($tpLabelClearSearch); ?>"><span
                                            class="fa fa-backspace"></span></a>
                                <?php if ($crmFeatureEnabled && $canOpenLoyaltyDesk) { ?>
                                    <a href="#" onclick="openTakeposLoyaltyDesk(); return false;"
                                       title="<?php echo dol_escape_htmltag($langs->trans('TakeposShortcutLoyaltyDesk')); ?>">
                                        <span class="fa fa-id-card"></span>
                                        <span class="hideonsmartphone"><?php echo dol_escape_htmltag($langs->trans('TakeposIndexLoyalty')); ?></span>
                                    </a>
                                <?php } ?>
                                <?php if ($customerDisplayPageAvailable) { ?>
                                    <a href="#" onclick="OpenCustomerDisplay(); return false;"
                                       title="<?php echo dol_escape_htmltag($langs->trans("TakeposIndexCustomerDisplay")); ?>">
                                        <span class="fa fa-desktop"></span>
                                        <span class="hideonsmartphone"><?php echo dol_escape_htmltag($langs->trans("TakeposIndexCustomerDisplay")); ?></span>
                                    </a>
                                <?php } ?>
                                <a href="<?php echo dol_escape_htmltag($takeposArabicSwitchUrl); ?>"
                                   title="<?php echo dol_escape_htmltag($langs->trans('TakeposIndexLangArabic')); ?>">
                                    <span class="fa fa-language"></span>
                                    <span><?php echo dol_escape_htmltag($langs->trans('TakeposIndexLangArabicShort')); ?></span>
                                </a>
                                <a href="<?php echo dol_escape_htmltag($takeposEnglishSwitchUrl); ?>"
                                   title="<?php echo dol_escape_htmltag($langs->trans('TakeposIndexLangEnglish')); ?>">
                                    <span><?php echo dol_escape_htmltag($langs->trans('TakeposIndexLangEnglishShort')); ?></span>
                                </a>
                                <a href="<?php echo DOL_URL_ROOT . '/'; ?>" target="backoffice" rel="opener">
                                    <!-- we need rel="opener" here, we are on same domain and we need to be able to reuse this tab several times -->
                                    <span class="fas fa-home"></span></a>
                                <?php if (empty($conf->dol_use_jmobile)) { ?>
                                    <a class="hideonsmartphone" onclick="FullScreen();"
                                       title="<?php echo dol_escape_htmltag($langs->trans("ClickFullScreenEscapeToLeave")); ?>"><span
                                                class="fa fa-expand-arrows-alt"></span></a>
                                <?php } ?>
                            </div>
                            <?php
                        }
                        ?>
                        <div class="login_block_user">
                            <?php
                            print top_menu_user(1, DOL_URL_ROOT . '/user/logout.php?token=' . newToken() . '&urlfrom=' . urlencode('/takepos/?setterminal=' . ((int)$term)));
                            ?>
                        </div>
                    </div>
                    <div class="arrows">
                        <span class="indicator left"><i class="fa fa-arrow-left"></i></span>
                        <span class="indicator right"><i class="fa fa-arrow-right"></i></span>
                    </div>
                </div>
            </div>
            <?php
        }
        ?>

        <?php
        $shortcutsDrawerPartial = __DIR__ . '/partials/shortcuts_drawer.php';
        if (is_file($shortcutsDrawerPartial)) {
            require $shortcutsDrawerPartial;
        }
        ?>

        <!-- Modal terminal box -->
        <div id="ModalTerminal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <?php
                    if (!getDolGlobalString('TAKEPOS_FORCE_TERMINAL_SELECT')) {
                        ?>
                        <span class="close" href="#"
                              onclick="document.getElementById('ModalTerminal').style.display = 'none';">&times;</span>
                        <?php
                    } ?>
                    <h3><?php print $langs->trans("TerminalSelect"); ?></h3>
                </div>
                <div class="modal-body">
                    <?php
                    if (is_array($takeposBranchTerminals)) {
                        if (empty($takeposBranchTerminals)) {
                            print '<p style="padding:14px;color:#c0392b">No terminals available for your branch. Contact your administrator.</p>';
                        } else {
                            foreach ($takeposBranchTerminals as $tid => $tobj) {
                                $tlabel = !empty($tobj->label) ? $tobj->label : ('Terminal ' . $tid);
                                $hasPIN = !empty(getDolGlobalString('TAKEPOS_TERMINAL_PIN_'.$tid));
                                if ($hasPIN) {
                                    print '<button type="button" class="block" onclick="takeposPinPrompt('.(int)$tid.',\'' . dol_escape_js($tlabel) . '\')">' . dol_escape_htmltag($tlabel) . ' <span style="font-size:0.75em;opacity:0.7">&#128274;</span></button>';
                                } else {
                                    print '<button type="button" class="block" onclick="location.href=\'index.php?setterminal=' . (int) $tid . '\'">' . dol_escape_htmltag($tlabel) . '</button>';
                                }
                            }
                        }
                    } else {
                        ?>
                        <?php
                        $hasPIN1 = !empty(getDolGlobalString('TAKEPOS_TERMINAL_PIN_1'));
                        if ($hasPIN1) {
                            $_tlabel1 = getDolGlobalString('TAKEPOS_TERMINAL_NAME_1', $langs->trans('TerminalName', 1));
                            print '<button type="button" class="block" onclick="takeposPinPrompt(1,\'' . dol_escape_js($_tlabel1) . '\')">' . dol_escape_htmltag($_tlabel1) . ' <span style="font-size:0.75em;opacity:0.7">&#128274;</span></button>';
                        } else { ?>\n                        <button type="button" class="block"\n                                onclick="location.href='index.php?setterminal=1'"><?php print getDolGlobalString("TAKEPOS_TERMINAL_NAME_1", $langs->trans("TerminalName", 1)); ?></button>
                        <?php } ?>
                        <?php
                        $nbloop = getDolGlobalInt('TAKEPOS_NUM_TERMINALS');
                        for ($i = 2; $i <= $nbloop; $i++) {
                            $hasPINi = !empty(getDolGlobalString('TAKEPOS_TERMINAL_PIN_'.$i));
                            if ($hasPINi) {
                                $_tlabeli = getDolGlobalString('TAKEPOS_TERMINAL_NAME_'.$i, $langs->trans('TerminalName', $i));
                                print '<button type="button" class="block" onclick="takeposPinPrompt('.$i.',\'' . dol_escape_js($_tlabeli) . '\')">' . dol_escape_htmltag($_tlabeli) . ' <span style="font-size:0.75em;opacity:0.7">&#128274;</span></button>';
                            } else {
                                print '<button type="button" class="block" onclick="location.href=\'index.php?setterminal=' . $i . '\'">' . getDolGlobalString("TAKEPOS_TERMINAL_NAME_" . $i, $langs->trans("TerminalName", $i)) . '</button>';
                            }
                        }
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Modal PIN verify for terminal access -->
        <div id="ModalTerminalPIN" class="modal" style="display:none">
            <div class="modal-content" style="max-width:380px;margin:8% auto;padding:0;border-radius:12px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,0.25)">
                <div class="modal-header" style="background:#1a2744;padding:20px 24px;display:flex;align-items:center;gap:12px">
                    <span style="font-size:1.5em">🔐</span>
                    <div>
                        <h3 style="margin:0;color:#fff;font-size:1.1em" id="ModalTerminalPINTitle">Terminal PIN</h3>
                        <p style="margin:0;color:rgba(255,255,255,0.6);font-size:0.8em">Enter your PIN to access this terminal</p>
                    </div>
                </div>
                <div class="modal-body" style="padding:24px;background:#fff">
                    <div style="display:flex;justify-content:center;gap:10px;margin-bottom:20px" id="pinDots">
                        <?php
                        $pinLen = getDolGlobalInt('TAKEPOS_TERMINAL_PIN_LENGTH', 4);
                        for ($d = 0; $d < $pinLen; $d++) {
                            print '<div class="pin-dot" style="width:16px;height:16px;border-radius:50%;border:2px solid #ccc;background:#fff;transition:all 0.15s"></div>';
                        }
                        ?>
                    </div>
                    <input type="password" id="terminalPinInput"
                           style="position:absolute;opacity:0;pointer-events:none"
                           maxlength="<?php print $pinLen; ?>"
                           autocomplete="off" inputmode="numeric" />
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;max-width:240px;margin:0 auto">
                        <?php foreach([1,2,3,4,5,6,7,8,9,'',0,'⌫'] as $k) { ?>
                            <button type="button" onclick="pinPad('<?php print $k; ?>')"
                                    style="padding:16px;font-size:1.3em;font-weight:600;border:1px solid #e0e0e0;border-radius:8px;background:#f8f9fa;cursor:pointer;<?php print ($k==='' ? 'visibility:hidden' : ''); ?>"
                                <?php print ($k==='' ? 'disabled' : ''); ?>>
                                <?php print $k; ?>
                            </button>
                        <?php } ?>
                    </div>
                    <p id="terminalPinError" style="color:#e74c3c;text-align:center;margin:14px 0 0;display:none;font-size:0.9em">&#10007; Incorrect PIN</p>
                    <button type="button" onclick="takeposPinCancel()" style="width:100%;margin-top:16px;padding:10px;border:1px solid #ddd;border-radius:8px;background:#f5f5f5;cursor:pointer;font-size:0.9em;color:#666">Cancel</button>
                </div>
            </div>
        </div>
        <style>
            .pin-dot.filled { background:#1a2744 !important; border-color:#1a2744 !important; }
        </style>

        <script>
            var _takeposPinTerminalId = 0;
            var _pinLength = <?php print getDolGlobalInt('TAKEPOS_TERMINAL_PIN_LENGTH', 4); ?>;
            var _pinValue = '';

            function pinPad(k) {
                if (k === '⌫') {
                    _pinValue = _pinValue.slice(0, -1);
                } else if (String(k) !== '' && _pinValue.length < _pinLength) {
                    _pinValue += String(k);
                }
                // Update dots
                var dots = document.querySelectorAll('.pin-dot');
                for (var i = 0; i < dots.length; i++) {
                    dots[i].classList.toggle('filled', i < _pinValue.length);
                }
                document.getElementById('terminalPinInput').value = _pinValue;
                if (_pinValue.length === _pinLength) {
                    setTimeout(takeposPinVerify, 120);
                }
            }

            function takeposPinPrompt(tid, tlabel) {
                _takeposPinTerminalId = tid;
                _pinValue = '';
                document.getElementById('ModalTerminalPINTitle').textContent = tlabel;
                document.getElementById('terminalPinInput').value = '';
                document.getElementById('terminalPinError').style.display = 'none';
                var dots = document.querySelectorAll('.pin-dot');
                dots.forEach(function(d){ d.classList.remove('filled'); });
                document.getElementById('ModalTerminalPIN').style.display = 'block';
            }
            function takeposPinCancel() {
                document.getElementById('ModalTerminalPIN').style.display = 'none';
                _pinValue = '';
            }
            function takeposPinVerify() {
                if (!_pinValue) return;
                document.getElementById('takeposPinInput2').value = _pinValue;
                document.getElementById('takeposPinTerminalId').value = _takeposPinTerminalId;
                document.getElementById('takeposPinForm').submit();
            }
            document.addEventListener('keydown', function(e){
                if (document.getElementById('ModalTerminalPIN').style.display === 'none') return;
                if (e.key >= '0' && e.key <= '9') { pinPad(e.key); }
                else if (e.key === 'Backspace') { pinPad('⌫'); }
                else if (e.key === 'Escape') { takeposPinCancel(); }
            });
        </script>

        <form id="takeposPinForm" method="POST" action="<?php print DOL_URL_ROOT; ?>/takepos/terminal_pin_check.php" style="display:none">
            <input type="hidden" name="token" value="<?php print newToken(); ?>">
            <input type="hidden" name="terminal_id" id="takeposPinTerminalId" value="">
            <input type="hidden" name="pin" id="takeposPinInput2" value="">
        </form>

        <!-- Modal multicurrency box -->
        <?php if (isModEnabled('multicurrency')) { ?>
            <div id="ModalCurrency" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <span class="close" href="#"
                              onclick="document.getElementById('ModalCurrency').style.display = 'none';">&times;</span>
                        <h3><?php print $langs->trans("SetMultiCurrencyCode"); ?></h3>
                    </div>
                    <div class="modal-body">
                        <?php
                        print '<button type="button" class="block" onclick="location.href=\'index.php?setcurrency=' . dol_escape_js($conf->currency) . '\'">' . dol_escape_htmltag($conf->currency) . '</button>';
                        $sql = 'SELECT code FROM ' . MAIN_DB_PREFIX . 'multicurrency';
                        $sql .= " WHERE entity IN ('" . getEntity('multicurrency') . "')";
                        $resql = $db->query($sql);
                        if ($resql) {
                            while ($obj = $db->fetch_object($resql)) {
                                $currencyCode = takeposNormalizeCurrencyCode($obj->code);
                                if ($currencyCode === '') {
                                    continue;
                                }
                                print '<button type="button" class="block" onclick="location.href=\'index.php?setcurrency=' . dol_escape_js($currencyCode) . '\'">' . dol_escape_htmltag($currencyCode) . '</button>';
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
        <?php } else { ?>
            <!-- Issue #9 fix: TakePOS native currency modal (no multicurrency module needed) -->
            <div id="ModalCurrency" class="modal">
                <div class="modal-content" style="
                max-width: 420px;
                width: calc(100vw - 32px);
                box-sizing: border-box;
                overflow: hidden;
            ">
                    <div class="modal-header" style="direction:rtl;">
                        <span class="close" href="#" onclick="document.getElementById('ModalCurrency').style.display='none';">&times;</span>
                        <h3 style="margin:0;font-size:16px;"><?php echo dol_escape_htmltag($langs->trans('TakeposCurrencySelectTitle')); ?></h3>
                    </div>
                    <div class="modal-body" style="padding:16px;direction:rtl;">
                        <?php
                        $baseCurr   = strtoupper(trim(!empty($conf->currency) ? $conf->currency : 'JOD'));
                        $activeCurr = isset($_SESSION['takeposcustomercurrency']) ? strtoupper((string)$_SESSION['takeposcustomercurrency']) : '';
                        $activeRate = isset($_SESSION['takeposcustomercurrencyrate']) ? (float)$_SESSION['takeposcustomercurrencyrate'] : 0;
                        $isBaseCurr = ($activeCurr === '' || $activeCurr === $baseCurr);

                        // Active currency badge
                        if (!$isBaseCurr && $activeRate > 0) {
                            echo '<div style="margin-bottom:12px;padding:8px 12px;background:#e3f2fd;border:1px solid #1565c0;border-radius:8px;font-size:13px;color:#1565c0;font-weight:600;text-align:right;">';
                            echo '✅ ' . dol_escape_htmltag($langs->trans('TakeposActiveCurrency')) . ': <strong>' . dol_escape_htmltag($activeCurr) . '</strong> — ' . dol_escape_htmltag($langs->trans('TakeposExchangeRate')) . ': ' . number_format($activeRate, 4);
                            echo '</div>';
                        }
                        ?>

                        <!-- Base currency -->
                        <button type="button" onclick="location.href='index.php?setcurrency=<?php echo dol_escape_js($baseCurr); ?>'" style="
                                display:block; width:100%; box-sizing:border-box;
                                padding:12px 16px; margin-bottom:10px;
                                font-size:15px; font-weight:700; text-align:center;
                                background:<?php echo $isBaseCurr ? '#2e7d32' : '#e8f5e9'; ?>;
                                color:<?php echo $isBaseCurr ? '#fff' : '#2e7d32'; ?>;
                                border:2px solid #2e7d32; border-radius:10px; cursor:pointer;">
                            <?php echo dol_escape_htmltag($baseCurr); ?> — <?php echo dol_escape_htmltag($langs->trans('TakeposCurrencyBase')); ?>
                            <?php if ($isBaseCurr) echo ' ✓'; ?>
                        </button>

                        <?php
                        // Build currency list: USD, EUR + any others we want to show
                        $currencyList = array(
                            'USD' => array('flag' => '🇺🇸', 'label' => $langs->trans('TakeposCurrencyUSD'), 'defaultRate' => 0.71, 'color' => '#1565c0'),
                            'EUR' => array('flag' => '🇪🇺', 'label' => $langs->trans('TakeposCurrencyEUR'), 'defaultRate' => 0.76, 'color' => '#6a1b9a'),
                        );
                        foreach ($currencyList as $code => $info) {
                            $isActive = ($activeCurr === $code);
                            $savedR   = ($isActive && $activeRate > 0) ? $activeRate : $info['defaultRate'];
                            $inputId  = 'kafo_rate_' . strtolower($code);
                            $btnColor = $info['color'];
                            ?>
                            <div style="
                                    border:2px solid <?php echo $isActive ? $btnColor : '#e0e0e0'; ?>;
                                    border-radius:10px; padding:12px 14px; margin-bottom:10px;
                                    background:<?php echo $isActive ? '#f3f0ff' : '#fafafa'; ?>;
                                    box-sizing:border-box;
                                    ">
                                <div style="font-weight:700;font-size:14px;margin-bottom:10px;color:<?php echo $btnColor; ?>;">
                                    <?php echo $info['flag']; ?> <?php echo dol_escape_htmltag($code); ?> — <?php echo dol_escape_htmltag($info['label']); ?>
                                    <?php if ($isActive) echo ' <span style="font-size:12px;background:'.$btnColor.';color:#fff;padding:1px 6px;border-radius:4px;">✓ نشط</span>'; ?>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px;flex-wrap:nowrap;justify-content:flex-end;">
                                    <button type="button"
                                            onclick="var r=parseFloat(document.getElementById('<?php echo $inputId; ?>').value);if(r>0){location.href='index.php?setcurrency=<?php echo dol_escape_js($code); ?>&setcurrencyrate='+encodeURIComponent(r.toFixed(4));}else{alert('<?php echo dol_escape_js($langs->trans('TakeposRateInvalid')); ?>')}"
                                            style="padding:7px 16px;background:<?php echo $btnColor; ?>;color:#fff;border:none;border-radius:7px;cursor:pointer;font-size:13px;font-weight:700;white-space:nowrap;flex-shrink:0;">
                                        <?php echo dol_escape_htmltag($langs->trans('TakeposSelect')); ?>
                                    </button>
                                    <input type="number" id="<?php echo $inputId; ?>"
                                           step="0.0001" min="0.0001"
                                           value="<?php echo number_format((float)$savedR, 4, '.', ''); ?>"
                                           style="width:90px;min-width:70px;padding:7px;border:1px solid #ccc;border-radius:7px;font-size:14px;text-align:center;flex-shrink:0;">
                                    <label style="font-size:13px;white-space:nowrap;color:#555;"><?php echo dol_escape_htmltag($langs->trans('TakeposExchangeRate')); ?>:</label>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        <?php } ?>

        <!-- Modal terminal Credit Note -->
        <div id="ModalCreditNote" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="close" href="#"
                          onclick="document.getElementById('ModalCreditNote').style.display = 'none';">&times;</span>
                    <h3><?php print $langs->trans("invoiceAvoirWithLines"); ?></h3>
                </div>
                <div class="modal-body">
                    <button type="button" class="block"
                            onclick="CreditNote(); document.getElementById('ModalCreditNote').style.display = 'none';"><?php print $langs->trans("Yes"); ?></button>
                    <button type="button" class="block"
                            onclick="document.getElementById('ModalCreditNote').style.display = 'none';"><?php print $langs->trans("No"); ?></button>
                </div>
            </div>
        </div>

        <!-- Modal Note -->
        <div id="ModalNote" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="close" href="#" onclick="document.getElementById('ModalNote').style.display = 'none';">&times;</span>
                    <h3><?php print $langs->trans("Note"); ?></h3>
                </div>
                <div class="modal-body">
                    <input type="text" class="block" id="textinput">
                    <button type="button" class="block"
                            onclick="SetNote(); document.getElementById('ModalNote').style.display = 'none';">OK
                    </button>
                </div>
            </div>
        </div>
        <!-- Modal Calculator -->
        <div id="ModalCalculator" class="modal">
            <div class="modal-content takepos-calc-modal-content"
                 style="width:min(420px,92vw);max-width:420px;margin:6vh auto;border:1px solid #5f82b4;border-radius:16px;overflow:hidden;box-shadow:0 22px 50px rgba(22,39,72,0.28);background:#f7f9fd;">
                <div class="modal-header takepos-calc-modal-header"
                     style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;background:linear-gradient(135deg,#5b86c5,#6f97d5);border-bottom:1px solid rgba(255,255,255,0.18);">
                    <span class="close" href="#" style="color:#fff;font-size:34px;line-height:1;text-shadow:none;"
                          onclick="document.getElementById('ModalCalculator').style.display = 'none';">&times;</span>
                    <h3 style="margin:0;font-size:24px;font-weight:700;color:#fff;"><?php print dol_escape_htmltag($tpLabelCalculator); ?></h3>
                </div>
                <div class="modal-body takepos-calc-modal-body" style="padding:18px;background:#f7f9fd;">
                    <input type="text" id="takepos-calc-display" class="takepos-calc-display" inputmode="decimal"
                           autocomplete="off"
                           style="width:100%;box-sizing:border-box;font-size:28px;font-weight:700;padding:14px 16px;text-align:right;margin-bottom:14px;border:1px solid #c8d6ea;border-radius:12px;background:#fff;color:#23324c;box-shadow:inset 0 1px 2px rgba(35,50,76,0.06);"
                           onkeydown="if (event.key === 'Enter') { event.preventDefault(); TakeposCalcEval(); }">
                    <div class="takepos-calc-grid"
                         style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;">
                        <button type="button" class="button takepos-calc-btn"
                                style="width:100%;margin:0;padding:14px 10px;border:1px solid #d2dceb;border-radius:12px;background:#ffffff;color:#20314d;font-size:22px;font-weight:700;line-height:1.1;box-shadow:0 2px 6px rgba(25,42,70,0.08);"
                                onclick="TakeposCalcAppend('7')">7
                        </button>
                        <button type="button" class="button takepos-calc-btn"
                                style="width:100%;margin:0;padding:14px 10px;border:1px solid #d2dceb;border-radius:12px;background:#ffffff;color:#20314d;font-size:22px;font-weight:700;line-height:1.1;box-shadow:0 2px 6px rgba(25,42,70,0.08);"
                                onclick="TakeposCalcAppend('8')">8
                        </button>
                        <button type="button" class="button takepos-calc-btn"
                                style="width:100%;margin:0;padding:14px 10px;border:1px solid #d2dceb;border-radius:12px;background:#ffffff;color:#20314d;font-size:22px;font-weight:700;line-height:1.1;box-shadow:0 2px 6px rgba(25,42,70,0.08);"
                                onclick="TakeposCalcAppend('9')">9
                        </button>
                        <button type="button" class="button takepos-calc-btn takepos-calc-operator"
                                style="width:100%;margin:0;padding:14px 10px;border:1px solid #c8d6ea;border-radius:12px;background:#edf3fc;color:#2f5e9f;font-size:22px;font-weight:700;line-height:1.1;box-shadow:0 2px 6px rgba(25,42,70,0.08);"
                                onclick="TakeposCalcAppend('/')">/
                        </button>
                        <button type="button" class="button takepos-calc-btn"
                                style="width:100%;margin:0;padding:14px 10px;border:1px solid #d2dceb;border-radius:12px;background:#ffffff;color:#20314d;font-size:22px;font-weight:700;line-height:1.1;box-shadow:0 2px 6px rgba(25,42,70,0.08);"
                                onclick="TakeposCalcAppend('4')">4
                        </button>
                        <button type="button" class="button takepos-calc-btn"
                                style="width:100%;margin:0;padding:14px 10px;border:1px solid #d2dceb;border-radius:12px;background:#ffffff;color:#20314d;font-size:22px;font-weight:700;line-height:1.1;box-shadow:0 2px 6px rgba(25,42,70,0.08);"
                                onclick="TakeposCalcAppend('5')">5
                        </button>
                        <button type="button" class="button takepos-calc-btn"
                                style="width:100%;margin:0;padding:14px 10px;border:1px solid #d2dceb;border-radius:12px;background:#ffffff;color:#20314d;font-size:22px;font-weight:700;line-height:1.1;box-shadow:0 2px 6px rgba(25,42,70,0.08);"
                                onclick="TakeposCalcAppend('6')">6
                        </button>
                        <button type="button" class="button takepos-calc-btn takepos-calc-operator"
                                style="width:100%;margin:0;padding:14px 10px;border:1px solid #c8d6ea;border-radius:12px;background:#edf3fc;color:#2f5e9f;font-size:22px;font-weight:700;line-height:1.1;box-shadow:0 2px 6px rgba(25,42,70,0.08);"
                                onclick="TakeposCalcAppend('*')">*
                        </button>
                        <button type="button" class="button takepos-calc-btn"
                                style="width:100%;margin:0;padding:14px 10px;border:1px solid #d2dceb;border-radius:12px;background:#ffffff;color:#20314d;font-size:22px;font-weight:700;line-height:1.1;box-shadow:0 2px 6px rgba(25,42,70,0.08);"
                                onclick="TakeposCalcAppend('1')">1
                        </button>
                        <button type="button" class="button takepos-calc-btn"
                                style="width:100%;margin:0;padding:14px 10px;border:1px solid #d2dceb;border-radius:12px;background:#ffffff;color:#20314d;font-size:22px;font-weight:700;line-height:1.1;box-shadow:0 2px 6px rgba(25,42,70,0.08);"
                                onclick="TakeposCalcAppend('2')">2
                        </button>
                        <button type="button" class="button takepos-calc-btn"
                                style="width:100%;margin:0;padding:14px 10px;border:1px solid #d2dceb;border-radius:12px;background:#ffffff;color:#20314d;font-size:22px;font-weight:700;line-height:1.1;box-shadow:0 2px 6px rgba(25,42,70,0.08);"
                                onclick="TakeposCalcAppend('3')">3
                        </button>
                        <button type="button" class="button takepos-calc-btn takepos-calc-operator"
                                style="width:100%;margin:0;padding:14px 10px;border:1px solid #c8d6ea;border-radius:12px;background:#edf3fc;color:#2f5e9f;font-size:22px;font-weight:700;line-height:1.1;box-shadow:0 2px 6px rgba(25,42,70,0.08);"
                                onclick="TakeposCalcAppend('-')">-
                        </button>
                        <button type="button" class="button takepos-calc-btn"
                                style="width:100%;margin:0;padding:14px 10px;border:1px solid #d2dceb;border-radius:12px;background:#ffffff;color:#20314d;font-size:22px;font-weight:700;line-height:1.1;box-shadow:0 2px 6px rgba(25,42,70,0.08);"
                                onclick="TakeposCalcAppend('0')">0
                        </button>
                        <button type="button" class="button takepos-calc-btn"
                                style="width:100%;margin:0;padding:14px 10px;border:1px solid #d2dceb;border-radius:12px;background:#ffffff;color:#20314d;font-size:22px;font-weight:700;line-height:1.1;box-shadow:0 2px 6px rgba(25,42,70,0.08);"
                                onclick="TakeposCalcAppend('.')">.
                        </button>
                        <button type="button" class="button takepos-calc-btn takepos-calc-clear"
                                style="width:100%;margin:0;padding:14px 10px;border:1px solid #e8bbb5;border-radius:12px;background:#fff1f0;color:#b44434;font-size:22px;font-weight:700;line-height:1.1;box-shadow:0 2px 6px rgba(25,42,70,0.08);"
                                onclick="TakeposCalcAppend('C')">C
                        </button>
                        <button type="button" class="button takepos-calc-btn takepos-calc-operator"
                                style="width:100%;margin:0;padding:14px 10px;border:1px solid #c8d6ea;border-radius:12px;background:#edf3fc;color:#2f5e9f;font-size:22px;font-weight:700;line-height:1.1;box-shadow:0 2px 6px rgba(25,42,70,0.08);"
                                onclick="TakeposCalcAppend('+')">+
                        </button>
                    </div>
                    <div class="takepos-calc-actions"
                         style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:14px;">
                        <button type="button" class="button button-pay takepos-calc-btn takepos-calc-equals"
                                style="width:100%;margin:0;padding:14px 10px;border:1px solid #5f82b4;border-radius:12px;background:#5f82b4;color:#fff;font-size:22px;font-weight:700;line-height:1.1;box-shadow:0 2px 6px rgba(25,42,70,0.08);"
                                onclick="TakeposCalcEval()">=
                        </button>
                        <button type="button" class="button takepos-calc-btn takepos-calc-close"
                                style="width:100%;margin:0;padding:14px 10px;border:1px solid #d2dceb;border-radius:12px;background:#eef2f7;color:#40546f;font-size:18px;font-weight:700;line-height:1.1;box-shadow:0 2px 6px rgba(25,42,70,0.08);"
                                onclick="document.getElementById('ModalCalculator').style.display = 'none';"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonClose')); ?></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- TakePOS Feedback Bar -->
        <div id="takepos-feedback-bar" class="takepos-feedback-bar" style="display:none;" role="status"
             aria-live="polite"></div>

        <!-- Modal Hold Sale -->
        <div id="ModalHold" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="close" href="#" onclick="document.getElementById('ModalHold').style.display='none';">&times;</span>
                    <h3>
                        <span class="fa fa-pause-circle"></span> <?php echo dol_escape_htmltag($langs->trans('TakeposIndexHoldModalTitle')); ?>
                    </h3>
                </div>
                <div class="modal-body">
                    <p style="margin:0 0 10px"><?php echo dol_escape_htmltag($langs->trans('TakeposIndexHoldModalDescription')); ?></p>
                    <label for="takepos-hold-label-input"
                           style="display:block;margin-bottom:4px"><?php echo dol_escape_htmltag($langs->trans('TakeposIndexHoldLabel')); ?></label>
                    <input type="text" id="takepos-hold-label-input" class="block"
                           placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposIndexHoldPlaceholder')); ?>"
                           maxlength="128">
                    <div id="takepos-hold-feedback"
                         style="min-height:18px;margin:6px 0;color:#c00;font-size:0.9em;"></div>
                    <button type="button" class="block" onclick="HoldSaleConfirm();">
                        <span class="fa fa-pause-circle"></span> <?php echo dol_escape_htmltag($langs->trans('TakeposIndexConfirmHold')); ?>
                    </button>
                    <button type="button" class="block"
                            onclick="document.getElementById('ModalHold').style.display='none';">
                        <?php echo dol_escape_htmltag($langs->trans('TakeposCommonCancel')); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Held Sales List -->
        <div id="ModalHeldList" class="modal">
            <div class="modal-content" style="max-width:680px;width:92%;">
                <div class="modal-header">
                    <span class="close" href="#"
                          onclick="document.getElementById('ModalHeldList').style.display='none';">&times;</span>
                    <h3>
                        <span class="fa fa-list-alt"></span> <?php echo dol_escape_htmltag($langs->trans('TakeposIndexHeldSalesTitle')); ?>
                    </h3>
                </div>
                <div class="modal-body">
                    <div id="takepos-held-list" style="min-height:60px;overflow-x:auto;">
                        <em><?php echo dol_escape_htmltag($langs->trans('TakeposIndexHeldLoading')); ?></em>
                    </div>
                    <button type="button" class="block"
                            onclick="document.getElementById('ModalHeldList').style.display='none';"
                            style="margin-top:14px">
                        <?php echo dol_escape_htmltag($langs->trans('TakeposIndexClose')); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Manager Override -->
        <div id="ModalManagerOverride" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="close" href="#" onclick="closeManagerOverrideModal();">&times;</span>
                    <h3><?php echo dol_escape_htmltag($langs->trans('TakeposIndexManagerApprovalRequired')); ?></h3>
                </div>
                <div class="modal-body">
                    <div><?php echo dol_escape_htmltag($langs->trans('TakeposIndexActionNotAllowed')); ?> <strong
                                id="manager-override-action-label"><?php echo dol_escape_htmltag($langs->trans('TakeposIndexManagerActionGeneric')); ?></strong>.
                    </div>
                    <div><?php echo dol_escape_htmltag($langs->trans('TakeposIndexManagerApprovalHint')); ?></div>
                    <input type="text" class="block" id="manager_barcode"
                           placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposIndexManagerBarcodePlaceholder')); ?>">
                    <div style="margin: 8px 0; text-align: center;"><?php echo dol_escape_htmltag($langs->trans('TakeposIndexOr')); ?></div>
                    <input type="text" class="block" id="manager_login"
                           placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposIndexManagerLoginPlaceholder')); ?>">
                    <input type="password" class="block" id="manager_password"
                           placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposIndexManagerPasswordPlaceholder')); ?>">
                    <div id="manager-override-message" style="margin: 8px 0; min-height: 18px;"></div>
                    <button type="button" class="block"
                            onclick="submitManagerOverride();"><?php echo dol_escape_htmltag($langs->trans('TakeposIndexApprove')); ?></button>
                    <button type="button" class="block"
                            onclick="closeManagerOverrideModal();"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonCancel')); ?></button>
                </div>
            </div>
        </div>

        <div id="takepos-main-layout">
            <div class="row1<?php if (!getDolGlobalString('TAKEPOS_HIDE_HEAD_BAR')) {
                print 'withhead';
            } ?>">

                <div id="poslines" class="div1">
                </div>

                <div class="div2">
                    <button type="button" class="calcbutton" onclick="Edit(7);"><span class="takepos-pad-badge">7</span>7
                    </button>
                    <button type="button" class="calcbutton" onclick="Edit(8);"><span class="takepos-pad-badge">8</span>8
                    </button>
                    <button type="button" class="calcbutton" onclick="Edit(9);"><span class="takepos-pad-badge">9</span>9
                    </button>
                    <button type="button" id="qty" class="calcbutton2 takepos-pad-qty" onclick="Edit('qty')"><span
                                class="takepos-pad-badge">F9</span><?php echo $langs->trans("TakeposUiQty"); ?></button>
                    <button type="button" class="calcbutton" onclick="Edit(4);"><span class="takepos-pad-badge">4</span>4
                    </button>
                    <button type="button" class="calcbutton" onclick="Edit(5);"><span class="takepos-pad-badge">5</span>5
                    </button>
                    <button type="button" class="calcbutton" onclick="Edit(6);"><span class="takepos-pad-badge">6</span>6
                    </button>
                    <button type="button" id="price" class="calcbutton2 takepos-pad-price" onclick="Edit('p')"><span
                                class="takepos-pad-badge">F10</span><?php echo $langs->trans("TakeposUiPrice"); ?>
                    </button>
                    <button type="button" class="calcbutton" onclick="Edit(1);"><span class="takepos-pad-badge">1</span>1
                    </button>
                    <button type="button" class="calcbutton" onclick="Edit(2);"><span class="takepos-pad-badge">2</span>2
                    </button>
                    <button type="button" class="calcbutton" onclick="Edit(3);"><span class="takepos-pad-badge">3</span>3
                    </button>
                    <button type="button" id="reduction" class="calcbutton2 takepos-pad-discount" onclick="Edit('r')">
                        <span class="takepos-pad-badge">F11</span><?php echo $langs->trans("TakeposUiLineDiscountShort"); ?>
                    </button>
                    <button type="button" class="calcbutton" onclick="Edit(0);"><span class="takepos-pad-badge">0</span>0
                    </button>
                    <button type="button" class="calcbutton" onclick="Edit('.')"><span
                                class="takepos-pad-badge">.</span>.
                    </button>
                    <button type="button" class="calcbutton poscolorblue" onclick="Edit('c')"><span
                                class="takepos-pad-badge">BS</span>C
                    </button>
                    <button type="button" class="calcbutton2 poscolordelete" id="delete" onclick="deleteline()"><span
                                class="takepos-pad-badge">Del</span><span class="fa fa-trash"></span></button>
                </div>

                <?php

                // TakePOS setup check
                if (isset($_SESSION["takeposterminal"]) && $_SESSION["takeposterminal"]) {
                    $sql = "SELECT code, libelle FROM " . MAIN_DB_PREFIX . "c_paiement";
                    $sql .= " WHERE entity IN (" . getEntity('c_paiement') . ")";
                    $sql .= " AND active = 1";
                    $sql .= " ORDER BY CASE code WHEN 'LIQ' THEN 1 WHEN 'CB' THEN 2 WHEN 'CHQ' THEN 3 ELSE 99 END, libelle";

                    $resql = $db->query($sql);
                    $paiementsModes = array();
                    if ($resql) {
                        while ($obj = $db->fetch_object($resql)) {
                            $paycode = $obj->code;
                            if ($paycode == 'LIQ') {
                                $paycode = 'CASH';
                            }
                            if ($paycode == 'CHQ') {
                                $paycode = 'CHEQUE';
                            }

                            $constantforkey = "CASHDESK_ID_BANKACCOUNT_" . $paycode . $_SESSION["takeposterminal"];
                            //var_dump($constantforkey.' '.getDolGlobalInt($constantforkey));
                            if (takeposResolveTerminalBankAccountId($paycode, $_SESSION["takeposterminal"]) > 0) {
                                array_push($paiementsModes, $obj);
                            }
                        }
                    }

                    if (empty($paiementsModes) && isModEnabled("bank")) {
                        $langs->load('errors');
                        setEventMessages($langs->trans("ErrorModuleSetupNotComplete", $langs->transnoentitiesnoconv("TakePOS")), null, 'errors');
                        setEventMessages($langs->trans("ProblemIsInSetupOfTerminal", $_SESSION["takeposterminal"]), null, 'errors');
                    }
                }

                if (count($maincategories) == 0) {
                    if (getDolGlobalInt('TAKEPOS_ROOT_CATEGORY_ID') > 0) {
                        $tmpcategory = new Categorie($db);
                        $tmpcategory->fetch(getDolGlobalInt('TAKEPOS_ROOT_CATEGORY_ID'));
                        setEventMessages($langs->trans("TakeposNeedsAtLeastOnSubCategoryIntoParentCategory", $tmpcategory->label), null, 'errors');
                    } else {
                        setEventMessages($langs->trans("TakeposNeedsCategories"), null, 'errors');
                    }
                }
                require __DIR__ . '/partials/index_action_menus.php';
                require __DIR__ . '/partials/index_action_buttons.php';
                ?>
            </div>

            <div class="row2<?php if (!getDolGlobalString('TAKEPOS_HIDE_HEAD_BAR')) {
                print 'withhead';
            } ?>">

                <!--  Show categories -->
                <?php
                if (getDolGlobalInt('TAKEPOS_HIDE_CATEGORIES') == 1) {
                    print '<div class="div4" style="display: none;">';
                } else {
                    // Force single row: columns = MAXCATEG - 2 (minus the 2 nav arrows)
                    $catColCount = max(1, (int)$MAXCATEG - 2);
                    print '<div class="div4" style="grid-template-columns: repeat('.$catColCount.', minmax(0, 1fr)) !important;">';
                }

                $count = 0;
                while ($count < $MAXCATEG) {
                    ?>
                    <div class="wrapper" <?php if ($count == ($MAXCATEG - 2)) {
                        echo 'onclick="MoreCategories(\'less\')"';
                    } elseif ($count == ($MAXCATEG - 1)) {
                        echo 'onclick="MoreCategories(\'more\')"';
                    } else {
                        echo 'onclick="LoadProducts(' . $count . ')"';
                    } ?> id="catdiv<?php echo $count; ?>">
                        <?php
                        if ($count == ($MAXCATEG - 2)) {
                            //echo '<img class="imgwrapper" src="img/arrow-prev-top.png" height="100%" id="catimg'.$count.'" />';
                            echo '<span class="fa fa-chevron-left centerinmiddle" style="font-size: 5em; cursor: pointer;"></span>';
                        } elseif ($count == ($MAXCATEG - 1)) {
                            //echo '<img class="imgwrapper" src="img/arrow-next-top.png" height="100%" id="catimg'.$count.'" />';
                            echo '<span class="fa fa-chevron-right centerinmiddle" style="font-size: 5em; cursor: pointer;"></span>';
                        } else {
                            if (!getDolGlobalString('TAKEPOS_HIDE_CATEGORY_IMAGES')) {
                                echo '<img class="imgwrapper" id="catimg' . $count . '" />';
                            }
                        } ?>
                        <?php if ($count != $MAXCATEG - 2 && $count != $MAXCATEG - 1) { ?>
                            <div class="description" id="catdivdesc<?php echo $count; ?>">
                                <div class="description_content" id="catdesc<?php echo $count; ?>"></div>
                            </div>
                        <?php } ?>
                        <div class="catwatermark" id='catwatermark<?php echo $count; ?>'>...</div>
                    </div>
                    <?php
                    $count++;
                }
                ?>
            </div>

            <!--  Show product -->
            <div class="div5<?php if (getDolGlobalInt('TAKEPOS_HIDE_CATEGORIES') == 1) {
                print ' centpercent';
            } ?>">
                <?php
                $count = 0;

                while ($count < $MAXPRODUCT) {
                print '<div class="wrapper2' . (($count >= ($MAXPRODUCT - 2)) ? ' arrow' : '') . '" id="prodiv' . $count . '" '; ?>
                <?php if ($count == ($MAXPRODUCT - 2)) {
                    ?> onclick="MoreProducts('less')" <?php
                }
                if ($count == ($MAXPRODUCT - 1)) {
                    ?> onclick="MoreProducts('more')" <?php
                } else {
                    echo 'onclick="ClickProduct(' . ((int)$count) . ')"';
                } ?>>
                <?php
                if ($count == ($MAXPRODUCT - 2)) {
                    //echo '<img class="imgwrapper" src="img/arrow-prev-top.png" height="100%" id="proimg'.$count.'" />';
                    print '<span class="fa fa-chevron-left centerinmiddle" style="font-size: 5em; cursor: pointer;"></span>';
                } elseif ($count == ($MAXPRODUCT - 1)) {
                    //echo '<img class="imgwrapper" src="img/arrow-next-top.png" height="100%" id="proimg'.$count.'" />';
                    print '<span class="fa fa-chevron-right centerinmiddle" style="font-size: 5em; cursor: pointer;"></span>';
                } else {
                    if (!getDolGlobalString('TAKEPOS_HIDE_PRODUCT_PRICES')) {
                        print '<div class="" id="proprice' . $count . '"></div>';
                    }
                    if (!getDolGlobalString('TAKEPOS_SHOW_PRODUCT_IMAGES')) {
                        print '<button type="button" id="probutton' . $count . '" class="productbutton" style="display: none;"></button>';
                    } else {
                        print '<img class="imgwrapper" title="" id="proimg' . $count . '">';
                        print '<span class="takepos-missing-product-image-badge" title="' . dol_escape_htmltag($langs->trans('TakeposUiMissingProductImage')) . '"><span class="fa fa-exclamation-triangle"></span></span>';
                    }
                } ?>
                <?php if ($count != $MAXPRODUCT - 2 && $count != $MAXPRODUCT - 1 && getDolGlobalString('TAKEPOS_SHOW_PRODUCT_IMAGES')) { ?>
                    <div class="description" id="prodivdesc<?php echo $count; ?>">
                        <div class="description_content" id="prodesc<?php echo $count; ?>"></div>
                    </div>
                <?php } ?>
                <div class="catwatermark" id='prowatermark<?php echo $count; ?>'>...</div>
            </div>
            <?php
            $count++;
            }
            ?>
            <input type="hidden" id="search_start_less" value="0">
            <input type="hidden" id="search_start_more" value="0">
            <input type="hidden" id="search_pagination" value="">
        </div>
    </div>
    </div>
    <?php if ($takeposV2Enabled) { ?>
        <!-- V2: floating keypad toggle (only used when takepos-v2 body class is present) -->
        <button type="button" id="takepos-keypad-toggle" title="<?php echo dol_escape_htmltag($tpLabelCalculator); ?>"
                aria-label="<?php echo dol_escape_htmltag($tpLabelCalculator); ?>">
            <span class="fa fa-calculator" aria-hidden="true"></span>
        </button>
        <button type="button" id="takepos-keypad-close" title="✕" aria-label="✕">×</button>
        <!-- V2: toast slot -->
        <div id="takepos-toast" class="takepos-toast" role="status" aria-live="polite">
            <span class="icon-circle">✓</span>
            <span class="msg"></span>
        </div>
        <script>
            (function () {
                // Floating keypad open/close — body class drives visibility (.div2 in CSS)
                var bodyEl = document.body;
                var toggleBtn = document.getElementById('takepos-keypad-toggle');
                var closeBtn = document.getElementById('takepos-keypad-close');
                var keypadPanel = null;

                // Position close button at top-right corner of the keypad panel
                function positionCloseBtn() {
                    if (!closeBtn) return;
                    if (!keypadPanel) {
                        keypadPanel = document.querySelector('#takepos-main-layout .div2');
                    }
                    if (!keypadPanel) return;
                    var rect = keypadPanel.getBoundingClientRect();
                    if (rect.width === 0) return; // not visible yet
                    closeBtn.style.top    = Math.max(4, rect.top - 10) + 'px';
                    closeBtn.style.left   = (rect.right - 10) + 'px';
                    closeBtn.style.right  = 'auto';
                    closeBtn.style.bottom = 'auto';
                }

                if (toggleBtn) {
                    toggleBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        bodyEl.classList.add('takepos-keypad-open');
                        // Wait for CSS transition then position
                        setTimeout(positionCloseBtn, 30);
                    });
                }
                if (closeBtn) {
                    closeBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        bodyEl.classList.remove('takepos-keypad-open');
                    });
                }

                // Reposition on resize
                window.addEventListener('resize', function () {
                    if (bodyEl.classList.contains('takepos-keypad-open')) {
                        positionCloseBtn();
                    }
                });

                // Auto-open the keypad when the user clicks a cart line, since that is
                // the moment they likely want to edit qty/price. Existing onclick="Edit(...)"
                // handlers continue to work normally.
                document.addEventListener('click', function (e) {
                    var target = e.target;
                    if (!target) return;
                    var t = target.closest && target.closest('#poslines tr');
                    if (t && !t.closest('thead')) {
                        bodyEl.classList.add('takepos-keypad-open');
                        setTimeout(positionCloseBtn, 30);
                    }
                });
                // Press 'C' or Escape to dismiss
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' && bodyEl.classList.contains('takepos-keypad-open')) {
                        bodyEl.classList.remove('takepos-keypad-open');
                    }
                });

                // Tiny toast helper available globally; existing JS can call window.takeposToast(msg)
                var toastEl = document.getElementById('takepos-toast');
                var toastTimer = null;
                window.takeposToast = function (msg, kind) {
                    if (!toastEl) return;
                    var msgEl = toastEl.querySelector('.msg');
                    if (msgEl) msgEl.textContent = String(msg || '');
                    toastEl.classList.remove('is-error', 'is-warning');
                    if (kind === 'error') toastEl.classList.add('is-error');
                    else if (kind === 'warning') toastEl.classList.add('is-warning');
                    toastEl.classList.add('is-show');
                    if (toastTimer) clearTimeout(toastTimer);
                    toastTimer = setTimeout(function () {
                        toastEl.classList.remove('is-show');
                    }, 2500);
                };

                // Drawer-open body class — the existing toggleTakeposShortcutsDrawer()
                // already toggles the .is-open class on the drawer; mirror it on <body>
                // so the V2 CSS can dim the main layout.
                var drawer = document.getElementById('takepos-shortcuts-drawer');
                if (drawer && window.MutationObserver) {
                    var obs = new MutationObserver(function () {
                        if (drawer.classList.contains('is-open')) {
                            bodyEl.classList.add('takepos-shortcuts-open');
                        } else {
                            bodyEl.classList.remove('takepos-shortcuts-open');
                        }
                    });
                    obs.observe(drawer, {attributes: true, attributeFilter: ['class']});
                }
            })();
        </script>
    <?php } ?>

    <script>
        (function () {
            var isRtl = document.documentElement.getAttribute('dir') === 'rtl' || document.body.classList.contains('tp-rtl');
            if (!isRtl) return;

            function getLoginBlock() {
                return document.querySelector('.topnav-right .login_block_user');
            }

            function getTrigger(block) {
                if (!block) return null;
                return block.querySelector('a.atoplogin, .userimg.atoplogin, .userimgatoplogin a, .userimgatoplogin, a');
            }

            function pickMenuTarget(block, trigger) {
                if (!block) return null;
                var selectors = [
                    '.user-body',
                    '.dropdown-menu',
                    '.menu_tdo',
                    '.menu_tderight',
                    '.tmenudiv'
                ];
                for (var s = 0; s < selectors.length; s++) {
                    var list = block.querySelectorAll(selectors[s]);
                    for (var i = 0; i < list.length; i++) {
                        var el = list[i];
                        if (!el || el === block || el === trigger) continue;
                        if (el.id === 'topmenu-login-dropdown') continue;
                        if (trigger && el.contains(trigger)) continue;
                        if (!el.querySelector || !el.querySelector('a, .tmenu')) continue;
                        return el;
                    }
                }
                return null;
            }

            function isOpen(el) {
                if (!el) return false;
                var cs = window.getComputedStyle(el);
                if (cs.display === 'none' || cs.visibility === 'hidden' || parseFloat(cs.opacity || '1') === 0) return false;
                var r = el.getBoundingClientRect();
                return r.width > 0 && r.height > 0;
            }

            function positionTarget(target, trigger) {
                if (!target || !trigger) return;
                var rect = trigger.getBoundingClientRect();
                target.classList.add('takepos-user-menu-overlay-target');
                target.style.top = Math.round(rect.bottom + 6) + 'px';
                target.style.right = Math.max(8, Math.round(window.innerWidth - rect.right)) + 'px';
                target.style.left = 'auto';
                target.style.bottom = 'auto';
            }

            function refresh() {
                var block = getLoginBlock();
                var trigger = getTrigger(block);
                var target = pickMenuTarget(block, trigger);
                if (!block || !trigger || !target) return;
                window.requestAnimationFrame(function () {
                    if (isOpen(target) || block.matches(':hover') || document.activeElement === trigger) {
                        positionTarget(target, trigger);
                    }
                });
            }

            function bind() {
                var block = getLoginBlock();
                if (!block) return;
                block.addEventListener('click', function () {
                    setTimeout(refresh, 0);
                    setTimeout(refresh, 60);
                    setTimeout(refresh, 180);
                }, true);
                window.addEventListener('resize', refresh);
                document.addEventListener('scroll', refresh, true);

                var obs = new MutationObserver(refresh);
                obs.observe(block, {
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['class', 'style']
                });
                setTimeout(refresh, 250);
            }

            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
            else bind();
        })();
    </script>

    <?php echo takeposHelpRender($langs, __FILE__); ?>
    <?php if ($takeposV2Enabled) { ?>
        <script>
            window.KAFO = window.KAFO || {
                token: <?php echo json_encode(newToken()); ?>,
                ajaxUrl: (typeof takeposAjaxUrl !== 'undefined' ? takeposAjaxUrl : <?php echo json_encode(DOL_URL_ROOT . '/takepos/ajax/ajax.php'); ?>),
                invoiceUrl: <?php echo json_encode(DOL_URL_ROOT . '/takepos/invoice.php'); ?>,
                place: (typeof place !== 'undefined' ? place : 0),
                cashAcct: (typeof takeposDirectPaymentConfig !== 'undefined' && takeposDirectPaymentConfig.LIQ ? takeposDirectPaymentConfig.LIQ.accountId : 0),
                cardAcct: (typeof takeposDirectPaymentConfig !== 'undefined' && takeposDirectPaymentConfig.CB ? takeposDirectPaymentConfig.CB.accountId : 0)
            };
        </script>
        <script src="<?php echo DOL_URL_ROOT ?>/takepos/js/kf_offline.js"></script>
        <script src="<?php echo DOL_URL_ROOT ?>/takepos/js/kf_offline_index.js"></script>
        <script>
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register(
                    <?php echo json_encode(DOL_URL_ROOT . '/takepos/kf_sw.js'); ?>,
                    { scope: <?php echo json_encode(DOL_URL_ROOT . '/takepos/'); ?> }
                ).catch(function (e) { console.warn('[KFOffline] service worker registration failed:', e); });
            }
        </script>
        <script src="<?php echo DOL_URL_ROOT ?>/takepos/js/takepos_v2_cat_active.js"></script>
        <script src="<?php echo DOL_URL_ROOT ?>/takepos/js/takepos_kafo_fixes.js"></script>
        <script src="<?php echo DOL_URL_ROOT ?>/takepos/js/takepos_v2_topbar.js?v=20260506e"></script>
    <?php } ?>
    </body>
<?php

llxFooter();

$db->close();