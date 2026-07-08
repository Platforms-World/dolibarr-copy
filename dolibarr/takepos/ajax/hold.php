<?php
/**
 * TakePOS Hold Sale AJAX handler.
 *
 * Actions:
 *   hold   - Mark current draft invoice as held
 *   resume - Un-hold a held invoice (reload it as active)
 *   list   - Return list of held invoices for this terminal
 *   cancel_hold - Cancel (discard) a held invoice
 *
 * Copyright (C) 2025 TakePOS Custom
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

// Locate main.inc.php relative to this file
$mainPath = __DIR__ . '/../../main.inc.php';
if (!file_exists($mainPath)) {
    $mainPath = __DIR__ . '/../../../main.inc.php';
}
require $mainPath;

require_once DOL_DOCUMENT_ROOT . '/takepos/lib/takepos_access_guard.php';
takeposAccessGuardCurrent($db, isset($user) ? $user : null, __FILE__);
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposInputValidator.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposAudit.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposShiftService.class.php';

/**
 * @var Conf      $conf
 * @var DoliDB    $db
 * @var Translate $langs
 * @var User      $user
 */

if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
}

if (!$user->hasRight('takepos', 'run')) {
    http_response_code(403);
    echo json_encode(array('success' => false, 'error' => 'Access denied'));
    exit;
}

$action     = GETPOST('action', 'aZ09');
$invoiceid  = GETPOSTINT('invoiceid');
$label      = TakeposInputValidator::normalizeUtf8Text(GETPOST('label', 'none'), 128, true);
$terminal   = isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : 0;

/**
 * JSON output helper.
 */
function holdJsonOut($data)
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function holdValidateCsrfToken()
{
    $token = (string) GETPOST('token', 'alpha');
    $sessionToken = isset($_SESSION['newtoken']) ? (string) $_SESSION['newtoken'] : '';

    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        http_response_code(403);
        holdJsonOut(array('success' => false, 'error' => 'Invalid CSRF token'));
    }
}

/**
 * Verify that the hold table exists (created by takepos_hold_upgrade.sql migration).
 * Does NOT create the table at runtime - outputs a clear error and exits if missing.
 *
 * @param DoliDB $db
 * @return void  exits with JSON error when table is absent
 */
