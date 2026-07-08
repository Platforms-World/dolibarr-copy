<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modKafoGuard extends DolibarrModules
{
    public function __construct($db)
    {
        $this->db             = $db;
        $this->numero         = 500230;            // keep unique
        $this->rights_class   = 'kafoguard';
        $this->family         = 'technic';
        $this->module_position= '90';
        $this->name           = preg_replace('/^mod/i', '', get_class($this));
        $this->description    = 'KAFO guard: restrict Setup/Modules pages to Laravel admin';
        $this->version        = '1.0';
        $this->const_name     = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->picto          = 'generic';

        // These contexts fire on essentially every back-office page (incl. /admin/)
        $this->module_parts = array('hooks' => array('toprightmenu', 'leftblock', 'main'));

        $this->dirs = array();  $this->depends = array();  $this->requiredby = array();
        $this->conflictwith = array();  $this->langfiles = array();
        $this->const = array();  $this->rights = array();  $this->menu = array();
        $this->config_page_url = array();  $this->hidden = false;
    }
    public function init($options = '')   { return $this->_init(array(), $options); }
    public function remove($options = '') { return $this->_remove(array(), $options); }
}