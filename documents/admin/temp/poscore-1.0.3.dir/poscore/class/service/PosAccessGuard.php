<?php

class PosAccessGuard
{
    private $db;
    private $user;
    private $conf;

    public function __construct($db, $user, $conf)
    {
        $this->db = $db;
        $this->user = $user;
        $this->conf = $conf;
    }

    private function loadAccess()
    {
        $paths = array(
            DOL_DOCUMENT_ROOT.'/custom/saascore/class/service/SaasAccessService.php',
            DOL_DOCUMENT_ROOT.'/custom/saascore/class/services/SaasAccessService.php',
            DOL_DOCUMENT_ROOT.'/custom/saascore/class/SaasAccessService.php',
            DOL_DOCUMENT_ROOT.'/custom/saascore/lib/SaasAccessService.php'
        );

        foreach ($paths as $p) {
            if (file_exists($p)) {
                require_once $p;
            }
        }

        if (class_exists('SaasAccessService')) {
            return new SaasAccessService($this->db);
        }

        return null;
    }

    public function checkModule()
    {
        $svc = $this->loadAccess();
        if (!$svc) return true;

        if (method_exists($svc, 'isModuleEnabled')) {
            return (bool) $svc->isModuleEnabled($this->conf->entity, 'poscore');
        }

        return true;
    }

    public function checkPermission($perm)
    {
        if (!empty($this->user->admin)) return true;
        if (!$this->checkModule()) return false;

        $svc = $this->loadAccess();
        if (!$svc) return true;

        if (method_exists($svc, 'checkUserPermission')) {
            return (bool) $svc->checkUserPermission($this->user->id, $perm);
        }

        return true;
    }

    public function requirePermission($perm, $message = 'Permission denied')
    {
        if (!$this->checkModule()) {
            accessforbidden('POS module is not enabled for this tenant');
        }

        if (!$this->checkPermission($perm)) {
            accessforbidden($message);
        }
    }

    public function canUseFeature($featureCode)
    {
        if (!empty($this->user->admin)) return true;

        $svc = $this->loadAccess();
        if (!$svc) return true;

        if (method_exists($svc, 'isFeatureEnabled')) {
            return (bool) $svc->isFeatureEnabled($this->conf->entity, $featureCode);
        }

        return true;
    }

    public function isLimitExceeded($limitCode, $currentValue)
    {
        $svc = $this->loadAccess();
        if (!$svc) return false;

        if (method_exists($svc, 'checkLimitExceeded')) {
            return (bool) $svc->checkLimitExceeded($this->conf->entity, $limitCode, $currentValue);
        }

        return false;
    }
}
