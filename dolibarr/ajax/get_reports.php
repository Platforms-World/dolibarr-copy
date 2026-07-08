<?php
/**
 * AJAX provider for POS reports.
 */

if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
}
if (!defined('NOBROWSERNOTIF')) {
    define('NOBROWSERNOTIF', '1');
}

if (!defined('DOL_DOCUMENT_ROOT')) {
    $mainPath = __DIR__ . '/../../main.inc.php';
    if (!file_exists($mainPath)) {
        $mainPath = __DIR__ . '/../../../main.inc.php';
    }
    require $mainPath;
}
require_once DOL_DOCUMENT_ROOT . '/takepos/lib/takepos_lang.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposAccess.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposAudit.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposStoreService.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposUserAccess.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposInputValidator.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposUtf8.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('cashdesk', 'bills', 'main', 'takeposcustom@takepos'));

function takeposReportsTrans($key, $fallback)
{
    global $langs;

    $translated = $langs->trans($key);
    return ($translated !== $key ? $translated : $fallback);
}

/**
 * Validate ISO date.
 *
 * @param   string  $value  Date value
 * @return  bool
 */
function takeposIsIsoDate($value)
{
    return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value);
}

/**
 * Fetch SQL rows.
 *
 * @param   DoliDB  $db     Database
 * @param   string  $sql    SQL query
 * @return  array
 */
function takeposFetchRows($db, $sql)
{
    $rows = array();
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $rows[] = (array) $obj;
        }
    }

    return $rows;
}

function takeposReportsTableExists($db, $table)
{
    $resql = $db->query("SHOW TABLES LIKE '" . $db->escape($table) . "'");
    return ($resql && $db->num_rows($resql) > 0);
}

function takeposReportsColumnExists($db, $table, $column)
{
    $resql = $db->query("SHOW COLUMNS FROM " . $table . " LIKE '" . $db->escape($column) . "'");
    return ($resql && $db->num_rows($resql) > 0);
}

function takeposReportsDateRangeWhere($db, $filters, $expr)
{
    $where = array();
    if (!empty($filters['date_from']) && takeposIsIsoDate($filters['date_from'])) {
        $where[] = $expr . " >= '" . $db->escape($filters['date_from']) . " 00:00:00'";
    }
    if (!empty($filters['date_to']) && takeposIsIsoDate($filters['date_to'])) {
        $where[] = $expr . " <= '" . $db->escape($filters['date_to']) . " 23:59:59'";
    }
    return $where;
}

function takeposReportsReminderStatusSql($dateExpr)
{
    return "CASE WHEN " . $dateExpr . " IS NULL THEN '' WHEN " . $dateExpr . " < CURDATE() THEN 'overdue' WHEN " . $dateExpr . " <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'due_soon' ELSE 'pending' END";
}

/**
 * Standard JSON response wrapper.
 *
 * @param   bool    $success    Success flag
 * @param   string  $message    User-friendly message
 * @param   array   $data       Payload data
 * @param   array   $errors     Error list
 * @param   int     $httpCode   HTTP code
 * @param   array   $extra      Compatibility keys
 * @return  void
 */
