@echo off
setlocal
cd /d "%~dp0"
if not exist "output\logs" mkdir "output\logs"
if not exist "output\run" mkdir "output\run"
set "PY=%~dp0.venv\Scripts\python.exe"
if not exist "%PY%" (
    echo Missing .venv. Double-click "Social Bot.bat" once to set up the app.
    exit /b 1
)
powershell -NoProfile -ExecutionPolicy Bypass -Command "$p = Start-Process -FilePath $env:PY -ArgumentList @('scheduler_daemon.py') -WorkingDirectory '%CD%' -WindowStyle Hidden -RedirectStandardOutput '%CD%\output\logs\scheduler.log' -RedirectStandardError '%CD%\output\logs\scheduler.err.log' -PassThru; Set-Content -Path '%CD%\output\run\scheduler.pid' -Value $p.Id"
