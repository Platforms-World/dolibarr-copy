<?php
/* Copyright (C) 2025 TakePOS Custom - نظام الفوترة الإقليمية
 *
 * ملف إعداد نظام الفوترة حسب الدولة (الأردن / المملكة العربية السعودية)
 * يتيح هذا الملف اختيار نظام الفوترة المعتمد في كل دولة
 * وضبط المعايير الخاصة بكل نظام (QR، ضريبة، رقم الفاتورة...)
 *
 * \file       htdocs/takepos/admin/billing_country.php
 * \ingroup    takepos
 * \brief      إعداد نظام الفوترة الإقليمية لـ TakePOS
 */

// Load Dolibarr environment
require '../../main.inc.php';
require_once __DIR__ . '/../lib/takepos_help.php';
require_once DOL_DOCUMENT_ROOT . '/takepos/lib/takepos_access_guard.php';
takeposAccessGuardCurrent($db, isset($user) ? $user : null, __FILE__);
require_once __DIR__ . '/../class/TakeposAccess.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . "/core/lib/takepos.lib.php";

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Societe $mysoc
 * @var Translate $langs
 * @var User $user
 */

// Security check
if (!$user->admin) {
    accessforbidden();
}

TakeposAccess::enforceAdmin($db, $user, 'takepos.admin.billing_country', null);

$langs->loadLangs(array("admin", "cashdesk", "companies"));

/*
 * Actions
 */
$error = 0;

