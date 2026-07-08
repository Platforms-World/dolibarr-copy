<?php
/**
 * Page to create/edit WhatsApp template
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
if (!$user->admin && empty($user->rights->chwhatsappbutton->write)) {
    accessforbidden();
}

// Parameters
$id = GETPOST('id', 'int');
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

// Initialize technical objects
$object = new ChWhatsAppTemplate($db);
$form = new Form($db);

if ($id > 0) {
    $object->fetch($id);
}

/*
 * Actions
 */

if ($cancel) {
    if (!empty($backtopage)) {
        header("Location: ".$backtopage);
        exit;
    } else {
        header("Location: templateslist.php");
        exit;
    }
}

// Action to add record
if ($action == 'add') {
    if (!GETPOST('cancel', 'alpha')) {
        $error = 0;

        $object->ref = GETPOST('ref', 'alpha');
        $object->label = GETPOST('label', 'alpha');
        $object->description = GETPOST('description', 'restricthtml');
        $object->message_text = GETPOST('message_text', 'restricthtml');
        $object->entity_type = GETPOST('entity_type', 'alpha');
        $object->is_active = GETPOST('is_active', 'int') ? 1 : 0;
        $object->is_default = GETPOST('is_default', 'int') ? 1 : 0;
        $object->position = GETPOST('position', 'int');

        if (empty($object->ref)) {
            setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Ref")), null, 'errors');
            $error++;
        }
        if (empty($object->label)) {
            setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Label")), null, 'errors');
            $error++;
        }
        if (empty($object->message_text)) {
            setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("MessageText")), null, 'errors');
            $error++;
        }
        if (empty($object->entity_type)) {
            setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("EntityType")), null, 'errors');
            $error++;
        }

        if (!$error) {
            $result = $object->create($user);
            if ($result > 0) {
                setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
                header("Location: templateslist.php");
                exit;
            } else {
                setEventMessages($object->error, $object->errors, 'errors');
                $action = 'create';
            }
        } else {
            $action = 'create';
        }
    }
}

// Action to update record
if ($action == 'update') {
    if (!GETPOST('cancel', 'alpha')) {
        $error = 0;

        $object->ref = GETPOST('ref', 'alpha');
        $object->label = GETPOST('label', 'alpha');
        $object->description = GETPOST('description', 'restricthtml');
        $object->message_text = GETPOST('message_text', 'restricthtml');
        $object->entity_type = GETPOST('entity_type', 'alpha');
        $object->is_active = GETPOST('is_active', 'int') ? 1 : 0;
        $object->is_default = GETPOST('is_default', 'int') ? 1 : 0;
        $object->position = GETPOST('position', 'int');

        if (empty($object->ref)) {
            setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Ref")), null, 'errors');
            $error++;
        }
        if (empty($object->label)) {
            setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Label")), null, 'errors');
            $error++;
        }
        if (empty($object->message_text)) {
            setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("MessageText")), null, 'errors');
            $error++;
        }

        if (!$error) {
            $result = $object->update($user);
            if ($result > 0) {
                setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
                header("Location: templateslist.php");
                exit;
            } else {
                setEventMessages($object->error, $object->errors, 'errors');
                $action = 'edit';
            }
        } else {
            $action = 'edit';
        }
    }
}

// Action to delete
if ($action == 'confirm_delete' && GETPOST('confirm', 'alpha') == 'yes') {
    $result = $object->delete($user);
    if ($result > 0) {
        setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
        header("Location: templateslist.php");
        exit;
    } else {
        setEventMessages($object->error, $object->errors, 'errors');
    }
}

/*
 * View
 */

$title = $langs->trans("WhatsAppTemplate");
$help_url = '';

llxHeader('', $title, $help_url);

