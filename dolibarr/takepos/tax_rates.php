<?php
/**
 * Simple VAT/tax rate manager for TakePOS product entry screens.
 */
require '../main.inc.php';

require_once __DIR__ . '/lib/takepos_lang.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/class/TakeposAccess.class.php';
require_once __DIR__ . '/class/TakeposInputValidator.class.php';

$langs->loadLangs(array('main', 'admin', 'products', 'bills', 'takeposcustom@takepos'));

$sessionTerminalToken = isset($_SESSION['takeposterminal']) ? (int) $_SESSION['takeposterminal'] : 1;
$entity = !empty($user->entity) ? (int) $user->entity : 1;
$pageUrl = DOL_URL_ROOT . '/takepos/tax_rates.php';

TakeposAccess::requireFrontendAccess(
    $db,
    $user,
    'takepos.catalog.manage_products',
    'takepos.use',
    $sessionTerminalToken,
    $langs->trans('TakeposTaxRatesAccessDenied'),
    array('page' => 'tax_rates.php')
);

$canWrite = (!empty($user->admin) || $user->hasRight('produit', 'creer') || $user->hasRight('service', 'creer'));
if (!$canWrite) {
    accessforbidden($langs->trans('TakeposTaxRatesAccessDenied'));
}

function takeposTaxColumnExists($db, $column)
{
    $table = MAIN_DB_PREFIX . 'c_tva';
    $resql = $db->query("SHOW COLUMNS FROM " . $table . " LIKE '" . $db->escape($column) . "'");
    return ($resql && $db->num_rows($resql) > 0);
}

function takeposTaxRateExists($db, $entity, $rate, $countryCode = '')
{
    $table = MAIN_DB_PREFIX . 'c_tva';
    $sql = "SELECT rowid FROM " . $table . " WHERE taux = " . price2num($rate, 'MU');
    if (takeposTaxColumnExists($db, 'entity')) {
        $sql .= " AND entity IN (0," . ((int) $entity) . ")";
    }
    if ($countryCode !== '' && takeposTaxColumnExists($db, 'countrycode')) {
        $sql .= " AND (countrycode = '" . $db->escape($countryCode) . "' OR countrycode IS NULL OR countrycode = '')";
    }
    $sql .= " LIMIT 1";
    $resql = $db->query($sql);
    if ($resql && ($obj = $db->fetch_object($resql))) {
        return (int) $obj->rowid;
    }
    return 0;
}

function takeposBuildTaxCode($rate)
{
    $text = preg_replace('/[^0-9]+/', '_', (string) price2num($rate, 'MU'));
    $text = trim((string) $text, '_');
    if ($text === '') {
        $text = '0';
    }
    return 'TAKEPOS_' . $text;
}

$action = GETPOST('action', 'aZ09');
$message = '';
$messageType = 'mesgs';
$countryCode = '';
if (is_object($mysoc) && !empty($mysoc->country_code)) {
    $countryCode = (string) $mysoc->country_code;
}

try {
    if ($action !== '' && GETPOST('token', 'alpha') !== (isset($_SESSION['newtoken']) ? (string) $_SESSION['newtoken'] : '')) {
        throw new Exception($langs->trans('TakeposExpenseInvalidSecurityToken'));
    }

    if ($action === 'add_tax') {
        $rateRaw = GETPOST('tax_rate', 'none');
        $rate = 0.0;
        if (class_exists('TakeposInputValidator') && TakeposInputValidator::parseDecimal($rateRaw, $parsedRate, true, 8)) {
            $rate = (float) $parsedRate;
        } else {
            $rate = (float) price2num((string) $rateRaw, 'MU');
        }
        if ($rate < 0 || $rate > 100) {
            throw new Exception($langs->trans('TakeposTaxRatesInvalidRate'));
        }

        $existing = takeposTaxRateExists($db, $entity, $rate, $countryCode);
        if ($existing > 0) {
            $message = $langs->trans('TakeposTaxRatesAlreadyExists');
        } else {
            $columns = array();
            $values = array();
            $fieldValues = array(
                'entity' => (string) $entity,
                'code' => "'" . $db->escape(takeposBuildTaxCode($rate)) . "'",
                'libelle' => "'" . $db->escape($langs->trans('TakeposTaxRatesVatLabel') . ' ' . price2num($rate, 'MU') . '%') . "'",
                'taux' => price2num($rate, 'MU'),
                'localtax1' => '0',
                'localtax2' => '0',
                'localtax1_type' => "''",
                'localtax2_type' => "''",
                'revenuestamp' => '0',
                'deductible' => '1',
                'recuperableonly' => '0',
                'note' => "'" . $db->escape('Added from TakePOS tax manager') . "'",
                'active' => '1',
                'countrycode' => ($countryCode !== '' ? "'" . $db->escape($countryCode) . "'" : 'NULL'),
            );
            foreach ($fieldValues as $column => $value) {
                if (takeposTaxColumnExists($db, $column)) {
                    $columns[] = $column;
                    $values[] = $value;
                }
            }
            if (!in_array('taux', $columns, true)) {
                throw new Exception($langs->trans('TakeposTaxRatesMissingTable'));
            }

            $sql = "INSERT INTO " . MAIN_DB_PREFIX . "c_tva (" . implode(',', $columns) . ") VALUES (" . implode(',', $values) . ")";
            if (!$db->query($sql)) {
                throw new Exception($db->lasterror());
            }
            $message = $langs->trans('TakeposTaxRatesSaved');
        }
    }
} catch (Throwable $e) {
    $message = $e->getMessage();
    $messageType = 'errors';
}

