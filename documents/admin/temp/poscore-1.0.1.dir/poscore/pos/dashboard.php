<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/poscore/class/service/PosAccessGuard.php';

$langs->load("poscore@poscore");

$guard=new PosAccessGuard($db,$user,$conf);
$guard->requirePermission("view_pos_dashboard");

llxHeader("","POS Dashboard");

print load_fiche_titre("POS Dashboard");

print "<p>POS module installed successfully.</p>";

llxFooter();
$db->close();
