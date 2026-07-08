<?php
/* Copyright */
include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

class modSaascore extends DolibarrModules
{
    public function __construct($db)
    {
        global $conf, $langs;

        $this->db = $db;
        $this->numero = 105500;
        $this->rights_class = 'saascore';

        $this->family = 'platform';
        $this->module_position = '90';
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = 'kafo_ERP_Control entitlement, features, limits and permissions layer';
        $this->descriptionlong = 'kafo_ERP_Control platform layer for multi-tenant Dolibarr';
        $this->version = '1.0.9';
        $this->const_name = 'MAIN_MODULE_SAASCORE';
        $this->picto = 'generic';

        $this->special = 0;
        $this->module_parts = array();
        $this->dirs = array();
        $this->hidden = false;

        $this->depends = array();
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->langfiles = array('saascore@kafo_ERP_Control');
        $this->config_page_url = array('index.php@kafo_ERP_Control');

        $this->phpmin = array(8, 1);
        $this->need_dolibarr_version = array(22, 0);

        $this->const = array(
            1 => array('SAASCORE_ENABLE_BUNDLES', 'yesno', '1', 'Enable bundles support', 1, 'current', 1),
            2 => array('SAASCORE_ENABLE_ROLE_LAYER', 'yesno', '1', 'Enable kafo_ERP_Control role layer', 1, 'current', 1),
            3 => array('SAASCORE_FAIL_CLOSED', 'yesno', '1', 'Fail closed if entitlement is not found', 1, 'current', 1),
        );

        $r = 0;
        $this->rights = array();
        $this->rights[$r][0] = 105501;
        $this->rights[$r][1] = 'Read kafo_ERP_Control setup';
        $this->rights[$r][4] = 'read';
        $r++;
        $this->rights[$r][0] = 105502;
        $this->rights[$r][1] = 'Manage kafo_ERP_Control registries';
        $this->rights[$r][4] = 'write';
        $r++;
        $this->rights[$r][0] = 105503;
        $this->rights[$r][1] = 'Manage tenant entitlements';
        $this->rights[$r][4] = 'tenantmanage';
        $r++;
        $this->rights[$r][0] = 105504;
        $this->rights[$r][1] = 'Manage kafo_ERP_Control roles and permissions';
        $this->rights[$r][4] = 'rolemanage';
        $r++;

        $this->menu = array();
        $r = 0;
        $this->menu[$r++] = array(
            'fk_menu'   => 'fk_mainmenu=home,fk_leftmenu=setup',
            'type'      => 'left',
            'titre'     => 'SaaSCoreSetup',
            'mainmenu'  => 'home',
            'leftmenu'  => 'saascore',
            'url'       => '/kafo_ERP_Control/admin/index.php',
            'langs'     => 'saascore@kafo_ERP_Control',
            'position'  => 900,
            'enabled'   => 'isModEnabled("saascore")',
            'perms'     => '$user->admin || $user->hasRight("saascore", "read")',
            'target'    => '',
            'user'      => 0
        );
        $pages = array(
            'tenant' => array('TenantConfiguration', 910, 'tenantmanage', '/kafo_ERP_Control/admin/tenant.php'),
            'modules' => array('ModulesCatalog', 920, 'write', '/kafo_ERP_Control/admin/modules.php'),
            'features' => array('FeaturesCatalog', 930, 'write', '/kafo_ERP_Control/admin/features.php'),
            'limits' => array('LimitsCatalog', 940, 'write', '/kafo_ERP_Control/admin/limits.php'),
            'permissions' => array('PermissionsCatalog', 950, 'rolemanage', '/kafo_ERP_Control/admin/permissions.php'),
            'bundles' => array('BundlesCatalog', 960, 'write', '/kafo_ERP_Control/admin/bundles.php'),
            'roles' => array('RolesCatalog', 970, 'rolemanage', '/kafo_ERP_Control/admin/roles.php'),
        );
        foreach ($pages as $leftmenu => $cfg) {
            $enabled = 'isModEnabled("saascore")';
            if ($leftmenu === 'bundles') $enabled .= ' && !empty($conf->global->SAASCORE_ENABLE_BUNDLES)';
            if ($leftmenu === 'roles') $enabled .= ' && !empty($conf->global->SAASCORE_ENABLE_ROLE_LAYER)';
            $this->menu[$r++] = array(
                'fk_menu'   => 'fk_mainmenu=home,fk_leftmenu=saascore',
                'type'      => 'left',
                'titre'     => $cfg[0],
                'mainmenu'  => 'home',
                'leftmenu'  => 'saascore_'.$leftmenu,
                'url'       => $cfg[3],
                'langs'     => 'saascore@kafo_ERP_Control',
                'position'  => $cfg[1],
                'enabled'   => $enabled,
                'perms'     => '$user->admin || $user->hasRight("saascore", "'.$cfg[2].'")',
                'target'    => '',
                'user'      => 0
            );
        }
    }

    public function init($options = '')
    {
        $sql = array();
        $result = $this->_load_tables('/kafo_ERP_Control/sql/');
        if ($result < 0) {
            return -1;
        }

        $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."saas_user_permissions (
            rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
            entity_id INTEGER NOT NULL,
            fk_user INTEGER NOT NULL,
            permission_code VARCHAR(64) NOT NULL,
            allowed TINYINT NOT NULL DEFAULT 0,
            date_created DATETIME NOT NULL,
            tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_saas_user_permissions (entity_id, fk_user, permission_code),
            KEY idx_saas_user_permissions_entity (entity_id),
            KEY idx_saas_user_permissions_user (fk_user)
        ) ENGINE=innodb";

        return $this->_init($sql, $options);
    }

    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }
}



