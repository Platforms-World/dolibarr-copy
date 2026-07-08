<?php
/**
 * Product barcode alias management for TakePOS.
 */
require_once __DIR__ . '/TakeposInputValidator.class.php';

class TakeposProductBarcodeService
{
    protected static function trans($key, $fallback)
    {
        global $langs;

        if (is_object($langs)) {
            $langs->load('takeposcustom@takepos');
            $translated = $langs->trans($key);
            if ($translated !== $key) {
                return $translated;
            }
        }

        return $fallback;
    }

    public static function table()
    {
        return MAIN_DB_PREFIX . 'takepos_product_barcode';
    }

    public static function canRead($user)
    {
        return (!empty($user->admin) || $user->hasRight('produit', 'lire') || $user->hasRight('service', 'lire'));
    }

    public static function canWrite($user)
    {
        return (!empty($user->admin) || $user->hasRight('produit', 'creer') || $user->hasRight('service', 'creer'));
    }

    public static function ensureSchema($db)
    {
        $sql = "CREATE TABLE IF NOT EXISTS " . self::table() . " ("
            . " rowid INT AUTO_INCREMENT PRIMARY KEY,"
            . " fk_product INT NOT NULL,"
            . " barcode VARCHAR(190) NOT NULL,"
            . " entity INT NOT NULL DEFAULT 1,"
            . " date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,"
            . " tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
            . " UNIQUE KEY uk_takepos_product_barcode_entity (entity, barcode),"
            . " KEY idx_takepos_product_barcode_product (fk_product)"
            . " ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $resql = $db->query($sql);
        if (!$resql) {
            throw new Exception(self::trans('TakeposProductBarcodeSchemaError', 'Unable to initialize product barcode storage.'));
        }
    }

    public static function normalizeBarcode($barcode)
    {
        return trim(TakeposInputValidator::normalizeUtf8Text($barcode, 190, false));
    }

    public static function getProduct($db, $entity, $productId)
    {
        $sql = "SELECT rowid, ref, label, barcode, tosell, tobuy";
        $sql .= " FROM " . MAIN_DB_PREFIX . "product";
        $sql .= " WHERE entity IN (" . getEntity('product') . ")";
        $sql .= " AND rowid = " . ((int) $productId);
        $sql .= " LIMIT 1";

        $resql = $db->query($sql);
        if (!$resql || !($obj = $db->fetch_object($resql))) {
            return null;
        }

        return $obj;
    }

