<?php
require_once __DIR__ . '/TakeposMigration.class.php';
require_once __DIR__ . '/TakeposUserAccess.class.php';
require_once __DIR__ . '/TakeposAudit.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

class TakeposPurchaseService
{
    protected static function trans($key, $fallback)
    {
        global $langs;
        if (is_object($langs)) {
            $translated = $langs->trans($key);
            if ($translated !== $key) return $translated;
        }
        return $fallback;
    }

    public static function tablePurchase()
    {
        return MAIN_DB_PREFIX . 'takepos_purchase';
    }

    public static function tablePurchaseLine()
    {
        return MAIN_DB_PREFIX . 'takepos_purchase_line';
    }

    public static function ensureSchema($db)
    {
        $ok = true;

        $ok = $ok && TakeposMigration::ensureTable($db, self::tablePurchase(), "CREATE TABLE " . self::tablePurchase() . " ("
            . " rowid INT AUTO_INCREMENT PRIMARY KEY,"
            . " entity INT NOT NULL DEFAULT 1,"
            . " ref VARCHAR(64) NOT NULL,"
            . " purchase_date DATETIME NOT NULL,"
            . " fk_supplier INT NULL,"
            . " fk_warehouse INT NOT NULL,"
            . " external_ref VARCHAR(190) NULL,"
            . " supplier_invoice_ref VARCHAR(190) NULL,"
            . " supplier_invoice_url VARCHAR(255) NULL,"
            . " note_private TEXT NULL,"
            . " status TINYINT(1) NOT NULL DEFAULT 1,"
            . " total_ht DECIMAL(24,8) NOT NULL DEFAULT 0,"
            . " total_tva DECIMAL(24,8) NOT NULL DEFAULT 0,"
            . " total_ttc DECIMAL(24,8) NOT NULL DEFAULT 0,"
            . " fk_user_creat INT NOT NULL,"
            . " fk_user_modif INT NULL,"
            . " date_creation DATETIME NOT NULL,"
            . " date_modification DATETIME NULL,"
            . " tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
            . " UNIQUE KEY uk_takepos_purchase_entity_ref (entity, ref),"
            . " KEY idx_takepos_purchase_entity_date (entity, purchase_date),"
            . " KEY idx_takepos_purchase_entity_supplier (entity, fk_supplier)"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $ok = $ok && TakeposMigration::ensureTable($db, self::tablePurchaseLine(), "CREATE TABLE " . self::tablePurchaseLine() . " ("
            . " rowid INT AUTO_INCREMENT PRIMARY KEY,"
            . " entity INT NOT NULL DEFAULT 1,"
            . " fk_purchase INT NOT NULL,"
            . " fk_product INT NOT NULL,"
            . " product_ref VARCHAR(128) NULL,"
            . " product_label VARCHAR(255) NULL,"
            . " qty DECIMAL(24,8) NOT NULL DEFAULT 0,"
            . " buy_price_ht DECIMAL(24,8) NOT NULL DEFAULT 0,"
            . " tva_tx DECIMAL(12,4) NOT NULL DEFAULT 0,"
            . " total_ht DECIMAL(24,8) NOT NULL DEFAULT 0,"
            . " total_tva DECIMAL(24,8) NOT NULL DEFAULT 0,"
            . " total_ttc DECIMAL(24,8) NOT NULL DEFAULT 0,"
            . " note_line TEXT NULL,"
            . " date_creation DATETIME NOT NULL,"
            . " tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
            . " KEY idx_takepos_purchase_line_purchase (entity, fk_purchase),"
            . " KEY idx_takepos_purchase_line_product (entity, fk_product)"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if (!$ok) {
            return false;
        }

        $purchaseColumns = array(
            'entity' => "INT NOT NULL DEFAULT 1",
            'ref' => "VARCHAR(64) NOT NULL",
            'purchase_date' => "DATETIME NOT NULL",
            'fk_supplier' => "INT NULL",
            'fk_warehouse' => "INT NOT NULL",
            'external_ref' => "VARCHAR(190) NULL",
            'supplier_invoice_ref' => "VARCHAR(190) NULL",
            'supplier_invoice_url' => "VARCHAR(255) NULL",
            'note_private' => "TEXT NULL",
            'status' => "TINYINT(1) NOT NULL DEFAULT 1",
            'total_ht' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
            'total_tva' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
            'total_ttc' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
            'fk_user_creat' => "INT NOT NULL",
            'fk_user_modif' => "INT NULL",
            'date_creation' => "DATETIME NOT NULL",
            'date_modification' => "DATETIME NULL",
            'tms' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        );
        foreach ($purchaseColumns as $column => $definition) {
            if (!TakeposMigration::ensureColumn($db, self::tablePurchase(), $column, $definition)) {
                return false;
            }
        }

        if (!TakeposMigration::ensureIndex($db, self::tablePurchase(), 'uk_takepos_purchase_entity_ref', '(entity, ref)', 'UNIQUE')) return false;
        if (!TakeposMigration::ensureIndex($db, self::tablePurchase(), 'idx_takepos_purchase_entity_date', '(entity, purchase_date)')) return false;
        if (!TakeposMigration::ensureIndex($db, self::tablePurchase(), 'idx_takepos_purchase_entity_supplier', '(entity, fk_supplier)')) return false;

        $lineColumns = array(
            'entity' => "INT NOT NULL DEFAULT 1",
            'fk_purchase' => "INT NOT NULL",
            'fk_product' => "INT NOT NULL",
            'product_ref' => "VARCHAR(128) NULL",
            'product_label' => "VARCHAR(255) NULL",
            'qty' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
            'buy_price_ht' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
            'tva_tx' => "DECIMAL(12,4) NOT NULL DEFAULT 0",
            'total_ht' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
            'total_tva' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
            'total_ttc' => "DECIMAL(24,8) NOT NULL DEFAULT 0",
            'note_line' => "TEXT NULL",
            'date_creation' => "DATETIME NOT NULL",
            'tms' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        );
        foreach ($lineColumns as $column => $definition) {
            if (!TakeposMigration::ensureColumn($db, self::tablePurchaseLine(), $column, $definition)) {
                return false;
            }
        }

        if (!TakeposMigration::ensureIndex($db, self::tablePurchaseLine(), 'idx_takepos_purchase_line_purchase', '(entity, fk_purchase)')) return false;
        if (!TakeposMigration::ensureIndex($db, self::tablePurchaseLine(), 'idx_takepos_purchase_line_product', '(entity, fk_product)')) return false;

        return true;
    }

    public static function canRead($dbOrUser, $user = null)
    {
        $db = null;
        if ($user === null) {
            $user = $dbOrUser;
        } else {
            $db = $dbOrUser;
        }

        if (!empty($user->admin)) {
            return true;
        }
        if (is_object($db) && class_exists('TakeposUserAccess') && TakeposUserAccess::userHasPermission($db, $user, 'takepos.purchase.read')) {
            return true;
        }

        return ($user->hasRight('produit', 'lire') || $user->hasRight('service', 'lire'));
    }

    public static function canCreate($dbOrUser, $user = null)
    {
        $db = null;
        if ($user === null) {
            $user = $dbOrUser;
        } else {
            $db = $dbOrUser;
        }

        if (!empty($user->admin)) {
            return true;
        }
        if (is_object($db) && class_exists('TakeposUserAccess') && TakeposUserAccess::userHasPermission($db, $user, 'takepos.purchase.create')) {
            return true;
        }

        return ($user->hasRight('produit', 'creer') || $user->hasRight('service', 'creer'));
    }

    public static function listWarehouses($db, $entity)
    {
        // FIX (warehouse-dropdown-v2): see admin/branches.php — drop status filter
        // (column name varies across Dolibarr versions) AND broaden entity scope.
        $sql = "SELECT rowid, ref, label FROM " . MAIN_DB_PREFIX . "entrepot WHERE entity IN (" . getEntity('stock') . ") ORDER BY ref ASC, label ASC";
        $rows = array();
        $resql = $db->query($sql);
        if ($resql) while ($obj = $db->fetch_object($resql)) $rows[] = $obj;
        return $rows;
    }

    public static function listSuppliers($db, $entity)
    {
        $sql = "SELECT rowid, code_fournisseur, nom, name_alias FROM " . MAIN_DB_PREFIX . "societe WHERE entity = " . ((int) $entity) . " AND fournisseur > 0 AND status = 1 ORDER BY nom ASC";
        $rows = array();
        $resql = $db->query($sql);
        if ($resql) while ($obj = $db->fetch_object($resql)) $rows[] = $obj;
        return $rows;
    }

    protected static function resolveDefaultWarehouseId($db, $entity)
    {
        $warehouses = self::listWarehouses($db, $entity);
        if (!empty($warehouses[0]->rowid)) {
            return (int) $warehouses[0]->rowid;
        }

        return 0;
    }

    protected static function supplierExists($db, $entity, $supplierId)
    {
        $supplierId = (int) $supplierId;
        if ($supplierId <= 0) {
            return false;
        }

        $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "societe";
        $sql .= " WHERE entity = " . ((int) $entity);
        $sql .= " AND rowid = " . $supplierId;
        $sql .= " AND fournisseur > 0 AND status = 1";
        $sql .= " LIMIT 1";

        $resql = $db->query($sql);
        return ($resql && $db->fetch_object($resql));
    }

    public static function listBuyableProducts($db, $entity)
    {
        $rows = array();
        $sql = "SELECT rowid, ref, label, price, pmp, barcode, tobuy, fk_product_type FROM " . MAIN_DB_PREFIX . "product";
        $sql .= " WHERE entity = " . ((int) $entity) . " AND tobuy = 1 AND fk_product_type = 0 ORDER BY ref ASC, label ASC";
        $resql = $db->query($sql);
        if ($resql) while ($obj = $db->fetch_object($resql)) $rows[] = $obj;
        return $rows;
    }

    public static function buildNextRef($db, $entity)
    {
        self::ensureSchema($db);
        $prefix = 'PUR-' . dol_print_date(dol_now(), '%Y%m%d') . '-';
        $sql = "SELECT ref FROM " . self::tablePurchase() . " WHERE entity = " . ((int) $entity) . " AND ref LIKE '" . $db->escape($prefix) . "%' ORDER BY ref DESC LIMIT 1";
        $next = 1;
        $resql = $db->query($sql);
        if ($resql && ($obj = $db->fetch_object($resql))) {
            $tail = substr((string) $obj->ref, strlen($prefix));
            if (ctype_digit((string) $tail)) $next = ((int) $tail) + 1;
        }
        return $prefix . sprintf('%04d', $next);
    }

    public static function normalizeDateTime($value)
    {
        $value = trim((string) $value);
        if ($value === '') return dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S');
        $value = str_replace('T', ' ', $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) $value .= ':00';
        return $value;
    }

    protected static function normalizePayload($db, $user, $payload)
    {
        $entity = !empty($user->entity) ? (int) $user->entity : (!empty($GLOBALS['conf']->entity) ? (int) $GLOBALS['conf']->entity : 1);
        $warehouseId = isset($payload['warehouse_id']) ? (int) $payload['warehouse_id'] : 0;
        $supplierId = isset($payload['supplier_id']) ? (int) $payload['supplier_id'] : 0;
        $purchaseDate = self::normalizeDateTime(isset($payload['purchase_date']) ? $payload['purchase_date'] : '');
        $note = trim((string) (isset($payload['note_private']) ? $payload['note_private'] : ''));
        $externalRef = trim((string) (isset($payload['external_ref']) ? $payload['external_ref'] : ''));
        $supplierInvoiceRef = trim((string) (isset($payload['supplier_invoice_ref']) ? $payload['supplier_invoice_ref'] : ''));
        $lines = isset($payload['lines']) && is_array($payload['lines']) ? $payload['lines'] : array();

        if ($warehouseId <= 0) {
            $warehouseId = self::resolveDefaultWarehouseId($db, $entity);
        }
        if ($supplierId > 0 && !self::supplierExists($db, $entity, $supplierId)) {
            $supplierId = 0;
        }

        if ($warehouseId <= 0) throw new Exception(self::trans('TakeposPurchaseWarehouseRequired', 'Warehouse is required.'));
        if (empty($lines)) throw new Exception(self::trans('TakeposPurchaseLinesRequired', 'At least one purchase line is required.'));

        $cleanLines = array();
        $totalHt = 0.0;
        $totalTva = 0.0;
        $totalTtc = 0.0;

        foreach ($lines as $line) {
            $productId = isset($line['product_id']) ? (int) $line['product_id'] : 0;
            $qty = price2num(isset($line['qty']) ? $line['qty'] : 0, 'MS');
            $buyPrice = price2num(isset($line['buy_price_ht']) ? $line['buy_price_ht'] : 0, 'MU');
            $vatRate = price2num(isset($line['tva_tx']) ? $line['tva_tx'] : 0, 'MU');
            if ($productId <= 0) throw new Exception(self::trans('TakeposPurchaseProductRequired', 'Each line must have a product.'));
            if ($qty <= 0) throw new Exception(self::trans('TakeposPurchaseQtyPositive', 'Quantity must be greater than zero.'));
            if ($buyPrice < 0) throw new Exception(self::trans('TakeposPurchasePricePositive', 'Purchase price cannot be negative.'));

            $product = new Product($db);
            if ($product->fetch($productId) <= 0) throw new Exception(self::trans('TakeposPurchaseProductLoadFailed', 'Unable to load one of the selected products.'));
            if ((int) $product->type === 1) throw new Exception(self::trans('TakeposPurchaseServiceNotAllowed', 'Services cannot be added to stock purchase receipt lines.'));

            $lineTotalHt = round(((float) $qty) * ((float) $buyPrice), 6);
            $lineTotalTva = round($lineTotalHt * (((float) $vatRate) / 100), 6);
            $lineTotalTtc = round($lineTotalHt + $lineTotalTva, 6);
            $cleanLines[] = array(
                'product_id' => $productId,
                'product_ref' => (string) $product->ref,
                'product_label' => (string) $product->label,
                'qty' => (float) $qty,
                'buy_price_ht' => (float) $buyPrice,
                'tva_tx' => (float) $vatRate,
                'total_ht' => (float) $lineTotalHt,
                'total_tva' => (float) $lineTotalTva,
                'total_ttc' => (float) $lineTotalTtc,
                'note_line' => trim((string) (isset($line['note_line']) ? $line['note_line'] : '')),
            );
            $totalHt += $lineTotalHt; $totalTva += $lineTotalTva; $totalTtc += $lineTotalTtc;
        }

        return array(
            'warehouse_id' => $warehouseId,
            'supplier_id' => $supplierId,
            'purchase_date' => $purchaseDate,
            'note_private' => $note,
            'external_ref' => $externalRef,
            'supplier_invoice_ref' => $supplierInvoiceRef,
            'lines' => $cleanLines,
            'total_ht' => $totalHt,
            'total_tva' => $totalTva,
            'total_ttc' => $totalTtc,
        );
    }

    protected static function insertLines($db, $entity, $purchaseId, $lines)
    {
        foreach ($lines as $line) {
            $sqlLine = "INSERT INTO " . self::tablePurchaseLine() . " (entity, fk_purchase, fk_product, product_ref, product_label, qty, buy_price_ht, tva_tx, total_ht, total_tva, total_ttc, note_line, date_creation) VALUES (";
            $sqlLine .= ((int) $entity) . ", " . ((int) $purchaseId) . ", " . ((int) $line['product_id']) . ", '" . $db->escape($line['product_ref']) . "', '" . $db->escape($line['product_label']) . "', ";
            $sqlLine .= price2num($line['qty'], 'MS') . ", " . price2num($line['buy_price_ht'], 'MU') . ", " . price2num($line['tva_tx'], 'MU') . ", ";
            $sqlLine .= price2num($line['total_ht'], 'MU') . ", " . price2num($line['total_tva'], 'MU') . ", " . price2num($line['total_ttc'], 'MU') . ", ";
            $sqlLine .= ($line['note_line'] !== '' ? "'" . $db->escape($line['note_line']) . "'" : 'NULL') . ", '" . $db->escape(dol_print_date(dol_now(), 'dayhourlog')) . "')";
            if (!$db->query($sqlLine)) throw new Exception($db->lasterror());
        }
    }

    protected static function applyStockForLines($db, $user, $warehouseId, $ref, $lines, $direction)
    {
        require_once DOL_DOCUMENT_ROOT . '/product/stock/class/mouvementstock.class.php';
        $inventoryCode = dol_print_date(dol_now(), 'dayhourlog');
        foreach ($lines as $line) {
            $mouvement = new MouvementStock($db);
            if ($direction > 0) {
                $res = $mouvement->reception($user, (int) $line['product_id'], (int) $warehouseId, (float) $line['qty'], (float) $line['buy_price_ht'], 'TakePOS Purchase ' . $ref, '', '', '', '', 0, $inventoryCode);
            } else {
                $res = $mouvement->livraison($user, (int) $line['product_id'], (int) $warehouseId, (float) $line['qty'], (float) $line['buy_price_ht'], 'TakePOS Purchase reverse ' . $ref, '', '', '', '', 0, $inventoryCode);
            }
            if ($res < 0) throw new Exception(!empty($mouvement->error) ? $mouvement->error : self::trans('TakeposPurchaseStockFailed', 'Unable to create stock movement for the purchase receipt.'));
            if ($direction > 0) self::updateProductAverageCost($db, (int) $line['product_id'], (float) $line['qty'], (float) $line['buy_price_ht']);
        }
    }

    protected static function getProductRealStock($db, $productId)
    {
        $sql = "SELECT COALESCE(SUM(reel),0) AS qty FROM " . MAIN_DB_PREFIX . "product_stock WHERE fk_product = " . ((int) $productId);
        $resql = $db->query($sql);
        if ($resql && ($obj = $db->fetch_object($resql))) return (float) $obj->qty;
        return 0.0;
    }

    protected static function updateProductAverageCost($db, $productId, $receivedQty, $buyPrice)
    {
        if ($receivedQty <= 0) return;
        $sql = "SELECT pmp FROM " . MAIN_DB_PREFIX . "product WHERE rowid = " . ((int) $productId) . " LIMIT 1";
        $resql = $db->query($sql);
        $oldPmp = 0.0;
        if ($resql && ($obj = $db->fetch_object($resql))) $oldPmp = (float) $obj->pmp;
        $stockAfter = self::getProductRealStock($db, $productId);
        $stockBefore = max(0.0, $stockAfter - $receivedQty);
        $newPmp = ($stockBefore + $receivedQty) > 0 ? (($stockBefore * $oldPmp) + ($receivedQty * $buyPrice)) / ($stockBefore + $receivedQty) : $buyPrice;
        $sqlUpdate = "UPDATE " . MAIN_DB_PREFIX . "product SET pmp = " . price2num($newPmp, 'MU') . " WHERE rowid = " . ((int) $productId);
        $db->query($sqlUpdate);
    }

    public static function createPurchase($db, $user, $payload)
    {
        self::ensureSchema($db);
        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        $data = self::normalizePayload($db, $user, $payload);
        $ref = self::buildNextRef($db, $entity);
        $supplierInvoiceUrl = '';
        if ($data['supplier_id'] > 0) {
            $supplierInvoiceUrl = DOL_URL_ROOT . '/fourn/facture/card.php?action=create&token='.newToken().'&socid=' . ((int) $data['supplier_id']) . '&origin=takepos_purchase&originid=';
        }

        $db->begin();
        $sql = "INSERT INTO " . self::tablePurchase() . " (entity, ref, purchase_date, fk_supplier, fk_warehouse, external_ref, supplier_invoice_ref, supplier_invoice_url, note_private, status, total_ht, total_tva, total_ttc, fk_user_creat, date_creation) VALUES (";
        $sql .= ((int) $entity) . ", '" . $db->escape($ref) . "', '" . $db->escape($data['purchase_date']) . "', ";
        $sql .= ($data['supplier_id'] > 0 ? $data['supplier_id'] : 'NULL') . ", " . ((int) $data['warehouse_id']) . ", ";
        $sql .= ($data['external_ref'] !== '' ? "'" . $db->escape($data['external_ref']) . "'" : 'NULL') . ", ";
        $sql .= ($data['supplier_invoice_ref'] !== '' ? "'" . $db->escape($data['supplier_invoice_ref']) . "'" : 'NULL') . ", ";
        $sql .= ($supplierInvoiceUrl !== '' ? "'" . $db->escape($supplierInvoiceUrl) . "'" : 'NULL') . ", ";
        $sql .= ($data['note_private'] !== '' ? "'" . $db->escape($data['note_private']) . "'" : 'NULL') . ", 1, ";
        $sql .= price2num($data['total_ht'], 'MU') . ", " . price2num($data['total_tva'], 'MU') . ", " . price2num($data['total_ttc'], 'MU') . ", ";
        $sql .= ((int) $user->id) . ", '" . $db->escape(dol_print_date(dol_now(), 'dayhourlog')) . "')";
        if (!$db->query($sql)) { $db->rollback(); throw new Exception($db->lasterror()); }
        $purchaseId = (int) $db->last_insert_id(self::tablePurchase());
        if ($purchaseId <= 0) { $db->rollback(); throw new Exception(self::trans('TakeposPurchaseCreateFailed', 'Unable to create purchase receipt header.')); }

        self::insertLines($db, $entity, $purchaseId, $data['lines']);
        self::applyStockForLines($db, $user, $data['warehouse_id'], $ref, $data['lines'], 1);

        if ($supplierInvoiceUrl !== '') {
            $db->query("UPDATE " . self::tablePurchase() . " SET supplier_invoice_url = '" . $db->escape($supplierInvoiceUrl . $purchaseId) . "' WHERE rowid = " . $purchaseId);
        }

        TakeposAudit::logEvent($db, $user, 'purchase_created', TakeposAudit::SEVERITY_INFO, array('purchase_id' => $purchaseId, 'ref' => $ref, 'supplier_id' => $data['supplier_id'], 'warehouse_id' => $data['warehouse_id'], 'total_ttc' => $data['total_ttc'], 'line_count' => count($data['lines'])), self::trans('TakeposPurchaseAuditCreated', 'Purchase receipt created'), 'takepos_purchase', $purchaseId, $data['total_ttc']);
        $db->commit();
        return $purchaseId;
    }

    public static function updatePurchase($db, $user, $purchaseId, $payload)
    {
        self::ensureSchema($db);
        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        $existing = self::getPurchaseById($db, $entity, $purchaseId);
        if (!$existing) throw new Exception(self::trans('TakeposPurchaseNotFound', 'Purchase receipt was not found.'));
        $existingLines = self::listPurchaseLines($db, $entity, $purchaseId);
        $data = self::normalizePayload($db, $user, $payload);
        $supplierInvoiceUrl = ($data['supplier_id'] > 0 ? DOL_URL_ROOT . '/fourn/facture/card.php?action=create&token='.newToken().'&socid=' . ((int) $data['supplier_id']) . '&origin=takepos_purchase&originid=' . ((int) $purchaseId) : '');

        $db->begin();
        if (!empty($existingLines)) self::applyStockForLines($db, $user, (int) $existing->fk_warehouse, $existing->ref, array_map(function($l){ return array('product_id'=>$l->fk_product,'qty'=>$l->qty,'buy_price_ht'=>$l->buy_price_ht); }, $existingLines), -1);
        if (!$db->query("DELETE FROM " . self::tablePurchaseLine() . " WHERE entity = " . ((int) $entity) . " AND fk_purchase = " . ((int) $purchaseId))) { $db->rollback(); throw new Exception($db->lasterror()); }

        $sql = "UPDATE " . self::tablePurchase() . " SET purchase_date = '" . $db->escape($data['purchase_date']) . "', fk_supplier = " . ($data['supplier_id'] > 0 ? $data['supplier_id'] : 'NULL') . ", fk_warehouse = " . ((int) $data['warehouse_id']) . ", ";
        $sql .= "external_ref = " . ($data['external_ref'] !== '' ? "'" . $db->escape($data['external_ref']) . "'" : 'NULL') . ", ";
        $sql .= "supplier_invoice_ref = " . ($data['supplier_invoice_ref'] !== '' ? "'" . $db->escape($data['supplier_invoice_ref']) . "'" : 'NULL') . ", ";
        $sql .= "supplier_invoice_url = " . ($supplierInvoiceUrl !== '' ? "'" . $db->escape($supplierInvoiceUrl) . "'" : 'NULL') . ", ";
        $sql .= "note_private = " . ($data['note_private'] !== '' ? "'" . $db->escape($data['note_private']) . "'" : 'NULL') . ", ";
        $sql .= "total_ht = " . price2num($data['total_ht'], 'MU') . ", total_tva = " . price2num($data['total_tva'], 'MU') . ", total_ttc = " . price2num($data['total_ttc'], 'MU') . ", ";
        $sql .= "fk_user_modif = " . ((int) $user->id) . ", date_modification = '" . $db->escape(dol_print_date(dol_now(), 'dayhourlog')) . "' ";
        $sql .= "WHERE entity = " . ((int) $entity) . " AND rowid = " . ((int) $purchaseId);
        if (!$db->query($sql)) { $db->rollback(); throw new Exception($db->lasterror()); }

        self::insertLines($db, $entity, $purchaseId, $data['lines']);
        self::applyStockForLines($db, $user, $data['warehouse_id'], $existing->ref, $data['lines'], 1);

        TakeposAudit::logEvent($db, $user, 'purchase_updated', TakeposAudit::SEVERITY_INFO, array('purchase_id' => $purchaseId, 'ref' => $existing->ref, 'supplier_id' => $data['supplier_id'], 'warehouse_id' => $data['warehouse_id'], 'total_ttc' => $data['total_ttc'], 'line_count' => count($data['lines'])), self::trans('TakeposPurchaseAuditUpdated', 'Purchase receipt updated'), 'takepos_purchase', $purchaseId, $data['total_ttc']);
        $db->commit();
        return $purchaseId;
    }

    public static function getPurchaseById($db, $entity, $purchaseId)
    {
        self::ensureSchema($db);
        $sql = "SELECT p.rowid, p.ref, p.purchase_date, p.fk_supplier, p.fk_warehouse, p.external_ref, p.supplier_invoice_ref, p.supplier_invoice_url, p.note_private, p.status, p.total_ht, p.total_tva, p.total_ttc, p.fk_user_creat, p.date_creation, p.fk_user_modif, p.date_modification,";
        $sql .= " s.nom AS supplier_name, s.code_fournisseur, e.ref AS warehouse_ref, e.label AS warehouse_label";
        $sql .= " FROM " . self::tablePurchase() . " p";
        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = p.fk_supplier";
        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "entrepot e ON e.rowid = p.fk_warehouse";
        $sql .= " WHERE p.entity = " . ((int) $entity) . " AND p.rowid = " . ((int) $purchaseId) . " LIMIT 1";
        $resql = $db->query($sql);
        return ($resql ? $db->fetch_object($resql) : null);
    }

    public static function listPurchaseLines($db, $entity, $purchaseId)
    {
        self::ensureSchema($db);
        $sql = "SELECT rowid, fk_purchase, fk_product, product_ref, product_label, qty, buy_price_ht, tva_tx, total_ht, total_tva, total_ttc, note_line FROM " . self::tablePurchaseLine() . " WHERE entity = " . ((int) $entity) . " AND fk_purchase = " . ((int) $purchaseId) . " ORDER BY rowid ASC";
        $rows = array();
        $resql = $db->query($sql);
        if ($resql) while ($obj = $db->fetch_object($resql)) $rows[] = $obj;
        return $rows;
    }

    public static function listRecentPurchases($db, $entity, $limit = 12)
    {
        self::ensureSchema($db);
        $sql = "SELECT p.rowid, p.ref, p.purchase_date, p.external_ref, p.supplier_invoice_ref, p.total_ttc, p.total_ht, p.total_tva, s.nom AS supplier_name, e.ref AS warehouse_ref, e.label AS warehouse_label";
        $sql .= " FROM " . self::tablePurchase() . " p";
        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = p.fk_supplier";
        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "entrepot e ON e.rowid = p.fk_warehouse";
        $sql .= " WHERE p.entity = " . ((int) $entity) . " ORDER BY p.purchase_date DESC, p.rowid DESC LIMIT " . max(1, (int) $limit);
        $rows = array();
        $resql = $db->query($sql);
        if ($resql) while ($obj = $db->fetch_object($resql)) $rows[] = $obj;
        return $rows;
    }
}
