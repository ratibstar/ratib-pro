# Fetch pending branded build specs from the platform API and run build-branded-app.ps1 for each.
# GitHub Actions: secrets MOBILE_BUILD_SECRET, vars MOBILE_BUILD_API_URL (default rateb.sa).
param(
    [string]$ApiUrl = $env:MOBILE_BUILD_API_URL,
    [string]$Secret = $env:MOBILE_BUILD_SECRET,
    [string]$App = 'all'
)

$ErrorActionPreference = 'Stop'
if (-not $ApiUrl) {
    $ApiUrl = 'https://rateb.sa/rateb-erp/public/api/v1/mobile/branded/specs'
}
if (-not $Secret) { throw 'MOBILE_BUILD_SECRET is required' }

$q = "app=$App&mode=actionable"
$specs = Invoke-RestMethod -Uri "$ApiUrl?$q" -Headers @{ 'X-Rateb-Build-Secret' = $Secret } -TimeoutSec 60
if ($specs.success -ne $true) { throw 'API returned failure' }
$list = @($specs.specs)
if (-not $list -or $list.Count -eq 0) {
    Write-Host 'No branded builds pending.'
    exit 0
}
$root = Split-Path -Parent $PSScriptRoot
$failed = @()
foreach ($s in $list) {
    try {
        $args = @(
            '-App', $s.app,
            '-Key', $s.key,
            '-Package', $s.package,
            '-Name', $s.name,
            '-NameAr', $(if ($s.name_ar) { $s.name_ar } else { $s.name }),
            '-Server', $s.server,
            '-Code', $(if ($s.code) { $s.code } else { '' })
        )
        if ($s.icon_url) { $args += '-IconUrl', $s.icon_url }
        & (Join-Path $root 'scripts\build-branded-app.ps1') @args
    } catch {
        Write-Host "$($s.key): $_" -ForegroundColor Red
        $failed += $s.key
    }
}
if ($failed) { throw "FAILED: $($failed -join ', ')" }
