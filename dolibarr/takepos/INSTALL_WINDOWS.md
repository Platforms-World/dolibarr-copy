# 🪟 دليل التثبيت على Windows + XAMPP

دليل سريع وبسيط للتثبيت على بيئة Windows مع XAMPP.

---

## 🎯 الطريقة الأولى: تلقائية (موصى بها)

### الخطوات:

1. **فك ضغط** `takepos_redesign_patch.zip` على سطح المكتب أو في أي مكان

2. **افتح المجلد** `takepos_patch`

3. **انقر بالزر الأيمن** على `install.bat` → **"Run as administrator"**
   (مهم: تشغيل كمسؤول حتى يستطيع الكتابة في `htdocs`)

4. **أدخل مسار takepos** عند طلبه. المسار النموذجي على XAMPP:
   ```
   C:\xampp\htdocs\dolibarr\htdocs\takepos
   ```
   
   إذا كان عندك مسار مختلف، أدخله. مثلاً:
   ```
   C:\xampp\htdocs\takepos
   D:\xampp\htdocs\dolibarr\htdocs\takepos
   ```

5. **انتظر** رسالة النجاح ✓

6. **امسح الكاش**:
   - في Dolibarr: `Setup → Other Setup → Purge Cache`
   - في المتصفح: اضغط `Ctrl+Shift+F5` (تحديث قسري)

7. **افتح TakePOS** ← يجب أن ترى التصميم الجديد!

---

## 🛠️ الطريقة الثانية: يدوية (إذا فشلت التلقائية)

### الخطوة 1: انسخ ملفين

افتح مجلد `takepos_patch` بعد فك الضغط، وانسخ:

| من | إلى |
|---|---|
| `takepos_patch\css\pos_redesign.css` | `C:\xampp\htdocs\dolibarr\htdocs\takepos\css\pos_redesign.css` |
| `takepos_patch\partials\shortcuts_drawer.php` | `C:\xampp\htdocs\dolibarr\htdocs\takepos\partials\shortcuts_drawer.php` |

⚠️ **انتبه**: الملف الثاني سيحلّ محل ملف موجود. خذ نسخة احتياطية أولاً!

```
احفظ نسخة باسم: shortcuts_drawer.php.backup
```

### الخطوة 2: عدّل ملف index.php

1. افتح `index.php` في **Notepad++** أو **VS Code** (لا تستخدم Notepad العادي - يفسد الترميز العربي)

2. اضغط `Ctrl+G` لتذهب لسطر معيّن، واذهب للسطر **735**

3. ستجد هذا السطر:
   ```php
   $arrayofcss = array('/takepos/css/pos.css.php?v=20260419layout2', '/takepos/css/colorbox.css');
   ```

4. **استبدله** بهذا (لاحظ إضافة العنصر الثالث):
   ```php
   $arrayofcss = array('/takepos/css/pos.css.php?v=20260419layout2', '/takepos/css/colorbox.css', '/takepos/css/pos_redesign.css?v=20260428pro1');
   ```

5. **احفظ الملف** بترميز UTF-8 (في Notepad++: `Encoding → UTF-8`)

### الخطوة 3: امسح الكاش

افتح المتصفح واضغط `Ctrl+Shift+F5` بعد فتح TakePOS.

---

## 🔍 التأكد من نجاح التثبيت

افتح TakePOS وتحقق من:

| العلامة | المعنى |
|---|---|
| ✅ شريط علوي **داكن** (slate-900) | CSS تم تحميله بنجاح |
| ✅ زر **عمودي ونحيف** على الحافة اليسرى مكتوب فيه "الاختصارات" عمودياً | CSS الجديد للـ launcher يعمل |
| ✅ خط **IBM Plex Sans Arabic** أوضح من قبل | الخطوط محمَّلة |
| ✅ بطاقات منتجات بـ **shadow ناعم** و **رفع عند الـ hover** | الكروت الجديدة تعمل |
| ✅ عند فتح Shortcuts: **drawer كامل من الجانب** مع overlay شفاف | الـ drawer الجديد يعمل |

---

## ❓ إذا لم يتغيّر شيء

### السبب الأرجح: الكاش
1. اذهب لـ Dolibarr: `Home → Setup → Other Setup → Purge Cache`
2. في المتصفح: اضغط `F12` لفتح DevTools → `Network` tab → اضغط `Disable cache`
3. أعد تحميل الصفحة بـ `Ctrl+Shift+F5`

### السبب الثاني: الملف لم يُحمَّل
افتح DevTools (`F12`) → `Network` tab → ابحث عن `pos_redesign.css`:
- ✅ **status 200** = الملف موجود ويُحمَّل
- ❌ **status 404** = المسار خطأ، تأكد من نسخ الملف
- ❌ غير موجود = `index.php` لم يُعدَّل بشكل صحيح

### السبب الثالث: index.php لم يُعدَّل
افتح `index.php` في محرر نصوص وابحث (`Ctrl+F`) عن `pos_redesign`:
- إذا وُجد → التعديل تم
- إذا لم يوجد → عدّل يدوياً (الخطوة 2 أعلاه)

---

## 🔄 إلغاء التثبيت

شغّل `uninstall.bat` كمسؤول، أو يدوياً:

1. احذف `C:\xampp\htdocs\dolibarr\htdocs\takepos\css\pos_redesign.css`
2. أعد التعديل العكسي على `index.php` سطر 735 (احذف الإضافة)
3. استرجع `shortcuts_drawer.php.backup` إذا كنت أخذته

---

## 🆘 لم يعمل؟ تواصل معي

أعطني:
1. لقطة شاشة من DevTools (`F12` → Network) بعد محاولة فتح TakePOS
2. صورة لمحتوى السطر 735 من `index.php` بعد التعديل
3. قائمة محتوى المجلد `takepos\css\` (للتأكد من وجود `pos_redesign.css`)
