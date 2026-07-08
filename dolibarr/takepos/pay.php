<?php
/* Copyright (C) 2018		Andreu Bisquerra	<jove@bisquerra.com>
 * Copyright (C) 2021-2022	Thibault FOUCART	<support@ptibogxiv.net>
 * Copyright (C) 2024       Frederic France             <frederic.france@free.fr>
 * Copyright (C) 2024-2025	MDW							<mdeweerd@users.noreply.github.com>
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
 *	\file       htdocs/takepos/pay.php
 *	\ingroup	takepos
 *	\brief      Page with the content of the popup to enter payments
 */

// if (! defined('NOREQUIREUSER'))		define('NOREQUIREUSER', '1');		// Not disabled cause need to load personalized language
// if (! defined('NOREQUIREDB'))		define('NOREQUIREDB', '1');			// Not disabled cause need to load personalized language
// if (! defined('NOREQUIRESOC'))		define('NOREQUIRESOC', '1');
// if (! defined('NOREQUIRETRAN'))		define('NOREQUIRETRAN', '1');

if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}

// Load Dolibarr environment
require '../main.inc.php';
require_once __DIR__ . '/lib/takepos_help.php';
require_once __DIR__ . '/lib/takepos_currency.php';
require_once __DIR__ . '/lib/takepos_loader.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/lib/takepos_access_guard.php';
takeposAccessGuardCurrent($db, isset($user) ? $user : null, __FILE__);
// Load $user and permissions
takeposRequireModuleFile('class/TakeposAccess.class.php');
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/stripe/class/stripe.class.php';


/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

// Load translation files required by the page
$langs->loadLangs(array("main", "bills", "cashdesk", "banks", "takeposcustom@takepos"));

$place = (GETPOST('place', 'aZ09') ? GETPOST('place', 'aZ09') : '0'); // $place is id of table for Bar or Restaurant

$invoiceid = GETPOSTINT('invoiceid');
$preferredpay = GETPOST('preferredpay', 'aZ09');
$takeposPaymentTestMode = (getDolGlobalInt('TAKEPOS_PAYMENT_TEST_MODE') == 1 || GETPOSTINT('testmode') == 1);

$hookmanager->initHooks(array('takepospay'));

if (!$user->hasRight('takepos', 'run')) {
    accessforbidden();
}

TakeposAccess::enforceFrontend($db, isset($user) ? $user : null, 'takepos.payment', $_SESSION["takeposterminal"]);


/*
 * View
 */

$arrayofcss = array('/takepos/css/pos.css.php');
$arrayofjs = array();

$head = '';
$title = '';
$disablejs = 0;
$disablehead = 0;

$head = '<link rel="stylesheet" href="css/pos.css.php">';
if (getDolGlobalInt('TAKEPOS_COLOR_THEME') == 1) {
    $head .= '<link rel="stylesheet" href="css/colorful.css">';
}

top_htmlhead($head, $title, $disablejs, $disablehead, $arrayofjs, $arrayofcss);

?>
<body>
<?php
$takeposPaymentModeTitle = $takeposPaymentTestMode ? $langs->trans('TakeposPaymentModeTestTitle') : $langs->trans('TakeposPaymentModeLiveTitle');
$takeposPaymentModeDesc = $takeposPaymentTestMode ? $langs->trans('TakeposPaymentModeTestDesc') : $langs->trans('TakeposPaymentModeLiveDesc');
$takeposPaymentModeClass = $takeposPaymentTestMode ? 'takepos-payment-mode-test' : 'takepos-payment-mode-live';
?>
<div class="takepos-payment-mode <?php echo dol_escape_htmltag($takeposPaymentModeClass); ?>">
    <strong><?php echo dol_escape_htmltag($takeposPaymentModeTitle); ?></strong>
    <span><?php echo dol_escape_htmltag($takeposPaymentModeDesc); ?></span>
