<?php
/**
 * history_v2.php — Kafo POS v2 · سجل المبيعات
 * نسخة جديدة بتصميم v2 — لا تمس history.php الأصلية
 */
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML'))  define('NOREQUIREHTML', '1');
if (!defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX', '1');

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
$pageUrl = DOL_URL_ROOT . '/takepos/history_v2.php';

TakeposAccess::requireFrontendAccess(
    $db, $user, 'takepos.frontend', 'takepos.use',
    (int) $sessionTerminalToken, $langs->trans('TakeposHistoryAccessDenied'), array('page' => 'history_v2.php')
);
TakeposAudit::logEvent($db, $user, 'history_screen_opened', TakeposAudit::SEVERITY_INFO, array('page' => 'history_v2.php'), 'POS history v2 opened');

$canViewAll = (!empty($user->admin) || TakeposUserAccess::userHasPermission($db, $user, 'takepos.store.view_all'));
$statusMap  = array(
    ''  => $langs->trans('TakeposCommonAll'),
    '0' => $langs->trans('TakeposHistoryStatusDraft'),
    '1' => $langs->trans('TakeposHistoryStatusValidated'),
    '2' => $langs->trans('TakeposHistoryStatusPaid'),
);

$filters = array(
    'search'    => trim((string) GETPOST('search',    'none')),
    'status'    => trim((string) GETPOST('status',    'alpha')),
    'date_from' => trim((string) GETPOST('date_from', 'alpha')),
    'date_to'   => trim((string) GETPOST('date_to',   'alpha')),
    'scope'     => ($canViewAll && GETPOST('scope', 'aZ09') === 'all') ? 'all' : 'mine',
);
if (!isset($statusMap[$filters['status']])) $filters['status'] = '';

$allowedSorts = array(
    'date' => 'COALESCE(f.datef, f.datec)',
    'ref'  => 'f.ref',
    'total'=> 'f.total_ttc',
    'status'=>'f.fk_statut',
    'customer'=>'customer_name',
);
$sort  = GETPOST('sort', 'aZ09_');
$order = strtoupper(GETPOST('order', 'aZ09'));
if (!isset($allowedSorts[$sort])) $sort = 'date';
if ($order !== 'ASC' && $order !== 'DESC') $order = 'DESC';

$perPage = GETPOSTINT('per_page');
if (!in_array($perPage, array(25, 50, 100), true)) $perPage = 25;
$page = max(1, GETPOSTINT('page') ?: 1);

$baseQuery = array_merge($filters, array('sort'=>$sort,'order'=>$order,'per_page'=>$perPage));

/* ── SQL ── */
$where = array(
    "f.entity IN (" . getEntity('invoice') . ")",
    "f.module_source = 'takepos'",
    "f.pos_source = '" . $db->escape($sessionTerminalToken) . "'",
);
if (!$canViewAll || $filters['scope'] !== 'all') $where[] = "f.fk_user_author = " . (int) $user->id;
if ($filters['status'] !== '') $where[] = "f.fk_statut = " . (int) $filters['status'];
if ($filters['date_from'] !== '') $where[] = "COALESCE(f.datef,f.datec) >= '" . $db->escape($filters['date_from']) . " 00:00:00'";
if ($filters['date_to']   !== '') $where[] = "COALESCE(f.datef,f.datec) <= '" . $db->escape($filters['date_to'])   . " 23:59:59'";
if ($filters['search'] !== '') $where[] = "(f.ref LIKE '%" . $db->escape($filters['search']) . "%' OR COALESCE(s.nom,'') LIKE '%" . $db->escape($filters['search']) . "%')";

$whereSql = implode(' AND ', $where);
$countRes = $db->query("SELECT COUNT(*) AS nb FROM " . MAIN_DB_PREFIX . "facture f LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid=f.fk_soc WHERE " . $whereSql);
$totalRows  = ($countRes && ($co = $db->fetch_object($countRes))) ? (int) $co->nb : 0;
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$sql  = "SELECT f.rowid, f.ref, COALESCE(f.datef,f.datec) AS invoice_date, f.total_ttc, f.fk_statut, f.paye,";
$sql .= " COALESCE(s.nom,'') AS customer_name,";
$sql .= " COALESCE(NULLIF(CONCAT(COALESCE(u.firstname,''),' ',COALESCE(u.lastname,'')), ' '), u.login, CONCAT('User#',f.fk_user_author)) AS cashier_name";
$sql .= " FROM " . MAIN_DB_PREFIX . "facture f";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid=f.fk_soc";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid=f.fk_user_author";
$sql .= " WHERE " . $whereSql;
$sql .= " ORDER BY " . $allowedSorts[$sort] . " " . $order . ", f.rowid DESC";
$sql .= $db->plimit($perPage, $offset);

$rows = array();
$resql = $db->query($sql);
if ($resql) while ($obj = $db->fetch_object($resql)) $rows[] = $obj;

function v2HistoryStatus($langs, $row) {
    if ((int)$row->paye === 1 || (int)$row->fk_statut >= 2) return array($langs->trans('TakeposHistoryStatusPaid'), 'paid');
    if ((int)$row->fk_statut === 1) return array($langs->trans('TakeposHistoryStatusValidated'), 'validated');
    return array($langs->trans('TakeposHistoryStatusDraft'), 'draft');
}
function v2HistoryUrl($base, $params) { return $base . '?' . http_build_query($params); }
function v2SortUrl($base, $bq, $col, $cur, $curOrd) {
    $p = $bq; $p['sort'] = $col; $p['page'] = 1;
    $p['order'] = ($cur === $col && strtoupper($curOrd) === 'ASC') ? 'DESC' : 'ASC';
    return v2HistoryUrl($base, $p);
}

$currentLangCode = takeposCurrentLangCode($langs, $user);

/* ── HTML ── */
$FA    = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css';
$title = $langs->trans('TakeposHistoryTitle');
$head  = '<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<link rel="stylesheet" href="' . $FA . '">';
$arrayofcss = array('/takepos/css/workspace_v2.css');
top_htmlhead($head, $title, 0, 0, array(), $arrayofcss);
?>
<body class="kfv2-body">
<?php
$v2PageTitle = $langs->trans('TakeposHistoryTitle');
$v2PageIcon  = 'fa-clock-rotate-left';
$v2PageSub   = $langs->trans('TakeposHistoryFilters');
$v2BackUrl   = DOL_URL_ROOT . '/takepos/pos_v2.php';
require __DIR__ . '/topbar_v2.php';
?>

<div class="kfv2-page-body">

    <!-- Filter Panel -->
    <div class="kfv2-filter-panel">
        <h3><i class="fa-solid fa-filter"></i> <?php echo dol_escape_htmltag($langs->trans('TakeposHistoryFilters')); ?></h3>
        <form method="get" action="<?php echo dol_escape_htmltag($pageUrl); ?>">
            <div class="kfv2-form-grid">
                <div class="kfv2-field">
                    <label><?php echo dol_escape_htmltag($langs->trans('TakeposCommonSearch')); ?></label>
                    <input type="text" name="search" value="<?php echo dol_escape_htmltag($filters['search']); ?>" placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposHistorySearchPlaceholder')); ?>">
                </div>
                <div class="kfv2-field">
                    <label><?php echo dol_escape_htmltag($langs->trans('TakeposCommonStatus')); ?></label>
                    <select name="status">
                        <?php foreach ($statusMap as $sc => $sl): ?>
                            <option value="<?php echo dol_escape_htmltag($sc); ?>"<?php echo $filters['status'] === (string)$sc ? ' selected' : ''; ?>><?php echo dol_escape_htmltag($sl); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="kfv2-field">
                    <label><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseLedgerDateFrom')); ?></label>
                    <input type="text" name="date_from" placeholder="YYYY-MM-DD" value="<?php echo dol_escape_htmltag($filters['date_from']); ?>">
                </div>
                <div class="kfv2-field">
                    <label><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseLedgerDateTo')); ?></label>
                    <input type="text" name="date_to" placeholder="YYYY-MM-DD" value="<?php echo dol_escape_htmltag($filters['date_to']); ?>">
                </div>
                <?php if ($canViewAll): ?>
                <div class="kfv2-field">
                    <label><?php echo dol_escape_htmltag($langs->trans('TakeposHistoryScope')); ?></label>
                    <select name="scope">
                        <option value="mine"<?php echo $filters['scope'] === 'mine' ? ' selected' : ''; ?>><?php echo dol_escape_htmltag($langs->trans('TakeposHistoryScopeMine')); ?></option>
                        <option value="all" <?php echo $filters['scope'] === 'all'  ? ' selected' : ''; ?>><?php echo dol_escape_htmltag($langs->trans('TakeposHistoryScopeAll'));  ?></option>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            <div class="kfv2-actions">
                <button type="submit" class="kfv2-btn kfv2-btn-primary">
                    <i class="fa-solid fa-magnifying-glass"></i> <?php echo dol_escape_htmltag($langs->trans('TakeposHistoryApplyFilters')); ?>
                </button>
                <a class="kfv2-btn kfv2-btn-outline" href="<?php echo dol_escape_htmltag($pageUrl); ?>">
                    <i class="fa-solid fa-rotate"></i> <?php echo dol_escape_htmltag($langs->trans('TakeposHistoryReset')); ?>
                </a>
            </div>
            <input type="hidden" name="sort" value="<?php echo dol_escape_htmltag($sort); ?>">
            <input type="hidden" name="order" value="<?php echo dol_escape_htmltag($order); ?>">
            <input type="hidden" name="per_page" value="<?php echo (int)$perPage; ?>">
        </form>
    </div>

    <!-- Results -->
    <div class="kfv2-card">
        <div class="kfv2-table-wrap">
            <table class="kfv2-table">
                <thead><tr>
                    <th><a href="<?php echo dol_escape_htmltag(v2SortUrl($pageUrl,$baseQuery,'date',$sort,$order)); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseDate')); ?></a></th>
                    <th><a href="<?php echo dol_escape_htmltag(v2SortUrl($pageUrl,$baseQuery,'ref',$sort,$order)); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseRef')); ?></a></th>
                    <th><a href="<?php echo dol_escape_htmltag(v2SortUrl($pageUrl,$baseQuery,'customer',$sort,$order)); ?>"><?php echo dol_escape_htmltag($langs->trans('Customer')); ?></a></th>
                    <th><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseUser')); ?></th>
                    <th class="num"><a href="<?php echo dol_escape_htmltag(v2SortUrl($pageUrl,$baseQuery,'total',$sort,$order)); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposHistoryTotalTtc')); ?></a></th>
                    <th><a href="<?php echo dol_escape_htmltag(v2SortUrl($pageUrl,$baseQuery,'status',$sort,$order)); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonStatus')); ?></a></th>
                    <th><?php echo dol_escape_htmltag($langs->trans('TakeposCommonActions')); ?></th>
                </tr></thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr class="empty-row"><td colspan="7"><?php echo dol_escape_htmltag($langs->trans('TakeposHistoryNoData')); ?></td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $row):
                        list($stLabel, $stClass) = v2HistoryStatus($langs, $row);
                    ?>
                    <tr>
                        <td class="num"><?php echo dol_escape_htmltag((string)$row->invoice_date); ?></td>
                        <td class="num"><?php echo dol_escape_htmltag((string)$row->ref); ?></td>
                        <td><?php echo dol_escape_htmltag((string)$row->customer_name); ?></td>
                        <td><?php echo dol_escape_htmltag((string)$row->cashier_name); ?></td>
                        <td class="num"><?php echo dol_escape_htmltag(price((float)$row->total_ttc,0,'',1,0,0,'',0,0)); ?></td>
                        <td><span class="kfv2-pill <?php echo $stClass; ?>"><?php echo dol_escape_htmltag($stLabel); ?></span></td>
                        <td style="display:flex;gap:6px;flex-wrap:wrap">
                            <a class="kfv2-lnk kfv2-btn-sm" href="<?php echo dol_escape_htmltag(DOL_URL_ROOT.'/compta/facture/card.php?facid='.(int)$row->rowid.'&langs='.rawurlencode($currentLangCode)); ?>" target="_blank">
                                <i class="fa-solid fa-eye"></i> <?php echo dol_escape_htmltag($langs->trans('TakeposCommonOpen')); ?>
                            </a>
                            <?php if ((int)$row->fk_statut >= 1 || (int)$row->paye === 1): ?>
                            <a class="kfv2-lnk kfv2-btn-sm" href="<?php echo dol_escape_htmltag(DOL_URL_ROOT.'/takepos/receipt.php?facid='.(int)$row->rowid); ?>" target="_blank">
                                <i class="fa-solid fa-receipt"></i> <?php echo dol_escape_htmltag(takeposTranslateWithFallback($langs,'Receipt','الإيصال','Receipt')); ?>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="kfv2-pagination">
            <span class="info"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonRows')); ?>: <b><?php echo (int)$totalRows; ?></b></span>
            <div class="btns">
                <?php
                $prevQ = $baseQuery; $prevQ['page'] = max(1, $page - 1);
                $nextQ = $baseQuery; $nextQ['page'] = min($totalPages, $page + 1);
                ?>
                <a class="kfv2-pg-btn<?php echo $page <= 1 ? ' kfv2-hidden' : ''; ?>" href="<?php echo dol_escape_htmltag(v2HistoryUrl($pageUrl,$prevQ)); ?>">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
                <span class="kfv2-pg-btn active"><?php echo (int)$page; ?> / <?php echo (int)$totalPages; ?></span>
                <a class="kfv2-pg-btn<?php echo $page >= $totalPages ? ' kfv2-hidden' : ''; ?>" href="<?php echo dol_escape_htmltag(v2HistoryUrl($pageUrl,$nextQ)); ?>">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>
<?php echo takeposHelpRender($langs, __FILE__); ?>
</body>
</html>
