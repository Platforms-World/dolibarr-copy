<?php
/**
 * lib/takepos_billing_country.php
 *
 * مكتبة الفوترة الإقليمية: الأردن + السعودية (ZATCA/FATOORA)
 *
 * طريقة توليد QR:
 *   - يُولَّد النص (أردني نصي / سعودي TLV-Base64)
 *   - يُمرَّر إلى genimg/qr_text.php كـ ?d=BASE64(نص)
 *   - qr_text.php مثل qr.php الأصلي تماماً: NOLOGIN=1 ويخرج PNG مباشرةً
 *   - لا يوجد ob_start ولا تعارض مع send.php
 */

if (!defined('DOL_DOCUMENT_ROOT')) {
    exit('Not allowed');
}

/**
 * الحصول على الدولة المفعّلة
 */
function takeposGetBillingCountry($conf)
{
    return getDolGlobalString('TAKEPOS_BILLING_COUNTRY', '');
}

/**
 * بناء URL لصورة QR - يستخدم qr_text.php
 *
 * @param string $data  النص الخام (سيُشفَّر base64)
 * @return string  رابط صورة PNG
 */
function takeposBuildQRUrl($data)
{
    $encoded = base64_encode($data);
    return DOL_URL_ROOT . '/takepos/genimg/qr_text.php?d=' . urlencode($encoded);
}

// =====================================================================
// JORDAN
// =====================================================================

function takeposBuildJordanQRData($invoice, $mysoc, $conf)
{
    $vatNumber   = getDolGlobalString('TAKEPOS_JO_VAT_NUMBER', $mysoc->tva_intra);
    $nationalNo  = getDolGlobalString('TAKEPOS_JO_NATIONAL_NUMBER');
    $sellerName  = $mysoc->name;
    $invoiceRef  = $invoice->ref;
    $invoiceDate = dol_print_date($invoice->date, 'dayhour', 'tzserver');
    $totalTTC    = price2num($invoice->total_ttc, 'MT');
    $totalVAT    = price2num($invoice->total_tva, 'MT');
    $totalHT     = price2num($invoice->total_ht, 'MT');
    $currency    = $conf->currency;

    $lines = array(
        'Seller: '    . $sellerName,
        'VAT No: '    . $vatNumber,
    );
    if ($nationalNo) {
        $lines[] = 'CR No: ' . $nationalNo;
    }
    $lines[] = 'Invoice: '   . $invoiceRef;
    $lines[] = 'Date: '      . $invoiceDate;
    $lines[] = 'Excl.Tax: '  . $totalHT  . ' ' . $currency;
    $lines[] = 'Tax: '       . $totalVAT . ' ' . $currency;
    $lines[] = 'Total: '     . $totalTTC . ' ' . $currency;

    return implode("\n", $lines);
}

function takeposBuildJordanReceiptHeader($invoice, $mysoc, $conf, $langs)
{
    $vatNumber    = getDolGlobalString('TAKEPOS_JO_VAT_NUMBER', $mysoc->tva_intra);
    $nationalNo   = getDolGlobalString('TAKEPOS_JO_NATIONAL_NUMBER');
    $taxpayerType = getDolGlobalString('TAKEPOS_JO_TAXPAYER_TYPE', 'B2C');
    $typeLabel    = ($taxpayerType === 'B2B') ? '(فاتورة ضريبية كاملة)' : '(فاتورة ضريبية مبسّطة)';

    $html  = '<div style="text-align:center;border-bottom:1px dashed #999;padding-bottom:8px;margin-bottom:8px;">';
    $html .= '<strong style="font-size:1.1em;">' . htmlspecialchars($mysoc->name) . '</strong><br>';
    if (!empty($mysoc->address)) $html .= htmlspecialchars($mysoc->address) . '<br>';
    if (!empty($mysoc->town))    $html .= htmlspecialchars($mysoc->town) . '<br>';
    if (!empty($mysoc->phone))   $html .= 'هاتف: ' . htmlspecialchars($mysoc->phone) . '<br>';
    $html .= '<hr style="border:none;border-top:1px solid #ccc;">';
    $html .= '<strong>فاتورة ضريبية</strong><br>';
    $html .= '<span style="font-size:0.85em;">' . $typeLabel . '</span>';
    $html .= '</div>';

    $html .= '<table style="width:100%;font-size:0.85em;">';
    $html .= '<tr><td style="text-align:right;">الرقم الضريبي:</td><td><strong>' . htmlspecialchars($vatNumber) . '</strong></td></tr>';
    if ($nationalNo) {
        $html .= '<tr><td style="text-align:right;">السجل التجاري:</td><td>' . htmlspecialchars($nationalNo) . '</td></tr>';
    }
    $html .= '<tr><td style="text-align:right;">رقم الفاتورة:</td><td><strong>' . htmlspecialchars($invoice->ref) . '</strong></td></tr>';
    $html .= '<tr><td style="text-align:right;">التاريخ:</td><td>' . dol_print_date($invoice->date, 'dayhour', 'tzuserrel') . '</td></tr>';
    $html .= '</table>';
    $html .= '<hr style="border:none;border-top:1px dashed #999;">';

    return $html;
}

