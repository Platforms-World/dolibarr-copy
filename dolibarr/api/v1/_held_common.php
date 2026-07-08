<?php  
if (!defined('TAKEPOS_API_V1_HELD_COMMON_INCLUDED')) {  
define('TAKEPOS_API_V1_HELD_COMMON_INCLUDED', 1);  
function takeposApiHeldTable() { return MAIN_DB_PREFIX . 'takepos_held_sale'; }  
function takeposApiHeldRequireTable($db) { $probe = $db->query('SELECT rowid FROM ' . takeposApiHeldTable() . ' WHERE 1=0'); if ($probe === false) takeposApiError('INTERNAL_ERROR', 'Held sales table is missing.', 500); }  
function takeposApiHeldActiveShiftId($db, $entity, $terminalId) { $shift = TakeposShiftService::getActiveShiftForTerminal($db, $entity, (int) $terminalId); return ($shift && !empty($shift->rowid) ? (int) $shift->rowid : 0); }  
function takeposApiHeldCleanupInvoice($db, $entity, $invoiceId) { $sql = "UPDATE " . takeposApiHeldTable() . " SET status = 0, date_update = '" . $db->idate(dol_now()) . "' WHERE entity = " . ((int) $entity) . " AND fk_invoice = " . ((int) $invoiceId) . " AND status = 1"; $db->query($sql); }  
function takeposApiHeldPayload($row) { return array('id' => (int) $row->rowid, 'cart_id' => (int) $row->fk_invoice, 'terminal_id' => (!empty($row->fk_terminal) ? (int) $row->fk_terminal : null), 'shift_id' => (!empty($row->fk_shift) ? (int) $row->fk_shift : null), 'user_id' => (!empty($row->fk_user) ? (int) $row->fk_user : null), 'note' => (string) $row->hold_label, 'created_at' => (!empty($row->date_hold) ? (string) $row->date_hold : null)); }  
}  
