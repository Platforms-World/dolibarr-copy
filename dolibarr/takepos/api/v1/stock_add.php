<?php
/**
 * TakePOS API v1 — Add Stock (Manager Approved)
 *
 * POST /takepos/api/v1/stock_add.php
 *
 * Adds stock to a product at the terminal warehouse. Requires manager
 * credentials (different user from the API token holder).
 * Writes a real MouvementStock::reception() stock movement and audit log.
 *
 * Auth: Bearer token (standard API v1 auth)
 *
 * Body:
 *   product_id        INT     required
 *   qty               FLOAT   required  must be > 0
 *   manager_login     STRING  required
 *   manager_password  STRING  required
 *   reason            STRING  optional  default "POS stock-in via API"
 *   terminal_id       INT     optional  overrides session terminal
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_request.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/stock/class/mouvementstock.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposManagerOverrideService.class.php';

takeposApiRequireMethod(array('POST'));

$auth   = takeposApiAuth($db, 'write', 'takepos.api_layer');
$entity = (int) $auth['entity'];
$body   = takeposApiRequestBody();

$productId       = (int)    takeposApiRequestRequireField($body, 'product_id');
$qty             = (float)  takeposApiRequestRequireField($body, 'qty');
$managerLogin    = trim((string) takeposApiRequestRequireField($body, 'manager_login'));
$managerPassword = (string) takeposApiRequestRequireField($body, 'manager_password');
$reason          = !empty($body['reason']) ? trim((string) $body['reason']) : 'POS stock-in via API';
// FIX (B4): Removed $_SESSION fallback. terminal_id must be supplied in the
// request body. When missing or 0, the NO_WAREHOUSE error fires immediately
// with a clear message directing the caller to pass terminal_id.
$terminalId = !empty($body['terminal_id']) ? (int) $body['terminal_id'] : 0;

if ($qty <= 0) {
    throw new TakeposApiException('INVALID_PARAMETER', 'qty must be greater than zero.', 422);
}
if ($productId <= 0) {
    throw new TakeposApiException('INVALID_PARAMETER', 'product_id is required.', 422);
}
if ($managerLogin === '' || $managerPassword === '') {
    throw new TakeposApiException('INVALID_PARAMETER', 'manager_login and manager_password are required.', 422);
}

// Resolve warehouse
$warehouseId = getDolGlobalInt('CASHDESK_ID_WAREHOUSE' . $terminalId);
if ($warehouseId <= 0) {
    throw new TakeposApiException('NO_WAREHOUSE', 'No warehouse configured. Pass terminal_id in the request body and confirm CASHDESK_ID_WAREHOUSE' . $terminalId . ' is set in TakePOS terminal settings.', 422);
}

// Load product
$prod = new Product($db);
if ($prod->fetch($productId) <= 0) {
    throw new TakeposApiException('NOT_FOUND', 'Product not found.', 404);
}

// Verify manager credentials
$managerUser = TakeposManagerOverrideService::findManagerByLogin($db, $managerLogin);
if (!$managerUser || !TakeposManagerOverrideService::validateManagerPassword($managerUser, $managerPassword)) {
    TakeposAudit::logEvent($db, $auth['user'], 'api_stock_add_rejected', TakeposAudit::SEVERITY_WARNING,
        array('product_id' => $productId, 'qty' => $qty, 'manager_login' => $managerLogin, 'reason' => 'invalid_credentials'),
        'API stock-add rejected: invalid manager credentials');
    throw new TakeposApiException('INVALID_MANAGER_CREDENTIALS', 'Manager credentials are invalid or insufficient.', 403);
}

// Block self-approval
if ((int) $managerUser->id === (int) $auth['user']->id) {
    throw new TakeposApiException('SELF_APPROVAL_FORBIDDEN', 'The API token holder cannot approve their own stock addition.', 403);
}

// Apply stock movement
$mouv = new MouvementStock($db);
$mouv->setOrigin('takepos_api');
$res = $mouv->reception(
    $auth['user'],
    $productId,
    $warehouseId,
    $qty,
    $prod->price,
    $reason . ' (approved by ' . $managerLogin . ')'
);

if ($res < 0) {
    throw new TakeposApiException('STOCK_MOVEMENT_FAILED', !empty($mouv->error) ? $mouv->error : 'Failed to apply stock movement.', 500);
}

// Get new stock level
$sqlStock = 'SELECT reel FROM ' . MAIN_DB_PREFIX . 'product_stock'
    . ' WHERE fk_product = ' . (int)$productId . ' AND fk_entrepot = ' . (int)$warehouseId;
$resStock = $db->query($sqlStock);
$newStock = ($resStock && $db->num_rows($resStock)) ? (float) $db->fetch_object($resStock)->reel : 0.0;

TakeposAudit::logEvent($db, $auth['user'], 'api_stock_add', TakeposAudit::SEVERITY_INFO,
    array('product_id' => $productId, 'qty_added' => $qty, 'new_stock' => $newStock,
          'warehouse_id' => $warehouseId, 'manager_login' => $managerLogin, 'reason' => $reason),
    'Stock added via API');

takeposApiAuditAccess($db, $auth, 'stock_add', array(
    'product_id'   => $productId,
    'qty_added'    => $qty,
    'warehouse_id' => $warehouseId,
    'manager_login'=> $managerLogin,
));

takeposApiSuccess(array(
    'product_id'   => $productId,
    'qty_added'    => $qty,
    'new_stock'    => $newStock,
    'warehouse_id' => $warehouseId,
    'approved_by'  => $managerLogin,
    'reason'       => $reason,
), array('entity' => $entity), 201);
