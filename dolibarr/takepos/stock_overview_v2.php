<?php
/**
 * stock_overview.php — Single-branch stock overview (cashier view)
 *
 * FIX (stock-branch-v3):
 *  - Added "Sold Today" and "Sold This Week" columns (from stock movements)
 *  - Added "Min Threshold" column (seuil_stock_alerte from product sheet)
 *  - Raised product limit from 200 → 500
 *  - Added CSV export
 *  - Added "Low stock only" filter
 *  - Added link to All Branches Stock view for managers/admins
 *  - Stock color now respects the product's own threshold, not just hardcoded 5
 */
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
require '../main.inc.php';
require_once __DIR__ . '/lib/takepos_lang.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/class/TakeposBranchService.class.php';
$langs->loadLangs(array('admin', 'products', 'stocks', 'takeposcustom@takepos'));

if (empty($user) || (!$user->admin && !$user->hasRight('produit', 'lire') && !$user->hasRight('takepos', 'run'))) {
    accessforbidden();
}

$terminal    = isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : 0;
$warehouseId = getDolGlobalInt('CASHDESK_ID_WAREHOUSE' . $terminal);
$search      = trim((string) GETPOST('s', 'alphanohtml'));
$lowOnly     = (GETPOST('low_only', 'int') == 1);
$exportCsv   = (GETPOST('export', 'aZ09') === 'csv');

// Is this user a manager/admin who can also see the all-branches view?
$isBranchUser  = !$user->admin && TakeposBranchService::isBranchUser($db, (int) $user->id);
$canSeeAllView = !$isBranchUser && ($user->admin || $user->hasRight('produit', 'lire'));

// Warehouse label
$warehouseLabel = '';
if ($warehouseId > 0) {
    $sqlW = "SELECT rowid, ref, lieu, label FROM " . MAIN_DB_PREFIX . "entrepot WHERE rowid = " . ((int) $warehouseId);
    $resW = $db->query($sqlW);
    if ($resW && ($objW = $db->fetch_object($resW))) {
        $warehouseLabel = trim((string) ($objW->ref ?: $objW->label ?: $objW->lieu));
    }
}

// ── Product + stock query ─────────────────────────────────────────────────────
$rows     = array();
$sqlError = '';

$sql = "SELECT p.rowid, p.ref, p.label, p.barcode,"
    . " COALESCE(p.seuil_stock_alerte, 0) AS alert_threshold,"
    . " COALESCE(ps.reel, 0) AS reel"
    . " FROM " . MAIN_DB_PREFIX . "product AS p";

if ($warehouseId > 0) {
    $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "product_stock AS ps"
        . " ON ps.fk_product = p.rowid AND ps.fk_entrepot = " . ((int) $warehouseId);
} else {
    $sql .= " LEFT JOIN (SELECT fk_product, SUM(reel) AS reel FROM "
        . MAIN_DB_PREFIX . "product_stock GROUP BY fk_product) AS ps ON ps.fk_product = p.rowid";
}

$sql .= " WHERE p.entity IN (" . getEntity('product') . ")"
    . " AND p.fk_product_type = 0";

if ($search !== '') {
    $s    = "'%" . $db->escape($search) . "%'";
    $sql .= " AND (p.ref LIKE $s OR p.label LIKE $s OR p.barcode LIKE $s)";
}

if ($lowOnly) {
    // Low stock = reel <= alert_threshold (or <= 5 if no threshold set)
    $sql .= " AND COALESCE(ps.reel,0) <= CASE"
        . " WHEN COALESCE(p.seuil_stock_alerte,0) > 0 THEN p.seuil_stock_alerte ELSE 5 END";
}

$sql .= " ORDER BY p.label ASC";
$sql .= $db->plimit(500, 0); // FIX: raised from 200 → 500

$res = $db->query($sql);
if ($res) {
    while ($obj = $db->fetch_object($res)) {
        $rows[(int) $obj->rowid] = $obj;
    }
} else {
    $sqlError = $db->lasterror();
}

// ── Sold Today / Sold This Week (via stock movements) ────────────────────────
// Using llx_mouvement: sens=0 (out), origintype='facture', for this warehouse
$soldToday = array(); // product_id => qty
$soldWeek  = array();

