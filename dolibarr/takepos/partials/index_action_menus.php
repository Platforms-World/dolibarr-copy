<?php
// User menu and external TakePOS modules
$menus = array();
$r = 0;

// زر جديد
if (!getDolGlobalString('TAKEPOS_BAR_RESTAURANT')) {
    $menus[$r++] = array('id' => 'takepos-action-new', 'title' => '<span class="fa fa-plus-circle paddingrightonly"></span><div class="trunc">'.$langs->trans("TakeposUiNew").'</div>', 'action' => 'New();');
} else {
    $menus[$r++] = array('id' => 'takepos-action-new', 'title' => '<span class="fa fa-chair paddingrightonly"></span><div class="trunc">'.$langs->trans("Place").'</div>', 'action' => 'Floors();');
}

// ══ أزرار الدفع والأساسية — دائماً في الأعلى ══

$menus[$r++] = array(
    'id'     => 'takepos-action-payment',
    'title'  => '<span class="far fa-money-bill-alt paddingrightonly"></span><div class="trunc">'.$langs->trans("TakeposUiPayment").'</div>',
    'action' => 'CloseBill();'
);

$menus[$r++] = array(
    'id'     => 'takepos-action-direct-payment',
    'title'  => '<span class="fas fa-coins paddingrightonly"></span><div class="trunc">'.$langs->trans("TakeposUiCash").'</div>',
    'action' => 'DirectPayment();'
);

$menus[$r++] = array(
    'id'     => 'takepos-action-direct-card-payment',
    'title'  => '<span class="far fa-credit-card paddingrightonly"></span><div class="trunc">'.$langs->trans("TakeposUiVisa").'</div>',
    'action' => 'DirectCardPayment();'
);

$takeposDrawerAction = 'DolibarrOpenDrawer();';
if (getDolGlobalString('TAKEPOS_PRINT_METHOD') == "takeposconnector") {
    $takeposDrawerAction = 'OpenDrawer();';
} elseif (getDolGlobalInt('TAKEPOS_PRINTER_TO_USE'.$term) > 0 || getDolGlobalString('TAKEPOS_PRINT_METHOD') == "receiptprinter") {
    $takeposDrawerAction = 'DolibarrOpenDrawer();';
}
$menus[$r++] = array(
    'id'     => 'takepos-action-open-drawer',
    'title'  => '<span class="fa fa-cash-register paddingrightonly"></span><div class="trunc">'.$tpLabelOpenDrawer.'</div>',
    'action' => $takeposDrawerAction
);

// ══ تبديل اللغة ══
$currentLang = isset($langs->defaultlang) ? $langs->defaultlang : 'ar_JO';
$isCurrentlyArabic = (strpos($currentLang, 'ar_') === 0);
if ($isCurrentlyArabic) {
    $menus[$r++] = array(
        'id'     => 'takepos-lang-switch',
        'title'  => '<span class="fa fa-language paddingrightonly"></span><div class="trunc">EN</div>',
        'action' => 'window.location.href=\''.dol_escape_js(takeposBuildLanguageSwitchUrl('en_US')).'\';'
    );
} else {
    $menus[$r++] = array(
        'id'     => 'takepos-lang-switch',
        'title'  => '<span class="fa fa-language paddingrightonly"></span><div class="trunc">ع</div>',
        'action' => 'window.location.href=\''.dol_escape_js(takeposBuildLanguageSwitchUrl('ar_JO')).'\';'
    );
}

// ══ باقي الأزرار ══

if ($canAccessTakeposReports) {
    $menus[$r++] = array('id' => 'takepos-action-reports', 'title' => '<span class="fa fa-chart-line paddingrightonly"></span><div class="trunc">'.$langs->trans("TakeposIndexReportsMenuLabel").'</div>', 'action' => 'Reports();');
}

$menus[$r++] = array('id' => 'takepos-action-hold', 'title' => '<span class="fa fa-pause-circle paddingrightonly"></span><div class="trunc">'.$langs->trans("TakeposIndexHold").'</div>', 'action' => 'HoldSale();');
$menus[$r++] = array('id' => 'takepos-action-held', 'title' => '<span class="fa fa-list-alt paddingrightonly"></span><div class="trunc">'.$langs->trans("TakeposIndexHeld").'</div>', 'action' => 'ShowHeldSales();');

