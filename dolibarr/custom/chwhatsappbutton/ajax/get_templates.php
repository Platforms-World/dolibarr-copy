<?php
/**
 * AJAX endpoint to get WhatsApp templates and phone number
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    http_response_code(500);
    echo json_encode(['error' => 'Cannot load Dolibarr environment']);
    exit;
}

// Load translation files
$langs->loadLangs(array("chwhatsappbutton@chwhatsappbutton"));

// Check permissions
if (!$user->rights->chwhatsappbutton->read && !$user->admin) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

// Get parameters
$entity_type = GETPOST('entity_type', 'alpha');
$entity_id = GETPOST('entity_id', 'int');

if (empty($entity_type) || empty($entity_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

// Load template class
dol_include_once('/chwhatsappbutton/class/chwhatsapptemplate.class.php');

// Get phone number based on entity type
$phone = '';
$thirdparty_name = '';
$entity_data = array();

try {
    switch ($entity_type) {
        case 'thirdparty':
            require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
            $object = new Societe($db);
            $object->fetch($entity_id);
            $phone = !empty($object->phone) ? $object->phone : $object->phone_mobile;
            $thirdparty_name = $object->name;
            $entity_data['THIRDPARTY_NAME'] = $object->name;
            $entity_data['THIRDPARTY_CODE'] = $object->code_client;
            break;
            
        case 'invoice':
            require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
            require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
            $object = new Facture($db);
            $object->fetch($entity_id);
            $thirdparty = new Societe($db);
            $thirdparty->fetch($object->socid);
            $phone = !empty($thirdparty->phone) ? $thirdparty->phone : $thirdparty->phone_mobile;
            $thirdparty_name = $thirdparty->name;
            $entity_data['THIRDPARTY_NAME'] = $thirdparty->name;
            $entity_data['INVOICE_REF'] = $object->ref;
            $entity_data['INVOICE_TOTAL_TTC'] = price($object->total_ttc).' '.$conf->currency;
            break;
            
        case 'propal':
            require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
            require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
            $object = new Propal($db);
            $object->fetch($entity_id);
            $thirdparty = new Societe($db);
            $thirdparty->fetch($object->socid);
            $phone = !empty($thirdparty->phone) ? $thirdparty->phone : $thirdparty->phone_mobile;
            $thirdparty_name = $thirdparty->name;
            $entity_data['THIRDPARTY_NAME'] = $thirdparty->name;
            $entity_data['PROPAL_REF'] = $object->ref;
            $entity_data['PROPAL_TOTAL_TTC'] = price($object->total_ttc).' '.$conf->currency;
            break;
            
        case 'project':
            require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
            require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
            $object = new Project($db);
            $object->fetch($entity_id);
            $thirdparty = new Societe($db);
            $thirdparty->fetch($object->socid);
            $phone = !empty($thirdparty->phone) ? $thirdparty->phone : $thirdparty->phone_mobile;
            $thirdparty_name = $thirdparty->name;
            $entity_data['THIRDPARTY_NAME'] = $thirdparty->name;
            $entity_data['PROJECT_REF'] = $object->ref;
            $entity_data['PROJECT_TITLE'] = $object->title;
            break;
    }
    
    // Clean phone number
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    if (empty($phone)) {
        echo json_encode([
            'success' => false,
            'error' => $langs->trans('NoWhatsAppPhone')
        ]);
        exit;
    }
    
    // Get templates
    $template_obj = new ChWhatsAppTemplate($db);
    $templates = $template_obj->fetchAllByType($entity_type, true);
    
    // Format templates for response
    $templates_formatted = array();
    foreach ($templates as $tpl) {
        $message = $tpl->message_text;
        
        // Replace variables
        foreach ($entity_data as $key => $value) {
            $message = str_replace('__'.$key.'__', $value, $message);
        }
        
        // Convert \n to actual line breaks
        $message = str_replace('\\n', "\n", $message);
        
        $templates_formatted[] = array(
            'id' => $tpl->id,
            'ref' => $tpl->ref,
            'label' => $tpl->label,
            'description' => $tpl->description,
            'message' => $message,
            'is_default' => $tpl->is_default
        );
    }
    
    echo json_encode([
        'success' => true,
        'phone' => $phone,
        'thirdparty_name' => $thirdparty_name,
        'templates' => $templates_formatted,
        'entity_data' => $entity_data
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
