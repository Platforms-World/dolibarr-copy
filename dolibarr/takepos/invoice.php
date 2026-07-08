<?php
/**
 * Copyright (C) 2018    	Andreu Bisquerra   		<jove@bisquerra.com>
 * Copyright (C) 2021    	Nicolas ZABOURI    		<info@inovea-conseil.com>
 * Copyright (C) 2022-2023	Christophe Battarel		<christophe.battarel@altairis.fr>
 * Copyright (C) 2024-2025	MDW						<mdeweerd@users.noreply.github.com>
 * Copyright (C) 2024		Frederic France			<frederic.france@free.fr>
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
 *    \file       htdocs/takepos/invoice.php
 *    \ingroup    takepos
 *    \brief      Page to generate section with list of lines
 */

// if (! defined('NOREQUIREUSER')) 		define('NOREQUIREUSER', '1'); 		// Not disabled cause need to load personalized language
// if (! defined('NOREQUIREDB')) 		define('NOREQUIREDB', '1'); 		// Not disabled cause need to load personalized language
// if (! defined('NOREQUIRESOC')) 		define('NOREQUIRESOC', '1');
// if (! defined('NOREQUIRETRAN')) 		define('NOREQUIRETRAN', '1');

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

// Load Dolibarr environment
if (!defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
    require '../main.inc.php';
}
require_once __DIR__ . '/lib/takepos_lang.php';
require_once __DIR__ . '/lib/takepos_currency.php';
require_once __DIR__ . '/lib/takepos_help.php';
require_once __DIR__ . '/lib/takepos_loader.php';
if (!function_exists('takeposApplyForcedLanguage')) {
    function takeposApplyForcedLanguage($langs, $user = null)
    {
        $allowed = array('en_US', 'ar_JO');
        $forced = '';

        if (!empty($_SESSION['forcelang']) && in_array($_SESSION['forcelang'], $allowed, true)) {
            $forced = (string) $_SESSION['forcelang'];
        }

        if (!empty($_GET['langs']) && in_array($_GET['langs'], $allowed, true)) {
            $forced = (string) $_GET['langs'];
            $_SESSION['forcelang'] = $forced;
        }

        if ($forced === '') {
            return '';
        }

        if (is_object($langs) && method_exists($langs, 'setDefaultLang')) {
            $langs->setDefaultLang($forced);
        }

        if (is_object($user)) {
            if (!isset($user->conf) || !is_object($user->conf)) {
                $user->conf = new stdClass();
            }
            $user->conf->MAIN_LANG_DEFAULT = $forced;
        }

        return $forced;
    }
}
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
foreach (array(
             'class/TakeposAccess.class.php',
             'class/TakeposAudit.class.php',
             'class/TakeposUserAccess.class.php',
             'class/TakeposInputValidator.class.php',
             'class/TakeposShiftService.class.php',
             'class/TakeposManagerOverrideService.class.php',
             'class/TakeposOfflineService.class.php',
             'class/TakeposLoyaltyService.class.php',
             'class/TakeposWebhookService.class.php',
         ) as $takeposModuleFile) {
    takeposRequireModuleFile($takeposModuleFile);
}
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
function takeposEnforceShiftForSale($db, $user, $invoiceId = 0)
{
    list($shiftAllowed, $shiftDenyMessage, $activeShiftSummary) = TakeposShiftService::enforceSaleShiftRequirement($db, $user, isset($_SESSION['takeposterminal']) ? $_SESSION['takeposterminal'] : '', (int) $invoiceId);
    if (!$shiftAllowed) {
        dol_htmloutput_errors($shiftDenyMessage, array(), 1);
        return false;
    }

    return true;
}

function takeposInvoiceTableExists($db, $table)
{
    // PERFORMANCE: per-request cache. takeposLinkInvoiceToShift() runs on every
    // addline + save and used to issue 2 SHOW queries every time. The schema
    // does not change mid-request, so the first answer is the right one for the
    // rest of this PHP process.
    static $cache = array();
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    $resql = $db->query("SHOW TABLES LIKE '" . $db->escape($table) . "'");
    $exists = ($resql && $db->num_rows($resql) > 0);
    if ($exists) {
        $cache[$table] = true;
    }
    return $exists;
}

function takeposInvoiceColumnExists($db, $table, $column)
{
    // PERFORMANCE: per-request cache (see takeposInvoiceTableExists).
    static $cache = array();
    $key = $table . '|' . $column;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $resql = $db->query("SHOW COLUMNS FROM " . $table . " LIKE '" . $db->escape($column) . "'");
    $exists = ($resql && $db->num_rows($resql) > 0);
    if ($exists) {
        $cache[$key] = true;
    }
    return $exists;
}

function takeposLinkInvoiceToShift($db, $user, $invoiceId, $activeShiftSummary = null)
{
    if ((int) $invoiceId <= 0 || !class_exists('TakeposShiftService')) {
        return true;
    }

    $terminalCode = isset($_SESSION['takeposterminal']) ? (string) $_SESSION['takeposterminal'] : '';
    if (!is_array($activeShiftSummary)) {
        $activeShiftSummary = TakeposShiftService::getCurrentActiveShiftSummary($db, $user, $terminalCode);
    }
    if (!is_array($activeShiftSummary) || empty($activeShiftSummary['shift_id'])) {
        return true;
    }

    $entity = !empty($user->entity) ? (int) $user->entity : 1;
    $shiftId = (int) $activeShiftSummary['shift_id'];
    $fkTerminal = !empty($activeShiftSummary['terminal_id']) ? (int) $activeShiftSummary['terminal_id'] : 0;
    $fkCashier = !empty($activeShiftSummary['cashier_user_id']) ? (int) $activeShiftSummary['cashier_user_id'] : (int) $user->id;
    $terminalForLink = !empty($activeShiftSummary['terminal_code']) ? (string) $activeShiftSummary['terminal_code'] : $terminalCode;

    $linkTable = MAIN_DB_PREFIX . 'takepos_invoice_shift';
    if (takeposInvoiceTableExists($db, $linkTable)) {
        $sqlCheck = "SELECT rowid FROM " . $linkTable . " WHERE entity = " . $entity . " AND fk_invoice = " . ((int) $invoiceId) . " LIMIT 1";
        $resCheck = $db->query($sqlCheck);
        if ($resCheck && !($db->fetch_object($resCheck))) {
            $sqlInsert = "INSERT INTO " . $linkTable
                . " (entity, fk_invoice, fk_shift, fk_terminal, fk_cashier_user, terminal_code, date_creation) VALUES ("
                . $entity . ", " . ((int) $invoiceId) . ", " . $shiftId . ", " . $fkTerminal . ", " . $fkCashier . ", '" . $db->escape($terminalForLink) . "', '" . $db->idate(dol_now()) . "')";
            $db->query($sqlInsert);
        } elseif (!$resCheck) {
            return false;
        }
    }

    $factureTable = MAIN_DB_PREFIX . 'facture';
    if (takeposInvoiceColumnExists($db, $factureTable, 'fk_takepos_shift')) {
        $sqlUpdate = "UPDATE " . $factureTable . " SET fk_takepos_shift = " . $shiftId . " WHERE rowid = " . ((int) $invoiceId) . " AND (fk_takepos_shift IS NULL OR fk_takepos_shift = 0)";
        $db->query($sqlUpdate);
    }

    return true;
}

function takeposRecordPaymentCurrencyMeta($db, $user, $invoiceId, $paymentId, $paymentCode, $amountBase, $excessBase, $currencyCode, $rate, $foreignAmount, $foreignExcess)
{
    global $conf;

    $currencyCode = takeposNormalizeCurrencyCode($currencyCode);
    $baseCurrency = takeposNormalizeCurrencyCode(isset($conf->currency) ? $conf->currency : '');
    $rate = takeposNormalizeCurrencyRate($rate);
    if ((int) $invoiceId <= 0 || (int) $paymentId <= 0 || $currencyCode === '' || $currencyCode === $baseCurrency || $rate <= 0) {
        return true;
    }

    $table = MAIN_DB_PREFIX . 'takepos_payment_currency';
    if (!takeposInvoiceTableExists($db, $table)) {
        return true;
    }

    $entity = !empty($user->entity) ? (int) $user->entity : 1;
    $amountBase = (float) $amountBase;
    $excessBase = (float) $excessBase;
    $foreignAmount = (float) $foreignAmount;
    $foreignExcess = (float) $foreignExcess;
    if ($foreignAmount <= 0) {
        $foreignAmount = round($amountBase * $rate, 8);
    }
    if ($foreignExcess <= 0 && $excessBase > 0) {
        $foreignExcess = round($excessBase * $rate, 8);
    }

    $sql = "INSERT INTO " . $table
        . " (entity, fk_invoice, fk_paiement, payment_code, base_currency, payment_currency, payment_rate, amount_base, amount_foreign, excess_base, excess_foreign, fk_user_author, date_creation) VALUES ("
        . $entity . ", " . ((int) $invoiceId) . ", " . ((int) $paymentId) . ", '" . $db->escape((string) $paymentCode) . "', '" . $db->escape($baseCurrency) . "', '" . $db->escape($currencyCode) . "', " . price2num($rate, 'MU') . ", " . price2num($amountBase, 'MU') . ", " . price2num($foreignAmount, 'MU') . ", " . price2num($excessBase, 'MU') . ", " . price2num($foreignExcess, 'MU') . ", " . ((int) $user->id) . ", '" . $db->idate(dol_now()) . "')";
    return (bool) $db->query($sql);
}

function takeposShouldStrictlyProtectInvoiceView($action)
{
    $publicActions = array('history', 'creditnote');
    return !in_array((string) $action, $publicActions, true);
}

function takeposEnforceStrictShiftGateForInvoiceView($db, $user, $action, $invoiceId = 0)
{
    if (!takeposShouldStrictlyProtectInvoiceView($action)) {
        return true;
    }

    if (!TakeposAccess::isFeatureEnabledForCurrentEntity($db, 'takepos.shift_management')) {
        return true;
    }

    return takeposEnforceShiftForSale($db, $user, (int) $invoiceId);
}

function takeposIsInvoiceMutationAction($action)
{
    static $mutationActions = array(
        'addline',
        'freezone',
        'updateqty',
        'updateprice',
        'updatereduction',
        'deleteline',
        'delete',
        'valid',
        'addnote',
        'order',
        'temp'
    );

    return in_array((string) $action, $mutationActions, true);
}

function takeposCanViewAllPosInvoices($db, $user)
{
    return (!empty($user->admin) || TakeposUserAccess::userHasPermission($db, $user, 'takepos.store.view_all'));
}

function takeposEnforceInvoiceViewAccess($db, $user, $invoice, $denyMessage)
{
    if (!is_object($invoice) || empty($invoice->id)) {
        accessforbidden($denyMessage);
    }

    if (takeposCanViewAllPosInvoices($db, $user)) {
        return true;
    }

    if ((int) $invoice->fk_user_author !== (int) $user->id) {
        accessforbidden($denyMessage);
    }

    return true;
}

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Societe $mysoc
 * @var Translate $langs
 * @var User $user
 */

$hookmanager->initHooks(array('takeposinvoice'));

$langs->loadLangs(array("companies", "commercial", "bills", "cashdesk", "stocks", "banks", "takeposcustom@takepos"));

$tpLabelPrintTicket = takeposTranslateWithFallback($langs, 'PrintTicket', 'طباعة التذكرة', 'Print ticket');

print '<style>#php-debugbar,.phpdebugbar,.php-debugbar,.debugbar,.debug-bar,.debugbar-container,.sf-toolbar,#sfwdt,div[id*="debugbar"],div[class*="debugbar"]{display:none !important;visibility:hidden !important;opacity:0 !important;pointer-events:none !important;}</style>';
print '<script>function takeposHideInvoiceDebugBars(){var sels=["#php-debugbar",".phpdebugbar",".php-debugbar",".debugbar",".debug-bar",".debugbar-container",".sf-toolbar","#sfwdt","div[id*=\"debugbar\"]","div[class*=\"debugbar\"]"];try{document.querySelectorAll(sels.join(",")).forEach(function(el){el.remove();});}catch(e){}}document.addEventListener("DOMContentLoaded",takeposHideInvoiceDebugBars);window.addEventListener("load",takeposHideInvoiceDebugBars);setTimeout(takeposHideInvoiceDebugBars,300);</script>';

$action = GETPOST('action', 'aZ09');
$idproduct = GETPOSTINT('idproduct');
$place = (GETPOST('place', 'aZ09') ? GETPOST('place', 'aZ09') : 0); // $place is id of table for Bar or Restaurant
$placeid = 0; // $placeid is ID of invoice
$mobilepage = GETPOST('mobilepage', 'alpha');

// Terminal is stored into $_SESSION["takeposterminal"];

if (!defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
    TakeposAccess::requireFrontendAccess($db, isset($user) ? $user : null, 'takepos.frontend', 'takepos.use', isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : null, $langs->trans('TakeposInvoiceAccessDenied'), array('page' => 'invoice.php', 'action' => $action));
    TakeposAudit::logEvent($db, $user, 'pos_open_screen', TakeposAudit::SEVERITY_INFO, array('page' => 'invoice.php', 'action' => $action), 'POS invoice screen opened');
    if (empty($_SESSION['takepos_invoice_login_logged'])) {
        TakeposAudit::logEvent($db, $user, 'pos_login', TakeposAudit::SEVERITY_INFO, array('page' => 'invoice.php'), 'POS invoice session started');
        $_SESSION['takepos_invoice_login_logged'] = 1;
    }
}

if ((getDolGlobalString('TAKEPOS_PHONE_BASIC_LAYOUT') == 1 && $conf->browser->layout == 'phone') || defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
    // DIRECT LINK TO THIS PAGE FROM MOBILE AND NO TERMINAL SELECTED
    if ($_SESSION["takeposterminal"] == "") {
        if (getDolGlobalString('TAKEPOS_NUM_TERMINALS') == "1") {
            $_SESSION["takeposterminal"] = 1;
        } else {
            header("Location: ".DOL_URL_ROOT."/takepos/index.php");
            exit;
        }
    }
}


$takeposterminal = isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : '';

$invoiceIdForStrictShiftGate = GETPOSTINT('placeid');
if ($invoiceIdForStrictShiftGate <= 0) {
    $invoiceIdForStrictShiftGate = GETPOSTINT('invoiceid');
}
if (!defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
    if (!takeposEnforceStrictShiftGateForInvoiceView($db, $user, $action, $invoiceIdForStrictShiftGate)) {
        print '</body></html>';
        exit;
    }
}

// When session has expired (selected terminal has been lost from session), redirect to the terminal selection.
if (empty($takeposterminal)) {
    if (getDolGlobalInt('TAKEPOS_NUM_TERMINALS') == 1) {
        $_SESSION["takeposterminal"] = 1; // Use terminal 1 if there is only 1 terminal
        $takeposterminal = 1;
    } elseif (!empty($_COOKIE["takeposterminal"])) {
        $_SESSION["takeposterminal"] = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_COOKIE["takeposterminal"]); // Restore takeposterminal from previous session
        $takeposterminal = $_SESSION["takeposterminal"];
    } else {
        print <<<SCRIPT
<script language="javascript">
	$( document ).ready(function() {
		ModalBox('ModalTerminal');
	});
</script>
SCRIPT;
        exit;
    }
}


/**
 * Abort invoice creation with a given error message
 *
 * @param   string  $message        Message explaining the error to the user
 * @return	never
 */
function fail($message)
{
    header($_SERVER['SERVER_PROTOCOL'].' 500 Internal Server Error', true, 500);
    die($message);
}



/**
 * Tell if a user can delete a TakePOS line directly.
 *
 * @param   User            $currentUser     User to test
 * @param   CommonObject    $line            Optional invoice line object
 * @return  bool
 */
/**
 * Safe audit logger wrapper.
 */
function takeposAuditLog($db, $user, $eventType, $severity = TakeposAudit::SEVERITY_INFO, $data = array(), $description = '', $objectType = '', $objectId = 0, $amountTtc = null)
{
    if (!class_exists('TakeposAudit')) {
        return;
    }

    try {
        TakeposAudit::logEvent($db, $user, $eventType, $severity, $data, $description, $objectType, $objectId, $amountTtc);
    } catch (Throwable $e) {
        if (function_exists('dol_syslog')) {
            dol_syslog('[TakePOS][Audit] '.$e->getMessage(), LOG_WARNING);
        }
    }
}

/**
 * Resolve runtime metadata for each sensitive POS action.
 */
function takeposSensitiveActionMeta($actionType)
{
    $map = array(
        'delete_line' => array(
            'permission' => 'takepos.action.line_delete',
            'override_permission' => 'takepos.override.line_delete',
            'feature' => 'takepos.line_delete',
            'label' => 'line deletion',
        ),
        'price_override' => array(
            'permission' => 'takepos.action.price_override',
            'override_permission' => 'takepos.override.price',
            'feature' => 'takepos.price_override',
            'label' => 'price override',
        ),
        'discount' => array(
            'permission' => 'takepos.action.discount',
            'override_permission' => 'takepos.override.discount',
            'feature' => 'takepos.discount',
            'label' => 'discount override',
        ),
        'invoice_cancel' => array(
            'permission' => 'takepos.action.invoice_cancel',
            'override_permission' => 'takepos.override.cancel',
            'feature' => 'takepos.invoice_cancel',
            'label' => 'invoice cancelation',
        ),
    );

    return isset($map[$actionType]) ? $map[$actionType] : array();
}

/**
 * Determine whether manager override feature is available for this entity.
 */
