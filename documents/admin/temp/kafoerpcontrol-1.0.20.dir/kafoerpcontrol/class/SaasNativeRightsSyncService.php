<?php
class SaasNativeRightsSyncService
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public static function buildNativePermissionCode($module, $perms, $subperms, $rightId)
    {
        $parts = array();
        $module = trim((string) $module);
        $perms = trim((string) $perms);
        $subperms = trim((string) $subperms);
        if ($module !== '') $parts[] = $module;
        if ($perms !== '') $parts[] = $perms;
        if ($subperms !== '') $parts[] = $subperms;
        if (empty($parts)) return 'native.right_' . ((int) $rightId);
        return 'native.' . implode('.', $parts);
    }

    protected function getAvailableNativeRights($entityId)
    {
        $rightsByCode = array();
        $rightsById = array();
        $sql = 'SELECT id, module, perms, subperms';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'rights_def';
        $sql .= ' WHERE entity IN (0, ' . ((int) $entityId) . ')';
        $resql = $this->db->query($sql);
        while ($resql && ($obj = $this->db->fetch_object($resql))) {
            $id = (int) $obj->id;
            $code = self::buildNativePermissionCode($obj->module, $obj->perms, $obj->subperms, $id);
            $rightsByCode[$code] = $id;
            $rightsById[$id] = 1;
        }
        return array($rightsByCode, $rightsById);
    }

    protected function getDesiredRightIdsForUser($entityId, $userId, array $rightsByCode)
    {
        $wanted = array();
        if ($userId <= 0 || empty($rightsByCode)) return $wanted;

        $sql = 'SELECT DISTINCT srp.permission_code';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'saas_user_roles as sur';
        $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'saas_role_permissions as srp';
        $sql .= ' ON srp.entity_id = sur.entity_id AND srp.role_code = sur.role_code';
        $sql .= ' WHERE sur.entity_id = ' . ((int) $entityId);
        $sql .= ' AND sur.fk_user = ' . ((int) $userId);
        $sql .= ' AND srp.allowed = 1';
        $sql .= " AND srp.permission_code LIKE 'native.%'";
        $resql = $this->db->query($sql);
        while ($resql && ($obj = $this->db->fetch_object($resql))) {
            $code = trim((string) $obj->permission_code);
            if ($code !== '' && isset($rightsByCode[$code])) {
                $wanted[(int) $rightsByCode[$code]] = 1;
            }
        }

        return $wanted;
    }

    public function syncUser($entityId, $userId)
    {
        $entityId = (int) $entityId;
        $userId = (int) $userId;
        if ($entityId <= 0 || $userId <= 0) return true;

        list($rightsByCode, $rightsById) = $this->getAvailableNativeRights($entityId);
        if (empty($rightsById)) return true;

        $desired = $this->getDesiredRightIdsForUser($entityId, $userId, $rightsByCode);

        $this->db->begin();
        $ok = true;

        $sql = 'DELETE FROM ' . MAIN_DB_PREFIX . 'user_rights';
        $sql .= ' WHERE fk_user = ' . $userId;
        $sql .= ' AND fk_id IN (' . implode(',', array_keys($rightsById)) . ')';
        if (!$this->db->query($sql)) {
            $ok = false;
        }

        if ($ok && !empty($desired)) {
            $now = dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S');
            foreach (array_keys($desired) as $rightId) {
                $sql = 'INSERT INTO ' . MAIN_DB_PREFIX . 'user_rights(entity, fk_user, fk_id) VALUES (' . $entityId . ', ' . $userId . ', ' . ((int) $rightId) . ')';
                if (!$this->db->query($sql)) {
                    $ok = false;
                    break;
                }
            }
        }

        if ($ok) {
            $this->db->commit();
            return true;
        }

        $this->db->rollback();
        return false;
    }

    public function syncUsers($entityId, array $userIds)
    {
        $done = array();
        foreach ($userIds as $userId) {
            $userId = (int) $userId;
            if ($userId <= 0 || isset($done[$userId])) continue;
            $done[$userId] = 1;
            if (!$this->syncUser($entityId, $userId)) return false;
        }
        return true;
    }
}
