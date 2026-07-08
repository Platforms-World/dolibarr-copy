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

$maxCashiers = (int) $bridge->getLimit($conf->entity, 'max_cashiers', 1);
$maxTerminals = (int) $bridge->getLimit($conf->entity, 'max_terminals', 1);
$maxSalesPerDay = (int) $bridge->getLimit($conf->entity, 'max_sales_per_day', 0);
if ($maxTerminals <= 0) $maxTerminals = 1;

llxHeader('', 'POS Terminal');
print '<link rel="stylesheet" href="'.dol_buildpath('/custom/poscore/css/pos.css.php', 1).'">';
print '<script src="'.dol_buildpath('/custom/poscore/js/pos.js.php', 1).'"></script>';
print '<input type="hidden" id="poscore_token" value="'.$token.'">';

print '<div class="pos-touch-app">';
print '  <div class="pos-shell">';
print '    <div class="pos-toolbar">';
print '      <div class="pos-toolbar-left">';
print '        <div class="pos-date-chip">'.dol_print_date(dol_now(), 'day').'</div>';
print '        <div class="pos-terminal-chip" id="selected-terminal-chip">Terminal ?</div>';
print '      </div>';
print '      <div class="pos-toolbar-right">';
print '        <div class="pos-toolbar-search"><input type="text" id="pos-search" placeholder="Search product, ref or barcode"></div>';
print '        <button type="button" class="pos-icon-btn" id="btn-open-terminal-modal" title="Select terminal">⌂</button>';
print '        <button type="button" class="pos-icon-btn" id="btn-focus-search" title="Focus search">⌕</button>';
print '      </div>';
print '    </div>';

print '    <div class="pos-main-layout">';

print '      <section class="pos-stage-panel">';
print '        <div class="pos-section-title">Sale Workspace</div>';
print '        <div class="pos-stage-meta">Cashier '.dol_escape_htmltag($user->login).' • Entity '.((int) $conf->entity).'</div>';
print '        <div class="pos-stage-body">';
print '          <div class="pos-cart-card">';
print '            <div class="pos-mini-header">Current Sale Cart</div>';
print '            <div class="pos-cart-wrap">';
print '              <table class="noborder centpercent pos-touch-table" id="pos-cart-table">';
print '                <thead><tr class="liste_titre"><th>Product</th><th class="right">Qty</th><th class="right">Price</th><th class="right">Disc.</th><th class="right">Total</th><th class="center">Remove</th></tr></thead>';
print '                <tbody><tr><td colspan="6" class="opacitymedium pos-empty-note">Cart is empty</td></tr></tbody>';
print '              </table>';
print '            </div>';
print '          </div>';
print '
';
print '          <div class="pos-products-card">';
print '            <div class="pos-products-top">';
print '              <div class="pos-mini-header">Products</div>';
print '              <div class="pos-search-inline">';
print '                <input type="text" id="pos-barcode" placeholder="Barcode">';
print '                <button type="button" id="btn-search-products" class="pos-search-btn">Search</button>';
print '              </div>';
print '            </div>';
print '            <div class="pos-results-wrap">';
print '              <table class="noborder centpercent pos-touch-table" id="pos-search-results">';
print '                <thead><tr class="liste_titre"><th>Product</th><th class="right">Price</th><th class="right">Stock</th><th class="center">Add</th></tr></thead>';
print '                <tbody><tr><td colspan="4" class="opacitymedium pos-empty-note">Search products to begin</td></tr></tbody>';
print '              </table>';
print '            </div>';
print '          </div>';
print '        </div>';
print '      </section>';

print '      <section class="pos-center-panel">';
print '        <div class="pos-mini-header">Quick Qty</div>';
print '        <div class="pos-keypad-shell">';
print '          <div class="pos-keypad-display">';
print '            <div class="pos-keypad-label">Qty</div>';
print '            <div class="pos-keypad-value" id="pos-selected-qty">1</div>';
print '          </div>';
print '          <div class="pos-keypad">';
foreach (array('7','8','9','4','5','6','1','2','3','0','.','C') as $key) {
    $cls = ($key === 'C') ? ' pos-key-clear' : '';
    print '            <button type="button" class="pos-key'.$cls.'" data-key="'.$key.'">'.$key.'</button>';
}
print '          </div>';
print '        </div>';
print '      </section>';

print '      <section class="pos-actions-panel">';
print '        <div class="pos-mini-header">Actions</div>';
print '        <div class="pos-action-grid">';
print '          <button type="button" class="pos-action-tile pos-action-accent" id="btn-new-sale"><span>🧾</span><strong>New</strong></button>';
print '          <button type="button" class="pos-action-tile" id="btn-history"><span>↺</span><strong>History</strong></button>';
print '          <button type="button" class="pos-action-tile" id="btn-free-text"><span>⌨</span><strong>Free-text product</strong></button>';
print '          <button type="button" class="pos-action-tile"><span>%</span><strong>Invoice disc.</strong></button>';
print '          <button type="button" class="pos-action-tile" id="btn-hold-order"><span>⏸</span><strong>Hold order</strong></button>';
print '          <button type="button" class="pos-action-tile" id="btn-split-sale"><span>✂</span><strong>Split sale</strong></button>';
print '          <button type="button" class="pos-action-tile pos-action-pay button-pay" data-payment="cash"><span>💵</span><strong>Cash</strong></button>';
print '          <button type="button" class="pos-action-tile pos-action-pay button-pay" data-payment="card"><span>💳</span><strong>Payment</strong></button>';
print '        </div>';

print '        <div class="pos-summary-card">';
print '          <div class="pos-summary-row"><span>Subtotal</span><strong id="cart-subtotal">0.00</strong></div>';
print '          <div class="pos-summary-row"><span>Tax</span><strong id="cart-tax">0.00</strong></div>';
print '          <div class="pos-summary-row pos-summary-grand"><span>Grand Total</span><strong id="payment-grand-total">0.00</strong></div>';
print '        </div>';

print '        <div class="pos-customer-card">';
print '          <label>Customer</label>';
print '          <select id="pos-customer" class="flat width100">';
print '            <option value="0">Select customer</option>';
foreach ($customers as $c) {
    print '            <option value="'.$c['id'].'">'.dol_escape_htmltag($c['name']).'</option>';
}
print '          </select>';
print '        </div>';

print '        <div class="pos-limits-strip">';
print '          <div class="pos-limit-box"><span>Cashiers</span><strong>'.((int) $maxCashiers).'</strong></div>';
print '          <div class="pos-limit-box"><span>Terminals</span><strong>'.((int) $maxTerminals).'</strong></div>';
print '          <div class="pos-limit-box"><span>Sales/Day</span><strong>'.((int) $maxSalesPerDay).'</strong></div>';
print '        </div>';
print '      </section>';

print '    </div>';
print '  </div>';

print '  <div class="pos-terminal-modal is-open" id="pos-terminal-modal">';
print '    <div class="pos-terminal-dialog">';
print '      <div class="pos-terminal-dialog-header">';
print '        <div>Select terminal you want to use:</div>';
print '        <button type="button" class="pos-terminal-close" id="btn-close-terminal-modal">×</button>';
print '      </div>';
print '      <div class="pos-terminal-list">';
for ($i = 1; $i <= $maxTerminals; $i++) {
    print '        <button type="button" class="pos-terminal-option" data-terminal="'.$i.'">Terminal '.$i.'</button>';
}
print '      </div>';
print '    </div>';
print '  </div>';
print '</div>';

print '<script>
window.POSCORE_CONF = {
  token: "'.$token.'",
  maxTerminals: '.((int)$maxTerminals).',
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