function takeposManagerOverrideEnabled($db)
{
    if (class_exists('TakeposManagerOverrideService')) {
        return TakeposManagerOverrideService::isFeatureEnabled($db);
    }
    try {
        return TakeposAccess::isFeatureEnabledForCurrentEntity($db, 'takepos.manager_override');
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Return true only for deny reasons that may be unlocked by one-time manager approval.
 */
function takeposDenyReasonAllowsOverride($denyReason)
{
    if (class_exists('TakeposManagerOverrideService')) {
        return TakeposManagerOverrideService::denyReasonAllowsOverride($denyReason);
    }
    return in_array((string) $denyReason, array(
        'permission_denied',
        'ordered_line_denied',
        'price_limit_exceeded',
        'discount_percent_limit_exceeded',
        'discount_amount_limit_exceeded',
    ), true);
}

/**
 * Return target line id to delete.
 */
function takeposResolveDeleteLineId($db, $invoiceId, $preferredLine)
{
    $invoiceId = (int) $invoiceId;
    $preferredLine = (int) $preferredLine;
    if ($invoiceId <= 0) {
        return 0;
    }
    if ($preferredLine > 0) {
        return $preferredLine;
    }

    $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."facturedet";
    $sql .= " WHERE fk_facture = ".$invoiceId;
    $sql .= " ORDER BY rowid DESC";
    $sql .= " LIMIT 1";
    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        if ($obj) {
            return (int) $obj->rowid;
        }
    }

    return 0;
}

/**
 * Find line object by id on invoice object.
 */
function takeposFindInvoiceLineById($invoice, $lineId)
{
    $lineId = (int) $lineId;
    if ($lineId <= 0 || empty($invoice->lines) || !is_array($invoice->lines)) {
        return null;
    }
    foreach ($invoice->lines as $line) {
        if ((int) $line->id === $lineId || (isset($line->rowid) && (int) $line->rowid === $lineId)) {
            return $line;
        }
    }

    return null;
}

/**
 * Find manager user by card/barcode input.
 */
function takeposFindManagerByBarcode($db, $barcode)
{
    $barcode = trim((string) $barcode);
    if ($barcode === '') {
        return null;
    }

    $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."user";
    $sql .= " WHERE entity IN (".getEntity('user').")";
    $sql .= " AND statut = 1";
    $sql .= " ORDER BY rowid ASC";
    $resql = $db->query($sql);
    if (!$resql) {
        return null;
    }

    while ($obj = $db->fetch_object($resql)) {
        $tmpUser = new User($db);
        if ($tmpUser->fetch((int) $obj->rowid) <= 0) {
            continue;
        }

        if (!empty($tmpUser->login) && hash_equals((string) $tmpUser->login, $barcode)) {
            return $tmpUser;
        }

        if (isset($tmpUser->barcode) && $tmpUser->barcode !== '' && hash_equals((string) $tmpUser->barcode, $barcode)) {
            return $tmpUser;
        }

        if (method_exists($tmpUser, 'fetch_optionals')) {
            $tmpUser->fetch_optionals();
            $possibleKeys = array('options_barcode', 'options_card', 'options_cardcode', 'options_manager_card');
            foreach ($possibleKeys as $key) {
                if (!empty($tmpUser->array_options[$key]) && hash_equals((string) $tmpUser->array_options[$key], $barcode)) {
                    return $tmpUser;
                }
            }
        }
    }

    return null;
}

/**
 * Find manager user by login.
 */
function takeposFindManagerByLogin($db, $login)
{
    $login = trim((string) $login);
    if ($login === '') {
        return null;
    }

    $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."user";
    $sql .= " WHERE entity IN (".getEntity('user').")";
    $sql .= " AND statut = 1";
    $sql .= " AND login = '".$db->escape($login)."'";
    $sql .= " LIMIT 1";
    $resql = $db->query($sql);
    if (!$resql) {
        return null;
    }
    $obj = $db->fetch_object($resql);
    if (!$obj) {
        return null;
    }

    $tmpUser = new User($db);
    if ($tmpUser->fetch((int) $obj->rowid) > 0) {
        return $tmpUser;
    }

    return null;
}

/**
 * Validate manager password/PIN with Dolibarr-compatible checks.
 */
function takeposValidateManagerPassword($managerUser, $password)
{
    $password = (string) $password;
    if ($password === '' || empty($managerUser->login)) {
        return false;
    }

    $login = (string) $managerUser->login;
    $entity = isset($managerUser->entity) ? (int) $managerUser->entity : 0;

    if (function_exists('checkLoginPassEntity')) {
        $calls = array(
            function () use ($login, $password, $entity) { return checkLoginPassEntity($login, $password, $entity, 1); },
            function () use ($login, $password, $entity) { return checkLoginPassEntity($login, $password, $entity); },
            function () use ($login, $password) { return checkLoginPassEntity($login, $password); },
        );
        foreach ($calls as $call) {
            try {
                $res = $call();
                if ((int) $res > 0) {
                    return true;
                }
            } catch (Throwable $e) {
            }
        }
    }

    $hashCandidates = array(
        isset($managerUser->pass_indatabase_crypted) ? (string) $managerUser->pass_indatabase_crypted : '',
        isset($managerUser->pass_crypted) ? (string) $managerUser->pass_crypted : '',
        isset($managerUser->pass_indatabase) ? (string) $managerUser->pass_indatabase : '',
        isset($managerUser->pass) ? (string) $managerUser->pass : '',
    );
    foreach ($hashCandidates as $hash) {
        if ($hash === '') {
            continue;
        }
        if (function_exists('dol_verifyHash')) {
            try {
                if (dol_verifyHash($password, $hash, 'auto')) {
                    return true;
                }
            } catch (Throwable $e) {
            }
        }
        if (function_exists('password_verify') && password_verify($password, $hash)) {
            return true;
        }
    }

    return false;
}

/**
 * Check direct user permission and per-user limits for sensitive action.
 */
function takeposUserCanPerformSensitiveAction($db, $currentUser, $actionType, $invoice, $line = null, $requestedNumber = null, &$denyReason = '')
{
    $denyReason = '';
    $meta = takeposSensitiveActionMeta($actionType);
    if (empty($meta)) {
        return true;
    }

    if (defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
        if (is_object($invoice) && $invoice->status == $invoice::STATUS_DRAFT && $invoice->pos_source && $invoice->module_source == 'takepos') {
            return true;
        }
        $denyReason = 'public_context_denied';
        return false;
    }

    if (empty($currentUser) || empty($currentUser->id)) {
        $denyReason = 'missing_user';
        return false;
    }

    try {
        if (!TakeposAccess::isFeatureEnabledForCurrentEntity($db, $meta['feature'])) {
            $denyReason = 'feature_disabled';
            return false;
        }
    } catch (Throwable $e) {
        $denyReason = 'feature_lookup_failed';
        return false;
    }

    if (!empty($currentUser->admin)) {
        return true;
    }

    $hasPermission = false;
    if (class_exists('TakeposUserAccess')) {
        $hasPermission = TakeposUserAccess::userHasPermission($db, $currentUser, $meta['permission']);
    }
    if (!$hasPermission) {
        $denyReason = 'permission_denied';
        return false;
    }

    if ($actionType === 'delete_line' && is_object($line) && isset($line->special_code) && (string) $line->special_code === '4' && !$currentUser->hasRight('takepos', 'editorderedlines')) {
        $denyReason = 'ordered_line_denied';
        return false;
    }

    $entity = !empty($currentUser->entity) ? (int) $currentUser->entity : 1;
    $limits = class_exists('TakeposUserAccess') ? TakeposUserAccess::getUserLimits($db, (int) $currentUser->id, $entity) : array();

    if ($actionType === 'price_override' && is_object($line)) {
        $maxDelta = isset($limits['max_price_override_delta']) ? (float) $limits['max_price_override_delta'] : 0.0;
        if ($maxDelta > 0) {
            $currentPrice = (getDolGlobalInt('TAKEPOS_CHANGE_PRICE_HT') == 1 ? (float) $line->subprice : (((float) $line->qty != 0.0) ? ((float) $line->total_ttc / (float) $line->qty) : (float) $line->subprice));
            $delta = abs((float) $requestedNumber - $currentPrice);
            if ($delta > $maxDelta) {
                $denyReason = 'price_limit_exceeded';
                return false;
            }
        }
    }

    if ($actionType === 'discount' && is_object($line)) {
        $maxPercent = isset($limits['max_discount_percent']) ? (float) $limits['max_discount_percent'] : 0.0;
        if ($maxPercent > 0 && (float) $requestedNumber > $maxPercent) {
            $denyReason = 'discount_percent_limit_exceeded';
            return false;
        }

        $maxAmount = isset($limits['max_discount_amount']) ? (float) $limits['max_discount_amount'] : 0.0;
        if ($maxAmount > 0) {
            $baseAmount = abs((float) $line->subprice * (float) $line->qty);
            $discountAmount = $baseAmount * ((float) $requestedNumber / 100.0);
            if ($discountAmount > $maxAmount) {
                $denyReason = 'discount_amount_limit_exceeded';
                return false;
            }
        }
    }

    return true;
}

/**
 * Validate if manager can approve a specific one-time override.
 */
function takeposManagerCanApproveOverrideForAction($db, $managerUser, $actionType, $line = null)
{
    if (empty($managerUser) || empty($managerUser->id)) {
        return false;
    }
    if (!takeposManagerOverrideEnabled($db)) {
        return false;
    }

    $meta = takeposSensitiveActionMeta($actionType);
    if (empty($meta)) {
        return false;
    }

    if (!empty($managerUser->admin)) {
        return true;
    }

    $hasPermission = class_exists('TakeposUserAccess') ? TakeposUserAccess::userHasPermission($db, $managerUser, $meta['override_permission']) : false;
    if (!$hasPermission) {
        return false;
    }

    if ($actionType === 'delete_line' && is_object($line) && isset($line->special_code) && (string) $line->special_code === '4' && !$managerUser->hasRight('takepos', 'editorderedlines')) {
        return false;
    }

    return true;
}

/**
 * Store one-time override approval in session and optional DB table.
 */
function takeposStoreManagerOverrideSession($db, $override)
{
    if (class_exists('TakeposManagerOverrideService')) {
        return TakeposManagerOverrideService::storeSession($db, $override);
    }
    $now = dol_now();
    $token = '';
    try {
        $token = bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        $token = sha1(uniqid('', true));
    }

    $record = array(
        'authorized' => 1,
        'token' => $token,
        'manager_id' => (int) $override['manager_id'],
        'action' => (string) $override['action'],
        'invoice_id' => (int) $override['invoice_id'],
        'line_id' => (int) $override['line_id'],
        'cashier_id' => (int) $override['cashier_id'],
        'requested_number' => ($override['requested_number'] === '' || $override['requested_number'] === null ? null : (float) $override['requested_number']),
        'created_at' => $now,
        'expires_at' => $now + 300,
    );

    $_SESSION['takepos_manager_override'] = $record;

    if (class_exists('TakeposUserAccess') && TakeposUserAccess::tableExists($db, 'takepos_override_session')) {
        global $conf;
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."takepos_override_session (entity, session_token, action_code, fk_invoice, fk_line, fk_cashier, fk_manager, requested_number, date_approved, date_expires, used) VALUES (";
        $sql .= ((int) $conf->entity).", '".$db->escape($token)."', '".$db->escape($record['action'])."', ".((int) $record['invoice_id']).", ".((int) $record['line_id'] > 0 ? (int) $record['line_id'] : 'NULL').", ".((int) $record['cashier_id']).", ".((int) $record['manager_id']).", ".($record['requested_number'] === null ? 'NULL' : (float) $record['requested_number']).", '".$db->idate($now)."', '".$db->idate($record['expires_at'])."', 0)";
        $db->query($sql);
    }

    return $record;
}

/**
 * Consume one-time manager override from session and optional DB table.
 */
function takeposConsumeManagerOverride($db = null, $usedReason = 'consumed')
{
    if (class_exists('TakeposManagerOverrideService')) {
        TakeposManagerOverrideService::consumeSession($db, $usedReason);
        return;
    }
    $token = '';
    if (!empty($_SESSION['takepos_manager_override']) && is_array($_SESSION['takepos_manager_override']) && !empty($_SESSION['takepos_manager_override']['token'])) {
        $token = (string) $_SESSION['takepos_manager_override']['token'];
    }

    if ($db && $token !== '' && class_exists('TakeposUserAccess') && TakeposUserAccess::tableExists($db, 'takepos_override_session')) {
        $sql = "UPDATE ".MAIN_DB_PREFIX."takepos_override_session";
        $sql .= " SET used = 1, date_used = '".$db->idate(dol_now())."', used_reason = '".$db->escape($usedReason)."'";
        $sql .= " WHERE session_token = '".$db->escape($token)."' AND used = 0";
        $db->query($sql);
    }

    unset($_SESSION['takepos_manager_override']);
}

/**
 * Check one-time manager override validity for requested action context.
 */
function takeposHasValidManagerOverrideForAction($db, $actionType, $invoiceId, $lineId, $cashierId, $requestedNumber = null, $consume = false, &$overrideData = null)
{
    if (class_exists('TakeposManagerOverrideService')) {
        return TakeposManagerOverrideService::hasValidSessionForAction($db, $actionType, $invoiceId, $lineId, $cashierId, $requestedNumber, $consume, $overrideData);
    }
    if (empty($_SESSION['takepos_manager_override']) || !is_array($_SESSION['takepos_manager_override'])) {
        return false;
    }

    $override = $_SESSION['takepos_manager_override'];
    $now = dol_now();

    if (empty($override['authorized'])) {
        return false;
    }
    if (empty($override['action']) || (string) $override['action'] !== (string) $actionType) {
        return false;
    }
    if (empty($override['invoice_id']) || (int) $override['invoice_id'] !== (int) $invoiceId) {
        return false;
    }
    if ((int) $lineId > 0 && (int) $override['line_id'] !== (int) $lineId) {
        return false;
    }
    if (empty($override['cashier_id']) || (int) $override['cashier_id'] !== (int) $cashierId) {
        return false;
    }
    if (empty($override['created_at']) || (int) $override['created_at'] <= 0) {
        return false;
    }
    if (empty($override['expires_at']) || (int) $override['expires_at'] < $now) {
        return false;
    }

    if ($requestedNumber !== null && in_array((string) $actionType, array('price_override', 'discount'), true)) {
        if (!array_key_exists('requested_number', $override) || $override['requested_number'] === null) {
            return false;
        }
        if (abs((float) $override['requested_number'] - (float) $requestedNumber) > 0.00001) {
            return false;
        }
    }

    // FIX (M6): Replaced non-atomic SELECT + UPDATE with a single atomic
    // UPDATE WHERE used=0. The old approach had a race condition: two concurrent
    // requests could both pass the SELECT check and both consume the same manager
    // override token. Using UPDATE + affected_rows ensures only one can claim it.
    if (class_exists('TakeposUserAccess') && TakeposUserAccess::tableExists($db, 'takepos_override_session') && !empty($override['token'])) {
        if ($consume) {
            // Atomic claim: UPDATE WHERE used=0 — only succeeds for one concurrent caller
            $claimSql  = "UPDATE ".MAIN_DB_PREFIX."takepos_override_session";
            $claimSql .= " SET used = 1, date_used = '".$db->idate(dol_now())."', used_reason = 'action_used'";
            $claimSql .= " WHERE session_token = '".$db->escape((string) $override['token'])."'";
            $claimSql .= " AND action_code = '".$db->escape((string) $actionType)."'";
            $claimSql .= " AND fk_invoice = ".((int) $invoiceId);
            if ((int) $lineId > 0) { $claimSql .= " AND fk_line = ".((int) $lineId); }
            $claimSql .= " AND fk_cashier = ".((int) $cashierId);
            $claimSql .= " AND used = 0";
            $claimSql .= " AND date_expires >= '".$db->idate($now)."'";
            $claimRes = $db->query($claimSql);
            if (!$claimRes || $db->affected_rows($db->db) < 1) { return false; }
            unset($_SESSION['takepos_manager_override']);
            return true; // claimed atomically — skip rest of function
        } else {
            // Read-only check (consume=false)
            $chkSql  = "SELECT rowid FROM ".MAIN_DB_PREFIX."takepos_override_session";
            $chkSql .= " WHERE session_token = '".$db->escape((string) $override['token'])."'";
            $chkSql .= " AND action_code = '".$db->escape((string) $actionType)."'";
            $chkSql .= " AND fk_invoice = ".((int) $invoiceId);
            if ((int) $lineId > 0) { $chkSql .= " AND fk_line = ".((int) $lineId); }
            $chkSql .= " AND fk_cashier = ".((int) $cashierId);
            $chkSql .= " AND used = 0";
            $chkSql .= " AND date_expires >= '".$db->idate($now)."' LIMIT 1";
            $chkRes = $db->query($chkSql);
            if (!$chkRes || !$db->fetch_object($chkRes)) { return false; }
        }
    }

    $overrideData = $override;
    if ($consume) {
        takeposConsumeManagerOverride($db, 'action_used');
    }

    return true;
}

/**
 * Backward-compatible delete-line override checker.
 */
function takeposHasValidManagerOverride($invoiceId, $lineId, $cashierId)
{
    global $db;
    $overrideData = null;
    return takeposHasValidManagerOverrideForAction($db, 'delete_line', $invoiceId, $lineId, $cashierId, null, false, $overrideData);
}

/**
 * Backward-compatible manager approval checker for delete flow.
 */
function takeposManagerCanApproveOverride($managerUser, $line = null)
{
    global $db;
    return takeposManagerCanApproveOverrideForAction($db, $managerUser, 'delete_line', $line);
}
$numberRaw = GETPOST('number', 'none');
$number = 0.0;
$numberInvalid = false;
if ($numberRaw !== '') {
    if (class_exists('TakeposInputValidator') && TakeposInputValidator::parseDecimal($numberRaw, $parsedNumber, true, 8)) {
        $number = (float) $parsedNumber;
    } else {
        $numberInvalid = true;
    }
}
$idline = GETPOSTINT('idline');
$selectedline = GETPOSTINT('selectedline');
$desc = GETPOST('desc', 'alphanohtml');
$pay = GETPOST('pay', 'aZ09');
$amountofpaymentRaw = GETPOST('amount', 'none');
$paymentCurrencyCode = takeposNormalizeCurrencyCode(GETPOST('payment_currency', 'aZ09'));
$paymentCurrencyRate = takeposNormalizeCurrencyRate(GETPOST('payment_rate', 'alphanohtml'));
$paymentForeignAmount = 0.0;
$paymentForeignAmountRaw = GETPOST('payment_amount_foreign', 'none');
if ($paymentForeignAmountRaw !== '') {
    $paymentForeignAmount = (float) price2num((string) $paymentForeignAmountRaw, 'MU');
}
$paymentForeignExcess = 0.0;
$paymentForeignExcessRaw = GETPOST('payment_excess_foreign', 'none');
if ($paymentForeignExcessRaw !== '') {
    $paymentForeignExcess = (float) price2num((string) $paymentForeignExcessRaw, 'MU');
}
$amountofpayment = 0.0;
$takeposRememberLastPaidInvoiceId = 0;
$amountofpaymentInvalid = false;
if ($amountofpaymentRaw !== '') {
    if (class_exists('TakeposInputValidator') && TakeposInputValidator::parseDecimal($amountofpaymentRaw, $parsedAmountOfPayment, true, 8)) {
        $amountofpayment = (float) $parsedAmountOfPayment;
    } else {
        $amountofpaymentInvalid = true;
    }
}

$invoiceid = GETPOSTINT('invoiceid');

$strictNumericActions = array('addline', 'updateqty', 'updateprice', 'updatereduction', 'update_reduction_global');
if ($numberInvalid && in_array((string) $action, $strictNumericActions, true)) {
    takeposAuditLog($db, $user, 'security_denied', TakeposAudit::SEVERITY_WARNING, array('action' => (string) $action, 'reason' => 'invalid_numeric_payload', 'number_raw' => (string) $numberRaw), 'Invalid numeric payload denied');
    dol_htmloutput_errors($langs->trans('TakeposInvoiceErrorInvalidNumericValue'), array(), 1);
    $action = 'invalid_numeric_payload';
}
if ($amountofpaymentInvalid && $action === 'valid') {
    takeposAuditLog($db, $user, 'payment_failed', TakeposAudit::SEVERITY_WARNING, array('invoice_id' => (int) $invoiceid, 'reason' => 'invalid_payment_amount', 'amount_raw' => (string) $amountofpaymentRaw), 'Payment blocked because amount payload was invalid', 'invoice', (int) $invoiceid);
    dol_htmloutput_errors($langs->trans('TakeposInvoiceErrorInvalidPaymentAmount'), array(), 1);
    $action = 'invalid_payment_payload';
}
$paycode = $pay;
if ($pay == 'cash') {
    $paycode = 'LIQ'; // For backward compatibility
}
if ($pay == 'card') {
    $paycode = 'CB'; // For backward compatibility
}
if ($pay == 'cheque') {
    $paycode = 'CHQ'; // For backward compatibility
}

// Retrieve paiementid
$paiementid = 0;
if ($paycode) {
    $sql = "SELECT id FROM ".MAIN_DB_PREFIX."c_paiement";
    $sql .= " WHERE entity IN (".getEntity('c_paiement').")";
    $sql .= " AND code = '".$db->escape($paycode)."'";
    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        if ($obj) {
            $paiementid = $obj->id;
        }
    }
}

$invoice = new Facture($db);
// FIX (multi-cashier): when place=0 (non-restaurant mode) and no invoiceid given,
// restore this specific user's last active invoice from session so two cashiers
// on the same terminal do not share/overwrite each other's draft invoice.
$_takeposUserInvoiceKey = 'takepos_user_invoice_' . (int)$user->id . '_' . (int)$takeposterminal;
$_sourceParam    = GETPOST('source', 'alpha');
$_isResumeLoad   = ($_sourceParam === 'resume' && $invoiceid > 0);
$_isHoldNewLoad  = ($_sourceParam === 'hold_new');

if ($_isHoldNewLoad) {
    // After holding a sale: clear session so we get a truly fresh invoice
    unset($_SESSION[$_takeposUserInvoiceKey]);
    $invoiceid = 0;
} elseif (!$_isResumeLoad && $invoiceid <= 0 && $place == 0 && !empty($_SESSION[$_takeposUserInvoiceKey])) {
    $invoiceid = (int) $_SESSION[$_takeposUserInvoiceKey];
}
// On resume: update session immediately so subsequent loads pick the right invoice
if ($_isResumeLoad) {
    $_SESSION[$_takeposUserInvoiceKey] = (int) $invoiceid;
}
if ($invoiceid > 0) {
    $ret = $invoice->fetch($invoiceid);
    // Verify the invoice still belongs to this terminal and is still a draft
    if ($ret > 0 && ($invoice->module_source !== 'takepos' || $invoice->status != Facture::STATUS_DRAFT)) {
        // Invoice is paid or invalid — clear session and start fresh
        unset($_SESSION[$_takeposUserInvoiceKey]);
        $invoice = new Facture($db);
        $invoiceid = 0;
        $ret = $invoice->fetch(0, '(PROV-POS'.$takeposterminal.'-'.$place.')');
    }
} else {
    $ret = $invoice->fetch(0, '(PROV-POS'.$takeposterminal.'-'.$place.')');
}
if ($ret > 0) {
    $placeid = $invoice->id;
    // Save this user's active invoice to session
    if ($place == 0 && $invoice->status == Facture::STATUS_DRAFT) {
        $_SESSION[$_takeposUserInvoiceKey] = (int) $invoice->id;
    }
}

$constforcompanyid = 'CASHDESK_ID_THIRDPARTY'.$takeposterminal;
$defaultThirdPartyId = takeposResolveTerminalThirdPartyId($takeposterminal);

$soc = new Societe($db);
if ($invoice->socid > 0) {
    $soc->fetch($invoice->socid);
} elseif ($defaultThirdPartyId > 0) {
    $soc->fetch($defaultThirdPartyId);
}

// Assign a default project, if relevant
if (isModEnabled('project') && getDolGlobalInt("CASHDESK_ID_PROJECT".$takeposterminal)) {
    $invoice->fk_project = getDolGlobalInt("CASHDESK_ID_PROJECT".$takeposterminal);
}

if ($paymentCurrencyCode !== '') {
    takeposSetSessionCurrencySelection(isset($conf->currency) ? $conf->currency : '', $paymentCurrencyCode, $paymentCurrencyRate);
}

// Change the currency of invoice if it was modified
if (isModEnabled('multicurrency')) {
    $baseCurrencyCode = takeposNormalizeCurrencyCode($conf->currency);
    $sessionCurrencyCode = takeposGetSessionCurrencyCode();
    $sessionCurrencyRate = takeposGetSessionCurrencyRate();

    if ($sessionCurrencyCode !== '') {
        if ($invoice->multicurrency_code != $sessionCurrencyCode) {
            $invoice->setMulticurrencyCode($sessionCurrencyCode);
        }

        if ($invoice->id > 0 && $sessionCurrencyCode === takeposNormalizeCurrencyCode($invoice->multicurrency_code) && $sessionCurrencyRate > 0 && (float) $invoice->multicurrency_tx !== (float) $sessionCurrencyRate) {
            $sqlUpdateCurrency = "UPDATE " . MAIN_DB_PREFIX . "facture"
                . " SET multicurrency_code = '" . $db->escape($sessionCurrencyCode) . "', multicurrency_tx = " . price2num($sessionCurrencyRate, 'MU')
                . " WHERE rowid = " . ((int) $invoice->id);
            if ($db->query($sqlUpdateCurrency)) {
                $invoice->fetch($invoice->id);
            }
        }
    } elseif (
        $invoice->id > 0
        && (int) $invoice->status === (int) Facture::STATUS_DRAFT
        && takeposNormalizeCurrencyCode($invoice->multicurrency_code) !== ''
        && takeposNormalizeCurrencyCode($invoice->multicurrency_code) !== $baseCurrencyCode
    ) {
        $sqlResetCurrency = "UPDATE " . MAIN_DB_PREFIX . "facture"
            . " SET multicurrency_code = '" . $db->escape($baseCurrencyCode) . "', multicurrency_tx = 1"
            . " WHERE rowid = " . ((int) $invoice->id);
        if ($db->query($sqlResetCurrency)) {
            $invoice->fetch($invoice->id);
        }
    }
}
$takeposDisplayCurrencyCode = takeposResolveDocumentCurrencyCode($conf, $invoice);
$takeposDisplayCurrencyRate = takeposResolveDocumentCurrencyRate($db, $conf, $invoice);

$term = empty($_SESSION["takeposterminal"]) ? 1 : $_SESSION["takeposterminal"];


/*
 * Actions
 */
$error = 0;
$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $invoice, $action);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

