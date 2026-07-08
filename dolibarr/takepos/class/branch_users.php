<?php
/**
 * Branch User Management – Dolibarr Admin Page
 *
 * Assigns existing Dolibarr users to branches with roles:
 *   cashier  – can open POS on terminals linked to this branch
 *   manager  – POS + branch reports
 *   viewer   – read-only reports
 *
 * Access: TakePOS admin (takepos.store_governance) only.
 * No subscription or plan controls are exposed here.
 */

require '../../main.inc.php';
require_once __DIR__ . '/../lib/takepos_help.php';
require_once __DIR__ . '/../lib/takepos_lang.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/../class/TakeposAccess.class.php';
require_once __DIR__ . '/../class/TakeposBranchService.class.php';

$langs->loadLangs(['admin', 'users', 'takeposcustom@takepos']);

restrictedArea($user, 'takepos', 0, '');
TakeposAccess::requireAdminAccess(
    $db, $user,
    'takepos.store_governance',
    'takepos.store.manage',
    isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null,
    'Branch user admin access required.'
);

$entity   = !empty($user->entity) ? (int) $user->entity : 1;
$branchId = GETPOSTINT('branch_id') ?: GETPOSTINT('bid');
$action   = GETPOST('action', 'aZ09');
$msg      = '';
$msgType  = 'mesgs';

try {
    if (!empty($action) && GETPOST('token', 'alpha') !== $_SESSION['newtoken']) {
        throw new Exception('Invalid security token.');
    }

    if ($action === 'assign_user') {
        TakeposBranchService::assignUserToBranch(
            $db, $user, $entity,
            GETPOSTINT('branch_id'),
            GETPOSTINT('target_user_id'),
            GETPOST('role', 'aZ09')
        );
        $branchId = GETPOSTINT('branch_id');
        $msg      = 'User assigned to branch.';
    }

    if ($action === 'remove_user') {
        TakeposBranchService::removeUserFromBranch(
            $db, $user, $entity,
            GETPOSTINT('branch_id'),
            GETPOSTINT('target_user_id')
        );
        $branchId = GETPOSTINT('branch_id');
        $msg      = 'User removed from branch.';
    }
} catch (Throwable $e) {
    $msg     = $e->getMessage();
    $msgType = 'errors';
}

$branches = TakeposBranchService::listBranches($db, $entity, true);

// All active Dolibarr users in this entity
$allUsers = [];
$sqlU = "SELECT rowid, login, firstname, lastname, email"
      . " FROM " . MAIN_DB_PREFIX . "user"
      . " WHERE entity IN (0," . $entity . ") AND statut=1"
      . " ORDER BY login ASC";
$resU = $db->query($sqlU);
if ($resU) {
    while ($obj = $db->fetch_object($resU)) {
        $allUsers[] = $obj;
    }
}

$selectedBranch = null;
$branchUsers    = [];
if ($branchId > 0) {
    $selectedBranch = TakeposBranchService::getBranch($db, $entity, $branchId);
    if ($selectedBranch) {
        $branchUsers = TakeposBranchService::getBranchUsers($db, $entity, $branchId);
    }
}

// ── Render ───────────────────────────────────────────────────────────────────
llxHeader('', 'Branch User Management');
print load_fiche_titre('Branch User Management');

print '<div class="tabsAction">';
print '<a class="butAction" href="branches.php">← Back to Branches</a>';
print '</div>';

if ($msg !== '') {
    setEventMessages($msg, null, $msgType);
}

// ── Branch selector ───────────────────────────────────────────────────────────
print '<form method="GET" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '" style="margin-bottom:20px">';
print '<label><strong>Select Branch:</strong> </label>';
print '<select name="branch_id" onchange="this.form.submit()" style="min-width:220px">';
print '<option value="">— Choose a branch —</option>';
foreach ($branches as $b) {
    $sel = ((int) $b->rowid === (int) $branchId ? ' selected' : '');
    print '<option value="' . ((int) $b->rowid) . '"' . $sel . '>'
          . dol_escape_htmltag($b->code . ' – ' . $b->label) . '</option>';
}
print '</select></form>';

if (!$selectedBranch && $branchId > 0) {
    setEventMessages('Branch not found or inactive.', null, 'errors');
}

