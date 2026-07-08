<?php
/**
 * TakePOS - Scale / Supermarket Barcode Configuration
 *
 * Configures the embedded-value barcode parsing used when items are
 * weighed or priced at a supermarket scale.  The scanner produces a
 * barcode whose first two digits identify the type (weight / price /
 * quantity) and whose remaining digits encode the product code followed
 * by the embedded numeric value.
 *
 * Reading order enforced by this page:
 *   [2-digit prefix] [product-code (N digits)] [embedded-value (M digits)] [optional check digit]
 *
 * Example (EAN-13, weight label, default settings):
 *   21  |  00123  |  00500  |  4
 *   ^^     ^^^^^     ^^^^^    ^
 *   prefix  prod    500 g   check (ignored when IGNORE_CHECK_DIGIT=1)
 */

require '../../main.inc.php';

require_once DOL_DOCUMENT_ROOT . '/takepos/lib/takepos_access_guard.php';
takeposAccessGuardCurrent($db, isset($user) ? $user : null, __FILE__);
require_once __DIR__ . '/../lib/takepos_lang.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/../class/TakeposAccess.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/takepos.lib.php';

/**
 * @var Conf         $conf
 * @var DoliDB       $db
 * @var HookManager  $hookmanager
 * @var Translate    $langs
 * @var User         $user
 */

// Admin-only
if (!$user->admin) {
    accessforbidden();
}

TakeposAccess::enforceAdmin($db, $user, 'takepos.admin.barcode_scale', null);

$langs->loadLangs(array('admin', 'cashdesk', 'takeposcustom@takepos'));

$action = GETPOST('action', 'aZ09');
$error  = 0;

/*
 * Actions
 */

if ($action === 'set') {
    $db->begin();

    // Master enable/disable
    $res = dolibarr_set_const($db, 'TAKEPOS_SUPERMARKET_BARCODE_ENABLE',
        GETPOSTINT('TAKEPOS_SUPERMARKET_BARCODE_ENABLE') ? '1' : '0',
        'chaine', 0, '', $conf->entity);
    if (!($res > 0)) {
        $error++;
    }

    // 2-digit mode prefixes
    foreach (array('WEIGHT', 'PRICE', 'QUANTITY') as $mode) {
        $key = 'TAKEPOS_SUPERMARKET_' . $mode . '_PREFIX';
        $val = trim(GETPOST($key, 'aZ09'));
        // Allow empty (disables that mode) but must be numeric if set
        if ($val !== '' && !ctype_digit($val)) {
            $error++;
            setEventMessages($langs->trans('TakeposBarcodeScaleErrorPrefixNumeric', $mode), null, 'errors');
        } else {
            $res = dolibarr_set_const($db, $key, $val, 'chaine', 0, '', $conf->entity);
            if (!($res > 0)) {
                $error++;
            }
        }
    }

    // Field lengths
    foreach (array('PRODUCT_CODE_LEN', 'VALUE_LEN') as $lenKey) {
        $key = 'TAKEPOS_SUPERMARKET_' . $lenKey;
        $val = (int) GETPOST($key, 'int');
        if ($val < 1 || $val > 20) {
            $error++;
            setEventMessages($langs->trans('TakeposBarcodeScaleErrorLenRange', $lenKey, 1, 20), null, 'errors');
        } else {
            $res = dolibarr_set_const($db, $key, (string) $val, 'chaine', 0, '', $conf->entity);
            if (!($res > 0)) {
                $error++;
            }
        }
    }

    // Divisors (positive floats)
    foreach (array('WEIGHT_DIVISOR' => '1000', 'PRICE_DIVISOR' => '100', 'QUANTITY_DIVISOR' => '1') as $divKey => $default) {
        $key = 'TAKEPOS_SUPERMARKET_' . $divKey;
        $val = trim(GETPOST($key, 'alpha'));
        $fval = (float) price2num($val !== '' ? $val : $default);
        if ($fval <= 0) {
            $error++;
            setEventMessages($langs->trans('TakeposBarcodeScaleErrorDivisorPositive', $divKey), null, 'errors');
        } else {
            $res = dolibarr_set_const($db, $key, (string) $fval, 'chaine', 0, '', $conf->entity);
            if (!($res > 0)) {
                $error++;
            }
        }
    }

    // Check-digit flag
    $res = dolibarr_set_const($db, 'TAKEPOS_SUPERMARKET_IGNORE_CHECK_DIGIT',
        GETPOSTINT('TAKEPOS_SUPERMARKET_IGNORE_CHECK_DIGIT') ? '1' : '0',
        'chaine', 0, '', $conf->entity);
    if (!($res > 0)) {
        $error++;
    }

    if (!$error) {
        $db->commit();
        setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
    } else {
        $db->rollback();
        setEventMessages($langs->trans('Error'), null, 'errors');
    }
}

