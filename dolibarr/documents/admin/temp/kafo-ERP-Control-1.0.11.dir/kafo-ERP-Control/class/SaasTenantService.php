<?php
class SaasTenantService
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function setTenantModule($entityId, $moduleCode, $enabled)
    {
        return $this->upsertToggle('saas_tenant_modules', 'module_code', $entityId, $moduleCode, $enabled);
    }

    public function setTenantFeature($entityId, $featureCode, $enabled)
    {
        return $this->upsertToggle('saas_tenant_features', 'feature_code', $entityId, $featureCode, $enabled);
    }

    public function setTenantLimit($entityId, $limitCode, $value)
    {
        $entityId = (int) $entityId;
        $limitCode = $this->db->escape($limitCode);
        $value = (int) $value;
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."saas_tenant_limits(entity_id, limit_code, value, date_created)
                VALUES (".$entityId.", '".$limitCode."', ".$value.", '".dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')."')
                ON DUPLICATE KEY UPDATE value = VALUES(value)";
        return $this->db->query($sql);
    }

    public function assignBundle($entityId, $bundleCode, $isPrimary = 1)
    {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."saas_tenant_bundles(entity_id, bundle_code, is_primary, date_created)
                VALUES (".(int)$entityId.", '".$this->db->escape($bundleCode)."', ".(int)$isPrimary.", '".dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')."')
                ON DUPLICATE KEY UPDATE is_primary = VALUES(is_primary)";
        return $this->db->query($sql);
    }

    protected function upsertToggle($table, $field, $entityId, $code, $enabled)
    {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX.$table."(entity_id, ".$field.", enabled, date_created)
                VALUES (".(int)$entityId.", '".$this->db->escape($code)."', ".(int)$enabled.", '".dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')."')
                ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)";
        return $this->db->query($sql);
    }
}

