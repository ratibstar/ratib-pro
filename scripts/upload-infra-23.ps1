# Upload the 23 infrastructure marketplace files via cPanel Fileman API.
# Requires: CPANEL_HOST, CPANEL_USER, CPANEL_API_TOKEN (same as GitHub Actions secrets).
# Optional: CPANEL_PORT (default 2083), CPANEL_REMOTE_BASE (default /home/admin/public_html)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

foreach ($name in @('CPANEL_HOST', 'CPANEL_USER', 'CPANEL_API_TOKEN')) {
    if (-not (Get-Item "Env:$name" -ErrorAction SilentlyContinue)) {
        Write-Error "$name is not set. Export it in this shell or set GitHub-style secrets locally, then re-run."
    }
}

if (-not $env:CPANEL_PORT) { $env:CPANEL_PORT = '2083' }
if (-not $env:CPANEL_REMOTE_BASE) { $env:CPANEL_REMOTE_BASE = '/home/admin/public_html' }
$env:CPANEL_DEPLOY_MODE = 'list'
$env:CPANEL_DEPLOY_FILELIST = 'scripts/infra-deploy-23-files.list'

$py = Get-Command python -ErrorAction SilentlyContinue
if (-not $py) { $py = Get-Command python3 -ErrorAction SilentlyContinue }
if (-not $py) { throw 'Python not found on PATH' }

& $py.Source scripts/github-cpanel-fileman-deploy-core.py
exit $LASTEXITCODE
