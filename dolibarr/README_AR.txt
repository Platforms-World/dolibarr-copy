هذا البكج يصلح سببين يمنعان ظهور زر Executive Dashboard في القائمة:

1) index.php كان يحتوي شرط عرض لزر الداشبورد التنفيذي باستخدام متغيرين غير معرفين:
   - $dashboardProFeatureEnabled
   - $canOpenDashboardPro
   لذلك الزر لا يظهر حتى مع وجود الصلاحيات.

2) تسجيل الـ feature في السجل كان باسم خاطئ في بعض الملفات:
   - كان: takepos.dashboard.view كـ feature
   - الصحيح: takepos.dashboard.pro
   مع بقاء الصلاحيات كالتالي:
   - takepos.dashboard.view
   - takepos.dashboard.export_pdf

الملفات داخل البكج:
- takepos/index.php
- takepos/class/TakeposSaasBridge.class.php
- takepos/sql/takepos_saas_seed.sql
- takepos/sql/takepos_dashboard_menu_and_registry_fix.sql

خطوات التطبيق:
1. استبدل الملفات الثلاثة المذكورة.
2. نفذ SQL مرة واحدة: takepos/sql/takepos_dashboard_menu_and_registry_fix.sql
3. افتح kafoerpcontrol وتأكد من تفعيل:
   - Feature: takepos.dashboard.pro
   - Permission: takepos.dashboard.view
   - Permission: takepos.dashboard.export_pdf
4. اعمل Ctrl+F5.

إذا كان المستخدم ليس Admin، تأكد أنه يملك permission: takepos.dashboard.view.
