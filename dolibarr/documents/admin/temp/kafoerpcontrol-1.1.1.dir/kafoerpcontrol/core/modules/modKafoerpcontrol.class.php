<?php
/* Copyright */
include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

class modKafoerpcontrol extends DolibarrModules
{
    public function __construct($db)
    {
        global $conf, $langs;

        $this->db = $db;
        $this->numero = 105500;
        $this->rights_class = 'kafoerpcontrol';

        $this->family = 'platform';
        $this->module_position = '90';
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = 'kafo-ERP-Control entitlement, features, limits and permissions layer';
        $this->descriptionlong = 'kafo-ERP-Control platform layer for multi-tenant Dolibarr';
        $this->version = '1.1.0';
        $this->const_name = 'MAIN_MODULE_KAFOERPCONTROL';
        $this->picto = 'generic';

        $this->special = 0;
        $this->module_parts = array();
        $this->dirs = array();
        $this->hidden = false;

        $this->depends = array();
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->langfiles = array('kafoerpcontrol@kafoerpcontrol');
        $this->config_page_url = array('index.php@kafoerpcontrol');

        $this->phpmin = array(8, 1);
        $this->need_dolibarr_version = array(22, 0);

        $this->const = array(
            1 => array('SAASCORE_ENABLE_BUNDLES', 'yesno', '1', 'Enable bundles support', 1, 'current', 1),
            2 => array('SAASCORE_ENABLE_ROLE_LAYER', 'yesno', '1', 'Enable kafo-ERP-Control role layer', 1, 'current', 1),
            3 => array('SAASCORE_FAIL_CLOSED', 'yesno', '1', 'Fail closed if entitlement is not found', 1, 'current', 1),
            4 => array('SAAS_API_FIXED_TOKEN', 'chaine', '', 'Fixed API token for kafo-ERP-Control API', 0, 'current', 1),
        );

        $r = 0;
        $this->rights = array();
        $this->rights[$r][0] = 105501;
        $this->rights[$r][1] = 'Read kafo-ERP-Control setup';
        $this->rights[$r][4] = 'read';
        $r++;
        $this->rights[$r][0] = 105502;
        $this->rights[$r][1] = 'Manage kafo-ERP-Control registries';
        $this->rights[$r][4] = 'write';
        $r++;
        $this->rights[$r][0] = 105503;
        $this->rights[$r][1] = 'Manage tenant entitlements';
        $this->rights[$r][4] = 'tenantmanage';
        $r++;
        $this->rights[$r][0] = 105504;
        $this->rights[$r][1] = 'Manage kafo-ERP-Control roles and permissions';
        $this->rights[$r][4] = 'rolemanage';
        $r++;
        $this->rights[$r][0] = 105505;
        $this->rights[$r][1] = 'Manage Dolibarr native user rights';
        $this->rights[$r][4] = 'nativerightsmanage';
        $r++;
        $this->rights[$r][0] = 105506;
        $this->rights[$r][1] = 'Manage kafo-ERP-Control API access';
        $this->rights[$r][4] = 'apimanage';
        $r++;

        $this->menu = array();
        $r = 0;
        $this->menu[$r++] = array(
            'fk_menu'   => 'fk_mainmenu=home,fk_leftmenu=setup',
            'type'      => 'left',
            'titre'     => 'kafo-ERP-Control',
            'mainmenu'  => 'home',
            'leftmenu'  => 'saascore',
            'url'       => '/custom/kafoerpcontrol/admin/index.php',
            'langs'     => 'kafoerpcontrol@kafoerpcontrol',
            'position'  => 900,
            'enabled'   => 'isModEnabled("kafoerpcontrol")',
            'perms'     => '$user->admin || $user->hasRight("kafoerpcontrol", "read")',
            'target'    => '',
            'user'      => 0
        );
        $pages = array(
            'tenant' => array('TenantConfiguration', 910, 'tenantmanage', '/custom/kafoerpcontrol/admin/tenant.php'),
            'modules' => array('ModulesCatalog', 920, 'write', '/custom/kafoerpcontrol/admin/modules.php'),
            'features' => array('FeaturesCatalog', 930, 'write', '/custom/kafoerpcontrol/admin/features.php'),
            'limits' => array('LimitsCatalog', 940, 'write', '/custom/kafoerpcontrol/admin/limits.php'),
            'permissions' => array('PermissionsCatalog', 950, 'rolemanage', '/custom/kafoerpcontrol/admin/permissions.php'),
            'permissionscontrol' => array('PermissionsControl', 955, 'nativerightsmanage', '/custom/kafoerpcontrol/admin/permissions_control.php'),
            'bundles' => array('BundlesCatalog', 960, 'write', '/custom/kafoerpcontrol/admin/bundles.php'),
            'roles' => array('RolesCatalog', 970, 'rolemanage', '/custom/kafoerpcontrol/admin/roles.php'),
            'apiaccess' => array('ApiAccess', 980, 'apimanage', '/custom/kafoerpcontrol/admin/api.php'),
        );
        foreach ($pages as $leftmenu => $cfg) {
            $enabled = 'isModEnabled("kafoerpcontrol")';
            if ($leftmenu === 'bundles') $enabled .= ' && !empty($conf->global->SAASCORE_ENABLE_BUNDLES)';
            if ($leftmenu === 'roles') $enabled .= ' && !empty($conf->global->SAASCORE_ENABLE_ROLE_LAYER)';
            $this->menu[$r++] = array(
                'fk_menu'   => 'fk_mainmenu=home,fk_leftmenu=saascore',
                'type'      => 'left',
                'titre'     => $cfg[0],
                'mainmenu'  => 'home',
                'leftmenu'  => 'saascore_'.$leftmenu,
                'url'       => $cfg[3],
                'langs'     => 'kafoerpcontrol@kafoerpcontrol',
                'position'  => $cfg[1],
                'enabled'   => $enabled,
                'perms'     => '$user->admin || $user->hasRight("kafoerpcontrol", "'.$cfg[2].'")',
                'target'    => '',
                'user'      => 0
            );
        }
    }

    public function init($options = '')
    {
        $sql = array();
        $result = $this->_load_tables('/kafoerpcontrol/sql/');
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

        $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."saas_api_keys (
            rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
            entity_id INTEGER NOT NULL,
            label VARCHAR(128) NOT NULL,
            token_prefix VARCHAR(32) NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            can_read TINYINT NOT NULL DEFAULT 1,
            can_write TINYINT NOT NULL DEFAULT 0,
            can_update TINYINT NOT NULL DEFAULT 0,
            is_active TINYINT NOT NULL DEFAULT 1,
            last_used_at DATETIME NULL,
            notes TEXT NULL,
            date_created DATETIME NOT NULL,
            tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_saas_api_keys_hash (token_hash),
            KEY idx_saas_api_keys_entity (entity_id),
            KEY idx_saas_api_keys_prefix (token_prefix)
        ) ENGINE=innodb";

        return $this->_init($sql, $options);
    }

    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }
}