<?php
/**
 * TakePOS - Roles & Permissions Admin Page (Redesigned)
 */

require '../../main.inc.php';
require_once __DIR__ . '/../lib/takepos_help.php';
require_once __DIR__ . '/../lib/takepos_lang.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/takepos.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once __DIR__ . '/../class/TakeposAccess.class.php';
require_once __DIR__ . '/../class/TakeposUserAccess.class.php';

$langs->loadLangs(array('admin', 'takepos', 'takeposcustom@takepos'));

if (empty($user->id)) { accessforbidden(); }
restrictedArea($user, 'takepos', 0, '');
TakeposAccess::requireAdminAccess($db, $user, 'takepos.users.manage', 'takepos.action.users_manage');

$db->query("CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "takepos_roles (
    rowid INT AUTO_INCREMENT PRIMARY KEY, entity INT NOT NULL DEFAULT 1,
    role_code VARCHAR(64) NOT NULL, label VARCHAR(128) NOT NULL, description TEXT,
    datec DATETIME, tms DATETIME,
    UNIQUE KEY uk_takepos_roles (entity, role_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$db->query("CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "takepos_role_permissions (
    rowid INT AUTO_INCREMENT PRIMARY KEY, entity INT NOT NULL DEFAULT 1,
    role_code VARCHAR(64) NOT NULL, permission_code VARCHAR(128) NOT NULL,
    UNIQUE KEY uk_takepos_role_perm (entity, role_code, permission_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$posUiActions = array(
    'Action Buttons' => array(
        'ui.action.new'         => array('label' => 'New Sale',            'icon' => 'fa-plus-circle',      'key' => 'F1'),
        'ui.action.payment'     => array('label' => 'Payment',             'icon' => 'fa-money-bill-alt',   'key' => 'F2'),
        'ui.action.cash'        => array('label' => 'Direct Cash',         'icon' => 'fa-coins',            'key' => 'F3'),
        'ui.action.visa'        => array('label' => 'Card Payment',        'icon' => 'fa-credit-card',      'key' => 'F4'),
        'ui.action.open_drawer' => array('label' => 'Open Drawer',         'icon' => 'fa-cash-register',    'key' => 'F5'),
        'ui.action.reports'     => array('label' => 'Reports',             'icon' => 'fa-chart-line',       'key' => 'F7'),
        'ui.action.hold'        => array('label' => 'Hold Sale',           'icon' => 'fa-pause-circle',     'key' => 'F8'),
        'ui.action.held'        => array('label' => 'Held Sales',          'icon' => 'fa-list-alt',         'key' => 'F9'),
        'ui.action.history'     => array('label' => 'History',             'icon' => 'fa-history',          'key' => 'F10'),
        'ui.action.shift_desk'  => array('label' => 'Shift Desk',          'icon' => 'fa-user-clock',       'key' => 'F11'),
        'ui.action.paid_in'     => array('label' => 'Paid In',             'icon' => 'fa-arrow-circle-down','key' => 'F12'),
        'ui.action.paid_out'    => array('label' => 'Paid Out',            'icon' => 'fa-arrow-circle-up',  'key' => ''),
        'ui.action.exchange'    => array('label' => 'Exchange Desk',       'icon' => 'fa-exchange-alt',     'key' => ''),
        'ui.action.freezone'    => array('label' => 'Free-text Product',   'icon' => 'fa-cube',             'key' => ''),
        'ui.action.discount'    => array('label' => 'Invoice Discount',    'icon' => 'fa-percent',          'key' => ''),
        'ui.action.split'       => array('label' => 'Split Sale',          'icon' => 'fa-cut',              'key' => ''),
        'ui.action.calculator'  => array('label' => 'Calculator',          'icon' => 'fa-calculator',       'key' => ''),
        'ui.action.product_info'=> array('label' => 'Product Info',        'icon' => 'fa-info-circle',      'key' => ''),
        'ui.action.print_ticket'=> array('label' => 'Print Ticket',        'icon' => 'fa-print',            'key' => ''),
    ),
    'Catalog & Inventory' => array(
        'ui.shortcut.add_product'          => array('label' => 'Add Product',        'icon' => 'fa-box',             'key' => ''),
        'ui.shortcut.manage_products'      => array('label' => 'Manage Products',    'icon' => 'fa-th-list',         'key' => ''),
        'ui.shortcut.barcodes'             => array('label' => 'Product Barcodes',   'icon' => 'fa-barcode',         'key' => ''),
        'ui.shortcut.tax_rates'            => array('label' => 'Tax Rates',          'icon' => 'fa-percent',         'key' => ''),
        'ui.shortcut.stock_overview'       => array('label' => 'Stock Overview',     'icon' => 'fa-warehouse',       'key' => ''),
        'ui.shortcut.stock_adjustments'    => array('label' => 'Stock Adjustments',  'icon' => 'fa-clipboard-check', 'key' => ''),
        'ui.shortcut.all_branches_stock'   => array('label' => 'All Branches Stock', 'icon' => 'fa-layer-group',     'key' => ''),
        'ui.shortcut.stock_transfer'       => array('label' => 'Stock Transfer',     'icon' => 'fa-exchange-alt',    'key' => ''),
        'ui.shortcut.stock_reconciliation' => array('label' => 'Stock Reconciliation','icon'=> 'fa-balance-scale',   'key' => ''),
        'ui.shortcut.inventory_count'      => array('label' => 'Inventory Count',    'icon' => 'fa-clipboard-check', 'key' => ''),
        'ui.shortcut.piece_box'            => array('label' => 'Piece / Box Variants','icon'=> 'fa-boxes',           'key' => ''),
        'ui.shortcut.cheques'              => array('label' => 'Cheques',            'icon' => 'fa-money-check-alt', 'key' => ''),
    ),
    'Sales Operations' => array(
        'ui.shortcut.shift_desk'     => array('label' => 'Shift Desk',     'icon' => 'fa-user-clock',      'key' => ''),
        'ui.shortcut.refund_desk'    => array('label' => 'Refund Desk',    'icon' => 'fa-undo',            'key' => ''),
        'ui.shortcut.exchange_desk'  => array('label' => 'Exchange Desk',  'icon' => 'fa-exchange-alt',    'key' => ''),
        'ui.shortcut.loyalty_desk'   => array('label' => 'Loyalty Desk',   'icon' => 'fa-id-card',         'key' => ''),
        'ui.shortcut.expenses'       => array('label' => 'Expenses',       'icon' => 'fa-receipt',         'key' => ''),
        'ui.shortcut.expense_ledger' => array('label' => 'Expense Ledger', 'icon' => 'fa-book',            'key' => ''),
        'ui.shortcut.purchases'      => array('label' => 'Purchases',      'icon' => 'fa-truck-loading',   'key' => ''),
    ),
    'Analytics' => array(
        'ui.shortcut.kpi_dashboard' => array('label' => 'KPI Dashboard', 'icon' => 'fa-chart-line', 'key' => ''),
    ),
);

$currentEntity = !empty($user->entity) ? (int) $user->entity : 1;
$action   = GETPOST('action', 'aZ09');
$mode     = GETPOST('mode', 'aZ09');
$roleCode = GETPOST('role_code', 'alphanohtml');
$mesg     = '';
$mesgType = 'mesgs';
$usersPerPage = 10;
$usersPage    = max(1, GETPOSTINT('upage'));

function mapUiActionToSaasPermission($uiCode) {
    $map = array(
        'ui.action.history'                => 'takepos.refund.view',
        'ui.action.reports'                => 'takepos.action.reports_view',
        'ui.action.shift_desk'             => 'takepos.shift.open',
        'ui.action.paid_in'                => 'takepos.cash.paidin',
        'ui.action.paid_out'               => 'takepos.cash.paidout',
        'ui.action.exchange'               => 'takepos.exchange.process',
        'ui.action.discount'               => 'takepos.action.discount',
        'ui.shortcut.refund_desk'          => 'takepos.refund.full',
        'ui.shortcut.exchange_desk'        => 'takepos.exchange.process',
        'ui.shortcut.loyalty_desk'         => 'takepos.loyalty.view',
        'ui.shortcut.expenses'             => 'takepos.expense.create',
        'ui.shortcut.expense_ledger'       => 'takepos.expense.read',
        'ui.shortcut.purchases'            => 'takepos.purchase.create',
        'ui.shortcut.kpi_dashboard'        => 'takepos.analytics.view',
        'ui.shortcut.cheques'              => 'takepos.cheque.read',
        'ui.shortcut.shift_desk'           => 'takepos.shift.open',
    );
    return isset($map[$uiCode]) ? $map[$uiCode] : null;
}

/**
 * Map UI permission codes to Dolibarr rights IDs.
 * These are granted automatically when a role is assigned to a user.
 */
function getDolibarrRightsForUiPermission($uiCode) {
    // right IDs from llx_rights_def
    $map = array(
        // Products
        'ui.shortcut.add_product'          => array(31, 32),          // produit.lire + produit.creer
        'ui.shortcut.manage_products'      => array(31, 32),          // produit.lire + produit.creer
        'ui.shortcut.barcodes'             => array(31),              // produit.lire
        'ui.shortcut.piece_box'            => array(31, 32),          // produit.lire + produit.creer
        // Stock
        'ui.shortcut.stock_overview'       => array(1001, 1004),      // stock.lire + stock.mouvement.lire
        'ui.shortcut.stock_adjustments'    => array(1001, 1002, 1005),// stock.lire + creer + mouvement.creer
        'ui.shortcut.all_branches_stock'   => array(1001, 1004),      // stock.lire + mouvement.lire
        'ui.shortcut.stock_transfer'       => array(1001, 1002, 1004, 1005), // stock full
        'ui.shortcut.stock_reconciliation' => array(1001, 1002, 1011, 1012), // stock + inventory
        'ui.shortcut.inventory_count'      => array(1001, 1011, 1012),// stock.lire + inventory
        // Categories
        'ui.shortcut.tax_rates'            => array(241, 242),        // categorie.lire + creer
        // Invoices / History
        'ui.action.history'                => array(11),              // facture.lire
        'ui.action.payment'                => array(11, 12, 16),      // facture.lire + creer + paiement
        'ui.action.cash'                   => array(11, 12, 16),      // facture.lire + creer + paiement
        'ui.action.visa'                   => array(11, 12, 16),      // facture.lire + creer + paiement
        // Reports
        'ui.action.reports'                => array(11, 81),          // facture.lire + commande.lire
        // Refunds / Exchange
        'ui.shortcut.refund_desk'          => array(11, 12, 16, 19),  // facture full
        'ui.shortcut.exchange_desk'        => array(11, 12, 16),      // facture.lire + creer + paiement
        'ui.action.exchange'               => array(11, 12, 16),
        // Expenses
        'ui.shortcut.expenses'             => array(11, 12),          // facture.lire + creer
        'ui.shortcut.expense_ledger'       => array(11),              // facture.lire
        // Purchases
        'ui.shortcut.purchases'            => array(1181, 1182, 1183),// fournisseur.lire + commande.lire + creer
        // Cheques
        'ui.shortcut.cheques'              => array(31, 11),          // produit.lire + facture.lire
        // Loyalty / CRM
        'ui.shortcut.loyalty_desk'         => array(121, 281),        // societe.lire + contact.lire
        // Shift / Cash (no extra Dolibarr rights needed - handled by saas)
        'ui.action.shift_desk'             => array(),
        'ui.shortcut.shift_desk'           => array(),
        'ui.action.paid_in'                => array(),
        'ui.action.paid_out'               => array(),
        'ui.action.open_drawer'            => array(),
        'ui.action.hold'                   => array(),
        'ui.action.held'                   => array(),
        'ui.action.new'                    => array(11, 12),          // facture.lire + creer
        'ui.action.freezone'               => array(11, 12),
        'ui.action.discount'               => array(11, 12),
        'ui.action.split'                  => array(11, 12),
        'ui.action.calculator'             => array(),
        'ui.action.product_info'           => array(31),
        'ui.action.print_ticket'           => array(11),
        // Analytics
        'ui.shortcut.kpi_dashboard'        => array(11, 81, 1001),    // facture + commande + stock lire
    );
    return isset($map[$uiCode]) ? $map[$uiCode] : array();
}

/**
 * Grant Dolibarr native rights to a user based on their UI permissions.
 * Only grants — never removes existing rights.
 */
function grantDolibarrRightsForRole($db, $userId, $entity, $uiPermissions) {
    if (empty($uiPermissions) || !is_array($uiPermissions)) return;

    // Collect all right IDs needed
    $rightIds = array();
    foreach ($uiPermissions as $uiCode) {
        $ids = getDolibarrRightsForUiPermission($uiCode);
        foreach ($ids as $id) {
            $rightIds[(int)$id] = true;
        }
    }

    if (empty($rightIds)) return;

    // Always include basic rights needed for POS to function
    // 11=facture.lire, 12=facture.creer, 16=facture.paiement, 14=facture.validate
    // 31=produit.lire, 32=produit.creer, 81=commande.lire, 121=societe.lire
    // 241=categorie.lire, 1001=stock.lire, 1004=stock.mouvement.lire
    $baseRights = array(11, 12, 14, 16, 31, 81, 121, 241, 1001, 1004);
    foreach ($baseRights as $id) { $rightIds[$id] = true; }

    foreach (array_keys($rightIds) as $rightId) {
        // Check if already exists
        $checkSql = "SELECT COUNT(*) AS cnt FROM " . MAIN_DB_PREFIX . "user_rights"
            . " WHERE fk_user = " . (int)$userId
            . " AND fk_id = " . (int)$rightId
            . " AND entity = " . (int)$entity;
        $resCheck = $db->query($checkSql);
        if ($resCheck && ($obj = $db->fetch_object($resCheck)) && (int)$obj->cnt > 0) continue;

        $db->query("INSERT INTO " . MAIN_DB_PREFIX . "user_rights (entity, fk_user, fk_id)"
            . " VALUES (" . (int)$entity . ", " . (int)$userId . ", " . (int)$rightId . ")");
    }
}

try {
    if (!empty($action) && GETPOST('token') !== $_SESSION['newtoken']) {
        throw new Exception('Invalid security token.');
    }

    if ($action === 'create_role') {
        $newCode  = strtolower(trim(GETPOST('new_role_code', 'alphanohtml')));
        $newLabel = trim(GETPOST('new_label', 'none'));
        $newDesc  = trim(GETPOST('new_description', 'none'));
        if ($newCode === '' || $newLabel === '') throw new Exception('Role code and label are required.');
        if (!preg_match('/^[a-z0-9_]+$/', $newCode)) throw new Exception('Role code must be lowercase letters, numbers and underscores only.');
        $now = date('Y-m-d H:i:s');
        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "takepos_roles (entity,role_code,label,description,datec,tms) VALUES ($currentEntity,'".$db->escape($newCode)."','".$db->escape($newLabel)."','".$db->escape($newDesc)."','$now','$now') ON DUPLICATE KEY UPDATE label=VALUES(label),description=VALUES(description),tms=VALUES(tms)";
        if (!$db->query($sql)) throw new Exception('Failed to save role: ' . $db->lasterror());
        $db->query("DELETE FROM " . MAIN_DB_PREFIX . "takepos_role_permissions WHERE entity=$currentEntity AND role_code='".$db->escape($newCode)."'");
        $perms = GETPOST('ui_permissions', 'array');
        if (is_array($perms)) {
            foreach ($perms as $perm) {
                $perm = trim((string)$perm);
                $allCodes = array();
                foreach ($posUiActions as $grp) { foreach ($grp as $k => $v) { $allCodes[] = $k; } }
                if ($perm !== '' && in_array($perm, $allCodes)) {
                    $db->query("INSERT IGNORE INTO ".MAIN_DB_PREFIX."takepos_role_permissions (entity,role_code,permission_code) VALUES ($currentEntity,'".$db->escape($newCode)."','".$db->escape($perm)."')");
                }
            }
        }
        $mesg = 'Role "'.htmlspecialchars($newLabel).'" saved successfully.';
        $mode = 'edit'; $roleCode = $newCode;
    }

    if ($action === 'delete_role') {
        $delCode = GETPOST('del_role_code', 'alphanohtml');
        $db->query("DELETE FROM ".MAIN_DB_PREFIX."takepos_roles WHERE entity=$currentEntity AND role_code='".$db->escape($delCode)."'");
        $db->query("DELETE FROM ".MAIN_DB_PREFIX."takepos_role_permissions WHERE entity=$currentEntity AND role_code='".$db->escape($delCode)."'");
        $mesg = 'Role deleted.';
    }

    if ($action === 'assign_role_to_user') {
        $targetUserId = GETPOSTINT('target_user_id');
        $assignRole   = GETPOST('assign_role_code', 'alphanohtml');
        $resql = $db->query("SELECT permission_code FROM ".MAIN_DB_PREFIX."takepos_role_permissions WHERE entity=$currentEntity AND role_code='".$db->escape($assignRole)."'");
        $rolePerms = array();
        if ($resql) { while ($obj = $db->fetch_object($resql)) { $rolePerms[] = $obj->permission_code; } }
        $saasPerms = array('takepos.use','takepos.shift.open','takepos.shift.close','takepos.cash.paidin','takepos.cash.paidout');
        foreach ($rolePerms as $rp) { $mapped = mapUiActionToSaasPermission($rp); if ($mapped && !in_array($mapped, $saasPerms)) { $saasPerms[] = $mapped; } }
        TakeposUserAccess::saveUserPermissionCodes($db, $targetUserId, $currentEntity, $saasPerms);

        // Grant Dolibarr native rights based on UI permissions in the role
        grantDolibarrRightsForRole($db, $targetUserId, $currentEntity, $rolePerms);

        // Save role assignment: __user_{id} -> role_code
        $db->query("DELETE FROM " . MAIN_DB_PREFIX . "takepos_role_permissions WHERE entity=$currentEntity AND role_code='__user_" . (int)$targetUserId . "'");
        $db->query("INSERT INTO " . MAIN_DB_PREFIX . "takepos_role_permissions (entity, role_code, permission_code) VALUES ($currentEntity, '__user_" . (int)$targetUserId . "', '" . $db->escape($assignRole) . "')");

        $mesg = 'Role assigned to user successfully.';
    }
} catch (Throwable $e) {
    $mesg = $e->getMessage(); $mesgType = 'errors';
}

$roles = array();
$resql = $db->query("SELECT role_code, label, description FROM ".MAIN_DB_PREFIX."takepos_roles WHERE entity=$currentEntity ORDER BY label ASC");
if ($resql) { while ($obj = $db->fetch_object($resql)) { $roles[$obj->role_code] = $obj; } }

$editRolePerms = array(); $editRoleLabel = ''; $editRoleDesc = '';
if ($mode === 'edit' && $roleCode !== '') {
    $resql2 = $db->query("SELECT permission_code FROM ".MAIN_DB_PREFIX."takepos_role_permissions WHERE entity=$currentEntity AND role_code='".$db->escape($roleCode)."'");
    if ($resql2) { while ($obj = $db->fetch_object($resql2)) { $editRolePerms[] = $obj->permission_code; } }
    if (isset($roles[$roleCode])) { $editRoleLabel = $roles[$roleCode]->label; $editRoleDesc = $roles[$roleCode]->description; }
}

$allUsers = array();
$usersOffset  = ($usersPage - 1) * $usersPerPage;
$resTotalUsers = $db->query("SELECT COUNT(*) AS cnt FROM ".MAIN_DB_PREFIX."user WHERE statut=1 AND admin=0");
$totalUsers   = 0;
if ($resTotalUsers && ($oTU = $db->fetch_object($resTotalUsers))) { $totalUsers = (int)$oTU->cnt; }
$totalUsersPages = max(1, (int)ceil($totalUsers / $usersPerPage));
$usersPage = min($usersPage, $totalUsersPages);
$usersOffset = ($usersPage - 1) * $usersPerPage;
$resUsers = $db->query("SELECT rowid, login, firstname, lastname FROM ".MAIN_DB_PREFIX."user WHERE statut=1 AND admin=0 ORDER BY login ASC LIMIT $usersPerPage OFFSET $usersOffset");
if ($resUsers) { while ($obj = $db->fetch_object($resUsers)) { $allUsers[] = $obj; } }

// Load current role assignment per user
$userRoles = array();
foreach ($allUsers as $u) {
    $rRes = $db->query("SELECT permission_code FROM ".MAIN_DB_PREFIX."takepos_role_permissions WHERE entity=$currentEntity AND role_code='__user_".(int)$u->rowid."' LIMIT 1");
    if ($rRes && ($rObj = $db->fetch_object($rRes))) {
        $userRoles[(int)$u->rowid] = $rObj->permission_code;
    }
}

// ── Output ────────────────────────────────────────────────────────────────────
$head = takepos_admin_prepare_head();
llxHeader('', 'TakePOS - Role Management');
print dol_get_fiche_head($head, 'roles', 'TakePOS', -1, 'cash-register');

if ($mesg !== '') { setEventMessages($mesg, null, $mesgType); }
?>
    <style>
        .kafo-roles-wrap { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; max-width: 1100px; }

        /* Top bar */
        .kafo-topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; }
        .kafo-topbar h2 { margin:0; font-size:20px; font-weight:600; color:#1e293b; }
        .kafo-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:7px; font-size:13px; font-weight:500; cursor:pointer; border:none; text-decoration:none; transition:all .15s; }
        .kafo-btn-primary { background:#4f46e5; color:#fff; }
        .kafo-btn-primary:hover { background:#4338ca; color:#fff; }
        .kafo-btn-secondary { background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; }
        .kafo-btn-secondary:hover { background:#e2e8f0; color:#1e293b; }
        .kafo-btn-danger { background:#fee2e2; color:#dc2626; border:1px solid #fecaca; }
        .kafo-btn-danger:hover { background:#fecaca; }
        .kafo-btn-sm { padding:5px 11px; font-size:12px; }

        /* Role cards list */
        .kafo-roles-grid { display:grid; gap:12px; margin-bottom:32px; }
        .kafo-role-card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:16px 20px; display:flex; align-items:center; justify-content:space-between; transition:box-shadow .15s; }
        .kafo-role-card:hover { box-shadow:0 2px 8px rgba(0,0,0,.08); }
        .kafo-role-info { display:flex; flex-direction:column; gap:3px; }
        .kafo-role-name { font-size:15px; font-weight:600; color:#1e293b; }
        .kafo-role-code { font-size:11px; color:#94a3b8; font-family:monospace; }
        .kafo-role-desc { font-size:12px; color:#64748b; }
        .kafo-role-badge { background:#ede9fe; color:#6d28d9; border-radius:20px; padding:3px 10px; font-size:11px; font-weight:500; }
        .kafo-role-actions { display:flex; gap:8px; align-items:center; }
        .kafo-empty { text-align:center; padding:48px 20px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:10px; color:#94a3b8; }
        .kafo-empty i { font-size:32px; margin-bottom:12px; display:block; }

        /* Form */
        .kafo-form-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:24px; margin-bottom:24px; }
        .kafo-form-card h3 { margin:0 0 20px; font-size:16px; font-weight:600; color:#1e293b; border-bottom:1px solid #f1f5f9; padding-bottom:12px; }
        .kafo-fields { display:grid; grid-template-columns:1fr 1fr 2fr; gap:16px; margin-bottom:24px; }
        .kafo-field label { display:block; font-size:12px; font-weight:600; color:#64748b; margin-bottom:6px; text-transform:uppercase; letter-spacing:.04em; }
        .kafo-field input { width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:13px; color:#1e293b; box-sizing:border-box; outline:none; transition:border .15s; }
        .kafo-field input:focus { border-color:#4f46e5; box-shadow:0 0 0 3px rgba(79,70,229,.1); }
        .kafo-field input[readonly] { background:#f8fafc; color:#64748b; cursor:default; }

        /* Permission groups */
        .kafo-perm-section { margin-bottom:24px; }
        .kafo-perm-section-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
        .kafo-perm-section-title { font-size:13px; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:.05em; display:flex; align-items:center; gap:8px; }
        .kafo-perm-section-title::before { content:''; display:inline-block; width:3px; height:14px; background:#4f46e5; border-radius:2px; }
        .kafo-perm-select-all { font-size:11px; color:#4f46e5; cursor:pointer; text-decoration:none; }
        .kafo-perm-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(180px,1fr)); gap:8px; }
        .kafo-perm-item { position:relative; }
        .kafo-perm-item input[type=checkbox] { position:absolute; opacity:0; width:0; height:0; }
        .kafo-perm-item label { display:flex; align-items:center; gap:8px; padding:8px 12px; border:1px solid #e2e8f0; border-radius:8px; cursor:pointer; font-size:12px; color:#475569; background:#f8fafc; transition:all .12s; user-select:none; }
        .kafo-perm-item label:hover { border-color:#a5b4fc; background:#ede9fe; color:#4f46e5; }
        .kafo-perm-item input:checked + label { border-color:#4f46e5; background:#ede9fe; color:#4f46e5; font-weight:500; }
        .kafo-perm-item label .kafo-perm-icon { width:22px; height:22px; display:flex; align-items:center; justify-content:center; background:#fff; border-radius:5px; font-size:11px; flex-shrink:0; }
        .kafo-perm-item input:checked + label .kafo-perm-icon { background:#4f46e5; color:#fff; }
        .kafo-perm-key { font-size:9px; background:#e2e8f0; border-radius:3px; padding:1px 4px; margin-left:auto; flex-shrink:0; }
        .kafo-perm-item input:checked + label .kafo-perm-key { background:#a5b4fc; }

        /* Assign section */
        .kafo-assign-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:24px; }
        .kafo-assign-card h3 { margin:0 0 16px; font-size:16px; font-weight:600; color:#1e293b; border-bottom:1px solid #f1f5f9; padding-bottom:12px; }
        .kafo-assign-row { display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap; }
        .kafo-assign-field { display:flex; flex-direction:column; gap:6px; min-width:200px; }
        .kafo-assign-field label { font-size:12px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.04em; }
        .kafo-assign-field select { padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:13px; color:#1e293b; background:#fff; outline:none; }
        .kafo-assign-field select:focus { border-color:#4f46e5; }

        /* User assignments table */
        .kafo-user-table-wrap { overflow-x:auto; }
        .kafo-user-table { width:100%; border-collapse:collapse; }
        .kafo-user-table thead th { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:#94a3b8; padding:8px 16px; text-align:left; border-bottom:1px solid #f1f5f9; }
        .kafo-user-table tbody tr { border-bottom:1px solid #f8fafc; transition:background .1s; }
        .kafo-user-table tbody tr:hover { background:#f8fafc; }
        .kafo-user-table tbody td { padding:12px 16px; vertical-align:middle; }
        .kafo-user-cell { display:flex; align-items:center; gap:10px; }
        .kafo-user-avatar { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,#6366f1,#8b5cf6); color:#fff; font-size:12px; font-weight:600; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .kafo-user-login { font-size:13px; font-weight:500; color:#1e293b; }
        .kafo-user-name { font-size:11px; color:#94a3b8; }
        .kafo-role-pill { background:#ede9fe; color:#6d28d9; border-radius:20px; padding:3px 12px; font-size:12px; font-weight:500; }
        .kafo-no-role { color:#cbd5e1; font-size:12px; font-style:italic; }
        .kafo-inline-select { padding:6px 10px; border:1px solid #e2e8f0; border-radius:7px; font-size:12px; color:#1e293b; background:#fff; outline:none; min-width:140px; }
        .kafo-inline-select:focus { border-color:#4f46e5; }

        /* Pagination */
        .kafo-pagination { display:flex; align-items:center; justify-content:space-between; padding:12px 16px; border-top:1px solid #f1f5f9; margin-top:0; }
        .kafo-pagination-info { font-size:12px; color:#94a3b8; }
        .kafo-pagination-btns { display:flex; gap:4px; }
        .kafo-pagination-btns a, .kafo-pagination-btns span { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:6px; font-size:12px; font-weight:500; text-decoration:none; border:1px solid #e2e8f0; color:#475569; background:#fff; }
        .kafo-pagination-btns a:hover { background:#ede9fe; border-color:#a5b4fc; color:#4f46e5; }
        .kafo-pagination-btns span.active { background:#4f46e5; color:#fff; border-color:#4f46e5; }
        .kafo-pagination-btns span.disabled { color:#cbd5e1; cursor:default; }
    </style>

    <div class="kafo-roles-wrap">

        <?php if ($mode === 'create' || $mode === 'edit'): ?>
            <?php $isEdit = ($mode === 'edit' && $roleCode !== ''); ?>

            <div class="kafo-topbar">
                <h2><?= $isEdit ? '✏️ Edit Role: ' . htmlspecialchars($editRoleLabel) : '➕ Create New Role' ?></h2>
                <a href="<?= $_SERVER['PHP_SELF'] ?>" class="kafo-btn kafo-btn-secondary"><i class="fa fa-arrow-left"></i> Back to Roles</a>
            </div>

            <form method="POST" action="<?= $_SERVER['PHP_SELF'] . ($isEdit ? '?mode=edit&role_code='.urlencode($roleCode) : '?mode=create') ?>">
                <input type="hidden" name="token" value="<?= newToken() ?>">
                <input type="hidden" name="action" value="create_role">

                <div class="kafo-form-card">
                    <h3>Role Details</h3>
                    <div class="kafo-fields">
                        <div class="kafo-field">
                            <label>Role Code</label>
                            <?php if ($isEdit): ?>
                                <input type="text" value="<?= htmlspecialchars($roleCode) ?>" readonly>
                                <input type="hidden" name="new_role_code" value="<?= htmlspecialchars($roleCode) ?>">
                            <?php else: ?>
                                <input type="text" name="new_role_code" placeholder="e.g. cashier" required>
                            <?php endif; ?>
                        </div>
                        <div class="kafo-field">
                            <label>Label</label>
                            <input type="text" name="new_label" value="<?= htmlspecialchars($editRoleLabel) ?>" placeholder="e.g. Cashier" required>
                        </div>
                        <div class="kafo-field">
                            <label>Description</label>
                            <input type="text" name="new_description" value="<?= htmlspecialchars($editRoleDesc) ?>" placeholder="Optional description">
                        </div>
                    </div>

                    <h3>POS Permissions</h3>
                    <?php foreach ($posUiActions as $groupName => $groupPerms): ?>
                        <div class="kafo-perm-section">
                            <div class="kafo-perm-section-header">
                                <span class="kafo-perm-section-title"><?= htmlspecialchars($groupName) ?></span>
                                <a href="#" class="kafo-perm-select-all" onclick="toggleGroup(this,'<?= md5($groupName) ?>');return false;">Select all</a>
                            </div>
                            <div class="kafo-perm-grid" id="grp_<?= md5($groupName) ?>">
                                <?php foreach ($groupPerms as $pCode => $pInfo): ?>
                                    <?php $checked = in_array($pCode, $editRolePerms, true); $uid = 'p_'.md5($pCode); ?>
                                    <div class="kafo-perm-item">
                                        <input type="checkbox" id="<?= $uid ?>" name="ui_permissions[]" value="<?= htmlspecialchars($pCode) ?>" <?= $checked ? 'checked' : '' ?>>
                                        <label for="<?= $uid ?>">
                                            <span class="kafo-perm-icon"><i class="fa <?= htmlspecialchars($pInfo['icon']) ?>"></i></span>
                                            <?= htmlspecialchars($pInfo['label']) ?>
                                            <?php if ($pInfo['key']): ?><span class="kafo-perm-key"><?= htmlspecialchars($pInfo['key']) ?></span><?php endif; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div style="margin-top:24px;display:flex;gap:12px;">
                        <button type="submit" class="kafo-btn kafo-btn-primary">
                            <i class="fa fa-save"></i> <?= $isEdit ? 'Update Role' : 'Create Role' ?>
                        </button>
                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="kafo-btn kafo-btn-secondary">Cancel</a>
                    </div>
                </div>
            </form>

        <?php else: ?>

            <div class="kafo-topbar">
                <h2>Roles & Permissions</h2>
                <a href="<?= $_SERVER['PHP_SELF'] ?>?mode=create" class="kafo-btn kafo-btn-primary"><i class="fa fa-plus"></i> Create New Role</a>
            </div>

            <?php if (empty($roles)): ?>
                <div class="kafo-empty">
                    <i class="fa fa-shield-alt"></i>
                    No roles created yet.<br>
                    <a href="<?= $_SERVER['PHP_SELF'] ?>?mode=create" style="color:#4f46e5;margin-top:8px;display:inline-block;">Create your first role →</a>
                </div>
            <?php else: ?>
                <div class="kafo-roles-grid">
                    <?php foreach ($roles as $rCode => $rObj):
                        $resP = $db->query("SELECT COUNT(*) AS cnt FROM ".MAIN_DB_PREFIX."takepos_role_permissions WHERE entity=$currentEntity AND role_code='".$db->escape($rCode)."'");
                        $pCount = 0;
                        if ($resP && ($objP = $db->fetch_object($resP))) { $pCount = (int)$objP->cnt; }
                        ?>
                        <div class="kafo-role-card">
                            <div class="kafo-role-info">
                                <span class="kafo-role-name"><?= htmlspecialchars($rObj->label) ?></span>
                                <span class="kafo-role-code"><?= htmlspecialchars($rCode) ?></span>
                                <?php if ($rObj->description): ?><span class="kafo-role-desc"><?= htmlspecialchars($rObj->description) ?></span><?php endif; ?>
                            </div>
                            <div class="kafo-role-actions">
                                <span class="kafo-role-badge"><i class="fa fa-key"></i> <?= $pCount ?> permissions</span>
                                <a href="<?= $_SERVER['PHP_SELF'] ?>?mode=edit&role_code=<?= urlencode($rCode) ?>" class="kafo-btn kafo-btn-secondary kafo-btn-sm"><i class="fa fa-edit"></i> Edit</a>
                                <form method="POST" style="margin:0" onsubmit="return confirm('Delete role <?= htmlspecialchars($rObj->label) ?>?');">
                                    <input type="hidden" name="token" value="<?= newToken() ?>">
                                    <input type="hidden" name="action" value="delete_role">
                                    <input type="hidden" name="del_role_code" value="<?= htmlspecialchars($rCode) ?>">
                                    <button type="submit" class="kafo-btn kafo-btn-danger kafo-btn-sm"><i class="fa fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($roles) && !empty($allUsers)): ?>
                <div class="kafo-assign-card">
                    <h3><i class="fa fa-users" style="color:#4f46e5;margin-right:8px;"></i>User Role Assignments</h3>
                    <div class="kafo-user-table-wrap">
                        <table class="kafo-user-table">
                            <thead>
                            <tr>
                                <th>User</th>
                                <th>Current Role</th>
                                <th>Change Role</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($allUsers as $u):
                                $uName = trim($u->firstname . ' ' . $u->lastname);
                                $currentRole = isset($userRoles[(int)$u->rowid]) ? $userRoles[(int)$u->rowid] : null;
                                $currentRoleLabel = ($currentRole && isset($roles[$currentRole])) ? $roles[$currentRole]->label : null;
                                ?>
                                <tr>
                                    <td>
                                        <div class="kafo-user-cell">
                                            <div class="kafo-user-avatar"><?= strtoupper(substr($u->login, 0, 2)) ?></div>
                                            <div>
                                                <div class="kafo-user-login"><?= htmlspecialchars($u->login) ?></div>
                                                <?php if ($uName): ?><div class="kafo-user-name"><?= htmlspecialchars($uName) ?></div><?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($currentRoleLabel): ?>
                                            <span class="kafo-role-pill"><?= htmlspecialchars($currentRoleLabel) ?></span>
                                        <?php else: ?>
                                            <span class="kafo-no-role">No role assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" style="margin:0;display:flex;gap:8px;align-items:center;">
                                            <input type="hidden" name="token" value="<?= newToken() ?>">
                                            <input type="hidden" name="action" value="assign_role_to_user">
                                            <input type="hidden" name="target_user_id" value="<?= (int)$u->rowid ?>">
                                            <select name="assign_role_code" class="kafo-inline-select">
                                                <?php foreach ($roles as $rCode => $rObj): ?>
                                                    <option value="<?= htmlspecialchars($rCode) ?>" <?= ($currentRole === $rCode ? 'selected' : '') ?>>
                                                        <?= htmlspecialchars($rObj->label) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="kafo-btn kafo-btn-primary kafo-btn-sm"><i class="fa fa-check"></i> Apply</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($totalUsersPages > 1): ?>
                        <div class="kafo-pagination">
                            <div class="kafo-pagination-info">
                                Showing <?= $usersOffset + 1 ?>–<?= min($usersOffset + $usersPerPage, $totalUsers) ?> of <?= $totalUsers ?> users
                            </div>
                            <div class="kafo-pagination-btns">
                                <?php
                                $baseUrl = $_SERVER['PHP_SELF'] . '?';
                                $params = $_GET;
                                // Prev
                                if ($usersPage > 1):
                                    $params['upage'] = $usersPage - 1;
                                    echo '<a href="' . $baseUrl . http_build_query($params) . '"><i class="fa fa-chevron-left"></i></a>';
                                else:
                                    echo '<span class="disabled"><i class="fa fa-chevron-left"></i></span>';
                                endif;
                                // Pages
                                $start = max(1, $usersPage - 2);
                                $end   = min($totalUsersPages, $usersPage + 2);
                                if ($start > 1): $params['upage'] = 1; echo '<a href="'.$baseUrl.http_build_query($params).'">1</a>'; if ($start > 2) echo '<span class="disabled">…</span>'; endif;
                                for ($p = $start; $p <= $end; $p++):
                                    $params['upage'] = $p;
                                    if ($p === $usersPage): echo '<span class="active">'.$p.'</span>';
                                    else: echo '<a href="'.$baseUrl.http_build_query($params).'">'.$p.'</a>'; endif;
                                endfor;
                                if ($end < $totalUsersPages): if ($end < $totalUsersPages - 1) echo '<span class="disabled">…</span>'; $params['upage'] = $totalUsersPages; echo '<a href="'.$baseUrl.http_build_query($params).'">'.$totalUsersPages.'</a>'; endif;
                                // Next
                                if ($usersPage < $totalUsersPages):
                                    $params['upage'] = $usersPage + 1;
                                    echo '<a href="' . $baseUrl . http_build_query($params) . '"><i class="fa fa-chevron-right"></i></a>';
                                else:
                                    echo '<span class="disabled"><i class="fa fa-chevron-right"></i></span>';
                                endif;
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>

    <script>
        function toggleGroup(el, grpId) {
            var grp = document.getElementById('grp_' + grpId);
            var boxes = grp.querySelectorAll('input[type=checkbox]');
            var allChecked = Array.from(boxes).every(function(b){ return b.checked; });
            boxes.forEach(function(b){ b.checked = !allChecked; });
            el.textContent = allChecked ? 'Select all' : 'Deselect all';
        }
    </script>

<?php
print dol_get_fiche_end();
llxFooter();
$db->close();