$sectionwithinvoicelink = '';
$CUSTOMER_DISPLAY_line1 = '';
$CUSTOMER_DISPLAY_line2 = '';
$headerorder = '';
$footerorder = '';
$printer = null;
$idoflineadded = 0;
if (empty($reshook)) {
    if ((($action == 'managerapprove' || $action == 'manager_override_approve') && ($user->hasRight('takepos', 'run') || defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')))) {
        top_httphead('application/json');

        if (GETPOST('token', 'alpha') !== $_SESSION['newtoken']) {
            echo json_encode(array('status' => 'error', 'message' => 'Invalid security token'));
            exit;
        }

        $overrideResult = TakeposManagerOverrideService::approveFromPayload(
            $db,
            $user,
            array(
                'override_action' => GETPOST('override_action', 'aZ09'),
                'invoice_id' => GETPOSTINT('invoiceid'),
                'line_id' => GETPOSTINT('idline'),
                'requested_number' => GETPOST('requested_number', 'none'),
                'manager_barcode' => GETPOST('manager_barcode', 'none'),
                'manager_login' => GETPOST('manager_login', 'none'),
                'manager_password' => GETPOST('manager_password', 'none'),
            )
        );

        if (empty($overrideResult['success'])) {
            echo json_encode(array(
                'status' => 'error',
                'message' => (string) $overrideResult['message'],
                'error' => isset($overrideResult['data']['reason']) ? (string) $overrideResult['data']['reason'] : 'manager_override_failed',
            ));
            exit;
        }

        echo json_encode(array(
            'status' => 'ok',
            'message' => (string) $overrideResult['message'],
            'override_action' => isset($overrideResult['data']['override_action']) ? $overrideResult['data']['override_action'] : '',
            'invoice_id' => isset($overrideResult['data']['invoice_id']) ? (int) $overrideResult['data']['invoice_id'] : 0,
            'line_id' => isset($overrideResult['data']['line_id']) ? (int) $overrideResult['data']['line_id'] : 0,
            'manager_id' => isset($overrideResult['data']['manager_id']) ? (int) $overrideResult['data']['manager_id'] : 0,
            'token' => isset($overrideResult['data']['token']) ? (string) $overrideResult['data']['token'] : '',
        ));
        exit;
    }
    // Action to record a payment on a TakePOS invoice
    if ($action == 'valid' && $user->hasRight('facture', 'creer')) {
        $bankaccount = 0;
        $error = 0;

        if (getDolGlobalString('TAKEPOS_CAN_FORCE_BANK_ACCOUNT_DURING_PAYMENT')) {
            $bankaccount = GETPOSTINT('accountid');
        } else {
            if ($pay == 'LIQ') {
                $bankaccount = takeposResolveTerminalBankAccountId('CASH', $_SESSION["takeposterminal"]);            // For backward compatibility
            } elseif ($pay == "CHQ") {
                $bankaccount = takeposResolveTerminalBankAccountId('CHEQUE', $_SESSION["takeposterminal"]);    // For backward compatibility
            } else {
                $bankaccount = takeposResolveTerminalBankAccountId($pay, $_SESSION["takeposterminal"]);
            }
        }

        // FIX: If no bank account configured for this terminal, auto-find any active account
        // This allows direct payment buttons to work even when terminal isn't fully configured
        if ($bankaccount <= 0 && isModEnabled('bank')) {
            $payType = strtoupper((string) $pay);
            // Map payment code to bank account type
            $typeMap = array('LIQ' => 'CASH', 'CB' => 'CB', 'CASH' => 'CASH', 'VISA' => 'CB', 'CARD' => 'CB');
            $accountType = isset($typeMap[$payType]) ? $typeMap[$payType] : '';

            // Try to find any active bank account of the right type
            $sqlAcc = "SELECT rowid FROM " . MAIN_DB_PREFIX . "bank_account WHERE entity IN (" . getEntity('bank_account') . ") AND clos = 0";
            if ($accountType === 'CASH') {
                $sqlAcc .= " AND courant = 2"; // Cash type
            } elseif ($accountType === 'CB') {
                $sqlAcc .= " AND courant = 1"; // Bank type
            }
            $sqlAcc .= " ORDER BY rowid ASC LIMIT 1";
            $resAcc = $db->query($sqlAcc);
            if ($resAcc && ($objAcc = $db->fetch_object($resAcc))) {
                $bankaccount = (int) $objAcc->rowid;
            }
        }

        if ($bankaccount <= 0 && $pay != "delayed" && isModEnabled("bank")) {
            $errormsg = $langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("BankAccount"));
            $error++;
        }

        $now = dol_now();
        $res = 0;

        $invoice = new Facture($db);
        $invoice->fetch($placeid);
        list($shiftAllowed, $shiftDenyMessage, $activeShiftSummary) = TakeposShiftService::enforcePaymentShiftRequirement($db, $user, isset($_SESSION['takeposterminal']) ? $_SESSION['takeposterminal'] : '', (int) $invoice->id);
        if (!$shiftAllowed) {
            $error++;
            $errormsg = (string) $shiftDenyMessage;
            dol_htmloutput_errors($shiftDenyMessage, array(), 1);
        }
        takeposAuditLog($db, $user, 'payment_started', TakeposAudit::SEVERITY_INFO, array('invoice_id' => (int) $invoice->id, 'payment_code' => $pay, 'amount' => $amountofpayment, 'shift_gate' => $shiftAllowed ? 1 : 0), 'Payment flow started', 'invoice', (int) $invoice->id, $amountofpayment);

        $db->begin();

        if (!$error && !takeposLinkInvoiceToShift($db, $user, (int) $invoice->id, $activeShiftSummary)) {
            $error++;
            dol_htmloutput_errors($db->lasterror(), array(), 1);
        }

        if ($invoice->total_ttc < 0) {
            $invoice->type = $invoice::TYPE_CREDIT_NOTE;

            $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."facture";
            $sql .= " WHERE entity IN (".getEntity('invoice').")";
            $sql .= " AND fk_soc = ".((int) $invoice->socid);
            $sql .= " AND type <> ".Facture::TYPE_CREDIT_NOTE;
            $sql .= " AND fk_statut >= ".$invoice::STATUS_VALIDATED;
            $sql .= " ORDER BY rowid DESC";

            $fk_source = 0;
            $resql = $db->query($sql);
            if ($resql) {
                $obj = $db->fetch_object($resql);
                $fk_source = $obj->rowid;
                if ((int) $fk_source == 0) {
                    fail($langs->transnoentitiesnoconv("NoPreviousBillForCustomer"));
                }
            } else {
                fail($langs->transnoentitiesnoconv("NoPreviousBillForCustomer"));
            }
            $invoice->fk_facture_source = $fk_source;
            $invoice->update($user);
        }

        $constantforkey = 'CASHDESK_NO_DECREASE_STOCK'.(isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : '');
        $allowstockchange = (getDolGlobalString($constantforkey) != "1");

        if ($error) {
            dol_htmloutput_errors($errormsg, [], 1);
        } elseif ($invoice->status != Facture::STATUS_DRAFT) {
            //If invoice is validated but it is not fully paid is not error and make the payment
            $remaintopay = $invoice->getRemainToPay();
            if (($remaintopay > 0 && $invoice->type != Facture::TYPE_CREDIT_NOTE) || ($remaintopay < 0 && $invoice->type == Facture::TYPE_CREDIT_NOTE)) {
                $res = 1;
            } else {
                dol_syslog("Sale already validated");
                dol_htmloutput_errors($langs->trans("InvoiceIsAlreadyValidated", "TakePos"), [], 1);
            }
        } elseif (count($invoice->lines) == 0) {
            $error++;
            dol_syslog('Sale without lines');
            dol_htmloutput_errors($langs->trans("NoLinesToBill", "TakePos"), [], 1);
        } elseif (isModEnabled('stock') && !isModEnabled('productbatch') && $allowstockchange) {
            // Validation of invoice with change into stock when product/lot module is NOT enabled.
            $savconst = getDolGlobalString('STOCK_CALCULATE_ON_BILL');
            $conf->global->STOCK_CALCULATE_ON_BILL = 1;

            $constantforkey = 'CASHDESK_ID_WAREHOUSE'.(isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : '');
            $idwarehouseForValidate = getDolGlobalInt($constantforkey);

            if ($idwarehouseForValidate > 0) {
                // Terminal has a specific warehouse configured.
                // Check if ALL lines have stock in this warehouse. If any product
                // lives in a different warehouse, fall through to the per-line path
                // so we don't get a false "not enough stock" from the default warehouse.
                $allInDefaultWarehouse = true;
                require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
                foreach ($invoice->lines as $line) {
                    if ($line->fk_product <= 0 || $line->product_type != 0) continue;
                    if (!empty($line->no_incdec)) continue;
                    $sqlChk = "SELECT reel FROM ".MAIN_DB_PREFIX."product_stock"
                        ." WHERE fk_product = ".((int)$line->fk_product)
                        ." AND fk_entrepot = ".((int)$idwarehouseForValidate);
                    $resChk = $db->query($sqlChk);
                    $stockInDefault = ($resChk && ($objChk = $db->fetch_object($resChk))) ? (float)$objChk->reel : 0;
                    if ($stockInDefault <= 0) {
                        // Product has no stock in default warehouse — need per-line deduction
                        $allInDefaultWarehouse = false;
                        break;
                    }
                }

                if ($allInDefaultWarehouse) {
                    // All products are stocked in the terminal default warehouse — safe to use it directly.
                    dol_syslog("Validate invoice with stock change. Warehouse from constant ".$constantforkey." = ".$idwarehouseForValidate);
                    $batch_rule = 0;
                    $res = $invoice->validate($user, '', $idwarehouseForValidate, 0, $batch_rule);
                } else {
                    // Mixed warehouses: validate without a warehouse (skips Dolibarr stock check),
                    // then deduct per line from each product correct warehouse.
                    dol_syslog("Validate invoice with mixed-warehouse stock deduction (terminal default = ".$idwarehouseForValidate.")");
                    $batch_rule = 0;
                    $res = $invoice->validate($user, '', 0, 0, $batch_rule);

                    if ($res >= 0) {
                        $inventorycode = dol_print_date(dol_now(), 'dayhourlog');
                        $labelmvt = 'TakePOS - '.$langs->trans("Invoice").' '.$invoice->ref;

                        foreach ($invoice->lines as $line) {
                            if ($line->fk_product <= 0 || $line->product_type != 0) continue;
                            if (!empty($line->no_incdec)) continue;

                            // Prefer: line warehouse > product default warehouse > terminal default warehouse
                            $wid = 0;
                            if (!empty($line->fk_warehouse) && (int)$line->fk_warehouse > 0) {
                                $wid = (int)$line->fk_warehouse;
                            } else {
                                $sqlW = "SELECT fk_default_warehouse FROM ".MAIN_DB_PREFIX."product"
                                    ." WHERE rowid=".((int)$line->fk_product)." LIMIT 1";
                                $resW = $db->query($sqlW);
                                if ($resW) {
                                    $objW = $db->fetch_object($resW);
                                    $wid = ($objW && $objW->fk_default_warehouse) ? (int)$objW->fk_default_warehouse : 0;
                                }
                                if ($wid <= 0) {
                                    $wid = $idwarehouseForValidate; // fall back to terminal default
                                }
                            }

                            if ($wid <= 0) continue;

                            $mouvP = new MouvementStock($db);
                            $mouvP->setOrigin($invoice->element, $invoice->id);
                            $resStock = $mouvP->livraison(
                                $user,
                                (int)$line->fk_product,
                                $wid,
                                (float)$line->qty,
                                (float)$line->price,
                                $labelmvt,
                                '', '', '', '', 0,
                                $inventorycode
                            );
                            if ($resStock < 0) {
                                dol_syslog('TakePOS: mixed-warehouse stock deduction failed for product '.$line->fk_product.': '.$mouvP->error, LOG_WARNING);
                            }
                        }
                    }
                }
            } else {
                // CASHDESK_ID_WAREHOUSE = 0 means "all warehouses" mode.
                // Dolibarr's validate() with idwarehouse=0 skips stock deduction entirely.
                // Instead we validate WITHOUT a warehouse (which validates the invoice),
                // then manually deduct stock per line using each product's fk_default_warehouse.
                $batch_rule = 0;
                $res = $invoice->validate($user, '', 0, 0, $batch_rule);

                if ($res >= 0) {
                    // Manual per-product stock deduction using fk_default_warehouse
                    require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
                    $inventorycode = dol_print_date(dol_now(), 'dayhourlog');
                    $labelmvt = 'TakePOS - '.$langs->trans("Invoice").' '.$invoice->ref;

                    foreach ($invoice->lines as $line) {
                        if ($line->fk_product <= 0 || $line->product_type != 0) continue;
                        if (!empty($line->no_incdec)) continue;

                        // Determine warehouse: fk_warehouse on line > fk_default_warehouse on product > skip
                        $wid = 0;
                        if (!empty($line->fk_warehouse) && (int)$line->fk_warehouse > 0) {
                            $wid = (int)$line->fk_warehouse;
                        } else {
                            $sqlW = "SELECT fk_default_warehouse FROM ".MAIN_DB_PREFIX."product"
                                ." WHERE rowid=".((int)$line->fk_product)." LIMIT 1";
                            $resW = $db->query($sqlW);
                            if ($resW) {
                                $objW = $db->fetch_object($resW);
                                $wid = $objW ? (int)$objW->fk_default_warehouse : 0;
                            }
                        }

                        if ($wid <= 0) continue; // No warehouse — skip stock deduction for this line

                        $mouvP = new MouvementStock($db);
                        $mouvP->setOrigin($invoice->element, $invoice->id);
                        $resStock = $mouvP->livraison(
                            $user,
                            (int)$line->fk_product,
                            $wid,
                            (float)$line->qty,
                            (float)$line->price,
                            $labelmvt,
                            '', '', '', '', 0,
                            $inventorycode
                        );
                        if ($resStock < 0) {
                            dol_syslog('TakePOS: stock deduction failed for product '.$line->fk_product.': '.$mouvP->error, LOG_WARNING);
                        }
                    }
                }
            }

            // Restore setup
            $conf->global->STOCK_CALCULATE_ON_BILL = $savconst;
        } else {
            // Validation of invoice with no change into stock (because param $idwarehouse is not fill)
            $res = $invoice->validate($user);
            if ($res < 0) {
                $error++;
                $langs->load("admin");
                dol_htmloutput_errors($invoice->error == 'NotConfigured' ? $langs->trans("NotConfigured").' (TakePos numbering module)' : $invoice->error, $invoice->errors, 1);
            }
        }

        // Add the payment
        if (!$error && $res >= 0) {
            $remaintopay = $invoice->getRemainToPay();
            if ($remaintopay > 0) {
                $payment = new Paiement($db);

                $payment->datepaye = $now;
                $payment->fk_account = $bankaccount;
                if ($pay == 'LIQ') {
                    $payment->pos_change = GETPOSTFLOAT('excess');
                }

                $payment->amounts[$invoice->id] = $amountofpayment;
                // If user has not used change control, add total invoice payment
                // Or if user has used change control and the amount of payment is higher than remain to pay, add the remain to pay
                if ($amountofpayment <= 0 || $amountofpayment > $remaintopay) {
                    $payment->amounts[$invoice->id] = $remaintopay;
                }
                if (isModEnabled('multicurrency') && $paymentCurrencyCode !== '' && $paymentCurrencyCode !== takeposNormalizeCurrencyCode($conf->currency) && $paymentCurrencyRate > 0) {
                    $payment->multicurrency_code[$invoice->id] = $paymentCurrencyCode;
                    $payment->multicurrency_tx[$invoice->id] = $paymentCurrencyRate;
                    $payment->multicurrency_amounts[$invoice->id] = round(((float) $payment->amounts[$invoice->id]) * $paymentCurrencyRate, 8);
                }

                $payment->paiementid = $paiementid;
                $payment->num_payment = $invoice->ref;

                if ($pay != "delayed") {
                    $createdPaymentId = 0;
                    $res = $payment->create($user);
                    if ($res < 0) {
                        $error++;
                        dol_htmloutput_errors($langs->trans('Error').' '.$payment->error, $payment->errors, 1);
                    } else {
                        $createdPaymentId = ((int) $res > 0 ? (int) $res : (int) $payment->id);
                        $res = $payment->addPaymentToBank($user, 'payment', '(CustomerInvoicePayment)', $bankaccount, '', '');
                        if ($res < 0) {
                            $error++;
                            dol_htmloutput_errors($langs->trans('ErrorNoPaymentDefined').' '.$payment->error, $payment->errors, 1);
                        } elseif (!takeposRecordPaymentCurrencyMeta($db, $user, (int) $invoice->id, $createdPaymentId, $paycode, (float) $payment->amounts[$invoice->id], (float) GETPOSTFLOAT('excess'), $paymentCurrencyCode, $paymentCurrencyRate, $paymentForeignAmount, $paymentForeignExcess)) {
                            $error++;
                            dol_htmloutput_errors($db->lasterror(), array(), 1);
                        }
                    }
                    $remaintopay = $invoice->getRemainToPay(); // Recalculate remain to pay after the payment is recorded
                } elseif (getDolGlobalInt("TAKEPOS_DELAYED_TERMS")) {
                    $invoice->setPaymentTerms(getDolGlobalInt("TAKEPOS_DELAYED_TERMS"));
                }
            }

            if ($remaintopay == 0) {
                dol_syslog("Invoice is paid, so we set it to status Paid");
                $result = $invoice->setPaid($user);
                if ($result > 0) {
                    $invoice->paye = 1;
                    $invoice->status = $invoice::STATUS_CLOSED;
                }
                // set payment method
                $invoice->setPaymentMethods($paiementid);
            } else {
                dol_syslog("Invoice is not paid, remain to pay = ".$remaintopay);
            }
        } else {
            dol_htmloutput_errors($invoice->error, $invoice->errors, 1);
        }

        $warehouseid = 0;
        // Update stock for batch products
        if (!$error && $res >= 0) {
            if (isModEnabled('stock') && isModEnabled('productbatch') && $allowstockchange) {
                // Update stocks
                dol_syslog("Now we record the stock movement for each qualified line");

                // The case !isModEnabled('productbatch') was processed few lines before.
                require_once DOL_DOCUMENT_ROOT . "/product/stock/class/mouvementstock.class.php";
                $constantforkey = 'CASHDESK_ID_WAREHOUSE'.$_SESSION["takeposterminal"];
                $inventorycode = dol_print_date(dol_now(), 'dayhourlog');
                // Label of stock movement will be "TakePOS - Invoice XXXX"
                $labeltakeposmovement = 'TakePOS - '.$langs->trans("Invoice").' '.$invoice->ref;

                foreach ($invoice->lines as $line) {
                    // Use the warehouse id defined on invoice line else in the setup
                    $warehouseid = ($line->fk_warehouse ? $line->fk_warehouse : getDolGlobalInt($constantforkey));

                    // var_dump('fk_product='.$line->fk_product.' batch='.$line->batch.' warehouse='.$line->fk_warehouse.' qty='.$line->qty);
                    if ($line->batch != '' && $warehouseid > 0) {
                        $prod_batch = new Productbatch($db);
                        $prod_batch->find(0, '', '', $line->batch, $warehouseid);

                        $mouvP = new MouvementStock($db);
                        $mouvP->setOrigin($invoice->element, $invoice->id);

                        $res = $mouvP->livraison($user, $line->fk_product, $warehouseid, $line->qty, $line->price, $labeltakeposmovement, '', '', '', $prod_batch->batch, $prod_batch->id, $inventorycode);
                        if ($res < 0) {
                            dol_htmloutput_errors($mouvP->error, $mouvP->errors, 1);
                            $error++;
                        }
                    } else {
                        $mouvP = new MouvementStock($db);
                        $mouvP->setOrigin($invoice->element, $invoice->id);

                        $res = $mouvP->livraison($user, $line->fk_product, $warehouseid, $line->qty, $line->price, $labeltakeposmovement, '', '', '', '', 0, $inventorycode);
                        if ($res < 0) {
                            dol_htmloutput_errors($mouvP->error, $mouvP->errors, 1);
                            $error++;
                        }
                    }
                }
            }
        }

        if (!$error && $res >= 0) {
            $db->commit();
            $_SESSION['takepos_last_paid_invoice_id'] = (int) $invoice->id;
            $takeposRememberLastPaidInvoiceId = (int) $invoice->id;
            takeposAuditLog($db, $user, 'payment_completed', TakeposAudit::SEVERITY_INFO, array('invoice_id' => (int) $invoice->id, 'payment_code' => $pay, 'amount' => $amountofpayment), 'Payment flow completed', 'invoice', (int) $invoice->id, $amountofpayment);
            if (class_exists('TakeposWebhookService')) {
                try {
                    TakeposWebhookService::emitEvent($db, (!empty($user->entity) ? (int) $user->entity : 1), 'sale_completed', array(
                        'invoice_id' => (int) $invoice->id,
                        'invoice_ref' => (string) $invoice->ref,
                        'payment_code' => (string) $pay,
                        'amount' => (float) $amountofpayment,
                        'terminal' => (isset($_SESSION['takeposterminal']) ? (string) $_SESSION['takeposterminal'] : ''),
                    ), $user);
                } catch (Throwable $e) {
                    if (function_exists('dol_syslog')) {
                        dol_syslog('[TakePOS][Invoice] Webhook sale_completed emit failure: ' . $e->getMessage(), LOG_WARNING);
                    }
                }
            }


            // In offline mode, persist a replay-safe queue entry for sale/payment metadata.
            if (class_exists('TakeposOfflineService') && TakeposOfflineService::isOfflineModeSession()) {
                $syncLocalRef = 'INV-' . ((int) $invoice->id);
                try {
                    TakeposOfflineService::queueSaleSubmit($db, $user, (int) $invoice->id, $syncLocalRef);
                    if ((float) $amountofpayment > 0) {
                        TakeposOfflineService::queuePaymentMeta($db, $user, (int) $invoice->id, (string) $pay, (string) $amountofpayment, $syncLocalRef);
                    }
                } catch (Throwable $e) {
                    takeposAuditLog($db, $user, 'sync_failed', TakeposAudit::SEVERITY_WARNING, array('invoice_id' => (int) $invoice->id, 'reason' => $e->getMessage()), 'Offline queue sync registration failed', 'invoice', (int) $invoice->id);
                }
            }

            // Loyalty earning is non-blocking and only runs when loyalty feature is enabled.
            if (class_exists('TakeposLoyaltyService')) {
                try {
                    TakeposLoyaltyService::autoEarnForInvoice($db, $user, (int) $invoice->id);
                } catch (Throwable $e) {
                    takeposAuditLog($db, $user, 'loyalty_redeem_rejected', TakeposAudit::SEVERITY_WARNING, array('invoice_id' => (int) $invoice->id, 'reason' => $e->getMessage()), 'Loyalty earn flow failed', 'invoice', (int) $invoice->id);
                }
            }
        } else {
            $db->rollback();
            takeposAuditLog($db, $user, 'payment_failed', TakeposAudit::SEVERITY_WARNING, array('invoice_id' => (int) $invoice->id, 'payment_code' => $pay, 'amount' => $amountofpayment), 'Payment flow failed', 'invoice', (int) $invoice->id, $amountofpayment);
        }
    }
    $creditnote = null;
    if ($action == 'creditnote' && $user->hasRight('facture', 'creer')) {
        $db->begin();

        $creditnote = new Facture($db);
        $creditnote->socid = $invoice->socid;
        $creditnote->date = dol_now();
        $creditnote->module_source = 'takepos';
        $creditnote->pos_source =  isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : '' ;
        $creditnote->type = Facture::TYPE_CREDIT_NOTE;
        $creditnote->fk_facture_source = $placeid;
        //$creditnote->remise_absolue = $invoice->remise_absolue;
        //$creditnote->remise_percent = $invoice->remise_percent;
        $creditnote->create($user);

        $fk_parent_line = 0; // Initialise

        foreach ($invoice->lines as $line) {
            // Reset fk_parent_line for no child products and special product
            if (($line->product_type != 9 && empty($line->fk_parent_line)) || $line->product_type == 9) {
                $fk_parent_line = 0;
            }

            if (getDolGlobalInt('INVOICE_USE_SITUATION')) {
                if (!empty($invoice->situation_counter)) {
                    $source_fk_prev_id = $line->fk_prev_id; // temporary storing situation invoice fk_prev_id
                    $line->fk_prev_id  = $line->id; // The new line of the new credit note we are creating must be linked to the situation invoice line it is created from
                    if (!empty($invoice->tab_previous_situation_invoice)) {
                        // search the last standard invoice in cycle and the possible credit note between this last and invoice
                        // TODO Move this out of loop of $invoice->lines
                        $tab_jumped_credit_notes = array();
                        $lineIndex = count($invoice->tab_previous_situation_invoice) - 1;
                        $searchPreviousInvoice = true;
                        while ($searchPreviousInvoice) {
                            if ($invoice->tab_previous_situation_invoice[$lineIndex]->situation_cycle_ref || $lineIndex < 1) {
                                $searchPreviousInvoice = false; // find, exit;
                                break;
                            } else {
                                if ($invoice->tab_previous_situation_invoice[$lineIndex]->type == Facture::TYPE_CREDIT_NOTE) {
                                    $tab_jumped_credit_notes[$lineIndex] = $invoice->tab_previous_situation_invoice[$lineIndex]->id;
                                }
                                $lineIndex--; // go to previous invoice in cycle
                            }
                        }

                        $maxPrevSituationPercent = 0;
                        foreach ($invoice->tab_previous_situation_invoice[$lineIndex]->lines as $prevLine) {
                            if ($prevLine->id == $source_fk_prev_id) {
                                $maxPrevSituationPercent = max($maxPrevSituationPercent, $prevLine->situation_percent);

                                //$line->subprice  = $line->subprice - $prevLine->subprice;
                                $line->total_ht  -= $prevLine->total_ht;
                                $line->total_tva -= $prevLine->total_tva;
                                $line->total_ttc -= $prevLine->total_ttc;
                                $line->total_localtax1 -= $prevLine->total_localtax1;
                                $line->total_localtax2 -= $prevLine->total_localtax2;

                                $line->multicurrency_subprice  -= $prevLine->multicurrency_subprice;
                                $line->multicurrency_total_ht  -= $prevLine->multicurrency_total_ht;
                                $line->multicurrency_total_tva -= $prevLine->multicurrency_total_tva;
                                $line->multicurrency_total_ttc -= $prevLine->multicurrency_total_ttc;
                            }
                        }

                        // prorata
                        $line->situation_percent = $maxPrevSituationPercent - $line->situation_percent;

                        //print 'New line based on invoice id '.$invoice->tab_previous_situation_invoice[$lineIndex]->id.' fk_prev_id='.$source_fk_prev_id.' will be fk_prev_id='.$line->fk_prev_id.' '.$line->total_ht.' '.$line->situation_percent.'<br>';

                        // If there is some credit note between last situation invoice and invoice used for credit note generation (note: credit notes are stored as delta)
                        $maxPrevSituationPercent = 0;
                        foreach ($tab_jumped_credit_notes as $index => $creditnoteid) {
                            foreach ($invoice->tab_previous_situation_invoice[$index]->lines as $prevLine) {
                                if ($prevLine->fk_prev_id == $source_fk_prev_id) {
                                    $maxPrevSituationPercent = $prevLine->situation_percent;

                                    $line->total_ht  -= $prevLine->total_ht;
                                    $line->total_tva -= $prevLine->total_tva;
                                    $line->total_ttc -= $prevLine->total_ttc;
                                    $line->total_localtax1 -= $prevLine->total_localtax1;
                                    $line->total_localtax2 -= $prevLine->total_localtax2;

                                    $line->multicurrency_subprice  -= $prevLine->multicurrency_subprice;
                                    $line->multicurrency_total_ht  -= $prevLine->multicurrency_total_ht;
                                    $line->multicurrency_total_tva -= $prevLine->multicurrency_total_tva;
                                    $line->multicurrency_total_ttc -= $prevLine->multicurrency_total_ttc;
                                }
                            }
                        }

                        // prorata
                        $line->situation_percent += $maxPrevSituationPercent;

                        //print 'New line based on invoice id '.$invoice->tab_previous_situation_invoice[$lineIndex]->id.' fk_prev_id='.$source_fk_prev_id.' will be fk_prev_id='.$line->fk_prev_id.' '.$line->total_ht.' '.$line->situation_percent.'<br>';
                    }
                }
            }

            // We update field for credit notes
            $line->fk_facture = $creditnote->id;
            $line->fk_parent_line = $fk_parent_line;

            $line->subprice = -$line->subprice; // invert price for object
            // $line->pa_ht = $line->pa_ht; // we chose to have the buy/cost price always positive, so no inversion of the sign here
            $line->total_ht = -$line->total_ht;
            $line->total_tva = -$line->total_tva;
            $line->total_ttc = -$line->total_ttc;
            $line->total_localtax1 = -$line->total_localtax1;
            $line->total_localtax2 = -$line->total_localtax2;

            $line->multicurrency_subprice = -$line->multicurrency_subprice;
            $line->multicurrency_total_ht = -$line->multicurrency_total_ht;
            $line->multicurrency_total_tva = -$line->multicurrency_total_tva;
            $line->multicurrency_total_ttc = -$line->multicurrency_total_ttc;

            $result = $line->insert(0, 1); // When creating credit note with same lines than source, we must ignore error if discount already linked

            $creditnote->lines[] = $line; // insert new line in current object

            // Defined the new fk_parent_line
            if ($result > 0 && $line->product_type == 9) {
                $fk_parent_line = $result;
            }
        }
        $creditnote->update_price(1);

        // The credit note is create here. We must now validate it.

        $constantforkey = 'CASHDESK_NO_DECREASE_STOCK'.(isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : '');
        $allowstockchange = getDolGlobalString($constantforkey) != "1";

        if (isModEnabled('stock') && !isModEnabled('productbatch') && $allowstockchange) {
            // If module stock is enabled and we do not setup takepo to disable stock decrease
            // The case for isModEnabled('productbatch') is processed few lines later.
            $savconst = getDolGlobalString('STOCK_CALCULATE_ON_BILL');
            $conf->global->STOCK_CALCULATE_ON_BILL = 1;	// We force setup to have update of stock on invoice validation/unvalidation

            $constantforkey = 'CASHDESK_ID_WAREHOUSE'.(isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : '');

            dol_syslog("Validate invoice with stock change into warehouse defined into constant ".$constantforkey." = ".getDolGlobalString($constantforkey)." or warehouseid= ".$warehouseid." if defined.");

            // Validate invoice with stock change into warehouse getDolGlobalInt($constantforkey)
            // Label of stock movement will be the same as when we validate invoice "Invoice XXXX validated"
            $batch_rule = 0;	// Module productbatch is disabled here, so no need for a batch_rule.
            $res = $creditnote->validate($user, '', getDolGlobalInt($constantforkey), 0, $batch_rule);
            if ($res < 0) {
                $error++;
                dol_htmloutput_errors($creditnote->error, $creditnote->errors, 1);
            }

            // Restore setup
            $conf->global->STOCK_CALCULATE_ON_BILL = $savconst;
        } else {
            $res = $creditnote->validate($user);
        }

        // Update stock for batch products
        if (!$error && $res >= 0) {
            if (isModEnabled('stock') && isModEnabled('productbatch') && $allowstockchange) {
                // Update stocks
                dol_syslog("Now we record the stock movement for each qualified line");

                // The case !isModEnabled('productbatch') was processed few lines before.
                require_once DOL_DOCUMENT_ROOT . "/product/stock/class/mouvementstock.class.php";
                $constantforkey = 'CASHDESK_ID_WAREHOUSE'.$_SESSION["takeposterminal"];
                $inventorycode = dol_print_date(dol_now(), 'dayhourlog');
                // Label of stock movement will be "TakePOS - Invoice XXXX"
                $labeltakeposmovement = 'TakePOS - '.$langs->trans("CreditNote").' '.$creditnote->ref;

                foreach ($creditnote->lines as $line) {
                    // Use the warehouse id defined on invoice line else in the setup
                    $warehouseid = ($line->fk_warehouse ? $line->fk_warehouse : getDolGlobalInt($constantforkey));
                    //var_dump('fk_product='.$line->fk_product.' batch='.$line->batch.' warehouse='.$line->fk_warehouse.' qty='.$line->qty);exit;

                    if ($line->batch != '' && $warehouseid > 0) {
                        //$prod_batch = new Productbatch($db);
                        //$prod_batch->find(0, '', '', $line->batch, $warehouseid);

                        $mouvP = new MouvementStock($db);
                        $mouvP->setOrigin($creditnote->element, $creditnote->id);

                        $res = $mouvP->reception($user, $line->fk_product, $warehouseid, $line->qty, $line->price, $labeltakeposmovement, '', '', $line->batch, '', 0, $inventorycode);
                        if ($res < 0) {
                            dol_htmloutput_errors($mouvP->error, $mouvP->errors, 1);
                            $error++;
                        }
                    } else {
                        $mouvP = new MouvementStock($db);
                        $mouvP->setOrigin($creditnote->element, $creditnote->id);

                        $res = $mouvP->reception($user, $line->fk_product, $warehouseid, $line->qty, $line->price, $labeltakeposmovement, '', '', '', '', 0, $inventorycode);
                        if ($res < 0) {
                            dol_htmloutput_errors($mouvP->error, $mouvP->errors, 1);
                            $error++;
                        }
                    }
                }
            }
        }

        if (!$error && $res >= 0) {
            $db->commit();
        } else {
            $creditnote->id = $placeid;	// Creation has failed, we reset to ID of source invoice so we go back to this one in action=history
            $db->rollback();
        }
    }

    if (($action == 'history' || $action == 'creditnote') && $user->hasRight('takepos', 'run')) {
        if ($action == 'creditnote' && $creditnote !== null && $creditnote->id > 0) {	// Test on permission already done
            $placeid = $creditnote->id;
        } else {
            $placeid = GETPOSTINT('placeid');
        }

        $invoice = new Facture($db);
        $invoice->fetch($placeid);
        takeposEnforceInvoiceViewAccess($db, $user, $invoice, $langs->trans('TakeposHistoryAccessDenied'));
    }

    if (takeposIsInvoiceMutationAction($action) && ($user->hasRight('takepos', 'run') || defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE'))) {
        $invoiceIdForShiftCheck = $placeid > 0 ? $placeid : GETPOSTINT('invoiceid');
        if (!takeposEnforceShiftForSale($db, $user, $invoiceIdForShiftCheck)) {
            print '</body></html>';
            exit;
        }
    }

    // If we add a line and no invoice yet, we create the invoice
    if (($action == "addline" || $action == "freezone") && $placeid == 0 && ($user->hasRight('takepos', 'run') || defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE'))) {
        $invoice->socid = $defaultThirdPartyId;

        $dolnowtzuserrel = dol_now('tzuserrel');	// If user is 02 january 22:00, we want to store '02 january'
        $monthuser = dol_print_date($dolnowtzuserrel, '%m', 'gmt');
        $dayuser = dol_print_date($dolnowtzuserrel, '%d', 'gmt');
        $yearuser = dol_print_date($dolnowtzuserrel, '%Y', 'gmt');
        $dateinvoice = dol_mktime(0, 0, 0, (int) $monthuser, (int) $dayuser, (int) $yearuser, 'tzserver');	// If we enter the 02 january, we need to save the 02 january for server

        include_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
        $invoice->date = $dateinvoice;		// Invoice::create() needs a date with no hours

        /*
        print "monthuser=".$monthuser." dayuser=".$dayuser." yearuser=".$yearuser.'<br>';
        print '---<br>';
        print 'TZSERVER: '.dol_print_date(dol_now('tzserver'), 'dayhour', 'gmt').'<br>';
        print 'TZUSER: '.dol_print_date(dol_now('tzuserrel'), 'dayhour', 'gmt').'<br>';
        print 'GMT: '.dol_print_date(dol_now('gmt'), 'dayhour', 'gmt').'<br>';	// Hour in greenwich
        print '---<br>';
        print dol_print_date($invoice->date, 'dayhour', 'gmt').'<br>';
        print "IN SQL, we will got: ".dol_print_date($db->idate($invoice->date), 'dayhour', 'gmt').'<br>';
        print dol_print_date($db->idate($invoice->date, 'gmt'), 'dayhour', 'gmt').'<br>';
        */

        $invoice->module_source = 'takepos';
        $invoice->pos_source =  isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : '' ;
        $invoice->entity = !empty($_SESSION["takeposinvoiceentity"]) ? $_SESSION["takeposinvoiceentity"] : $conf->entity;

        if ($invoice->socid <= 0) {
            $langs->load('errors');
            dol_htmloutput_errors($langs->trans("ErrorModuleSetupNotComplete", "TakePos"), [], 1);
        } else {
            $db->begin();

            // Create invoice
            $placeid = $invoice->create($user);
            // FIX (multi-cashier): save new invoice to this user's session slot
            if ($placeid > 0 && $place == 0) {
                $_SESSION['takepos_user_invoice_' . (int)$user->id . '_' . (int)$takeposterminal] = (int) $placeid;
            }

            if ($placeid < 0) {
                dol_htmloutput_errors($invoice->error, $invoice->errors, 1);
            }
            $sql = "UPDATE ".MAIN_DB_PREFIX."facture";
            $sql .= " SET ref='(PROV-POS".$_SESSION["takeposterminal"]."-".$place.")'";
            $sql .= " WHERE rowid = ".((int) $placeid);
            $resql = $db->query($sql);
            if (!$resql) {
                $error++;
            }

            if (!$error && !takeposLinkInvoiceToShift($db, $user, (int) $placeid, null)) {
                $error++;
                dol_htmloutput_errors($db->lasterror(), array(), 1);
            }

            if (!$error) {
                $db->commit();
            } else {
                $db->rollback();
            }
        }
    }

    $tva_npr = 0;
    // If we add a line by click on product (invoice exists here because it was created juste before if it didn't exists)
    if ($action == "addline" && ($user->hasRight('takepos', 'run') || defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE'))) {
        $prod = new Product($db);
        $prod->fetch($idproduct);

        $customer = new Societe($db);
        $customer->fetch($invoice->socid);

        $datapriceofproduct = $prod->getSellPrice($mysoc, $customer, 0);

        $qty = GETPOSTISSET('qty') ? GETPOSTFLOAT('qty') : 1;
        $price = $datapriceofproduct['pu_ht'];
        $price_ttc = $datapriceofproduct['pu_ttc'];
        //$price_min = $datapriceofproduct['price_min'];
        $price_base_type = empty($datapriceofproduct['price_base_type']) ? 'HT' : $datapriceofproduct['price_base_type'];
        $tva_tx = $datapriceofproduct['tva_tx'];
        $tva_npr = (int) $datapriceofproduct['tva_npr'];

        // Carton barcode: if qty matches a carton barcode qty_multiplier, use carton price.
        // Reads directly from DB — no dependency on index.php passing any parameter.
        if ($qty > 1 && $idproduct > 0) {
            $_ctbl = MAIN_DB_PREFIX . 'takepos_product_barcode';
            $_cchk = $db->query("SHOW TABLES LIKE '" . $db->escape($_ctbl) . "'");
            if ($_cchk && (int) $db->num_rows($_cchk) > 0) {
                $_csql = "SELECT qty_multiplier, price_override FROM " . $_ctbl
                    . " WHERE fk_product = " . (int) $idproduct
                    . " AND entity = " . (int) $conf->entity
                    . " AND qty_multiplier = " . (float) $qty
                    . " AND price_override IS NOT NULL AND price_override > 0"
                    . " LIMIT 1";
                $_cres = $db->query($_csql);
                if ($_cres && $db->num_rows($_cres) > 0) {
                    $_cobj = $db->fetch_object($_cres);
                    $price_ttc = (float) $_cobj->price_override / (float) $_cobj->qty_multiplier;
                    $price = ($tva_tx > 0) ? ($price_ttc / (1 + $tva_tx / 100)) : $price_ttc;
                }
            }
        }

        // Local Taxes
        $localtax1_tx = get_localtax($tva_tx, 1, $customer, $mysoc, $tva_npr);
        $localtax2_tx = get_localtax($tva_tx, 2, $customer, $mysoc, $tva_npr);


        if (isModEnabled('productbatch') && isModEnabled('stock')) {
            $batch = GETPOST('batch', 'alpha');

            if (!empty($batch)) {	// We have just clicked on a batch number, we will execute action=setbatch later...
                $action = "setbatch";
            } elseif ($prod->status_batch > 0) {
                // If product need a lot/serial, we show the list of lot/serial available for the product...

                // Set nb of suggested with nb of batch into the warehouse of the terminal
                $nbofsuggested = 0;
                $prod->load_stock('warehouseopen');

                $constantforkey = 'CASHDESK_ID_WAREHOUSE'.$_SESSION["takeposterminal"];
                $warehouseid = getDolGlobalInt($constantforkey);

                //var_dump($prod->stock_warehouse);
                foreach ($prod->stock_warehouse as $tmpwarehouseid => $tmpval) {
                    if (getDolGlobalInt($constantforkey) && $tmpwarehouseid != getDolGlobalInt($constantforkey)) {
                        // Product to select is not on the warehouse configured for terminal, so we ignore this warehouse
                        continue;
                    }
                    if (!empty($prod->stock_warehouse[$tmpwarehouseid]) && is_array($prod->stock_warehouse[$tmpwarehouseid]->detail_batch)) {
                        if (is_object($prod->stock_warehouse[$tmpwarehouseid]) && count($prod->stock_warehouse[$tmpwarehouseid]->detail_batch)) {
                            foreach ($prod->stock_warehouse[$tmpwarehouseid]->detail_batch as $dbatch) {
                                $nbofsuggested++;
                            }
                        }
                    }
                }
                //var_dump($prod->stock_warehouse);

                echo "<script>\n";
                echo "function addbatch(batch, warehouseid) {\n";
                echo "console.log('We add batch '+batch+' from warehouse id '+warehouseid);\n";
                echo '$("#poslines").load("'.DOL_URL_ROOT.'/takepos/invoice.php?action=addline&batch="+encodeURI(batch)+"&warehouseid="+warehouseid+"&place='.$place.'&idproduct='.$idproduct.'&token='.newToken().'", function() {});'."\n";
                echo "}\n";
                echo "</script>\n";

                $suggestednb = 1;
                echo "<center>".$langs->trans("SearchIntoBatch").": <b> $nbofsuggested </b></center><br><table>";
                foreach ($prod->stock_warehouse as $tmpwarehouseid => $tmpval) {
                    if (getDolGlobalInt($constantforkey) && $tmpwarehouseid != getDolGlobalInt($constantforkey)) {
                        // Not on the forced warehouse, so we ignore this warehouse
                        continue;
                    }
                    if (!empty($prod->stock_warehouse[$tmpwarehouseid]) && is_array($prod->stock_warehouse[$tmpwarehouseid]->detail_batch)) {
                        foreach ($prod->stock_warehouse[$tmpwarehouseid]->detail_batch as $dbatch) {	// $dbatch is instance of Productbatch
                            $batchStock = + $dbatch->qty; // To get a numeric
                            $quantityToBeDelivered = 1;
                            $deliverableQty = min($quantityToBeDelivered, $batchStock);
                            print '<tr>';
                            print '<!-- subj='.$suggestednb.'/'.$nbofsuggested.' -->';
                            print '<!-- Show details of lot/serial in warehouseid='.$tmpwarehouseid.' -->';
                            print '<td class="left">';
                            $detail = '';
                            $detail .= '<span class="opacitymedium">'.$langs->trans("LotSerial").':</span> '.$dbatch->batch;
                            //if (!getDolGlobalString('PRODUCT_DISABLE_SELLBY')) {
                            //$detail .= ' - '.$langs->trans("SellByDate").': '.dol_print_date($dbatch->sellby, "day");
                            //}
                            //if (!getDolGlobalString('PRODUCT_DISABLE_EATBY')) {
                            //$detail .= ' - '.$langs->trans("EatByDate").': '.dol_print_date($dbatch->eatby, "day");
                            //}
                            $detail .= '</td><td>';
                            $detail .= '<span class="opacitymedium">'.$langs->trans("Qty").':</span> '.$dbatch->qty;
                            $detail .= '</td><td>';
                            $detail .= ' <button class="marginleftonly" onclick="addbatch(\''.dol_escape_js($dbatch->batch).'\', '.$tmpwarehouseid.')">'.$langs->trans("Select")."</button>";
                            $detail .= '<br>';
                            print $detail;

                            $quantityToBeDelivered -= $deliverableQty;
                            if ($quantityToBeDelivered < 0) {
                                $quantityToBeDelivered = 0;
                            }
                            $suggestednb++;
                            print '</td></tr>';
                        }
                    }
                }
                print "</table>";

                print '</body></html>';
                exit;
            }
        }


        if (getDolGlobalString('TAKEPOS_SUPPLEMENTS')) {
            require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
            $cat = new Categorie($db);
            $categories = $cat->containing($idproduct, 'product');
            $found = (array_search(getDolGlobalInt('TAKEPOS_SUPPLEMENTS_CATEGORY'), array_column($categories, 'id')));
            if ($found !== false) { // If this product is a supplement
                $sql = "SELECT fk_parent_line FROM ".MAIN_DB_PREFIX."facturedet where rowid = ".((int) $selectedline);
                $resql = $db->query($sql);
                $row = $db->fetch_array($resql);
                if ($row[0] == null) {
                    $parent_line = $selectedline;
                } else {
                    $parent_line = $row[0]; //If the parent line is already a supplement, add the supplement to the main  product
                }
            }
        }

        $err = 0;

        // Ensure stock_reel is populated before the qty-in-stock checks below.
        // For batch products this was already done above; for normal products it
        // was never done, leaving stock_reel = 0 and blocking every add.
        // load_stock('warehouseopen') sums stock across ALL active warehouses,
        // which is correct: if the product has enough stock somewhere, the sale
        // should be allowed regardless of which warehouse holds it.
        if (getDolGlobalString('TAKEPOS_QTY_IN_STOCK') && !isModEnabled('productbatch')) {
            $prod->load_stock('warehouseopen');
        }

        // Group if enabled. Skip group if line already sent to the printer
        if (getDolGlobalString('TAKEPOS_GROUP_SAME_PRODUCT', '1')) {
            foreach ($invoice->lines as $line) {
                if ($line->product_ref == $prod->ref) {
                    if ($line->special_code == 4) {
                        continue;
                    } // If this line is sended to printer create new line
                    // check if qty in stock
                    if (getDolGlobalString('TAKEPOS_QTY_IN_STOCK') && (($line->qty + $qty) > $prod->stock_reel)) {
                        $invoice->error = $langs->trans("ErrorStockIsNotEnough");
                        dol_htmloutput_errors($invoice->error, $invoice->errors, 1);
                        $err++;
                        break;
                    }
                    // Carton grouping: blend existing line price + carton price
                    $_gSubprice = $line->subprice;
                    if ($price_ttc > 0 && abs($price_ttc - $datapriceofproduct['pu_ttc']) > 0.001) {
                        $_gCartonHt = $price * $qty;
                        $_gExistHt = (float) $line->subprice * (float) $line->qty;
                        $_gNewQty = $line->qty + $qty;
                        $_gSubprice = ($_gNewQty > 0) ? (($_gExistHt + $_gCartonHt) / $_gNewQty) : $line->subprice;
                    }
                    $result = $invoice->updateline($line->id, $line->desc, $_gSubprice, $line->qty + $qty, $line->remise_percent, $line->date_start, $line->date_end, $line->tva_tx, $line->localtax1_tx, $line->localtax2_tx, 'HT', $line->info_bits, $line->product_type, $line->fk_parent_line, 0, $line->fk_fournprice, $line->pa_ht, $line->label, $line->special_code, $line->array_options, $line->situation_percent, $line->fk_unit);
                    if ($result < 0) {
                        dol_htmloutput_errors($invoice->error, $invoice->errors, 1);
                    } else {
                        $idoflineadded = $line->id;
                    }
                    break;
                }
            }
        }
        if ($idoflineadded <= 0 && empty($err)) {
            $invoice->fetch_thirdparty();
            $array_options = array();

            $line = array('description' => $prod->description, 'price' => $price, 'tva_tx' => $tva_tx, 'localtax1_tx' => $localtax1_tx, 'localtax2_tx' => $localtax2_tx, 'remise_percent' => $customer->remise_percent, 'price_ttc' => $price_ttc, 'array_options' => $array_options);

            /* setup of margin calculation */
            if (getDolGlobalString('MARGIN_TYPE')) {
                if (getDolGlobalString('MARGIN_TYPE') == 'pmp' && !empty($prod->pmp)) {
                    $line['fk_fournprice'] = null;
                    $line['pa_ht'] = $prod->pmp;
                } elseif (getDolGlobalString('MARGIN_TYPE') == 'costprice' && !empty($prod->cost_price)) {
                    $line['fk_fournprice'] = null;
                    $line['pa_ht'] = $prod->cost_price;
                } else {
                    // default is fournprice
                    require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.product.class.php';
                    $pf = new ProductFournisseur($db);
                    if ($pf->find_min_price_product_fournisseur($idproduct, $qty) > 0) {
                        $line['fk_fournprice'] = $pf->product_fourn_price_id;
                        $line['pa_ht'] = $pf->fourn_unitprice_with_discount;
                        if (getDolGlobalString('PRODUCT_CHARGES') && $pf->fourn_charges > 0) {
                            $line['pa_ht'] += (float) $pf->fourn_charges / $pf->fourn_qty;
                        }
                    }
                }
            }

            // complete line by hook
            $parameters = array('prod' => $prod, 'line' => $line);
            $reshook = $hookmanager->executeHooks('completeTakePosAddLine', $parameters, $invoice, $action);    // Note that $action and $line may have been modified by some hooks
            if ($reshook < 0) {
                setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
            }


            if (empty($reshook)) {
                if (!empty($hookmanager->resArray)) {
                    $line = $hookmanager->resArray;
                }

                // check if qty in stock
                if (getDolGlobalString('TAKEPOS_QTY_IN_STOCK') && $qty > $prod->stock_reel) {
                    $invoice->error = $langs->trans("ErrorStockIsNotEnough");
                    dol_htmloutput_errors($invoice->error, $invoice->errors, 1);
                    $err++;
                }

                if (empty($err)) {
                    $idoflineadded = $invoice->addline($line['description'], $line['price'], $qty, $line['tva_tx'], $line['localtax1_tx'], $line['localtax2_tx'], $idproduct, (float) $line['remise_percent'], '', 0, 0, 0, 0, $price_base_type, $line['price_ttc'], $prod->type, -1, 0, '', 0, (empty($parent_line) ? '' : $parent_line), (empty($line['fk_fournprice']) ? 0 : $line['fk_fournprice']), (empty($line['pa_ht']) ? '' : $line['pa_ht']), '', $line['array_options'], 100, 0, null, 0);
                }
            }

            if (getDolGlobalString('TAKEPOS_CUSTOMER_DISPLAY')) {
                $CUSTOMER_DISPLAY_line1 = $prod->label;
                $CUSTOMER_DISPLAY_line2 = price($price_ttc);
            }
        }

        $invoice->fetch($placeid);
    }

    // If we add a line by submitting freezone form (invoice exists here because it was created just before if it didn't exist)
    if ($idoflineadded > 0) {
        takeposAuditLog($db, $user, 'add_product_line', TakeposAudit::SEVERITY_INFO, array('invoice_id' => (int) $placeid, 'line_id' => (int) $idoflineadded, 'product_id' => (int) $idproduct, 'qty' => isset($qty) ? (float) $qty : 0.0), 'Product line added', 'invoice', (int) $placeid);
    }
    if ($action == "freezone" && $user->hasRight('takepos', 'run')) {
        $customer = new Societe($db);
        $customer->fetch($invoice->socid);

        $tva_tx = GETPOST('tva_tx', 'alpha');
        if ($tva_tx != '') {
            if (!preg_match('/\((.*)\)/', $tva_tx)) {
                $tva_tx = price2num($tva_tx);
            }
        } else {
            $tva_tx = get_default_tva($mysoc, $customer);
        }

        // Local Taxes
        $localtax1_tx = get_localtax($tva_tx, 1, $customer, $mysoc, $tva_npr);
        $localtax2_tx = get_localtax($tva_tx, 2, $customer, $mysoc, $tva_npr);

        $res = $invoice->addline($desc, $number, 1, $tva_tx, $localtax1_tx, $localtax2_tx, 0, 0, '', 0, 0, 0, 0, getDolGlobalInt('TAKEPOS_DISCOUNT_TTC') ? ($number >= 0 ? 'HT' : 'TTC') : (getDolGlobalInt('TAKEPOS_CHANGE_PRICE_HT') ? 'HT' : 'TTC'), $number, 0, -1, 0, '', 0, 0, 0, 0, '', array(), 100, 0, null, 0);
        if ($res < 0) {
            dol_htmloutput_errors($invoice->error, $invoice->errors, 1);
        }
        $invoice->fetch($placeid);
    }

    if ($action == "addnote" && ($user->hasRight('takepos', 'run') || defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE'))) {
        $desc = GETPOST('addnote', 'alpha');
        if ($idline == 0) {
            $invoice->update_note($desc, '_public');
        } else {
            foreach ($invoice->lines as $line) {
                if ($line->id == $idline) {
                    $result = $invoice->updateline($line->id, $desc, $line->subprice, $line->qty, $line->remise_percent, $line->date_start, $line->date_end, $line->tva_tx, $line->localtax1_tx, $line->localtax2_tx, 'HT', $line->info_bits, $line->product_type, $line->fk_parent_line, 0, $line->fk_fournprice, $line->pa_ht, $line->label, $line->special_code, $line->array_options, $line->situation_percent, $line->fk_unit);
                }
            }
        }
        $invoice->fetch($placeid);
    }

    if ($action == "deleteline" && ($user->hasRight('takepos', 'run') || defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE'))) {
        $targetLineId = takeposResolveDeleteLineId($db, $placeid, $idline);
        $invoiceLine = takeposFindInvoiceLineById($invoice, $targetLineId);
        $denyReason = '';
        $overrideUsed = false;
        $overrideData = null;

        $deleteAllowed = takeposUserCanPerformSensitiveAction($db, $user, 'delete_line', $invoice, $invoiceLine, null, $denyReason);
        if (!$deleteAllowed && takeposDenyReasonAllowsOverride($denyReason) && takeposManagerOverrideEnabled($db)) {
            if ($placeid > 0 && $targetLineId > 0 && takeposHasValidManagerOverrideForAction($db, 'delete_line', $placeid, $targetLineId, (int) $user->id, null, true, $overrideData)) {
                $deleteAllowed = true;
                $overrideUsed = true;
            }
        }

        if (!$deleteAllowed) {
            takeposAuditLog($db, $user, 'security_denied', TakeposAudit::SEVERITY_WARNING, array('action' => 'delete_line', 'invoice_id' => (int) $placeid, 'line_id' => (int) $targetLineId, 'reason' => $denyReason), 'Delete line denied');
            dol_htmloutput_errors($langs->trans('TakeposInvoiceErrorManagerApprovalDeleteLine'), array(), 1);
        } else {
            if ($targetLineId > 0 && $placeid > 0) {
                $invoice->deleteLine($targetLineId);
                $invoice->fetch($placeid);
                takeposAuditLog($db, $user, 'remove_product_line', TakeposAudit::SEVERITY_WARNING, array('invoice_id' => (int) $placeid, 'line_id' => (int) $targetLineId, 'override_used' => $overrideUsed ? 1 : 0), 'Product line removed', 'invoice', (int) $placeid);
            }

            if (count($invoice->lines) == 0) {
                $invoice->delete($user);

                if (defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
                    header("Location: ".DOL_URL_ROOT."/takepos/public/auto_order.php");
                } else {
                    header("Location: ".DOL_URL_ROOT."/takepos/invoice.php");
                }
                exit;
            }
        }
    }
    // Action to delete or discard an invoice
    if ($action == "delete" && ($user->hasRight('takepos', 'run') || defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE'))) {
        if ($placeid > 0) {
            $result = $invoice->fetch($placeid);
            $denyReason = '';
            $overrideUsed = false;
            $overrideData = null;

            $cancelAllowed = takeposUserCanPerformSensitiveAction($db, $user, 'invoice_cancel', $invoice, null, null, $denyReason);
            if (!$cancelAllowed && takeposDenyReasonAllowsOverride($denyReason) && takeposManagerOverrideEnabled($db)) {
                if (takeposHasValidManagerOverrideForAction($db, 'invoice_cancel', $placeid, 0, (int) $user->id, null, true, $overrideData)) {
                    $cancelAllowed = true;
                    $overrideUsed = true;
                }
            }

            takeposAuditLog($db, $user, 'cancel_invoice_attempt', TakeposAudit::SEVERITY_WARNING, array('invoice_id' => (int) $placeid, 'override_used' => $overrideUsed ? 1 : 0), 'Cancel invoice attempted', 'invoice', (int) $placeid);

            if (!$cancelAllowed) {
                takeposAuditLog($db, $user, 'security_denied', TakeposAudit::SEVERITY_WARNING, array('action' => 'invoice_cancel', 'invoice_id' => (int) $placeid, 'reason' => $denyReason), 'Cancel invoice denied');
                dol_htmloutput_errors($langs->trans('TakeposInvoiceErrorManagerApprovalCancelInvoice'), array(), 1);
            } elseif ($result > 0 && $invoice->status == Facture::STATUS_DRAFT) {
                $db->begin();

                $resdeletelines = 1;
                foreach ($invoice->lines as $line) {
                    $tmpres = $invoice->deleteLine($line->id);
                    if ($tmpres < 0) {
                        $resdeletelines = 0;
                        break;
                    }
                }

                $sql = "UPDATE ".MAIN_DB_PREFIX."facture";
                $varforconst = 'CASHDESK_ID_THIRDPARTY'.$_SESSION["takeposterminal"];
                $sql .= " SET fk_soc = ".((int) takeposResolveTerminalThirdPartyId($_SESSION["takeposterminal"])).", ";
                $sql .= " datec = '".$db->idate(dol_now())."'";
                $sql .= " WHERE entity IN (".getEntity('invoice').")";
                $sql .= " AND ref = '(PROV-POS".$db->escape($_SESSION["takeposterminal"]."-".$place).")'";
                $resql1 = $db->query($sql);

                if ($resdeletelines && $resql1) {
                    $db->commit();
                    takeposAuditLog($db, $user, 'cancel_invoice_success', TakeposAudit::SEVERITY_WARNING, array('invoice_id' => (int) $placeid, 'override_used' => $overrideUsed ? 1 : 0), 'Invoice canceled', 'invoice', (int) $placeid);
                } else {
                    $db->rollback();
                    takeposAuditLog($db, $user, 'cancel_invoice_attempt', TakeposAudit::SEVERITY_WARNING, array('invoice_id' => (int) $placeid, 'status' => 'transaction_failed'), 'Invoice cancel transaction failed', 'invoice', (int) $placeid);
                }

                $invoice->fetch($placeid);
            } elseif ($result > 0) {
                takeposAuditLog($db, $user, 'security_denied', TakeposAudit::SEVERITY_WARNING, array('action' => 'invoice_cancel', 'invoice_id' => (int) $placeid, 'reason' => 'invoice_not_draft'), 'Cancel invoice denied for non-draft status');
                dol_htmloutput_errors($langs->trans('TakeposInvoiceErrorCannotCancelStatus'), array(), 1);
            }
        }
    }
    if (!function_exists('takeposBreakBlendedSubpriceHt')) {
        /**
         * Bulk / packaging "break" pricing.
         *
         * Packages are defined as rows in llx_takepos_product_barcode where
         * qty_multiplier = number of base units in the package and
         * price_override = price (TTC) of the WHOLE package.
         *
         * Given the total quantity on a line, this greedily fills the largest
         * packages first, then charges the remainder at the base unit price, and
         * returns the resulting *blended* unit HT price (line total / qty).
         *
         * Returns null when the product has no package defined, so the caller can
         * leave the existing (possibly manually overridden) price untouched.
         */
        function takeposBreakBlendedSubpriceHt($db, $fk_product, $qty, $baseUnitHt, $tva_tx, $entity)
        {
            $qty = (float) $qty;
            if ((int) $fk_product <= 0 || $qty <= 0) {
                return null;
            }
            $table = MAIN_DB_PREFIX.'takepos_product_barcode';
            $chk = $db->query("SHOW TABLES LIKE '".$db->escape($table)."'");
            if (!$chk || (int) $db->num_rows($chk) == 0) {
                return null;
            }
            $sql = "SELECT qty_multiplier, price_override FROM ".$table
                ." WHERE fk_product = ".((int) $fk_product)
                ." AND entity = ".((int) $entity)
                ." AND qty_multiplier > 1 AND price_override IS NOT NULL AND price_override > 0"
                ." ORDER BY qty_multiplier DESC";
            $res = $db->query($sql);
            if (!$res || (int) $db->num_rows($res) == 0) {
                return null;   // no packaging -> caller keeps current price
            }
            $div = 1 + ((float) $tva_tx / 100);
            $remaining = $qty;
            $totalHt = 0.0;
            while ($obj = $db->fetch_object($res)) {
                $n = (float) $obj->qty_multiplier;
                if ($n <= 1) {
                    continue;
                }
                $packs = floor($remaining / $n);
                if ($packs > 0) {
                    $pkgHt = ($div > 0) ? ((float) $obj->price_override / $div) : (float) $obj->price_override;
                    $totalHt += $packs * $pkgHt;
                    $remaining -= $packs * $n;
                }
            }
            $totalHt += $remaining * (float) $baseUnitHt;
            return ($qty > 0) ? ($totalHt / $qty) : null;
        }
    }

    if ($action == "updateqty") {	// Test on permission is done later
        foreach ($invoice->lines as $line) {
            if ($line->id == $idline) {
                $permissiontoupdateline = ($user->hasRight('takepos', 'editlines') && ($user->hasRight('takepos', 'editorderedlines') || $line->special_code != "4"));
                if (defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
                    if ($invoice->status == $invoice::STATUS_DRAFT && $invoice->pos_source && $invoice->module_source == 'takepos') {
                        $permissiontoupdateline = true;
                        // TODO Add also a test on $_SESSION('publicobjectid'] defined at creation of object
                        // TODO Check also that invoice->ref is (PROV-POS1-2) with 1 = terminal and 2, the table ID
                    }
                }
                if (!$permissiontoupdateline) {
                    dol_htmloutput_errors($langs->trans('TakeposInvoiceErrorNoPermissionUpdateQty'), [], 1);
                } else {
                    // SERVER-SIDE STOCK GUARD: block if new qty exceeds available stock
                    $stockBlocked = false;
                    if (getDolGlobalInt('TAKEPOS_PRODUCT_IN_STOCK') == 1 && $line->fk_product > 0 && $line->product_type == 0) {
                        require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
                        $prodCheck = new Product($db);
                        if ($prodCheck->fetch($line->fk_product) > 0 && empty($prodCheck->no_incdec)) {
                            $terminal    = isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : 0;
                            $warehouseId = getDolGlobalInt('CASHDESK_ID_WAREHOUSE'.$terminal);
                            if ($warehouseId > 0) {
                                $sqlSt = "SELECT reel FROM ".MAIN_DB_PREFIX."product_stock WHERE fk_product = ".((int) $line->fk_product)." AND fk_entrepot = ".((int) $warehouseId);
                            } else {
                                $sqlSt = "SELECT COALESCE(SUM(reel),0) AS reel FROM ".MAIN_DB_PREFIX."product_stock WHERE fk_product = ".((int) $line->fk_product);
                            }
                            $resSt = $db->query($sqlSt);
                            $stockReal = ($resSt && $db->num_rows($resSt)) ? (float) $db->fetch_object($resSt)->reel : 0;
                            // stock available for this line = total stock - qty in OTHER lines for same product
                            $qtyOtherLines = 0;
                            foreach ($invoice->lines as $otherLine) {
                                if ($otherLine->fk_product == $line->fk_product && $otherLine->id != $line->id) {
                                    $qtyOtherLines += (float) $otherLine->qty;
                                }
                            }
                            $stockForThisLine = $stockReal - $qtyOtherLines;
                            if ($number > $stockForThisLine) {
                                dol_htmloutput_errors($langs->trans('ErrorStockIsNotEnough'), [], 1);
                                $stockBlocked = true;
                            }
                        }
                    }

                    if (!$stockBlocked) {
                        $vatratecode = $line->tva_tx;
                        if ($line->vat_src_code) {
                            $vatratecode .= ' ('.$line->vat_src_code.')';
                        }

                        // ── Packaging "break" pricing: recompute the unit price from the
                        //    base product price + package rules for the new quantity. Only
                        //    products that actually have a package defined are affected;
                        //    others keep their current (possibly manual) price.
                        $useSubprice = $line->subprice;

                        $result = $invoice->updateline($line->id, $line->desc, $useSubprice, $number, $line->remise_percent, $line->date_start, $line->date_end, $vatratecode, $line->localtax1_tx, $line->localtax2_tx, 'HT', $line->info_bits, $line->product_type, $line->fk_parent_line, 0, $line->fk_fournprice, $line->pa_ht, $line->label, $line->special_code, $line->array_options, $line->situation_percent, $line->fk_unit);
                        if ($result >= 0) { takeposAuditLog($db, $user, 'change_qty', TakeposAudit::SEVERITY_INFO, array('invoice_id' => (int) $placeid, 'line_id' => (int) $line->id, 'old_qty' => (float) $line->qty, 'new_qty' => (float) $number), 'Line quantity changed', 'invoice', (int) $placeid); }
                    }
                }
            }
        }

        $invoice->fetch($placeid);
    }

    if ($action == "updateprice") {
        $customer = new Societe($db);
        $customer->fetch($invoice->socid);

        foreach ($invoice->lines as $line) {
            if ($line->id == $idline) {
                $overrideUsed = false;
                $denyReason = '';
                takeposAuditLog($db, $user, 'price_override_attempt', TakeposAudit::SEVERITY_WARNING, array('invoice_id' => (int) $placeid, 'line_id' => (int) $line->id, 'old_price' => (float) $line->subprice, 'new_price' => (float) $number), 'Price override attempted', 'invoice', (int) $placeid);

                $prod = new Product($db);
                $prod->fetch($line->fk_product);
                $datapriceofproduct = $prod->getSellPrice($mysoc, $customer, 0);
                $price_min = $datapriceofproduct['price_min'];
                $usercanproductignorepricemin = ((getDolGlobalString('MAIN_USE_ADVANCED_PERMS') && !$user->hasRight('produit', 'ignore_price_min_advance')) || !getDolGlobalString('MAIN_USE_ADVANCED_PERMS'));

                $vatratecleaned = $line->tva_tx;
                $reg = array();
                if (preg_match('/^(.*)\s*\((.*)\)$/', (string) $line->tva_tx, $reg)) {
                    $vatratecleaned = trim($reg[1]);
                }

                $pu_ht = price2num((float) price2num($number, 'MU') / (1 + ((float) $vatratecleaned / 100)), 'MU');
                if ($usercanproductignorepricemin && (!empty($price_min) && ((float) price2num($pu_ht) * (1 - (float) price2num($line->remise_percent) / 100) < price2num($price_min)))) {
                    $langs->load("products");
                    dol_htmloutput_errors($langs->trans("CantBeLessThanMinPrice", price(price2num($price_min, 'MU'), 0, $langs, 0, 0, -1, $conf->currency)));
                } else {
                    $permissionAllowed = takeposUserCanPerformSensitiveAction($db, $user, 'price_override', $invoice, $line, $number, $denyReason);
                    if (!$permissionAllowed && takeposDenyReasonAllowsOverride($denyReason) && takeposManagerOverrideEnabled($db)) {
                        $overrideData = null;
                        if (takeposHasValidManagerOverrideForAction($db, 'price_override', $placeid, (int) $line->id, (int) $user->id, $number, true, $overrideData)) {
                            $permissionAllowed = true;
                            $overrideUsed = true;
                        }
                    }

                    $vatratecode = $line->tva_tx;
                    if ($line->vat_src_code) {
                        $vatratecode .= ' ('.$line->vat_src_code.')';
                    }

                    if (!$permissionAllowed) {
                        takeposAuditLog($db, $user, 'security_denied', TakeposAudit::SEVERITY_WARNING, array('action' => 'price_override', 'invoice_id' => (int) $placeid, 'line_id' => (int) $line->id, 'reason' => $denyReason), 'Price override denied');
                        dol_htmloutput_errors($langs->trans('TakeposInvoiceErrorManagerApprovalPriceOverride'), array(), 1);
                    } elseif (getDolGlobalInt('TAKEPOS_CHANGE_PRICE_HT')  == 1) {
                        $result = $invoice->updateline($line->id, $line->desc, $number, $line->qty, $line->remise_percent, $line->date_start, $line->date_end, $vatratecode, $line->localtax1_tx, $line->localtax2_tx, 'HT', $line->info_bits, $line->product_type, $line->fk_parent_line, 0, $line->fk_fournprice, $line->pa_ht, $line->label, $line->special_code, $line->array_options, $line->situation_percent, $line->fk_unit);
                        if ($result >= 0) {
                            takeposAuditLog($db, $user, 'price_override_success', TakeposAudit::SEVERITY_INFO, array('invoice_id' => (int) $placeid, 'line_id' => (int) $line->id, 'old_price' => (float) $line->subprice, 'new_price' => (float) $number, 'override_used' => $overrideUsed ? 1 : 0), 'Price override success', 'invoice', (int) $placeid);
                        }
                    } else {
                        $result = $invoice->updateline($line->id, $line->desc, $number, $line->qty, $line->remise_percent, $line->date_start, $line->date_end, $vatratecode, $line->localtax1_tx, $line->localtax2_tx, 'TTC', $line->info_bits, $line->product_type, $line->fk_parent_line, 0, $line->fk_fournprice, $line->pa_ht, $line->label, $line->special_code, $line->array_options, $line->situation_percent, $line->fk_unit);
                        if ($result >= 0) {
                            takeposAuditLog($db, $user, 'price_override_success', TakeposAudit::SEVERITY_INFO, array('invoice_id' => (int) $placeid, 'line_id' => (int) $line->id, 'old_price' => (float) $line->subprice, 'new_price' => (float) $number, 'override_used' => $overrideUsed ? 1 : 0), 'Price override success', 'invoice', (int) $placeid);
                        }
                    }
                }
            }
        }

        $invoice->fetch($placeid);
    }
    if ($action == "updatereduction") {
        $customer = new Societe($db);
        $customer->fetch($invoice->socid);

        foreach ($invoice->lines as $line) {
            if ($line->id == $idline) {
                dol_syslog("updatereduction Process line ".$line->id.' to apply discount of '.$number.'%');
                takeposAuditLog($db, $user, 'apply_discount_attempt', TakeposAudit::SEVERITY_WARNING, array('invoice_id' => (int) $placeid, 'line_id' => (int) $line->id, 'discount_percent' => (float) $number), 'Discount attempt', 'invoice', (int) $placeid);

                $prod = new Product($db);
                $prod->fetch($line->fk_product);

                $datapriceofproduct = $prod->getSellPrice($mysoc, $customer, 0);
                $price_min = $datapriceofproduct['price_min'];
                $usercanproductignorepricemin = ((getDolGlobalString('MAIN_USE_ADVANCED_PERMS') && !$user->hasRight('produit', 'ignore_price_min_advance')) || !getDolGlobalString('MAIN_USE_ADVANCED_PERMS'));

                if ($usercanproductignorepricemin && (!empty($price_min) && ((float) price2num($line->subprice) * (1 - (float) price2num($number) / 100) < (float) price2num($price_min)))) {
                    $langs->load("products");
                    dol_htmloutput_errors($langs->trans("CantBeLessThanMinPrice", price(price2num($price_min, 'MU'), 0, $langs, 0, 0, -1, $conf->currency)));
                } else {
                    $denyReason = '';
                    $overrideUsed = false;
                    $permissionAllowed = takeposUserCanPerformSensitiveAction($db, $user, 'discount', $invoice, $line, $number, $denyReason);
                    if (!$permissionAllowed && takeposDenyReasonAllowsOverride($denyReason) && takeposManagerOverrideEnabled($db)) {
                        $overrideData = null;
                        if (takeposHasValidManagerOverrideForAction($db, 'discount', $placeid, (int) $line->id, (int) $user->id, $number, true, $overrideData)) {
                            $permissionAllowed = true;
                            $overrideUsed = true;
                        }
                    }

                    if (!$permissionAllowed) {
                        takeposAuditLog($db, $user, 'security_denied', TakeposAudit::SEVERITY_WARNING, array('action' => 'discount', 'invoice_id' => (int) $placeid, 'line_id' => (int) $line->id, 'reason' => $denyReason), 'Discount denied');
                        dol_htmloutput_errors($langs->trans('TakeposInvoiceErrorManagerApprovalDiscount'), array(), 1);
                    } else {
                        $vatratecode = $line->tva_tx;
                        if ($line->vat_src_code) {
                            $vatratecode .= ' ('.$line->vat_src_code.')';
                        }
                        $result = $invoice->updateline($line->id, $line->desc, $line->subprice, $line->qty, $number, $line->date_start, $line->date_end, $vatratecode, $line->localtax1_tx, $line->localtax2_tx, 'HT', $line->info_bits, $line->product_type, $line->fk_parent_line, 0, $line->fk_fournprice, $line->pa_ht, $line->label, $line->special_code, $line->array_options, $line->situation_percent, $line->fk_unit);
                        if ($result >= 0) {
                            takeposAuditLog($db, $user, 'apply_discount_success', TakeposAudit::SEVERITY_INFO, array('invoice_id' => (int) $placeid, 'line_id' => (int) $line->id, 'old_discount_percent' => (float) $line->remise_percent, 'new_discount_percent' => (float) $number, 'override_used' => $overrideUsed ? 1 : 0), 'Discount applied', 'invoice', (int) $placeid);
                        }
                    }
                }
            }
        }

        $invoice->fetch($placeid);
    } elseif ($action == 'update_reduction_global') {
        $denyReason = '';
        $permissionAllowed = true;
        foreach ($invoice->lines as $line) {
            if (!takeposUserCanPerformSensitiveAction($db, $user, 'discount', $invoice, $line, $number, $denyReason)) {
                $permissionAllowed = false;
                break;
            }
        }

        if (!$permissionAllowed) {
            takeposAuditLog($db, $user, 'security_denied', TakeposAudit::SEVERITY_WARNING, array('action' => 'discount_global', 'invoice_id' => (int) $placeid, 'reason' => $denyReason), 'Global discount denied');
            dol_htmloutput_errors($langs->trans('TakeposInvoiceErrorGlobalDiscountDenied'), array(), 1);
        } else {
            foreach ($invoice->lines as $line) {
                $vatratecode = $line->tva_tx;
                if ($line->vat_src_code) {
                    $vatratecode .= ' ('.$line->vat_src_code.')';
                }
                $result = $invoice->updateline($line->id, $line->desc, $line->subprice, $line->qty, $number, $line->date_start, $line->date_end, $vatratecode, $line->localtax1_tx, $line->localtax2_tx, 'HT', $line->info_bits, $line->product_type, $line->fk_parent_line, 0, $line->fk_fournprice, $line->pa_ht, $line->label, $line->special_code, $line->array_options, $line->situation_percent, $line->fk_unit);
            }
            takeposAuditLog($db, $user, 'apply_discount_success', TakeposAudit::SEVERITY_INFO, array('invoice_id' => (int) $placeid, 'line_id' => 0, 'new_discount_percent' => (float) $number, 'scope' => 'global'), 'Global discount applied', 'invoice', (int) $placeid);
        }

        $invoice->fetch($placeid);
    }

    if ($action == "setbatch" && ($user->hasRight('takepos', 'run') || defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE'))) {
        $constantforkey = 'CASHDESK_ID_WAREHOUSE'.$_SESSION["takeposterminal"];
        $warehouseid = (GETPOSTINT('warehouseid') > 0 ? GETPOSTINT('warehouseid') : getDolGlobalInt($constantforkey));	// Get the warehouse id from GETPOSTINT('warehouseid'), otherwise use default setup.
        $sql = "UPDATE ".MAIN_DB_PREFIX."facturedet SET batch = '".$db->escape($batch)."', fk_warehouse = ".((int) $warehouseid);
        $sql .= " WHERE rowid=".((int) $idoflineadded);
        $db->query($sql);
    }

    if ($action == "order" && $placeid != 0 && ($user->hasRight('takepos', 'run') || defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE'))) {
        include_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
        if ((isModEnabled('receiptprinter') && getDolGlobalInt('TAKEPOS_PRINTER_TO_USE'.$term) > 0) || getDolGlobalString('TAKEPOS_PRINT_METHOD') == "receiptprinter" || getDolGlobalString('TAKEPOS_PRINT_METHOD') == "takeposconnector") {
            require_once DOL_DOCUMENT_ROOT.'/core/class/dolreceiptprinter.class.php';
            $printer = new dolReceiptPrinter($db);
        }

        $sql = "SELECT label FROM ".MAIN_DB_PREFIX."takepos_floor_tables where rowid=".((int) $place);
        $resql = $db->query($sql);
        $row = $db->fetch_object($resql);
        $headerorder = '<html><br><b>'.$langs->trans('Place').' '.$row->label.'<br><table width="65%"><thead><tr><th class="left">'.$langs->trans("Label").'</th><th class="right">'.$langs->trans("Qty").'</th></tr></thead><tbody>';
        $footerorder = '</tbody></table>'.dol_print_date(dol_now(), 'dayhour').'<br></html>';
        $order_receipt_printer1 = "";
        $order_receipt_printer2 = "";
        $order_receipt_printer3 = "";
        $catsprinter1 = explode(';', getDolGlobalString('TAKEPOS_PRINTED_CATEGORIES_1'));
        $catsprinter2 = explode(';', getDolGlobalString('TAKEPOS_PRINTED_CATEGORIES_2'));
        $catsprinter3 = explode(';', getDolGlobalString('TAKEPOS_PRINTED_CATEGORIES_3'));
        $linestoprint = 0;
        foreach ($invoice->lines as $line) {
            if ($line->special_code == "4") {
                continue;
            }
            $c = new Categorie($db);
            $existing = $c->containing($line->fk_product, Categorie::TYPE_PRODUCT, 'id');
            $result = array_intersect($catsprinter1, $existing);
            $count = count($result);
            if (!$line->fk_product) {
                $count++; // Print Free-text item (Unassigned printer) to Printer 1
            }
            if ($count > 0) {
                $linestoprint++;
                $sql = "UPDATE ".MAIN_DB_PREFIX."facturedet set special_code='1' where rowid=".$line->id; //Set to print on printer 1
                $db->query($sql);
                $order_receipt_printer1 .= '<tr><td class="left">';
                if ($line->fk_product) {
                    $order_receipt_printer1 .= $line->product_label;
                } else {
                    $order_receipt_printer1 .= $line->description;
                }
                $order_receipt_printer1 .= '</td><td class="right">'.$line->qty;
                if (!empty($line->array_options['options_order_notes'])) {
                    $order_receipt_printer1 .= "<br>(".$line->array_options['options_order_notes'].")";
                }
                $order_receipt_printer1 .= '</td></tr>';
            }
        }
        if (((isModEnabled('receiptprinter') && getDolGlobalInt('TAKEPOS_PRINTER_TO_USE'.$term) > 0) || getDolGlobalString('TAKEPOS_PRINT_METHOD') == "receiptprinter" || getDolGlobalString('TAKEPOS_PRINT_METHOD') == "takeposconnector") && $linestoprint > 0 && $printer !== null) {
            $invoice->fetch($placeid); //Reload object before send to printer
            $printer->orderprinter = 1;
            echo "<script>";
            echo "var orderprinter1esc='";
            $ret = $printer->sendToPrinter($invoice, getDolGlobalInt('TAKEPOS_TEMPLATE_TO_USE_FOR_ORDERS'.$_SESSION["takeposterminal"]), getDolGlobalInt('TAKEPOS_ORDER_PRINTER1_TO_USE'.$_SESSION["takeposterminal"])); // PRINT TO PRINTER 1
            echo "';</script>";
        }
        $sql = "UPDATE ".MAIN_DB_PREFIX."facturedet set special_code='4' where special_code='1' and fk_facture=".$invoice->id; // Set as printed
        $db->query($sql);
        $invoice->fetch($placeid); //Reload object after set lines as printed
        $linestoprint = 0;

        foreach ($invoice->lines as $line) {
            if ($line->special_code == "4") {
                continue;
            }
            $c = new Categorie($db);
            $existing = $c->containing($line->fk_product, Categorie::TYPE_PRODUCT, 'id');
            $result = array_intersect($catsprinter2, $existing);
            $count = count($result);
            if ($count > 0) {
                $linestoprint++;
                $sql = "UPDATE ".MAIN_DB_PREFIX."facturedet set special_code='2' where rowid=".$line->id; //Set to print on printer 2
                $db->query($sql);
                $order_receipt_printer2 .= '<tr>'.$line->product_label.'<td class="right">'.$line->qty;
                if (!empty($line->array_options['options_order_notes'])) {
                    $order_receipt_printer2 .= "<br>(".$line->array_options['options_order_notes'].")";
                }
                $order_receipt_printer2 .= '</td></tr>';
            }
        }
        if (((isModEnabled('receiptprinter') && getDolGlobalInt('TAKEPOS_PRINTER_TO_USE'.$term) > 0) || getDolGlobalString('TAKEPOS_PRINT_METHOD') == "receiptprinter" || getDolGlobalString('TAKEPOS_PRINT_METHOD') == "takeposconnector") && $linestoprint > 0) {
            $invoice->fetch($placeid); //Reload object before send to printer
            $printer->orderprinter = 2;
            echo "<script>";
            echo "var orderprinter2esc='";
            $ret = $printer->sendToPrinter($invoice, getDolGlobalInt('TAKEPOS_TEMPLATE_TO_USE_FOR_ORDERS'.$_SESSION["takeposterminal"]), getDolGlobalInt('TAKEPOS_ORDER_PRINTER2_TO_USE'.$_SESSION["takeposterminal"])); // PRINT TO PRINTER 2
            echo "';</script>";
        }
        $sql = "UPDATE ".MAIN_DB_PREFIX."facturedet set special_code='4' where special_code='2' and fk_facture=".$invoice->id; // Set as printed
        $db->query($sql);
        $invoice->fetch($placeid); //Reload object after set lines as printed
        $linestoprint = 0;

        foreach ($invoice->lines as $line) {
            if ($line->special_code == "4") {
                continue;
            }
            $c = new Categorie($db);
            $existing = $c->containing($line->fk_product, Categorie::TYPE_PRODUCT, 'id');
            $result = array_intersect($catsprinter3, $existing);
            $count = count($result);
            if ($count > 0) {
                $linestoprint++;
                $sql = "UPDATE ".MAIN_DB_PREFIX."facturedet set special_code='3' where rowid=".$line->id; //Set to print on printer 3
                $db->query($sql);
                $order_receipt_printer3 .= '<tr>'.$line->product_label.'<td class="right">'.$line->qty;
                if (!empty($line->array_options['options_order_notes'])) {
                    $order_receipt_printer3 .= "<br>(".$line->array_options['options_order_notes'].")";
                }
                $order_receipt_printer3 .= '</td></tr>';
            }
        }
        if (((isModEnabled('receiptprinter') && getDolGlobalInt('TAKEPOS_PRINTER_TO_USE'.$term) > 0) || getDolGlobalString('TAKEPOS_PRINT_METHOD') == "receiptprinter" || getDolGlobalString('TAKEPOS_PRINT_METHOD') == "takeposconnector") && $linestoprint > 0 && $printer !== null) {
            $invoice->fetch($placeid); //Reload object before send to printer
            $printer->orderprinter = 3;
            echo "<script>";
            echo "var orderprinter3esc='";
            $ret = $printer->sendToPrinter($invoice, getDolGlobalInt('TAKEPOS_TEMPLATE_TO_USE_FOR_ORDERS'.$_SESSION["takeposterminal"]), getDolGlobalInt('TAKEPOS_ORDER_PRINTER3_TO_USE'.$_SESSION["takeposterminal"])); // PRINT TO PRINTER 3
            echo "';</script>";
        }
        $sql = "UPDATE ".MAIN_DB_PREFIX."facturedet set special_code='4' where special_code='3' and fk_facture=".$invoice->id; // Set as printed
        $db->query($sql);
        $invoice->fetch($placeid); //Reload object after set lines as printed
    }

    $sectionwithinvoicelink = '';
    // FIX (multi-cashier): clear this user's session invoice slot when invoice is closed/paid
    if (in_array($action, array('valid', 'history', 'creditnote')) && $place == 0) {
        unset($_SESSION['takepos_user_invoice_' . (int)$user->id . '_' . (int)$takeposterminal]);
    }
    if (($action == "valid" || $action == "history" || $action == 'creditnote' || ($action == 'addline' && $invoice->status == $invoice::STATUS_CLOSED)) && $user->hasRight('takepos', 'run')) {
        $sectionwithinvoicelink .= '<!-- Section with invoice link -->'."\n";
        $sectionwithinvoicelink .= '<span style="font-size:120%;" class="center inline-block marginbottomonly">';
        $sectionwithinvoicelink .= $invoice->getNomUrl(1, '', 0, 0, '', 0, 0, -1, '_backoffice')." - ";
        $remaintopay = $invoice->getRemainToPay();
        if ($remaintopay > 0) {
            $sectionwithinvoicelink .= $langs->trans('RemainToPay').': <span class="amountremaintopay" style="font-size: unset">'.price($remaintopay, 1, $langs, 1, -1, -1, $conf->currency).'</span>';
        } else {
            $sectionwithinvoicelink .= $invoice->getLibStatut(2);
        }

        $sectionwithinvoicelink .= '</span><br>';
        $autoPrintFallbackJs = '';
        if (getDolGlobalInt('TAKEPOS_PRINT_INVOICE_DOC_INSTEAD_OF_RECEIPT')) {
            $sectionwithinvoicelink .= ' <a target="_blank" class="button" href="' . DOL_URL_ROOT . '/document.php?token=' . newToken() . '&modulepart=facture&file=' . $invoice->ref . '/' . $invoice->ref . '.pdf">Invoice</a>';
        } elseif (getDolGlobalString('TAKEPOS_PRINT_METHOD') == "takeposconnector") {
            if (getDolGlobalString('TAKEPOS_PRINT_SERVER') && filter_var(getDolGlobalString('TAKEPOS_PRINT_SERVER'), FILTER_VALIDATE_URL) == true) {
                $sectionwithinvoicelink .= ' <button id="buttonprint" type="button" onclick="TakeposConnector('.$placeid.')">'.$tpLabelPrintTicket.'</button>';
                $autoPrintFallbackJs = 'TakeposConnector('.$placeid.');';
            } else {
                $sectionwithinvoicelink .= ' <button id="buttonprint" type="button" onclick="TakeposPrinting('.$placeid.')">'.$tpLabelPrintTicket.'</button>';
                $autoPrintFallbackJs = 'TakeposPrinting('.$placeid.');';
            }
        } elseif ((isModEnabled('receiptprinter') && getDolGlobalInt('TAKEPOS_PRINTER_TO_USE'.$term) > 0) || getDolGlobalString('TAKEPOS_PRINT_METHOD') == "receiptprinter") {
            $sectionwithinvoicelink .= ' <button id="buttonprint" type="button" onclick="DolibarrTakeposPrinting('.$placeid.')">'.$tpLabelPrintTicket.'</button>';
            $autoPrintFallbackJs = 'DolibarrTakeposPrinting('.$placeid.');';
        } else {
            $sectionwithinvoicelink .= ' <button id="buttonprint" type="button" onclick="Print('.$placeid.')">'.$tpLabelPrintTicket.'</button>';
            $autoPrintFallbackJs = 'Print('.$placeid.');';
            if (getDolGlobalString('TAKEPOS_PRINT_WITHOUT_DETAILS')) {
                $sectionwithinvoicelink .= ' <button id="buttonprint" type="button" onclick="PrintBox('.$placeid.', \'without_details\')">'.$langs->trans('PrintWithoutDetails').'</button>';
            }
            if (getDolGlobalString('TAKEPOS_GIFT_RECEIPT')) {
                $sectionwithinvoicelink .= ' <button id="buttonprint" type="button" onclick="Print('.$placeid.', 1)">'.$langs->trans('GiftReceipt').'</button>';
            }
        }
        if (getDolGlobalString('TAKEPOS_EMAIL_TEMPLATE_INVOICE') && getDolGlobalInt('TAKEPOS_EMAIL_TEMPLATE_INVOICE') > 0) {
            $sectionwithinvoicelink .= ' <button id="buttonsend" type="button" onclick="SendTicket('.$placeid.')">'.$langs->trans('SendTicket').'</button>';
        }

        if ($remaintopay <= 0 && $action == "valid") {
            $sectionwithinvoicelink .= '<script type="text/javascript">(function(){setTimeout(function(){var btn=document.getElementById("buttonprint"); if(btn && typeof btn.click === "function"){btn.click(); return;} '.($autoPrintFallbackJs !== '' ? $autoPrintFallbackJs : '').'}, 120);})();</script>';
        }
    }
}


/*
 * View
 */

$form = new Form($db);

// llxHeader
if ((getDolGlobalString('TAKEPOS_PHONE_BASIC_LAYOUT') == 1 && $conf->browser->layout == 'phone') || defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
    $title = 'TakePOS - Dolibarr '.DOL_VERSION;
    if (getDolGlobalString('MAIN_APPLICATION_TITLE')) {
        $title = 'TakePOS - ' . getDolGlobalString('MAIN_APPLICATION_TITLE');
    }
    $head = '<meta name="apple-mobile-web-app-title" content="TakePOS"/>
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="mobile-web-app-capable" content="yes">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>';
    $arrayofcss = array(
        '/takepos/css/pos.css.php',
    );
    $arrayofjs = array('/takepos/js/jquery.colorbox-min.js');
    $disablejs = 0;
    $disablehead = 0;
    top_htmlhead($head, $title, $disablejs, $disablehead, $arrayofjs, $arrayofcss);

    print '<body>'."\n";
} else {
    top_httphead('text/html', 1);
}

?>
    <!-- invoice.php -->
    <script type="text/javascript">
        var selectedline=0;
        var selectedtext="";
        <?php if ($action == "valid") {
            echo "var place=0;";
        }?> // Set to default place after close sale
        var placeid=<?php echo($placeid > 0 ? $placeid : 0); ?>;
        $(document).ready(function() {
            var idoflineadded = <?php echo(empty($idoflineadded) ? 0 : $idoflineadded); ?>;

            $('.posinvoiceline').click(function(){
                console.log("Click done on "+this.id);
                $('.posinvoiceline').removeClass("selected");
                $(this).addClass("selected");
                if (!this.id) {
                    return;
                }
                if (selectedline == this.id) {
                    return; // If is already selected
                } else {
                    selectedline = this.id;
                }
                selectedtext=$('#'+selectedline).find("td:first").html();
                <?php
                if (defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
                    print '$("#phonediv1").load("'.DOL_URL_ROOT.'/takepos/public/auto_order.php?action=editline&token='.newToken().'&placeid="+placeid+"&selectedline="+selectedline, function() {
			});';
                }
                ?>
            });

            /* Autoselect the line */
            if (idoflineadded > 0)
            {
                console.log("Auto select "+idoflineadded);
                $('.posinvoiceline#'+idoflineadded).click();
            }
            <?php

            if ($action == "order" && !empty($order_receipt_printer1)) {
            if (filter_var(getDolGlobalString('TAKEPOS_PRINT_SERVER'), FILTER_VALIDATE_URL) == true) {
            ?>
            $.ajax({
                type: "POST",
                url: '<?php print getDolGlobalString('TAKEPOS_PRINT_SERVER'); ?>/printer/index.php',
                data: 'invoice='+orderprinter1esc
            });
            <?php
            } else {
            ?>
            $.ajax({
                type: "POST",
                url: 'http://<?php print getDolGlobalString('TAKEPOS_PRINT_SERVER'); ?>:8111/print',
                data: '<?php
                    print $headerorder.$order_receipt_printer1.$footerorder; ?>'
            });
            <?php
            }
            }

            if ($action == "order" && !empty($order_receipt_printer2)) {
            if (filter_var(getDolGlobalString('TAKEPOS_PRINT_SERVER'), FILTER_VALIDATE_URL) == true) {
            ?>
            $.ajax({
                type: "POST",
                url: '<?php print getDolGlobalString('TAKEPOS_PRINT_SERVER'); ?>/printer/index.php?printer=2',
                data: 'invoice='+orderprinter2esc
            });
            <?php
            } else {
            ?>
            $.ajax({
                type: "POST",
                url: 'http://<?php print getDolGlobalString('TAKEPOS_PRINT_SERVER'); ?>:8111/print2',
                data: '<?php
                    print $headerorder.$order_receipt_printer2.$footerorder; ?>'
            });
            <?php
            }
            }

            if ($action == "order" && !empty($order_receipt_printer3)) {
            if (filter_var(getDolGlobalString('TAKEPOS_PRINT_SERVER'), FILTER_VALIDATE_URL) == true) {
            ?>
            $.ajax({
                type: "POST",
                url: '<?php print getDolGlobalString('TAKEPOS_PRINT_SERVER'); ?>/printer/index.php?printer=3',
                data: 'invoice='+orderprinter3esc
            });
            <?php
            }
            }

            // Set focus to search field
            if ($action == "search" || $action == "valid") {
            ?>
            parent.ClearSearch(true);
            <?php
            }


            if ($action == "temp" && !empty($ticket_printer1)) {
            ?>
            $.ajax({
                type: "POST",
                url: 'http://<?php print getDolGlobalString('TAKEPOS_PRINT_SERVER'); ?>:8111/print',
                data: '<?php
                    print $header_soc.$header_ticket.$body_ticket.$ticket_printer1.$ticket_total.$footer_ticket; ?>'
            });
            <?php
            }

            if ($action == "search") {
            ?>
            $('#search').focus();
            <?php
            }

            ?>

        });

        function SendTicket(id)
        {
            console.log("Open box to select the Print/Send form");
            $.colorbox({href:"send.php?facid="+id, width:"70%", height:"30%", transition:"none", iframe:"true", title:'<?php echo dol_escape_js($langs->trans("SendTicket")); ?>'});
            return true;
        }

        function PrintBox(id, action) {
            console.log("Open box before printing");
            $.colorbox({href:"printbox.php?facid="+id+"&action="+action+"&token=<?php echo newToken(); ?>", width:"80%", height:"200px", transition:"none", iframe:"true", title:"<?php echo $langs->trans("PrintWithoutDetails"); ?>"});
            return true;
        }

        function Print(id, gift){
            console.log("Call Print() to generate the receipt.");
            if (typeof gift === "undefined" || gift === null || gift === "") {
                gift = 0;
            }
            var receiptUrl = "receipt.php?facid=" + id + "&gift=" + gift;
            <?php
            $billingCountry = getDolGlobalString('TAKEPOS_BILLING_COUNTRY', '');
            $isFullInvoice  = ($billingCountry !== '' && function_exists('takeposUseJordanFullInvoice') && takeposUseJordanFullInvoice($conf));
            $showPreview    = getDolGlobalString('TAKEPOS_PRINT_PREVIEW');
            ?>
            <?php if ($showPreview) { ?>
            // TAKEPOS_PRINT_PREVIEW = ON → Colorbox preview regardless of invoice type
            <?php if ($isFullInvoice) { ?>
            $.colorbox({href: receiptUrl, width:"80%", height:"95%", transition:"none", iframe:"true", title:'<?php echo dol_escape_js($tpLabelPrintTicket); ?>'});
            <?php } else { ?>
            $.colorbox({href: receiptUrl, width:"40%", height:"90%", transition:"none", iframe:"true", title:'<?php echo dol_escape_js($tpLabelPrintTicket); ?>'});
            <?php } ?>
            <?php } else { ?>
            // TAKEPOS_PRINT_PREVIEW = OFF → popup auto-prints, no cashier interaction
            <?php if ($isFullInvoice) { ?>
            // Full A4 invoice needs a larger window
            var pw = window.open(receiptUrl, "takepos_receipt_" + id,
                "width=900,height=850,toolbar=0,menubar=0,location=0,status=0,scrollbars=1,resizable=1");
            <?php } else { ?>
            var pw = window.open(receiptUrl, "takepos_receipt_" + id,
                "width=520,height=750,toolbar=0,menubar=0,location=0,status=0,scrollbars=1,resizable=1");
            <?php } ?>
            if (pw) {
                pw.addEventListener('afterprint', function() { setTimeout(function(){ try{ pw.close(); }catch(e){} }, 300); });
                setTimeout(function() { try { if (pw && !pw.closed) pw.close(); } catch(e){} }, 30000);
            }
            <?php } ?>
            return true;
        }

        function TakeposPrinting(id){
            var receipt;
            console.log("TakeposPrinting" + id);
            $.get("receipt.php?facid="+id, function(data, status) {
                receipt=data.replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '');
                $.ajax({
                    type: "POST",
                    url: 'http://<?php print getDolGlobalString('TAKEPOS_PRINT_SERVER'); ?>:8111/print',
                    data: receipt
                });
            });
            return true;
        }

        function TakeposConnector(id){
            console.log("TakeposConnector" + id);
            $.get("<?php echo DOL_URL_ROOT; ?>/takepos/ajax/ajax.php?action=printinvoiceticket&token=<?php echo newToken(); ?>&term=<?php echo urlencode(isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : ''); ?>&id="+id+"&token=<?php echo currentToken(); ?>", function(data, status) {
                $.ajax({
                    type: "POST",
                    url: '<?php print getDolGlobalString('TAKEPOS_PRINT_SERVER'); ?>/printer/index.php',
                    data: 'invoice='+data
                });
            });
            return true;
        }

        // Call the ajax to execute the print.
        // With some external module another method may be called.
        function DolibarrTakeposPrinting(id) {
            console.log("DolibarrTakeposPrinting Printing invoice ticket " + id);
            $.ajax({
                type: "GET",
                data: { token: '<?php echo currentToken(); ?>' },
                url: "<?php print DOL_URL_ROOT.'/takepos/ajax/ajax.php?action=printinvoiceticket&token='.newToken().'&term='.urlencode(isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : '').'&id='; ?>" + id,

            });
            return true;
        }

        <?php if (!empty($takeposRememberLastPaidInvoiceId)) { ?>
        try {
            if (typeof window.takeposRememberLastTicketId === 'function') {
                window.takeposRememberLastTicketId(<?php echo (int) $takeposRememberLastPaidInvoiceId; ?>);
            }
            if (window.parent && window.parent !== window && typeof window.parent.takeposRememberLastTicketId === 'function') {
                window.parent.takeposRememberLastTicketId(<?php echo (int) $takeposRememberLastPaidInvoiceId; ?>);
            }
        } catch (e) {
        }
        <?php } ?>

        // Call url to generate a credit note (with same lines) from existing invoice
        function CreditNote() {
            $("#poslines").load("<?php print DOL_URL_ROOT; ?>/takepos/invoice.php?action=creditnote&token=<?php echo newToken() ?>&invoiceid="+placeid, function() {	});
            return true;
        }

        // Call url to add notes
        function SetNote() {
            $("#poslines").load("<?php print DOL_URL_ROOT; ?>/takepos/invoice.php?action=addnote&token=<?php echo newToken() ?>&invoiceid="+placeid+"&idline="+selectedline, { "addnote": $("#textinput").val() });
            return true;
        }


        $( document ).ready(function() {
            console.log("Set customer info and sales in header placeid=<?php echo $placeid; ?> status=<?php echo $invoice->statut; ?>");

            <?php
            $s = $langs->trans("Customer");
            if ($invoice->id > 0 && (int) $invoice->socid > 0 && ((int) $invoice->socid != (int) $defaultThirdPartyId)) {
                print '$("#idcustomer").val("'.((int) $invoice->socid).'");';
                $s = $soc->name;
                if (getDolGlobalInt('TAKEPOS_CHOOSE_CONTACT')) {
                    $contactids = $invoice->getIdContact('external', 'BILLING');
                    $contactid = $contactids[0];
                    if ($contactid > 0) {
                        $contact = new Contact($db);
                        $contact->fetch($contactid);
                        $s .= " - " . $contact->getFullName($langs);
                    }
                }
            } else {
                print '$("#idcustomer").val("");';
            }
            ?>

            $("#customerandsales").html('');
            $("#shoppingcart").html('');

            <?php if (getDolGlobalInt('TAKEPOS_CHOOSE_CONTACT') == 0) { ?>
            $("#customerandsales").append('<a class="valignmiddle tdoverflowmax100 minwidth100" id="customer" onclick="Customer();" title="<?php print dol_escape_js(dol_escape_htmltag((string) $s)); ?>"><span class="fas fa-building paddingrightonly"></span><?php print dol_escape_js((string) $s); ?></a>');
            <?php } else { ?>
            $("#customerandsales").append('<a class="valignmiddle tdoverflowmax300 minwidth100" id="contact" onclick="Contact();" title="<?php print dol_escape_js(dol_escape_htmltag((string) $s)); ?>"><span class="fas fa-building paddingrightonly"></span><?php print dol_escape_js((string) $s); ?></a>');
            <?php } ?>

            <?php
            $sql = "SELECT rowid, datec, ref FROM ".MAIN_DB_PREFIX."facture";
            $sql .= " WHERE entity IN (".getEntity('invoice').")";
            if (!getDolGlobalString('TAKEPOS_CAN_EDIT_IF_ALREADY_VALIDATED')) {
                // By default, only invoices with a ref not already defined can in list of open invoice we can edit.
                $sql .= " AND ref LIKE '(PROV-POS".$db->escape(isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : '')."-0%'";
            } else {
                // If TAKEPOS_CAN_EDIT_IF_ALREADY_VALIDATED set, we show also draft invoice that already has a reference defined
                $sql .= " AND pos_source = '".$db->escape((string) $_SESSION["takeposterminal"])."'";
                $sql .= " AND module_source = 'takepos'";
            }

            if (empty($user->admin)) {
                $sql .= " AND fk_user_author = ".((int) $user->id);
            }

            $sql .= $db->order('datec', 'ASC');
            $resql = $db->query($sql);
            if ($resql) {
                $max_sale = 0;
                while ($obj = $db->fetch_object($resql)) {
                    echo '$("#shoppingcart").append(\'';
                    echo '<a class="valignmiddle" title="'.dol_escape_js($langs->trans("SaleStartedAt", dol_print_date($db->jdate($obj->datec), '%H:%M', 'tzuser')).' - '.$obj->ref).'" onclick="place=\\\'';
                    $num_sale = str_replace(")", "", str_replace("(PROV-POS".$_SESSION["takeposterminal"]."-", "", $obj->ref));
                    echo $num_sale;
                    if (str_replace("-", "", $num_sale) > $max_sale) {
                        $max_sale = str_replace("-", "", $num_sale);
                    }
                    echo '\\\'; invoiceid=\\\'';
                    echo $obj->rowid;
                    echo '\\\'; Refresh();">';
                    if ($placeid == $obj->rowid) {
                        echo '<span class="basketselected">';
                    } else {
                        echo '<span class="basketnotselected">';
                    }
                    echo '<span class="fa fa-shopping-cart paddingright"></span>'.dol_print_date($db->jdate($obj->datec), '%H:%M', 'tzuser');
                    echo '</span>';
                    echo '</a>\');';
                }
                echo '$("#shoppingcart").append(\'<a onclick="place=\\\'0-';
                echo $max_sale + 1;
                echo '\\\'; invoiceid=0; Refresh();"><div><span class="fa fa-plus" title="'.dol_escape_htmltag($langs->trans("StartAParallelSale")).'"><span class="fa fa-shopping-cart"></span></div></a>\');';
            } else {
                dol_print_error($db);
            }

            $s = '';

            $idwarehouse = 0;
            $constantforkey = 'CASHDESK_NO_DECREASE_STOCK'. (isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : '');
            if (isModEnabled('stock')) {
                if (getDolGlobalString($constantforkey) != "1") {
                    $constantforkey = 'CASHDESK_ID_WAREHOUSE'. (isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : '');
                    $idwarehouse = getDolGlobalInt($constantforkey);
                    if ($idwarehouse > 0) {
                        $s = '<span class="small">';
                        $warehouse = new Entrepot($db);
                        $warehouse->fetch($idwarehouse);
                        $s .= '<span class="hideonsmartphone">'.$langs->trans("Warehouse").'<br></span>'.$warehouse->ref;
                        if ($warehouse->statut == Entrepot::STATUS_CLOSED) {
                            $s .= ' ('.$langs->trans("Closed").')';
                        }
                        $s .= '</span>';
                        print "$('#infowarehouse').html('".dol_escape_js($s)."');";
                        print '$("#infowarehouse").css("display", "inline-block");';
                    } else {
                        // CASHDESK_ID_WAREHOUSE = 0 means "all warehouses" mode.
                        // Show a clear label instead of the misleading "No warehouse defined".
                        $s = '<span class="small">';
                        $s .= '<span class="hideonsmartphone">'.$langs->trans("Warehouse").'<br></span>';
                        $s .= takeposTranslateWithFallback($langs, 'TakeposAllWarehouses', 'جميع المستودعات', 'All warehouses');
                        $s .= '</span>';
                        print "$('#infowarehouse').html('".dol_escape_js($s)."');";
                        print '$("#infowarehouse").css("display", "inline-block");';
                    }
                } else {
                    $s = '<span class="small hideonsmartphone">'.$langs->trans("StockChangeDisabled").'</span>';
                    print "$('#infowarehouse').html('".dol_escape_js($s)."');";
                    if (!empty($conf->dol_optimize_smallscreen)) {
                        print '$("#infowarehouse").css("display", "none");';
                    }
                }
            }


            // Module Adherent
            $s = '';
            if (isModEnabled('member') && $invoice->socid > 0 && $invoice->socid != $defaultThirdPartyId) {
                $s = '<span class="small">';
                require_once DOL_DOCUMENT_ROOT.'/adherents/class/adherent.class.php';
                $langs->load("members");
                $s .= $langs->trans("Member").': ';
                $adh = new Adherent($db);
                $result = $adh->fetch(0, '', $invoice->socid);
                if ($result > 0) {
                    $adh->ref = $adh->getFullName($langs);
                    if (empty($adh->statut) || $adh->statut == Adherent::STATUS_EXCLUDED) {
                        $s .= "<s>";
                    }
                    $s .= $adh->getFullName($langs);
                    $s .= ' - '.$adh->type;
                    if ($adh->datefin) {
                        $s .= '<br>'.$langs->trans("SubscriptionEndDate").': '.dol_print_date($adh->datefin, 'day');
                        if ($adh->hasDelay()) {
                            $s .= " ".img_warning($langs->trans("Late"));
                        }
                    } else {
                        $s .= '<br>'.$langs->trans("SubscriptionNotReceived");
                        if ($adh->statut > 0) {
                            $s .= " ".img_warning($langs->trans("Late")); // displays delay Pictogram only if not a draft and not terminated
                        }
                    }
                    if (empty($adh->statut) || $adh->statut == Adherent::STATUS_EXCLUDED) {
                        $s .= "</s>";
                    }
                } else {
                    $s .= '<br>'.$langs->trans("ThirdpartyNotLinkedToMember");
                }
                $s .= '</span>';
            }
            ?>
            $("#moreinfo").html('<?php print dol_escape_js($s); ?>');
            if (typeof refreshTakeposCustomerPanel === 'function') {
                refreshTakeposCustomerPanel();
            }

        });


        <?php
        if (getDolGlobalString('TAKEPOS_CUSTOMER_DISPLAY')) {
            echo "function CustomerDisplay(){";
            echo "var line1='".$CUSTOMER_DISPLAY_line1."'.substring(0,20);";
            echo "line1=line1.padEnd(20);";
            echo "var line2='".$CUSTOMER_DISPLAY_line2."'.substring(0,20);";
            echo "line2=line2.padEnd(20);";
            echo "$.ajax({
		type: 'GET',
		data: { text: line1+line2 },
		url: '".getDolGlobalString('TAKEPOS_PRINT_SERVER')."/display/index.php',
	});";
            echo "}";
        }
        ?>

    </script>

    <!-- V2 redesign: helper functions used by the new cart layout -->
    <script type="text/javascript">
        /**
         * Stepper +/- callback. Used by the V2 cart line qty buttons.
         * Calls the existing updateqty endpoint of invoice.php (the same one Edit('qty')
         * uses), reusing the parent page's loadPosLines so all the existing post-update
         * logic (focus, customer display refresh) keeps running.
         *
         * Defined here so it's available regardless of phone-layout flag.
         */
        window.takeposV2SetQty = function(lineId, newQty, direction) {
            var qty = parseFloat(newQty);
            if (!isFinite(qty)) qty = 1;
            if (qty < 0) qty = 0;
            var url = "invoice.php?action=updateqty"
                + "&token=<?php echo newToken(); ?>"
                + "&place=" + encodeURIComponent(typeof place !== 'undefined' ? place : '<?php echo (int) $place; ?>')
                + "&idline=" + encodeURIComponent(lineId)
                + "&number=" + encodeURIComponent(qty);
            if (typeof loadPosLines === 'function') {
                loadPosLines(url);
            } else {
                jQuery('#poslines').load(url);
            }
        };

        /**
         * Convenience wrapper: triggers click on an existing action button on the
         * sidebar so we don't duplicate JS logic. Used by the V2 footer's Pay/Hold/
         * Cancel buttons. If the action button isn't there for any reason we fall
         * back to a direct call.
         */
        window.takeposV2TriggerAction = function(actionId, fallbackFn) {
            var btn = document.getElementById(actionId)
                || document.querySelector('[data-takepos-action-id="' + actionId + '"]');
            if (btn && typeof btn.click === 'function') {
                btn.click();
                return;
            }
            if (typeof fallbackFn === 'function') {
                try { fallbackFn(); } catch (e) { console.warn('takeposV2 fallback failed', e); }
            }
        };
    </script>

<?php
// Add again js for footer because this content is injected into index.php page so all init
// for tooltip and other js beautifiers must be reexecuted too.
if (!empty($conf->use_javascript_ajax)) {
    print "\n".'<!-- Includes JS Footer of Dolibarr -->'."\n";
    print '<script src="'.DOL_URL_ROOT.'/core/js/lib_foot.js.php?lang='.$langs->defaultlang.'"></script>'."\n";
}

$usediv = (GETPOST('format') == 'div');

print '<!-- invoice.php place='.(int) $place.' invoice='.$invoice->ref.' usediv='.json_encode($usediv).', mobilepage='.(empty($mobilepage) ? '' : $mobilepage).' $_SESSION["basiclayout"]='.(empty($_SESSION["basiclayout"]) ? '' : $_SESSION["basiclayout"]).' conf TAKEPOS_BAR_RESTAURANT='.getDolGlobalString('TAKEPOS_BAR_RESTAURANT').' -->'."\n";
print '<div class="div-table-responsive-no-min invoice">';
if ($usediv) {
    print '<div id="tablelines">';
} else {
    print '<table id="tablelines" class="noborder noshadow postablelines centpercent">';
}

// =========================================================================
// V2 redesign — Cart header + Customer card
// Inserted as full-width rows (colspan="99") so the existing column-count
// logic below isn't affected. Hidden on mobile basiclayout because that
// layout has its own header.
// =========================================================================
if (!$usediv && (empty($_SESSION["basiclayout"]) || $_SESSION["basiclayout"] != 1)) {
    $v2InvoiceRef = !empty($invoice->ref) ? trim((string) $invoice->ref) : '';
    $v2InvoiceRefDisplay = ($v2InvoiceRef !== '' && $v2InvoiceRef !== '(PROV)' && stripos($v2InvoiceRef, 'PROV') === false)
        ? $v2InvoiceRef
        : ($invoice->id > 0 ? '#'.((int) $invoice->id) : '');
    $v2CustomerName = '';
    $v2CustomerInitial = '';
    if (!empty($soc) && is_object($soc)) {
        $v2CustomerName = trim((string) (isset($soc->name) ? $soc->name : ''));
    }
    if ($v2CustomerName === '' && !empty($invoice->thirdparty) && is_object($invoice->thirdparty)) {
        $v2CustomerName = trim((string) (isset($invoice->thirdparty->name) ? $invoice->thirdparty->name : ''));
    }
    if ($v2CustomerName !== '') {
        $v2CustomerInitial = mb_strtoupper(mb_substr($v2CustomerName, 0, 1, 'UTF-8'), 'UTF-8');
    } else {
        $v2CustomerInitial = '?';
    }
    $v2CartTitleLabel = $langs->trans('TakeposV2CartTitle');
    if ($v2CartTitleLabel === 'TakeposV2CartTitle' || $v2CartTitleLabel === '') {
        $v2CartTitleLabel = (in_array(substr((string) $langs->defaultlang, 0, 2), array('ar')) ? 'سلة المبيعات' : 'Sales Cart');
    }
    $v2CustomerLabel = $langs->trans('TakeposV2CustomerLabel');
    if ($v2CustomerLabel === 'TakeposV2CustomerLabel' || $v2CustomerLabel === '') {
        $v2CustomerLabel = (in_array(substr((string) $langs->defaultlang, 0, 2), array('ar')) ? 'العميل' : 'Customer');
    }
    $v2NoCustomerLabel = $langs->trans('TakeposV2NoCustomer');
    if ($v2NoCustomerLabel === 'TakeposV2NoCustomer' || $v2NoCustomerLabel === '') {
        $v2NoCustomerLabel = (in_array(substr((string) $langs->defaultlang, 0, 2), array('ar')) ? 'عميل عام' : 'Walk-in customer');
    }
    $v2HoldHint = $langs->trans('TakeposV2HoldHint');
    if ($v2HoldHint === 'TakeposV2HoldHint' || $v2HoldHint === '') {
        $v2HoldHint = (in_array(substr((string) $langs->defaultlang, 0, 2), array('ar')) ? 'تعليق' : 'Hold');
    }
    $v2NoteHint = $langs->trans('TakeposV2NoteHint');
    if ($v2NoteHint === 'TakeposV2NoteHint' || $v2NoteHint === '') {
        $v2NoteHint = (in_array(substr((string) $langs->defaultlang, 0, 2), array('ar')) ? 'ملاحظة' : 'Note');
    }
    $v2DiscountHint = $langs->trans('TakeposV2DiscountHint');
    if ($v2DiscountHint === 'TakeposV2DiscountHint' || $v2DiscountHint === '') {
        $v2DiscountHint = (in_array(substr((string) $langs->defaultlang, 0, 2), array('ar')) ? 'خصم' : 'Discount');
    }

    print '<tr class="tpv2-cart-header-row nodrag nodrop"><td colspan="99" class="tpv2-cart-header-cell">';
    print '<div class="tpv2-cart-header">';
    print '<div class="tpv2-cart-title">'.dol_escape_htmltag($v2CartTitleLabel).'</div>';
    if ($v2InvoiceRefDisplay !== '') {
        print '<div class="tpv2-invoice-badge">'.dol_escape_htmltag($v2InvoiceRefDisplay).'</div>';
    }
    print '<div class="tpv2-cart-header-actions">';
    print '<button type="button" class="tpv2-icon-btn" onclick="takeposV2TriggerAction(\'takepos-action-hold\', window.HoldSale);" title="'.dol_escape_htmltag($v2HoldHint).'" aria-label="'.dol_escape_htmltag($v2HoldHint).'"><span class="fa fa-pause"></span></button>';
    print '<button type="button" class="tpv2-icon-btn" onclick="takeposV2TriggerAction(\'takepos-action-reduction\', window.Reduction);" title="'.dol_escape_htmltag($v2DiscountHint).'" aria-label="'.dol_escape_htmltag($v2DiscountHint).'"><span class="fa fa-percent"></span></button>';
    print '<button type="button" class="tpv2-icon-btn" onclick="if (typeof TakeposOrderNotes === \'function\') { TakeposOrderNotes(); }" title="'.dol_escape_htmltag($v2NoteHint).'" aria-label="'.dol_escape_htmltag($v2NoteHint).'"><span class="fa fa-sticky-note"></span></button>';
    print '</div>';
    print '</div>';
    print '</td></tr>';

    print '<tr class="tpv2-customer-row nodrag nodrop"><td colspan="99" class="tpv2-customer-cell">';
    print '<div class="tpv2-customer" onclick="if (typeof Customer === \'function\') { Customer(); }">';
    print '<div class="tpv2-customer-avatar">'.dol_escape_htmltag($v2CustomerInitial).'</div>';
    print '<div class="tpv2-customer-text">';
    print '<div class="tpv2-customer-label">'.dol_escape_htmltag($v2CustomerLabel).'</div>';
    print '<div class="tpv2-customer-name">'.dol_escape_htmltag($v2CustomerName !== '' ? $v2CustomerName : $v2NoCustomerLabel).'</div>';
    print '</div>';
    print '</div>';
    print '</td></tr>';
}
// =========================================================================
// End V2 cart header / customer card
// =========================================================================

$buttontocreatecreditnote = '';
if (($action == "valid" || $action == "history" ||  ($action == "addline" && $invoice->status == $invoice::STATUS_CLOSED)) && $invoice->type != Facture::TYPE_CREDIT_NOTE && !getDolGlobalString('TAKEPOS_NO_CREDITNOTE')) {
    $buttontocreatecreditnote .= ' &nbsp; <!-- Show button to create a credit note -->'."\n";
    $buttontocreatecreditnote .= '<button id="buttonprint" type="button" onclick="ModalBox(\'ModalCreditNote\')">'.$langs->trans('CreateCreditNote').'</button>';
    if (getDolGlobalInt('TAKEPOS_PRINT_INVOICE_DOC_INSTEAD_OF_RECEIPT')) {
        $buttontocreatecreditnote .= ' <a target="_blank" class="button" href="' . DOL_URL_ROOT . '/document.php?token=' . newToken() . '&modulepart=facture&file=' . urlencode($invoice->ref . '/' . $invoice->ref . '.pdf').'">'.$langs->trans("Invoice").'</a>';
    }
}

// Show the ref of invoice
if ($sectionwithinvoicelink && ($mobilepage == "invoice" || $mobilepage == "")) {
    print '<!-- Print table line with link to invoice ref -->';
    if (getDolGlobalString('TAKEPOS_SHOW_HT')) {
        print '<tr><td colspan="5" class="paddingtopimp paddingbottomimp" style="padding-top: 10px !important; padding-bottom: 10px !important;">';
        print $sectionwithinvoicelink;
        print $buttontocreatecreditnote;
        print '</td></tr>';
    } else {
        print '<tr><td colspan="4" class="paddingtopimp paddingbottomimp" style="padding-top: 10px !important; padding-bottom: 10px !important;">';
        print $sectionwithinvoicelink;
        print $buttontocreatecreditnote;
        print '</td></tr>';
    }
}

// Show the list of selected product
if (!$usediv) {
    print '<tr class="liste_titre nodrag nodrop">';
    print '<td class="linecoldescription">';
}
// In phone version only show when it is invoice page
if (empty($mobilepage) || $mobilepage == "invoice") {
    print '<!-- hidden var used by some js functions -->';
    print '<input type="hidden" name="invoiceid" id="invoiceid" value="'.$invoice->id.'">';
    print '<input type="hidden" name="thirdpartyid" id="thirdpartyid" value="'.$invoice->socid.'">';
}
if (!$usediv) {
    if (getDolGlobalString('TAKEPOS_BAR_RESTAURANT')) {
        $sql = "SELECT floor, label FROM ".MAIN_DB_PREFIX."takepos_floor_tables where rowid=".((int) $place);
        $resql = $db->query($sql);
        $obj = $db->fetch_object($resql);
        if ($obj) {
            $label = $obj->label;
            $floor = $obj->floor;
        }
        if ($mobilepage == "invoice" || $mobilepage == "") {
            // If not on smartphone version or if it is the invoice page
            //print 'mobilepage='.$mobilepage;
            print '<span class="opacitymedium">'.$langs->trans('Place')."</span> <b>".(empty($label) ? '?' : $label)."</b><br>";
            print '<span class="opacitymedium">'.$langs->trans('Floor')."</span> <b>".(empty($floor) ? '?' : $floor)."</b>";
        }
    }
    print '</td>';
}

// Complete header by hook
$parameters = array();
$reshook = $hookmanager->executeHooks('completeTakePosInvoiceHeader', $parameters, $invoice, $action);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}
print $hookmanager->resPrint;

if (empty($_SESSION["basiclayout"]) || $_SESSION["basiclayout"] != 1) {
    if (getDolGlobalInt("TAKEPOS_SHOW_SUBPRICE")) {
        print '<td class="linecolqty right">'.$langs->trans('PriceUHT').'</td>';
    }
    print '<td class="linecolqty right">'.$langs->trans('ReductionShort').'</td>';
    print '<td class="linecolqty right">'.$langs->trans('Qty').'</td>';
    if (getDolGlobalString('TAKEPOS_SHOW_HT')) {
        print '<td class="linecolht right nowraponall">';
        print '<span class="opacitymedium small">' . $langs->trans('TotalHTShort') . '</span><br>';
        // In phone version only show when it is invoice page
        if (empty($mobilepage) || $mobilepage == "invoice") {
            print '<span id="linecolht-span-total" style="font-size:1.3em; font-weight: bold;">' . price($invoice->total_ht, 1, '', 1, -1, -1, $conf->currency) . '</span>';
            if ($takeposDisplayCurrencyCode !== '' && $takeposDisplayCurrencyRate > 0) {
                print '<br><span id="linecolht-span-total" style="font-size:0.9em; font-style:italic;">(' . price($invoice->total_ht * $takeposDisplayCurrencyRate) . ' ' . $takeposDisplayCurrencyCode . ')</span>';
            }
        }
        print '</td>';
    }
    print '<td class="linecolht right nowraponall">';
    print '<span class="opacitymedium small">'.$langs->trans('TotalTTCShort').'</span><br>';
    // In phone version only show when it is invoice page
    if (empty($mobilepage) || $mobilepage == "invoice") {
        print '<span id="linecolht-span-total" style="font-size:1.3em; font-weight: bold;">'.price($invoice->total_ttc, 1, '', 1, -1, -1, $conf->currency).'</span>';
        if ($takeposDisplayCurrencyCode !== '' && $takeposDisplayCurrencyRate > 0) {
            print '<br><span id="linecolht-span-total" style="font-size:0.9em; font-style:italic;">('.price($invoice->total_ttc * $takeposDisplayCurrencyRate).' '.$takeposDisplayCurrencyCode.')</span>';
        }
    }
    print '</td>';
} elseif ($mobilepage == "invoice") {
    print '<td class="linecolqty right">'.$langs->trans('Qty').'</td>';
}
if (!$usediv) {
    print "</tr>\n";
}

if (!empty($_SESSION["basiclayout"]) && $_SESSION["basiclayout"] == 1) {
    if ($mobilepage == "cats") {
        require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
        $categorie = new Categorie($db);
        $categories = $categorie->get_full_arbo('product');
        $htmlforlines = '';
        foreach ($categories as $row) {
            if (defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
                $htmlforlines .= '<div class="leftcat"';
            } else {
                $htmlforlines .= '<tr class="drag drop oddeven posinvoiceline"';
            }
            $htmlforlines .= ' onclick="LoadProducts('.$row['id'].');">';
            if (defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
                $htmlforlines .= '<img class="imgwrapper" width="33%" src="'.DOL_URL_ROOT.'/takepos/public/auto_order.php?genimg=cat&query=cat&id='.$row['id'].'"><br>';
            } else {
                $htmlforlines .= '<td class="left">';
            }
            $htmlforlines .= $row['label'];
            if (defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
                $htmlforlines .= '</div>'."\n";
            } else {
                $htmlforlines .= '</td></tr>'."\n";
            }
        }
        print $htmlforlines;
    }

    if ($mobilepage == "products") {
        require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
        $object = new Categorie($db);
        $catid = GETPOSTINT('catid');
        $result = $object->fetch($catid);
        $prods = $object->getObjectsInCateg("product");
        $htmlforlines = '';
        foreach ($prods as $row) {
            if (defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
                $htmlforlines .= '<div class="leftcat"';
            } else {
                $htmlforlines .= '<tr class="drag drop oddeven posinvoiceline"';
            }
            $htmlforlines .= ' onclick="AddProduct(\''.$place.'\', '.$row->id.')"';
            $htmlforlines .= '>';
            if (defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
                $htmlforlines .= '<img class="imgwrapper" width="33%" src="'.DOL_URL_ROOT.'/takepos/public/auto_order.php?genimg=pro&query=pro&id='.$row->id.'"><br>';
                $htmlforlines .= $row->label.' '.price($row->price_ttc, 1, $langs, 1, -1, -1, $conf->currency);
                $htmlforlines .= '</div>'."\n";
            } else {
                $htmlforlines .= '<td class="left">';
                $htmlforlines .= $row->label;
                $htmlforlines .= '<div class="right">'.price($row->price_ttc, 1, $langs, 1, -1, -1, $conf->currency).'</div>';
                $htmlforlines .= '</td>';
                $htmlforlines .= '</tr>'."\n";
            }
        }
        print $htmlforlines;
    }

    if ($mobilepage == "places") {
        $sql = "SELECT rowid, entity, label, leftpos, toppos, floor FROM ".MAIN_DB_PREFIX."takepos_floor_tables";
        $resql = $db->query($sql);

        $rows = array();
        $htmlforlines = '';
        while ($row = $db->fetch_array($resql)) {
            $rows[] = $row;
            $htmlforlines .= '<tr class="drag drop oddeven posinvoiceline';
            $htmlforlines .= '" onclick="LoadPlace(\''.$row['label'].'\')">';
            $htmlforlines .= '<td class="left">';
            $htmlforlines .= $row['label'];
            $htmlforlines .= '</td>';
            $htmlforlines .= '</tr>'."\n";
        }
        print $htmlforlines;
    }
}

if ($placeid > 0) {
    //In Phone basic layout hide some content depends situation
    if (!empty($_SESSION["basiclayout"]) && $_SESSION["basiclayout"] == 1 && $mobilepage != "invoice" && $action != "order") {
        return;
    }

    // Loop on each lines on invoice
    if (is_array($invoice->lines) && count($invoice->lines)) {
        print '<!-- invoice.php show lines of invoices -->'."\n";
        $tmplines = array_reverse($invoice->lines);
        $htmlsupplements = array();
        foreach ($tmplines as $line) {
            if ($line->fk_parent_line != false) {
                $htmlsupplements[$line->fk_parent_line] .= '<tr class="drag drop oddeven posinvoiceline';
                if ($line->special_code == "4") {
                    $htmlsupplements[$line->fk_parent_line] .= ' order';
                }
                $htmlsupplements[$line->fk_parent_line] .= '" id="'.$line->id.'"';
                if ($line->special_code == "4") {
                    $htmlsupplements[$line->fk_parent_line] .= ' title="'.dol_escape_htmltag($langs->trans("AlreadyPrinted")).'"';
                }
                $htmlsupplements[$line->fk_parent_line] .= '>';
                $htmlsupplements[$line->fk_parent_line] .= '<td class="left">';
                $htmlsupplements[$line->fk_parent_line] .= img_picto('', 'rightarrow');
                if ($line->product_label) {
                    $htmlsupplements[$line->fk_parent_line] .= $line->product_label;
                }
                if ($line->product_label && $line->desc) {
                    $htmlsupplements[$line->fk_parent_line] .= '<br>';
                }
                if ($line->product_label != $line->desc) {
                    $firstline = dolGetFirstLineOfText($line->desc);
                    if ($firstline != $line->desc) {
                        $htmlsupplements[$line->fk_parent_line] .= $form->textwithpicto(dolGetFirstLineOfText($line->desc), $line->desc);
                    } else {
                        $htmlsupplements[$line->fk_parent_line] .= $line->desc;
                    }
                }
                $htmlsupplements[$line->fk_parent_line] .= '</td>';

                // complete line by hook
                $parameters = array('line' => $line);
                $reshook = $hookmanager->executeHooks('completeTakePosInvoiceParentLine', $parameters, $invoice, $action);    // Note that $action and $object may have been modified by some hooks
                if ($reshook < 0) {
                    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
                }
                $htmlsupplements[$line->fk_parent_line] .= $hookmanager->resPrint;

                if (empty($_SESSION["basiclayout"]) || $_SESSION["basiclayout"] != 1) {
                    $htmlsupplements[$line->fk_parent_line] .= '<td class="right">'.vatrate(price2num($line->remise_percent), true).'</td>';
                    $htmlsupplements[$line->fk_parent_line] .= '<td class="right">'.$line->qty.'</td>';
                    $htmlsupplements[$line->fk_parent_line] .= '<td class="right">'.price($line->total_ttc).'</td>';
                }
                $htmlsupplements[$line->fk_parent_line] .= '</tr>'."\n";
                continue;
            }
            $htmlforlines = '';

            $htmlforlines .= '<tr class="drag drop oddeven posinvoiceline';
            if ($line->special_code == "4") {
                $htmlforlines .= ' order';
            }
            $htmlforlines .= '" id="'.$line->id.'"';
            // data attributes used by JS stock check before updateqty
            $htmlforlines .= ' data-fk-product="'.((int) $line->fk_product).'"';
            $htmlforlines .= ' data-qty="'.((float) $line->qty).'"';
            if ($line->special_code == "4") {
                $htmlforlines .= ' title="'.dol_escape_htmltag($langs->trans("AlreadyPrinted")).'"';
            }
            $htmlforlines .= '>';
            $htmlforlines .= '<td class="left">';
            if (!empty($_SESSION["basiclayout"]) && $_SESSION["basiclayout"] == 1) {
                $htmlforlines .= '<span class="phoneqty">'.$line->qty."</span> x ";
            }
            if (isset($line->product_type)) {
                if (empty($line->product_type)) {
                    $htmlforlines .= img_object('', 'product').' ';
                } else {
                    $htmlforlines .= img_object('', 'service').' ';
                }
            }
            $tooltiptext = '';
            if (!getDolGlobalString('TAKEPOS_SHOW_N_FIRST_LINES')) {
                if ($line->product_ref) {
                    $tooltiptext .= '<b>'.$langs->trans("Ref").'</b> : '.$line->product_ref.'<br>';
                    $tooltiptext .= '<b>'.$langs->trans("Label").'</b> : '.$line->product_label.'<br>';
                    if (!empty($line->batch)) {
                        $tooltiptext .= '<br><b>'.$langs->trans("LotSerial").'</b> : '.$line->batch.'<br>';
                    }
                    if (!empty($line->fk_warehouse)) {
                        $tooltiptext .= '<b>'.$langs->trans("Warehouse").'</b> : '.$line->fk_warehouse.'<br>';
                    }
                    if ($line->product_label != $line->desc) {
                        if ($line->desc) {
                            $tooltiptext .= '<br>';
                        }
                        $tooltiptext .= $line->desc;
                    }
                }
                if (getDolGlobalInt('TAKEPOS_SHOW_PRODUCT_REFERENCE') == 1) {
                    $htmlforlines .= $form->textwithpicto($line->product_label ? '<b>' . $line->product_ref . '</b> - ' . $line->product_label : dolGetFirstLineOfText($line->desc, 1), $tooltiptext);
                } elseif (getDolGlobalInt('TAKEPOS_SHOW_PRODUCT_REFERENCE') == 2) {
                    if ($line->product_label) {
                        $displayLabel = ($line->product_ref ? '<b>'.$line->product_ref.'</b> - ' : '').$line->product_label;
                    } else {
                        $displayLabel = ($line->product_ref ? '<b>'.$line->product_ref.'</b>' : dolGetFirstLineOfText($line->desc, 1));
                    }
                    $htmlforlines .= $form->textwithpicto($displayLabel, $tooltiptext);
                } else {
                    $htmlforlines .= $form->textwithpicto($line->product_label ? $line->product_label : ($line->product_ref ? $line->product_ref : dolGetFirstLineOfText($line->desc, 1)), $tooltiptext);
                }
            } else {
                if ($line->product_ref) {
                    $tooltiptext .= '<b>'.$langs->trans("Ref").'</b> : '.$line->product_ref.'<br>';
                    $tooltiptext .= '<b>'.$langs->trans("Label").'</b> : '.$line->product_label.'<br>';
                }
                if (!empty($line->batch)) {
                    $tooltiptext .= '<br><b>'.$langs->trans("LotSerial").'</b> : '.$line->batch.'<br>';
                }
                if (!empty($line->fk_warehouse)) {
                    $tooltiptext .= '<b>'.$langs->trans("Warehouse").'</b> : '.$line->fk_warehouse.'<br>';
                }

                if ($line->product_label) {
                    $htmlforlines .= $line->product_label;
                }
                if ($line->product_label != $line->desc) {
                    if ($line->product_label && $line->desc) {
                        $htmlforlines .= '<br>';
                    }
                    $firstline = dolGetFirstLineOfText($line->desc, getDolGlobalInt('TAKEPOS_SHOW_N_FIRST_LINES'));
                    if ($firstline != $line->desc) {
                        $htmlforlines .= $form->textwithpicto(dolGetFirstLineOfText($line->desc), $line->desc);
                    } else {
                        $htmlforlines .= $line->desc;
                    }
                }
            }
            if (!empty($line->array_options['options_order_notes'])) {
                $htmlforlines .= "<br>(".$line->array_options['options_order_notes'].")";
            }
            if (!empty($_SESSION["basiclayout"]) && $_SESSION["basiclayout"] == 1) {
                $htmlforlines .= '</td><td class="right phonetable"><button type="button" onclick="SetQty(place, '.$line->rowid.', '.($line->qty - 1).');" class="publicphonebutton2 phonered">-</button>&nbsp;&nbsp;<button type="button" onclick="SetQty(place, '.$line->rowid.', '.($line->qty + 1).');" class="publicphonebutton2 phonegreen">+</button>';
            }
            if (empty($_SESSION["basiclayout"]) || $_SESSION["basiclayout"] != 1) {
                $moreinfo = '';
                $moreinfo .= $langs->transcountry("TotalHT", $mysoc->country_code).': '.price($line->total_ht);
                if ($line->vat_src_code) {
                    $moreinfo .= '<br>'.$langs->trans("VATCode").': '.$line->vat_src_code;
                }
                $moreinfo .= '<br>'.$langs->transcountry("TotalVAT", $mysoc->country_code).': '.price($line->total_tva);
                $moreinfo .= '<br>'.$langs->transcountry("TotalLT1", $mysoc->country_code).': '.price($line->total_localtax1);
                $moreinfo .= '<br>'.$langs->transcountry("TotalLT2", $mysoc->country_code).': '.price($line->total_localtax2);
                $moreinfo .= '<hr>';
                $moreinfo .= $langs->transcountry("TotalTTC", $mysoc->country_code).': '.price($line->total_ttc);
                //$moreinfo .= $langs->trans("TotalHT").': '.$line->total_ht;
                if ($line->date_start || $line->date_end) {
                    $htmlforlines .= '<br><div class="clearboth nowraponall">'.get_date_range($line->date_start, $line->date_end).'</div>';
                }
                $htmlforlines .= '</td>';

                // complete line by hook
                $parameters = array('line' => $line);
                $reshook = $hookmanager->executeHooks('completeTakePosInvoiceLine', $parameters, $invoice, $action);    // Note that $action and $object may have been modified by some hooks
                if ($reshook < 0) {
                    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
                }
                $htmlforlines .= $hookmanager->resPrint;

                if (getDolGlobalInt("TAKEPOS_SHOW_SUBPRICE")) {
                    $htmlforlines .= '<td class="right">'.price($line->subprice).'</td>';
                }
                $htmlforlines .= '<td class="right">'.vatrate(price2num($line->remise_percent), true).'</td>';
                $htmlforlines .= '<td class="right tpv2-qty-cell">';
                // V2 redesign: render qty as a [-] qty [+] stepper. Clicks
                // stop propagation so the parent <tr> doesn't get "selected".
                $v2LineQty = (float) $line->qty;
                $v2LineQtyMinus = $v2LineQty - 1;
                $v2LineQtyPlus = $v2LineQty + 1;
                $v2LineQtyDisplay = (abs($v2LineQty - round($v2LineQty)) < 0.0001) ? (string) (int) $v2LineQty : (string) $v2LineQty;
                $htmlforlines .= '<span class="tpv2-qty-stepper" onclick="event.stopPropagation();">';
                $htmlforlines .= '<button type="button" class="tpv2-qty-btn tpv2-qty-minus" onclick="event.stopPropagation(); takeposV2SetQty('.((int) $line->id).', '.((float) $v2LineQtyMinus).', \'minus\');" aria-label="-">−</button>';
                $htmlforlines .= '<span class="tpv2-qty-value">'.dol_escape_htmltag($v2LineQtyDisplay).'</span>';
                $htmlforlines .= '<button type="button" class="tpv2-qty-btn tpv2-qty-plus" onclick="event.stopPropagation(); takeposV2SetQty('.((int) $line->id).', '.((float) $v2LineQtyPlus).', \'plus\');" aria-label="+">+</button>';
                $htmlforlines .= '</span>';
                // Keep the legacy plain-text qty hidden in the same cell so any JS
                // that reads .text() still gets the number. (No known caller does
                // this in current code, but it's a safety net.)
                $htmlforlines .= '<span class="tpv2-qty-legacy" style="display:none">'.dol_escape_htmltag($v2LineQtyDisplay).'</span>';
                if (isModEnabled('stock') && $user->hasRight('stock', 'mouvement', 'lire')) {
                    $constantforkey = 'CASHDESK_ID_WAREHOUSE'.$_SESSION["takeposterminal"];
                    if (getDolGlobalString($constantforkey) && $line->fk_product > 0 && !getDolGlobalString('TAKEPOS_HIDE_STOCK_ON_LINE')) {
                        $productChildrenNb = 0;
                        if (getDolGlobalInt('PRODUIT_SOUSPRODUITS')) {
                            if (empty($line->product) || !($line->product->id > 0)) {
                                $line->fetch_product();
                            }
                            if (!empty($line->product)) {
                                $productChildrenNb = $line->product->hasFatherOrChild(1);
                            }
                        }
                        if ($productChildrenNb == 0) {
                            // Step 1: try the terminal's default warehouse first.
                            $defaultWarehouseId = (int) getDolGlobalString($constantforkey);
                            $sql = "SELECT ps.reel";
                            $sql .= " FROM ".MAIN_DB_PREFIX."product_stock as ps";
                            $sql .= " INNER JOIN ".MAIN_DB_PREFIX."entrepot as e ON e.rowid = ps.fk_entrepot";
                            $sql .= " WHERE ps.fk_entrepot = ".$defaultWarehouseId;
                            $sql .= " AND e.entity IN (".getEntity('stock').")";
                            $sql .= " AND ps.fk_product = ".((int) $line->fk_product);
                            $resql = $db->query($sql);
                            $stock_real = 0;
                            $stock_warehouse_label = ''; // tooltip suffix for non-default warehouse
                            if ($resql) {
                                $obj = $db->fetch_object($resql);
                                if ($obj) {
                                    $stock_real = price2num($obj->reel, 'MS');
                                }
                            }

                            // Step 2: if the default warehouse has no stock for this product,
                            // fall back to the sum across ALL warehouses so the cart indicator
                            // is never misleadingly zero for products stored elsewhere.
                            if ($stock_real == 0) {
                                $sqlAll = "SELECT SUM(ps.reel) as total_reel";
                                $sqlAll .= " FROM ".MAIN_DB_PREFIX."product_stock as ps";
                                $sqlAll .= " INNER JOIN ".MAIN_DB_PREFIX."entrepot as e ON e.rowid = ps.fk_entrepot";
                                $sqlAll .= " WHERE ps.fk_product = ".((int) $line->fk_product);
                                $sqlAll .= " AND e.entity IN (".getEntity('stock').")";
                                $sqlAll .= " AND e.statut = 1"; // active warehouses only
                                $resqlAll = $db->query($sqlAll);
                                if ($resqlAll) {
                                    $objAll = $db->fetch_object($resqlAll);
                                    if ($objAll && $objAll->total_reel !== null) {
                                        $stock_real = price2num($objAll->total_reel, 'MS');
                                        if ($stock_real != 0) {
                                            // Mark that this total comes from all warehouses
                                            $stock_warehouse_label = ' ('.$langs->trans("AllWarehouses").')';
                                        }
                                    }
                                }
                            }

                            $htmlforlines .= '&nbsp; ';
                            $htmlforlines .= '<span class="opacitylow" title="'.$langs->trans("Stock").' '.price($stock_real, 1, '', 1, 0).$stock_warehouse_label.'">';
                            $htmlforlines .= '(';
                            if ($line->qty && $line->qty > $stock_real) {
                                $htmlforlines .= '<span style="color: var(--amountremaintopaycolor)">';
                            }
                            $htmlforlines .= img_picto('', 'stock', 'class="pictofixedwidth"').price($stock_real, 1, '', 1, 0);
                            if ($line->qty && $line->qty > $stock_real) {
                                $htmlforlines .= "</span>";
                            }
                            $htmlforlines .= ')';
                            $htmlforlines .= '</span>';

                            if (!$resql) {
                                dol_print_error($db);
                            }
                        }
                    }
                }

                $htmlforlines .= '</td>';
                if (getDolGlobalInt('TAKEPOS_SHOW_HT')) {
                    $htmlforlines .= '<td class="right classfortooltip" title="'.$moreinfo.'">';
                    $htmlforlines .= price($line->total_ht, 1, '', 1, -1, -1, $conf->currency);
                    if ($takeposDisplayCurrencyCode !== '' && $takeposDisplayCurrencyRate > 0) {
                        $htmlforlines .= '<br><span id="linecolht-span-total" style="font-size:0.9em; font-style:italic;">('.price($line->total_ht * $takeposDisplayCurrencyRate).' '.$takeposDisplayCurrencyCode.')</span>';
                    }
                    $htmlforlines .= '</td>';
                }
                $htmlforlines .= '<td class="right classfortooltip" title="'.$moreinfo.'">';
                $htmlforlines .= price($line->total_ttc, 1, '', 1, -1, -1, $conf->currency);
                if ($takeposDisplayCurrencyCode !== '' && $takeposDisplayCurrencyRate > 0) {
                    $htmlforlines .= '<br><span id="linecolht-span-total" style="font-size:0.9em; font-style:italic;">('.price($line->total_ttc * $takeposDisplayCurrencyRate).' '.$takeposDisplayCurrencyCode.')</span>';
                }
                $htmlforlines .= '</td>';
            }
            $htmlforlines .= '</tr>'."\n";
            $htmlforlines .= empty($htmlsupplements[$line->id]) ? '' : $htmlsupplements[$line->id];

            print $htmlforlines;
        }
    } else {
        print '<tr class="drag drop oddeven"><td class="left"><span class="opacitymedium">'.$langs->trans("Empty").'</span></td><td></td>';
        if (empty($_SESSION["basiclayout"]) || $_SESSION["basiclayout"] != 1) {
            print '<td></td><td></td>';
            if (getDolGlobalString('TAKEPOS_SHOW_HT')) {
                print '<td></td>';
            }
        }
        print '</tr>';
    }
} else {      // No invoice generated yet
    print '<tr class="drag drop oddeven"><td class="left"><span class="opacitymedium">'.$langs->trans("Empty").'</span></td><td></td>';
    if (empty($_SESSION["basiclayout"]) || $_SESSION["basiclayout"] != 1) {
        print '<td></td><td></td>';
        if (getDolGlobalString('TAKEPOS_SHOW_HT')) {
            print '<td></td>';
        }
    }
    print '</tr>';
}

if ($usediv) {
    print '</div>';
} else {
    print '</table>';
}

// =========================================================================
// V2 redesign — Cart summary + Pay/Hold/Cancel action buttons
// Sits OUTSIDE the table, inside the .invoice wrapper. Mirrors the side-rail
// action buttons by triggering them via takeposV2TriggerAction so all the
// existing logic (shift gates, stock checks, manager overrides) keeps running.
// Hidden when no invoice exists yet, on basiclayout, and during 'search' or
// 'history' modes (the cart footer doesn't make sense there).
// =========================================================================
if (!$usediv
    && (empty($_SESSION["basiclayout"]) || $_SESSION["basiclayout"] != 1)
    && !empty($invoice->id)
    && $invoice->id > 0
    && !in_array((string) $action, array('search', 'history', 'temp'), true)) {

    // Localised labels with sensible Arabic / English fallbacks
    $v2LabelSubtotal = $langs->trans('TakeposV2Subtotal');
    if ($v2LabelSubtotal === 'TakeposV2Subtotal' || $v2LabelSubtotal === '') {
        $v2LabelSubtotal = (in_array(substr((string) $langs->defaultlang, 0, 2), array('ar')) ? 'المجموع الفرعي' : 'Subtotal');
    }
    $v2LabelDiscount = $langs->trans('TakeposV2Discount');
    if ($v2LabelDiscount === 'TakeposV2Discount' || $v2LabelDiscount === '') {
        $v2LabelDiscount = (in_array(substr((string) $langs->defaultlang, 0, 2), array('ar')) ? 'الخصم' : 'Discount');
    }
    $v2LabelTax = $langs->trans('TakeposV2Tax');
    if ($v2LabelTax === 'TakeposV2Tax' || $v2LabelTax === '') {
        $v2LabelTax = (in_array(substr((string) $langs->defaultlang, 0, 2), array('ar')) ? 'الضريبة' : 'Tax');
    }
    $v2LabelGrandTotal = $langs->trans('TakeposV2GrandTotal');
    if ($v2LabelGrandTotal === 'TakeposV2GrandTotal' || $v2LabelGrandTotal === '') {
        $v2LabelGrandTotal = (in_array(substr((string) $langs->defaultlang, 0, 2), array('ar')) ? 'الإجمالي المطلوب' : 'Total Due');
    }
    $v2LabelPay = $langs->trans('TakeposV2Pay');
    if ($v2LabelPay === 'TakeposV2Pay' || $v2LabelPay === '') {
        $v2LabelPay = (in_array(substr((string) $langs->defaultlang, 0, 2), array('ar')) ? 'إتمام الدفع' : 'Pay');
    }
    $v2LabelHold = $langs->trans('TakeposV2Hold');
    if ($v2LabelHold === 'TakeposV2Hold' || $v2LabelHold === '') {
        $v2LabelHold = (in_array(substr((string) $langs->defaultlang, 0, 2), array('ar')) ? 'تعليق' : 'Hold');
    }
    $v2LabelCancel = $langs->trans('TakeposV2Cancel');
    if ($v2LabelCancel === 'TakeposV2Cancel' || $v2LabelCancel === '') {
        $v2LabelCancel = (in_array(substr((string) $langs->defaultlang, 0, 2), array('ar')) ? 'إلغاء' : 'Cancel');
    }
    $v2LabelItems = $langs->trans('TakeposV2Items');
    if ($v2LabelItems === 'TakeposV2Items' || $v2LabelItems === '') {
        $v2LabelItems = (in_array(substr((string) $langs->defaultlang, 0, 2), array('ar')) ? 'منتجات' : 'items');
    }

    $v2LinesCount = 0;
    if (is_array($invoice->lines)) {
        foreach ($invoice->lines as $vline) {
            if (empty($vline->fk_parent_line)) {
                $v2LinesCount++;
            }
        }
    }
    $v2TotalHt = (float) (isset($invoice->total_ht) ? $invoice->total_ht : 0);
    $v2TotalTtc = (float) (isset($invoice->total_ttc) ? $invoice->total_ttc : 0);
    $v2TotalTva = (float) (isset($invoice->total_tva) ? $invoice->total_tva : 0);
    // Best-effort line-discount sum (subprice*qty - total_ht).
    $v2DiscountAmount = 0.0;
    if (is_array($invoice->lines)) {
        foreach ($invoice->lines as $vline) {
            $grossHt = (float) (isset($vline->subprice) ? $vline->subprice : 0) * (float) (isset($vline->qty) ? $vline->qty : 0);
            $netHt = (float) (isset($vline->total_ht) ? $vline->total_ht : 0);
            $diff = $grossHt - $netHt;
            if ($diff > 0) {
                $v2DiscountAmount += $diff;
            }
        }
    }
    // Round to 2 decimals to avoid floating-point garbage (-0.0000001)
    $v2DiscountAmount = round($v2DiscountAmount, 2);

    $v2CurrencyCode = !empty($conf->currency) ? $conf->currency : '';

    print '<div class="tpv2-cart-footer">';

    // Summary panel
    print '<div class="tpv2-summary">';
    print '<div class="tpv2-summary-row">';
    print '<span class="tpv2-summary-label">'.dol_escape_htmltag($v2LabelSubtotal.' ('.$v2LinesCount.' '.$v2LabelItems.')').'</span>';
    print '<span class="tpv2-summary-value">'.price($v2TotalHt, 1, '', 1, -1, -1, $v2CurrencyCode).'</span>';
    print '</div>';
    if ($v2DiscountAmount > 0.001) {
        print '<div class="tpv2-summary-row tpv2-summary-discount">';
        print '<span class="tpv2-summary-label">'.dol_escape_htmltag($v2LabelDiscount).'</span>';
        print '<span class="tpv2-summary-value">-'.price($v2DiscountAmount, 1, '', 1, -1, -1, $v2CurrencyCode).'</span>';
        print '</div>';
    }
    print '<div class="tpv2-summary-row">';
    print '<span class="tpv2-summary-label">'.dol_escape_htmltag($v2LabelTax).'</span>';
    print '<span class="tpv2-summary-value">'.price($v2TotalTva, 1, '', 1, -1, -1, $v2CurrencyCode).'</span>';
    print '</div>';
    print '</div>';

    // Dark grand-total bar
    print '<div class="tpv2-grand-total">';
    print '<span class="tpv2-grand-total-label">'.dol_escape_htmltag($v2LabelGrandTotal).'</span>';
    print '<span class="tpv2-grand-total-value">'.price($v2TotalTtc, 1, '', 1, -1, -1, $v2CurrencyCode).'</span>';
    print '</div>';

    // Three primary actions: Cancel / Hold / Pay
    // Each one delegates to the existing side-rail button if present so all
    // the wired-up shift/stock/manager-override checks fire normally.
    print '<div class="tpv2-cart-actions">';
    print '<button type="button" class="tpv2-btn tpv2-btn-cancel" onclick="takeposV2TriggerAction(\'takepos-action-delete\', function(){ if (typeof New === \'function\') New(); });">';
    print '<span class="tpv2-btn-key">F12</span>';
    print '<span class="tpv2-btn-label"><span class="fa fa-trash-alt"></span> '.dol_escape_htmltag($v2LabelCancel).'</span>';
    print '</button>';
    print '<button type="button" class="tpv2-btn tpv2-btn-hold" onclick="takeposV2TriggerAction(\'takepos-action-hold\', window.HoldSale);">';
    print '<span class="tpv2-btn-key">F7</span>';
    print '<span class="tpv2-btn-label"><span class="fa fa-pause"></span> '.dol_escape_htmltag($v2LabelHold).'</span>';
    print '</button>';
    print '<button type="button" class="tpv2-btn tpv2-btn-pay" onclick="takeposV2TriggerAction(\'takepos-action-direct-payment\', function(){ if (typeof DirectPayment === \'function\') DirectPayment(); });">';
    print '<span class="tpv2-btn-key">F11</span>';
    print '<span class="tpv2-btn-label"><span class="fa fa-credit-card"></span> '.dol_escape_htmltag($v2LabelPay).' '.price($v2TotalTtc, 1, '', 1, -1, -1, $v2CurrencyCode).'</span>';
    print '</button>';
    print '</div>';

    print '</div>'; // tpv2-cart-footer
}
// =========================================================================
// End V2 cart footer
// =========================================================================

if ($action == "search") {
    print '<center>
	<input type="text" id="search" class="input-nobottom" name="search" onkeyup="Search2(\'\', null);" style="width: 80%; font-size: 150%;" placeholder="'.dol_escape_htmltag($langs->trans('Search')).'">
	</center>';
}

print '</div>';

// llxFooter
if ((getDolGlobalString('TAKEPOS_PHONE_BASIC_LAYOUT') == 1 && $conf->browser->layout == 'phone') || defined('INCLUDE_PHONEPAGE_FROM_PUBLIC_PAGE')) {
    print '</body></html>';
}