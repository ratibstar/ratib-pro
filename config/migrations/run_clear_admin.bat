@echo off
echo Clearing control admins, keeping admin admin123...
cd /d "%~dp0..\.."
php config\migrations\clear_control_admins_keep_admin.php
if errorlevel 1 (
    echo.
    echo If PHP not found: Add PHP to PATH, or run from XAMPP/WAMP shell.
    pause
) else (
    echo.
    pause
)
