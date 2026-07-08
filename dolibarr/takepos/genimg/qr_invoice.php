<?php
/**
 * genimg/qr_invoice.php
 *
 * توليد صورة QR Code للفاتورة حسب نظام الفوترة الإقليمي المفعّل
 *
 * المعاملات:
 *   country=JO|SA  : نظام الدولة
 *   facid=N        : رقم معرّف الفاتورة في قاعدة البيانات
 *
 * مثال: /takepos/genimg/qr_invoice.php?country=SA&facid=123
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
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/lib/takepos_billing_country.php';
require '../../core/modules/barcode/doc/tcpdfbarcode.modules.php';

TakeposAccess::enforcePublic($db, 'takepos.qr');

if (!isModEnabled('takepos')) {
    accessforbidden('TakePOS module disabled');
}

/**
 * @var Conf    $conf
 * @var DoliDB  $db
 * @var Societe $mysoc
 */

$facid   = GETPOSTINT('facid');
$country = GETPOST('country', 'alpha');

// التحقق من البيانات
if (empty($facid) || $facid <= 0) {
    header('Content-Type: image/png');
    // إرجاع QR فارغ
    $module = new modTcpdfbarcode();
    $module->buildBarCode('NO-INVOICE', 'QRCODE', 'Y');
    exit;
}

// تحميل الفاتورة
$invoice = new Facture($db);
$result  = $invoice->fetch($facid);
if ($result <= 0) {
    header('HTTP/1.1 404 Not Found');
    exit;
}

// توليد بيانات QR حسب الدولة
if (empty($country)) {
    $country = takeposGetBillingCountry($conf);
}

$qrData = '';

if ($country === 'SA') {
    // ZATCA: بيانات TLV مشفّرة بـ Base64
    $qrData = takeposBuildSaudiZATCAQRData($invoice, $mysoc, $conf);
} elseif ($country === 'JO') {
    // الأردن: نص مقروء بالعربية
    $qrData = takeposBuildJordanQRData($invoice, $mysoc, $conf);
} else {
    // Fallback: رقم الفاتورة فقط
    $qrData = $invoice->ref;
}

// توليد صورة QR
$module = new modTcpdfbarcode();
$module->buildBarCode($qrData, 'QRCODE', 'Y');
