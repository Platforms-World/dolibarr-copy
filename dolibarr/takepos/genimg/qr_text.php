<?php
/**
 * genimg/qr_text.php
 *
 * يولّد QR Code من نص مُمرَّر مباشرةً كـ GET parameter
 * نسخة مبسّطة من qr.php لاستخدامها في الفواتير الإقليمية
 *
 * الاستخدام:
 *   <img src="/takepos/genimg/qr_text.php?d=BASE64_ENCODED_TEXT">
 *
 * مثال:
 *   $url = DOL_URL_ROOT.'/takepos/genimg/qr_text.php?d='.urlencode(base64_encode($qrData));
 */

if (!defined("NOLOGIN")) {
    define("NOLOGIN", '1');
}
if (!defined('NOIPCHECK')) {
    define('NOIPCHECK', '1');
}
if (!defined('NOREQUIRESOC')) {
    define('NOREQUIRESOC', '1');
}
if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
}

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/lib/takepos_access_guard.php';
takeposAccessGuardCurrent($db, isset($user) ? $user : null, __FILE__);
require_once __DIR__ . '/../class/TakeposAccess.class.php';
require '../../core/modules/barcode/doc/tcpdfbarcode.modules.php';

if (!isModEnabled('takepos')) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

TakeposAccess::enforcePublic($db, 'takepos.qr');

// استقبال النص: مُشفَّر بـ base64 ثم urlencode
$raw = GETPOST('d', 'none');
if (empty($raw)) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}

// فكّ التشفير
$text = base64_decode($raw);
if ($text === false || $text === '') {
    header('HTTP/1.1 400 Bad Request');
    exit;
}

// الحد الأقصى لحجم النص (QR يدعم حتى ~4000 حرف)
$text = mb_substr($text, 0, 1000);

// توليد QR - مثل qr.php الأصلي تماماً
$module = new modTcpdfbarcode();
$module->buildBarCode($text, 'QRCODE', 'Y');
