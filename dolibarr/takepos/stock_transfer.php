<?php
/**
 * stock_transfer.php — Inter-branch / inter-warehouse stock transfer
 *
 * Allows a supervisor or admin to move stock from one warehouse/branch to
 * another directly from TakePOS. Uses Dolibarr's native stock movement API:
 *  - Negative correction on the source warehouse (stock out)
 *  - Positive correction on the destination warehouse (stock in)
 *
 * Both movements are labeled "TakePOS Transfer — [ref]" so they appear
 * correctly in Dolibarr's stock movement history.
 *
 * Access: admin OR takepos.store.manage permission.
 * Branch users are blocked (they should not move stock between warehouses).
 *
 * FIX (stock-branch-v4): New file.
 */

if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');

require '../main.inc.php';
require_once __DIR__ . '/lib/takepos_lang.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/class/TakeposAccess.class.php';
require_once __DIR__ . '/class/TakeposBranchService.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/stock/class/mouvementstock.class.php';

$langs->loadLangs(array('admin', 'products', 'stocks', 'takeposcustom@takepos'));

// ── Access control ────────────────────────────────────────────────────────────
if (empty($user) || empty($user->id)) {
    accessforbidden();
}

// Block branch users — they cannot transfer stock between warehouses
if (!$user->admin && TakeposBranchService::isBranchUser($db, (int) $user->id)) {
    accessforbidden($langs->trans('TakeposTransferBranchUserDenied'));
}

// Require admin OR store management permission
if (!$user->admin) {
    TakeposAccess::requireFrontendAccess(
        $db, $user,
        'takepos.store_governance',
        'takepos.store.manage',
        isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null,
        $langs->trans('TakeposTransferAccessDenied'),
        array('page' => 'stock_transfer.php')
    );
}

$entity = !empty($user->entity) ? (int) $user->entity : 1;

// ── Load warehouses ───────────────────────────────────────────────────────────
$warehouses = array();
$sqlWh = "SELECT e.rowid, e.ref, e.label,"
    . " COALESCE(b.label, '') AS branch_label,"
    . " COALESCE(b.code, '') AS branch_code"
    . " FROM " . MAIN_DB_PREFIX . "entrepot e"
    . " LEFT JOIN " . MAIN_DB_PREFIX . "takepos_branch b ON b.fk_warehouse = e.rowid AND b.entity = e.entity"
    . " WHERE e.entity IN (" . getEntity('stock') . ") AND e.statut IN (0, 1)"
    . " ORDER BY e.ref ASC";
$resWh = $db->query($sqlWh);
if ($resWh) {
    while ($obj = $db->fetch_object($resWh)) {
        $warehouses[] = $obj;
    }
}

// ── Load sellable products ────────────────────────────────────────────────────
$products = array();
$sqlProd  = "SELECT rowid, ref, label, barcode FROM " . MAIN_DB_PREFIX . "product"
    . " WHERE entity IN (" . getEntity('product') . ") AND fk_product_type = 0 AND tosell = 1"
    . " ORDER BY ref ASC"
    . $db->plimit(1000, 0);
$resProd = $db->query($sqlProd);
if ($resProd) {
    while ($obj = $db->fetch_object($resProd)) {
        $products[] = $obj;
    }
}

