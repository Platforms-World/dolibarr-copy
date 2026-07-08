<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/poscore/class/service/PosAccessGuard.php';

$langs->load("poscore@poscore");

$guard=new PosAccessGuard($db,$user,$conf);
$guard->requirePermission("create_terminal");

llxHeader("","Terminals");

print load_fiche_titre("POS Terminals");

$sql="SELECT rowid,ref,label,status FROM ".MAIN_DB_PREFIX."pos_terminal WHERE entity=".$conf->entity;
$res=$db->query($sql);

print "<table class='border'>";
print "<tr><th>ID</th><th>Ref</th><th>Label</th><th>Status</th></tr>";

while($obj=$db->fetch_object($res)){
print "<tr>";
print "<td>".$obj->rowid."</td>";
print "<td>".$obj->ref."</td>";
print "<td>".$obj->label."</td>";
print "<td>".$obj->status."</td>";
print "</tr>";
}

print "</table>";

llxFooter();
