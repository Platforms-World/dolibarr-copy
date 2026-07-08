<?php
/**
 * product_variants.php — Piece / Box variant management for TakePOS
 *
 * Lets an admin link a "piece" product to a "box" product and specify
 * how many pieces are in one box.  Both products keep their own stock.
 * At the POS the cashier chooses "Piece" or "Box" from a popup.
 *
 * Table used: llx_takepos_product_variants
 *   rowid          INT PK AUTO_INCREMENT
 *   fk_product_piece  INT  (rowid of the single-unit product)
 *   fk_product_box    INT  (rowid of the box/carton product)
 *   units_per_box     INT  (e.g. 24)
 *   label_piece       VARCHAR(100) shown in popup  (default "Piece")
 *   label_box         VARCHAR(100) shown in popup  (default "Box")
 *   entity            INT
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once __DIR__ . '/../lib/takepos_help.php';

$langs->loadLangs(array('admin', 'products', 'cashdesk'));

restrictedArea($user, 'takepos', 0, '');
if (!$user->admin) {
    accessforbidden();
}

/**
 * Ensure the variants table exists.
 */
function takeposVariantsEnsureTable($db)
{
    $sql = "CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "takepos_product_variants (
        rowid           INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        fk_product_piece INT NOT NULL,
        fk_product_box   INT NOT NULL,
        units_per_box    INT NOT NULL DEFAULT 1,
        label_piece      VARCHAR(100) NOT NULL DEFAULT 'Piece',
        label_box        VARCHAR(100) NOT NULL DEFAULT 'Box',
        entity           INT NOT NULL DEFAULT 1,
        UNIQUE KEY uq_piece_box (fk_product_piece, fk_product_box, entity)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    $db->query($sql); // silent — already exists is fine
}

takeposVariantsEnsureTable($db);

$action  = GETPOST('action', 'aZ09');
$token   = GETPOST('token', 'alpha');
$msg     = '';
$msgType = 'mesgs';

// ── Handle form actions ────────────────────────────────────────────────────
if (!empty($action) && $token !== $_SESSION['newtoken']) {
    $action = '';
}

if ($action === 'add') {
    $pieceId     = GETPOSTINT('fk_product_piece');
    $boxId       = GETPOSTINT('fk_product_box');
    $unitsPerBox = max(1, GETPOSTINT('units_per_box'));
    $labelPiece  = trim(GETPOST('label_piece', 'alphanohtml')) ?: 'Piece';
    $labelBox    = trim(GETPOST('label_box',   'alphanohtml')) ?: 'Box';

    if ($pieceId <= 0 || $boxId <= 0) {
        $msg     = 'Please select both a piece product and a box product.';
        $msgType = 'errors';
    } elseif ($pieceId === $boxId) {
        $msg     = 'The piece product and the box product must be different.';
        $msgType = 'errors';
    } else {
        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "takepos_product_variants
                    (fk_product_piece, fk_product_box, units_per_box, label_piece, label_box, entity)
                VALUES ("
            . (int)$pieceId . ", "
            . (int)$boxId . ", "
            . (int)$unitsPerBox . ", '"
            . $db->escape($labelPiece) . "', '"
            . $db->escape($labelBox) . "', "
            . (int)$conf->entity . ")
                ON DUPLICATE KEY UPDATE
                    units_per_box = VALUES(units_per_box),
                    label_piece   = VALUES(label_piece),
                    label_box     = VALUES(label_box)";
        if ($db->query($sql)) {
            $msg = 'Variant saved successfully.';
        } else {
            $msg     = 'Database error: ' . $db->lasterror();
            $msgType = 'errors';
        }
    }
}

if ($action === 'delete') {
    $rowid = GETPOSTINT('rowid');
    if ($rowid > 0) {
        $sql = "DELETE FROM " . MAIN_DB_PREFIX . "takepos_product_variants WHERE rowid = " . (int)$rowid . " AND entity = " . (int)$conf->entity;
        $db->query($sql);
        $msg = 'Variant deleted.';
    }
}

// ── Load existing variants ─────────────────────────────────────────────────
$sql = "SELECT v.rowid, v.fk_product_piece, v.fk_product_box, v.units_per_box, v.label_piece, v.label_box,
               pp.ref AS piece_ref, pp.label AS piece_label,
               pb.ref AS box_ref,   pb.label AS box_label
        FROM " . MAIN_DB_PREFIX . "takepos_product_variants v
        LEFT JOIN " . MAIN_DB_PREFIX . "product pp ON pp.rowid = v.fk_product_piece
        LEFT JOIN " . MAIN_DB_PREFIX . "product pb ON pb.rowid = v.fk_product_box
        WHERE v.entity = " . (int)$conf->entity . "
        ORDER BY pp.ref";
$resql    = $db->query($sql);
$variants = array();
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $variants[] = $obj;
    }
}

