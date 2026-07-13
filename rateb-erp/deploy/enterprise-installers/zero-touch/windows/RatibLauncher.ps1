# Phase D.4 — RATIB ERP zero-touch launcher (Windows)
# Always opens LOCAL Branch Appliance (127.0.0.1) — never https://rateb.sa
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

function Resolve-Php([string]$Root, [string]$Preferred) {
  foreach ($c in @(
    $Preferred,
    (Join-Path $Root 'runtime\php\php.exe'),
    (Get-Command php.exe -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source)
  )) {
    if ($c -and (Test-Path $c)) { return (Resolve-Path $c).Path }
  }
  return $null
}

function Test-LocalUp([string]$u) {
  try { Invoke-WebRequest -Uri $u -UseBasicParsing -TimeoutSec 2 | Out-Null; return $true } catch { return $false }
}

$appEnv = Join-Path $InstallRoot 'storage\branch\appliance.env'
$url = 'http://127.0.0.1:8088/'
$phpPref = ''
if (Test-Path $appEnv) {
  Get-Content $appEnv | ForEach-Object {
    if ($_ -match '^RATEB_BRANCH_HTTP_URL=(.+)$') { $url = $Matches[1].Trim() }
    if ($_ -match '^RATEB_PHP_BIN=(.+)$') { $phpPref = $Matches[1].Trim() }
  }
}
# Never open cloud portal from this launcher
if ($url -match 'rateb\.sa' -or $url -match '^https://') {
  $url = 'http://127.0.0.1:8088/'
}

$php = Resolve-Php $InstallRoot $phpPref
if (-not $php) {
  Write-Host 'PHP not found. Install PHP 8.2+ or place runtime under runtime\php.'
  throw 'php.exe not found'
}

New-Item -ItemType Directory -Force -Path (Join-Path $InstallRoot 'storage\branch') | Out-Null
$statusPath = Join-Path $InstallRoot 'storage\branch\status.json'
@{
  phase='D.4'; state='starting'; label='STARTING'; display='STARTING'
  open_url=$url; updated_at=(Get-Date).ToUniversalTime().ToString('o')
} | ConvertTo-Json | Set-Content $statusPath -Encoding UTF8

# Prefer Windows services when present
foreach ($n in @('RATIBBranchWeb','RATIBHybridSync')) {
  $exe = Join-Path $InstallRoot "bin\windows\$n.exe"
  if (Test-Path $exe) { & $exe start 2>$null }
  else {
    $svc = Get-Service -Name $n -ErrorAction SilentlyContinue
    if ($svc -and $svc.Status -ne 'Running') { Start-Service $n -ErrorAction SilentlyContinue }
  }
}

# Fallback: start built-in PHP server if local URL is down (no WinSW required)
if (-not (Test-LocalUp $url)) {
  $serve = Join-Path $InstallRoot 'bin\hybrid-branch-serve.php'
  $port = 8088
  if ($url -match ':(\d+)') { $port = [int]$Matches[1] }
  Start-Process -FilePath $php -ArgumentList @(
    '-d','extension=pdo_sqlite','-d','extension=sqlite3','-d','extension=gd',
    "`"$serve`"", "--host=127.0.0.1", "--port=$port"
  ) -WorkingDirectory $InstallRoot -WindowStyle Hidden
}

$mon = Join-Path $InstallRoot 'bin\hybrid-zero-touch-status.php'
if (Test-Path $mon) {
  Start-Process -FilePath $php -ArgumentList @('-d','extension=pdo_sqlite','-d','extension=sqlite3',"`"$mon`"","--loop","--interval=3") -WorkingDirectory $InstallRoot -WindowStyle Hidden
}

if (-not $NoTray) {
  $tray = Join-Path $PSScriptRoot 'RatibTray.ps1'
  $running = Get-CimInstance Win32_Process -Filter "Name='powershell.exe'" -ErrorAction SilentlyContinue |
    Where-Object { $_.CommandLine -and $_.CommandLine -like '*RatibTray.ps1*' }
  if (-not $running -and (Test-Path $tray)) {
    Start-Process powershell.exe -ArgumentList @('-NoProfile','-WindowStyle','Hidden','-ExecutionPolicy','Bypass','-File',"`"$tray`"","-InstallRoot","`"$InstallRoot`"") -WindowStyle Hidden
  }
}

$deadline = (Get-Date).AddSeconds(20)
while ((Get-Date) -lt $deadline) {
  if (Test-LocalUp $url) { break }
  Start-Sleep -Milliseconds 400
}

if (-not $NoBrowser) {
  # Force local URL only
  Start-Process $url
}
Write-Host "RATIB ERP (local) → $url"
exit 0
