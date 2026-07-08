<?php
/**
 * lib/takepos_efatura_jo.php
 *
 * نظام الفوترة الإلكترونية الأردنية - e-Fatura Jordan
 * دائرة ضريبة الدخل والمبيعات الأردنية
 *
 * الوظائف:
 *   - توليد XML الفاتورة وفق المعيار الأردني
 *   - إرسال الفاتورة إلى e-Fatura API
 *   - حفظ حالة الإرسال في قاعدة البيانات
 *   - عرض حالة الإرسال في الفاتورة
 *
 * الاستخدام:
 *   require_once DOL_DOCUMENT_ROOT.'/takepos/lib/takepos_efatura_jo.php';
 *   TakeposEFaturaJo::submitInvoice($db, $invoice, $mysoc, $conf);
 */

if (!defined('DOL_DOCUMENT_ROOT')) {
    exit('Not allowed');
}

class TakeposEFaturaJo
{
    /** اسم الجدول الذي يحفظ حالة الإرسال */
    const TABLE = 'takepos_efatura_jo_log';

    // ─── حالات الإرسال ───────────────────────────────────────────
    const STATUS_PENDING  = 'pending';
    const STATUS_SENT     = 'sent';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';
    const STATUS_ERROR    = 'error';

    /**
     * هل e-Fatura مفعّل؟
     */
    public static function isEnabled()
    {
        return getDolGlobalString('TAKEPOS_BILLING_COUNTRY') === 'JO'
            && getDolGlobalString('TAKEPOS_JO_ENABLE_EFATURA') == 1;
    }

