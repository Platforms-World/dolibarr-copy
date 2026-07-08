<?php
/**
 * stock_count.php — Physical inventory count & stock adjustment
 *
 * Allows a supervisor or admin to do a quick physical count from the POS:
 *  1. Loads all products for the current terminal's warehouse
 *  2. Supervisor enters the counted quantity for each product
 *  3. On submit: calculates difference (counted - current) and creates a
 *     Dolibarr stock correction movement for each line with a variance
 *
 * Uses Dolibarr's MouvementStock::livraison() for negative adjustments and
 * MouvementStock::reception() for positive adjustments, with
 * origintype='inventory' so movements appear labeled as inventory corrections.
 *
 * Access: admin or takepos.store.manage permission.
 * Branch users cannot access this page.
 *
 * FIX (stock-branch-v8): New file.
 */

if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');

require '../main.inc.php';
require_once __DIR__ . '/lib/takepos_lang.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/class/TakeposAccess.class.php';
require_once __DIR__ . '/class/TakeposBranchService.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/stock/class/mouvementstock.class.php';

$langs->loadLangs(array('admin', 'products', 'stocks', 'takeposcustom@takepos'));

// ── Access control ────────────────────────────────────────────────────────────
if (empty($user) || empty($user->id)) accessforbidden();
if (!$user->admin && TakeposBranchService::isBranchUser($db, (int) $user->id)) {
    accessforbidden($langs->trans('TakeposCountBranchDenied'));
}
if (!$user->admin) {
    TakeposAccess::requireFrontendAccess($db, $user, 'takepos.store_governance', 'takepos.store.manage',
        isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null,
        $langs->trans('TakeposCountAccessDenied'), array('page' => 'stock_count.php'));
}

$entity      = !empty($user->entity) ? (int) $user->entity : 1;
$terminal    = isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : 0;
$warehouseId = GETPOSTINT('warehouse_id');

// If no warehouse selected via form, use the terminal's default
if ($warehouseId <= 0) {
    $warehouseId = getDolGlobalInt('CASHDESK_ID_WAREHOUSE' . $terminal);
}

$search = trim((string) GETPOST('s', 'alphanohtml'));
$action = GETPOST('action', 'aZ09');

// ── Load warehouses ───────────────────────────────────────────────────────────
$warehouses = array();
$sqlWh = "SELECT e.rowid, e.ref, e.label, COALESCE(b.label,'') AS branch_label"
    . " FROM " . MAIN_DB_PREFIX . "entrepot e"
    . " LEFT JOIN " . MAIN_DB_PREFIX . "takepos_branch b ON b.fk_warehouse = e.rowid AND b.entity = e.entity"
    . " WHERE e.entity IN (" . getEntity('stock') . ") AND e.statut IN (0, 1) ORDER BY e.ref";
$resWh = $db->query($sqlWh);
if ($resWh) { while ($o = $db->fetch_object($resWh)) { $warehouses[] = $o; } }

// ── Handle POST: apply stock corrections ─────────────────────────────────────
$messages = array();
$errors   = array();
$adjustedCount = 0;

