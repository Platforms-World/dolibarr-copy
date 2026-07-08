<?php
/**
 * branch_detail.php — Branch detail page
 * Shows terminal, products, invoices and operations for a single branch.
 */

require '../../main.inc.php';
require_once __DIR__ . '/../lib/takepos_help.php';
require_once __DIR__ . '/../lib/takepos_lang.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/../class/TakeposAccess.class.php';
require_once __DIR__ . '/../class/TakeposBranchService.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';

$langs->loadLangs(['admin', 'main', 'cashdesk', 'bills', 'takeposcustom@takepos']);

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

$entity    = !empty($user->entity) ? (int) $user->entity : 1;
$branchId  = GETPOSTINT('branch_id') ?: GETPOSTINT('id');

if ($branchId <= 0) {
    header('Location: branches.php');
    exit;
}

$branch = TakeposBranchService::getBranch($db, $entity, $branchId);
if (!$branch) {
    header('Location: branches.php');
    exit;
}

$branchUserId = !empty($branch->fk_user) ? (int)$branch->fk_user : 0;

$msg     = '';
$msgType = 'mesgs';

// ── Actions ───────────────────────────────────────────────────────────────────
$action = GETPOST('action', 'aZ09');

if (!empty($action) && GETPOST('token', 'alpha') !== $_SESSION['newtoken']) {
    $msg     = 'Invalid security token.';
    $msgType = 'errors';
} elseif ($action === 'add_terminal') {
    try {
        $terminalId = TakeposBranchService::createStarterTerminal($db, $entity, $branchId, $branch->code, (int)$branch->fk_store);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?branch_id=' . $branchId . '&msg=terminal_added');
        exit;
    } catch (Throwable $e) {
        $msg     = $e->getMessage();
        $msgType = 'errors';
    }
} elseif ($action === 'delete_terminal') {
    $termId = GETPOSTINT('terminal_id');
    if ($termId > 0) {
        $chk = $db->query("SELECT rowid FROM " . MAIN_DB_PREFIX . "takepos_terminal WHERE rowid=" . $termId . " AND fk_branch=" . $branchId);
        if ($chk && $db->num_rows($chk) > 0) {
            $db->query("DELETE FROM " . MAIN_DB_PREFIX . "const WHERE name LIKE '%_" . $termId . "'");
            $db->query("DELETE FROM " . MAIN_DB_PREFIX . "takepos_terminal WHERE rowid=" . $termId);
            header('Location: ' . $_SERVER['PHP_SELF'] . '?branch_id=' . $branchId . '&msg=terminal_deleted');
            exit;
        } else {
            $msg     = $langs->trans('TakeposBranchTerminalDeleteError');
            $msgType = 'errors';
        }
    }
}

// ── Render ────────────────────────────────────────────────────────────────────
require_once DOL_DOCUMENT_ROOT . '/core/lib/takepos.lib.php';
$head = takepos_admin_prepare_head();
llxHeader('', $langs->trans('TakeposBranchDetailTitle').': '.dol_escape_htmltag($branch->label));
print dol_get_fiche_head($head, 'branches', 'TakePOS', -1, 'cash-register');
print dol_get_fiche_end();

// Back button
print '<div style="margin-bottom:16px">';
print '<a href="branches.php" class="button">← Back to Branches</a>';
print '</div>';

if ($msg !== '') setEventMessages($msg, null, $msgType);
if (GETPOST('msg') === 'terminal_added') setEventMessages($langs->trans('TakeposBranchTerminalAdded'), null, 'mesgs');
if (GETPOST('msg') === 'terminal_deleted') setEventMessages($langs->trans('TakeposBranchTerminalDeleted'), null, 'mesgs');

