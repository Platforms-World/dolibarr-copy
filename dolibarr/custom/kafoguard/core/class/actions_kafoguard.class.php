<?php
class ActionsKafoGuard
{
    public $db; public $results = array(); public $resprints;
    private static $checked = false;

    public function __construct($db) { $this->db = $db; }

    public function printTopRightMenu($parameters, &$object, &$action, $hookmanager) { $this->guard(); return 0; }
    public function printLeftBlock($parameters, &$object, &$action, $hookmanager)    { $this->guard(); return 0; }
    public function doActions($parameters, &$object, &$action, $hookmanager)         { $this->guard(); return 0; }

    private function guard()
    {
        if (self::$checked) return;
        self::$checked = true;

        if (strpos($_SERVER['PHP_SELF'] ?? '', '/admin/') === false) return;

        // TEMP DEBUG — delete after testing
        die('GUARD FIRED'
            . ' | PHP_SELF=' . ($_SERVER['PHP_SELF'] ?? '')
            . ' | LOCK=' . getDolGlobalString('KAFO_LOCK_MODULES')
            . ' | BYPASS=' . (!empty($_SESSION['kafo_admin_bypass']) ? '1' : '0'));
    }
}