@echo off
echo Creating archive folder...
if not exist archive mkdir archive

echo Moving .md files to archive...
move /Y *.md archive\ 2>nul

echo Moving documentation folders...
if exist docs move /Y docs archive\docs
if exist tests move /Y tests archive\tests
if exist setup move /Y setup archive\setup
if exist errors move /Y errors archive\errors
if exist exports move /Y exports archive\exports
if exist php move /Y php archive\php
if exist Forms move /Y Forms archive\Forms
if exist dashboard move /Y dashboard archive\dashboard
if exist cron move /Y cron archive\cron
if exist database move /Y database archive\database
if exist backups move /Y backups archive\backups
if exist Utils move /Y Utils archive\Utils

echo Moving duplicate path folders...
if exist "path=api" move /Y "path=api" archive\path-api
if exist "path=pages" move /Y "path=pages" archive\path-pages

echo Moving deployment files...
move /Y PRODUCTION_DEPLOYMENT_GUIDE.md archive\ 2>nul
move /Y move-to-archive.ps1 archive\ 2>nul
move /Y move-to-archive.bat archive\ 2>nul

echo.
echo Done! All non-critical files moved to archive folder.
echo The archive folder can be excluded when uploading to production server.
pause