function takeposReportsJson($success, $message = '', $data = array(), $errors = array(), $httpCode = 200, $extra = array())
{
    if (!headers_sent()) {
        http_response_code((int) $httpCode);
        header('Content-Type: application/json; charset=UTF-8');
    }

    $payload = array(
        'success' => (bool) $success,
        'message' => (string) $message,
        'data' => is_array($data) ? $data : array(),
        'errors' => is_array($errors) ? array_values($errors) : array((string) $errors),
    );

    if (!empty($extra) && is_array($extra)) {
        $payload = array_merge($payload, $extra);
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Build WHERE clause for invoices.
 *
 * @param   DoliDB  $db         Database
 * @param   array   $filters    Filters
 * @param   bool    $withLine   True to include facturedet alias fd filtering
 * @return  string
 */
function takeposBuildWhereClause($db, $filters, $withLine = false)
{
    global $conf;

    $dateExpr = 'COALESCE(f.datef, f.datec)';
    $where = array();
    $where[] = 'f.entity = ' . ((int) $conf->entity);
    $where[] = "f.module_source = 'takepos'";

    if (!empty($filters['date_from']) && takeposIsIsoDate($filters['date_from'])) {
        $where[] = $dateExpr . " >= '" . $db->escape($filters['date_from']) . " 00:00:00'";
    }
    if (!empty($filters['date_to']) && takeposIsIsoDate($filters['date_to'])) {
        $where[] = $dateExpr . " <= '" . $db->escape($filters['date_to']) . " 23:59:59'";
    }
    if (!empty($filters['cashier_id'])) {
        $where[] = 'f.fk_user_author = ' . ((int) $filters['cashier_id']);
    }
    if (!empty($filters['terminal_id'])) {
        $where[] = "f.pos_source = '" . $db->escape($filters['terminal_id']) . "'";
    }
    if (!empty($filters['store_id'])) {
        $where[] = "EXISTS (
            SELECT 1
            FROM " . MAIN_DB_PREFIX . "takepos_terminal tmap
            WHERE tmap.entity = f.entity
              AND tmap.terminal_code = f.pos_source
              AND tmap.fk_store = " . ((int) $filters['store_id']) . "
              AND tmap.active = 1
        )";
    }
    if (!empty($filters['allowed_store_ids']) && is_array($filters['allowed_store_ids'])) {
        $safeStoreIds = array();
        foreach ($filters['allowed_store_ids'] as $sid) {
            $sid = (int) $sid;
            if ($sid > 0) {
                $safeStoreIds[] = $sid;
            }
        }

        if (!empty($safeStoreIds)) {
            $where[] = "EXISTS (
                SELECT 1
                FROM " . MAIN_DB_PREFIX . "takepos_terminal tmap2
                WHERE tmap2.entity = f.entity
                  AND tmap2.terminal_code = f.pos_source
                  AND tmap2.fk_store IN (" . implode(',', $safeStoreIds) . ")
                  AND tmap2.active = 1
            )";
        } else {
            $where[] = '1 = 0';
        }
    }
    if (!empty($filters['customer_id'])) {
        $where[] = 'f.fk_soc = ' . ((int) $filters['customer_id']);
    }
    if ($filters['invoice_status'] !== '' && $filters['invoice_status'] !== null) {
        $where[] = 'f.fk_statut = ' . ((int) $filters['invoice_status']);
    }
    if (!empty($filters['payment_method'])) {
        $where[] = "EXISTS (
            SELECT 1
            FROM " . MAIN_DB_PREFIX . "paiement_facture pf2
            INNER JOIN " . MAIN_DB_PREFIX . "paiement p2 ON p2.rowid = pf2.fk_paiement
            INNER JOIN " . MAIN_DB_PREFIX . "c_paiement cp2 ON cp2.id = p2.fk_paiement
            WHERE pf2.fk_facture = f.rowid
            AND cp2.code = '" . $db->escape($filters['payment_method']) . "'
        )";
    }
    if ($withLine && !empty($filters['product_id'])) {
        $where[] = 'fd.fk_product = ' . ((int) $filters['product_id']);
    }
    if (!$withLine && !empty($filters['product_id'])) {
        $where[] = "EXISTS (
            SELECT 1 FROM " . MAIN_DB_PREFIX . "facturedet fd2
            WHERE fd2.fk_facture = f.rowid
            AND fd2.fk_product = " . ((int) $filters['product_id']) . "
        )";
    }

    return implode(' AND ', $where);
}

/**
 * Build CSV output from rows.
 *
 * @param   string  $reportType  Report type
 * @param   array   $data        Full report data
 * @return  array{0:array,1:array}
 */
