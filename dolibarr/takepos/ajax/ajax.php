<?php
/* Copyright (C) 2001-2004	Andreu Bisquerra	<jove@bisquerra.com>
 * Copyright (C) 2020		Thibault FOUCART	<support@ptibogxiv.net>
 * Copyright (C) 2024       Frederic France              <frederic.france@free.fr>
 * Copyright (C) 2025		MDW						<mdeweerd@users.noreply.github.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/takepos/ajax/ajax.php
 *	\brief      Ajax search component for TakePos. It search products of a category.
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

// Load Dolibarr environment
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/lib/takepos_lang.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once DOL_DOCUMENT_ROOT . '/takepos/lib/takepos_access_guard.php';
takeposAccessGuardCurrent($db, isset($user) ? $user : null, __FILE__);
// Load $user and permissions
require_once __DIR__.'/../class/TakeposAccess.class.php';
require_once __DIR__ . '/../class/TakeposInputValidator.class.php';
require_once __DIR__ . '/../class/TakeposUtf8.class.php';
require_once __DIR__ . '/../class/TakeposShiftService.class.php';
require_once __DIR__ . '/../class/TakeposProductImageService.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT."/product/class/product.class.php";
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

TakeposUtf8::bootstrapConnection($db);

function takeposJsonOutput($payload)
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function takeposTableExists($db, $tableName)
{
    $resql = $db->query("SHOW TABLES LIKE '".$db->escape($tableName)."'");
    if (!$resql) {
        return false;
    }
    return ((int) $db->num_rows($resql) > 0);
}

function takeposBuildProductSearchRow($db, $productData, $pricelevel, $matchedBarcode = '', $qty = 1, $priceOverrideTtc = null)
{
    global $conf, $langs;

    $objProd = new Product($db);
    $objProd->fetch((int) $productData->rowid);
    $ig = TakeposProductImageService::buildProductImageUrl((int) $productData->rowid);

    $priceHt = empty($objProd->multiprices[$pricelevel]) ? $productData->price : $objProd->multiprices[$pricelevel];
    $priceTtc = empty($objProd->multiprices_ttc[$pricelevel]) ? $productData->price_ttc : $objProd->multiprices_ttc[$pricelevel];

    // Multi-barcode price override: a per-barcode TTC price wins over the normal
    // (and multiprice) product price. HT is derived from the product VAT rate.
    if ($priceOverrideTtc !== null && (float) $priceOverrideTtc > 0) {
        $priceTtc = (float) $priceOverrideTtc;
        $tva_tx   = isset($objProd->tva_tx) ? (float) $objProd->tva_tx : 0;
        $priceHt  = ($tva_tx > 0) ? ($priceTtc / (1 + $tva_tx / 100)) : $priceTtc;
    }

    return array(
        'id' => (int) $productData->rowid,
        'rowid' => $productData->rowid,
        'ref' => $productData->ref,
        'label' => $productData->label,
        'tosell' => $productData->tosell,
        'tobuy' => $productData->tobuy,
        'barcode' => $productData->barcode,
        'price' => $priceHt,
        'price_ttc' => $priceTtc,
        'object' => 'product',
        'img' => $ig,
        'image_url' => $ig,
        'has_image' => TakeposProductImageService::hasProductImage($db, (int) $productData->rowid) ? 1 : 0,
        'qty' => $qty,
        'price_formated' => price(price2num($priceHt, 'MT'), 1, $langs, 1, -1, -1, $conf->currency),
        'price_ttc_formated' => price(price2num($priceTtc, 'MT'), 1, $langs, 1, -1, -1, $conf->currency),
        'matched_barcode' => (string) $matchedBarcode,
    );
}

$category = GETPOST('category', 'alphanohtml'); // Can be id of category or supplements
$action = GETPOST('action', 'aZ09');
$term = GETPOST('term', 'aZ09');
$searchTermRaw = GETPOST('search_term', 'none');
$search_term = TakeposInputValidator::normalizeUtf8Text($searchTermRaw, 190, true);

// ── Branch user product isolation ────────────────────────────────────────────
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposBranchService.class.php';
$takeposBranchProductIds       = TakeposBranchService::allowedProductIdsForUser($db, $user);
$takeposBranchProductSqlFilter = '';
if (is_array($takeposBranchProductIds)) {
    if (empty($takeposBranchProductIds)) {
        $takeposBranchProductSqlFilter = " AND 1=0";
    } else {
        $takeposBranchProductSqlFilter = " AND p.rowid IN (" . implode(',', array_map('intval', $takeposBranchProductIds)) . ")";
    }
}
$id = GETPOSTINT('id');
$search_start = GETPOSTINT('search_start');
$search_limit = GETPOSTINT('search_limit');

if (!$user->hasRight('takepos', 'run')) {
    accessforbidden();
}

/*
 * View
 */

$thirdparty = new Societe($db);

if ($action == 'checkfeature' && $user->hasRight('takepos', 'run')) {
    top_httphead('application/json');
    $featureCode = TakeposInputValidator::normalizeUtf8Text(GETPOST('feature_code', 'none'), 128, true);
    $allowed = true;

    try {
        if ($featureCode !== '') {
            $allowed = TakeposAccess::isFeatureEnabled($db, $featureCode);
        }
    } catch (Exception $e) {
        $allowed = false;
    }

    takeposJsonOutput(array('allowed' => ($allowed ? 1 : 0), 'feature_code' => $featureCode));
    exit;
}

TakeposAccess::enforceFrontend($db, isset($user) ? $user : null, 'takepos.frontend', isset($_SESSION['takeposterminal']) ? $_SESSION['takeposterminal'] : null);

function takeposAjaxRequireOpenShiftForSensitiveAction($db, $user, $action, $invoiceId = 0)
{
    $protectedActions = array('getInvoice'); // Product search must stay available without an open shift; mutating invoice actions remain protected.
    if (!in_array((string) $action, $protectedActions, true)) {
        return;
    }

    list($allowed, $message) = TakeposShiftService::enforceSaleShiftRequirement($db, $user, isset($_SESSION['takeposterminal']) ? $_SESSION['takeposterminal'] : '', (int) $invoiceId);
    if ($allowed) {
        return;
    }

    if (!headers_sent()) {
        http_response_code(403);
    }

    if ((string) $action === 'getInvoice') {
        print dol_escape_htmltag((string) $message);
    } else {
        takeposJsonOutput(array('error' => 'shift_required', 'message' => (string) $message, 'items' => array()));
    }
    exit;
}

$invoiceIdForShiftGate = GETPOSTINT('id');
if ($invoiceIdForShiftGate <= 0) {
    $invoiceIdForShiftGate = GETPOSTINT('invoiceid');
}
takeposAjaxRequireOpenShiftForSensitiveAction($db, $user, $action, $invoiceIdForShiftGate);

// Initialize a technical object to manage hooks. Note that conf->hooks_modules contains array of hooks
$hookmanager->initHooks(array('takeposproductsearch')); // new context for product search hooks

