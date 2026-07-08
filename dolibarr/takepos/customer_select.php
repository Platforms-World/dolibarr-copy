<?php
/**
 * Simplified POS customer selector.
 */
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');
if (!defined('NOREQUIREAJAX')) define('NOREQUIREAJAX', '1');
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');

require '../main.inc.php';
require_once __DIR__ . '/lib/takepos_lang.php';
require_once __DIR__ . '/class/TakeposCustomerService.class.php';
require_once __DIR__ . '/class/TakeposUserAccess.class.php';

takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
$langs->loadLangs(array('main', 'companies', 'customers', 'takeposcustom@takepos'));

if (empty($user->id) || (empty($user->admin) && empty($user->rights->takepos->run))) {
    accessforbidden($langs->trans('TakeposCustomerSelectAccessDenied'));
}

$canReadCustomer = (!empty($user->admin) || $user->hasRight('societe', 'lire') || TakeposUserAccess::userHasPermission($db, $user, 'takepos.customer.view'));
if (!$canReadCustomer) {
    accessforbidden($langs->trans('TakeposCustomerSelectAccessDenied'));
}

$entity = !empty($user->entity) ? (int) $user->entity : 1;
$query = trim((string) GETPOST('q', 'none'));
$place = GETPOST('place', 'aZ09');
$rows = TakeposCustomerService::searchCustomers($db, $entity, $query, 50);

?><!DOCTYPE html>
<html lang="<?php echo dol_escape_htmltag((string) $langs->defaultlang); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo dol_escape_htmltag($langs->trans('TakeposCustomerSelectTitle')); ?></title>
<style>
body{margin:0;background:#f5f7fb;color:#172033;font-family:Arial,Helvetica,sans-serif}
.takepos-customer-select{padding:18px}
.card{background:#fff;border:1px solid #dbe3ef;border-radius:16px;box-shadow:0 6px 18px rgba(15,23,42,.08);padding:16px;margin-bottom:14px}
h2{margin:0 0 8px;font-size:22px}.muted{color:#667085;font-size:14px}
form{display:flex;gap:10px;flex-wrap:wrap;align-items:center}input[type=text]{min-width:260px;flex:1;padding:11px 13px;border:1px solid #cbd5e1;border-radius:10px;font-size:15px}.button,button{border:0;border-radius:10px;background:#2563eb;color:#fff;font-weight:700;padding:10px 14px;cursor:pointer;text-decoration:none;display:inline-block}.button-secondary{background:#475569}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:14px;overflow:hidden}th,td{padding:10px 12px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:14px}th{background:#f8fafc;color:#334155}.empty{padding:22px;text-align:center;color:#64748b}.msg{min-height:20px;margin-top:10px;font-weight:700}.ok{color:#047857}.bad{color:#b91c1c}@media(max-width:720px){table,thead,tbody,tr,td,th{display:block}thead{display:none}td{border-bottom:0;padding:7px 10px}tr{border-bottom:1px solid #e5e7eb;padding:8px 0}.button{width:100%;text-align:center;box-sizing:border-box}}
</style>
</head>
<body>
<div class="takepos-customer-select">
    <div class="card">
        <h2><?php echo dol_escape_htmltag($langs->trans('TakeposCustomerSelectTitle')); ?></h2>
        <div class="muted"><?php echo dol_escape_htmltag($langs->trans('TakeposCustomerSelectIntro')); ?></div>
        <form method="get" action="customer_select.php" style="margin-top:14px">
            <input type="hidden" name="place" value="<?php echo dol_escape_htmltag($place); ?>">
            <input type="hidden" name="langs" value="<?php echo dol_escape_htmltag((string) $langs->defaultlang); ?>">
            <input type="text" name="q" value="<?php echo dol_escape_htmltag($query); ?>" placeholder="<?php echo dol_escape_htmltag($langs->trans('Search')); ?>">
            <button type="submit"><?php echo dol_escape_htmltag($langs->trans('Search')); ?></button>
            <a class="button button-secondary" target="_blank" rel="noopener" href="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/societe/list.php?type=c'); ?>"><?php echo dol_escape_htmltag($langs->trans('Companies')); ?></a>
        </form>
        <div id="takepos_customer_select_msg" class="msg"></div>
    </div>

    <div class="card" style="padding:0;overflow:hidden">
        <?php if (empty($rows)) { ?>
            <div class="empty"><?php echo dol_escape_htmltag($langs->trans('TakeposCustomerSelectNoResults')); ?></div>
        <?php } else { ?>
            <table>
                <thead>
                    <tr>
                        <th><?php echo dol_escape_htmltag($langs->trans('Code')); ?></th>
                        <th><?php echo dol_escape_htmltag($langs->trans('Name')); ?></th>
                        <th><?php echo dol_escape_htmltag($langs->trans('Email')); ?></th>
                        <th><?php echo dol_escape_htmltag($langs->trans('Phone')); ?></th>
                        <th><?php echo dol_escape_htmltag($langs->trans('Town')); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row) { ?>
                    <tr>
                        <td><?php echo dol_escape_htmltag($row['code']); ?></td>
                        <td><?php echo dol_escape_htmltag($row['name']); ?></td>
                        <td><?php echo dol_escape_htmltag($row['email']); ?></td>
                        <td><?php echo dol_escape_htmltag($row['phone']); ?></td>
                        <td><?php echo dol_escape_htmltag($row['town']); ?></td>
                        <td><button type="button" class="button" onclick="takeposChooseCustomer(<?php echo (int) $row['id']; ?>, <?php echo json_encode($row['name'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>)"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonSelect')); ?></button></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        <?php } ?>
    </div>
</div>
<script>
var takeposCustomerSelectI18n = <?php echo json_encode(array(
    'selected' => $langs->trans('TakeposCustomerSelectSelected'),
    'failed' => $langs->trans('TakeposLoyaltyUnableLinkCustomerToSale')
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
function takeposSetMessage(message, good) {
    var node = document.getElementById('takepos_customer_select_msg');
    if (!node) return;
    node.className = 'msg ' + (good ? 'ok' : 'bad');
    node.textContent = message || '';
}
function takeposChooseCustomer(customerId, customerName) {
    try {
        if (window.parent && typeof window.parent.ChangeThirdparty === 'function') {
            window.parent.ChangeThirdparty(customerId, customerName || '');
            takeposSetMessage(String(takeposCustomerSelectI18n.selected || '').replace('%s', customerName || ('#' + customerId)), true);
            setTimeout(function () {
                try {
                    if (window.parent.jQuery && window.parent.jQuery.colorbox) {
                        window.parent.jQuery.colorbox.close();
                    }
                } catch (ignore) {}
            }, 350);
            return false;
        }
    } catch (e) {
        takeposSetMessage(takeposCustomerSelectI18n.failed || '', false);
        return false;
    }
    takeposSetMessage(String(takeposCustomerSelectI18n.selected || '').replace('%s', customerName || ('#' + customerId)), true);
    return false;
}
</script>
</body>
</html>
