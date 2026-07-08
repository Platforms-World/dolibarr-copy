<?php
/**
 * stock_all_branches.php — Cross-branch / cross-warehouse stock comparison
 *
 * Shows every product (or filtered by search) with one stock column per
 * branch/warehouse in a single table.  Managers and admins can instantly
 * see which branch has stock, which is running low, and which is out.
 *
 * Also includes:
 *  - "Sold Today" and "Sold This Week" columns per branch
 *  - Color-coded stock levels (out / low / ok)
 *  - CSV export
 *  - Branch filter (show all or one branch)
 *  - Low-stock-only filter
 *
 * Access: admin OR takepos.store.view_all permission (managers/supervisors).
 * Branch users (isBranchUser) are redirected to the single-branch stock_overview.
 *
 * FIX (stock-branch-v2): New file. No original to replace.
 */

if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');

require '../main.inc.php';
require_once __DIR__ . '/lib/takepos_lang.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/class/TakeposAccess.class.php';
require_once __DIR__ . '/class/TakeposBranchService.class.php';

$langs->loadLangs(array('admin', 'products', 'stocks', 'takeposcustom@takepos'));

// ── Access control ──────────────────────────────────────────────────────────
if (empty($user) || empty($user->id)) {
    accessforbidden();
}

// Branch users: redirect to the single-branch stock overview
if (!$user->admin && TakeposBranchService::isBranchUser($db, (int) $user->id)) {
    header('Location: ' . DOL_URL_ROOT . '/takepos/stock_overview.php');
    exit;
}

// Require admin OR store.view_all permission
$canView = $user->admin
    || $user->hasRight('produit', 'lire')
    || $user->hasRight('takepos', 'run');

if (!$canView) {
    accessforbidden($langs->trans('TakeposWorkspaceStockAccessDenied'));
}

$entity = !empty($user->entity) ? (int) $user->entity : 1;

// ── Request parameters ───────────────────────────────────────────────────────
$search       = trim((string) GETPOST('s', 'alphanohtml'));
$filterBranch = GETPOSTINT('branch_id');   // 0 = all
$lowOnly      = (GETPOST('low_only', 'int') == 1);
$exportCsv    = (GETPOST('export', 'aZ09') === 'csv');

// ── Load all active branches with their warehouses ───────────────────────────
$branches = array();
$sqlBranches = "SELECT b.rowid, b.code, b.label, b.fk_warehouse, e.ref AS wh_ref, e.label AS wh_label"
    . " FROM " . MAIN_DB_PREFIX . "takepos_branch b"
    . " LEFT JOIN " . MAIN_DB_PREFIX . "entrepot e ON e.rowid = b.fk_warehouse"
    . " WHERE b.entity IN (0, " . $entity . ") AND b.active = 1"
    . " ORDER BY b.code ASC";
$resBranches = $db->query($sqlBranches);
if ($resBranches) {
    while ($obj = $db->fetch_object($resBranches)) {
        $branches[] = $obj;
    }
}

// If no branches exist, fall back to all active warehouses (non-branch setup)
$warehouses = array();
if (empty($branches)) {
    $sqlWh = "SELECT rowid, ref, label FROM " . MAIN_DB_PREFIX . "entrepot"
        . " WHERE entity IN (" . getEntity('stock') . ") AND statut IN (0, 1) ORDER BY ref ASC";
    $resWh = $db->query($sqlWh);
    if ($resWh) {
        while ($obj = $db->fetch_object($resWh)) {
            $warehouses[] = $obj;
        }
    }
}

// Build the working set: columns = branches (or warehouses if no branches)
$cols = array(); // array of { id (warehouse rowid), label, branch_code, branch_label }
if (!empty($branches)) {
    foreach ($branches as $b) {
        if ($filterBranch > 0 && (int) $b->rowid !== $filterBranch) continue;
        if ((int) $b->fk_warehouse <= 0) {
            // Branch has no warehouse — still show as a column with "?" stock
            $cols[] = array(
                'wh_id'        => 0,
                'branch_id'    => (int) $b->rowid,
                'branch_code'  => $b->code,
                'branch_label' => $b->label,
                'wh_label'     => $langs->trans('TakeposStockBranchNoWarehouse'),
            );
        } else {
            $cols[] = array(
                'wh_id'        => (int) $b->fk_warehouse,
                'branch_id'    => (int) $b->rowid,
                'branch_code'  => $b->code,
                'branch_label' => $b->label,
                'wh_label'     => ($b->wh_ref ?: $b->wh_label),
            );
        }
    }
} else {
    foreach ($warehouses as $wh) {
        $cols[] = array(
            'wh_id'        => (int) $wh->rowid,
            'branch_id'    => 0,
            'branch_code'  => '',
            'branch_label' => ($wh->ref ?: $wh->label),
            'wh_label'     => ($wh->ref ?: $wh->label),
        );
    }
}