// ── Handle POST: perform transfer ─────────────────────────────────────────────
$messages = array();
$errors   = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    $token = (string) GETPOST('token', 'alpha');
    $sessionToken = isset($_SESSION['newtoken']) ? (string) $_SESSION['newtoken'] : '';
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        $errors[] = $langs->trans('TakeposTransferInvalidToken');
    } else {
        $srcWhId  = GETPOSTINT('src_warehouse_id');
        $dstWhId  = GETPOSTINT('dst_warehouse_id');
        $productId = GETPOSTINT('product_id');
        $qty       = (float) str_replace(',', '.', GETPOST('qty', 'none'));
        $note      = trim((string) GETPOST('note', 'restricthtml'));

        // Validation
        if ($srcWhId <= 0) $errors[] = $langs->trans('TakeposTransferSelectSource');
        if ($dstWhId <= 0) $errors[] = $langs->trans('TakeposTransferSelectDest');
        if ($srcWhId > 0 && $dstWhId > 0 && $srcWhId === $dstWhId) $errors[] = $langs->trans('TakeposTransferSameWarehouse');
        if ($productId <= 0) $errors[] = $langs->trans('TakeposTransferSelectProduct');
        if ($qty <= 0)        $errors[] = $langs->trans('TakeposTransferQtyPositive');

        // Check source stock
        if (empty($errors) && $srcWhId > 0 && $productId > 0) {
            $sqlStock = "SELECT reel FROM " . MAIN_DB_PREFIX . "product_stock"
                . " WHERE fk_product = " . $productId . " AND fk_entrepot = " . $srcWhId;
            $resStock = $db->query($sqlStock);
            $srcStock = 0;
            if ($resStock && $db->num_rows($resStock) > 0) {
                $objS = $db->fetch_object($resStock);
                $srcStock = (float) $objS->reel;
            }
            if ($qty > $srcStock) {
                $errors[] = $langs->transnoentitiesnoconv(
                    'TakeposTransferInsufficientStock',
                    number_format($qty, 0),
                    number_format($srcStock, 0)
                );
            }
        }

        if (empty($errors)) {
            // Load product for its ref (used in movement label)
            $prod = new Product($db);
            $prod->fetch($productId);
            $label = $langs->trans('TakeposTransferMovementLabel', $prod->ref, $prod->label);
            if ($note !== '') $label .= ' — ' . $note;

            $db->begin();
            $ok = true;

            // Stock OUT from source warehouse
            $mouv = new MouvementStock($db);
            $mouv->origin_type = 'takepos_transfer';
            $res1 = $mouv->livraison($user, $productId, $srcWhId, $qty, 0, $label);
            if ($res1 < 0) {
                $errors[] = $langs->trans('TakeposTransferOutFailed') . ': ' . implode(', ', $mouv->errors);
                $ok = false;
            }

            if ($ok) {
                // Stock IN to destination warehouse
                $mouv2 = new MouvementStock($db);
                $mouv2->origin_type = 'takepos_transfer';
                $res2 = $mouv2->reception($user, $productId, $dstWhId, $qty, 0, $label);
                if ($res2 < 0) {
                    $errors[] = $langs->trans('TakeposTransferInFailed') . ': ' . implode(', ', $mouv2->errors);
                    $ok = false;
                }
            }

            if ($ok) {
                $db->commit();
                $srcName = '';
                $dstName = '';
                foreach ($warehouses as $wh) {
                    if ((int) $wh->rowid === $srcWhId) $srcName = $wh->ref ?: $wh->label;
                    if ((int) $wh->rowid === $dstWhId) $dstName = $wh->ref ?: $wh->label;
                }
                $messages[] = $langs->transnoentitiesnoconv(
                    'TakeposTransferSuccess',
                    number_format($qty, 0),
                    dol_escape_htmltag($prod->ref),
                    dol_escape_htmltag($srcName),
                    dol_escape_htmltag($dstName)
                );
            } else {
                $db->rollback();
            }
        }
    }
}

// ── Live stock lookup AJAX ────────────────────────────────────────────────────
// If ?action=stock_check is called via JS, return JSON stock for a product+warehouse
if (GETPOST('action', 'aZ09') === 'stock_check') {
    $pid = GETPOSTINT('product_id');
    $wid = GETPOSTINT('warehouse_id');
    if (!headers_sent()) header('Content-Type: application/json; charset=UTF-8');
    $qty = 0;
    if ($pid > 0 && $wid > 0) {
        $sqlSC = "SELECT reel FROM " . MAIN_DB_PREFIX . "product_stock"
            . " WHERE fk_product=" . $pid . " AND fk_entrepot=" . $wid;
        $resSC = $db->query($sqlSC);
        if ($resSC && $db->num_rows($resSC) > 0) {
            $objSC = $db->fetch_object($resSC);
            $qty   = (float) $objSC->reel;
        }
    }
    echo json_encode(array('qty' => $qty));
    exit;
}

