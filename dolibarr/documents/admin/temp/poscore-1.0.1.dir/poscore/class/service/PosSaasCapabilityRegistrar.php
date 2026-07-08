<?php

class PosSaasCapabilityRegistrar
{
    public $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    private function loadRegistry()
    {
        $paths = [
            DOL_DOCUMENT_ROOT.'/custom/saascore/class/service/SaasRegistryService.php',
            DOL_DOCUMENT_ROOT.'/custom/saascore/class/services/SaasRegistryService.php',
            DOL_DOCUMENT_ROOT.'/custom/saascore/class/SaasRegistryService.php',
            DOL_DOCUMENT_ROOT.'/custom/saascore/lib/SaasRegistryService.php'
        ];

        foreach($paths as $p){
            if(file_exists($p)){
                require_once $p;
            }
        }

        if(class_exists('SaasRegistryService'))
            return new SaasRegistryService($this->db);

        return null;
    }

    public function registerAll()
    {
        $svc = $this->loadRegistry();
        if(!$svc) return;

        if(method_exists($svc,'registerModule'))
            $svc->registerModule('poscore',['label'=>'POS Management']);

        $features=['multi_terminal','multi_cashier','shift_management','refund_sales'];

        foreach($features as $f){
            if(method_exists($svc,'registerFeature'))
                $svc->registerFeature('poscore',$f);
        }

        $limits=['max_terminals','max_cashiers'];

        foreach($limits as $l){
            if(method_exists($svc,'registerLimit'))
                $svc->registerLimit('poscore',$l);
        }

        $perms=['view_pos_dashboard','create_terminal','create_cashier','open_shift'];

        foreach($perms as $p){
            if(method_exists($svc,'registerPermission'))
                $svc->registerPermission('poscore',$p);
        }
    }
}