if ($action === 'apply_count' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) GETPOST('token', 'alpha');
    $sessionToken = isset($_SESSION['newtoken']) ? (string) $_SESSION['newtoken'] : '';
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        $errors[] = $langs->trans('TakeposCountInvalidToken');
    } elseif ($warehouseId <= 0) {
        $errors[] = $langs->trans('TakeposCountNoWarehouse');
    } else {
        $countedIds  = isset($_POST['counted_product_id']) && is_array($_POST['counted_product_id'])
            ? $_POST['counted_product_id'] : array();
        $countedQtys = isset($_POST['counted_qty']) && is_array($_POST['counted_qty'])
            ? $_POST['counted_qty'] : array();
        $countedSystemQtys = isset($_POST['system_qty']) && is_array($_POST['system_qty'])
            ? $_POST['system_qty'] : array();
        $countNote = trim((string) GETPOST('count_note', 'restricthtml'));
        $countLabel = $langs->trans('TakeposCountMovementLabel') . ($countNote ? ' — ' . $countNote : '');

        $db->begin();
        $ok = true;

        for ($i = 0, $n = count($countedIds); $i < $n; $i++) {
            $pid       = (int) ($countedIds[$i] ?? 0);
            $countedStr = trim((string) ($countedQtys[$i] ?? ''));
            $systemQty  = (float) ($countedSystemQtys[$i] ?? 0);

            if ($pid <= 0 || $countedStr === '') continue; // unchanged / not entered
            $countedQty = (float) str_replace(',', '.', $countedStr);
            $diff = round($countedQty - $systemQty, 6);
            if (abs($diff) < 0.0001) continue; // no change

            $mouv = new MouvementStock($db);
            $mouv->origin_type = 'inventory';

            if ($diff > 0) {
                // Stock gain: physical count is higher than system — reception (in)
                $res = $mouv->reception($user, $pid, $warehouseId, $diff, 0, $countLabel);
            } else {
                // Stock loss: physical count is lower than system — livraison (out)
                $res = $mouv->livraison($user, $pid, $warehouseId, abs($diff), 0, $countLabel);
            }

            if ($res < 0) {
                $errors[] = 'Product #' . $pid . ': ' . implode(', ', $mouv->errors);
                $ok = false;
                break;
            }
            $adjustedCount++;
        }

        if ($ok && $adjustedCount > 0) {
            $db->commit();
            $messages[] = $langs->transnoentitiesnoconv('TakeposCountSuccess', $adjustedCount);
        } elseif ($ok && $adjustedCount === 0) {
            $db->commit();
            $messages[] = $langs->trans('TakeposCountNoChanges');
        } else {
            $db->rollback();
        }
    }
}

// ── Load products with current stock ─────────────────────────────────────────
$products = array();
if ($warehouseId > 0) {
    $sqlP = "SELECT p.rowid, p.ref, p.label, p.barcode,"
        . " COALESCE(ps.reel, 0) AS system_qty"
        . " FROM " . MAIN_DB_PREFIX . "product p"
        . " LEFT JOIN " . MAIN_DB_PREFIX . "product_stock ps"
        . " ON ps.fk_product = p.rowid AND ps.fk_entrepot = " . $warehouseId
        . " WHERE p.entity IN (" . getEntity('product') . ") AND p.fk_product_type = 0 AND p.tosell = 1";
    if ($search !== '') {
        $s = "'%" . $db->escape($search) . "%'";
        $sqlP .= " AND (p.ref LIKE $s OR p.label LIKE $s OR p.barcode LIKE $s)";
    }
    $sqlP .= " ORDER BY p.ref ASC" . $db->plimit(300, 0);
    $resP = $db->query($sqlP);
    if ($resP) { while ($o = $db->fetch_object($resP)) { $products[] = $o; } }
}