function checkHoldTableExists($db)
{
    // A lightweight existence probe: SELECT on an impossible condition is near-zero cost.
    $probe = $db->query("SELECT rowid FROM " . MAIN_DB_PREFIX . "takepos_held_sale WHERE 1=0");
    if ($probe === false) {
        // Table does not exist - migration has not been run yet.
        http_response_code(500);
        echo json_encode(array(
            'success' => false,
            'error'   => 'Hold feature table missing. Please run the SQL migration: takepos/sql/takepos_hold_upgrade.sql',
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function holdTableHasShiftColumn($db)
{
    static $hasShiftColumn = null;

    if ($hasShiftColumn !== null) {
        return $hasShiftColumn;
    }

    $resql = $db->query("SHOW COLUMNS FROM " . MAIN_DB_PREFIX . "takepos_held_sale LIKE 'fk_shift'");
    $hasShiftColumn = ($resql && (int) $db->num_rows($resql) > 0);
    return $hasShiftColumn;
}

function holdResolveActiveShiftId($db, $user, $entity, $terminal)
{
    // 1st: shift for this cashier on this terminal (exact match)
    $shift = TakeposShiftService::getActiveShiftForCashier($db, $entity, (int) $user->id, (int) $terminal);
    if ($shift && !empty($shift->rowid)) {
        return (int) $shift->rowid;
    }

    // 2nd: any active shift on this terminal (opened by another cashier)
    $shift = TakeposShiftService::getActiveShiftForTerminal($db, $entity, (int) $terminal);
    if ($shift && !empty($shift->rowid)) {
        return (int) $shift->rowid;
    }

    // 3rd: any active shift for this cashier on any terminal
    $shift = TakeposShiftService::getActiveShiftForCashier($db, $entity, (int) $user->id, 0);
    if ($shift && !empty($shift->rowid)) {
        return (int) $shift->rowid;
    }

    // 4th: any active shift in this entity (last resort fallback)
    $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "takepos_shift"
        . " WHERE entity = " . ((int) $entity)
        . " AND status IN ('open', 'closing_pending')"
        . " ORDER BY rowid DESC LIMIT 1";
    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        if ($obj && !empty($obj->rowid)) {
            return (int) $obj->rowid;
        }
    }

    return 0;
}

checkHoldTableExists($db);

if (in_array($action, array('hold', 'list', 'resume', 'cancel_hold'), true)) {
    holdValidateCsrfToken();
}

// ACTION: hold
if ($action === 'hold') {
    if ($invoiceid <= 0) {
        holdJsonOut(array('success' => false, 'error' => 'No invoice to hold'));
    }

    $invoice = new Facture($db);
    $res = $invoice->fetch($invoiceid);

    if ($res <= 0 || $invoice->id <= 0) {
        holdJsonOut(array('success' => false, 'error' => 'Invoice not found'));
    }
    if ($invoice->status != Facture::STATUS_DRAFT) {
        holdJsonOut(array('success' => false, 'error' => 'Only draft invoices can be held'));
    }
    if ($invoice->module_source !== 'takepos') {
        holdJsonOut(array('success' => false, 'error' => 'Not a TakePOS invoice'));
    }

    // Check invoice has at least one line (prevent holding empty invoices)
    $sqlLineCount = "SELECT COUNT(*) AS nb FROM " . MAIN_DB_PREFIX . "facturedet WHERE fk_facture = " . ((int) $invoiceid);
    $resLineCount = $db->query($sqlLineCount);
    if ($resLineCount) {
        $objLC = $db->fetch_object($resLineCount);
        if (!$objLC || (int) $objLC->nb === 0) {
            holdJsonOut(array('success' => false, 'error' => 'Cannot hold an empty invoice. Please add products first.'));
        }
    }

    // Check not already held
    $sqlCheck = "SELECT rowid FROM " . MAIN_DB_PREFIX . "takepos_held_sale"
        . " WHERE fk_invoice = " . ((int) $invoiceid)
        . " AND entity = " . ((int) $conf->entity)
        . " AND status = 1";
    $resCheck = $db->query($sqlCheck);
    if ($resCheck && $db->num_rows($resCheck) > 0) {
        holdJsonOut(array('success' => false, 'error' => 'Invoice already held'));
    }

    $now = $db->idate(dol_now());
    $hasShiftColumn = holdTableHasShiftColumn($db);
    $activeShiftId = 0;
    if ($hasShiftColumn) {
        $activeShiftId = holdResolveActiveShiftId($db, $user, $conf->entity, $terminal);
        if ($activeShiftId <= 0) {
            holdJsonOut(array('success' => false, 'error' => 'An active shift is required before holding a sale'));
        }
    }

    $insertFields = array('entity', 'fk_invoice', 'fk_terminal', 'fk_user', 'hold_label', 'date_hold', 'date_update', 'status');
    $insertValues = array(
        ((int) $conf->entity),
        ((int) $invoiceid),
        ((int) $terminal),
        ((int) $user->id),
        "'" . $db->escape($label) . "'",
        "'" . $now . "'",
        "'" . $now . "'",
        '1',
    );
    if ($hasShiftColumn) {
        $insertFields[] = 'fk_shift';
        $insertValues[] = (int) $activeShiftId;
    }

    $sqlIns = "INSERT INTO " . MAIN_DB_PREFIX . "takepos_held_sale"
        . " (" . implode(', ', $insertFields) . ")"
        . " VALUES (" . implode(', ', $insertValues) . ")";

    $resIns = $db->query($sqlIns);
    if (!$resIns) {
        holdJsonOut(array('success' => false, 'error' => 'DB error: ' . $db->lasterror()));
    }

    // Get the new hold rowid
    $heldSaleRowid = $db->last_insert_id(MAIN_DB_PREFIX . 'takepos_held_sale');

    // Rename the held invoice ref so the (PROV-POS{term}-0) slot is freed for a new invoice.
    // New ref: (HELD-POS{term}-{holdrowid}) — unique and retrievable on resume.
    $newHeldRef = '(HELD-POS' . ((int) $terminal) . '-' . ((int) $heldSaleRowid) . ')';
    $sqlRename  = "UPDATE " . MAIN_DB_PREFIX . "facture"
        . " SET ref = '" . $db->escape($newHeldRef) . "'"
        . " WHERE rowid = " . ((int) $invoiceid)
        . " AND entity IN (" . getEntity('invoice') . ")";
    $db->query($sqlRename); // non-fatal: hold is saved, rename is best-effort

    TakeposAudit::logEvent(
        $db, $user,
        'pos_hold_sale',
        TakeposAudit::SEVERITY_INFO,
        array('invoice_id' => $invoiceid, 'terminal' => $terminal, 'label' => $label, 'new_ref' => $newHeldRef),
        'Sale held/suspended'
    );

    holdJsonOut(array('success' => true, 'invoice_id' => $invoiceid, 'message' => 'Sale held successfully'));
}

// ACTION: list
if ($action === 'list') {
    $hasShiftColumn = holdTableHasShiftColumn($db);
    $activeShiftId = 0;
    if ($hasShiftColumn) {
        $activeShiftId = holdResolveActiveShiftId($db, $user, $conf->entity, $terminal);
        if ($activeShiftId <= 0) {
            holdJsonOut(array('success' => true, 'held' => array(), 'count' => 0));
        }
    }

    $sqlList = "SELECT h.rowid, h.fk_invoice, h.hold_label, h.date_hold, h.fk_user,"
        . " f.total_ttc, f.ref,"
        . " CONCAT(u.firstname, ' ', u.lastname) AS cashier_name"
        . " FROM " . MAIN_DB_PREFIX . "takepos_held_sale h"
        . " LEFT JOIN " . MAIN_DB_PREFIX . "facture f ON f.rowid = h.fk_invoice"
        . " LEFT JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid = h.fk_user"
        . " WHERE h.entity = " . ((int) $conf->entity)
        . " AND h.fk_terminal = " . ((int) $terminal)
        . " AND h.status = 1"
        . " ORDER BY h.date_hold DESC";
    if ($hasShiftColumn) {
        $sqlList = str_replace(" AND h.status = 1", " AND h.fk_shift = " . ((int) $activeShiftId) . " AND h.status = 1", $sqlList);
    } else {
        $sqlList = str_replace(" AND h.status = 1", " AND h.fk_user = " . ((int) $user->id) . " AND h.status = 1", $sqlList);
    }

    $resList = $db->query($sqlList);
    $rows = array();
    if ($resList) {
        while ($obj = $db->fetch_object($resList)) {
            // Count lines
            $sqlLines = "SELECT COUNT(*) AS nb FROM " . MAIN_DB_PREFIX . "facturedet WHERE fk_facture = " . ((int) $obj->fk_invoice);
            $resLines = $db->query($sqlLines);
            $nbLines = 0;
            if ($resLines) {
                $objL = $db->fetch_object($resLines);
                $nbLines = $objL ? (int) $objL->nb : 0;
            }

            $rows[] = array(
                'hold_id'      => (int) $obj->rowid,
                'invoice_id'   => (int) $obj->fk_invoice,
                'ref'          => $obj->ref,
                'label'        => $obj->hold_label,
                'date_hold'    => dol_print_date($db->jdate($obj->date_hold), 'dayhour'),
                'total_ttc'    => price2num($obj->total_ttc, 'MT'),
                'cashier'      => trim($obj->cashier_name),
                'nb_lines'     => $nbLines,
            );
        }
    }

    holdJsonOut(array('success' => true, 'held' => $rows, 'count' => count($rows)));
}

// ACTION: resume
if ($action === 'resume') {
    $holdid    = GETPOSTINT('hold_id');
    $currentId = GETPOSTINT('current_invoice_id'); // invoice currently on screen (to cancel if empty)

    if ($holdid <= 0) {
        holdJsonOut(array('success' => false, 'error' => 'Invalid hold_id'));
    }

    $hasShiftColumn = holdTableHasShiftColumn($db);
    $activeShiftId = 0;
    if ($hasShiftColumn) {
        $activeShiftId = holdResolveActiveShiftId($db, $user, $conf->entity, $terminal);
        if ($activeShiftId <= 0) {
            holdJsonOut(array('success' => false, 'error' => 'An active shift is required before resuming a held sale'));
        }
    }

    // Fetch hold record by hold_id + terminal only (no shift restriction - shift may differ)
    $sqlGet = "SELECT h.rowid, h.fk_invoice, h.fk_terminal"
        . " FROM " . MAIN_DB_PREFIX . "takepos_held_sale h"
        . " WHERE h.rowid = " . ((int) $holdid)
        . " AND h.entity = " . ((int) $conf->entity)
        . " AND h.status = 1";
    $resGet = $db->query($sqlGet);

    if (!$resGet || $db->num_rows($resGet) == 0) {
        holdJsonOut(array('success' => false, 'error' => 'Hold record not found or already resumed'));
    }
    $holdObj = $db->fetch_object($resGet);
    $heldInvoiceId = (int) $holdObj->fk_invoice;

    // Validate the held invoice still exists and is draft
    $invoice = new Facture($db);
    $res = $invoice->fetch($heldInvoiceId);
    if ($res <= 0) {
        $db->query("UPDATE " . MAIN_DB_PREFIX . "takepos_held_sale SET status=0, date_update='" . $db->idate(dol_now()) . "' WHERE rowid=" . ((int) $holdid));
        holdJsonOut(array('success' => false, 'error' => 'Held invoice not found in database'));
    }
    if ($invoice->status != Facture::STATUS_DRAFT) {
        $db->query("UPDATE " . MAIN_DB_PREFIX . "takepos_held_sale SET status=0, date_update='" . $db->idate(dol_now()) . "' WHERE rowid=" . ((int) $holdid));
        holdJsonOut(array('success' => false, 'error' => 'Held invoice already paid or validated'));
    }

    // If there's a current active invoice on screen that's empty, cancel it
    // (We don't force-cancel non-empty invoices; let the JS decide)
    if ($currentId > 0 && $currentId != $heldInvoiceId) {
        $currentInv = new Facture($db);
        if ($currentInv->fetch($currentId) > 0 && $currentInv->status == Facture::STATUS_DRAFT) {
            $sqlCountLines = "SELECT COUNT(*) AS nb FROM " . MAIN_DB_PREFIX . "facturedet WHERE fk_facture = " . ((int) $currentId);
            $resCount = $db->query($sqlCountLines);
            $nbCurrent = 0;
            if ($resCount) {
                $objC = $db->fetch_object($resCount);
                $nbCurrent = $objC ? (int) $objC->nb : 0;
            }
            if ($nbCurrent == 0) {
                // Safe to delete empty invoice
                $currentInv->delete($user);
            }
        }
    }

    // Extract place from the held invoice ref.
    // Ref may be (PROV-POS{term}-{place}) or (HELD-POS{term}-{holdid}) after rename.
    $heldPlace = 0;
    if (preg_match('/\(PROV-POS\d+-(\d+)\)/', $invoice->ref, $m)) {
        $heldPlace = (int) $m[1];
    } elseif (preg_match('/\(HELD-POS\d+-(\d+)\)/', $invoice->ref, $m)) {
        // ref was renamed on hold; place is 0 (non-restaurant mode)
        $heldPlace = 0;
    }

    // Restore ref to (PROV-POS{term}-{place}) so POS can use it normally again.
    $restoreRef = '(PROV-POS' . ((int) $terminal) . '-' . $heldPlace . ')';
    // Only rename if current ref is the HELD- form (avoid collision in restaurant mode)
    if (strpos($invoice->ref, '(HELD-POS') !== false) {
        $sqlRestore = "UPDATE " . MAIN_DB_PREFIX . "facture"
            . " SET ref = '" . $db->escape($restoreRef) . "'"
            . " WHERE rowid = " . ((int) $heldInvoiceId)
            . " AND entity IN (" . getEntity('invoice') . ")";
        $db->query($sqlRestore); // best-effort
        // Also update session so invoice.php can find it
        $_SESSION['takepos_user_invoice_' . (int) $user->id . '_' . (int) $terminal] = (int) $heldInvoiceId;
    }

    // Mark hold as resumed
    $sqlResume = "UPDATE " . MAIN_DB_PREFIX . "takepos_held_sale"
        . " SET status = 0, date_update = '" . $db->idate(dol_now()) . "'"
        . " WHERE rowid = " . ((int) $holdid);
    $db->query($sqlResume);

    TakeposAudit::logEvent(
        $db, $user,
        'pos_resume_sale',
        TakeposAudit::SEVERITY_INFO,
        array('invoice_id' => $heldInvoiceId, 'terminal' => $terminal, 'restored_ref' => $restoreRef),
        'Held sale resumed'
    );

    holdJsonOut(array(
        'success'    => true,
        'invoice_id' => $heldInvoiceId,
        'place'      => $heldPlace,
        'message'    => 'Sale resumed',
    ));
}

// ACTION: cancel_hold
if ($action === 'cancel_hold') {
    $holdid = GETPOSTINT('hold_id');

    if ($holdid <= 0) {
        holdJsonOut(array('success' => false, 'error' => 'Invalid hold_id'));
    }

    $hasShiftColumn = holdTableHasShiftColumn($db);
    $activeShiftId = 0;
    if ($hasShiftColumn) {
        $activeShiftId = holdResolveActiveShiftId($db, $user, $conf->entity, $terminal);
        if ($activeShiftId <= 0) {
            holdJsonOut(array('success' => false, 'error' => 'An active shift is required before cancelling a held sale'));
        }
    }

    // Fetch hold record by hold_id only (no shift restriction)
    $sqlGet = "SELECT h.fk_invoice FROM " . MAIN_DB_PREFIX . "takepos_held_sale h"
        . " WHERE h.rowid = " . ((int) $holdid)
        . " AND h.entity = " . ((int) $conf->entity)
        . " AND h.status = 1";
    $resGet = $db->query($sqlGet);

    if (!$resGet || $db->num_rows($resGet) == 0) {
        holdJsonOut(array('success' => false, 'error' => 'Hold not found'));
    }
    $holdObj = $db->fetch_object($resGet);
    $heldInvoiceId = (int) $holdObj->fk_invoice;

    // Delete the draft invoice
    $invoice = new Facture($db);
    if ($invoice->fetch($heldInvoiceId) > 0 && $invoice->status == Facture::STATUS_DRAFT) {
        $invoice->delete($user);
    }

    // Mark hold cancelled
    $db->query("UPDATE " . MAIN_DB_PREFIX . "takepos_held_sale SET status=0, date_update='" . $db->idate(dol_now()) . "' WHERE rowid=" . ((int) $holdid));

    holdJsonOut(array('success' => true, 'message' => 'Held sale cancelled'));
}

holdJsonOut(array('success' => false, 'error' => 'Unknown action: ' . $action));