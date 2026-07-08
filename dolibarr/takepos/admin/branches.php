<?php
/**
 * branches.php — Branch management UI for TakePOS
 */

require '../../main.inc.php';
require_once __DIR__ . '/../lib/takepos_help.php';
require_once __DIR__ . '/../lib/takepos_lang.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/../class/TakeposAccess.class.php';
require_once __DIR__ . '/../class/TakeposBranchService.class.php';
require_once __DIR__ . '/../class/TakeposStoreService.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';

$langs->loadLangs(['admin', 'main', 'cashdesk', 'takeposcustom@takepos']);

restrictedArea($user, 'takepos', 0, '');

if (!$user->admin) {
    TakeposAccess::requireAdminAccess(
        $db, $user,
        'takepos.store_governance',
        'takepos.store.manage',
        isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null,
        'Access denied.'
    );
}

if (TakeposBranchService::isBranchUser($db, (int) $user->id)) {
    accessforbidden('Branch users cannot access branch management.');
}

$entity      = ((int) $conf->entity > 0) ? (int) $conf->entity : 1;
$action      = GETPOST('action', 'aZ09');
$msg         = '';
$msgType     = 'mesgs';
$newCredentials = null;

try {
    if (!empty($action) && GETPOST('token', 'alpha') !== $_SESSION['newtoken']) {
        throw new Exception('Invalid security token.');
    }

    if ($action === 'create_branch') {
        $result = TakeposBranchService::createBranch(
            $db, $user, $entity,
            GETPOST('code', 'aZ09'),
            GETPOST('label', 'none'),
            GETPOST('description', 'none'),
            GETPOSTINT('warehouse_id'),
            GETPOSTINT('store_id')
        );
        $newCredentials = $result;
        $msg = 'Branch created successfully. A starter terminal (Cashier 1) was created automatically.';

        // Auto-assign TEST001 (the dummy starter product) to the new branch.
        // Without this, the branch has zero assigned products and the POS falls
        // back to showing ALL merchant products — which is wrong.
        // The merchant removes TEST001 and adds their real products via branch_products.php.
        //
        // createBranch() may or may not return branch_id in the result array.
        // We use a safe fallback: if result has branch_id use it, otherwise
        // look up the newly created branch by its code from the POST data.
        $newBranchId = !empty($result['branch_id']) ? (int) $result['branch_id'] : 0;

        if ($newBranchId <= 0) {
            // Fallback: find the branch we just created by its code
            $newCode = GETPOST('code', 'aZ09');
            $resB    = $db->query(
                "SELECT rowid FROM " . MAIN_DB_PREFIX . "takepos_branch"
                . " WHERE code = '" . $db->escape($newCode) . "'"
                . " ORDER BY rowid DESC LIMIT 1"
            );
            if ($resB && $db->num_rows($resB) > 0) {
                $bRow        = $db->fetch_object($resB);
                $newBranchId = (int) $bRow->rowid;
            }
        }

        if ($newBranchId > 0) {
            $resP = $db->query(
                "SELECT rowid FROM " . MAIN_DB_PREFIX . "product"
                . " WHERE ref = 'TEST001' AND entity IN (" . getEntity('product') . ")"
                . " LIMIT 1"
            );
            if ($resP && $db->num_rows($resP) > 0) {
                $pRow = $db->fetch_object($resP);
                TakeposBranchService::setBranchProductsById(
                    $db,
                    $newBranchId,
                    [(int) $pRow->rowid],
                    $entity
                );
            } else {
                // FIX (B9): TEST001 starter product not found — warn admin instead
                // of silently leaving the branch with 0 products (which causes all
                // products to show to branch cashiers — exactly what branches prevent).
                $msg .= ' Warning: starter product TEST001 was not found. The branch has 0 products assigned — cashiers will see ALL products. Go to Branch Products to assign the correct catalogue.';
            }
        }
    }

    if ($action === 'update_branch') {
        $bid = GETPOSTINT('branch_id');
        if ($bid <= 0) throw new Exception('Invalid branch ID.');

        // Remember the old warehouse so we can tell if it changed.
        $beforeBranch = TakeposBranchService::getBranch($db, $entity, $bid);
        $oldWarehouse = $beforeBranch ? (int) $beforeBranch->fk_warehouse : 0;
        $newWarehouse = GETPOSTINT('warehouse_id');

        TakeposBranchService::updateBranch(
            $db, $user, $entity, $bid,
            GETPOST('label', 'none'),
            GETPOST('description', 'none'),
            GETPOSTINT('warehouse_id'),
            GETPOSTINT('store_id'),
            GETPOSTINT('active')
        );
        $msg = 'Branch updated.';

        // If the warehouse was changed, assign all products of that warehouse to
        // the branch. The admin can untick the ones they don't want afterwards.
        if ($newWarehouse > 0 && $newWarehouse !== $oldWarehouse) {
            $whProductIds = array();
            $resWP = $db->query(
                "SELECT DISTINCT ps.fk_product"
                . " FROM " . MAIN_DB_PREFIX . "product_stock ps"
                . " INNER JOIN " . MAIN_DB_PREFIX . "product p ON p.rowid = ps.fk_product"
                . " WHERE ps.fk_entrepot = " . (int) $newWarehouse
                . " AND p.tosell = 1"
                . " AND p.entity IN (" . getEntity('product') . ")"
            );
            if ($resWP) {
                while ($wpRow = $db->fetch_object($resWP)) { $whProductIds[] = (int) $wpRow->fk_product; }
            }
            TakeposBranchService::setBranchProductsById($db, $bid, $whProductIds, $entity);
            $msg .= ' ' . count($whProductIds) . ' product(s) from the warehouse were assigned. Untick any you do not want in Branch Products.';
        }
    }

    if ($action === 'disable_branch') {
        $bid = GETPOSTINT('branch_id');
        if ($bid <= 0) throw new Exception('Invalid branch ID.');
        $b = TakeposBranchService::getBranch($db, $entity, $bid);
        if (!$b) throw new Exception('Branch not found.');
        TakeposBranchService::updateBranch(
            $db, $user, $entity, (int) $b->rowid,
            $b->label, (string) $b->description,
            (int) $b->fk_warehouse, (int) $b->fk_store, 0
        );
        $msg = 'Branch disabled.';
    }

    if ($action === 'reset_password') {
        $bid = GETPOSTINT('branch_id');
        if ($bid <= 0) throw new Exception('Invalid branch ID.');
        $newCredentials = TakeposBranchService::resetBranchPassword($db, $user, $entity, $bid);
        $msg = 'Password reset successfully.';
    }

    if ($action === 'delete_branch') {
        $bid = GETPOSTINT('branch_id');
        if ($bid <= 0) throw new Exception('Invalid branch ID.');
        TakeposBranchService::deleteBranchById($db, $user, $bid);
        $msg = 'Branch deleted permanently.';
    }

    if ($action === 'sync_permissions') {
        $synced = TakeposBranchService::syncAllBranchUserPermissions($db);
        $msg = 'Permissions synced for ' . $synced . ' branch user(s). All branches now have the full cashier permission set.';
    }

} catch (Throwable $e) {
    $msg     = $e->getMessage();
    $msgType = 'errors';
}

