#Requires -RunAsAdministrator
<#
.SYNOPSIS
  Install RATEB Hybrid Sync as a Windows Service (WinSW) — not Task Scheduler.

.DESCRIPTION
  1) Place WinSW executable as: rateb-erp/bin/windows/RatebHybridSync.exe
     (rename winsw-*.exe from https://github.com/winsw/winsw/releases)
  2) Ensure rateb-hybrid-sync.xml sits beside it.
  3) Run this script.

.PARAMETER PhpPath
  Full path to php.exe (must have pdo_sqlite).
#>
param(
    [string]$PhpPath = (Get-Command php.exe -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source)
)

$ErrorActionPreference = 'Stop'
$here = Split-Path -Parent $MyInvocation.MyCommand.Path
$erpRoot = Resolve-Path (Join-Path $here '..\..')
$winsw = Join-Path $here 'RatebHybridSync.exe'
$xml = Join-Path $here 'rateb-hybrid-sync.xml'

if (-not (Test-Path $winsw)) {
    throw "Missing WinSW binary at $winsw — download WinSW and rename to RatebHybridSync.exe"
}
if (-not (Test-Path $xml)) {
    throw "Missing $xml"
}
if (-not $PhpPath -or -not (Test-Path $PhpPath)) {
    throw "php.exe not found. Pass -PhpPath 'C:\path\to\php.exe'"
}

# Rewrite executable absolute path into a generated XML next to WinSW
$genXml = Join-Path $here 'RatebHybridSync.xml'
$servicePhp = Join-Path $erpRoot 'bin\hybrid-sync-service.php'
$workDir = $erpRoot.Path
$logDir = Join-Path $erpRoot 'storage\branch\logs'
New-Item -ItemType Directory -Force -Path $logDir | Out-Null

@"
<service>
  <id>RatebHybridSync</id>
  <name>RATEB Hybrid Sync Service</name>
  <description>Always-On Hybrid Sync (Branch SQLite to Cloud MySQL)</description>
  <executable>$PhpPath</executable>
  <arguments>-d extension=pdo_sqlite -d extension=sqlite3 `"$servicePhp`"</arguments>
  <workingdirectory>$workDir</workingdirectory>
  <logmode>roll</logmode>
  <logpath>$logDir</logpath>
  <onfailure action="restart" delay="5 sec"/>
  <resetfailure>1 hour</resetfailure>
  <stoptimeout>30 sec</stoptimeout>
  <env name="RATEB_RUNTIME" value="branch" />
  <env name="RATEB_HYBRID_SYNC_ENABLED" value="1" />
  <env name="RATEB_HYBRID_SYNC_SINK" value="mysql" />
  <stopparentprocessfirst>true</stopparentprocessfirst>
</service>
"@ | Set-Content -Path $genXml -Encoding UTF8

& $winsw stop 2>$null
& $winsw uninstall 2>$null
& $winsw install
& $winsw start
Write-Host "Installed and started RatebHybridSync Windows Service."
Write-Host "Status:"; & $winsw status
