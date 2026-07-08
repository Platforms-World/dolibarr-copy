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
require_once __DIR__ . '/class/TakeposAccess.class.php';
require_once __DIR__ . '/class/TakeposAudit.class.php';
require_once __DIR__ . '/class/TakeposExpenseService.class.php';
require_once __DIR__ . '/class/TakeposInputValidator.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';

$langs->loadLangs(array('cashdesk', 'main', 'banks', 'admin', 'takeposcustom@takepos'));

$sessionTerminalToken = isset($_SESSION['takeposterminal']) ? (string) $_SESSION['takeposterminal'] : '1';
$pageUrl = DOL_URL_ROOT . '/takepos/expense_ledger_v2.php';

TakeposAccess::requireFrontendAccess(
    $db,
    $user,
    'takepos.cash_control',
    'takepos.use',
    (int) $sessionTerminalToken,
    $langs->trans('TakeposExpenseLedgerAccessDenied'),
    array('page' => 'expense_ledger_v2.php')
);

if (!TakeposExpenseService::canRead($db, $user)) {
    TakeposAccess::denyAccess($db, $user, $langs->trans('TakeposExpenseReadPermissionRequired'), array('page' => 'expense_ledger_v2.php', 'permission' => 'takepos.expense.read'));
}

TakeposAudit::logEvent($db, $user, 'expense_ledger_opened', TakeposAudit::SEVERITY_INFO, array('page' => 'expense_ledger_v2.php'), 'POS expense ledger opened');
TakeposExpenseService::ensureSchema($db);

$entity = !empty($user->entity) ? (int) $user->entity : 1;
$form = new Form($db);
$canCreateExpense = TakeposExpenseService::canCreate($db, $user);
$canPostExpense = (TakeposExpenseService::canPost($db, $user) || TakeposExpenseService::canAdmin($db, $user));
$canAdminExpense = TakeposExpenseService::canAdmin($db, $user);

$statuses = TakeposExpenseService::listStatuses();
$categories = TakeposExpenseService::listCategories($db, $entity, false);
$terminals = TakeposExpenseService::listAccessibleTerminals($db, $user, false);
$expenseUsers = TakeposExpenseService::listExpenseUsers($db, $entity, $user);
$postingStates = array(
    '' => $langs->trans('TakeposCommonAll'),
    'posted' => $langs->trans('TakeposExpensePostedStatePosted'),
    'not_posted' => $langs->trans('TakeposExpensePostedStateNotPosted'),
);

$messages = array();
$errors = array();
$action = GETPOST('action', 'aZ09');

$filters = array(
    'date_from' => trim((string) GETPOST('date_from', 'none')),
    'date_to' => trim((string) GETPOST('date_to', 'none')),
    'fk_category' => GETPOSTINT('fk_category'),
    'fk_terminal' => GETPOSTINT('fk_terminal'),
    'fk_user' => GETPOSTINT('fk_user'),
    'status' => trim((string) GETPOST('status', 'none')),
    'posting_state' => trim((string) GETPOST('posting_state', 'aZ09_')),
    'search' => trim((string) GETPOST('search', 'none')),
);

if ($filters['status'] !== '' && !array_key_exists((int) $filters['status'], $statuses)) {
    $filters['status'] = '';
}
if (!isset($postingStates[$filters['posting_state']])) {
    $filters['posting_state'] = '';
}

$sort = trim((string) GETPOST('sort', 'aZ09_'));
$order = strtoupper(trim((string) GETPOST('order', 'aZ09')));
$allowedSorts = array('date', 'ref', 'user', 'category', 'amount_ttc', 'status');
if (!in_array($sort, $allowedSorts, true)) {
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
    'date_from' => $filters['date_from'],
    'date_to' => $filters['date_to'],
    'fk_category' => ($filters['fk_category'] > 0 ? (int) $filters['fk_category'] : ''),
    'fk_terminal' => ($filters['fk_terminal'] > 0 ? (int) $filters['fk_terminal'] : ''),
    'fk_user' => ($filters['fk_user'] > 0 ? (int) $filters['fk_user'] : ''),
    'status' => ($filters['status'] !== '' ? $filters['status'] : ''),
    'posting_state' => $filters['posting_state'],
    'search' => $filters['search'],
    'sort' => $sort,
    'order' => $order,
    'per_page' => $perPage,
);

