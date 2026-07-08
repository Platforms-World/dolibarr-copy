<?php
/*
 * TakePOS API v1 - deployment diagnostic.
 * Temporary file: request GET /takepos/api/v1/ping.php and read the JSON.
 * It reports the directory PHP actually served this from, and whether the
 * auth_login.php sitting next to it contains the new "context" change.
 * Delete this file once deployment is confirmed.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$dir = __DIR__;
$authFile = $dir . '/auth_login.php';
$ctxFile  = $dir . '/_context.php';
$setFile  = $dir . '/set_terminal.php';

$authSrc = (is_readable($authFile) ? (string) file_get_contents($authFile) : '');

echo json_encode(array(
    'ping'                    => 'takepos-ctx-build-1',
    'served_directory'        => $dir,
    'auth_login_exists'       => file_exists($authFile),
    'auth_login_has_context'  => ($authSrc !== '' && strpos($authSrc, "'context' => \$context") !== false),
    'auth_login_mtime'        => (file_exists($authFile) ? date('c', (int) filemtime($authFile)) : null),
    'context_helper_exists'   => file_exists($ctxFile),
    'set_terminal_exists'     => file_exists($setFile),
    'opcache_enabled'         => (function_exists('opcache_get_status') ? (bool) @opcache_get_status(false)['opcache_enabled'] : false),
    'php_version'             => PHP_VERSION,
    'server_time'             => date('c'),
), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
