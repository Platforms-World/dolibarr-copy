<?php
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');
if (!defined('NOREQUIREAJAX')) define('NOREQUIREAJAX', '1');

require '../main.inc.php';
require_once __DIR__ . '/lib/takepos_lang.php';
require_once __DIR__ . '/lib/takepos_help.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/class/TakeposAccess.class.php';
require_once __DIR__ . '/class/TakeposPurchaseService.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';

$langs->loadLangs(array('cashdesk', 'main', 'products', 'stocks', 'suppliers', 'takeposcustom@takepos'));

$sessionTerminalToken = isset($_SESSION['takeposterminal']) ? (string) $_SESSION['takeposterminal'] : '1';
TakeposAccess::requireFrontendAccess(
    $db,
    $user,
    'takepos.purchases',
    'takepos.purchase.read',
    (int) $sessionTerminalToken,
    $langs->trans('TakeposPurchaseAccessDenied'),
    array('page' => 'purchases_v2.php')
);

if (!TakeposPurchaseService::canRead($db, $user)) {
    TakeposAccess::denyAccess($db, $user, $langs->trans('TakeposPurchaseReadPermissionRequired'), array('page' => 'purchases_v2.php'));
}
TakeposPurchaseService::ensureSchema($db);

$entity = !empty($user->entity) ? (int) $user->entity : 1;
$pageUrl = DOL_URL_ROOT . '/takepos/purchases_v2.php';
$form = new Form($db);
$messages = array();
$errors = array();
$purchaseId = GETPOSTINT('id');
$action = GETPOST('action', 'alpha');
$canCreatePurchase = TakeposPurchaseService::canCreate($db, $user);
$printMode = ($action === 'print' && $purchaseId > 0);

if (!empty($_GET['result'])) {
    if (GETPOST('result', 'alpha') === 'saved') $messages[] = $langs->trans('TakeposPurchaseSavedSuccess');
    if (GETPOST('result', 'alpha') === 'updated') $messages[] = $langs->trans('TakeposPurchaseUpdatedSuccess');
}

$warehouses = TakeposPurchaseService::listWarehouses($db, $entity);
$suppliers = TakeposPurchaseService::listSuppliers($db, $entity);
$products = TakeposPurchaseService::listBuyableProducts($db, $entity);

$defaultWarehouseId = 0;
$warehouseConst = 'CASHDESK_ID_WAREHOUSE' . $sessionTerminalToken;
if ((int) getDolGlobalInt($warehouseConst) > 0) $defaultWarehouseId = (int) getDolGlobalInt($warehouseConst);
elseif (!empty($warehouses[0]->rowid)) $defaultWarehouseId = (int) $warehouses[0]->rowid;

