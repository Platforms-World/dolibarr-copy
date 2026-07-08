<?php
require_once __DIR__ . '/TakeposTerminalService.class.php';
require_once __DIR__ . '/TakeposStoreService.class.php';
require_once __DIR__ . '/TakeposShiftService.class.php';

if (defined('DOL_DOCUMENT_ROOT')) {
    require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
    require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
}

class TakeposApiCheckoutService
{
    public static function actorUser($entity)
    {
        global $user;

        $actor = (is_object($user) ? clone $user : new stdClass());
        if (!isset($actor->id)) {
            $actor->id = 0;
        }
        if (empty($actor->entity)) {
            $actor->entity = (int) $entity;
        }
        if (empty($actor->login)) {
            $actor->login = 'api';
        }

        return $actor;
    }

    public static function requireDraftCart($invoice)
    {
        if (!is_object($invoice) || empty($invoice->id)) {
            throw new TakeposApiException('NOT_FOUND', 'Cart not found.', 404);
        }
        if ((string) $invoice->module_source !== 'takepos') {
            throw new TakeposApiException('NOT_FOUND', 'Cart not found.', 404);
        }
        if ((int) $invoice->status !== Facture::STATUS_DRAFT) {
            throw new TakeposApiException('INVALID_CART_STATE', 'Cart is not in draft state.', 409);
        }
        if (empty($invoice->lines) || !is_array($invoice->lines) || count($invoice->lines) === 0) {
            throw new TakeposApiException('INVALID_CART_STATE', 'Cart has no items.', 409);
        }
    }

    public static function resolveTerminalForInvoice($db, $entity, $invoice)
    {
        $terminalCode = (!empty($invoice->pos_source) ? (string) $invoice->pos_source : '');
        if ($terminalCode === '') {
            throw new TakeposApiException('INVALID_CART_STATE', 'Cart terminal is missing.', 409);
        }

        $terminal = TakeposTerminalService::getTerminalByCode($db, $entity, $terminalCode);
        return self::assertTerminalUsable($db, $entity, $terminal, 'INVALID_CART_STATE', 409);
    }

    public static function assertTerminalUsable($db, $entity, $terminal, $errorCode = 'INVALID_PARAMETER', $httpCode = 422)
    {
        global $user;

        if (!$terminal || empty($terminal->rowid)) {
            throw new TakeposApiException($errorCode, 'Terminal is missing or invalid.', $httpCode);
        }
        if (empty($terminal->active)) {
            throw new TakeposApiException($errorCode, 'Terminal is inactive.', $httpCode);
        }

        if (!empty($terminal->fk_store)) {
            $store = TakeposStoreService::getStore($db, $entity, (int) $terminal->fk_store);
            if (!$store || empty($store->active)) {
                throw new TakeposApiException($errorCode, 'Terminal store is missing or inactive.', $httpCode);
            }
            if (TakeposStoreService::enforceStoreRestrictionEnabled($db)
                && is_object($user)
                && !empty($user->id)
                && empty($user->admin)
                && !TakeposStoreService::userCanAccessStore($db, $user, (int) $terminal->fk_store, $entity)
            ) {
                throw new TakeposApiException('FORBIDDEN', 'Terminal store access is denied for this user.', 403);
            }
        }

        return $terminal;
    }

    public static function terminalWarehouseId($terminal)
    {
        if (!is_object($terminal) || empty($terminal->terminal_code)) {
            return 0;
        }

        return (int) getDolGlobalInt('CASHDESK_ID_WAREHOUSE' . (string) $terminal->terminal_code);
    }

    public static function stockCheckEnabled()
    {
        return (function_exists('isModEnabled') && isModEnabled('stock') && getDolGlobalInt('TAKEPOS_PRODUCT_IN_STOCK') == 1);
    }

    public static function allowsNegativeStock()
    {
        return !(bool) getDolGlobalInt('STOCK_DISALLOW_NEGATIVE_TRANSFER');
    }

    public static function productUsesStockGuard($product)
    {
        if (!is_object($product) || empty($product->id)) {
            return false;
        }

        if ((int) $product->type === 1) {
            return false;
        }
        if (!empty($product->no_incdec)) {
            return false;
        }

        return true;
    }

    public static function availableStockQty($db, $productId, $warehouseId = 0)
    {
        $productId = (int) $productId;
        if ($productId <= 0) {
            return 0.0;
        }

        if ((int) $warehouseId > 0) {
            $sql = "SELECT reel FROM " . MAIN_DB_PREFIX . "product_stock"
                . " WHERE fk_product = " . $productId
                . " AND fk_entrepot = " . ((int) $warehouseId)
                . " LIMIT 1";
            $resql = $db->query($sql);
            if ($resql && ($obj = $db->fetch_object($resql))) {
                return (float) price2num($obj->reel, 'MS');
            }

            return 0.0;
        }

        $sql = "SELECT COALESCE(SUM(reel), 0) AS total"
            . " FROM " . MAIN_DB_PREFIX . "product_stock"
            . " WHERE fk_product = " . $productId;
        $resql = $db->query($sql);
        if ($resql && ($obj = $db->fetch_object($resql))) {
            return (float) price2num($obj->total, 'MS');
        }

        return 0.0;
    }

