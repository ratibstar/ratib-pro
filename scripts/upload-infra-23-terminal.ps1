# Terminal upload: 23 infrastructure files to out.ratib.sa (GitHub main → live docroot).
# Step 1: refresh ratib-profile-check.php on the server (includes infra23 handler).
# Step 2: pull all 23 infra files from GitHub main onto the server.

$ErrorActionPreference = 'Stop'
$base = 'https://out.ratib.sa/ratib-profile-check.php'
$key = 'ratib-deploy-sync-2026'

function Invoke-RatibDeploy([string]$Query) {
    $url = "${base}?${Query}&key=${key}"
    Write-Host "`n>>> GET $url" -ForegroundColor Cyan
    $out = curl.exe -sS $url
    Write-Host $out
    if ($out -match 'Summary: ok=(\d+) fail=(\d+)') {
        $f = [int]$Matches[2]
        if ($f -gt 0) { throw "Deploy reported $f failure(s)." }
    } elseif ($out -match 'Forbidden') {
        throw 'Deploy forbidden (wrong key or blocked).'
    }
}

Write-Host 'Step 1/2: update deploy helper on server...'
Invoke-RatibDeploy -Query 'deploy=1'

Write-Host 'Step 2/2: upload 23 infrastructure files...'
Invoke-RatibDeploy -Query 'infra23=1'

Write-Host "`nBuild marker on live site:"
curl.exe -sS 'https://out.ratib.sa/public/ratib-build.txt'
Write-Host ''
