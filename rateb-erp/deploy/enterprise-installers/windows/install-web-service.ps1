#Requires -RunAsAdministrator
param(
    [string]$InstallRoot = 'C:\Program Files\RATIB Branch',
    [string]$PhpPath = 'php.exe',
    [int]$Port = 80
)
$ErrorActionPreference = 'Stop'
$here = Split-Path -Parent $MyInvocation.MyCommand.Path
$winsw = Join-Path $here 'RATIBBranchWeb.exe'
$alt = Join-Path $InstallRoot 'bin\windows\RATIBBranchWeb.exe'
if (-not (Test-Path $winsw) -and (Test-Path $alt)) { $winsw = $alt }
if (-not (Test-Path $winsw)) {
    throw "Missing WinSW binary RATIBBranchWeb.exe (download WinSW and rename)."
}

$logDir = Join-Path $InstallRoot 'storage\branch\logs'
New-Item -ItemType Directory -Force -Path $logDir | Out-Null
$servePhp = Join-Path $InstallRoot 'bin\hybrid-branch-serve.php'
$genXml = Join-Path (Split-Path $winsw) 'RATIBBranchWeb.xml'

@"
<service>
  <id>RATIBBranchWeb</id>
  <name>RATIB Branch Web</name>
  <description>RATIB ERP Branch Appliance local PHP web server</description>
  <executable>$PhpPath</executable>
  <arguments>-d extension=pdo_sqlite -d extension=sqlite3 -d extension=gd -d extension=mbstring `"$servePhp`" --host=127.0.0.1 --port=$Port</arguments>
  <workingdirectory>$InstallRoot</workingdirectory>
  <logmode>roll</logmode>
  <logpath>$logDir</logpath>
  <onfailure action="restart" delay="5 sec"/>
  <resetfailure>1 hour</resetfailure>
  <stoptimeout>15 sec</stoptimeout>
  <env name="RATEB_RUNTIME" value="branch" />
  <stopparentprocessfirst>true</stopparentprocessfirst>
</service>
"@ | Set-Content -Path $genXml -Encoding UTF8

& $winsw stop 2>$null
& $winsw uninstall 2>$null
& $winsw install
& $winsw start

# Firewall (localhost only is default; allow inbound loopback HTTP if needed)
try {
    New-NetFirewallRule -DisplayName 'RATIB Branch Web' -Direction Inbound -Protocol TCP -LocalPort $Port -Action Allow -Profile Any -ErrorAction SilentlyContinue | Out-Null
} catch {}

Write-Host "Installed Windows Service: RATIB Branch Web (port $Port)"