// Create mode
if ($action == 'create') {
    print load_fiche_titre($langs->trans("NewTemplate"), '', 'object_chwhatsappbutton');

    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="add">';

    print dol_get_fiche_head();

    print '<table class="border centpercent tableforfieldcreate">'."\n";

    // Ref
    print '<tr><td class="fieldrequired titlefieldcreate">'.$langs->trans("Ref").'</td><td>';
    print '<input type="text" name="ref" size="30" maxlength="128" value="'.GETPOST('ref', 'alpha').'" required>';
    print '</td></tr>';

    // Label
    print '<tr><td class="fieldrequired">'.$langs->trans("Label").'</td><td>';
    print '<input type="text" name="label" size="50" maxlength="255" value="'.GETPOST('label', 'alpha').'" required>';
    print '</td></tr>';

    // Description
    print '<tr><td class="tdtop">'.$langs->trans("Description").'</td><td>';
    print '<textarea name="description" rows="3" cols="80">'.GETPOST('description', 'restricthtml').'</textarea>';
    print '</td></tr>';

    // Entity Type
    print '<tr><td class="fieldrequired">'.$langs->trans("EntityType").'</td><td>';
    $entity_types = array(
        'thirdparty' => $langs->trans("ThirdParty"),
        'project' => $langs->trans("Project"),
        'propal' => $langs->trans("Proposal"),
        'invoice' => $langs->trans("Invoice")
    );
    print $form->selectarray('entity_type', $entity_types, GETPOST('entity_type', 'alpha'), 0, 0, 0, '', 0, 0, 0, '', 'minwidth200', 1);
    print '</td></tr>';

    // Message Text
    print '<tr><td class="fieldrequired tdtop">'.$langs->trans("MessageText").'</td><td>';
    print '<textarea name="message_text" rows="10" cols="80" required>'.GETPOST('message_text', 'restricthtml').'</textarea>';
    print '<br><small>'.$langs->trans("AvailableSubstitutions").': __THIRDPARTY_NAME__, __THIRDPARTY_CODE__, __PROJECT_REF__, __PROJECT_TITLE__, __PROPAL_REF__, __PROPAL_TOTAL_TTC__, __INVOICE_REF__, __INVOICE_TOTAL_TTC__</small>';
    print '</td></tr>';

    // Is Active
    print '<tr><td>'.$langs->trans("Active").'</td><td>';
    print '<input type="checkbox" name="is_active" value="1" checked>';
    print '</td></tr>';

    // Is Default
    print '<tr><td>'.$langs->trans("Default").'</td><td>';
    print '<input type="checkbox" name="is_default" value="1">';
    print '</td></tr>';

    // Position
    print '<tr><td>'.$langs->trans("Position").'</td><td>';
    print '<input type="number" name="position" value="'.GETPOST('position', 'int').'" min="0">';
    print '</td></tr>';

    print '</table>';

    print dol_get_fiche_end();

    print '<div class="center">';
    print '<input type="submit" class="button" value="'.$langs->trans("Create").'">';
    print ' &nbsp; ';
    print '<input type="button" class="button button-cancel" value="'.$langs->trans("Cancel").'" onclick="history.back()">';
    print '</div>';

    print '</form>';
}

// Edit mode
if ($action == 'edit' && $id > 0) {
    print load_fiche_titre($langs->trans("EditTemplate"), '', 'object_chwhatsappbutton');

    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$id.'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="update">';
    print '<input type="hidden" name="id" value="'.$id.'">';

    print dol_get_fiche_head();

    print '<table class="border centpercent tableforfieldedit">'."\n";

    // Ref
    print '<tr><td class="fieldrequired titlefieldcreate">'.$langs->trans("Ref").'</td><td>';
    print '<input type="text" name="ref" size="30" maxlength="128" value="'.$object->ref.'" required>';
    print '</td></tr>';

    // Label
    print '<tr><td class="fieldrequired">'.$langs->trans("Label").'</td><td>';
    print '<input type="text" name="label" size="50" maxlength="255" value="'.$object->label.'" required>';
    print '</td></tr>';

    // Description
    print '<tr><td class="tdtop">'.$langs->trans("Description").'</td><td>';
    print '<textarea name="description" rows="3" cols="80">'.$object->description.'</textarea>';
    print '</td></tr>';

    // Entity Type
    print '<tr><td class="fieldrequired">'.$langs->trans("EntityType").'</td><td>';
    $entity_types = array(
        'thirdparty' => $langs->trans("ThirdParty"),
        'project' => $langs->trans("Project"),
        'propal' => $langs->trans("Proposal"),
        'invoice' => $langs->trans("Invoice")
    );
    print $form->selectarray('entity_type', $entity_types, $object->entity_type, 0, 0, 0, '', 0, 0, 0, '', 'minwidth200', 1);
    print '</td></tr>';

    // Message Text
    print '<tr><td class="fieldrequired tdtop">'.$langs->trans("MessageText").'</td><td>';
    print '<textarea name="message_text" rows="10" cols="80" required>'.$object->message_text.'</textarea>';
    print '<br><small>'.$langs->trans("AvailableSubstitutions").': __THIRDPARTY_NAME__, __THIRDPARTY_CODE__, __PROJECT_REF__, __PROJECT_TITLE__, __PROPAL_REF__, __PROPAL_TOTAL_TTC__, __INVOICE_REF__, __INVOICE_TOTAL_TTC__</small>';
    print '</td></tr>';

    // Is Active
    print '<tr><td>'.$langs->trans("Active").'</td><td>';
    print '<input type="checkbox" name="is_active" value="1"'.($object->is_active ? ' checked' : '').'>';
    print '</td></tr>';

    // Is Default
    print '<tr><td>'.$langs->trans("Default").'</td><td>';
    print '<input type="checkbox" name="is_default" value="1"'.($object->is_default ? ' checked' : '').'>';
    print '</td></tr>';

    // Position
    print '<tr><td>'.$langs->trans("Position").'</td><td>';
    print '<input type="number" name="position" value="'.$object->position.'" min="0">';
    print '</td></tr>';

    print '</table>';

    print dol_get_fiche_end();

    print '<div class="center">';
    print '<input type="submit" class="button" value="'.$langs->trans("Save").'">';
    print ' &nbsp; ';
    print '<input type="button" class="button button-cancel" value="'.$langs->trans("Cancel").'" onclick="history.back()">';
    print '</div>';

    print '</form>';
}