if (GETPOST('action', 'alpha') == 'set_billing_country') {
    $db->begin();

    // حفظ نظام الفوترة المختار
    $res = dolibarr_set_const($db, "TAKEPOS_BILLING_COUNTRY", GETPOST('TAKEPOS_BILLING_COUNTRY', 'alpha'), 'chaine', 0, '', $conf->entity);

    // إعدادات الأردن
    if (GETPOST('TAKEPOS_BILLING_COUNTRY', 'alpha') === 'JO') {
        $res = dolibarr_set_const($db, "TAKEPOS_JO_VAT_NUMBER", GETPOST('TAKEPOS_JO_VAT_NUMBER', 'alpha'), 'chaine', 0, '', $conf->entity);
        $res = dolibarr_set_const($db, "TAKEPOS_JO_NATIONAL_NUMBER", GETPOST('TAKEPOS_JO_NATIONAL_NUMBER', 'alpha'), 'chaine', 0, '', $conf->entity);
        $res = dolibarr_set_const($db, "TAKEPOS_JO_ENABLE_EFATURA", GETPOST('TAKEPOS_JO_ENABLE_EFATURA', 'alpha'), 'chaine', 0, '', $conf->entity);
        $res = dolibarr_set_const($db, "TAKEPOS_JO_TAXPAYER_TYPE", GETPOST('TAKEPOS_JO_TAXPAYER_TYPE', 'alpha'), 'chaine', 0, '', $conf->entity);
        // بيانات API e-Fatura
        $res = dolibarr_set_const($db, "TAKEPOS_JO_EFATURA_API_URL", GETPOST('TAKEPOS_JO_EFATURA_API_URL', 'alpha'), 'chaine', 0, '', $conf->entity);
        $res = dolibarr_set_const($db, "TAKEPOS_JO_EFATURA_USERNAME", GETPOST('TAKEPOS_JO_EFATURA_USERNAME', 'alpha'), 'chaine', 0, '', $conf->entity);
        if (GETPOST('TAKEPOS_JO_EFATURA_PASSWORD', 'none') !== '••••••••') {
            $res = dolibarr_set_const($db, "TAKEPOS_JO_EFATURA_PASSWORD", GETPOST('TAKEPOS_JO_EFATURA_PASSWORD', 'none'), 'chaine', 0, '', $conf->entity);
        }
    }

    // إعدادات السعودية (ZATCA)
    if (GETPOST('TAKEPOS_BILLING_COUNTRY', 'alpha') === 'SA') {
        $res = dolibarr_set_const($db, "TAKEPOS_SA_VAT_NUMBER", GETPOST('TAKEPOS_SA_VAT_NUMBER', 'alpha'), 'chaine', 0, '', $conf->entity);
        $res = dolibarr_set_const($db, "TAKEPOS_SA_CR_NUMBER", GETPOST('TAKEPOS_SA_CR_NUMBER', 'alpha'), 'chaine', 0, '', $conf->entity);
        $res = dolibarr_set_const($db, "TAKEPOS_SA_INVOICE_TYPE", GETPOST('TAKEPOS_SA_INVOICE_TYPE', 'alpha'), 'chaine', 0, '', $conf->entity);
        $res = dolibarr_set_const($db, "TAKEPOS_SA_ZATCA_PHASE", GETPOST('TAKEPOS_SA_ZATCA_PHASE', 'alpha'), 'chaine', 0, '', $conf->entity);
        $res = dolibarr_set_const($db, "TAKEPOS_SA_ZATCA_ENV", GETPOST('TAKEPOS_SA_ZATCA_ENV', 'alpha'), 'chaine', 0, '', $conf->entity);
        $res = dolibarr_set_const($db, "TAKEPOS_SA_SELLER_NAME", GETPOST('TAKEPOS_SA_SELLER_NAME', 'alpha'), 'chaine', 0, '', $conf->entity);
        $res = dolibarr_set_const($db, "TAKEPOS_SA_STREET", GETPOST('TAKEPOS_SA_STREET', 'alpha'), 'chaine', 0, '', $conf->entity);
        $res = dolibarr_set_const($db, "TAKEPOS_SA_CITY", GETPOST('TAKEPOS_SA_CITY', 'alpha'), 'chaine', 0, '', $conf->entity);
        $res = dolibarr_set_const($db, "TAKEPOS_SA_DISTRICT", GETPOST('TAKEPOS_SA_DISTRICT', 'alpha'), 'chaine', 0, '', $conf->entity);
        $res = dolibarr_set_const($db, "TAKEPOS_SA_POSTAL_CODE", GETPOST('TAKEPOS_SA_POSTAL_CODE', 'alpha'), 'chaine', 0, '', $conf->entity);
        $res = dolibarr_set_const($db, "TAKEPOS_SA_BUILDING_NUMBER", GETPOST('TAKEPOS_SA_BUILDING_NUMBER', 'alpha'), 'chaine', 0, '', $conf->entity);
    }

    // إجراءات ZATCA Phase 2 Onboarding
    if (GETPOST('action', 'alpha') === 'zatca_onboard_csid') {
        require_once DOL_DOCUMENT_ROOT . '/takepos/lib/takepos_zatca_sa.php';
        $csr = GETPOST('zatca_csr', 'none');
        $otp = GETPOST('zatca_otp', 'alpha');
        if ($csr && $otp) {
            $onboard = TakeposZatcaSA::onboardingGetCSID($db, $csr, $otp);
            setEventMessages($onboard['message'], null, $onboard['success'] ? 'mesgs' : 'errors');
        }
    }
    if (GETPOST('action', 'alpha') === 'zatca_production_csid') {
        require_once DOL_DOCUMENT_ROOT . '/takepos/lib/takepos_zatca_sa.php';
        $onboard = TakeposZatcaSA::onboardingGetProductionCSID($db, $conf);
        setEventMessages($onboard['message'], null, $onboard['success'] ? 'mesgs' : 'errors');
    }

    if (!($res > 0)) {
        $error++;
    }

    if (!$error) {
        $db->commit();
        setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
    } else {
        $db->rollback();
        setEventMessages($langs->trans("Error"), null, 'errors');
    }
}

/*
 * View
 */
$form = new Form($db);

$billingCountry = getDolGlobalString('TAKEPOS_BILLING_COUNTRY', '');

llxHeader('', 'نظام الفوترة الإقليمية', '', '', 0, 0, '', '', '', 'mod-takepos page-admin_billing_country');

$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">' . $langs->trans("BackToModuleList") . '</a>';
print load_fiche_titre('إعداد نظام الفوترة (TakePOS)', $linkback, 'title_setup');

$head = takepos_admin_prepare_head();
print dol_get_fiche_head($head, 'billing_country', 'TakePOS', -1, 'cash-register');

