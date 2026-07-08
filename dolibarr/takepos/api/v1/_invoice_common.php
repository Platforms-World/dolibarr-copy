<?php
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

if (!defined('TAKEPOS_API_V1_INVOICE_COMMON_INCLUDED')) {
    define('TAKEPOS_API_V1_INVOICE_COMMON_INCLUDED', 1);

    // ── User context ──────────────────────────────────────────────────────────

    function takeposApiActorUser($entity)
    {
        global $user;
        $actor = (is_object($user) ? clone $user : new stdClass());
        if (!isset($actor->id))     { $actor->id     = 0; }
        if (empty($actor->entity))  { $actor->entity  = (int) $entity; }
        if (empty($actor->login))   { $actor->login   = 'api'; }
        return $actor;
    }

    // ── Date helpers ──────────────────────────────────────────────────────────

    function takeposApiDateValue($value)
    {
        if (empty($value)) {
            return null;
        }
        if (is_numeric($value)) {
            return dol_print_date($value, 'dayhourlog');
        }
        return (string) $value;
    }

    // ── Terminal helpers ──────────────────────────────────────────────────────

    function takeposApiTerminalRow($db, $entity, $terminalId)
    {
        TakeposTerminalService::ensureSchema($db);
        $sql = 'SELECT rowid, entity, terminal_code, label, fk_store, active'
             . ' FROM ' . TakeposTerminalService::tableTerminal()
             . ' WHERE entity = ' . ((int) $entity)
             . ' AND rowid = '    . ((int) $terminalId)
             . ' LIMIT 1';
        $resql = $db->query($sql);
        return ($resql ? $db->fetch_object($resql) : null);
    }

    function takeposApiTerminalBySource($db, $entity, $source)
    {
        return ($source !== '' ? TakeposTerminalService::getTerminalByCode($db, $entity, $source) : null);
    }

    function takeposApiRequireTerminal($db, $entity, $terminalId, $activeOnly = true)
    {
        $terminal = takeposApiTerminalRow($db, $entity, $terminalId);
        if (!$terminal) {
            throw new TakeposApiException('INVALID_PARAMETER', 'Invalid terminal_id.', 422);
        }
        if ($activeOnly && empty($terminal->active)) {
            throw new TakeposApiException('INVALID_PARAMETER', 'Terminal is inactive.', 422);
        }
        return TakeposApiCheckoutService::assertTerminalUsable($db, $entity, $terminal, 'INVALID_PARAMETER', 422);
    }

    function takeposApiResolveDefaultThirdpartyId($terminalCode)
    {
        $id = getDolGlobalInt('CASHDESK_ID_THIRDPARTY' . $terminalCode);
        if ($id <= 0) {
            $id = getDolGlobalInt('CASHDESK_ID_THIRDPARTY');
        }
        return (int) $id;
    }

    // ── Invoice field helpers ─────────────────────────────────────────────────

    function takeposApiInvoiceCurrency($invoice)
    {
        global $conf;
        if (!empty($invoice->multicurrency_code)) {
            return (string) $invoice->multicurrency_code;
        }
        return (!empty($conf->currency) ? (string) $conf->currency : (string) getDolGlobalString('MAIN_MONNAIE', 'USD'));
    }

    function takeposApiInvoiceStatusLabel($invoice)
    {
        $status = isset($invoice->status)
            ? (int) $invoice->status
            : (isset($invoice->statut) ? (int) $invoice->statut : 0);

        if ($status === Facture::STATUS_DRAFT) { return 'draft'; }
        if (!empty($invoice->paye))            { return 'paid';  }
        if ($status > 0)                       { return 'validated'; }
        return 'unknown';
    }

    function takeposApiInvoicePaymentStatus($invoice)
    {
        $remain = (float) $invoice->getRemainToPay();
        if (!empty($invoice->paye) || $remain <= 0) { return 'paid';    }
        if ($remain < (float) $invoice->total_ttc)  { return 'partial'; }
        return 'unpaid';
    }

    function takeposApiInvoiceTerminalId($db, $entity, $invoice)
    {
        $terminal = takeposApiTerminalBySource(
            $db,
            $entity,
            !empty($invoice->pos_source) ? (string) $invoice->pos_source : ''
        );
        return ($terminal && !empty($terminal->rowid) ? (int) $terminal->rowid : null);
    }

    function takeposApiInvoiceTerminalCode($invoice)
    {
        return (!empty($invoice->pos_source) ? (string) $invoice->pos_source : '');
    }

    function takeposApiInvoiceVatRateCode($line)
    {
        $code = $line->tva_tx;
        if (!empty($line->vat_src_code)) {
            $code .= ' (' . $line->vat_src_code . ')';
        }
        return $code;
    }

    function takeposApiInvoiceStatusWhere($db, $status)
    {
        $status = strtolower(trim((string) $status));
        if ($status === '' || $status === 'all')  { return ''; }
        if ($status === 'draft')                  { return ' AND fk_statut = 0'; }
        if ($status === 'validated')              { return ' AND fk_statut > 0'; }
        if ($status === 'paid')                   { return ' AND paye = 1'; }
        throw new TakeposApiException('INVALID_PARAMETER', 'Invalid status filter.', 422);
    }

    // ── Invoice line items ────────────────────────────────────────────────────

    function takeposApiInvoiceItems($invoice)
    {
        $items = array();
        if (empty($invoice->lines) || !is_array($invoice->lines)) {
            return $items;
        }
        foreach ($invoice->lines as $line) {
            $items[] = array(
                'id'               => (int)   $line->id,
                'product_id'       => (!empty($line->fk_product) ? (int) $line->fk_product : null),
                'label'            => (!empty($line->product_label) ? (string) $line->product_label : (string) $line->desc),
                'description'      => (string) $line->desc,
                'qty'              => (float)  price2num($line->qty,           'MS'),
                'price'            => (float)  price2num($line->subprice,      'MT'),
                'discount_percent' => (float)  price2num($line->remise_percent,'MT'),
                'total_ht'         => (float)  price2num($line->total_ht,      'MT'),
                'total_tva'        => (float)  price2num($line->total_tva,     'MT'),
                'total_ttc'        => (float)  price2num($line->total_ttc,     'MT'),
            );
        }
        return $items;
    }

    // ── Invoice snapshot ──────────────────────────────────────────────────────

    function takeposApiInvoiceSnapshot($db, $entity, $invoice, $withItems = true)
    {
        $data = array(
            'id'             => (int)    $invoice->id,
            'ref'            => (string) $invoice->ref,
            'terminal_id'    => takeposApiInvoiceTerminalId($db, $entity, $invoice),
            'thirdparty_id'  => (!empty($invoice->socid) ? (int) $invoice->socid : null),
            'status'         => takeposApiInvoiceStatusLabel($invoice),
            'payment_status' => takeposApiInvoicePaymentStatus($invoice),
            'currency'       => takeposApiInvoiceCurrency($invoice),
            'items_count'    => (!empty($invoice->lines) && is_array($invoice->lines) ? count($invoice->lines) : 0),
            'total_ht'       => (float)  price2num($invoice->total_ht,  'MT'),
            'total_tva'      => (float)  price2num($invoice->total_tva, 'MT'),
            'total_ttc'      => (float)  price2num($invoice->total_ttc, 'MT'),
            'date_creation'  => takeposApiDateValue(
                !empty($invoice->date_creation) ? $invoice->date_creation
                    : (!empty($invoice->datec) ? $invoice->datec : null)
            ),
        );

        // Payment settlement (overrides basic payment_status above)
        if (class_exists('TakeposApiPaymentService')) {
            $settlement            = TakeposApiPaymentService::invoiceSettlement($db, $entity, $invoice);
            $data['payment_status']    = $settlement['payment_status'];
            $data['paid_amount']       = (float) $settlement['paid_amount'];
            $data['remaining_amount']  = (float) $settlement['remaining_amount'];
        }

        // Fallback if service not available
        if (!isset($data['paid_amount'])) {
            $data['paid_amount']      = max(0.0, (float) $data['total_ttc'] - (float) price2num($invoice->getRemainToPay(), 'MT'));
        }
        if (!isset($data['remaining_amount'])) {
            $data['remaining_amount'] = max(0.0, (float) price2num($invoice->getRemainToPay(), 'MT'));
        }

        if ($withItems) {
            $data['items'] = takeposApiInvoiceItems($invoice);
        }

        return $data;
    }

    // ── Fetch helpers ─────────────────────────────────────────────────────────

    function takeposApiFetchInvoice($db, $invoiceId)
    {
        $invoice = new Facture($db);
        if ($invoice->fetch((int) $invoiceId) <= 0 || empty($invoice->id)) {
            return null;
        }
        if (method_exists($invoice, 'fetch_lines')) {
            $invoice->fetch_lines();
        }
        return $invoice;
    }

    function takeposApiRequireTakeposInvoice($db, $entity, $invoiceId)
    {
        $invoice = takeposApiFetchInvoice($db, $invoiceId);
        if (!$invoice) {
            throw new TakeposApiException('NOT_FOUND', 'Cart not found.', 404);
        }
        if ((int) $invoice->entity !== (int) $entity) {
            throw new TakeposApiException('NOT_FOUND', 'Cart not found.', 404);
        }
        if ((string) $invoice->module_source !== 'takepos') {
            throw new TakeposApiException('NOT_FOUND', 'Cart not found.', 404);
        }
        return $invoice;
    }

    function takeposApiAssertDraftInvoice($invoice)
    {
        if (takeposApiInvoiceStatusLabel($invoice) !== 'draft') {
            throw new TakeposApiException('CONFLICT', 'Cart is not editable (already validated or paid).', 409);
        }
    }

    function takeposApiInvoiceLineContext($db, $entity, $lineId)
    {
        $sql   = 'SELECT fk_facture FROM ' . MAIN_DB_PREFIX . 'facturedet'
               . ' WHERE rowid = ' . ((int) $lineId)
               . ' LIMIT 1';
        $resql = $db->query($sql);
        if (!$resql) {
            return array(null, null);
        }
        $obj = $db->fetch_object($resql);
        if (!$obj) {
            return array(null, null);
        }
        $invoice = takeposApiRequireTakeposInvoice($db, $entity, (int) $obj->fk_facture);
        foreach ($invoice->lines as $line) {
            if ((int) $line->id === (int) $lineId) {
                return array($invoice, $line);
            }
        }
        return array($invoice, null);
    }

    // ── Cart CRUD ─────────────────────────────────────────────────────────────

    function takeposApiCreateCartInvoice($db, $entity, $terminal, $thirdpartyId)
    {
        $invoice               = new Facture($db);
        $invoice->socid        = (int)    $thirdpartyId;
        $invoice->date         = dol_now();
        $invoice->module_source = 'takepos';
        $invoice->pos_source   = (string) $terminal->terminal_code;
        $invoice->entity       = (int)    $entity;

        $actor = takeposApiActorUser($entity);
        $db->begin();
        $result = $invoice->create($actor);
        if ($result <= 0) {
            $db->rollback();
            throw new TakeposApiException(
                'INTERNAL_ERROR',
                !empty($invoice->error) ? $invoice->error : 'Failed to create cart.',
                500
            );
        }
        $db->commit();
        return takeposApiRequireTakeposInvoice($db, $entity, (int) $result);
    }

    function takeposApiDeleteCartInvoice($db, $entity, $invoice)
    {
        takeposApiAssertDraftInvoice($invoice);
        $actor  = takeposApiActorUser($entity);
        $result = $invoice->delete($actor);
        if ($result <= 0) {
            throw new TakeposApiException(
                'INTERNAL_ERROR',
                !empty($invoice->error) ? $invoice->error : 'Failed to cancel cart.',
                500
            );
        }
        return true;
    }

    // ── Stub: replaced by direct query in invoices.php / carts.php ───────────
    function takeposApiListTakeposInvoices($db, $entity, $filters = array(), $limit = 50, $offset = 0)
    {
        return array(
            'total'  => 0,
            'rows'   => array(),
            'limit'  => max(1, min(200, (int) $limit)),
            'offset' => max(0, (int) $offset),
        );
    }
}
