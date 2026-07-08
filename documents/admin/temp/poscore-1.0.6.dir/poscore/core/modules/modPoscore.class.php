<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modPoscore extends DolibarrModules
{
    public function __construct($db)
    {
        global $conf;

        $this->db = $db;
        $this->numero = 106500;
        $this->rights_class = 'poscore';
        $this->family = 'products';
        $this->module_position = '90';
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = 'POS Core child module controlled by saascore';
        $this->descriptionlong = 'POS Terminal module for Dolibarr 22 running under saascore entitlement control';
        $this->version = '1.0.6';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->picto = 'generic';
        $this->langfiles = array('poscore@poscore');
        $this->dirs = array();
        $this->config_page_url = array();
        $this->depends = array('modSaascore');
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->phpmin = array(8, 0);
        $this->need_dolibarr_version = array(22, -3);
        $this->enabled = "isModEnabled('saascore')";

        $this->const = array();

        $this->sql = array();

        $r = 0;
        $this->rights = array();

        $this->rights[$r][0] = 106501;
        $this->rights[$r][1] = 'View POS';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'view';
        $this->rights[$r][5] = '';
        $r++;

        $this->rights[$r][0] = 106502;
        $this->rights[$r][1] = 'Use cashier terminal';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'cashier';
        $this->rights[$r][5] = '';
        $r++;

        $this->rights[$r][0] = 106503;
        $this->rights[$r][1] = 'Refund sale';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'refund';
        $this->rights[$r][5] = '';
        $r++;

        $this->rights[$r][0] = 106504;
        $this->rights[$r][1] = 'View POS reports';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'reports';
        $this->rights[$r][5] = '';
        $r++;

        $this->rights[$r][0] = 106505;
        $this->rights[$r][1] = 'Admin POS';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'admin';
        $this->rights[$r][5] = '';
        $r++;

        $this->menu = array();
        $this->menu[] = array(
            'fk_menu'   => 0,
            'type'      => 'top',
            'titre'     => 'POS',
            'mainmenu'  => 'poscore',
            'leftmenu'  => '',
            'url'       => '/custom/poscore/pos.php',
            'langs'     => 'poscore@poscore',
            'position'  => 1000,
            'enabled'   => 'isModEnabled("poscore")',
            'perms'     => '$user->hasRight("poscore", "cashier")',
            'target'    => '',
            'user'      => 2
        );

        $this->menu[] = array(
            'fk_menu'   => 'fk_mainmenu=poscore',
            'type'      => 'left',
            'titre'     => 'POS Terminal',
            'mainmenu'  => 'poscore',
            'leftmenu'  => 'pos_terminal',
            'url'       => '/custom/poscore/pos.php',
            'langs'     => 'poscore@poscore',
            'position'  => 100,
            'enabled'   => 'isModEnabled("poscore")',
            'perms'     => '$user->hasRight("poscore", "cashier")',
            'target'    => '',
            'user'      => 2
        );
    }

    public function init($options = '')
    {
        $result = $this->_load_tables('/poscore/sql/');
        if ($result < 0) {
            return -1;
        }

        $sql = array();
        $result = $this->_init($sql, $options);
        if ($result <= 0) {
            return $result;
        }

        require_once DOL_DOCUMENT_ROOT.'/custom/poscore/class/service/PosSaasBridge.php';
        $bridge = new PosSaasBridge($this->db);
        if ($bridge->registerCapabilities() <= 0) {
            $this->error = $bridge->error ?: 'Unable to register poscore in saascore';
            return -1;
        }

        return $result;
    }

    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }
}
