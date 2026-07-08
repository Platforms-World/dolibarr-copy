<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/poscore/class/PosSaasBridge.php';
require_once DOL_DOCUMENT_ROOT.'/custom/poscore/class/PosCartService.php';
header('Content-Type: application/json');
$bridge = new PosSaasBridge($db);
$bridge->ensureAjaxAccess($conf, $user, 'create_sale', 'poscore.cashier');
$customerId = (int) GETPOST('customer_id', 'int');
$paymentMethod = GETPOST('payment_method', 'alphanohtml');
if ($customerId <= 0) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'error' => 'Customer is required'));
    exit;
}
$maxSales = (int) $bridge->getLimit($conf->entity, 'max_sales_per_day', 0);
if ($maxSales > 0) {
    $sqlCount = "SELECT COUNT(rowid) as nb FROM ".MAIN_DB_PREFIX."facture WHERE entity = ".((int) $conf->entity)." AND datec >= '".$db->idate(dol_get_first_hour(dol_now()))."'";
    $resCount = $db->query($sqlCount);
    if ($resCount && ($obj = $db->fetch_object($resCount)) && (int) $obj->nb >= $maxSales) {
        http_response_code(403);
        echo json_encode(array('success' => false, 'error' => 'Daily sales limit reached'));
        exit;
    }
}
$sql = "SELECT c.fk_product, c.qty, c.price_ht, c.remise_percent, p.label, p.ref, p.tva_tx
        FROM ".MAIN_DB_PREFIX."poscore_cart as c
        INNER JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = c.fk_product AND p.entity = c.entity
        WHERE c.entity = ".((int) $conf->entity)." AND c.fk_user = ".((int) $user->id)." ORDER BY c.rowid ASC";
$resql = $db->query($sql);
if (!$resql) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'error' => $db->lasterror()));
    exit;
}
$lines = array();
while ($obj = $db->fetch_object($resql)) $lines[] = $obj;
if (empty($lines)) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'error' => 'Cart is empty'));
    exit;
}
$db->begin();
try {
    $invoice = new Facture($db);
    $invoice->socid = $customerId;
    $invoice->type = Facture::TYPE_STANDARD;
    $invoice->entity = (int) $conf->entity;
    $invoice->cond_reglement_id = 1;
    $invoice->mode_reglement_id = 0;
    $invoice->note_private = "Created by POS Terminal\nCashier User ID: ".$user->id."\nEntity: ".$conf->entity."\nPayment: ".$paymentMethod;
    $invoiceId = $invoice->create($user);
    if ($invoiceId <= 0) throw new Exception($invoice->error ?: implode(', ', (array) $invoice->errors));
    foreach ($lines as $line) {
        $desc = $line->ref.' - '.$line->label;
        $res = $invoice->addline($desc, price2num($line->price_ht, 'MU'), price2num($line->qty, 'MU'), (float) $line->tva_tx, 0, 0, (int) $line->fk_product, price2num($line->remise_percent, 'MU'));
        if ($res <= 0) throw new Exception($invoice->error ?: implode(', ', (array) $invoice->errors));
    }
    $sqlDelete = "DELETE FROM ".MAIN_DB_PREFIX."poscore_cart WHERE entity = ".((int) $conf->entity)." AND fk_user = ".((int) $user->id);
    if (!$db->query($sqlDelete)) throw new Exception($db->lasterror());
    $db->commit();
    echo json_encode(array('success' => true, 'invoice_id' => (int) $invoiceId, 'invoice_ref' => $invoice->ref, 'message' => 'Invoice created successfully'));
} catch (Exception $e) {
    $db->rollback();
    http_response_code(500);
    echo json_encode(array('success' => false, 'error' => $e->getMessage()));
}
