@echo off
cd /d C:\xampp\htdocs\medlink_ai\ai_api
if exist .venv_real\Scripts\activate.bat call .venv_real\Scripts\activate.bat
if exist .venv\Scripts\activate.bat call .venv\Scripts\activate.bat
python app.py
pause
