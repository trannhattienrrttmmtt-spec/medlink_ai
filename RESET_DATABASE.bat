@echo off
title MedLink AI - Reset Database
color 0C
echo ============================================
echo        MedLink AI - Reset Database
echo ============================================
echo.
echo This will DELETE the medlink_ai database and import database.sql again.
echo Existing users, history, and predictions will be removed.
echo.
set /p CONFIRM=Type RESET to continue: 
if /I not "%CONFIRM%"=="RESET" (
    echo Cancelled.
    pause
    exit /b
)

if not exist "C:\xampp\mysql\bin\mysql.exe" (
    echo [ERROR] MySQL not found at C:\xampp\mysql\bin\mysql.exe
    pause
    exit /b
)

echo Dropping old database...
"C:\xampp\mysql\bin\mysql.exe" -u root -e "DROP DATABASE IF EXISTS medlink_ai;"
if errorlevel 1 (
    echo [ERROR] Could not drop database.
    pause
    exit /b
)

echo Importing database.sql...
"C:\xampp\mysql\bin\mysql.exe" -u root < "%~dp0database.sql"
if errorlevel 1 (
    echo [ERROR] Import failed.
    pause
    exit /b
)

echo.
echo Database reset complete.
echo phpMyAdmin: http://localhost/phpmyadmin
pause
