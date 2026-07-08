@echo off
chcp 65001 >nul
setlocal enabledelayedexpansion

REM ============================================================
REM TakePOS Professional Redesign - Windows Auto Installer
REM ============================================================
REM
REM الاستخدام:
REM   1) ضع هذا الملف داخل مجلد takepos_patch
REM   2) شغّله بالنقر المزدوج، أو من cmd:
REM      install.bat "C:\xampp\htdocs\dolibarr\htdocs\takepos"
REM
REM ============================================================

echo.
echo ============================================================
echo    TakePOS Professional Redesign - Installer (Windows)
echo ============================================================
echo.

REM ===== استلام مسار takepos =====
set "TAKEPOS_DIR=%~1"

if "%TAKEPOS_DIR%"=="" (
    echo لم يتم تحديد مسار. الرجاء إدخال المسار الكامل لمجلد takepos:
    echo مثال: C:\xampp\htdocs\dolibarr\htdocs\takepos
    echo.
    set /p TAKEPOS_DIR="المسار: "
)

REM إزالة علامات التنصيص إن وجدت
set "TAKEPOS_DIR=%TAKEPOS_DIR:"=%"

REM ===== التحقق من المسار =====
if not exist "%TAKEPOS_DIR%" (
    echo.
    echo [خطأ] المجلد غير موجود: %TAKEPOS_DIR%
    pause
    exit /b 1
)

if not exist "%TAKEPOS_DIR%\index.php" (
    echo.
    echo [خطأ] لم يتم العثور على index.php في:
    echo        %TAKEPOS_DIR%
    echo تأكد من أن المسار يشير لمجلد takepos الصحيح.
    pause
    exit /b 1
)

echo [✓] تم التحقق من المسار: %TAKEPOS_DIR%
echo.

REM ===== مجلد السكريبت الحالي =====
set "SCRIPT_DIR=%~dp0"
REM إزالة الـ backslash الأخير
if "%SCRIPT_DIR:~-1%"=="\" set "SCRIPT_DIR=%SCRIPT_DIR:~0,-1%"

REM ===== التحقق من وجود الملفات الجديدة =====
if not exist "%SCRIPT_DIR%\css\pos_redesign.css" (
    echo [خطأ] الملف css\pos_redesign.css غير موجود في حزمة التثبيت
    pause
    exit /b 1
)

if not exist "%SCRIPT_DIR%\partials\shortcuts_drawer.php" (
    echo [خطأ] الملف partials\shortcuts_drawer.php غير موجود في حزمة التثبيت
    pause
    exit /b 1
)

REM ===== إنشاء النسخة الاحتياطية =====
REM نستخدم تاريخ ووقت بصيغة آمنة
for /f "tokens=2 delims==" %%a in ('wmic OS Get localdatetime /value 2^>nul') do set "dt=%%a"
set "BACKUP_DATE=%dt:~0,4%%dt:~4,2%%dt:~6,2%-%dt:~8,2%%dt:~10,2%%dt:~12,2%"
set "BACKUP_DIR=%TAKEPOS_DIR%\.redesign_backup_%BACKUP_DATE%"

echo [+] إنشاء نسخة احتياطية في:
echo     %BACKUP_DIR%
mkdir "%BACKUP_DIR%" 2>nul
mkdir "%BACKUP_DIR%\partials" 2>nul

copy /Y "%TAKEPOS_DIR%\index.php" "%BACKUP_DIR%\index.php" >nul
if errorlevel 1 (
    echo [خطأ] فشل في نسخ index.php
    pause
    exit /b 1
)
echo     [✓] index.php

copy /Y "%TAKEPOS_DIR%\partials\shortcuts_drawer.php" "%BACKUP_DIR%\partials\shortcuts_drawer.php" >nul
if errorlevel 1 (
    echo [خطأ] فشل في نسخ shortcuts_drawer.php
    pause
    exit /b 1
)
echo     [✓] partials\shortcuts_drawer.php
echo.

