<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/custom/poscore/lib/poscore_bootstrap.php';
require_once DOL_DOCUMENT_ROOT . '/custom/poscore/class/pos_setting.class.php';

$guard = poscore_bootstrap_page('manage_pos_settings', 'poscore->admin');

$entity = (int) $conf->entity;
$action = GETPOST('action', 'aZ09');
$settings = new PosSetting($db);

$defaultKeys = array(
    'DEFAULT_TERMINAL_ID' => 'Default terminal rowid for POS operations',
    'DEFAULT_RECEIPT_PROFILE_ID' => 'Default receipt profile rowid',
    'POS_ALLOW_NEGATIVE_STOCK_FALLBACK' => '0 or 1 fallback flag; final behavior still controlled by saascore',
    'POS_DEFAULT_QUICK_SALE_MODE' => '0 or 1 preferred UI default; final behavior still controlled by saascore',
);

if ($action === 'save' && GETPOST('token') === $_SESSION['newtoken']) {
    foreach ($defaultKeys as $code => $description) {
        $settings->upsert($entity, $code, GETPOST($code, 'restricthtml'), $description);
    }
    setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$current = $settings->getAllByEntity($entity);

llxHeader('', $langs->trans('POSSettings'));

$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">' . $langs->trans('BackToModuleList') . '</a>';
print load_fiche_titre($langs->trans('POSSettings'), $linkback, 'title_setup');

$head = poscoreAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans('Module105500Name'), -1, 'cash-register');

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="save">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>' . $langs->trans('Code') . '</th><th>' . $langs->trans('Value') . '</th><th>' . $langs->trans('Description') . '</th></tr>';

foreach ($defaultKeys as $code => $description) {
    $val = isset($current[$code]) ? $current[$code]['value'] : '';
    print '<tr>';
    print '<td>' . dol_escape_htmltag($code) . '</td>';
    print '<td><input type="text" class="flat minwidth300" name="' . dol_escape_htmltag($code) . '" value="' . dol_escape_htmltag($val) . '"></td>';
    print '<td>' . dol_escape_htmltag($description) . '</td>';
    print '</tr>';
}

print '</table>';
print '<div class="tabsAction">';
print '<input type="submit" class="button button-save" value="' . $langs->trans('Save') . '">';
print '</div>';
print '</form>';

print '<div class="opacitymedium">SaaS access is enforced at runtime by saascore. These values are operational defaults only.</div>';

print dol_get_fiche_end();
llxFooter();
$db->close();