// ── Branch info ───────────────────────────────────────────────────────────────
print '<div style="border:1px solid #ddd;border-radius:6px;padding:16px 20px;margin-bottom:20px;background:#f9f9f9">';
print '<h2 style="margin:0 0 12px">🏪 ' . dol_escape_htmltag($branch->label) . '</h2>';
print '<table style="border-collapse:collapse">';
print '<tr><td style="padding:4px 16px 4px 0;color:#555;width:140px">Branch ID</td><td><strong>' . (int) $branch->rowid . '</strong></td></tr>';
print '<tr><td style="padding:4px 16px 4px 0;color:#555">Code</td><td><code style="background:#eee;padding:2px 6px;border-radius:3px">' . dol_escape_htmltag($branch->code) . '</code></td></tr>';
print '<tr><td style="padding:4px 16px 4px 0;color:#555">Login</td><td><code style="background:#eee;padding:2px 6px;border-radius:3px">' . dol_escape_htmltag($branch->branch_login ?: '—') . '</code></td></tr>';
print '<tr><td style="padding:4px 16px 4px 0;color:#555">Status</td><td>' . ((int)$branch->active === 1 ? '<span style="color:#1aab8c;font-weight:bold">✓ Active</span>' : '<span style="color:#c00;font-weight:bold">✗ Disabled</span>') . '</td></tr>';
if (!empty($branch->description)) {
    print '<tr><td style="padding:4px 16px 4px 0;color:#555">Description</td><td>' . dol_escape_htmltag($branch->description) . '</td></tr>';
}
print '</table>';
print '</div>';

// ── Terminal ──────────────────────────────────────────────────────────────────
print '<h3 style="border-bottom:2px solid #1aab8c;padding-bottom:6px">🖥️ Terminals</h3>';

$tcCheck = $db->query("SHOW TABLES LIKE '" . $db->escape(MAIN_DB_PREFIX . 'takepos_terminal') . "'");
if ($tcCheck && $db->num_rows($tcCheck) > 0) {
    $termRes = $db->query(
        "SELECT rowid, terminal_code, COALESCE(NULLIF(label,''), terminal_code) AS label, active"
        . " FROM " . MAIN_DB_PREFIX . "takepos_terminal"
        . " WHERE fk_branch=" . $branchId
    );
    if ($termRes && $db->num_rows($termRes) > 0) {
        print '<table class="noborder centpercent" style="margin-bottom:12px">';
        print '<tr class="liste_titre"><th>Terminal ID</th><th>Code</th><th>Label</th><th>Status</th><th>Setup</th><th>Delete</th></tr>';
        while ($t = $db->fetch_object($termRes)) {
            $setupUrl = DOL_URL_ROOT . '/takepos/admin/terminal.php?terminal=' . (int)$t->rowid;
            print '<tr class="oddeven">';
            print '<td>' . (int)$t->rowid . '</td>';
            print '<td><code>' . dol_escape_htmltag($t->terminal_code) . '</code></td>';
            print '<td>' . dol_escape_htmltag($t->label) . '</td>';
            print '<td>' . ((int)$t->active === 1 ? '<span style="color:#1aab8c">'.$langs->trans('TakeposBranchTerminalActive').'</span>' : '<span style="color:#c00">'.$langs->trans('TakeposBranchTerminalInactive').'</span>') . '</td>';
            print '<td><a href="' . dol_escape_htmltag($setupUrl) . '" class="button">⚙️ Setup</a></td>';
            print '<td>';
            print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '" onsubmit="return confirm(\'Delete this terminal permanently?\')">';
            print '<input type="hidden" name="token" value="' . newToken() . '">';
            print '<input type="hidden" name="action" value="delete_terminal">';
            print '<input type="hidden" name="branch_id" value="' . $branchId . '">';
            print '<input type="hidden" name="terminal_id" value="' . (int)$t->rowid . '">';
            print '<input type="submit" class="button button-cancel" style="background:#c0392b;color:#fff" value="🗑 ' . $langs->trans('TakeposBranchTerminalDelete') . '">';
            print '</form>';
            print '</td>';
            print '</tr>';
        }
        print '</table>';
    } else {
        print '<p style="color:#888;margin-bottom:12px">'.$langs->trans('TakeposBranchTerminalNone').'</p>';
    }

    // Add new terminal button
    print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="add_terminal">';
    print '<input type="hidden" name="branch_id" value="' . $branchId . '">';
    print '<input type="submit" class="button" style="background:#1aab8c;color:#fff;margin-bottom:20px" value="' . $langs->trans('TakeposBranchTerminalAdd') . '">';
    print '</form>';
} else {
    print '<p style="color:#888;margin-bottom:20px">Terminal table not found.</p>';
}

