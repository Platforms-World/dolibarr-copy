<?php
/**
 * Branch Management – Dolibarr Admin Page
 *
 * Allows the Dolibarr admin (tenant) to:
 *   - See how many branches their plan allows (read-only, set by Laravel)
 *   - Create / edit / disable branches within that limit
 *   - Link each branch to a TakePOS Store and a Warehouse
 *
 * Subscription plans are managed entirely in the Laravel admin panel.
 * This page never exposes plan/subscription controls.
 */

require '../../main.inc.php';
require_once __DIR__ . '/../lib/takepos_help.php';
require_once __DIR__ . '/../lib/takepos_lang.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/../class/TakeposAccess.class.php';
require_once __DIR__ . '/../class/TakeposBranchService.class.php';
require_once __DIR__ . '/../class/TakeposStoreService.class.php';
require_once __DIR__ . '/../class/TakeposAudit.class.php';

$langs->loadLangs(['admin', 'main', 'cashdesk', 'takeposcustom@takepos']);

restrictedArea($user, 'takepos', 0, '');
TakeposAccess::requireAdminAccess(
    $db, $user,
    'takepos.store_governance',
    'takepos.store.manage',
    isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null,
    'Access denied – TakePOS branch admin required.'
);

$entity  = !empty($user->entity) ? (int) $user->entity : 1;
$action  = GETPOST('action', 'aZ09');
$msg     = '';
$msgType = 'mesgs';

try {
    if (!empty($action) && GETPOST('token', 'alpha') !== $_SESSION['newtoken']) {
        throw new Exception('Invalid security token.');
    }

    if ($action === 'create_branch') {
        $newId = TakeposBranchService::createBranch(
            $db, $user, $entity,
            GETPOST('code', 'aZ09'),
            GETPOST('label', 'none'),
            GETPOST('description', 'none'),
            GETPOSTINT('warehouse_id'),
            GETPOSTINT('store_id')
        );
        $msg = 'Branch created (ID #' . $newId . ').';
    }

    if ($action === 'update_branch') {
        TakeposBranchService::updateBranch(
            $db, $user, $entity,
            GETPOSTINT('branch_id'),
            GETPOST('label', 'none'),
            GETPOST('description', 'none'),
            GETPOSTINT('warehouse_id'),
            GETPOSTINT('store_id'),
            GETPOSTINT('active')
        );
        $msg = 'Branch updated.';
    }

    if ($action === 'disable_branch') {
        $b = TakeposBranchService::getBranch($db, $entity, GETPOSTINT('branch_id'));
        if (!$b) {
            throw new Exception('Branch not found.');
        }
        TakeposBranchService::updateBranch(
            $db, $user, $entity,
            (int) $b->rowid,
            $b->label,
            (string) $b->description,
            (int) $b->fk_warehouse,
            (int) $b->fk_store,
            0
        );
        $msg = 'Branch disabled.';
    }
} catch (Throwable $e) {
    $msg     = $e->getMessage();
    $msgType = 'errors';
}

// ── Data ─────────────────────────────────────────────────────────────────────
$branches     = TakeposBranchService::listBranches($db, $entity, false);
$maxBranches  = TakeposBranchService::getMaxBranches($db, $entity);
$usedBranches = TakeposBranchService::countBranches($db, $entity, true);
$canCreate    = ($maxBranches > 0 && $usedBranches < $maxBranches);
$subActive    = TakeposBranchService::subscriptionActive($db, $entity);

// Warehouses
$warehouses = [];
if (isModEnabled('stock')) {
    $sqlWh = "SELECT rowid, ref, label FROM " . MAIN_DB_PREFIX . "entrepot"
           . " WHERE entity=" . $entity . " AND status=1 ORDER BY ref ASC";
    $res = $db->query($sqlWh);
    if ($res) {
        while ($obj = $db->fetch_object($res)) {
            $warehouses[] = $obj;
        }
    }
}

// TakePOS Stores
$stores = TakeposStoreService::listStores($db, $entity, true);

// ── Render ───────────────────────────────────────────────────────────────────
llxHeader('', 'Branch Management');
print load_fiche_titre('Branch Management');

// Only link to branch users — no subscription or plan pages
print '<div class="tabsAction">';
print '<a class="butAction" href="branch_users.php">Manage Branch Users →</a>';
print '</div>';

if ($msg !== '') {
    setEventMessages($msg, null, $msgType);
}

// ── Branch quota banner — read-only, no link to any subscription page ─────────
if (!$subActive) {
    // Subscription inactive: show a support message, nothing more
    print '<div class="error" style="padding:10px 14px;margin-bottom:15px">'
        . '⚠️ <strong>Branch creation is currently unavailable.</strong> '
        . 'Please contact support to activate or renew your subscription.'
        . '</div>';
} else {
    $pct      = $maxBranches > 0 ? round(($usedBranches / $maxBranches) * 100) : 0;
    $barColor = $pct >= 100 ? '#e04646' : ($pct >= 80 ? '#f0a500' : '#1aab8c');

    print '<div style="padding:10px 14px;margin-bottom:15px;border-left:4px solid '
          . $barColor . ';background:#f8f8f8;border-radius:2px">';
    print '<strong>Branches:</strong> ' . $usedBranches . ' of ' . $maxBranches . ' used &nbsp;';
    // Mini progress bar
    print '<span style="display:inline-block;width:120px;height:10px;background:#ddd;'
          . 'border-radius:5px;vertical-align:middle;margin:0 6px">';
    print '<span style="display:block;width:' . min(100, $pct) . '%;height:10px;background:'
          . $barColor . ';border-radius:5px"></span>';
    print '</span>';
    if ($pct >= 100) {
        print '<span style="color:#e04646">Limit reached — contact support to increase.</span>';
    }
    print '</div>';
}

