# Phase D — build Windows customer deployment package (repository files only).
$ErrorActionPreference = 'Stop'
$Root = Resolve-Path (Join-Path $PSScriptRoot '..\..')
$Stamp = Get-Date -Format 'yyyyMMddHHmmss'
$Out = Join-Path $Root "storage\branch\package\windows"
$Dest = Join-Path $Out "rateb-branch-appliance-$Stamp"
New-Item -ItemType Directory -Force -Path $Dest | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $Dest 'bin\windows') | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $Dest 'docs\branch-appliance') | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $Dest 'config') | Out-Null

$bins = @(
  'hybrid-branch-appliance-install.php',
  'hybrid-branch-serve.php',
  'hybrid-branch-register.php',
  'hybrid-branch-diagnostics.php',
  'hybrid-branch-health.php',
  'hybrid-branch-backup.php',
  'hybrid-branch-update.php',
  'hybrid-branch-recover.php',
  'hybrid-branch-certify.php',
  'hybrid-sync-service.php'
)
foreach ($b in $bins) {
  Copy-Item (Join-Path $Root "bin\$b") (Join-Path $Dest "bin\$b")
}
Copy-Item (Join-Path $Root 'bin\windows\*') (Join-Path $Dest 'bin\windows\') -Recurse -Force
Copy-Item (Join-Path $Root 'config\hybrid.runtime.example.env') (Join-Path $Dest 'config\')
Copy-Item (Join-Path $Root 'VERSION') $Dest
Copy-Item (Join-Path $Root 'docs\branch-appliance\*') (Join-Path $Dest 'docs\branch-appliance\') -Recurse -Force
Copy-Item (Join-Path $Root 'deploy\branch-appliance\README.md') (Join-Path $Dest 'README.md')

@"
# Run from full rateb-erp tree after extract merge:
php -d extension=pdo_sqlite -d extension=sqlite3 bin\hybrid-branch-appliance-install.php @args
"@ | Set-Content (Join-Path $Dest 'INSTALL.ps1') -Encoding UTF8

$Zip = Join-Path $Out "rateb-branch-appliance-$Stamp.zip"
if (Test-Path $Zip) { Remove-Item $Zip -Force }
Compress-Archive -Path $Dest -DestinationPath $Zip -Force
Write-Host "OK $Zip"
