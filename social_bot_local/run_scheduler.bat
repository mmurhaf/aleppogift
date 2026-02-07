@echo off
cd /d "%~dp0"
set PYTHON="C:\xampp\htdocs\aleppogift\.venv\Scripts\python.exe"
%PYTHON% scheduler_daemon.py > output\logs\scheduler.log 2>&1