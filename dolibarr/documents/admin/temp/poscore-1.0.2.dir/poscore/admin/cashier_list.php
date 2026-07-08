<?php
$res = @include '../../../main.inc.php';
if (!$res) $res = @include '../../../../main.inc.php';
if (!$res) die('main.inc.php not found');
require_once DOL_DOCUMENT_ROOT.'/custom/poscore/class/service/PosAccessGuard.php';

$langs->load("poscore@poscore");

$guard = new PosAccessGuard($db, $user, $conf);
$guard->requirePermission("create_cashier");

llxHeader("", "Cashiers");

print load_fiche_titre("POS Cashiers");
print '<div class="info">Cashier management screen placeholder. Integrate with your cashier master table here.</div>';

llxFooter();
$db->close();