    /**
     * إنشاء جدول السجل إن لم يكن موجوداً
     */
    public static function ensureTable($db)
    {
        $sql = "CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . self::TABLE . " (
            rowid       INT AUTO_INCREMENT PRIMARY KEY,
            fk_invoice  INT NOT NULL,
            status      VARCHAR(20) NOT NULL DEFAULT 'pending',
            sent_at     DATETIME,
            response    TEXT,
            uuid        VARCHAR(128),
            xml_data    MEDIUMTEXT,
            tdate       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_invoice (fk_invoice)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $db->query($sql);
    }

    /**
     * توليد XML الفاتورة وفق المعيار الأردني
     *
     * @param Facture $invoice
     * @param Societe $mysoc
     * @param Conf    $conf
     * @return string XML
     */
    public static function buildInvoiceXML($invoice, $mysoc, $conf)
    {
        $vatNumber   = getDolGlobalString('TAKEPOS_JO_VAT_NUMBER', $mysoc->tva_intra);
        $nationalNo  = getDolGlobalString('TAKEPOS_JO_NATIONAL_NUMBER', '');
        $taxpayerType = getDolGlobalString('TAKEPOS_JO_TAXPAYER_TYPE', 'B2C');

        $invoiceDate = date('Y-m-d', $invoice->date);
        $invoiceTime = date('H:i:s', $invoice->date);
        $totalHT     = number_format((float) price2num($invoice->total_ht, 'MT'), 3, '.', '');
        $totalVAT    = number_format((float) price2num($invoice->total_tva, 'MT'), 3, '.', '');
        $totalTTC    = number_format((float) price2num($invoice->total_ttc, 'MT'), 3, '.', '');
        $currency    = $conf->currency ?: 'JOD';

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"' . "\n";
        $xml .= '         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"' . "\n";
        $xml .= '         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">' . "\n";

        // معلومات الفاتورة الأساسية
        $xml .= '  <cbc:ID>' . htmlspecialchars($invoice->ref) . '</cbc:ID>' . "\n";
        $xml .= '  <cbc:IssueDate>' . $invoiceDate . '</cbc:IssueDate>' . "\n";
        $xml .= '  <cbc:IssueTime>' . $invoiceTime . '</cbc:IssueTime>' . "\n";
        $xml .= '  <cbc:InvoiceTypeCode>' . ($taxpayerType === 'B2B' ? '388' : '381') . '</cbc:InvoiceTypeCode>' . "\n";
        $xml .= '  <cbc:DocumentCurrencyCode>' . htmlspecialchars($currency) . '</cbc:DocumentCurrencyCode>' . "\n";

        // معلومات البائع
        $xml .= '  <cac:AccountingSupplierParty>' . "\n";
        $xml .= '    <cac:Party>' . "\n";
        $xml .= '      <cac:PartyName><cbc:Name>' . htmlspecialchars($mysoc->name) . '</cbc:Name></cac:PartyName>' . "\n";
        $xml .= '      <cac:PostalAddress>' . "\n";
        $xml .= '        <cbc:CityName>' . htmlspecialchars($mysoc->town ?: '') . '</cbc:CityName>' . "\n";
        $xml .= '        <cac:Country><cbc:IdentificationCode>JO</cbc:IdentificationCode></cac:Country>' . "\n";
        $xml .= '      </cac:PostalAddress>' . "\n";
        $xml .= '      <cac:PartyTaxScheme>' . "\n";
        $xml .= '        <cbc:CompanyID>' . htmlspecialchars($vatNumber) . '</cbc:CompanyID>' . "\n";
        $xml .= '        <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>' . "\n";
        $xml .= '      </cac:PartyTaxScheme>' . "\n";
        if ($nationalNo) {
            $xml .= '      <cac:PartyLegalEntity><cbc:CompanyID>' . htmlspecialchars($nationalNo) . '</cbc:CompanyID></cac:PartyLegalEntity>' . "\n";
        }
        $xml .= '    </cac:Party>' . "\n";
        $xml .= '  </cac:AccountingSupplierParty>' . "\n";

        // بنود الفاتورة
        $lineNum = 1;
        if (!empty($invoice->lines)) {
            foreach ($invoice->lines as $line) {
                $lineHT  = number_format((float) price2num($line->total_ht, 'MT'), 3, '.', '');
                $lineVAT = number_format((float) price2num($line->total_tva, 'MT'), 3, '.', '');
                $vatRate = number_format((float) $line->tva_tx, 2, '.', '');
                $unitPrice = number_format((float) price2num($line->subprice, 'MT'), 3, '.', '');
                $desc = !empty($line->product_label) ? $line->product_label : $line->desc;

                $xml .= '  <cac:InvoiceLine>' . "\n";
                $xml .= '    <cbc:ID>' . $lineNum . '</cbc:ID>' . "\n";
                $xml .= '    <cbc:InvoicedQuantity unitCode="PCE">' . (int) $line->qty . '</cbc:InvoicedQuantity>' . "\n";
                $xml .= '    <cbc:LineExtensionAmount currencyID="' . htmlspecialchars($currency) . '">' . $lineHT . '</cbc:LineExtensionAmount>' . "\n";
                $xml .= '    <cac:TaxTotal>' . "\n";
                $xml .= '      <cbc:TaxAmount currencyID="' . htmlspecialchars($currency) . '">' . $lineVAT . '</cbc:TaxAmount>' . "\n";
                $xml .= '      <cac:TaxSubtotal>' . "\n";
                $xml .= '        <cac:TaxCategory>' . "\n";
                $xml .= '          <cbc:Percent>' . $vatRate . '</cbc:Percent>' . "\n";
                $xml .= '          <cac:TaxScheme><cbc:ID>GST</cbc:ID></cac:TaxScheme>' . "\n";
                $xml .= '        </cac:TaxCategory>' . "\n";
                $xml .= '      </cac:TaxSubtotal>' . "\n";
                $xml .= '    </cac:TaxTotal>' . "\n";
                $xml .= '    <cac:Item><cbc:Description>' . htmlspecialchars($desc) . '</cbc:Description></cac:Item>' . "\n";
                $xml .= '    <cac:Price><cbc:PriceAmount currencyID="' . htmlspecialchars($currency) . '">' . $unitPrice . '</cbc:PriceAmount></cac:Price>' . "\n";
                $xml .= '  </cac:InvoiceLine>' . "\n";
                $lineNum++;
            }
        }

        // الإجماليات
        $xml .= '  <cac:LegalMonetaryTotal>' . "\n";
        $xml .= '    <cbc:LineExtensionAmount currencyID="' . htmlspecialchars($currency) . '">' . $totalHT . '</cbc:LineExtensionAmount>' . "\n";
        $xml .= '    <cbc:TaxExclusiveAmount currencyID="' . htmlspecialchars($currency) . '">' . $totalHT . '</cbc:TaxExclusiveAmount>' . "\n";
        $xml .= '    <cbc:TaxInclusiveAmount currencyID="' . htmlspecialchars($currency) . '">' . $totalTTC . '</cbc:TaxInclusiveAmount>' . "\n";
        $xml .= '    <cbc:PayableAmount currencyID="' . htmlspecialchars($currency) . '">' . $totalTTC . '</cbc:PayableAmount>' . "\n";
        $xml .= '  </cac:LegalMonetaryTotal>' . "\n";

        // إجمالي الضريبة
        $xml .= '  <cac:TaxTotal>' . "\n";
        $xml .= '    <cbc:TaxAmount currencyID="' . htmlspecialchars($currency) . '">' . $totalVAT . '</cbc:TaxAmount>' . "\n";
        $xml .= '  </cac:TaxTotal>' . "\n";

        $xml .= '</Invoice>';

        return $xml;
    }

    /**
     * إرسال الفاتورة إلى e-Fatura API الأردني
     * يحفظ حالة الإرسال في جدول السجل
     *
     * @param DoliDB  $db
     * @param Facture $invoice
     * @param Societe $mysoc
     * @param Conf    $conf
     * @return array ['success'=>bool, 'status'=>string, 'message'=>string]
     */
    public static function submitInvoice($db, $invoice, $mysoc, $conf)
    {
        if (!self::isEnabled()) {
            return ['success' => false, 'status' => self::STATUS_ERROR, 'message' => 'e-Fatura not enabled'];
        }

        self::ensureTable($db);

        // توليد XML
        if (empty($invoice->lines)) {
            $invoice->fetch_lines();
        }
        $xml = self::buildInvoiceXML($invoice, $mysoc, $conf);

        // API endpoint الأردني (يمكن تغييره من الإعدادات)
        $apiUrl      = getDolGlobalString('TAKEPOS_JO_EFATURA_API_URL',
            'https://efatura.jo/api/v1/invoices');
        $apiUsername = getDolGlobalString('TAKEPOS_JO_EFATURA_USERNAME', '');
        $apiPassword = getDolGlobalString('TAKEPOS_JO_EFATURA_PASSWORD', '');

        // إذا لم تُعيَّن بيانات API، احفظ كـ pending
        if (empty($apiUsername) || empty($apiPassword)) {
            self::saveLog($db, (int) $invoice->id, self::STATUS_PENDING, $xml,
                '', 'API credentials not configured — invoice queued for submission');
            return [
                'success' => false,
                'status'  => self::STATUS_PENDING,
                'message' => 'API credentials not configured. Invoice saved as pending.'
            ];
        }

        // إرسال HTTP
        $result = self::sendHTTP($apiUrl, $xml, $apiUsername, $apiPassword);

        if ($result['http_code'] >= 200 && $result['http_code'] < 300) {
            $responseData = json_decode($result['body'], true);
            $uuid = isset($responseData['uuid']) ? $responseData['uuid'] : '';
            self::saveLog($db, (int) $invoice->id, self::STATUS_ACCEPTED, $xml,
                $result['body'], 'Accepted by e-Fatura', $uuid);
            return [
                'success' => true,
                'status'  => self::STATUS_ACCEPTED,
                'message' => 'Invoice accepted by e-Fatura Jordan',
                'uuid'    => $uuid,
            ];
        } else {
            $errMsg = 'HTTP ' . $result['http_code'] . ': ' . substr($result['body'], 0, 300);
            self::saveLog($db, (int) $invoice->id, self::STATUS_ERROR, $xml, $result['body'], $errMsg);
            return [
                'success' => false,
                'status'  => self::STATUS_ERROR,
                'message' => $errMsg,
            ];
        }
    }

    /**
     * إرسال HTTP POST بـ Basic Auth
     */
    private static function sendHTTP($url, $xml, $username, $password)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $xml,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/xml',
                'Accept: application/json',
            ],
            CURLOPT_USERPWD        => $username . ':' . $password,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body      = curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return ['http_code' => 0, 'body' => 'cURL error: ' . $curlError];
        }
        return ['http_code' => $httpCode, 'body' => (string) $body];
    }

    /**
     * حفظ/تحديث سجل الإرسال
     */
    private static function saveLog($db, $invoiceId, $status, $xml, $response, $message, $uuid = '')
    {
        $table  = MAIN_DB_PREFIX . self::TABLE;
        $exists = $db->query("SELECT rowid FROM {$table} WHERE fk_invoice=" . (int) $invoiceId);

        $xmlEsc      = $db->escape($xml);
        $responseEsc = $db->escape(substr($response, 0, 4000));
        $statusEsc   = $db->escape($status);
        $uuidEsc     = $db->escape($uuid);
        $sentAt      = ($status !== self::STATUS_PENDING) ? "'" . date('Y-m-d H:i:s') . "'" : 'NULL';

        if ($exists && $db->num_rows($exists) > 0) {
            $db->query("UPDATE {$table} SET status='{$statusEsc}', sent_at={$sentAt},
                response='{$responseEsc}', uuid='{$uuidEsc}', xml_data='{$xmlEsc}'
                WHERE fk_invoice=" . (int) $invoiceId);
        } else {
            $db->query("INSERT INTO {$table}
                (fk_invoice, status, sent_at, response, uuid, xml_data)
                VALUES (" . (int) $invoiceId . ", '{$statusEsc}', {$sentAt},
                '{$responseEsc}', '{$uuidEsc}', '{$xmlEsc}')");
        }

        dol_syslog('e-Fatura JO invoice #' . $invoiceId . ': ' . $status . ' — ' . $message, LOG_INFO);
    }

    /**
     * جلب حالة الإرسال لفاتورة معينة
     *
     * @param DoliDB $db
     * @param int    $invoiceId
     * @return array|null
     */
    public static function getStatus($db, $invoiceId)
    {
        self::ensureTable($db);
        $res = $db->query("SELECT * FROM " . MAIN_DB_PREFIX . self::TABLE
            . " WHERE fk_invoice=" . (int) $invoiceId . " LIMIT 1");
        if ($res && $db->num_rows($res) > 0) {
            return (array) $db->fetch_object($res);
        }
        return null;
    }

    /**
     * إعادة إرسال فاتورة مرفوضة أو معلّقة
     */
    public static function resubmit($db, $invoiceId, $mysoc, $conf)
    {
        $invoice = new Facture($db);
        if ($invoice->fetch($invoiceId) <= 0) {
            return ['success' => false, 'message' => 'Invoice not found'];
        }
        $invoice->fetch_lines();
        return self::submitInvoice($db, $invoice, $mysoc, $conf);
    }

    /**
     * عرض بادج حالة e-Fatura في الفاتورة (HTML)
     */
    public static function renderStatusBadge($db, $invoiceId)
    {
        if (!self::isEnabled()) return '';
        $log = self::getStatus($db, $invoiceId);
        if (!$log) return '';

        $colors = [
            self::STATUS_PENDING  => ['#f59e0b', '#fffbeb', 'معلّق | Pending'],
            self::STATUS_SENT     => ['#3b82f6', '#eff6ff', 'مُرسَل | Sent'],
            self::STATUS_ACCEPTED => ['#10b981', '#f0fdf4', 'مقبول ✓ | Accepted'],
            self::STATUS_REJECTED => ['#ef4444', '#fef2f2', 'مرفوض ✗ | Rejected'],
            self::STATUS_ERROR    => ['#6b7280', '#f9fafb', 'خطأ | Error'],
        ];

        $s = $log['status'];
        $c = isset($colors[$s]) ? $colors[$s] : ['#6b7280', '#f9fafb', $s];

        $html  = '<div style="margin:8px 0;padding:6px 12px;border-radius:6px;border:1px solid ' . $c[0] . ';background:' . $c[1] . ';font-size:12px;display:inline-block">';
        $html .= '<span style="color:' . $c[0] . ';font-weight:bold">🇯🇴 e-Fatura: ' . $c[2] . '</span>';
        if (!empty($log['uuid'])) {
            $html .= ' <span style="color:#666;font-size:11px">UUID: ' . htmlspecialchars($log['uuid']) . '</span>';
        }
        if (!empty($log['sent_at'])) {
            $html .= ' <span style="color:#888;font-size:11px">' . $log['sent_at'] . '</span>';
        }
        $html .= '</div>';

        return $html;
    }
}
