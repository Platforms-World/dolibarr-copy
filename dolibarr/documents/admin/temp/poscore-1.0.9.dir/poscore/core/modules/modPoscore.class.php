<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modPoscore extends DolibarrModules
{
    public function __construct($db)
    {
        $this->db = $db;
        $this->numero = 106500;
        $this->rights_class = 'poscore';
        $this->family = 'crm';
        $this->module_position = '50';
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = 'POS Core module controlled by saascore';
        $this->descriptionlong = 'POS Terminal child module for Dolibarr SaaS platform controlled by saascore';
        $this->version = '1.0.9';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->picto = 'generic';
        $this->langfiles = array('poscore@poscore');
        $this->phpmin = array(8, 0);
        $this->need_dolibarr_version = array(22, -3);
        $this->enabled = "isModEnabled('saascore')";
        $this->hidden = false;
        $this->depends = array('modSaascore');
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->skip_late_activation = false;
        $this->const = array();
        $this->dirs = array();
        $this->config_page_url = array();

        $r = 0;
        $this->rights = array();
        $this->rights[$r][0] = 106501; $this->rights[$r][1] = 'View POS'; $this->rights[$r][4] = 'view'; $this->rights[$r][5] = ''; $r++;
        $this->rights[$r][0] = 106502; $this->rights[$r][1] = 'Use cashier terminal'; $this->rights[$r][4] = 'cashier'; $this->rights[$r][5] = ''; $r++;
        $this->rights[$r][0] = 106503; $this->rights[$r][1] = 'Refund sale'; $this->rights[$r][4] = 'refund'; $this->rights[$r][5] = ''; $r++;
        $this->rights[$r][0] = 106504; $this->rights[$r][1] = 'View POS reports'; $this->rights[$r][4] = 'reports'; $this->rights[$r][5] = ''; $r++;
        $this->rights[$r][0] = 106505; $this->rights[$r][1] = 'Admin POS'; $this->rights[$r][4] = 'admin'; $this->rights[$r][5] = ''; $r++;

        $this->menu = array();
        $this->menu[] = array(
            'fk_menu'   => 0,
            'type'      => 'top',
            'titre'     => 'POS',
            'prefix'    => img_picto('', 'generic'),
            'mainmenu'  => 'poscore',
            'leftmenu'  => '',
            'url'       => '/custom/poscore/pos.php',
            'langs'     => 'poscore@poscore',
            'position'  => 100,
            'enabled'   => 'isModEnabled("poscore")',
            'perms'     => '$user->id > 0',
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
            'position'  => 101,
            'enabled'   => 'isModEnabled("poscore")',
            'perms'     => '$user->id > 0',
            'target'    => '',
            'user'      => 2
        );
    }

    public function init($options = '')
    {
        $res = $this->_load_tables('/poscore/sql/');
        if ($res < 0) return -1;

        $result = $this->_init(array(), $options);
        if ($result <= 0) return $result;

        $this->registerIntoSaascore();
        return $result;
    }

    public function remove($options = '')
    {
        return $this->_remove(array(), $options);
    }

    protected function registerIntoSaascore()
    {
        $file = DOL_DOCUMENT_ROOT.'/custom/saascore/class/SaasRegistryService.php';
        if (!is_file($file)) {
            dol_syslog(__METHOD__.' saascore registry service not found', LOG_WARNING);
            return 0;
        }
        require_once $file;
        if (!class_exists('SaasRegistryService')) {
            dol_syslog(__METHOD__.' SaasRegistryService class not found', LOG_WARNING);
            return 0;
        }

        try {
            $registry = new SaasRegistryService($this->db);
            $registry->registerModule('poscore', 'POS Core', 'Point of sale management module', 0);

            $registry->registerFeature('pos_terminal', 'POS Terminal', 'poscore', 'Open POS terminal screen');
            $registry->registerFeature('create_sale', 'Create Sale', 'poscore', 'Create POS sale invoice');
            $registry->registerFeature('refund_sale', 'Refund Sale', 'poscore', 'Refund POS transaction');
            $registry->registerFeature('hold_sale', 'Hold Sale', 'poscore', 'Hold cart for later');
            $registry->registerFeature('print_receipt', 'Print Receipt', 'poscore', 'Print sale receipt');
            $registry->registerFeature('x_report', 'X Report', 'poscore', 'View X report');
            $registry->registerFeature('z_report', 'Z Report', 'poscore', 'View Z report');
            $registry->registerFeature('multi_cashier', 'Multi Cashier', 'poscore', 'Allow more than one cashier');
            $registry->registerFeature('multi_terminal', 'Multi Terminal', 'poscore', 'Allow more than one POS terminal');

            $registry->registerLimit('max_cashiers', 'Maximum Cashiers', 'poscore', 1, 'Maximum allowed POS cashiers');
            $registry->registerLimit('max_terminals', 'Maximum Terminals', 'poscore', 1, 'Maximum allowed POS terminals');
            $registry->registerLimit('max_sales_per_day', 'Maximum Sales Per Day', 'poscore', 0, '0 means unlimited');

            $registry->registerPermission('poscore.view', 'View POS', 'poscore', 'View POS screens');
            $registry->registerPermission('poscore.cashier', 'POS Cashier', 'poscore', 'Use POS terminal');
            $registry->registerPermission('poscore.refund', 'Refund Sale', 'poscore', 'Refund POS sales');
            $registry->registerPermission('poscore.reports', 'POS Reports', 'poscore', 'Access POS reports');
            $registry->registerPermission('poscore.admin', 'POS Admin', 'poscore', 'Administer POS module');
        } catch (Exception $e) {
            dol_syslog(__METHOD__.' '.$e->getMessage(), LOG_ERR);
        }

        return 1;
    }
}
