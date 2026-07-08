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

function takeposBuildProductSearchRow($db, $productData, $pricelevel, $matchedBarcode = '', $qty = 1)
{
    global $conf, $langs;

    $objProd = new Product($db);
    $objProd->fetch((int) $productData->rowid);
    $ig = TakeposProductImageService::buildProductImageUrl((int) $productData->rowid);

    $priceHt = empty($objProd->multiprices[$pricelevel]) ? $productData->price : $objProd->multiprices[$pricelevel];
    $priceTtc = empty($objProd->multiprices_ttc[$pricelevel]) ? $productData->price_ttc : $objProd->multiprices_ttc[$pricelevel];

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

	$result = $object->fetch($category);
	if ($result > 0) {
		$filter = '';
		if ($tosell != '') {
			$filter = '(o.tosell:=:'.((int) $tosell).')';
		}
		$prods = $object->getObjectsInCateg("product", 0, $limit, $offset, getDolGlobalString('TAKEPOS_SORTPRODUCTFIELD'), 'ASC', $filter);
		// Removed properties we don't need
		$res = array();
		if (is_array($prods) && count($prods) > 0) {
			$productChildrenNb = 0;
			foreach ($prods as $prod) {
				'@phan-var-force Product $prod';
				if (getDolGlobalInt('TAKEPOS_PRODUCT_IN_STOCK') == 1) {
					if (getDolGlobalInt('PRODUIT_SOUSPRODUITS')) {
						$productChildrenNb = $prod->hasFatherOrChild(1);
					}
					// always show virtual products (don't manage stock)
					if ($productChildrenNb == 0) {
						// remove products without stock
						$prod->load_stock('nobatch,novirtual');
						if ($prod->stock_warehouse[getDolGlobalString('CASHDESK_ID_WAREHOUSE'.$_SESSION['takeposterminal'])]->real <= 0) {
							continue;
						}
					}
				}
				unset($prod->fields);
				unset($prod->db);

				$prod->price_formated = price(price2num(empty($prod->multiprices[$pricelevel]) ? $prod->price : $prod->multiprices[$pricelevel], 'MT'), 1, $langs, 1, -1, -1, $conf->currency);
				$prod->price_ttc_formated = price(price2num(empty($prod->multiprices_ttc[$pricelevel]) ? $prod->price_ttc : $prod->multiprices_ttc[$pricelevel], 'MT'), 1, $langs, 1, -1, -1, $conf->currency);
				$prod->id = (int) (!empty($prod->id) ? $prod->id : $prod->rowid);
				$prod->rowid = (int) (!empty($prod->rowid) ? $prod->rowid : $prod->id);
				$prod->img = TakeposProductImageService::buildProductImageUrl((int) $prod->rowid);
				$prod->image_url = $prod->img;
				$prod->has_image = TakeposProductImageService::hasProductImage($db, (int) $prod->rowid) ? 1 : 0;

				$res[] = $prod;
			}
		}
		takeposJsonOutput($res);
	} else {
		echo 'Failed to load category with id='.dol_escape_htmltag($category);
	}
} elseif ($action == 'search' && $search_term != '' && $user->hasRight('takepos', 'run')) {
	top_httphead('application/json');

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

		// If barcode has trailing check digit, ignore it
		if ($ignoreCheckDigit && dol_strlen($trimmedSearch) == ($minExpectedLength + 1)) {
			$trimmedSearch = substr($trimmedSearch, 0, $minExpectedLength);
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

			$sql  = "SELECT rowid, ref, label, tosell, tobuy, barcode, price, price_ttc";
			$sql .= " FROM " . $db->prefix() . "product as p";
			$sql .= " WHERE entity IN (" . getEntity('product') . ")";
			$sql .= " AND (ref = '" . $db->escape($productCode) . "' OR barcode = '" . $db->escape($productCode) . "')";

			if ($filteroncategids) {
				$sql .= " AND EXISTS (SELECT cp.fk_product FROM " . $db->prefix() . "categorie_product as cp WHERE cp.fk_product = p.rowid AND cp.fk_categorie IN (".$db->sanitize($filteroncategids)."))";
			}

			$sql .= " AND tosell = 1";
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
		}
		if (getDolGlobalInt('TAKEPOS_PRODUCT_IN_STOCK') == 1) {
			if (getDolGlobalInt('CASHDESK_ID_WAREHOUSE'.$_SESSION['takeposterminal'])) {
				$sqlExact .= ', ps.reel';
			} else {
				$sqlExact .= ', SUM(ps.reel) as reel';
			}
		}
		$sqlExact .= ' FROM '.MAIN_DB_PREFIX.'product as p';
		if ($hasMultiBarcodeTable) {
			$sqlExact .= ' LEFT JOIN '.MAIN_DB_PREFIX.'takepos_product_barcode as pb ON (pb.fk_product = p.rowid AND pb.entity IN ('.getEntity('product').'))';
		}
		if (getDolGlobalInt('TAKEPOS_PRODUCT_IN_STOCK') == 1) {
			$sqlExact .= ' LEFT JOIN '.MAIN_DB_PREFIX.'product_stock as ps';
			$sqlExact .= ' ON (p.rowid = ps.fk_product';
			if (getDolGlobalString('CASHDESK_ID_WAREHOUSE'.$_SESSION['takeposterminal'])) {
				$sqlExact .= " AND ps.fk_entrepot = ".((int) getDolGlobalInt("CASHDESK_ID_WAREHOUSE".$_SESSION['takeposterminal']));
			}
			$sqlExact .= ')';
		}
		$sqlExact .= ' WHERE p.entity IN ('.getEntity('product').')';
		if ($filteroncategids) {
			$sqlExact .= ' AND EXISTS (SELECT cp.fk_product FROM '.MAIN_DB_PREFIX.'categorie_product as cp WHERE cp.fk_product = p.rowid AND cp.fk_categorie IN ('.$db->sanitize($filteroncategids).'))';
		}
		$sqlExact .= ' AND p.tosell = 1';
		if (getDolGlobalInt('TAKEPOS_PRODUCT_IN_STOCK') == 1 && getDolGlobalInt('CASHDESK_ID_WAREHOUSE'.$_SESSION['takeposterminal'])) {
			$sqlExact .= ' AND ps.reel > 0';
		}
		$sqlExact .= " AND (p.ref = '" . $db->escape($searchExact) . "' OR p.barcode = '" . $db->escape($searchExact) . "'";
		if ($hasMultiBarcodeTable) {
			$sqlExact .= " OR pb.barcode = '" . $db->escape($searchExact) . "'";
		}
		$sqlExact .= ')';
		if (getDolGlobalInt('TAKEPOS_PRODUCT_IN_STOCK') == 1 && !getDolGlobalInt('CASHDESK_ID_WAREHOUSE'.$_SESSION['takeposterminal'])) {
			$sqlExact .= ' GROUP BY p.rowid, p.ref, p.label, p.tosell, p.tobuy, p.barcode, p.price, p.price_ttc';
			$sqlExact .= ' HAVING SUM(ps.reel) > 0';
		} elseif ($hasMultiBarcodeTable) {
			$sqlExact .= ' GROUP BY p.rowid, p.ref, p.label, p.tosell, p.tobuy, p.barcode, p.price, p.price_ttc';
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

			takeposJsonOutput(array(takeposBuildProductSearchRow($db, $exactProduct, $pricelevel, $matchedBarcode, 1)));
			exit();
		}
	}

	$sql = 'SELECT p.rowid, p.ref, p.label, p.tosell, p.tobuy, p.barcode, p.price, p.price_ttc';
	if ($hasMultiBarcodeTable) {
		$sql .= ', GROUP_CONCAT(DISTINCT pb.barcode SEPARATOR ",") as barcode_aliases';
	}
	if (getDolGlobalInt('TAKEPOS_PRODUCT_IN_STOCK') == 1) {
		if (getDolGlobalInt('CASHDESK_ID_WAREHOUSE'.$_SESSION['takeposterminal'])) {
			$sql .= ', ps.reel';
		} else {
			$sql .= ', SUM(ps.reel) as reel';
		}
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
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'product_stock as ps';
		$sql .= ' ON (p.rowid = ps.fk_product';
		if (getDolGlobalString('CASHDESK_ID_WAREHOUSE'.$_SESSION['takeposterminal'])) {
			$sql .= " AND ps.fk_entrepot = ".((int) getDolGlobalInt("CASHDESK_ID_WAREHOUSE".$_SESSION['takeposterminal']));
		}
		$sql .= ')';
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
	if (getDolGlobalInt('TAKEPOS_PRODUCT_IN_STOCK') == 1 && getDolGlobalInt('CASHDESK_ID_WAREHOUSE'.$_SESSION['takeposterminal'])) {
		$sql .= ' AND ps.reel > 0';
	}
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

	if (getDolGlobalInt('TAKEPOS_PRODUCT_IN_STOCK') == 1 && !getDolGlobalInt('CASHDESK_ID_WAREHOUSE'.$_SESSION['takeposterminal'])) {
		$sql .= ' GROUP BY p.rowid, p.ref, p.label, p.tosell, p.tobuy, p.barcode, p.price, p.price_ttc';
		// Add fields from hooks
		$parameters = array();
		$reshook = $hookmanager->executeHooks('printFieldListSelect', $parameters);
		if ($reshook >= 0) {
			$sql .= $hookmanager->resPrint;
		}
		$sql .= ' HAVING SUM(ps.reel) > 0';
	}

	if ($hasMultiBarcodeTable && strpos($sql, ' GROUP BY ') === false) {
		$sql .= ' GROUP BY p.rowid, p.ref, p.label, p.tosell, p.tobuy, p.barcode, p.price, p.price_ttc';
	}

	// load only one page of products
	$sql .= $db->plimit($search_limit, $search_start);

	$resql = $db->query($sql);
	if ($resql) {
		$rows = array();

		while ($obj = $db->fetch_object($resql)) {
			$objProd = new Product($db);
			$objProd->fetch($obj->rowid);
			$ig = TakeposProductImageService::buildProductImageUrl((int) $obj->rowid);

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
} elseif ($action == "opendrawer" && $term != '' && $user->hasRight('takepos', 'run')) {
	top_httphead('application/html');
	require_once DOL_DOCUMENT_ROOT.'/core/class/dolreceiptprinter.class.php';
	$printer = new dolReceiptPrinter($db);
	// check printer for terminal
	if (getDolGlobalInt('TAKEPOS_PRINTER_TO_USE'.$term) > 0) {
		$printer->initPrinter(getDolGlobalInt('TAKEPOS_PRINTER_TO_USE'.$term));
		// open cashdrawer
		if ($printer->getPrintConnector()) {
			$printer->pulse();
			$printer->close();
		} else {
			print 'Failed to init printer with ID='.getDolGlobalInt('TAKEPOS_PRINTER_TO_USE'.$term);
		}
	}
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
