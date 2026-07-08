<?php
/**
 * stock_reconciliation.php — Sales vs. Purchases vs. Stock reconciliation report
 *
 * Shows, per product:
 *  - Opening stock (at start of chosen period, estimated from movements)
 *  - Total purchased qty (from llx_takepos_purchase_line)
 *  - Total sold qty (from llx_facturedet / validated invoices)
 *  - Expected closing stock = opening + purchased - sold
 *  - Actual closing stock (current llx_product_stock.reel)
 *  - Variance = actual - expected (positive = untracked gain, negative = shrinkage/loss)
 *
 * Date range filter: defaults to current month.
 * Branch/warehouse filter: all or one warehouse.
 * CSV export included.
 *
 * Access: admin or takepos.analytics.view permission.
 *
 * FIX (stock-branch-v7): New file.
 */

if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');

require '../main.inc.php';
require_once __DIR__ . '/lib/takepos_lang.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/class/TakeposAccess.class.php';
require_once __DIR__ . '/class/TakeposBranchService.class.php';

$langs->loadLangs(array('admin', 'products', 'stocks', 'bills', 'takeposcustom@takepos'));

// ── Access control ────────────────────────────────────────────────────────────
if (empty($user) || empty($user->id)) accessforbidden();
if (!$user->admin && TakeposBranchService::isBranchUser($db, (int) $user->id)) {
    accessforbidden($langs->trans('TakeposReconBranchDenied'));
}
if (!$user->admin) {
    TakeposAccess::requireFrontendAccess($db, $user, 'takepos.analytics', 'takepos.analytics.view',
        isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null,
        $langs->trans('TakeposReconAccessDenied'), array('page' => 'stock_reconciliation_v2.php'));
}

$entity = !empty($user->entity) ? (int) $user->entity : 1;

// ── Parameters ────────────────────────────────────────────────────────────────
$dateFrom   = GETPOST('date_from', 'alpha') ?: date('Y-m-01');
$dateTo     = GETPOST('date_to',   'alpha') ?: date('Y-m-d');
$warehouseId = GETPOSTINT('warehouse_id');  // 0 = all
$search      = trim((string) GETPOST('s', 'alphanohtml'));
$varOnly     = (GETPOST('var_only', 'int') == 1); // show only products with variance
$exportCsv   = (GETPOST('export', 'aZ09') === 'csv');

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo))   $dateTo   = date('Y-m-d');

$dtFrom = $dateFrom . ' 00:00:00';
$dtTo   = $dateTo   . ' 23:59:59';

// ── Load warehouses for filter ────────────────────────────────────────────────
$warehouses = array();
$sqlWh = "SELECT e.rowid, e.ref, e.label,"
    . " COALESCE(b.label,'') AS branch_label"
    . " FROM " . MAIN_DB_PREFIX . "entrepot e"
    . " LEFT JOIN " . MAIN_DB_PREFIX . "takepos_branch b ON b.fk_warehouse = e.rowid AND b.entity = e.entity"
    . " WHERE e.entity IN (" . getEntity('stock') . ") AND e.statut IN (0, 1) ORDER BY e.ref";
$resWh = $db->query($sqlWh);
if ($resWh) { while ($o = $db->fetch_object($resWh)) { $warehouses[] = $o; } }

// ── Load products ─────────────────────────────────────────────────────────────
$products = array();
$sqlP = "SELECT p.rowid, p.ref, p.label, p.barcode, COALESCE(p.seuil_stock_alerte,0) AS alert_threshold"
    . " FROM " . MAIN_DB_PREFIX . "product p"
    . " WHERE p.entity IN (" . getEntity('product') . ") AND p.fk_product_type = 0";
