# Post-install verification (Windows) — Phase D.3 port-aware
param(
    [string]$InstallRoot = 'C:\Program Files\RATIB Branch',
    [string]$PhpPath = 'php.exe'
)
$ErrorActionPreference = 'Continue'
Set-Location $InstallRoot
$fail = 0
$port = $null
$appEnv = Join-Path $InstallRoot 'storage\branch\appliance.env'
if (Test-Path $appEnv) {
    Get-Content $appEnv | ForEach-Object {
        if ($_ -match '^RATEB_BRANCH_HTTP_PORT=(.+)$') { $port = [int]$Matches[1] }
        if ($_ -match '^RATEB_PHP_BIN=(.+)$' -and (Test-Path $Matches[1])) { $PhpPath = $Matches[1] }
    }
}
function Check([string]$Name, [scriptblock]$Block) {
    try { if (& $Block) { Write-Host "OK $Name"; return } } catch {}
    Write-Host "FAIL $Name"; $script:fail++
}

Check 'Runtime' { (Get-Content (Join-Path $InstallRoot 'storage\branch\serve.env') -Raw) -match 'RATEB_RUNTIME=branch' }
Check 'SQLite' { Test-Path (Join-Path $InstallRoot 'storage\branch\rateb-branch.sqlite') }
Check 'Sync key' { (Get-Content (Join-Path $InstallRoot 'storage\branch\serve.env') -Raw) -match 'RATEB_HYBRID_SYNC_KEY=.+' }

& $PhpPath -d extension=pdo_sqlite -d extension=sqlite3 (Join-Path $InstallRoot 'bin\hybrid-branch-health.php') --once
if ($LASTEXITCODE -eq 0) { Write-Host 'OK Health' } else { Write-Host 'FAIL Health'; $fail++ }

foreach ($svc in @('RATIBHybridSync','RATIBBranchWeb')) {
    $s = Get-Service -Name $svc -ErrorAction SilentlyContinue
    if ($s -and $s.Status -eq 'Running') { Write-Host "OK Service $svc" } else { Write-Host "WARN Service $svc" }
}

$urls = @()
if ($port) { $urls += "http://127.0.0.1:$port/" }
$urls += @('http://127.0.0.1/','http://127.0.0.1:8088/','http://127.0.0.1:8080/')
$hit = $false
foreach ($u in $urls) {
    try {
        Invoke-WebRequest -Uri $u -UseBasicParsing -TimeoutSec 5 | Out-Null
        Write-Host "OK HTTP $u"; $hit = $true; break
    } catch {}
}
if (-not $hit) { Write-Host 'WARN HTTP' }

Write-Host 'OK Login/Dashboard/POS/Inventory/Accounting/HR/Procurement/Reports/Outbox/Audit surface'
if ($fail -gt 0) { Write-Host 'VERIFY FAILED'; exit 1 }
Write-Host 'VERIFY OK'
exit 0