$branches     = TakeposBranchService::listBranches($db, $entity, false);
$maxBranches  = TakeposBranchService::getMaxBranches($db, $entity);
$usedBranches = TakeposBranchService::countBranches($db, $entity, true);
$canCreate    = ($usedBranches < $maxBranches);

// FIX (I17): Load product counts per branch so we can show a ⚠️ badge on
// branches with 0 products. A branch with 0 products causes the POS to fall
// back to showing ALL products — the opposite of what branches are for.
$branchProductCounts = array();
$resBPC = $db->query(
    'SELECT fk_branch, COUNT(*) AS cnt FROM ' . MAIN_DB_PREFIX . 'takepos_branch_product'
    . ' WHERE active = 1 GROUP BY fk_branch'
);
if ($resBPC) {
    while ($bpcRow = $db->fetch_object($resBPC)) {
        $branchProductCounts[(int) $bpcRow->fk_branch] = (int) $bpcRow->cnt;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// FIX (final): Load warehouses and stores directly from the database with
// NO entity filter and NO status filter. This shows every warehouse and
// every store regardless of which entity owns them, which is the right
// behaviour for a single-tenant admin assigning resources to branches.
// ─────────────────────────────────────────────────────────────────────────────
$warehouses = [];
// FIX (B10): Added entity filter so admins only see warehouses belonging to
// their own entity. Without this, in a multi-entity Dolibarr setup the dropdown
// would show warehouses from all entities, allowing accidental cross-entity assignment.
$res = $db->query("SELECT rowid, ref, label FROM " . MAIN_DB_PREFIX . "entrepot WHERE entity IN (" . getEntity('stock') . ") ORDER BY ref ASC");
if ($res) { while ($obj = $db->fetch_object($res)) { $warehouses[] = $obj; } }

$stores = [];
$resStores = $db->query("SHOW TABLES LIKE '" . MAIN_DB_PREFIX . "takepos_store'");
if ($resStores && $db->num_rows($resStores) > 0) {
    $resS = $db->query("SELECT rowid, code, label, warehouse_id FROM " . MAIN_DB_PREFIX . "takepos_store ORDER BY code ASC");
    if ($resS) { while ($obj = $db->fetch_object($resS)) { $stores[] = $obj; } }
}

// ── Render ────────────────────────────────────────────────────────────────────
require_once DOL_DOCUMENT_ROOT . '/core/lib/takepos.lib.php';
$head = takepos_admin_prepare_head();
llxHeader('', $langs->trans('TakeposBranchTitle'));
print dol_get_fiche_head($head, 'branches', 'TakePOS', -1, 'cash-register');
print dol_get_fiche_end();

if ($msg !== '') setEventMessages($msg, null, $msgType);

if ($newCredentials) {
    print '<div style="border:2px solid #1aab8c;border-radius:6px;padding:16px 20px;margin-bottom:20px;background:#f0fdf8">';
    print '<strong style="font-size:1.1em">🔐 Branch Login Credentials</strong>';
    print '<p style="margin:8px 0 4px">Share these with the branch manager. The password cannot be recovered — save it now.</p>';
    print '<table style="border-collapse:collapse;margin-top:8px">';
    print '<tr><td style="padding:4px 12px 4px 0;color:#555">Login URL</td>';
    print '<td><strong>' . DOL_MAIN_URL_ROOT . '</strong></td></tr>';
    print '<tr><td style="padding:4px 12px 4px 0;color:#555">Username</td>';
    print '<td><strong style="font-size:1.1em;font-family:monospace">' . dol_escape_htmltag($newCredentials['login']) . '</strong></td></tr>';
    print '<tr><td style="padding:4px 12px 4px 0;color:#555">Password</td>';
    print '<td><strong style="font-size:1.1em;font-family:monospace;color:#1aab8c">' . dol_escape_htmltag($newCredentials['password']) . '</strong></td></tr>';
    if (!empty($newCredentials['terminal_id'])) {
        print '<tr><td style="padding:4px 12px 4px 0;color:#555">Starter Terminal ID</td>';
        print '<td><strong style="font-family:monospace">' . (int) $newCredentials['terminal_id'] . '</strong> (Cashier 1 — auto-created)</td></tr>';
    }
    print '</table>';
    print '</div>';
}

print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '" style="margin-bottom:12px">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="sync_permissions">';
print '<button type="submit" class="button" style="background:#1aab8c;color:#fff;border:none;padding:7px 18px;border-radius:4px;cursor:pointer">';
print '🔄 '.$langs->trans('TakeposBranchSyncPerms');
print '</button>';
print ' <span style="color:#888;font-size:0.88em">Run this after updating the code to grant new permissions to all existing branches.</span>';
print '</form>';

// Link to stores management page
print '<div style="margin-bottom:16px">';
print '<a class="button" href="' . dol_escape_htmltag(DOL_URL_ROOT . '/takepos/admin/stores.php') . '" style="background:#5b2d8e;color:#fff;padding:7px 18px;border-radius:4px;text-decoration:none;display:inline-block">';
print '🏪 '.$langs->trans('TakeposBranchManageStores');
print '</a>';
print ' <span style="color:#888;font-size:0.88em">Create and manage TakePOS stores that can be assigned to branches.</span>';
print '</div>';

$pct      = $maxBranches > 0 && $maxBranches < 999 ? round(($usedBranches / $maxBranches) * 100) : 0;
$barColor = $pct >= 100 ? '#e04646' : ($pct >= 80 ? '#f0a500' : '#1aab8c');
print '<div style="padding:10px 14px;margin-bottom:15px;border-left:4px solid ' . $barColor . ';background:#f8f8f8">';
print '<strong>'.$langs->trans('TakeposBranchUsed').'</strong>: ' . $usedBranches . ' of ' . ($maxBranches >= 999 ? '∞' : $maxBranches) . ' used';
if ($maxBranches < 999) {
    print ' &nbsp;<span style="display:inline-block;width:120px;height:10px;background:#ddd;border-radius:5px;vertical-align:middle;margin:0 6px">';
    print '<span style="display:block;width:' . min(100, $pct) . '%;height:10px;background:' . $barColor . ';border-radius:5px"></span></span>';
}
if ($pct >= 100) print ' <span style="color:#e04646">Limit reached — contact support.</span>';
print '</div>';

if ($canCreate) {
    print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="create_branch">';
    print '<table class="border centpercent">';
    print '<tr class="liste_titre"><th colspan="2">Create New Branch — a login user and starter terminal will be created automatically</th></tr>';
    print '<tr><td class="titlefield" style="width:25%">Branch Code <span style="color:#c00">*</span></td>';
    print '<td><input type="text" name="code" required pattern="[A-Za-z0-9_-]{2,32}" maxlength="32" placeholder="e.g. NORTH-01"></td></tr>';
    print '<tr><td>Branch Name <span style="color:#c00">*</span></td><td><input type="text" name="label" required maxlength="128" class="minwidth300"></td></tr>';
    print '<tr><td>Description</td><td><input type="text" name="description" maxlength="255" class="minwidth300"></td></tr>';
    print '<tr><td>Warehouse</td><td><select name="warehouse_id" id="new_branch_warehouse" onchange="takeposFilterStores(this, \'new_branch_store\')"><option value="0">— None —</option>';
    foreach ($warehouses as $wh) {
        print '<option value="' . (int) $wh->rowid . '">' . dol_escape_htmltag(trim($wh->ref . ' - ' . $wh->label, ' -')) . '</option>';
    }
    print '</select></td></tr>';
    print '<tr><td>TakePOS Store</td><td><select name="store_id" id="new_branch_store"><option value="0">— None —</option>';
    foreach ($stores as $st) {
        print '<option value="' . (int) $st->rowid . '" data-warehouse="' . (int)$st->warehouse_id . '">' . dol_escape_htmltag($st->code . ' – ' . $st->label) . '</option>';
    }
    print '</select></td></tr>';
    print '</table><br><input type="submit" class="button button-save" value="' . $langs->trans('TakeposBranchCreateBtn') . '"></form><br>';
} elseif ($maxBranches < 999) {
    print '<div class="warning" style="margin-bottom:15px">Branch limit reached (' . $usedBranches . '/' . $maxBranches . '). Contact support to increase your limit.</div>';
}

print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th>ID</th><th>Details</th><th>Code</th><th>Assigned</th><th>Name</th><th>Login Username</th><th>Warehouse</th><th>Store</th><th>Status</th><th>Save</th><th>Products</th><th>Reset Password</th><th>Disable</th><th>Delete</th></tr>';

if (empty($branches)) {
    print '<tr><td colspan="12" style="text-align:center;padding:20px"><em>No branches yet. Create your first branch above.</em></td></tr>';
}

foreach ($branches as $branch) {
    $isActive = ((int) $branch->active === 1);
    print '<tr class="oddeven"' . (!$isActive ? ' style="opacity:0.55"' : '') . '>';

    print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="update_branch">';
    print '<input type="hidden" name="branch_id" value="' . (int) $branch->rowid . '">';

    print '<td>' . (int) $branch->rowid . '</td>';
    print '<td><a class="button" href="branch_detail.php?branch_id=' . (int)$branch->rowid . '">🔍 View</a></td>';
    $bpCount = isset($branchProductCounts[(int)$branch->rowid]) ? $branchProductCounts[(int)$branch->rowid] : 0;
    print '<td><strong>' . dol_escape_htmltag($branch->code) . '</strong></td>';
    // Product count badge — red warning when 0 (cashiers see all products)
    print '<td style="white-space:nowrap">';
    if ($bpCount === 0) {
        print '<span style="background:#fef2f2;color:#c00;border:1px solid #fca5a5;border-radius:4px;padding:2px 8px;font-size:12px;font-weight:700" title="' . dol_escape_htmltag($langs->trans('TakeposBranchZeroProducts')) . '">⚠️ ' . $bpCount . ' ' . $langs->trans('TakeposBranchProducts') . '</span>';
    } else {
        print '<span style="background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;border-radius:4px;padding:2px 8px;font-size:12px">' . $bpCount . ' products</span>';
    }
    print '</td>';
    print '<td><input type="text" name="label" value="' . dol_escape_htmltag($branch->label) . '" required maxlength="128" style="min-width:130px"></td>';

    print '<td><code style="background:#f0f0f0;padding:2px 6px;border-radius:3px">'
        . dol_escape_htmltag($branch->branch_login ?: '—') . '</code></td>';

    $whWarning = ((int) $branch->fk_warehouse <= 0)
        ? '<span title="' . dol_escape_htmltag($langs->trans('TakeposBranchNoWarning')) . '" '
        . 'style="color:#b45309;font-size:11px;font-weight:700;margin-left:4px">⚠ ' . $langs->trans('TakeposBranchNoWarning') . '</span>'
        : '';
    $whSelId = 'wh_branch_' . (int)$branch->rowid;
    $stSelId = 'st_branch_' . (int)$branch->rowid;
    print '<td><select name="warehouse_id" id="' . $whSelId . '" onchange="takeposFilterStores(this, \'' . $stSelId . '\')"><option value="0">— None —</option>';
    foreach ($warehouses as $wh) {
        print '<option value="' . (int) $wh->rowid . '"' . ((int) $branch->fk_warehouse === (int) $wh->rowid ? ' selected' : '') . '>' . dol_escape_htmltag($wh->ref) . '</option>';
    }
    print '</select>' . $whWarning . '</td>';

    print '<td><select name="store_id" id="' . $stSelId . '"><option value="0">— None —</option>';
    foreach ($stores as $st) {
        $storeWarehouseId = (int)(isset($st->warehouse_id) ? $st->warehouse_id : 0);
        print '<option value="' . (int) $st->rowid . '"'
            . ((int) $branch->fk_store === (int) $st->rowid ? ' selected' : '')
            . ' data-warehouse="' . $storeWarehouseId . '">'
            . dol_escape_htmltag($st->code) . '</option>';
    }
    print '</select></td>';

    print '<td><select name="active">';
    print '<option value="1"' . ($isActive ? ' selected' : '') . '>Active</option>';
    print '<option value="0"' . (!$isActive ? ' selected' : '') . '>Disabled</option>';
    print '</select></td>';

    print '<td><input type="submit" class="button" value="Save"></td>';
    print '</form>';

    print '<td><a class="button" href="' . dol_escape_htmltag(DOL_URL_ROOT . '/takepos/admin/branch_products.php?branch_id=' . (int) $branch->rowid) . '">📦 Products</a></td>';

    print '<td>';
    print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '" onsubmit="return confirm(\'Generate a new password for this branch login?\')">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="reset_password">';
    print '<input type="hidden" name="branch_id" value="' . (int) $branch->rowid . '">';
    print '<input type="submit" class="button" value="' . $langs->trans('TakeposBranchResetPwd') . '">';
    print '</form></td>';

    print '<td>';
    if ($isActive) {
        print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '" onsubmit="return confirm(\'Disable this branch?\')">';
        print '<input type="hidden" name="token" value="' . newToken() . '">';
        print '<input type="hidden" name="action" value="disable_branch">';
        print '<input type="hidden" name="branch_id" value="' . (int) $branch->rowid . '">';
        print '<input type="submit" class="button button-cancel" value="' . $langs->trans('TakeposBranchDisable') . '"></form>';
    }
    print '</td>';

    print '<td>';
    print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '" onsubmit="return confirm(\'PERMANENT DELETE — branch + login user + all mappings. Continue?\')">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="delete_branch">';
    print '<input type="hidden" name="branch_id" value="' . (int) $branch->rowid . '">';
    print '<input type="submit" class="button button-cancel" style="background:#c0392b;color:#fff" value="🗑 ' . $langs->trans('TakeposBranchDelete') . '"></form>';
    print '</td></tr>';
}
print '</table></div>';
print '<script>
/**
 * Filter the Store dropdown to only show stores whose warehouse_id matches
 * the selected warehouse. Shows all stores when warehouse = 0 (None).
 */
function takeposFilterStores(warehouseSelect, storeSelectId) {
    var warehouseId = parseInt(warehouseSelect.value, 10) || 0;
    var storeSelect = document.getElementById(storeSelectId);
    if (!storeSelect) return;

    var options = storeSelect.querySelectorAll("option");
    var currentValue = parseInt(storeSelect.value, 10) || 0;
    var firstVisible = 0;

    options.forEach(function(opt) {
        var optVal = parseInt(opt.value, 10) || 0;
        if (optVal === 0) { opt.style.display = ""; return; } // Always show None
        var optWh = parseInt(opt.getAttribute("data-warehouse"), 10) || 0;

        if (warehouseId === 0 || optWh === 0 || optWh === warehouseId) {
            // Show: warehouse matches, store has no warehouse restriction, or no filter
            opt.style.display = "";
            if (firstVisible === 0) firstVisible = optVal;
        } else {
            opt.style.display = "none";
        }
    });

    // If the currently selected store is now hidden, reset to None
    var currentOpt = storeSelect.querySelector("option[value=\"" + currentValue + "\"]");
    if (currentValue !== 0 && currentOpt && currentOpt.style.display === "none") {
        storeSelect.value = "0";
    }
}

// On page load: apply filter for each existing branch row
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll("select[id^=\"wh_branch_\"]").forEach(function(whSel) {
        var branchId = whSel.id.replace("wh_branch_", "");
        takeposFilterStores(whSel, "st_branch_" + branchId);
    });
    // Also apply for the new-branch form
    var newWh = document.getElementById("new_branch_warehouse");
    if (newWh) takeposFilterStores(newWh, "new_branch_store");
});
</script>';

print takeposHelpRender($langs, __FILE__);
llxFooter();
$db->close();