if (getDolGlobalString('TAKEPOS_HIDE_HEAD_BAR')) {
    if (getDolGlobalString('TAKEPOS_CHOOSE_CONTACT')) {
        $menus[$r++] = array('title' => '<span class="far fa-building paddingrightonly"></span><div class="trunc">'.$langs->trans("Contact").'</div>', 'action' => 'Contact();');
    } else {
        $menus[$r++] = array('title' => '<span class="far fa-building paddingrightonly"></span><div class="trunc">'.$langs->trans("TakeposUiCustomer").'</div>', 'action' => 'Customer();');
    }
}
if (!getDolGlobalString('TAKEPOS_HIDE_HISTORY')) {
    $menus[$r++] = array('id' => 'takepos-action-history', 'title' => '<span class="fa fa-history paddingrightonly"></span><div class="trunc">'.$langs->trans("TakeposUiHistory").'</div>', 'action' => 'History();');
}
if ($shiftFeatureEnabled && $canOpenShiftDesk) {
    $menus[$r++] = array('id' => 'takepos-action-shift-desk', 'title' => '<span class="fa fa-user-clock paddingrightonly"></span><div class="trunc">'.$langs->trans("TakeposShortcutShiftDesk").'</div>', 'action' => 'openTakeposWorkspacePage(\'/takepos/workspace.php?key=shift_ops\', \''.dol_escape_js($langs->trans("TakeposShortcutShiftDesk")).'\');');
}
if ($shiftFeatureEnabled && $cashControlFeatureEnabled && $canRecordPaidIn) {
    $menus[$r++] = array('id' => 'takepos-action-paid-in', 'title' => '<span class="fa fa-arrow-circle-down paddingrightonly"></span><div class="trunc">'.$langs->trans("TakeposShiftPaidIn").'</div>', 'action' => 'openTakeposWorkspacePage(\'/takepos/shifts.php?movement_type=paid_in\', \''.dol_escape_js($langs->trans("TakeposShiftPaidIn")).'\');');
}
if ($shiftFeatureEnabled && $cashControlFeatureEnabled && $canRecordPaidOut) {
    $menus[$r++] = array('id' => 'takepos-action-paid-out', 'title' => '<span class="fa fa-arrow-circle-up paddingrightonly"></span><div class="trunc">'.$langs->trans("TakeposShiftPaidOut").'</div>', 'action' => 'openTakeposWorkspacePage(\'/takepos/shifts.php?movement_type=paid_out\', \''.dol_escape_js($langs->trans("TakeposShiftPaidOut")).'\');');
}
if ($exchangeFeatureEnabled && $returnsFeatureEnabled && $canOpenExchangeDesk) {
    $menus[$r++] = array('id' => 'takepos-action-exchange', 'title' => '<span class="fa fa-exchange-alt paddingrightonly"></span><div class="trunc">'.$langs->trans("TakeposShortcutExchangeDesk").'</div>', 'action' => 'openTakeposWorkspacePage(\'/takepos/workspace.php?key=exchange_ops\', \''.dol_escape_js($langs->trans("TakeposShortcutExchangeDesk")).'\');');
}
$menus[$r++] = array('id' => 'takepos-action-freezone', 'title' => '<span class="fa fa-cube paddingrightonly"></span><div class="trunc">'.$langs->trans("TakeposUiFreeTextProduct").'</div>', 'action' => 'FreeZone();');
$menus[$r++] = array('id' => 'takepos-action-reduction', 'title' => '<span class="fa fa-percent paddingrightonly"></span><div class="trunc">'.$tpLabelInvoiceDiscountShort.'</div>', 'action' => 'Reduction();');

if (!getDolGlobalString('TAKEPOS_NO_SPLIT_SALE')) {
    $menus[$r++] = array('id' => 'takepos-action-split', 'title' => '<span class="fas fa-cut paddingrightonly"></span><div class="trunc">'.$langs->trans("TakeposUiSplitSale").'</div>', 'action' => 'Split();');
}

if (getDolGlobalString('TAKEPOS_BAR_RESTAURANT')) {
    if (getDolGlobalString('TAKEPOS_ORDER_PRINTERS')) {
        $menus[$r++] = array('title' => '<span class="fa fa-blender-phone paddingrightonly"></span><div class="trunc">'.$tpLabelOrder.'</div>', 'action' => 'TakeposPrintingOrder();');
    }
}

$menus[$r++] = array('id' => 'takepos-action-calculator', 'title' => '<span class="fa fa-calculator paddingrightonly"></span><div class="trunc">'.$tpLabelCalculator.'</div>', 'action' => 'OpenCalculator();');
$menus[$r++] = array('id' => 'takepos-action-product-info', 'title' => '<span class="fa fa-info-circle paddingrightonly"></span><div class="trunc">'.$tpLabelProductInfo.'</div>', 'action' => 'ShowSelectedProductInfo();');
$menus[$r++] = array('id' => 'takepos-action-print-ticket', 'title' => '<span class="fa fa-print paddingrightonly"></span><div class="trunc">'.$tpLabelPrintTicket.'</div>', 'action' => 'PrintCurrentOrLastTicket();');

