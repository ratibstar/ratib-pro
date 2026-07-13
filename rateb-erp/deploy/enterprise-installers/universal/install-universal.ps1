#Requires -RunAsAdministrator
<#
.SYNOPSIS
  Phase D.3 — Universal Windows Branch Appliance install (self-contained, port/firewall/services/rollback).
#>
param(
  [string]$InstallRoot = 'C:\Program Files\RATIB Branch',
  [string]$PhpPath = '',
  [switch]$Silent,
  [switch]$SkipDownloadPhp
)
$ErrorActionPreference = 'Stop'
$here = Split-Path -Parent $MyInvocation.MyCommand.Path
$common = Join-Path $here '..\common'
$win = Join-Path $here '..\windows'
$fresh = -not (Test-Path (Join-Path $InstallRoot 'storage\branch\rateb-branch.sqlite'))
$rolled = $false

function Invoke-Rollback([string]$Reason) {
  if ($script:rolled) { throw $Reason }
  $script:rolled = $true
  Write-Host "ROLLBACK — $Reason"
  foreach ($n in @('RATIBBranchWeb','RATIBHybridSync')) {
    $exe = Join-Path $InstallRoot "bin\windows\$n.exe"
    if (Test-Path $exe) { & $exe stop 2>$null; & $exe uninstall 2>$null }
  }
  if ($fresh -and (Test-Path $InstallRoot)) {
    Remove-Item $InstallRoot -Recurse -Force -ErrorAction SilentlyContinue
  }
  throw "Installation rolled back: $Reason"
}

& (Join-Path $here 'detect-platform.ps1') | Out-Null

if (-not $SkipDownloadPhp) {
  $PhpPath = & (Join-Path $here 'resolve-php.ps1') -InstallRoot $InstallRoot -PreferredPhp $PhpPath
} elseif (-not $PhpPath) {
  $PhpPath = & (Join-Path $here 'resolve-php.ps1') -InstallRoot $InstallRoot
}
Write-Host "php=$PhpPath"

$port = [int](& (Join-Path $here 'detect-port.ps1'))
Write-Host "port=$port"

foreach ($d in @('storage\branch','storage\branch\logs','storage\branch\backups','storage\branch\tmp','storage\sessions')) {
  New-Item -ItemType Directory -Force -Path (Join-Path $InstallRoot $d) | Out-Null
}

$db = Join-Path $InstallRoot 'storage\branch\rateb-branch.sqlite'
if (Test-Path $db) {
  $stamp = Get-Date -Format 'yyyyMMddHHmmss'
  $bak = Join-Path $InstallRoot "storage\branch\backups\pre-upgrade-$stamp.sqlite"
  Copy-Item $db $bak -Force
  Write-Host "Backed up SQLite → $bak"
}

Set-Location $InstallRoot
if (-not (Test-Path $db)) {
  & $PhpPath -d extension=pdo_sqlite -d extension=sqlite3 (Join-Path $InstallRoot 'bin\hybrid-branch-install.php')
  if ($LASTEXITCODE -ne 0) { Invoke-Rollback 'cold-start failed' }
} else {
  Write-Host 'Existing SQLite preserved'
}

& (Join-Path $win 'install-hybrid-sync-service.ps1') -InstallRoot $InstallRoot -PhpPath $PhpPath
& (Join-Path $win 'install-web-service.ps1') -InstallRoot $InstallRoot -PhpPath $PhpPath -Port $port
& (Join-Path $here 'configure-firewall.ps1') -Port $port
& (Join-Path $here 'schedule-backups.ps1') -InstallRoot $InstallRoot -PhpPath $PhpPath

$url = & (Join-Path $here 'write-appliance-config.ps1') -InstallRoot $InstallRoot -Port $port -PhpPath $PhpPath

& (Join-Path $common 'verify-install.ps1') -InstallRoot $InstallRoot -PhpPath $PhpPath
if ($LASTEXITCODE -ne 0) { Invoke-Rollback 'health verification failed' }

# Phase D.4 zero-touch
$ztInstall = Join-Path $InstallRoot 'deploy\enterprise-installers\zero-touch\windows\install-zero-touch.ps1'
if (-not (Test-Path $ztInstall)) {
  $ztInstall = Join-Path $here '..\zero-touch\windows\install-zero-touch.ps1'
}
if (Test-Path $ztInstall) {
  & $ztInstall -InstallRoot $InstallRoot -PhpPath $PhpPath
}

if (-not $Silent) {
  $launcher = Join-Path $InstallRoot 'deploy\enterprise-installers\zero-touch\windows\RatibLauncher.ps1'
  if (-not (Test-Path $launcher)) {
    $launcher = Join-Path $here '..\zero-touch\windows\RatibLauncher.ps1'
  }
  if (Test-Path $launcher) {
    & $launcher -InstallRoot $InstallRoot
  } else {
    Start-Process $url
  }
}
Write-Host "OK Universal+ZeroTouch install → $url"
Get-Content (Join-Path $InstallRoot 'storage\branch\appliance.env')
