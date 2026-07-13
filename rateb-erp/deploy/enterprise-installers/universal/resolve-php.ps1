# Phase D.3 — resolve PHP (system → bundled → download official Windows build)
param(
  [string]$InstallRoot = 'C:\Program Files\RATIB Branch',
  [string]$PreferredPhp = ''
)
$ErrorActionPreference = 'Stop'
$Required = @('pdo_sqlite','sqlite3','openssl','gd','curl','zip','json')

function Test-PhpOk([string]$Bin) {
  if (-not (Test-Path $Bin)) { return $false }
  $ver = & $Bin -r "echo PHP_VERSION;" 2>$null
  if (-not $ver) { return $false }
  try {
    if ([version]($ver.Split('-')[0]) -lt [version]'8.2.0') { return $false }
  } catch { return $false }
  $mods = & $Bin -m 2>$null
  foreach ($e in $Required) {
    if ($mods -notcontains $e) { return $false }
  }
  return $true
}

$candidates = @()
if ($PreferredPhp) { $candidates += $PreferredPhp }
$candidates += (Join-Path $InstallRoot 'runtime\php\php.exe')
$cmd = Get-Command php.exe -ErrorAction SilentlyContinue
if ($cmd) { $candidates += $cmd.Source }

foreach ($c in $candidates) {
  if ($c -and (Test-PhpOk $c)) {
    Write-Output (Resolve-Path $c).Path
    return
  }
}

# Auto-download official PHP Windows x64 NTS into runtime\php (clean machine)
$runtime = Join-Path $InstallRoot 'runtime\php'
New-Item -ItemType Directory -Force -Path $runtime | Out-Null
$zip = Join-Path $env:TEMP 'ratib-php-nts.zip'
$urls = @(
  'https://windows.php.net/downloads/releases/latest/php-8.3-nts-Win32-vs16-x64-latest.zip',
  'https://windows.php.net/downloads/releases/latest/php-8.2-nts-Win32-vs16-x64-latest.zip'
)
$downloaded = $false
foreach ($u in $urls) {
  try {
    Write-Host "Downloading bundled PHP: $u"
    Invoke-WebRequest -Uri $u -OutFile $zip -UseBasicParsing
    Expand-Archive -Path $zip -DestinationPath $runtime -Force
    $downloaded = $true
    break
  } catch {
    Write-Warning $_.Exception.Message
  }
}
$phpIni = Join-Path $runtime 'php.ini-production'
$ini = Join-Path $runtime 'php.ini'
if (Test-Path $phpIni) {
  Copy-Item $phpIni $ini -Force
  $extDir = Join-Path $runtime 'ext'
  Add-Content $ini "`nextension_dir=`"$extDir`""
  foreach ($e in @('curl','gd','mbstring','openssl','pdo_sqlite','sqlite3','zip')) {
    Add-Content $ini "`nextension=$e"
  }
}
$bundled = Join-Path $runtime 'php.exe'
if (Test-PhpOk $bundled) {
  Write-Output (Resolve-Path $bundled).Path
  return
}
throw 'No usable PHP 8.2+. Place runtime under runtime\php or install PHP with pdo_sqlite/gd/curl/zip.'
