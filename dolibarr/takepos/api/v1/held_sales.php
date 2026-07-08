<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_request.php';
require_once __DIR__ . '/_invoice_common.php';
require_once __DIR__ . '/_held_common.php';

$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET');
if (!in_array($method, array('GET', 'POST'), true)) {
    takeposApiError('METHOD_NOT_ALLOWED', 'Method not allowed.', 405, null, array(), array('Allow: GET, POST'));
}

$auth   = takeposApiAuth($db, ($method === 'GET' ? 'read' : 'write'), 'takepos.api_layer');
$entity = (int) $auth['entity'];

takeposApiHeldRequireTable($db);

// ── POST ──────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $body   = takeposApiRequestBody();
    $action = strtolower(trim((string) GETPOST('action', 'alpha')));

    // ── Resume held sale ──────────────────────────────────────────────────────
    if ($action === 'resume') {
        $heldId = (int) takeposApiRequestRequireField($body, 'held_sale_id');

        $sql   = 'SELECT rowid, fk_invoice, fk_terminal, fk_shift, fk_user, hold_label, date_hold'
            . ' FROM ' . takeposApiHeldTable()
            . ' WHERE entity = ' . ((int) $entity)
            . ' AND status = 1'
            . ' AND rowid = ' . $heldId
            . ' LIMIT 1';
        $resql = $db->query($sql);
        $row   = ($resql ? $db->fetch_object($resql) : null);

        if (!$row) {
            throw new TakeposApiException('NOT_FOUND', 'Held sale not found.', 404);
        }

        $activeShiftId = takeposApiHeldActiveShiftId($db, $entity, (int) $row->fk_terminal);
        if ($activeShiftId <= 0) {
            throw new TakeposApiException('SHIFT_NOT_OPEN', 'No open shift found for this terminal.', 409);
        }
        if (!empty($row->fk_shift) && (int) $row->fk_shift !== (int) $activeShiftId) {
            throw new TakeposApiException('NOT_FOUND', 'Held sale not found for the current shift.', 404);
        }

        $updateSql = 'UPDATE ' . takeposApiHeldTable()
            . ' SET status = 0, date_update = ' . chr(39) . $db->idate(dol_now()) . chr(39)
            . ' WHERE entity = ' . ((int) $entity)
            . ' AND rowid = ' . $heldId;
        if (!$db->query($updateSql)) {
            throw new TakeposApiException('INTERNAL_ERROR', 'Failed to resume held sale.', 500);
        }

        $invoice = takeposApiRequireTakeposInvoice($db, $entity, (int) $row->fk_invoice);
        takeposApiAssertDraftInvoice($invoice);

        // Restore ref from HELD- back to PROV- so POS can use it normally
        if (strpos($invoice->ref, '(HELD-POS') !== false) {
            $restoreRef = '(PROV-POS' . ((int) $row->fk_terminal) . '-0)';
            $db->query('UPDATE ' . MAIN_DB_PREFIX . 'facture'
                . " SET ref = '" . $db->escape($restoreRef) . "'"
                . ' WHERE rowid = ' . ((int) $row->fk_invoice)
                . ' AND entity IN (' . getEntity('invoice') . ')');
        }

        takeposApiSuccess(takeposApiInvoiceSnapshot($db, $entity, $invoice, true), array('entity' => $entity));
    }

    // ── Hold current cart ─────────────────────────────────────────────────────
    $cartId = (int) takeposApiRequestRequireField($body, 'cart_id');
    $note   = (!empty($body['note']) ? trim((string) $body['note']) : '');

    $invoice    = takeposApiRequireTakeposInvoice($db, $entity, $cartId);
    takeposApiAssertDraftInvoice($invoice);

    $terminalId = takeposApiInvoiceTerminalId($db, $entity, $invoice);
    if ($terminalId <= 0) {
        throw new TakeposApiException('INVALID_PARAMETER', 'Cart terminal is invalid.', 422);
    }

    $shiftId = takeposApiHeldActiveShiftId($db, $entity, $terminalId);
    if ($shiftId <= 0) {
        throw new TakeposApiException('SHIFT_NOT_OPEN', 'No open shift found for this terminal.', 409);
    }

    // Check for duplicate hold
    $dupSql = 'SELECT rowid FROM ' . takeposApiHeldTable()
        . ' WHERE entity = ' . ((int) $entity)
        . ' AND status = 1'
        . ' AND fk_invoice = ' . $cartId
        . ' LIMIT 1';
    $dupRes = $db->query($dupSql);
    if ($dupRes && $db->fetch_object($dupRes)) {
        throw new TakeposApiException('CONFLICT', 'Cart is already held.', 409);
    }

    $userId    = (!empty($auth['user']->id) ? (int) $auth['user']->id : 0);
    $noteValue = ($note !== '' ? chr(39) . $db->escape($note) . chr(39) : 'NULL');

    $insertSql = 'INSERT INTO ' . takeposApiHeldTable()
        . ' (entity, fk_invoice, fk_terminal, fk_user, fk_shift, hold_label, date_hold, date_update, status)'
        . ' VALUES ('
        . ((int) $entity)  . ', '
        . $cartId          . ', '
        . ((int) $terminalId) . ', '
        . $userId          . ', '
        . ((int) $shiftId) . ', '
        . $noteValue       . ', '
        . chr(39) . $db->idate(dol_now()) . chr(39) . ', '
        . chr(39) . $db->idate(dol_now()) . chr(39) . ', '
        . '1)';

    if (!$db->query($insertSql)) {
        throw new TakeposApiException('INTERNAL_ERROR', 'Failed to hold sale.', 500);
    }

    $heldId   = (int) $db->last_insert_id(takeposApiHeldTable());

    // Rename invoice ref to HELD-POS{term}-{holdid} so the PROV slot is freed
    $terminalId = takeposApiInvoiceTerminalId($db, $entity, $invoice);
    $newHeldRef = '(HELD-POS' . ((int) $terminalId) . '-' . ((int) $heldId) . ')';
    $db->query('UPDATE ' . MAIN_DB_PREFIX . 'facture'
        . " SET ref = '" . $db->escape($newHeldRef) . "'"
        . ' WHERE rowid = ' . $cartId
        . ' AND entity IN (' . getEntity('invoice') . ')');

    $fetchSql = 'SELECT rowid, fk_invoice, fk_terminal, fk_shift, fk_user, hold_label, date_hold'
        . ' FROM ' . takeposApiHeldTable()
        . ' WHERE entity = ' . ((int) $entity)
        . ' AND rowid = ' . $heldId
        . ' LIMIT 1';
    $fetchRes = $db->query($fetchSql);
    $held     = ($fetchRes ? $db->fetch_object($fetchRes) : null);

    takeposApiSuccess(takeposApiHeldPayload($held, $db), array('entity' => $entity), 201);
}

