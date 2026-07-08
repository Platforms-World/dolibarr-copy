<?php
class SaasApiAuthService
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function generateToken($length = 64)
    {
        $length = max(16, (int) $length);
        if ($length % 2 !== 0) {
            $length++;
        }
        return bin2hex(random_bytes((int) ($length / 2)));
    }

    public function createToken($entityId, $label, $plainToken, $canRead, $canWrite, $canUpdate, $notes = '')
    {
        $entityId = (int) $entityId;
        $label = trim((string) $label);
        $plainToken = trim((string) $plainToken);

        if ($entityId <= 0 || $label === '' || $plainToken === '') {
            return false;
        }

        $hash = password_hash($plainToken, PASSWORD_DEFAULT);
        $prefix = substr($plainToken, 0, 12);
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."saas_api_keys(entity_id,label,token_prefix,token_hash,can_read,can_write,can_update,is_active,notes,date_created) VALUES ("
            .$entityId.", '".$this->db->escape($label)."', '".$this->db->escape($prefix)."', '".$this->db->escape($hash)."', "
            .((int) $canRead).", ".((int) $canWrite).", ".((int) $canUpdate).", 1, '".$this->db->escape($notes)."', '".dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')."')";
        return $this->db->query($sql);
    }

    public function deleteToken($entityId, $rowid)
    {
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."saas_api_keys WHERE rowid = ".((int) $rowid)." AND entity_id = ".((int) $entityId);
        return $this->db->query($sql);
    }

    public function getTokens($entityId)
    {
        $rows = array();
        $sql = "SELECT rowid, label, token_prefix, can_read, can_write, can_update, is_active, last_used_at, notes, date_created"
             ." FROM ".MAIN_DB_PREFIX."saas_api_keys WHERE entity_id = ".((int) $entityId)." ORDER BY rowid DESC";
        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $rows[] = $obj;
            }
        }
        return $rows;
    }

    public function authenticate($entityId, $plainToken)
    {
        global $conf;

        $entityId = (int) $entityId;
        $plainToken = trim((string) $plainToken);
        if ($plainToken === '') {
            return null;
        }

        if (!empty($conf->global->SAAS_API_FIXED_TOKEN) && hash_equals((string) $conf->global->SAAS_API_FIXED_TOKEN, $plainToken)) {
            return array(
                'type' => 'fixed',
                'label' => 'Fixed token',
                'can_read' => 1,
                'can_write' => 1,
                'can_update' => 1,
            );
        }

        $sql = "SELECT rowid, label, token_hash, can_read, can_write, can_update, is_active FROM ".MAIN_DB_PREFIX."saas_api_keys WHERE entity_id = ".$entityId." AND is_active = 1";
        $resql = $this->db->query($sql);
        if (!$resql) {
            return null;
        }

        while ($obj = $this->db->fetch_object($resql)) {
            if (password_verify($plainToken, (string) $obj->token_hash)) {
                $this->db->query("UPDATE ".MAIN_DB_PREFIX."saas_api_keys SET last_used_at = '".dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S')."' WHERE rowid = ".((int) $obj->rowid));
                return array(
                    'type' => 'generated',
                    'rowid' => (int) $obj->rowid,
                    'label' => $obj->label,
                    'can_read' => (int) $obj->can_read,
                    'can_write' => (int) $obj->can_write,
                    'can_update' => (int) $obj->can_update,
                );
            }
        }

        return null;
    }
}
