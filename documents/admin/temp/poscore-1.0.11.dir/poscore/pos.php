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
print '<link rel="stylesheet" href="'.dol_buildpath('/custom/poscore/css/pos.css.php', 1).'">';
print '<script src="'.dol_buildpath('/custom/poscore/js/pos.js.php', 1).'"></script>';
print '<input type="hidden" id="poscore_token" value="'.$token.'">';

print '<div class="pos-terminal-page">';
print '  <div class="pos-topbar">';
print '      <div class="pos-brand-block">';
print '          <div class="pos-screen-title">POS Terminal</div>';
print '          <div class="pos-screen-meta">Entity '.((int) $conf->entity).' • Cashier '.dol_escape_htmltag($user->login).'</div>';
print '      </div>';
print '      <div class="pos-searchbar">';
print '          <div class="pos-search-group">';
print '              <label>Product search</label>';
print '              <input type="text" id="pos-search" class="flat width100" placeholder="Search by product, ref, barcode">';
print '          </div>';
print '          <div class="pos-search-group pos-barcode-group">';
print '              <label>Barcode</label>';
print '              <input type="text" id="pos-barcode" class="flat width100" placeholder="Scan barcode">';
print '          </div>';
print '          <button type="button" class="button pos-primary-btn" id="btn-search-products">Search</button>';
print '      </div>';
print '  </div>';

print '  <div class="pos-layout-touch">';

print '      <section class="pos-panel pos-products-panel">';
print '          <div class="pos-panel-header">';
print '              <div>';
print '                  <h3>Products</h3>';
print '                  <p>Fast lookup for touch and barcode workflow</p>';
print '              </div>';
print '              <div class="pos-badge">Ready</div>';
print '          </div>';
print '          <div class="pos-results-wrap">';
print '              <table class="noborder centpercent pos-touch-table" id="pos-search-results">';
print '                  <thead><tr class="liste_titre"><th>Product</th><th class="right">Price</th><th class="right">Stock</th><th class="center">Add</th></tr></thead>';
print '                  <tbody><tr><td colspan="4" class="opacitymedium">Search products to begin</td></tr></tbody>';
print '              </table>';
print '          </div>';
print '      </section>';

print '      <section class="pos-panel pos-cart-panel">';
print '          <div class="pos-panel-header">';
print '              <div>';
print '                  <h3>Current Sale Cart</h3>';
print '                  <p>Large rows optimized for cashier speed</p>';
print '              </div>';
print '              <div class="pos-qty-chip">Qty <span id="pos-selected-qty">1</span></div>';
print '          </div>';
print '          <div class="pos-cart-wrap">';
print '              <table class="noborder centpercent pos-touch-table" id="pos-cart-table">';
print '                  <thead><tr class="liste_titre"><th>Product</th><th class="right">Qty</th><th class="right">Price</th><th class="right">Discount</th><th class="right">Total</th><th class="center">Remove</th></tr></thead>';
print '                  <tbody><tr><td colspan="6" class="opacitymedium">Cart is empty</td></tr></tbody>';
print '              </table>';
print '          </div>';
print '          <div class="pos-totals-card">';
print '              <div class="pos-totals-line"><span>Subtotal</span><strong id="cart-subtotal">0.00</strong></div>';
print '              <div class="pos-totals-line"><span>Tax</span><strong id="cart-tax">0.00</strong></div>';
print '              <div class="pos-totals-line pos-total-line"><span>Total</span><strong id="cart-total">0.00</strong></div>';
print '          </div>';
print '      </section>';

print '      <section class="pos-panel pos-actions-panel">';
print '          <div class="pos-panel-header">';
print '              <div>';
print '                  <h3>Payment & Actions</h3>';
print '                  <p>Touch-first controls</p>';
print '              </div>';
print '              <div class="pos-badge">Touch UI</div>';
print '          </div>';

print '          <div class="pos-customer-box">';
print '              <label>Customer</label>';
print '              <select id="pos-customer" class="flat width100">';
print '                  <option value="0">Select customer</option>';
foreach ($customers as $c) {
    print '                  <option value="'.$c['id'].'">'.dol_escape_htmltag($c['name']).'</option>';
}
print '              </select>';
print '          </div>';

print '          <div class="pos-keypad-actions">';
print '              <div class="pos-keypad">';
foreach (array('7','8','9','4','5','6','1','2','3','0','.','C') as $key) {
    $cls = ($key === 'C') ? ' pos-key-danger' : '';
    print '                  <button type="button" class="pos-key'.$cls.'" data-key="'.$key.'">'.$key.'</button>';
}
print '              </div>';
print '              <div class="pos-action-grid">';
print '                  <button type="button" class="pos-action-tile pos-action-primary" id="btn-new-sale">New Sale</button>';
print '                  <button type="button" class="pos-action-tile" id="btn-history">History</button>';
print '                  <button type="button" class="pos-action-tile" id="btn-hold-order">Hold Order</button>';
print '                  <button type="button" class="pos-action-tile" id="btn-clear-cart">Clear Cart</button>';
print '                  <button type="button" class="pos-action-tile pos-action-success button-pay" data-payment="cash">Cash</button>';
print '                  <button type="button" class="pos-action-tile pos-action-info button-pay" data-payment="card">Card</button>';
print '                  <button type="button" class="pos-action-tile pos-action-warning button-pay" data-payment="mixed">Mixed</button>';
print '                  <button type="button" class="pos-action-tile" id="btn-free-text">Quick Note</button>';
print '              </div>';
print '          </div>';

print '          <div class="pos-payment-summary">';
print '              <div class="pos-payment-total-label">Grand Total</div>';
print '              <div id="payment-grand-total">0.00</div>';
print '          </div>';

print '          <div class="pos-terminal-footer">';
print '              <div class="pos-limit-card"><span>Cashiers</span><strong>'.((int) $maxCashiers).'</strong></div>';
print '              <div class="pos-limit-card"><span>Terminals</span><strong>'.((int) $maxTerminals).'</strong></div>';
print '              <div class="pos-limit-card"><span>Sales/Day</span><strong>'.((int) $maxSalesPerDay).'</strong></div>';
print '          </div>';
print '      </section>';

print '  </div>';
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
