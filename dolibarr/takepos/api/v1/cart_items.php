<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_request.php';
require_once __DIR__ . '/_invoice_common.php';

$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET');
if (!in_array($method, array('POST', 'PATCH', 'DELETE'), true)) {
    takeposApiError('METHOD_NOT_ALLOWED', 'Method not allowed.', 405, null, array(), array('Allow: POST, PATCH, DELETE'));
}

$auth = takeposApiAuth($db, 'write', 'takepos.api_layer');
$entity = (int) $auth['entity'];

if ($method === 'POST') {
    $body = takeposApiRequestBody();
    $cartId = (int) takeposApiRequestRequireField($body, 'cart_id');
    $productId = (int) takeposApiRequestRequireField($body, 'product_id');
    $qty = (!empty($body['qty']) ? (float) $body['qty'] : 1.0);
    if ($qty <= 0) {
        throw new TakeposApiException('INVALID_PARAMETER', 'qty must be greater than zero.', 422);
    }

    $invoice = takeposApiRequireTakeposInvoice($db, $entity, $cartId);
    takeposApiAssertDraftInvoice($invoice);

    $terminal = TakeposApiCheckoutService::resolveTerminalForInvoice($db, $entity, $invoice);

    $product = new Product($db);
    if ($product->fetch($productId) <= 0) {
        throw new TakeposApiException('NOT_FOUND', 'Product not found.', 404);
    }

    TakeposApiCheckoutService::assertProductStockAvailableForInvoice($db, $entity, $invoice, $terminal, $product, $qty);

    $customer = new Societe($db);
    if (!empty($invoice->socid)) {
        $customer->fetch((int) $invoice->socid);
    }

    $priceData = $product->getSellPrice($mysoc, $customer, 0);
    $priceBaseType = (!empty($priceData['price_base_type']) ? (string) $priceData['price_base_type'] : 'HT');
    $tvaTx = (float) $priceData['tva_tx'];
    $tvaNpr = (!empty($priceData['tva_npr']) ? (int) $priceData['tva_npr'] : 0);
    $localtax1 = get_localtax($tvaTx, 1, $customer, $mysoc, $tvaNpr);
    $localtax2 = get_localtax($tvaTx, 2, $customer, $mysoc, $tvaNpr);

    if (array_key_exists('price', $body)) {
        if ($priceBaseType === 'TTC') {
            $priceTtc = (float) price2num($body['price'], 'MU');
            $price = (float) price2num($priceTtc / (1 + ($tvaTx / 100)), 'MU');
        } else {
            $price = (float) price2num($body['price'], 'MU');
            $priceTtc = (float) price2num($price * (1 + ($tvaTx / 100)), 'MU');
        }
    } else {
        $price = (float) price2num($priceData['pu_ht'], 'MU');
        $priceTtc = (float) price2num($priceData['pu_ttc'], 'MU');
    }


    $res = $invoice->addline(
        $product->description,  // desc
        $price,                 // subprice (pu_ht)
        $qty,                   // qty
        $tvaTx,                 // txtva
        $localtax1,             // txlocaltax1
        $localtax2,             // txlocaltax2
        $productId,             // fk_product
        0,                      // remise_percent
        '',                     // date_start
        '',                     // date_end
        0,                      // ventil
        0,                      // info_bits
        '',                     // price_base_type — اتركه فاضي
        'HT',                   // price_base_type الصحيح
        $priceTtc,              // pu_ttc
        $product->type,         // type
        -1,                     // rang
        0,                      // special_code
        '',                     // origin
        0,                      // origin_id
        0,                      // fk_parent_line
        0,                      // fk_fournprice
        0,                      // pa_ht
        '',                     // label
        array(),                // array_options
        100,                    // situation_percent
        0,                      // fk_unit
        null,                   // pu_ht_devise
        0                       // ref_ext
    );    if ($res <= 0) {
        throw new TakeposApiException('INTERNAL_ERROR', !empty($invoice->error) ? $invoice->error : 'Failed to add cart item.', 500);
    }

    $invoice = takeposApiRequireTakeposInvoice($db, $entity, $cartId);
    takeposApiSuccess(takeposApiInvoiceSnapshot($db, $entity, $invoice, true), array('entity' => $entity), 201);
}

$lineId = GETPOSTINT('id');
if ($lineId <= 0) {
    throw new TakeposApiException('INVALID_PARAMETER', 'id is required.', 422);
}

list($invoice, $line) = takeposApiInvoiceLineContext($db, $entity, $lineId);
if (!$invoice || !$line) {
    throw new TakeposApiException('NOT_FOUND', 'Cart item not found.', 404);
}
takeposApiAssertDraftInvoice($invoice);

if ($method === 'DELETE') {
    $res = $invoice->deleteLine($lineId);
    if ($res < 0) {
        throw new TakeposApiException('INTERNAL_ERROR', !empty($invoice->error) ? $invoice->error : 'Failed to delete cart item.', 500);
    }

    $invoice = takeposApiRequireTakeposInvoice($db, $entity, (int) $invoice->id);
    takeposApiSuccess(takeposApiInvoiceSnapshot($db, $entity, $invoice, true), array('entity' => $entity));
}

$body = takeposApiRequestBody();
if (!array_key_exists('qty', $body) && !array_key_exists('price', $body) && !array_key_exists('discount_percent', $body)) {
    throw new TakeposApiException('INVALID_PARAMETER', 'Nothing to update.', 422);
}

$qty = (array_key_exists('qty', $body) ? (float) $body['qty'] : (float) $line->qty);
if ($qty <= 0) {
    throw new TakeposApiException('INVALID_PARAMETER', 'qty must be greater than zero.', 422);
}

$price = (array_key_exists('price', $body) ? (float) price2num($body['price'], 'MU') : (float) price2num($line->subprice, 'MU'));
$discount = (array_key_exists('discount_percent', $body) ? (float) price2num($body['discount_percent'], 'MU') : (float) price2num($line->remise_percent, 'MU'));

if (!empty($line->fk_product)) {
    $terminal = TakeposApiCheckoutService::resolveTerminalForInvoice($db, $entity, $invoice);
    $product = new Product($db);
    if ($product->fetch((int) $line->fk_product) > 0) {
        TakeposApiCheckoutService::assertProductStockAvailableForInvoice($db, $entity, $invoice, $terminal, $product, $qty, (int) $line->id);
    }
}

$res = $invoice->updateline($line->id, $line->desc, $price, $qty, $discount, $line->date_start, $line->date_end, takeposApiInvoiceVatRateCode($line), $line->localtax1_tx, $line->localtax2_tx, 'HT', $line->info_bits, $line->product_type, $line->fk_parent_line, 0, $line->fk_fournprice, $line->pa_ht, $line->label, $line->special_code, $line->array_options, $line->situation_percent, $line->fk_unit);
if ($res < 0) {
    throw new TakeposApiException('INTERNAL_ERROR', !empty($invoice->error) ? $invoice->error : 'Failed to update cart item.', 500);
}

$invoice = takeposApiRequireTakeposInvoice($db, $entity, (int) $invoice->id);
takeposApiSuccess(takeposApiInvoiceSnapshot($db, $entity, $invoice, true), array('entity' => $entity));
