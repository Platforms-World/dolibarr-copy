<?php
if (!defined('TAKEPOS_API_V1_HELD_COMMON_INCLUDED')) {
    define('TAKEPOS_API_V1_HELD_COMMON_INCLUDED', 1);

    function takeposApiHeldTable()
    {
        return MAIN_DB_PREFIX . 'takepos_held_sale';
    }

    function takeposApiHeldRequireTable($db)
    {
        $probe = $db->query('SELECT rowid FROM ' . takeposApiHeldTable() . ' WHERE 1=0');
        if ($probe === false) {
            takeposApiError('INTERNAL_ERROR', 'Held sales table is missing. Run install SQL first.', 500);
        }
    }

    function takeposApiHeldActiveShiftId($db, $entity, $terminalId)
    {
        $shift = TakeposShiftService::getActiveShiftForTerminal($db, $entity, (int) $terminalId);
        return ($shift && !empty($shift->rowid) ? (int) $shift->rowid : 0);
    }

    function takeposApiHeldCleanupInvoice($db, $entity, $invoiceId)
    {
        $sql = 'UPDATE ' . takeposApiHeldTable()
            . " SET status = 0, date_update = '" . $db->idate(dol_now()) . "'"
            . ' WHERE entity = '     . ((int) $entity)
            . ' AND fk_invoice = '   . ((int) $invoiceId)
            . ' AND status = 1';
        $db->query($sql);
    }

    /**
     * Fetch lines count + total (ttc) for the invoice/cart linked to a held
     * sale row. Returns array('lines_count' => int, 'total' => float).
     * Never throws - defaults to 0/0.0 if the invoice or lines can't be read,
     * so a broken lookup never breaks the held sales list.
     *
     * @param DoliDB $db
     * @param int    $invoiceId
     * @return array
     */
    function takeposApiHeldInvoiceTotals($db, $invoiceId)
    {
        $result = array('lines_count' => 0, 'total' => 0.0);

        $invoiceId = (int) $invoiceId;
        if ($invoiceId <= 0) {
            return $result;
        }

        // Total from the invoice header itself (total_ttc already reflects
        // all lines, discounts and taxes - no need to re-sum manually).
        $sqlTotal = 'SELECT total_ttc FROM ' . MAIN_DB_PREFIX . 'facture'
            . ' WHERE rowid = ' . $invoiceId
            . ' LIMIT 1';
        $resTotal = $db->query($sqlTotal);
        if ($resTotal && ($objTotal = $db->fetch_object($resTotal))) {
            $result['total'] = (float) $objTotal->total_ttc;
        }

        // Line count from facturedet.
        $sqlLines = 'SELECT COUNT(rowid) AS nb FROM ' . MAIN_DB_PREFIX . 'facturedet'
            . ' WHERE fk_facture = ' . $invoiceId;
        $resLines = $db->query($sqlLines);
        if ($resLines && ($objLines = $db->fetch_object($resLines))) {
            $result['lines_count'] = (int) $objLines->nb;
        }

        return $result;
    }

    /**
     * @param DoliDB $db  Required to look up lines_count/total for the cart.
     *                    Pass null to skip the lookup (fields default to 0).
     */
    function takeposApiHeldPayload($row, $db = null)
    {
        $payload = array(
            'id'          => (int) $row->rowid,
            'cart_id'     => (int) $row->fk_invoice,
            'terminal_id' => (!empty($row->fk_terminal) ? (int) $row->fk_terminal : null),
            'shift_id'    => (!empty($row->fk_shift)    ? (int) $row->fk_shift    : null),
            'user_id'     => (!empty($row->fk_user)     ? (int) $row->fk_user     : null),
            'note'        => (string) $row->hold_label,
            'created_at'  => (!empty($row->date_hold)   ? (string) $row->date_hold : null),
            'lines_count' => 0,
            'total'       => 0.0,
        );

        if ($db !== null) {
            $totals = takeposApiHeldInvoiceTotals($db, (int) $row->fk_invoice);
            $payload['lines_count'] = $totals['lines_count'];
            $payload['total']       = $totals['total'];
        }

        return $payload;
    }
}