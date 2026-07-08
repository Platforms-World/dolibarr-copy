<?php
if (!defined('NOLOGIN'))     define('NOLOGIN', '1');
if (!defined('NOCSRFCHECK'))    define('NOCSRFCHECK',    '1');
if (!defined('NOIPCHECK'))      define('NOIPCHECK',      '1');
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
if (!defined('NOREQUIREMENU'))  define('NOREQUIREMENU',  '1');
if (!defined('NOREQUIREHTML'))  define('NOREQUIREHTML',  '1');
if (!defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX',  '1');
if (!defined('NOBROWSERNOTIF')) define('NOBROWSERNOTIF', '1');

require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/class/TakeposProductImageService.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';

$debugLog = 'C:/xampp/tmp/kafo_debug.log';
function kafo_dbg($msg) {
    global $debugLog;
    @file_put_contents($debugLog, '[' . date('H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

$productId = (int) (isset($_GET['id']) ? $_GET['id'] : (isset($_POST['id']) ? $_POST['id'] : 0));

if ($productId <= 0) {
    TakeposProductImageService::outputPlaceholder();
    exit;
}

// -- With NOLOGIN defined, main.inc.php treats this as a fully public page
// and does NOT populate $user from the session cookie, even if a valid
// Dolibarr session exists in the browser. That's why $user->id was always 0
// after adding NOLOGIN for API support - it silently broke the browser flow.
// Fix: if $user is still empty, load it ourselves from the session.
if (empty($user->id) && !empty($_SESSION['dol_login'])) {
    $tmpUser = new User($db);
    $loadResult = $tmpUser->fetch('', $_SESSION['dol_login']);
    if ($loadResult > 0) {
        $tmpUser->getrights();
        $user = $tmpUser;
    }
    kafo_dbg('manual session load: dol_login=' . $_SESSION['dol_login'] . ' fetch_result=' . $loadResult . ' user->id_after=' . (isset($user->id) ? $user->id : 'still unset'));
} else {
    kafo_dbg('dol_login session key: ' . (isset($_SESSION['dol_login']) ? $_SESSION['dol_login'] : 'NOT SET') . ' | user->id from main.inc.php=' . (isset($user->id) ? $user->id : 'unset'));
}

$authorised = !empty($user->id);
kafo_dbg('=== id=' . $productId . ' final user->id=' . (isset($user->id) ? $user->id : 0) . ' authorised=' . ($authorised ? '1' : '0') . ' ===');

if (!$authorised) {
    TakeposProductImageService::outputPlaceholder();
    exit;
}

while (ob_get_level()) ob_end_clean();
TakeposProductImageService::outputProductImage($db, $productId);
exit;