// ── Products ──────────────────────────────────────────────────────────────────
print '<h3 style="border-bottom:2px solid #1aab8c;padding-bottom:6px">📦 Assigned Products</h3>';

$bpCheck = $db->query("SHOW TABLES LIKE '" . $db->escape(MAIN_DB_PREFIX . 'takepos_branch_product') . "'");
if ($bpCheck && $db->num_rows($bpCheck) > 0) {
    $prodRes = $db->query(
        "SELECT p.rowid, p.ref, p.label, p.price, p.tosell"
        . " FROM " . MAIN_DB_PREFIX . "takepos_branch_product bp"
        . " INNER JOIN " . MAIN_DB_PREFIX . "product p ON p.rowid = bp.fk_product"
        . " WHERE bp.fk_branch = " . $branchId
        . " AND p.entity = " . $entity
        . " ORDER BY p.ref ASC"
        . " LIMIT 100"
    );
    if ($prodRes && $db->num_rows($prodRes) > 0) {
        print '<table class="noborder centpercent" style="margin-bottom:20px">';
        print '<tr class="liste_titre"><th>Ref</th><th>Label</th><th>Price</th><th>For Sale</th></tr>';
        while ($p = $db->fetch_object($prodRes)) {
            print '<tr class="oddeven">';
            print '<td><code>' . dol_escape_htmltag($p->ref) . '</code></td>';
            print '<td>' . dol_escape_htmltag($p->label) . '</td>';
            print '<td>' . price($p->price) . '</td>';
            print '<td>' . ((int)$p->tosell === 1 ? '✓' : '—') . '</td>';
            print '</tr>';
        }
        print '</table>';
        print '<p><a href="' . dol_escape_htmltag(DOL_URL_ROOT . '/takepos/admin/branch_products.php?branch_id=' . $branchId) . '" class="button">📦 Manage Products</a></p>';
    } else {
        print '<p style="color:#888">'.$langs->trans('TakeposBranchNoProducts').'</p>';
        print '<p><a href="' . dol_escape_htmltag(DOL_URL_ROOT . '/takepos/admin/branch_products.php?branch_id=' . $branchId) . '" class="button">📦 Assign Products</a></p>';
    }
} else {
    print '<p style="color:#888;margin-bottom:20px">Branch products table not found.</p>';
}

// ── Recent Invoices ───────────────────────────────────────────────────────────
print '<h3 style="border-bottom:2px solid #1aab8c;padding-bottom:6px">🧾 Recent Invoices</h3>';

