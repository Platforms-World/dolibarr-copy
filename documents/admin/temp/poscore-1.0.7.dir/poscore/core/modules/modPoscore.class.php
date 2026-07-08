<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modPoscore extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;
        $this->numero = 106500;
        $this->rights_class = 'poscore';
        $this->family = 'crm';
        $this->module_position = '50';
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = 'POS Core module controlled by saascore';
        $this->descriptionlong = 'POS terminal child module for Dolibarr SaaS platform controlled by saascore';
        $this->version = '1.0.7';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->picto = 'generic';
        $this->editor_name = 'OpenAI';
        $this->editor_url = 'https://openai.com';
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
        $this->rights[$r][0] = 106501;
        $this->rights[$r][1] = 'View POS';
        $this->rights[$r][4] = 'view';
        $this->rights[$r][5] = '';
        $r++;
        $this->rights[$r][0] = 106502;
        $this->rights[$r][1] = 'Use cashier terminal';
        $this->rights[$r][4] = 'cashier';
        $this->rights[$r][5] = '';
        $r++;
        $this->rights[$r][0] = 106503;
        $this->rights[$r][1] = 'Refund sale';
        $this->rights[$r][4] = 'refund';
        $this->rights[$r][5] = '';
        $r++;
        $this->rights[$r][0] = 106504;
        $this->rights[$r][1] = 'View POS reports';
        $this->rights[$r][4] = 'reports';
        $this->rights[$r][5] = '';
        $r++;
        $this->rights[$r][0] = 106505;
        $this->rights[$r][1] = 'Admin POS';
        $this->rights[$r][4] = 'admin';
        $this->rights[$r][5] = '';
        $r++;

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
        $sqls = array(
            "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."poscore_cart (\n                rowid INTEGER AUTO_INCREMENT PRIMARY KEY,\n                entity INTEGER NOT NULL DEFAULT 1,\n                fk_user INTEGER NOT NULL,\n                fk_product INTEGER NOT NULL,\n                qty DOUBLE NOT NULL DEFAULT 1,\n                price_ht DOUBLE NOT NULL DEFAULT 0,\n                remise_percent DOUBLE NOT NULL DEFAULT 0,\n                tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n                UNIQUE KEY uk_poscore_cart (entity, fk_user, fk_product)\n            ) ENGINE=innodb"
        );

        $result = $this->_init($sqls, $options);
        if ($result <= 0) {
            return $result;
        }

        $this->registerIntoSaascore();
        return $result;
    }

    public function remove($options = '')
    {
        return $this->_remove(array(), $options);
    }

    protected function registerIntoSaascore()
    {
        global $conf;

        $candidates = array(
            DOL_DOCUMENT_ROOT.'/custom/saascore/class/service/SaasRegistryService.php',
            DOL_DOCUMENT_ROOT.'/custom/saascore/class/SaasRegistryService.php',
            DOL_DOCUMENT_ROOT.'/custom/saascore/lib/SaasRegistryService.php'
        );

        $loaded = false;
        foreach ($candidates as $file) {
            if (is_file($file)) {
                require_once $file;
                $loaded = true;
                break;
            }
        }

        if (!$loaded || !class_exists('SaasRegistryService')) {
            dol_syslog(__METHOD__.' saascore registry service not found; skipping registration', LOG_WARNING);
            return 0;
        }

        try {
            $registry = new SaasRegistryService($this->db);

            $this->safeRegistryCall($registry, 'registerModule', array(
                array('module_code' => 'poscore', 'module_label' => 'POS Core', 'entity' => (int) $conf->entity),
                array((int) $conf->entity, 'poscore', 'POS Core')
            ));

            $features = array(
                'pos_terminal'  => 'POS Terminal',
                'create_sale'   => 'Create Sale',
                'refund_sale'   => 'Refund Sale',
                'hold_sale'     => 'Hold Sale',
                'print_receipt' => 'Print Receipt',
                'x_report'      => 'X Report',
                'z_report'      => 'Z Report',
                'multi_cashier' => 'Multi Cashier',
            );
            foreach ($features as $code => $label) {
                $this->safeRegistryCall($registry, 'registerFeature', array(
                    array('module_code' => 'poscore', 'feature_code' => $code, 'feature_label' => $label, 'entity' => (int) $conf->entity),
                    array((int) $conf->entity, 'poscore', $code, $label)
                ));
            }

            $limits = array(
                'max_cashiers'      => 'Max Cashiers',
                'max_terminals'     => 'Max Terminals',
                'max_sales_per_day' => 'Max Sales Per Day',
            );
            foreach ($limits as $code => $label) {
                $this->safeRegistryCall($registry, 'registerLimit', array(
                    array('module_code' => 'poscore', 'limit_code' => $code, 'limit_label' => $label, 'entity' => (int) $conf->entity),
                    array((int) $conf->entity, 'poscore', $code, $label)
                ));
            }

            $permissions = array(
                'poscore.view'    => 'View POS',
                'poscore.cashier' => 'Cashier',
                'poscore.refund'  => 'Refund',
                'poscore.reports' => 'Reports',
                'poscore.admin'   => 'Admin',
            );
            foreach ($permissions as $code => $label) {
                $this->safeRegistryCall($registry, 'registerPermission', array(
                    array('module_code' => 'poscore', 'permission_code' => $code, 'permission_label' => $label, 'entity' => (int) $conf->entity),
                    array((int) $conf->entity, 'poscore', $code, $label)
                ));
            }
        } catch (Exception $e) {
            dol_syslog(__METHOD__.' '.$e->getMessage(), LOG_WARNING);
        }

        return 1;
    }

    protected function safeRegistryCall($registry, $method, array $variants)
    {
        if (!method_exists($registry, $method)) {
            return false;
        }
        foreach ($variants as $args) {
            try {
                call_user_func_array(array($registry, $method), $args);
                return true;
            } catch (Throwable $e) {
                // try next signature
            }
        }
        return false;
    }
}
