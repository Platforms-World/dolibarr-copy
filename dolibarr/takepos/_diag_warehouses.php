<?php
/**
 * TakePOS warehouse-dropdown diagnostic.
 *
 * Drop this file into the takepos/ folder, then open:
 *   /takepos/_diag_warehouses.php
 * in your browser while logged in as admin.
 *
 * Delete this file after you read the output.
 */

// Standard Dolibarr bootstrap.
$res = 0;
if (!$res && file_exists(__DIR__ . '/../main.inc.php')) {
    $res = @include __DIR__ . '/../main.inc.php';
}
if (!$res) {
    die("Cannot find main.inc.php — put this file in takepos/ root.\n");
}

if (empty($user->admin)) {
    accessforbidden();
}

header('Content-Type: text/plain; charset=utf-8');

echo "===== TakePOS Warehouse-Dropdown Diagnostic =====\n\n";

// ──────────────────────────────────────────────────────────────────────────
echo "## 1. Current user / entity context\n";
echo "user_id          : " . (int) $user->id . "\n";
echo "user_login       : " . $user->login . "\n";
echo "user_admin       : " . ($user->admin ? "YES" : "no") . "\n";
echo "user->entity     : " . (int) $user->entity . "\n";
echo "conf->entity     : " . (int) $conf->entity . "\n";
echo "getEntity('stock') returns : " . getEntity('stock') . "\n";
echo "getEntity('takepos') returns : " . getEntity('takepos') . "\n";
echo "\n";

// ──────────────────────────────────────────────────────────────────────────
echo "## 2. Columns that exist in llx_entrepot\n";
$resCols = $db->query("SHOW COLUMNS FROM " . MAIN_DB_PREFIX . "entrepot");
if ($resCols) {
    while ($col = $db->fetch_object($resCols)) {
        echo "  - " . $col->Field . " (" . $col->Type . ")\n";
    }
} else {
    echo "  (could not read columns)\n";
}
echo "\n";

// ──────────────────────────────────────────────────────────────────────────
echo "## 3. All warehouses, no filter\n";
$resAll = $db->query("SELECT rowid, ref, entity FROM " . MAIN_DB_PREFIX . "entrepot ORDER BY rowid");
if ($resAll) {
    printf("  %-6s  %-30s  %s\n", "rowid", "ref", "entity");
    while ($obj = $db->fetch_object($resAll)) {
        printf("  %-6s  %-30s  %s\n", $obj->rowid, $obj->ref, $obj->entity);
    }
} else {
    echo "  (query failed: " . $db->lasterror() . ")\n";
}
echo "\n";

// ──────────────────────────────────────────────────────────────────────────
echo "## 4. Test the queries the admin pages would run\n";

$tests = array(
    "entity = " . (int) $user->entity                              => "branches.php v1 (original)",
    "entity = " . (int) $user->entity . " AND statut IN (0, 1)"    => "branches.php v3 (statut filter)",
    "entity IN (" . getEntity('stock') . ")"                       => "branches.php v3.1 (getEntity, no status)",
    "entity IN (" . getEntity('stock') . ") AND statut IN (0, 1)"  => "v3.1 + statut filter",
    "entity IN (" . getEntity('stock') . ") AND status IN (0, 1)"  => "v3.1 + status filter (Dolibarr 22?)",
);
foreach ($tests as $where => $label) {
    $sql = "SELECT COUNT(*) AS cnt FROM " . MAIN_DB_PREFIX . "entrepot WHERE " . $where;
    $resT = $db->query($sql);
    if ($resT) {
        $obj = $db->fetch_object($resT);
        printf("  [%2d rows]  %s\n", (int) $obj->cnt, $label);
        if ((int) $obj->cnt === 0) {
            echo "             SQL: " . $sql . "\n";
        }
    } else {
        printf("  [ERROR  ]  %s — %s\n", $label, $db->lasterror());
    }
}
echo "\n";

// ──────────────────────────────────────────────────────────────────────────
echo "## 5. takepos_branch and takepos_store rows + their entity\n";
$resB = $db->query("SELECT rowid, code, label, fk_warehouse, entity, active FROM " . MAIN_DB_PREFIX . "takepos_branch ORDER BY rowid");
if ($resB) {
    echo "  llx_takepos_branch:\n";
    printf("    %-6s  %-10s  %-15s  %-12s  %-8s  %s\n", "rowid", "code", "label", "fk_warehouse", "entity", "active");
    while ($obj = $db->fetch_object($resB)) {
        printf("    %-6s  %-10s  %-15s  %-12s  %-8s  %s\n",
            $obj->rowid, $obj->code, $obj->label, $obj->fk_warehouse, $obj->entity, $obj->active);
    }
}
$resS = $db->query("SHOW TABLES LIKE '" . MAIN_DB_PREFIX . "takepos_store'");
if ($resS && $db->num_rows($resS) > 0) {
    $resS2 = $db->query("SELECT * FROM " . MAIN_DB_PREFIX . "takepos_store LIMIT 20");
    echo "\n  llx_takepos_store (sample):\n";
    if ($resS2 && $db->num_rows($resS2) > 0) {
        while ($obj = $db->fetch_object($resS2)) {
            echo "    " . json_encode($obj) . "\n";
        }
    } else {
        echo "    (table exists but is empty)\n";
    }
} else {
    echo "\n  llx_takepos_store: table not found.\n";
}

echo "\n===== End =====\n";
echo "When you have copied this output, DELETE this file from the server.\n";
