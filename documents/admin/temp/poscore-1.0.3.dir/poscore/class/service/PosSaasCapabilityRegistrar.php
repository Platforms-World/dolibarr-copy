<?php

class PosSaasCapabilityRegistrar
{
    public $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    private function loadRegistry()
    {
        $paths = array(
            DOL_DOCUMENT_ROOT.'/custom/saascore/class/service/SaasRegistryService.php',
            DOL_DOCUMENT_ROOT.'/custom/saascore/class/services/SaasRegistryService.php',
            DOL_DOCUMENT_ROOT.'/custom/saascore/class/SaasRegistryService.php',
            DOL_DOCUMENT_ROOT.'/custom/saascore/lib/SaasRegistryService.php'
        );

        foreach ($paths as $p) {
            if (file_exists($p)) {
                require_once $p;
            }
        }

        if (class_exists('SaasRegistryService')) {
            return new SaasRegistryService($this->db);
        }

        return null;
    }

    public function registerAll()
    {
        $svc = $this->loadRegistry();
        if (!$svc) return;

        if (method_exists($svc, 'registerModule')) {
            $svc->registerModule('poscore', 'POS Management', 'Point of sale management module', 0);
        }

        $features = array(
            'multi_terminal'    => array('Multi Terminal', 'Allow more than one POS terminal for a tenant'),
            'multi_cashier'     => array('Multi Cashier', 'Allow more than one cashier for a tenant'),
            'shift_management'  => array('Shift Management', 'Open and close cashier shifts'),
            'refund_sales'      => array('Refund Sales', 'Allow refunds and reverse POS transactions')
        );

        foreach ($features as $code => $meta) {
            if (method_exists($svc, 'registerFeature')) {
                $svc->registerFeature($code, $meta[0], 'poscore', $meta[1]);
            }
        }

        $limits = array(
            'max_terminals' => array('Maximum Terminals', 1, 'Maximum allowed POS terminals for the tenant'),
            'max_cashiers'  => array('Maximum Cashiers', 1, 'Maximum allowed POS cashiers for the tenant')
        );

        foreach ($limits as $code => $meta) {
            if (method_exists($svc, 'registerLimit')) {
                $svc->registerLimit($code, $meta[0], 'poscore', (int) $meta[1], $meta[2]);
            }
        }

        $perms = array(
            'view_pos_dashboard' => array('View POS Dashboard', 'Open the POS dashboard'),
            'create_terminal'    => array('Create Terminal', 'Create and manage POS terminals'),
            'create_cashier'     => array('Create Cashier', 'Create and manage POS cashiers'),
            'open_shift'         => array('Open Shift', 'Open and close cashier shifts')
        );

        foreach ($perms as $code => $meta) {
            if (method_exists($svc, 'registerPermission')) {
                $svc->registerPermission($code, $meta[0], 'poscore', $meta[1]);
            }
        }

        $this->cleanupBrokenLegacyRows();
        $this->bootstrapCurrentEntity();
    }

    private function cleanupBrokenLegacyRows()
    {
        $this->db->query("DELETE FROM ".MAIN_DB_PREFIX."saas_features WHERE code = 'poscore' AND (module_code IS NULL OR module_code = '')");
        $this->db->query("DELETE FROM ".MAIN_DB_PREFIX."saas_limits WHERE code = 'poscore' AND (module_code IS NULL OR module_code = '')");
        $this->db->query("DELETE FROM ".MAIN_DB_PREFIX."saas_permissions WHERE code = 'poscore' AND (module_code IS NULL OR module_code = '')");
    }

