<?php  
require_once __DIR__ . '/bootstrap.php';  
require_once __DIR__ . '/_invoice_common.php';  
require_once __DIR__ . '/_held_common.php';  
takeposApiRequireMethod(array('GET'));  
$auth = takeposApiAuth($db, 'read', 'takepos.api_layer');  
$entity = (int) $auth['entity'];  
$cartId = GETPOSTINT('cart_id'); if ($cartId <= 0) throw new TakeposApiException('INVALID_PARAMETER', 'cart_id is required', 422);  
$invoice = takeposApiRequireTakeposInvoice($db, $entity, $cartId); $terminal = takeposApiTerminalBySource($db, $entity, takeposApiInvoiceTerminalCode($invoice)); if ($terminal) { try { $terminal = TakeposApiCheckoutService::assertTerminalUsable($db, $entity, $terminal, 'INVALID_CART_STATE', 409); } catch (TakeposApiException $e) { $terminal = null; } } $snapshot = takeposApiInvoiceSnapshot($db, $entity, $invoice, false);  
$warnings = array(); $requirements = array('open_shift' => true, 'terminal' => ($terminal && !empty($terminal->active)), 'customer' => (!empty($invoice->socid)), 'stock' => true);  
if (!$requirements['terminal']) $warnings[] = array('code' => 'TERMINAL_INVALID', 'message' => 'Terminal is missing, inactive, or not allowed.'); if ((int) $snapshot['items_count'] <= 0) $warnings[] = array('code' => 'CART_EMPTY', 'message' => 'Cart has no items.'); if (!$requirements['customer']) $warnings[] = array('code' => 'CUSTOMER_MISSING', 'message' => 'Cart customer is missing.');  
$shiftFeature = TakeposUserAccess::featureEnabledForEntityStrict($db, $entity, 'takepos.shift_management'); if ($shiftFeature && TakeposShiftService::requireOpenShiftForPayments()) { $requirements['open_shift'] = ($terminal ? (takeposApiHeldActiveShiftId($db, $entity, (int) $terminal->rowid) > 0) : false); if (!$requirements['open_shift']) $warnings[] = array('code' => 'SHIFT_NOT_OPEN', 'message' => 'No open shift found for this terminal.'); }  
$stockIssues = ($terminal ? TakeposApiCheckoutService::getInvoiceStockIssues($db, $entity, $invoice, $terminal) : array()); if (!empty($stockIssues)) { $requirements['stock'] = false; $warnings[] = array('code' => 'INSUFFICIENT_STOCK', 'message' => 'Insufficient stock', 'details' => $stockIssues[0]); }  
$ready = ($requirements['terminal'] && $requirements['customer'] && $requirements['open_shift'] && $requirements['stock'] && ((int) $snapshot['items_count'] > 0));  
takeposApiSuccess(array('cart_id' => (int) $snapshot['id'], 'ready' => $ready, 'currency' => $snapshot['currency'], 'items_count' => (int) $snapshot['items_count'], 'total_ht' => (float) $snapshot['total_ht'], 'total_tva' => (float) $snapshot['total_tva'], 'total_ttc' => (float) $snapshot['total_ttc'], 'warnings' => $warnings, 'requirements' => $requirements), array('entity' => $entity));  