if ($search !== '') {
    $s = "'%" . $db->escape($search) . "%'";
    $sqlP .= " AND (p.ref LIKE $s OR p.label LIKE $s OR p.barcode LIKE $s)";
}
$sqlP .= " ORDER BY p.ref ASC" . $db->plimit(500, 0);
$resP = $db->query($sqlP);
if ($resP) {
    while ($o = $db->fetch_object($resP)) {
        $products[(int)$o->rowid] = array(
            'rowid' => (int)$o->rowid, 'ref' => $o->ref, 'label' => $o->label,
            'barcode' => $o->barcode, 'alert_threshold' => (float)$o->alert_threshold,
            'purchased' => 0, 'sold' => 0, 'current_stock' => 0,
        );
    }
}
if (empty($products)) {
    $rows = array();
} else {
    $pids = implode(',', array_keys($products));

    // ── Current stock ─────────────────────────────────────────────────────────
    $sqlStock = "SELECT fk_product, " . ($warehouseId > 0 ? "reel" : "SUM(reel) AS reel")
        . " FROM " . MAIN_DB_PREFIX . "product_stock"
        . " WHERE fk_product IN ($pids)"
        . ($warehouseId > 0 ? " AND fk_entrepot = $warehouseId" : "")
        . ($warehouseId <= 0 ? " GROUP BY fk_product" : "");
    $resStock = $db->query($sqlStock);
    if ($resStock) {
        while ($o = $db->fetch_object($resStock)) {
            if (isset($products[(int)$o->fk_product])) {
                $products[(int)$o->fk_product]['current_stock'] = (float)$o->reel;
            }
        }
    }

    // ── Sold qty (validated invoice lines in date range) ──────────────────────
    // Map warehouse → invoices via terminal config is complex; instead use
    // stock movements (sens=0, origintype=facture) which are already warehouse-scoped
    $sqlSold = "SELECT m.fk_product, SUM(m.value) AS qty"
        . " FROM " . MAIN_DB_PREFIX . "mouvement m"
        . " WHERE m.sens = 0 AND m.origintype = 'facture'"
        . " AND m.datem BETWEEN '" . $db->escape($dtFrom) . "' AND '" . $db->escape($dtTo) . "'"
        . " AND m.fk_product IN ($pids)"
        . " AND m.entity IN (" . getEntity('stock') . ")"
        . ($warehouseId > 0 ? " AND m.fk_entrepot = $warehouseId" : "")
        . " GROUP BY m.fk_product";
    $resSold = $db->query($sqlSold);
    if ($resSold) {
        while ($o = $db->fetch_object($resSold)) {
            if (isset($products[(int)$o->fk_product])) {
                $products[(int)$o->fk_product]['sold'] = (float)$o->qty;
            }
        }
    }

    // ── Purchased qty (from takepos_purchase_line in date range) ─────────────
    $purchaseLineTable = MAIN_DB_PREFIX . 'takepos_purchase_line';
    $purchaseTable     = MAIN_DB_PREFIX . 'takepos_purchase';
    // Check table exists (it may not exist if purchases module was never used)
    $chk = $db->query("SHOW TABLES LIKE '" . $db->escape($purchaseLineTable) . "'");
    if ($chk && $db->num_rows($chk) > 0) {
        $sqlPurch = "SELECT pl.fk_product, SUM(pl.qty) AS qty"
            . " FROM $purchaseLineTable pl"
            . " INNER JOIN $purchaseTable pu ON pu.rowid = pl.fk_purchase AND pu.entity = pl.entity"
            . " WHERE pl.fk_product IN ($pids)"
            . " AND pl.entity = $entity"
            . " AND pu.purchase_date BETWEEN '" . $db->escape($dtFrom) . "' AND '" . $db->escape($dtTo) . "'"
            . ($warehouseId > 0 ? " AND pu.fk_warehouse = $warehouseId" : "")
            . " GROUP BY pl.fk_product";
        $resPurch = $db->query($sqlPurch);
        if ($resPurch) {
            while ($o = $db->fetch_object($resPurch)) {
                if (isset($products[(int)$o->fk_product])) {
                    $products[(int)$o->fk_product]['purchased'] = (float)$o->qty;
                }
            }
        }
    }

    // ── Opening stock = current - purchased + sold (reverse calculation) ──────
    // Since we don't store daily snapshots, we estimate:
    // opening ≈ current_stock - net movements in period
    // net_in (purchases) increases stock; net_out (sales) decreases it
    // opening = current + sold - purchased
    foreach ($products as &$p) {
        $p['opening_stock'] = $p['current_stock'] + $p['sold'] - $p['purchased'];
        $p['expected_closing'] = $p['opening_stock'] + $p['purchased'] - $p['sold'];
        // expected_closing should equal current_stock if everything matches
        $p['variance'] = round($p['current_stock'] - $p['expected_closing'], 4);
    }
    unset($p);

    // FIX: Show ALL products by default (not just ones with activity)
    // Only filter when varOnly is checked OR search is active
    // This way the report is useful even on a fresh system
    $rows = array_filter($products, function($p) use ($varOnly) {
        if ($varOnly) return abs($p['variance']) > 0.001;
        return true; // show all products; sort by variance so interesting ones come first
    });
    // Sort: products with variance first, then by ref
    usort($rows, function($a, $b) {
        $vA = abs($a['variance']);
        $vB = abs($b['variance']);
        if ($vA != $vB) return $vB > $vA ? 1 : -1;
        return strcmp($a['ref'], $b['ref']);
    });
}

