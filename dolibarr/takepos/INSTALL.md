# 🎨 TakePOS — Professional Redesign Patch

تحسين بصري احترافي لواجهة TakePOS في Dolibarr.

## ✨ المميزات

- **تصميم احترافي حديث**: ألوان متناسقة، خطوط عربية واضحة (IBM Plex Sans Arabic)
- **شريط علوي داكن**: مظهر مدمج يوفر مساحة أكبر للعمل
- **بطاقات منتجات محسّنة**: ظلال لطيفة، تفاعل سلس، شارة سعر بارزة
- **لوحة Shortcuts متطورة**:
  - Drawer جانبي بانزلاق ناعم + overlay مع blur
  - شريط بحث ديناميكي (يفلتر الاختصارات أثناء الكتابة)
  - عداد عدد الاختصارات لكل قسم
  - أيقونات ملونة لكل قسم (5 ألوان حسب الفئة)
  - اختصارات لوحة المفاتيح: `Ctrl+K` للفتح، `Esc` للإغلاق

## 🛡️ الأمان

✅ **لا يعدّل أي وظيفة من وظائف الموديول** — فقط CSS overrides + إضافة markup
✅ **يحافظ على نفس الـ IDs والـ classes الأصلية** — JavaScript الموجود يعمل كما هو
✅ **سهل الإلغاء** — احذف سطرًا واحدًا في `index.php` وستعود الواجهة لشكلها الأصلي
✅ **متوافق مع RTL** بشكل كامل — يدعم العربية والإنجليزية

---

## 📦 محتويات الحزمة

```
takepos_patch/
├── css/
│   └── pos_redesign.css           (ملف الأنماط الجديد)
├── partials/
│   └── shortcuts_drawer.php       (نسخة محسّنة من ملف الـ drawer الأصلي)
└── INSTALL.md                     (هذا الملف)
```

---

## 🚀 خطوات التثبيت

### 1️⃣ أخذ نسخة احتياطية (مهم جداً!)

```bash
cd /var/www/dolibarr/htdocs/takepos
cp partials/shortcuts_drawer.php partials/shortcuts_drawer.php.bak
cp index.php index.php.bak
```

### 2️⃣ نسخ الملفات الجديدة

انسخ الملفين المرفقين إلى المسارات التالية:

| الملف الجديد | المسار في الموديول |
|---|---|
| `css/pos_redesign.css` | `takepos/css/pos_redesign.css` |
| `partials/shortcuts_drawer.php` | `takepos/partials/shortcuts_drawer.php` (يستبدل القديم) |

### 3️⃣ تعديل واحد بسيط في `index.php`

افتح `takepos/index.php` وابحث عن السطر **735**:

```php
$arrayofcss = array('/takepos/css/pos.css.php?v=20260419layout2', '/takepos/css/colorbox.css');
```

استبدله بـ:

```php
$arrayofcss = array('/takepos/css/pos.css.php?v=20260419layout2', '/takepos/css/colorbox.css', '/takepos/css/pos_redesign.css?v=20260428pro1');
```

(فقط أضفنا `'/takepos/css/pos_redesign.css?v=20260428pro1'` إلى المصفوفة)

### 4️⃣ مسح الكاش

- **في Dolibarr**: اذهب لـ Setup → Other Setup → Purge Cache
- **في المتصفح**: اضغط `Ctrl+Shift+R` أو `Cmd+Shift+R`

### 5️⃣ افتح TakePOS وتمتّع بالواجهة الجديدة! 🎉

---

## ⚙️ التخصيص

### تغيير الألوان

افتح `css/pos_redesign.css` وعدّل المتغيرات في أعلى الملف:

```css
:root {
  --tpx-primary:    #1e40af;   /* اللون الأساسي */
  --tpx-bg-dark:    #0f172a;   /* لون الشريط العلوي */
  --tpx-success:    #047857;   /* لون النجاح */
  /* ... */
}
```

### تعطيل الخط العربي الجديد

إذا كنت تفضل الخط الافتراضي، احذف هذا الجزء من بداية الملف:

```css
@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic...');
```

وعطّل القسم:

```css
body.bodytakepos.tp-rtl,
body.bodytakepos.tp-rtl input,
... {
  font-family: var(--tpx-font-ar) !important;
}
```

### إخفاء النقطة النابضة على زر Shortcuts

في `pos_redesign.css`، عدّل:

```css
#takepos-shortcuts-launcher::after {
  display: none !important;  /* أضف هذا السطر */
}
```

---

## 🔄 إلغاء التثبيت

### الطريقة السريعة (بدون حذف ملفات):

في `index.php` سطر 735، احذف العنصر الجديد فقط:

```php
$arrayofcss = array('/takepos/css/pos.css.php?v=20260419layout2', '/takepos/css/colorbox.css');
```

ستعود الواجهة لشكلها الأصلي فورًا.

### الطريقة الكاملة:

```bash
cd /var/www/dolibarr/htdocs/takepos
mv partials/shortcuts_drawer.php.bak partials/shortcuts_drawer.php
mv index.php.bak index.php
rm css/pos_redesign.css
```

---

## 🧪 الاختبار قبل الإنتاج

يُنصح بشدة باختبار التحديث في بيئة Staging قبل تطبيقه على الإنتاج:

1. اختبر إضافة منتجات للسلة ✓
2. اختبر فتح الـ Shortcuts والبحث داخلها ✓
3. اختبر `Ctrl+K` و `Esc` ✓
4. اختبر التبديل بين العربية والإنجليزية ✓
5. اختبر على شاشات بأحجام مختلفة ✓
6. اختبر عملية الدفع كاملة ✓

---

## 📞 الدعم

إذا واجهت أي مشكلة:
- تحقق من أن المسارات صحيحة
- تحقق من أن المتصفح حدّث الكاش
- افتح Developer Tools (F12) لرؤية أي أخطاء

**نسخة الباتش**: `2026-04-28-pro1`
**متوافق مع**: Dolibarr 18+ / TakePOS module