function takeposBuildJordanReceiptFooter($invoice, $mysoc, $conf, $langs)
{
    $qrData = takeposBuildJordanQRData($invoice, $mysoc, $conf);
    $qrSrc  = takeposBuildQRUrl($qrData);

    $html  = '<div style="text-align:center;margin-top:10px;border-top:1px dashed #999;padding-top:8px;">';
    $html .= '<p style="font-size:0.8em;">يُرجى الاحتفاظ بهذه الفاتورة</p>';
    $html .= '<img src="' . $qrSrc . '" width="120" height="120" alt="QR">';
    $html .= '<br><span style="font-size:0.75em;color:#666;">امسح للتحقق من الفاتورة</span>';
    $html .= '</div>';

    return $html;
}

// =====================================================================
// SAUDI ARABIA - ZATCA
// =====================================================================

function takeposZATCATLVEncode($tag, $value)
{
    $valueBytes = mb_convert_encoding((string)$value, 'UTF-8');
    $length     = strlen($valueBytes);
    return chr($tag) . chr($length) . $valueBytes;
}

function takeposBuildSaudiZATCAQRData($invoice, $mysoc, $conf)
{
    $sellerName = getDolGlobalString('TAKEPOS_SA_SELLER_NAME', $mysoc->name);
    $vatNumber  = getDolGlobalString('TAKEPOS_SA_VAT_NUMBER', $mysoc->tva_intra);
    $timestamp  = date('Y-m-d\TH:i:s\Z', dol_now());
    $totalTTC   = number_format((float)price2num($invoice->total_ttc, 'MT'), 2, '.', '');
    $totalVAT   = number_format((float)price2num($invoice->total_tva, 'MT'), 2, '.', '');

    $tlv  = takeposZATCATLVEncode(1, $sellerName);
    $tlv .= takeposZATCATLVEncode(2, $vatNumber);
    $tlv .= takeposZATCATLVEncode(3, $timestamp);
    $tlv .= takeposZATCATLVEncode(4, $totalTTC);
    $tlv .= takeposZATCATLVEncode(5, $totalVAT);

    // نعيد TLV الخام (ثنائي) - qr_text.php سيشفّره مرة ثانية بـ base64
    return $tlv;
}

function takeposBuildSaudiReceiptHeader($invoice, $mysoc, $conf, $langs)
{
    $sellerName  = getDolGlobalString('TAKEPOS_SA_SELLER_NAME', $mysoc->name);
    $vatNumber   = getDolGlobalString('TAKEPOS_SA_VAT_NUMBER', $mysoc->tva_intra);
    $crNumber    = getDolGlobalString('TAKEPOS_SA_CR_NUMBER');
    $street      = getDolGlobalString('TAKEPOS_SA_STREET', $mysoc->address);
    $district    = getDolGlobalString('TAKEPOS_SA_DISTRICT');
    $city        = getDolGlobalString('TAKEPOS_SA_CITY', $mysoc->town);
    $postalCode  = getDolGlobalString('TAKEPOS_SA_POSTAL_CODE', $mysoc->zip);
    $buildingNo  = getDolGlobalString('TAKEPOS_SA_BUILDING_NUMBER');
    $invoiceType = getDolGlobalString('TAKEPOS_SA_INVOICE_TYPE', 'simplified');
    $typeLabel   = ($invoiceType === 'standard') ? 'فاتورة ضريبية' : 'فاتورة ضريبية مبسّطة';

    $html  = '<div style="text-align:center;border-bottom:1px dashed #999;padding-bottom:8px;margin-bottom:8px;direction:rtl;">';
    $html .= '<strong style="font-size:1.1em;">' . htmlspecialchars($sellerName) . '</strong><br>';
    if ($buildingNo || $street) {
        $html .= htmlspecialchars(trim($buildingNo . ' ' . $street)) . '<br>';
    }
    if ($district) {
        $html .= htmlspecialchars($district) . ($city ? ' - ' . htmlspecialchars($city) : '') . '<br>';
    } elseif ($city) {
        $html .= htmlspecialchars($city) . '<br>';
    }
    if ($postalCode) $html .= htmlspecialchars($postalCode) . '<br>';
    if (!empty($mysoc->phone)) $html .= 'هاتف: ' . htmlspecialchars($mysoc->phone) . '<br>';
    $html .= '<hr style="border:none;border-top:1px solid #ccc;">';
    $html .= '<strong>' . $typeLabel . '</strong>';
    $html .= '</div>';

    $html .= '<table style="width:100%;font-size:0.85em;direction:rtl;">';
    $html .= '<tr><td style="text-align:right;">الرقم الضريبي:</td><td><strong>' . htmlspecialchars($vatNumber) . '</strong></td></tr>';
    if ($crNumber) {
        $html .= '<tr><td style="text-align:right;">السجل التجاري:</td><td>' . htmlspecialchars($crNumber) . '</td></tr>';
    }
    $html .= '<tr><td style="text-align:right;">رقم الفاتورة:</td><td><strong>' . htmlspecialchars($invoice->ref) . '</strong></td></tr>';
    $html .= '<tr><td style="text-align:right;">التاريخ:</td><td>' . dol_print_date($invoice->date, 'dayhour', 'tzuserrel') . '</td></tr>';
    $html .= '</table>';
    $html .= '<hr style="border:none;border-top:1px dashed #999;">';

    return $html;
}

