<?php
/**
 * TakePOS — Stock adjustments audit page (v2 — redesigned UI).
 *
 * One-screen view of every POS-driven stock-in event (the "Add stock" popup).
 * URL: /takepos/audit/stock_adjustments.php
 *
 * Filters (GET):
 *   date_from   YYYY-MM-DD
 *   date_to     YYYY-MM-DD
 *   product_id  optional product filter
 *   manager     optional manager-login filter
 *   status      'approved' | 'rejected' | 'failed' | '' (all)
 */

require '../../main.inc.php';
require_once __DIR__ . '/../class/TakeposAccess.class.php';
require_once __DIR__ . '/../class/TakeposAudit.class.php';

$langs->loadLangs(array('admin', 'main', 'stocks', 'takepos', 'takeposcustom@takepos'));
restrictedArea($user, 'takepos', 0, '');
TakeposAudit::ensureTable($db);

if (empty($user->admin) && empty($user->rights->takepos->run)) {
    accessforbidden($langs->trans('TakeposAuditLogAccessDenied') ?: 'Access denied.');
}

TakeposAccess::requireFrontendAccess(
    $db,
    $user,
    'takepos.audit.log',
    'takepos.use',
    isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null,
    $langs->trans('TakeposAuditLogAccessDenied') ?: 'Access denied.',
    array('page' => 'audit/stock_adjustments.php')
);

/** $langs->trans returns the key when untranslated; fall back. */
$L = function ($key, $fallback) use ($langs) {
    $v = $langs->trans($key);
    return ($v === $key || $v === '') ? $fallback : $v;
};

/** Pretty-print a machine reason code as a sentence-case label. */
$prettyReason = function ($code) use ($L) {
    $map = array(
        'invalid_manager_credentials' => $L('TakeposStockAdjReasonInvalidCreds',   'Invalid manager credentials'),
        'self_approval'               => $L('TakeposStockAdjReasonSelfApproval',   'Self-approval not allowed'),
        'manager_permission_denied'   => $L('TakeposStockAdjReasonPermDenied',     'Manager lacks required rights'),
        'product_is_service'          => $L('TakeposStockAdjReasonIsService',      'Service product (no stock)'),
        'product_not_found'           => $L('TakeposStockAdjReasonNotFound',       'Product not found'),
        'no_warehouse'                => $L('TakeposStockAdjReasonNoWarehouse',    'No warehouse configured'),
        'bad_qty'                     => $L('TakeposStockAdjReasonBadQty',         'Invalid quantity'),
        'bad_product_id'              => $L('TakeposStockAdjReasonBadProduct',     'Invalid product'),
        'missing_manager_credentials' => $L('TakeposStockAdjReasonMissingCreds',   'Manager credentials missing'),
        'movement_failed'             => $L('TakeposStockAdjReasonMovementFailed', 'Stock movement failed'),
        'reception_failed'            => $L('TakeposStockAdjReasonMovementFailed', 'Stock movement failed'),
    );
    return isset($map[$code]) ? $map[$code] : $code;
};

// --- Read filters
$dateFromRaw = GETPOST('date_from', 'alphanohtml');
$dateToRaw   = GETPOST('date_to',   'alphanohtml');
$productId   = GETPOSTINT('product_id');
$managerFlt  = trim((string) GETPOST('manager', 'alphanohtml'));
$status      = GETPOST('status', 'aZ09');
if (!in_array($status, array('approved', 'rejected', 'failed', ''), true)) {
    $status = '';
}
if ($dateFromRaw === '' && $dateToRaw === '') {
    $dateFromRaw = dol_print_date(dol_now() - 30 * 86400, '%Y-%m-%d');
    $dateToRaw   = dol_print_date(dol_now(),               '%Y-%m-%d');
}

// --- Build SQL
$table = MAIN_DB_PREFIX . 'takepos_audit';
$where = array("entity = " . ((int) $conf->entity));
$statusToCode = array(
    'approved' => 'pos_add_stock_approved',
    'rejected' => 'pos_add_stock_rejected',
    'failed'   => 'pos_add_stock_failed',
);
if ($status !== '' && isset($statusToCode[$status])) {
    $where[] = "event_code = '" . $db->escape($statusToCode[$status]) . "'";
} else {
    $where[] = "event_code IN ('pos_add_stock_approved','pos_add_stock_rejected','pos_add_stock_failed')";
}
if ($dateFromRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFromRaw)) {
    $where[] = "datec >= '" . $db->escape($dateFromRaw) . " 00:00:00'";
}
if ($dateToRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateToRaw)) {
    $where[] = "datec <= '" . $db->escape($dateToRaw) . " 23:59:59'";
}
if ($productId > 0) {
    $where[] = "object_type = 'product' AND object_id = " . ((int) $productId);
}
if ($managerFlt !== '') {
    $where[] = "extra_json LIKE '%\"manager_login\":\"" . $db->escape($managerFlt) . "\"%'";
}