if (!empty($rows) && $warehouseId > 0) {
    $todayStart = dol_get_first_hour(dol_now(), 'gmt');
    $weekStart  = dol_get_first_hour(dol_now() - 6 * 86400, 'gmt');
    $todayStr   = "'" . $db->idate($todayStart) . "'";
    $weekStr    = "'" . $db->idate($weekStart) . "'";

    $sqlSold = "SELECT m.fk_product,"
        . " SUM(CASE WHEN m.datem >= $todayStr THEN m.value ELSE 0 END) AS qty_today,"
        . " SUM(m.value) AS qty_week"
        . " FROM " . MAIN_DB_PREFIX . "mouvement m"
        . " WHERE m.fk_entrepot = " . $warehouseId
        . " AND m.sens = 0"              // 0 = out (sale deduction)
        . " AND m.origintype = 'facture'"
        . " AND m.datem >= $weekStr"
        . " AND m.entity IN (" . getEntity('stock') . ")"
        . " AND m.fk_product IN (" . implode(',', array_keys($rows)) . ")"
        . " GROUP BY m.fk_product";

    $resSold = $db->query($sqlSold);
    if ($resSold) {
        while ($obj = $db->fetch_object($resSold)) {
            $soldToday[(int) $obj->fk_product] = (float) $obj->qty_today;
            $soldWeek[(int) $obj->fk_product]  = (float) $obj->qty_week;
        }
    }
} elseif (!empty($rows) && $warehouseId <= 0) {
    // No warehouse configured: sum movements across all warehouses
    $todayStart = dol_get_first_hour(dol_now(), 'gmt');
    $weekStart  = dol_get_first_hour(dol_now() - 6 * 86400, 'gmt');
    $todayStr   = "'" . $db->idate($todayStart) . "'";
    $weekStr    = "'" . $db->idate($weekStart) . "'";

    $sqlSold = "SELECT m.fk_product,"
        . " SUM(CASE WHEN m.datem >= $todayStr THEN m.value ELSE 0 END) AS qty_today,"
        . " SUM(m.value) AS qty_week"
        . " FROM " . MAIN_DB_PREFIX . "mouvement m"
        . " WHERE m.sens = 0"
        . " AND m.origintype = 'facture'"
        . " AND m.datem >= $weekStr"
        . " AND m.entity IN (" . getEntity('stock') . ")"
        . " AND m.fk_product IN (" . implode(',', array_keys($rows)) . ")"
        . " GROUP BY m.fk_product";

    $resSold = $db->query($sqlSold);
    if ($resSold) {
        while ($obj = $db->fetch_object($resSold)) {
            $soldToday[(int) $obj->fk_product] = (float) $obj->qty_today;
            $soldWeek[(int) $obj->fk_product]  = (float) $obj->qty_week;
        }
    }
}

// ── CSV Export ────────────────────────────────────────────────────────────────
if ($exportCsv) {
    if (!headers_sent()) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="stock_overview_' . date('Ymd_Hi') . '.csv"');
        header('Cache-Control: no-cache');
    }
    echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
    echo "Ref,Product,Barcode,Min Threshold,Stock Qty,Sold Today,Sold This Week\n";
    foreach ($rows as $pid => $row) {
        $cols = array(
            $row->ref,
            $row->label,
            $row->barcode,
            (float) $row->alert_threshold,
            (float) $row->reel,
            isset($soldToday[$pid]) ? $soldToday[$pid] : 0,
            isset($soldWeek[$pid])  ? $soldWeek[$pid]  : 0,
        );
        echo implode(',', array_map(function ($v) {
            return '"' . str_replace('"', '""', (string) $v) . '"';
        }, $cols)) . "\n";
    }
    exit;
}

