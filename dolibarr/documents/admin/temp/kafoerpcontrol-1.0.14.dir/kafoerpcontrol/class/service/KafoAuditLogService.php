<?php

class KafoAuditLogService
{
    protected $db;
    protected $table;
    protected static $schemaReady = false;

    public function __construct($db)
    {
        $this->db = $db;
        $this->table = MAIN_DB_PREFIX . 'saas_audit_log';
    }

    public function ensureSchema()
    {
        if (self::$schemaReady) {
            return true;
        }
        if (!is_object($this->db)) {
            return false;
        }

        $sql = "CREATE TABLE IF NOT EXISTS " . $this->table . " (
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
            if (!$this->columnExists($column)) {
                $alter = 'ALTER TABLE ' . $this->table . ' ADD COLUMN ' . $column . ' ' . $definition;
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

        foreach ($indexes as $indexName => $indexExpr) {
            if (!$this->indexExists($indexName)) {
                $alter = 'ALTER TABLE ' . $this->table . ' ADD KEY ' . $indexName . ' ' . $indexExpr;
                if (!$this->db->query($alter)) {
                    return false;
                }
            }
        }

        self::$schemaReady = true;
        return true;
    }

    public function logAction($actorUserId, $targetUserId, $actionType, $objectType, $objectKey, $oldValue = null, $newValue = null, $description = '', $extra = array())
    {
        try {
            if (!$this->ensureSchema()) {
                return false;
            }

            $actionType = trim((string) $actionType);
            $objectType = trim((string) $objectType);
            $objectKey = trim((string) $objectKey);
            if ($actionType === '' || $objectType === '') {
                return false;
            }

            $entityId = $this->getCurrentEntity();
            $now = $this->db->idate(dol_now());
            $ip = $this->getRequestIp();
            $userAgent = $this->getUserAgent();
            $contextPage = $this->getContextPage();
            $oldValue = $this->normalizeValue($oldValue);
            $newValue = $this->normalizeValue($newValue);
            $description = trim((string) $description);
            $extraJson = $this->normalizeExtra($extra);

            $actorUserId = (int) $actorUserId;
            $targetUserId = (int) $targetUserId;

            $sql = 'INSERT INTO ' . $this->table . '(';
            $sql .= 'entity_id, entity, date_created, datec, fk_user, fk_user_actor, fk_user_target,';
            $sql .= 'action_code, action_type, target_type, object_type, target_code, object_key,';
            $sql .= 'old_value, new_value, description, ip_address, user_agent, context_page, extra_json';
            $sql .= ') VALUES (';
            $sql .= ((int) $entityId) . ', ';
            $sql .= ((int) $entityId) . ', ';
            $sql .= "'" . $this->db->escape($now) . "', ";
            $sql .= "'" . $this->db->escape($now) . "', ";
            $sql .= ($actorUserId > 0 ? $actorUserId : 'NULL') . ', ';
            $sql .= ($actorUserId > 0 ? $actorUserId : 'NULL') . ', ';
            $sql .= ($targetUserId > 0 ? $targetUserId : 'NULL') . ', ';
            $sql .= "'" . $this->db->escape($actionType) . "', ";
            $sql .= "'" . $this->db->escape($actionType) . "', ";
            $sql .= "'" . $this->db->escape($objectType) . "', ";
            $sql .= "'" . $this->db->escape($objectType) . "', ";
            $sql .= "'" . $this->db->escape($objectKey) . "', ";
            $sql .= "'" . $this->db->escape($objectKey) . "', ";
            $sql .= $this->quoteNullable($oldValue) . ', ';
            $sql .= $this->quoteNullable($newValue) . ', ';
            $sql .= $this->quoteNullable($description) . ', ';
            $sql .= $this->quoteNullable($ip) . ', ';
            $sql .= $this->quoteNullable($userAgent) . ', ';
            $sql .= $this->quoteNullable($contextPage) . ', ';
            $sql .= $this->quoteNullable($extraJson);
            $sql .= ')';

            return (bool) $this->db->query($sql);
        } catch (Throwable $e) {
            return false;
        }
    }

    public function logLoginSuccess($userId, $login = '', $contextPage = '', $minIntervalSeconds = 900)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return false;
        }