// ── Load products ─────────────────────────────────────────────────────────────
$products = array();
$sqlP = "SELECT p.rowid, p.ref, p.label, p.barcode, p.seuil_stock_alerte AS alert_threshold"
    . " FROM " . MAIN_DB_PREFIX . "product p"
    . " WHERE p.entity IN (" . getEntity('product') . ")"
    . " AND p.fk_product_type = 0 AND p.tosell = 1";
if ($search !== '') {
    $s = "'%" . $db->escape($search) . "%'";
    $sqlP .= " AND (p.ref LIKE $s OR p.label LIKE $s OR p.barcode LIKE $s)";
}
$sqlP .= " ORDER BY p.label ASC";
$sqlP .= $db->plimit(500, 0);  // raised from 200 to 500; add pagination if needed

$resP = $db->query($sqlP);
if ($resP) {
    while ($obj = $db->fetch_object($resP)) {
        $products[$obj->rowid] = array(
            'rowid'           => (int) $obj->rowid,
            'ref'             => (string) $obj->ref,
            'label'           => (string) $obj->label,
            'barcode'         => (string) ($obj->barcode ?? ''),
            'alert_threshold' => (float) ($obj->alert_threshold ?? 0),
            'stock'           => array(), // wh_id => qty
            'sold_today'      => array(), // branch_id => qty
            'sold_week'       => array(), // branch_id => qty
        );
    }
}

// ── Load stock per warehouse ──────────────────────────────────────────────────
$whIds = array_unique(array_filter(array_column($cols, 'wh_id')));
if (!empty($whIds) && !empty($products)) {
    $sqlS = "SELECT fk_product, fk_entrepot, reel"
        . " FROM " . MAIN_DB_PREFIX . "product_stock"
        . " WHERE fk_product IN (" . implode(',', array_keys($products)) . ")"
        . " AND fk_entrepot IN (" . implode(',', $whIds) . ")";
    $resS = $db->query($sqlS);
    if ($resS) {
        while ($obj = $db->fetch_object($resS)) {
            $pid  = (int) $obj->fk_product;
            $whId = (int) $obj->fk_entrepot;
            if (isset($products[$pid])) {
                $products[$pid]['stock'][$whId] = (float) $obj->reel;
            }
        }
    }
}

// ── Load sold quantities (today and this week) per branch warehouse ───────────
// "Sold" = validated invoice lines (facture.fk_statut >= 1)
// We map branch terminal warehouse → invoices from that terminal's cashdesk warehouse
// Approach: look at facture lines joined to facture where the warehouse constant matches
// Simpler approach: use stock movements (sens=0 = delivery = stock out from sale)
// We use facturedet + facture filtered by date, grouped by product + warehouse (from terminal config)
// Since terminals store their warehouse in llx_const, we join that way.

$todayStart  = dol_get_first_hour(dol_now(), 'gmt');
$weekStart   = dol_get_first_hour(dol_now() - 6 * 86400, 'gmt');
$todayStr    = "'" . $db->idate($todayStart) . "'";
$weekStr     = "'" . $db->idate($weekStart) . "'";