// ── Product list for selects ───────────────────────────────────────────────
$sqlProds = "SELECT rowid, ref, label FROM " . MAIN_DB_PREFIX . "product
             WHERE entity IN (" . getEntity('product') . ") AND tosell = 1
             ORDER BY ref";
$resProds = $db->query($sqlProds);
$products = array();
if ($resProds) {
    while ($obj = $db->fetch_object($resProds)) {
        $products[] = $obj;
    }
}

// ── Page output ───────────────────────────────────────────────────────────
$head = array();
$head[0] = array(DOL_URL_ROOT . '/takepos/admin/product_variants.php', 'Piece / Box Variants', 'product_variants');

llxHeader('', 'TakePOS – Piece / Box Variants');
print dol_get_fiche_head($head, 'product_variants', 'TakePOS', -1, 'cash-register');

if ($msg) {
    if ($msgType === 'errors') {
        setEventMessages($msg, null, 'errors');
    } else {
        setEventMessages($msg, null, 'mesgs');
    }
}

print '<p style="color:#555;margin-bottom:18px">';
print 'Link a <strong>piece product</strong> with a <strong>box product</strong> and set how many pieces are in each box.<br>';
print 'At the POS, clicking either product will show a popup so the cashier can choose <em>Piece</em> or <em>Box</em>.';
print '</p>';

// ── Add form ──────────────────────────────────────────────────────────────
print '<form method="POST" action="product_variants.php">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="add">';

print '<table class="noborder centpercent" style="max-width:860px">';
print '<tr class="liste_titre">';
print '<th>Piece product</th>';
print '<th>Box product</th>';
print '<th style="width:110px">Units per box</th>';
print '<th style="width:120px">Label piece</th>';
print '<th style="width:120px">Label box</th>';
print '<th style="width:80px"></th>';
print '</tr>';
print '<tr class="oddeven">';

// Piece product select
print '<td>';
print '<select name="fk_product_piece" class="flat" style="max-width:250px">';
print '<option value="">-- select piece product --</option>';
foreach ($products as $p) {
    print '<option value="' . (int)$p->rowid . '">' . dol_escape_htmltag($p->ref . ' – ' . $p->label) . '</option>';
}
print '</select></td>';

// Box product select
print '<td>';
print '<select name="fk_product_box" class="flat" style="max-width:250px">';
print '<option value="">-- select box product --</option>';
foreach ($products as $p) {
    print '<option value="' . (int)$p->rowid . '">' . dol_escape_htmltag($p->ref . ' – ' . $p->label) . '</option>';
}
print '</select></td>';

// Units per box
print '<td><input type="number" name="units_per_box" value="24" min="1" class="flat" style="width:80px"></td>';

// Labels
print '<td><input type="text" name="label_piece" value="Piece" class="flat" style="width:100px"></td>';
print '<td><input type="text" name="label_box"   value="Box"   class="flat" style="width:100px"></td>';

print '<td><input type="submit" value="Add / Save" class="button"></td>';
print '</tr>';
print '</table>';
print '</form>';

print '<br>';

// ── Existing variants table ───────────────────────────────────────────────
if (empty($variants)) {
    print '<p style="color:#888"><em>No piece/box variants configured yet.</em></p>';
} else {
    print '<table class="noborder centpercent" style="max-width:860px">';
    print '<tr class="liste_titre">';
    print '<th>Piece product</th>';
    print '<th>Box product</th>';
    print '<th style="width:110px">Units per box</th>';
    print '<th style="width:120px">Piece label</th>';
    print '<th style="width:120px">Box label</th>';
    print '<th style="width:60px">Delete</th>';
    print '</tr>';
    foreach ($variants as $v) {
        print '<tr class="oddeven">';
        print '<td>' . dol_escape_htmltag($v->piece_ref . ' – ' . $v->piece_label) . '</td>';
        print '<td>' . dol_escape_htmltag($v->box_ref   . ' – ' . $v->box_label)   . '</td>';
        print '<td style="text-align:center">' . (int)$v->units_per_box . '</td>';
        print '<td>' . dol_escape_htmltag($v->label_piece) . '</td>';
        print '<td>' . dol_escape_htmltag($v->label_box)   . '</td>';
        print '<td style="text-align:center">';
        print '<a href="product_variants.php?action=delete&rowid=' . (int)$v->rowid . '&token=' . newToken() . '"';
        print ' onclick="return confirm(\'Delete this variant?\')"';
        print ' style="color:red">✕</a>';
        print '</td>';
        print '</tr>';
    }
    print '</table>';
}

print dol_get_fiche_end();
llxFooter();
$db->close();
