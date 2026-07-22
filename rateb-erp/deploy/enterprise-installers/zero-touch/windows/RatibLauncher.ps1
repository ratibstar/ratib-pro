# Phase D.4 — RATEB ERP zero-touch launcher (Windows)
# Browser always opens cloud admin: https://rateb.sa/rateb-erp/public/admin/
# (same URL online and offline via PWA). Local services still start for Hybrid Sync.
param(
  [string]$InstallRoot = '',
  [switch]$NoBrowser,
  [switch]$NoTray
)
$ErrorActionPreference = 'SilentlyContinue'
if (-not $InstallRoot) {
  $InstallRoot = Split-Path (Split-Path (Split-Path $PSScriptRoot))
  if (-not (Test-Path (Join-Path $InstallRoot 'bin\hybrid-branch-serve.php'))) {
    $InstallRoot = 'C:\Program Files\RATEB Branch'
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
$localUrl = 'http://127.0.0.1:8088/admin'
$cloudAdmin = 'https://rateb.sa/rateb-erp/public/admin/'
$phpPref = ''
if (Test-Path $appEnv) {
  Get-Content $appEnv | ForEach-Object {
    if ($_ -match '^RATEB_BRANCH_HTTP_URL=(.+)$') { $localUrl = $Matches[1].Trim() }
    if ($_ -match '^RATEB_CLOUD_ADMIN_URL=(.+)$') { $cloudAdmin = $Matches[1].Trim() }
    if ($_ -match '^RATEB_PHP_BIN=(.+)$') { $phpPref = $Matches[1].Trim() }
  }
}
# Local branch URL must stay on loopback (never rateb.sa)
if ($localUrl -match 'rateb\.sa' -or $localUrl -match '^https://') {
  $localUrl = 'http://127.0.0.1:8088/admin'
}
if ($localUrl -match '^https?://[^/]+/?$') {
  $localUrl = $localUrl.TrimEnd('/') + '/admin'
} elseif ($localUrl -notmatch '/admin') {
  $localUrl = $localUrl.TrimEnd('/') + '/admin'
}
if ($cloudAdmin -notmatch '/admin') {
  $cloudAdmin = $cloudAdmin.TrimEnd('/') + '/admin/'
}
$cloudAdmin = $cloudAdmin.TrimEnd('/') + '/'

$php = Resolve-Php $InstallRoot $phpPref
if (-not $php) {
  Write-Host 'PHP not found. Install PHP 8.2+ or place runtime under runtime\php.'
  throw 'php.exe not found'
}

New-Item -ItemType Directory -Force -Path (Join-Path $InstallRoot 'storage\branch') | Out-Null
$statusPath = Join-Path $InstallRoot 'storage\branch\status.json'
@{
  phase='D.4'; state='starting'; label='STARTING'; display='STARTING'
  open_url=$cloudAdmin; local_url=$localUrl; cloud_admin_url=$cloudAdmin
  updated_at=(Get-Date).ToUniversalTime().ToString('o')
} | ConvertTo-Json | Set-Content $statusPath -Encoding UTF8

# Prefer Windows services when present
foreach ($n in @('RATEBBranchWeb','RATEBHybridSync')) {
  $exe = Join-Path $InstallRoot "bin\windows\$n.exe"
  if (Test-Path $exe) { & $exe start 2>$null }
  else {
    $svc = Get-Service -Name $n -ErrorAction SilentlyContinue
    if ($svc -and $svc.Status -ne 'Running') { Start-Service $n -ErrorAction SilentlyContinue }
  }
}

# Fallback: start built-in PHP server if local URL is down (no WinSW required)
if (-not (Test-LocalUp $localUrl)) {
  $serve = Join-Path $InstallRoot 'bin\hybrid-branch-serve.php'
  $port = 8088
  if ($localUrl -match ':(\d+)') { $port = [int]$Matches[1] }
  Start-Process -FilePath $php -ArgumentList @(
    '-d','extension=pdo_sqlite','-d','extension=sqlite3','-d','extension=gd','-d','extension=mbstring',
    "`"$serve`"", "--host=127.0.0.1", "--port=$port"
  ) -WorkingDirectory $InstallRoot -WindowStyle Hidden
}

$mon = Join-Path $InstallRoot 'bin\hybrid-zero-touch-status.php'
if (Test-Path $mon) {
  # One-shot probe so open_url reflects online/offline before browser opens
  & $php -d extension=pdo_sqlite -d extension=sqlite3 $mon | Out-Null
  Start-Process -FilePath $php -ArgumentList @('-d','extension=pdo_sqlite','-d','extension=sqlite3',"`"$mon`"","--loop","--interval=3") -WorkingDirectory $InstallRoot -WindowStyle Hidden
}

if (-not $NoTray) {
  $tray = Join-Path $PSScriptRoot 'RatebTray.ps1'
  $running = Get-CimInstance Win32_Process -Filter "Name='powershell.exe'" -ErrorAction SilentlyContinue |
    Where-Object { $_.CommandLine -and $_.CommandLine -like '*RatebTray.ps1*' }
  if (-not $running -and (Test-Path $tray)) {
    Start-Process powershell.exe -ArgumentList @('-NoProfile','-WindowStyle','Hidden','-ExecutionPolicy','Bypass','-File',"`"$tray`"","-InstallRoot","`"$InstallRoot`"") -WindowStyle Hidden
  }
}

$deadline = (Get-Date).AddSeconds(20)
while ((Get-Date) -lt $deadline) {
  if (Test-LocalUp $localUrl) { break }
  Start-Sleep -Milliseconds 400
}

$openUrl = $cloudAdmin
if (Test-Path $statusPath) {
  try {
    $st = Get-Content $statusPath -Raw | ConvertFrom-Json
    if ($st.open_url -and ([string]$st.open_url -match 'rateb\.sa')) { $openUrl = [string]$st.open_url }
    elseif ($st.cloud_admin_url) { $openUrl = [string]$st.cloud_admin_url }
  } catch {}
}

if (-not $NoBrowser) {
  Start-Process $openUrl
}
Write-Host "RATEB ERP → $openUrl"
exit 0