$currentPurchase = ($purchaseId > 0 ? TakeposPurchaseService::getPurchaseById($db, $entity, $purchaseId) : null);
$currentLines = ($purchaseId > 0 ? TakeposPurchaseService::listPurchaseLines($db, $entity, $purchaseId) : array());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) GETPOST('token', 'alpha');
    $sessionToken = isset($_SESSION['newtoken']) ? (string) $_SESSION['newtoken'] : '';
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        $errors[] = $langs->trans('TakeposPurchaseInvalidCsrf');
    } elseif (!$canCreatePurchase) {
        $errors[] = $langs->trans('TakeposPurchaseCreatePermissionRequired');
    } else {
        try {
            $postedLines = array();
            $productIds = isset($_POST['line_product_id']) && is_array($_POST['line_product_id']) ? $_POST['line_product_id'] : array();
            $qtys = isset($_POST['line_qty']) && is_array($_POST['line_qty']) ? $_POST['line_qty'] : array();
            $prices = isset($_POST['line_buy_price_ht']) && is_array($_POST['line_buy_price_ht']) ? $_POST['line_buy_price_ht'] : array();
            $vats = isset($_POST['line_tva_tx']) && is_array($_POST['line_tva_tx']) ? $_POST['line_tva_tx'] : array();
            $notes = isset($_POST['line_note']) && is_array($_POST['line_note']) ? $_POST['line_note'] : array();
            $count = max(count($productIds), count($qtys), count($prices), count($vats));
            for ($i = 0; $i < $count; $i++) {
                $postedLines[] = array(
                    'product_id' => isset($productIds[$i]) ? $productIds[$i] : 0,
                    'qty' => isset($qtys[$i]) ? $qtys[$i] : 0,
                    'buy_price_ht' => isset($prices[$i]) ? $prices[$i] : 0,
                    'tva_tx' => isset($vats[$i]) ? $vats[$i] : 0,
                    'note_line' => isset($notes[$i]) ? $notes[$i] : '',
                );
            }

            $payload = array(
                'warehouse_id' => GETPOSTINT('warehouse_id'),
                'supplier_id' => GETPOSTINT('supplier_id'),
                'purchase_date' => GETPOST('purchase_date', 'none'),
                'external_ref' => GETPOST('external_ref', 'alphanohtml'),
                'supplier_invoice_ref' => GETPOST('supplier_invoice_ref', 'alphanohtml'),
                'note_private' => GETPOST('note_private', 'restricthtml'),
                'lines' => $postedLines,
            );

            if ($action === 'update' && $purchaseId > 0) {
                $purchaseId = TakeposPurchaseService::updatePurchase($db, $user, $purchaseId, $payload);
                header('Location: ' . $pageUrl . '?id=' . ((int) $purchaseId) . '&result=updated');
                exit;
            }

            $purchaseId = TakeposPurchaseService::createPurchase($db, $user, $payload);
            header('Location: ' . $pageUrl . '?id=' . ((int) $purchaseId) . '&result=saved');
            exit;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$currentPurchase = ($purchaseId > 0 ? TakeposPurchaseService::getPurchaseById($db, $entity, $purchaseId) : null);
$currentLines = ($purchaseId > 0 ? TakeposPurchaseService::listPurchaseLines($db, $entity, $purchaseId) : array());
$recentPurchases = TakeposPurchaseService::listRecentPurchases($db, $entity, 16);

$formValues = array(
    'purchase_date' => (GETPOST('purchase_date', 'none') !== '' ? (string) GETPOST('purchase_date', 'none') : ($currentPurchase ? str_replace(' ', 'T', substr((string) $currentPurchase->purchase_date, 0, 16)) : date('Y-m-d\TH:i'))),
    'warehouse_id' => (GETPOSTINT('warehouse_id') > 0 ? GETPOSTINT('warehouse_id') : ($currentPurchase ? (int) $currentPurchase->fk_warehouse : $defaultWarehouseId)),
    'supplier_id' => (GETPOSTINT('supplier_id') > 0 ? GETPOSTINT('supplier_id') : ($currentPurchase ? (int) $currentPurchase->fk_supplier : 0)),
    'external_ref' => (GETPOST('external_ref', 'alpha') !== '' ? (string) GETPOST('external_ref', 'alpha') : ($currentPurchase ? (string) $currentPurchase->external_ref : '')),
    'supplier_invoice_ref' => (GETPOST('supplier_invoice_ref', 'alpha') !== '' ? (string) GETPOST('supplier_invoice_ref', 'alpha') : ($currentPurchase ? (string) $currentPurchase->supplier_invoice_ref : '')),
    'note_private' => (GETPOST('note_private', 'restricthtml') !== '' ? (string) GETPOST('note_private', 'restricthtml') : ($currentPurchase ? (string) $currentPurchase->note_private : '')),
);

$postedRows = array();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productIds = isset($_POST['line_product_id']) && is_array($_POST['line_product_id']) ? $_POST['line_product_id'] : array();
    $qtys = isset($_POST['line_qty']) && is_array($_POST['line_qty']) ? $_POST['line_qty'] : array();
    $prices = isset($_POST['line_buy_price_ht']) && is_array($_POST['line_buy_price_ht']) ? $_POST['line_buy_price_ht'] : array();
    $vats = isset($_POST['line_tva_tx']) && is_array($_POST['line_tva_tx']) ? $_POST['line_tva_tx'] : array();
    $notes = isset($_POST['line_note']) && is_array($_POST['line_note']) ? $_POST['line_note'] : array();
    $rowCount = max(count($productIds), count($qtys), count($prices), count($vats), 1);
    for ($i = 0; $i < $rowCount; $i++) {
        $postedRows[] = array(
            'product_id' => isset($productIds[$i]) ? (int) $productIds[$i] : 0,
            'qty' => isset($qtys[$i]) ? (string) $qtys[$i] : '',
            'buy_price_ht' => isset($prices[$i]) ? (string) $prices[$i] : '',
            'tva_tx' => isset($vats[$i]) ? (string) $vats[$i] : '0',
            'note_line' => isset($notes[$i]) ? (string) $notes[$i] : '',
        );
    }
} elseif (!empty($currentLines)) {
    foreach ($currentLines as $line) {
        $postedRows[] = array(
            'product_id' => (int) $line->fk_product,
            'qty' => (string) price2num($line->qty, 'MS'),
            'buy_price_ht' => (string) price2num($line->buy_price_ht, 'MU'),
            'tva_tx' => (string) price2num($line->tva_tx, 'MU'),
            'note_line' => (string) $line->note_line,
        );
    }
}
if (empty($postedRows)) {
    // FIX (stock-branch-v5): If coming from the low-stock dashboard widget,
    // pre-fill the first line with the suggested product and reorder quantity.
    $prefillProductId = GETPOSTINT('prefill_product_id');
    $prefillQty       = (float) str_replace(',', '.', GETPOST('prefill_qty', 'none'));
    if ($prefillProductId > 0) {
        $postedRows[] = array(
            'product_id'   => $prefillProductId,
            'qty'          => ($prefillQty > 0 ? (string) $prefillQty : '1'),
            'buy_price_ht' => '',
            'tva_tx'       => '0',
            'note_line'    => '',
        );
    } else {
        $postedRows[] = array('product_id' => 0, 'qty' => '1', 'buy_price_ht' => '', 'tva_tx' => '0', 'note_line' => '');
    }
}

if ($printMode && $currentPurchase) {
    ?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title><?php echo dol_escape_htmltag($currentPurchase->ref); ?></title>
<link rel="stylesheet" href="<?php echo DOL_URL_ROOT; ?>/theme/common.css.php">
<style>
body{font-family:Arial,sans-serif;padding:20px;color:#111}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:8px;text-align:left}h1{margin:0 0 10px}.meta{display:grid;grid-template-columns:repeat(2,minmax(240px,1fr));gap:12px;margin:16px 0}.meta div{border:1px solid #ddd;padding:10px}.totals{margin-top:16px;width:340px;margin-left:auto}.actions{margin-bottom:12px}@media print {.actions{display:none}}</style>
</head><body onload="window.print()">
<div class="actions"><a href="<?php echo dol_escape_htmltag($pageUrl . '?id=' . ((int) $purchaseId)); ?>"><span class="fa fa-arrow-left"></span> <?php echo dol_escape_htmltag($langs->trans('Back')); ?></a></div>
<h1><?php echo dol_escape_htmltag($langs->trans('TakeposPurchasePrintTitle')); ?> - <?php echo dol_escape_htmltag($currentPurchase->ref); ?></h1>
<div class="meta">
<div><strong><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseDate')); ?>:</strong> <?php echo dol_escape_htmltag($currentPurchase->purchase_date); ?></div>
<div><strong><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseWarehouse')); ?>:</strong> <?php echo dol_escape_htmltag(trim((string)$currentPurchase->warehouse_ref.' - '.(string)$currentPurchase->warehouse_label)); ?></div>
<div><strong><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseSupplier')); ?>:</strong> <?php echo dol_escape_htmltag(!empty($currentPurchase->supplier_name) ? $currentPurchase->supplier_name : $langs->trans('TakeposPurchaseWalkInSupplier')); ?></div>
<div><strong><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseExternalRef')); ?>:</strong> <?php echo dol_escape_htmltag((string)$currentPurchase->external_ref); ?></div>
</div>
<table><thead><tr><th><?php echo dol_escape_htmltag($langs->trans('Ref')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('Label')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('Qty')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseUnitPriceHt')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('VAT')); ?> %</th><th><?php echo dol_escape_htmltag($langs->trans('TotalHT')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TotalTTC')); ?></th></tr></thead><tbody>
<?php foreach ($currentLines as $line) { ?><tr><td><?php echo dol_escape_htmltag($line->product_ref); ?></td><td><?php echo dol_escape_htmltag($line->product_label); ?></td><td><?php echo dol_escape_htmltag(price($line->qty,0,'',1,0,0,'',0,0)); ?></td><td><?php echo dol_escape_htmltag(price($line->buy_price_ht,0,'',1,0,0,'',0,0)); ?></td><td><?php echo dol_escape_htmltag(price($line->tva_tx,0,'',1,0,0,'',0,0)); ?></td><td><?php echo dol_escape_htmltag(price($line->total_ht,0,'',1,0,0,'',0,0)); ?></td><td><?php echo dol_escape_htmltag(price($line->total_ttc,0,'',1,0,0,'',0,0)); ?></td></tr><?php } ?>
</tbody></table>
<table class="totals"><tr><th><?php echo dol_escape_htmltag($langs->trans('TotalHT')); ?></th><td><?php echo dol_escape_htmltag(price($currentPurchase->total_ht,0,'',1,0,0,'',0,0)); ?></td></tr><tr><th><?php echo dol_escape_htmltag($langs->trans('VAT')); ?></th><td><?php echo dol_escape_htmltag(price($currentPurchase->total_tva,0,'',1,0,0,'',0,0)); ?></td></tr><tr><th><?php echo dol_escape_htmltag($langs->trans('TotalTTC')); ?></th><td><?php echo dol_escape_htmltag(price($currentPurchase->total_ttc,0,'',1,0,0,'',0,0)); ?></td></tr></table>
<?php echo takeposHelpRender($langs, __FILE__); ?>
</body></html><?php
    exit;
}

$title = $langs->trans('TakeposPurchaseTitle');
$head = '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
$arrayofcss = array('/takepos/css/workspace_v2.css', '/takepos/css/purchases.css');
top_htmlhead($head, $title, 0, 0, array(), $arrayofcss);
$productOptions = array();
foreach ($products as $product) {
    $productOptions[(int) $product->rowid] = array(
        'id' => (int) $product->rowid,
        'ref' => (string) $product->ref,
        'label' => (string) $product->label,
        'barcode' => (string) (isset($product->barcode) ? $product->barcode : ''),
        'price' => (float) price2num((string) $product->price, 'MU'),
        'pmp' => (float) price2num((string) $product->pmp, 'MU'),
    );
}
?>
<body class="kfv2-body">
<?php
$v2PageTitle = $langs->trans('TakeposPurchasesTitle');
$v2PageIcon  = 'fa-truck-arrow-right';
$v2BackUrl   = DOL_URL_ROOT . '/takepos/pos_v2.php';
require __DIR__ . '/topbar_v2.php';
?>
<div class="takepos-workspace-reports-page takepos-purchase-page">
    <div class="takepos-purchase-header-row">
        <h2 style="display:none"><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseTitle')); ?></h2>
        <div class="takepos-purchase-top-actions">
            <a href="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/workspace.php?key=stock_overview'); ?>" class="butActionRefused"><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseOpenStock')); ?></a>
            <?php if ($currentPurchase) { ?>
                <a href="<?php echo dol_escape_htmltag($pageUrl . '?id=' . ((int) $currentPurchase->rowid) . '&action=print'); ?>" class="butAction"><?php echo dol_escape_htmltag($langs->trans('TakeposPurchasePrintReceipt')); ?></a>
                <a href="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/cheques.php?prefill_purchase_id=' . ((int) $currentPurchase->rowid) . '&prefill_supplier_id=' . ((int) $currentPurchase->fk_supplier) . '&prefill_amount=' . urlencode(price($currentPurchase->total_ttc, 0, '', 1, 0, 0, '', 0, 0))); ?>" class="butAction"><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseCreateCheque')); ?></a>
                <?php if (!empty($currentPurchase->supplier_invoice_url)) { ?><a href="<?php echo dol_escape_htmltag($currentPurchase->supplier_invoice_url); ?>" class="butAction"><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseOpenDolSupplierInvoice')); ?></a><?php } ?>
            <?php } ?>
        </div>
    </div>

    <?php foreach ($messages as $message) { ?><div class="ok"><?php echo dol_escape_htmltag($message); ?></div><?php } ?>
    <?php foreach ($errors as $errorMessage) { ?><div class="error"><?php echo dol_escape_htmltag($errorMessage); ?></div><?php } ?>
    <?php if (empty($warehouses)) { ?><div class="warning"><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseNoWarehouses')); ?></div><?php } ?>

    <section class="takepos-workspace-panel takepos-purchase-panel">
        <div class="kfv2-card-block-head"><h3 style="margin:0;font-size:14.5px"><?php echo dol_escape_htmltag($currentPurchase ? $langs->trans('TakeposPurchaseEditReceipt') . ': ' . $currentPurchase->ref : $langs->trans('TakeposPurchaseCreateReceipt')); ?></h3></div><div class="kfv2-card-block-body">
        <form method="post" action="<?php echo dol_escape_htmltag($pageUrl . ($purchaseId > 0 ? '?id=' . ((int) $purchaseId) : '')); ?>" id="takepos-purchase-form">
            <input type="kfv2-hidden" name="token" value="<?php echo dol_escape_htmltag(newToken()); ?>">
            <div class="takepos-workspace-filter-grid takepos-purchase-grid">
                <div>
                    <label for="purchase_date"><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseDate')); ?></label>
                    <input type="datetime-local" id="purchase_date" name="purchase_date" value="<?php echo dol_escape_htmltag($formValues['purchase_date']); ?>">
                </div>
                <div>
                    <label for="warehouse_id"><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseWarehouse')); ?></label>
                    <select id="warehouse_id" name="warehouse_id" required>
                        <option value="0"><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseSelectWarehouse')); ?></option>
                        <?php foreach ($warehouses as $warehouse) { ?><option value="<?php echo (int) $warehouse->rowid; ?>"<?php echo ((int) $formValues['warehouse_id'] === (int) $warehouse->rowid ? ' selected' : ''); ?>><?php echo dol_escape_htmltag(trim((string) $warehouse->ref . ' - ' . (string) $warehouse->label)); ?></option><?php } ?>
                    </select>
                </div>
                <div>
                    <label for="supplier_search"><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseSupplierQuickSearch')); ?></label>
                    <input type="text" id="supplier_search" placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseSupplierQuickSearchPlaceholder')); ?>">
                </div>
                <div>
                    <label for="supplier_id"><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseSupplier')); ?></label>
                    <select id="supplier_id" name="supplier_id">
                        <option value="0"><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseWalkInSupplier')); ?></option>
                        <?php foreach ($suppliers as $supplier) { $supplierLabel = trim((string) $supplier->nom . (!empty($supplier->code_fournisseur) ? ' [' . $supplier->code_fournisseur . ']' : '')); ?><option value="<?php echo (int) $supplier->rowid; ?>" data-search="<?php echo dol_escape_htmltag(mb_strtolower($supplierLabel)); ?>"<?php echo ((int) $formValues['supplier_id'] === (int) $supplier->rowid ? ' selected' : ''); ?>><?php echo dol_escape_htmltag($supplierLabel); ?></option><?php } ?>
                    </select>
                </div>
                <div>
                    <label for="external_ref"><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseExternalRef')); ?></label>
                    <input type="text" id="external_ref" name="external_ref" value="<?php echo dol_escape_htmltag($formValues['external_ref']); ?>" placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseExternalRefPlaceholder')); ?>">
                </div>
                <div>
                    <label for="supplier_invoice_ref"><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseSupplierInvoiceRef')); ?></label>
                    <input type="text" id="supplier_invoice_ref" name="supplier_invoice_ref" value="<?php echo dol_escape_htmltag($formValues['supplier_invoice_ref']); ?>" placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseSupplierInvoiceRefPlaceholder')); ?>">
                </div>
                <div class="takepos-purchase-grid-span-2">
                    <label for="note_private"><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseNote')); ?></label>
                    <input type="text" id="note_private" name="note_private" value="<?php echo dol_escape_htmltag($formValues['note_private']); ?>" placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseNotePlaceholder')); ?>">
                </div>
            </div>

            <div class="takepos-purchase-tools">
                <div>
                    <label for="barcode_input"><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseBarcodeInput')); ?></label>
                    <input type="text" id="barcode_input" placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseBarcodePlaceholder')); ?>">
                </div>
                <div class="takepos-purchase-tools-wide">
                    <label for="product_search"><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseProductQuickSearch')); ?></label>
                    <input type="text" id="product_search" placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseProductQuickSearchPlaceholder')); ?>">
                </div>
                <div class="takepos-purchase-tools-actions"><button type="button" class="butAction" onclick="addPurchaseLine();"><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseAddLine')); ?></button></div>
            </div>

            <div class="takepos-purchase-table-wrap">
                <table class="noborder takepos-purchase-table">
                    <thead>
                        <tr class="liste_titre">
                            <th><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseProduct')); ?></th>
                            <th><?php echo dol_escape_htmltag($langs->trans('Qty')); ?></th>
                            <th><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseUnitPriceHt')); ?></th>
                            <th><?php echo dol_escape_htmltag($langs->trans('VAT')); ?> %</th>
                            <th><?php echo dol_escape_htmltag($langs->trans('TakeposPurchasePmp')); ?></th>
                            <th><?php echo dol_escape_htmltag($langs->trans('Note')); ?></th>
                            <th><?php echo dol_escape_htmltag($langs->trans('TotalHT')); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="purchase-lines-body">
                    <?php foreach ($postedRows as $row) { ?>
                        <tr class="purchase-line-row">
                            <td>
                                <select name="line_product_id[]" class="purchase-product-select" required>
                                    <option value="0"><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseSelectProduct')); ?></option>
                                    <?php foreach ($products as $product) { $productLabel = trim((string) $product->ref . ' - ' . (string) $product->label); ?><option value="<?php echo (int) $product->rowid; ?>" data-default-price="<?php echo dol_escape_htmltag((string) price2num((string) $product->price, 'MU')); ?>" data-pmp="<?php echo dol_escape_htmltag((string) price2num((string) $product->pmp, 'MU')); ?>" data-ref="<?php echo dol_escape_htmltag((string) $product->ref); ?>" data-label="<?php echo dol_escape_htmltag((string) $product->label); ?>" data-barcode="<?php echo dol_escape_htmltag((string) $product->barcode); ?>"<?php echo ((int) $row['product_id'] === (int) $product->rowid ? ' selected' : ''); ?>><?php echo dol_escape_htmltag($productLabel); ?></option><?php } ?>
                                </select>
                            </td>
                            <td><input type="number" name="line_qty[]" class="purchase-line-qty" min="0.001" step="0.001" value="<?php echo dol_escape_htmltag($row['qty']); ?>" required></td>
                            <td><input type="number" name="line_buy_price_ht[]" class="purchase-line-price" min="0" step="0.001" value="<?php echo dol_escape_htmltag($row['buy_price_ht']); ?>" required></td>
                            <td><input type="number" name="line_tva_tx[]" class="purchase-line-vat" min="0" step="0.001" value="<?php echo dol_escape_htmltag($row['tva_tx']); ?>"></td>
                            <td><input type="text" class="purchase-line-pmp" readonly value=""></td>
                            <td><input type="text" name="line_note[]" value="<?php echo dol_escape_htmltag($row['note_line']); ?>"></td>
                            <td><input type="text" class="purchase-line-total-ht" value="0.000" readonly></td>
                            <td><button type="button" class="butActionDelete" onclick="removePurchaseLine(this)"><?php echo dol_escape_htmltag($langs->trans('Delete')); ?></button></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>

            <div class="takepos-workspace-filter-grid takepos-purchase-grid takepos-purchase-totals-grid">
                <div><label><?php echo dol_escape_htmltag($langs->trans('TotalHT')); ?></label><input type="text" id="purchase-total-ht" value="0.000" readonly></div>
                <div><label><?php echo dol_escape_htmltag($langs->trans('VAT')); ?></label><input type="text" id="purchase-total-vat" value="0.000" readonly></div>
                <div><label><?php echo dol_escape_htmltag($langs->trans('TotalTTC')); ?></label><input type="text" id="purchase-total-ttc" value="0.000" readonly></div>
                <div><label><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseStockImpact')); ?></label><input type="text" value="<?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseStockImpactHint')); ?>" readonly></div>
            </div>

            <div class="takepos-purchase-submit-row">
                <?php if ($currentPurchase) { ?><button type="submit" name="action" value="update" class="butAction"><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseUpdateAndReceive')); ?></button><?php } else { ?><button type="submit" name="action" value="save" class="butAction"><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseSaveAndReceive')); ?></button><?php } ?>
                <a href="<?php echo dol_escape_htmltag($pageUrl); ?>" class="butActionRefused"><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseNewReceipt')); ?></a>
            </div>
        </form>
    </section>

    <?php if ($currentPurchase) { ?>
    <section class="takepos-workspace-panel takepos-purchase-panel">
        <div class="kfv2-card-block-head"><h3 style="margin:0;font-size:14.5px"><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseLastReceipt')); ?>: <?php echo dol_escape_htmltag($currentPurchase->ref); ?></h3></div><div class="kfv2-card-block-body">
        <div class="takepos-workspace-filter-grid takepos-purchase-grid">
            <div><label><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseDate')); ?></label><input type="text" readonly value="<?php echo dol_escape_htmltag($currentPurchase->purchase_date); ?>"></div>
            <div><label><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseSupplier')); ?></label><input type="text" readonly value="<?php echo dol_escape_htmltag(!empty($currentPurchase->supplier_name) ? $currentPurchase->supplier_name : $langs->trans('TakeposPurchaseWalkInSupplier')); ?>"></div>
            <div><label><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseWarehouse')); ?></label><input type="text" readonly value="<?php echo dol_escape_htmltag(trim((string) $currentPurchase->warehouse_ref . ' - ' . (string) $currentPurchase->warehouse_label)); ?>"></div>
            <div><label><?php echo dol_escape_htmltag($langs->trans('TotalTTC')); ?></label><input type="text" readonly value="<?php echo dol_escape_htmltag(price((float) $currentPurchase->total_ttc, 0, '', 1, 0, 0, '', 0, 0)); ?>"></div>
            <div><label><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseExternalRef')); ?></label><input type="text" readonly value="<?php echo dol_escape_htmltag((string) $currentPurchase->external_ref); ?>"></div>
            <div><label><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseSupplierInvoiceRef')); ?></label><input type="text" readonly value="<?php echo dol_escape_htmltag((string) $currentPurchase->supplier_invoice_ref); ?>"></div>
        </div>
        <div class="takepos-purchase-table-wrap">
            <table class="noborder takepos-purchase-table compact">
                <thead><tr class="liste_titre"><th><?php echo dol_escape_htmltag($langs->trans('Ref')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('Label')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('Qty')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseUnitPriceHt')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('VAT')); ?> %</th><th><?php echo dol_escape_htmltag($langs->trans('TotalTTC')); ?></th></tr></thead>
                <tbody><?php foreach ($currentLines as $line) { ?><tr><td><?php echo dol_escape_htmltag($line->product_ref); ?></td><td><?php echo dol_escape_htmltag($line->product_label); ?></td><td><?php echo dol_escape_htmltag(price((float) $line->qty, 0, '', 1, 0, 0, '', 0, 0)); ?></td><td><?php echo dol_escape_htmltag(price((float) $line->buy_price_ht, 0, '', 1, 0, 0, '', 0, 0)); ?></td><td><?php echo dol_escape_htmltag(price((float) $line->tva_tx, 0, '', 1, 0, 0, '', 0, 0)); ?></td><td><?php echo dol_escape_htmltag(price((float) $line->total_ttc, 0, '', 1, 0, 0, '', 0, 0)); ?></td></tr><?php } ?></tbody>
            </table>
        </div>
    </section>
    <?php } ?>

    <section class="takepos-workspace-panel takepos-purchase-panel">
        <div class="kfv2-card-block-head"><h3 style="margin:0;font-size:14.5px"><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseRecentReceipts')); ?></h3></div><div class="kfv2-card-block-body">
        <div class="takepos-purchase-table-wrap">
            <table class="noborder takepos-purchase-table compact">
                <thead><tr class="liste_titre"><th><?php echo dol_escape_htmltag($langs->trans('Ref')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('Date')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('ThirdParty')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('Warehouse')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseExternalRef')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('Amount')); ?></th></tr></thead>
                <tbody><?php if (empty($recentPurchases)) { ?><tr><td colspan="6" class="opacitymedium"><?php echo dol_escape_htmltag($langs->trans('TakeposPurchaseNoReceiptsYet')); ?></td></tr><?php } else { foreach ($recentPurchases as $purchase) { ?><tr><td><a href="<?php echo dol_escape_htmltag($pageUrl . '?id=' . ((int) $purchase->rowid)); ?>"><?php echo dol_escape_htmltag($purchase->ref); ?></a></td><td><?php echo dol_escape_htmltag($purchase->purchase_date); ?></td><td><?php echo dol_escape_htmltag(!empty($purchase->supplier_name) ? $purchase->supplier_name : $langs->trans('TakeposPurchaseWalkInSupplier')); ?></td><td><?php echo dol_escape_htmltag(trim((string) $purchase->warehouse_ref . ' - ' . (string) $purchase->warehouse_label)); ?></td><td><?php echo dol_escape_htmltag((string) $purchase->external_ref); ?></td><td><?php echo dol_escape_htmltag(price((float) $purchase->total_ttc, 0, '', 1, 0, 0, '', 0, 0)); ?></td></tr><?php } } ?></tbody>
            </table>
        </div>
    </section>
</div>

<script>
const purchaseLineTemplate = <?php echo json_encode('<tr class="purchase-line-row">'
    . '<td><select name="line_product_id[]" class="purchase-product-select" required><option value="0">' . dol_escape_js($langs->trans('TakeposPurchaseSelectProduct')) . '</option>'
    . (function () use ($products) {
        $html = '';
        foreach ($products as $product) {
            $label = trim((string) $product->ref . ' - ' . (string) $product->label);
            $html .= '<option value="' . ((int) $product->rowid) . '" data-default-price="' . dol_escape_htmltag((string) price2num((string) $product->price, 'MU')) . '" data-pmp="' . dol_escape_htmltag((string) price2num((string) $product->pmp, 'MU')) . '" data-ref="' . dol_escape_htmltag((string) $product->ref) . '" data-label="' . dol_escape_htmltag((string) $product->label) . '" data-barcode="' . dol_escape_htmltag((string) $product->barcode) . '">' . dol_escape_htmltag($label) . '</option>';
        }
        return $html;
    })()
    . '</select></td>'
    . '<td><input type="number" name="line_qty[]" class="purchase-line-qty" min="0.001" step="0.001" value="1" required></td>'
    . '<td><input type="number" name="line_buy_price_ht[]" class="purchase-line-price" min="0" step="0.001" value="" required></td>'
    . '<td><input type="number" name="line_tva_tx[]" class="purchase-line-vat" min="0" step="0.001" value="0"></td>'
    . '<td><input type="text" class="purchase-line-pmp" readonly value=""></td>'
    . '<td><input type="text" name="line_note[]" value=""></td>'
    . '<td><input type="text" class="purchase-line-total-ht" value="0.000" readonly></td>'
    . '<td><button type="button" class="butActionDelete" onclick="removePurchaseLine(this)">' . dol_escape_js($langs->trans('Delete')) . '</button></td>'
    . '</tr>'); ?>;

function addPurchaseLine(productId = null) {
    const body = document.getElementById('purchase-lines-body');
    body.insertAdjacentHTML('beforeend', purchaseLineTemplate);
    const row = body.lastElementChild;
    bindPurchaseRows(row);
    if (productId) {
        const select = row.querySelector('.purchase-product-select');
        if (select) select.value = String(productId);
        applyProductDefaults(row, true);
    }
    refreshPurchaseTotals();
}

function removePurchaseLine(btn) {
    const body = document.getElementById('purchase-lines-body');
    if (body.querySelectorAll('.purchase-line-row').length <= 1) return;
    const row = btn.closest('.purchase-line-row');
    if (row) row.remove();
    refreshPurchaseTotals();
}

function applyProductDefaults(row, force = false) {
    const select = row.querySelector('.purchase-product-select');
    if (!select) return;
    const opt = select.options[select.selectedIndex];
    const priceInput = row.querySelector('.purchase-line-price');
    const pmpInput = row.querySelector('.purchase-line-pmp');
    if (priceInput && opt && opt.dataset.defaultPrice && (force || !priceInput.value)) priceInput.value = opt.dataset.defaultPrice;
    if (pmpInput) pmpInput.value = opt && opt.dataset.pmp ? Number(opt.dataset.pmp || 0).toFixed(3) : '';
}

function bindPurchaseRows(singleRow = null) {
    const rows = singleRow ? [singleRow] : Array.from(document.querySelectorAll('.purchase-line-row'));
    rows.forEach(function(row) {
        row.querySelectorAll('input, select').forEach(function(el) {
            if (el.dataset.bound === '1') return;
            el.dataset.bound = '1';
            el.addEventListener('input', refreshPurchaseTotals);
            el.addEventListener('change', function() {
                if (el.classList.contains('purchase-product-select')) applyProductDefaults(row, false);
                refreshPurchaseTotals();
            });
        });
        applyProductDefaults(row, false);
    });
}

function refreshPurchaseTotals() {
    let totalHt = 0, totalVat = 0, totalTtc = 0;
    document.querySelectorAll('.purchase-line-row').forEach(function(row) {
        const qty = parseFloat(row.querySelector('.purchase-line-qty')?.value || '0');
        const price = parseFloat(row.querySelector('.purchase-line-price')?.value || '0');
        const vat = parseFloat(row.querySelector('.purchase-line-vat')?.value || '0');
        const lineHt = qty * price;
        const lineVat = lineHt * vat / 100;
        const lineTtc = lineHt + lineVat;
        const totalField = row.querySelector('.purchase-line-total-ht');
        if (totalField) totalField.value = isFinite(lineHt) ? lineHt.toFixed(3) : '0.000';
        totalHt += isFinite(lineHt) ? lineHt : 0;
        totalVat += isFinite(lineVat) ? lineVat : 0;
        totalTtc += isFinite(lineTtc) ? lineTtc : 0;
    });
    document.getElementById('purchase-total-ht').value = totalHt.toFixed(3);
    document.getElementById('purchase-total-vat').value = totalVat.toFixed(3);
    document.getElementById('purchase-total-ttc').value = totalTtc.toFixed(3);
}

function findProductIdByText(term) {
    const q = String(term || '').trim().toLowerCase();
    if (!q) return null;
    for (const select of document.querySelectorAll('.purchase-product-select')) {
        for (const opt of select.options) {
            if (!opt.value || opt.value === '0') continue;
            const hay = [opt.text, opt.dataset.ref, opt.dataset.label, opt.dataset.barcode].join(' ').toLowerCase();
            if (hay.includes(q)) return opt.value;
        }
        break;
    }
    return null;
}

function addOrIncrementProduct(productId) {
    if (!productId) return;
    for (const row of document.querySelectorAll('.purchase-line-row')) {
        const select = row.querySelector('.purchase-product-select');
        if (select && select.value === String(productId)) {
            const qty = row.querySelector('.purchase-line-qty');
            qty.value = (parseFloat(qty.value || '0') + 1).toFixed(3).replace(/\.000$/, '');
            refreshPurchaseTotals();
            return;
        }
    }
    addPurchaseLine(productId);
}

document.getElementById('barcode_input')?.addEventListener('keydown', function(e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const productId = findProductIdByText(this.value);
    if (productId) { addOrIncrementProduct(productId); this.value = ''; }
});

document.getElementById('product_search')?.addEventListener('keydown', function(e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const productId = findProductIdByText(this.value);
    if (productId) addOrIncrementProduct(productId);
});

document.getElementById('supplier_search')?.addEventListener('input', function() {
    const q = String(this.value || '').trim().toLowerCase();
    const select = document.getElementById('supplier_id');
    if (!select || !q) return;
    for (const opt of select.options) {
        if ((opt.dataset.search || '').includes(q)) { select.value = opt.value; break; }
    }
});

bindPurchaseRows();
refreshPurchaseTotals();
</script>
</body>
</html>
