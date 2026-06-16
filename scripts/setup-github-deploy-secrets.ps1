# Interactive helper — copies each GitHub Environment secret value to clipboard.
# Does NOT upload secrets automatically. Never commits the private key.
$ErrorActionPreference = 'Stop'

$envUrl = 'https://github.com/ratibstar/ratib-pro/settings/environments'
$keyPath = Join-Path $env:USERPROFILE '.ssh\ratib_da_deploy'

$secrets = @(
    @{ Name = 'DEPLOY_BACKEND'; Value = 'rsync' }
    @{ Name = 'DEPLOY_SSH_HOST'; Value = '167.233.71.107' }
    @{ Name = 'DEPLOY_SSH_USER'; Value = 'admin' }
    @{ Name = 'DEPLOY_REMOTE_BASE'; Value = '/home/admin/domains/rateb.sa/public_html' }
    @{ Name = 'DEPLOY_SITE_URL'; Value = 'https://rateb.sa' }
)

if (-not (Test-Path $keyPath)) {
    Write-Host "Missing private key: $keyPath" -ForegroundColor Red
    Write-Host 'Run: ssh-keygen -t ed25519 -f "%USERPROFILE%\.ssh\ratib_da_deploy" -N "" -C "github-deploy@rateb.sa"'
    exit 1
}

$privateKey = Get-Content -Raw -Path $keyPath
$secrets += @{ Name = 'DEPLOY_SSH_PRIVATE_KEY'; Value = $privateKey.Trim() }

Write-Host ''
Write-Host 'GitHub Environment secrets helper (rateb.sa)' -ForegroundColor Cyan
Write-Host "1. Browser will open: $envUrl"
Write-Host '2. Click environment: rateb.sa'
Write-Host '3. For each secret below: Add environment secret -> paste from clipboard (Ctrl+V)'
Write-Host ''

Start-Process $envUrl
Start-Sleep -Seconds 2

$i = 0
foreach ($s in $secrets) {
    $i++
    Set-Clipboard -Value $s.Value
    $preview = $s.Value
    if ($s.Name -eq 'DEPLOY_SSH_PRIVATE_KEY') {
        $preview = '-----BEGIN OPENSSH PRIVATE KEY----- ... (full key copied)'
    }
    Write-Host "[$i/$($secrets.Count)] Name: $($s.Name)" -ForegroundColor Yellow
    Write-Host "      Value copied to clipboard: $preview"
    Read-Host '      Press Enter after you clicked Add secret in GitHub'
}

Write-Host ''
Write-Host 'Done. All 6 secrets should be in GitHub Environment rateb.sa.' -ForegroundColor Green
Write-Host 'Next: https://github.com/ratibstar/ratib-pro/actions -> Re-run all jobs'
