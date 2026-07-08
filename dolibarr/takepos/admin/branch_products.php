<?php
/**
 * branch_products.php — Assign products to a branch
 *
 * FIX LOG:
 *  - setBranchProductsById now receives $entity so multi-entity setups work correctly.
 *  - getBranchProductIdsById now receives $entity for same reason.
 *  - Query to fetch all sellable products now uses getEntity() helper instead of
 *    hard-coded entity=1.
 *  - Added GETPOSTINT sanitization on branch_id to prevent type-juggling issues.
 *  - Branch users can now VIEW their own branch products (read-only).
 *    They cannot modify product assignments.
 */

require '../../main.inc.php';
require_once __DIR__ . '/../lib/takepos_help.php';
require_once __DIR__ . '/../lib/takepos_lang.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/../class/TakeposAccess.class.php';
require_once __DIR__ . '/../class/TakeposBranchService.class.php';

$langs->loadLangs(['admin', 'main', 'cashdesk', 'products']);

restrictedArea($user, 'takepos', 0, '');

$isBranchUser = TakeposBranchService::isBranchUser($db, (int) $user->id);

// Non-branch, non-admin users must have store_governance permission
if (!$isBranchUser && !$user->admin) {
    TakeposAccess::requireAdminAccess(
        $db, $user,
        'takepos.store_governance',
        'takepos.store.manage',
        isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null,
        'Access denied.'
    );
}

$entity   = !empty($user->entity) ? (int) $user->entity : 1;
$action   = GETPOST('action', 'aZ09');
$msg      = '';
$msgType  = 'mesgs';

// If branch user, force branch_id to their own branch (ignore URL param)
if ($isBranchUser) {
    $ownBranch = TakeposBranchService::getBranchByUserId($db, (int) $user->id);
    if (!$ownBranch) accessforbidden('Branch not found.');
    $branchId = (int) $ownBranch->rowid;
    $entity   = (int) $ownBranch->entity > 0 ? (int) $ownBranch->entity : 1;
} else {
    $branchId = GETPOSTINT('branch_id');
}

if ($branchId <= 0) {
    header('Location: ' . DOL_URL_ROOT . '/takepos/admin/branches.php');
    exit;
}

// Fetch the branch (entity-aware)
$branch = TakeposBranchService::getBranch($db, $entity, $branchId);
if (!$branch) {
    // Fallback: try without entity restriction (useful when admin is on a different entity)
    $res = $db->query("SELECT rowid, code, label FROM " . MAIN_DB_PREFIX . "takepos_branch WHERE rowid=" . (int)$branchId . " LIMIT 1");
    $branch = ($res && $db->num_rows($res) > 0) ? $db->fetch_object($res) : null;
}
if (!$branch) {
    setEventMessages('Branch id=' . $branchId . ' does not exist.', null, 'warnings');
    header('Location: ' . DOL_URL_ROOT . '/takepos/admin/branches.php');
    exit;
}

try {
    if ($action === 'save_products') {
        if ($isBranchUser) throw new Exception('Branch users cannot modify product assignments.');
        if (GETPOST('token', 'alpha') !== $_SESSION['newtoken']) throw new Exception('Invalid token.');
        $selected = GETPOST('product_ids', 'array');
        if (!is_array($selected)) $selected = [];
        TakeposBranchService::setBranchProductsById($db, $branchId, array_map('intval', $selected), $entity);
        $msg = 'Saved. ' . count($selected) . ' product(s) assigned to this branch.';
    }
} catch (Throwable $e) {
    $msg = $e->getMessage(); $msgType = 'errors';
}

$assigned = array_flip(TakeposBranchService::getBranchProductIdsById($db, $branchId, $entity));

$products = [];
$res = $db->query(
    "SELECT p.rowid, p.ref, p.label, p.price"
    . " FROM " . MAIN_DB_PREFIX . "product p"
    . " WHERE p.entity IN (" . getEntity('product') . ") AND p.tosell=1"
    . " ORDER BY p.ref ASC"
);
if ($res) { while ($o = $db->fetch_object($res)) { $products[] = $o; } }

require_once DOL_DOCUMENT_ROOT . '/core/lib/takepos.lib.php';
$head = takepos_admin_prepare_head();
llxHeader('', 'Branch Products — ' . dol_escape_htmltag($branch->label));
print dol_get_fiche_head($head, 'branches', 'TakePOS', -1, 'cash-register');
print dol_get_fiche_end();

if ($msg !== '') setEventMessages($msg, null, $msgType);

if (!$isBranchUser) {
    print '<div style="margin-bottom:14px"><a class="button" href="' . dol_escape_htmltag(DOL_URL_ROOT . '/takepos/admin/branches.php') . '">← Back to Branches</a></div>';
}
print '<h2>Products for: ' . dol_escape_htmltag($branch->label) . ' (' . dol_escape_htmltag($branch->code) . ')</h2>';

