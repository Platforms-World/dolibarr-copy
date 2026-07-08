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
        if (!$this->access->isFeatureEnabled((int) $conf->entity, 'pos_terminal')) {
            accessforbidden('Access forbidden by saascore: feature pos_terminal disabled');
        }
        if (!$this->access->checkUserPermission((int) $user->id, 'poscore.cashier')) {
            accessforbidden('Access forbidden by saascore: missing poscore.cashier');
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
        if (!$this->access->isFeatureEnabled((int) $conf->entity, $featureCode)) {
            $this->jsonError('Feature '.$featureCode.' disabled', 403);
        }
        if (!$this->access->checkUserPermission((int) $user->id, $permissionCode)) {
            $this->jsonError('Missing permission '.$permissionCode, 403);
        }
    }

    public function getLimit($entityId, $limitCode, $default = 0)
    {
        if (!$this->access) return $default;
        return $this->access->getLimit((int) $entityId, $limitCode);
    }

    protected function jsonError($message, $code = 403)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(array('success' => false, 'error' => $message));
        exit;
    }
}
