<?php
/**
 * Refund receipt / printable evidence.
 */
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');
if (!defined('NOREQUIREAJAX')) define('NOREQUIREAJAX', '1');

require '../main.inc.php';
require_once __DIR__ . '/lib/takepos_help.php';
require_once __DIR__ . '/class/TakeposAccess.class.php';
require_once __DIR__ . '/class/TakeposAudit.class.php';
require_once __DIR__ . '/class/TakeposRefundService.class.php';
require_once __DIR__ . '/class/TakeposUserAccess.class.php';

$langs->loadLangs(array('cashdesk', 'main', 'bills', 'takeposcustom@takepos'));
$terminal = isset($_SESSION['takeposterminal']) ? (string) $_SESSION['takeposterminal'] : '1';
$refundAccessDeniedMessage = $langs->trans('TakeposRefundReceiptAccessDenied');
$canAccessRefundDesk = (!empty($user->admin)
    || TakeposUserAccess::userHasAnyPermission($db, $user, array('takepos.refund.view', 'takepos.refund.partial', 'takepos.refund.full')));
if (!$canAccessRefundDesk) {
    accessforbidden($refundAccessDeniedMessage);
}
TakeposAccess::requireFrontendAccess($db, $user, 'takepos.refunds', 'takepos.use', (int) $terminal, $refundAccessDeniedMessage, array('page' => 'refund_receipt.php'));

$entity = !empty($user->entity) ? (int) $user->entity : 1;
$refundId = GETPOSTINT('id');
$refund = TakeposRefundService::getRefundById($db, $entity, $refundId);
if (!$refund) {
    accessforbidden($langs->trans('TakeposRefundNotFound'));
}
$lines = TakeposRefundService::getRefundLines($db, $entity, $refundId);

TakeposAudit::logEvent($db, $user, 'refund_receipt_printed', TakeposAudit::SEVERITY_INFO, array('refund_id' => $refundId, 'refund_ref' => (string) $refund->refund_ref), 'Refund receipt printed', 'refund', $refundId, (float) $refund->total_amount);

top_htmlhead('', '', 1);
?>
<body>
<style>
.right { text-align: right; }
.center { text-align: center; }
.left { text-align: left; }
.centpercent { width: 100%; }
body { font-family: Arial, sans-serif; margin: 14px; }
</style>

<center><h2><?php echo dol_escape_htmltag($mysoc->name); ?></h2></center>
<p class="left">
<strong><?php echo dol_escape_htmltag($langs->trans('TakeposRefundPrintReceipt')); ?></strong><br>
<?php echo dol_escape_htmltag($langs->trans('TakeposExpenseRef')); ?>: <?php echo dol_escape_htmltag($refund->refund_ref); ?><br>
<?php echo dol_escape_htmltag($langs->trans('TakeposLoyaltyDate')); ?>: <?php echo dol_escape_htmltag($refund->date_creation); ?><br>
<?php echo dol_escape_htmltag($langs->trans('TakeposRefundOriginalInvoice')); ?>: <?php echo dol_escape_htmltag($refund->original_invoice_ref); ?><br>
<?php echo dol_escape_htmltag($langs->trans('TakeposShiftTerminal')); ?>: <?php echo dol_escape_htmltag((string) $refund->terminal_code); ?>
</p>

<table class="centpercent" style="border-top-style: double;">
<thead>
<tr>
    <th class="left"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonLabel')); ?></th>
    <th class="right"><?php echo dol_escape_htmltag($langs->trans('Qty')); ?></th>
    <th class="right"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundUnitPrice')); ?></th>
    <th class="right"><?php echo dol_escape_htmltag($langs->trans('TakeposLoyaltyTotal')); ?></th>
</tr>
</thead>
<tbody>
<?php foreach ($lines as $ln) { ?>
<tr>
    <td><?php echo dol_escape_htmltag((string) $ln->original_line_label); ?></td>
    <td class="right"><?php echo price($ln->qty_refunded, 0, '', 1, -1, -1, ''); ?></td>
    <td class="right"><?php echo price($ln->unit_price); ?></td>
    <td class="right"><?php echo price($ln->line_total); ?></td>
</tr>
<?php } ?>
</tbody>
</table>

<br>
<table class="centpercent">
<tr><td class="left"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundPaymentMethod')); ?></td><td class="right"><?php echo dol_escape_htmltag((string) $refund->payment_method); ?></td></tr>
<tr><td class="left"><?php echo dol_escape_htmltag($langs->trans('TakeposRefundReason')); ?></td><td class="right"><?php echo dol_escape_htmltag((string) $refund->reason_code); ?></td></tr>
<tr><td class="left"><strong><?php echo dol_escape_htmltag($langs->trans('TakeposRefundAmount')); ?></strong></td><td class="right"><strong><?php echo price($refund->total_amount); ?></strong></td></tr>
</table>

<script>
window.onload = function(){ window.print(); };
</script>
<?php echo takeposHelpRender($langs, __FILE__); ?>
</body>
<?php
$db->close();