?><!DOCTYPE html>
<html lang="<?php echo dol_escape_htmltag((string) $langs->defaultlang); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo dol_escape_htmltag($langs->trans('TakeposTransferTitle')); ?></title>
<style>
*{box-sizing:border-box}
body{font-family:Arial,Helvetica,sans-serif;margin:0;background:#f5f7fb;color:#1f2937;font-size:13px}
.wrap{padding:16px;max-width:820px;margin:0 auto}
.card{background:#fff;border:1px solid #d9e2ef;border-radius:12px;padding:18px 20px;box-shadow:0 2px 10px rgba(15,23,42,.05);margin-bottom:14px}
h2{margin:0 0 4px 0;font-size:18px;color:#1B3A6B}
.sub{color:#6b7280;font-size:12px;margin:0 0 16px 0}
.form-group{margin-bottom:14px}
label{display:block;font-weight:600;font-size:12px;color:#374151;margin-bottom:4px}
select,input[type=text],input[type=number]{width:100%;padding:9px 11px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;background:#fff;color:#1f2937}
select:focus,input:focus{outline:2px solid #93c5fd;border-color:#3b82f6}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:600px){.row2{grid-template-columns:1fr}}
.stock-badge{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;margin-top:4px;background:#f1f5f9;color:#374151}
.stock-badge.ok{background:#d1fae5;color:#047857}
.stock-badge.low{background:#fef3c7;color:#b45309}
.stock-badge.out{background:#fee2e2;color:#b91c1c}
.arrow{text-align:center;font-size:28px;color:#1d4ed8;line-height:1;padding-top:24px}
.btn{padding:10px 16px;border:0;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.btn-submit{background:#1d4ed8;color:#fff;width:100%;justify-content:center;font-size:15px;padding:12px}
.btn-submit:hover{background:#1e40af}
.alert{padding:10px 12px;border-radius:8px;margin-bottom:10px;font-size:13px}
.alert-success{background:#d1fae5;border:1px solid #6ee7b7;color:#065f46}
.alert-error{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b}
.back-link{display:inline-flex;align-items:center;gap:5px;color:#1d4ed8;text-decoration:none;font-size:13px;margin-bottom:14px}
.back-link:hover{text-decoration:underline}
.history-link{color:#6366f1;font-size:12px;text-decoration:none}
.history-link:hover{text-decoration:underline}
</style>
</head>
<body>
<div class="wrap">

  <a href="<?php echo DOL_URL_ROOT; ?>/takepos/stock_all_branches.php" class="back-link">← <?php echo dol_escape_htmltag($langs->trans('TakeposShortcutStockAllBranches')); ?></a>

  <div class="card">
    <h2>🔄 <?php echo dol_escape_htmltag($langs->trans('TakeposTransferTitle')); ?></h2>
    <p class="sub"><?php echo dol_escape_htmltag($langs->trans('TakeposTransferDesc')); ?></p>

    <?php foreach ($messages as $msg): ?>
      <div class="alert alert-success">✅ <?php echo $msg; ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $err): ?>
      <div class="alert alert-error">❌ <?php echo dol_escape_htmltag($err); ?></div>
    <?php endforeach; ?>

    <?php if (empty($warehouses)): ?>
      <div class="alert alert-error"><?php echo dol_escape_htmltag($langs->trans('TakeposTransferNoWarehouses')); ?></div>
    <?php elseif (count($warehouses) < 2): ?>
      <div class="alert alert-error"><?php echo dol_escape_htmltag($langs->trans('TakeposTransferNeedTwoWarehouses')); ?></div>
    <?php else: ?>

    <form method="POST" id="transfer-form">
      <input type="hidden" name="token" value="<?php echo newToken(); ?>">

      <!-- Product -->
      <div class="form-group">
        <label><?php echo dol_escape_htmltag($langs->trans('TakeposTransferProduct')); ?> *</label>
        <select name="product_id" id="product_id" required onchange="onProductChange()">
          <option value=""><?php echo dol_escape_htmltag($langs->trans('TakeposTransferSelectProduct')); ?></option>
          <?php foreach ($products as $p): ?>
            <option value="<?php echo (int) $p->rowid; ?>"
              <?php echo ((GETPOSTINT('product_id') === (int)$p->rowid) ? ' selected' : ''); ?>>
              <?php echo dol_escape_htmltag($p->ref . ' — ' . $p->label . ($p->barcode ? ' [' . $p->barcode . ']' : '')); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Source → Destination -->
      <div class="row2">
        <!-- Source warehouse -->
        <div class="form-group">
          <label>📤 <?php echo dol_escape_htmltag($langs->trans('TakeposTransferSource')); ?> *</label>
          <select name="src_warehouse_id" id="src_warehouse_id" required onchange="onSrcChange()">
            <option value=""><?php echo dol_escape_htmltag($langs->trans('TakeposTransferSelectWarehouse')); ?></option>
            <?php foreach ($warehouses as $wh): ?>
              <option value="<?php echo (int) $wh->rowid; ?>"
                <?php echo ((GETPOSTINT('src_warehouse_id') === (int)$wh->rowid) ? ' selected' : ''); ?>>
                <?php
                  $label = $wh->ref ?: $wh->label;
                  if ($wh->branch_label) $label .= ' (' . $wh->branch_label . ')';
                  echo dol_escape_htmltag($label);
                ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div id="src_stock_badge" class="stock-badge" style="display:none"></div>
        </div>

        <div class="arrow">→</div>

        <!-- Destination warehouse -->
        <div class="form-group">
          <label>📥 <?php echo dol_escape_htmltag($langs->trans('TakeposTransferDest')); ?> *</label>
          <select name="dst_warehouse_id" id="dst_warehouse_id" required>
            <option value=""><?php echo dol_escape_htmltag($langs->trans('TakeposTransferSelectWarehouse')); ?></option>
            <?php foreach ($warehouses as $wh): ?>
              <option value="<?php echo (int) $wh->rowid; ?>"
                <?php echo ((GETPOSTINT('dst_warehouse_id') === (int)$wh->rowid) ? ' selected' : ''); ?>>
                <?php
                  $label = $wh->ref ?: $wh->label;
                  if ($wh->branch_label) $label .= ' (' . $wh->branch_label . ')';
                  echo dol_escape_htmltag($label);
                ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Quantity -->
      <div class="form-group">
        <label><?php echo dol_escape_htmltag($langs->trans('Qty')); ?> *</label>
        <input type="number" name="qty" id="qty" min="0.001" step="0.001"
               value="<?php echo dol_escape_htmltag(GETPOST('qty', 'none') ?: '1'); ?>" required>
      </div>

      <!-- Note -->
      <div class="form-group">
        <label><?php echo dol_escape_htmltag($langs->trans('Note')); ?></label>
        <input type="text" name="note" maxlength="200"
               value="<?php echo dol_escape_htmltag(GETPOST('note', 'alphanohtml')); ?>"
               placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposTransferNotePlaceholder')); ?>">
      </div>

      <button type="submit" class="btn btn-submit">
        🔄 <?php echo dol_escape_htmltag($langs->trans('TakeposTransferSubmit')); ?>
      </button>
    </form>

    <p style="margin-top:14px;font-size:11px;color:#9ca3af">
      <?php echo dol_escape_htmltag($langs->trans('TakeposTransferFootnote')); ?>
      <a href="<?php echo DOL_URL_ROOT; ?>/product/stock/mouvement.php" target="_blank" class="history-link">
        <?php echo dol_escape_htmltag($langs->trans('TakeposTransferViewHistory')); ?> ↗
      </a>
    </p>

    <?php endif; ?>
  </div>
</div>

<script>
// Live stock lookup when product or source warehouse changes
var stockCheckUrl = '<?php echo DOL_URL_ROOT; ?>/takepos/stock_transfer.php?action=stock_check';

function fetchSourceStock() {
    var pid = document.getElementById('product_id').value;
    var wid = document.getElementById('src_warehouse_id').value;
    var badge = document.getElementById('src_stock_badge');
    if (!pid || !wid) { badge.style.display = 'none'; return; }

    fetch(stockCheckUrl + '&product_id=' + pid + '&warehouse_id=' + wid)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var qty = parseFloat(data.qty) || 0;
            badge.textContent = '<?php echo addslashes($langs->trans("Stock")); ?>: ' + qty;
            badge.className = 'stock-badge ' + (qty <= 0 ? 'out' : qty <= 5 ? 'low' : 'ok');
            badge.style.display = 'inline-block';

            // Update max for qty field
            var qtyInput = document.getElementById('qty');
            if (qty > 0) qtyInput.max = qty;
        })
        .catch(function() { badge.style.display = 'none'; });
}

function onProductChange() { fetchSourceStock(); }
function onSrcChange()     { fetchSourceStock(); }

// Run on load in case form was submitted with errors (values pre-filled)
document.addEventListener('DOMContentLoaded', fetchSourceStock);
</script>
</body>
</html>
