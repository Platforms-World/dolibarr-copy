<?php
$res = @include '../../../main.inc.php';
if (!$res) $res = @include '../../../../main.inc.php';
if (!$res) die('main.inc.php not found');
require_once DOL_DOCUMENT_ROOT.'/custom/poscore/class/service/PosAccessGuard.php';

$langs->load('poscore@poscore');

$guard = new PosAccessGuard($db, $user, $conf);
$guard->requirePermission('create_terminal');

llxHeader('', 'Terminals');

print load_fiche_titre('POS Terminals');

$sql = 'SELECT rowid, ref, label, status FROM '.MAIN_DB_PREFIX.'pos_terminal WHERE entity = '.((int) $conf->entity).' ORDER BY rowid DESC';
$resql = $db->query($sql);

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>ID</th><th>Ref</th><th>Label</th><th>Status</th></tr>';

if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        print '<tr class="oddeven">';
        print '<td>'.((int) $obj->rowid).'</td>';
        print '<td>'.dol_escape_htmltag($obj->ref).'</td>';
        print '<td>'.dol_escape_htmltag($obj->label).'</td>';
        print '<td>'.((int) $obj->status).'</td>';
        print '</tr>';
    }
}

print '</table>';

llxFooter();
$db->close();
