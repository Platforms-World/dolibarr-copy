<?php
class SaasRegistryService
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function registerModule($code, $label = '', $description = '', $isCore = 0)
    {
        return $this->upsert('saas_modules', array(
            'code' => $code,
            'label' => $label ?: $code,
            'description' => $description,
            'is_core' => (int) $isCore,
            'date_created' => dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')
        ), 'code');
    }

    public function registerFeature($code, $label = '', $moduleCode = '', $description = '')
    {
        return $this->upsert('saas_features', array(
            'code' => $code,
            'label' => $label ?: $code,
            'module_code' => $moduleCode,
            'description' => $description,
            'date_created' => dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')
        ), 'code');
    }

    public function registerLimit($code, $label = '', $moduleCode = '', $defaultValue = 0, $description = '')
    {
        return $this->upsert('saas_limits', array(
            'code' => $code,
            'label' => $label ?: $code,
            'module_code' => $moduleCode,
            'default_value' => (int) $defaultValue,
            'description' => $description,
            'date_created' => dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')
        ), 'code');
    }

    public function registerPermission($code, $label = '', $moduleCode = '', $description = '')
    {
        return $this->upsert('saas_permissions', array(
            'code' => $code,
            'label' => $label ?: $code,
            'module_code' => $moduleCode,
            'description' => $description,
            'date_created' => dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')
        ), 'code');
    }

    public function registerBundle($code, $label = '', $description = '', $isActive = 1)
    {
        return $this->upsert('saas_bundles', array(
            'code' => $code,
            'label' => $label ?: $code,
            'description' => $description,
            'is_active' => (int) $isActive,
            'date_created' => dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')
        ), 'code');
    }

    protected function upsert($table, array $data, $uniqueField)
    {
        $tableName = MAIN_DB_PREFIX . $table;
        $uniqueValue = $this->db->escape($data[$uniqueField]);
        $sql = "SELECT rowid FROM ".$tableName." WHERE ".$uniqueField." = '".$uniqueValue."'";
        $resql = $this->db->query($sql);
        if ($resql && ($obj = $this->db->fetch_object($resql))) {
            $updates = array();
            foreach ($data as $k => $v) {
                if ($k === $uniqueField || $k === 'date_created') continue;
                $updates[] = $k.' = '.$this->quote($v);
            }
            if (!empty($updates)) {
                $sql = "UPDATE ".$tableName." SET ".implode(', ', $updates)." WHERE rowid = ".(int) $obj->rowid;
                return $this->db->query($sql);
            }
            return true;
        }

        $fields = array_keys($data);
        $values = array();
        foreach ($data as $v) $values[] = $this->quote($v);
        $sql = "INSERT INTO ".$tableName."(".implode(',', $fields).") VALUES (".implode(',', $values).")";
        return $this->db->query($sql);
    }

    protected function quote($value)
    {
        if ($value === null) return 'NULL';
        if (is_int($value) || is_float($value) || ctype_digit((string) $value)) return (string) $value;
        return "'".$this->db->escape($value)."'";
    }
}

