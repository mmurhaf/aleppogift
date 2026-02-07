@echo off
cd /d "%~dp0"
set PYTHON="C:\xampp\htdocs\aleppogift\.venv\Scripts\python.exe"
%PYTHON% -m streamlit run app.py --server.port 8501 > output\logs\ui.log 2>&1