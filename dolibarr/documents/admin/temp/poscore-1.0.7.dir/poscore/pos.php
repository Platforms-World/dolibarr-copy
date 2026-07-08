<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/poscore/class/PosSaasBridge.php';

$langs->loadLangs(array('main', 'products', 'companies', 'bills'));

$bridge = new PosSaasBridge($db);
$bridge->ensureTerminalAccess($conf, $user);
$form = new Form($db);
$token = newToken();

$thirdparties = array();
$sql = "SELECT rowid, nom FROM ".MAIN_DB_PREFIX."societe WHERE entity IN (".getEntity('societe').") AND client IN (1,2,3) AND status = 1 ORDER BY nom ASC";
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $thirdparties[] = array('id' => (int) $obj->rowid, 'name' => $obj->nom);
    }
}

$maxCashiers = $bridge->getLimitValue($conf->entity, 'max_cashiers', 'N/A');
$maxTerminals = $bridge->getLimitValue($conf->entity, 'max_terminals', 'N/A');
$maxSalesDay = $bridge->getLimitValue($conf->entity, 'max_sales_per_day', 'N/A');

llxHeader('', 'POS Terminal');
print load_fiche_titre('POS Terminal');
print '<div class="opacitymedium margintbottom">SaaS limits: Max cashiers: '.dol_escape_htmltag((string) $maxCashiers).' | Max terminals: '.dol_escape_htmltag((string) $maxTerminals).' | Max sales/day: '.dol_escape_htmltag((string) $maxSalesDay).'</div>';
print '<link rel="stylesheet" href="'.dol_buildpath('/custom/poscore/css/pos.css.php', 1).'">';
print '<script src="'.dol_buildpath('/custom/poscore/js/pos.js.php', 1).'"></script>';
print '<div class="poscore-layout">';

print '<div class="poscore-col poscore-left">';
print '<div class="titre">Products</div>';
print '<div class="poscore-panel">';
print '<label>Product search</label>';
print '<input type="text" id="pos-search" class="flat inputforselect width100" placeholder="Search by name, ref, barcode">';
print '<label>Barcode</label>';
print '<input type="text" id="pos-barcode" class="flat inputforselect width100" placeholder="Scan barcode">';
print '<div class="margintop"><button type="button" class="button" id="btn-search-products">Search</button></div>';
print '</div>';
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent" id="pos-search-results"><thead><tr class="liste_titre"><th>Product</th><th class="right">Price</th><th class="right">Stock</th><th class="center">Add</th></tr></thead><tbody><tr><td colspan="4" class="opacitymedium">No products loaded</td></tr></tbody></table></div>';
print '</div>';

print '<div class="poscore-col poscore-center">';
print '<div class="titre">Current Sale Cart</div>';
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent" id="pos-cart-table"><thead><tr class="liste_titre"><th>Product</th><th class="right">Qty</th><th class="right">Price</th><th class="right">Discount</th><th class="right">Total</th><th class="center">Remove</th></tr></thead><tbody><tr><td colspan="6" class="opacitymedium">Cart is empty</td></tr></tbody></table></div>';
print '<div class="pos-totals"><div><span>Subtotal</span><strong id="cart-subtotal">0.00</strong></div><div><span>Tax</span><strong id="cart-tax">0.00</strong></div><div class="pos-grand-line"><span>Total</span><strong id="cart-total">0.00</strong></div></div>';
print '</div>';

print '<div class="poscore-col poscore-right">';
print '<div class="titre">Payment</div>';
print '<div class="poscore-panel">';
print '<label>Customer</label><select id="pos-customer" class="flat width100"><option value="0">Select customer</option>';
foreach ($thirdparties as $tp) {
    print '<option value="'.$tp['id'].'">'.dol_escape_htmltag($tp['name']).'</option>';
}
print '</select>';
print '<div class="margintop"><button type="button" class="button button-pay" data-payment="cash">Cash</button> <button type="button" class="button button-pay" data-payment="card">Card</button> <button type="button" class="button button-pay" data-payment="mixed">Mixed payment</button></div>';
print '<div class="margintop"><button type="button" class="button" id="btn-hold-order">Hold order</button> <button type="button" class="button button-delete" id="btn-clear-cart">Clear cart</button></div>';
print '<div class="pos-payment-summary margintop"><div>Grand Total</div><div id="payment-grand-total">0.00</div></div>';
print '</div></div>';

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
