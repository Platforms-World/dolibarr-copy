<?php
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modChwhatsappbutton extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;
        
        $this->db = $db;
        
        // Id for module (must be unique)
        $this->numero = 105004;
        
        // Key text used to identify module
        $this->rights_class = 'chwhatsappbutton';
        
        // Family
        $this->family = "interface";
        
        // Module position in the family
        $this->module_position = '90';
        
        // Module label
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        
        // Module description
        $this->description = "Botones de WhatsApp en terceros, proyectos, presupuestos y facturas";
        $this->descriptionlong = "Módulo que agrega botones de WhatsApp para enviar mensajes desde terceros, proyectos, presupuestos y facturas usando plantillas personalizables";
        
        // Author
        $this->editor_name = 'DolibarrModules';
        $this->editor_url = '';
        
        // Version
        $this->version = '1.0.0';
        
        // Key used in llx_const table
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        
        // Icon
        $this->picto = 'technic';
        
        // Define some features supported by module
        $this->module_parts = array(
            'triggers' => 0,
            'login' => 0,
            'substitutions' => 0,
            'menus' => 0,
            'tpl' => 0,
            'barcode' => 0,
            'models' => 0,
            'printing' => 0,
            'theme' => 0,
            'css' => array('/chwhatsappbutton/css/chwhatsappbutton.css'),
            'js' => array(
                '/chwhatsappbutton/js/chwhatsappbutton_lang.js.php',
                '/chwhatsappbutton/js/chwhatsappbutton.js'
            ),
            // Hooks para inyectar botones en las páginas
            'hooks' => array(
                'thirdpartycard',      // Ficha de tercero
                'projectcard',         // Ficha de proyecto
                'propalcard',          // Ficha de presupuesto
                'invoicecard',         // Ficha de factura
                'contactcard',         // Ficha de contacto
            ),
            'moduleforexternal' => 0,
        );
        
        // Data directories to create
        $this->dirs = array("/chwhatsappbutton");
        
        // Config pages
        $this->config_page_url = array("chwhatsappbutton_setup.php@chwhatsappbutton");
        
        // Dependencies
        $this->hidden = false;
        $this->depends = array();
        $this->requiredby = array();
        $this->conflictwith = array();
        
        // Language files
        $this->langfiles = array("chwhatsappbutton@chwhatsappbutton");
        
        // Prerequisites
        $this->phpmin = array(7, 0);
        $this->need_dolibarr_version = array(11, -3);
        
        // Constants
        $this->const = array();

        // Tabs
        $this->tabs = array();

        // Dictionaries
        $this->dictionaries = array();

        // Boxes/Widgets
        $this->boxes = array();

        // Cronjobs
        $this->cronjobs = array();

        // Permissions
        $this->rights = array();
        $r = 0;

        // Permission to read
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Leer plantillas de WhatsApp';
        $this->rights[$r][4] = 'chwhatsappbutton';
        $this->rights[$r][5] = 'read';
        $r++;

        // Permission to write
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Crear/modificar plantillas de WhatsApp';
        $this->rights[$r][4] = 'chwhatsappbutton';
        $this->rights[$r][5] = 'write';
        $r++;

        // Permission to delete
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Eliminar plantillas de WhatsApp';
        $this->rights[$r][4] = 'chwhatsappbutton';
        $this->rights[$r][5] = 'delete';
        $r++;

        // Menus
        $this->menu = array();
        $r = 0;

        // Main menu in Tools
        $this->menu[$r]['fk_menu'] = 'fk_mainmenu=tools';
        $this->menu[$r]['type'] = 'left';
        $this->menu[$r]['titre'] = 'WhatsApp';
        $this->menu[$r]['mainmenu'] = 'tools';
        $this->menu[$r]['leftmenu'] = 'chwhatsappbutton';
        $this->menu[$r]['url'] = '/custom/chwhatsappbutton/templateslist.php';
        $this->menu[$r]['langs'] = 'chwhatsappbutton@chwhatsappbutton';
        $this->menu[$r]['position'] = 1000;
        $this->menu[$r]['enabled'] = 1;
        $this->menu[$r]['perms'] = '1';
        $this->menu[$r]['target'] = '';
        $this->menu[$r]['user'] = 2;
        $r++;

        // Submenu: Templates list
        $this->menu[$r]['fk_menu'] = 'fk_mainmenu=tools,fk_leftmenu=chwhatsappbutton';
        $this->menu[$r]['type'] = 'left';
        $this->menu[$r]['titre'] = 'WhatsAppTemplates';
        $this->menu[$r]['mainmenu'] = 'tools';
        $this->menu[$r]['leftmenu'] = 'chwhatsappbutton_templates';
        $this->menu[$r]['url'] = '/custom/chwhatsappbutton/templateslist.php';
        $this->menu[$r]['langs'] = 'chwhatsappbutton@chwhatsappbutton';
        $this->menu[$r]['position'] = 1001;
        $this->menu[$r]['enabled'] = 1;
        $this->menu[$r]['perms'] = '1';
        $this->menu[$r]['target'] = '';
        $this->menu[$r]['user'] = 2;
        $r++;

        // Submenu: New template
        $this->menu[$r]['fk_menu'] = 'fk_mainmenu=tools,fk_leftmenu=chwhatsappbutton';
        $this->menu[$r]['type'] = 'left';
        $this->menu[$r]['titre'] = 'NewTemplate';
        $this->menu[$r]['mainmenu'] = 'tools';
        $this->menu[$r]['leftmenu'] = 'chwhatsappbutton_new';
        $this->menu[$r]['url'] = '/custom/chwhatsappbutton/templatecard.php?action=create';
        $this->menu[$r]['langs'] = 'chwhatsappbutton@chwhatsappbutton';
        $this->menu[$r]['position'] = 1002;
        $this->menu[$r]['enabled'] = 1;
        $this->menu[$r]['perms'] = '1';
        $this->menu[$r]['target'] = '';
        $this->menu[$r]['user'] = 2;
        $r++;
    }

    /**
     * Function called when module is enabled.
     *
     * @param string $options Options when enabling module
     * @return int 1 if OK, 0 if KO
     */
    public function init($options = '')
    {
        global $conf, $langs, $db;

        $sql = array();
        
        // Tables are created manually via test_activation.php
        // No SQL needed here to avoid activation errors
        
        return $this->_init($sql, $options);
    }
    
    /**
     * Create tables and load initial data
     *
     * @return int <0 if KO, >0 if OK
     */
    public function loadTables()
    {
        global $db, $langs;
        
        // Load language file
        $langs->load("chwhatsappbutton@chwhatsappbutton");
        
        // Create table
        $sql = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."chwhatsapp_templates (
            rowid int(11) NOT NULL AUTO_INCREMENT,
            ref varchar(128) NOT NULL,
            label varchar(255) NOT NULL,
            description text,
            message_text longtext NOT NULL,
            entity_type varchar(50) NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            is_default tinyint(1) DEFAULT 0,
            position int(11) DEFAULT 0,
            fk_user_author int(11) NOT NULL,
            fk_user_modif int(11),
            datec datetime NOT NULL,
            tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (rowid),
            UNIQUE KEY uk_chwhatsapp_templates_ref (ref),
            KEY idx_chwhatsapp_entity_type (entity_type),
            KEY idx_chwhatsapp_active (is_active)
        ) ENGINE=innodb DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->query($sql);
        
        // Define default templates structure (ref, entity_type, is_default, position)
        $templates_structure = array(
            array('THIRDPARTY_DEFAULT', 'thirdparty', 1, 10),
            array('PROJECT_UPDATE', 'project', 1, 20),
            array('PROPAL_SEND', 'propal', 1, 30),
            array('INVOICE_SEND', 'invoice', 1, 40),
            array('PAYMENT_REMINDER', 'invoice', 0, 50),
            array('PROPAL_FOLLOWUP', 'propal', 0, 60)
        );
        
        // Insert templates with translations from lang files
        foreach ($templates_structure as $tpl_struct) {
            $ref = $tpl_struct[0];
            $entity_type = $tpl_struct[1];
            $is_default = $tpl_struct[2];
            $position = $tpl_struct[3];
            
            // Get translations from lang file
            $label = $langs->trans('Template_'.$ref.'_Label');
            $description = $langs->trans('Template_'.$ref.'_Desc');
            $message = $langs->trans('Template_'.$ref.'_Message');
            
            // Skip if translation not found
            if ($label == 'Template_'.$ref.'_Label') {
                continue;
            }
            
            $sql = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."chwhatsapp_templates ";
            $sql .= "(ref, label, description, message_text, entity_type, is_active, is_default, position, fk_user_author, datec) ";
            $sql .= "VALUES (";
            $sql .= "'".$db->escape($ref)."', ";
            $sql .= "'".$db->escape($label)."', ";
            $sql .= "'".$db->escape($description)."', ";
            $sql .= "'".$db->escape($message)."', ";
            $sql .= "'".$db->escape($entity_type)."', ";
            $sql .= "1, ";
            $sql .= (int)$is_default.", ";
            $sql .= (int)$position.", ";
            $sql .= "1, ";
            $sql .= "NOW()";
            $sql .= ")";
            
            $db->query($sql);
        }
        
        return 1;
    }
}