/*
 * Current values (read after possible save)
 */

$cfgEnabled       = (int) getDolGlobalInt('TAKEPOS_SUPERMARKET_BARCODE_ENABLE');
$cfgWeightPrefix  = getDolGlobalString('TAKEPOS_SUPERMARKET_WEIGHT_PREFIX',   '21');
$cfgPricePrefix   = getDolGlobalString('TAKEPOS_SUPERMARKET_PRICE_PREFIX',    '22');
$cfgQtyPrefix     = getDolGlobalString('TAKEPOS_SUPERMARKET_QUANTITY_PREFIX', '23');
$cfgProductLen    = (int) getDolGlobalInt('TAKEPOS_SUPERMARKET_PRODUCT_CODE_LEN');
if ($cfgProductLen < 1) {
    $cfgProductLen = 5;
}
$cfgValueLen      = (int) getDolGlobalInt('TAKEPOS_SUPERMARKET_VALUE_LEN');
if ($cfgValueLen < 1) {
    $cfgValueLen = 5;
}
$cfgIgnoreCheck   = (int) getDolGlobalInt('TAKEPOS_SUPERMARKET_IGNORE_CHECK_DIGIT');
$cfgWeightDiv     = getDolGlobalString('TAKEPOS_SUPERMARKET_WEIGHT_DIVISOR',   '1000');
$cfgPriceDiv      = getDolGlobalString('TAKEPOS_SUPERMARKET_PRICE_DIVISOR',    '100');
$cfgQtyDiv        = getDolGlobalString('TAKEPOS_SUPERMARKET_QUANTITY_DIVISOR', '1');

/*
 * View
 */

$help_url = 'EN:Module_Point_of_sale_(TakePOS)';
llxHeader('', $langs->trans('TakeposBarcodeScaleTitle'), $help_url);

$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">' . $langs->trans('BackToModuleList') . '</a>';
print load_fiche_titre($langs->trans('TakeposBarcodeScaleTitle') . ' (TakePOS)', $linkback, 'title_setup');

$head = takepos_admin_prepare_head();
print dol_get_fiche_head($head, 'barcode_scale', 'TakePOS', -1, 'cash-register');

// ---- How scale barcodes work (info box) --------------------------------
print '<div class="info">';
print '<b>' . $langs->trans('TakeposBarcodeScaleHowItWorksTitle') . '</b><br>';
print $langs->trans('TakeposBarcodeScaleHowItWorksBody');
print '</div>';
print '<br>';

// ---- Form --------------------------------------------------------------
print '<form action="' . $_SERVER['PHP_SELF'] . '" method="POST">';
print '<input type="hidden" name="token"  value="' . newToken() . '">';
print '<input type="hidden" name="action" value="set">';

// === SECTION 1: Enable ===================================================
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td class="titlefield" colspan="3">' . $langs->trans('TakeposBarcodeScaleSectionEnable') . '</td>';
print '</tr>';

// Enable toggle
print '<tr class="oddeven">';
print '<td>' . $langs->trans('TakeposBarcodeScaleEnableLabel') . '</td>';
print '<td>';
print '<input type="checkbox" name="TAKEPOS_SUPERMARKET_BARCODE_ENABLE" value="1"' . ($cfgEnabled ? ' checked' : '') . '>';
print '</td>';
print '<td class="opacitymedium">' . $langs->trans('TakeposBarcodeScaleEnableHelp') . '</td>';
print '</tr>';
print '</table>';
print '<br>';

// === SECTION 2: Mode Prefixes ============================================
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td class="titlefield" colspan="3">' . $langs->trans('TakeposBarcodeScaleSectionPrefixes') . '</td>';
print '</tr>';