function takeposBuildCsv($reportType, $data)
{
    if ($reportType === 'summary') {
        $headers = array(
            takeposReportsTrans('TakeposReportsTotalInvoices', 'Total invoices'),
            takeposReportsTrans('TakeposReportsTotalQuantitySold', 'Total quantity'),
            takeposReportsTrans('TakeposExpenseAmountHt', 'Subtotal'),
            takeposReportsTrans('TakeposReportsTotalTax', 'Tax'),
            takeposReportsTrans('TakeposReportsTotalDiscount', 'Discount'),
            takeposReportsTrans('TakeposReportsTotalFinalSales', 'Total sales')
        );
        $row = $data['summary'];
        $rows = array(array($row['total_invoices'], $row['total_qty'], $row['subtotal_ht'], $row['total_tax'], $row['total_discount'], $row['total_ttc']));
        return array($headers, $rows);
    }
    if ($reportType === 'cashier') {
        $headers = array(
            takeposReportsTrans('TakeposReportsCashier', 'Cashier'),
            takeposReportsTrans('TakeposReportsTotalInvoices', 'Invoices'),
            takeposReportsTrans('TakeposReportsTotalQuantitySold', 'Quantity sold'),
            takeposReportsTrans('TakeposReportsTotalFinalSales', 'Total sales'),
            takeposReportsTrans('TakeposReportsAveragePrice', 'Average invoice value')
        );
        $rows = array();
        foreach ($data['by_cashier'] as $r) {
            $rows[] = array($r['cashier_name'], $r['invoice_count'], $r['total_qty'], $r['total_ttc'], $r['avg_invoice']);
        }
        return array($headers, $rows);
    }
    if ($reportType === 'terminal') {
        $headers = array(
            takeposReportsTrans('TakeposReportsTerminal', 'Terminal'),
            takeposReportsTrans('TakeposReportsTotalInvoices', 'Invoices'),
            takeposReportsTrans('TakeposReportsTotalQuantitySold', 'Quantity sold'),
            takeposReportsTrans('TakeposReportsTotalFinalSales', 'Total sales')
        );
        $rows = array();
        foreach ($data['by_terminal'] as $r) {
            $rows[] = array($r['terminal_name'], $r['invoice_count'], $r['total_qty'], $r['total_ttc']);
        }
        return array($headers, $rows);
    }
    if ($reportType === 'product') {
        $headers = array(
            takeposReportsTrans('TakeposReportsProductRef', 'Product ref'),
            takeposReportsTrans('TakeposReportsProductLabel', 'Product label'),
            takeposReportsTrans('TakeposReportsTotalQuantitySold', 'Quantity sold'),
            takeposReportsTrans('TakeposReportsTotalFinalSales', 'Total sales'),
            takeposReportsTrans('TakeposReportsAveragePrice', 'Average price')
        );
        $rows = array();
        foreach ($data['by_product'] as $r) {
            $rows[] = array($r['product_ref'], $r['product_label'], $r['total_qty'], $r['total_ttc'], $r['avg_price']);
        }
        return array($headers, $rows);
    }

    if ($reportType === 'cheques') {
        $headers = array('Ref', 'Cheque No.', 'Supplier', 'Bank', 'Amount', 'Cheque date', 'Collection date', 'Status', 'Reminder');
        $rows = array();
        foreach ($data['cheques'] as $r) {
            $rows[] = array($r['ref'], $r['cheque_number'], $r['supplier_name'], $r['bank_name'], $r['amount'], $r['cheque_date'], $r['collection_date'], $r['status'], $r['reminder_status']);
        }
        return array($headers, $rows);
    }
    if ($reportType === 'receivables') {
        $headers = array('Invoice ref', 'Customer', 'Invoice date', 'Due date', 'Total', 'Paid', 'Remaining', 'Reminder');
        $rows = array();
        foreach ($data['receivables'] as $r) {
            $rows[] = array($r['ref'], $r['customer_name'], $r['invoice_date'], $r['due_date'], $r['total_ttc'], $r['paid_amount'], $r['remaining_amount'], $r['reminder_status']);
        }
        return array($headers, $rows);
    }
    if ($reportType === 'payables') {
        $headers = array('Supplier invoice ref', 'Supplier', 'Invoice date', 'Due date', 'Total', 'Paid', 'Remaining', 'Reminder');
        $rows = array();
        foreach ($data['payables'] as $r) {
            $rows[] = array($r['ref'], $r['supplier_name'], $r['invoice_date'], $r['due_date'], $r['total_ttc'], $r['paid_amount'], $r['remaining_amount'], $r['reminder_status']);
        }
        return array($headers, $rows);
    }
    if ($reportType === 'product_velocity') {
        $headers = array('Product ref', 'Product label', 'Quantity sold', 'Total sales', 'Average price', 'Qty/day', 'Movement class');
        $rows = array();
        foreach ($data['product_velocity'] as $r) {
            $rows[] = array($r['product_ref'], $r['product_label'], $r['total_qty'], $r['total_ttc'], $r['avg_price'], $r['qty_per_day'], $r['movement_class']);
        }
        return array($headers, $rows);
    }
    if ($reportType === 'stock_moves') {
        $headers = array('Date', 'Product ref', 'Product label', 'Warehouse', 'Qty movement', 'Type', 'Label', 'Inventory code', 'User');
        $rows = array();
        foreach ($data['stock_moves'] as $r) {
            $rows[] = array($r['movement_date'], $r['product_ref'], $r['product_label'], $r['warehouse_name'], $r['qty_movement'], $r['movement_type'], $r['movement_label'], $r['inventorycode'], $r['user_login']);
        }
        return array($headers, $rows);
    }
    if ($reportType === 'near_expiry') {
        $headers = array('Product ref', 'Product label', 'Batch', 'Warehouse', 'Quantity', 'Expiry date', 'Status');
        $rows = array();
        foreach ($data['near_expiry'] as $r) {
            $rows[] = array($r['product_ref'], $r['product_label'], $r['batch'], $r['warehouse_name'], $r['qty'], $r['expiry_date'], $r['expiry_status']);
        }
        return array($headers, $rows);
    }

    $headers = array(
        takeposReportsTrans('TakeposReportsInvoiceRef', 'Invoice ref'),
        takeposReportsTrans('TakeposReportsDate', 'Date'),
        takeposReportsTrans('TakeposReportsCashier', 'Cashier'),
        takeposReportsTrans('TakeposReportsTerminal', 'Terminal'),
        takeposReportsTrans('TakeposReportsStore', 'Store'),
        takeposReportsTrans('TakeposReportsCustomer', 'Customer'),
        takeposReportsTrans('TakeposExpenseAmountHt', 'Subtotal'),
        takeposReportsTrans('TakeposReportsTotalTax', 'Tax'),
        takeposReportsTrans('TakeposReportsTotal', 'Total'),
        takeposReportsTrans('TakeposReportsPaymentMethod', 'Payment method'),
        takeposReportsTrans('TakeposReportsStatus', 'Status')
    );
    $rows = array();
    foreach ($data['detailed'] as $r) {
        $rows[] = array($r['ref'], $r['invoice_date'], $r['cashier_name'], $r['terminal_name'], $r['store_name'], $r['customer_name'], $r['total_ht'], $r['total_tax'], $r['total_ttc'], $r['payment_methods'], $r['status_label']);
    }

    return array($headers, $rows);
}