if ($action === 'export_csv') {
    $filename = 'expense-ledger-' . dol_print_date(dol_now(), '%Y%m%d-%H%M%S') . '.csv';
    if ($filters['date_from'] !== '' || $filters['date_to'] !== '') {
        $filename = 'expense-ledger-' . ($filters['date_from'] !== '' ? preg_replace('/[^0-9]/', '', $filters['date_from']) : 'all');
        if ($filters['date_to'] !== '') {
            $filename .= '-to-' . preg_replace('/[^0-9]/', '', $filters['date_to']);
        }
        $filename .= '.csv';
    }

    TakeposAudit::logEvent(
        $db,
        $user,
        'expense_ledger_exported',
        TakeposAudit::SEVERITY_INFO,
        array(
            'page' => 'expense_ledger_v2.php',
            'filters' => $filters,
            'sort' => $sort,
            'order' => $order,
        ),
        'POS expense ledger exported'
    );

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: public');
    header('Expires: 0');

    TakeposExpenseService::streamExpensesCsv($db, $entity, $user, $filters, $sort, $order);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) GETPOST('token', 'alpha');
    $sessionToken = isset($_SESSION['newtoken']) ? (string) $_SESSION['newtoken'] : '';
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        $errors[] = $langs->trans('TakeposExpenseInvalidCsrf');
    } else {
        try {
            if ($action === 'post_expense') {
                $expenseId = GETPOSTINT('expense_id');
                if (!$canPostExpense) {
                    throw new Exception($langs->trans('TakeposExpensePostingPermissionRequired'));
                }
                TakeposExpenseService::postExpense($db, $user, $expenseId, $sessionTerminalToken);
                $redirectQuery = $baseQuery;
                $redirectQuery['page'] = $page;
                $redirectQuery['posted'] = 1;
                $redirectQuery['posted_id'] = $expenseId;
                header('Location: ' . $pageUrl . '?' . http_build_query($redirectQuery));
                exit;
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

if (GETPOSTINT('posted') === 1) {
    $messages[] = $langs->trans('TakeposExpenseLedgerPostedSuccess') . (GETPOSTINT('posted_id') > 0 ? ' (#' . GETPOSTINT('posted_id') . ')' : '') . '.';
}

$totalRows = TakeposExpenseService::countExpenses($db, $entity, $user, $filters);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$summary = TakeposExpenseService::summarizeExpenses($db, $entity, $user, $filters);
$rows = TakeposExpenseService::listExpenses($db, $entity, $filters, $perPage, $offset, $sort, $order, $user);

$title = $langs->trans('TakeposExpenseLedgerTitle');
$head = '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
$arrayofcss = array('/takepos/css/workspace_v2.css');
top_htmlhead($head, $title, 0, 0, array(), $arrayofcss);

function takeposExpenseLedgerUrl($pageUrl, $params)
{
    return $pageUrl . '?' . http_build_query($params);
}

function takeposExpenseLedgerSortUrl($pageUrl, $baseQuery, $targetSort, $currentSort, $currentOrder)
{
    $params = $baseQuery;
    $params['sort'] = $targetSort;
    $params['order'] = ($currentSort === $targetSort && strtoupper($currentOrder) === 'ASC') ? 'DESC' : 'ASC';
    $params['page'] = 1;
    return takeposExpenseLedgerUrl($pageUrl, $params);
}
?>
<body class="kfv2-body">
<?php
$v2PageTitle = $langs->trans('TakeposExpenseLedgerTitle');
$v2PageIcon  = 'fa-book';
$v2BackUrl   = DOL_URL_ROOT . '/takepos/pos_v2.php';
require __DIR__ . '/topbar_v2.php';
?>
<div class="kfv2-page-body" style="max-width:1460px;margin:0 auto;padding:22px 26px 48px">
    <div style="display:none">
        <h2 style="display:none"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseLedgerTitle')); ?></h2>
        <div class="takepos-workspace-filter-actions takepos-workspace-ledger-top-actions">
            <?php if ($canCreateExpense) { ?>
                <a class="kfv2-btn kfv2-btn-primary" href="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/workspace.php?key=expenses_ops'); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseLedgerNewExpense')); ?></a>
            <?php } ?>
            <?php if ($canAdminExpense) { ?>
                <a class="kfv2-btn kfv2-btn-outline" href="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/workspace.php?key=admin_expense_categories'); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseLedgerExpenseCategories')); ?></a>
            <?php } ?>
            <a class="kfv2-btn kfv2-btn-outline" href="<?php echo dol_escape_htmltag($pageUrl . '?' . http_build_query(array_merge($baseQuery, array('action' => 'export_csv')))); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseLedgerExportCsv')); ?></a>
            <a class="kfv2-btn kfv2-btn-outline" href="<?php echo dol_escape_htmltag($pageUrl); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseLedgerResetFilters')); ?></a>
        </div>
    </div>

    <?php foreach ($messages as $message) { ?>
        <div class="ok"><?php echo dol_escape_htmltag($message); ?></div>
    <?php } ?>
    <?php foreach ($errors as $errorMessage) { ?>
        <div class="error"><?php echo dol_escape_htmltag($errorMessage); ?></div>
    <?php } ?>

    <section class="kfv2-card-block">
        <div class="kfv2-card-block-head"><h3 style="margin:0;font-size:14.5px"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseLedgerFilters')); ?></h3></div><div class="kfv2-card-block-body">
        <form method="get" action="<?php echo dol_escape_htmltag($pageUrl); ?>">
            <div class="kfv2-form-grid">
                <div>
                    <label for="date_from"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseLedgerDateFrom')); ?></label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo dol_escape_htmltag($filters['date_from']); ?>">
                </div>
                <div>
                    <label for="date_to"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseLedgerDateTo')); ?></label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo dol_escape_htmltag($filters['date_to']); ?>">
                </div>
                <div>
                    <label for="fk_category"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseCategory')); ?></label>
                    <select id="fk_category" name="fk_category">
                        <option value=""><?php echo dol_escape_htmltag($langs->trans('TakeposCommonAll')); ?></option>
                        <?php foreach ($categories as $category) { ?>
                            <option value="<?php echo (int) $category->rowid; ?>"<?php echo ((int) $filters['fk_category'] === (int) $category->rowid ? ' selected' : ''); ?>>
                                <?php echo dol_escape_htmltag($category->label); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div>
                    <label for="fk_terminal"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseTerminal')); ?></label>
                    <select id="fk_terminal" name="fk_terminal">
                        <option value=""><?php echo dol_escape_htmltag($langs->trans('TakeposCommonAll')); ?></option>
                        <?php foreach ($terminals as $terminal) { ?>
                            <option value="<?php echo (int) $terminal->rowid; ?>"<?php echo ((int) $filters['fk_terminal'] === (int) $terminal->rowid ? ' selected' : ''); ?>>
                                <?php echo dol_escape_htmltag($terminal->terminal_code . ' - ' . $terminal->label); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div>
                    <label for="fk_user"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseUserCashier')); ?></label>
                    <select id="fk_user" name="fk_user">
                        <option value=""><?php echo dol_escape_htmltag($langs->trans('TakeposCommonAll')); ?></option>
                        <?php foreach ($expenseUsers as $expenseUser) { ?>
                            <?php
                            $userLabel = trim((string) $expenseUser->login);
                            if ($userLabel === '') {
                                $userLabel = trim((string) $expenseUser->firstname . ' ' . (string) $expenseUser->lastname);
                            }
                            ?>
                            <option value="<?php echo (int) $expenseUser->rowid; ?>"<?php echo ((int) $filters['fk_user'] === (int) $expenseUser->rowid ? ' selected' : ''); ?>>
                                <?php echo dol_escape_htmltag($userLabel !== '' ? $userLabel : ($langs->trans('TakeposExpenseUserIdPrefix') . ((int) $expenseUser->rowid))); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div>
                    <label for="status"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonStatus')); ?></label>
                    <select id="status" name="status">
                        <option value=""><?php echo dol_escape_htmltag($langs->trans('TakeposCommonAll')); ?></option>
                        <?php foreach ($statuses as $statusCode => $statusLabel) { ?>
                            <option value="<?php echo (int) $statusCode; ?>"<?php echo ((string) $filters['status'] !== '' && (int) $filters['status'] === (int) $statusCode ? ' selected' : ''); ?>>
                                <?php echo dol_escape_htmltag($statusLabel); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div>
                    <label for="posting_state"><?php echo dol_escape_htmltag($langs->trans('TakeposExpensePostingState')); ?></label>
                    <select id="posting_state" name="posting_state">
                        <?php foreach ($postingStates as $postingCode => $postingLabel) { ?>
                            <option value="<?php echo dol_escape_htmltag($postingCode); ?>"<?php echo ($filters['posting_state'] === $postingCode ? ' selected' : ''); ?>>
                                <?php echo dol_escape_htmltag($postingLabel); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div style="grid-column: 1 / -1;">
                    <label for="search"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonSearch')); ?></label>
                    <input type="text" id="search" name="search" value="<?php echo dol_escape_htmltag($filters['search']); ?>" placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposExpenseLedgerSearchPlaceholder')); ?>">
                </div>
            </div>
            <div class="kfv2-actions">
                <button type="submit" class="kfv2-btn kfv2-btn-primary"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseLedgerApplyFilters')); ?></button>
                <a class="kfv2-btn kfv2-btn-outline" href="<?php echo dol_escape_htmltag($pageUrl); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonReset')); ?></a>
            </div>
            <input type="kfv2-hidden" name="sort" value="<?php echo dol_escape_htmltag($sort); ?>">
            <input type="kfv2-hidden" name="order" value="<?php echo dol_escape_htmltag($order); ?>">
            <input type="kfv2-hidden" name="per_page" value="<?php echo (int) $perPage; ?>">
        </form>
    </section>

    <section class="kfv2-card-block">
        <div class="kfv2-card-block-head"><h3 style="margin:0;font-size:14.5px"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseLedgerSummary')); ?></h3></div><div class="kfv2-card-block-body">
        <div class="kfv2-kpis">
            <div class="kfv2-kpi"><div class="kk"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseLedgerTotalCount')); ?></div><div class="kv num"><?php echo (int) (!empty($summary->total_count) ? $summary->total_count : 0); ?></div></div>
            <div class="kfv2-kpi"><div class="kk"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseLedgerTotalHt')); ?></div><div class="kv num"><?php echo price((float) (!empty($summary->total_amount_ht) ? $summary->total_amount_ht : 0)); ?></div></div>
            <div class="kfv2-kpi"><div class="kk"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseLedgerTotalVat')); ?></div><div class="kv num"><?php echo price((float) (!empty($summary->total_amount_tva) ? $summary->total_amount_tva : 0)); ?></div></div>
            <div class="kfv2-kpi"><div class="kk"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseLedgerTotalTtc')); ?></div><div class="kv num"><?php echo price((float) (!empty($summary->total_amount_ttc) ? $summary->total_amount_ttc : 0)); ?></div></div>
            <div class="kfv2-kpi"><div class="kk"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseLedgerPostedCount')); ?></div><div class="kv num"><?php echo (int) (!empty($summary->posted_count) ? $summary->posted_count : 0); ?></div></div>
            <div class="kfv2-kpi"><div class="kk"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseLedgerNotPostedCount')); ?></div><div class="kv num"><?php echo (int) (!empty($summary->not_posted_count) ? $summary->not_posted_count : 0); ?></div></div>
        </div>
    </section>

    <section class="kfv2-card-block">
        <div class="takepos-workspace-ledger-headerline">
            <div class="kfv2-card-block-head"><h3 style="margin:0;font-size:14.5px"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseLedgerTitle')); ?></h3></div><div class="kfv2-card-block-body">
            <div class="opacitymedium"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonRows')); ?>: <?php echo (int) $totalRows; ?> | <?php echo dol_escape_htmltag($langs->trans('TakeposCommonPage')); ?> <?php echo (int) $page; ?> / <?php echo (int) $totalPages; ?></div>
        </div>
        <div class="kfv2-table-wrap">
            <table class="kfv2-table">
                <thead>
                <tr>
                    <th><a href="<?php echo dol_escape_htmltag(takeposExpenseLedgerSortUrl($pageUrl, $baseQuery, 'date', $sort, $order)); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseDate')); ?></a></th>
                    <th><a href="<?php echo dol_escape_htmltag(takeposExpenseLedgerSortUrl($pageUrl, $baseQuery, 'ref', $sort, $order)); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseReference')); ?></a></th>
                    <th><a href="<?php echo dol_escape_htmltag(takeposExpenseLedgerSortUrl($pageUrl, $baseQuery, 'user', $sort, $order)); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseUser')); ?></a></th>
                    <th><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseTerminal')); ?></th>
                    <th><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseShift')); ?></th>
                    <th><a href="<?php echo dol_escape_htmltag(takeposExpenseLedgerSortUrl($pageUrl, $baseQuery, 'category', $sort, $order)); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseCategory')); ?></a></th>
                    <th><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseDescription')); ?></th>
                    <th class="right"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseAmountHt')); ?></th>
                    <th class="right"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseVat')); ?></th>
                    <th><a href="<?php echo dol_escape_htmltag(takeposExpenseLedgerSortUrl($pageUrl, $baseQuery, 'amount_ttc', $sort, $order)); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseAmountTtc')); ?></a></th>
                    <th><a href="<?php echo dol_escape_htmltag(takeposExpenseLedgerSortUrl($pageUrl, $baseQuery, 'status', $sort, $order)); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonStatus')); ?></a></th>
                    <th><?php echo dol_escape_htmltag($langs->trans('TakeposExpensePostedState')); ?></th>
                    <th><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseAccountingAccount')); ?></th>
                    <th><?php echo dol_escape_htmltag($langs->trans('TakeposExpensePaymentSource')); ?></th>
                    <th><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseBankAccountingLink')); ?></th>
                    <th><?php echo dol_escape_htmltag($langs->trans('TakeposCommonActions')); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)) { ?>
                    <tr>
                        <td colspan="16">
                            <?php echo dol_escape_htmltag($langs->trans('TakeposExpenseLedgerNoResults')); ?>
                            <?php if ($canCreateExpense) { ?>
                                <a href="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/workspace.php?key=expenses_ops'); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseLedgerCreateNewExpense')); ?></a>.
                            <?php } ?>
                        </td>
                    </tr>
                <?php } else { ?>
                    <?php foreach ($rows as $row) { ?>
                        <?php
                        $posted = TakeposExpenseService::isExpensePosted($row);
                        $userLabel = trim((string) $row->user_login);
                        if ($userLabel === '') {
                            $userLabel = trim((string) $row->user_firstname . ' ' . (string) $row->user_lastname);
                        }
                        $terminalLabel = trim((string) $row->terminal_code . ' - ' . (string) $row->terminal_label);
                        $postedSummary = array();
                        if (!empty($row->fk_payment_various)) {
                            $postedSummary[] = $langs->trans('TakeposExpensePaymentVariousShort') . ((int) $row->fk_payment_various);
                        }
                        if (!empty($row->fk_bank_line)) {
                            $postedSummary[] = $langs->trans('TakeposExpenseBankShort') . ((int) $row->fk_bank_line);
                        }
                        if (!empty($row->date_posted)) {
                            $postedSummary[] = $langs->trans('TakeposExpenseAt') . ' ' . (string) $row->date_posted;
                        }
                        if (!empty($row->posted_user_login)) {
                            $postedSummary[] = $langs->trans('TakeposExpenseBy') . ' ' . (string) $row->posted_user_login;
                        }
                        ?>
                        <tr>
                            <td><?php echo dol_escape_htmltag((string) $row->date_expense); ?></td>
                            <td><?php echo dol_escape_htmltag((string) $row->ref); ?></td>
                            <td><?php echo dol_escape_htmltag($userLabel !== '' ? $userLabel : ($langs->trans('TakeposExpenseUserIdPrefix') . ((int) $row->fk_user))); ?></td>
                            <td>
                                <?php echo dol_escape_htmltag($terminalLabel !== '' ? $terminalLabel : '-'); ?>
                                <?php if (!empty($row->store_label)) { ?>
                                    <div class="opacitymedium"><?php echo dol_escape_htmltag((string) $row->store_label); ?></div>
                                <?php } ?>
                            </td>
                            <td><?php echo dol_escape_htmltag(!empty($row->shift_ref) ? (string) $row->shift_ref : (!empty($row->fk_shift) ? ('#' . ((int) $row->fk_shift)) : '-')); ?></td>
                            <td><?php echo dol_escape_htmltag((string) $row->category_label); ?></td>
                            <td>
                                <?php echo dol_escape_htmltag(function_exists('dol_trunc') ? dol_trunc((string) $row->description, 64) : (string) $row->description); ?>
                                <?php if (!empty($row->external_ref)) { ?>
                                    <div class="opacitymedium"><?php echo dol_escape_htmltag($langs->trans('TakeposExpenseRefShort')); ?> <?php echo dol_escape_htmltag((string) $row->external_ref); ?></div>
                                <?php } ?>
                            </td>
                            <td class="right"><?php echo price((float) $row->amount_ht); ?></td>
                            <td class="right"><?php echo price((float) $row->amount_tva); ?></td>
                            <td class="right"><?php echo price((float) $row->amount_ttc); ?></td>
                            <td><span class="takepos-expense-status takepos-expense-status-<?php echo (int) $row->status; ?>"><?php echo dol_escape_htmltag(TakeposExpenseService::statusLabel((int) $row->status)); ?></span></td>
                            <td><span class="takepos-posted-badge <?php echo ($posted ? 'is-posted' : 'is-not-posted'); ?>"><?php echo dol_escape_htmltag(TakeposExpenseService::postedStateLabel($row)); ?></span></td>
                            <td><?php echo dol_escape_htmltag((string) ($row->accountancy_code !== '' ? $row->accountancy_code : '-')); ?></td>
                            <td><?php echo dol_escape_htmltag(TakeposExpenseService::paymentSourceLabel($row->payment_source)); ?></td>
                            <td>
                                <?php if (empty($postedSummary)) { ?>
                                    <span class="opacitymedium">-</span>
                                <?php } else { ?>
                                    <?php foreach ($postedSummary as $summaryLine) { ?>
                                        <div><?php echo dol_escape_htmltag($summaryLine); ?></div>
                                    <?php } ?>
                                <?php } ?>
                            </td>
                            <td>
                                <div class="takepos-ledger-actions">
                                    <a class="kfv2-btn kfv2-btn-outline" href="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/takepos/expenses.php?id=' . ((int) $row->rowid)); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonOpen')); ?></a>
                                    <?php if (TakeposExpenseService::canPostExpenseRecord($db, $user, $row)) { ?>
                                        <form method="post" action="<?php echo dol_escape_htmltag($pageUrl . '?' . http_build_query(array_merge($baseQuery, array('page' => $page)))); ?>" class="takepos-inline-form">
                                            <input type="kfv2-hidden" name="token" value="<?php echo dol_escape_htmltag(newToken()); ?>">
                                            <input type="kfv2-hidden" name="action" value="post_expense">
                                            <input type="kfv2-hidden" name="expense_id" value="<?php echo (int) $row->rowid; ?>">
                                            <button type="submit" class="kfv2-btn kfv2-btn-primary" onclick="return confirm('<?php echo dol_escape_js($langs->trans('TakeposExpenseLedgerPostConfirm')); ?>');"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonPost')); ?></button>
                                        </form>
                                    <?php } ?>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1) { ?>
            <div class="takepos-workspace-filter-actions takepos-ledger-pagination">
                <?php if ($page > 1) { ?>
                    <a class="kfv2-btn kfv2-btn-outline" href="<?php echo dol_escape_htmltag(takeposExpenseLedgerUrl($pageUrl, array_merge($baseQuery, array('page' => $page - 1)))); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonPrevious')); ?></a>
                <?php } ?>
                <span class="takepos-ledger-pageinfo"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonPage')); ?> <?php echo (int) $page; ?> / <?php echo (int) $totalPages; ?></span>
                <?php if ($page < $totalPages) { ?>
                    <a class="kfv2-btn kfv2-btn-outline" href="<?php echo dol_escape_htmltag(takeposExpenseLedgerUrl($pageUrl, array_merge($baseQuery, array('page' => $page + 1)))); ?>"><?php echo dol_escape_htmltag($langs->trans('TakeposCommonNext')); ?></a>
                <?php } ?>
            </div>
        <?php } ?>
    </section>
</div>

<style>
.takepos-workspace-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}
.takepos-workspace-ledger-top-actions {
    margin-top: 0;
}
.takepos-workspace-ledger-headerline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}
.takepos-expense-status,
.takepos-posted-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}
.takepos-expense-status-0 {
    background: #eef3fb;
    color: #37527e;
}
.takepos-expense-status-1 {
    background: #fff4d7;
    color: #7b5a00;
}
.takepos-expense-status-2 {
    background: #dff7ef;
    color: #0f6e4f;
}
.takepos-expense-status-9 {
    background: #fde6e8;
    color: #9f2e39;
}
.takepos-posted-badge.is-posted {
    background: #dff7ef;
    color: #0f6e4f;
}
.takepos-posted-badge.is-not-posted {
    background: #eef3fb;
    color: #37527e;
}
.takepos-ledger-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.takepos-inline-form {
    display: inline-flex;
    margin: 0;
}
.takepos-ledger-pagination {
    justify-content: space-between;
    align-items: center;
}
.takepos-ledger-pageinfo {
    font-weight: 700;
    color: #32507e;
}
.takepos-workspace-table th a {
    color: inherit;
    text-decoration: none;
}
@media (max-width: 767px) {
    .takepos-ledger-pagination {
        justify-content: flex-start;
    }
}
</style>
</body>
<?php
llxFooter();
$db->close();