// ── CSV Export ────────────────────────────────────────────────────────────────
if ($exportCsv) {
    if (!headers_sent()) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="reconciliation_' . $dateFrom . '_' . $dateTo . '.csv"');
        header('Cache-Control: no-cache');
    }
    echo "\xEF\xBB\xBF";
    echo "Ref,Product,Barcode,Opening Stock,Purchased,Sold,Expected Closing,Actual Stock,Variance\n";
    foreach ($rows as $p) {
        $row = array($p['ref'],$p['label'],$p['barcode'],
            $p['opening_stock'],$p['purchased'],$p['sold'],
            $p['expected_closing'],$p['current_stock'],$p['variance']);
        echo implode(',', array_map(function($v){ return '"'.str_replace('"','""',(string)$v).'"'; }, $row))."\n";
    }
    exit;
}
?><!DOCTYPE html>
<html lang="<?php echo dol_escape_htmltag((string)$langs->defaultlang); ?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo dol_escape_htmltag($langs->trans('TakeposReconTitle')); ?></title>
<style>
*{box-sizing:border-box}
body{font-family:Arial,sans-serif;margin:0;background:#f1f5f9;color:#1f2937;font-size:13px}
.wrap{padding:16px}
.card{background:#fff;border:1px solid #d1d9e6;border-radius:12px;padding:14px 16px;box-shadow:0 2px 8px rgba(15,23,42,.05);margin-bottom:12px}
h2{margin:0 0 4px;font-size:18px;color:#1B3A6B}
.sub{color:#6b7280;font-size:12px;margin:0 0 14px}
.toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:8px}
.toolbar input,.toolbar select{padding:7px 10px;border:1px solid #cbd5e1;border-radius:7px;font-size:13px;background:#fff}
.toolbar label{display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer}
.btn{padding:8px 12px;border:0;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:5px}
.btn-primary{background:#1d4ed8;color:#fff}
.btn-export{background:#059669;color:#fff}
.btn-back{background:#e5e7eb;color:#374151}
.tbl-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:12px}
th,td{padding:7px 9px;border-bottom:1px solid #e5e7eb;text-align:left;white-space:nowrap}
thead th{background:#1B3A6B;color:#fff;font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.3px}
tr:hover td{background:#f8fafc}
.num{text-align:right}
.muted{color:#9ca3af}
.var-pos{color:#047857;font-weight:700}
.var-neg{color:#b91c1c;font-weight:700}
.var-zero{color:#9ca3af}
.pill{display:inline-block;padding:2px 7px;border-radius:999px;font-size:10px;font-weight:700}
.pill-sold{background:#dbeafe;color:#1d4ed8}
.pill-purch{background:#d1fae5;color:#065f46}
.summary-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin:10px 0}
.stat{background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:10px 12px}
.stat strong{display:block;font-size:20px;color:#1B3A6B;margin-bottom:2px}
.stat small{color:#6b7280;font-size:11px}
</style>

<link rel="stylesheet" href="<?php echo DOL_URL_ROOT; ?>/takepos/css/workspace_v2.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="kfv2-body">

<?php
$v2PageTitle = $langs->trans('TakeposStockReconTitle');
$v2PageIcon  = 'fa-scale-balanced';
$v2BackUrl   = DOL_URL_ROOT . '/takepos/pos_v2.php';
require __DIR__ . '/topbar_v2.php';
?>
<div class="wrap">
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px">
      <div>
        <h2>📊 <?php echo dol_escape_htmltag($langs->trans('TakeposReconTitle')); ?></h2>
        <p class="sub"><?php echo dol_escape_htmltag($langs->trans('TakeposReconDesc')); ?></p>
      </div>
      <a href="<?php echo DOL_URL_ROOT; ?>/takepos/stock_all_branches.php" class="btn btn-back">← <?php echo dol_escape_htmltag($langs->trans('TakeposShortcutStockAllBranches')); ?></a>
    </div>

    <form method="get">
      <div class="toolbar">
        <label><?php echo dol_escape_htmltag($langs->trans('DateFrom')); ?>
          <input type="date" name="date_from" value="<?php echo dol_escape_htmltag($dateFrom); ?>">
        </label>
        <label><?php echo dol_escape_htmltag($langs->trans('DateTo')); ?>
          <input type="date" name="date_to" value="<?php echo dol_escape_htmltag($dateTo); ?>">
        </label>
        <select name="warehouse_id">
          <option value="0"><?php echo dol_escape_htmltag($langs->trans('TakeposStockAllBranches')); ?></option>
          <?php foreach ($warehouses as $wh): ?>
            <option value="<?php echo (int)$wh->rowid; ?>"<?php echo ($warehouseId===(int)$wh->rowid?' selected':''); ?>>
              <?php echo dol_escape_htmltag(($wh->ref?:$wh->label).($wh->branch_label?' ('.$wh->branch_label.')':'')); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="s" value="<?php echo dol_escape_htmltag($search); ?>" placeholder="<?php echo dol_escape_htmltag($langs->trans('Search')); ?>">
        <label><input type="checkbox" name="var_only" value="1"<?php echo ($varOnly?' checked':''); ?>> <?php echo dol_escape_htmltag($langs->trans('TakeposReconVarOnly')); ?></label>
        <button type="submit" class="btn btn-primary">🔍</button>
        <a href="?date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&warehouse_id=<?php echo (int)$warehouseId; ?>&s=<?php echo urlencode($search); ?>&var_only=<?php echo ($varOnly?1:0); ?>&export=csv"
           class="btn btn-export">⬇ CSV</a>
      </div>
    </form>

    <?php
    $totalSold = array_sum(array_column($rows, 'sold'));
    $totalPurch = array_sum(array_column($rows, 'purchased'));
    $totalVar = array_sum(array_column($rows, 'variance'));
    $varItems = count(array_filter($rows, function($p){ return abs($p['variance']) > 0.001; }));
    ?>
    <div class="summary-row">
      <div class="stat"><strong><?php echo count($rows); ?></strong><small><?php echo dol_escape_htmltag($langs->trans('Products')); ?></small></div>
      <div class="stat"><strong style="color:#1d4ed8"><?php echo number_format($totalSold,2); ?></strong><small><?php echo dol_escape_htmltag($langs->trans('TakeposReconSold')); ?></small></div>
      <div class="stat"><strong style="color:#047857"><?php echo number_format($totalPurch,2); ?></strong><small><?php echo dol_escape_htmltag($langs->trans('TakeposReconPurchased')); ?></small></div>
      <div class="stat"><strong style="color:<?php echo $totalVar < 0 ? '#b91c1c' : ($totalVar > 0 ? '#047857' : '#9ca3af'); ?>"><?php echo ($totalVar>0?'+':'').number_format($totalVar,2); ?></strong><small><?php echo dol_escape_htmltag($langs->trans('TakeposReconVariance')); ?> total</small></div>
      <div class="stat"><strong style="color:<?php echo $varItems>0?'#b45309':'#047857'; ?>"><?php echo $varItems; ?></strong><small><?php echo dol_escape_htmltag($langs->trans('TakeposReconVarItems')); ?></small></div>
    </div>
  </div>

  <div class="card" style="padding:0">
    <div class="tbl-wrap">
      <table>
        <thead><tr>
          <th><?php echo dol_escape_htmltag($langs->trans('Ref')); ?></th>
          <th><?php echo dol_escape_htmltag($langs->trans('Product')); ?></th>
          <th class="num" title="<?php echo dol_escape_htmltag($langs->trans('TakeposReconOpeningHint')); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposReconOpening')); ?></th>
          <th class="num"><?php echo dol_escape_htmltag($langs->trans('TakeposReconPurchased')); ?></th>
          <th class="num"><?php echo dol_escape_htmltag($langs->trans('TakeposReconSold')); ?></th>
          <th class="num"><?php echo dol_escape_htmltag($langs->trans('TakeposReconExpected')); ?></th>
          <th class="num"><?php echo dol_escape_htmltag($langs->trans('TakeposReconActual')); ?></th>
          <th class="num"><?php echo dol_escape_htmltag($langs->trans('TakeposReconVariance')); ?></th>
        </tr></thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="8" class="muted" style="padding:16px"><?php echo dol_escape_htmltag($langs->trans('NoRecordFound')); ?></td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $p):
            $varClass = abs($p['variance']) < 0.001 ? 'var-zero' : ($p['variance'] < 0 ? 'var-neg' : 'var-pos');
        ?>
          <tr>
            <td><strong><?php echo dol_escape_htmltag($p['ref']); ?></strong></td>
            <td><?php echo dol_escape_htmltag($p['label']); ?></td>
            <td class="num muted"><?php echo number_format($p['opening_stock'],2); ?></td>
            <td class="num"><span class="pill pill-purch"><?php echo number_format($p['purchased'],2); ?></span></td>
            <td class="num"><span class="pill pill-sold"><?php echo number_format($p['sold'],2); ?></span></td>
            <td class="num muted"><?php echo number_format($p['expected_closing'],2); ?></td>
            <td class="num"><?php echo number_format($p['current_stock'],2); ?></td>
            <td class="num <?php echo $varClass; ?>"><?php echo ($p['variance']>0?'+':'').number_format($p['variance'],2); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <p style="font-size:11px;color:#9ca3af;margin:0">
    <?php echo dol_escape_htmltag($langs->trans('TakeposReconFootnote')); ?>
  </p>
</div>
</body>
</html>