if ($selectedBranch) {
    print '<h3 style="margin-top:0">Branch: <strong>'
          . dol_escape_htmltag($selectedBranch->code . ' – ' . $selectedBranch->label)
          . '</strong></h3>';

    // ── Assign user form ──────────────────────────────────────────────────────
    $assignedIds = array_map(fn($u) => (int) $u->fk_user, $branchUsers);
    $available   = array_filter($allUsers, fn($u) => !in_array((int) $u->rowid, $assignedIds, true));

    if (!empty($available)) {
        print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF'])
              . '?branch_id=' . (int) $branchId . '">';
        print '<input type="hidden" name="token" value="' . newToken() . '">';
        print '<input type="hidden" name="action" value="assign_user">';
        print '<input type="hidden" name="branch_id" value="' . (int) $branchId . '">';

        print '<table class="border" style="width:auto;margin-bottom:10px">';
        print '<tr class="liste_titre"><th colspan="3">Assign User to this Branch</th></tr>';
        print '<tr>';
        print '<td style="padding:8px">User</td>';
        print '<td style="padding:8px"><select name="target_user_id" required style="min-width:220px">';
        print '<option value="">— Select user —</option>';
        foreach ($available as $u) {
            $display = dol_escape_htmltag($u->login . ' – ' . trim($u->firstname . ' ' . $u->lastname));
            print '<option value="' . ((int) $u->rowid) . '">' . $display . '</option>';
        }
        print '</select></td>';
        print '<td style="padding:8px"><select name="role" style="min-width:160px">';
        $roles = ['cashier' => '🖥️ Cashier', 'manager' => '👔 Branch Manager', 'viewer' => '👁️ Viewer (Read-only)'];
        foreach ($roles as $rCode => $rLabel) {
            print '<option value="' . $rCode . '">' . $rLabel . '</option>';
        }
        print '</select></td>';
        print '</tr></table>';
        print '<input type="submit" class="button button-save" value="Assign User">';
        print '</form><br>';
    } else {
        print '<div class="info" style="margin-bottom:15px">All available users are already assigned to this branch.</div>';
    }

    // ── Assigned users list ───────────────────────────────────────────────────
    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th>Login</th><th>Full Name</th><th>Email</th><th>Role</th><th>Remove</th>';
    print '</tr>';

    if (empty($branchUsers)) {
        print '<tr><td colspan="5" style="text-align:center;padding:20px">'
              . '<em>No users assigned to this branch yet.</em></td></tr>';
    }

    $roleLabels = ['cashier' => '🖥️ Cashier', 'manager' => '👔 Manager', 'viewer' => '👁️ Viewer'];

    foreach ($branchUsers as $bu) {
        print '<tr class="oddeven">';
        print '<td><strong>' . dol_escape_htmltag($bu->login) . '</strong></td>';
        print '<td>' . dol_escape_htmltag(trim($bu->firstname . ' ' . $bu->lastname)) . '</td>';
        print '<td>' . dol_escape_htmltag($bu->email) . '</td>';
        print '<td>' . ($roleLabels[$bu->role] ?? dol_escape_htmltag($bu->role)) . '</td>';
        print '<td>';
        print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF'])
              . '?branch_id=' . (int) $branchId
              . '" onsubmit="return confirm(\'Remove this user from the branch?\')">';
        print '<input type="hidden" name="token" value="' . newToken() . '">';
        print '<input type="hidden" name="action" value="remove_user">';
        print '<input type="hidden" name="branch_id" value="' . (int) $branchId . '">';
        print '<input type="hidden" name="target_user_id" value="' . ((int) $bu->fk_user) . '">';
        print '<input type="submit" class="button button-cancel" value="Remove">';
        print '</form>';
        print '</td>';
        print '</tr>';
    }

    print '</table></div>';

    // Role legend
    print '<br><div style="font-size:0.9em;color:#666;padding:8px 0">';
    print '<strong>Role guide:</strong> ';
    print '🖥️ <em>Cashier</em> – can open POS on terminals linked to this branch only. ';
    print '👔 <em>Manager</em> – POS access + branch reports and cash management. ';
    print '👁️ <em>Viewer</em> – read-only access to branch reports.';
    print '</div>';
}

print takeposHelpRender($langs, __FILE__);
llxFooter();
$db->close();
