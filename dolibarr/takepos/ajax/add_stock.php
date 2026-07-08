<?php
/**
 * TakePOS — Add Stock AJAX endpoint.
 *
 * Allows a cashier to add stock to a zero-stock (or low-stock) product directly
 * from the POS, gated by a manager login/password (re-uses the same credential-
 * verification helpers from the manager override system).
 *
 * Flow:
 *   1. Cashier clicks "Add stock" on a product whose stock check failed.
 *   2. JS opens a popup asking for qty + manager login + manager password.
 *   3. JS POSTs here.
 *   4. We:
 *        a. validate inputs
 *        b. verify the manager exists, password matches, and manager is admin
 *           (or has product/creer right — same level as adding a product manually)
 *        c. forbid self-approval (manager_id must differ from cashier_id)
 *        d. open a real MouvementStock::reception() on the terminal's warehouse
 *        e. write an audit-log entry
 *        f. return the new stock level so JS can refresh the badge
 *
 * Action: add_stock
 * Method: POST
 * Params:
 *    product_id        (int, required)
 *    qty               (number > 0, required)
 *    manager_login     (string, required)
 *    manager_password  (string, required)
 *    reason            (string, optional — defaults to "POS stock-in")
 *    token             (CSRF token)
 *
 * Returns: JSON
 *    success:           bool
 *    message:           string  (always set; localized on failure)
 *    new_stock:         float   (on success — the new on-hand qty at this warehouse)
 *    warehouse_id:      int
 *    error:             string  (machine-readable reason on failure)
 */