// View mode
if ($action != 'create' && $action != 'edit' && $id > 0) {
    $head = array();
    $h = 0;
    $head[$h][0] = $_SERVER["PHP_SELF"].'?id='.$id;
    $head[$h][1] = $langs->trans("Card");
    $head[$h][2] = 'card';
    $h++;

    print dol_get_fiche_head($head, 'card', $langs->trans("WhatsAppTemplate"), -1, 'object_chwhatsappbutton');

    $linkback = '<a href="'.DOL_URL_ROOT.'/custom/chwhatsappbutton/templateslist.php">'.$langs->trans("BackToList").'</a>';

    dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');

    print '<div class="fichecenter">';
    print '<div class="underbanner clearboth"></div>';
    print '<table class="border centpercent tableforfield">'."\n";

    // Label
    print '<tr><td class="titlefield">'.$langs->trans("Label").'</td><td>'.$object->label.'</td></tr>';

    // Description
    if ($object->description) {
        print '<tr><td class="tdtop">'.$langs->trans("Description").'</td><td>'.$object->description.'</td></tr>';
    }

    // Entity Type
    print '<tr><td>'.$langs->trans("EntityType").'</td><td>';
    $entity_types = array(
        'thirdparty' => $langs->trans("ThirdParty"),
        'project' => $langs->trans("Project"),
        'propal' => $langs->trans("Proposal"),
        'invoice' => $langs->trans("Invoice")
    );
    print isset($entity_types[$object->entity_type]) ? $entity_types[$object->entity_type] : $object->entity_type;
    print '</td></tr>';

    // Message Text
    print '<tr><td class="tdtop">'.$langs->trans("MessageText").'</td><td>';
    print '<div style="white-space: pre-wrap; background: #f5f5f5; padding: 10px; border-radius: 3px;">';
    print nl2br(htmlspecialchars($object->message_text));
    print '</div>';
    print '</td></tr>';

    // Status
    print '<tr><td>'.$langs->trans("Status").'</td><td>';
    if ($object->is_active) {
        print '<span class="badge badge-status4 badge-status">'.$langs->trans("Active").'</span>';
    } else {
        print '<span class="badge badge-status8 badge-status">'.$langs->trans("Inactive").'</span>';
    }
    print '</td></tr>';

    // Default
    print '<tr><td>'.$langs->trans("Default").'</td><td>';
    print $object->is_default ? $langs->trans("Yes") : $langs->trans("No");
    print '</td></tr>';

    // Position
    print '<tr><td>'.$langs->trans("Position").'</td><td>'.$object->position.'</td></tr>';

    print '</table>';
    print '</div>';

    print dol_get_fiche_end();

    // Buttons
    print '<div class="tabsAction">';

    if ($user->rights->chwhatsappbutton->write || $user->admin) {
        print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&action=edit">'.$langs->trans("Modify").'</a>';
    }

    if ($user->rights->chwhatsappbutton->delete || $user->admin) {
        print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&action=delete&token='.newToken().'">'.$langs->trans("Delete").'</a>';
    }

    print '</div>';
    
    // Debug info for troubleshooting
    if (!empty($conf->global->MAIN_FEATURES_LEVEL) && $conf->global->MAIN_FEATURES_LEVEL >= 2) {
        print '<!-- Debug: user->admin='.$user->admin.' -->';
        print '<!-- Debug: user->rights->chwhatsappbutton exists='.(!empty($user->rights->chwhatsappbutton) ? 'yes' : 'no').' -->';
    }
}

// End of page
llxFooter();
$db->close();
