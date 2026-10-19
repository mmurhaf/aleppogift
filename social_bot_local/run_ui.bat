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
powershell -NoProfile -ExecutionPolicy Bypass -Command "$p = Start-Process -FilePath $env:PY -ArgumentList @('-m','streamlit','run','app.py','--server.port','8501','--server.address','127.0.0.1','--server.headless','true','--browser.gatherUsageStats','false') -WorkingDirectory '%CD%' -WindowStyle Hidden -RedirectStandardOutput '%CD%\output\logs\ui.log' -RedirectStandardError '%CD%\output\logs\ui.err.log' -PassThru; Set-Content -Path '%CD%\output\run\ui.pid' -Value $p.Id"
