<?php
/* Copyright (C) 2007-2008 Jeremie Ollivier    <jeremie.o@laposte.net>
 * Copyright (C) 2011-2023 Laurent Destailleur <eldy@users.sourceforge.net>
 * Copyright (C) 2012      Marcos Garcia       <marcosgdf@gmail.com>
 * Copyright (C) 2018      Andreu Bisquerra    <jove@bisquerra.com>
 * Copyright (C) 2019      Josep Lluis Amador  <joseplluis@lliuretic.cat>
 * Copyright (C) 2021      Nicolas ZABOURI     <info@inovea-conseil.com>
 * Copyright (C) 2024		MDW							<mdeweerd@users.noreply.github.com>
 * Copyright (C) 2024-2025  Frederic France         <frederic.france@free.fr>
 * Copyright (C) 202        Ferran Marcet      <fmarcet@2byte.es>
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
 *	\file       htdocs/takepos/receipt.php
 *	\ingroup    takepos
 *	\brief      Page to show a receipt.
 */

// Include main (when fie in included into send.php, $action is set and main was already loaded)
if (!isset($action)) {
	//if (! defined('NOREQUIREUSER'))	define('NOREQUIREUSER', '1');	// Not disabled cause need to load personalized language
	//if (! defined('NOREQUIREDB'))		define('NOREQUIREDB', '1');		// Not disabled cause need to load personalized language
	//if (! defined('NOREQUIRESOC'))		define('NOREQUIRESOC', '1');
	//if (! defined('NOREQUIRETRAN'))		define('NOREQUIRETRAN', '1');
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

	require '../main.inc.php';

	require_once __DIR__ . '/lib/takepos_lang.php';
	takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
}
require_once __DIR__ . '/lib/takepos_currency.php';
require_once __DIR__ . '/lib/takepos_loader.php';
require_once __DIR__ . '/lib/takepos_help.php';
// نظام الفوترة الإقليمية (الأردن / السعودية)
if (file_exists(__DIR__ . '/lib/takepos_billing_country.php')) {
	require_once __DIR__ . '/lib/takepos_billing_country.php';
}
// e-Fatura الأردني — إرسال تلقائي عند طباعة الإيصال
if (file_exists(__DIR__ . '/lib/takepos_efatura_jo.php')) {
	require_once __DIR__ . '/lib/takepos_efatura_jo.php';
}
// ZATCA السعودي — إرسال تلقائي
if (file_exists(__DIR__ . '/lib/takepos_zatca_sa.php')) {
	require_once __DIR__ . '/lib/takepos_zatca_sa.php';
}
foreach (array(
	'class/TakeposAccess.class.php',
	'class/TakeposUserAccess.class.php',
	'class/TakeposAudit.class.php',
) as $takeposModuleFile) {
	takeposRequireModuleFile($takeposModuleFile);
}
include_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

function takeposReceiptCanViewAllInvoices($db, $user)
{
	return (!empty($user->admin) || TakeposUserAccess::userHasPermission($db, $user, 'takepos.store.view_all'));
}

