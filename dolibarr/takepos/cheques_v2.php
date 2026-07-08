<?php
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');
if (!defined('NOREQUIREAJAX')) define('NOREQUIREAJAX', '1');

require '../main.inc.php';
require_once __DIR__ . '/lib/takepos_lang.php';
require_once __DIR__ . '/lib/takepos_help.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/class/TakeposAccess.class.php';
require_once __DIR__ . '/class/TakeposChequeService.class.php';

$langs->loadLangs(array('main', 'suppliers', 'cashdesk', 'takeposcustom@takepos'));

$sessionTerminalToken = isset($_SESSION['takeposterminal']) ? (string) $_SESSION['takeposterminal'] : '1';
TakeposAccess::requireFrontendAccess(
    $db,
    $user,
    'takepos.cheques',
    'takepos.cheque.read',
    (int) $sessionTerminalToken,
    $langs->trans('TakeposChequeAccessDenied'),
    array('page' => 'cheques_v2.php')
);
if (!TakeposChequeService::canRead($db, $user)) {
    TakeposAccess::denyAccess($db, $user, $langs->trans('TakeposChequeReadPermissionRequired'), array('page' => 'cheques_v2.php'));
}
TakeposChequeService::ensureSchema($db);

$entity = !empty($user->entity) ? (int) $user->entity : 1;
$pageUrl = DOL_URL_ROOT . '/takepos/cheques_v2.php';
$purchasesUrl = DOL_URL_ROOT . '/takepos/purchases.php';

$messages = array();
$errors = array();
$chequeId = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$canCreate = TakeposChequeService::canCreate($db, $user);
$prefillPurchaseId = GETPOSTINT('prefill_purchase_id');
$prefillSupplierId = GETPOSTINT('prefill_supplier_id');
$prefillAmount = GETPOST('prefill_amount', 'alphanohtml');