function takeposBuildSaudiReceiptFooter($invoice, $mysoc, $conf, $langs)
{
    // QR السعودي: TLV ثنائي → base64 → URL encode → qr_text.php يولّد صورة
    $qrData = takeposBuildSaudiZATCAQRData($invoice, $mysoc, $conf);
    $qrSrc  = takeposBuildQRUrl($qrData);

    $html  = '<div style="text-align:center;margin-top:10px;border-top:1px dashed #999;padding-top:8px;direction:rtl;">';
    $html .= '<img src="' . $qrSrc . '" width="130" height="130" alt="ZATCA QR">';
    $html .= '<br><span style="font-size:0.75em;color:#666;">يمكن التحقق من الفاتورة بمسح رمز QR</span><br>';
    $html .= '<span style="font-size:0.75em;color:#666;">ضريبة القيمة المضافة 15%</span>';
    $html .= '</div>';

    return $html;
}

// =====================================================================
// JORDAN — فاتورة نقدية بنمط "فوترة" الوطني (صفحة A4 كاملة)
// =====================================================================

/**
 * صياغة رقم بعدد المنازل العشرية الخاص بالعملة (الدينار = 3 منازل).
 */
function takeposJoFmt($value, $decimals = 3)
{
    return number_format((float) $value, $decimals, '.', '');
}

/**
 * بناء فاتورة "فوترة" الكاملة بنمط الصفحة (A4) — مطابقة للنموذج الوطني.
 *
 * يُرجع HTML كامل لجسم الصفحة (يُستبدل به إيصال الكاشير الحراري عندما
 * يكون نمط الفاتورة الأردني = full).
 *
 * @param Facture  $invoice
 * @param Societe  $mysoc
 * @param Conf     $conf
 * @param Translate $langs
 * @param DoliDB   $db
 * @return string
 */
