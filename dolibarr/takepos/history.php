<?php
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
}

require '../main.inc.php';

require_once __DIR__ . '/lib/takepos_lang.php';
require_once __DIR__ . '/lib/takepos_help.php';
require_once __DIR__ . '/class/TakeposUserAccess.class.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/class/TakeposAccess.class.php';
require_once __DIR__ . '/class/TakeposAudit.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';

$langs->loadLangs(array('main', 'bills', 'cashdesk', 'takeposcustom@takepos'));

$sessionTerminalToken = isset($_SESSION['takeposterminal']) ? (string) $_SESSION['takeposterminal'] : '1';
$pageUrl = DOL_URL_ROOT . '/takepos/history.php';

TakeposAccess::requireFrontendAccess(
    $db,
    $user,
    'takepos.frontend',
    'takepos.use',
    (int) $sessionTerminalToken,
    $langs->trans('TakeposHistoryAccessDenied'),
    array('page' => 'history.php')
);

TakeposAudit::logEvent($db, $user, 'history_screen_opened', TakeposAudit::SEVERITY_INFO, array('page' => 'history.php'), 'POS history screen opened');

$canViewAllHistory = (!empty($user->admin) || TakeposUserAccess::userHasPermission($db, $user, 'takepos.store.view_all'));
$statusMap = array(
    '' => $langs->trans('TakeposCommonAll'),
    '0' => $langs->trans('TakeposHistoryStatusDraft'),
    '1' => $langs->trans('TakeposHistoryStatusValidated'),
    '2' => $langs->trans('TakeposHistoryStatusPaid'),
);

$filters = array(
    'search' => trim((string) GETPOST('search', 'none')),
    'status' => trim((string) GETPOST('status', 'alpha')),
    'date_from' => trim((string) GETPOST('date_from', 'alpha')),
    'date_to' => trim((string) GETPOST('date_to', 'alpha')),
    'scope' => ($canViewAllHistory && GETPOST('scope', 'aZ09') === 'all') ? 'all' : 'mine',
);

if (!isset($statusMap[$filters['status']])) {
    $filters['status'] = '';
}

$allowedSorts = array(
    'date' => 'COALESCE(f.datef, f.datec)',
    'ref' => 'f.ref',
    'total' => 'f.total_ttc',
    'status' => 'f.fk_statut',
    'customer' => 'customer_name',
);
$sort = trim((string) GETPOST('sort', 'aZ09_'));
$order = strtoupper(trim((string) GETPOST('order', 'aZ09')));
if (!isset($allowedSorts[$sort])) {
    $sort = 'date';
}
if ($order !== 'ASC' && $order !== 'DESC') {
    $order = 'DESC';
}

$perPage = GETPOSTINT('per_page');
if (!in_array($perPage, array(25, 50, 100), true)) {
    $perPage = 25;
}
$page = GETPOSTINT('page');
if ($page < 1) {
    $page = 1;
}

$baseQuery = array(
    'search' => $filters['search'],
    'status' => $filters['status'],
    'date_from' => $filters['date_from'],
    'date_to' => $filters['date_to'],
    'scope' => $filters['scope'],
    'sort' => $sort,
    'order' => $order,
    'per_page' => $perPage,
);

$where = array();
$where[] = "f.entity IN (" . getEntity('invoice') . ")";
$where[] = "f.module_source = 'takepos'";
if ($sessionTerminalToken !== '') {
    $where[] = "f.pos_source = '" . $db->escape($sessionTerminalToken) . "'";
}
if (!$canViewAllHistory || $filters['scope'] !== 'all') {
    $where[] = "f.fk_user_author = " . ((int) $user->id);
}
if ($filters['status'] !== '') {
    $where[] = "f.fk_statut = " . ((int) $filters['status']);
}
if ($filters['date_from'] !== '') {
    $where[] = "COALESCE(f.datef, f.datec) >= '" . $db->escape($filters['date_from']) . " 00:00:00'";
}
if ($filters['date_to'] !== '') {
    $where[] = "COALESCE(f.datef, f.datec) <= '" . $db->escape($filters['date_to']) . " 23:59:59'";
}
if ($filters['search'] !== '') {
    $where[] = "("
        . "f.ref LIKE '%" . $db->escape($filters['search']) . "%'"
        . " OR COALESCE(s.nom,'') LIKE '%" . $db->escape($filters['search']) . "%'"
        . ")";
}

