<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/poscore/class/PosSaasBridge.php';
header('Content-Type: application/json');
$bridge = new PosSaasBridge($db);
$bridge->ensureAjaxAccess($conf, $user, 'pos_terminal', 'poscore.cashier');
$q = trim(GETPOST('q', 'alphanohtml'));
$barcode = trim(GETPOST('barcode', 'alphanohtml'));
$sql = "SELECT p.rowid, p.ref, p.label, p.price, p.barcode, COALESCE(SUM(ps.reel), 0) as stock_qty
        FROM ".MAIN_DB_PREFIX."product as p
        LEFT JOIN ".MAIN_DB_PREFIX."product_stock as ps ON ps.fk_product = p.rowid
        WHERE p.entity = ".((int) $conf->entity)."
          AND p.tosell = 1";
if ($q !== '') {
    $esc = $db->escape($q);
    $sql .= " AND (p.ref LIKE '%{$esc}%' OR p.label LIKE '%{$esc}%' OR p.barcode LIKE '%{$esc}%')";
}
if ($barcode !== '') {
    $sql .= " AND p.barcode = '".$db->escape($barcode)."'";
}
$sql .= " GROUP BY p.rowid, p.ref, p.label, p.price, p.barcode ORDER BY p.label ASC LIMIT 30";
$resql = $db->query($sql);
if (!$resql) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'error' => $db->lasterror()));
    exit;
}
$rows = array();
while ($obj = $db->fetch_object($resql)) {
    $rows[] = array(
        'id' => (int) $obj->rowid,
        'product' => trim($obj->ref.' - '.$obj->label),
        'price' => price2num($obj->price, 'MU'),
        'stock' => price2num($obj->stock_qty, 'MU'),
        'barcode' => $obj->barcode
    );
}
echo json_encode(array('success' => true, 'rows' => $rows));