// ── Stock CSS class helper ────────────────────────────────────────────────────
function stockCssClass($qty, $threshold) {
    $thr = $threshold > 0 ? (float) $threshold : 5;
    if ((float) $qty <= 0)    return 'stock-out';
    if ((float) $qty <= $thr) return 'stock-low';
    return 'stock-ok';
}
?><!DOCTYPE html>
<html lang="<?php echo dol_escape_htmltag((string) $langs->defaultlang); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo dol_escape_htmltag($langs->trans('TakeposShortcutStockOverview')); ?></title>
<style>
*{box-sizing:border-box}
body{font-family:Arial,Helvetica,sans-serif;margin:0;background:#f5f7fb;color:#1f2937;font-size:13px}
.wrap{padding:16px}
.card{background:#fff;border:1px solid #d9e2ef;border-radius:14px;padding:14px 16px;box-shadow:0 4px 16px rgba(15,23,42,.06);margin-bottom:14px}
.badge{display:inline-block;padding:5px 10px;border-radius:999px;background:#e8f0fe;color:#1d4ed8;font-weight:700;font-size:12px}
.alert{padding:10px 12px;border-radius:10px;margin:8px 0;font-size:12px}
.alert-warning{background:#fff7ed;border:1px solid #fdba74;color:#9a3412}
.alert-info{background:#eff6ff;border:1px solid #93c5fd;color:#1d4ed8}
.toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:10px 0 6px}
.toolbar input[type=text]{padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;min-width:260px;font-size:13px}
.toolbar label{display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;color:#374151}
.btn{padding:8px 12px;border:0;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:5px}
.btn-primary{background:#1d4ed8;color:#fff}
.btn-export{background:#059669;color:#fff}
.btn-branches{background:#7c3aed;color:#fff}
/* FEATURE (add-stock-popup): per-row "Add stock" button — neutral on every row,
   sits next to the conditional low-stock "Order" button */
.btn-addstock{background:#0f766e;color:#fff;padding:3px 8px;font-size:11px;border-radius:5px;border:0;cursor:pointer;font-weight:700;white-space:nowrap}
.btn-addstock:hover{background:#0d655e}
.row-actions{display:flex;gap:4px;flex-wrap:wrap;justify-content:flex-end}
.tbl-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;background:#fff;font-size:12px}
th,td{padding:8px 10px;border-bottom:1px solid #e5e7eb;text-align:left}
th{background:#f8fafc;font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.3px;color:#374151}
tr:hover td{background:#f8fafc}
.num{text-align:right}
.muted{color:#9ca3af}
.sold{color:#6366f1;font-size:11px}
.stock-low{color:#b45309;font-weight:700}
.stock-out{color:#b91c1c;font-weight:700}
.stock-ok{color:#047857;font-weight:700}
.legend{display:flex;gap:12px;flex-wrap:wrap;font-size:11px;align-items:center;margin-top:8px}
.legend span{display:flex;align-items:center;gap:4px}
.dot{width:9px;height:9px;border-radius:50%;display:inline-block}
.dot-ok{background:#047857}.dot-low{background:#b45309}.dot-out{background:#b91c1c}
</style>

<link rel="stylesheet" href="<?php echo DOL_URL_ROOT; ?>/takepos/css/workspace_v2.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="kfv2-body">

<?php
$v2PageTitle = $langs->trans('TakeposStockOverviewTitle');
$v2PageIcon  = 'fa-warehouse';
$v2BackUrl   = DOL_URL_ROOT . '/takepos/pos_v2.php';
require __DIR__ . '/topbar_v2.php';
?>
<div class="wrap">
  <div class="card">
    <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap">
      <div>
        <h2 style="margin:0 0 4px 0"><?php echo dol_escape_htmltag($langs->trans('TakeposShortcutStockOverview')); ?></h2>
        <div class="muted"><?php echo dol_escape_htmltag($langs->trans('TakeposStockOverviewIntro')); ?></div>
      </div>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <span class="badge"><?php echo dol_escape_htmltag($langs->trans('Terminal')); ?> #<?php echo (int) $terminal; ?></span>
        <?php if ($canSeeAllView): ?>
          <a href="<?php echo DOL_URL_ROOT; ?>/takepos/stock_all_branches.php" class="btn btn-branches">
            🏪 <?php echo dol_escape_htmltag($langs->trans('TakeposShortcutStockAllBranches')); ?>
          </a>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($warehouseId > 0): ?>
      <div class="alert alert-info">
        <?php echo dol_escape_htmltag($langs->trans('TakeposStockOverviewWarehouseInUse')); ?>:
        <strong><?php echo dol_escape_htmltag($warehouseLabel !== '' ? $warehouseLabel : ('#' . $warehouseId)); ?></strong>
      </div>
    <?php else: ?>
      <div class="alert alert-warning"><?php echo dol_escape_htmltag($langs->trans('TakeposStockOverviewNoWarehouse')); ?></div>
    <?php endif; ?>

    <?php if ($sqlError !== ''): ?>
      <div class="alert alert-warning"><?php echo dol_escape_htmltag($langs->trans('Error') . ': ' . $sqlError); ?></div>
    <?php endif; ?>

    <form method="get">
      <input type="kfv2-hidden" name="langs" value="<?php echo dol_escape_htmltag((string) $langs->defaultlang); ?>">
      <div class="toolbar">
        <input type="text" name="s" value="<?php echo dol_escape_htmltag($search); ?>"
               placeholder="<?php echo dol_escape_htmltag($langs->trans('Search')); ?>" autofocus>
        <label>
          <input type="checkbox" name="low_only" value="1"<?php echo ($lowOnly ? ' checked' : ''); ?>>
          <?php echo dol_escape_htmltag($langs->trans('TakeposStockLowOnly')); ?>
        </label>
        <button type="submit" class="btn btn-primary">🔍 <?php echo dol_escape_htmltag($langs->trans('Search')); ?></button>
        <a href="?s=<?php echo urlencode($search); ?>&low_only=<?php echo ($lowOnly?1:0); ?>&export=csv"
           class="btn btn-export">⬇ CSV</a>
      </div>
    </form>

    <div class="legend">
      <span><span class="dot dot-ok"></span> <?php echo dol_escape_htmltag($langs->trans('TakeposStockOk')); ?></span>
      <span><span class="dot dot-low"></span> <?php echo dol_escape_htmltag($langs->trans('TakeposStockLow')); ?></span>
      <span><span class="dot dot-out"></span> <?php echo dol_escape_htmltag($langs->trans('TakeposStockOut')); ?></span>
      <span class="muted" style="margin-left:auto"><?php echo count($rows); ?> <?php echo dol_escape_htmltag($langs->trans('Products')); ?></span>
    </div>
  </div>

  <div class="card" style="padding:0">
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th><?php echo dol_escape_htmltag($langs->trans('Ref')); ?></th>
            <th><?php echo dol_escape_htmltag($langs->trans('Product')); ?></th>
            <th><?php echo dol_escape_htmltag($langs->trans('Barcode')); ?></th>
            <th class="num"><?php echo dol_escape_htmltag($langs->trans('TakeposStockQty')); ?></th>
            <th class="num" title="Product alert threshold from product sheet">Min</th>
            <th class="num"><?php echo dol_escape_htmltag($langs->trans('TakeposStockSoldToday')); ?></th>
            <th class="num"><?php echo dol_escape_htmltag($langs->trans('TakeposStockSoldWeek')); ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="7" class="muted" style="padding:16px"><?php echo dol_escape_htmltag($langs->trans('NoRecordFound')); ?></td></tr>
          <?php endif; ?>
          <?php foreach ($rows as $pid => $row):
            $qty       = (float) $row->reel;
            $thr       = (float) $row->alert_threshold;
            $cssClass  = stockCssClass($qty, $thr);
            $qtySoldT  = isset($soldToday[$pid]) ? $soldToday[$pid] : 0;
            $qtySoldW  = isset($soldWeek[$pid])  ? $soldWeek[$pid]  : 0;
          ?>
          <tr>
            <td><strong><?php echo dol_escape_htmltag((string) $row->ref); ?></strong></td>
            <td><?php echo dol_escape_htmltag((string) $row->label); ?></td>
            <td class="muted"><?php echo dol_escape_htmltag((string) $row->barcode); ?></td>
            <td class="num <?php echo $cssClass; ?>"><?php echo number_format($qty, 0); ?></td>
            <td class="num muted"><?php echo $thr > 0 ? number_format($thr, 0) : '—'; ?></td>
            <td class="num sold"><?php echo $qtySoldT > 0 ? number_format($qtySoldT, 0) : '<span class="muted">0</span>'; ?></td>
            <td class="num sold"><?php echo $qtySoldW > 0 ? number_format($qtySoldW, 0) : '<span class="muted">0</span>'; ?></td>
          <?php
            // Build the actions cell. We ALWAYS show an "Add stock" button so
            // managers can top up any product, not just out-of-stock ones.
            // For low-stock rows, we also keep the existing "Order" link.
            $thr2 = $row->alert_threshold;
            $qty2 = (float)$row->reel;
            $isLow = ($thr2 > 0 ? $qty2 <= $thr2 : $qty2 <= 5);
            $sugQty = max(1, ceil(($thr2 > 0 ? $thr2 : 5) - $qty2 + ($thr2 > 0 ? $thr2 : 5)));
            $purchUrl = DOL_URL_ROOT . '/takepos/purchases.php?prefill_product_id=' . $pid . '&prefill_qty=' . $sugQty;
          ?>
            <td><div class="row-actions">
              <button type="button"
                      class="btn-addstock"
                      onclick="tpAddStockOpen(<?php echo (int) $pid; ?>, this)"
                      data-product-id="<?php echo (int) $pid; ?>"
                      data-product-label="<?php echo dol_escape_htmltag(($row->ref ? $row->ref . ' — ' : '') . (string) $row->label); ?>"
                      data-stock-free="<?php echo (float) $row->reel; ?>"
                      title="<?php echo dol_escape_htmltag($langs->trans('TakeposShortcutAddStockTooltip') !== 'TakeposShortcutAddStockTooltip' ? $langs->trans('TakeposShortcutAddStockTooltip') : 'Add stock (manager approval required)'); ?>">
                + <?php echo dol_escape_htmltag($langs->trans('TakeposShortcutAddStock') !== 'TakeposShortcutAddStock' ? $langs->trans('TakeposShortcutAddStock') : 'Add stock'); ?>
              </button>
              <?php if ($isLow) { ?>
                <a href="<?php echo dol_escape_htmltag($purchUrl); ?>"
                   style="display:inline-block;padding:3px 8px;background:#1d4ed8;color:#fff;border-radius:5px;font-size:11px;font-weight:700;text-decoration:none;white-space:nowrap">
                  + <?php echo dol_escape_htmltag($langs->trans('TakeposDashboardCreatePurchase')); ?>
                </a>
              <?php } ?>
            </div></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
// FEATURE (add-stock-popup): include the popup script and expose globals so
// the per-row "+ Add stock" button can open the same popup used in the main POS.
// Uses the same takeposTransOrFallback pattern as index.php.
$_addStockTrans = function ($key, $fallback) use ($langs) {
    $v = $langs->trans($key);
    return ($v === $key || $v === '') ? $fallback : $v;
};
?>
<script>
window.takeposAddStockEndpoint = "<?php echo dol_escape_js(DOL_URL_ROOT . '/takepos/ajax/add_stock.php'); ?>";
window.takeposCsrfToken        = "<?php echo dol_escape_js(newToken()); ?>";
window.takeposAddStockLabels   = {
    title:             "<?php echo dol_escape_js($_addStockTrans('TakeposAddStockTitle',             'Add stock for this product')); ?>",
    currentStock:      "<?php echo dol_escape_js($_addStockTrans('TakeposAddStockCurrentStock',      'Current free stock')); ?>",
    qtyRequested:      "<?php echo dol_escape_js($_addStockTrans('TakeposAddStockQtyRequested',      'Qty requested')); ?>",
    qtyToAdd:          "<?php echo dol_escape_js($_addStockTrans('TakeposAddStockQtyToAdd',          'Quantity to add to stock')); ?>",
    managerLogin:      "<?php echo dol_escape_js($_addStockTrans('TakeposAddStockManagerLogin',      'Manager login')); ?>",
    managerPassword:   "<?php echo dol_escape_js($_addStockTrans('TakeposAddStockManagerPassword',   'Manager password')); ?>",
    reason:            "<?php echo dol_escape_js($_addStockTrans('TakeposAddStockReason',            'Reason (optional)')); ?>",
    reasonPlaceholder: "<?php echo dol_escape_js($_addStockTrans('TakeposAddStockReasonPlaceholder', 'e.g. found extra units in storage')); ?>",
    cancel:            "<?php echo dol_escape_js($_addStockTrans('Cancel',                           'Cancel')); ?>",
    confirm:           "<?php echo dol_escape_js($_addStockTrans('TakeposAddStockConfirm',           'Add stock & continue')); ?>",
    working:           "<?php echo dol_escape_js($_addStockTrans('TakeposAddStockWorking',           'Saving stock movement...')); ?>",
    ok:                "<?php echo dol_escape_js($_addStockTrans('TakeposAddStockSuccess',           'Stock added successfully.')); ?>",
    errQty:            "<?php echo dol_escape_js($_addStockTrans('TakeposAddStockBadQty',            'Quantity must be greater than zero.')); ?>",
    errCreds:          "<?php echo dol_escape_js($_addStockTrans('TakeposAddStockManagerRequired',   'Manager login and password are required.')); ?>",
    errNetwork:        "<?php echo dol_escape_js($_addStockTrans('TakeposAddStockNetworkError',      'Network error. Please try again.')); ?>",
    errGeneric:        "<?php echo dol_escape_js($_addStockTrans('TakeposAddStockGenericError',      'Could not add stock.')); ?>"
};
</script>
<script src="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/js/takepos_add_stock.js'); ?>"></script>
<script>
// Click handler for every "+ Add stock" row button. Reads product context from
// the button's data-* attributes and opens the shared popup. On success the
// page reloads so the new stock and "Sold Today/Week" rollups stay accurate.
function tpAddStockOpen(productId, btn) {
    if (typeof window.takeposAddStockPrompt !== 'function') {
        alert('Add-stock popup script failed to load.');
        return;
    }
    var label = btn ? btn.getAttribute('data-product-label') : '';
    var free  = btn ? parseFloat(btn.getAttribute('data-stock-free')) : null;
    window.takeposAddStockPrompt({
        productId:    productId,
        productLabel: label,
        qtyWanted:    1,            // not "requested by cart" — just a sensible default
        stockFree:    (isNaN(free) ? null : free),
        onSuccess: function () {
            // Reload to refresh the stock column. Cache-bust via timestamp.
            var u = new URL(window.location.href);
            u.searchParams.set('_t', Date.now());
            window.location.href = u.toString();
        }
    });
}
</script>
</body>
</html>