?>
<style>
.billing-card {
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    padding: 20px;
    margin: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
    display: inline-block;
    width: 200px;
    vertical-align: top;
}
.billing-card:hover {
    border-color: #0052cc;
    box-shadow: 0 4px 12px rgba(0,82,204,0.2);
}
.billing-card.selected {
    border-color: #0052cc;
    background-color: #e8f0fe;
    box-shadow: 0 4px 12px rgba(0,82,204,0.3);
}
.billing-card .flag {
    font-size: 48px;
    margin-bottom: 10px;
    display: block;
}
.billing-card .country-name {
    font-size: 16px;
    font-weight: bold;
    color: #333;
    display: block;
    margin-bottom: 5px;
}
.billing-card .system-name {
    font-size: 12px;
    color: #666;
    display: block;
}
.section-settings {
    display: none;
    margin-top: 20px;
}
.section-settings.active {
    display: block;
}
.info-box {
    background: #f0f7ff;
    border-left: 4px solid #0052cc;
    padding: 12px 16px;
    margin: 15px 0;
    border-radius: 4px;
    font-size: 13px;
    line-height: 1.6;
}
.info-box.warning {
    background: #fff8e1;
    border-left-color: #f9a825;
}
.info-box.success {
    background: #e8f5e9;
    border-left-color: #2e7d32;
}
.field-group {
    margin-bottom: 15px;
}
.field-group label {
    display: block;
    font-weight: bold;
    margin-bottom: 5px;
    color: #444;
}
.field-group input, .field-group select {
    width: 100%;
    max-width: 400px;
    padding: 8px 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 14px;
}
.field-group .hint {
    font-size: 11px;
    color: #888;
    margin-top: 3px;
}
.standards-list {
    list-style: none;
    padding: 0;
}
.standards-list li {
    padding: 6px 0;
    border-bottom: 1px solid #eee;
    font-size: 13px;
}
.standards-list li:before {
    content: "✓ ";
    color: #2e7d32;
    font-weight: bold;
}
.badge-required {
    background: #d32f2f;
    color: white;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 10px;
    margin-left: 5px;
}
.badge-optional {
    background: #616161;
    color: white;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 10px;
    margin-left: 5px;
}
</style>

<form action="<?php echo $_SERVER["PHP_SELF"]; ?>" method="post" id="billing_form">
<input type="hidden" name="token" value="<?php echo newToken(); ?>">
<input type="hidden" name="action" value="set_billing_country">
<input type="hidden" name="TAKEPOS_BILLING_COUNTRY" id="TAKEPOS_BILLING_COUNTRY" value="<?php echo htmlspecialchars($billingCountry); ?>">

