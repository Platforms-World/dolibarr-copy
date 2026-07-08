<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/poscore/class/service/PosSaasBridge.php';
require_once DOL_DOCUMENT_ROOT.'/custom/poscore/class/service/PosCartService.php';

$langs->loadLangs(array('main', 'products', 'companies', 'bills', 'poscore@poscore'));

$bridge = new PosSaasBridge($db);
$bridge->requireAccess('pos_terminal', 'poscore.cashier', 'none');
$form = new Form($db);
$token = newToken();
$cartService = new PosCartService($db);
$cart = $cartService->getCart((int) $conf->entity, (int) $user->id);

$thirdparties = array();
$sql = "SELECT rowid, nom
        FROM ".MAIN_DB_PREFIX."societe
        WHERE entity IN (".getEntity('societe').")
          AND client IN (1,2,3)
          AND status = 1
        ORDER BY nom ASC";
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $thirdparties[] = array('id' => (int) $obj->rowid, 'name' => $obj->nom);
    }
}

$maxCashiers = $bridge->getLimitValue('max_cashiers', 'N/A');
$maxTerminals = $bridge->getLimitValue('max_terminals', 'N/A');
$maxSalesDay = $bridge->getLimitValue('max_sales_per_day', 'N/A');

llxHeader('', 'POS Terminal');
print load_fiche_titre('POS Terminal');
print '<div class="opacitymedium margintbottom">SaaS limits: Max cashiers: '.dol_escape_htmltag((string) $maxCashiers).' | Max terminals: '.dol_escape_htmltag((string) $maxTerminals).' | Max sales/day: '.dol_escape_htmltag((string) $maxSalesDay).'</div>';
print '<link rel="stylesheet" href="'.dol_buildpath('/poscore/css/pos.css.php', 1).'">';
print '<script src="'.dol_buildpath('/poscore/js/pos.js.php', 1).'"></script>';
print '<input type="hidden" id="poscore_token" value="'.$token.'">';

print '<div class="poscore-layout">';

print '<div class="poscore-col poscore-left">';
print '<div class="titre">Products</div>';
print '<div class="poscore-panel">';
print '<label>Product search</label>';
print '<input type="text" id="pos-search" class="flat width100" placeholder="Search by name, ref, barcode">';
print '<label>Barcode</label>';
print '<input type="text" id="pos-barcode" class="flat width100" placeholder="Scan barcode">';
print '<div class="margintop"><button type="button" class="button" id="btn-search-products">Search</button></div>';
print '</div>';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent" id="pos-search-results">';
print '<thead><tr class="liste_titre"><th>Product</th><th class="right">Price</th><th class="right">Stock</th><th class="center">Add</th></tr></thead>';
print '<tbody><tr><td colspan="4" class="opacitymedium">No products loaded</td></tr></tbody>';
print '</table></div></div>';

print '<div class="poscore-col poscore-center">';
print '<div class="titre">Current Sale Cart</div>';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent" id="pos-cart-table">';
print '<thead><tr class="liste_titre"><th>Product</th><th class="right">Qty</th><th class="right">Price</th><th class="right">Discount</th><th class="right">Total</th><th class="center">Remove</th></tr></thead>';
print '<tbody></tbody></table></div>';
print '<div class="pos-totals">';
print '<div><span>Subtotal</span><strong id="cart-subtotal">'.price($cart['subtotal'], 0, '', 1, -1, -1, $conf->currency).'</strong></div>';
print '<div><span>Tax</span><strong id="cart-tax">'.price($cart['tax'], 0, '', 1, -1, -1, $conf->currency).'</strong></div>';
print '<div class="pos-grand-line"><span>Total</span><strong id="cart-total">'.price($cart['total'], 0, '', 1, -1, -1, $conf->currency).'</strong></div>';
print '</div></div>';

print '<div class="poscore-col poscore-right">';
print '<div class="titre">Payment</div>';
print '<div class="poscore-panel">';
print '<label>Customer</label>';
print '<select id="pos-customer" class="flat width100"><option value="0">Select customer</option>';
foreach ($thirdparties as $tp) {
    print '<option value="'.$tp['id'].'">'.dol_escape_htmltag($tp['name']).'</option>';
}
print '</select>';
print '<div class="margintop">';
print '<button type="button" class="button button-pay" data-payment="cash">Cash</button> ';
print '<button type="button" class="button button-pay" data-payment="card">Card</button> ';
print '<button type="button" class="button button-pay" data-payment="mixed">Mixed payment</button>';
print '</div>';
print '<div class="margintop">';
print '<button type="button" class="button" id="btn-hold-order">Hold order</button> ';
print '<button type="button" class="button button-delete" id="btn-clear-cart">Clear cart</button>';
print '</div>';
print '<div class="pos-payment-summary margintop"><div>Grand Total</div><div id="payment-grand-total">'.price($cart['total'], 0, '', 1, -1, -1, $conf->currency).'</div></div>';
print '</div></div>';
print '</div>';

print '<script>';
print 'window.POSCORE_CONF = {';
print 'token: "'.$token.'",';
print 'currencySymbol: "'.dol_escape_js($langs->transnoentitiesnoconv($conf->currency)).'",';
print 'urls: {';
print 'searchProducts: "'.dol_buildpath('/poscore/ajax/search_products.php', 1).'",';
print 'addToCart: "'.dol_buildpath('/poscore/ajax/add_to_cart.php', 1).'",';
print 'removeFromCart: "'.dol_buildpath('/poscore/ajax/remove_from_cart.php', 1).'",';
print 'clearCart: "'.dol_buildpath('/poscore/ajax/clear_cart.php', 1).'",';
print 'createInvoice: "'.dol_buildpath('/poscore/ajax/create_invoice.php', 1).'"';
print '},';
print 'initialCart: '.json_encode($cart).'';
print '};';
print '</script>';

llxFooter();
$db->close();
