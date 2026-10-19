@echo off
title Aleppo Gift Social Bot
cd /d "%~dp0"

if not exist ".venv\Scripts\python.exe" (
    echo Setting up the Social Bot Python environment...
    where py >nul 2>&1
    if errorlevel 1 (
        echo Python 3.10 or newer is required. Install it from https://www.python.org/downloads/ and run this again.
        pause
        exit /b 1
    )
    py -3 -m venv .venv
    if errorlevel 1 (
        echo Could not create the Python environment.
        pause
        exit /b 1
    )
    ".venv\Scripts\python.exe" -m pip install --upgrade pip
    ".venv\Scripts\python.exe" -m pip install -r "%~dp0requirements.txt"
    if errorlevel 1 (
        echo Dependency installation failed.
        pause
        exit /b 1
    )
)

".venv\Scripts\python.exe" "%~dp0launch_desktop.py"
if errorlevel 1 pause