if (getDolGlobalString('TAKEPOS_BAR_RESTAURANT')) {
    if (getDolGlobalString('TAKEPOS_PRINT_METHOD') == "takeposconnector") {
        if (getDolGlobalString('TAKEPOS_PRINT_SERVER') && filter_var($conf->global->TAKEPOS_PRINT_SERVER, FILTER_VALIDATE_URL) == true) {
            $menus[$r++] = array('title' => '<span class="fa fa-receipt paddingrightonly"></span><div class="trunc">'.$tpLabelReceipt.'</div>', 'action' => 'TakeposConnector(placeid);');
        } else {
            $menus[$r++] = array('title' => '<span class="fa fa-receipt paddingrightonly"></span><div class="trunc">'.$tpLabelReceipt.'</div>', 'action' => 'TakeposPrinting(placeid);');
        }
    } elseif ((isModEnabled('receiptprinter') && getDolGlobalInt('TAKEPOS_PRINTER_TO_USE'.$term) > 0) || getDolGlobalString('TAKEPOS_PRINT_METHOD') == "receiptprinter") {
        $menus[$r++] = array('title' => '<span class="fa fa-receipt paddingrightonly"></span><div class="trunc">'.$tpLabelReceipt.'</div>', 'action' => 'DolibarrTakeposPrinting(placeid);');
    } else {
        $menus[$r++] = array('title' => '<span class="fa fa-receipt paddingrightonly"></span><div class="trunc">'.$tpLabelReceipt.'</div>', 'action' => 'Print(placeid);');
    }
    if (getDolGlobalString('TAKEPOS_PRINT_METHOD') == "takeposconnector" && getDolGlobalString('TAKEPOS_ORDER_NOTES') == 1) {
        $menus[$r++] = array('title' => '<span class="fa fa-sticky-note paddingrightonly"></span><div class="trunc">'.$tpLabelOrderNotes.'</div>', 'action' => 'TakeposOrderNotes();');
    }
    if (getDolGlobalString('TAKEPOS_SUPPLEMENTS')) {
        $menus[$r++] = array('title' => '<span class="fa fa-receipt paddingrightonly"></span><div class="trunc">'.$tpLabelProductSupplements.'</div>', 'action' => 'LoadProducts(\'supplements\');');
    }
}

$sql = "SELECT rowid, status, entity FROM ".MAIN_DB_PREFIX."pos_cash_fence WHERE";
$sql .= " entity = ".((int) $conf->entity)." AND ";
$sql .= " posnumber = ".((int) empty($_SESSION["takeposterminal"]) ? 0 : $_SESSION["takeposterminal"])." AND ";
$sql .= " date_creation > '".$db->idate(dol_get_first_hour(dol_now()))."'";
$sql .= " AND status = 0";
$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);
    if ($num) {
        $obj = $db->fetch_object($resql);
        $menus[$r++] = array('title' => '<span class="fas fa-file-invoice-dollar paddingrightonly"></span><div class="trunc">'.$tpLabelCashReport.'</div>', 'action' => 'CashReport('.$obj->rowid.');');
        if ($obj->status == 0) {
            $menus[$r++] = array('title' => '<span class="fas fa-cash-register paddingrightonly"></span><div class="trunc">'.$tpLabelCloseCashFence.'</div>', 'action' => 'CloseCashFence('.$obj->rowid.');');
        }
    }
}

$parameters = array('menus' => $menus);
$reshook = $hookmanager->executeHooks('ActionButtons', $parameters);
if ($reshook == 0) {
    if (is_array($hookmanager->resArray)) {
        foreach ($hookmanager->resArray as $resArray) {
            foreach ($resArray as $butmenu) {
                $menus[$r++] = $butmenu;
            }
        }
    }
} elseif ($reshook == 1) {
    $r = 0;
    if (is_array($hookmanager->resArray)) {
        foreach ($hookmanager->resArray as $resArray) {
            foreach ($resArray as $butmenu) {
                $menus[$r++] = $butmenu;
            }
        }
    }
}

