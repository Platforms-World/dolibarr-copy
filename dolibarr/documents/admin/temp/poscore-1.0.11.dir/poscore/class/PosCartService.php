<?php
class PosCartService
{
    protected $db;
    protected $conf;
    protected $user;

    public function __construct($db, $conf, $user)
    {
        $this->db = $db;
        $this->conf = $conf;
        $this->user = $user;
    }

    public function buildCart()
    {
        $sql = "SELECT c.fk_product, c.qty, c.price_ht, c.remise_percent, p.ref, p.label, p.tva_tx
                FROM ".MAIN_DB_PREFIX."poscore_cart as c
                INNER JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = c.fk_product AND p.entity = c.entity
                WHERE c.entity = ".((int) $this->conf->entity)."
                  AND c.fk_user = ".((int) $this->user->id)."
                ORDER BY c.rowid ASC";
        $resql = $this->db->query($sql);
        $items = array();
        $subtotal = 0; $tax = 0; $total = 0;
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $lineSubtotal = price2num($obj->qty * $obj->price_ht * (1 - ($obj->remise_percent / 100)), 'MU');
                $lineTax = price2num($lineSubtotal * ((float) $obj->tva_tx / 100), 'MU');
                $lineTotal = price2num($lineSubtotal + $lineTax, 'MU');
                $subtotal += $lineSubtotal; $tax += $lineTax; $total += $lineTotal;
                $items[] = array(
                    'product_id' => (int) $obj->fk_product,
                    'product' => trim($obj->ref.' - '.$obj->label),
                    'qty' => (float) $obj->qty,
                    'price' => price2num($obj->price_ht, 'MU'),
                    'discount' => price2num($obj->remise_percent, 'MU'),
                    'total' => price2num($lineTotal, 'MU')
                );
            }
        }
        return array(
            'items' => $items,
            'subtotal' => price2num($subtotal, 'MU'),
            'tax' => price2num($tax, 'MU'),
            'total' => price2num($total, 'MU')
        );
    }
}