$sql = "SELECT rowid, fk_user, login, terminal, event_code, severity, object_type, object_id, amount_ttc, description, extra_json, datec"
    . " FROM " . $table
    . " WHERE " . implode(' AND ', $where)
    . " ORDER BY datec DESC, rowid DESC"
    . " LIMIT 500";

$resq = $db->query($sql);
$rows = array();
$productIdsToLookup = array();
if ($resq) {
    while ($r = $db->fetch_object($resq)) {
        $rows[] = $r;
        $d = $r->extra_json ? json_decode($r->extra_json, true) : null;
        $hasName = is_array($d) && (!empty($d['product_label']) || !empty($d['product_ref']));
        if (!$hasName && (int) $r->object_id > 0 && $r->object_type === 'product') {
            $productIdsToLookup[(int) $r->object_id] = true;
        }
    }
}

// Backfill product info from llx_product for rows missing it
$productInfo = array();
if (!empty($productIdsToLookup)) {
    $ids = array_keys($productIdsToLookup);
    $sqlP = "SELECT rowid, ref, label FROM " . MAIN_DB_PREFIX . "product WHERE rowid IN (" . implode(',', array_map('intval', $ids)) . ")";
    $resP = $db->query($sqlP);
    if ($resP) {
        while ($p = $db->fetch_object($resP)) {
            $productInfo[(int) $p->rowid] = array(
                'ref'   => (string) $p->ref,
                'label' => (string) $p->label,
            );
        }
    }
}

// Roll-up metrics
$totApproved = 0; $totRejected = 0; $totFailed = 0; $totQtyAdded = 0.0;
foreach ($rows as $r) {
    if ($r->event_code === 'pos_add_stock_approved') {
        $totApproved++;
        $d = $r->extra_json ? json_decode($r->extra_json, true) : null;
        if (is_array($d) && isset($d['qty_added'])) $totQtyAdded += (float) $d['qty_added'];
    } elseif ($r->event_code === 'pos_add_stock_rejected') {
        $totRejected++;
    } elseif ($r->event_code === 'pos_add_stock_failed') {
        $totFailed++;
    }
}

llxHeader('', $L('TakeposStockAdjustmentsTitle', 'Stock adjustments (POS)'));

