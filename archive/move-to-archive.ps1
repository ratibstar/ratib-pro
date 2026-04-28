# PowerShell Script to Move Non-Critical Files to Archive Folder
# Run this script to organize files for production deployment

Write-Host "Creating archive folder..." -ForegroundColor Green
if (-not (Test-Path "archive")) {
    New-Item -ItemType Directory -Path "archive" | Out-Null
}

Write-Host "Moving .md files to archive..." -ForegroundColor Yellow
Get-ChildItem -Path "." -Filter "*.md" -File | Move-Item -Destination "archive\" -Force

Write-Host "Moving documentation folders..." -ForegroundColor Yellow
$foldersToMove = @("docs", "tests", "setup", "errors", "exports", "php", "Forms", "dashboard", "cron", "database", "backups", "Utils")
foreach ($folder in $foldersToMove) {
    if (Test-Path $folder) {
        Write-Host "Moving $folder..." -ForegroundColor Cyan
        Move-Item -Path $folder -Destination "archive\$folder" -Force
    }
}

Write-Host "Moving duplicate path folders..." -ForegroundColor Yellow
if (Test-Path "path=api") {
    Move-Item -Path "path=api" -Destination "archive\path-api" -Force
}
if (Test-Path "path=pages") {
    Move-Item -Path "path=pages" -Destination "archive\path-pages" -Force
}

Write-Host "`nDone! All non-critical files moved to archive folder." -ForegroundColor Green
Write-Host "The archive folder can be excluded when uploading to production server." -ForegroundColor Green