if ($isBranchUser) {
    print '<p style="color:#666">These are the products available in your branch.</p>';
} else {
    print '<p style="color:#666">Tick products this branch can sell. Unticked = hidden from branch POS. Categories will show only the products ticked here.</p>';
}

$assignedCount = count($assigned);
print '<p><strong>' . $assignedCount . '</strong> product(s) currently assigned.</p>';

if (empty($products)) {
    print '<div class="warning">No active sellable products exist in the system.</div>';
    llxFooter(); $db->close(); exit;
}

print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="save_products">';
print '<input type="hidden" name="branch_id" value="' . (int) $branchId . '">';

if (!$isBranchUser) {
    print '<div style="margin:10px 0">';
    print '<button type="button" class="button" onclick="document.querySelectorAll(\'input.bp\').forEach(c=>c.checked=true)">Select All</button> ';
    print '<button type="button" class="button" onclick="document.querySelectorAll(\'input.bp\').forEach(c=>c.checked=false)">Clear All</button> ';
    print '<input type="text" id="bp-filter" placeholder="Filter by ref or name..." style="margin-left:14px;padding:4px 8px;min-width:240px">';
    print '</div>';
} else {
    print '<div style="margin:10px 0">';
    print '<input type="text" id="bp-filter" placeholder="Filter by ref or name..." style="padding:4px 8px;min-width:240px">';
    print '</div>';
}

print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';

if ($isBranchUser) {
    // Branch users: only show assigned products, no checkboxes
    print '<tr class="liste_titre"><th>Ref</th><th>Label</th><th style="width:120px">Price</th></tr>';
    foreach ($products as $p) {
        if (!isset($assigned[(int) $p->rowid])) continue;
        $t = strtolower($p->ref . ' ' . $p->label);
        print '<tr class="oddeven bpr" data-t="' . dol_escape_htmltag($t) . '">';
        print '<td><strong>' . dol_escape_htmltag($p->ref) . '</strong></td>';
        print '<td>' . dol_escape_htmltag($p->label) . '</td>';
        print '<td>' . price($p->price) . '</td>';
        print '</tr>';
    }
} else {
    // Admins: show all products with checkboxes
    print '<tr class="liste_titre"><th style="width:40px"></th><th>Ref</th><th>Label</th><th style="width:120px">Price</th></tr>';
    foreach ($products as $p) {
        $ck = isset($assigned[(int) $p->rowid]) ? ' checked' : '';
        $t  = strtolower($p->ref . ' ' . $p->label);
        print '<tr class="oddeven bpr" data-t="' . dol_escape_htmltag($t) . '">';
        print '<td><input type="checkbox" class="bp" name="product_ids[]" value="' . (int) $p->rowid . '"' . $ck . '></td>';
        print '<td><strong>' . dol_escape_htmltag($p->ref) . '</strong></td>';
        print '<td>' . dol_escape_htmltag($p->label) . '</td>';
        print '<td>' . price($p->price) . '</td>';
        print '</tr>';
    }
}

print '</table></div>';

// Pagination controls (shown/hidden by the script at the bottom)
print '<div id="bp-pager" style="margin:12px 0;display:none;align-items:center;gap:12px">';
print '<button type="button" class="button" onclick="bpPrev()">← Prev</button>';
print '<span id="bp-pageinfo" style="color:#555"></span>';
print '<button type="button" class="button" onclick="bpNext()">Next →</button>';
print '</div>';

if (!$isBranchUser) {
    print '<br><input type="submit" class="button button-save" value="Save Branch Products">';
}
print '</form>';

print '<script>
(function(){
  var pageSize = 50;
  var current = 1;
  var rows = Array.prototype.slice.call(document.querySelectorAll("tr.bpr"));
  var pager = document.getElementById("bp-pager");
  var info  = document.getElementById("bp-pageinfo");
  var filterBox = document.getElementById("bp-filter");

  function render(){
    var q = (filterBox && filterBox.value ? filterBox.value : "").toLowerCase().trim();
    if (q){
      rows.forEach(function(r){ r.style.display = (r.dataset.t.indexOf(q) >= 0) ? "" : "none"; });
      if (pager) pager.style.display = "none";
      return;
    }
    if (pager) pager.style.display = (rows.length > pageSize) ? "flex" : "none";
    var totalPages = Math.max(1, Math.ceil(rows.length / pageSize));
    if (current > totalPages) current = totalPages;
    if (current < 1) current = 1;
    var start = (current - 1) * pageSize, endi = start + pageSize;
    rows.forEach(function(r, i){ r.style.display = (i >= start && i < endi) ? "" : "none"; });
    if (info) info.textContent = "Page " + current + " of " + totalPages + " — " + rows.length + " products";
  }

  window.bpPrev = function(){ if (current > 1){ current--; render(); } };
  window.bpNext = function(){ var tp = Math.ceil(rows.length / pageSize); if (current < tp){ current++; render(); } };

  if (filterBox){ filterBox.addEventListener("keyup", function(){ current = 1; render(); }); }
  render();
})();
</script>';

print takeposHelpRender($langs, __FILE__);
llxFooter();
$db->close();