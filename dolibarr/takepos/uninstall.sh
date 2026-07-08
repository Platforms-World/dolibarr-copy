#!/bin/bash
#
# TakePOS Professional Redesign — Uninstaller
# ==============================================
# إلغاء تثبيت التصميم الجديد واستعادة الأصلي
#
# الاستخدام:
#   bash uninstall.sh /path/to/dolibarr/htdocs/takepos [BACKUP_DATE]
#
# مثال:
#   bash uninstall.sh /var/www/dolibarr/htdocs/takepos 20260428-103000
#
# إذا لم تحدد BACKUP_DATE، سيستخدم آخر نسخة احتياطية متوفرة.
#

set -e

TAKEPOS_DIR="$1"
BACKUP_DATE="$2"

if [ -z "$TAKEPOS_DIR" ]; then
    echo "❌ خطأ: يجب تحديد مسار مجلد takepos"
    echo ""
    echo "الاستخدام:"
    echo "  bash uninstall.sh /var/www/dolibarr/htdocs/takepos [BACKUP_DATE]"
    exit 1
fi

if [ ! -d "$TAKEPOS_DIR" ]; then
    echo "❌ خطأ: المجلد $TAKEPOS_DIR غير موجود"
    exit 1
fi

# إذا لم يحدد المستخدم BACKUP_DATE، نستخدم آخر نسخة
if [ -z "$BACKUP_DATE" ]; then
    LATEST_BACKUP=$(ls -td "$TAKEPOS_DIR"/.redesign_backup_* 2>/dev/null | head -1)
    if [ -z "$LATEST_BACKUP" ]; then
        echo "❌ لم يتم العثور على نسخة احتياطية"
        echo "   تأكد أنك ثبّتت التصميم باستخدام install.sh"
        exit 1
    fi
    BACKUP_DIR="$LATEST_BACKUP"
    echo "📦 استخدام آخر نسخة احتياطية: $(basename $BACKUP_DIR)"
else
    BACKUP_DIR="$TAKEPOS_DIR/.redesign_backup_$BACKUP_DATE"
fi

if [ ! -d "$BACKUP_DIR" ]; then
    echo "❌ النسخة الاحتياطية غير موجودة: $BACKUP_DIR"
    exit 1
fi

# ===== استعادة الملفات =====
echo "🔄 استعادة الملفات الأصلية..."
cp "$BACKUP_DIR/index.php" "$TAKEPOS_DIR/index.php"
echo "   ✓ index.php"

cp "$BACKUP_DIR/partials/shortcuts_drawer.php" "$TAKEPOS_DIR/partials/shortcuts_drawer.php"
echo "   ✓ partials/shortcuts_drawer.php"

# ===== حذف ملف CSS الجديد =====
if [ -f "$TAKEPOS_DIR/css/pos_redesign.css" ]; then
    rm "$TAKEPOS_DIR/css/pos_redesign.css"
    echo "   ✓ حذف css/pos_redesign.css"
fi

# ===== حذف ملف sed.before-redesign إن وُجد =====
if [ -f "$TAKEPOS_DIR/index.php.before-redesign" ]; then
    rm "$TAKEPOS_DIR/index.php.before-redesign"
fi

echo ""
echo "✅ تم إلغاء التثبيت بنجاح. الواجهة عادت لشكلها الأصلي."
echo "   امسح كاش المتصفح (Ctrl+Shift+R) لرؤية النتيجة."