// ── Create form (shown only when limit is not reached) ────────────────────────
if ($canCreate) {
    print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="create_branch">';
    print '<table class="border centpercent">';
    print '<tr class="liste_titre"><th colspan="2">Create New Branch</th></tr>';

    print '<tr><td class="titlefield" style="width:25%">Branch Code</td>';
    print '<td><input type="text" name="code" required pattern="[A-Za-z0-9_-]{2,32}" maxlength="32"'
          . ' placeholder="e.g. BRANCH-NORTH"></td></tr>';

    print '<tr><td>Label</td>';
    print '<td><input type="text" name="label" required maxlength="128" class="minwidth300"></td></tr>';

    print '<tr><td>Description</td>';
    print '<td><input type="text" name="description" maxlength="255" class="minwidth300"></td></tr>';

    print '<tr><td>Warehouse (Stock)</td><td>';
    print '<select name="warehouse_id"><option value="0">— None —</option>';
    foreach ($warehouses as $wh) {
        print '<option value="' . ((int) $wh->rowid) . '">'
              . dol_escape_htmltag(trim($wh->ref . ' - ' . $wh->label, ' -')) . '</option>';
    }
    print '</select></td></tr>';

    print '<tr><td>TakePOS Store</td><td>';
    print '<select name="store_id"><option value="0">— None —</option>';
    foreach ($stores as $st) {
        print '<option value="' . ((int) $st->rowid) . '">'
              . dol_escape_htmltag($st->code . ' – ' . $st->label) . '</option>';
    }
    print '</select></td></tr>';

    print '</table>';
    print '<br><input type="submit" class="button button-save" value="Create Branch">';
    print '</form><br>';

} elseif ($subActive) {
    // Limit hit but subscription is valid — no mention of plans
    print '<div class="warning" style="margin-bottom:15px">'
        . 'You have used all available branches (' . $usedBranches . '/' . $maxBranches . '). '
        . 'Contact support to increase your limit.'
        . '</div>';
}

// ── Branch list ───────────────────────────────────────────────────────────────
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
foreach (['ID', 'Code', 'Label', 'Description', 'Warehouse', 'Store', 'Status', 'Users', 'Save', 'Disable'] as $th) {
    print '<th>' . $th . '</th>';
}
print '</tr>';

if (empty($branches)) {
    print '<tr><td colspan="10" style="text-align:center;padding:20px">'
          . '<em>No branches yet. Use the form above to create your first branch.</em>'
          . '</td></tr>';
}

foreach ($branches as $branch) {
    $dimmed = ((int) $branch->active === 0 ? ' style="opacity:0.55"' : '');
    print '<tr class="oddeven"' . $dimmed . '>';

    // Edit form (update action)
    print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="update_branch">';
    print '<input type="hidden" name="branch_id" value="' . ((int) $branch->rowid) . '">';

    print '<td>' . ((int) $branch->rowid) . '</td>';
    print '<td><strong>' . dol_escape_htmltag($branch->code) . '</strong></td>';
    print '<td><input type="text" name="label" value="' . dol_escape_htmltag($branch->label)
          . '" required maxlength="128" style="min-width:130px"></td>';
    print '<td><input type="text" name="description" value="' . dol_escape_htmltag((string) $branch->description)
          . '" maxlength="255" style="min-width:130px"></td>';

    print '<td><select name="warehouse_id"><option value="0">— None —</option>';
    foreach ($warehouses as $wh) {
        $sel = ((int) $branch->fk_warehouse === (int) $wh->rowid ? ' selected' : '');
        print '<option value="' . ((int) $wh->rowid) . '"' . $sel . '>'
              . dol_escape_htmltag($wh->ref) . '</option>';
    }
    print '</select></td>';

    print '<td><select name="store_id"><option value="0">— None —</option>';
    foreach ($stores as $st) {
        $sel = ((int) $branch->fk_store === (int) $st->rowid ? ' selected' : '');
        print '<option value="' . ((int) $st->rowid) . '"' . $sel . '>'
              . dol_escape_htmltag($st->code) . '</option>';
    }
    print '</select></td>';

    print '<td><select name="active">';
    print '<option value="1"' . ((int) $branch->active === 1 ? ' selected' : '') . '>Active</option>';
    print '<option value="0"' . ((int) $branch->active === 0 ? ' selected' : '') . '>Disabled</option>';
    print '</select></td>';

    $uCount = count(TakeposBranchService::getBranchUsers($db, $entity, (int) $branch->rowid));
    print '<td><a href="branch_users.php?branch_id=' . ((int) $branch->rowid) . '">'
          . $uCount . ' user(s)</a></td>';

    print '<td><input type="submit" class="button" value="Save"></td>';
    print '</form>';

    print '<td>';
    if ((int) $branch->active === 1) {
        print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF'])
              . '" onsubmit="return confirm(\'Disable this branch?\')">';
        print '<input type="hidden" name="token" value="' . newToken() . '">';
        print '<input type="hidden" name="action" value="disable_branch">';
        print '<input type="hidden" name="branch_id" value="' . ((int) $branch->rowid) . '">';
        print '<input type="submit" class="button button-cancel" value="Disable">';
        print '</form>';
    }
    print '</td>';
    print '</tr>';
}

print '</table></div>';
print takeposHelpRender($langs, __FILE__);
llxFooter();
$db->close();
