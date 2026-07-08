<?php
require_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

class modUserSyncHook extends DolibarrModules
{
    public function __construct($db)
    {
        parent::__construct($db);

        $this->editor_name    = 'Custom';
        $this->numero         = 500001;
        $this->rights_class   = 'usersynchook';
        $this->family         = 'interface';
        $this->name           = 'UserSyncHook';
        $this->description    = 'Syncs users and establishments to Laravel';
        $this->version        = '1.0';
        $this->const_name     = 'MAIN_MODULE_USERSYNCHOOK';
        $this->always_enabled = false;
        $this->module_parts   = ['triggers' => 1];
    }
}