if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
if (!defined('NOREQUIREMENU'))  define('NOREQUIREMENU',  '1');
if (!defined('NOREQUIREHTML'))  define('NOREQUIREHTML',  '1');
if (!defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX',  '1');
if (!defined('NOBROWSERNOTIF')) define('NOBROWSERNOTIF', '1');

$mainPath = __DIR__ . '/../../main.inc.php';
if (!file_exists($mainPath)) {
    $mainPath = __DIR__ . '/../../../main.inc.php';
}
require $mainPath;

require_once DOL_DOCUMENT_ROOT . '/takepos/lib/takepos_access_guard.php';
takeposAccessGuardCurrent($db, isset($user) ? $user : null, __FILE__);

require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposAccess.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposAudit.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposManagerOverrideService.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/stock/class/mouvementstock.class.php';

$langs->loadLangs(array('takeposcustom@takepos', 'stocks', 'main'));

/** @var Conf $conf @var DoliDB $db @var Translate $langs @var User $user */

function takeposAddStockJson($payload, $httpCode = 200)
{
    if (!headers_sent()) {
        http_response_code((int) $httpCode);
        header('Content-Type: application/json; charset=UTF-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// --- Auth: only authenticated POS users can even reach this endpoint
if (!isset($user) || empty($user->id) || !$user->hasRight('takepos', 'run')) {
    takeposAddStockJson(array(
        'success' => false,
        'error'   => 'forbidden',
        'message' => $langs->trans('NotEnoughPermissions'),
    ), 403);
}

// --- CSRF: same pattern as ajax/manager_override.php
$token        = (string) GETPOST('token', 'alpha');
$sessionToken = isset($_SESSION['newtoken']) ? (string) $_SESSION['newtoken'] : '';
if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
    takeposAddStockJson(array(
        'success' => false,
        'error'   => 'invalid_csrf',
        'message' => $langs->trans('TakeposCommonInvalidCsrfToken'),
    ), 403);
}

// --- Inputs
$productId       = GETPOSTINT('product_id');
$qtyRaw          = (string) GETPOST('qty', 'none');
$qty             = (float) str_replace(',', '.', $qtyRaw);
$managerLogin    = trim((string) GETPOST('manager_login', 'none'));
$managerPassword = (string) GETPOST('manager_password', 'none');
$reason          = trim((string) GETPOST('reason', 'restricthtml'));
if ($reason === '') {
    $reason = 'POS stock-in';
}

if ($productId <= 0) {
    takeposAddStockJson(array(
        'success' => false,
        'error'   => 'bad_product_id',
        'message' => $langs->trans('TakeposAddStockBadProduct') ?: 'Invalid product.',
    ), 422);
}
if ($qty <= 0) {
    takeposAddStockJson(array(
        'success' => false,
        'error'   => 'bad_qty',
        'message' => $langs->trans('TakeposAddStockBadQty') ?: 'Quantity must be greater than zero.',
    ), 422);
}
if ($managerLogin === '' || $managerPassword === '') {
    takeposAddStockJson(array(
        'success' => false,
        'error'   => 'missing_manager_credentials',
        'message' => $langs->trans('TakeposAddStockManagerRequired') ?: 'Manager login and password are required.',
    ), 422);
}

// --- Load product and refuse for services
$prod = new Product($db);
if ($prod->fetch($productId) <= 0) {
    takeposAddStockJson(array(
        'success' => false,
        'error'   => 'product_not_found',
        'message' => $langs->trans('TakeposAddStockProductNotFound') ?: 'Product not found.',
    ), 404);
}
// type==1 means service — services don't have stock
if ((int) $prod->type === 1) {
    takeposAddStockJson(array(
        'success' => false,
        'error'   => 'product_is_service',
        'message' => $langs->trans('TakeposAddStockProductIsService') ?: 'This is a service. Services do not have stock.',
    ), 422);
}

// --- Resolve the terminal's warehouse
$terminal    = isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : 0;
$warehouseId = getDolGlobalInt('CASHDESK_ID_WAREHOUSE' . $terminal);
if ($warehouseId <= 0) {
    takeposAddStockJson(array(
        'success' => false,
        'error'   => 'no_warehouse',
        'message' => $langs->trans('TakeposAddStockNoWarehouse')
            ?: 'No warehouse is configured for this terminal. Configure CASHDESK_ID_WAREHOUSE first.',
    ), 422);
}

// --- Verify manager credentials (re-uses the same helpers as the override system)
$managerUser = TakeposManagerOverrideService::findManagerByLogin($db, $managerLogin);
if (!$managerUser || !TakeposManagerOverrideService::validateManagerPassword($managerUser, $managerPassword)) {
    TakeposAudit::logEvent(
        $db,
        $user,
        'pos_add_stock_rejected',
        TakeposAudit::SEVERITY_WARNING,
        array(
            'reason'        => 'invalid_manager_credentials',
            'product_id'    => $productId,
            'product_ref'   => isset($prod->ref)   ? (string) $prod->ref   : '',
            'product_label' => isset($prod->label) ? (string) $prod->label : '',
            'qty'           => $qty,
            'warehouse_id'  => $warehouseId,
            'manager_login' => $managerLogin,
        ),
        'POS add-stock rejected: invalid manager credentials',
        'product',
        $productId
    );
    takeposAddStockJson(array(
        'success' => false,
        'error'   => 'invalid_manager_credentials',
        'message' => $langs->trans('TakeposManagerOverrideInvalidCredentials')
            ?: 'Invalid manager credentials.',
    ), 403);
}

// --- Block self-approval, EXCEPT for full Dolibarr admins.
// Rationale: in a real shop the cashier is usually not the same user as the
// manager. But many small shops run a single admin user as both. Admins are
// already trusted with full system access (they can adjust any data via the
// stock UI directly), so blocking them from approving their own POS add-stock
// adds no real security — it just blocks single-user setups from working at
// all. Regular managers (admin=0 with stock/product rights) still CANNOT
// self-approve: the audit trail must show two distinct user accounts.
if ((int) $managerUser->id === (int) $user->id && empty($managerUser->admin)) {
    TakeposAudit::logEvent(
        $db,
        $user,
        'pos_add_stock_rejected',
        TakeposAudit::SEVERITY_WARNING,
        array(
            'reason'        => 'self_approval',
            'product_id'    => $productId,
            'product_ref'   => isset($prod->ref)   ? (string) $prod->ref   : '',
            'product_label' => isset($prod->label) ? (string) $prod->label : '',
            'qty'           => $qty,
            'warehouse_id'  => $warehouseId,
            'manager_login' => $managerLogin,
        ),
        'POS add-stock rejected: self approval (non-admin)',
        'product',
        $productId
    );
    takeposAddStockJson(array(
        'success' => false,
        'error'   => 'self_approval_denied',
        'message' => $langs->trans('TakeposManagerOverrideSelfApprovalDenied')
            ?: 'Manager approval must come from a different user.',
    ), 403);
}

// --- Check manager actually has the right to add stock.
// We accept any of: admin user OR has product/creer (can create products) OR
// has stock/mouvement/creer (can move stock). The cashier is not enough.
$managerOk = false;
if (!empty($managerUser->admin)) {
    $managerOk = true;
} elseif (method_exists($managerUser, 'hasRight')) {
    if ($managerUser->hasRight('stock', 'mouvement', 'creer')
        || $managerUser->hasRight('produit', 'creer')
        || $managerUser->hasRight('service', 'creer')) {
        $managerOk = true;
    }
}
if (!$managerOk) {
    TakeposAudit::logEvent(
        $db,
        $user,
        'pos_add_stock_rejected',
        TakeposAudit::SEVERITY_WARNING,
        array(
            'reason'        => 'manager_permission_denied',
            'product_id'    => $productId,
            'product_ref'   => isset($prod->ref)   ? (string) $prod->ref   : '',
            'product_label' => isset($prod->label) ? (string) $prod->label : '',
            'qty'           => $qty,
            'warehouse_id'  => $warehouseId,
            'manager_id'    => (int) $managerUser->id,
            'manager_login' => $managerLogin,
        ),
        'POS add-stock rejected: manager lacks required rights',
        'product',
        $productId
    );
    takeposAddStockJson(array(
        'success' => false,
        'error'   => 'manager_permission_denied',
        'message' => $langs->trans('TakeposManagerOverridePermissionDenied')
            ?: 'Manager does not have permission to approve this action.',
    ), 403);
}

// --- All checks passed — perform the real stock-in movement.
//
// We use MouvementStock::reception() which is the same call purchases.php uses
// when receiving goods. This records a row in llx_stock_mouvement and updates
// llx_product_stock.reel atomically inside Dolibarr.
//
// Buy price = current product cost_price (or 0 if unset). We do NOT update
// the PMP/average cost here — this is a stock-add, not a purchase. If you
// also want to update the cost basis, do a real purchase in purchases.php.
$buyPrice = (float) (isset($prod->cost_price) ? $prod->cost_price : 0);

$mouvement     = new MouvementStock($db);
$inventoryCode = dol_print_date(dol_now(), 'dayhourlog');
$movementLabel = 'TakePOS add-stock (terminal ' . $terminal . ', cashier ' . $user->login
    . ', approved-by ' . $managerUser->login . '): ' . $reason;

$db->begin();

$res = $mouvement->reception(
    $user,
    (int) $productId,
    (int) $warehouseId,
    (float) $qty,
    (float) $buyPrice,
    $movementLabel,
    '', // price_base_type — leave default
    '', // datem
    '', // eatby
    '', // sellby
    0,  // batch
    $inventoryCode
);

if ($res < 0) {
    $db->rollback();
    TakeposAudit::logEvent(
        $db,
        $user,
        'pos_add_stock_failed',
        TakeposAudit::SEVERITY_CRITICAL,
        array(
            'product_id'   => $productId,
            'qty'          => $qty,
            'warehouse_id' => $warehouseId,
            'manager_id'   => (int) $managerUser->id,
            'error'        => !empty($mouvement->error) ? $mouvement->error : 'reception_failed',
        ),
        'POS add-stock: MouvementStock::reception failed',
        'product',
        $productId
    );
    takeposAddStockJson(array(
        'success' => false,
        'error'   => 'movement_failed',
        'message' => !empty($mouvement->error) ? $mouvement->error
            : ($langs->trans('TakeposAddStockMovementFailed') ?: 'Stock movement failed.'),
    ), 500);
}

$db->commit();

// --- Read back the new on-hand qty at this warehouse
$newStock = 0.0;
$sqlNew = "SELECT reel FROM " . MAIN_DB_PREFIX . "product_stock"
    . " WHERE fk_product = " . ((int) $productId)
    . " AND fk_entrepot = " . ((int) $warehouseId);
$resNew = $db->query($sqlNew);
if ($resNew && $db->num_rows($resNew) > 0) {
    $rowNew = $db->fetch_object($resNew);
    $newStock = (float) $rowNew->reel;
}

// --- Success audit
TakeposAudit::logEvent(
    $db,
    $user,
    'pos_add_stock_approved',
    TakeposAudit::SEVERITY_INFO,
    array(
        'product_id'    => $productId,
        'product_ref'   => $prod->ref,
        'product_label' => $prod->label,
        'qty_added'     => $qty,
        'new_stock'     => $newStock,
        'warehouse_id'  => $warehouseId,
        'manager_id'    => (int) $managerUser->id,
        'manager_login' => $managerUser->login,
        'reason'        => $reason,
    ),
    'POS add-stock approved by manager ' . $managerUser->login,
    'product',
    $productId,
    $qty * $buyPrice
);

takeposAddStockJson(array(
    'success'      => true,
    'message'      => $langs->trans('TakeposAddStockSuccess')
        ?: 'Stock added successfully.',
    'new_stock'    => $newStock,
    'qty_added'    => $qty,
    'warehouse_id' => $warehouseId,
    'product_id'   => $productId,
), 200);