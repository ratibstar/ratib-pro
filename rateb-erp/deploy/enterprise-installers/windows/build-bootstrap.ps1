# Portable Windows bootstrap when Inno Setup is unavailable.
# Produces RATEB-Branch-Setup.zip + RATEB-Branch-Setup.cmd (Admin).
# Prefer RATEB-Branch-Setup.exe via build.ps1 + Inno Setup 6 for enterprise releases.
$ErrorActionPreference = 'Stop'
$Root = Resolve-Path (Join-Path $PSScriptRoot '..\..\..')
$Out = Join-Path $Root 'storage\branch\enterprise-installers'
$Payload = Join-Path $Out 'payload\windows'
$Stage = Join-Path $Out 'windows-bootstrap'

# Ensure payload exists
& (Join-Path $PSScriptRoot 'build.ps1') | Out-Null

Remove-Item $Stage -Recurse -Force -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Force -Path $Stage | Out-Null
Copy-Item $Payload\* $Stage -Recurse -Force
Copy-Item (Join-Path $PSScriptRoot '*') (Join-Path $Stage 'deploy\enterprise-installers\windows') -Recurse -Force -ErrorAction SilentlyContinue
Copy-Item (Join-Path $PSScriptRoot '..\common') (Join-Path $Stage 'deploy\enterprise-installers\common') -Recurse -Force

$cmd = @'
@echo off
net session >nul 2>&1
if errorlevel 1 (
  echo Run as Administrator.
  exit /b 1
)
set "ROOT=%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$dest='C:\Program Files\RATEB Branch'; New-Item -ItemType Directory -Force -Path $dest | Out-Null; robocopy '%ROOT%' $dest /E /XD storage\branch\backups 2>nul; if (Test-Path (Join-Path $dest 'storage\branch\rateb-branch.sqlite')) { Write-Host 'Upgrade: preserving SQLite' } ; & (Join-Path $dest 'deploy\enterprise-installers\windows\install-branch.ps1') -InstallRoot $dest"
'@
Set-Content -Path (Join-Path $Stage 'RATEB-Branch-Setup.cmd') -Value $cmd -Encoding ASCII

$zip = Join-Path $Out 'RATEB-Branch-Setup.zip'
if (Test-Path $zip) { Remove-Item $zip -Force }
Compress-Archive -Path (Join-Path $Stage '*') -DestinationPath $zip -Force

# Marker for CI: exe requires Inno; zip is interim shippable package
$marker = Join-Path $Out 'RATEB-Branch-Setup.README.txt'
@"
Enterprise Windows installer:
  Preferred: RATEB-Branch-Setup.exe  (Inno Setup 6 — run windows\build.ps1)
  Interim:   RATEB-Branch-Setup.zip  (extract, run RATEB-Branch-Setup.cmd as Admin)
WinSW: place RATEBHybridSync.exe and RATEBBranchWeb.exe under bin\windows before packaging.
"@ | Set-Content $marker -Encoding UTF8

Write-Host "OK $zip"