// ── GET ───────────────────────────────────────────────────────────────────────
$terminalId = GETPOSTINT('terminal_id');
$shiftId    = GETPOSTINT('shift_id');
$userId     = GETPOSTINT('user_id');
$limit      = GETPOSTINT('limit');
if ($limit <= 0)   { $limit  = 50;  }
if ($limit > 200)  { $limit  = 200; }
$offset = GETPOSTINT('offset');
if ($offset < 0)   { $offset = 0;   }

$join  = ' LEFT JOIN ' . TakeposShiftService::tableShift() . ' s ON s.rowid = hs.fk_shift AND s.entity = hs.entity';
$where = ' WHERE hs.entity = ' . ((int) $entity)
    . ' AND hs.status = 1'
    . ' AND (hs.fk_shift IS NULL OR s.status IN ('
    .     chr(39) . TakeposShiftService::STATUS_OPEN           . chr(39) . ', '
    .     chr(39) . TakeposShiftService::STATUS_CLOSING_PENDING . chr(39)
    . '))';

if ($terminalId > 0) {
    takeposApiRequireTerminal($db, $entity, $terminalId, false);
    $activeShiftId = takeposApiHeldActiveShiftId($db, $entity, $terminalId);
    if ($activeShiftId <= 0) {
        takeposApiSuccess(array(), array('entity' => $entity, 'count' => 0, 'limit' => $limit, 'offset' => $offset));
    }
    if ($shiftId > 0 && $shiftId !== $activeShiftId) {
        takeposApiSuccess(array(), array('entity' => $entity, 'count' => 0, 'limit' => $limit, 'offset' => $offset));
    }
    $where .= ' AND hs.fk_terminal = ' . ((int) $terminalId)
        . ' AND hs.fk_shift = '    . ((int) $activeShiftId);
} elseif ($shiftId > 0) {
    $where .= ' AND hs.fk_shift = ' . ((int) $shiftId);
}

if ($userId > 0) {
    $where .= ' AND hs.fk_user = ' . ((int) $userId);
}

$countSql = 'SELECT COUNT(hs.rowid) AS nb FROM ' . takeposApiHeldTable() . ' hs' . $join . $where;
$countRes = $db->query($countSql);
$total    = 0;
if ($countRes && ($countObj = $db->fetch_object($countRes))) {
    $total = (int) $countObj->nb;
}

$sql = 'SELECT hs.rowid, hs.fk_invoice, hs.fk_terminal, hs.fk_shift, hs.fk_user, hs.hold_label, hs.date_hold'
    . ' FROM ' . takeposApiHeldTable() . ' hs'
    . $join
    . $where
    . ' ORDER BY hs.rowid DESC'
    . ' LIMIT ' . $offset . ', ' . $limit;

$rows  = array();
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $rows[] = takeposApiHeldPayload($obj, $db);
    }
}

takeposApiSuccess($rows, array(
    'entity' => $entity,
    'count'  => count($rows),
    'total'  => $total,
    'limit'  => $limit,
    'offset' => $offset,
));