        try {
            if (!$this->ensureSchema()) {
                return false;
            }

            $minIntervalSeconds = max(60, (int) $minIntervalSeconds);
            $sessionKey = 'kafoerpcontrol_login_last_' . $this->getCurrentEntity() . '_' . $userId;
            $lastSessionTs = isset($_SESSION[$sessionKey]) ? (int) $_SESSION[$sessionKey] : 0;
            $nowTs = dol_now();
            if ($lastSessionTs > 0 && ($nowTs - $lastSessionTs) < $minIntervalSeconds) {
                return true;
            }

            $sql = 'SELECT COALESCE(datec, date_created) as lastdate';
            $sql .= ' FROM ' . $this->table;
            $sql .= ' WHERE COALESCE(action_type, action_code) = \'login_success\'';
            $sql .= ' AND COALESCE(fk_user_target, fk_user_actor, fk_user) = ' . $userId;
            $sql .= ' ORDER BY rowid DESC';
            $sql .= ' LIMIT 1';

            $resql = $this->db->query($sql);
            if ($resql && ($obj = $this->db->fetch_object($resql))) {
                $lastDbTs = strtotime((string) $obj->lastdate);
                if ($lastDbTs !== false && ($nowTs - $lastDbTs) < $minIntervalSeconds) {
                    $_SESSION[$sessionKey] = $nowTs;
                    return true;
                }
            }

            $extra = array(
                'status' => 'success',
                'login' => (string) $login,
            );
            if ($contextPage !== '') {
                $extra['source_page'] = (string) $contextPage;
            }

            $result = $this->logAction(
                $userId,
                $userId,
                'login_success',
                'user',
                (string) $userId,
                null,
                'success',
                'User login success: ' . (string) $login,
                $extra
            );

            if ($result) {
                $_SESSION[$sessionKey] = $nowTs;
            }

            return $result;
        } catch (Throwable $e) {
            return false;
        }
    }

    protected function getCurrentEntity()
    {
        global $conf;

        if (is_object($conf) && isset($conf->entity)) {
            return (int) $conf->entity;
        }

        return 1;
    }

    protected function getRequestIp()
    {
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            return (string) $_SERVER['REMOTE_ADDR'];
        }
        return null;
    }

    protected function getUserAgent()
    {
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            return dol_trunc((string) $_SERVER['HTTP_USER_AGENT'], 255, 'right', 'UTF-8', 1);
        }
        return null;
    }

    protected function getContextPage()
    {
        if (!empty($_SERVER['PHP_SELF'])) {
            return (string) $_SERVER['PHP_SELF'];
        }
        return null;
    }

    protected function normalizeExtra($extra)
    {
        if (!is_array($extra) || empty($extra)) {
            return null;
        }

        $encoded = json_encode($extra);
        if ($encoded === false) {
            return null;
        }

        return $encoded;
    }

    protected function normalizeValue($value)
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $encoded = json_encode($value);
        if ($encoded === false) {
            return dol_trunc(print_r($value, true), 65000, 'right', 'UTF-8', 1);
        }

        return $encoded;
    }

    protected function quoteNullable($value)
    {
        if ($value === null || $value === '') {
            return 'NULL';
        }

        return "'" . $this->db->escape((string) $value) . "'";
    }

    protected function columnExists($columnName)
    {
        $sql = 'SHOW COLUMNS FROM ' . $this->table . " LIKE '" . $this->db->escape($columnName) . "'";
        $resql = $this->db->query($sql);
        if (!$resql) {
            return false;
        }

        return ($this->db->num_rows($resql) > 0);
    }

    protected function indexExists($indexName)
    {
        $sql = 'SHOW INDEX FROM ' . $this->table . " WHERE Key_name = '" . $this->db->escape($indexName) . "'";
        $resql = $this->db->query($sql);
        if (!$resql) {
            return false;
        }

        return ($this->db->num_rows($resql) > 0);
    }
}


