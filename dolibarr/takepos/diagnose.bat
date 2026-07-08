@echo off
chcp 65001 >nul
setlocal enabledelayedexpansion

echo.
echo ============================================================
echo    TakePOS Redesign - Diagnose
echo ============================================================
echo.

set "TAKEPOS_DIR=%~1"

if "%TAKEPOS_DIR%"=="" (
    set /p TAKEPOS_DIR="Enter the full path to takepos folder: "
)

set "TAKEPOS_DIR=%TAKEPOS_DIR:"=%"

if not exist "%TAKEPOS_DIR%\index.php" (
    echo [ERROR] Path not found: %TAKEPOS_DIR%
    pause
    exit /b 1
)

echo Checking path: %TAKEPOS_DIR%
echo.

echo [1] Checking pos_redesign.css...
if exist "%TAKEPOS_DIR%\css\pos_redesign.css" (
    echo     [OK] File exists
    for %%A in ("%TAKEPOS_DIR%\css\pos_redesign.css") do echo     Size: %%~zA bytes
) else (
    echo     [MISSING] File not found - copy css\pos_redesign.css to this folder
)
echo.

echo [2] Checking index.php...
findstr /C:"pos_redesign.css" "%TAKEPOS_DIR%\index.php" >nul
if not errorlevel 1 (
    echo     [OK] Line added in index.php
    echo     Found:
    for /f "tokens=*" %%i in ('findstr /C:"pos_redesign.css" "%TAKEPOS_DIR%\index.php"') do (
        echo       %%i
    )
) else (
    echo     [MISSING] Line not in index.php
)
echo.

echo [3] Checking shortcuts_drawer.php...
findstr /C:"takepos-shortcuts-overlay" "%TAKEPOS_DIR%\partials\shortcuts_drawer.php" >nul
if not errorlevel 1 (
    echo     [OK] Updated drawer is installed
) else (
    echo     [OLD] drawer is the original version - replace with new one
)
echo.

echo ============================================================
echo  Browser checks:
echo ============================================================
echo  1) Open TakePOS
echo  2) Press F12 - go to Network tab
echo  3) Refresh with Ctrl+Shift+R
echo  4) Look for: pos_redesign.css
echo     - status 200 = loaded OK
echo     - status 404 = file not in path
echo     - not shown   = index.php not patched
echo.
pause
