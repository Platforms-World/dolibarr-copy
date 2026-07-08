<?php
$res = @include '../../../main.inc.php';
if (!$res) $res = @include '../../../../main.inc.php';
if (!$res) die('main.inc.php not found');
require_once DOL_DOCUMENT_ROOT.'/custom/poscore/class/service/PosAccessGuard.php';

$langs->load('poscore@poscore');

$guard = new PosAccessGuard($db, $user, $conf);
$guard->requirePermission('view_pos_dashboard');

llxHeader('', 'POS Dashboard');

print load_fiche_titre('POS Dashboard');
print '<div class="opacitymedium">POS module installed and entitlement checks passed successfully.</div>';
print '<br>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>Check</th><th>Status</th></tr>';
print '<tr class="oddeven"><td>Tenant module entitlement</td><td>'.($guard->checkModule() ? 'Enabled' : 'Disabled').'</td></tr>';
print '<tr class="oddeven"><td>User dashboard permission</td><td>'.($guard->checkPermission('view_pos_dashboard') ? 'Granted' : 'Denied').'</td></tr>';
print '<tr class="oddeven"><td>Shift feature</td><td>'.($guard->canUseFeature('shift_management') ? 'Enabled' : 'Disabled').'</td></tr>';
print '</table>';

llxFooter();
$db->close();
