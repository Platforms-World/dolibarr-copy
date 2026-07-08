@echo off
chcp 65001 >nul
setlocal enabledelayedexpansion

REM ============================================================
REM TakePOS Professional Redesign - Windows Uninstaller
REM ============================================================

echo.
echo ============================================================
echo    TakePOS Redesign - Uninstaller (Windows)
echo ============================================================
echo.

set "TAKEPOS_DIR=%~1"

if "%TAKEPOS_DIR%"=="" (
    echo الرجاء إدخال المسار الكامل لمجلد takepos:
    set /p TAKEPOS_DIR="المسار: "
)

set "TAKEPOS_DIR=%TAKEPOS_DIR:"=%"

if not exist "%TAKEPOS_DIR%" (
    echo [خطأ] المجلد غير موجود: %TAKEPOS_DIR%
    pause
    exit /b 1
)

REM ===== البحث عن آخر نسخة احتياطية =====
set "LATEST_BACKUP="
for /f "delims=" %%d in ('dir /b /ad /o-n "%TAKEPOS_DIR%\.redesign_backup_*" 2^>nul') do (
    if not defined LATEST_BACKUP set "LATEST_BACKUP=%TAKEPOS_DIR%\%%d"
)

if not defined LATEST_BACKUP (
    echo [خطأ] لم يتم العثور على نسخة احتياطية في:
    echo        %TAKEPOS_DIR%
    pause
    exit /b 1
)

echo [+] استعادة من: %LATEST_BACKUP%
echo.

REM ===== استعادة الملفات =====
copy /Y "%LATEST_BACKUP%\index.php" "%TAKEPOS_DIR%\index.php" >nul
echo     [✓] index.php

copy /Y "%LATEST_BACKUP%\partials\shortcuts_drawer.php" "%TAKEPOS_DIR%\partials\shortcuts_drawer.php" >nul
echo     [✓] partials\shortcuts_drawer.php

REM ===== حذف ملف CSS الجديد =====
if exist "%TAKEPOS_DIR%\css\pos_redesign.css" (
    del /F /Q "%TAKEPOS_DIR%\css\pos_redesign.css"
    echo     [✓] حذف css\pos_redesign.css
)

echo.
echo ============================================================
echo    [✓] تم إلغاء التثبيت بنجاح!
echo ============================================================
echo.
echo    امسح كاش المتصفح (Ctrl+Shift+R) لرؤية الواجهة الأصلية.
echo.
pause
