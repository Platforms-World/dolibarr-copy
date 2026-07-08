<?php
class PosSaasBridge
{
    protected $db;
    protected $access;

    public function __construct($db)
    {
        $this->db = $db;
        $file = DOL_DOCUMENT_ROOT.'/custom/saascore/class/SaasAccessService.php';
        if (is_file($file)) {
            require_once $file;
            if (class_exists('SaasAccessService')) {
                $this->access = new SaasAccessService($db);
            }
        }
    }

    public function ensureTerminalAccess($conf, $user)
    {
        if (empty($user->id)) accessforbidden('Login required');
        if (!$this->access) accessforbidden('saascore access service not found');
        if (!$this->access->isModuleEnabled((int) $conf->entity, 'poscore')) {
            accessforbidden('Access forbidden by saascore: module poscore disabled');
        }

        $feature = $this->findEnabledFeature((int) $conf->entity, array(
            'pos_terminal',
            'view_pos_dashboard',
            'create_terminal',
            'open_shift',
            'multi_terminal'
        ));
        if (!$feature) {
            accessforbidden('Access forbidden by saascore: no compatible POS feature enabled');
        }

        $permission = $this->findGrantedPermission((int) $conf->entity, (int) $user->id, array(
            'poscore.cashier',
            'view_pos_dashboard',
            'open_shift',
            'create_terminal',
            'create_cashier'
        ));
        if (!$permission) {
            accessforbidden('Access forbidden by saascore: missing compatible cashier permission');
        }
    }

    public function ensureAjaxAccess($conf, $user, $featureCode = 'pos_terminal', $permissionCode = 'poscore.cashier')
    {
        if (empty($user->id)) $this->jsonError('Authentication required', 401);
        if (!checkValideToken('', '', 'post') && !checkValideToken('', '', 'get')) {
            $this->jsonError('Invalid CSRF token', 403);
        }
        if (!$this->access) $this->jsonError('saascore access service not found', 403);
        if (!$this->access->isModuleEnabled((int) $conf->entity, 'poscore')) {
            $this->jsonError('Module poscore disabled', 403);
        }

        $featureCandidates = array_unique(array_filter(array(
            $featureCode,
            'pos_terminal',
            'view_pos_dashboard',
            'create_terminal',
            'open_shift',
            'multi_terminal'
        )));
        if (!$this->findEnabledFeature((int) $conf->entity, $featureCandidates)) {
            $this->jsonError('No compatible POS feature enabled in saascore', 403);
        }

        $permissionCandidates = array_unique(array_filter(array(
            $permissionCode,
            'poscore.cashier',
            'view_pos_dashboard',
            'open_shift',
            'create_terminal',
            'create_cashier'
        )));
        if (!$this->findGrantedPermission((int) $conf->entity, (int) $user->id, $permissionCandidates)) {
            $this->jsonError('No compatible POS permission granted in saascore', 403);
        }
    }

    public function getLimit($entityId, $limitCode, $default = 0)
    {
        if (!$this->access) return $default;
        $value = $this->access->getLimit((int) $entityId, $limitCode);
        return ($value === null || $value === '') ? $default : $value;
    }

    protected function findEnabledFeature($entityId, array $featureCodes)
    {
        foreach ($featureCodes as $code) {
            if ($code !== '' && $this->access->isFeatureEnabled((int) $entityId, $code)) {
                return $code;
            }
        }
        return false;
    }

    protected function findGrantedPermission($entityId, $userId, array $permissionCodes)
    {
        foreach ($permissionCodes as $code) {
            if ($code === '') continue;
            if ($this->access->checkUserPermission((int) $userId, $code)) {
                return $code;
            }
            if ($this->checkDirectUserPermission((int) $entityId, (int) $userId, $code)) {
                return $code;
            }
        }
        return false;
    }

    protected function checkDirectUserPermission($entityId, $userId, $permissionCode)
    {
        $sql = "SELECT allowed
"
             . " FROM ".MAIN_DB_PREFIX."saas_user_permissions
"
             . " WHERE entity_id = ".((int) $entityId)
             . " AND fk_user = ".((int) $userId)
             . " AND permission_code = '".$this->db->escape($permissionCode)."'
"
             . " LIMIT 1";

        $resql = $this->db->query($sql);
        if ($resql && ($obj = $this->db->fetch_object($resql))) {
            return ((int) $obj->allowed === 1);
        }
        return false;
    }

    protected function jsonError($message, $code = 403)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(array('success' => false, 'error' => $message));
        exit;
    }
}