$pricelevel = 1;	// default price level if PRODUIT_MULTIPRICES. TODO Get price level from thirdparty.
if ($action == 'getProducts' && $user->hasRight('takepos', 'run')) {
    $tosell = GETPOSTISSET('tosell') ? GETPOSTINT('tosell') : '';
    $limit = GETPOSTISSET('limit') ? GETPOSTINT('limit') : 0;
    $offset = GETPOSTISSET('offset') ? GETPOSTINT('offset') : 0;

    top_httphead('application/json');

    // Search
    if (GETPOSTINT('thirdpartyid') > 0) {
        $result = $thirdparty->fetch(GETPOSTINT('thirdpartyid'));
        if ($result > 0) {
            $pricelevel = $thirdparty->price_level;
        }
    }

    $object = new Categorie($db);
    if ($category == "supplements") {
        $category = getDolGlobalInt('TAKEPOS_SUPPLEMENTS_CATEGORY');
        if (empty($category)) {
            echo 'Error, the category to use for supplements is not defined. Go into setup of module TakePOS.';
            exit;
        }
    }

    // FIX (all-products-v2): When category=0 (the "All" tab), return all categorized
    // products with proper limit/offset so LoadProducts() pagination works normally.
    // Only products assigned to at least one category are returned, matching the
    // "All 118" badge count shown in the category tab strip.
    if ((int) $category === 0 || $category === '' || $category === '0') {
        $res = array();
        $_entity = !empty($conf->entity) ? (int) $conf->entity : 1;
        $sqlAll  = "SELECT DISTINCT p.rowid, p.ref, p.label, p.tosell, p.tobuy, p.barcode, p.price, p.price_ttc";
        $sqlAll .= " FROM " . MAIN_DB_PREFIX . "product AS p";
        if (is_array($takeposBranchProductIds) && !empty($takeposBranchProductIds)) {
            // Branch user with a warehouse-derived product list: do NOT require the
            // product to be in a category — show all products from the warehouse,
            // whether or not they've been added to a POS category.
            $sqlAll .= " WHERE p.tosell = 1";
            $sqlAll .= " AND p.rowid IN (" . implode(',', array_map('intval', $takeposBranchProductIds)) . ")";
        } else {
            // Master/admin: only show products that belong to at least one category
            // (matches legacy behaviour and the category pill badge counts).
            $sqlAll .= " INNER JOIN " . MAIN_DB_PREFIX . "categorie_product AS cp ON cp.fk_product = p.rowid";
            $sqlAll .= " WHERE p.tosell = 1";
            $sqlAll .= " AND p.entity = " . $_entity;
        }
        $sortField = getDolGlobalString('TAKEPOS_SORTPRODUCTFIELD') ?: 'p.ref';
        $sqlAll .= " ORDER BY " . $db->sanitize($sortField) . " ASC";
        // Apply limit/offset for pagination (exactly as getProducts does for normal categories)
        if ((int) $limit > 0) {
            $sqlAll .= $db->plimit((int) $limit + 1, (int) $offset);
        }
        $resAll = $db->query($sqlAll);
        if ($resAll) {
            // FIX (M3): Direct row mapping — no Product::fetch() per row.
            while ($obj = $db->fetch_object($resAll)) {
                $ig = TakeposProductImageService::buildProductImageUrl((int) $obj->rowid);
                $entry = new stdClass();
                $entry->id           = (int) $obj->rowid;
                $entry->rowid        = (int) $obj->rowid;
                $entry->ref          = (string) $obj->ref;
                $entry->label        = (string) $obj->label;
                $entry->tosell       = (int) $obj->tosell;
                $entry->tobuy        = isset($obj->tobuy) ? (int) $obj->tobuy : 0;
                $entry->barcode      = isset($obj->barcode) ? (string) $obj->barcode : null;
                $entry->price        = (float) price2num($obj->price, 'MT');
                $entry->price_ttc    = (float) price2num($obj->price_ttc, 'MT');
                $entry->img          = $ig;
                $entry->image_url    = $ig;
                $entry->has_image    = TakeposProductImageService::hasProductImage($db, (int) $obj->rowid) ? 1 : 0;
                $entry->price_formated     = price(price2num($obj->price, 'MT'), 1, $langs, 1, -1, -1, $conf->currency);
                $entry->price_ttc_formated = price(price2num($obj->price_ttc, 'MT'), 1, $langs, 1, -1, -1, $conf->currency);
                $res[] = $entry;
            }
        }
        takeposJsonOutput($res);
        exit;
    }

    $result = $object->fetch($category);
    if ($result > 0) {
        $filter = '';
        if ($tosell != '') {
            $filter = '(o.tosell:=:'.((int) $tosell).')';
        }

        // FIX: For branch users, use a direct SQL query that applies the branch
        // product filter at DB level instead of PHP-looping getObjectsInCateg().
        // The PHP loop approach caused the JS cache to store [] (empty) on first
        // load before the entity fix was applied, and the empty result was served
        // forever from cache. Direct SQL is also faster.
        if (is_array($takeposBranchProductIds) && !empty($takeposBranchProductIds)) {
            $branchProductsInCateg = array();
            $safeIds = implode(',', array_map('intval', $takeposBranchProductIds));
            $sqlBranch  = "SELECT p.rowid, p.ref, p.label, p.tosell, p.tobuy, p.barcode, p.price, p.price_ttc";
            $sqlBranch .= " FROM " . MAIN_DB_PREFIX . "product AS p";
            $sqlBranch .= " INNER JOIN " . MAIN_DB_PREFIX . "categorie_product AS cp ON cp.fk_product = p.rowid";
            $sqlBranch .= " WHERE cp.fk_categorie = " . ((int) $category);
            $sqlBranch .= " AND p.tosell = 1";
            $sqlBranch .= " AND p.rowid IN (" . $safeIds . ")";
            $sortField = getDolGlobalString('TAKEPOS_SORTPRODUCTFIELD') ?: 'p.ref';
            $sqlBranch .= " ORDER BY " . $db->sanitize($sortField) . " ASC";
            // FIX (category-pagination): request limit+1 so the JS gets enough rows
            // to fill `maxproduct` tiles AND detect a next page. Mirrors "All" branch.
            if ($limit > 0) {
                $sqlBranch .= $db->plimit((int) $limit + 1, (int) $offset);
            }
            $resBranch = $db->query($sqlBranch);
            $res = array();
            // FIX (M3): Replaced Product::fetch() loop (1 ORM call per product = N+1
            // queries) with direct row mapping. All needed fields are already in $obj
            // from the SQL query above — no additional DB round-trip required.
            if ($resBranch) {
                while ($obj = $db->fetch_object($resBranch)) {
                    $ig = TakeposProductImageService::buildProductImageUrl((int) $obj->rowid);
                    $entry = new stdClass();
                    $entry->id           = (int) $obj->rowid;
                    $entry->rowid        = (int) $obj->rowid;
                    $entry->ref          = (string) $obj->ref;
                    $entry->label        = (string) $obj->label;
                    $entry->tosell       = (int) $obj->tosell;
                    $entry->tobuy        = (int) $obj->tobuy;
                    $entry->barcode      = isset($obj->barcode) ? (string) $obj->barcode : null;
                    $entry->price        = (float) price2num($obj->price, 'MT');
                    $entry->price_ttc    = (float) price2num($obj->price_ttc, 'MT');
                    $entry->img          = $ig;
                    $entry->image_url    = $ig;
                    $entry->has_image    = TakeposProductImageService::hasProductImage($db, (int) $obj->rowid) ? 1 : 0;
                    $entry->price_formated     = price(price2num($obj->price, 'MT'), 1, $langs, 1, -1, -1, $conf->currency);
                    $entry->price_ttc_formated = price(price2num($obj->price_ttc, 'MT'), 1, $langs, 1, -1, -1, $conf->currency);
                    $res[] = $entry;
                }
            }
            takeposJsonOutput($res);
        } elseif (is_array($takeposBranchProductIds) && empty($takeposBranchProductIds)) {
            // Branch exists but has zero products assigned
            takeposJsonOutput(array());
        } else {
            // Master/admin — use direct SQL instead of $object->getObjectsInCateg().
            //
            // FIX (category-count-mismatch-v3): The pill counter on the category strip
            // and getObjectsInCateg disagree on how many products belong to a category.
            // Symptoms: pill says "BAKERY 11" but the AJAX returns only 9 products,
            // so 2 products are silently invisible AND the page counter shows "1 / 1".
            //
            // Root cause: takeposGetCategoryProductCounts (pill badge) counts rows in
            // categorie_product joined with product on entity + tosell=1.
            // getObjectsInCateg applies additional silent filters in some core
            // versions (sub-entity expansion, visibility flags, collation edge cases)
            // that drop rows the count function kept.
            //
            // Fix: use the same SQL shape as the "All" and branch-user branches so all
            // paths agree on counts.
            //
            // FIX (collation-crash-v5): $prod->fetch() can hit llx_ecm_files with the
            // product ref as filepath. If llx_ecm_files uses a different collation
            // than llx_product (e.g. latin1_swedish_ci vs utf8mb4_unicode_ci) AND the
            // ref contains non-ASCII characters (Arabic etc.), MariaDB throws
            // DB_ERROR_1267 "Illegal mix of collations" and Dolibarr prints an HTML
            // error page that corrupts the JSON response — symptom: AJAX returns
            // garbage or [] and the grid shows stale data from a previous click.
            //
            // We can't fix the collation from here (that's a DB ALTER), so we:
            //   1) wrap each row in output buffering — error HTML is captured & dropped
            //   2) wrap fetch() in try/catch — if one product crashes, skip it
            // Net effect: bad-ref products are silently skipped, the JSON stays valid,
            // and the grid updates correctly.
            // FIX (master-category-no-fetch): Eliminated per-row $prod->fetch() loop
            // which caused silent product loss in two scenarios:
            //   1) TAKEPOS_PRODUCT_IN_STOCK=1 — fetch() then load_stock() was used to
            //      check stock, but this caused N+1 DB queries and any collation error
            //      in llx_ecm_files triggered a PHP Throwable that marked $rowOk=false,
            //      silently dropping the product from the result.
            //   2) Collation mismatch (latin1 vs utf8mb4) on product refs containing
            //      Arabic/non-ASCII characters — fetch() hit llx_ecm_files JOIN and
            //      crashed, the ob_end_clean() swallowed the error, product vanished.
            //
            // New approach: handle TAKEPOS_PRODUCT_IN_STOCK via SQL JOIN on
            // llx_product_stock so the DB does the filtering in one query.
            // Multi-price support uses a secondary per-row query only when pricelevel > 1.
            $res = array();
            $_entity = !empty($conf->entity) ? (int) $conf->entity : 1;
            $sqlCat  = "SELECT DISTINCT p.rowid, p.ref, p.label, p.tosell, p.tobuy, p.barcode, p.price, p.price_ttc";
            $sqlCat .= " FROM " . MAIN_DB_PREFIX . "product AS p";
            $sqlCat .= " INNER JOIN " . MAIN_DB_PREFIX . "categorie_product AS cp ON cp.fk_product = p.rowid";

            // FIX (stock-filter-in-sql): When TAKEPOS_PRODUCT_IN_STOCK=1, filter at DB
            // level via JOIN instead of PHP loop + load_stock(). This eliminates N+1
            // queries and prevents silent product loss from fetch() collation crashes.
       /*     if (getDolGlobalInt('TAKEPOS_PRODUCT_IN_STOCK') == 1) {
                $sqlCat .= " INNER JOIN " . MAIN_DB_PREFIX . "product_stock AS ps ON ps.fk_product = p.rowid AND ps.reel > 0";
            }*/

            $sqlCat .= " WHERE cp.fk_categorie = " . ((int) $category);
            $sqlCat .= " AND p.entity = " . $_entity;
            if ($tosell != '') {
                $sqlCat .= " AND p.tosell = " . ((int) $tosell);
            }
            $sortField = getDolGlobalString('TAKEPOS_SORTPRODUCTFIELD') ?: 'p.ref';
            $sqlCat .= " ORDER BY " . $db->sanitize($sortField) . " ASC";
            if ((int) $limit > 0) {
                $sqlCat .= $db->plimit((int) $limit + 1, (int) $offset);
            }
            $resCat = $db->query($sqlCat);
            if ($resCat) {
                while ($obj = $db->fetch_object($resCat)) {
                    $ig = TakeposProductImageService::buildProductImageUrl((int) $obj->rowid);
                    $entry = new stdClass();
                    $entry->id           = (int) $obj->rowid;
                    $entry->rowid        = (int) $obj->rowid;
                    $entry->ref          = (string) $obj->ref;
                    $entry->label        = (string) $obj->label;
                    $entry->tosell       = (int) $obj->tosell;
                    $entry->tobuy        = (int) $obj->tobuy;
                    $entry->barcode      = isset($obj->barcode) ? (string) $obj->barcode : null;
                    $entry->price        = (float) price2num($obj->price, 'MT');
                    $entry->price_ttc    = (float) price2num($obj->price_ttc, 'MT');
                    $entry->img          = $ig;
                    $entry->image_url    = $ig;
                    $entry->has_image    = TakeposProductImageService::hasProductImage($db, (int) $obj->rowid) ? 1 : 0;
                    $entry->price_formated     = price(price2num($obj->price, 'MT'), 1, $langs, 1, -1, -1, $conf->currency);
                    $entry->price_ttc_formated = price(price2num($obj->price_ttc, 'MT'), 1, $langs, 1, -1, -1, $conf->currency);
                    $res[] = $entry;
                }
            }
            takeposJsonOutput($res);
        } // end master/admin else
    } else {
        echo 'Failed to load category with id='.dol_escape_htmltag($category);
    }
} elseif ($action == 'getFavoriteProducts' && $user->hasRight('takepos', 'run')) {
    // Returns product data for a specific list of IDs (used by the Favorites view).
    // Accepts: ids=1,2,3 (comma-separated product rowids, max 200)
    top_httphead('application/json');
    $rawIds = GETPOST('ids', 'alphanohtml');
    $idList = array();
    foreach (explode(',', $rawIds) as $v) {
        $v = trim($v);
        if (ctype_digit($v) && (int)$v > 0) $idList[] = (int)$v;
    }
    if (empty($idList)) { echo '[]'; exit; }
    // Safety cap
    $idList = array_slice($idList, 0, 200);

    $_entity = !empty($conf->entity) ? (int)$conf->entity : 1;
    $sql  = "SELECT DISTINCT p.rowid, p.ref, p.label, p.tosell, p.tobuy, p.barcode, p.price, p.price_ttc";
    $sql .= " FROM " . MAIN_DB_PREFIX . "product AS p";
    $sql .= " WHERE p.rowid IN (" . implode(',', $idList) . ")";
    $sql .= " AND p.tosell = 1";
    $sql .= " AND p.entity = " . $_entity;

    $res = array();
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $ig = TakeposProductImageService::buildProductImageUrl((int)$obj->rowid);
            $entry = new stdClass();
            $entry->id           = (int)$obj->rowid;
            $entry->rowid        = (int)$obj->rowid;
            $entry->ref          = (string)$obj->ref;
            $entry->label        = (string)$obj->label;
            $entry->tosell       = (int)$obj->tosell;
            $entry->tobuy        = isset($obj->tobuy) ? (int)$obj->tobuy : 0;
            $entry->barcode      = isset($obj->barcode) ? (string)$obj->barcode : null;
            $entry->price        = (float)price2num($obj->price, 'MT');
            $entry->price_ttc    = (float)price2num($obj->price_ttc, 'MT');
            $entry->img          = $ig;
            $entry->image_url    = $ig;
            $entry->has_image    = TakeposProductImageService::hasProductImage($db, (int)$obj->rowid) ? 1 : 0;
            $entry->price_formated     = price(price2num($obj->price, 'MT'), 1, $langs, 1, -1, -1, $conf->currency);
            $entry->price_ttc_formated = price(price2num($obj->price_ttc, 'MT'), 1, $langs, 1, -1, -1, $conf->currency);
            $res[] = $entry;
        }
    }
    takeposJsonOutput($res);
    exit;

} elseif ($action == 'getProductsAll' && $user->hasRight('takepos', 'run')) {
    // Returns ALL products visible to this user (branch-filtered or all for master).
    // Used by the "All" category tab to show every assigned product in one request.
    top_httphead('application/json');

    $res = array();

    if (is_array($takeposBranchProductIds) && empty($takeposBranchProductIds)) {
        // Branch with no products assigned — but first try re-fetching without entity filter
        // in case the entity repair in getBranchProductIdsById hasn't run yet
        $branch = TakeposBranchService::getBranchByUserId($db, (int) $user->id);
        if ($branch) {
            // FIX (M4): Read-only fallback — no longer mutates data during a GET request.
            // Entity repair (setting the correct entity on branch_product rows) must be done
            // via the TakeposMigration or admin tools, not silently inside a product search.
            // If rows exist without an entity filter they are returned as-is, with a syslog
            // warning so the admin knows to run the migration.
            $rawRes = $db->query(
                'SELECT fk_product FROM ' . MAIN_DB_PREFIX . 'takepos_branch_product'
                . ' WHERE fk_branch=' . (int)$branch->rowid
            );
            $rawIds = array();
            if ($rawRes) { while ($o = $db->fetch_object($rawRes)) { $rawIds[] = (int)$o->fk_product; } }
            if (!empty($rawIds)) {
                dol_syslog('[TakePOS] Branch product rows missing entity for branch '
                    . (int)$branch->rowid . ' — run TakeposMigration to repair. Using raw IDs as fallback.',
                    LOG_WARNING);
                $takeposBranchProductIds = $rawIds;
            }
        }
        if (empty($takeposBranchProductIds)) {
            takeposJsonOutput($res); // no products assigned to this branch
            exit;
        }
    } else {
        // Use direct entity value instead of getEntity() — getEntity() may expand
        // to a multi-entity subquery that misses the current entity in some configs.
        $_entity = !empty($conf->entity) ? (int) $conf->entity : 1;
        $sqlAll  = "SELECT p.rowid, p.ref, p.label, p.tosell, p.tobuy, p.barcode, p.price, p.price_ttc";
        $sqlAll .= " FROM " . MAIN_DB_PREFIX . "product AS p";
        $sqlAll .= " WHERE p.tosell = 1";
        if (is_array($takeposBranchProductIds) && !empty($takeposBranchProductIds)) {
            // Branch user: filter to assigned products only (entity already guaranteed by assignment)
            $sqlAll .= " AND p.rowid IN (" . implode(',', array_map('intval', $takeposBranchProductIds)) . ")";
        } else {
            // Master: filter by entity
            $sqlAll .= " AND p.entity = " . $_entity;
        }
        $sortField = getDolGlobalString('TAKEPOS_SORTPRODUCTFIELD') ?: 'p.ref';
        $sqlAll .= " ORDER BY " . $db->sanitize($sortField) . " ASC";

        // FIX (M3): Direct row mapping — no Product::fetch() per row.
        $resAll = $db->query($sqlAll);
        if ($resAll) {
            while ($obj = $db->fetch_object($resAll)) {
                $ig = TakeposProductImageService::buildProductImageUrl((int) $obj->rowid);
                $entry = new stdClass();
                $entry->id           = (int) $obj->rowid;
                $entry->rowid        = (int) $obj->rowid;
                $entry->ref          = (string) $obj->ref;
                $entry->label        = (string) $obj->label;
                $entry->tosell       = (int) $obj->tosell;
                $entry->tobuy        = isset($obj->tobuy) ? (int) $obj->tobuy : 0;
                $entry->barcode      = isset($obj->barcode) ? (string) $obj->barcode : null;
                $entry->price        = (float) price2num($obj->price, 'MT');
                $entry->price_ttc    = (float) price2num($obj->price_ttc, 'MT');
                $entry->img          = $ig;
                $entry->image_url    = $ig;
                $entry->has_image    = TakeposProductImageService::hasProductImage($db, (int) $obj->rowid) ? 1 : 0;
                $entry->price_formated     = price(price2num($obj->price, 'MT'), 1, $langs, 1, -1, -1, $conf->currency);
                $entry->price_ttc_formated = price(price2num($obj->price_ttc, 'MT'), 1, $langs, 1, -1, -1, $conf->currency);
                $res[] = $entry;
            }
        }
        takeposJsonOutput($res);
    } // end else (has products)
} elseif ($action == 'search' && $search_term != '' && $user->hasRight('takepos', 'run')) {
    top_httphead('application/json');

    // EAN check digit for scale barcodes is handled inside the supermarket block below

    // Search barcode into third parties. If found, it means we want to change third parties.
    $result = $thirdparty->fetch(0, '', '', $search_term);

    if ($result && $thirdparty->id > 0) {
        $rows = array();
        $rows[] = array(
            'rowid' => $thirdparty->id,
            'name' => $thirdparty->name,
            'barcode' => $thirdparty->barcode,
            'object' => 'thirdparty'
        );
        takeposJsonOutput($rows);
        exit;
    }

    // Search
    if (GETPOSTINT('thirdpartyid') > 0) {
        $result = $thirdparty->fetch(GETPOSTINT('thirdpartyid'));
        if ($result > 0) {
            $pricelevel = $thirdparty->price_level;
        }
    }

    // Define $filteroncategids, the filter on category ID if there is a Root category defined.
    $filteroncategids = '';
    if (getDolGlobalInt('TAKEPOS_ROOT_CATEGORY_ID') > 0) {	// A root category is defined, we must filter on products inside this category tree
        $object = new Categorie($db);
        //$result = $object->fetch($conf->global->TAKEPOS_ROOT_CATEGORY_ID);
        $arrayofcateg = $object->get_full_arbo('product', getDolGlobalInt('TAKEPOS_ROOT_CATEGORY_ID'), 1);
        if (is_array($arrayofcateg) && count($arrayofcateg) > 0) {
            foreach ($arrayofcateg as $val) {
                $filteroncategids .= ($filteroncategids ? ', ' : '').$val['id'];
            }
        }
    }
    // Supermarket embedded barcode support (weighted / priced / quantity labels)
    $superEnabled = (int) getDolGlobalInt('TAKEPOS_SUPERMARKET_BARCODE_ENABLE');
    if ($superEnabled > 0 && is_numeric($search_term)) {
        $superPrefixes = array(
            'weight' => getDolGlobalString('TAKEPOS_SUPERMARKET_WEIGHT_PREFIX', '21'),
            'price' => getDolGlobalString('TAKEPOS_SUPERMARKET_PRICE_PREFIX', '22'),
            'quantity' => getDolGlobalString('TAKEPOS_SUPERMARKET_QUANTITY_PREFIX', '23'),
        );

        $productCodeLen = (int) getDolGlobalInt('TAKEPOS_SUPERMARKET_PRODUCT_CODE_LEN');
        if (empty($productCodeLen)) $productCodeLen = 5;

        $valueLen = (int) getDolGlobalInt('TAKEPOS_SUPERMARKET_VALUE_LEN');
        if (empty($valueLen)) $valueLen = 5;

        $ignoreCheckDigit = (int) getDolGlobalInt('TAKEPOS_SUPERMARKET_IGNORE_CHECK_DIGIT');

        $weightDivisor = (float) price2num(getDolGlobalString('TAKEPOS_SUPERMARKET_WEIGHT_DIVISOR', '1000'));
        if (empty($weightDivisor)) $weightDivisor = 1000;

        $priceDivisor = (float) price2num(getDolGlobalString('TAKEPOS_SUPERMARKET_PRICE_DIVISOR', '100'));
        if (empty($priceDivisor)) $priceDivisor = 100;

        $quantityDivisor = (float) price2num(getDolGlobalString('TAKEPOS_SUPERMARKET_QUANTITY_DIVISOR', '1'));
        if (empty($quantityDivisor)) $quantityDivisor = 1;

        $trimmedSearch = $search_term;
        $minExpectedLength = 2 + $productCodeLen + $valueLen;

        // If barcode has trailing check digit: validate it first, then ignore it
        if (dol_strlen($trimmedSearch) == ($minExpectedLength + 1)) {
            // تحقق من الـ check digit بمعادلة EAN-13
            $eanDigits = str_split(substr($trimmedSearch, 0, $minExpectedLength));
            $eanSum = 0;
            foreach ($eanDigits as $eanPos => $eanD) {
                $eanSum += ($eanPos % 2 === 0) ? (int)$eanD : (int)$eanD * 3;
            }
            $eanExpected = (10 - ($eanSum % 10)) % 10;
            $eanActual   = (int) substr($trimmedSearch, -1);
            if ($eanActual !== $eanExpected) {
                takeposJsonOutput(array(array(
                    'object'  => 'error',
                    'error'   => 'invalid_check_digit',
                    'message' => 'Invalid scale barcode: check digit ' . $eanActual . ' should be ' . $eanExpected,
                )));
                exit;
            }
            // Check digit is valid, now strip it
            if ($ignoreCheckDigit) {
                $trimmedSearch = substr($trimmedSearch, 0, $minExpectedLength);
            }
        }

        $prefix = substr($trimmedSearch, 0, 2);
        $mode = '';

        foreach ($superPrefixes as $candidateMode => $candidatePrefix) {
            if ($candidatePrefix !== '' && $prefix === $candidatePrefix) {
                $mode = $candidateMode;
                break;
            }
        }

        if ($mode !== '' && dol_strlen($trimmedSearch) == $minExpectedLength) {
            $productCode = substr($trimmedSearch, 2, $productCodeLen);
            $embeddedValueRaw = substr($trimmedSearch, 2 + $productCodeLen, $valueLen);

            $embeddedValue = 0;
            $qty = 1;

            if ($mode === 'weight') {
                $qty = ((float) $embeddedValueRaw) / $weightDivisor;
            } elseif ($mode === 'quantity') {
                $qty = ((float) $embeddedValueRaw) / $quantityDivisor;
            } elseif ($mode === 'price') {
                $embeddedValue = ((float) $embeddedValueRaw) / $priceDivisor;
            }

            // Also try matching the full prefix+productCode as stored barcode (e.g. '2100123')
            $productCodeWithPrefix = $prefix . $productCode;

            $sql  = "SELECT rowid, ref, label, tosell, tobuy, barcode, price, price_ttc";
            $sql .= " FROM " . $db->prefix() . "product as p";
            $sql .= " WHERE entity IN (" . getEntity('product') . ")";
            $sql .= " AND (ref = '" . $db->escape($productCode) . "' OR barcode = '" . $db->escape($productCode) . "'"
                .  " OR ref = '" . $db->escape($productCodeWithPrefix) . "' OR barcode = '" . $db->escape($productCodeWithPrefix) . "')";

            if ($filteroncategids) {
                $sql .= " AND EXISTS (SELECT cp.fk_product FROM " . $db->prefix() . "categorie_product as cp WHERE cp.fk_product = p.rowid AND cp.fk_categorie IN (".$db->sanitize($filteroncategids)."))";
            }

            $sql .= " AND tosell = 1";
            $sql .= $takeposBranchProductSqlFilter;
            $resql = $db->query($sql);

            if ($resql && $db->num_rows($resql) == 1) {
                $obj = $db->fetch_object($resql);
                $objProd = new Product($db);
                $objProd->fetch($obj->rowid);

                // If barcode contains final price, derive qty from TTC unit price
                if ($mode === 'price') {
                    $unitPrice = empty($objProd->multiprices_ttc[$pricelevel]) ? $obj->price_ttc : $objProd->multiprices_ttc[$pricelevel];
                    $unitPrice = price2num($unitPrice, 'MT');
                    if ($unitPrice > 0) {
                        $qty = $embeddedValue / $unitPrice;
                    }
                }

                if ($qty > 0) {
                    $ig = TakeposProductImageService::buildProductImageUrl((int) $obj->rowid);

                    $rows = array();
                    $rows[] = array(
                        'id' => (int) $obj->rowid,
                        'rowid' => $obj->rowid,
                        'ref' => $obj->ref,
                        'label' => $obj->label,
                        'tosell' => $obj->tosell,
                        'tobuy' => $obj->tobuy,
                        'barcode' => $search_term,
                        'price' => empty($objProd->multiprices[$pricelevel]) ? $obj->price : $objProd->multiprices[$pricelevel],
                        'price_ttc' => empty($objProd->multiprices_ttc[$pricelevel]) ? $obj->price_ttc : $objProd->multiprices_ttc[$pricelevel],
                        'object' => 'product',
                        'img' => $ig,
                        'image_url' => $ig,
                        'has_image' => TakeposProductImageService::hasProductImage($db, (int) $obj->rowid) ? 1 : 0,
                        'qty' => $qty,
                    );

                    takeposJsonOutput($rows);
                    exit();
                }
            }
        }
    }
    $barcode_rules = getDolGlobalString('TAKEPOS_BARCODE_RULE_TO_INSERT_PRODUCT');
    if (isModEnabled('barcode') && !empty($barcode_rules)) {
        $barcode_rules_list = array();

        // get barcode rules
        $barcode_char_nb = 0;
        $barcode_rules_arr = explode('+', $barcode_rules);
        foreach ($barcode_rules_arr as $barcode_rules_values) {
            $barcode_rules_values_arr = explode(':', $barcode_rules_values);
            if (count($barcode_rules_values_arr) == 2) {
                $char_nb = intval($barcode_rules_values_arr[1]);
                $barcode_rules_list[] = array('code' => $barcode_rules_values_arr[0], 'char_nb' => $char_nb);
                $barcode_char_nb += $char_nb;
            }
        }

        $barcode_value_list = array();
        $barcode_offset = 0;
        $barcode_length = dol_strlen($search_term);
        if ($barcode_length == $barcode_char_nb) {
            $rows = array();

            // split term with barcode rules
            foreach ($barcode_rules_list as $barcode_rule_arr) {
                $code = $barcode_rule_arr['code'];
                $char_nb = $barcode_rule_arr['char_nb'];
                $barcode_value_list[$code] = substr($search_term, $barcode_offset, $char_nb);
                $barcode_offset += $char_nb;
            }

            if (isset($barcode_value_list['ref'])) {
                // search product from reference
                $sql  = "SELECT rowid, ref, label, tosell, tobuy, barcode, price, price_ttc";
                $sql .= " FROM " . $db->prefix() . "product as p";
                $sql .= " WHERE entity IN (" . getEntity('product') . ")";
                $sql .= " AND (ref = '" . $db->escape($barcode_value_list['ref']) . "' OR barcode = '" . $db->escape($barcode_value_list['ref']) . "')";
                if ($filteroncategids) {
                    $sql .= " AND EXISTS (SELECT cp.fk_product FROM " . $db->prefix() . "categorie_product as cp WHERE cp.fk_product = p.rowid AND cp.fk_categorie IN (".$db->sanitize($filteroncategids)."))";
                }
                $sql .= " AND tosell = 1";
                $sql .= " AND (barcode IS NULL OR barcode <> '" . $db->escape($search_term) . "')";
                $sql .= $takeposBranchProductSqlFilter;

                $resql = $db->query($sql);
                if ($resql && $db->num_rows($resql) == 1) {
                    if ($obj = $db->fetch_object($resql)) {
                        $qty = 1;
                        if (isset($barcode_value_list['qu'])) {
                            $qty_str = $barcode_value_list['qu'];
                            if (isset($barcode_value_list['qd'])) {
                                $qty_str .= '.' . $barcode_value_list['qd'];
                            }
                            $qty = (float) $qty_str;
                        }

                        $objProd = new Product($db);
                        $objProd->fetch($obj->rowid);

                        $ig = TakeposProductImageService::buildProductImageUrl((int) $obj->rowid);

                        $rows[] = array(
                            'id' => (int) $obj->rowid,
                            'rowid' => $obj->rowid,
                            'ref' => $obj->ref,
                            'label' => $obj->label,
                            'tosell' => $obj->tosell,
                            'tobuy' => $obj->tobuy,
                            'barcode' => $search_term, // there is only one product matches the barcode rule and so the term is considered as the barcode of this product
                            'price' => empty($objProd->multiprices[$pricelevel]) ? $obj->price : $objProd->multiprices[$pricelevel],
                            'price_ttc' => empty($objProd->multiprices_ttc[$pricelevel]) ? $obj->price_ttc : $objProd->multiprices_ttc[$pricelevel],
                            'object' => 'product',
                            'img' => $ig,
                            'image_url' => $ig,
                            'has_image' => TakeposProductImageService::hasProductImage($db, (int) $obj->rowid) ? 1 : 0,
                            'qty' => $qty,
                        );
                    }
                    $db->free($resql);
                }
            }

            if (count($rows) == 1) {
                takeposJsonOutput($rows);
                exit();
            }
        }
    }

    $hasMultiBarcodeTable = takeposTableExists($db, MAIN_DB_PREFIX.'takepos_product_barcode');
    $searchExact = trim((string) $search_term);
    if ($searchExact !== '') {
        $sqlExact = 'SELECT p.rowid, p.ref, p.label, p.tosell, p.tobuy, p.barcode, p.price, p.price_ttc';
        if ($hasMultiBarcodeTable) {
            $sqlExact .= ', GROUP_CONCAT(DISTINCT pb.barcode SEPARATOR ",") as barcode_aliases';
            // Pull the qty multiplier / price override of the *scanned* barcode only.
            $sqlExact .= ", MAX(CASE WHEN pb.barcode = '".$db->escape($searchExact)."' THEN pb.qty_multiplier ELSE NULL END) as bc_qty_multiplier";
            $sqlExact .= ", MAX(CASE WHEN pb.barcode = '".$db->escape($searchExact)."' THEN pb.price_override ELSE NULL END) as bc_price_override";
        }
        if (getDolGlobalInt('TAKEPOS_PRODUCT_IN_STOCK') == 1) {
            // Always SUM across all warehouses
            $sqlExact .= ', SUM(COALESCE(ps.reel, 0)) as reel';
        }
        $sqlExact .= ' FROM '.MAIN_DB_PREFIX.'product as p';
        if ($hasMultiBarcodeTable) {
            $sqlExact .= ' LEFT JOIN '.MAIN_DB_PREFIX.'takepos_product_barcode as pb ON (pb.fk_product = p.rowid AND pb.entity IN ('.getEntity('product').'))';
        }
        if (getDolGlobalInt('TAKEPOS_PRODUCT_IN_STOCK') == 1) {
            // Join WITHOUT warehouse filter — sum stock from ALL warehouses
            $sqlExact .= ' LEFT JOIN '.MAIN_DB_PREFIX.'product_stock as ps ON (p.rowid = ps.fk_product)';
        }
        $sqlExact .= ' WHERE p.entity IN ('.getEntity('product').')';
        if ($filteroncategids) {
            $sqlExact .= ' AND EXISTS (SELECT cp.fk_product FROM '.MAIN_DB_PREFIX.'categorie_product as cp WHERE cp.fk_product = p.rowid AND cp.fk_categorie IN ('.$db->sanitize($filteroncategids).'))';
        }
        $sqlExact .= ' AND p.tosell = 1';
        $sqlExact .= $takeposBranchProductSqlFilter;
        if (getDolGlobalInt('TAKEPOS_PRODUCT_IN_STOCK') == 1) {
            // Use HAVING SUM > 0 so we check total stock across all warehouses
            // (handled in GROUP BY below)
        }
        $sqlExact .= " AND (p.ref = '" . $db->escape($searchExact) . "' OR p.barcode = '" . $db->escape($searchExact) . "'";
        if ($hasMultiBarcodeTable) {
            $sqlExact .= " OR pb.barcode = '" . $db->escape($searchExact) . "'";
        }
        $sqlExact .= ')';
        // Always group and check total stock across all warehouses
        $sqlExact .= ' GROUP BY p.rowid, p.ref, p.label, p.tosell, p.tobuy, p.barcode, p.price, p.price_ttc';
        if (getDolGlobalInt('TAKEPOS_PRODUCT_IN_STOCK') == 1) {
            $sqlExact .= ' HAVING SUM(ps.reel) > 0';
        }

        $resqlExact = $db->query($sqlExact);
        if ($resqlExact && (int) $db->num_rows($resqlExact) === 1) {
            $exactProduct = $db->fetch_object($resqlExact);
            $matchedBarcode = '';
            if (!empty($exactProduct->barcode) && hash_equals((string) $exactProduct->barcode, $searchExact)) {
                $matchedBarcode = $searchExact;
            } elseif ($hasMultiBarcodeTable && !empty($exactProduct->barcode_aliases)) {
                $barcodeAliases = explode(',', (string) $exactProduct->barcode_aliases);
                foreach ($barcodeAliases as $barcodeAlias) {
                    $barcodeAlias = trim((string) $barcodeAlias);
                    if ($barcodeAlias !== '' && hash_equals($barcodeAlias, $searchExact)) {
                        $matchedBarcode = $searchExact;
                        break;
                    }
                }
            }

            // Box / carton support: a scanned extra-barcode can carry its own
            // quantity (e.g. 24 units in a carton) and its own price.
            $bcQty = (isset($exactProduct->bc_qty_multiplier) && (float) $exactProduct->bc_qty_multiplier > 0)
                ? (float) $exactProduct->bc_qty_multiplier
                : 1;
            $bcPriceOverride = (isset($exactProduct->bc_price_override) && $exactProduct->bc_price_override !== null && (float) $exactProduct->bc_price_override > 0)
                ? (float) $exactProduct->bc_price_override
                : null;

            takeposJsonOutput(array(takeposBuildProductSearchRow($db, $exactProduct, $pricelevel, $matchedBarcode, $bcQty, $bcPriceOverride)));
            exit();
        }
    }

    $sql = 'SELECT p.rowid, p.ref, p.label, p.tosell, p.tobuy, p.barcode, p.price, p.price_ttc';
    if ($hasMultiBarcodeTable) {
        $sql .= ', GROUP_CONCAT(DISTINCT pb.barcode SEPARATOR ",") as barcode_aliases';
    }
    if (getDolGlobalInt('TAKEPOS_PRODUCT_IN_STOCK') == 1) {
        // Always SUM across all warehouses
        $sql .= ', SUM(COALESCE(ps.reel, 0)) as reel';
    }
    /* this will be possible when field archive will be supported into llx_product_price
    if (getDolGlobalString('PRODUIT_MULTIPRICES')) {
        $sql .= ', pp.price_level, pp.price as multiprice_ht, pp.price_ttc as multiprice_ttc';
    }*/
    // Add fields from hooks
    $parameters = array();
    $reshook = $hookmanager->executeHooks('printFieldListSelect', $parameters);
    if ($reshook >= 0) {
        $sql .= $hookmanager->resPrint;
    }

    $sql .= ' FROM '.MAIN_DB_PREFIX.'product as p';
    if ($hasMultiBarcodeTable) {
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'takepos_product_barcode as pb ON (pb.fk_product = p.rowid AND pb.entity IN ('.getEntity('product').'))';
    }
    /* this will be possible when field archive will be supported into llx_product_price
    if (getDolGlobalString('PRODUIT_MULTIPRICES')) {
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product_price as pp ON pp.fk_product = p.rowid AND pp.entity = ".((int) $conf->entity)." AND pp.price_level = ".((int) $pricelevel);
        $sql .= " AND archive = 0";
    }*/
    if (getDolGlobalInt('TAKEPOS_PRODUCT_IN_STOCK') == 1) {
        // FIX: Join without warehouse filter — sum stock across ALL warehouses.
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'product_stock as ps ON (p.rowid = ps.fk_product)';
    }

    // Add tables from hooks
    $parameters = array();
    $reshook = $hookmanager->executeHooks('printFieldListTables', $parameters);
    if ($reshook >= 0) {
        $sql .= $hookmanager->resPrint;
    }

    $sql .= ' WHERE p.entity IN ('.getEntity('product').')';
    if ($filteroncategids) {
        $sql .= ' AND EXISTS (SELECT cp.fk_product FROM '.MAIN_DB_PREFIX.'categorie_product as cp WHERE cp.fk_product = p.rowid AND cp.fk_categorie IN ('.$db->sanitize($filteroncategids).'))';
    }
    $sql .= ' AND p.tosell = 1';
    $sql .= $takeposBranchProductSqlFilter;
    // Stock filter handled in HAVING clause below (all warehouses summed)
    if ($hasMultiBarcodeTable) {
        $sql .= natural_search(array('p.ref', 'p.label', 'p.barcode', 'pb.barcode'), $search_term);
    } else {
        $sql .= natural_search(array('p.ref', 'p.label', 'p.barcode'), $search_term);
    }
    // Add where from hooks
    $parameters = array();
    $reshook = $hookmanager->executeHooks('printFieldListWhere', $parameters);
    if ($reshook >= 0) {
        $sql .= $hookmanager->resPrint;
    }

    // Always GROUP BY and use HAVING to filter by total stock across all warehouses
    if (strpos($sql, ' GROUP BY ') === false) {
        $sql .= ' GROUP BY p.rowid, p.ref, p.label, p.tosell, p.tobuy, p.barcode, p.price, p.price_ttc';
    }
    if (getDolGlobalInt('TAKEPOS_PRODUCT_IN_STOCK') == 1) {
        $sql .= ' HAVING SUM(COALESCE(ps.reel, 0)) > 0';
    }

    // load only one page of products
    $sql .= $db->plimit($search_limit, $search_start);

    $resql = $db->query($sql);
    if ($resql) {
        $rows = array();

        // FIX (M3): Direct row mapping — no Product::fetch() per row.
        while ($obj = $db->fetch_object($resql)) {
            $ig = TakeposProductImageService::buildProductImageUrl((int) $obj->rowid);
            $objProd = new stdClass();
            $objProd->id           = (int) $obj->rowid;
            $objProd->rowid        = (int) $obj->rowid;

            $row = array(
                'id' => (int) $obj->rowid,
                'rowid' => $obj->rowid,
                'ref' => $obj->ref,
                'label' => $obj->label,
                'tosell' => $obj->tosell,
                'tobuy' => $obj->tobuy,
                'barcode' => $obj->barcode,
                'price' => empty($objProd->multiprices[$pricelevel]) ? $obj->price : $objProd->multiprices[$pricelevel],
                'price_ttc' => empty($objProd->multiprices_ttc[$pricelevel]) ? $obj->price_ttc : $objProd->multiprices_ttc[$pricelevel],
                'object' => 'product',
                'img' => $ig,
                'image_url' => $ig,
                'has_image' => TakeposProductImageService::hasProductImage($db, (int) $obj->rowid) ? 1 : 0,
                'qty' => 1,
                'price_formated' => price(price2num(empty($objProd->multiprices[$pricelevel]) ? $obj->price : $objProd->multiprices[$pricelevel], 'MT'), 1, $langs, 1, -1, -1, $conf->currency),
                'price_ttc_formated' => price(price2num(empty($objProd->multiprices_ttc[$pricelevel]) ? $obj->price_ttc : $objProd->multiprices_ttc[$pricelevel], 'MT'), 1, $langs, 1, -1, -1, $conf->currency)
            );
            $matchedBarcode = '';
            $searchExact = trim((string) $search_term);
            if ($searchExact !== '') {
                if (!empty($obj->barcode) && hash_equals((string) $obj->barcode, $searchExact)) {
                    $matchedBarcode = $searchExact;
                } elseif (!empty($obj->barcode_aliases)) {
                    $barcodeAliases = explode(',', (string) $obj->barcode_aliases);
                    foreach ($barcodeAliases as $barcodeAlias) {
                        $barcodeAlias = trim((string) $barcodeAlias);
                        if ($barcodeAlias !== '' && hash_equals($barcodeAlias, $searchExact)) {
                            $matchedBarcode = $searchExact;
                            break;
                        }
                    }
                }
            }
            $row['matched_barcode'] = $matchedBarcode;
            // Add entries to row from hooks
            $parameters = array();
            $parameters['row'] = $row;
            $parameters['obj'] = $obj;
            $reshook = $hookmanager->executeHooks('completeAjaxReturnArray', $parameters);
            if ($reshook > 0) {
                // replace
                if (count($hookmanager->resArray)) {
                    $row = $hookmanager->resArray;
                } else {
                    $row = array();
                }
                $rows[] = $row;
            } else {
                // add
                if (count($hookmanager->resArray)) {
                    $rows[] = $hookmanager->resArray;
                }
                $rows[] = $row;
            }
        }

        takeposJsonOutput($rows);
    } else {
        echo 'Failed to search product : '.$db->lasterror();
    }
} elseif ($action == "opendrawer" && $user->hasRight('takepos', 'run')) {
    top_httphead('application/json');
    require_once DOL_DOCUMENT_ROOT.'/core/class/dolreceiptprinter.class.php';
    $printer = new dolReceiptPrinter($db);
    $result = array('success' => false, 'message' => '');

    // Try with configured printer
    if ($term != '' && getDolGlobalInt('TAKEPOS_PRINTER_TO_USE'.$term) > 0) {
        $printer->initPrinter(getDolGlobalInt('TAKEPOS_PRINTER_TO_USE'.$term));
        if ($printer->getPrintConnector()) {
            $printer->pulse();
            $printer->close();
            $result['success'] = true;
            $result['message'] = 'Drawer opened';
        } else {
            $result['message'] = 'Printer init failed. Please configure printer in TakePOS settings.';
        }
    } elseif (getDolGlobalString('TAKEPOS_PRINT_METHOD') == "takeposconnector" && getDolGlobalString('TAKEPOS_PRINT_SERVER')) {
        // takeposconnector method — handled client-side via OpenDrawer()
        $result['success'] = true;
        $result['message'] = 'connector';
    } else {
        // No printer configured — send ESC/p command via response for manual setup
        $result['success']  = false;
        $result['message']  = 'NoDrawerConfigured';
        $result['help']     = 'Go to TakePOS Admin > Terminal settings and configure a receipt printer to enable cash drawer.';
    }
    print json_encode($result);
} elseif ($action == "printinvoiceticket" && $term != '' && $id > 0 && $user->hasRight('takepos', 'run') && $user->hasRight('facture', 'lire')) {
    top_httphead('application/html');

    require_once DOL_DOCUMENT_ROOT.'/core/class/dolreceiptprinter.class.php';
    require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
    $printer = new dolReceiptPrinter($db);
    // check printer for terminal
    if ((getDolGlobalInt('TAKEPOS_PRINTER_TO_USE'.$term) > 0 || getDolGlobalString('TAKEPOS_PRINT_METHOD') == "takeposconnector") && getDolGlobalInt('TAKEPOS_TEMPLATE_TO_USE_FOR_INVOICES'.$term) > 0) {
        $object = new Facture($db);
        $object->fetch($id);
        $ret = $printer->sendToPrinter($object, getDolGlobalInt('TAKEPOS_TEMPLATE_TO_USE_FOR_INVOICES'.$term), getDolGlobalInt('TAKEPOS_PRINTER_TO_USE'.$term));
    }
} elseif ($action == 'getInvoice' && $user->hasRight('takepos', 'run')) {
    top_httphead('application/json');

    require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

    $object = new Facture($db);
    if ($id > 0) {
        $object->fetch($id);
    }

    takeposJsonOutput($object);
} elseif ($action == 'thecheck' && $user->hasRight('takepos', 'run')) {
    top_httphead('application/html');

    $place = TakeposInputValidator::normalizeUtf8Text(GETPOST('place', 'none'), 64, true);
    require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
    require_once DOL_DOCUMENT_ROOT.'/core/class/dolreceiptprinter.class.php';

    $object = new Facture($db);

    $printer = new dolReceiptPrinter($db);
    $printer->sendToPrinter($object, getDolGlobalInt('TAKEPOS_TEMPLATE_TO_USE_FOR_INVOICES'.$term), getDolGlobalInt('TAKEPOS_PRINTER_TO_USE'.$term));
}