function takeposReceiptEnforceInvoiceAccess($db, $user, $invoice, $denyMessage)
{
	if (!is_object($invoice) || empty($invoice->id)) {
		accessforbidden($denyMessage);
	}

	if (takeposReceiptCanViewAllInvoices($db, $user)) {
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
$langs->loadLangs(array("main", "bills", "cashdesk", "companies", "takeposcustom@takepos"));

$place = (GETPOST('place', 'aZ09') ? GETPOST('place', 'aZ09') : 0); // $place is id of table for Bar or Restaurant

$facid = GETPOSTINT('facid');

$action = GETPOST('action', 'aZ09');
$gift = GETPOSTINT('gift');

if (!$user->hasRight('takepos', 'run')) {
	accessforbidden();
}

TakeposAccess::requireFrontendAccess($db, isset($user) ? $user : null, 'takepos.receipt', 'takepos.use', isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : null, $langs->trans('TakeposReceiptAccessDenied'), array('page' => 'receipt.php', 'action' => $action));

/*
 * Actions
 */

// None


/*
 * View
 */

print '<!DOCTYPE html>';
print '<html lang="'.dol_escape_htmltag((string) $langs->defaultlang).'" dir="'.(preg_match('/^ar/i', (string) $langs->defaultlang) ? 'rtl' : 'ltr').'">';
print '<head>';
print '<meta charset="utf-8">';
print '<meta name="viewport" content="width=device-width, initial-scale=1">';
print '<title>'.dol_escape_htmltag($langs->trans("Receipt")).'</title>';
print '</head>';

if ((string) $place != '' && !empty($_SESSION["takeposterminal"])) {
	$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."facture";
	$sql .= " WHERE ref = '(PROV-POS".$db->escape($_SESSION["takeposterminal"]."-".$place).")'";
	$sql .= " AND entity IN (".getEntity('invoice').")";

	$resql = $db->query($sql);
	$obj = $db->fetch_object($resql);
	if ($obj) {
		$facid = $obj->rowid;
	}
}
$object = new Facture($db);
$object->fetch($facid);
takeposReceiptEnforceInvoiceAccess($db, $user, $object, $langs->trans('TakeposHistoryAccessDenied'));
TakeposAudit::logEvent($db, $user, 'print_receipt', TakeposAudit::SEVERITY_INFO, array('invoice_id' => (int) $facid, 'gift' => (int) $gift), 'Receipt viewed/printed', 'invoice', (int) $facid);
$takeposDisplayCurrencyCode = takeposResolveDocumentCurrencyCode($conf, $object);
$takeposDisplayCurrencyRate = takeposResolveDocumentCurrencyRate($db, $conf, $object);

// ── e-Fatura أردني: إرسال تلقائي عند أول طباعة للفاتورة المدفوعة ──────────
if ($facid > 0
    && class_exists('TakeposEFaturaJo')
    && TakeposEFaturaJo::isEnabled()
    && !empty($object->id)
    && (int) $object->paye === 1
) {
    $efLog = TakeposEFaturaJo::getStatus($db, (int) $object->id);
    // أرسل فقط إن لم تُرسَل من قبل (أو كان الإرسال السابق خطأ)
    if (!$efLog || in_array($efLog['status'], array('pending', 'error'), true)) {
        $object->fetch_lines();
        TakeposEFaturaJo::submitInvoice($db, $object, $mysoc, $conf);
    }
}

// ── ZATCA سعودي: إرسال/توليد تلقائي عند أول طباعة ─────────────────────────
if ($facid > 0
    && class_exists('TakeposZatcaSA')
    && TakeposZatcaSA::isEnabled()
    && !empty($object->id)
    && (int) $object->paye === 1
) {
    $zatcaLog = TakeposZatcaSA::getStatus($db, (int) $object->id);
    if (!$zatcaLog || in_array($zatcaLog['status'], array('pending', 'error'), true)) {
        $object->fetch_lines();
        TakeposZatcaSA::submitInvoice($db, $object, $mysoc, $conf);
    }
}


// Record entry in blocked logs
// DOL_DOCUMENT_ROOT.'/blockedlog/ajax/block-add.php?id='.$object->id.'&element='.$object->element.'&action=DOC_PREVIEW&token='.newToken();
$blockedLogUrl = DOL_URL_ROOT.'/blockedlog/ajax/block-add.php';
$blockedLogPayload = http_build_query(array(
	'id' => (int) $object->id,
	'element' => (string) $object->element,
	'action' => 'DOC_PREVIEW',
	'token' => currentToken(),
));
print "
<script>
(function () {
	function takeposReceiptLogPreview() {
		try {
			var xhr = new XMLHttpRequest();
			xhr.open('POST', '".dol_escape_js($blockedLogUrl)."', true);
			xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
			xhr.send('".dol_escape_js($blockedLogPayload)."');
		} catch (e) {}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', takeposReceiptLogPreview);
	} else {
		takeposReceiptLogPreview();
	}
})();
</script>";


// Call to external receipt modules if exist
$parameters = array();
$hookmanager->initHooks(array('takeposfrontend'));
$reshook = $hookmanager->executeHooks('TakeposReceipt', $parameters, $object);
if (!empty($hookmanager->resPrint)) {
	print $hookmanager->resPrint;
	return;	// Receipt page can be called by the takepos/send.php page that use ob_start/end so we must use return and not exit to stop page
}

// === نظام الفوترة الأردني: فاتورة "فوترة" الكاملة بنمط الصفحة (A4) ===
// تَستبدل إيصال الكاشير الحراري الافتراضي عندما يكون البلد = الأردن والنمط = full.
if ($gift != 1
	&& !empty($object->id)
	&& function_exists('takeposUseJordanFullInvoice')
	&& takeposUseJordanFullInvoice($conf)
) {
	print '<body>';
	print takeposBuildJordanFullInvoice($object, $mysoc, $conf, $langs, $db);
	if ($facid) {
		print '<script type="text/javascript">window.print();</script>';
	}
	print '</body></html>';
	return;	// أوقف العرض الافتراضي (return وليس exit لأن send.php يستخدم ob_start/end)
}

// IMPORTANT: This file is sended to 'Takepos Printing' application. Keep basic file. No external files as css, js... If you need images use absolute path.
?>
<body>
<style>
.right {
	text-align: right;
}
.center {
	text-align: center;
}
.left {
	text-align: left;
}
.centpercent {
	width: 100%;
}
@media only screen and (min-width: 1024px)
{
	body {
		margin-left: 50px;
		margin-right: 50px;
	}
}
</style>
<center>
<div style="font-size: 1.5em">
<?php echo '<b>'.$mysoc->name.'</b>'; ?>
</div>
</center>
<br>
<?php
// === نظام الفوترة الإقليمي: رأس الفاتورة ===
if (function_exists('takeposBuildCountryReceiptHeader') && !empty($object->id)) {
	$countryHeader = takeposBuildCountryReceiptHeader($object, $mysoc, $conf, $langs);
	if ($countryHeader) {
		echo $countryHeader;
	}
}
?>
<p class="left">
<?php
$constFreeText = 'TAKEPOS_HEADER'.(empty($_SESSION['takeposterminal']) ? '0' : $_SESSION['takeposterminal']);
if (getDolGlobalString('TAKEPOS_HEADER') || getDolGlobalString($constFreeText)) {
	$newfreetext = '';
	$substitutionarray = getCommonSubstitutionArray($langs);
	complete_substitutions_array($substitutionarray, $langs, $object);
	if (getDolGlobalString('TAKEPOS_HEADER')) {
		$newfreetext .= make_substitutions(getDolGlobalString('TAKEPOS_HEADER'), $substitutionarray);
	}
	if (getDolGlobalString($constFreeText)) {
		$newfreetext .= make_substitutions(getDolGlobalString($constFreeText), $substitutionarray);
	}
	print nl2br($newfreetext);
}
?>
</p>
<p class="right">
<?php
print $langs->trans('Date')." ".dol_print_date($object->date, 'day').'<br>';
if (getDolGlobalString('TAKEPOS_RECEIPT_NAME')) {
	print getDolGlobalString('TAKEPOS_RECEIPT_NAME') . " ";
}
if ($object->status == Facture::STATUS_DRAFT) {
	print str_replace(")", "", str_replace("-", " ".$langs->trans('Place')." ", str_replace("(PROV-POS", $langs->trans("Terminal")." ", $object->ref)));
} else {
	print $object->ref;
}
if (getDolGlobalString('TAKEPOS_SHOW_CUSTOMER')) {
	$defaultThirdPartyId = takeposResolveTerminalThirdPartyId($_SESSION["takeposterminal"]);
	if ($object->socid != $defaultThirdPartyId) {
		$soc = new Societe($db);
		if ($object->socid > 0) {
			$soc->fetch($object->socid);
		} elseif ($defaultThirdPartyId > 0) {
			$soc->fetch($defaultThirdPartyId);
		}
		print "<br>".$langs->trans("Customer").': '.$soc->name;
	}
}
if (getDolGlobalString('TAKEPOS_SHOW_DATE_OF_PRINING')) {
	print "<br>".$langs->trans("DateOfPrinting").': '.dol_print_date(dol_now(), 'dayhour', 'tzuserrel').'<br>';
}
?>
</p>
<br>

<table class="centpercent" style="border-top-style: double;">
	<thead>
	<tr>
		<th class="center"><?php print $langs->trans("Label"); ?></th>
		<th class="right"><?php print $langs->trans("Qty"); ?></th>
		<th class="right"><?php if ($gift != 1) {
			print $langs->trans("Price");
						  } ?></th>
		<?php  if (getDolGlobalString('TAKEPOS_SHOW_HT_RECEIPT')) { ?>
		<th class="right"><?php if ($gift != 1) {
			print $langs->trans("TotalHT");
						  } ?></th>
		<?php } ?>
		<th class="right"><?php if ($gift != 1) {
			print $langs->trans("TotalTTC");
						  } ?></th>
	</tr>
	</thead>
	<tbody>
	<?php
	if ($action == 'without_details') {
		$qty = GETPOSTINT('qty') > 0 ? GETPOSTINT('qty') : 1;
		print '<tr>';
		print '<td>' . GETPOST('label', 'alphanohtml') . '</td>';
		print '<td class="right">' . $qty . '</td>';
		print '<td class="right">' . price(price2num($object->total_ttc / $qty, 'MU'), 1) . '</td>';
		if (getDolGlobalString('TAKEPOS_SHOW_HT_RECEIPT')) {
			print '<td class="right">' . price($object->total_ht, 1) . '</td>';
		}
		print '<td class="right">' . price($object->total_ttc, 1) . '</td>';
		print '</tr>';
	} else {
		foreach ($object->lines as $line) {
			?>
		<tr>
			<td>
			<?php if (!empty($line->product_label)) {
				echo $line->product_label;
			} else {
				echo $line->desc;
			} ?>
			</td>
			<td class="right"><?php echo $line->qty; ?></td>
			<td class="right"><?php if ($gift != 1) {
				echo price(price2num($line->total_ttc / $line->qty, 'MT'), 1);
							  } ?></td>
			<?php
			if (getDolGlobalString('TAKEPOS_SHOW_HT_RECEIPT')) { ?>
						<td class="right"><?php if ($gift != 1) {
							echo price($line->total_ht, 1);
										  } ?></td>
				<?php
			} ?>
			<td class="right"><?php if ($gift != 1) {
				echo price($line->total_ttc, 1);
							  } ?></td>
		</tr>
			<?php
		}
	}
	?>
	</tbody>
</table>
<br>
<table class="right centpercent">
<tr>
	<th class="right"><?php if ($gift != 1) {
		echo $langs->trans("TotalHT");
					  } ?></th>
	<td class="right"><?php if ($gift != 1) {
		echo price($object->total_ht, 1, '', 1, - 1, - 1, $conf->currency)."\n";
					  } ?></td>
</tr>
<?php if (getDolGlobalString('TAKEPOS_TICKET_VAT_GROUPPED')) {
	$vat_groups = array();
	foreach ($object->lines as $line) {
		if (!array_key_exists($line->tva_tx, $vat_groups)) {
			$vat_groups[$line->tva_tx] = 0;
		}
		$vat_groups[$line->tva_tx] += $line->total_tva;
	}
	// Loop on each VAT group
	foreach ($vat_groups as $key => $val) {
		?>
	<tr>
		<th align="right"><?php if ($gift != 1) {
			echo $langs->trans("VAT").' '.vatrate($key, true);
						  } ?></th>
		<td align="right"><?php if ($gift != 1) {
			echo price($val, 1, '', 1, - 1, - 1, $conf->currency)."\n";
						  } ?></td>
	</tr>
		<?php
	}
} else { ?>
<tr>
	<th class="right"><?php if ($gift != 1) {
		echo $langs->trans("TotalVAT").'</th><td class="right">'.price($object->total_tva, 1, '', 1, - 1, - 1, $conf->currency)."\n";
					  } ?></th>
</tr>
<?php }

// Now show local taxes if company uses them

if (price2num($object->total_localtax1, 'MU') || $mysoc->useLocalTax(1)) { ?>
<tr>
	<th class="right"><?php if ($gift != 1) {
		echo ''.$langs->trans("TotalLT1").'</th><td class="right">'.price($object->total_localtax1, 1, '', 1, - 1, - 1, $conf->currency)."\n";
					  } ?></th>
</tr>
<?php } ?>
<?php if (price2num($object->total_localtax2, 'MU') || $mysoc->useLocalTax(2)) { ?>
<tr>
	<th class="right"><?php if ($gift != 1) {
		echo ''.$langs->trans("TotalLT2").'</th><td class="right">'.price($object->total_localtax2, 1, '', 1, - 1, - 1, $conf->currency)."\n";
					  } ?></th>
</tr>
<?php } ?>
<tr>
	<th class="right"><?php if ($gift != 1) {
		echo ''.$langs->trans("TotalTTC").'</th><td class="right">'.price($object->total_ttc, 1, '', 1, - 1, - 1, $conf->currency)."\n";
					  } ?></th>
</tr>
<?php
if ($takeposDisplayCurrencyCode !== '' && $takeposDisplayCurrencyRate > 0) {
	echo '<tr><th class="right">';
	if ($gift != 1) {
		echo ''.$langs->trans("TotalTTC").' '.$takeposDisplayCurrencyCode.'</th><td class="right">'.price($object->total_ttc * $takeposDisplayCurrencyRate, 1, '', 1, - 1, - 1, $takeposDisplayCurrencyCode)."\n";
	}
	echo '</td></tr>';
}

if (getDolGlobalString('TAKEPOS_PRINT_PAYMENT_METHOD')) {
	if (empty($facid)) {
		// Case of specimen
		echo '<tr>';
		echo '<td class="right">';
		echo $langs->transnoentitiesnoconv("PaymentTypeShortLIQ");
		echo '</td>';
		echo '<td class="right">';
		$amount_payment = 0;
		echo price($amount_payment, 1, '', 1, - 1, - 1, $conf->currency);
		echo '</td>';
		echo '</tr>';
	} else {
		$sql = "SELECT p.pos_change as pos_change, p.datep as date, p.fk_paiement, p.num_paiement as num,";
		$sql .= " f.multicurrency_code,";
		$sql .= " pf.amount as amount, pf.multicurrency_amount,";
		$sql .= " cp.code";
		$sql .= " FROM ".MAIN_DB_PREFIX."paiement_facture as pf, ".MAIN_DB_PREFIX."facture as f, ".MAIN_DB_PREFIX."paiement as p";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_paiement as cp ON p.fk_paiement = cp.id";
		$sql .= " WHERE pf.fk_facture = f.rowid AND pf.fk_paiement = p.rowid AND pf.fk_facture = ".((int) $facid);
		$sql .= " ORDER BY p.datep";

		$resql = $db->query($sql);
		if ($resql) {
			$num = $db->num_rows($resql);

			$i = 0;
			while ($i < $num) {
				$row = $db->fetch_object($resql);

				echo '<tr>';
				echo '<td class="right">';
				echo $langs->transnoentitiesnoconv("PaymentTypeShort".$row->code);
				echo '</td>';
				echo '<td class="right">';
				$amount_payment = (isModEnabled('multicurrency') && $object->multicurrency_tx != 1) ? $row->multicurrency_amount : $row->amount;
				//print "xx ".$row->multicurrency_amount." - ".$row->amount." - ".$amount_payment." - ".$object->multicurrency_tx;
				if ((!isModEnabled('multicurrency') || $object->multicurrency_tx == 1) && $row->code == "LIQ" && $row->pos_change > 0) {
					$amount_payment += $row->pos_change; // Show amount with excess received if it's cash payment
					$currency = $conf->currency;
				} else {
					// We do not show change if payment into a different currency because not yet supported
					$currency = $row->multicurrency_code;
				}
				echo price($amount_payment, 1, '', 1, - 1, - 1, $currency);
				echo '</td>';
				echo '</tr>';
				if ((!isModEnabled('multicurrency') || $object->multicurrency_tx == 1) && $row->code == "LIQ" && $row->pos_change > 0) {
					echo '<tr>';
					echo '<td class="right">';
					echo $langs->trans("Change");	// ChangeBack ?
					echo '</td>';
					echo '<td class="right">';
					echo price($row->pos_change, 1, '', 1, - 1, - 1, $currency);
					echo '</td>';
					echo '</tr>';
				}
				$i++;
			}
		}
	}
}
?>
</table>
<div style="border-top-style: double;">
<br>
<br>
<br>
<?php
$constFreeText = 'TAKEPOS_FOOTER'.(empty($_SESSION['takeposterminal']) ? '0' : $_SESSION['takeposterminal']);
if (getDolGlobalString('TAKEPOS_FOOTER') || getDolGlobalString($constFreeText)) {
	$newfreetext = '';
	$substitutionarray = getCommonSubstitutionArray($langs);
	complete_substitutions_array($substitutionarray, $langs, $object);
	if (getDolGlobalString($constFreeText)) {
		$newfreetext .= make_substitutions(getDolGlobalString($constFreeText), $substitutionarray);
	}
	if (getDolGlobalString('TAKEPOS_FOOTER')) {
		$newfreetext .= make_substitutions(getDolGlobalString('TAKEPOS_FOOTER'), $substitutionarray);
	}
	print $newfreetext;
}
?>

<?php
// === نظام الفوترة الإقليمي: ذيل الفاتورة مع QR Code ===
if (function_exists('takeposBuildCountryReceiptFooter') && !empty($object->id)) {
	$countryFooter = takeposBuildCountryReceiptFooter($object, $mysoc, $conf, $langs);
	if ($countryFooter) {
		echo $countryFooter;
	}
}
// === بادج حالة e-Fatura ===
if (class_exists('TakeposEFaturaJo') && TakeposEFaturaJo::isEnabled() && !empty($object->id)) {
	echo TakeposEFaturaJo::renderStatusBadge($db, (int) $object->id);
}
// === بادج حالة ZATCA ===
if (class_exists('TakeposZatcaSA') && TakeposZatcaSA::isEnabled() && !empty($object->id)) {
	echo TakeposZatcaSA::renderStatusBadge($db, (int) $object->id);
}
?>

<script type="text/javascript">
	<?php
	if ($facid) {
		print 'window.print();';
	} //Avoid print when is specimen
	?>
</script>

</body>
</html>
