<?php
/* Copyright */
require_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

class modPoscore extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;
        $this->numero = 105500;
        $this->rights_class = 'poscore';

        $this->family = 'crm';
        $this->module_position = 500;
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = 'POS Management integrated with saascore';
        $this->descriptionlong = 'POS terminals, cashiers, shifts, settings, receipt profiles and guarded POS entry points';
        $this->version = '1.0.0';
        $this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
        $this->picto = 'cash-register';

        $this->editor_name = 'Custom';
        $this->editor_url = '';
        $this->url_last_version = '';

        $this->hidden = false;
        $this->depends = array();
        $this->requiredby = array();
        $this->phpmin = array(8, 0);
        $this->need_dolibarr_version = array(22, 0);
        $this->langfiles = array('poscore@poscore');

        $this->dirs = array();
        $this->config_page_url = array(
            'settings.php@poscore',
            'terminal_list.php@poscore',
            'cashier_list.php@poscore',
            'receipt_profiles.php@poscore',
            'shifts.php@poscore'
        );

        $this->const = array(
            array('POSCORE_VERSION', 'chaine', '1.0.0', 'POSCORE module version', 0, 'current'),
            array('POSCORE_DEFAULT_TERMINAL_REF_PREFIX', 'chaine', 'TERM', 'Default terminal ref prefix', 0, 'current'),
            array('POSCORE_DEFAULT_CASHIER_REF_PREFIX', 'chaine', 'CASH', 'Default cashier ref prefix', 0, 'current'),
            array('POSCORE_DEFAULT_SHIFT_REF_PREFIX', 'chaine', 'SHIFT', 'Default shift ref prefix', 0, 'current')
        );

        $this->tabs = array();

        $r = 0;
        $this->rights[$r][0] = 105501;
        $this->rights[$r][1] = 'Read POS dashboard';
        $this->rights[$r][4] = 'read';
        $this->rights[$r][3] = 1;
        $this->rights[$r][5] = 'dashboard';
        $r++;

        $this->rights[$r][0] = 105502;
        $this->rights[$r][1] = 'Manage POS administration';
        $this->rights[$r][4] = 'admin';
        $this->rights[$r][3] = 0;
        $this->rights[$r][5] = 'admin';
        $r++;

        $this->rights[$r][0] = 105503;
        $this->rights[$r][1] = 'Operate POS';
        $this->rights[$r][4] = 'operate';
        $this->rights[$r][3] = 0;
        $this->rights[$r][5] = 'operate';
        $r++;

        $this->menu = array();

        $this->menu[] = array(
            'fk_menu'   => '',
            'type'      => 'top',
            'titre'     => 'POS',
            'prefix'    => img_picto('', 'cash-register', 'class="paddingrightonly"'),
            'mainmenu'  => 'poscore',
            'leftmenu'  => '',
            'url'       => '/poscore/pos/dashboard.php',
            'langs'     => 'poscore@poscore',
            'position'  => 1000,
            'enabled'   => '$conf->poscore->enabled',
            'perms'     => '$user->rights->poscore->read',
            'target'    => '',
            'user'      => 2
        );

        $leftMenus = array(
            array('Dashboard', '/poscore/pos/dashboard.php', 'pos_dashboard', '$user->rights->poscore->read'),
            array('Terminals', '/poscore/admin/terminal_list.php', 'pos_terminal', '$user->rights->poscore->admin'),
            array('Cashiers', '/poscore/admin/cashier_list.php', 'pos_cashier', '$user->rights->poscore->admin'),
            array('Shifts', '/poscore/admin/shifts.php', 'pos_shift', '$user->rights->poscore->read'),
            array('ReceiptProfiles', '/poscore/admin/receipt_profiles.php', 'pos_receipt_profiles', '$user->rights->poscore->admin'),
            array('Settings', '/poscore/admin/settings.php', 'pos_settings', '$user->rights->poscore->admin')
        );

        foreach ($leftMenus as $m) {
            $this->menu[] = array(
                'fk_menu'   => 'fk_mainmenu=poscore',
                'type'      => 'left',
                'titre'     => $m[0],
                'mainmenu'  => 'poscore',
                'leftmenu'  => $m[2],
                'url'       => $m[1],
                'langs'     => 'poscore@poscore',
                'position'  => 100,
                'enabled'   => '$conf->poscore->enabled',
                'perms'     => $m[3],
                'target'    => '',
                'user'      => 2,
            );
        }
    }

    public function init($options = '')
    {
        $sql = array();

        $result = $this->_load_tables('/poscore/sql/');
        if ($result < 0) {
            return -1;
        }

        $result = $this->_init($sql, $options);
        if ($result < 0) {
            return -1;
        }

        require_once DOL_DOCUMENT_ROOT . '/custom/poscore/class/service/PosSaasCapabilityRegistrar.php';
        $registrar = new PosSaasCapabilityRegistrar($this->db);
        $reg = $registrar->registerAll();
        if ($reg < 0) {
            $this->error = $registrar->error;
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
