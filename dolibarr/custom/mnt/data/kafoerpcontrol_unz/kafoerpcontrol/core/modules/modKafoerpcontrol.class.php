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
        $this->version = '1.2.0';
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
            4 => array('SAASCORE_API_ENABLED', 'yesno', '0', 'Enable Kafo ERP Control API', 1, 'current', 1),
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
        $this->rights[$r][1] = 'Manage kafo-ERP-Control API';
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
            'api' => array('API', 975, 'apimanage', '/custom/kafoerpcontrol/admin/api.php'),
            'auditlog' => array('AuditLog', 980, 'read', '/custom/kafoerpcontrol/admin/auditlog.php'),
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

        if (!$this->ensureAuditLogSchema()) {
            return -1;
        }

        return $this->_init($sql, $options);
    }

    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }

    protected function ensureAuditLogSchema()
    {
        $table = MAIN_DB_PREFIX . 'saas_audit_log';

        $sql = "CREATE TABLE IF NOT EXISTS " . $table . " (
            rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
            entity_id INTEGER NOT NULL,
            entity INTEGER NOT NULL DEFAULT 1,
            date_created DATETIME NOT NULL,
            datec DATETIME NOT NULL,
            fk_user INTEGER NULL,
            fk_user_actor INTEGER NULL,
            fk_user_target INTEGER NULL,
            action_code VARCHAR(64) NOT NULL,
            action_type VARCHAR(64) NOT NULL,
            target_type VARCHAR(64) NOT NULL,
            object_type VARCHAR(64) NOT NULL,
            target_code VARCHAR(128) NULL,
            object_key VARCHAR(128) NULL,
            old_value TEXT NULL,
            new_value TEXT NULL,
            description TEXT NULL,
            ip_address VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            context_page VARCHAR(255) NULL,
            extra_json TEXT NULL,
            KEY idx_saas_audit_entity (entity_id),
            KEY idx_saas_audit_user (fk_user),
            KEY idx_saas_audit_actor (fk_user_actor),
            KEY idx_saas_audit_target (fk_user_target),
            KEY idx_saas_audit_action (action_type),
            KEY idx_saas_audit_object (object_type),
            KEY idx_saas_audit_datec (datec)
        ) ENGINE=innodb";

        if (!$this->db->query($sql)) {
            return false;
        }

        $columns = array(
            'entity_id' => 'INTEGER NOT NULL DEFAULT 1',
            'entity' => 'INTEGER NOT NULL DEFAULT 1',
            'date_created' => 'DATETIME NULL',
            'datec' => 'DATETIME NULL',
            'fk_user' => 'INTEGER NULL',
            'fk_user_actor' => 'INTEGER NULL',
            'fk_user_target' => 'INTEGER NULL',
            'action_code' => "VARCHAR(64) NOT NULL DEFAULT ''",
            'action_type' => "VARCHAR(64) NOT NULL DEFAULT ''",
            'target_type' => "VARCHAR(64) NOT NULL DEFAULT ''",
            'object_type' => "VARCHAR(64) NOT NULL DEFAULT ''",
            'target_code' => 'VARCHAR(128) NULL',
            'object_key' => 'VARCHAR(128) NULL',
            'old_value' => 'TEXT NULL',
            'new_value' => 'TEXT NULL',
            'description' => 'TEXT NULL',
            'ip_address' => 'VARCHAR(64) NULL',
            'user_agent' => 'VARCHAR(255) NULL',
            'context_page' => 'VARCHAR(255) NULL',
            'extra_json' => 'TEXT NULL',
        );

        foreach ($columns as $column => $definition) {
            if (!$this->auditColumnExists($table, $column)) {
                $alter = 'ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition;
                if (!$this->db->query($alter)) {
                    return false;
                }
            }
        }

        $indexes = array(
            'idx_saas_audit_actor' => '(fk_user_actor)',
            'idx_saas_audit_target' => '(fk_user_target)',
            'idx_saas_audit_action' => '(action_type)',
            'idx_saas_audit_object' => '(object_type)',
            'idx_saas_audit_datec' => '(datec)',
        );

        foreach ($indexes as $indexName => $indexExpression) {
            if (!$this->auditIndexExists($table, $indexName)) {
                $alter = 'ALTER TABLE ' . $table . ' ADD KEY ' . $indexName . ' ' . $indexExpression;
                if (!$this->db->query($alter)) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function auditColumnExists($table, $column)
    {
        $sql = 'SHOW COLUMNS FROM ' . $table . " LIKE '" . $this->db->escape($column) . "'";
        $resql = $this->db->query($sql);
        if (!$resql) {
            return false;
        }

        return ($this->db->num_rows($resql) > 0);
    }

    protected function auditIndexExists($table, $indexName)
    {
        $sql = 'SHOW INDEX FROM ' . $table . " WHERE Key_name = '" . $this->db->escape($indexName) . "'";
        $resql = $this->db->query($sql);
        if (!$resql) {
            return false;
        }

        return ($this->db->num_rows($resql) > 0);
    }
}


