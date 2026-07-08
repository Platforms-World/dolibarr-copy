<?php
/**
 * TakePOS Language Switch Handler
 *
 * Sets the forced display language using Dolibarr's standard
 * $_SESSION['forcelang'] mechanism, then redirects back.
 *
 * Only touches: language session variable.
 * No business logic. No database. No design changes.
 */

require '../../main.inc.php';


require_once DOL_DOCUMENT_ROOT . '/takepos/lib/takepos_access_guard.php';
takeposAccessGuardCurrent($db, isset($user) ? $user : null, __FILE__);
// Only allow known TakePOS-supported locales
$allowed = array('en_US', 'ar_JO');

$lang = GETPOST('lang', 'aZ09');
$back = isset($_GET['back']) ? (string) $_GET['back'] : '';
if ($back === '' && isset($_POST['back'])) {
    $back = (string) $_POST['back'];
}

if (in_array($lang, $allowed, true)) {
    $_SESSION['forcelang'] = $lang;
}

// Redirect back to caller - only allow relative same-app URLs
$redirect = DOL_URL_ROOT . '/takepos/index.php';
if (!empty($back)) {
    $decoded = urldecode($back);
    if (preg_match('#^/[^/]#', $decoded)) {
        $redirect = $decoded;
    }
}

header('Location: ' . $redirect);
exit;