    public static function reservedQtyInInvoice($db, $invoiceId, $productId, $excludeLineId = 0)
    {
        $sql = "SELECT COALESCE(SUM(qty), 0) AS total_qty"
            . " FROM " . MAIN_DB_PREFIX . "facturedet"
            . " WHERE fk_facture = " . ((int) $invoiceId)
            . " AND fk_product = " . ((int) $productId);
        if ((int) $excludeLineId > 0) {
            $sql .= " AND rowid <> " . ((int) $excludeLineId);
        }

        $resql = $db->query($sql);
        if ($resql && ($obj = $db->fetch_object($resql))) {
            return (float) price2num($obj->total_qty, 'MS');
        }

        return 0.0;
    }

    public static function assertProductStockAvailableForInvoice($db, $entity, $invoice, $terminal, $product, $requestedQty, $excludeLineId = 0)
    {
        if (!self::stockCheckEnabled() || self::allowsNegativeStock() || !self::productUsesStockGuard($product)) {
            return true;
        }

        $warehouseId = self::terminalWarehouseId($terminal);
        $availableQty = self::availableStockQty($db, (int) $product->id, $warehouseId);
        $reservedQty = self::reservedQtyInInvoice($db, (int) $invoice->id, (int) $product->id, (int) $excludeLineId);
        $requestedQty = (float) price2num($requestedQty, 'MS');
        $requestedTotal = $reservedQty + $requestedQty;

        if ($availableQty + 0.000001 < $requestedTotal) {
            throw new TakeposApiException(
                'INSUFFICIENT_STOCK',
                'Insufficient stock',
                409,
                array(
                    'product_id' => (int) $product->id,
                    'available_qty' => (float) $availableQty,
                    'requested_qty' => (float) $requestedTotal,
                    'free_qty' => max(0.0, $availableQty - $reservedQty),
                    'warehouse_id' => ((int) $warehouseId > 0 ? (int) $warehouseId : null),
                )
            );
        }

        return true;
    }

    public static function getInvoiceStockIssues($db, $entity, $invoice, $terminal)
    {
        $issues = array();
        if (!self::stockCheckEnabled() || self::allowsNegativeStock()) {
            return $issues;
        }

        $warehouseId = self::terminalWarehouseId($terminal);
        $grouped = array();
        foreach ((array) $invoice->lines as $line) {
            $productId = !empty($line->fk_product) ? (int) $line->fk_product : 0;
            if ($productId <= 0) {
                continue;
            }

            if (!isset($grouped[$productId])) {
                $grouped[$productId] = 0.0;
            }
            $grouped[$productId] += (float) price2num($line->qty, 'MS');
        }

        foreach ($grouped as $productId => $requestedQty) {
            $product = new Product($db);
            if ($product->fetch((int) $productId) <= 0 || !self::productUsesStockGuard($product)) {
                continue;
            }

            $availableQty = self::availableStockQty($db, (int) $productId, $warehouseId);
            if ($availableQty + 0.000001 < $requestedQty) {
                $issues[] = array(
                    'product_id' => (int) $productId,
                    'available_qty' => (float) $availableQty,
                    'requested_qty' => (float) $requestedQty,
                    'warehouse_id' => ((int) $warehouseId > 0 ? (int) $warehouseId : null),
                );
            }
        }

        return $issues;
    }

    public static function requireCheckoutReady($db, $entity, $invoice)
    {
        self::requireDraftCart($invoice);
        $terminal = self::resolveTerminalForInvoice($db, $entity, $invoice);

        if (TakeposUserAccess::featureEnabledForEntityStrict($db, $entity, 'takepos.shift_management')
            && TakeposShiftService::requireOpenShiftForPayments()
        ) {
            $shift = TakeposShiftService::getActiveShiftForTerminal($db, $entity, (int) $terminal->rowid);
            if (!$shift || empty($shift->rowid)) {
                throw new TakeposApiException('INVALID_CART_STATE', 'No open shift found for this terminal.', 409);
            }
        }

        $issues = self::getInvoiceStockIssues($db, $entity, $invoice, $terminal);
        if (!empty($issues)) {
            $first = $issues[0];
            throw new TakeposApiException('INSUFFICIENT_STOCK', 'Insufficient stock', 409, $first);
        }

        return $terminal;
    }

    public static function validateCart($db, $entity, $invoice)
    {
        global $conf;

        $terminal = self::requireCheckoutReady($db, $entity, $invoice);
        $actor = self::actorUser($entity);
        $allowStockChange = (getDolGlobalString('CASHDESK_NO_DECREASE_STOCK' . $terminal->terminal_code) != '1');
        $saveConst = getDolGlobalString('STOCK_CALCULATE_ON_BILL');

        try {
            if (function_exists('isModEnabled') && isModEnabled('stock') && !isModEnabled('productbatch') && $allowStockChange) {
                $conf->global->STOCK_CALCULATE_ON_BILL = 1;
                $warehouseKey = 'CASHDESK_ID_WAREHOUSE' . $terminal->terminal_code;
                $res = $invoice->validate($actor, '', getDolGlobalInt($warehouseKey), 0, 0);
            } else {
                $res = $invoice->validate($actor);
            }
        } finally {
            $conf->global->STOCK_CALCULATE_ON_BILL = $saveConst;
        }

        if ($res < 0) {
            throw new TakeposApiException('INTERNAL_ERROR', !empty($invoice->error) ? $invoice->error : 'Failed to validate invoice.', 500);
        }

        $invoice->fetch((int) $invoice->id);
        return $invoice;
    }
}
