<?php  
require_once __DIR__ . '/bootstrap.php';  
 
function takeposApiShiftPayload($row) {  
return array('id' => (int) $row->rowid, 'terminal_id' => (int) $row->fk_terminal, 'terminal_label' => (!empty($row->terminal_label) ? (string) $row->terminal_label : null), 'user_id' => (int) $row->fk_cashier_user, 'user_name' => (!empty($row->cashier_login) ? (string) $row->cashier_login : null), 'store_id' => (!empty($row->fk_store) ? (int) $row->fk_store : null), 'store_label' => (!empty($row->store_label) ? (string) $row->store_label : null), 'status' => (string) $row->status, 'opened_at' => (!empty($row->date_open) ? (string) $row->date_open : null), 'closed_at' => (!empty($row->date_close) ? (string) $row->date_close : null), 'opening_amount' => (float) price2num($row->opening_float, 'MT'), 'closing_amount' => ($row->counted_cash !== null ? (float) price2num($row->counted_cash, 'MT') : null));  
}  
takeposApiRequireMethod(array('GET'));  
$auth = takeposApiAuth($db, 'read', 'takepos.api_layer');  
$entity = (int) $auth['entity'];  
$shiftId = GETPOSTINT('id');  
$filters = array('status' => GETPOST('status', 'aZ09'), 'store_id' => GETPOSTINT('store_id'), 'terminal_id' => GETPOSTINT('terminal_id'));  
$limit = GETPOSTINT('limit');  
if ($limit <= 0) $limit = 100;  
if ($limit > 500) $limit = 500;  
if ($shiftId > 0) { $row = TakeposShiftService::getShiftById($db, $entity, $shiftId); if (!$row) takeposApiError('NOT_FOUND', 'Shift not found.', 404); takeposApiAuditAccess($db, $auth, 'shifts.show', array('shift_id' => $shiftId)); takeposApiSuccess(takeposApiShiftPayload($row), array('entity' => $entity)); }  
$rows = array();  
foreach (TakeposShiftService::listShifts($db, $entity, $filters, $limit) as $row) $rows[] = takeposApiShiftPayload($row);  
takeposApiAuditAccess($db, $auth, 'shifts', array('filters' => $filters, 'count' => count($rows)));  
takeposApiSuccess($rows, array('entity' => $entity, 'count' => count($rows), 'limit' => $limit, 'offset' => 0)); 
