# Phase D.4 — RATIB ERP zero-touch launcher (Windows)
# Ensures services, starts tray if needed, opens local ERP URL. No config UI.
param(
  [string]$InstallRoot = '',
  [switch]$NoBrowser,
  [switch]$NoTray
)
$ErrorActionPreference = 'SilentlyContinue'
if (-not $InstallRoot) {
  $InstallRoot = Split-Path (Split-Path (Split-Path $PSScriptRoot))
  if (-not (Test-Path (Join-Path $InstallRoot 'bin\hybrid-branch-serve.php'))) {
    $InstallRoot = 'C:\Program Files\RATIB Branch'
  }
}

$appEnv = Join-Path $InstallRoot 'storage\branch\appliance.env'
$url = 'http://127.0.0.1:8088/'
$php = 'php.exe'
if (Test-Path $appEnv) {
  Get-Content $appEnv | ForEach-Object {
    if ($_ -match '^RATEB_BRANCH_HTTP_URL=(.+)$') { $url = $Matches[1].Trim() }
    if ($_ -match '^RATEB_PHP_BIN=(.+)$' -and (Test-Path $Matches[1].Trim())) { $php = $Matches[1].Trim() }
  }
}
$bundled = Join-Path $InstallRoot 'runtime\php\php.exe'
if ((-not (Test-Path $php)) -and (Test-Path $bundled)) { $php = $bundled }

# Mark starting
$statusPath = Join-Path $InstallRoot 'storage\branch\status.json'
$starting = @{
  phase='D.4'; state='starting'; label='STARTING'; display='🔵 STARTING'
  open_url=$url; updated_at=(Get-Date).ToUniversalTime().ToString('o')
} | ConvertTo-Json
Set-Content $statusPath $starting -Encoding UTF8

# Ensure WinSW services
foreach ($n in @('RATIBBranchWeb','RATIBHybridSync')) {
  $exe = Join-Path $InstallRoot "bin\windows\$n.exe"
  if (Test-Path $exe) {
    & $exe start 2>$null
  } else {
    $svc = Get-Service -Name $n -ErrorAction SilentlyContinue
    if ($svc -and $svc.Status -ne 'Running') { Start-Service $n -ErrorAction SilentlyContinue }
  }
}

# Status monitor (background)
$mon = Join-Path $InstallRoot 'bin\hybrid-zero-touch-status.php'
Start-Process -FilePath $php -ArgumentList @('-d','extension=pdo_sqlite','-d','extension=sqlite3',"`"$mon`"","--loop","--interval=3") -WorkingDirectory $InstallRoot -WindowStyle Hidden

# Health once
$health = Join-Path $InstallRoot 'bin\hybrid-branch-health.php'
Start-Process -FilePath $php -ArgumentList @('-d','extension=pdo_sqlite','-d','extension=sqlite3',"`"$health`"","--once") -WorkingDirectory $InstallRoot -WindowStyle Hidden

# Tray
if (-not $NoTray) {
  $tray = Join-Path $PSScriptRoot 'RatibTray.ps1'
  $running = Get-CimInstance Win32_Process -Filter "Name='powershell.exe'" -ErrorAction SilentlyContinue |
    Where-Object { $_.CommandLine -and $_.CommandLine -like '*RatibTray.ps1*' }
  if (-not $running -and (Test-Path $tray)) {
    Start-Process powershell.exe -ArgumentList @('-NoProfile','-WindowStyle','Hidden','-ExecutionPolicy','Bypass','-File',"`"$tray`"","-InstallRoot","`"$InstallRoot`"") -WindowStyle Hidden
  }
}

# Wait briefly for web
$deadline = (Get-Date).AddSeconds(15)
$ok = $false
while ((Get-Date) -lt $deadline) {
  try {
    Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 2 | Out-Null
    $ok = $true
    break
  } catch { Start-Sleep -Milliseconds 500 }
}

if (-not $NoBrowser) {
  Start-Process $url
}
Write-Host "RATIB ERP → $url"
exit 0