// Warehouse label
$warehouseLabel = '';
foreach ($warehouses as $wh) {
    if ((int)$wh->rowid === $warehouseId) {
        $warehouseLabel = ($wh->ref ?: $wh->label) . ($wh->branch_label ? ' (' . $wh->branch_label . ')' : '');
        break;
    }
}
?><!DOCTYPE html>
<html lang="<?php echo dol_escape_htmltag((string)$langs->defaultlang); ?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo dol_escape_htmltag($langs->trans('TakeposCountTitle')); ?></title>
<style>
*{box-sizing:border-box}
body{font-family:Arial,sans-serif;margin:0;background:#f1f5f9;color:#1f2937;font-size:13px}
.wrap{padding:16px;max-width:960px;margin:0 auto}
.card{background:#fff;border:1px solid #d1d9e6;border-radius:12px;padding:14px 16px;box-shadow:0 2px 8px rgba(15,23,42,.05);margin-bottom:12px}
h2{margin:0 0 4px;font-size:18px;color:#1B3A6B}
.sub{color:#6b7280;font-size:12px;margin:0 0 12px}
.toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:10px 0 6px}
.toolbar select,.toolbar input[type=text]{padding:7px 10px;border:1px solid #cbd5e1;border-radius:7px;font-size:13px;background:#fff}
.btn{padding:8px 12px;border:0;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:5px}
.btn-primary{background:#1d4ed8;color:#fff}
.btn-submit{background:#059669;color:#fff;font-size:14px;padding:10px 16px}
.btn-back{background:#e5e7eb;color:#374151}
.alert{padding:10px 12px;border-radius:8px;margin-bottom:10px;font-size:13px}
.alert-success{background:#d1fae5;border:1px solid #6ee7b7;color:#065f46}
.alert-error{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b}
.alert-info{background:#eff6ff;border:1px solid #93c5fd;color:#1d4ed8;font-size:12px}
.tbl-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:12px}
th,td{padding:7px 9px;border-bottom:1px solid #e5e7eb;text-align:left}
thead th{background:#1B3A6B;color:#fff;font-size:11px;text-transform:uppercase;letter-spacing:.3px}
tr:hover td{background:#f8fafc}
.num{text-align:right}
.muted{color:#9ca3af}
input.count-input{width:90px;padding:5px 7px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;text-align:right}
input.count-input:focus{outline:2px solid #93c5fd;border-color:#3b82f6}
input.count-input.changed{background:#fffbeb;border-color:#f59e0b;font-weight:700}
.diff-pos{color:#047857;font-weight:700}
.diff-neg{color:#b91c1c;font-weight:700}
.diff-zero{color:#9ca3af}
.wh-badge{display:inline-block;padding:4px 10px;border-radius:999px;background:#dbeafe;color:#1d4ed8;font-size:12px;font-weight:700}
</style>
</head>
<body>
<div class="wrap">

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px">
      <div>
        <h2>🔢 <?php echo dol_escape_htmltag($langs->trans('TakeposCountTitle')); ?></h2>
        <p class="sub"><?php echo dol_escape_htmltag($langs->trans('TakeposCountDesc')); ?></p>
      </div>
      <a href="<?php echo DOL_URL_ROOT; ?>/takepos/stock_all_branches.php" class="btn btn-back">← <?php echo dol_escape_htmltag($langs->trans('TakeposShortcutStockAllBranches')); ?></a>
    </div>

    <?php foreach ($messages as $msg): ?>
      <div class="alert alert-success">✅ <?php echo dol_escape_htmltag($msg); ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $err): ?>
      <div class="alert alert-error">❌ <?php echo dol_escape_htmltag($err); ?></div>
    <?php endforeach; ?>

    <!-- Warehouse & Search selector -->
    <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px">
      <select name="warehouse_id" onchange="this.form.submit()">
        <option value="0"><?php echo dol_escape_htmltag($langs->trans('TakeposTransferSelectWarehouse')); ?></option>
        <?php foreach ($warehouses as $wh): ?>
          <option value="<?php echo (int)$wh->rowid; ?>"<?php echo ($warehouseId===(int)$wh->rowid?' selected':''); ?>>
            <?php echo dol_escape_htmltag(($wh->ref?:$wh->label).($wh->branch_label?' ('.$wh->branch_label.')':'')); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <input type="hidden" name="warehouse_id" value="<?php echo (int)$warehouseId; ?>">
      <input type="text" name="s" value="<?php echo dol_escape_htmltag($search); ?>" placeholder="<?php echo dol_escape_htmltag($langs->trans('Search')); ?>">
      <button type="submit" class="btn btn-primary">🔍</button>
    </form>

    <?php if ($warehouseId > 0 && $warehouseLabel !== ''): ?>
      <div>📦 <?php echo dol_escape_htmltag($langs->trans('TakeposCommonWarehouse')); ?>: <span class="wh-badge"><?php echo dol_escape_htmltag($warehouseLabel); ?></span></div>
    <?php elseif ($warehouseId <= 0): ?>
      <div class="alert alert-info"><?php echo dol_escape_htmltag($langs->trans('TakeposCountSelectWarehouseFirst')); ?></div>
    <?php endif; ?>
  </div>

  <?php if ($warehouseId > 0 && !empty($products)): ?>
  <form method="POST" id="count-form">
    <input type="hidden" name="token" value="<?php echo newToken(); ?>">
    <input type="hidden" name="action" value="apply_count">
    <input type="hidden" name="warehouse_id" value="<?php echo (int)$warehouseId; ?>">
    <input type="hidden" name="s" value="<?php echo dol_escape_htmltag($search); ?>">

    <div class="card" style="padding:0">
      <div class="tbl-wrap">
        <table>
          <thead><tr>
            <th><?php echo dol_escape_htmltag($langs->trans('Ref')); ?></th>
            <th><?php echo dol_escape_htmltag($langs->trans('Product')); ?></th>
            <th><?php echo dol_escape_htmltag($langs->trans('Barcode')); ?></th>
            <th class="num"><?php echo dol_escape_htmltag($langs->trans('TakeposCountSystemQty')); ?></th>
            <th class="num"><?php echo dol_escape_htmltag($langs->trans('TakeposCountCountedQty')); ?></th>
            <th class="num"><?php echo dol_escape_htmltag($langs->trans('TakeposCountDiff')); ?></th>
          </tr></thead>
          <tbody>
          <?php foreach ($products as $i => $p): ?>
            <tr id="row_<?php echo (int)$p->rowid; ?>">
              <input type="hidden" name="counted_product_id[]" value="<?php echo (int)$p->rowid; ?>">
              <input type="hidden" name="system_qty[]" value="<?php echo (float)$p->system_qty; ?>">
              <td><strong><?php echo dol_escape_htmltag($p->ref); ?></strong></td>
              <td><?php echo dol_escape_htmltag($p->label); ?></td>
              <td class="muted"><?php echo dol_escape_htmltag($p->barcode); ?></td>
              <td class="num" id="sys_<?php echo (int)$p->rowid; ?>"><?php echo number_format((float)$p->system_qty, 2); ?></td>
              <td class="num">
                <input type="number" name="counted_qty[]"
                       class="count-input"
                       id="cnt_<?php echo (int)$p->rowid; ?>"
                       step="0.001" min="0"
                       placeholder="<?php echo number_format((float)$p->system_qty, 2); ?>"
                       data-system="<?php echo (float)$p->system_qty; ?>"
                       data-rowid="<?php echo (int)$p->rowid; ?>"
                       oninput="updateDiff(this)">
              </td>
              <td class="num diff-zero" id="diff_<?php echo (int)$p->rowid; ?>">—</td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
        <div style="flex:1;min-width:220px">
          <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px"><?php echo dol_escape_htmltag($langs->trans('Note')); ?></label>
          <input type="text" name="count_note" style="width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px"
                 placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposCountNotePlaceholder')); ?>">
        </div>
        <button type="submit" class="btn btn-submit">✅ <?php echo dol_escape_htmltag($langs->trans('TakeposCountSubmit')); ?></button>
      </div>
      <p style="font-size:11px;color:#9ca3af;margin:8px 0 0">
        <?php echo dol_escape_htmltag($langs->trans('TakeposCountFootnote')); ?>
        <a href="<?php echo DOL_URL_ROOT; ?>/product/stock/mouvement.php" target="_blank" style="color:#6366f1">
          <?php echo dol_escape_htmltag($langs->trans('TakeposTransferViewHistory')); ?> ↗
        </a>
      </p>
    </div>
  </form>

  <?php elseif ($warehouseId > 0 && empty($products)): ?>
    <div class="card"><p class="muted"><?php echo dol_escape_htmltag($langs->trans('NoRecordFound')); ?></p></div>
  <?php endif; ?>

</div>
<script>
function updateDiff(input) {
    var rowid = input.dataset.rowid;
    var system = parseFloat(input.dataset.system) || 0;
    var counted = input.value !== '' ? parseFloat(input.value) : NaN;
    var diffEl = document.getElementById('diff_' + rowid);
    if (!diffEl) return;
    if (isNaN(counted)) {
        diffEl.textContent = '—';
        diffEl.className = 'num diff-zero';
        input.classList.remove('changed');
        return;
    }
    var diff = Math.round((counted - system) * 10000) / 10000;
    diffEl.textContent = (diff > 0 ? '+' : '') + diff;
    diffEl.className = 'num ' + (diff > 0 ? 'diff-pos' : diff < 0 ? 'diff-neg' : 'diff-zero');
    input.classList.toggle('changed', diff !== 0);
}

// Confirm before submit if no fields changed
document.getElementById('count-form') && document.getElementById('count-form').addEventListener('submit', function(e) {
    var inputs = this.querySelectorAll('.count-input');
    var hasChange = false;
    inputs.forEach(function(inp) { if (inp.value !== '') hasChange = true; });
    if (!hasChange) {
        e.preventDefault();
        alert('<?php echo addslashes($langs->trans("TakeposCountNothingEntered")); ?>');
    }
});
</script>
</body>
</html>