$whereSql = implode(' AND ', $where);
$countSql = "SELECT COUNT(*) AS nb FROM " . MAIN_DB_PREFIX . "facture f LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = f.fk_soc WHERE " . $whereSql;
$countRes = $db->query($countSql);
$totalRows = 0;
if ($countRes && ($countObj = $db->fetch_object($countRes))) {
    $totalRows = (int) $countObj->nb;
}
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$sql = "SELECT f.rowid, f.ref, COALESCE(f.datef, f.datec) AS invoice_date, f.total_ttc, f.fk_statut, f.paye, f.note_public,";
$sql .= " COALESCE(s.nom, '') AS customer_name,";
$sql .= " COALESCE(NULLIF(CONCAT(COALESCE(u.firstname,''), ' ', COALESCE(u.lastname,'')), ' '), u.login, CONCAT('User#', f.fk_user_author)) AS cashier_name,";
$sql .= " COALESCE(r.refund_count, 0) AS refund_count,";
$sql .= " COALESCE(r.refunded_amount, 0) AS refunded_amount";
$sql .= " FROM " . MAIN_DB_PREFIX . "facture f";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = f.fk_soc";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid = f.fk_user_author";
$sql .= " LEFT JOIN (SELECT fk_original_invoice, COUNT(*) AS refund_count, SUM(total_amount) AS refunded_amount FROM " . MAIN_DB_PREFIX . "takepos_refund WHERE status = 'completed' GROUP BY fk_original_invoice) r ON r.fk_original_invoice = f.rowid";
$sql .= " WHERE " . $whereSql;
$sql .= " ORDER BY " . $allowedSorts[$sort] . " " . $order . ", f.rowid DESC";
$sql .= $db->plimit($perPage, $offset);

$rows = array();
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $rows[] = $obj;
    }
}

function takeposHistoryStatusLabel($langs, $row)
{
    $isPaid = ((int) $row->paye === 1 || (int) $row->fk_statut >= 2);
    if ($isPaid && !empty($row->refund_count) && (int) $row->refund_count > 0) {
        // Check if fully or partially refunded
        $refundedAmt = (float) $row->refunded_amount;
        $totalAmt    = (float) $row->total_ttc;
        if ($totalAmt > 0 && $refundedAmt >= $totalAmt - 0.001) {
            return $langs->trans('TakeposHistoryStatusRefunded');
        }
        return $langs->trans('TakeposHistoryStatusPartiallyRefunded');
    }
    if ($isPaid) {
        return $langs->trans('TakeposHistoryStatusPaid');
    }
    if ((int) $row->fk_statut === 1) {
        return $langs->trans('TakeposHistoryStatusValidated');
    }
    return $langs->trans('TakeposHistoryStatusDraft');
}

function takeposHistoryUrl($pageUrl, $params)
{
    return $pageUrl . '?' . http_build_query($params);
}

function takeposHistorySortUrl($pageUrl, $baseQuery, $targetSort, $currentSort, $currentOrder)
{
    $params = $baseQuery;
    $params['sort'] = $targetSort;
    $params['order'] = ($currentSort === $targetSort && strtoupper($currentOrder) === 'ASC') ? 'DESC' : 'ASC';
    $params['page'] = 1;
    return takeposHistoryUrl($pageUrl, $params);
}

