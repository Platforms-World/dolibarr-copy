<?php
/**
 * TakePOS API v1 - Reports
 * GET /takepos/api/v1/pos_reports.php
 *
 * Returns the same data as the web Reports page.
 *
 * Query params (all optional):
 *   date_from       YYYY-MM-DD
 *   date_to         YYYY-MM-DD
 *   cashier_id      int
 *   terminal_id     string
 *   store_id        int
 *   product_id      int
 *   customer_id     int
 *   invoice_status  0|1|2
 *   payment_method  string (e.g. CB, CASH)
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_request.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposUserAccess.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposStoreService.class.php';

$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET');
if ($method !== 'GET') {
    takeposApiError('METHOD_NOT_ALLOWED', 'Method not allowed.', 405, null, array(), array('Allow: GET'));
}

$auth   = takeposApiAuth($db, 'read', 'takepos.reports');
$entity = (int) $auth['entity'];
$user   = $auth['user'];

TakeposUtf8::bootstrapConnection($db);

// ── Helpers ───────────────────────────────────────────────────────────────────

function reportsIsIsoDate($v) {
    return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v);
}

function reportsFetchRows($db, $sql) {
    $rows = array();
    $res = $db->query($sql);
    if ($res) { while ($obj = $db->fetch_object($res)) { $rows[] = (array) $obj; } }
    return $rows;
}

function reportsTableExists($db, $table) {
    $res = $db->query("SHOW TABLES LIKE '" . $db->escape($table) . "'");
    return ($res && $db->num_rows($res) > 0);
}

function reportsReminderSql($expr) {
    return "CASE WHEN $expr IS NULL THEN '' WHEN $expr < CURDATE() THEN 'overdue' WHEN $expr <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'due_soon' ELSE 'pending' END";
}

function reportsBuildWhere($db, $filters, $entity, $withLine = false) {
    $where = array();
    $where[] = 'f.entity = ' . $entity;
    $where[] = "f.module_source = 'takepos'";
    $dateExpr = 'COALESCE(f.datef, f.datec)';
    if (!empty($filters['date_from']) && reportsIsIsoDate($filters['date_from']))
        $where[] = "$dateExpr >= '" . $db->escape($filters['date_from']) . " 00:00:00'";
    if (!empty($filters['date_to']) && reportsIsIsoDate($filters['date_to']))
        $where[] = "$dateExpr <= '" . $db->escape($filters['date_to']) . " 23:59:59'";
    if (!empty($filters['cashier_id']))
        $where[] = 'f.fk_user_author = ' . (int)$filters['cashier_id'];
    if (!empty($filters['terminal_id']))
        $where[] = "f.pos_source = '" . $db->escape($filters['terminal_id']) . "'";
    if (!empty($filters['store_id']))
        $where[] = "EXISTS (SELECT 1 FROM " . MAIN_DB_PREFIX . "takepos_terminal tmap WHERE tmap.entity = f.entity AND tmap.terminal_code = f.pos_source AND tmap.fk_store = " . (int)$filters['store_id'] . " AND tmap.active = 1)";
    if (!empty($filters['allowed_store_ids']) && is_array($filters['allowed_store_ids'])) {
        $ids = array_map('intval', $filters['allowed_store_ids']);
        $ids = array_filter($ids);
        if (!empty($ids)) $where[] = "EXISTS (SELECT 1 FROM " . MAIN_DB_PREFIX . "takepos_terminal tmap2 WHERE tmap2.entity = f.entity AND tmap2.terminal_code = f.pos_source AND tmap2.fk_store IN (" . implode(',', $ids) . ") AND tmap2.active = 1)";
        else $where[] = '1 = 0';
    }
    if (!empty($filters['customer_id']))
        $where[] = 'f.fk_soc = ' . (int)$filters['customer_id'];
    if ($filters['invoice_status'] !== '' && $filters['invoice_status'] !== null)
        $where[] = 'f.fk_statut = ' . (int)$filters['invoice_status'];
    if (!empty($filters['payment_method']))
        $where[] = "EXISTS (SELECT 1 FROM " . MAIN_DB_PREFIX . "paiement_facture pf2 INNER JOIN " . MAIN_DB_PREFIX . "paiement p2 ON p2.rowid = pf2.fk_paiement INNER JOIN " . MAIN_DB_PREFIX . "c_paiement cp2 ON cp2.id = p2.fk_paiement WHERE pf2.fk_facture = f.rowid AND cp2.code = '" . $db->escape($filters['payment_method']) . "')";
    if ($withLine && !empty($filters['product_id']))
        $where[] = 'fd.fk_product = ' . (int)$filters['product_id'];
    if (!$withLine && !empty($filters['product_id']))
        $where[] = "EXISTS (SELECT 1 FROM " . MAIN_DB_PREFIX . "facturedet fd2 WHERE fd2.fk_facture = f.rowid AND fd2.fk_product = " . (int)$filters['product_id'] . ")";
    return implode(' AND ', $where);
}

// ── Filters ───────────────────────────────────────────────────────────────────
$filters = array(
    'date_from'      => GETPOST('date_from', 'none'),
    'date_to'        => GETPOST('date_to', 'none'),
    'cashier_id'     => GETPOSTINT('cashier_id'),
    'terminal_id'    => GETPOST('terminal_id', 'none'),
    'store_id'       => GETPOSTINT('store_id'),
    'product_id'     => GETPOSTINT('product_id'),
    'customer_id'    => GETPOSTINT('customer_id'),
    'invoice_status' => GETPOST('invoice_status', 'none'),
    'payment_method' => GETPOST('payment_method', 'none'),
);

// Store restriction
$enforceStore   = TakeposStoreService::enforceStoreRestrictionEnabled($db);
$canViewAll     = (!empty($user->admin) || TakeposUserAccess::userHasPermission($db, $user, 'takepos.store.view_all'));
if ($enforceStore && !$canViewAll) {
    $allowedIds = TakeposStoreService::getUserStoreIds($db, $entity, (int) $user->id);
    if (!empty($filters['store_id']) && !in_array((int) $filters['store_id'], $allowedIds, true)) {
        takeposApiError('STORE_ACCESS_DENIED', 'Store access denied.', 403);
    }
    $filters['allowed_store_ids'] = $allowedIds;
}

$whereLine    = reportsBuildWhere($db, $filters, $entity, true);
$whereInvoice = reportsBuildWhere($db, $filters, $entity, false);

// ── Summary ───────────────────────────────────────────────────────────────────
$summaryRows = reportsFetchRows($db,
    "SELECT COUNT(DISTINCT f.rowid) AS total_invoices,
            COALESCE(SUM(fd.qty), 0) AS total_qty,
            COALESCE(SUM(fd.total_ht), 0) AS subtotal_ht,
            COALESCE(SUM(fd.total_tva + fd.total_localtax1 + fd.total_localtax2), 0) AS total_tax,
            COALESCE(SUM((fd.subprice * fd.qty) * (fd.remise_percent / 100)), 0) AS total_discount,
            COALESCE(SUM(fd.total_ttc), 0) AS total_ttc
     FROM " . MAIN_DB_PREFIX . "facture f
     INNER JOIN " . MAIN_DB_PREFIX . "facturedet fd ON fd.fk_facture = f.rowid
     WHERE $whereLine");
$summary = !empty($summaryRows[0]) ? $summaryRows[0] : array('total_invoices'=>0,'total_qty'=>0,'subtotal_ht'=>0,'total_tax'=>0,'total_discount'=>0,'total_ttc'=>0);

// ── By Cashier ────────────────────────────────────────────────────────────────
$byCashier = reportsFetchRows($db,
    "SELECT COALESCE(NULLIF(CONCAT(COALESCE(u.firstname,''),' ',COALESCE(u.lastname,'')), ' '), u.login, CONCAT('User#',f.fk_user_author)) AS cashier_name,
            COUNT(DISTINCT f.rowid) AS invoice_count,
            COALESCE(SUM(fd.qty),0) AS total_qty,
            COALESCE(SUM(fd.total_ttc),0) AS total_ttc,
            COALESCE(SUM(fd.total_ttc)/NULLIF(COUNT(DISTINCT f.rowid),0),0) AS avg_invoice
     FROM " . MAIN_DB_PREFIX . "facture f
     INNER JOIN " . MAIN_DB_PREFIX . "facturedet fd ON fd.fk_facture = f.rowid
     LEFT JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid = f.fk_user_author
     WHERE $whereLine
     GROUP BY f.fk_user_author, cashier_name ORDER BY total_ttc DESC");

// ── By Terminal ───────────────────────────────────────────────────────────────
$byTerminal = reportsFetchRows($db,
    "SELECT f.pos_source AS terminal_id,
            COALESCE(NULLIF(tt.label,''), CONCAT('Terminal ',f.pos_source)) AS terminal_name,
            COUNT(DISTINCT f.rowid) AS invoice_count,
            COALESCE(SUM(fd.qty),0) AS total_qty,
            COALESCE(SUM(fd.total_ttc),0) AS total_ttc
     FROM " . MAIN_DB_PREFIX . "facture f
     INNER JOIN " . MAIN_DB_PREFIX . "facturedet fd ON fd.fk_facture = f.rowid
     LEFT JOIN " . MAIN_DB_PREFIX . "takepos_terminal tt ON tt.entity = f.entity AND tt.terminal_code = f.pos_source
     WHERE $whereLine
     GROUP BY f.pos_source, tt.label ORDER BY total_ttc DESC");

// ── By Product ────────────────────────────────────────────────────────────────
$byProduct = reportsFetchRows($db,
    "SELECT COALESCE(p.ref,'') AS product_ref,
            COALESCE(NULLIF(p.label,''), fd.label, fd.description, CONCAT('Product#',fd.fk_product)) AS product_label,
            COALESCE(SUM(fd.qty),0) AS total_qty,
            COALESCE(SUM(fd.total_ttc),0) AS total_ttc,
            COALESCE(SUM(fd.total_ttc)/NULLIF(SUM(fd.qty),0),0) AS avg_price
     FROM " . MAIN_DB_PREFIX . "facture f
     INNER JOIN " . MAIN_DB_PREFIX . "facturedet fd ON fd.fk_facture = f.rowid
     LEFT JOIN " . MAIN_DB_PREFIX . "product p ON p.rowid = fd.fk_product
     WHERE $whereLine
     GROUP BY fd.fk_product, product_ref, product_label ORDER BY total_ttc DESC");

// ── Detailed ──────────────────────────────────────────────────────────────────
$detailed = reportsFetchRows($db,
    "SELECT f.rowid, f.ref,
            COALESCE(f.datef, f.datec) AS invoice_date,
            COALESCE(NULLIF(CONCAT(COALESCE(u.firstname,''),' ',COALESCE(u.lastname,'')), ' '), u.login, CONCAT('User#',f.fk_user_author)) AS cashier_name,
            COALESCE(NULLIF(tt.label,''), CONCAT('Terminal ',f.pos_source)) AS terminal_name,
            COALESCE(st.label,'') AS store_name,
            COALESCE(s.nom,'') AS customer_name,
            f.total_ht, (f.total_tva + f.total_localtax1 + f.total_localtax2) AS total_tax, f.total_ttc,
            COALESCE(pm.payment_methods,'') AS payment_methods,
            f.fk_statut,
            CASE f.fk_statut WHEN 0 THEN 'Draft' WHEN 1 THEN 'Validated' WHEN 2 THEN 'Closed/Paid' ELSE 'Other' END AS status_label
     FROM " . MAIN_DB_PREFIX . "facture f
     LEFT JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid = f.fk_user_author
     LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = f.fk_soc
     LEFT JOIN " . MAIN_DB_PREFIX . "takepos_terminal tt ON tt.entity = f.entity AND tt.terminal_code = f.pos_source
     LEFT JOIN " . MAIN_DB_PREFIX . "takepos_store st ON st.entity = f.entity AND st.rowid = tt.fk_store
     LEFT JOIN (SELECT pf.fk_facture, GROUP_CONCAT(DISTINCT cp.code ORDER BY cp.code SEPARATOR ', ') AS payment_methods
                FROM " . MAIN_DB_PREFIX . "paiement_facture pf
                INNER JOIN " . MAIN_DB_PREFIX . "paiement p ON p.rowid = pf.fk_paiement
                INNER JOIN " . MAIN_DB_PREFIX . "c_paiement cp ON cp.id = p.fk_paiement
                GROUP BY pf.fk_facture) pm ON pm.fk_facture = f.rowid
     WHERE $whereInvoice
     ORDER BY COALESCE(f.datef, f.datec) DESC, f.rowid DESC
     LIMIT 500");

// ── Product Velocity ──────────────────────────────────────────────────────────
$periodFrom = (!empty($filters['date_from']) && reportsIsIsoDate($filters['date_from'])) ? strtotime($filters['date_from']) : strtotime(date('Y-m-d'));
$periodTo   = (!empty($filters['date_to'])   && reportsIsIsoDate($filters['date_to']))   ? strtotime($filters['date_to'])   : $periodFrom;
$periodDays = max(1, (int) floor(($periodTo - $periodFrom) / 86400) + 1);
$productVelocity = array();
foreach ($byProduct as $row) {
    $qty = (float) $row['total_qty'];
    $qpd = $qty / $periodDays;
    $row['qty_per_day']     = round($qpd, 4);
    $row['movement_class']  = $qpd >= 5 ? 'fast' : ($qpd >= 1 ? 'normal' : 'slow');
    $productVelocity[]      = $row;
}

// ── Cheques ───────────────────────────────────────────────────────────────────
$cheques = array();
if (reportsTableExists($db, MAIN_DB_PREFIX . 'takepos_cheque')) {
    $cWhere = array('c.entity = ' . $entity);
    if (!empty($filters['date_from']) && reportsIsIsoDate($filters['date_from'])) $cWhere[] = "c.collection_date >= '" . $db->escape($filters['date_from']) . "'";
    if (!empty($filters['date_to'])   && reportsIsIsoDate($filters['date_to']))   $cWhere[] = "c.collection_date <= '" . $db->escape($filters['date_to'])   . "'";
    $cheques = reportsFetchRows($db,
        "SELECT c.ref, c.cheque_number, COALESCE(s.nom,'') AS supplier_name, c.bank_name, c.amount, c.cheque_date, c.collection_date, c.status, " . reportsReminderSql('c.collection_date') . " AS reminder_status
         FROM " . MAIN_DB_PREFIX . "takepos_cheque c
         LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = c.fk_supplier
         WHERE " . implode(' AND ', $cWhere) . " ORDER BY c.collection_date ASC LIMIT 500");
}

// ── Receivables ───────────────────────────────────────────────────────────────
$rWhere = array('f.entity = ' . $entity, 'f.fk_statut > 0', '(f.paye = 0 OR f.total_ttc > COALESCE(pay.paid_amount, 0))');
if (!empty($filters['date_from']) && reportsIsIsoDate($filters['date_from'])) $rWhere[] = "COALESCE(f.date_lim_reglement, f.datef, f.datec) >= '" . $db->escape($filters['date_from']) . "'";
if (!empty($filters['date_to'])   && reportsIsIsoDate($filters['date_to']))   $rWhere[] = "COALESCE(f.date_lim_reglement, f.datef, f.datec) <= '" . $db->escape($filters['date_to'])   . "'";
$receivables = reportsFetchRows($db,
    "SELECT f.ref, COALESCE(s.nom,'') AS customer_name, COALESCE(f.datef,f.datec) AS invoice_date, f.date_lim_reglement AS due_date, f.total_ttc, COALESCE(pay.paid_amount,0) AS paid_amount, (f.total_ttc - COALESCE(pay.paid_amount,0)) AS remaining_amount, " . reportsReminderSql('f.date_lim_reglement') . " AS reminder_status
     FROM " . MAIN_DB_PREFIX . "facture f
     LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = f.fk_soc
     LEFT JOIN (SELECT fk_facture, SUM(amount) AS paid_amount FROM " . MAIN_DB_PREFIX . "paiement_facture GROUP BY fk_facture) pay ON pay.fk_facture = f.rowid
     WHERE " . implode(' AND ', $rWhere) . " HAVING remaining_amount > 0.00001 ORDER BY due_date ASC LIMIT 500");

// ── Payables ──────────────────────────────────────────────────────────────────
$payables = array();
if (reportsTableExists($db, MAIN_DB_PREFIX . 'facture_fourn')) {
    $pWhere = array('ff.entity = ' . $entity, 'ff.fk_statut > 0');
    if (!empty($filters['date_from']) && reportsIsIsoDate($filters['date_from'])) $pWhere[] = "COALESCE(ff.date_lim_reglement, ff.datef, ff.datec) >= '" . $db->escape($filters['date_from']) . "'";
    if (!empty($filters['date_to'])   && reportsIsIsoDate($filters['date_to']))   $pWhere[] = "COALESCE(ff.date_lim_reglement, ff.datef, ff.datec) <= '" . $db->escape($filters['date_to'])   . "'";
    $payables = reportsFetchRows($db,
        "SELECT ff.ref, COALESCE(s.nom,'') AS supplier_name, COALESCE(ff.datef,ff.datec) AS invoice_date, ff.date_lim_reglement AS due_date, ff.total_ttc, COALESCE(pay.paid_amount,0) AS paid_amount, (ff.total_ttc - COALESCE(pay.paid_amount,0)) AS remaining_amount, " . reportsReminderSql('ff.date_lim_reglement') . " AS reminder_status
         FROM " . MAIN_DB_PREFIX . "facture_fourn ff
         LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = ff.fk_soc
         LEFT JOIN (SELECT fk_facturefourn, SUM(amount) AS paid_amount FROM " . MAIN_DB_PREFIX . "paiementfourn_facturefourn GROUP BY fk_facturefourn) pay ON pay.fk_facturefourn = ff.rowid
         WHERE " . implode(' AND ', $pWhere) . " HAVING remaining_amount > 0.00001 ORDER BY due_date ASC LIMIT 500");
}

// ── Stock Moves ───────────────────────────────────────────────────────────────
$stockMoves = array();
if (reportsTableExists($db, MAIN_DB_PREFIX . 'stock_mouvement')) {
    $smWhere = array('p.entity IN (' . getEntity('product') . ')');
    if (!empty($filters['date_from']) && reportsIsIsoDate($filters['date_from'])) $smWhere[] = "sm.datem >= '" . $db->escape($filters['date_from']) . " 00:00:00'";
    if (!empty($filters['date_to'])   && reportsIsIsoDate($filters['date_to']))   $smWhere[] = "sm.datem <= '" . $db->escape($filters['date_to'])   . " 23:59:59'";
    if (!empty($filters['product_id'])) $smWhere[] = 'sm.fk_product = ' . (int)$filters['product_id'];
    $stockMoves = reportsFetchRows($db,
        "SELECT sm.datem AS movement_date, COALESCE(p.ref,'') AS product_ref, COALESCE(p.label,'') AS product_label, COALESCE(NULLIF(e.ref,''), NULLIF(e.label,''), '') AS warehouse_name, sm.value AS qty_movement, sm.type_mouvement AS movement_type, sm.label AS movement_label, sm.inventorycode, COALESCE(u.login,'') AS user_login
         FROM " . MAIN_DB_PREFIX . "stock_mouvement sm
         LEFT JOIN " . MAIN_DB_PREFIX . "product p ON p.rowid = sm.fk_product
         LEFT JOIN " . MAIN_DB_PREFIX . "entrepot e ON e.rowid = sm.fk_entrepot
         LEFT JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid = sm.fk_user_author
         WHERE " . implode(' AND ', $smWhere) . " ORDER BY sm.datem DESC LIMIT 500");
}

// ── Near Expiry ───────────────────────────────────────────────────────────────
$nearExpiry = array();
if (reportsTableExists($db, MAIN_DB_PREFIX . 'product_batch')) {
    $exprE = 'COALESCE(pb.sellby, pb.eatby)';
    $neWhere = array('p.entity IN (' . getEntity('product') . ')', "$exprE IS NOT NULL");
    if (!empty($filters['date_from']) && reportsIsIsoDate($filters['date_from'])) $neWhere[] = "$exprE >= '" . $db->escape($filters['date_from']) . " 00:00:00'";
    else $neWhere[] = "$exprE >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
    if (!empty($filters['date_to'])   && reportsIsIsoDate($filters['date_to']))   $neWhere[] = "$exprE <= '" . $db->escape($filters['date_to'])   . " 23:59:59'";
    else $neWhere[] = "$exprE <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)";
    if (!empty($filters['product_id'])) $neWhere[] = 'p.rowid = ' . (int)$filters['product_id'];
    $nearExpiry = reportsFetchRows($db,
        "SELECT COALESCE(p.ref,'') AS product_ref, COALESCE(p.label,'') AS product_label, pb.batch, COALESCE(NULLIF(e.ref,''), NULLIF(e.label,''), '') AS warehouse_name, COALESCE(pb.qty,0) AS qty, DATE($exprE) AS expiry_date, CASE WHEN $exprE < CURDATE() THEN 'expired' WHEN $exprE <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'near_expiry' ELSE 'watch' END AS expiry_status
         FROM " . MAIN_DB_PREFIX . "product_batch pb
         INNER JOIN " . MAIN_DB_PREFIX . "product_stock ps ON ps.rowid = pb.fk_product_stock
         INNER JOIN " . MAIN_DB_PREFIX . "product p ON p.rowid = ps.fk_product
         LEFT JOIN " . MAIN_DB_PREFIX . "entrepot e ON e.rowid = ps.fk_entrepot
         WHERE " . implode(' AND ', $neWhere) . " ORDER BY $exprE ASC LIMIT 500");
}

// ── Response ──────────────────────────────────────────────────────────────────
takeposApiSuccess(array(
    'summary'          => $summary,
    'by_cashier'       => $byCashier,
    'by_terminal'      => $byTerminal,
    'by_product'       => $byProduct,
    'detailed'         => $detailed,
    'product_velocity' => $productVelocity,
    'cheques'          => $cheques,
    'receivables'      => $receivables,
    'payables'         => $payables,
    'stock_moves'      => $stockMoves,
    'near_expiry'      => $nearExpiry,
), array(
    'entity'      => $entity,
    'period_days' => $periodDays,
    'filters'     => array(
        'date_from'      => $filters['date_from'],
        'date_to'        => $filters['date_to'],
        'cashier_id'     => $filters['cashier_id'],
        'terminal_id'    => $filters['terminal_id'],
        'store_id'       => $filters['store_id'],
        'product_id'     => $filters['product_id'],
        'customer_id'    => $filters['customer_id'],
        'invoice_status' => $filters['invoice_status'],
        'payment_method' => $filters['payment_method'],
    ),
));
