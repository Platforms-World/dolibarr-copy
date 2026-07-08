<?php

class PosAccessGuard
{
    private $db;
    private $user;
    private $conf;

    public function __construct($db,$user,$conf)
    {
        $this->db=$db;
        $this->user=$user;
        $this->conf=$conf;
    }

    private function loadAccess()
    {
        $paths=[
            DOL_DOCUMENT_ROOT.'/custom/saascore/class/service/SaasAccessService.php',
            DOL_DOCUMENT_ROOT.'/custom/saascore/class/services/SaasAccessService.php',
            DOL_DOCUMENT_ROOT.'/custom/saascore/class/SaasAccessService.php',
            DOL_DOCUMENT_ROOT.'/custom/saascore/lib/SaasAccessService.php'
        ];

        foreach($paths as $p){
            if(file_exists($p)){
                require_once $p;
            }
        }

        if(class_exists('SaasAccessService'))
            return new SaasAccessService($this->db);

        return null;
    }

    public function checkModule()
    {
        $svc=$this->loadAccess();
        if(!$svc) return true;

        if(method_exists($svc,'isModuleEnabled'))
            return $svc->isModuleEnabled($this->conf->entity,'poscore');

        return true;
    }

    public function requirePermission($perm)
    {
        if(!$this->checkModule()) accessforbidden("POS disabled");

        $svc=$this->loadAccess();
        if(!$svc) return;

        if(method_exists($svc,'checkUserPermission')){
            if(!$svc->checkUserPermission($this->user->id,$perm,$this->conf->entity,'poscore'))
                accessforbidden("Permission denied");
        }
    }
}