function takeposBuildJordanFullInvoice($invoice, $mysoc, $conf, $langs, $db)
{
    // ---- بيانات البائع ----
    $vatNumber    = getDolGlobalString('TAKEPOS_JO_VAT_NUMBER', $mysoc->tva_intra);
    $incomeSource = getDolGlobalString('TAKEPOS_JO_INCOME_SOURCE', getDolGlobalString('TAKEPOS_JO_NATIONAL_NUMBER', ''));
    $sellerName   = $mysoc->name;
    $sellerPhone  = $mysoc->phone;
    $sellerAddr   = trim((string) ($mysoc->town ?: $mysoc->address));
    if ($sellerAddr === '') {
        $sellerAddr = 'الأردن';
    }
    $sellerZip    = $mysoc->zip;

    // ---- نوع الفاتورة والعملة ----
    $taxpayerType = getDolGlobalString('TAKEPOS_JO_TAXPAYER_TYPE', 'B2C');
    $invoiceTypeLabel = ($taxpayerType === 'B2B') ? 'فاتورة ضريبية' : 'فاتورة محلية';
    $currencyCode = $conf->currency ?: 'JOD';
    $currencyLabel = ($currencyCode === 'JOD') ? 'دينار أردني (JOD)' : $currencyCode;
    $dec = ($currencyCode === 'JOD') ? 3 : 2;

    // وضع الضريبة في العرض: ttc = القيم شاملة الضريبة (افتراضي), ht = قبل الضريبة
    $taxMode = getDolGlobalString('TAKEPOS_JO_INVOICE_TAX_MODE', 'ttc');

    // ---- بيانات المشتري ----
    $buyerName = '-';
    $buyerPhone = '-';
    $buyerAddr = '-';
    $buyerZip = '-';
    $defaultThirdPartyId = function_exists('takeposResolveTerminalThirdPartyId')
        ? takeposResolveTerminalThirdPartyId(isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : 0)
        : 0;
    if (!empty($invoice->socid) && (int) $invoice->socid !== (int) $defaultThirdPartyId) {
        $soc = new Societe($db);
        if ($soc->fetch((int) $invoice->socid) > 0) {
            $buyerName  = $soc->name ?: '-';
            $buyerPhone = $soc->phone ?: '-';
            $buyerAddr  = trim((string) ($soc->town ?: $soc->address)) ?: '-';
            $buyerZip   = $soc->zip ?: '-';
        }
    }

    // ---- تاريخ ورقم الفاتورة ----
    $invoiceRef = $invoice->ref;
    $invoiceDate = dol_print_date($invoice->date, '%d-%m-%Y');

    // ---- البنود + الإجماليات ----
    if (empty($invoice->lines)) {
        $invoice->fetch_lines();
    }
    $totalBeforeDiscount = 0.0;
    $totalDiscount       = 0.0;
    $grandTotal          = 0.0;

    $rowsHtml = '';
    $i = 0;
    if (!empty($invoice->lines)) {
        foreach ($invoice->lines as $line) {
            $i++;
            $qty     = (float) $line->qty;
            $taxMul  = ($taxMode === 'ht') ? 1.0 : (1 + ((float) $line->tva_tx) / 100);
            $unit    = (float) price2num($line->subprice, 'MU') * $taxMul;       // سعر الوحدة قبل الخصم
            $amount  = $qty * $unit;                                              // المبلغ قبل الخصم
            $lineNet = ($taxMode === 'ht') ? (float) $line->total_ht : (float) $line->total_ttc; // بعد الخصم
            $disc    = $amount - $lineNet;                                        // قيمة الخصم
            if ($disc < 0.0005) {
                $disc = 0.0; // تفادي قيم سالبة صغيرة بسبب التقريب
            }
            $after   = $amount - $disc;

            $totalBeforeDiscount += $amount;
            $totalDiscount       += $disc;
            $grandTotal          += $after;

            $desc = !empty($line->product_label) ? $line->product_label : $line->desc;
            $desc = dol_string_nohtmltag($desc);

            $rowsHtml .= '<tr>'
                . '<td class="c">' . $i . '</td>'
                . '<td class="desc">' . htmlspecialchars($desc) . '</td>'
                . '<td class="c">' . takeposJoFmt($qty, 3) . '</td>'
                . '<td class="c">' . takeposJoFmt($unit, $dec) . '</td>'
                . '<td class="c">' . takeposJoFmt($amount, $dec) . '</td>'
                . '<td class="c">' . ($disc > 0 ? takeposJoFmt($disc, $dec) : '0') . '</td>'
                . '<td class="c">' . takeposJoFmt($after, $dec) . '</td>'
                . '<td class="c">' . takeposJoFmt($after, $dec) . '</td>'
                . '</tr>';
        }
    }

    // ---- QR ----
    $qrSrc = takeposBuildQRUrl(takeposBuildJordanQRData($invoice, $mysoc, $conf));

    // =================== HTML ===================
    $h  = '<div class="jo-inv" dir="rtl">';

    // أنماط CSS (مضمّنة — لا ملفات خارجية لأن الملف يُرسَل لتطبيق الطباعة)
    $h .= '<style>'
        . '.jo-inv{font-family:Tahoma,Arial,sans-serif;color:#222;max-width:900px;margin:0 auto;padding:10px 18px;font-size:13px;line-height:1.6;}'
        . '.jo-inv *{box-sizing:border-box;}'
        . '.jo-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:6px;}'
        . '.jo-logo{text-align:center;}'
        . '.jo-logo .mark{color:#7a1f2b;font-weight:bold;font-size:30px;letter-spacing:1px;}'
        . '.jo-logo .sub{color:#7a1f2b;font-size:10px;}'
        . '.jo-title{flex:1;text-align:center;font-size:22px;font-weight:bold;padding-top:14px;}'
        . '.jo-ph{width:46px;height:34px;border:1px solid #ccc;background:#f7f7f7;}'
        . '.jo-meta{width:100%;border-collapse:collapse;margin:6px 0 14px;}'
        . '.jo-meta td{border:1px solid #d9d9d9;padding:7px 10px;width:25%;}'
        . '.jo-meta .lbl{color:#15808c;font-weight:bold;background:#fafafa;width:16%;white-space:nowrap;}'
        . '.jo-parties{display:flex;gap:22px;margin-bottom:16px;}'
        . '.jo-party{flex:1;}'
        . '.jo-party h4{text-align:center;color:#15808c;margin:0 0 4px;font-size:15px;}'
        . '.jo-party table{width:100%;border-collapse:collapse;}'
        . '.jo-party td{padding:5px 4px;border-bottom:1px solid #e4e4e4;}'
        . '.jo-party td.lbl{color:#15808c;text-align:right;width:42%;white-space:nowrap;}'
        . '.jo-party td.val{text-align:left;}'
        . '.jo-tbl{width:100%;border-collapse:collapse;margin-bottom:16px;}'
        . '.jo-tbl th{background:#ececec;color:#15808c;border:1px solid #cfcfcf;padding:8px 4px;font-size:12px;text-align:center;}'
        . '.jo-tbl td{border:1px solid #d9d9d9;padding:7px 4px;}'
        . '.jo-tbl td.c{text-align:center;}'
        . '.jo-tbl td.desc{text-align:right;padding-right:8px;}'
        . '.jo-bottom{display:flex;align-items:flex-start;gap:24px;}'
        . '.jo-qr{flex:0 0 auto;}'
        . '.jo-qr img{width:150px;height:150px;}'
        . '.jo-tot{flex:1;}'
        . '.jo-tot table{width:100%;border-collapse:collapse;}'
        . '.jo-tot td{padding:9px 12px;border-top:1px solid #e0e0e0;border-bottom:1px solid #e0e0e0;}'
        . '.jo-tot td.lbl{color:#15808c;font-weight:bold;text-align:right;}'
        . '.jo-tot td.val{text-align:left;font-weight:bold;font-size:15px;}'
        . '.jo-tot tr.grand td{background:#eaf2f7;border:1px solid #cddde6;}'
        . '@media print{.jo-inv{max-width:none;}}'
        . '</style>';

    // الشريط العلوي: شعار + العنوان
    $h .= '<div class="jo-top">';
    $h .= '<span class="jo-ph"></span>';
    $h .= '<div class="jo-title">فاتورة نقدية</div>';
    $h .= '<div class="jo-logo"><div class="mark">فوترة</div><div class="sub">نظام الفوترة الوطني الإلكتروني</div></div>';
    $h .= '</div>';

    // شبكة بيانات الفاتورة (رقم/نوع/عملة/تاريخ)
    $h .= '<table class="jo-meta"><tr>'
        . '<td class="lbl">رقم الفاتورة الإلكترونية</td><td>' . htmlspecialchars($invoiceRef) . '</td>'
        . '<td class="lbl">نوع العملة</td><td>' . htmlspecialchars($currencyLabel) . '</td>'
        . '</tr><tr>'
        . '<td class="lbl">نوع الفاتورة</td><td>' . htmlspecialchars($invoiceTypeLabel) . '</td>'
        . '<td class="lbl">تاريخ إصدار الفاتورة</td><td>' . htmlspecialchars($invoiceDate) . '</td>'
        . '</tr></table>';

    // البائع + المشتري
    $h .= '<div class="jo-parties">';
    // البائع
    $h .= '<div class="jo-party"><h4>البائع</h4><table>'
        . '<tr><td class="lbl">الاسم</td><td class="val">' . htmlspecialchars($sellerName) . '</td></tr>'
        . '<tr><td class="lbl">الرقم الضريبي</td><td class="val">' . htmlspecialchars($vatNumber ?: '-') . '</td></tr>'
        . '<tr><td class="lbl">تسلسل مصدر الدخل</td><td class="val">' . htmlspecialchars($incomeSource ?: '-') . '</td></tr>'
        . '<tr><td class="lbl">رقم الهاتف</td><td class="val">' . htmlspecialchars($sellerPhone ?: '-') . '</td></tr>'
        . '<tr><td class="lbl">العنوان</td><td class="val">' . htmlspecialchars($sellerAddr) . '</td></tr>'
        . '<tr><td class="lbl">الرقم البريدي</td><td class="val">' . htmlspecialchars($sellerZip ?: '-') . '</td></tr>'
        . '</table></div>';
    // المشتري
    $h .= '<div class="jo-party"><h4>المشتري</h4><table>'
        . '<tr><td class="lbl">الاسم</td><td class="val">' . htmlspecialchars($buyerName) . '</td></tr>'
        . '<tr><td class="lbl">رقم الهاتف</td><td class="val">' . htmlspecialchars($buyerPhone) . '</td></tr>'
        . '<tr><td class="lbl">العنوان</td><td class="val">' . htmlspecialchars($buyerAddr) . '</td></tr>'
        . '<tr><td class="lbl">الرقم البريدي</td><td class="val">' . htmlspecialchars($buyerZip) . '</td></tr>'
        . '</table></div>';
    $h .= '</div>';

    // جدول البنود
    $h .= '<table class="jo-tbl"><thead><tr>'
        . '<th>#</th>'
        . '<th>الوصف</th>'
        . '<th>الكمية</th>'
        . '<th>سعر الوحدة</th>'
        . '<th>المبلغ</th>'
        . '<th>الخصم</th>'
        . '<th>الإجمالي بعد الخصم</th>'
        . '<th>إجمالي المبلغ</th>'
        . '</tr></thead><tbody>' . $rowsHtml . '</tbody></table>';

    // الإجماليات + QR
    $cc = htmlspecialchars($currencyCode);
    $h .= '<div class="jo-bottom">';
    $h .= '<div class="jo-tot"><table>'
        . '<tr><td class="lbl">إجمالي الفاتورة قبل الخصم (' . $cc . ')</td><td class="val">' . takeposJoFmt($totalBeforeDiscount, $dec) . '</td></tr>'
        . '<tr><td class="lbl">مجموع قيمة الخصم (' . $cc . ')</td><td class="val">' . takeposJoFmt($totalDiscount, $dec) . '</td></tr>'
        . '<tr class="grand"><td class="lbl">إجمالي قيمة الفاتورة (' . $cc . ')</td><td class="val">' . takeposJoFmt($grandTotal, $dec) . '</td></tr>'
        . '</table></div>';
    $h .= '<div class="jo-qr"><img src="' . $qrSrc . '" alt="QR"></div>';
    $h .= '</div>';

    $h .= '</div>'; // .jo-inv
    return $h;
}

