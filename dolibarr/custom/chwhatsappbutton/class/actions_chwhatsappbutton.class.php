<?php
/**
 * Actions class for ChWhatsAppButton hooks
 */

class ActionsChwhatsappbutton
{
    /**
     * @var DoliDB Database handler
     */
    public $db;

    /**
     * @var array Errors
     */
    public $errors = array();

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get WhatsApp phone number from object
     *
     * @param object $object Object (thirdparty, project, propal, invoice)
     * @param string $entity_type Entity type
     * @return string Phone number or empty string
     */
    private function getWhatsAppPhone($object, $entity_type)
    {
        $phone = '';

        // For thirdparty, use the object directly
        if ($entity_type == 'thirdparty') {
            $thirdparty = $object;
        }
        // For other entities, try to get thirdparty from object
        elseif (isset($object->thirdparty) && is_object($object->thirdparty)) {
            $thirdparty = $object->thirdparty;
        }
        // Try to load thirdparty if we have socid
        elseif (!empty($object->socid)) {
            require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
            $thirdparty = new Societe($this->db);
            $thirdparty->fetch($object->socid);
        } else {
            $thirdparty = null;
        }

        if ($thirdparty && is_object($thirdparty)) {
            // Try phone field
            if (!empty($thirdparty->phone)) {
                $phone = $thirdparty->phone;
            }
            // Try mobile field if no phone
            elseif (!empty($thirdparty->phone_mobile)) {
                $phone = $thirdparty->phone_mobile;
            }
        }

        // Clean phone number (remove spaces, dashes, parentheses)
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        return $phone;
    }

    /**
     * Generate WhatsApp button HTML
     *
     * @param string $phone Phone number
     * @param string $message Message text
     * @param string $entity_type Entity type
     * @return string HTML button
     */
    private function generateWhatsAppButton($phone, $message, $entity_type)
    {
        global $langs;

        $langs->load("chwhatsappbutton@chwhatsappbutton");

        // Encode message for URL
        $encoded_message = urlencode($message);

        // Generate WhatsApp Web URL
        $whatsapp_url = "https://web.whatsapp.com/send?phone=".$phone."&text=".$encoded_message;

        // Button HTML
        $html = '<div class="chwhatsapp-button-container" style="display: inline-block; margin-left: 5px;">';
        $html .= '<a href="'.$whatsapp_url.'" target="_blank" class="butAction" id="chwhatsapp-send-btn" title="'.$langs->trans("SendViaWhatsApp").'">';
        $html .= '<span class="fa fa-whatsapp"></span> '.$langs->trans("WhatsApp");
        $html .= '</a>';
        $html .= '</div>';

        // Add info tooltip
        $html .= '<script type="text/javascript">';
        $html .= '$(document).ready(function() {';
        $html .= '  $("#chwhatsapp-send-btn").click(function(e) {';
        $html .= '    if (!confirm("'.$langs->trans("WhatsAppRequirement").'")) {';
        $html .= '      e.preventDefault();';
        $html .= '    }';
        $html .= '  });';
        $html .= '});';
        $html .= '</script>';

        return $html;
    }

    /**
     * Generate template selector modal
     *
     * @param array $templates Array of templates
     * @param string $phone Phone number
     * @param object $object Object
     * @param string $entity_type Entity type
     * @return string HTML modal
     */
    private function generateTemplateModal($templates, $phone, $object, $entity_type)
    {
        global $langs;

        $langs->load("chwhatsappbutton@chwhatsappbutton");

        dol_include_once('/chwhatsappbutton/class/chwhatsapptemplate.class.php');

        $html = '<div id="chwhatsapp-modal" class="chwhatsapp-modal" style="display:none;">';
        $html .= '<div class="chwhatsapp-modal-content">';
        $html .= '<span class="chwhatsapp-close">&times;</span>';
        $html .= '<h3>'.$langs->trans("SelectWhatsAppTemplate").'</h3>';
        $html .= '<div class="chwhatsapp-templates-list">';

        foreach ($templates as $template) {
            $message = $template->replaceSubstitutions($object, $entity_type);
            $encoded_message = urlencode($message);
            $whatsapp_url = "https://web.whatsapp.com/send?phone=".$phone."&text=".$encoded_message;

            $html .= '<div class="chwhatsapp-template-item" style="margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">';
            $html .= '<h4 style="margin-top: 0;">'.$template->label.'</h4>';
            if ($template->description) {
                $html .= '<p style="color: #666; font-size: 0.9em;">'.$template->description.'</p>';
            }
            $html .= '<div style="background: #f5f5f5; padding: 10px; margin: 10px 0; border-radius: 3px; white-space: pre-wrap; font-family: monospace; font-size: 0.9em;">';
            $html .= nl2br(htmlspecialchars($message));
            $html .= '</div>';
            $html .= '<a href="'.$whatsapp_url.'" target="_blank" class="butAction" style="margin-right: 10px;">';
            $html .= '<span class="fa fa-whatsapp"></span> '.$langs->trans("SendThisMessage");
            $html .= '</a>';
            $html .= '</div>';
        }

        // Custom message option
        $html .= '<div class="chwhatsapp-template-item" style="margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">';
        $html .= '<h4 style="margin-top: 0;">'.$langs->trans("CustomMessage").'</h4>';
        $html .= '<textarea id="chwhatsapp-custom-message" rows="5" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 3px;"></textarea>';
        $html .= '<button type="button" class="butAction" style="margin-top: 10px;" onclick="chwhatsappSendCustom(\''.$phone.'\')">';
        $html .= '<span class="fa fa-whatsapp"></span> '.$langs->trans("SendCustomMessage");
        $html .= '</button>';
        $html .= '</div>';

        $html .= '</div>'; // templates-list
        $html .= '</div>'; // modal-content
        $html .= '</div>'; // modal

        // JavaScript for modal
        $html .= '<script type="text/javascript">';
        $html .= 'function chwhatsappSendCustom(phone) {';
        $html .= '  var message = document.getElementById("chwhatsapp-custom-message").value;';
        $html .= '  if (message.trim() === "") {';
        $html .= '    alert("'.$langs->trans("PleaseEnterMessage").'");';
        $html .= '    return;';
        $html .= '  }';
        $html .= '  var url = "https://web.whatsapp.com/send?phone=" + phone + "&text=" + encodeURIComponent(message);';
        $html .= '  window.open(url, "_blank");';
        $html .= '}';
        $html .= '</script>';

        return $html;
    }