$prefixRows = array(
    array(
        'const'   => 'TAKEPOS_SUPERMARKET_WEIGHT_PREFIX',
        'label'   => $langs->trans('TakeposBarcodeScaleWeightPrefix'),
        'help'    => $langs->trans('TakeposBarcodeScaleWeightPrefixHelp'),
        'current' => $cfgWeightPrefix,
        'default' => '21',
    ),
    array(
        'const'   => 'TAKEPOS_SUPERMARKET_PRICE_PREFIX',
        'label'   => $langs->trans('TakeposBarcodeScalePricePrefix'),
        'help'    => $langs->trans('TakeposBarcodeScalePricePrefixHelp'),
        'current' => $cfgPricePrefix,
        'default' => '22',
    ),
    array(
        'const'   => 'TAKEPOS_SUPERMARKET_QUANTITY_PREFIX',
        'label'   => $langs->trans('TakeposBarcodeScaleQuantityPrefix'),
        'help'    => $langs->trans('TakeposBarcodeScaleQuantityPrefixHelp'),
        'current' => $cfgQtyPrefix,
        'default' => '23',
    ),
);

foreach ($prefixRows as $row) {
    print '<tr class="oddeven">';
    print '<td>' . dol_escape_htmltag($row['label']) . '</td>';
    print '<td>';
    print '<input type="text" name="' . dol_escape_htmltag($row['const']) . '" class="minwidth50" maxlength="2"';
    print ' value="' . dol_escape_htmltag($row['current']) . '"';
    print ' placeholder="' . dol_escape_htmltag($row['default']) . '">';
    print '</td>';
    print '<td class="opacitymedium">' . dol_escape_htmltag($row['help']) . '</td>';
    print '</tr>';
}

print '</table>';
print '<br>';

// === SECTION 3: Reading Order / Field Lengths ============================
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td class="titlefield" colspan="3">' . $langs->trans('TakeposBarcodeScaleSectionReadingOrder') . '</td>';
print '</tr>';

// Visual reading-order diagram
print '<tr class="oddeven">';
print '<td colspan="3">';
print '<div style="font-family:monospace; font-size:1.1em; background:#f5f5f5; border:1px solid #ccc; padding:10px 16px; border-radius:4px; margin:6px 0;">';
print '<span style="background:#b3d9ff; padding:2px 6px; border-radius:3px; margin-right:2px;" title="' . $langs->trans('TakeposBarcodeScalePrefixLabel') . '">PP</span>';
print '<span style="background:#b3ffb3; padding:2px 6px; border-radius:3px; margin-right:2px;" title="' . $langs->trans('TakeposBarcodeScaleProductCodeLabel') . '">';
print str_repeat('R', $cfgProductLen);
print '</span>';
print '<span style="background:#ffe0b3; padding:2px 6px; border-radius:3px; margin-right:2px;" title="' . $langs->trans('TakeposBarcodeScaleValueLabel') . '">';
print str_repeat('V', $cfgValueLen);
print '</span>';
if ($cfgIgnoreCheck) {
    print '<span style="background:#e0e0e0; padding:2px 6px; border-radius:3px; text-decoration:line-through;" title="' . $langs->trans('TakeposBarcodeScaleCheckDigitIgnoredLabel') . '">C</span>';
} else {
    print '<span style="background:#ffd6d6; padding:2px 6px; border-radius:3px;" title="' . $langs->trans('TakeposBarcodeScaleCheckDigitLabel') . '">C</span>';
}
print '</div>';
print '<small class="opacitymedium">';
print '<span style="background:#b3d9ff; padding:1px 4px; border-radius:2px;">PP</span> = ' . $langs->trans('TakeposBarcodeScalePrefixLabel') . ' (2 digits)&nbsp;&nbsp;';
print '<span style="background:#b3ffb3; padding:1px 4px; border-radius:2px;">R…</span> = ' . $langs->trans('TakeposBarcodeScaleProductCodeLabel') . '&nbsp;&nbsp;';
print '<span style="background:#ffe0b3; padding:1px 4px; border-radius:2px;">V…</span> = ' . $langs->trans('TakeposBarcodeScaleValueLabel') . '&nbsp;&nbsp;';
print '<span style="background:#ffd6d6; padding:1px 4px; border-radius:2px;">C</span> = ' . $langs->trans('TakeposBarcodeScaleCheckDigitLabel');
print '</small>';
print '</td>';
print '</tr>';