REM ===== نسخ الملفات الجديدة =====
echo [+] نسخ الملفات الجديدة...
copy /Y "%SCRIPT_DIR%\css\pos_redesign.css" "%TAKEPOS_DIR%\css\pos_redesign.css" >nul
if errorlevel 1 (
    echo [خطأ] فشل في نسخ pos_redesign.css
    pause
    exit /b 1
)
echo     [✓] css\pos_redesign.css

copy /Y "%SCRIPT_DIR%\partials\shortcuts_drawer.php" "%TAKEPOS_DIR%\partials\shortcuts_drawer.php" >nul
if errorlevel 1 (
    echo [خطأ] فشل في نسخ shortcuts_drawer.php
    pause
    exit /b 1
)
echo     [✓] partials\shortcuts_drawer.php
echo.

REM ===== تعديل index.php لإضافة CSS =====
echo [+] تعديل index.php لإضافة ملف CSS الجديد...

REM التحقق إذا كان مطبّقاً مسبقاً
findstr /C:"pos_redesign.css" "%TAKEPOS_DIR%\index.php" >nul
if not errorlevel 1 (
    echo     [!] pos_redesign.css موجود مسبقاً - تخطّي التعديل
    goto :done_patch
)

REM إنشاء سكريبت PowerShell مؤقت
set "PSFILE=%TEMP%\takepos_patch_%RANDOM%.ps1"
(
    echo $ErrorActionPreference = 'Stop'
    echo $f = '%TAKEPOS_DIR%\index.php'
    echo $c = Get-Content -Raw -Encoding UTF8 $f
    echo $old = "`$arrayofcss = array('/takepos/css/pos.css.php?v=20260419layout2', '/takepos/css/colorbox.css');"
    echo $new = "`$arrayofcss = array('/takepos/css/pos.css.php?v=20260419layout2', '/takepos/css/colorbox.css', '/takepos/css/pos_redesign.css?v=20260428pro1');"
    echo if ^($c.Contains^($old^)^) {
    echo     $c = $c.Replace^($old, $new^)
    echo     [System.IO.File]::WriteAllText^($f, $c, ^(New-Object System.Text.UTF8Encoding $false^)^)
    echo     Write-Host '    [OK] تم التعديل بنجاح'
    echo     exit 0
    echo } else {
    echo     Write-Host '    [خطأ] لم يتم العثور على السطر الأصلي.'
    echo     Write-Host '    لعلّ التعديل تم سابقاً - تحقّق من index.php يدوياً'
    echo     exit 1
    echo }
) > "%PSFILE%"

powershell -NoProfile -ExecutionPolicy Bypass -File "%PSFILE%"
set "PS_RESULT=%errorlevel%"
del /F /Q "%PSFILE%" 2>nul

if not "%PS_RESULT%"=="0" (
    echo.
    echo [خطأ] فشل تعديل index.php تلقائياً.
    echo الحلّ اليدوي:
    echo   1) افتح: %TAKEPOS_DIR%\index.php
    echo   2) اذهب للسطر 735 ^(ابحث عن: $arrayofcss = array^)
    echo   3) أضف '/takepos/css/pos_redesign.css?v=20260428pro1' للمصفوفة
    echo.
    pause
    exit /b 1
)

:done_patch
echo.

REM ===== رسالة النجاح =====
echo ============================================================
echo.
echo    [✓] تم تثبيت التصميم الجديد بنجاح!
echo.
echo ============================================================
echo.
echo    النسخة الاحتياطية محفوظة في:
echo    %BACKUP_DIR%
echo.
echo    الخطوات التالية:
echo    1) امسح كاش Dolibarr:
echo       Setup → Other Setup → Purge Cache
echo    2) امسح كاش المتصفح: Ctrl+Shift+R
echo    3) افتح TakePOS وتمتّع بالواجهة الجديدة!
echo.
echo    لإلغاء التثبيت لاحقاً:
echo    uninstall.bat "%TAKEPOS_DIR%"
echo.
echo ============================================================
echo.
pause
