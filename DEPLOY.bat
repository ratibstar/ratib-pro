@echo off
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\deploy-update.ps1"
if errorlevel 1 (
  echo.
  echo FAILED — press any key.
  pause >nul
  exit /b 1
)
timeout /t 2 /nobreak >nul