// Product code length
print '<tr class="oddeven">';
print '<td>' . $langs->trans('TakeposBarcodeScaleProductCodeLen') . '</td>';
print '<td>';
print '<input type="number" name="TAKEPOS_SUPERMARKET_PRODUCT_CODE_LEN" class="minwidth50" min="1" max="20"';
print ' value="' . (int) $cfgProductLen . '">';
print '</td>';
print '<td class="opacitymedium">' . $langs->trans('TakeposBarcodeScaleProductCodeLenHelp') . '</td>';
print '</tr>';

// Embedded value length
print '<tr class="oddeven">';
print '<td>' . $langs->trans('TakeposBarcodeScaleValueLen') . '</td>';
print '<td>';
print '<input type="number" name="TAKEPOS_SUPERMARKET_VALUE_LEN" class="minwidth50" min="1" max="20"';
print ' value="' . (int) $cfgValueLen . '">';
print '</td>';
print '<td class="opacitymedium">' . $langs->trans('TakeposBarcodeScaleValueLenHelp') . '</td>';
print '</tr>';

// Ignore check digit
print '<tr class="oddeven">';
print '<td>' . $langs->trans('TakeposBarcodeScaleIgnoreCheckDigit') . '</td>';
print '<td>';
print '<input type="checkbox" name="TAKEPOS_SUPERMARKET_IGNORE_CHECK_DIGIT" value="1"' . ($cfgIgnoreCheck ? ' checked' : '') . '>';
print '</td>';
print '<td class="opacitymedium">' . $langs->trans('TakeposBarcodeScaleIgnoreCheckDigitHelp') . '</td>';
print '</tr>';

print '</table>';
print '<br>';

// === SECTION 4: Divisors =================================================
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td class="titlefield" colspan="3">' . $langs->trans('TakeposBarcodeScaleSectionDivisors') . '</td>';
print '</tr>';

$divisorRows = array(
    array(
        'const'   => 'TAKEPOS_SUPERMARKET_WEIGHT_DIVISOR',
        'label'   => $langs->trans('TakeposBarcodeScaleWeightDivisor'),
        'help'    => $langs->trans('TakeposBarcodeScaleWeightDivisorHelp'),
        'current' => $cfgWeightDiv,
        'default' => '1000',
        'unit'    => $langs->trans('TakeposBarcodeScaleWeightDivisorUnit'),
    ),
    array(
        'const'   => 'TAKEPOS_SUPERMARKET_PRICE_DIVISOR',
        'label'   => $langs->trans('TakeposBarcodeScalePriceDivisor'),
        'help'    => $langs->trans('TakeposBarcodeScalePriceDivisorHelp'),
        'current' => $cfgPriceDiv,
        'default' => '100',
        'unit'    => $langs->trans('TakeposBarcodeScalePriceDivisorUnit'),
    ),
    array(
        'const'   => 'TAKEPOS_SUPERMARKET_QUANTITY_DIVISOR',
        'label'   => $langs->trans('TakeposBarcodeScaleQtyDivisor'),
        'help'    => $langs->trans('TakeposBarcodeScaleQtyDivisorHelp'),
        'current' => $cfgQtyDiv,
        'default' => '1',
        'unit'    => $langs->trans('TakeposBarcodeScaleQtyDivisorUnit'),
    ),
);

foreach ($divisorRows as $row) {
    print '<tr class="oddeven">';
    print '<td>' . dol_escape_htmltag($row['label']) . '</td>';
    print '<td>';
    print '<input type="text" name="' . dol_escape_htmltag($row['const']) . '" class="minwidth75"';
    print ' value="' . dol_escape_htmltag($row['current']) . '"';
    print ' placeholder="' . dol_escape_htmltag($row['default']) . '">';
    if (!empty($row['unit'])) {
        print ' <span class="opacitymedium">' . dol_escape_htmltag($row['unit']) . '</span>';
    }
    print '</td>';
    print '<td class="opacitymedium">' . dol_escape_htmltag($row['help']) . '</td>';
    print '</tr>';
}

print '</table>';

// === Save button =========================================================
print '<div class="tabsAction">';
print '<input type="submit" class="button button-save" value="' . dol_escape_htmltag($langs->trans('Save')) . '">';
print '</div>';

print '</form>';