print '<style>
.tpsa-page{padding:0 8px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
.tpsa-toolbar{display:flex;justify-content:space-between;align-items:center;margin:0 0 16px;flex-wrap:wrap;gap:10px}
.tpsa-toolbar h2{margin:0;font-size:20px;font-weight:600;color:#0f172a}
.tpsa-links{display:flex;gap:6px;flex-wrap:wrap}
.tpsa-cards{display:grid;grid-template-columns:repeat(4,minmax(140px,1fr));gap:12px;margin:0 0 16px}
.tpsa-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;display:flex;flex-direction:column;gap:4px;min-width:0}
.tpsa-card .l{font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.4px}
.tpsa-card .v{font-size:26px;font-weight:600;color:#0f172a;line-height:1.15;font-variant-numeric:tabular-nums}
.tpsa-card.ok    .v{color:#15803d}
.tpsa-card.bad   .v{color:#b91c1c}
.tpsa-card.warn  .v{color:#b45309}
.tpsa-filter{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 14px;margin:0 0 16px}
.tpsa-filter-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr)) auto;gap:10px;align-items:end}
.tpsa-filter-grid label{font-size:11px;font-weight:600;color:#475569;display:block;margin:0 0 4px}
.tpsa-filter-grid input,.tpsa-filter-grid select{width:100%;padding:7px 9px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;background:#fff;box-sizing:border-box;height:34px;color:#0f172a}
.tpsa-filter-grid input:focus,.tpsa-filter-grid select:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 2px rgba(37,99,235,.18)}
.tpsa-filter-actions{display:flex;gap:6px;align-items:end}
.tpsa-btn{padding:7px 14px;border:1px solid transparent;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;height:34px;line-height:1;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;white-space:nowrap;transition:background .12s,border-color .12s}
.tpsa-btn-primary{background:#2563eb;color:#fff;border-color:#1d4ed8}
.tpsa-btn-primary:hover{background:#1d4ed8;color:#fff;text-decoration:none}
.tpsa-btn-secondary{background:#fff;color:#475569;border-color:#cbd5e1}
.tpsa-btn-secondary:hover{background:#f1f5f9;color:#0f172a;text-decoration:none}
.tpsa-table-wrap{background:#fff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden}
.tpsa-table{width:100%;border-collapse:collapse;font-size:13px}
.tpsa-table thead th{background:#f8fafc;color:#475569;font-weight:600;text-transform:uppercase;letter-spacing:.3px;font-size:11px;padding:10px 12px;text-align:left;border-bottom:1px solid #e2e8f0;white-space:nowrap}
.tpsa-table tbody td{padding:10px 12px;border-bottom:1px solid #f1f5f9;color:#0f172a;vertical-align:top}
.tpsa-table tbody tr:last-child td{border-bottom:none}
.tpsa-table tbody tr:hover{background:#f8fafc}
.tpsa-table .num{text-align:right;font-variant-numeric:tabular-nums;font-weight:500}
.tpsa-table .muted{color:#94a3b8}
.tpsa-table .date{white-space:nowrap;color:#475569;font-size:12px;font-variant-numeric:tabular-nums}
.tpsa-table .product a{color:#2563eb;text-decoration:none;font-weight:500}
.tpsa-table .product a:hover{text-decoration:underline}
.tpsa-table .product .ref{display:block;color:#64748b;font-size:11px;font-weight:400;margin-top:1px}
.tpsa-table .user{font-weight:500}
.tpsa-pill{display:inline-block;padding:3px 9px;border-radius:11px;font-size:11px;font-weight:600;letter-spacing:.2px;white-space:nowrap}
.tpsa-pill-approved{background:#dcfce7;color:#166534}
.tpsa-pill-rejected{background:#fee2e2;color:#991b1b}
.tpsa-pill-failed  {background:#fef3c7;color:#92400e}
.tpsa-empty{padding:48px 24px;text-align:center;color:#94a3b8;font-size:13px}
.tpsa-footer-count{margin:10px 4px 0;text-align:right;color:#94a3b8;font-size:11px}
@media (max-width:900px){.tpsa-cards{grid-template-columns:repeat(2,1fr)}.tpsa-filter-grid{grid-template-columns:1fr 1fr}.tpsa-filter-actions{grid-column:1/-1;justify-content:flex-end}}
</style>';

print '<div class="tpsa-page">';

print '<div class="tpsa-toolbar">';
print '<h2>' . dol_escape_htmltag($L('TakeposStockAdjustmentsTitle', 'Stock adjustments (POS)')) . '</h2>';
print '<div class="tpsa-links">';
print '<a class="tpsa-btn tpsa-btn-secondary" href="' . DOL_URL_ROOT . '/takepos/audit/list.php">'
    . dol_escape_htmltag($L('TakeposAuditOpenLog', 'Audit log')) . '</a>';
print '<a class="tpsa-btn tpsa-btn-secondary" href="' . DOL_URL_ROOT . '/takepos/audit/dashboard.php">'
    . dol_escape_htmltag($L('TakeposAuditOpenDashboard', 'Audit dashboard')) . '</a>';
print '<a class="tpsa-btn tpsa-btn-secondary" href="' . DOL_URL_ROOT . '/product/stock/movement_list.php">'
    . dol_escape_htmltag($L('TakeposStockAdjustmentsOpenMovements', 'All stock movements')) . '</a>';
print '</div>';
print '</div>';

// Stat cards
$fmtTotal = number_format($totQtyAdded, 2, '.', ',');
if (strpos($fmtTotal, '.') !== false) $fmtTotal = rtrim(rtrim($fmtTotal, '0'), '.');

print '<div class="tpsa-cards">';
print '<div class="tpsa-card ok"><span class="l">'   . dol_escape_htmltag($L('TakeposStockAdjustmentsApproved', 'Approved')) . '</span><span class="v">' . (int) $totApproved . '</span></div>';
print '<div class="tpsa-card bad"><span class="l">'  . dol_escape_htmltag($L('TakeposStockAdjustmentsRejected', 'Rejected')) . '</span><span class="v">' . (int) $totRejected . '</span></div>';
print '<div class="tpsa-card warn"><span class="l">' . dol_escape_htmltag($L('TakeposStockAdjustmentsFailed',   'Failed'))   . '</span><span class="v">' . (int) $totFailed   . '</span></div>';
print '<div class="tpsa-card"><span class="l">'      . dol_escape_htmltag($L('TakeposStockAdjustmentsTotalQty', 'Total qty added')) . '</span><span class="v">' . dol_escape_htmltag($fmtTotal) . '</span></div>';
print '</div>';

// Filters
print '<form method="get" action="" class="tpsa-filter"><div class="tpsa-filter-grid">';
print '<div><label>' . dol_escape_htmltag($L('TakeposCommonDateFrom', 'Date from')) . '</label><input type="date" name="date_from" value="' . dol_escape_htmltag($dateFromRaw) . '"></div>';
print '<div><label>' . dol_escape_htmltag($L('TakeposCommonDateTo',   'Date to'))   . '</label><input type="date" name="date_to"   value="' . dol_escape_htmltag($dateToRaw)   . '"></div>';
print '<div><label>' . dol_escape_htmltag($L('TakeposStockAdjustmentsProductId', 'Product ID')) . '</label><input type="number" name="product_id" value="' . ($productId > 0 ? (int) $productId : '') . '" placeholder="' . dol_escape_htmltag($L('TakeposStockAdjustmentsAny', 'Any')) . '"></div>';
print '<div><label>' . dol_escape_htmltag($L('TakeposStockAdjustmentsManagerLogin', 'Manager login')) . '</label><input type="text" name="manager" value="' . dol_escape_htmltag($managerFlt) . '" placeholder="' . dol_escape_htmltag($L('TakeposStockAdjustmentsAny', 'Any')) . '"></div>';
print '<div><label>' . dol_escape_htmltag($L('Status', 'Status')) . '</label><select name="status">';
print '<option value=""         ' . ($status === ''         ? 'selected' : '') . '>' . dol_escape_htmltag($L('TakeposStockAdjustmentsAll', 'All')) . '</option>';
print '<option value="approved" ' . ($status === 'approved' ? 'selected' : '') . '>' . dol_escape_htmltag($L('TakeposStockAdjustmentsApproved', 'Approved')) . '</option>';
print '<option value="rejected" ' . ($status === 'rejected' ? 'selected' : '') . '>' . dol_escape_htmltag($L('TakeposStockAdjustmentsRejected', 'Rejected')) . '</option>';
print '<option value="failed"   ' . ($status === 'failed'   ? 'selected' : '') . '>' . dol_escape_htmltag($L('TakeposStockAdjustmentsFailed',   'Failed'))   . '</option>';
print '</select></div>';
print '<div class="tpsa-filter-actions">';
print '<button type="submit" class="tpsa-btn tpsa-btn-primary">' . dol_escape_htmltag($L('Filter', 'Filter')) . '</button>';
print '<a class="tpsa-btn tpsa-btn-secondary" href="?">' . dol_escape_htmltag($L('Reset', 'Reset')) . '</a>';
print '</div>';
print '</div></form>';

// Results table
print '<div class="tpsa-table-wrap"><table class="tpsa-table">';
print '<thead><tr>';
print '<th>' . dol_escape_htmltag($L('TakeposCommonDate',                    'Date'))         . '</th>';
print '<th>' . dol_escape_htmltag($L('Product',                              'Product'))      . '</th>';
print '<th class="num">' . dol_escape_htmltag($L('TakeposCommonQuantity',    'Qty'))          . '</th>';
print '<th>' . dol_escape_htmltag($L('TakeposAuditCashier',                  'Cashier'))      . '</th>';
print '<th>' . dol_escape_htmltag($L('TakeposStockAdjustmentsApprovedBy',    'Approved by'))  . '</th>';
print '<th>' . dol_escape_htmltag($L('TakeposStockAdjustmentsReason',        'Reason'))       . '</th>';
print '<th class="num">' . dol_escape_htmltag($L('TakeposStockAdjustmentsNewStock', 'New stock')) . '</th>';
print '<th>' . dol_escape_htmltag($L('Status',                               'Status'))       . '</th>';
print '</tr></thead><tbody>';

if (empty($rows)) {
    print '<tr><td colspan="8" class="tpsa-empty">'
        . dol_escape_htmltag($L('TakeposStockAdjustmentsNoRows', 'No stock adjustments found for the selected filters.'))
        . '</td></tr>';
}

$fmtNum = function ($n) {
    if ($n === null || $n === '') return '';
    $s = number_format((float) $n, 3, '.', '');
    if (strpos($s, '.') !== false) $s = rtrim(rtrim($s, '0'), '.');
    return $s;
};

foreach ($rows as $r) {
    $extra = $r->extra_json ? json_decode($r->extra_json, true) : null;
    if (!is_array($extra)) $extra = array();

    $qty       = isset($extra['qty_added']) ? (float) $extra['qty_added'] : (isset($extra['qty']) ? (float) $extra['qty'] : 0);
    $newStock  = isset($extra['new_stock']) ? (float) $extra['new_stock'] : null;
    $userReason= isset($extra['reason']) && !in_array((string)$extra['reason'], array('invalid_manager_credentials','self_approval','manager_permission_denied','product_is_service','product_not_found','no_warehouse','bad_qty','bad_product_id','missing_manager_credentials','movement_failed','reception_failed'), true) ? (string) $extra['reason'] : '';
    $machineReason = isset($extra['reason']) ? (string) $extra['reason'] : '';
    $prodRef   = isset($extra['product_ref'])   ? (string) $extra['product_ref']   : '';
    $prodLabel = isset($extra['product_label']) ? (string) $extra['product_label'] : '';
    $managerLg = isset($extra['manager_login']) ? (string) $extra['manager_login'] : '';

    // Backfill product info from llx_product if missing
    $pid = (int) $r->object_id;
    if (($prodRef === '' && $prodLabel === '') && $pid > 0 && isset($productInfo[$pid])) {
        $prodRef   = $productInfo[$pid]['ref'];
        $prodLabel = $productInfo[$pid]['label'];
    }

    // Product cell
    $productCell = '';
    if ($pid > 0 && $r->object_type === 'product') {
        $pUrl = DOL_URL_ROOT . '/product/card.php?id=' . $pid;
        if ($prodLabel === '' && $prodRef === '') {
            $productCell = '<a href="' . dol_escape_htmltag($pUrl) . '" target="_blank">#' . $pid . '</a>';
        } else {
            $mainLabel = $prodLabel !== '' ? $prodLabel : $prodRef;
            $secondary = $prodRef !== '' && $prodLabel !== '' ? $prodRef : '';
            $productCell = '<a href="' . dol_escape_htmltag($pUrl) . '" target="_blank">' . dol_escape_htmltag($mainLabel) . '</a>';
            if ($secondary !== '') {
                $productCell .= '<span class="ref">' . dol_escape_htmltag($secondary) . '</span>';
            }
        }
    } else {
        $productCell = '<span class="muted">—</span>';
    }

    // Reason cell logic:
    //   approved → show the cashier's reason
    //   rejected/failed → show the pretty machine reason
    $reasonText = '';
    if ($r->event_code === 'pos_add_stock_approved') {
        $reasonText = $userReason;
    } else {
        $reasonText = $prettyReason($machineReason);
    }

    // Approved-by cell:
    //   approved → manager login (bold)
    //   rejected → attempted login in muted color (so reviewer knows who tried)
    //   failed   → manager login if any
    $approvedByCell = '<span class="muted">—</span>';
    if ($managerLg !== '') {
        if ($r->event_code === 'pos_add_stock_approved') {
            $approvedByCell = dol_escape_htmltag($managerLg);
        } else {
            $approvedByCell = '<span class="muted">' . dol_escape_htmltag($managerLg) . '</span>';
        }
    }

    // Status pill
    if ($r->event_code === 'pos_add_stock_approved') {
        $statusPill = '<span class="tpsa-pill tpsa-pill-approved">' . dol_escape_htmltag($L('TakeposStockAdjustmentsApproved', 'Approved')) . '</span>';
    } elseif ($r->event_code === 'pos_add_stock_rejected') {
        $statusPill = '<span class="tpsa-pill tpsa-pill-rejected">' . dol_escape_htmltag($L('TakeposStockAdjustmentsRejected', 'Rejected')) . '</span>';
    } else {
        $statusPill = '<span class="tpsa-pill tpsa-pill-failed">'   . dol_escape_htmltag($L('TakeposStockAdjustmentsFailed',   'Failed'))   . '</span>';
    }

    print '<tr>';
    print '<td class="date">' . dol_escape_htmltag($r->datec) . '</td>';
    print '<td class="product">' . $productCell . '</td>';
    print '<td class="num">' . dol_escape_htmltag($fmtNum($qty)) . '</td>';
    print '<td class="user">' . dol_escape_htmltag($r->login ?: ('#' . (int) $r->fk_user)) . '</td>';
    print '<td>' . $approvedByCell . '</td>';
    print '<td>' . ($reasonText !== '' ? dol_escape_htmltag($reasonText) : '<span class="muted">—</span>') . '</td>';
    print '<td class="num">' . ($newStock !== null ? dol_escape_htmltag($fmtNum($newStock)) : '<span class="muted">—</span>') . '</td>';
    print '<td>' . $statusPill . '</td>';
    print '</tr>';
}

print '</tbody></table></div>';

print '<div class="tpsa-footer-count">'
    . dol_escape_htmltag(sprintf($L('TakeposStockAdjustmentsRowCount', '%d entries shown (max 500).'), count($rows)))
    . '</div>';

print '</div>'; // .tpsa-page

llxFooter();
$db->close();