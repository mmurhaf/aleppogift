@echo off
REM AleppoGift Deployment Script - Batch Wrapper
REM This provides easy double-click deployment with menu options

setlocal enabledelayedexpansion

echo ======================================
echo   AleppoGift Deployment Tool
echo ======================================
echo.

:menu
echo Choose an option:
echo.
echo [1] Deploy to Production (with backup prompt)
echo [2] Deploy to Production (skip backup)
echo [3] Dry Run (preview files)
echo [4] Force Deploy All Files
echo [5] Backup Production Files
echo [6] Exit
echo.

set /p choice="Enter your choice (1-6): "

if "%choice%"=="1" (
    echo.
    echo Starting deployment with backup prompt...
    powershell -ExecutionPolicy Bypass -File "%~dp0deploy.ps1"
    goto end
)

if "%choice%"=="2" (
    echo.
    echo Starting deployment (skipping backup)...
    powershell -ExecutionPolicy Bypass -File "%~dp0deploy.ps1" -SkipBackup
    goto end
)

if "%choice%"=="3" (
    echo.
    echo Running dry run mode...
    powershell -ExecutionPolicy Bypass -File "%~dp0deploy.ps1" -DryRun
    goto end
)

if "%choice%"=="4" (
    echo.
    echo Force deploying all files...
    powershell -ExecutionPolicy Bypass -File "%~dp0deploy.ps1" -Force -SkipBackup
    goto end
)

if "%choice%"=="5" (
    echo.
    echo Starting backup...
    if exist "%~dp0backup.ps1" (
        powershell -ExecutionPolicy Bypass -File "%~dp0backup.ps1"
    ) else (
        echo ERROR: backup.ps1 not found
    )
    goto end
)

if "%choice%"=="6" (
    echo Exiting...
    goto exit
)

echo Invalid choice, please try again.
echo.
goto menu

:end
echo.
echo ======================================
pause

:exit
endlocal
