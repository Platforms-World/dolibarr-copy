<?php
class SaasAccessService
{
    protected $db;
    protected $failClosed = true;

    public function __construct($db)
    {
        global $conf;
        $this->db = $db;
        $this->failClosed = !empty($conf->global->SAASCORE_FAIL_CLOSED);
    }

    public function isModuleEnabled($entityId, $moduleCode)
    {
        return $this->getToggle('saas_tenant_modules', 'module_code', $entityId, $moduleCode);
    }

    public function isFeatureEnabled($entityId, $featureCode)
    {
        return $this->getToggle('saas_tenant_features', 'feature_code', $entityId, $featureCode);
    }

    public function getLimit($entityId, $limitCode)
    {
        $entityId = (int) $entityId;
        $limitCode = trim($limitCode);
        if ($limitCode === '') return 0;

        $sql = "SELECT value FROM ".MAIN_DB_PREFIX."saas_tenant_limits WHERE entity_id = ".$entityId." AND limit_code = '".$this->db->escape($limitCode)."'";
        $resql = $this->db->query($sql);
        if ($resql && ($obj = $this->db->fetch_object($resql))) {
            return (int) $obj->value;
        }

        $sql = "SELECT default_value FROM ".MAIN_DB_PREFIX."saas_limits WHERE code = '".$this->db->escape($limitCode)."'";
        $resql = $this->db->query($sql);
        if ($resql && ($obj = $this->db->fetch_object($resql))) {
            return (int) $obj->default_value;
        }

        return $this->failClosed ? 0 : PHP_INT_MAX;
    }

    public function checkLimitExceeded($entityId, $limitCode, $currentValue)
    {
        $limit = $this->getLimit($entityId, $limitCode);
        $currentValue = (int) $currentValue;
        if ($limit <= 0) return false;
        return $currentValue >= $limit;
    }

    public function checkUserPermission($userId, $permissionCode, $entityId = null)
    {
        $decision = $this->getUserPermissionDecision($userId, $permissionCode, $entityId);
        return !empty($decision['allowed']);
    }

    public function getUserPermissionDecision($userId, $permissionCode, $entityId = null)
    {
        global $conf;

        if ($entityId === null) {
            $entityId = (int) $conf->entity;
        }

        $entityId = (int) $entityId;
        $userId = (int) $userId;
        $permissionCode = trim($permissionCode);

        if ($userId <= 0 || $permissionCode === '') {
            return array('allowed' => false, 'source' => 'invalid');
        }

        // Tenant admins bypass SaaS permission checks by design.
        if ($this->isUserAdmin($userId, $entityId)) {
            return array('allowed' => true, 'source' => 'admin');
        }

        // Direct user override has the highest priority (allow or deny).
        $sql = "SELECT allowed
                FROM ".MAIN_DB_PREFIX."saas_user_permissions
                WHERE entity_id = ".$entityId."
                  AND fk_user = ".$userId."
                  AND permission_code = '".$this->db->escape($permissionCode)."'
                LIMIT 1";
        $resql = $this->db->query($sql);
        if ($resql && ($obj = $this->db->fetch_object($resql))) {
            $isAllowed = ((int) $obj->allowed === 1);
            return array('allowed' => $isAllowed, 'source' => ($isAllowed ? 'direct_allow' : 'direct_deny'));
        }

        // Role-based result: deny wins over allow for safer control.
        $sql = "SELECT
                    SUM(CASE WHEN rp.allowed = 1 THEN 1 ELSE 0 END) as allow_count,
                    SUM(CASE WHEN rp.allowed = 0 THEN 1 ELSE 0 END) as deny_count
                FROM ".MAIN_DB_PREFIX."saas_user_roles ur
                INNER JOIN ".MAIN_DB_PREFIX."saas_role_permissions rp
                    ON rp.entity_id = ur.entity_id
                   AND rp.role_code = ur.role_code
                WHERE ur.entity_id = ".$entityId."
                  AND ur.fk_user = ".$userId."
                  AND rp.permission_code = '".$this->db->escape($permissionCode)."'";
        $resql = $this->db->query($sql);
        if ($resql && ($obj = $this->db->fetch_object($resql))) {
            $allowCount = (int) $obj->allow_count;
            $denyCount = (int) $obj->deny_count;

            if ($denyCount > 0) {
                return array('allowed' => false, 'source' => 'role_deny');
            }
            if ($allowCount > 0) {
                return array('allowed' => true, 'source' => 'role_allow');
            }
        }

        return array('allowed' => !$this->failClosed, 'source' => ($this->failClosed ? 'default_deny' : 'default_allow'));
    }

    public function enforceModuleEnabled($entityId, $moduleCode, $message = null)
    {
        if (!$this->isModuleEnabled($entityId, $moduleCode)) {
            accessforbidden($message ?: 'Module entitlement denied');
        }
    }

    public function enforceFeatureEnabled($entityId, $featureCode, $message = null)
    {
        if (!$this->isFeatureEnabled($entityId, $featureCode)) {
            accessforbidden($message ?: 'Feature entitlement denied');
        }
    }

    public function enforceUserPermission($userId, $permissionCode, $message = null)
    {
        if (!$this->checkUserPermission($userId, $permissionCode)) {
            accessforbidden($message ?: 'Permission denied');
        }
    }

    protected function getToggle($table, $field, $entityId, $code)
    {
        $entityId = (int) $entityId;
        $code = trim($code);
        if ($code === '') return false;

        $sql = "SELECT enabled FROM ".MAIN_DB_PREFIX.$table." WHERE entity_id = ".$entityId." AND ".$field." = '".$this->db->escape($code)."'";
        $resql = $this->db->query($sql);
        if ($resql && ($obj = $this->db->fetch_object($resql))) {
            return ((int) $obj->enabled === 1);
        }
        return !$this->failClosed;
    }

    protected function isUserAdmin($userId, $entityId)
    {
        $userId = (int) $userId;
        $entityId = (int) $entityId;
        if ($userId <= 0) return false;

        $sql = "SELECT admin, statut, entity FROM ".MAIN_DB_PREFIX."user WHERE rowid = ".$userId." LIMIT 1";
        $resql = $this->db->query($sql);
        if (!$resql || !($obj = $this->db->fetch_object($resql))) {
            return false;
        }

        if ((int) $obj->statut <= 0) {
            return false;
        }

        if ((int) $obj->admin !== 1) {
            return false;
        }

        $userEntity = (int) $obj->entity;
        return ($userEntity === 0 || $userEntity === $entityId);
    }
}