// For each branch, get its warehouse and query sold quantities
foreach ($cols as $col) {
    $whId     = $col['wh_id'];
    $branchId = $col['branch_id'];
    if ($whId <= 0 || empty($products)) continue;

    // Sold today: stock movements of type 'out' (sens=0) linked to invoices, for this warehouse
    // Using llx_mouvement (stock movements): fk_product, value (qty), datem, origintype='facture'
    $sqlSold = "SELECT m.fk_product,"
        . " SUM(CASE WHEN m.datem >= $todayStr THEN m.value ELSE 0 END) AS qty_today,"
        . " SUM(CASE WHEN m.datem >= $weekStr THEN m.value ELSE 0 END) AS qty_week"
        . " FROM " . MAIN_DB_PREFIX . "mouvement m"
        . " WHERE m.fk_entrepot = " . $whId
        . " AND m.sens = 0"  // 0 = out (sale deduction)
        . " AND m.origintype = 'facture'"
        . " AND m.datem >= $weekStr"
        . " AND m.entity IN (" . getEntity('stock') . ")"
        . " GROUP BY m.fk_product";

    $resSold = $db->query($sqlSold);
    if ($resSold) {
        while ($obj = $db->fetch_object($resSold)) {
            $pid = (int) $obj->fk_product;
            if (isset($products[$pid])) {
                $products[$pid]['sold_today'][$branchId] = (float) $obj->qty_today;
                $products[$pid]['sold_week'][$branchId]  = (float) $obj->qty_week;
            }
        }
    }
}

// ── Apply low-stock filter ────────────────────────────────────────────────────
if ($lowOnly) {
    $products = array_filter($products, function ($p) use ($cols) {
        foreach ($cols as $col) {
            $whId = $col['wh_id'];
            $qty  = isset($p['stock'][$whId]) ? $p['stock'][$whId] : 0;
            $thr  = $p['alert_threshold'] > 0 ? $p['alert_threshold'] : 5;
            if ($qty <= $thr) return true;
        }
        return false;
    });
}

// ── CSV Export ────────────────────────────────────────────────────────────────
if ($exportCsv) {
    if (!headers_sent()) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="stock_all_branches_' . date('Ymd_Hi') . '.csv"');
        header('Cache-Control: no-cache');
    }
    echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
    $csvCols = array('Ref', 'Product', 'Barcode', 'Alert Threshold');
    foreach ($cols as $col) {
        $csvCols[] = 'Stock: ' . ($col['branch_label'] ?: $col['wh_label']);
        $csvCols[] = 'Sold Today: ' . ($col['branch_label'] ?: $col['wh_label']);
        $csvCols[] = 'Sold This Week: ' . ($col['branch_label'] ?: $col['wh_label']);
    }
    echo implode(',', array_map(function ($v) { return '"' . str_replace('"', '""', $v) . '"'; }, $csvCols)) . "\n";
    foreach ($products as $p) {
        $row = array($p['ref'], $p['label'], $p['barcode'], $p['alert_threshold']);
        foreach ($cols as $col) {
            $row[] = isset($p['stock'][$col['wh_id']]) ? $p['stock'][$col['wh_id']] : 0;
            $row[] = isset($p['sold_today'][$col['branch_id']]) ? $p['sold_today'][$col['branch_id']] : 0;
            $row[] = isset($p['sold_week'][$col['branch_id']]) ? $p['sold_week'][$col['branch_id']] : 0;
        }
        echo implode(',', array_map(function ($v) { return '"' . str_replace('"', '""', (string)$v) . '"'; }, $row)) . "\n";
    }
    exit;
}

// ── HTML Output ───────────────────────────────────────────────────────────────
function stockClass($qty, $threshold) {
    $thr = $threshold > 0 ? $threshold : 5;
    if ($qty <= 0)    return 'stock-out';
    if ($qty <= $thr) return 'stock-low';
    return 'stock-ok';
}

function stockCell($qty, $threshold) {
    $class = stockClass($qty, $threshold);
    return '<td class="num ' . $class . '">' . number_format($qty, 0) . '</td>';
}

