<?php
require_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

class modPoscore extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;
        $this->db = $db;

        $this->numero = 105500;
        $this->rights_class = 'poscore';
        $this->family = "crm";
        $this->module_position = '90';

        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = "POS Management Module integrated with saascore";
        $this->version = '1.0.2';
        $this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
        $this->picto = 'cash-register';

        $this->langfiles = array("poscore@poscore");

        $this->dirs = array();

        $this->config_page_url = array(
            "terminal_list.php@poscore",
            "cashier_list.php@poscore"
        );

        $this->menu = array();

        $this->menu[] = array(
            'fk_menu'=>'',
            'type'=>'top',
            'titre'=>'POS',
            'mainmenu'=>'poscore',
            'leftmenu'=>'',
            'url'=>'/poscore/pos/dashboard.php',
            'langs'=>'poscore@poscore',
            'position'=>1000,
            'enabled'=>'$conf->poscore->enabled',
            'perms'=>'$user->rights->poscore->read',
            'target'=>'',
            'user'=>2
        );

        $r = 0;
        $this->rights[$r][0] = 105501;
        $this->rights[$r][1] = 'Read POS';
        $this->rights[$r][4] = 'read';
        $this->rights[$r][3] = 1;
        $this->rights[$r][5] = 'read';
        $r++;

        $this->rights[$r][0] = 105502;
        $this->rights[$r][1] = 'Manage POS';
        $this->rights[$r][4] = 'admin';
        $this->rights[$r][3] = 0;
        $this->rights[$r][5] = 'admin';
    }

    public function init($options='')
    {
        $sql = array();

        $result = $this->_load_tables('/poscore/sql/');
        if ($result < 0) return -1;

        $result = $this->_init($sql, $options);
        if ($result <= 0) return $result;

        require_once DOL_DOCUMENT_ROOT.'/custom/poscore/class/service/PosSaasCapabilityRegistrar.php';
        $registrar = new PosSaasCapabilityRegistrar($this->db);
        $registrar->registerAll();

        return $result;
    }

    public function remove($options='')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }
}