if ($branchUserId > 0) {
    // Get branch terminal IDs for filtering
    $branchTermIds = [];
    $btRes = $db->query("SELECT rowid FROM " . MAIN_DB_PREFIX . "takepos_terminal WHERE fk_branch=" . $branchId);
    if ($btRes) { while ($o = $db->fetch_object($btRes)) { $branchTermIds[] = (int)$o->rowid; } }

    if (!empty($branchTermIds)) {
        $termInSql = implode(',', $branchTermIds);
        $invRes = $db->query(
            "SELECT f.rowid, f.ref, f.datef, f.total_ttc, f.paye,"
            . " CONCAT(u.firstname, ' ', u.lastname) AS cashier_name,"
            . " COALESCE(t.label, isl.terminal_code, '—') AS terminal_label"
            . " FROM " . MAIN_DB_PREFIX . "facture f"
            . " LEFT JOIN " . MAIN_DB_PREFIX . "takepos_invoice_shift isl ON isl.fk_invoice = f.rowid"
            . " LEFT JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid = isl.fk_cashier_user"
            . " LEFT JOIN " . MAIN_DB_PREFIX . "takepos_terminal t ON t.rowid = isl.fk_terminal"
            . " WHERE f.fk_user_author = " . $branchUserId
            . " AND f.entity = " . $entity
            . " ORDER BY f.rowid DESC LIMIT 50"        );
    } else {
        $invRes = $db->query(
            "SELECT f.rowid, f.ref, f.datef, f.total_ttc, f.paye,"
            . " '' AS cashier_name, '' AS terminal_label"
            . " FROM " . MAIN_DB_PREFIX . "facture f"
            . " WHERE f.fk_user_author = " . $branchUserId
            . " AND f.entity = " . $entity
            . " ORDER BY f.rowid DESC LIMIT 50"
        );
    }

    if ($invRes && $db->num_rows($invRes) > 0) {
        print '<table class="noborder centpercent" style="margin-bottom:20px">';
        print '<tr class="liste_titre"><th>Ref</th><th>Date</th><th>Total</th><th>Status</th><th>Terminal</th><th>Cashier</th><th>View</th></tr>';
        while ($inv = $db->fetch_object($invRes)) {
            $statusLabel = ((int)$inv->paye === 1) ? '<span style="color:#1aab8c">'.$langs->trans('TakeposBranchInvPaid').'</span>' : '<span style="color:#f0a500">'.$langs->trans('TakeposBranchInvUnpaid').'</span>';
            $invUrl = DOL_URL_ROOT . '/compta/facture/card.php?id=' . (int)$inv->rowid;
            $cashier  = trim($inv->cashier_name) ?: '—';
            $terminal = trim($inv->terminal_label) ?: '—';
            print '<tr class="oddeven">';
            print '<td><strong>' . dol_escape_htmltag($inv->ref) . '</strong></td>';
            print '<td>' . dol_print_date($db->jdate($inv->datef), 'day') . '</td>';
            print '<td>' . price($inv->total_ttc) . '</td>';
            print '<td>' . $statusLabel . '</td>';
            print '<td>' . dol_escape_htmltag($terminal) . '</td>';
            print '<td>' . dol_escape_htmltag($cashier) . '</td>';
            print '<td><a href="' . dol_escape_htmltag($invUrl) . '" target="_blank" class="button">View</a></td>';
            print '</tr>';
        }
        print '</table>';
    } else {
        print '<p style="color:#888;margin-bottom:20px">'.$langs->trans('TakeposBranchNoInvoices').'</p>';
    }
} else {
    print '<p style="color:#888;margin-bottom:20px">'.$langs->trans('TakeposBranchNoUserLinked').'</p>';
}

// ── Sales Summary ─────────────────────────────────────────────────────────────
print '<h3 style="border-bottom:2px solid #1aab8c;padding-bottom:6px">📊 Sales Summary</h3>';

if ($branchUserId > 0) {
    $summRes = $db->query(
        "SELECT COUNT(*) AS nb_invoices,"
        . " SUM(total_ttc) AS total_sales,"
        . " SUM(CASE WHEN paye=1 THEN total_ttc ELSE 0 END) AS total_paid,"
        . " SUM(CASE WHEN paye=0 THEN total_ttc ELSE 0 END) AS total_unpaid"
        . " FROM " . MAIN_DB_PREFIX . "facture"
        . " WHERE fk_user_author = " . $branchUserId
        . " AND entity = " . $entity
    );
    if ($summRes) {
        $summ = $db->fetch_object($summRes);
        print '<div style="display:flex;gap:20px;flex-wrap:wrap;margin-bottom:20px">';
        $cards = [
            [$langs->trans('TakeposBranchSalesTotal'), (int)$summ->nb_invoices, '#1aab8c'],
            [$langs->trans('TakeposBranchSalesTotalSales'), price($summ->total_sales ?: 0), '#2980b9'],
            [$langs->trans('TakeposBranchSalesTotalPaid'), price($summ->total_paid ?: 0), '#27ae60'],
            [$langs->trans('TakeposBranchSalesTotalUnpaid'), price($summ->total_unpaid ?: 0), '#e74c3c'],
        ];
        foreach ($cards as $card) {
            print '<div style="flex:1;min-width:140px;border:1px solid ' . $card[2] . ';border-radius:8px;padding:14px 18px;text-align:center">';
            print '<div style="font-size:1.6em;font-weight:bold;color:' . $card[2] . '">' . $card[1] . '</div>';
            print '<div style="color:#666;font-size:0.85em;margin-top:4px">' . $card[0] . '</div>';
            print '</div>';
        }
        print '</div>';
    }
} else {
    print '<p style="color:#888;margin-bottom:20px">'.$langs->trans('TakeposBranchNoUserLinked').'</p>';
}

print takeposHelpRender($langs, __FILE__);
llxFooter();
$db->close();