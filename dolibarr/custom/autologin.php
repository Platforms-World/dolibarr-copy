<?php
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    die('Access denied');
}

$token      = isset($_GET['token']) ? $_GET['token'] : '';
$validToken = '2083806';

if ($token !== $validToken) {
    die('Invalid token');
}

$login = isset($_GET['login']) ? $_GET['login'] : 'admin';

// correct path
define('NOCSRFCHECK', 1);
define('NOTOKENRENEWAL', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);

require_once dirname(__FILE__) . '/../main.inc.php';

// fetch and load user
$user   = new User($db);
$result = $user->fetch('', $login);

if ($result <= 0) {
    die('User not found: ' . $login);
}

$user->getrights();

// set session
$_SESSION['dol_login']        = $user->login;
$_SESSION['dol_entity']       = 1;
$_SESSION['dol_user']         = $user->id;
$_SESSION['dol_authmode']     = 'forceuser';
$_SESSION['dol_tz']           = 0;
$_SESSION['dol_dst']          = 0;
$_SESSION['dol_screenwidth']  = 1280;
$_SESSION['dol_screenheight'] = 800;
$_SESSION['dol_loginmesg']    = '';
$_SESSION['kafo_admin_bypass'] = 1;
// ── Redirect to setup page if provided, otherwise go to home ──────
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '/index.php?mainmenu=home';

// Security: only allow relative paths (no external URLs)
if (strpos($redirect, 'http') === 0 || strpos($redirect, '//') === 0) {
    $redirect = '/index.php?mainmenu=home';
}

// ── If redirecting to an admin/setup page, add bypass token ───────
// This allows Laravel admin to access setup pages even when
// KAFO_LOCK_MODULES is enabled (module page is locked for merchants)
$isAdminPage = strpos($redirect, '/admin/') !== false
    || strpos($redirect, 'setup.php') !== false;

if ($isAdminPage) {
    $separator = strpos($redirect, '?') !== false ? '&' : '?';
    $redirect .= $separator . 'kafo_bypass=' . $validToken;
}

header('Location: ' . DOL_URL_ROOT . $redirect);
exit;