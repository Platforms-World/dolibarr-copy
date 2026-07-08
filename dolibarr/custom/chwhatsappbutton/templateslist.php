<?php
/**
 * Page to list WhatsApp templates
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../main.inc.php")) {
    $res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/chwhatsappbutton/class/chwhatsapptemplate.class.php');

// Load translation files
$langs->loadLangs(array("chwhatsappbutton@chwhatsappbutton"));

// Access control
if (!$user->rights->chwhatsappbutton->read && !$user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$massaction = GETPOST('massaction', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$toselect = GETPOST('toselect', 'array');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'templateslist';

$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
if (empty($page) || $page == -1) {
    $page = 0;
}
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (!$sortfield) {
    $sortfield = "t.position,t.label";
}
if (!$sortorder) {
    $sortorder = "ASC";
}

// Initialize technical objects
$object = new ChWhatsAppTemplate($db);
$hookmanager->initHooks(array('templateslist'));

/*
 * Actions
 */

if (GETPOST('cancel', 'alpha')) {
    $action = 'list';
    $massaction = '';
}
if (!GETPOST('confirmmassaction', 'alpha') && $massaction != 'presend' && $massaction != 'confirm_presend') {
    $massaction = '';
}

// Delete action
if ($action == 'confirm_delete' && $confirm == 'yes') {
    $object->fetch(GETPOST('id', 'int'));
    $result = $object->delete($user);
    if ($result > 0) {
        setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
        header("Location: ".$_SERVER["PHP_SELF"]);
        exit;
    } else {
        setEventMessages($object->error, $object->errors, 'errors');
    }
}

/*
 * View
 */

$form = new Form($db);

$title = $langs->trans("WhatsAppTemplates");
$help_url = '';

llxHeader('', $title, $help_url);

// Build and execute select
$sql = "SELECT";
$sql .= " t.rowid,";
$sql .= " t.ref,";
$sql .= " t.label,";
$sql .= " t.entity_type,";
$sql .= " t.is_active,";
$sql .= " t.is_default,";
$sql .= " t.position";
$sql .= " FROM ".MAIN_DB_PREFIX."chwhatsapp_templates as t";
$sql .= " WHERE 1 = 1";

// Count total nb of records
$nbtotalofrecords = '';
if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST)) {
    $resql = $db->query($sql);
    $nbtotalofrecords = $db->num_rows($resql);
    if (($page * $limit) > $nbtotalofrecords) {
        $page = 0;
        $offset = 0;
    }
}

$sql .= $db->order($sortfield, $sortorder);
if ($limit) {
    $sql .= $db->plimit($limit + 1, $offset);
}

$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);

    $arrayofselected = is_array($toselect) ? $toselect : array();

    $param = '';
    if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) {
        $param .= '&contextpage='.urlencode($contextpage);
    }
    if ($limit > 0 && $limit != $conf->liste_limit) {
        $param .= '&limit='.urlencode($limit);
    }

    $newcardbutton = '';
    if ($user->rights->chwhatsappbutton->write) {
        $newcardbutton = dolGetButtonTitle($langs->trans('NewTemplate'), '', 'fa fa-plus-circle', DOL_URL_ROOT.'/custom/chwhatsappbutton/templatecard.php?action=create');
    }

    print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">';
    if ($optioncss != '') {
        print '<input type="hidden" name="optioncss" value="'.$optioncss.'">';
    }
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
    print '<input type="hidden" name="action" value="list">';
    print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
    print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
    print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';

    print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $nbtotalofrecords, 'object_chwhatsappbutton', 0, $newcardbutton, '', $limit, 0, 0, 1);

    print '<div class="div-table-responsive">';
    print '<table class="tagtable nobottomiftotal liste">';

    // Fields title
    print '<tr class="liste_titre">';
    print_liste_field_titre("Ref", $_SERVER["PHP_SELF"], "t.ref", "", $param, '', $sortfield, $sortorder);
    print_liste_field_titre("Label", $_SERVER["PHP_SELF"], "t.label", "", $param, '', $sortfield, $sortorder);
    print_liste_field_titre("EntityType", $_SERVER["PHP_SELF"], "t.entity_type", "", $param, '', $sortfield, $sortorder);
    print_liste_field_titre("Status", $_SERVER["PHP_SELF"], "t.is_active", "", $param, '', $sortfield, $sortorder, 'center ');
    print_liste_field_titre("Default", $_SERVER["PHP_SELF"], "t.is_default", "", $param, '', $sortfield, $sortorder, 'center ');
    print_liste_field_titre("Position", $_SERVER["PHP_SELF"], "t.position", "", $param, '', $sortfield, $sortorder, 'center ');
    print_liste_field_titre('', $_SERVER["PHP_SELF"], "", '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ');
    print "</tr>\n";

    // Lines
    $i = 0;
    $totalarray = array();
    while ($i < min($num, $limit)) {
        $obj = $db->fetch_object($resql);
        if (empty($obj)) {
            break;
        }

        print '<tr class="oddeven">';

        // Ref
        print '<td>';
        print '<a href="'.DOL_URL_ROOT.'/custom/chwhatsappbutton/templatecard.php?id='.$obj->rowid.'">';
        print $obj->ref;
        print '</a>';
        print '</td>';

        // Label
        print '<td>'.$obj->label.'</td>';

        // Entity type
        print '<td>';
        $entity_types = array(
            'thirdparty' => $langs->trans("ThirdParty"),
            'project' => $langs->trans("Project"),
            'propal' => $langs->trans("Proposal"),
            'invoice' => $langs->trans("Invoice")
        );
        print isset($entity_types[$obj->entity_type]) ? $entity_types[$obj->entity_type] : $obj->entity_type;
        print '</td>';

        // Status
        print '<td class="center">';
        if ($obj->is_active) {
            print '<span class="badge badge-status4 badge-status">'.$langs->trans("Active").'</span>';
        } else {
            print '<span class="badge badge-status8 badge-status">'.$langs->trans("Inactive").'</span>';
        }
        print '</td>';

        // Default
        print '<td class="center">';
        if ($obj->is_default) {
            print '<span class="fa fa-check" style="color: green;"></span>';
        }
        print '</td>';

        // Position
        print '<td class="center">'.$obj->position.'</td>';

        // Actions
        print '<td class="center">';
        if ($user->rights->chwhatsappbutton->write) {
            print '<a class="editfielda" href="'.DOL_URL_ROOT.'/custom/chwhatsappbutton/templatecard.php?id='.$obj->rowid.'&action=edit">';
            print img_edit();
            print '</a> ';
        }
        if ($user->rights->chwhatsappbutton->delete) {
            print '<a class="deletefielda" href="'.$_SERVER["PHP_SELF"].'?id='.$obj->rowid.'&action=delete&token='.newToken().'">';
            print img_delete();
            print '</a>';
        }
        print '</td>';

        print '</tr>'."\n";

        $i++;
    }

    // If no record found
    if ($num == 0) {
        $colspan = 7;
        print '<tr><td colspan="'.$colspan.'" class="opacitymedium">'.$langs->trans("NoRecordFound").'</td></tr>';
    }

    print "</table>";
    print '</div>';

    print '</form>';

    $db->free($resql);
} else {
    dol_print_error($db);
}

// End of page
llxFooter();
$db->close();