$title = $langs->trans('TakeposHistoryTitle');
$head = '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
$arrayofcss = array('/takepos/css/workspace.css');
$currentLangCode = takeposCurrentLangCode($langs, isset($user) ? $user : null);
$dateFormatHint = 'YYYY-MM-DD';
top_htmlhead($head, $title, 0, 0, array(), $arrayofcss);
print '<style>#php-debugbar,.phpdebugbar,.php-debugbar,.debugbar,.debug-bar,.debugbar-container,.sf-toolbar,#sfwdt,div[id*="debugbar"],div[class*="debugbar"]{display:none !important;visibility:hidden !important;opacity:0 !important;pointer-events:none !important;}</style>';
print '<script>function takeposHideHistoryDebugBars(){var sels=["#php-debugbar",".phpdebugbar",".php-debugbar",".debugbar",".debug-bar",".debugbar-container",".sf-toolbar","#sfwdt","div[id*=\"debugbar\"]","div[class*=\"debugbar\"]"];try{document.querySelectorAll(sels.join(",")).forEach(function(el){el.remove();});}catch(e){}}document.addEventListener("DOMContentLoaded",takeposHideHistoryDebugBars);window.addEventListener("load",takeposHideHistoryDebugBars);setTimeout(takeposHideHistoryDebugBars,300);</script>';
?>
<body class="takepos-workspace-reports-body">
<div class="takepos-workspace-reports-page">
    <div class="takepos-workspace-title-row">
        <h2 class="takepos-workspace-title"><?php echo dol_escape_htmltag($langs->trans('TakeposHistoryTitle')); ?></h2>
        <div class="takepos-workspace-filter-actions">
            <a class="button button-cancel" href="<?php echo dol_escape_htmltag($pageUrl); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposHistoryReset')); ?></a>
        </div>
    </div>

    <section class="takepos-workspace-panel takepos-workspace-filter-panel">
        <h3><?php echo dol_escape_htmltag($langs->trans('TakeposHistoryFilters')); ?></h3>
        <form method="get" action="<?php echo dol_escape_htmltag($pageUrl); ?>">
            <div class="takepos-workspace-filter-grid">
                <div>
                    <label for="search"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonSearch')); ?></label>
                    <input type="text" id="search" name="search" value="<?php echo dol_escape_htmltag($filters['search']); ?>" placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposHistorySearchPlaceholder')); ?>">
                </div>
                <div>
                    <label for="status"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonStatus')); ?></label>
                    <select id="status" name="status">
                        <?php foreach ($statusMap as $statusCode => $statusLabel) { ?>
                            <option value="<?php echo dol_escape_htmltag($statusCode); ?>"<?php echo ($filters['status'] === (string) $statusCode ? ' selected' : ''); ?>><?php echo dol_escape_htmltag($statusLabel); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div>
                    <label for="date_from"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseLedgerDateFrom')); ?> (<?php echo dol_escape_htmltag($dateFormatHint); ?>)</label>
                    <input type="text" id="date_from" name="date_from" inputmode="numeric" pattern="\d{4}-\d{2}-\d{2}" placeholder="<?php echo dol_escape_htmltag($dateFormatHint); ?>" value="<?php echo dol_escape_htmltag($filters['date_from']); ?>">
                </div>
                <div>
                    <label for="date_to"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseLedgerDateTo')); ?> (<?php echo dol_escape_htmltag($dateFormatHint); ?>)</label>
                    <input type="text" id="date_to" name="date_to" inputmode="numeric" pattern="\d{4}-\d{2}-\d{2}" placeholder="<?php echo dol_escape_htmltag($dateFormatHint); ?>" value="<?php echo dol_escape_htmltag($filters['date_to']); ?>">
                </div>
                <?php if ($canViewAllHistory) { ?>
                    <div>
                        <label for="scope"><?php echo dol_escape_htmltag($langs->trans('TakeposHistoryScope')); ?></label>
                        <select id="scope" name="scope">
                            <option value="mine"<?php echo ($filters['scope'] === 'mine' ? ' selected' : ''); ?>><?php echo dol_escape_htmltag($langs->trans('TakeposHistoryScopeMine')); ?></option>
                            <option value="all"<?php echo ($filters['scope'] === 'all' ? ' selected' : ''); ?>><?php echo dol_escape_htmltag($langs->trans('TakeposHistoryScopeAll')); ?></option>
                        </select>
                    </div>
                <?php } ?>
            </div>
            <div class="takepos-workspace-filter-actions">
                <button type="submit" class="button button-save"><?php echo dol_escape_htmltag($langs->trans('TakeposHistoryApplyFilters')); ?></button>
            </div>
            <input type="hidden" name="sort" value="<?php echo dol_escape_htmltag($sort); ?>">
            <input type="hidden" name="order" value="<?php echo dol_escape_htmltag($order); ?>">
            <input type="hidden" name="per_page" value="<?php echo (int) $perPage; ?>">
        </form>
    </section>

    <section class="takepos-workspace-panel">
        <div class="takepos-workspace-table-wrap">
            <table class="takepos-workspace-table">
                <thead>
                <tr>
                    <th><?php echo dol_escape_htmltag(takeposTranslateWithFallback($langs, 'TakeposInvoiceNumber', 'رقم الفاتورة', 'Invoice #')); ?></th>
                    <th><a href="<?php echo dol_escape_htmltag(takeposHistorySortUrl($pageUrl, $baseQuery, 'date', $sort, $order)); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseDate')); ?></a></th>
                    <th><a href="<?php echo dol_escape_htmltag(takeposHistorySortUrl($pageUrl, $baseQuery, 'ref', $sort, $order)); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseRef')); ?></a></th>
                    <th><a href="<?php echo dol_escape_htmltag(takeposHistorySortUrl($pageUrl, $baseQuery, 'customer', $sort, $order)); ?>"><?php echo dol_escape_htmltag($langs->trans('Customer')); ?></a></th>
                    <th><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseUser')); ?></th>
                    <th><a href="<?php echo dol_escape_htmltag(takeposHistorySortUrl($pageUrl, $baseQuery, 'total', $sort, $order)); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposHistoryTotalTtc')); ?></a></th>
                    <th><a href="<?php echo dol_escape_htmltag(takeposHistorySortUrl($pageUrl, $baseQuery, 'status', $sort, $order)); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonStatus')); ?></a></th>
                    <th><?php echo dol_escape_htmltag(takeposTranslateWithFallback($langs, 'TakeposSyncMode', 'أوفلاين / أونلاين', 'Offline / Online')); ?></th>
                    <th><?php echo dol_escape_htmltag($langs->trans('TakeposCommonActions')); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)) { ?>
                    <tr><td colspan="9"><?php echo dol_escape_htmltag($langs->trans('TakeposHistoryNoData')); ?></td></tr>
                <?php } ?>
                <?php foreach ($rows as $row) { ?>
                    <tr>
                        <td>#<?php echo (int) $row->rowid; ?></td>
                        <td><?php echo dol_escape_htmltag((string) $row->invoice_date); ?></td>
                        <td><?php echo dol_escape_htmltag((string) $row->ref); ?></td>
                        <td><?php echo dol_escape_htmltag((string) $row->customer_name); ?></td>
                        <td><?php echo dol_escape_htmltag((string) $row->cashier_name); ?></td>
                        <td class="right"><?php echo dol_escape_htmltag(price((float) $row->total_ttc, 0, '', 1, 0, 0, '', 0, 0)); ?></td>
                        <td>
                            <?php
                            $statusLabel = takeposHistoryStatusLabel($langs, $row);
                            $isRefunded  = !empty($row->refund_count) && (int) $row->refund_count > 0;
                            $badgeStyle  = '';
                            if ($isRefunded) {
                                $refundedAmt = (float) $row->refunded_amount;
                                $totalAmt    = (float) $row->total_ttc;
                                $isFull = ($totalAmt > 0 && $refundedAmt >= $totalAmt - 0.001);
                                $badgeStyle = $isFull
                                    ? 'display:inline-block;padding:2px 8px;border-radius:10px;background:#c0392b;color:#fff;font-size:0.85em;font-weight:700;'
                                    : 'display:inline-block;padding:2px 8px;border-radius:10px;background:#e67e22;color:#fff;font-size:0.85em;font-weight:700;';
                            }
                            ?>
                            <?php if ($isRefunded) { ?>
                                <span style="<?php echo $badgeStyle; ?>"><?php echo dol_escape_htmltag($statusLabel); ?></span>
                            <?php } else { ?>
                                <?php echo dol_escape_htmltag($statusLabel); ?>
                            <?php } ?>
                        </td>
                        <td>
                            <?php if (strpos((string) $row->note_public, 'KF_OFFLINE_SYNCED') !== false) { ?>
                                <span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#b45309;color:#fff;font-size:0.85em;font-weight:700;"><?php echo dol_escape_htmltag(takeposTranslateWithFallback($langs, 'TakeposOffline', 'أوفلاين', 'Offline')); ?></span>
                            <?php } else { ?>
                                <span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#0f766e;color:#fff;font-size:0.85em;font-weight:700;"><?php echo dol_escape_htmltag(takeposTranslateWithFallback($langs, 'TakeposOnline', 'أونلاين', 'Online')); ?></span>
                            <?php } ?>
                        </td>
                        <td>
                            <a class="button button-small" href="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/compta/facture/card.php?facid=' . ((int) $row->rowid) . '&langs=' . rawurlencode($currentLangCode)); ?>" target="_blank" rel="noopener"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonOpen')); ?></a>
                            <?php if ((int) $row->fk_statut >= 1 || (int) $row->paye === 1) { ?>
                                <a class="button button-small" href="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/receipt.php?facid=' . ((int) $row->rowid)); ?>" target="_blank" rel="noopener"><?php echo dol_escape_htmltag(takeposTranslateWithFallback($langs, 'Receipt', 'الإيصال', 'Receipt')); ?></a>
                            <?php } ?>
                            <?php
                            $isFullyRefunded = !empty($row->refund_count) && (int) $row->refund_count > 0
                                && (float) $row->total_ttc > 0
                                && (float) $row->refunded_amount >= (float) $row->total_ttc - 0.001;
                            if ((int) $row->paye === 1 && !$isFullyRefunded && TakeposAccess::isFeatureEnabled($db, 'takepos.returns')) { ?>
                                <a class="button button-small" href="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/refunds.php?invoice_ref=' . rawurlencode((string) $row->ref)); ?>" target="_parent"><?php echo dol_escape_htmltag(takeposTranslateWithFallback($langs, 'TakeposRefundAction', 'ارجاع', 'Refund')); ?></a>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1) { ?>
            <div class="takepos-workspace-pagination" style="margin-top:12px;display:flex;gap:8px;align-items:center;justify-content:space-between;flex-wrap:wrap;">
                <div><?php echo dol_escape_htmltag($langs->trans('TakeposCommonRows')); ?>: <?php echo (int) $totalRows; ?></div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <?php
                    $prevQuery = $baseQuery;
                    $prevQuery['page'] = max(1, $page - 1);
                    $nextQuery = $baseQuery;
                    $nextQuery['page'] = min($totalPages, $page + 1);
                    ?>
                    <a class="button<?php echo ($page <= 1 ? ' button-cancel' : ''); ?>" href="<?php echo dol_escape_htmltag(takeposHistoryUrl($pageUrl, $prevQuery)); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonPrevious')); ?></a>
                    <span><?php echo dol_escape_htmltag($langs->trans('TakeposCommonPage')); ?> <?php echo (int) $page; ?> / <?php echo (int) $totalPages; ?></span>
                    <a class="button<?php echo ($page >= $totalPages ? ' button-cancel' : ''); ?>" href="<?php echo dol_escape_htmltag(takeposHistoryUrl($pageUrl, $nextQuery)); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonNext')); ?></a>
                </div>
            </div>
        <?php } ?>
    </section>
</div>
<?php print takeposHelpRender($langs, __FILE__); ?>
</body>
</html>