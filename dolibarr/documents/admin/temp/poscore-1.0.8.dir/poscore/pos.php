<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/poscore/class/PosSaasBridge.php';

$langs->loadLangs(array('main', 'products', 'companies', 'bills', 'poscore@poscore'));

$bridge = new PosSaasBridge($db);
$bridge->ensureTerminalAccess($conf, $user);
$form = new Form($db);
$token = newToken();

$customers = array();
$sql = "SELECT rowid, nom
        FROM ".MAIN_DB_PREFIX."societe
        WHERE entity IN (".getEntity('societe').")
          AND client IN (1,2,3)
          AND status = 1
        ORDER BY nom ASC";
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $customers[] = array('id' => (int) $obj->rowid, 'name' => $obj->nom);
    }
}

$maxCashiers = $bridge->getLimit($conf->entity, 'max_cashiers', 0);
$maxTerminals = $bridge->getLimit($conf->entity, 'max_terminals', 0);
$maxSalesPerDay = $bridge->getLimit($conf->entity, 'max_sales_per_day', 0);

llxHeader('', 'POS Terminal');
print load_fiche_titre('POS Terminal');
print '<div class="opacitymedium margintbottom">Tenant limits: Cashiers = '.((int) $maxCashiers).' | Terminals = '.((int) $maxTerminals).' | Sales/Day = '.((int) $maxSalesPerDay).'</div>';
print '<link rel="stylesheet" href="'.dol_buildpath('/custom/poscore/css/pos.css.php', 1).'">';
print '<script src="'.dol_buildpath('/custom/poscore/js/pos.js.php', 1).'"></script>';
print '<input type="hidden" id="poscore_token" value="'.$token.'">';

print '<div class="poscore-layout">';
print '<div class="poscore-col poscore-left">';
print '<div class="titre">Products</div>';
print '<div class="poscore-panel"><label>Product search</label><input type="text" id="pos-search" class="flat width100" placeholder="Search by product/ref/barcode"><label>Barcode</label><input type="text" id="pos-barcode" class="flat width100" placeholder="Scan barcode"><div class="margintop"><button type="button" class="button" id="btn-search-products">Search</button></div></div>';
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent" id="pos-search-results"><thead><tr class="liste_titre"><th>Product</th><th class="right">Price</th><th class="right">Stock</th><th class="center">Add</th></tr></thead><tbody><tr><td colspan="4" class="opacitymedium">No results</td></tr></tbody></table></div>';
print '</div>';

print '<div class="poscore-col poscore-center">';
print '<div class="titre">Current Sale Cart</div>';
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent" id="pos-cart-table"><thead><tr class="liste_titre"><th>Product</th><th class="right">Qty</th><th class="right">Price</th><th class="right">Discount</th><th class="right">Total</th><th class="center">Remove</th></tr></thead><tbody><tr><td colspan="6" class="opacitymedium">Cart is empty</td></tr></tbody></table></div>';
print '<div class="pos-totals"><div><span>Subtotal</span><strong id="cart-subtotal">0.00</strong></div><div><span>Tax</span><strong id="cart-tax">0.00</strong></div><div class="pos-grand-line"><span>Total</span><strong id="cart-total">0.00</strong></div></div>';
print '</div>';

print '<div class="poscore-col poscore-right">';
print '<div class="titre">Payment</div>';
print '<div class="poscore-panel"><label>Customer</label><select id="pos-customer" class="flat width100"><option value="0">Select customer</option>';
foreach ($customers as $c) {
    print '<option value="'.$c['id'].'">'.dol_escape_htmltag($c['name']).'</option>';
}
print '</select>';
print '<div class="margintop"><button type="button" class="button button-pay" data-payment="cash">Cash</button> <button type="button" class="button button-pay" data-payment="card">Card</button> <button type="button" class="button button-pay" data-payment="mixed">Mixed</button></div>';
print '<div class="margintop"><button type="button" class="button" id="btn-hold-order">Hold Order</button> <button type="button" class="button button-delete" id="btn-clear-cart">Clear Cart</button></div>';
print '<div class="pos-payment-summary margintop"><div>Grand Total</div><div id="payment-grand-total">0.00</div></div></div>';
print '</div>';
print '</div>';

print '<script>
window.POSCORE_CONF = {
  token: "'.$token.'",
  urls: {
    searchProducts: "'.dol_buildpath('/custom/poscore/ajax/search_products.php', 1).'",
    addToCart: "'.dol_buildpath('/custom/poscore/ajax/add_to_cart.php', 1).'",
    removeFromCart: "'.dol_buildpath('/custom/poscore/ajax/remove_from_cart.php', 1).'",
    createInvoice: "'.dol_buildpath('/custom/poscore/ajax/create_invoice.php', 1).'"
  }
};
</script>';

llxFooter();
$db->close();
