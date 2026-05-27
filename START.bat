@echo off
title MedLink AI - Starting...
color 0A
echo ============================================
echo        MedLink AI - Auto Setup
echo ============================================
echo.

:: Check XAMPP
if not exist "C:\xampp\apache\bin\httpd.exe" (
    echo [ERROR] XAMPP not found at C:\xampp
    echo Please install XAMPP first: https://www.apachefriends.org
    pause
    exit /b
)

:: Start Apache if not running
tasklist /FI "IMAGENAME eq httpd.exe" | find "httpd.exe" >nul
if errorlevel 1 (
    echo [1/4] Starting Apache...
    start "" "C:\xampp\apache\bin\httpd.exe"
    timeout /t 2 >nul
) else (
    echo [1/4] Apache already running.
)

:: Start MySQL if not running
tasklist /FI "IMAGENAME eq mysqld.exe" | find "mysqld.exe" >nul
if errorlevel 1 (
    echo [2/4] Starting MySQL...
    start "" "C:\xampp\mysql\bin\mysqld.exe"
    timeout /t 2 >nul
) else (
    echo [2/4] MySQL already running.
)

:: Import database if needed
echo [3/4] Checking database...
"C:\xampp\mysql\bin\mysql.exe" -u root -e "USE medlink_ai" 2>nul
if errorlevel 1 (
    echo       Database not found. Importing database.sql...
    "C:\xampp\mysql\bin\mysql.exe" -u root < "%~dp0database.sql"
    if errorlevel 1 (
        echo       [ERROR] Database import failed.
        pause
        exit /b
    )
    echo       Database imported.
) else (
    echo       Database OK. Skipping import to keep existing data.
)

:: Start Flask API
echo [4/4] Starting AI API server...
echo.
echo ============================================
echo   Web: http://localhost/medlink_ai/public/index.php
echo   phpMyAdmin: http://localhost/phpmyadmin
echo   API: http://127.0.0.1:5000
echo   Reset DB: run RESET_DATABASE.bat
echo ============================================
echo.
echo Loading AI models... (wait 1-2 minutes)
echo.

cd /d "%~dp0ai_api"
if exist .venv_real\Scripts\activate.bat call .venv_real\Scripts\activate.bat
if exist .venv\Scripts\activate.bat call .venv\Scripts\activate.bat

:: Open browser after 5 seconds
start "" cmd /c "timeout /t 8 >nul & start http://localhost/medlink_ai/public/index.php?action=login"

python app.py
pause
