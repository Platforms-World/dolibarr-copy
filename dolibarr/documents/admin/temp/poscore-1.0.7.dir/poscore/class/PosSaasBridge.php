<?php
class PosSaasBridge
{
    protected $db;
    protected $access;

    public function __construct($db)
    {
        $this->db = $db;
        $this->access = $this->loadAccessService($db);
    }

    protected function loadAccessService($db)
    {
        $candidates = array(
            DOL_DOCUMENT_ROOT.'/custom/saascore/class/service/SaasAccessService.php',
            DOL_DOCUMENT_ROOT.'/custom/saascore/class/SaasAccessService.php',
            DOL_DOCUMENT_ROOT.'/custom/saascore/lib/SaasAccessService.php'
        );
        foreach ($candidates as $file) {
            if (is_file($file)) {
                require_once $file;
                if (class_exists('SaasAccessService')) {
                    return new SaasAccessService($db);
                }
            }
        }
        return null;
    }

    public function ensureTerminalAccess($conf, $user)
    {
        if (empty($user->id)) {
            accessforbidden();
        }
        if (!$this->access) {
            accessforbidden('saascore access service not found');
        }
        if (!$this->isModuleEnabled((int) $conf->entity, 'poscore')) {
            accessforbidden('POS module is not enabled for this tenant');
        }
        if (!$this->isFeatureEnabled((int) $conf->entity, 'poscore', 'pos_terminal')) {
            accessforbidden('POS terminal feature is not enabled');
        }
        if (!$this->hasPermission((int) $conf->entity, (int) $user->id, 'poscore.cashier')) {
            accessforbidden('Access forbidden by saascore: missing poscore.cashier');
        }
        return true;
    }

    public function ensureAjaxAccess($conf, $user, $feature = 'pos_terminal', $permission = 'poscore.cashier')
    {
        if (empty($user->id)) {
            $this->jsonError('Authentication required', 401);
        }
        if (!checkValideToken('', '', 'post') && !checkValideToken('', '', 'get')) {
            $this->jsonError('Invalid CSRF token', 403);
        }
        if (!$this->access) {
            $this->jsonError('saascore access service not found', 403);
        }
        if (!$this->isModuleEnabled((int) $conf->entity, 'poscore')) {
            $this->jsonError('Module disabled', 403);
        }
        if (!$this->isFeatureEnabled((int) $conf->entity, 'poscore', $feature)) {
            $this->jsonError('Feature disabled', 403);
        }
        if (!$this->hasPermission((int) $conf->entity, (int) $user->id, $permission)) {
            $this->jsonError('Permission denied by saascore', 403);
        }
    }

    public function getLimitValue($entity, $limitCode, $default = null)
    {
        if (!$this->access || !method_exists($this->access, 'getLimitValue')) {
            return $default;
        }
        try {
            return $this->access->getLimitValue((int) $entity, 'poscore', $limitCode, $default);
        } catch (Throwable $e) {
            return $default;
        }
    }

    protected function isModuleEnabled($entity, $moduleCode)
    {
        return $this->callBoolean('isModuleEnabled', array(array($entity, $moduleCode)));
    }

    protected function isFeatureEnabled($entity, $moduleCode, $featureCode)
    {
        return $this->callBoolean('isFeatureEnabled', array(array($entity, $moduleCode, $featureCode)));
    }

    protected function hasPermission($entity, $userId, $permissionCode)
    {
        return $this->callBoolean('hasPermission', array(array($entity, $userId, $permissionCode)));
    }

    protected function callBoolean($method, array $variants)
    {
        if (!$this->access || !method_exists($this->access, $method)) {
            return false;
        }
        foreach ($variants as $args) {
            try {
                return (bool) call_user_func_array(array($this->access, $method), $args);
            } catch (Throwable $e) {
            }
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
