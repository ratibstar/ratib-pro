# Terminal upload: 23 infrastructure files to rateb.sa.
# Preferred: cPanel Fileman API (set CPANEL_HOST, CPANEL_USER, CPANEL_API_TOKEN).
# Fallback: git push main → GitHub Actions fast deploy (FAST_FILES infra bundle).

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

$hasCpanel = @('CPANEL_HOST', 'CPANEL_USER', 'CPANEL_API_TOKEN') | ForEach-Object {
    Test-Path "Env:$_"
} | Where-Object { $_ -eq $true }

if ($hasCpanel.Count -eq 3) {
    Write-Host 'Uploading 23 files via cPanel Fileman API...' -ForegroundColor Cyan
    $env:CPANEL_DEPLOY_MODE = 'list'
    $env:CPANEL_DEPLOY_FILELIST = 'scripts/infra-deploy-23-files.list'
    if (-not $env:CPANEL_PORT) { $env:CPANEL_PORT = '2083' }
    if (-not $env:CPANEL_REMOTE_BASE) { $env:CPANEL_REMOTE_BASE = '/home/outratib/public_html' }
    $py = Get-Command python -ErrorAction SilentlyContinue
    if (-not $py) { $py = Get-Command python3 -ErrorAction SilentlyContinue }
    if (-not $py) { throw 'Python not found on PATH' }
    & $py.Source scripts/github-cpanel-fileman-deploy-core.py
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
} else {
    Write-Host 'CPANEL_* not set — pushing to main to trigger GitHub Actions deploy...' -ForegroundColor Yellow
    $git = 'C:\Program Files\Git\cmd\git.exe'
    if (-not (Test-Path $git)) { $git = 'git' }
    & $git add public/ratib-build.txt scripts/github-cpanel-fileman-deploy-core.py
    & $git commit -m 'Deploy infra 23-file bundle via FAST_FILES baseline' 2>$null
    if ($LASTEXITCODE -eq 0) { & $git push origin main }
    else { Write-Host 'Nothing to commit; ensure latest main is pushed for Actions deploy.' }
    Write-Host 'Waiting 90s for GitHub Actions...'
    Start-Sleep -Seconds 90
}

Write-Host "`nLive build marker:"
curl.exe -sS 'https://rateb.sa/public/ratib-build.txt'
Write-Host ''
Write-Host 'Production verify:'
curl.exe -sS 'https://rateb.sa/modules/infrastructure-marketplace/Cli/production-verify.php'
Write-Host ''