</div>
<style>
    .takepos-payment-mode{margin:10px 12px;padding:10px 14px;border-radius:12px;font-size:14px;display:flex;gap:10px;align-items:center;justify-content:center;flex-wrap:wrap;border:1px solid rgba(15,23,42,.18)}
    .takepos-payment-mode-live{background:#fee2e2;color:#7f1d1d;border-color:#fca5a5}
    .takepos-payment-mode-test{background:#dbeafe;color:#1e3a8a;border-color:#93c5fd}
</style>
<?php

$usestripeterminals = 0;
$keyforstripeterminalbank = '';
$stripe = null;
$servicestatus = 0;
$stripeacc = null;

if (isModEnabled('stripe')) {
    $service = 'StripeTest';

    if (getDolGlobalString('STRIPE_LIVE')/* && !GETPOST('forcesandbox', 'alpha') */) {
        $service = 'StripeLive';
        $servicestatus = 1;
    }

    // Force to use the correct API key
    global $stripearrayofkeysbyenv;
    $site_account = $stripearrayofkeysbyenv[$servicestatus]['publishable_key'];

    $stripe = new Stripe($db);
    $stripeacc = $stripe->getStripeAccount($service); // Get Stripe OAuth connect account (no remote access to Stripe here)

    include_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
    $invoicetmp = new Facture($db);
    $invoicetmp->fetch($invoiceid);
    $stripecu = $stripe->getStripeCustomerAccount($invoicetmp->socid, $servicestatus, $site_account); // Get remote Stripe customer 'cus_...' (no remote access to Stripe here)
    $keyforstripeterminalbank = takeposResolveTerminalStringConstant("CASHDESK_ID_BANKACCOUNT_STRIPETERMINAL", empty($_SESSION['takeposterminal']) ? '' : $_SESSION['takeposterminal']);

    $usestripeterminals = getDolGlobalString('STRIPE_LOCATION');

    if ($usestripeterminals) {
        ?>
        <script src="https://js.stripe.com/terminal/v1/"></script>
        <script>
            var terminal = StripeTerminal.create({
                onFetchConnectionToken: fetchConnectionToken,
                onUnexpectedReaderDisconnect: unexpectedDisconnect,
            });

            function unexpectedDisconnect() {
                // In this function, your app should notify the user that the reader disconnected.
                // You can also include a way to attempt to reconnect to a reader.
                console.log("Disconnected from reader")
            }

            function fetchConnectionToken() {
                <?php
                $urlconnexiontoken = DOL_URL_ROOT.'/stripe/ajax/ajax.php?action=getConnexionToken&token='.newToken().'&servicestatus='.urlencode((string) ($servicestatus));
                if (getDolGlobalString('STRIPE_LOCATION')) {
                    $urlconnexiontoken .= '&location='.urlencode(getDolGlobalString('STRIPE_LOCATION'));
                }
                if (!empty($stripeacc)) {
                    $urlconnexiontoken .= '&stripeacc='.urlencode($stripeacc);
                } ?>
                // Do not cache or hardcode the ConnectionToken. The SDK manages the ConnectionToken's lifecycle.
                return fetch('<?php echo $urlconnexiontoken; ?>', { method: "POST" })
                    .then(function(response) {
                        return response.json();
                    })
                    .then(function(data) {
                        return data.secret;
                    });
            }

        </script>
        <?php
    }
}

if (isModEnabled('stripe') && isset($keyforstripeterminalbank) && (!getDolGlobalString('STRIPE_LIVE')/* || GETPOST('forcesandbox', 'alpha') */)) {
    dol_htmloutput_mesg($langs->trans('YouAreCurrentlyInSandboxMode', 'Stripe'), [], 'warning', 1);
}

$invoice = new Facture($db);
if ($invoiceid > 0) {
    $invoice->fetch($invoiceid);
} else {
    $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."facture";
    $sql .= " WHERE entity IN (".getEntity('invoice').")";
    $sql .= " AND ref = '(PROV-POS".$_SESSION["takeposterminal"]."-".$place.")'";
    $resql = $db->query($sql);
    $obj = $db->fetch_object($resql);
    if ($obj) {
        $invoiceid = $obj->rowid;
    }
    if (!$invoiceid) {
        $invoiceid = 0; // Invoice does not exist yet
    } else {
        $invoice->fetch($invoiceid);
    }
}

?>
<script>
    var takeposPaymentTestMode = <?php echo $takeposPaymentTestMode ? 'true' : 'false'; ?>;
    var takeposPaymentTestBlockedMessage = <?php echo json_encode($langs->trans('TakeposPaymentTestBlocked'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    <?php
    if ($usestripeterminals && $invoice->type != $invoice::TYPE_CREDIT_NOTE) {
    if ($keyforstripeterminalbank === '' || $stripeacc === null) { ?>
    const config = {
        simulated: <?php if (empty($servicestatus) && getDolGlobalString('STRIPE_TERMINAL_SIMULATED')) { ?> true <?php } else { ?> false <?php } ?>
        <?php if (getDolGlobalString('STRIPE_LOCATION')) { ?>, location: '<?php echo dol_escape_js(getDolGlobalString('STRIPE_LOCATION')); ?>'<?php } ?>
    }
    terminal.discoverReaders(config).then(function(discoverResult) {
        if (discoverResult.error) {
            console.log('Failed to discover: ', discoverResult.error);
        } else if (discoverResult.discoveredReaders.length === 0) {
            console.log('No available readers.');
        } else {
            // You should show the list of discoveredReaders to the
            // cashier here and let them select which to connect to (see below).
            selectedReader = discoverResult.discoveredReaders[0];
            //console.log('terminal.discoverReaders', selectedReader); // only active for development

            terminal.connectReader(selectedReader).then(function(connectResult) {
                if (connectResult.error) {
                    document.getElementById("card-present-alert").innerHTML = '<div class="error">'+connectResult.error.message+'</div>';
                    console.log('Failed to connect: ', connectResult.error);
                } else {
                    document.getElementById("card-present-alert").innerHTML = '';
                    console.log('Connected to reader: ', connectResult.reader.label);
                    if (document.getElementById("StripeTerminal")) {
                        document.getElementById("StripeTerminal").innerHTML = '<button type="button" class="calcbutton2" onclick="ValidateStripeTerminal();"><span class="fa fa-2x fa-credit-card iconwithlabel"></span><br>'+connectResult.reader.label+'</button>';
                    }
                }
            });
        }
    });
    <?php } else { ?>
    terminal.connectReader(<?php echo json_encode($stripe->getSelectedReader($keyforstripeterminalbank, $stripeacc, $servicestatus)); ?>).then(function(connectResult) {
        if (connectResult.error) {
            document.getElementById("card-present-alert").innerHTML = '<div class="error clearboth">'+connectResult.error.message+'</div>';
            console.log('Failed to connect: ', connectResult.error);
        } else {
            document.getElementById("card-present-alert").innerHTML = '';
            console.log('Connected to reader: ', connectResult.reader.label);
            if (document.getElementById("StripeTerminal")) {
                document.getElementById("StripeTerminal").innerHTML = '<button type="button" class="calcbutton2" onclick="ValidateStripeTerminal();"><span class="fa fa-2x fa-credit-card iconwithlabel"></span><br>'+connectResult.reader.label+'</button>';
            }
        }
    });

    <?php }
    } ?>
</script>
<?php

// Define list of possible payments
$arrayOfValidPaymentModes = array();
$arrayOfValidBankAccount = array();

$sql = "SELECT code, libelle as label FROM ".MAIN_DB_PREFIX."c_paiement";
$sql .= " WHERE entity IN (".getEntity('c_paiement').")";
$sql .= " AND active = 1";
$sql .= " ORDER BY CASE code WHEN 'LIQ' THEN 1 WHEN 'CB' THEN 2 WHEN 'CHQ' THEN 3 ELSE 99 END, libelle";
$resql = $db->query($sql);

if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $paycode = $obj->code;
        if ($paycode == 'LIQ') {
            $paycode = 'CASH';
        }
        if ($paycode == 'CB') {
            $paycode = 'CB';
        }
        if ($paycode == 'CHQ') {
            $paycode = 'CHEQUE';
        }

        $bankAccountId = takeposResolveTerminalBankAccountId($paycode, $_SESSION["takeposterminal"]);
        if ($bankAccountId > 0) {
            $arrayOfValidBankAccount[$bankAccountId] = $bankAccountId;
            $arrayOfValidPaymentModes[] = $obj;
        }
        if (!isModEnabled('bank')) {
            if ($paycode == 'CASH' || $paycode == 'CB') {
                $arrayOfValidPaymentModes[] = $obj;
            }
        }
    }
}

// Keep the payment buttons deterministic. If a direct-payment flow provided
// preferredpay, put that method first without losing the default LIQ/CB/CHQ order.
$preferredpay = strtoupper((string) $preferredpay);
if (!empty($preferredpay) && count($arrayOfValidPaymentModes) > 1) {
    $preferredPaymentModes = array();
    $otherPaymentModes = array();
    foreach ($arrayOfValidPaymentModes as $paymentMode) {
        if (strtoupper((string) $paymentMode->code) === $preferredpay) {
            $preferredPaymentModes[] = $paymentMode;
        } else {
            $otherPaymentModes[] = $paymentMode;
        }
    }
    if (!empty($preferredPaymentModes)) {
        $arrayOfValidPaymentModes = array_merge($preferredPaymentModes, $otherPaymentModes);
    }
}
$takeposPrimaryPaymentCode = (count($arrayOfValidPaymentModes) > 0 ? (string) $arrayOfValidPaymentModes[0]->code : '');
$takeposBaseCurrency = takeposNormalizeCurrencyCode($conf->currency);
if ($takeposBaseCurrency === '') {
    $takeposBaseCurrency = 'JOD';
}
$takeposDisplayCurrencyCode = takeposResolveDocumentCurrencyCode($conf, $invoice);
$takeposDisplayCurrencyRate = takeposResolveDocumentCurrencyRate($db, $conf, $invoice);
$takeposSelectedCurrencyCode = ($takeposDisplayCurrencyCode !== '' ? $takeposDisplayCurrencyCode : $takeposBaseCurrency);
$takeposSelectedCurrencyRate = ($takeposSelectedCurrencyCode !== $takeposBaseCurrency ? $takeposDisplayCurrencyRate : 1.0);
$takeposPaymentCurrencies = array();
$takeposPaymentCurrencySeen = array();
$takeposAddPaymentCurrency = function ($currencyCode, $rate) use (&$takeposPaymentCurrencies, &$takeposPaymentCurrencySeen) {
    $currencyCode = takeposNormalizeCurrencyCode($currencyCode);
    $rate = takeposNormalizeCurrencyRate($rate);
    if ($currencyCode === '') {
        return;
    }
    if ($rate <= 0) {
        $rate = 1.0;
    }
    if (!empty($takeposPaymentCurrencySeen[$currencyCode])) {
        return;
    }
    $takeposPaymentCurrencySeen[$currencyCode] = true;
    $takeposPaymentCurrencies[] = array('code' => $currencyCode, 'rate' => (float) $rate);
};
$takeposAddPaymentCurrency($takeposBaseCurrency, 1.0);

$takeposExtraCurrencyRaw = getDolGlobalString('TAKEPOS_EXTRA_PAYMENT_CURRENCIES', 'USD');
if (getDolGlobalString('TAKEPOS_PAYMENT_CURRENCIES') !== '') {
    $takeposExtraCurrencyRaw .= ',' . getDolGlobalString('TAKEPOS_PAYMENT_CURRENCIES');
}
foreach (preg_split('/[,;|\s]+/', (string) $takeposExtraCurrencyRaw) as $currencyCodeCandidate) {
    $currencyCode = takeposNormalizeCurrencyCode($currencyCodeCandidate);
    if ($currencyCode === '' || $currencyCode === $takeposBaseCurrency) {
        continue;
    }
    $rateConstName = 'TAKEPOS_' . preg_replace('/[^A-Z0-9]/', '', $currencyCode) . '_RATE';
    $currencyRate = takeposNormalizeCurrencyRate(getDolGlobalString($rateConstName, ''));
    if ($currencyRate <= 0 && isModEnabled('multicurrency')) {
        $currencyRate = takeposFetchCurrencyRate($db, $currencyCode);
    }
    if ($currencyRate <= 0) {
        $currencyRate = 1.0;
    }
    $takeposAddPaymentCurrency($currencyCode, $currencyRate);
}
if (isModEnabled('multicurrency')) {
    $sqlCurrency = 'SELECT code FROM ' . MAIN_DB_PREFIX . 'multicurrency';
    $sqlCurrency .= " WHERE entity IN ('" . getEntity('multicurrency') . "')";
    $resqlCurrency = $db->query($sqlCurrency);
    if ($resqlCurrency) {
        while ($objCurrency = $db->fetch_object($resqlCurrency)) {
            $currencyCode = takeposNormalizeCurrencyCode($objCurrency->code);
            if ($currencyCode === '' || $currencyCode === $takeposBaseCurrency) {
                continue;
            }
            $takeposAddPaymentCurrency($currencyCode, takeposFetchCurrencyRate($db, $currencyCode));
        }
    }
}
print '<!-- conf->currency = '.$takeposBaseCurrency.' - selectedcurrency = '.$takeposSelectedCurrencyCode.' -->' . "\n";
?>

<script>
    <?php
    $remaintopay = 0;
    if ($invoice->id > 0) {
        $remaintopay = $invoice->getRemainToPay();
    }
    $alreadypayed = (is_object($invoice) ? ($invoice->total_ttc - $remaintopay) : 0);

    if (!getDolGlobalInt("TAKEPOS_NUMPAD")) {
        print "var received='';";
    } else {
        print "var received=0;";
    }

    ?>
    var alreadypayed = <?php echo $alreadypayed ?>;
    var takeposPrimaryPaymentCode = <?php echo json_encode((string) $takeposPrimaryPaymentCode); ?>;
    var takeposRemainToPay = <?php echo json_encode((float) $remaintopay); ?>;
    var takeposInvoiceTotalBase = <?php echo json_encode((float) $invoice->total_ttc); ?>;
    var takeposBaseCurrency = <?php echo json_encode($takeposBaseCurrency, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var takeposPaymentCurrencies = <?php echo json_encode($takeposPaymentCurrencies, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var takeposSelectedPaymentCurrency = <?php echo json_encode($takeposSelectedCurrencyCode, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var takeposSelectedPaymentRate = <?php echo json_encode((float) $takeposSelectedCurrencyRate); ?>;

    function normalizeReceivedValue(value)
    {
        var raw = String(typeof value === 'undefined' || value === null ? '' : value).replace(/,/g, '.').trim();
        if (raw === '') return 0;
        var numeric = parseFloat(raw);
        return isFinite(numeric) ? numeric : 0;
    }

    function getSelectedPaymentCurrency()
    {
        var el = document.getElementById('takepos-payment-currency');
        return String(el && el.value ? el.value : takeposSelectedPaymentCurrency || takeposBaseCurrency).toUpperCase();
    }

    function getSelectedPaymentRate()
    {
        var currency = getSelectedPaymentCurrency();
        if (currency === takeposBaseCurrency) return 1;
        var el = document.getElementById('takepos-payment-rate');
        var rate = normalizeReceivedValue(el && typeof el.value !== 'undefined' ? el.value : takeposSelectedPaymentRate);
        return rate > 0 ? rate : 1;
    }

    function paymentUsesForeignCurrency()
    {
        return getSelectedPaymentCurrency() !== takeposBaseCurrency;
    }

    function computeBaseAmountFromEntered(enteredAmount)
    {
        var numericAmount = normalizeReceivedValue(enteredAmount);
        if (!paymentUsesForeignCurrency()) {
            return numericAmount;
        }
        var rate = getSelectedPaymentRate();
        return rate > 0 ? (numericAmount / rate) : numericAmount;
    }

    function computeForeignAmountFromBase(baseAmount)
    {
        var numericAmount = normalizeReceivedValue(baseAmount);
        if (!paymentUsesForeignCurrency()) {
            return numericAmount;
        }
        return numericAmount * getSelectedPaymentRate();
    }

    function formatCurrencyAmount(amount, currencyCode)
    {
        return price2numjs(normalizeReceivedValue(amount)) + ' ' + currencyCode;
    }

    function formatPrimaryDisplay(baseAmount, foreignAmount)
    {
        if (!paymentUsesForeignCurrency()) {
            return pricejs(baseAmount, 'MT');
        }
        return pricejs(baseAmount, 'MT') + ' <span class="opacitymedium" style="font-size:0.9em;">(' + formatCurrencyAmount(foreignAmount, getSelectedPaymentCurrency()) + ')</span>';
    }

    function updatePaymentCurrencySummary()
    {
        var currency = getSelectedPaymentCurrency();
        var rate = getSelectedPaymentRate();
        var rateWrap = document.getElementById('takepos-payment-rate-wrap');
        var hint = document.getElementById('takepos-payment-currency-hint');
        if (rateWrap) {
            rateWrap.style.display = (currency === takeposBaseCurrency ? 'none' : 'inline-flex');
        }
        if (hint) {
            if (currency === takeposBaseCurrency) {
                hint.textContent = <?php echo json_encode($langs->trans('TakeposPaymentCurrencyBaseHint'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            } else {
                hint.textContent = <?php echo json_encode($langs->trans('TakeposPaymentRateHint'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> + ' 1 ' + takeposBaseCurrency + ' = ' + price2numjs(rate) + ' ' + currency;
            }
        }

        var totalEl = document.getElementById('totaldisplay');
        if (totalEl) {
            totalEl.innerHTML = formatPrimaryDisplay(takeposInvoiceTotalBase, computeForeignAmountFromBase(takeposInvoiceTotalBase));
        }
        var remainEl = document.getElementById('remaintopaydisplay');
        if (remainEl) {
            remainEl.innerHTML = formatPrimaryDisplay(takeposRemainToPay, computeForeignAmountFromBase(takeposRemainToPay));
        }
    }

    function updateReceivedDisplay()
    {
        var enteredAmount = normalizeReceivedValue(received);
        received = <?php echo (!getDolGlobalInt("TAKEPOS_NUMPAD") ? 'String(enteredAmount)' : 'enteredAmount'); ?>;
        var baseReceived = computeBaseAmountFromEntered(enteredAmount);
        $('.change1').html(formatPrimaryDisplay(baseReceived, enteredAmount));
        $('.change1').val(price2numjs(baseReceived));
        var alreadypaydplusreceived = price2numjs(alreadypayed + baseReceived);
        if (alreadypaydplusreceived > <?php echo (float) $invoice->total_ttc; ?>)
        {
            var change = parseFloat(alreadypayed + baseReceived - <?php echo (float) $invoice->total_ttc; ?>);
            $('.change2').html(formatPrimaryDisplay(change, computeForeignAmountFromBase(change)));
            $('.change2').val(price2numjs(change));
            $('.change1').removeClass('colorred').addClass('colorgreen');
            $('.change2').removeClass('colorwhite').addClass('colorred');
        }
        else
        {
            $('.change2').html(pricejs(0, 'MT'));
            $('.change2').val(price2numjs(0));
            if (alreadypaydplusreceived == <?php echo $invoice->total_ttc; ?>)
            {
                $('.change1').removeClass('colorred').addClass('colorgreen');
                $('.change2').removeClass('colorred').addClass('colorwhite');
            }
            else
            {
                $('.change1').removeClass('colorgreen').addClass('colorred');
                $('.change2').removeClass('colorred').addClass('colorwhite');
            }
        }
        updatePaymentCurrencySummary();
    }

    function addreceived(price)
    {
        <?php if (!getDolGlobalInt("TAKEPOS_NUMPAD")) { ?>
        if (price === '000') {
            received = String(received || '') + '000';
        } else {
            received = String(received || '') + String(price);
        }
        <?php } else { ?>
        received = normalizeReceivedValue(received) + normalizeReceivedValue(price);
        <?php } ?>
        updateReceivedDisplay();
        return true;
    }

    function reset()
    {
        <?php if (!getDolGlobalInt("TAKEPOS_NUMPAD")) { ?>
        received='';
        <?php } else { ?>
        received=0;
        <?php } ?>
        updateReceivedDisplay();
    }

    function takeposSetPaymentProcessing(processing)
    {
        window._takeposPayLock = !!processing;
        $('.takepos-payment-button').prop('disabled', !!processing).toggleClass('disabled', !!processing);
    }

    function takeposReleasePaymentLock()
    {
        takeposSetPaymentProcessing(false);
    }

    function Validate(payment)
    {
        console.log("Launch Validate");

        // UX: prevent double-click / double payment. Do not auto-release on timeout;
        // release only after validation fails or the request returns.
        if (window._takeposPayLock) {
            console.log("Payment already processing, ignoring duplicate click.");
            return false;
        }
        if (takeposPaymentTestMode) {
            alert(takeposPaymentTestBlockedMessage);
            return false;
        }
        takeposSetPaymentProcessing(true);

        var invoiceid = <?php echo($invoiceid > 0 ? $invoiceid : 0); ?>;
        var accountid = $("#selectaccountid").val();
        var amountpayed = normalizeReceivedValue($("#change1").val());
        var excess = normalizeReceivedValue($("#change2").val());
        var paymentCurrency = getSelectedPaymentCurrency();
        var paymentRate = getSelectedPaymentRate();
        var paymentForeignAmount = paymentUsesForeignCurrency() ? computeForeignAmountFromBase(amountpayed) : 0;
        var paymentForeignExcess = paymentUsesForeignCurrency() ? computeForeignAmountFromBase(excess) : 0;
        if (paymentUsesForeignCurrency() && (!paymentRate || paymentRate <= 0)) {
            takeposReleasePaymentLock();
            alert(<?php echo json_encode($langs->trans('TakeposPaymentRateRequired'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>);
            return false;
        }
        if (isNaN(amountpayed) || amountpayed < 0) {
            amountpayed = 0;
        }
        if (!amountpayed) {
            amountpayed = normalizeReceivedValue(takeposRemainToPay);
            paymentForeignAmount = paymentUsesForeignCurrency() ? computeForeignAmountFromBase(amountpayed) : 0;
        }
        if (amountpayed > <?php echo $invoice->total_ttc; ?>) {
            amountpayed = <?php echo $invoice->total_ttc; ?>;
            paymentForeignAmount = paymentUsesForeignCurrency() ? computeForeignAmountFromBase(amountpayed) : 0;
        }

        // Final stock validation before submitting payment
        <?php if (getDolGlobalInt('TAKEPOS_PRODUCT_IN_STOCK') == 1 && $invoiceid > 0) { ?>
        $.ajax({
            url: '<?php echo dol_escape_js(DOL_URL_ROOT . '/takepos/ajax/checkstock.php'); ?>',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'check_invoice',
                invoiceid: invoiceid,
                token: '<?php echo dol_escape_js(newToken()); ?>'
            },
            success: function(data) {
                if (data && data.allowed === false) {
                    takeposReleasePaymentLock();
                    var msg = data.message || <?php echo json_encode($langs->trans('TakeposStockInsufficientLine'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
                    alert(msg);
                    return;
                }
                doValidate(payment, invoiceid, accountid, amountpayed, excess, paymentCurrency, paymentRate, paymentForeignAmount, paymentForeignExcess);
            },
            error: function() {
                // Strict mode: block payment if stock check endpoint is unreachable.
                // Fail-open (legacy behaviour) when strict mode is disabled.
                <?php if (getDolGlobalInt('TAKEPOS_STRICT_STOCK_CHECK') == 1) { ?>
                takeposReleasePaymentLock();
                alert('<?php echo dol_escape_js($langs->trans('StockCheckUnavailableStrict', 'Stock validation service is unavailable. Payment blocked (strict mode). Please contact your administrator.')); ?>');
                <?php } else { ?>
                doValidate(payment, invoiceid, accountid, amountpayed, excess, paymentCurrency, paymentRate, paymentForeignAmount, paymentForeignExcess);
                <?php } ?>
            }
        });
        return true;
        <?php } ?>

        doValidate(payment, invoiceid, accountid, amountpayed, excess, paymentCurrency, paymentRate, paymentForeignAmount, paymentForeignExcess);
        return true;
    }

    function doValidate(payment, invoiceid, accountid, amountpayed, excess, paymentCurrency, paymentRate, paymentForeignAmount, paymentForeignExcess)
    {
        console.log("We click on the payment mode to pay amount = "+amountpayed);
        var paymentUrl = "invoice.php?place=<?php echo $place; ?>&action=valid&token=<?php echo newToken(); ?>&pay="+encodeURIComponent(payment)+"&amount="+encodeURIComponent(amountpayed)+"&excess="+encodeURIComponent(excess)+"&invoiceid="+encodeURIComponent(invoiceid)+"&accountid="+encodeURIComponent(accountid);
        if (paymentCurrency && paymentCurrency !== takeposBaseCurrency) {
            paymentUrl += "&payment_currency=" + encodeURIComponent(paymentCurrency);
            paymentUrl += "&payment_rate=" + encodeURIComponent(paymentRate);
            paymentUrl += "&payment_amount_foreign=" + encodeURIComponent(paymentForeignAmount);
            paymentUrl += "&payment_excess_foreign=" + encodeURIComponent(paymentForeignExcess);
        }
        parent.$("#poslines").load(paymentUrl, function(responseText, textStatus) {
            if (textStatus && textStatus !== 'success') {
                takeposReleasePaymentLock();
                alert(<?php echo json_encode($langs->trans('Error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>);
                return;
            }

            var amountNumeric = normalizeReceivedValue(amountpayed);
            var finished = (amountNumeric >= normalizeReceivedValue(<?php echo json_encode((float) $remaintopay); ?>));
            if (finished) {
                console.log("Close popup and reset cart after successful payment");
                if (typeof parent.TakeposFinalizePaymentUi === 'function') {
                    parent.TakeposFinalizePaymentUi(invoiceid);
                } else {
                    parent.$('#invoiceid').val("");
                    if (typeof parent.loadPosLines === 'function') {
                        parent.loadPosLines("invoice.php?token=<?php echo newToken(); ?>&place=<?php echo $place; ?>&invoiceid=0");
                    }
                    if (typeof parent.refreshTakeposShiftPanel === 'function') { parent.refreshTakeposShiftPanel(); }
                }
                parent.$.colorbox.close();
            } else {
                console.log("Amount is not complete, so we do NOT close popup and reload it.");
                takeposReleasePaymentLock();
                location.reload();
            }
        });
    }
    function handlePaymentKeyboard(ev)
    {
        if (!ev || ev.defaultPrevented) return;
        if (ev.key === 'Escape') {
            ev.preventDefault();
            parent.$.colorbox.close();
            return;
        }
        if (ev.key === 'Enter') {
            ev.preventDefault();
            if (takeposPrimaryPaymentCode) {
                Validate(takeposPrimaryPaymentCode);
            }
            return;
        }
        <?php if (!getDolGlobalInt("TAKEPOS_NUMPAD")) { ?>
        if (ev.key === 'Backspace') {
            ev.preventDefault();
            received = String(received || '');
            received = received.substring(0, Math.max(0, received.length - 1));
            updateReceivedDisplay();
        }
        <?php } ?>
    }

    document.addEventListener('keydown', handlePaymentKeyboard);
    $(function () {
        $('#takepos-payment-currency').on('change', function () {
            takeposSelectedPaymentCurrency = getSelectedPaymentCurrency();
            var selectedCode = takeposSelectedPaymentCurrency;
            var defaultRate = 1;
            for (var i = 0; i < takeposPaymentCurrencies.length; i++) {
                if (String(takeposPaymentCurrencies[i].code || '') === selectedCode) {
                    defaultRate = normalizeReceivedValue(takeposPaymentCurrencies[i].rate || 1) || 1;
                    break;
                }
            }
            if (selectedCode !== takeposBaseCurrency) {
                $('#takepos-payment-rate').val(price2numjs(defaultRate));
            }
            updateReceivedDisplay();
        });
        $('#takepos-payment-rate').on('input change', function () {
            takeposSelectedPaymentRate = getSelectedPaymentRate();
            updateReceivedDisplay();
        });
        updateReceivedDisplay();
    });

    function fetchPaymentIntentClientSecret(amount, invoiceid) {
        const bodyContent = JSON.stringify({ amount : amount, invoiceid : invoiceid });
        <?php
        $urlpaymentintent = DOL_URL_ROOT.'/stripe/ajax/ajax.php?action=createPaymentIntent&token='.newToken().'&servicestatus='.urlencode((string) $servicestatus);
        if (!empty($stripeacc)) {
            $urlpaymentintent .= '&stripeacc='.$stripeacc;
        }
        ?>
        return fetch('<?php echo $urlpaymentintent; ?>', {
            method: "POST",
            headers: {
                'Content-Type': 'application/json'
            },
            body: bodyContent
        })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                return data.client_secret;
            });
    }


    function capturePaymentIntent(paymentIntentId) {
        const bodyContent = JSON.stringify({"id": paymentIntentId})
        <?php
        $urlpaymentintent = DOL_URL_ROOT.'/stripe/ajax/ajax.php?action=capturePaymentIntent&token='.newToken().'&servicestatus='.urlencode((string) ($servicestatus));
        if (!empty($stripeacc)) {
            $urlpaymentintent .= '&stripeacc='.urlencode($stripeacc);
        }
        ?>
        return fetch('<?php echo $urlpaymentintent; ?>', {
            method: "POST",
            headers: {
                'Content-Type': 'application/json'
            },
            body: bodyContent
        })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                return data.client_secret;
            });
    }


    function ValidateStripeTerminal() {
        console.log("Launch ValidateStripeTerminal");
        var invoiceid = <?php echo($invoiceid > 0 ? $invoiceid : 0); ?>;
        var accountid = $("#selectaccountid").val();
        var amountpayed = $("#change1").val();
        var excess = $("#change2").val();
        if (amountpayed > <?php echo $invoice->getRemainToPay(); ?>) {
            amountpayed = <?php echo $invoice->getRemainToPay(); ?>;
        }
        if (amountpayed == 0) {
            amountpayed = <?php echo $invoice->getRemainToPay(); ?>;
        }

        console.log("Pay with terminal ", amountpayed);

        fetchPaymentIntentClientSecret(amountpayed, invoiceid).then(function(client_secret) {
            <?php if (empty($servicestatus) && getDolGlobalString('STRIPE_TERMINAL_SIMULATED')) { ?>
            terminal.setSimulatorConfiguration({testCardNumber: '<?php echo dol_escape_js(getDolGlobalString('STRIPE_TERMINAL_SIMULATED')); ?>'});
            <?php } ?>
            document.getElementById("card-present-alert").innerHTML = '<div class="warning clearboth"><?php echo $langs->trans('PaymentSendToStripeTerminal'); ?></div>';
            terminal.collectPaymentMethod(client_secret).then(function(result) {
                if (result.error) {
                    // Placeholder for handling result.error
                    document.getElementById("card-present-alert").innerHTML = '<div class="error clearboth">'+result.error.message+'</div>';
                } else {
                    document.getElementById("card-present-alert").innerHTML = '<div class="warning clearboth"><?php echo $langs->trans('PaymentBeingProcessed'); ?></div>';
                    console.log('terminal.collectPaymentMethod', result.paymentIntent);
                    terminal.processPayment(result.paymentIntent).then(function(result) {
                        if (result.error) {
                            document.getElementById("card-present-alert").innerHTML = '<div class="error clearboth">'+result.error.message+'</div>';
                            console.log(result.error)
                        } else if (result.paymentIntent) {
                            paymentIntentId = result.paymentIntent.id;
                            console.log('terminal.processPayment', result.paymentIntent);
                            capturePaymentIntent(paymentIntentId).then(function(client_secret) {
                                if (result.error) {
                                    // Placeholder for handling result.error
                                    document.getElementById("card-present-alert").innerHTML = '<div class="error clearboth">'+result.error.message+'</div>';
                                    console.log("error when capturing paymentIntent", result.error);
                                } else {
                                    document.getElementById("card-present-alert").innerHTML = '<div class="warning clearboth"><?php echo $langs->trans('PaymentValidated'); ?></div>';
                                    console.log("Capture paymentIntent successful "+paymentIntentId);
                                    parent.$("#poslines").load("invoice.php?place=<?php echo $place; ?>&action=valid&token=<?php echo newToken(); ?>&pay=CB&amount="+amountpayed+"&excess="+excess+"&invoiceid="+invoiceid+"&accountid="+accountid, function() {
                                        if (amountpayed > <?php echo $remaintopay; ?> || amountpayed == <?php echo $remaintopay; ?> || amountpayed==0 ) {
                                            console.log("Close popup");
                                            parent.$.colorbox.close();
                                        }
                                        else {
                                            console.log("Amount is not comple, so we do NOT close popup and reload it.");
                                            location.reload();
                                        }
                                    });

                                }
                            });
                        }
                    });
                }
            });
        });
    }

    function ValidateSumup() {
        console.log("Launch ValidateSumup");
        <?php $_SESSION['SMP_CURRENT_PAYMENT'] = "NEW" ?>
        var invoiceid = <?php echo($invoiceid > 0 ? $invoiceid : 0); ?>;
        var amountpayed = $("#change1").val();
        if (amountpayed > <?php echo $invoice->total_ttc; ?>) {
            amountpayed = <?php echo $invoice->total_ttc; ?>;
        }
        if (amountpayed == 0) {
            amountpayed = <?php echo $invoice->total_ttc; ?>;
        }
        var currencycode = "<?php echo $invoice->multicurrency_code; ?>";

        // Starting sumup app
        window.open('sumupmerchant://pay/1.0?affiliate-key=<?php echo urlencode(getDolGlobalString('TAKEPOS_SUMUP_AFFILIATE')) ?>&app-id=<?php echo urlencode(getDolGlobalString('TAKEPOS_SUMUP_APPID')) ?>&amount=' + amountpayed + '&currency=' + currencycode + '&title=' + invoiceid + '&callback=<?php echo DOL_MAIN_URL_ROOT ?>/takepos/smpcb.php');

        var loop = window.setInterval(function () {
            $.ajax({
                method: 'POST',
                data: { token: '<?php echo currentToken(); ?>' },
                url: '<?php echo DOL_URL_ROOT ?>/takepos/smpcb.php?status' }).done(function (data) {
                console.log(data);
                if (data === "SUCCESS") {
                    parent.$("#poslines").load("invoice.php?place=<?php echo urlencode($place); ?>&action=valid&token=<?php echo newToken(); ?>&pay=CB&amount=" + amountpayed + "&invoiceid=" + invoiceid, function () {
                        //parent.$("#poslines").scrollTop(parent.$("#poslines")[0].scrollHeight);
                        parent.$.colorbox.close();
                        //parent.setFocusOnSearchField();	// This does not have effect
                    });
                    clearInterval(loop);
                } else if (data === "FAILED") {
                    parent.$.colorbox.close();
                    clearInterval(loop);
                }
            });
        }, 2500);
    }

    <?php
    if (getDolGlobalString('TAKEPOS_CUSTOMER_DISPLAY')) {
        echo "var line1='".$langs->trans('TotalTTC')."'.substring(0,20);";
        echo "line1=line1.padEnd(20);";
        echo "var line2='".price($invoice->total_ttc, 1, '', 1, -1, -1)."'.substring(0,20);";
        echo "line2=line2.padEnd(20);";
        echo "$.ajax({
		type: 'GET',
		data: { text: line1+line2 },
		url: '".getDolGlobalString('TAKEPOS_PRINT_SERVER')."/display/index.php',
	});";
    }
    ?>
</script>


<div style="position:relative; padding-top: 20px; left:5%; height:140px; width:90%;">
    <div class="paymentbordline paymentbordlinetotal center">
		<span class="takepospay colorwhite"><?php echo $langs->trans('TotalTTC'); ?>: <span id="totaldisplay" class="colorwhite"><?php
                echo price($invoice->total_ttc, 1, '', 1, -1, -1, $conf->currency);
                if ($takeposDisplayCurrencyCode !== '' && $takeposDisplayCurrencyRate > 0) {
                    print ' &nbsp; <span id="linecolht-span-total opacitymedium" style="font-size:0.9em; font-style:italic;">(' . price($invoice->total_ttc * $takeposDisplayCurrencyRate) . ' ' . $takeposDisplayCurrencyCode . ')</span>';
                }
                ?></span></span>
    </div>
    <?php if ($remaintopay != $invoice->total_ttc) { ?>
        <div class="paymentbordline paymentbordlineremain center">
			<span class="takepospay colorwhite"><?php echo $langs->trans('RemainToPay'); ?>: <span id="remaintopaydisplay" class="colorwhite"><?php
                    echo price($remaintopay, 1, '', 1, -1, -1, $conf->currency);
                    if ($takeposDisplayCurrencyCode !== '' && $takeposDisplayCurrencyRate > 0) {
                        print ' &nbsp; <span id="linecolht-span-total opacitymedium" style="font-size:0.9em; font-style:italic;">(' . price($remaintopay * $takeposDisplayCurrencyRate) . ' ' . $takeposDisplayCurrencyCode . ')</span>';
                    }
                    ?></span></span>
        </div>
    <?php } ?>
    <div class="paymentbordline paymentbordlinereceived center">
		<span class="takepospay colorwhite"><?php echo $langs->trans("Received"); ?>: <span class="change1 colorred"><?php
                echo price(0, 1, '', 1, -1, -1, $conf->currency);
                if ($takeposDisplayCurrencyCode !== '' && $takeposDisplayCurrencyRate > 0) {
                    print ' &nbsp; <span id="linecolht-span-total opacitymedium" style="font-size:0.9em; font-style:italic;">(' . price(0, 1, '', 1, -1, -1, $takeposDisplayCurrencyCode) . ')</span>';
                }
                ?></span><input type="hidden" id="change1" class="change1" value="0"></span>
    </div>
    <div class="paymentbordline paymentbordlinechange center">
		<span class="takepospay colorwhite"><?php echo $langs->trans("Change"); ?>: <span class="change2 colorwhite"><?php
                echo price(0, 1, '', 1, -1, -1, $conf->currency);
                if ($takeposDisplayCurrencyCode !== '' && $takeposDisplayCurrencyRate > 0) {
                    print ' &nbsp; <span id="linecolht-span-total opacitymedium" style="font-size:0.9em; font-style:italic;">(' . price(0, 1, '', 1, -1, -1, $takeposDisplayCurrencyCode) . ')</span>';
                }
                ?></span><input type="hidden" id="change2" class="change2" value="0"></span>
    </div>
    <?php if (count($takeposPaymentCurrencies) > 1) { ?>
        <div class="paymentbordline paddingtop paddingbottom center" style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center;align-items:center;">
            <span class="takepospay colorwhite"><?php echo dol_escape_htmltag($langs->trans('TakeposPaymentCurrency')); ?>:</span>
            <select id="takepos-payment-currency" class="flat">
                <?php foreach ($takeposPaymentCurrencies as $currencyOption) { ?>
                    <option value="<?php echo dol_escape_htmltag($currencyOption['code']); ?>"<?php echo ($currencyOption['code'] === $takeposSelectedCurrencyCode ? ' selected' : ''); ?>><?php echo dol_escape_htmltag($currencyOption['code']); ?></option>
                <?php } ?>
            </select>
            <span id="takepos-payment-rate-wrap" style="<?php echo ($takeposSelectedCurrencyCode === $takeposBaseCurrency ? 'display:none;' : 'display:inline-flex;'); ?>gap:8px;align-items:center;">
			<span class="takepospay colorwhite"><?php echo dol_escape_htmltag($langs->trans('TakeposPaymentRate')); ?>:</span>
			<input type="number" step="0.000001" min="0.000001" id="takepos-payment-rate" class="flat" style="width:120px;" value="<?php echo dol_escape_htmltag(price2num((string) $takeposSelectedCurrencyRate, 'MU')); ?>">
		</span>
            <span id="takepos-payment-currency-hint" class="opacitymedium" style="font-size:0.9em;"></span>
        </div>
    <?php } ?>
    <?php
    if (getDolGlobalString('TAKEPOS_CAN_FORCE_BANK_ACCOUNT_DURING_PAYMENT')) {
        require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
        print '<div class="paymentbordline paddingtop paddingbottom center">';
        $filter = '';
        $form = new Form($db);
        print '<span class="takepospay colorwhite">'.$langs->trans("BankAccount").': </span>';
        $form->select_comptes(0, 'accountid', 0, $filter, 1, '');
        print ajax_combobox('selectaccountid');
        print '</div>';
    }
    ?>
</div>
<div style="position:absolute; left:5%; height:52%; width:90%;">
    <?php
    $action_buttons = array(
        array(
            "function" => "reset()",
            "span" => "style='font-size: 150%;'",
            "text" => "C",
            "class" => "poscolorblue"
        ),
        array(
            "function" => "parent.$.colorbox.close();",
            "span" => "id='printtext' style='font-weight: bold; font-size: 18pt;'",
            "text" => "X",
            "class" => "poscolordelete"
        ),
    );
    $numpad = getDolGlobalString('TAKEPOS_NUMPAD');
    if (isModEnabled('stripe') && isset($keyforstripeterminalbank) && getDolGlobalString('STRIPE_CARD_PRESENT')) {
        print '<span id="card-present-alert">';
        dol_htmloutput_mesg($langs->trans('ConnectingToStripeTerminal', 'Stripe'), [], 'warning', 1);
        print '</span>';
    }
    print '<button type="button" class="calcbutton" onclick="addreceived('.($numpad == 0 ? '7' : '10').')">'.($numpad == 0 ? '7' : '10').'</button>';
    print '<button type="button" class="calcbutton" onclick="addreceived('.($numpad == 0 ? '8' : '20').')">'.($numpad == 0 ? '8' : '20').'</button>';
    print '<button type="button" class="calcbutton" onclick="addreceived('.($numpad == 0 ? '9' : '50').')">'.($numpad == 0 ? '9' : '50').'</button>';
    ?>
    <?php if (count($arrayOfValidPaymentModes) > 0) {
        $paycode = $arrayOfValidPaymentModes[0]->code;
        $payIcon = '';
        if ($paycode == 'LIQ') {
            if (!isset($conf->global->TAKEPOS_NUMPAD_USE_PAYMENT_ICON) || getDolGlobalString('TAKEPOS_NUMPAD_USE_PAYMENT_ICON')) {
                $payIcon = 'coins';
            }
        } elseif ($paycode == 'CB') {
            if (!isset($conf->global->TAKEPOS_NUMPAD_USE_PAYMENT_ICON) || getDolGlobalString('TAKEPOS_NUMPAD_USE_PAYMENT_ICON')) {
                $payIcon = 'credit-card';
            }
        } elseif ($paycode == 'CHQ') {
            if (!isset($conf->global->TAKEPOS_NUMPAD_USE_PAYMENT_ICON) || getDolGlobalString('TAKEPOS_NUMPAD_USE_PAYMENT_ICON')) {
                $payIcon = 'money-check';
            }
        }

        print '<button type="button" class="calcbutton2 takepos-payment-button" onclick="Validate(\''.dol_escape_js($paycode).'\')">'.(!empty($payIcon) ? '<span class="fa fa-2x fa-'.$payIcon.' iconwithlabel"></span><span class="hideonsmartphone"><br>'.$langs->trans("PaymentTypeShort".$arrayOfValidPaymentModes[0]->code) : $langs->trans("PaymentTypeShort".$arrayOfValidPaymentModes[0]->code)).'</span></button>';
    } else {
        print '<button type="button" class="calcbutton2">'.$langs->trans("NoPaimementModesDefined").'</button>';
    }

    print '<button type="button" class="calcbutton" onclick="addreceived('.($numpad == 0 ? '4' : '1').')">'.($numpad == 0 ? '4' : '1').'</button>';
    print '<button type="button" class="calcbutton" onclick="addreceived('.($numpad == 0 ? '5' : '2').')">'.($numpad == 0 ? '5' : '2').'</button>';
    print '<button type="button" class="calcbutton" onclick="addreceived('.($numpad == 0 ? '6' : '5').')">'.($numpad == 0 ? '6' : '5').'</button>';
    ?>
    <?php if (count($arrayOfValidPaymentModes) > 1) {
        $paycode = $arrayOfValidPaymentModes[1]->code;
        $payIcon = '';
        if ($paycode == 'LIQ') {
            if (!isset($conf->global->TAKEPOS_NUMPAD_USE_PAYMENT_ICON) || getDolGlobalString('TAKEPOS_NUMPAD_USE_PAYMENT_ICON')) {
                $payIcon = 'coins';
            }
        } elseif ($paycode == 'CB') {
            if (!isset($conf->global->TAKEPOS_NUMPAD_USE_PAYMENT_ICON) || getDolGlobalString('TAKEPOS_NUMPAD_USE_PAYMENT_ICON')) {
                $payIcon = 'credit-card';
            }
        } elseif ($paycode == 'CHQ') {
            if (!isset($conf->global->TAKEPOS_NUMPAD_USE_PAYMENT_ICON) || getDolGlobalString('TAKEPOS_NUMPAD_USE_PAYMENT_ICON')) {
                $payIcon = 'money-check';
            }
        }

        print '<button type="button" class="calcbutton2 takepos-payment-button" onclick="Validate(\''.dol_escape_js($paycode).'\')">'.(!empty($payIcon) ? '<span class="fa fa-2x fa-'.$payIcon.' iconwithlabel"></span><br> '.$langs->trans("PaymentTypeShort".$arrayOfValidPaymentModes[1]->code) : $langs->trans("PaymentTypeShort".$arrayOfValidPaymentModes[1]->code)).'</button>';
    } else {
        $button = array_pop($action_buttons);
        print '<button type="button" class="calcbutton2" onclick="'.$button["function"].'"><span '.$button["span"].'>'.$button["text"].'</span></button>';
    }

    print '<button type="button" class="calcbutton" onclick="addreceived('.($numpad == 0 ? '1' : '0.10').')">'.($numpad == 0 ? '1' : '0.10').'</button>';
    print '<button type="button" class="calcbutton" onclick="addreceived('.($numpad == 0 ? '2' : '0.20').')">'.($numpad == 0 ? '2' : '0.20').'</button>';
    print '<button type="button" class="calcbutton" onclick="addreceived('.($numpad == 0 ? '3' : '0.50').')">'.($numpad == 0 ? '3' : '0.50').'</button>';
    ?>
    <?php if (count($arrayOfValidPaymentModes) > 2) {
        $paycode = $arrayOfValidPaymentModes[2]->code;
        $payIcon = '';
        if ($paycode == 'LIQ') {
            if (!isset($conf->global->TAKEPOS_NUMPAD_USE_PAYMENT_ICON) || getDolGlobalString('TAKEPOS_NUMPAD_USE_PAYMENT_ICON')) {
                $payIcon = 'coins';
            }
        } elseif ($paycode == 'CB') {
            if (!isset($conf->global->TAKEPOS_NUMPAD_USE_PAYMENT_ICON) || getDolGlobalString('TAKEPOS_NUMPAD_USE_PAYMENT_ICON')) {
                $payIcon = 'credit-card';
            }
        } elseif ($paycode == 'CHQ') {
            if (!isset($conf->global->TAKEPOS_NUMPAD_USE_PAYMENT_ICON) || getDolGlobalString('TAKEPOS_NUMPAD_USE_PAYMENT_ICON')) {
                $payIcon = 'money-check';
            }
        }

        print '<button type="button" class="calcbutton2 takepos-payment-button" onclick="Validate(\''.dol_escape_js($paycode).'\')">'.(!empty($payIcon) ? '<span class="fa fa-2x fa-'.$payIcon.' iconwithlabel"></span><br>'.$langs->trans("PaymentTypeShort".$arrayOfValidPaymentModes[2]->code) : $langs->trans("PaymentTypeShort".$arrayOfValidPaymentModes[2]->code)).'</button>';
    } else {
        $button = array_pop($action_buttons);
        print '<button type="button" class="calcbutton2" onclick="'.$button["function"].'"><span '.$button["span"].'>'.$button["text"].'</span></button>';
    }

    print '<button type="button" class="calcbutton" onclick="addreceived('.($numpad == 0 ? '0' : '0.01').')">'.($numpad == 0 ? '0' : '0.01').'</button>';
    print '<button type="button" class="calcbutton" onclick="addreceived('.($numpad == 0 ? '\'000\'' : '0.02').')">'.($numpad == 0 ? '000' : '0.02').'</button>';
    print '<button type="button" class="calcbutton" onclick="addreceived('.($numpad == 0 ? '\'.\'' : '0.05').')">'.($numpad == 0 ? '.' : '0.05').'</button>';

    $i = 3;
    while ($i < count($arrayOfValidPaymentModes)) {
        $paycode = $arrayOfValidPaymentModes[$i]->code;
        $payIcon = '';
        if ($paycode == 'LIQ') {
            if (!isset($conf->global->TAKEPOS_NUMPAD_USE_PAYMENT_ICON) || getDolGlobalString('TAKEPOS_NUMPAD_USE_PAYMENT_ICON')) {
                $payIcon = 'coins';
            }
        } elseif ($paycode == 'CB') {
            if (!isset($conf->global->TAKEPOS_NUMPAD_USE_PAYMENT_ICON) || getDolGlobalString('TAKEPOS_NUMPAD_USE_PAYMENT_ICON')) {
                $payIcon = 'credit-card';
            }
        } elseif ($paycode == 'CHQ') {
            if (!isset($conf->global->TAKEPOS_NUMPAD_USE_PAYMENT_ICON) || getDolGlobalString('TAKEPOS_NUMPAD_USE_PAYMENT_ICON')) {
                $payIcon = 'money-check';
            }
        }

        print '<button type="button" class="calcbutton2 takepos-payment-button" onclick="Validate(\''.dol_escape_js($paycode).'\')">'.(!empty($payIcon) ? '<span class="fa fa-2x fa-'.$payIcon.' iconwithlabel"></span><br>'.$langs->trans("PaymentTypeShort".$arrayOfValidPaymentModes[$i]->code) : $langs->trans("PaymentTypeShort".$arrayOfValidPaymentModes[$i]->code)).'</button>';
        $i += 1;
    }

    if (isModEnabled('stripe') && isset($keyforstripeterminalbank) && getDolGlobalString('STRIPE_CARD_PRESENT')) {
        $keyforstripeterminalbank = takeposResolveTerminalStringConstant("CASHDESK_ID_BANKACCOUNT_STRIPETERMINAL", $_SESSION["takeposterminal"]);
        print '<span id="StripeTerminal"></span>';
        if ($keyforstripeterminalbank !== '') {
            // Nothing
        } else {
            $langs->loadLangs(array("errors", "admin"));
            //print '<button type="button" class="calcbutton2 disabled" title="'.$langs->trans("SetupNotComplete").'">TerminalOff</button>';
        }
    }

    $keyforsumupbank = takeposResolveTerminalStringConstant("CASHDESK_ID_BANKACCOUNT_SUMUP", $_SESSION["takeposterminal"]);
    if (getDolGlobalInt("TAKEPOS_ENABLE_SUMUP")) {
        if ($keyforsumupbank !== '') {
            print '<button type="button" class="calcbutton2" onclick="ValidateSumup();">Sumup</button>';
        } else {
            $langs->loadLangs(array("errors", "admin"));
            print '<button type="button" class="calcbutton2 disabled" title="'.$langs->trans("SetupNotComplete").'">Sumup</button>';
        }
    }

    $parameters = array();
    $reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $invoice, $action); // Note that $action and $object may have been modified by hook
    if ($reshook < 0) {
        setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
    }

    $class = ($i == 3) ? "calcbutton3" : "calcbutton2";
    foreach ($action_buttons as $button) {
        $newclass = $class.($button["class"] ? " ".$button["class"] : "");
        print '<button type="button" class="'.$newclass.'" onclick="'.$button["function"].'"><span '.$button["span"].'>'.$button["text"].'</span></button>';
    }

    if (getDolGlobalString('TAKEPOS_DELAYED_PAYMENT')) {
        print '<button type="button" class="calcbutton2 takepos-payment-button" onclick="Validate(\'delayed\')">'.$langs->trans("Reported").'</button>';
    }
    ?>

    <?php
    // Add code from hooks
    $parameters = array();
    $hookmanager->executeHooks('completePayment', $parameters, $invoice);
    print $hookmanager->resPrint;
    ?>

</div>

</body>
</html>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap');

    html, body { background: #f1f5f9 !important; font-family: 'Inter', sans-serif !important; }

    .takepos-payment-mode { border-radius: 8px !important; font-size: 12px !important; font-weight: 600 !important; margin: 6px 10px !important; }
    .takepos-payment-mode-live { background: #fff1f2 !important; color: #9f1239 !important; border-color: #fecdd3 !important; }
    .takepos-payment-mode-test { background: #eff6ff !important; color: #1e40af !important; border-color: #bfdbfe !important; }

    /* Summary panel */
    div[style*="height:140px"], div[style*="height: 140px"] {
        background: #1e2740 !important;
        border-radius: 12px !important;
        border: none !important;
        height: auto !important;
        min-height: 80px !important;
        overflow: visible !important;
        position: relative !important;
        z-index: 10 !important;
    }
    .paymentbordline { background: rgba(255,255,255,.07) !important; border-radius: 8px !important; border: none !important; }
    .takepospay { color: #6b8aaf !important; font-size: 10px !important; font-weight: 700 !important; text-transform: uppercase !important; letter-spacing: .08em !important; }
    .colorwhite { color: #e2eaf6 !important; font-size: 18px !important; font-weight: 800 !important; }
    .colorred   { color: #fb7185 !important; font-size: 18px !important; font-weight: 800 !important; }
    .colorgreen { color: #34d399 !important; font-size: 18px !important; font-weight: 800 !important; }

    /* Buttons pad — push it down enough for the tallest summary state */
    div[style*="height:52%"], div[style*="height: 52%"] {
        top: auto !important;
        position: relative !important;
        left: 5% !important;
        width: 90% !important;
        height: 52% !important;
        margin-top: 6px !important;
    }

    button.calcbutton {
        background: #fff !important; border: 1px solid #e2e8f0 !important; border-radius: 10px !important;
        color: #1e293b !important; font-family: 'Inter', sans-serif !important; font-size: 20px !important;
        font-weight: 700 !important; box-shadow: 0 1px 3px rgba(0,0,0,.07) !important;
        transition: background .1s, transform .07s !important;
    }
    button.calcbutton:hover  { background: #f0f4ff !important; border-color: #a5b4fc !important; }
    button.calcbutton:active { background: #e0e7ff !important; transform: scale(.95) !important; }
    button.calcbutton.poscolorblue { background: #fff !important; border: 1.5px solid #fca5a5 !important; color: #dc2626 !important; font-size: 16px !important; font-weight: 800 !important; }
    button.calcbutton.poscolorblue:hover { background: #fff1f2 !important; }

    button.calcbutton2 {
        border-radius: 10px !important; border: none !important; font-family: 'Inter', sans-serif !important;
        font-size: 12px !important; font-weight: 700 !important; color: #fff !important;
        transition: filter .12s, transform .07s !important;
    }
    button.calcbutton2:hover  { filter: brightness(1.1) !important; }
    button.calcbutton2:active { transform: scale(.96) !important; }
    button.calcbutton2:nth-of-type(1) { background: linear-gradient(150deg,#059669,#10b981) !important; }
    button.calcbutton2:nth-of-type(2) { background: linear-gradient(150deg,#1d4ed8,#3b82f6) !important; }
    button.calcbutton2:nth-of-type(3) { background: linear-gradient(150deg,#b45309,#f59e0b) !important; }
    button.calcbutton2.poscolordelete, button.calcbutton3.poscolordelete {
        background: #fff !important; border: 1.5px solid #fca5a5 !important;
        color: #dc2626 !important; font-size: 20px !important; font-weight: 800 !important;
    }
    button.calcbutton2.poscolordelete:hover, button.calcbutton3.poscolordelete:hover { background: #fff1f2 !important; }
    button.calcbutton3 { background: #fff !important; border: 1.5px solid #e2e8f0 !important; border-radius: 10px !important; color: #1e293b !important; font-family: 'Inter', sans-serif !important; font-weight: 700 !important; }
</style>
<script>
/* FIX (I09): Auto-focus the received-amount input when the payment modal opens.
 * Previously cashiers had to tap the field before typing — an extra interaction
 * on every single transaction. The 220ms delay lets the colorbox animation finish
 * before focus lands so the cursor appears correctly. */
(function () {
    'use strict';
    window.addEventListener('load', function () {
        setTimeout(function () {
            var el = document.getElementById('change1') ||
                     document.querySelector('input.change1');
            if (el) { el.focus(); if (typeof el.select === 'function') el.select(); }
        }, 220);
    });
}());
</script>