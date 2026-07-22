#Requires -RunAsAdministrator
# Phase D.4 — install zero-touch desktop shortcut, startup tray, status service task
param(
  [string]$InstallRoot = 'C:\Program Files\RATEB Branch',
  [string]$PhpPath = ''
)
$ErrorActionPreference = 'Stop'
$zt = $PSScriptRoot
$launcher = Join-Path $zt 'RatebLauncher.ps1'
$tray = Join-Path $zt 'RatebTray.ps1'

# Desktop + Start Menu shortcut "RATEB ERP"
$ws = New-Object -ComObject WScript.Shell
$desktop = [Environment]::GetFolderPath('Desktop')
$programs = Join-Path ([Environment]::GetFolderPath('StartMenu')) 'Programs\RATEB'
New-Item -ItemType Directory -Force -Path $programs | Out-Null

# Desktop + Start Menu shortcut — distinct from Chrome PWA "RATEB ERP" (rateb.sa)
foreach ($linkPath in @(
  (Join-Path $desktop 'RATEB ERP - محلي.lnk'),
  (Join-Path $desktop 'RATEB ERP Local.lnk'),
  (Join-Path $programs 'RATEB ERP Local.lnk')
)) {
  $sc = $ws.CreateShortcut($linkPath)
  $sc.TargetPath = 'powershell.exe'
  $sc.Arguments = "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$launcher`" -InstallRoot `"$InstallRoot`""
  $sc.WorkingDirectory = $InstallRoot
  $sc.Description = 'RATEB ERP Branch (local offline) — not rateb.sa'
  $sc.Save()
}

# Startup: tray on login
$startup = [Environment]::GetFolderPath('Startup')
$sc2 = $ws.CreateShortcut((Join-Path $startup 'RATEB Tray.lnk'))
$sc2.TargetPath = 'powershell.exe'
$sc2.Arguments = "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$tray`" -InstallRoot `"$InstallRoot`""
$sc2.WorkingDirectory = $InstallRoot
$sc2.Save()

# Scheduled task: status monitor always-on
if (-not $PhpPath) {
  $appEnv = Join-Path $InstallRoot 'storage\branch\appliance.env'
  if (Test-Path $appEnv) {
    Get-Content $appEnv | ForEach-Object {
      if ($_ -match '^RATEB_PHP_BIN=(.+)$') { $PhpPath = $Matches[1].Trim() }
    }
  }
}
if (-not $PhpPath) { $PhpPath = (Get-Command php.exe -ErrorAction SilentlyContinue).Source }
if (-not $PhpPath) { $PhpPath = Join-Path $InstallRoot 'runtime\php\php.exe' }

$mon = Join-Path $InstallRoot 'bin\hybrid-zero-touch-status.php'
Unregister-ScheduledTask -TaskName 'RATEB Zero-Touch Status' -Confirm:$false -ErrorAction SilentlyContinue
$action = New-ScheduledTaskAction -Execute $PhpPath -Argument "-d extension=pdo_sqlite -d extension=sqlite3 `"$mon`" --loop --interval=3" -WorkingDirectory $InstallRoot
$trigger = New-ScheduledTaskTrigger -AtLogOn
Register-ScheduledTask -TaskName 'RATEB Zero-Touch Status' -Action $action -Trigger $trigger -User 'SYSTEM' -RunLevel Highest -Force | Out-Null

# Ensure cloud URL default in appliance.env
$appEnv = Join-Path $InstallRoot 'storage\branch\appliance.env'
if (Test-Path $appEnv) {
  $raw = Get-Content $appEnv -Raw
  if ($raw -notmatch 'RATEB_CLOUD_URL=') {
    Add-Content $appEnv "`nRATEB_CLOUD_URL=https://rateb.sa"
  }
}

Write-Host 'OK zero-touch shortcuts + tray + status monitor'