if (!empty($_GET['result'])) {
    if (GETPOST('result', 'alpha') === 'saved') $messages[] = $langs->trans('TakeposChequeSavedSuccess');
    if (GETPOST('result', 'alpha') === 'updated') $messages[] = $langs->trans('TakeposChequeUpdatedSuccess');
}
if ($prefillPurchaseId > 0) $messages[] = $langs->trans('TakeposChequePrefillFromPurchase');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) GETPOST('token', 'alpha');
    $sessionToken = isset($_SESSION['newtoken']) ? (string) $_SESSION['newtoken'] : '';
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        $errors[] = $langs->trans('TakeposChequeInvalidCsrf');
    } elseif (!$canCreate) {
        $errors[] = $langs->trans('TakeposChequeCreatePermissionRequired');
    } else {
        $payload = array(
            'cheque_number' => GETPOST('cheque_number', 'alphanohtml'),
            'supplier_id' => GETPOSTINT('supplier_id'),
            'purchase_id' => GETPOSTINT('purchase_id'),
            'bank_name' => GETPOST('bank_name', 'alphanohtml'),
            'amount' => GETPOST('amount', 'alphanohtml'),
            'cheque_date' => GETPOST('cheque_date', 'alphanohtml'),
            'collection_date' => GETPOST('collection_date', 'alphanohtml'),
            'status' => GETPOST('status', 'alphanohtml'),
            'note_private' => GETPOST('note_private', 'restricthtml'),
        );
        try {
            if ($action === 'update' && $chequeId > 0) {
                TakeposChequeService::updateCheque($db, $user, $chequeId, $payload);
                header('Location: ' . $pageUrl . '?id=' . ((int) $chequeId) . '&result=updated');
                exit;
            }
            $newId = TakeposChequeService::createCheque($db, $user, $payload);
            header('Location: ' . $pageUrl . '?id=' . ((int) $newId) . '&result=saved');
            exit;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$filters = array(
    'status' => GETPOST('filter_status', 'alphanohtml'),
    'supplier_id' => GETPOSTINT('filter_supplier_id'),
    'search' => GETPOST('search', 'alphanohtml'),
    'date_from' => GETPOST('date_from', 'alphanohtml'),
    'date_to' => GETPOST('date_to', 'alphanohtml'),
    'due_window' => GETPOST('due_window', 'alphanohtml'),
    'purchase_id' => GETPOSTINT('filter_purchase_id'),
);
$current = ($chequeId > 0 ? TakeposChequeService::getChequeById($db, $entity, $chequeId) : null);
$rows = TakeposChequeService::listCheques($db, $entity, $filters, 500);
$summary = TakeposChequeService::summarize($rows);
$alerts = TakeposChequeService::buildAlerts($summary);
$suppliers = TakeposChequeService::listSuppliers($db, $entity);
$purchases = TakeposChequeService::listRecentPurchases($db, $entity, 120);
$prefillPurchase = ($prefillPurchaseId > 0 ? TakeposChequeService::getPurchaseById($db, $entity, $prefillPurchaseId) : null);

if ($prefillPurchase && !$current && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (empty($prefillSupplierId) && !empty($prefillPurchase->fk_supplier)) $prefillSupplierId = (int) $prefillPurchase->fk_supplier;
    if ($prefillAmount === '' && isset($prefillPurchase->total_ttc)) $prefillAmount = price($prefillPurchase->total_ttc, 0, '', 1, 0, 0, '', 0, 0);
}

if ($action === 'print' && $current) {
?><!doctype html><html lang="<?php echo dol_escape_htmltag($langs->defaultlang); ?>"><head><meta charset="utf-8"><title><?php echo dol_escape_htmltag($langs->trans('TakeposChequeTitle')); ?></title><style>
body{font-family:Arial,sans-serif;margin:24px;color:#1f2937}
h1{font-size:24px;margin:0 0 16px}h2{font-size:16px;margin:18px 0 8px}
.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:20px}
.card{border:1px solid #d0d5dd;border-radius:12px;padding:12px}
.label{font-size:12px;color:#667085;margin-bottom:4px}.value{font-size:15px;font-weight:700}
table{width:100%;border-collapse:collapse;margin-top:10px}th,td{border:1px solid #e4e7ec;padding:9px 10px;text-align:left}th{background:#f8fafc}
.meta{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;color:#667085;font-size:12px}.note{white-space:pre-wrap}
.badge{display:inline-block;padding:4px 10px;border-radius:999px;background:#eff8ff;color:#175cd3;font-size:12px;font-weight:700}.rtl{direction:rtl;text-align:right}
.print-footer{margin-top:18px;font-size:12px;color:#667085}
</style>
<link rel="stylesheet" href="<?php echo DOL_URL_ROOT; ?>/takepos/css/workspace_v2.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head><body class="kfv2-body">
<?php
$v2PageTitle = $langs->trans('TakeposChequesTitle');
$v2PageIcon  = 'fa-money-check';
$v2BackUrl   = DOL_URL_ROOT . '/takepos/pos_v2.php';
require __DIR__ . '/topbar_v2.php';
?>
<div class="meta"><div><?php echo dol_escape_htmltag($langs->trans('TakeposChequeTitle')); ?></div><div><?php echo dol_escape_htmltag(date('Y-m-d H:i')); ?></div></div>
<h1><?php echo dol_escape_htmltag($current->ref . ' - ' . $langs->trans('TakeposChequeNumber') . ': ' . $current->cheque_number); ?></h1>
<div class="grid">
    <div class="card"><div class="label"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeSupplier')); ?></div><div class="value"><?php echo dol_escape_htmltag((string) $current->supplier_name); ?></div></div>
    <div class="card"><div class="label"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeBank')); ?></div><div class="value"><?php echo dol_escape_htmltag((string) $current->bank_name); ?></div></div>
    <div class="card"><div class="label"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeAmount')); ?></div><div class="value"><?php echo dol_escape_htmltag(price($current->amount, 0, '', 1, 0, 0, '', 0, 0)); ?></div></div>
    <div class="card"><div class="label"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeStatus')); ?></div><div class="value"><span class="badge"><?php echo dol_escape_htmltag(TakeposChequeService::statusLabel((string) $current->status)); ?></span></div></div>
    <div class="card"><div class="label"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeDate')); ?></div><div class="value"><?php echo dol_escape_htmltag((string) $current->cheque_date); ?></div></div>
    <div class="card"><div class="label"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeCollectionDate')); ?></div><div class="value"><?php echo dol_escape_htmltag((string) $current->collection_date); ?></div></div>
</div>
<h2><?php echo dol_escape_htmltag($langs->trans('TakeposChequeRelatedPurchase')); ?></h2>
<table><tbody>
<tr><th><?php echo dol_escape_htmltag($langs->trans('TakeposChequeLinkedPurchase')); ?></th><td><?php echo dol_escape_htmltag((string) $current->purchase_ref); ?></td></tr>
<tr><th><?php echo dol_escape_htmltag($langs->trans('TakeposChequeDueState')); ?></th><td><?php echo dol_escape_htmltag((string) $current->due_state_label); ?></td></tr>
<tr><th><?php echo dol_escape_htmltag($langs->trans('TakeposChequeNote')); ?></th><td class="note"><?php echo dol_escape_htmltag((string) $current->note_private); ?></td></tr>
</tbody></table>
<div class="print-footer"><?php echo dol_escape_htmltag($langs->trans('TakeposChequePrintFooter')); ?></div>
<?php echo takeposHelpRender($langs, __FILE__); ?>
</body></html><?php
    exit;
}

if ($action === 'print_report') {
?><!doctype html><html lang="<?php echo dol_escape_htmltag($langs->defaultlang); ?>"><head><meta charset="utf-8"><title><?php echo dol_escape_htmltag($langs->trans('TakeposChequePrintReportTitle')); ?></title><style>
body{font-family:Arial,sans-serif;margin:24px;color:#1f2937}h1{font-size:24px;margin:0 0 8px}.muted{color:#667085;font-size:12px}.summary{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:16px 0}
.card{border:1px solid #d0d5dd;border-radius:12px;padding:12px}.label{font-size:12px;color:#667085}.value{font-size:20px;font-weight:700;margin-top:4px}
table{width:100%;border-collapse:collapse;margin-top:14px}th,td{border:1px solid #e4e7ec;padding:8px;text-align:left}th{background:#f8fafc;font-size:12px}
.badge{display:inline-block;padding:4px 9px;border-radius:999px;background:#eff8ff;color:#175cd3;font-size:11px;font-weight:700}.rtl{direction:rtl;text-align:right}
</style></head><body class="kfv2-body">
<?php
$v2PageTitle = $langs->trans('TakeposChequesTitle');
$v2PageIcon  = 'fa-money-check';
$v2BackUrl   = DOL_URL_ROOT . '/takepos/pos_v2.php';
require __DIR__ . '/topbar_v2.php';
?>
<h1><?php echo dol_escape_htmltag($langs->trans('TakeposChequePrintReportTitle')); ?></h1>
<div class="muted"><?php echo dol_escape_htmltag($langs->trans('TakeposChequePrintFilters')); ?>: <?php echo dol_escape_htmltag($langs->trans('TakeposChequeStatus')); ?> <?php echo dol_escape_htmltag($filters['status'] !== '' ? TakeposChequeService::statusLabel($filters['status']) : $langs->trans('TakeposChequeStatusAll')); ?> | <?php echo dol_escape_htmltag($langs->trans('TakeposChequeDueWindow')); ?> <?php echo dol_escape_htmltag(TakeposChequeService::dueWindowLabel((string) $filters['due_window'])); ?> | <?php echo dol_escape_htmltag($langs->trans('Date')); ?> <?php echo dol_escape_htmltag((string) $filters['date_from']); ?> - <?php echo dol_escape_htmltag((string) $filters['date_to']); ?></div>
<div class="summary">
    <div class="card"><div class="label"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeSummaryTotal')); ?></div><div class="value"><?php echo dol_escape_htmltag(price($summary['total'], 0, '', 1, 0, 0, '', 0, 0)); ?></div></div>
    <div class="card"><div class="label"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeSummaryOverdue')); ?></div><div class="value"><?php echo dol_escape_htmltag(price($summary['overdue'], 0, '', 1, 0, 0, '', 0, 0)); ?></div></div>
    <div class="card"><div class="label"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeSummaryDueToday')); ?></div><div class="value"><?php echo dol_escape_htmltag(price($summary['due_today'], 0, '', 1, 0, 0, '', 0, 0)); ?></div></div>
    <div class="card"><div class="label"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeSummaryUpcoming7')); ?></div><div class="value"><?php echo dol_escape_htmltag(price($summary['upcoming_7'], 0, '', 1, 0, 0, '', 0, 0)); ?></div></div>
</div>
<table>
<thead><tr><th><?php echo dol_escape_htmltag($langs->trans('TakeposChequeRef')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposChequeNumber')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposChequeSupplier')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposChequeBank')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposChequeAmount')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposChequeDate')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposChequeCollectionDate')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposChequeStatus')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposChequeDueState')); ?></th></tr></thead>
<tbody>
<?php if (empty($rows)) { ?><tr><td colspan="9"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeNoData')); ?></td></tr><?php } ?>
<?php foreach ($rows as $row) { ?><tr><td><?php echo dol_escape_htmltag($row->ref); ?></td><td><?php echo dol_escape_htmltag($row->cheque_number); ?></td><td><?php echo dol_escape_htmltag((string) $row->supplier_name); ?></td><td><?php echo dol_escape_htmltag((string) $row->bank_name); ?></td><td><?php echo dol_escape_htmltag(price($row->amount, 0, '', 1, 0, 0, '', 0, 0)); ?></td><td><?php echo dol_escape_htmltag((string) $row->cheque_date); ?></td><td><?php echo dol_escape_htmltag((string) $row->collection_date); ?></td><td><?php echo dol_escape_htmltag(TakeposChequeService::statusLabel((string) $row->status)); ?></td><td><span class="badge"><?php echo dol_escape_htmltag((string) $row->due_state_label); ?></span></td></tr><?php } ?>
</tbody></table>
</body></html><?php
    exit;
}

$formValues = array(
    'cheque_number' => (GETPOST('cheque_number', 'alpha') !== '' ? GETPOST('cheque_number', 'alpha') : ($current ? (string) $current->cheque_number : '')),
    'supplier_id' => (GETPOSTINT('supplier_id') > 0 ? GETPOSTINT('supplier_id') : ($current ? (int) $current->fk_supplier : ($prefillSupplierId > 0 ? $prefillSupplierId : 0))),
    'purchase_id' => (GETPOSTINT('purchase_id') > 0 ? GETPOSTINT('purchase_id') : ($current ? (int) $current->fk_purchase : ($prefillPurchaseId > 0 ? $prefillPurchaseId : 0))),
    'bank_name' => (GETPOST('bank_name', 'alpha') !== '' ? GETPOST('bank_name', 'alpha') : ($current ? (string) $current->bank_name : '')),
    'amount' => (GETPOST('amount', 'alpha') !== '' ? GETPOST('amount', 'alpha') : ($current ? price($current->amount, 0, '', 1, 0, 0, '', 0, 0) : $prefillAmount)),
    'cheque_date' => (GETPOST('cheque_date', 'alpha') !== '' ? GETPOST('cheque_date', 'alpha') : ($current ? (string) $current->cheque_date : date('Y-m-d'))),
    'collection_date' => (GETPOST('collection_date', 'alpha') !== '' ? GETPOST('collection_date', 'alpha') : ($current ? (string) $current->collection_date : date('Y-m-d'))),
    'status' => (GETPOST('status', 'alpha') !== '' ? GETPOST('status', 'alpha') : ($current ? (string) $current->status : TakeposChequeService::STATUS_PENDING)),
    'note_private' => (GETPOST('note_private', 'restricthtml') !== '' ? GETPOST('note_private', 'restricthtml') : ($current ? (string) $current->note_private : '')),
);
?><!doctype html>
<html lang="<?php echo dol_escape_htmltag($langs->defaultlang); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo dol_escape_htmltag($langs->trans('TakeposChequeTitle')); ?></title>
<style>
body.takepos-cheques-body{background:#f5f7fb;font-family:Arial,sans-serif;color:#1f2937;margin:0}
.takepos-cheques-page{max-width:1480px;margin:0 auto;padding:18px}
.takepos-cheques-header{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:16px}
.takepos-cheques-title{margin:0;font-size:26px;font-weight:700}
.takepos-cheques-subtitle{color:#667085;font-size:13px;margin-top:6px}
.takepos-actions{display:flex;gap:10px;flex-wrap:wrap}
.takepos-summary{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;margin-bottom:14px}
.takepos-summary-card{background:#fff;border:1px solid #dde3ee;border-radius:16px;padding:14px;box-shadow:0 4px 14px rgba(15,23,42,.05)}
.takepos-summary-card .k{font-size:12px;color:#667085}.takepos-summary-card .v{font-size:20px;font-weight:700;margin-top:6px}.takepos-summary-card .m{margin-top:6px;font-size:12px;color:#667085}
.takepos-alerts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:14px}
.takepos-alert{border-radius:14px;padding:14px 16px;color:#fff}.takepos-alert .x{font-size:13px;opacity:.92}.takepos-alert .v{font-size:24px;font-weight:700;margin-top:2px}.takepos-alert .m{font-size:12px;opacity:.92;margin-top:4px}
.takepos-alert-danger{background:linear-gradient(135deg,#c01048,#ef4444)}.takepos-alert-warning{background:linear-gradient(135deg,#f59e0b,#f97316)}.takepos-alert-info{background:linear-gradient(135deg,#2563eb,#06b6d4)}
.takepos-cheques-grid{display:grid;grid-template-columns:1.05fr 1.95fr;gap:16px}
.takepos-panel{background:#fff;border:1px solid #dde3ee;border-radius:18px;padding:16px;box-shadow:0 4px 14px rgba(15,23,42,.05)}
.takepos-panel h3{margin:0 0 14px;font-size:18px}
.takepos-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.takepos-form-grid .span-2{grid-column:span 2}
label{display:block;font-size:13px;font-weight:600;margin-bottom:6px}
.takepos-field,select,textarea,input[type=text],input[type=date],input[type=number]{width:100%;box-sizing:border-box;border:1px solid #cfd8e3;border-radius:10px;padding:10px 12px;background:#fff}
textarea{min-height:92px;resize:vertical}
.takepos-filters{display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr 1.2fr auto auto;gap:10px;margin-bottom:12px}
.takepos-table-wrap{overflow:auto;border:1px solid #eef2f7;border-radius:14px}
.takepos-table{width:100%;border-collapse:collapse;background:#fff}
.takepos-table th,.takepos-table td{padding:10px 12px;border-bottom:1px solid #eef2f7;vertical-align:top;text-align:start}
.takepos-table th{font-size:12px;background:#f8fafc;position:sticky;top:0;z-index:1}
.status-badge,.due-badge{display:inline-block;padding:5px 10px;border-radius:999px;font-size:12px;font-weight:700}
.status-pending{background:#fff4db;color:#8a5a00}.status-collected{background:#dff7e6;color:#0b6b2d}.status-bounced{background:#ffe3e3;color:#b42318}.status-cancelled{background:#eceff3;color:#475467}.status-partial{background:#e6f0ff;color:#175cd3}
.due-overdue{background:#fee4e2;color:#b42318}.due-today{background:#fef0c7;color:#b54708}.due-upcoming{background:#dbeafe;color:#1d4ed8}.due-future{background:#ecfdf3;color:#027a48}.due-closed{background:#f2f4f7;color:#475467}
.row-overdue td{background:#fff5f5}.row-today td{background:#fffaf0}
.ok,.error,.warning{margin:0 0 14px;padding:12px 14px;border-radius:12px}.ok{background:#ecfdf3;color:#027a48}.error{background:#fef3f2;color:#b42318}.warning{background:#fffaeb;color:#b54708}
.muted{color:#667085;font-size:12px}.inline-links a{margin-inline-end:10px}
.prefill-box{margin-bottom:14px;padding:12px 14px;border-radius:12px;background:#eff8ff;color:#175cd3}
@media (max-width:1200px){.takepos-cheques-grid{grid-template-columns:1fr}.takepos-summary{grid-template-columns:repeat(3,1fr)}.takepos-alerts{grid-template-columns:1fr}.takepos-filters{grid-template-columns:repeat(2,1fr)}}
@media (max-width:760px){.takepos-summary{grid-template-columns:repeat(2,1fr)}.takepos-form-grid{grid-template-columns:1fr}.takepos-form-grid .span-2{grid-column:span 1}.takepos-filters{grid-template-columns:1fr}}
</style>
</head>
<body class="kfv2-body">
<?php
$v2PageTitle = $langs->trans('TakeposChequesTitle');
$v2PageIcon  = 'fa-money-check';
$v2BackUrl   = DOL_URL_ROOT . '/takepos/pos_v2.php';
require __DIR__ . '/topbar_v2.php';
?>
<div class="takepos-cheques-page">
    <div class="takepos-cheques-header">
        <div>
            <h1 class="takepos-cheques-title"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeTitle')); ?></h1>
            <div class="takepos-cheques-subtitle"><?php echo dol_escape_htmltag($langs->trans('TakeposChequePageSubtitle')); ?></div>
        </div>
        <div class="takepos-actions">
            <a class="butActionRefused" href="<?php echo dol_escape_htmltag($purchasesUrl); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeOpenPurchases')); ?></a>
            <a class="butAction" href="<?php echo dol_escape_htmltag($pageUrl . '?action=print_report&filter_status=' . urlencode((string) $filters['status']) . '&filter_supplier_id=' . ((int) $filters['supplier_id']) . '&search=' . urlencode((string) $filters['search']) . '&date_from=' . urlencode((string) $filters['date_from']) . '&date_to=' . urlencode((string) $filters['date_to']) . '&due_window=' . urlencode((string) $filters['due_window'])); ?>" target="_blank"><?php echo dol_escape_htmltag($langs->trans('TakeposChequePrintReport')); ?></a>
            <a class="butAction" href="<?php echo dol_escape_htmltag($pageUrl); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeNew')); ?></a>
        </div>
    </div>

    <?php foreach ($messages as $message) { ?><div class="ok"><?php echo dol_escape_htmltag($message); ?></div><?php } ?>
    <?php foreach ($errors as $errorMessage) { ?><div class="error"><?php echo dol_escape_htmltag($errorMessage); ?></div><?php } ?>

    <?php if ($prefillPurchase) { ?>
        <div class="prefill-box">
            <strong><?php echo dol_escape_htmltag($langs->trans('TakeposChequePrefillBoxTitle')); ?></strong>
            - <?php echo dol_escape_htmltag($prefillPurchase->ref); ?>
            - <?php echo dol_escape_htmltag((string) $prefillPurchase->supplier_name); ?>
            - <?php echo dol_escape_htmltag(price($prefillPurchase->total_ttc, 0, '', 1, 0, 0, '', 0, 0)); ?>
        </div>
    <?php } ?>

    <div class="takepos-summary">
        <div class="takepos-summary-card"><div class="k"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeSummaryTotal')); ?></div><div class="v"><?php echo dol_escape_htmltag(price($summary['total'], 0, '', 1, 0, 0, '', 0, 0)); ?></div><div class="m"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeSummaryCount')); ?>: <?php echo (int) $summary['count']; ?></div></div>
        <div class="takepos-summary-card"><div class="k"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeSummaryPending')); ?></div><div class="v"><?php echo dol_escape_htmltag(price($summary['pending'], 0, '', 1, 0, 0, '', 0, 0)); ?></div><div class="m"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeStatusPending')); ?></div></div>
        <div class="takepos-summary-card"><div class="k"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeSummaryCollected')); ?></div><div class="v"><?php echo dol_escape_htmltag(price($summary['collected'], 0, '', 1, 0, 0, '', 0, 0)); ?></div><div class="m"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeStatusCollected')); ?></div></div>
        <div class="takepos-summary-card"><div class="k"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeSummaryBounced')); ?></div><div class="v"><?php echo dol_escape_htmltag(price($summary['bounced'], 0, '', 1, 0, 0, '', 0, 0)); ?></div><div class="m"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeStatusBounced')); ?></div></div>
        <div class="takepos-summary-card"><div class="k"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeSummaryOverdue')); ?></div><div class="v"><?php echo dol_escape_htmltag(price($summary['overdue'], 0, '', 1, 0, 0, '', 0, 0)); ?></div><div class="m"><?php echo (int) $summary['overdue_count']; ?> <?php echo dol_escape_htmltag($langs->trans('TakeposChequeItems')); ?></div></div>
        <div class="takepos-summary-card"><div class="k"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeSummaryDueToday')); ?></div><div class="v"><?php echo dol_escape_htmltag(price($summary['due_today'], 0, '', 1, 0, 0, '', 0, 0)); ?></div><div class="m"><?php echo (int) $summary['due_today_count']; ?> <?php echo dol_escape_htmltag($langs->trans('TakeposChequeItems')); ?></div></div>
    </div>

    <?php if (!empty($alerts)) { ?>
        <div class="takepos-alerts">
            <?php foreach ($alerts as $alert) { ?>
                <div class="takepos-alert takepos-alert-<?php echo dol_escape_htmltag($alert['type']); ?>">
                    <div class="x"><?php echo dol_escape_htmltag($alert['label']); ?></div>
                    <div class="v"><?php echo (int) $alert['count']; ?></div>
                    <div class="m"><?php echo dol_escape_htmltag(price($alert['amount'], 0, '', 1, 0, 0, '', 0, 0)); ?></div>
                </div>
            <?php } ?>
        </div>
    <?php } ?>

    <div class="takepos-cheques-grid">
        <section class="takepos-panel">
            <div class="kfv2-card-block-head"><h3 style="margin:0;font-size:14.5px"><?php echo dol_escape_htmltag($current ? $langs->trans('TakeposChequeEditRecord') . ': ' . $current->ref : $langs->trans('TakeposChequeCreateRecord')); ?></h3></div><div class="kfv2-card-block-body">
            <form method="post" action="<?php echo dol_escape_htmltag($pageUrl . ($chequeId > 0 ? '?id=' . ((int) $chequeId) : '')); ?>">
                <input type="kfv2-hidden" name="token" value="<?php echo dol_escape_htmltag(newToken()); ?>">
                <input type="kfv2-hidden" name="action" value="<?php echo dol_escape_htmltag($current ? 'update' : 'create'); ?>">
                <div class="takepos-form-grid">
                    <div><label for="cheque_number"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeNumber')); ?></label><input type="text" id="cheque_number" name="cheque_number" value="<?php echo dol_escape_htmltag($formValues['cheque_number']); ?>" placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposChequeNumberPlaceholder')); ?>" required></div>
                    <div><label for="bank_name"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeBank')); ?></label><input type="text" id="bank_name" name="bank_name" value="<?php echo dol_escape_htmltag($formValues['bank_name']); ?>" placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposChequeBankPlaceholder')); ?>"></div>
                    <div class="span-2"><label for="supplier_search"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeSupplierQuickSearch')); ?></label><input type="text" id="supplier_search" class="takepos-field" placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposChequeSupplierQuickSearchPlaceholder')); ?>"></div>
                    <div><label for="supplier_id"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeSupplierOptional')); ?></label><select id="supplier_id" name="supplier_id"><option value="0"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeSelectSupplier')); ?></option><?php foreach ($suppliers as $supplier) { $label = trim((string) $supplier->nom . (!empty($supplier->code_fournisseur) ? ' [' . $supplier->code_fournisseur . ']' : '')); $dataSearch = function_exists('mb_strtolower') ? mb_strtolower($label) : strtolower($label); ?><option value="<?php echo (int) $supplier->rowid; ?>" data-search="<?php echo dol_escape_htmltag($dataSearch); ?>"<?php echo ((int) $formValues['supplier_id'] === (int) $supplier->rowid ? ' selected' : ''); ?>><?php echo dol_escape_htmltag($label); ?></option><?php } ?></select></div>
                    <div><label for="purchase_id"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeRelatedPurchase')); ?></label><select id="purchase_id" name="purchase_id"><option value="0"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeSelectPurchase')); ?></option><?php foreach ($purchases as $purchase) { $label = trim((string) $purchase->ref . ' - ' . (!empty($purchase->supplier_name) ? $purchase->supplier_name : '') . ' - ' . price($purchase->total_ttc, 0, '', 1, 0, 0, '', 0, 0)); ?><option value="<?php echo (int) $purchase->rowid; ?>"<?php echo ((int) $formValues['purchase_id'] === (int) $purchase->rowid ? ' selected' : ''); ?>><?php echo dol_escape_htmltag($label); ?></option><?php } ?></select></div>
                    <div><label for="amount"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeAmount')); ?></label><input type="number" step="0.01" min="0" id="amount" name="amount" value="<?php echo dol_escape_htmltag($formValues['amount']); ?>" required></div>
                    <div><label for="status"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeStatus')); ?></label><select id="status" name="status"><?php foreach (TakeposChequeService::statuses() as $statusCode) { ?><option value="<?php echo dol_escape_htmltag($statusCode); ?>"<?php echo ($formValues['status'] === $statusCode ? ' selected' : ''); ?>><?php echo dol_escape_htmltag(TakeposChequeService::statusLabel($statusCode)); ?></option><?php } ?></select></div>
                    <div><label for="cheque_date"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeDate')); ?></label><input type="date" id="cheque_date" name="cheque_date" value="<?php echo dol_escape_htmltag($formValues['cheque_date']); ?>" required></div>
                    <div><label for="collection_date"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeCollectionDate')); ?></label><input type="date" id="collection_date" name="collection_date" value="<?php echo dol_escape_htmltag($formValues['collection_date']); ?>" required></div>
                    <div class="span-2"><label for="note_private"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeNote')); ?></label><textarea id="note_private" name="note_private" placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposChequeNotePlaceholder')); ?>"><?php echo dol_escape_htmltag($formValues['note_private']); ?></textarea></div>
                </div>
                <div class="takepos-actions" style="margin-top:14px">
                    <button class="butAction" type="submit"><?php echo dol_escape_htmltag($current ? $langs->trans('TakeposCommonUpdate') : $langs->trans('TakeposCommonSave')); ?></button>
                    <?php if ($current) { ?><a class="butAction" href="<?php echo dol_escape_htmltag($pageUrl . '?action=print&id=' . ((int) $current->rowid)); ?>" target="_blank"><?php echo dol_escape_htmltag($langs->trans('TakeposChequePrint')); ?></a><?php } ?>
                    <a class="butActionRefused" href="<?php echo dol_escape_htmltag($pageUrl); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeClearForm')); ?></a>
                </div>
            </form>
        </section>

        <section class="takepos-panel">
            <div class="kfv2-card-block-head"><h3 style="margin:0;font-size:14.5px"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeListTitle')); ?></h3></div><div class="kfv2-card-block-body">
            <form method="get" action="<?php echo dol_escape_htmltag($pageUrl); ?>" class="takepos-filters">
                <select name="filter_status"><option value=""><?php echo dol_escape_htmltag($langs->trans('TakeposChequeStatusAll')); ?></option><?php foreach (TakeposChequeService::statuses() as $statusCode) { ?><option value="<?php echo dol_escape_htmltag($statusCode); ?>"<?php echo ($filters['status'] === $statusCode ? ' selected' : ''); ?>><?php echo dol_escape_htmltag(TakeposChequeService::statusLabel($statusCode)); ?></option><?php } ?></select>
                <select name="filter_supplier_id"><option value="0"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeFilterSupplier')); ?></option><?php foreach ($suppliers as $supplier) { $label = trim((string) $supplier->nom . (!empty($supplier->code_fournisseur) ? ' [' . $supplier->code_fournisseur . ']' : '')); ?><option value="<?php echo (int) $supplier->rowid; ?>"<?php echo ((int) $filters['supplier_id'] === (int) $supplier->rowid ? ' selected' : ''); ?>><?php echo dol_escape_htmltag($label); ?></option><?php } ?></select>
                <input type="date" name="date_from" value="<?php echo dol_escape_htmltag((string) $filters['date_from']); ?>" title="<?php echo dol_escape_htmltag($langs->trans('TakeposChequeDateFrom')); ?>">
                <input type="date" name="date_to" value="<?php echo dol_escape_htmltag((string) $filters['date_to']); ?>" title="<?php echo dol_escape_htmltag($langs->trans('TakeposChequeDateTo')); ?>">
                <select name="due_window"><?php foreach (TakeposChequeService::dueWindows() as $window) { ?><option value="<?php echo dol_escape_htmltag($window); ?>"<?php echo ((string) $filters['due_window'] === (string) $window ? ' selected' : ''); ?>><?php echo dol_escape_htmltag(TakeposChequeService::dueWindowLabel($window)); ?></option><?php } ?></select>
                <input type="text" name="search" value="<?php echo dol_escape_htmltag((string) $filters['search']); ?>" placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposChequeSearchPlaceholder')); ?>">
                <button type="submit" class="butAction"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonSearch')); ?></button>
                <a href="<?php echo dol_escape_htmltag($pageUrl); ?>" class="butActionRefused"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonReset')); ?></a>
            </form>
            <div class="muted" style="margin-bottom:10px"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeFilterHint')); ?></div>

            <div class="takepos-table-wrap">
                <table class="takepos-table">
                    <thead><tr><th><?php echo dol_escape_htmltag($langs->trans('TakeposChequeRef')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposChequeNumber')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposChequeSupplier')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposChequeBank')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposChequeAmount')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposChequeDate')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposChequeCollectionDate')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposChequeStatus')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposChequeDueState')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposChequeLinkedPurchase')); ?></th><th><?php echo dol_escape_htmltag($langs->trans('TakeposCommonActions')); ?></th></tr></thead>
                    <tbody>
                    <?php if (empty($rows)) { ?><tr><td colspan="11" class="muted"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeNoData')); ?></td></tr><?php } ?>
                    <?php foreach ($rows as $row) { $statusClass = 'status-' . preg_replace('/[^a-z]/', '', (string) $row->status); $dueClass = TakeposChequeService::dueClass((string) $row->due_state); $rowClass = ($row->due_state === TakeposChequeService::DUE_OVERDUE ? 'row-overdue' : ($row->due_state === TakeposChequeService::DUE_TODAY ? 'row-today' : '')); ?><tr class="<?php echo dol_escape_htmltag($rowClass); ?>">
                            <td><?php echo dol_escape_htmltag($row->ref); ?></td>
                            <td><?php echo dol_escape_htmltag($row->cheque_number); ?></td>
                            <td><?php echo dol_escape_htmltag((string) $row->supplier_name); ?></td>
                            <td><?php echo dol_escape_htmltag((string) $row->bank_name); ?></td>
                            <td><?php echo dol_escape_htmltag(price($row->amount, 0, '', 1, 0, 0, '', 0, 0)); ?></td>
                            <td><?php echo dol_escape_htmltag((string) $row->cheque_date); ?></td>
                            <td><?php echo dol_escape_htmltag((string) $row->collection_date); ?></td>
                            <td><span class="status-badge <?php echo dol_escape_htmltag($statusClass); ?>"><?php echo dol_escape_htmltag(TakeposChequeService::statusLabel((string) $row->status)); ?></span></td>
                            <td><span class="due-badge <?php echo dol_escape_htmltag($dueClass); ?>"><?php echo dol_escape_htmltag((string) $row->due_state_label); ?></span></td>
                            <td><?php if (!empty($row->purchase_ref)) { ?><a href="<?php echo dol_escape_htmltag($purchasesUrl . '?id=' . ((int) $row->fk_purchase)); ?>"><?php echo dol_escape_htmltag((string) $row->purchase_ref); ?></a><?php } ?></td>
                            <td class="inline-links">
                                <a href="<?php echo dol_escape_htmltag($pageUrl . '?id=' . ((int) $row->rowid)); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonEdit')); ?></a>
                                <a href="<?php echo dol_escape_htmltag($pageUrl . '?action=print&id=' . ((int) $row->rowid)); ?>" target="_blank"><?php echo dol_escape_htmltag($langs->trans('TakeposChequePrint')); ?></a>
                                <?php if (!empty($row->fk_purchase)) { ?><a href="<?php echo dol_escape_htmltag($purchasesUrl . '?id=' . ((int) $row->fk_purchase)); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposChequeOpenPurchase')); ?></a><?php } ?>
                            </td>
                        </tr><?php } ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
<script>
(function(){
    var searchInput = document.getElementById('supplier_search');
    var select = document.getElementById('supplier_id');
    if (!searchInput || !select) return;
    searchInput.addEventListener('input', function(){
        var q = (searchInput.value || '').toLowerCase();
        for (var i = 0; i < select.options.length; i++) {
            var opt = select.options[i];
            if (!opt.value) { opt.hidden = false; continue; }
            var hay = (opt.getAttribute('data-search') || opt.text || '').toLowerCase();
            opt.hidden = q !== '' && hay.indexOf(q) === -1;
        }
    });
})();
</script>
</body>
</html>