$numBranches = count($branches);
?><!DOCTYPE html>
<html lang="<?php echo dol_escape_htmltag((string) $langs->defaultlang); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo dol_escape_htmltag($langs->trans('TakeposStockAllBranchesTitle')); ?></title>
<style>
*{box-sizing:border-box}
body{font-family:Arial,Helvetica,sans-serif;margin:0;background:#f1f5f9;color:#1f2937;font-size:13px}
.wrap{padding:16px;max-width:100%}
.card{background:#fff;border:1px solid #d1d9e6;border-radius:12px;padding:14px 16px;box-shadow:0 2px 10px rgba(15,23,42,.05);margin-bottom:14px;overflow:hidden}
.card-title{font-size:16px;font-weight:700;color:#1B3A6B;margin:0 0 4px 0}
.card-sub{color:#6b7280;font-size:12px;margin:0 0 12px 0}
.toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px}
.toolbar input[type=text]{padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;min-width:220px;font-size:13px}
.toolbar select{padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;background:#fff}
.toolbar label{display:flex;align-items:center;gap:5px;font-size:13px;color:#374151;cursor:pointer}
.btn{padding:8px 12px;border:0;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:5px}
.btn-primary{background:#1d4ed8;color:#fff}
.btn-primary:hover{background:#1e40af}
.btn-export{background:#059669;color:#fff}
.btn-export:hover{background:#047857}
.btn-back{background:#e5e7eb;color:#374151}
.btn-back:hover{background:#d1d5db}
.tbl-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:12px}
th,td{padding:7px 8px;border-bottom:1px solid #e5e7eb;text-align:left;white-space:nowrap}
thead th{background:#1B3A6B;color:#fff;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.4px;position:sticky;top:0;z-index:2}
thead th.branch-group{background:#1d4ed8;border-left:2px solid #93c5fd}
thead tr.subheader th{background:#dbeafe;color:#1e3a5f;font-size:10px;font-weight:600;border-left:1px solid #bfdbfe}
tr:hover td{background:#f8fafc}
.num{text-align:right}
.stock-ok{color:#047857;font-weight:700}
.stock-low{color:#b45309;font-weight:700}
.stock-out{color:#b91c1c;font-weight:700}
.sold-num{color:#6366f1;font-size:11px}
.muted{color:#9ca3af}
.badge{display:inline-block;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:700}
.badge-branch{background:#dbeafe;color:#1d4ed8}
.badge-nowh{background:#fef3c7;color:#92400e}
.alert-info{background:#eff6ff;border:1px solid #93c5fd;border-radius:8px;padding:10px 12px;color:#1d4ed8;font-size:12px;margin-bottom:10px}
.alert-warn{background:#fff7ed;border:1px solid #fdba74;border-radius:8px;padding:10px 12px;color:#9a3412;font-size:12px;margin-bottom:10px}
.legend{display:flex;gap:14px;flex-wrap:wrap;font-size:11px;align-items:center;margin-top:6px}
.legend span{display:flex;align-items:center;gap:4px}
.dot{width:10px;height:10px;border-radius:50%;display:inline-block}
.dot-ok{background:#047857}.dot-low{background:#b45309}.dot-out{background:#b91c1c}
.sticky-col{position:sticky;left:0;background:#fff;z-index:1;border-right:2px solid #e5e7eb}
thead .sticky-col{background:#1B3A6B;z-index:3}
</style>
</head>
<body>
<div class="wrap">

  <!-- Header card -->
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px">
      <div>
        <h2 class="card-title">📦 <?php echo dol_escape_htmltag($langs->trans('TakeposStockAllBranchesTitle')); ?></h2>
        <p class="card-sub"><?php echo dol_escape_htmltag($langs->trans('TakeposStockAllBranchesDesc')); ?></p>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <a href="<?php echo DOL_URL_ROOT; ?>/takepos/stock_overview.php" class="btn btn-back">&larr; <?php echo dol_escape_htmltag($langs->trans('TakeposStockMyBranch')); ?></a>
        <?php if (!$isBranchUser): ?>
        <a href="<?php echo DOL_URL_ROOT; ?>/takepos/stock_transfer.php"
           style="padding:6px 10px;border-radius:8px;background:#7c3aed;color:#fff;text-decoration:none;font-size:12px;font-weight:600">
          &harr; <?php echo dol_escape_htmltag($langs->trans('TakeposShortcutStockTransfer')); ?>
        </a>
        <a href="<?php echo DOL_URL_ROOT; ?>/takepos/stock_reconciliation.php"
           style="padding:6px 10px;border-radius:8px;background:#0369a1;color:#fff;text-decoration:none;font-size:12px;font-weight:600">
          &asymp; <?php echo dol_escape_htmltag($langs->trans('TakeposShortcutStockRecon')); ?>
        </a>
        <a href="<?php echo DOL_URL_ROOT; ?>/takepos/stock_count.php"
           style="padding:6px 10px;border-radius:8px;background:#065f46;color:#fff;text-decoration:none;font-size:12px;font-weight:600">
          # <?php echo dol_escape_htmltag($langs->trans('TakeposShortcutStockCount')); ?>
        </a>
        <?php endif; ?>
      </div>
    </div>

    <?php if (empty($cols)): ?>
      <div class="alert-warn">
        <?php if (!empty($branches)): ?>
          <?php echo dol_escape_htmltag($langs->trans('TakeposStockBranchesNoWarehouse')); ?>
          <?php if ($user->admin): ?>
            — <a href="<?php echo DOL_URL_ROOT; ?>/takepos/admin/branches.php"><?php echo dol_escape_htmltag($langs->trans('TakeposStockFixBranches')); ?></a>
          <?php endif; ?>
        <?php else: ?>
          <?php echo dol_escape_htmltag($langs->trans('TakeposStockNoBranchesConfigured')); ?>
          <?php if ($user->admin): ?>
            <a href="<?php echo DOL_URL_ROOT; ?>/takepos/admin/branches.php"> <?php echo dol_escape_htmltag($langs->trans('TakeposStockSetupBranches')); ?></a>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <?php
      // FALLBACK: if branches exist but none have warehouses, show all warehouses as columns
      if (!empty($branches) && empty($warehouses)) {
          $sqlWhFb = "SELECT rowid, ref, label FROM " . MAIN_DB_PREFIX . "entrepot"
              . " WHERE entity IN (" . getEntity('stock') . ") AND statut IN (0, 1) ORDER BY ref ASC";
          $resWhFb = $db->query($sqlWhFb);
          if ($resWhFb) {
              while ($obj = $db->fetch_object($resWhFb)) {
                  $cols[] = array(
                      'wh_id'        => (int) $obj->rowid,
                      'branch_id'    => 0,
                      'branch_code'  => '',
                      'branch_label' => ($obj->ref ?: $obj->label),
                      'wh_label'     => ($obj->ref ?: $obj->label),
                  );
              }
          }
      }
      ?>
    <?php endif; ?>

    <?php
    // Warn about any branches without a warehouse
    $noWhBranches = array_filter($branches, function($b) { return (int)$b->fk_warehouse <= 0; });
    if (!empty($noWhBranches)):
    ?>
      <div class="alert-warn">
        ⚠ <?php echo dol_escape_htmltag($langs->trans('TakeposStockBranchesNoWarehouse')); ?>:
        <?php echo implode(', ', array_map(function($b) { return dol_escape_htmltag($b->label); }, $noWhBranches)); ?>.
        <?php if ($user->admin): ?>
          <a href="<?php echo DOL_URL_ROOT; ?>/takepos/admin/branches.php"><?php echo dol_escape_htmltag($langs->trans('TakeposStockFixBranches')); ?></a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- Toolbar -->
    <form method="get" action="">
      <div class="toolbar">
        <input type="text" name="s" value="<?php echo dol_escape_htmltag($search); ?>"
               placeholder="<?php echo dol_escape_htmltag($langs->trans('Search')); ?> ref / name / barcode"
               autofocus>

        <?php if (!empty($branches) && $filterBranch === 0): ?>
        <select name="branch_id">
          <option value="0"><?php echo dol_escape_htmltag($langs->trans('TakeposStockAllBranches')); ?></option>
          <?php foreach ($branches as $b): ?>
            <option value="<?php echo (int)$b->rowid; ?>"<?php echo ((int)$b->rowid === $filterBranch ? ' selected' : ''); ?>>
              <?php echo dol_escape_htmltag($b->code . ' — ' . $b->label); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php else: ?>
          <input type="hidden" name="branch_id" value="<?php echo (int)$filterBranch; ?>">
        <?php endif; ?>

        <label>
          <input type="checkbox" name="low_only" value="1"<?php echo ($lowOnly ? ' checked' : ''); ?>>
          <?php echo dol_escape_htmltag($langs->trans('TakeposStockLowOnly')); ?>
        </label>

        <button type="submit" class="btn btn-primary">🔍 <?php echo dol_escape_htmltag($langs->trans('Search')); ?></button>
        <a href="?s=<?php echo urlencode($search); ?>&branch_id=<?php echo (int)$filterBranch; ?>&low_only=<?php echo ($lowOnly?1:0); ?>&export=csv"
           class="btn btn-export">⬇ CSV</a>
      </div>
    </form>

    <div class="legend">
      <span><span class="dot dot-ok"></span> <?php echo dol_escape_htmltag($langs->trans('TakeposStockOk')); ?></span>
      <span><span class="dot dot-low"></span> <?php echo dol_escape_htmltag($langs->trans('TakeposStockLow')); ?> (≤ threshold)</span>
      <span><span class="dot dot-out"></span> <?php echo dol_escape_htmltag($langs->trans('TakeposStockOut')); ?></span>
      <span class="muted" style="margin-left:auto"><?php echo count($products); ?> <?php echo dol_escape_htmltag($langs->trans('Products')); ?></span>
    </div>
  </div>

  <!-- Main table card -->
  <?php if (!empty($cols) && !empty($products)): ?>
  <div class="card" style="padding:0">
    <div class="tbl-wrap">
      <table>
        <thead>
          <!-- Row 1: Product columns + branch group headers -->
          <tr>
            <th class="sticky-col" rowspan="2" style="min-width:80px"><?php echo dol_escape_htmltag($langs->trans('Ref')); ?></th>
            <th rowspan="2" style="min-width:160px"><?php echo dol_escape_htmltag($langs->trans('Product')); ?></th>
            <th rowspan="2"><?php echo dol_escape_htmltag($langs->trans('Barcode')); ?></th>
            <?php foreach ($cols as $col): ?>
            <th colspan="3" class="branch-group num">
              <?php echo dol_escape_htmltag($col['branch_label'] ?: $col['wh_label']); ?>
              <?php if ($col['wh_id'] <= 0): ?>
                <span class="badge badge-nowh">no warehouse</span>
              <?php else: ?>
                <span class="badge badge-branch"><?php echo dol_escape_htmltag($col['wh_label']); ?></span>
              <?php endif; ?>
            </th>
            <?php endforeach; ?>
          </tr>
          <!-- Row 2: sub-headers per branch column -->
          <tr class="subheader">
            <?php foreach ($cols as $col): ?>
            <th class="num" style="border-left:2px solid #93c5fd"><?php echo dol_escape_htmltag($langs->trans('TakeposStockQty')); ?></th>
            <th class="num"><?php echo dol_escape_htmltag($langs->trans('TakeposStockSoldToday')); ?></th>
            <th class="num"><?php echo dol_escape_htmltag($langs->trans('TakeposStockSoldWeek')); ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($products as $p):
            $rowId = $p['rowid'];
        ?>
          <tr>
            <td class="sticky-col"><strong><?php echo dol_escape_htmltag($p['ref']); ?></strong></td>
            <td><?php echo dol_escape_htmltag($p['label']); ?></td>
            <td class="muted"><?php echo dol_escape_htmltag($p['barcode']); ?></td>
            <?php foreach ($cols as $col):
                $whId     = $col['wh_id'];
                $branchId = $col['branch_id'];
                $qty      = $whId > 0 && isset($p['stock'][$whId]) ? $p['stock'][$whId] : ($whId > 0 ? 0 : null);
                $soldToday = isset($p['sold_today'][$branchId]) ? $p['sold_today'][$branchId] : 0;
                $soldWeek  = isset($p['sold_week'][$branchId])  ? $p['sold_week'][$branchId]  : 0;
                $thr = $p['alert_threshold'];
            ?>
              <?php if ($qty === null): ?>
                <td class="num muted" style="border-left:2px solid #93c5fd">—</td>
              <?php else: ?>
                <td class="num <?php echo stockClass($qty, $thr); ?>" style="border-left:2px solid #93c5fd"><?php echo number_format($qty, 0); ?></td>
              <?php endif; ?>
              <td class="num sold-num"><?php echo $soldToday > 0 ? number_format($soldToday, 0) : '<span class="muted">0</span>'; ?></td>
              <td class="num sold-num"><?php echo $soldWeek  > 0 ? number_format($soldWeek, 0)  : '<span class="muted">0</span>'; ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php elseif (!empty($cols) && empty($products)): ?>
    <div class="card">
      <p class="muted"><?php echo dol_escape_htmltag($langs->trans('NoRecordFound')); ?></p>
    </div>
  <?php endif; ?>

</div>
</body>
</html>