TakeposUtf8::bootstrapConnection($db);

$action = GETPOST('action', 'aZ09');
if ($action === '') {
    $action = 'generate';
}

TakeposAccess::requireAjaxAccess(
    $db,
    $user,
    'takepos.reports',
    'takepos.action.reports_view',
    isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : null,
    array('endpoint' => 'ajax/get_reports.php', 'requested_action' => $action)
);

TakeposAudit::logEvent(
    $db,
    $user,
    'open_reports',
    TakeposAudit::SEVERITY_INFO,
    array('source' => 'reports_ajax', 'action' => $action),
    'POS reports endpoint called'
);

try {
    $filters = array(
        'date_from' => TakeposInputValidator::normalizeUtf8Text(GETPOST('date_from', 'none'), 10, true),
        'date_to' => TakeposInputValidator::normalizeUtf8Text(GETPOST('date_to', 'none'), 10, true),
        'cashier_id' => GETPOSTINT('cashier_id'),
        'terminal_id' => TakeposInputValidator::normalizeUtf8Text(GETPOST('terminal_id', 'none'), 64, true),
        'store_id' => GETPOSTINT('store_id'),
        'product_id' => GETPOSTINT('product_id'),
        'customer_id' => GETPOSTINT('customer_id'),
        'invoice_status' => TakeposInputValidator::normalizeUtf8Text(GETPOST('invoice_status', 'none'), 8, true),
        'payment_method' => TakeposInputValidator::normalizeUtf8Text(GETPOST('payment_method', 'none'), 32, true),
    );

    $enforceStoreRestrictions = TakeposStoreService::enforceStoreRestrictionEnabled($db);
    $canViewAllStores = (!empty($user->admin) || TakeposUserAccess::userHasPermission($db, $user, 'takepos.store.view_all'));
    $allowedStoreIds = array();
    if ($enforceStoreRestrictions && !$canViewAllStores) {
        $allowedStoreIds = TakeposStoreService::getUserStoreIds($db, (int) $conf->entity, (int) $user->id);

        if (!empty($filters['store_id']) && !in_array((int) $filters['store_id'], $allowedStoreIds, true)) {
            TakeposAudit::logEvent($db, $user, 'store_restriction_denied', TakeposAudit::SEVERITY_WARNING, array('requested_store_id' => (int) $filters['store_id'], 'source' => 'reports'), 'Store restriction denied on reports');
            takeposReportsJson(false, takeposReportsTrans('TakeposReportsStoreAccessDenied', 'Store access denied.'), array(), array('store_access_denied'), 403);
        }

        $filters['allowed_store_ids'] = $allowedStoreIds;
    }

    if ($action === 'filters') {
        $whereTakepos = 'f.entity = ' . ((int) $conf->entity) . " AND f.module_source = 'takepos'";

        $cashiers = takeposFetchRows($db, "SELECT DISTINCT u.rowid, u.login, u.firstname, u.lastname
            FROM " . MAIN_DB_PREFIX . "facture f
            INNER JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid = f.fk_user_author
            WHERE " . $whereTakepos . "
            ORDER BY u.login ASC");

        $terminals = takeposFetchRows($db, "SELECT DISTINCT f.pos_source
            FROM " . MAIN_DB_PREFIX . "facture f
            WHERE " . $whereTakepos . " AND f.pos_source IS NOT NULL AND f.pos_source <> ''
            ORDER BY f.pos_source ASC");

        $products = takeposFetchRows($db, "SELECT DISTINCT p.rowid, p.ref, p.label
            FROM " . MAIN_DB_PREFIX . "facturedet fd
            INNER JOIN " . MAIN_DB_PREFIX . "facture f ON f.rowid = fd.fk_facture
            INNER JOIN " . MAIN_DB_PREFIX . "product p ON p.rowid = fd.fk_product
            WHERE " . $whereTakepos . "
            ORDER BY p.ref ASC, p.label ASC");

        $customers = takeposFetchRows($db, "SELECT DISTINCT s.rowid, s.nom as name
            FROM " . MAIN_DB_PREFIX . "facture f
            INNER JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = f.fk_soc
            WHERE " . $whereTakepos . "
            ORDER BY s.nom ASC");

        $paymentMethods = takeposFetchRows($db, "SELECT DISTINCT cp.code, cp.libelle
            FROM " . MAIN_DB_PREFIX . "paiement_facture pf
            INNER JOIN " . MAIN_DB_PREFIX . "paiement p ON p.rowid = pf.fk_paiement
            INNER JOIN " . MAIN_DB_PREFIX . "c_paiement cp ON cp.id = p.fk_paiement
            INNER JOIN " . MAIN_DB_PREFIX . "facture f ON f.rowid = pf.fk_facture
            WHERE " . $whereTakepos . "
            ORDER BY cp.libelle ASC");

        $storeWhere = 's.entity = ' . ((int) $conf->entity) . ' AND s.active = 1';
        if ($enforceStoreRestrictions && !$canViewAllStores) {
            if (!empty($allowedStoreIds)) {
                $storeWhere .= ' AND s.rowid IN (' . implode(',', array_map('intval', $allowedStoreIds)) . ')';
            } else {
                $storeWhere .= ' AND 1 = 0';
            }
        }

        $stores = takeposFetchRows($db, "SELECT s.rowid, s.code, s.label
            FROM " . MAIN_DB_PREFIX . "takepos_store s
            WHERE " . $storeWhere . "
            ORDER BY s.code ASC");

        $filterData = array(
            'cashiers' => $cashiers,
            'terminals' => $terminals,
            'stores' => $stores,
            'products' => $products,
            'customers' => $customers,
            'payment_methods' => $paymentMethods,
            'invoice_statuses' => array(
                array('id' => '0', 'label' => 'Draft'),
                array('id' => '1', 'label' => 'Validated'),
                array('id' => '2', 'label' => 'Closed/Paid'),
            ),
        );

        // Keep compatibility for older JS that reads root-level keys.
        takeposReportsJson(true, takeposReportsTrans('TakeposReportsFiltersLoaded', 'Filters loaded.'), array('filters' => $filterData), array(), 200, $filterData);
    }

    $whereLine = takeposBuildWhereClause($db, $filters, true);
    $whereInvoice = takeposBuildWhereClause($db, $filters, false);

    $sqlSummary = "SELECT
        COUNT(DISTINCT f.rowid) AS total_invoices,
        COALESCE(SUM(fd.qty), 0) AS total_qty,
        COALESCE(SUM(fd.total_ht), 0) AS subtotal_ht,
        COALESCE(SUM(fd.total_tva + fd.total_localtax1 + fd.total_localtax2), 0) AS total_tax,
        COALESCE(SUM((fd.subprice * fd.qty) * (fd.remise_percent / 100)), 0) AS total_discount,
        COALESCE(SUM(fd.total_ttc), 0) AS total_ttc
    FROM " . MAIN_DB_PREFIX . "facture f
    INNER JOIN " . MAIN_DB_PREFIX . "facturedet fd ON fd.fk_facture = f.rowid
    WHERE " . $whereLine;
    $summaryRows = takeposFetchRows($db, $sqlSummary);
    $summary = !empty($summaryRows[0]) ? $summaryRows[0] : array(
        'total_invoices' => 0,
        'total_qty' => 0,
        'subtotal_ht' => 0,
        'total_tax' => 0,
        'total_discount' => 0,
        'total_ttc' => 0,
    );

    $sqlByCashier = "SELECT
        COALESCE(NULLIF(CONCAT(COALESCE(u.firstname, ''), ' ', COALESCE(u.lastname, '')), ' '), u.login, CONCAT('User#', f.fk_user_author)) AS cashier_name,
        COUNT(DISTINCT f.rowid) AS invoice_count,
        COALESCE(SUM(fd.qty), 0) AS total_qty,
        COALESCE(SUM(fd.total_ttc), 0) AS total_ttc,
        COALESCE(SUM(fd.total_ttc) / NULLIF(COUNT(DISTINCT f.rowid), 0), 0) AS avg_invoice
    FROM " . MAIN_DB_PREFIX . "facture f
    INNER JOIN " . MAIN_DB_PREFIX . "facturedet fd ON fd.fk_facture = f.rowid
    LEFT JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid = f.fk_user_author
    WHERE " . $whereLine . "
    GROUP BY f.fk_user_author, cashier_name
    ORDER BY total_ttc DESC";
    $byCashier = takeposFetchRows($db, $sqlByCashier);

    $sqlByTerminal = "SELECT
        f.pos_source AS terminal_id,
        COALESCE(NULLIF(tt.label, ''), CONCAT('Terminal ', f.pos_source)) AS terminal_name,
        COUNT(DISTINCT f.rowid) AS invoice_count,
        COALESCE(SUM(fd.qty), 0) AS total_qty,
        COALESCE(SUM(fd.total_ttc), 0) AS total_ttc
    FROM " . MAIN_DB_PREFIX . "facture f
    INNER JOIN " . MAIN_DB_PREFIX . "facturedet fd ON fd.fk_facture = f.rowid
    LEFT JOIN " . MAIN_DB_PREFIX . "takepos_terminal tt ON tt.entity = f.entity AND tt.terminal_code = f.pos_source
    WHERE " . $whereLine . "
    GROUP BY f.pos_source, tt.label
    ORDER BY total_ttc DESC";
    $byTerminal = takeposFetchRows($db, $sqlByTerminal);

    $sqlByProduct = "SELECT
        COALESCE(p.ref, '') AS product_ref,
        COALESCE(NULLIF(p.label, ''), fd.label, fd.description, CONCAT('Product#', fd.fk_product)) AS product_label,
        COALESCE(SUM(fd.qty), 0) AS total_qty,
        COALESCE(SUM(fd.total_ttc), 0) AS total_ttc,
        COALESCE(SUM(fd.total_ttc) / NULLIF(SUM(fd.qty), 0), 0) AS avg_price
    FROM " . MAIN_DB_PREFIX . "facture f
    INNER JOIN " . MAIN_DB_PREFIX . "facturedet fd ON fd.fk_facture = f.rowid
    LEFT JOIN " . MAIN_DB_PREFIX . "product p ON p.rowid = fd.fk_product
    WHERE " . $whereLine . "
    GROUP BY fd.fk_product, product_ref, product_label
    ORDER BY total_ttc DESC";
    $byProduct = takeposFetchRows($db, $sqlByProduct);

    $sqlDetailed = "SELECT
        f.rowid,
        f.ref,
        COALESCE(f.datef, f.datec) AS invoice_date,
        COALESCE(NULLIF(CONCAT(COALESCE(u.firstname, ''), ' ', COALESCE(u.lastname, '')), ' '), u.login, CONCAT('User#', f.fk_user_author)) AS cashier_name,
        COALESCE(NULLIF(tt.label, ''), CONCAT('Terminal ', f.pos_source)) AS terminal_name,
        COALESCE(st.label, '') AS store_name,
        COALESCE(s.nom, '') AS customer_name,
        f.total_ht,
        (f.total_tva + f.total_localtax1 + f.total_localtax2) AS total_tax,
        f.total_ttc,
        COALESCE(pm.payment_methods, '') AS payment_methods,
        f.fk_statut,
        CASE f.fk_statut WHEN 0 THEN 'Draft' WHEN 1 THEN 'Validated' WHEN 2 THEN 'Closed/Paid' ELSE 'Other' END AS status_label
    FROM " . MAIN_DB_PREFIX . "facture f
    LEFT JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid = f.fk_user_author
    LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = f.fk_soc
    LEFT JOIN " . MAIN_DB_PREFIX . "takepos_terminal tt ON tt.entity = f.entity AND tt.terminal_code = f.pos_source
    LEFT JOIN " . MAIN_DB_PREFIX . "takepos_store st ON st.entity = f.entity AND st.rowid = tt.fk_store
    LEFT JOIN (
        SELECT
            pf.fk_facture,
            GROUP_CONCAT(DISTINCT cp.code ORDER BY cp.code SEPARATOR ', ') AS payment_methods
        FROM " . MAIN_DB_PREFIX . "paiement_facture pf
        INNER JOIN " . MAIN_DB_PREFIX . "paiement p ON p.rowid = pf.fk_paiement
        INNER JOIN " . MAIN_DB_PREFIX . "c_paiement cp ON cp.id = p.fk_paiement
        GROUP BY pf.fk_facture
    ) pm ON pm.fk_facture = f.rowid
    WHERE " . $whereInvoice . "
    ORDER BY COALESCE(f.datef, f.datec) DESC, f.rowid DESC";
    $detailed = takeposFetchRows($db, $sqlDetailed);

    $periodFrom = (!empty($filters['date_from']) && takeposIsIsoDate($filters['date_from'])) ? strtotime($filters['date_from']) : strtotime(date('Y-m-d'));
    $periodTo = (!empty($filters['date_to']) && takeposIsIsoDate($filters['date_to'])) ? strtotime($filters['date_to']) : $periodFrom;
    if ($periodFrom === false || $periodTo === false) {
        $periodFrom = strtotime(date('Y-m-d'));
        $periodTo = $periodFrom;
    }
    if ($periodTo < $periodFrom) {
        $tmpTs = $periodFrom;
        $periodFrom = $periodTo;
        $periodTo = $tmpTs;
    }
    $periodDays = max(1, (int) floor(($periodTo - $periodFrom) / 86400) + 1);

    $productVelocity = array();
    foreach ($byProduct as $row) {
        $qty = (float) $row['total_qty'];
        $qtyPerDay = $qty / $periodDays;
        $movementClass = 'slow';
        if ($qtyPerDay >= 5) {
            $movementClass = 'fast';
        } elseif ($qtyPerDay >= 1) {
            $movementClass = 'normal';
        }
        $row['qty_per_day'] = round($qtyPerDay, 4);
        $row['movement_class'] = $movementClass;
        $productVelocity[] = $row;
    }

    $cheques = array();
    $chequeTable = MAIN_DB_PREFIX . 'takepos_cheque';
    if (takeposReportsTableExists($db, $chequeTable)) {
        $chequeWhere = array('c.entity = ' . ((int) $conf->entity));
        if (!empty($filters['date_from']) && takeposIsIsoDate($filters['date_from'])) {
            $chequeWhere[] = "c.collection_date >= '" . $db->escape($filters['date_from']) . "'";
        }
        if (!empty($filters['date_to']) && takeposIsIsoDate($filters['date_to'])) {
            $chequeWhere[] = "c.collection_date <= '" . $db->escape($filters['date_to']) . "'";
        }
        $sqlCheques = "SELECT c.ref, c.cheque_number, COALESCE(s.nom, '') AS supplier_name, c.bank_name, c.amount, c.cheque_date, c.collection_date, c.status, "
            . takeposReportsReminderStatusSql('c.collection_date') . " AS reminder_status"
            . " FROM " . $chequeTable . " c"
            . " LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = c.fk_supplier"
            . " WHERE " . implode(' AND ', $chequeWhere)
            . " ORDER BY CASE WHEN c.status IN ('pending','partial','bounced') THEN 0 ELSE 1 END, c.collection_date ASC, c.rowid DESC"
            . $db->plimit(500, 0);
        $cheques = takeposFetchRows($db, $sqlCheques);
    }

    $receivableWhere = array(
        'f.entity = ' . ((int) $conf->entity),
        'f.fk_statut > 0',
        '(f.paye = 0 OR f.total_ttc > COALESCE(pay.paid_amount, 0))'
    );
    $receivableWhere = array_merge($receivableWhere, takeposReportsDateRangeWhere($db, $filters, 'COALESCE(f.date_lim_reglement, f.datef, f.datec)'));
    if (!empty($filters['customer_id'])) {
        $receivableWhere[] = 'f.fk_soc = ' . ((int) $filters['customer_id']);
    }
    $sqlReceivables = "SELECT f.ref, COALESCE(s.nom, '') AS customer_name, COALESCE(f.datef, f.datec) AS invoice_date, f.date_lim_reglement AS due_date, f.total_ttc, COALESCE(pay.paid_amount, 0) AS paid_amount, (f.total_ttc - COALESCE(pay.paid_amount, 0)) AS remaining_amount, "
        . takeposReportsReminderStatusSql('f.date_lim_reglement') . " AS reminder_status"
        . " FROM " . MAIN_DB_PREFIX . "facture f"
        . " LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = f.fk_soc"
        . " LEFT JOIN (SELECT fk_facture, SUM(amount) AS paid_amount FROM " . MAIN_DB_PREFIX . "paiement_facture GROUP BY fk_facture) pay ON pay.fk_facture = f.rowid"
        . " WHERE " . implode(' AND ', $receivableWhere)
        . " HAVING remaining_amount > 0.00001"
        . " ORDER BY due_date ASC, f.rowid DESC"
        . $db->plimit(500, 0);
    $receivables = takeposFetchRows($db, $sqlReceivables);

    $payables = array();
    $supplierInvoiceTable = MAIN_DB_PREFIX . 'facture_fourn';
    if (takeposReportsTableExists($db, $supplierInvoiceTable)) {
        $supplierPaymentLinkTable = MAIN_DB_PREFIX . 'paiementfourn_facturefourn';
        $supplierPaidJoin = '';
        $supplierPaidExpr = '0';
        if (takeposReportsTableExists($db, $supplierPaymentLinkTable)) {
            $supplierPaidJoin = " LEFT JOIN (SELECT fk_facturefourn, SUM(amount) AS paid_amount FROM " . $supplierPaymentLinkTable . " GROUP BY fk_facturefourn) pay ON pay.fk_facturefourn = ff.rowid";
            $supplierPaidExpr = 'COALESCE(pay.paid_amount, 0)';
        }
        $payableWhere = array(
            'ff.entity = ' . ((int) $conf->entity),
            'ff.fk_statut > 0',
            '(ff.paye = 0 OR ff.total_ttc > ' . $supplierPaidExpr . ')'
        );
        $payableWhere = array_merge($payableWhere, takeposReportsDateRangeWhere($db, $filters, 'COALESCE(ff.date_lim_reglement, ff.datef, ff.datec)'));
        $sqlPayables = "SELECT ff.ref, COALESCE(s.nom, '') AS supplier_name, COALESCE(ff.datef, ff.datec) AS invoice_date, ff.date_lim_reglement AS due_date, ff.total_ttc, " . $supplierPaidExpr . " AS paid_amount, (ff.total_ttc - " . $supplierPaidExpr . ") AS remaining_amount, "
            . takeposReportsReminderStatusSql('ff.date_lim_reglement') . " AS reminder_status"
            . " FROM " . $supplierInvoiceTable . " ff"
            . " LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = ff.fk_soc"
            . $supplierPaidJoin
            . " WHERE " . implode(' AND ', $payableWhere)
            . " HAVING remaining_amount > 0.00001"
            . " ORDER BY due_date ASC, ff.rowid DESC"
            . $db->plimit(500, 0);
        $payables = takeposFetchRows($db, $sqlPayables);
    }

    $stockMoves = array();
    if (takeposReportsTableExists($db, MAIN_DB_PREFIX . 'stock_mouvement') && takeposReportsTableExists($db, MAIN_DB_PREFIX . 'product')) {
        $stockMoveWhere = array('p.entity IN (' . getEntity('product') . ')');
        $stockMoveWhere = array_merge($stockMoveWhere, takeposReportsDateRangeWhere($db, $filters, 'sm.datem'));
        if (!empty($filters['product_id'])) {
            $stockMoveWhere[] = 'sm.fk_product = ' . ((int) $filters['product_id']);
        }
        $sqlStockMoves = "SELECT sm.datem AS movement_date, COALESCE(p.ref, '') AS product_ref, COALESCE(p.label, '') AS product_label, COALESCE(NULLIF(e.ref, ''), NULLIF(e.label, ''), NULLIF(e.lieu, ''), '') AS warehouse_name, sm.value AS qty_movement, sm.type_mouvement AS movement_type, sm.label AS movement_label, sm.inventorycode, COALESCE(u.login, '') AS user_login"
            . " FROM " . MAIN_DB_PREFIX . "stock_mouvement sm"
            . " LEFT JOIN " . MAIN_DB_PREFIX . "product p ON p.rowid = sm.fk_product"
            . " LEFT JOIN " . MAIN_DB_PREFIX . "entrepot e ON e.rowid = sm.fk_entrepot"
            . " LEFT JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid = sm.fk_user_author"
            . " WHERE " . implode(' AND ', $stockMoveWhere)
            . " ORDER BY sm.datem DESC, sm.rowid DESC"
            . $db->plimit(500, 0);
        $stockMoves = takeposFetchRows($db, $sqlStockMoves);
    }

    $nearExpiry = array();
    if (takeposReportsTableExists($db, MAIN_DB_PREFIX . 'product_batch') && takeposReportsTableExists($db, MAIN_DB_PREFIX . 'product_stock')) {
        $expiryExpr = 'COALESCE(pb.sellby, pb.eatby)';
        $nearExpiryWhere = array('p.entity IN (' . getEntity('product') . ')', $expiryExpr . ' IS NOT NULL');
        if (!empty($filters['date_from']) && takeposIsIsoDate($filters['date_from'])) {
            $nearExpiryWhere[] = $expiryExpr . " >= '" . $db->escape($filters['date_from']) . " 00:00:00'";
        } else {
            $nearExpiryWhere[] = $expiryExpr . " >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        }
        if (!empty($filters['date_to']) && takeposIsIsoDate($filters['date_to'])) {
            $nearExpiryWhere[] = $expiryExpr . " <= '" . $db->escape($filters['date_to']) . " 23:59:59'";
        } else {
            $nearExpiryWhere[] = $expiryExpr . " <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)";
        }
        if (!empty($filters['product_id'])) {
            $nearExpiryWhere[] = 'p.rowid = ' . ((int) $filters['product_id']);
        }
        $sqlNearExpiry = "SELECT COALESCE(p.ref, '') AS product_ref, COALESCE(p.label, '') AS product_label, pb.batch, COALESCE(NULLIF(e.ref, ''), NULLIF(e.label, ''), NULLIF(e.lieu, ''), '') AS warehouse_name, COALESCE(pb.qty, 0) AS qty, DATE(" . $expiryExpr . ") AS expiry_date, CASE WHEN " . $expiryExpr . " < CURDATE() THEN 'expired' WHEN " . $expiryExpr . " <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'near_expiry' ELSE 'watch' END AS expiry_status"
            . " FROM " . MAIN_DB_PREFIX . "product_batch pb"
            . " INNER JOIN " . MAIN_DB_PREFIX . "product_stock ps ON ps.rowid = pb.fk_product_stock"
            . " INNER JOIN " . MAIN_DB_PREFIX . "product p ON p.rowid = ps.fk_product"
            . " LEFT JOIN " . MAIN_DB_PREFIX . "entrepot e ON e.rowid = ps.fk_entrepot"
            . " WHERE " . implode(' AND ', $nearExpiryWhere)
            . " ORDER BY " . $expiryExpr . " ASC, p.label ASC"
            . $db->plimit(500, 0);
        $nearExpiry = takeposFetchRows($db, $sqlNearExpiry);
    }

    $data = array(
        'summary' => $summary,
        'by_cashier' => $byCashier,
        'by_terminal' => $byTerminal,
        'by_product' => $byProduct,
        'detailed' => $detailed,
        'cheques' => $cheques,
        'receivables' => $receivables,
        'payables' => $payables,
        'product_velocity' => $productVelocity,
        'stock_moves' => $stockMoves,
        'near_expiry' => $nearExpiry,
    );

    if ($action === 'csv') {
        $reportType = GETPOST('report_type', 'aZ09');
        if ($reportType === '') {
            $reportType = 'detailed';
        }

        list($headers, $rows) = takeposBuildCsv($reportType, $data);
        $filename = 'sales_report_' . dol_print_date(dol_now(), '%Y%m%d') . '.csv';

        TakeposAudit::logEvent(
            $db,
            $user,
            'open_reports',
            TakeposAudit::SEVERITY_INFO,
            array('source' => 'reports_csv', 'report_type' => $reportType),
            'CSV report export generated'
        );

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF";

        $fp = fopen('php://output', 'w');
        fputcsv($fp, $headers);
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);
        exit;
    }

    takeposReportsJson(true, takeposReportsTrans('TakeposReportsGeneratedMessage', 'Report generated.'), $data);
} catch (Throwable $e) {
    TakeposAudit::logEvent(
        $db,
        $user,
        'open_reports',
        TakeposAudit::SEVERITY_WARNING,
        array('source' => 'reports_ajax_error', 'action' => $action),
        'POS reports endpoint failed'
    );

    takeposReportsJson(false, takeposReportsTrans('TakeposReportsGenerateError', 'Unable to generate report data.'), array(), array($e->getMessage()), 500);
}
