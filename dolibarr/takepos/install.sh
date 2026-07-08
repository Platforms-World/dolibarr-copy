#!/bin/bash
#
# TakePOS Professional Redesign — Auto Installer
# ================================================
# تثبيت تلقائي للتصميم الجديد
#
# الاستخدام:
#   bash install.sh /path/to/dolibarr/htdocs/takepos
#
# مثال:
#   bash install.sh /var/www/dolibarr/htdocs/takepos
#

set -e  # توقف عند أي خطأ

# ===== التحقق من المسار =====
TAKEPOS_DIR="$1"

if [ -z "$TAKEPOS_DIR" ]; then
    echo "❌ خطأ: يجب تحديد مسار مجلد takepos"
    echo ""
    echo "الاستخدام:"
    echo "  bash install.sh /var/www/dolibarr/htdocs/takepos"
    exit 1
fi

if [ ! -d "$TAKEPOS_DIR" ]; then
    echo "❌ خطأ: المجلد $TAKEPOS_DIR غير موجود"
    exit 1
fi

if [ ! -f "$TAKEPOS_DIR/index.php" ]; then
    echo "❌ خطأ: لم يتم العثور على index.php في $TAKEPOS_DIR"
    echo "   تأكد أن المسار يشير لمجلد takepos الصحيح"
    exit 1
fi

echo "✅ تم التحقق من المسار: $TAKEPOS_DIR"
echo ""

# ===== مجلد السكريبت الحالي =====
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# ===== التحقق من وجود الملفات الجديدة =====
if [ ! -f "$SCRIPT_DIR/css/pos_redesign.css" ]; then
    echo "❌ خطأ: الملف css/pos_redesign.css غير موجود في حزمة التثبيت"
    exit 1
fi

if [ ! -f "$SCRIPT_DIR/partials/shortcuts_drawer.php" ]; then
    echo "❌ خطأ: الملف partials/shortcuts_drawer.php غير موجود في حزمة التثبيت"
    exit 1
fi

# ===== أخذ نسخة احتياطية =====
BACKUP_DATE=$(date +%Y%m%d-%H%M%S)
BACKUP_DIR="$TAKEPOS_DIR/.redesign_backup_$BACKUP_DATE"
mkdir -p "$BACKUP_DIR/partials"

echo "📦 إنشاء نسخة احتياطية في: $BACKUP_DIR"
cp "$TAKEPOS_DIR/index.php" "$BACKUP_DIR/index.php"
cp "$TAKEPOS_DIR/partials/shortcuts_drawer.php" "$BACKUP_DIR/partials/shortcuts_drawer.php"
echo "   ✓ index.php"
echo "   ✓ partials/shortcuts_drawer.php"
echo ""

# ===== نسخ الملفات الجديدة =====
echo "📥 نسخ الملفات الجديدة..."
cp "$SCRIPT_DIR/css/pos_redesign.css" "$TAKEPOS_DIR/css/pos_redesign.css"
echo "   ✓ css/pos_redesign.css"

cp "$SCRIPT_DIR/partials/shortcuts_drawer.php" "$TAKEPOS_DIR/partials/shortcuts_drawer.php"
echo "   ✓ partials/shortcuts_drawer.php"
echo ""

# ===== تعديل index.php لإضافة CSS =====
INDEX_FILE="$TAKEPOS_DIR/index.php"

# التحقق إذا كان السطر مطبّقاً مسبقاً
if grep -q "pos_redesign.css" "$INDEX_FILE"; then
    echo "⚠️  ملاحظة: pos_redesign.css موجود مسبقاً في index.php — تخطّي التعديل"
else
    echo "✏️  تعديل index.php لإضافة ملف CSS الجديد..."
    # نستخدم sed لإضافة الملف الجديد للمصفوفة
    # نبحث عن السطر الذي يحوي 'colorbox.css' ضمن $arrayofcss ونستبدله
    sed -i.before-redesign \
        "s|\$arrayofcss = array('/takepos/css/pos.css.php?v=20260419layout2', '/takepos/css/colorbox.css');|\$arrayofcss = array('/takepos/css/pos.css.php?v=20260419layout2', '/takepos/css/colorbox.css', '/takepos/css/pos_redesign.css?v=20260428pro1');|" \
        "$INDEX_FILE"

    # التحقق من النجاح
    if grep -q "pos_redesign.css" "$INDEX_FILE"; then
        echo "   ✓ تم إضافة pos_redesign.css بنجاح"
    else
        echo "   ❌ فشل التعديل. أعد التعديل يدوياً (راجع INSTALL.md)"
        exit 1
    fi
fi
echo ""

# ===== التحقق من صلاحيات الملفات =====
echo "🔧 ضبط صلاحيات الملفات..."
# نضبط صلاحيات تطابق الملفات الموجودة
chmod --reference="$TAKEPOS_DIR/index.php" "$TAKEPOS_DIR/css/pos_redesign.css" 2>/dev/null || chmod 644 "$TAKEPOS_DIR/css/pos_redesign.css"
chmod --reference="$TAKEPOS_DIR/index.php" "$TAKEPOS_DIR/partials/shortcuts_drawer.php" 2>/dev/null || chmod 644 "$TAKEPOS_DIR/partials/shortcuts_drawer.php"
echo "   ✓ تم"
echo ""

# ===== رسالة نجاح =====
cat << EOF
╔════════════════════════════════════════════════════════════╗
║                                                            ║
║   🎉  تم تثبيت التصميم الجديد بنجاح!                        ║
║                                                            ║
╠════════════════════════════════════════════════════════════╣
║                                                            ║
║   📂  النسخة الاحتياطية:                                    ║
║       $BACKUP_DIR
║                                                            ║
║   ⚠️   الخطوات التالية:                                      ║
║       1) امسح كاش Dolibarr                                 ║
║          (Setup → Other Setup → Purge Cache)              ║
║       2) امسح كاش المتصفح (Ctrl+Shift+R)                  ║
║       3) افتح TakePOS وتمتّع! 🎨                          ║
║                                                            ║
║   🔄  لإلغاء التثبيت:                                       ║
║       bash uninstall.sh "$TAKEPOS_DIR" "$BACKUP_DATE"
║                                                            ║
╚════════════════════════════════════════════════════════════╝
EOF