<div class="div-table-responsive-no-min">

    <!-- اختيار الدولة -->
    <?php print load_fiche_titre('اختر نظام الفوترة المعتمد', '', ''); ?>

    <div style="margin: 20px 0; text-align: center;">

        <!-- الأردن -->
        <div class="billing-card <?php echo ($billingCountry === 'JO') ? 'selected' : ''; ?>"
             id="card_JO"
             onclick="selectCountry('JO')">
            <span class="flag">🇯🇴</span>
            <span class="country-name">المملكة الأردنية الهاشمية</span>
            <span class="system-name">نظام الفوترة الإلكترونية<br>دائرة ضريبة الدخل والمبيعات</span>
        </div>

        <!-- السعودية -->
        <div class="billing-card <?php echo ($billingCountry === 'SA') ? 'selected' : ''; ?>"
             id="card_SA"
             onclick="selectCountry('SA')">
            <span class="flag">🇸🇦</span>
            <span class="country-name">المملكة العربية السعودية</span>
            <span class="system-name">نظام ZATCA<br>هيئة الزكاة والضريبة والجمارك</span>
        </div>

    </div>

    <!-- =========================== -->
    <!-- إعدادات الأردن -->
    <!-- =========================== -->
    <div id="settings_JO" class="section-settings <?php echo ($billingCountry === 'JO') ? 'active' : ''; ?>">

        <?php print load_fiche_titre('🇯🇴 إعدادات الفوترة الأردنية', '', ''); ?>

        <div class="info-box">
            <strong>نظام الفوترة الإلكترونية الأردني</strong><br>
            يلتزم هذا النظام بمتطلبات دائرة ضريبة الدخل والمبيعات الأردنية.
            سيتم توليد QR Code يحتوي على بيانات الفاتورة وفق المعيار الأردني.
        </div>

        <div class="info-box success">
            <strong>المعايير المُطبَّقة تلقائياً:</strong>
            <ul class="standards-list" style="margin:8px 0 0 0">
                <li>رقم الفاتورة الضريبية بالتسلسل</li>
                <li>اسم البائع وعنوانه بالعربية</li>
                <li>الرقم الضريبي للبائع (TIN)</li>
                <li>تاريخ ووقت إصدار الفاتورة</li>
                <li>المبلغ الإجمالي شامل ضريبة المبيعات</li>
                <li>ضريبة المبيعات (GST) 16%</li>
                <li>QR Code يحتوي رقم الفاتورة والبيانات الضريبية</li>
            </ul>
        </div>

        <table class="noborder centpercent">
            <tr class="liste_titre">
                <td colspan="2">بيانات التسجيل الضريبي - الأردن</td>
            </tr>

            <tr class="oddeven">
                <td width="40%">
                    الرقم الضريبي (TIN) <span class="badge-required">إلزامي</span>
                </td>
                <td>
                    <div class="field-group">
                        <input type="text"
                               name="TAKEPOS_JO_VAT_NUMBER"
                               value="<?php echo getDolGlobalString('TAKEPOS_JO_VAT_NUMBER'); ?>"
                               placeholder="مثال: 123456789"
                               maxlength="20">
                        <div class="hint">الرقم الضريبي المسجل لدى دائرة ضريبة الدخل والمبيعات</div>
                    </div>
                </td>
            </tr>

            <tr class="oddeven">
                <td>
                    الرقم الوطني للمنشأة <span class="badge-optional">اختياري</span>
                </td>
                <td>
                    <div class="field-group">
                        <input type="text"
                               name="TAKEPOS_JO_NATIONAL_NUMBER"
                               value="<?php echo getDolGlobalString('TAKEPOS_JO_NATIONAL_NUMBER'); ?>"
                               placeholder="رقم السجل التجاري">
                        <div class="hint">رقم تسجيل الشركة في وزارة الصناعة والتجارة</div>
                    </div>
                </td>
            </tr>

            <tr class="oddeven">
                <td>نوع دافع الضريبة</td>
                <td>
                    <div class="field-group">
                        <select name="TAKEPOS_JO_TAXPAYER_TYPE">
                            <option value="B2C" <?php echo (getDolGlobalString('TAKEPOS_JO_TAXPAYER_TYPE') === 'B2C') ? 'selected' : ''; ?>>
                                B2C - بيع للمستهلك النهائي
                            </option>
                            <option value="B2B" <?php echo (getDolGlobalString('TAKEPOS_JO_TAXPAYER_TYPE') === 'B2B') ? 'selected' : ''; ?>>
                                B2B - بيع بين الشركات
                            </option>
                        </select>
                    </div>
                </td>
            </tr>

            <tr class="oddeven">
                <td>تفعيل ربط الفوترة الإلكترونية (e-Fatura)</td>
                <td>
                    <?php print ajax_constantonoff("TAKEPOS_JO_ENABLE_EFATURA", array(), $conf->entity, 0, 0, 1, 0); ?>
                    <div class="hint" style="margin-top:5px">يمكّن إرسال الفواتير إلكترونياً لمنظومة دائرة ضريبة الدخل والمبيعات</div>
                </td>
            </tr>

            <?php if (getDolGlobalString('TAKEPOS_JO_ENABLE_EFATURA') == 1): ?>
            <tr>
                <td colspan="2">
                    <div style="background:#fff8e1;border-left:4px solid #f59e0b;padding:12px 16px;margin:8px 0;border-radius:4px;font-size:13px;">
                        <strong>⚙️ إعدادات API الاتصال بـ e-Fatura</strong><br>
                        <span style="color:#666;">أدخل بيانات الاعتماد المُقدَّمة من دائرة ضريبة الدخل والمبيعات للربط التلقائي.</span>
                    </div>
                </td>
            </tr>

            <tr class="oddeven">
                <td>رابط API e-Fatura</td>
                <td>
                    <div class="field-group">
                        <input type="text" name="TAKEPOS_JO_EFATURA_API_URL"
                               value="<?php echo dol_escape_htmltag(getDolGlobalString('TAKEPOS_JO_EFATURA_API_URL', 'https://efatura.jo/api/v1/invoices')); ?>"
                               style="min-width:400px">
                        <div class="hint">رابط API دائرة ضريبة الدخل والمبيعات</div>
                    </div>
                </td>
            </tr>

            <tr class="oddeven">
                <td>اسم المستخدم (API Username) <span class="badge-required">إلزامي</span></td>
                <td>
                    <div class="field-group">
                        <input type="text" name="TAKEPOS_JO_EFATURA_USERNAME"
                               value="<?php echo dol_escape_htmltag(getDolGlobalString('TAKEPOS_JO_EFATURA_USERNAME')); ?>"
                               autocomplete="off" style="min-width:300px">
                        <div class="hint">اسم المستخدم المقدَّم من دائرة ضريبة الدخل والمبيعات</div>
                    </div>
                </td>
            </tr>

            <tr class="oddeven">
                <td>كلمة المرور (API Password) <span class="badge-required">إلزامي</span></td>
                <td>
                    <div class="field-group">
                        <input type="password" name="TAKEPOS_JO_EFATURA_PASSWORD"
                               value="<?php echo getDolGlobalString('TAKEPOS_JO_EFATURA_PASSWORD') ? '••••••••' : ''; ?>"
                               autocomplete="new-password" style="min-width:300px">
                        <div class="hint">اترك فارغاً إذا لم تكن تريد تغيير كلمة المرور الحالية</div>
                    </div>
                </td>
            </tr>

            <tr class="oddeven">
                <td colspan="2">
                    <?php
                    $apiUser = getDolGlobalString('TAKEPOS_JO_EFATURA_USERNAME');
                    $apiPass = getDolGlobalString('TAKEPOS_JO_EFATURA_PASSWORD');
                    if ($apiUser && $apiPass) {
                        echo '<div style="background:#f0fdf4;border:1px solid #bbf7d0;padding:8px 14px;border-radius:4px;font-size:12px;color:#166534">';
                        echo '✅ بيانات API مُعيَّنة — سيتم إرسال الفواتير تلقائياً عند الدفع.';
                        echo '</div>';
                    } else {
                        echo '<div style="background:#fef2f2;border:1px solid #fca5a5;padding:8px 14px;border-radius:4px;font-size:12px;color:#991b1b">';
                        echo '⚠️ بيانات API غير مُعيَّنة — ستُحفظ الفواتير كـ "معلّقة" حتى يتم إدخال بيانات الاعتماد.';
                        echo '</div>';
                    }
                    ?>
                </td>
            </tr>
            <?php endif; ?>
        </table>

    </div>

    <!-- =========================== -->
    <!-- إعدادات السعودية (ZATCA) -->
    <!-- =========================== -->
    <div id="settings_SA" class="section-settings <?php echo ($billingCountry === 'SA') ? 'active' : ''; ?>">

        <?php print load_fiche_titre('🇸🇦 إعدادات الفوترة السعودية - ZATCA', '', ''); ?>

        <div class="info-box">
            <strong>نظام ZATCA - هيئة الزكاة والضريبة والجمارك</strong><br>
            يلتزم هذا النظام بمتطلبات الفوترة الإلكترونية السعودية (FATOORA) بالمرحلة الأولى والثانية.
            سيتم توليد QR Code وفق معيار TLV (Tag-Length-Value) المعتمد من ZATCA.
        </div>

        <div class="info-box success">
            <strong>المعايير المُطبَّقة تلقائياً (ZATCA FATOORA):</strong>
            <ul class="standards-list" style="margin:8px 0 0 0">
                <li>اسم البائع (Seller Name) - TLV Tag 1</li>
                <li>الرقم الضريبي VAT (15 رقم) - TLV Tag 2</li>
                <li>طابع التاريخ والوقت - TLV Tag 3</li>
                <li>إجمالي الفاتورة شامل الضريبة - TLV Tag 4</li>
                <li>مبلغ ضريبة القيمة المضافة 15% - TLV Tag 5</li>
                <li>QR Code مشفر بـ Base64 (TLV)</li>
                <li>رقم الفاتورة التسلسلي بصيغة ZATCA</li>
                <li>بيانات العنوان الوطني (رقم المبنى، الشارع، الحي، المدينة، الرمز البريدي)</li>
            </ul>
        </div>

        <table class="noborder centpercent">
            <tr class="liste_titre">
                <td colspan="2">بيانات التسجيل الضريبي - المملكة العربية السعودية</td>
            </tr>

            <tr class="oddeven">
                <td width="40%">
                    الرقم الضريبي VAT <span class="badge-required">إلزامي</span>
                </td>
                <td>
                    <div class="field-group">
                        <input type="text"
                               name="TAKEPOS_SA_VAT_NUMBER"
                               value="<?php echo getDolGlobalString('TAKEPOS_SA_VAT_NUMBER'); ?>"
                               placeholder="مثال: 310122393500003"
                               maxlength="15"
                               pattern="[0-9]{15}">
                        <div class="hint">الرقم الضريبي المكوّن من 15 رقماً المسجل لدى ZATCA</div>
                    </div>
                </td>
            </tr>

            <tr class="oddeven">
                <td>
                    اسم البائع بالعربية <span class="badge-required">إلزامي</span>
                </td>
                <td>
                    <div class="field-group">
                        <input type="text"
                               name="TAKEPOS_SA_SELLER_NAME"
                               value="<?php echo getDolGlobalString('TAKEPOS_SA_SELLER_NAME'); ?>"
                               placeholder="الاسم التجاري المسجل">
                        <div class="hint">الاسم كما هو مسجل لدى هيئة الزكاة والضريبة والجمارك</div>
                    </div>
                </td>
            </tr>

            <tr class="oddeven">
                <td>
                    رقم السجل التجاري (CR) <span class="badge-required">إلزامي</span>
                </td>
                <td>
                    <div class="field-group">
                        <input type="text"
                               name="TAKEPOS_SA_CR_NUMBER"
                               value="<?php echo getDolGlobalString('TAKEPOS_SA_CR_NUMBER'); ?>"
                               placeholder="مثال: 1010000000">
                        <div class="hint">رقم السجل التجاري المكوّن من 10 أرقام</div>
                    </div>
                </td>
            </tr>

            <tr class="oddeven">
                <td colspan="2"><strong>العنوان الوطني</strong></td>
            </tr>

            <tr class="oddeven">
                <td>رقم المبنى <span class="badge-required">إلزامي</span></td>
                <td>
                    <input type="text" name="TAKEPOS_SA_BUILDING_NUMBER"
                           value="<?php echo getDolGlobalString('TAKEPOS_SA_BUILDING_NUMBER'); ?>"
                           placeholder="مثال: 1234" maxlength="4">
                </td>
            </tr>

            <tr class="oddeven">
                <td>اسم الشارع <span class="badge-required">إلزامي</span></td>
                <td>
                    <input type="text" name="TAKEPOS_SA_STREET"
                           value="<?php echo getDolGlobalString('TAKEPOS_SA_STREET'); ?>"
                           placeholder="اسم الشارع">
                </td>
            </tr>

            <tr class="oddeven">
                <td>اسم الحي <span class="badge-required">إلزامي</span></td>
                <td>
                    <input type="text" name="TAKEPOS_SA_DISTRICT"
                           value="<?php echo getDolGlobalString('TAKEPOS_SA_DISTRICT'); ?>"
                           placeholder="اسم الحي">
                </td>
            </tr>

            <tr class="oddeven">
                <td>المدينة <span class="badge-required">إلزامي</span></td>
                <td>
                    <input type="text" name="TAKEPOS_SA_CITY"
                           value="<?php echo getDolGlobalString('TAKEPOS_SA_CITY'); ?>"
                           placeholder="مثال: الرياض">
                </td>
            </tr>

            <tr class="oddeven">
                <td>الرمز البريدي <span class="badge-required">إلزامي</span></td>
                <td>
                    <input type="text" name="TAKEPOS_SA_POSTAL_CODE"
                           value="<?php echo getDolGlobalString('TAKEPOS_SA_POSTAL_CODE'); ?>"
                           placeholder="مثال: 12345" maxlength="5">
                </td>
            </tr>

            <tr class="oddeven">
                <td>نوع الفاتورة</td>
                <td>
                    <div class="field-group">
                        <select name="TAKEPOS_SA_INVOICE_TYPE">
                            <option value="simplified" <?php echo (getDolGlobalString('TAKEPOS_SA_INVOICE_TYPE', 'simplified') === 'simplified') ? 'selected' : ''; ?>>
                                فاتورة مبسّطة (Simplified - B2C)
                            </option>
                            <option value="standard" <?php echo (getDolGlobalString('TAKEPOS_SA_INVOICE_TYPE') === 'standard') ? 'selected' : ''; ?>>
                                فاتورة ضريبية كاملة (Standard - B2B)
                            </option>
                        </select>
                        <div class="hint">الفاتورة المبسّطة للمستهلكين الأفراد، الكاملة للشركات</div>
                    </div>
                </td>
            </tr>

            <tr class="oddeven">
                <td>مرحلة ZATCA</td>
                <td>
                    <div class="field-group">
                        <select name="TAKEPOS_SA_ZATCA_PHASE">
                            <option value="1" <?php echo (getDolGlobalString('TAKEPOS_SA_ZATCA_PHASE', '1') === '1') ? 'selected' : ''; ?>>
                                المرحلة الأولى - توليد الفاتورة الإلكترونية
                            </option>
                            <option value="2" <?php echo (getDolGlobalString('TAKEPOS_SA_ZATCA_PHASE') === '2') ? 'selected' : ''; ?>>
                                المرحلة الثانية - الربط والتكامل مع ZATCA
                            </option>
                        </select>
                    </div>
                </td>
            </tr>

            <tr class="oddeven">
                <td>بيئة ZATCA</td>
                <td>
                    <div class="field-group">
                        <select name="TAKEPOS_SA_ZATCA_ENV">
                            <option value="sandbox" <?php echo (getDolGlobalString('TAKEPOS_SA_ZATCA_ENV', 'sandbox') === 'sandbox') ? 'selected' : ''; ?>>
                                Sandbox — بيئة الاختبار
                            </option>
                            <option value="simulation" <?php echo (getDolGlobalString('TAKEPOS_SA_ZATCA_ENV') === 'simulation') ? 'selected' : ''; ?>>
                                Simulation — المحاكاة
                            </option>
                            <option value="production" <?php echo (getDolGlobalString('TAKEPOS_SA_ZATCA_ENV') === 'production') ? 'selected' : ''; ?>>
                                Production — الإنتاج (حقيقي)
                            </option>
                        </select>
                        <div class="hint">ابدأ بـ Sandbox للاختبار قبل الانتقال إلى Production</div>
                    </div>
                </td>
            </tr>
        </table>

        <?php if (getDolGlobalString('TAKEPOS_SA_ZATCA_PHASE') === '2'): ?>
        <!-- ===== ZATCA Phase 2 Onboarding Wizard ===== -->
        <?php
        $csidSaved    = getDolGlobalString('TAKEPOS_SA_ZATCA_CSID', '');
        $onboarded    = getDolGlobalString('TAKEPOS_SA_ZATCA_ONBOARDED', '');
        $requestId    = getDolGlobalString('TAKEPOS_SA_ZATCA_REQUEST_ID', '');
        ?>

        <div style="margin-top:20px;">
        <?php print load_fiche_titre('🔐 ZATCA Phase 2 — Device Onboarding | تسجيل الجهاز', '', ''); ?>

        <?php if ($onboarded): ?>
        <div class="info-box success">
            <strong>✅ الجهاز مسجّل بالكامل — Production CSID نشط</strong><br>
            الفواتير ستُرسَل تلقائياً إلى ZATCA عند الدفع.<br>
            <small style="color:#666;">CSID: <?php echo substr(dol_escape_htmltag($csidSaved), 0, 40); ?>...</small>
        </div>
        <?php elseif ($csidSaved && $requestId): ?>
        <div class="info-box warning">
            <strong>⚠️ Compliance CSID تم الحصول عليه — خطوة أخيرة متبقية</strong><br>
            اضغط "الحصول على Production CSID" لإتمام التسجيل.
        </div>
        <?php else: ?>
        <div class="info-box">
            <strong>خطوات تسجيل الجهاز في ZATCA:</strong>
            <ol style="margin:8px 0;padding-right:20px;font-size:13px;line-height:2">
                <li>أنشئ <strong>CSR</strong> (Certificate Signing Request) من بوابة ZATCA أو باستخدام أداة OpenSSL</li>
                <li>احصل على <strong>OTP</strong> من بوابة ZATCA (صالح لمدة محدودة)</li>
                <li>أدخل CSR و OTP في النموذج أدناه لاستلام <strong>Compliance CSID</strong></li>
                <li>اضغط "الحصول على Production CSID" لإتمام التسجيل</li>
            </ol>
        </div>
        <?php endif; ?>

        <?php if (!$onboarded): ?>
        <table class="noborder centpercent" style="margin-top:12px;">
            <!-- الخطوة 1: CSR + OTP -->
            <tr class="liste_titre"><th colspan="2">
                الخطوة 1: إرسال CSR وOTP للحصول على Compliance CSID
            </th></tr>
            <tr class="oddeven">
                <td width="30%">محتوى CSR (PEM)</td>
                <td>
                    <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                    <input type="hidden" name="token" value="<?php echo newToken(); ?>">
                    <input type="hidden" name="action" value="zatca_onboard_csid">
                    <input type="hidden" name="TAKEPOS_BILLING_COUNTRY" value="SA">
                    <textarea name="zatca_csr" rows="6" style="width:100%;font-family:monospace;font-size:11px;"
                        placeholder="-----BEGIN CERTIFICATE REQUEST-----&#10;...&#10;-----END CERTIFICATE REQUEST-----"></textarea>
                </td>
            </tr>
            <tr class="oddeven">
                <td>OTP (من بوابة ZATCA)</td>
                <td>
                    <input type="text" name="zatca_otp" maxlength="10" placeholder="123456" style="width:150px">
                    <br><br>
                    <input type="submit" class="button" value="🔑 الحصول على Compliance CSID | Get Compliance CSID"
                           style="background:#1aab8c;color:#fff;">
                    </form>
                </td>
            </tr>

            <?php if ($csidSaved && $requestId): ?>
            <!-- الخطوة 2: Production CSID -->
            <tr class="liste_titre"><th colspan="2">
                الخطوة 2: الحصول على Production CSID
            </th></tr>
            <tr class="oddeven">
                <td>Request ID المحفوظ</td>
                <td>
                    <code style="background:#f0f0f0;padding:4px 8px;border-radius:3px"><?php echo dol_escape_htmltag($requestId); ?></code><br><br>
                    <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                    <input type="hidden" name="token" value="<?php echo newToken(); ?>">
                    <input type="hidden" name="action" value="zatca_production_csid">
                    <input type="hidden" name="TAKEPOS_BILLING_COUNTRY" value="SA">
                    <input type="submit" class="button" value="🚀 الحصول على Production CSID"
                           style="background:#2563eb;color:#fff;">
                    </form>
                </td>
            </tr>
            <?php endif; ?>

        </table>
        <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>

    <!-- زر الحفظ -->
    <br>
    <?php print $form->buttonsSaveCancel("Save", ''); ?>

</div>
</form>

<script type="text/javascript">
function selectCountry(countryCode) {
    // Remove selected from all cards
    document.querySelectorAll('.billing-card').forEach(function(card) {
        card.classList.remove('selected');
    });

    // Add selected to clicked card
    document.getElementById('card_' + countryCode).classList.add('selected');

    // Update hidden field
    document.getElementById('TAKEPOS_BILLING_COUNTRY').value = countryCode;

    // Hide all settings sections
    document.querySelectorAll('.section-settings').forEach(function(section) {
        section.classList.remove('active');
    });

    // Show selected country settings
    var settingsDiv = document.getElementById('settings_' + countryCode);
    if (settingsDiv) {
        settingsDiv.classList.add('active');
    }
}
</script>

<?php
llxFooter();
$db->close();
