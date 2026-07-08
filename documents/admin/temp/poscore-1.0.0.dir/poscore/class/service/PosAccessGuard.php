<?php

class PosAccessGuard
{
    protected $db;
    protected $user;
    protected $conf;

    public $error = '';
    public $lastReason = '';

    const MODULE_CODE = 'poscore';

    public function __construct($db, $user, $conf)
    {
        $this->db = $db;
        $this->user = $user;
        $this->conf = $conf;
    }

    protected function getEntityId()
    {
        return (int) (!empty($this->conf->entity) ? $this->conf->entity : 1);
    }

    protected function getAccessService()
    {
        $path = DOL_DOCUMENT_ROOT . '/custom/saascore/class/service/SaasAccessService.php';
        if (!file_exists($path)) {
            throw new Exception('saascore access service not found at ' . $path);
        }
        require_once $path;
        return new SaasAccessService($this->db);
    }

    protected function getValueByPath($root, $path)
    {
        $segments = explode('->', $path);
        $cursor = $root;
        foreach ($segments as $segment) {
            if (!isset($cursor->{$segment})) {
                return null;
            }
            $cursor = $cursor->{$segment};
        }
        return $cursor;
    }

    public function isModuleEnabled()
    {
        try {
            $svc = $this->getAccessService();
            return (bool) $svc->isModuleEnabled($this->getEntityId(), self::MODULE_CODE);
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            $this->lastReason = 'module_check_failed';
            return false;
        }
    }

    public function hasFeature($featureCode)
    {
        try {
            $svc = $this->getAccessService();
            return (bool) $svc->isFeatureEnabled($this->getEntityId(), $featureCode, self::MODULE_CODE);
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            $this->lastReason = 'feature_check_failed';
            return false;
        }
    }

    public function hasPermission($permissionCode)
    {
        try {
            $svc = $this->getAccessService();
            return (bool) $svc->checkUserPermission($this->user->id, $permissionCode, $this->getEntityId(), self::MODULE_CODE);
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            $this->lastReason = 'permission_check_failed';
            return false;
        }
    }

    public function limitAvailable($limitCode, $currentValue)
    {
        try {
            $svc = $this->getAccessService();
            $exceeded = $svc->checkLimitExceeded($this->getEntityId(), $limitCode, $currentValue, self::MODULE_CODE);
            return !$exceeded;
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            $this->lastReason = 'limit_check_failed';
            return false;
        }
    }

    public function hasDolibarrRight($path)
    {
        if (empty($path)) {
            return true;
        }

        $v = $this->getValueByPath($this->user->rights, $path);
        return !empty($v);
    }

    public function can(array $rules)
    {
        if (!$this->isModuleEnabled()) {
            $this->lastReason = 'module_disabled';
            return false;
        }

        if (!empty($rules['dol_right']) && !$this->hasDolibarrRight($rules['dol_right'])) {
            $this->lastReason = 'dolibarr_right_denied';
            return false;
        }

        if (!empty($rules['permission']) && !$this->hasPermission($rules['permission'])) {
            $this->lastReason = 'permission_denied';
            return false;
        }

        if (!empty($rules['feature']) && !$this->hasFeature($rules['feature'])) {
            $this->lastReason = 'feature_disabled';
            return false;
        }

        if (!empty($rules['limit'])) {
            $currentValue = (int) ($rules['current_count'] ?? 0);
            if (!$this->limitAvailable($rules['limit'], $currentValue)) {
                $this->lastReason = 'limit_reached';
                return false;
            }
        }

        return true;
    }

    public function guardOrDeny(array $rules)
    {
        if (!$this->can($rules)) {
            accessforbidden($this->buildDeniedMessage());
        }
        return true;
    }

    public function buildDeniedMessage()
    {
        if (!empty($this->error)) {
            return 'POS access denied (' . $this->error . ')';
        }

        switch ($this->lastReason) {
            case 'module_disabled':
                return 'POS module is not enabled for this tenant';
            case 'feature_disabled':
                return 'This POS feature is not enabled for this tenant';
            case 'limit_reached':
                return 'The configured POS subscription limit has been reached';
            case 'permission_denied':
            case 'dolibarr_right_denied':
            default:
                return 'POS access denied';
        }
    }
}
