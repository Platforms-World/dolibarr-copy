<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/factureligne.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/poscore/class/service/PosSaasBridge.php';
require_once DOL_DOCUMENT_ROOT.'/custom/poscore/class/service/PosCartService.php';

header('Content-Type: application/json');

function poscore_json_error($message, $code = 403)
{
    http_response_code($code);
    echo json_encode(array('success' => false, 'error' => $message));
    exit;
}

function poscore_count_today_sales($db, $entity)
{
    $startOfDay = dol_get_first_hour(dol_now());
    $sql = "SELECT COUNT(rowid) as nb
            FROM ".MAIN_DB_PREFIX."facture
            WHERE entity = ".((int) $entity)."
              AND datec >= '".$db->idate($startOfDay)."'";
    $resql = $db->query($sql);
    if ($resql && ($obj = $db->fetch_object($resql))) {
        return (int) $obj->nb;
    }
    return 0;
}

try {
    $bridge = new PosSaasBridge($db);
    $bridge->requireAccess('create_sale', 'poscore.cashier', 'post');

    $customerId = (int) GETPOST('customer_id', 'int');
    $paymentMethod = GETPOST('payment_method', 'alphanohtml');
    if ($customerId <= 0) {
        poscore_json_error('Customer is required', 400);
    }

    $maxSalesPerDay = $bridge->getLimitValue('max_sales_per_day', null);
    if (is_numeric($maxSalesPerDay) && (int) $maxSalesPerDay >= 0) {
        if (poscore_count_today_sales($db, (int) $conf->entity) >= (int) $maxSalesPerDay) {
            poscore_json_error('Daily sales limit reached', 403);
        }
    }

    $sql = "SELECT c.fk_product, c.qty, c.price_ht, c.remise_percent, p.label, p.ref, p.tva_tx
            FROM ".MAIN_DB_PREFIX."poscore_cart as c
            INNER JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = c.fk_product AND p.entity = c.entity
            WHERE c.entity = ".((int) $conf->entity)."
              AND c.fk_user = ".((int) $user->id)."
            ORDER BY c.rowid ASC";
    $resql = $db->query($sql);
    if (!$resql) {
        poscore_json_error($db->lasterror(), 500);
    }

    $lines = array();
    while ($obj = $db->fetch_object($resql)) {
        $lines[] = $obj;
    }
    if (empty($lines)) {
        poscore_json_error('Cart is empty', 400);
    }

    $db->begin();

    $invoice = new Facture($db);
    $invoice->socid = $customerId;
    $invoice->type = Facture::TYPE_STANDARD;
    $invoice->entity = (int) $conf->entity;
    $invoice->cond_reglement_id = 1;
    $invoice->mode_reglement_id = 0;
    $invoice->note_private = "Created by POS Terminal\nCashier User ID: ".$user->id."\nEntity: ".$conf->entity."\nPayment: ".$paymentMethod;

    $invoiceId = $invoice->create($user);
    if ($invoiceId <= 0) {
        throw new Exception($invoice->error ?: (!empty($invoice->errors) ? implode(', ', $invoice->errors) : 'Invoice creation failed'));
    }

    foreach ($lines as $line) {
        $desc = $line->ref.' - '.$line->label;
        $res = $invoice->addline(
            $desc,
            price2num($line->price_ht),
            price2num($line->qty),
            (float) $line->tva_tx,
            0,
            0,
            (int) $line->fk_product,
            price2num($line->remise_percent),
            '',
            0,
            0,
            '',
            'HT',
            0,
            FactureLigne::TYPE_STANDARD
        );
        if ($res <= 0) {
            throw new Exception($invoice->error ?: (!empty($invoice->errors) ? implode(', ', $invoice->errors) : 'Invoice line creation failed'));
        }
    }

    $cartService = new PosCartService($db);
    if (!$cartService->clearCart((int) $conf->entity, (int) $user->id)) {
        throw new Exception($db->lasterror());
    }

    $db->commit();

    echo json_encode(array(
        'success' => true,
        'invoice_id' => (int) $invoiceId,
        'invoice_ref' => $invoice->ref,
        'message' => 'Invoice created successfully',
    ));
} catch (Throwable $e) {
    $db->rollback();
    poscore_json_error($e->getMessage(), 500);
}
