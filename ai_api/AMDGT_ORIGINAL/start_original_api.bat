@echo off
chcp 65001 >nul
title MedLink AI - AMDGT Original API

cd /d C:\xampp\htdocs\medlink_ai\ai_api\AMDGT_ORIGINAL

echo ==========================================
echo START AMDGT ORIGINAL API - PORT 5001
echo ==========================================

if exist ..\.venv_real\Scripts\activate (
    call ..\.venv_real\Scripts\activate
) else if exist ..\venv_original\Scripts\activate (
    call ..\venv_original\Scripts\activate
) else if exist venv_original\Scripts\activate (
    call venv_original\Scripts\activate
) else (
    echo Khong thay venv, dung Python he thong
)

where python
python --version

python app_original.py

pause