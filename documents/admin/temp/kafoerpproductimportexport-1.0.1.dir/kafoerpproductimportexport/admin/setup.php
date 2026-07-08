<?php
/* Copyright (C) 2026 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'admin.lib.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'kafoerpproductimportexport.lib.php';

$langs->loadLangs(array('admin', 'kafoerpproductimportexport@kafoerpproductimportexport'));

if (!$user->admin) {
    accessforbidden();
}

llxHeader('', $langs->trans('KafoERPImportExportProductSetup'));

print load_fiche_titre($langs->trans('KafoERPImportExportProductSetup'), '', 'title_setup');
$head = kafoerpproductimportexportAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans('Module106420Name'), -1, 'barcode');

print '<div class="fichecenter">';
print '<div class="opacitymedium">' . $langs->trans('KafoERPImportExportProductSetupHelp') . '</div>';
print '<br>';
print '<ul>';
print '<li>' . $langs->trans('KafoERPImportExportProductRightRead') . '</li>';
print '<li>' . $langs->trans('KafoERPImportExportProductRightImport') . '</li>';
print '<li>' . $langs->trans('KafoERPImportExportProductRightExport') . '</li>';
print '</ul>';
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