$rows = array();
$sql = "SELECT * FROM " . MAIN_DB_PREFIX . "c_tva";
$where = array();
if (takeposTaxColumnExists($db, 'entity')) {
    $where[] = 'entity IN (0,' . $entity . ')';
}
if (takeposTaxColumnExists($db, 'countrycode') && $countryCode !== '') {
    $where[] = "(countrycode = '" . $db->escape($countryCode) . "' OR countrycode IS NULL OR countrycode = '')";
}
if (takeposTaxColumnExists($db, 'active')) {
    $where[] = 'active = 1';
}
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY taux ASC';
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $rows[] = $obj;
    }
}

llxHeader('', $langs->trans('TakeposTaxRatesTitle'));
print load_fiche_titre($langs->trans('TakeposTaxRatesTitle'), '', 'title_setup');

print '<div class="tabsAction">';
print '<a class="butAction" href="' . dol_escape_htmltag(DOL_URL_ROOT . '/takepos/index.php') . '">' . dol_escape_htmltag($langs->trans('TakeposCommonBackToPos')) . '</a>';
print '<a class="butAction" href="' . dol_escape_htmltag(DOL_URL_ROOT . '/takepos/workspace.php?key=manage_products') . '">' . dol_escape_htmltag($langs->trans('TakeposShortcutManageProducts')) . '</a>';
print '</div>';

if ($message !== '') {
    setEventMessages($message, null, $messageType);
}

print '<form method="POST" action="' . dol_escape_htmltag($pageUrl) . '">';
print '<input type="hidden" name="token" value="' . dol_escape_htmltag(newToken()) . '">';
print '<input type="hidden" name="action" value="add_tax">';
print '<table class="border centpercent">';
print '<tr class="liste_titre"><th colspan="2">' . dol_escape_htmltag($langs->trans('TakeposTaxRatesAddNew')) . '</th></tr>';
print '<tr><td class="titlefield">' . dol_escape_htmltag($langs->trans('TakeposTaxRatesRate')) . '</td><td><input type="text" name="tax_rate" class="width100" inputmode="decimal" placeholder="16"> %</td></tr>';
print '<tr><td></td><td><input type="submit" class="button button-save" value="' . dol_escape_htmltag($langs->trans('Add')) . '"></td></tr>';
print '</table>';
print '</form>';

print '<br>';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>' . dol_escape_htmltag($langs->trans('Code')) . '</th><th>' . dol_escape_htmltag($langs->trans('Label')) . '</th><th class="right">' . dol_escape_htmltag($langs->trans('TakeposTaxRatesRate')) . '</th><th>' . dol_escape_htmltag($langs->trans('Status')) . '</th></tr>';
if (empty($rows)) {
    print '<tr class="oddeven"><td colspan="4">' . dol_escape_htmltag($langs->trans('NoRecordFound')) . '</td></tr>';
}
foreach ($rows as $row) {
    $code = isset($row->code) ? (string) $row->code : '';
    $label = isset($row->libelle) ? (string) $row->libelle : (isset($row->note) ? (string) $row->note : '');
    $active = (!isset($row->active) || (int) $row->active === 1) ? $langs->trans('Enabled') : $langs->trans('Disabled');
    print '<tr class="oddeven">';
    print '<td>' . dol_escape_htmltag($code) . '</td>';
    print '<td>' . dol_escape_htmltag($label) . '</td>';
    print '<td class="right">' . dol_escape_htmltag(price((float) $row->taux)) . ' %</td>';
    print '<td>' . dol_escape_htmltag($active) . '</td>';
    print '</tr>';
}
print '</table>';
print '</div>';

llxFooter();
$db->close();
