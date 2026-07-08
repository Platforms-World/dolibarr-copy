<?php
/**
 * Trigger file for ChWhatsAppButton module
 * This file is not actually used as triggers, but hooks
 * Kept for compatibility with Dolibarr structure
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

class InterfaceChwhatsappbuttonTriggers extends DolibarrTriggers
{
    public function __construct($db)
    {
        $this->db = $db;
        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = "chwhatsappbutton";
        $this->description = "Triggers for ChWhatsAppButton module";
        $this->version = '1.0.0';
        $this->picto = 'technic';
    }

    public function runTrigger($action, $object, $user, $langs, $conf)
    {
        // No triggers needed for now
        return 0;
    }
}