// ── Kafo Role-Based UI Filter ─────────────────────────────────────────────────
if (!empty($user->id) && empty($user->admin)) {
    $kafoUiPermTable = MAIN_DB_PREFIX . 'takepos_role_permissions';
    $kafoTableCheck  = $db->query("SHOW TABLES LIKE '" . $db->escape($kafoUiPermTable) . "'");

    if ($kafoTableCheck && $db->num_rows($kafoTableCheck) > 0) {
        $kafoEntity = !empty($user->entity) ? (int) $user->entity : 1;

        // Load this user's assigned role
        $kafoUserRoleCode = null;
        $kafoUserRoleRes = $db->query(
            "SELECT permission_code FROM " . $kafoUiPermTable
            . " WHERE entity = " . $kafoEntity
            . " AND role_code = '__user_" . (int)$user->id . "' LIMIT 1"
        );
        if ($kafoUserRoleRes && ($kafoRoleObj = $db->fetch_object($kafoUserRoleRes))) {
            $kafoUserRoleCode = $kafoRoleObj->permission_code;
        }

        // Load the role's UI permissions (empty array if no role assigned)
        $kafoRolePerms = array();
        if ($kafoUserRoleCode !== null) {
            $kafoRolePermsRes = $db->query(
                "SELECT permission_code FROM " . $kafoUiPermTable
                . " WHERE entity = " . $kafoEntity
                . " AND role_code = '" . $db->escape($kafoUserRoleCode) . "'"
            );
            if ($kafoRolePermsRes) {
                while ($kafoPermObj = $db->fetch_object($kafoRolePermsRes)) {
                    $kafoRolePerms[] = $kafoPermObj->permission_code;
                }
            }
        }
        // If no role assigned → $kafoRolePerms stays empty → all optional buttons hidden

        // Always visible buttons (no permission required)
        $kafoAlwaysVisible = array(
            'takepos-action-new',
            'takepos-action-payment',
            'takepos-lang-switch',
            'takepos-action-delete',
        );

        // Map button IDs to required permission codes
        $kafoButtonPermMap = array(
            'takepos-action-direct-payment'      => 'ui.action.cash',
            'takepos-action-direct-card-payment' => 'ui.action.visa',
            'takepos-action-open-drawer'         => 'ui.action.open_drawer',
            'takepos-action-reports'             => 'ui.action.reports',
            'takepos-action-hold'                => 'ui.action.hold',
            'takepos-action-held'                => 'ui.action.held',
            'takepos-action-history'             => 'ui.action.history',
            'takepos-action-shift-desk'          => 'ui.action.shift_desk',
            'takepos-action-paid-in'             => 'ui.action.paid_in',
            'takepos-action-paid-out'            => 'ui.action.paid_out',
            'takepos-action-exchange'            => 'ui.action.exchange',
            'takepos-action-freezone'            => 'ui.action.freezone',
            'takepos-action-reduction'           => 'ui.action.discount',
            'takepos-action-split'               => 'ui.action.split',
            'takepos-action-calculator'          => 'ui.action.calculator',
            'takepos-action-product-info'        => 'ui.action.product_info',
            'takepos-action-print-ticket'        => 'ui.action.print_ticket',
        );

        $kafoFilteredMenus = array();
        foreach ($menus as $kafoMenu) {
            $kafoMenuId = !empty($kafoMenu['id']) ? (string) $kafoMenu['id'] : '';

            // Always visible
            if (in_array($kafoMenuId, $kafoAlwaysVisible)) {
                $kafoFilteredMenus[] = $kafoMenu;
                continue;
            }

            // Button with known ID
            if (array_key_exists($kafoMenuId, $kafoButtonPermMap)) {
                if (in_array($kafoButtonPermMap[$kafoMenuId], $kafoRolePerms)) {
                    $kafoFilteredMenus[] = $kafoMenu;
                }
                continue;
            }

            // Button with no ID — show only if user has a role
            if ($kafoMenuId === '' && $kafoUserRoleCode !== null) {
                $kafoFilteredMenus[] = $kafoMenu;
            }
        }

        $menus = $kafoFilteredMenus;
        $r = count($menus);
    }
}
// ── End Kafo Role-Based UI Filter ─────────────────────────────────────────────

if ($r % 3 == 2) {
    $menus[$r++] = array('title' => '', 'style' => 'visibility: hidden;');
}

if (getDolGlobalString('TAKEPOS_HIDE_HEAD_BAR')) {
    $menus[$r++] = array('title' => '<span class="fa fa-sign-out-alt pictofixedwidth"></span><div class="trunc">'.$tpLabelLogout.'</div>', 'action' => 'window.location.href=\''.DOL_URL_ROOT.'/user/logout.php?token='.newToken().'\';');
}

if (getDolGlobalString('TAKEPOS_WEIGHING_SCALE')) {
    $menus[$r++] = array('title' => '<span class="fa fa-balance-scale pictofixedwidth"></span><div class="trunc">'.$langs->trans("WeighingScale").'</div>', 'action' => 'WeighingScale();');
}