// === Live test decoder ===================================================
$minLen = 2 + $cfgProductLen + $cfgValueLen;
print '<br>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td colspan="2">' . $langs->trans('TakeposBarcodeScaleSectionTest') . '</td>';
print '</tr>';
print '<tr class="oddeven">';
print '<td class="titlefield">';
print $langs->trans('TakeposBarcodeScaleTestInput') . ' ';
print '<input type="text" id="takepos_scale_test_input" class="minwidth200" maxlength="30"';
print ' placeholder="e.g. 21001230050001">';
print ' <button type="button" class="button" onclick="takeposScaleDecode()">' . dol_escape_htmltag($langs->trans('TakeposBarcodeScaleTestDecode')) . '</button>';
print '</td>';
print '<td id="takepos_scale_test_result" style="padding:6px 10px;"></td>';
print '</tr>';
print '</table>';

print '<script>
function takeposScaleDecode() {
    var raw    = document.getElementById("takepos_scale_test_input").value.trim();
    var out    = document.getElementById("takepos_scale_test_result");
    var prefixes = {
        "weight":   ' . json_encode($cfgWeightPrefix) . ',
        "price":    ' . json_encode($cfgPricePrefix) . ',
        "quantity": ' . json_encode($cfgQtyPrefix) . '
    };
    var prodLen      = ' . (int) $cfgProductLen . ';
    var valueLen     = ' . (int) $cfgValueLen . ';
    var ignoreCheck  = ' . ($cfgIgnoreCheck ? 'true' : 'false') . ';
    var weightDiv    = ' . (float) $cfgWeightDiv . ';
    var priceDiv     = ' . (float) $cfgPriceDiv . ';
    var qtyDiv       = ' . (float) $cfgQtyDiv . ';
    var minLen       = 2 + prodLen + valueLen;

    if (!raw.match(/^\d+$/)) {
        out.innerHTML = "<span style=\'color:red\'>' . $langs->trans('TakeposBarcodeScaleTestNotNumeric') . '</span>";
        return;
    }

    var trimmed = raw;
    if (ignoreCheck && trimmed.length === (minLen + 1)) {
        trimmed = trimmed.substring(0, minLen);
    }

    if (trimmed.length !== minLen) {
        out.innerHTML = "<span style=\'color:orange\'>' . $langs->trans('TakeposBarcodeScaleTestLengthMismatch') . ' (expected " + minLen + (ignoreCheck ? " or " + (minLen+1) : "") + ", got " + raw.length + ")</span>";
        return;
    }

    var prefix = trimmed.substring(0, 2);
    var mode   = null;
    for (var m in prefixes) {
        if (prefixes[m] !== "" && prefix === prefixes[m]) { mode = m; break; }
    }
    if (!mode) {
        out.innerHTML = "<span style=\'color:orange\'>' . $langs->trans('TakeposBarcodeScaleTestPrefixUnknown') . ' (" + prefix + ")</span>";
        return;
    }

    var productCode = trimmed.substring(2, 2 + prodLen);
    var valueRaw    = trimmed.substring(2 + prodLen, 2 + prodLen + valueLen);
    var numValue    = parseFloat(valueRaw);
    var finalValue, unit;
    if (mode === "weight")   { finalValue = numValue / weightDiv; unit = "' . $langs->trans('TakeposBarcodeScaleTestUnitKg') . '"; }
    else if (mode === "price") { finalValue = numValue / priceDiv;  unit = "' . $langs->trans('TakeposBarcodeScaleTestUnitCurrency') . '"; }
    else                     { finalValue = numValue / qtyDiv;    unit = "' . $langs->trans('TakeposBarcodeScaleTestUnitQty') . '"; }

    out.innerHTML =
        "<b>' . $langs->trans('TakeposBarcodeScaleTestMode') . ':</b> " + mode + " &nbsp; " +
        "<b>' . $langs->trans('TakeposBarcodeScaleTestProductCode') . ':</b> " + productCode + " &nbsp; " +
        "<b>' . $langs->trans('TakeposBarcodeScaleTestValue') . ':</b> " + finalValue.toFixed(4) + " " + unit;
}
document.getElementById("takepos_scale_test_input").addEventListener("keydown", function(e){
    if (e.key === "Enter") { e.preventDefault(); takeposScaleDecode(); }
});
</script>';

print dol_get_fiche_end();

llxFooter();
$db->close();
