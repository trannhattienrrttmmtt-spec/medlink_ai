@echo off
title MedLink AI - Stopping...
color 0C
echo ============================================
echo        MedLink AI - Shutting Down
echo ============================================
echo.

echo [1/3] Stopping Flask API...
taskkill /F /IM python.exe /FI "WINDOWTITLE eq MedLink*" >nul 2>&1
taskkill /F /FI "WINDOWTITLE eq C:\xampp\htdocs\medlink_ai*" >nul 2>&1

echo [2/3] Stopping Apache...
taskkill /F /IM httpd.exe >nul 2>&1

echo [3/3] Stopping MySQL...
taskkill /F /IM mysqld.exe >nul 2>&1

echo.
echo ============================================
echo   All services stopped.
echo ============================================
timeout /t 3
