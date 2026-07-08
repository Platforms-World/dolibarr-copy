<?php
/**
 * TakePOS - Terminal PIN verification
 * Accepts POST form submission, verifies PIN, redirects to POS.
 */
require '../main.inc.php';

if (empty($user->login)) {
    accessforbidden();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . DOL_URL_ROOT . '/takepos/index.php');
    exit;
}

// CSRF
if (!newToken() && !GETPOST('token', 'alpha')) {
    accessforbidden('Bad token');
}

$terminalId = GETPOSTINT('terminal_id');
$pin        = GETPOST('pin', 'alphanohtml');
$redirect   = DOL_URL_ROOT . '/takepos/index.php?setterminal=' . $terminalId;

if ($terminalId <= 0 || $pin === '') {
    header('Location: ' . DOL_URL_ROOT . '/takepos/index.php?pin_error=1');
    exit;
}

// Rate limiting
$rateKey = 'takepos_pin_attempts_' . $terminalId;
if (!isset($_SESSION[$rateKey])) {
    $_SESSION[$rateKey] = array('count' => 0, 'since' => time());
}
if (time() - $_SESSION[$rateKey]['since'] > 60) {
    $_SESSION[$rateKey] = array('count' => 0, 'since' => time());
}
$_SESSION[$rateKey]['count']++;
if ($_SESSION[$rateKey]['count'] > 10) {
    header('Location: ' . DOL_URL_ROOT . '/takepos/index.php?pin_error=locked');
    exit;
}

$storedHash = getDolGlobalString('TAKEPOS_TERMINAL_PIN_' . $terminalId);

// No PIN set = allow
if (empty($storedHash)) {
    header('Location: ' . $redirect);
    exit;
}

if (password_verify($pin, $storedHash)) {
    $_SESSION[$rateKey]['count'] = 0;
    if (!isset($_SESSION['takepos_pin_verified'])) {
        $_SESSION['takepos_pin_verified'] = array();
    }
    $_SESSION['takepos_pin_verified'][$terminalId] = time();
    header('Location: ' . $redirect);
} else {
    header('Location: ' . DOL_URL_ROOT . '/takepos/index.php?pin_error=1&terminal_id=' . $terminalId);
}
exit;
