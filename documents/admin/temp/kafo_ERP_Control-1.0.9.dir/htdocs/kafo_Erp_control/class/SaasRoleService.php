<?php
class SaasRoleService
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function createRole($entityId, $code, $label, $description = '', $isSystem = 0)
    {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."saas_roles(entity_id, code, label, description, is_system, date_created)
                VALUES (".(int)$entityId.", '".$this->db->escape($code)."', '".$this->db->escape($label)."', '".$this->db->escape($description)."', ".(int)$isSystem.", '".dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')."')
                ON DUPLICATE KEY UPDATE label = VALUES(label), description = VALUES(description), is_system = VALUES(is_system)";
        return $this->db->query($sql);
    }

    public function setRolePermission($entityId, $roleCode, $permissionCode, $allowed)
    {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."saas_role_permissions(entity_id, role_code, permission_code, allowed)
                VALUES (".(int)$entityId.", '".$this->db->escape($roleCode)."', '".$this->db->escape($permissionCode)."', ".(int)$allowed.")
                ON DUPLICATE KEY UPDATE allowed = VALUES(allowed)";
        return $this->db->query($sql);
    }

    public function assignRoleToUser($entityId, $userId, $roleCode)
    {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."saas_user_roles(entity_id, fk_user, role_code, date_created)
                VALUES (".(int)$entityId.", ".(int)$userId.", '".$this->db->escape($roleCode)."', '".dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')."')
                ON DUPLICATE KEY UPDATE role_code = role_code";
        return $this->db->query($sql);
    }
}