    private function bootstrapCurrentEntity()
    {
        global $conf, $user;

        $entityId = (int) $conf->entity;
        if ($entityId <= 0) return;

        $now = dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S');
        $roleCode = 'POS_MANAGER';

        $this->upsertToggle('saas_tenant_modules', 'module_code', 'poscore', array(
            'entity_id' => $entityId,
            'module_code' => 'poscore',
            'enabled' => 1,
            'date_created' => $now
        ));

        $this->upsertToggle('saas_tenant_features', 'feature_code', 'multi_terminal', array(
            'entity_id' => $entityId,
            'feature_code' => 'multi_terminal',
            'enabled' => 1,
            'date_created' => $now
        ));
        $this->upsertToggle('saas_tenant_features', 'feature_code', 'multi_cashier', array(
            'entity_id' => $entityId,
            'feature_code' => 'multi_cashier',
            'enabled' => 1,
            'date_created' => $now
        ));
        $this->upsertToggle('saas_tenant_features', 'feature_code', 'shift_management', array(
            'entity_id' => $entityId,
            'feature_code' => 'shift_management',
            'enabled' => 1,
            'date_created' => $now
        ));

        $this->upsertLimit($entityId, 'max_terminals', 1, $now);
        $this->upsertLimit($entityId, 'max_cashiers', 1, $now);

        $this->upsertComposite('saas_roles', array(
            'entity_id' => $entityId,
            'code' => $roleCode,
            'label' => 'POS Manager',
            'description' => 'System role for POS access',
            'is_system' => 1,
            'date_created' => $now
        ), array('entity_id', 'code'));

        $permissions = array('view_pos_dashboard', 'create_terminal', 'create_cashier', 'open_shift');
        foreach ($permissions as $perm) {
            $this->upsertComposite('saas_role_permissions', array(
                'entity_id' => $entityId,
                'role_code' => $roleCode,
                'permission_code' => $perm,
                'allowed' => 1
            ), array('entity_id', 'role_code', 'permission_code'));
        }

        if (!empty($user->id)) {
            $this->upsertComposite('saas_user_roles', array(
                'entity_id' => $entityId,
                'fk_user' => (int) $user->id,
                'role_code' => $roleCode,
                'date_created' => $now
            ), array('entity_id', 'fk_user', 'role_code'));
        }
    }

    private function upsertLimit($entityId, $code, $value, $now)
    {
        $this->upsertComposite('saas_tenant_limits', array(
            'entity_id' => (int) $entityId,
            'limit_code' => $code,
            'value' => (int) $value,
            'date_created' => $now
        ), array('entity_id', 'limit_code'));
    }

    private function upsertToggle($table, $codeField, $codeValue, array $data)
    {
        $where = array(
            'entity_id' => (int) $data['entity_id'],
            $codeField => $codeValue
        );
        $this->upsertByWhere($table, $data, $where);
    }

    private function upsertComposite($table, array $data, array $uniqueFields)
    {
        $where = array();
        foreach ($uniqueFields as $field) {
            $where[$field] = $data[$field];
        }
        $this->upsertByWhere($table, $data, $where);
    }

    private function upsertByWhere($table, array $data, array $where)
    {
        $tableName = MAIN_DB_PREFIX.$table;
        $sql = "SELECT rowid FROM ".$tableName." WHERE ".$this->buildWhere($where);
        $resql = $this->db->query($sql);
        if ($resql && ($obj = $this->db->fetch_object($resql))) {
            $updates = array();
            foreach ($data as $k => $v) {
                if ($k === 'date_created') continue;
                $updates[] = $k.' = '.$this->quote($v);
            }
            if (!empty($updates)) {
                $this->db->query("UPDATE ".$tableName." SET ".implode(', ', $updates)." WHERE rowid = ".((int) $obj->rowid));
            }
            return;
        }

        $fields = array_keys($data);
        $values = array();
        foreach ($data as $v) {
            $values[] = $this->quote($v);
        }
        $this->db->query("INSERT INTO ".$tableName."(".implode(',', $fields).") VALUES (".implode(',', $values).")");
    }

    private function buildWhere(array $where)
    {
        $parts = array();
        foreach ($where as $k => $v) {
            $parts[] = $k.' = '.$this->quote($v);
        }
        return implode(' AND ', $parts);
    }

    private function quote($value)
    {
        if ($value === null) return 'NULL';
        if (is_int($value) || is_float($value) || ctype_digit((string) $value)) return (string) $value;
        return "'".$this->db->escape($value)."'";
    }
}
