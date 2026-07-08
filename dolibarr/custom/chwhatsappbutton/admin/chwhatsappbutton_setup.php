<?php
/**
 * ChWhatsAppButton setup page
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
    $res = @include "../../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

// Load translation files
$langs->loadLangs(array("admin", "chwhatsappbutton@chwhatsappbutton"));

// Access control
if (!$user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$value = GETPOST('value', 'alpha');

/*
 * Actions
 */

if ($action == 'setvalue' && $user->admin) {
    $const = GETPOST('const', 'alpha');
    $value = GETPOST('value', 'alpha');
    
    if (dolibarr_set_const($db, $const, $value, 'chaine', 0, '', $conf->entity) > 0) {
        setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
    } else {
        setEventMessages($langs->trans("Error"), null, 'errors');
    }
}

/*
 * View
 */

$form = new Form($db);

$page_name = "ChWhatsAppButtonSetup";
llxHeader('', $langs->trans($page_name));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

print '<div class="fichecenter">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print '</tr>';

// Example configuration option
print '<tr class="oddeven">';
print '<td>'.$langs->trans("EnableWhatsAppButton").'</td>';
print '<td>';
if (!empty($conf->global->CHWHATSAPPBUTTON_ENABLE)) {
    print '<span class="badge badge-status4 badge-status">'.$langs->trans("Enabled").'</span>';
} else {
    print '<span class="badge badge-status8 badge-status">'.$langs->trans("Disabled").'</span>';
}
print '</td>';
print '</tr>';

print '</table>';
print '</div>';

print '<br>';

// Information section
print '<div class="info">';
print '<h3>'.$langs->trans("Information").'</h3>';
print '<p>'.$langs->trans("HelpWhatsAppButton").'</p>';
print '<br>';
print '<h4>'.$langs->trans("HowToUse").'</h4>';
print '<ol>';
print '<li>'.$langs->trans("ConfigureTemplates").' (<a href="'.DOL_URL_ROOT.'/custom/chwhatsappbutton/templateslist.php">'.$langs->trans("WhatsAppTemplates").'</a>)</li>';
print '<li>'.$langs->trans("EnsurePhoneNumbers").'</li>';
print '<li>'.$langs->trans("UseButtonsInCards").'</li>';
print '</ol>';
print '<br>';
print '<h4>'.$langs->trans("Requirements").'</h4>';
print '<ul>';
print '<li>'.$langs->trans("WhatsAppWebOrDesktop").'</li>';
print '<li>'.$langs->trans("PhoneNumberInThirdparty").'</li>';
print '<li>'.$langs->trans("ActiveTemplates").'</li>';
print '</ul>';
print '</div>';

// End of page
llxFooter();
$db->close();
