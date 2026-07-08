# دليل تركيب نظام الفوترة الإقليمية في TakePOS
## يدعم: 🇯🇴 الأردن + 🇸🇦 المملكة العربية السعودية (ZATCA)

---

## الملفات المُضافة

```
takepos/
├── admin/
│   └── billing_country.php       ← صفحة إعداد نظام الفوترة (جديد)
├── lib/
│   ├── takepos_billing_country.php   ← مكتبة QR والفاتورة (جديد)
│   └── takepos_billing_shortcut.php  ← زر الاختصار (جديد)
├── genimg/
│   └── qr_invoice.php            ← مولّد QR الإقليمي (جديد)
└── receipt.php                   ← معدَّل (أضف السطور المذكورة)
```

---

## خطوات التركيب

### 1. نسخ الملفات الجديدة

```bash
cp admin/billing_country.php    /path/to/dolibarr/htdocs/takepos/admin/
cp lib/takepos_billing_country.php   /path/to/dolibarr/htdocs/takepos/lib/
cp lib/takepos_billing_shortcut.php  /path/to/dolibarr/htdocs/takepos/lib/
cp genimg/qr_invoice.php        /path/to/dolibarr/htdocs/takepos/genimg/
```

### 2. تعديل receipt.php (استبدله بالنسخة المُعدَّلة)

```bash
cp receipt.php /path/to/dolibarr/htdocs/takepos/receipt.php
```

أو يدوياً، أضف هذا بعد `require_once __DIR__ . '/lib/takepos_help.php';`:
```php
// نظام الفوترة الإقليمية
if (file_exists(__DIR__ . '/lib/takepos_billing_country.php')) {
    require_once __DIR__ . '/lib/takepos_billing_country.php';
}
```

ثم بعد `<br>` التي تعقب اسم الشركة:
```php
<?php
if (function_exists('takeposBuildCountryReceiptHeader') && !empty($object->id)) {
    echo takeposBuildCountryReceiptHeader($object, $mysoc, $conf, $langs);
}
?>
```

وقبل سكريبت `window.print()`:
```php
<?php
if (function_exists('takeposBuildCountryReceiptFooter') && !empty($object->id)) {
    echo takeposBuildCountryReceiptFooter($object, $mysoc, $conf, $langs);
}
?>
```

### 3. إضافة التبويب في لوحة الإدارة

افتح ملف `/path/to/dolibarr/htdocs/core/lib/takepos.lib.php`
وابحث عن دالة `takepos_admin_prepare_head()` ثم أضف تبويباً جديداً:

```php
$h = 0;
$head = array();
// ... التبويبات الموجودة ...

$head[$h][0] = DOL_URL_ROOT.'/takepos/admin/billing_country.php';
$head[$h][1] = 'نظام الفوترة';
$head[$h][2] = 'billing_country';
$h++;
```

### 4. إضافة زر الاختصار في واجهة TakePOS

إن أردت إظهار زر في لوحة Shortcuts (كما في الصورة)،
أضف في ملف shortcuts.php أو أي ملف يُولِّد قائمة الاختصارات:

```php
require_once DOL_DOCUMENT_ROOT.'/takepos/lib/takepos_billing_shortcut.php';
echo takeposBillingCountryShortcutButton();
```

---

## كيفية الاستخدام

1. اذهب إلى: **TakePOS → إعداد → نظام الفوترة**
2. اختر الدولة: 🇯🇴 الأردن أو 🇸🇦 السعودية
3. أدخل البيانات المطلوبة (الرقم الضريبي، بيانات الشركة...)
4. اضغط **حفظ**

من الآن فصاعداً، ستحتوي كل فاتورة تُطبع على:
- رأس فاتورة يتضمن البيانات الرسمية للدولة المختارة
- QR Code مولَّد وفق معيار الدولة
- تذييل مناسب

---

## معايير كل دولة

### 🇯🇴 الأردن - دائرة ضريبة الدخل والمبيعات

| الحقل | التفاصيل |
|-------|----------|
| الرقم الضريبي TIN | إلزامي |
| رقم السجل التجاري | اختياري |
| نوع الفاتورة | مبسّطة (B2C) / كاملة (B2B) |
| الضريبة | ضريبة مبيعات 16% |
| محتوى QR | نص مقروء (اسم، رقم ضريبي، مبالغ، تاريخ) |

### 🇸🇦 السعودية - ZATCA / FATOORA

| الحقل | التفاصيل |
|-------|----------|
| الرقم الضريبي VAT | 15 رقم، إلزامي |
| اسم البائع | إلزامي |
| السجل التجاري | 10 أرقام، إلزامي |
| العنوان الوطني | رقم مبنى + شارع + حي + مدينة + رمز بريدي |
| نوع الفاتورة | مبسّطة (B2C) / ضريبية كاملة (B2B) |
| الضريبة | ضريبة قيمة مضافة 15% |
| محتوى QR | TLV مشفّر Base64 (5 حقول معيار ZATCA) |

**حقول TLV السعودية:**
- Tag 1: اسم البائع
- Tag 2: الرقم الضريبي
- Tag 3: التاريخ والوقت (ISO 8601)
- Tag 4: إجمالي الفاتورة شامل الضريبة
- Tag 5: مبلغ ضريبة القيمة المضافة

---

## ثوابت قاعدة البيانات (llx_const)

### مشتركة
| الثابت | الوصف |
|--------|-------|
| `TAKEPOS_BILLING_COUNTRY` | `JO` أو `SA` |

### الأردن
| الثابت | الوصف |
|--------|-------|
| `TAKEPOS_JO_VAT_NUMBER` | الرقم الضريبي |
| `TAKEPOS_JO_NATIONAL_NUMBER` | رقم السجل التجاري |
| `TAKEPOS_JO_TAXPAYER_TYPE` | B2C أو B2B |
| `TAKEPOS_JO_ENABLE_EFATURA` | تفعيل ربط e-Fatura |

### السعودية
| الثابت | الوصف |
|--------|-------|
| `TAKEPOS_SA_VAT_NUMBER` | الرقم الضريبي (15 رقم) |
| `TAKEPOS_SA_SELLER_NAME` | اسم البائع |
| `TAKEPOS_SA_CR_NUMBER` | رقم السجل التجاري |
| `TAKEPOS_SA_STREET` | اسم الشارع |
| `TAKEPOS_SA_DISTRICT` | اسم الحي |
| `TAKEPOS_SA_CITY` | المدينة |
| `TAKEPOS_SA_POSTAL_CODE` | الرمز البريدي |
| `TAKEPOS_SA_BUILDING_NUMBER` | رقم المبنى |
| `TAKEPOS_SA_INVOICE_TYPE` | simplified أو standard |
| `TAKEPOS_SA_ZATCA_PHASE` | 1 أو 2 |