    public static function searchProducts($db, $entity, $search = '', $limit = 50)
    {
        $limit = max(1, (int) $limit);
        $search = trim((string) $search);

        $sql = "SELECT rowid, ref, label, barcode, tosell, tobuy";
        $sql .= " FROM " . MAIN_DB_PREFIX . "product";
        $sql .= " WHERE entity IN (" . getEntity('product') . ")";
        if ($search !== '') {
            $sql .= natural_search(array('ref', 'label', 'barcode'), $search);
        }
        $sql .= " ORDER BY rowid DESC";
        $sql .= $db->plimit($limit, 0);

        $rows = array();
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $rows[] = $obj;
            }
        }

        return $rows;
    }

    public static function listAliases($db, $entity, $productId)
    {
        $sql = "SELECT rowid, barcode, date_creation, tms";
        $sql .= " FROM " . self::table();
        $sql .= " WHERE entity = " . ((int) $entity);
        $sql .= " AND fk_product = " . ((int) $productId);
        $sql .= " ORDER BY barcode ASC";

        $rows = array();
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $rows[] = $obj;
            }
        }

        return $rows;
    }

    public static function getAlias($db, $entity, $productId, $aliasId)
    {
        $sql = "SELECT rowid, fk_product, barcode, date_creation, tms";
        $sql .= " FROM " . self::table();
        $sql .= " WHERE entity = " . ((int) $entity);
        $sql .= " AND fk_product = " . ((int) $productId);
        $sql .= " AND rowid = " . ((int) $aliasId);
        $sql .= " LIMIT 1";

        $resql = $db->query($sql);
        if ($resql && ($obj = $db->fetch_object($resql))) {
            return $obj;
        }

        return null;
    }

    protected static function barcodeExistsOnProduct($db, $entity, $barcode, $excludeProductId = 0)
    {
        $sql = "SELECT rowid, ref, label";
        $sql .= " FROM " . MAIN_DB_PREFIX . "product";
        $sql .= " WHERE entity IN (" . getEntity('product') . ")";
        $sql .= " AND barcode = '" . $db->escape($barcode) . "'";
        if ((int) $excludeProductId > 0) {
            $sql .= " AND rowid <> " . ((int) $excludeProductId);
        }
        $sql .= " LIMIT 1";

        $resql = $db->query($sql);
        return ($resql && ($obj = $db->fetch_object($resql))) ? $obj : null;
    }

    protected static function barcodeExistsOnAlias($db, $entity, $barcode, $excludeAliasId = 0)
    {
        $sql = "SELECT rowid, fk_product";
        $sql .= " FROM " . self::table();
        $sql .= " WHERE entity = " . ((int) $entity);
        $sql .= " AND barcode = '" . $db->escape($barcode) . "'";
        if ((int) $excludeAliasId > 0) {
            $sql .= " AND rowid <> " . ((int) $excludeAliasId);
        }
        $sql .= " LIMIT 1";

        $resql = $db->query($sql);
        return ($resql && ($obj = $db->fetch_object($resql))) ? $obj : null;
    }

    public static function addAlias($db, $user, $productId, $barcode)
    {
        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        $productId = (int) $productId;
        $barcode = self::normalizeBarcode($barcode);

        if ($productId <= 0) {
            throw new Exception(self::trans('TakeposProductBarcodeProductRequired', 'Product is required.'));
        }
        if ($barcode === '') {
            throw new Exception(self::trans('TakeposProductBarcodeValueRequired', 'Barcode value is required.'));
        }

        $product = self::getProduct($db, $entity, $productId);
        if (!$product) {
            throw new Exception(self::trans('TakeposProductBarcodeProductNotFound', 'Product not found.'));
        }

        if (!empty($product->barcode) && hash_equals((string) $product->barcode, $barcode)) {
            throw new Exception(self::trans('TakeposProductBarcodeMatchesPrimary', 'This barcode is already the primary barcode of the selected product.'));
        }

        $productCollision = self::barcodeExistsOnProduct($db, $entity, $barcode, $productId);
        if ($productCollision) {
            throw new Exception(self::trans('TakeposProductBarcodeDuplicateOtherProduct', 'This barcode is already assigned to another product.'));
        }

        $aliasCollision = self::barcodeExistsOnAlias($db, $entity, $barcode, 0);
        if ($aliasCollision) {
            if ((int) $aliasCollision->fk_product === $productId) {
                throw new Exception(self::trans('TakeposProductBarcodeDuplicateSameProduct', 'This barcode alias already exists for the selected product.'));
            }
            throw new Exception(self::trans('TakeposProductBarcodeDuplicateOtherProduct', 'This barcode is already assigned to another product.'));
        }

        $sql = "INSERT INTO " . self::table() . " (fk_product, barcode, entity, date_creation) VALUES (";
        $sql .= $productId . ", '" . $db->escape($barcode) . "', " . $entity . ", '" . $db->idate(dol_now()) . "')";

        if (!$db->query($sql)) {
            throw new Exception(self::trans('TakeposProductBarcodeSaveError', 'Unable to save product barcode alias.'));
        }

        return (int) $db->last_insert_id(self::table());
    }

    public static function deleteAlias($db, $user, $productId, $aliasId)
    {
        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        $productId = (int) $productId;
        $aliasId = (int) $aliasId;

        if ($productId <= 0 || $aliasId <= 0) {
            throw new Exception(self::trans('TakeposProductBarcodeAliasRequired', 'Barcode alias is required.'));
        }

        $sql = "DELETE FROM " . self::table();
        $sql .= " WHERE entity = " . $entity;
        $sql .= " AND fk_product = " . $productId;
        $sql .= " AND rowid = " . $aliasId;
        $sql .= " LIMIT 1";

        if (!$db->query($sql)) {
            throw new Exception(self::trans('TakeposProductBarcodeDeleteError', 'Unable to delete product barcode alias.'));
        }
    }

    public static function updateAlias($db, $user, $productId, $aliasId, $barcode)
    {
        $entity = !empty($user->entity) ? (int) $user->entity : 1;
        $productId = (int) $productId;
        $aliasId = (int) $aliasId;
        $barcode = self::normalizeBarcode($barcode);

        if ($productId <= 0) {
            throw new Exception(self::trans('TakeposProductBarcodeProductRequired', 'Product is required.'));
        }
        if ($aliasId <= 0) {
            throw new Exception(self::trans('TakeposProductBarcodeAliasRequired', 'Barcode alias is required.'));
        }
        if ($barcode === '') {
            throw new Exception(self::trans('TakeposProductBarcodeValueRequired', 'Barcode value is required.'));
        }

        $product = self::getProduct($db, $entity, $productId);
        if (!$product) {
            throw new Exception(self::trans('TakeposProductBarcodeProductNotFound', 'Product not found.'));
        }

        $alias = self::getAlias($db, $entity, $productId, $aliasId);
        if (!$alias) {
            throw new Exception(self::trans('TakeposProductBarcodeAliasRequired', 'Barcode alias is required.'));
        }

        if (!empty($product->barcode) && hash_equals((string) $product->barcode, $barcode)) {
            throw new Exception(self::trans('TakeposProductBarcodeMatchesPrimary', 'This barcode is already the primary barcode of the selected product.'));
        }

        $productCollision = self::barcodeExistsOnProduct($db, $entity, $barcode, $productId);
        if ($productCollision) {
            throw new Exception(self::trans('TakeposProductBarcodeDuplicateOtherProduct', 'This barcode is already assigned to another product.'));
        }

        $aliasCollision = self::barcodeExistsOnAlias($db, $entity, $barcode, $aliasId);
        if ($aliasCollision) {
            if ((int) $aliasCollision->fk_product === $productId) {
                throw new Exception(self::trans('TakeposProductBarcodeDuplicateSameProduct', 'This barcode alias already exists for the selected product.'));
            }
            throw new Exception(self::trans('TakeposProductBarcodeDuplicateOtherProduct', 'This barcode is already assigned to another product.'));
        }

        $sql = "UPDATE " . self::table();
        $sql .= " SET barcode = '" . $db->escape($barcode) . "'";
        $sql .= " WHERE entity = " . $entity;
        $sql .= " AND fk_product = " . $productId;
        $sql .= " AND rowid = " . $aliasId;
        $sql .= " LIMIT 1";

        if (!$db->query($sql)) {
            throw new Exception(self::trans('TakeposProductBarcodeSaveError', 'Unable to save product barcode alias.'));
        }

        return true;
    }
}