/**
 * هل نستخدم نمط الفاتورة الكامل (صفحة A4) بدل إيصال الكاشير الحراري؟
 * يُفعَّل فقط للأردن، وبحسب الإعداد TAKEPOS_JO_INVOICE_STYLE (full افتراضياً).
 */
function takeposUseJordanFullInvoice($conf)
{
    if (takeposGetBillingCountry($conf) !== 'JO') {
        return false;
    }
    // Show full invoice only when e-Fatura is enabled
    if (!getDolGlobalString('TAKEPOS_JO_ENABLE_EFATURA')) {
        return false;
    }
    return getDolGlobalString('TAKEPOS_JO_INVOICE_STYLE', 'full') === 'full';
}

// =====================================================================
// API موحّد
// =====================================================================

function takeposBuildCountryReceiptHeader($invoice, $mysoc, $conf, $langs)
{
    $country = takeposGetBillingCountry($conf);
    if ($country === 'JO') return takeposBuildJordanReceiptHeader($invoice, $mysoc, $conf, $langs);
    if ($country === 'SA') return takeposBuildSaudiReceiptHeader($invoice, $mysoc, $conf, $langs);
    return '';
}

function takeposBuildCountryReceiptFooter($invoice, $mysoc, $conf, $langs)
{
    $country = takeposGetBillingCountry($conf);
    if ($country === 'JO') return takeposBuildJordanReceiptFooter($invoice, $mysoc, $conf, $langs);
    if ($country === 'SA') return takeposBuildSaudiReceiptFooter($invoice, $mysoc, $conf, $langs);
    return '';
}

function takeposBuildCountryQRData($invoice, $mysoc, $conf)
{
    $country = takeposGetBillingCountry($conf);
    if ($country === 'JO') return takeposBuildJordanQRData($invoice, $mysoc, $conf);
    if ($country === 'SA') return takeposBuildSaudiZATCAQRData($invoice, $mysoc, $conf);
    return $invoice->ref;
}