    /**
     * Hook to add WhatsApp button on cards (called after main content)
     *
     * @param array $parameters Parameters
     * @param object $object Object
     * @param string $action Action
     * @return int 0
     */
    public function formObjectOptions($parameters, &$object, &$action)
    {
        // This hook is called but we'll use printCommonFooter instead
        return 0;
    }

    /**
     * Hook to add WhatsApp button on cards
     *
     * @param array $parameters Parameters
     * @param object $object Object
     * @param string $action Action
     * @return int 0
     */
    public function printCommonFooter($parameters, &$object, &$action)
    {
        global $conf, $langs;

        if (!empty($conf->chwhatsappbutton->enabled)) {
            $langs->load("chwhatsappbutton@chwhatsappbutton");
            dol_include_once('/chwhatsappbutton/class/chwhatsapptemplate.class.php');

            $entity_type = '';
            $context = isset($parameters['context']) ? $parameters['context'] : '';
            
            // Ensure context is an array
            if (!is_array($context)) {
                $context = array($context);
            }

            // Determine entity type based on context
            if (in_array('thirdpartycard', $context)) {
                $entity_type = 'thirdparty';
            } elseif (in_array('projectcard', $context)) {
                $entity_type = 'project';
            } elseif (in_array('propalcard', $context)) {
                $entity_type = 'propal';
            } elseif (in_array('invoicecard', $context)) {
                $entity_type = 'invoice';
            }

            if ($entity_type && is_object($object) && $object->id > 0) {
                // Get WhatsApp phone
                $phone = $this->getWhatsAppPhone($object, $entity_type);

                // Debug: Log what we found
                dol_syslog("ChWhatsAppButton: entity_type=".$entity_type.", phone=".$phone.", object_id=".$object->id, LOG_DEBUG);

                if (!empty($phone)) {
                    // Get templates for this entity type
                    $template_obj = new ChWhatsAppTemplate($this->db);
                    $templates = $template_obj->fetchAllByType($entity_type, true);

                    dol_syslog("ChWhatsAppButton: Found ".count($templates)." templates for ".$entity_type, LOG_DEBUG);

                    if (count($templates) > 0) {
                        // Add modal with templates
                        echo $this->generateTemplateModal($templates, $phone, $object, $entity_type);

                        // Add JavaScript to inject button and handle modal
                        echo '<script type="text/javascript">';
                        echo '$(document).ready(function() {';
                        echo '  // Wait for DOM to be fully loaded';
                        echo '  setTimeout(function() {';
                        echo '    var tabsAction = $(".tabsAction");';
                        echo '    if (tabsAction.length > 0) {';
                        echo '      // Create WhatsApp button';
                        echo '      var whatsappBtn = $(\'<a class="butAction" href="#" id="chwhatsapp-open-modal">\');';
                        echo '      whatsappBtn.html(\'<span class="fa fa-whatsapp"></span> '.$langs->trans("WhatsApp").'\');';
                        echo '      ';
                        echo '      // Add button to actions bar';
                        echo '      tabsAction.append(whatsappBtn);';
                        echo '      ';
                        echo '      // Setup modal';
                        echo '      var modal = $("#chwhatsapp-modal");';
                        echo '      whatsappBtn.click(function(e) {';
                        echo '        e.preventDefault();';
                        echo '        modal.show();';
                        echo '      });';
                        echo '      ';
                        echo '      $(".chwhatsapp-close").click(function() { modal.hide(); });';
                        echo '      $(window).click(function(e) { if (e.target == modal[0]) { modal.hide(); } });';
                        echo '      ';
                        echo '      console.log("ChWhatsAppButton: Button added successfully");';
                        echo '    } else {';
                        echo '      console.warn("ChWhatsAppButton: .tabsAction not found");';
                        echo '    }';
                        echo '  }, 100);';
                        echo '});';
                        echo '</script>';
                    } else {
                        dol_syslog("ChWhatsAppButton: No templates found for entity_type=".$entity_type, LOG_WARNING);
                    }
                } else {
                    dol_syslog("ChWhatsAppButton: No phone found for object id=".$object->id.", entity_type=".$entity_type, LOG_WARNING);
                }
            } else {
                dol_syslog("ChWhatsAppButton: Conditions not met - entity_type=".$entity_type.", is_object=".is_object($object).", id=".($object->id ?? 'N/A'), LOG_DEBUG);
                
                // Debug output (remove in production)
                if (!empty($conf->global->MAIN_MODULE_CHWHATSAPPBUTTON_DEBUG)) {
                    echo '<!-- ChWhatsAppButton Debug: ';
                    echo 'entity_type='.$entity_type.', ';
                    echo 'is_object='.is_object($object).', ';
                    echo 'id='.($object->id ?? 'N/A').', ';
                    echo 'context='.print_r($context, true);
                    echo ' -->';
                }
            }
        }

        return 0;
    }
}
