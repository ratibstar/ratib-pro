#Requires -RunAsAdministrator
param(
    [string]$InstallRoot = 'C:\Program Files\RATEB Branch',
    [string]$PhpPath = 'php.exe'
)
$ErrorActionPreference = 'Stop'
$here = Split-Path -Parent $MyInvocation.MyCommand.Path
$winsw = Join-Path $here 'RATEBHybridSync.exe'
if (-not (Test-Path $winsw)) {
    # Prefer copy next to install
    $alt = Join-Path $InstallRoot 'bin\windows\RATEBHybridSync.exe'
    if (Test-Path $alt) { $winsw = $alt }
}
if (-not (Test-Path $winsw)) {
    throw "Missing WinSW binary RATEBHybridSync.exe (download WinSW and rename)."
}

$logDir = Join-Path $InstallRoot 'storage\branch\logs'
New-Item -ItemType Directory -Force -Path $logDir | Out-Null
$servicePhp = Join-Path $InstallRoot 'bin\hybrid-sync-service.php'
$genXml = Join-Path (Split-Path $winsw) 'RATEBHybridSync.xml'

@"
<service>
  <id>RATEBHybridSync</id>
  <name>RATEB Hybrid Sync</name>
  <description>Always-On Hybrid Sync (Branch SQLite to Cloud MySQL)</description>
  <executable>$PhpPath</executable>
  <arguments>-d extension=pdo_sqlite -d extension=sqlite3 `"$servicePhp`"</arguments>
  <workingdirectory>$InstallRoot</workingdirectory>
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
Write-Host 'Installed Windows Service: RATEB Hybrid Sync'
