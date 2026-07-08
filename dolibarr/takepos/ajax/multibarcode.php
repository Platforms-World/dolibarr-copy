<?php
/**
 * TakePos Multi-Barcode AJAX Handler
 * File: htdocs/takepos/ajax/multibarcode.php
 *
 * Actions: list | add | delete | save_pending
 *
 * The  save_pending  action is called by the product card after a new product
 * is created, passing the barcodes that were queued in the create form.
 *
 * Added fields: qty_multiplier (default 1), price_override (default NULL)
 */

if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
if (!defined('NOREQUIREMENU'))  define('NOREQUIREMENU',  '1');
if (!defined('NOREQUIREHTML'))  define('NOREQUIREHTML',  '1');
if (!defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX',  '1');

require '../../main.inc.php';

/** @var Conf   $conf
 *  @var DoliDB $db
 *  @var User   $user */

header('Content-Type: application/json; charset=UTF-8');

// ── Permission ────────────────────────────────────────────────────────────────
$canWrite = ($user->hasRight('produit', 'creer') || $user->hasRight('service', 'creer'));
$canRead  = ($user->hasRight('produit', 'lire')  || $user->hasRight('service', 'lire'));

if (!$canRead) {
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

// ── Table guard ───────────────────────────────────────────────────────────────
$table = MAIN_DB_PREFIX . 'takepos_product_barcode';
$chk   = $db->query("SHOW TABLES LIKE '" . $db->escape($table) . "'");
if (!$chk || (int) $db->num_rows($chk) === 0) {
    echo json_encode(['success' => false, 'error' => 'Run install_multibarcode.sql first']);
    exit;
}

$action        = GETPOST('action',     'aZ09');
$productId     = GETPOSTINT('product_id');
$barcodeId     = GETPOSTINT('barcode_id');
$barcode       = trim(GETPOST('barcode',        'alphanohtml'));
$label         = trim(GETPOST('label',          'alphanohtml'));
$qtyMultiplier = (float) str_replace(',', '.', trim(GETPOST('qty_multiplier', 'alpha')));
$priceOverride = trim(GETPOST('price_override', 'alpha'));

if ($qtyMultiplier <= 0) $qtyMultiplier = 1.0;
$priceOverrideSql = ($priceOverride !== '' && is_numeric(str_replace(',', '.', $priceOverride)))
    ? (float) str_replace(',', '.', $priceOverride)
    : null;

// ── Helper: duplicate check ───────────────────────────────────────────────────
function tpbcIsDuplicate($db, $table, $barcode, $productId) {
    $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "product
            WHERE barcode = '" . $db->escape($barcode) . "'
              AND entity IN (" . getEntity('product') . ")
              AND rowid != " . (int) $productId;
    $r = $db->query($sql);
    if ($r && (int) $db->num_rows($r) > 0) return 'Barcode already used by another product';

    $sql2 = "SELECT rowid FROM " . $table . "
             WHERE barcode = '" . $db->escape($barcode) . "'
               AND entity IN (" . getEntity('product') . ")
               AND fk_product != " . (int) $productId;
    $r2 = $db->query($sql2);
    if ($r2 && (int) $db->num_rows($r2) > 0) return 'Barcode already used by another product';

    return false;
}

// ── LIST ──────────────────────────────────────────────────────────────────────
if ($action === 'list' && $productId > 0) {
    $sql = "SELECT rowid, barcode, label, qty_multiplier, price_override FROM " . $table . "
            WHERE fk_product = " . (int) $productId . "
              AND entity IN (" . getEntity('product') . ")
            ORDER BY rowid ASC";
    $res  = $db->query($sql);
    $rows = [];
    while ($res && $obj = $db->fetch_object($res)) {
        $rows[] = [
            'id'             => (int) $obj->rowid,
            'barcode'        => $obj->barcode,
            'label'          => $obj->label ?? '',
            'qty_multiplier' => (float) ($obj->qty_multiplier ?? 1),
            'price_override' => isset($obj->price_override) && $obj->price_override !== null ? (float) $obj->price_override : null,
        ];
    }
    echo json_encode(['success' => true, 'barcodes' => $rows]);
    exit;
}

// ── ADD ───────────────────────────────────────────────────────────────────────
if ($action === 'add' && $canWrite && $productId > 0 && $barcode !== '') {
    $dup = tpbcIsDuplicate($db, $table, $barcode, $productId);
    if ($dup) { echo json_encode(['success' => false, 'error' => $dup]); exit; }

    $priceVal = $priceOverrideSql !== null ? "'" . (float) $priceOverrideSql . "'" : 'NULL';

    $sql = "INSERT INTO " . $table . " (fk_product, barcode, label, qty_multiplier, price_override, entity)
            VALUES (" . (int) $productId . ",
                    '" . $db->escape($barcode) . "',
                    '" . $db->escape($label)   . "',
                    " . (float) $qtyMultiplier . ",
                    " . $priceVal . ",
                    " . (int) $conf->entity . ")
            ON DUPLICATE KEY UPDATE
                label          = '" . $db->escape($label) . "',
                qty_multiplier = " . (float) $qtyMultiplier . ",
                price_override = " . $priceVal;

    $res = $db->query($sql);
    if ($res) {
        echo json_encode(['success' => true, 'id' => (int) $db->last_insert_id($table, 'rowid')]);
    } else {
        echo json_encode(['success' => false, 'error' => $db->lasterror()]);
    }
    exit;
}

// ── SAVE PENDING  (called right after new product creation) ───────────────────
if ($action === 'save_pending' && $canWrite && $productId > 0) {
    $barcodeArr  = isset($_POST['tpbc_barcode'])        && is_array($_POST['tpbc_barcode'])        ? $_POST['tpbc_barcode']        : [];
    $labelArr    = isset($_POST['tpbc_label'])          && is_array($_POST['tpbc_label'])          ? $_POST['tpbc_label']          : [];
    $qtyArr      = isset($_POST['tpbc_qty_multiplier']) && is_array($_POST['tpbc_qty_multiplier']) ? $_POST['tpbc_qty_multiplier'] : [];
    $priceArr    = isset($_POST['tpbc_price_override']) && is_array($_POST['tpbc_price_override']) ? $_POST['tpbc_price_override'] : [];

    $saved  = 0;
    $errors = [];

    foreach ($barcodeArr as $i => $bc) {
        $bc    = trim((string) $bc);
        $lbl   = trim((string) ($labelArr[$i] ?? ''));
        $qty   = (float) str_replace(',', '.', trim((string) ($qtyArr[$i] ?? '1')));
        $price = trim((string) ($priceArr[$i] ?? ''));

        if ($bc === '') continue;
        if ($qty <= 0) $qty = 1.0;
        $priceVal = ($price !== '' && is_numeric(str_replace(',', '.', $price)))
            ? "'" . (float) str_replace(',', '.', $price) . "'"
            : 'NULL';

        $dup = tpbcIsDuplicate($db, $table, $bc, $productId);
        if ($dup) { $errors[] = $bc . ': ' . $dup; continue; }

        $sql = "INSERT IGNORE INTO " . $table . " (fk_product, barcode, label, qty_multiplier, price_override, entity)
                VALUES (" . (int) $productId . ",
                        '" . $db->escape($bc)  . "',
                        '" . $db->escape($lbl) . "',
                        " . (float) $qty . ",
                        " . $priceVal . ",
                        " . (int) $conf->entity . ")";
        if ($db->query($sql)) {
            $saved++;
        }
    }

    echo json_encode(['success' => true, 'saved' => $saved, 'errors' => $errors]);
    exit;
}

// ── DELETE ────────────────────────────────────────────────────────────────────
if ($action === 'delete' && $canWrite && $barcodeId > 0 && $productId > 0) {
    $sql = "DELETE FROM " . $table . "
            WHERE rowid = " . (int) $barcodeId . "
              AND fk_product = " . (int) $productId . "
              AND entity IN (" . getEntity('product') . ")";
    echo json_encode(['success' => (bool) $db->query($sql)]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
exit;
