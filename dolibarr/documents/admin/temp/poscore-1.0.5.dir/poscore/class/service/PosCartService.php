<?php
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

class PosCartService
{
    /** @var DoliDB */
    public $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function addProduct($entity, $userId, $productId, $qty = 1)
    {
        $product = new Product($this->db);
        if ($product->fetch((int) $productId) <= 0) {
            return array('success' => false, 'error' => 'Product not found');
        }
        if ((int) $product->entity !== (int) $entity) {
            return array('success' => false, 'error' => 'Product belongs to another entity');
        }

        $qty = max(1, (float) $qty);
        $price = price2num($product->price, 'MU');
        $discount = 0;

        $sql = "INSERT INTO ".MAIN_DB_PREFIX."poscore_cart (entity, fk_user, fk_product, qty, price_ht, remise_percent)
                VALUES (".((int) $entity).", ".((int) $userId).", ".((int) $productId).", ".price2num($qty).", ".price2num($price).", ".price2num($discount).")
                ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty), price_ht = VALUES(price_ht), remise_percent = VALUES(remise_percent)";

        if (!$this->db->query($sql)) {
            return array('success' => false, 'error' => $this->db->lasterror());
        }

        return array('success' => true, 'cart' => $this->getCart($entity, $userId));
    }

    public function removeProduct($entity, $userId, $productId)
    {
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."poscore_cart
                WHERE entity = ".((int) $entity)."
                  AND fk_user = ".((int) $userId)."
                  AND fk_product = ".((int) $productId);
        if (!$this->db->query($sql)) {
            return array('success' => false, 'error' => $this->db->lasterror());
        }
        return array('success' => true, 'cart' => $this->getCart($entity, $userId));
    }

    public function clearCart($entity, $userId)
    {
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."poscore_cart
                WHERE entity = ".((int) $entity)."
                  AND fk_user = ".((int) $userId);
        if (!$this->db->query($sql)) {
            return false;
        }
        return true;
    }

    public function getCart($entity, $userId)
    {
        $sql = "SELECT c.fk_product, c.qty, c.price_ht, c.remise_percent,
                       p.ref, p.label, p.tva_tx
                FROM ".MAIN_DB_PREFIX."poscore_cart as c
                INNER JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = c.fk_product AND p.entity = c.entity
                WHERE c.entity = ".((int) $entity)."
                  AND c.fk_user = ".((int) $userId)."
                ORDER BY c.rowid ASC";

        $resql = $this->db->query($sql);
        $items = array();
        $subtotal = 0;
        $tax = 0;
        $total = 0;

        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $lineSubtotal = price2num($obj->qty * $obj->price_ht * (1 - ((float) $obj->remise_percent / 100)));
                $lineTax = price2num($lineSubtotal * ((float) $obj->tva_tx / 100));
                $lineTotal = price2num($lineSubtotal + $lineTax);

                $subtotal += $lineSubtotal;
                $tax += $lineTax;
                $total += $lineTotal;

                $items[] = array(
                    'product_id' => (int) $obj->fk_product,
                    'product' => trim($obj->ref.' - '.$obj->label),
                    'qty' => price2num($obj->qty),
                    'price' => price2num($obj->price_ht),
                    'discount' => price2num($obj->remise_percent),
                    'total' => price2num($lineTotal),
                );
            }
        }

        return array(
            'items' => $items,
            'subtotal' => price2num($subtotal),
            'tax' => price2num($tax),
            'total' => price2num($total),
        );
    }
}
