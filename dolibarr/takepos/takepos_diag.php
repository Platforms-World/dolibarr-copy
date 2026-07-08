<?php
/**
 * takepos_diag.php — drop in C:\xampp\htdocs\test-seven\takepos\
 * Open in browser while logged in. DELETE after debugging.
 */

// Auto-find main.inc.php by walking up directories
$mainInc = null;
$dir = __DIR__;
for ($i = 0; $i < 6; $i++) {
    if (file_exists($dir . '/main.inc.php')) {
        $mainInc = $dir . '/main.inc.php';
        break;
    }
    $dir = dirname($dir);
}
if (!$mainInc) die('Cannot find main.inc.php — tried 6 levels up from ' . __DIR__);
require $mainInc;

require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposBranchService.class.php';

header('Content-Type: text/plain; charset=UTF-8');

echo "=== TakePOS Branch Product Diagnostic ===\n\n";
echo "DOL_DOCUMENT_ROOT: " . DOL_DOCUMENT_ROOT . "\n";
echo "User ID:    " . $user->id . "\n";
echo "User login: " . $user->login . "\n";
echo "User admin: " . ($user->admin ? 'YES' : 'no') . "\n";
echo "Entity:     " . $conf->entity . "\n\n";

$isBranch = TakeposBranchService::isBranchUser($db, (int)$user->id);
echo "isBranchUser: " . ($isBranch ? 'YES' : 'no') . "\n";

$branch = TakeposBranchService::getBranchByUserId($db, (int)$user->id);
if ($branch) {
    echo "Branch ID:           " . $branch->rowid . "\n";
    echo "Branch code:         " . $branch->code . "\n";
    echo "Branch fk_warehouse: " . ($branch->fk_warehouse ?: 'NULL') . "\n";
} else {
    echo "Branch: NOT FOUND for this user\n";
}

echo "\n--- Manual product list (takepos_branch_product) ---\n";
if ($branch) {
    $res = $db->query("SELECT fk_product, entity, active FROM " . MAIN_DB_PREFIX . "takepos_branch_product WHERE fk_branch=" . (int)$branch->rowid);
    if ($res && $db->num_rows($res) > 0) {
        while ($o = $db->fetch_object($res)) {
            echo "  product=" . $o->fk_product . "  entity=" . $o->entity . "  active=" . $o->active . "\n";
        }
    } else {
        echo "  (none)\n";
    }
}

echo "\n--- allowedProductIdsForUser() result ---\n";
$ids = TakeposBranchService::allowedProductIdsForUser($db, $user);
if ($ids === null) {
    echo "null — no filter (admin or non-branch user — sees ALL products)\n";
} elseif (empty($ids)) {
    echo "EMPTY ARRAY [] — no products will show!\n";
} else {
    echo count($ids) . " product(s) found: " . implode(', ', $ids) . "\n";
}

echo "\n--- Products with stock in branch warehouse ---\n";
if ($branch && $branch->fk_warehouse) {
    $wid = (int)$branch->fk_warehouse;
    echo "Checking warehouse ID: $wid\n";
    $res = $db->query(
        "SELECT p.rowid, p.ref, p.label, p.tosell, p.fk_default_warehouse, ps.reel"
        . " FROM " . MAIN_DB_PREFIX . "product p"
        . " INNER JOIN " . MAIN_DB_PREFIX . "product_stock ps ON ps.fk_product=p.rowid"
        . " WHERE ps.fk_entrepot=" . $wid
    );
    if ($res && $db->num_rows($res) > 0) {
        while ($o = $db->fetch_object($res)) {
            echo "  [" . $o->rowid . "] " . $o->ref . " — tosell=" . $o->tosell
                . " — default_wh=" . ($o->fk_default_warehouse ?: 'NULL')
                . " — stock=" . $o->reel . "\n";
        }
    } else {
        echo "  (no rows in product_stock for warehouse $wid)\n";
    }
} else {
    echo "  branch has no warehouse set\n";
}

echo "\n--- All branch rows ---\n";
$res = $db->query("SELECT rowid, code, label, fk_warehouse, fk_user, active, entity FROM " . MAIN_DB_PREFIX . "takepos_branch ORDER BY rowid");
if ($res) {
    while ($o = $db->fetch_object($res)) {
        echo "  [" . $o->rowid . "] code=" . $o->code
            . " wh=" . ($o->fk_warehouse ?: 'NULL')
            . " fk_user=" . $o->fk_user
            . " active=" . $o->active
            . " entity=" . $o->entity . "\n";
    }
}

echo "\n--- CASHDESK_ID_WAREHOUSE constants ---\n";
$res = $db->query("SELECT name, value FROM " . MAIN_DB_PREFIX . "const WHERE name LIKE 'CASHDESK_ID_WAREHOUSE%' ORDER BY name");
if ($res) {
    while ($o = $db->fetch_object($res)) {
        echo "  " . $o->name . " = " . $o->value . "\n";
    }
}

echo "\n=== END ===\n";