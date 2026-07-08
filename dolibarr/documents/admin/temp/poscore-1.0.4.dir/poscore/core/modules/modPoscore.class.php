<?php
require_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

class modPoscore extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;
        $this->db = $db;

        $this->numero = 105600;
        $this->rights_class = 'poscore';
        $this->family = 'products';
        $this->module_position = '90';

        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = 'POS Management Module integrated with saascore';
        $this->version = '1.0.4';
        $this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
        $this->picto = 'cash-register';

        $this->langfiles = array('poscore@poscore');
        $this->dirs = array();

        $this->config_page_url = array(
            'terminal_list.php@poscore',
            'cashier_list.php@poscore'
        );

        $this->menu = array();

        $this->menu[] = array(
            'fk_menu'   => '',
            'type'      => 'top',
            'titre'     => 'POS',
            'mainmenu'  => 'poscore',
            'leftmenu'  => '',
            'url'       => '/poscore/pos/dashboard.php',
            'langs'     => 'poscore@poscore',
            'position'  => 1000,
            'enabled'   => '$conf->poscore->enabled',
            'perms'     => '$user->admin || $user->rights->poscore->read',
            'target'    => '',
            'user'      => 2
        );

        $this->menu[] = array(
            'fk_menu'   => 'fk_mainmenu=poscore',
            'type'      => 'left',
            'titre'     => 'Dashboard',
            'mainmenu'  => 'poscore',
            'leftmenu'  => 'poscore_dashboard',
            'url'       => '/poscore/pos/dashboard.php',
            'langs'     => 'poscore@poscore',
            'position'  => 100,
            'enabled'   => '$conf->poscore->enabled',
            'perms'     => '$user->admin || $user->rights->poscore->read',
            'target'    => '',
            'user'      => 2
        );

        $this->menu[] = array(
            'fk_menu'   => 'fk_mainmenu=poscore',
            'type'      => 'left',
            'titre'     => 'Terminals',
            'mainmenu'  => 'poscore',
            'leftmenu'  => 'poscore_terminals',
            'url'       => '/poscore/admin/terminal_list.php',
            'langs'     => 'poscore@poscore',
            'position'  => 110,
            'enabled'   => '$conf->poscore->enabled',
            'perms'     => '$user->admin || $user->rights->poscore->admin',
            'target'    => '',
            'user'      => 2
        );

        $this->menu[] = array(
            'fk_menu'   => 'fk_mainmenu=poscore',
            'type'      => 'left',
            'titre'     => 'Cashiers',
            'mainmenu'  => 'poscore',
            'leftmenu'  => 'poscore_cashiers',
            'url'       => '/poscore/admin/cashier_list.php',
            'langs'     => 'poscore@poscore',
            'position'  => 120,
            'enabled'   => '$conf->poscore->enabled',
            'perms'     => '$user->admin || $user->rights->poscore->admin',
            'target'    => '',
            'user'      => 2
        );

        $this->rights = array();
        $r = 0;
        $this->rights[$r][0] = 105601;
        $this->rights[$r][1] = 'Read POS';
        $this->rights[$r][2] = 'r';
        $this->rights[$r][3] = 1;
        $this->rights[$r][4] = 'read';
        $this->rights[$r][5] = '';
        $r++;

        $this->rights[$r][0] = 105602;
        $this->rights[$r][1] = 'Manage POS';
        $this->rights[$r][2] = 'w';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'admin';
        $this->rights[$r][5] = '';
    }

    public function init($options = '')
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

    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }
}
