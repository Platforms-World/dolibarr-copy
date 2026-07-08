<?php

class KafoApiTokenService
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getCurrentToken()
    {
        global $conf;
        return isset($conf->global->SAASCORE_API_FIXED_TOKEN) ? trim((string) $conf->global->SAASCORE_API_FIXED_TOKEN) : '';
    }

    public function isEnabled()
    {
        global $conf;
        return !empty($conf->global->SAASCORE_API_ENABLED);
    }

    public function validateToken($token)
    {
        $token = trim((string) $token);
        if ($token === '' || !$this->isEnabled()) {
            return false;
        }

        $current = $this->getCurrentToken();
        if ($current === '') {
            return false;
        }

        return hash_equals($current, $token);
    }

    public function rotateToken($actorUserId = 0)
    {
        require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';

        $token = bin2hex(random_bytes(32));
        $now = dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S');

        $ok1 = dolibarr_set_const($this->db, 'SAASCORE_API_FIXED_TOKEN', $token, 'chaine', 0, 'Kafo ERP Control API bearer token', $this->getEntity());
        $ok2 = dolibarr_set_const($this->db, 'SAASCORE_API_TOKEN_LAST_ROTATED_AT', $now, 'chaine', 0, 'Kafo ERP Control API token last rotated at', $this->getEntity());
        $ok3 = dolibarr_set_const($this->db, 'SAASCORE_API_TOKEN_LAST_ROTATED_BY', (string) ((int) $actorUserId), 'chaine', 0, 'Kafo ERP Control API token last rotated by', $this->getEntity());

        if ($ok1 <= 0 || $ok2 <= 0 || $ok3 <= 0) {
            return false;
        }

        return $token;
    }

    public function touchLastUsed($ip = '')
    {
        require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';

        $now = dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S');
        dolibarr_set_const($this->db, 'SAASCORE_API_TOKEN_LAST_USED_AT', $now, 'chaine', 0, 'Kafo ERP Control API token last used at', $this->getEntity());
        if ($ip !== '') {
            dolibarr_set_const($this->db, 'SAASCORE_API_TOKEN_LAST_USED_IP', $ip, 'chaine', 0, 'Kafo ERP Control API token last used ip', $this->getEntity());
        }
        return true;
    }

    protected function getEntity()
    {
        global $conf;
        return is_object($conf) && isset($conf->entity) ? (int) $conf->entity : 1;
    }
}
