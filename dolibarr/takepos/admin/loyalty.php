<?php
/**
 * Admin loyalty settings page.
 */
require '../../main.inc.php';
require_once __DIR__ . '/../lib/takepos_help.php';

require_once __DIR__ . '/../lib/takepos_lang.php';
takeposApplyForcedLanguage($langs, isset($user) ? $user : null);
require_once __DIR__ . '/../class/TakeposAccess.class.php';
require_once __DIR__ . '/../class/TakeposLoyaltyService.class.php';

if (empty($user->admin)) {
    accessforbidden();
}

TakeposAccess::requireAdminAccess($db, $user, 'takepos.loyalty', 'takepos.loyalty.adjust', null, $langs->trans('TakeposAdminLoyaltySettingsAccessDenied'));

$langs->loadLangs(array('admin', 'cashdesk', 'main', 'takeposcustom@takepos'));

$action = GETPOST('action', 'aZ09');
if ($action === 'save' && GETPOST('token', 'alpha') === $_SESSION['newtoken']) {
    try {
        TakeposLoyaltyService::saveSettings($db, $user, GETPOST('points_per_currency', 'none'), GETPOST('redeem_points_per_currency', 'none'));
        setEventMessage($langs->trans('TakeposAdminLoyaltySettingsUpdated'));
    } catch (Throwable $e) {
        setEventMessages($e->getMessage(), null, 'errors');
    }
}

$settings = TakeposLoyaltyService::settings();

llxHeader('', $langs->trans('TakeposShortcutLoyaltySettings'));
print load_fiche_titre($langs->trans('TakeposShortcutLoyaltySettings'), '', 'title_setup');

print '<form method="post" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="save">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>' . dol_escape_htmltag($langs->trans('TakeposAdminLoyaltyParameter')) . '</td><td>' . dol_escape_htmltag($langs->trans('TakeposAdminLoyaltyValue')) . '</td></tr>';
print '<tr class="oddeven"><td>' . dol_escape_htmltag($langs->trans('TakeposAdminLoyaltyPointsPerCurrency')) . '</td><td><input type="number" step="0.000001" min="0.000001" name="points_per_currency" value="' . dol_escape_htmltag((string) $settings['points_per_currency']) . '"></td></tr>';
print '<tr class="oddeven"><td>' . dol_escape_htmltag($langs->trans('TakeposAdminLoyaltyRedeemPointsPerCurrency')) . '</td><td><input type="number" step="0.000001" min="0.000001" name="redeem_points_per_currency" value="' . dol_escape_htmltag((string) $settings['redeem_points_per_currency']) . '"></td></tr>';
print '</table>';
print '</div>';
print '<div class="tabsAction"><input type="submit" class="button button-save" value="' . dol_escape_htmltag($langs->trans('TakeposCommonSave')) . '"></div>';
print '</form>';

print takeposHelpRender($langs, __FILE__);

llxFooter();
$db->close();
