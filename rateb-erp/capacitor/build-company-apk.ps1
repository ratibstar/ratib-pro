# Build the signed RATEB ERP APK for ONE company admin URL (Capacitor server.url).
# The ERP Admin page (Oversight -> Mobile Apps -> ERP app -> company) shows the exact command.
# Usage (inside rateb-erp\capacitor):
#   .\build-company-apk.ps1 -AdminUrl "https://admin.rateb.sa/rateb-erp/public/admin" -Slug "admin-rateb"
# Output: dist\rateb-erp-<slug>.apk  -> upload it on the company's Mobile Apps page.
# capacitor.config.json is restored byte-for-byte afterwards.

param(
    [Parameter(Mandatory = $true)][string]$AdminUrl,
    [Parameter(Mandatory = $true)][string]$Slug
)

$ErrorActionPreference = "Stop"

$AdminUrl = $AdminUrl.Trim().TrimEnd("/")
if ($AdminUrl -notmatch '^https://[^/\s]+(/[^\s]*)?$') {
    throw "AdminUrl must be an https URL, e.g. https://rateb.sa/rateb-erp/public/admin"
}
$Slug = ($Slug.Trim().ToLowerInvariant() -replace '[^a-z0-9]+', '-').Trim('-')
if (-not $Slug) {
    throw "Slug is empty after sanitizing"
}

$root = $PSScriptRoot
Set-Location $root
if (-not (Test-Path (Join-Path $root "android\key.properties"))) {
    throw "android\key.properties is missing - release signing is required"
}

$configPath = Join-Path $root "capacitor.config.json"
$originalBytes = [System.IO.File]::ReadAllBytes($configPath)

try {
    $config = [System.Text.Encoding]::UTF8.GetString($originalBytes) | ConvertFrom-Json
    $config.server.url = $AdminUrl
    [System.IO.File]::WriteAllText($configPath, ($config | ConvertTo-Json -Depth 20), (New-Object System.Text.UTF8Encoding($false)))

    Write-Host "=== RATEB ERP for $Slug ===" -ForegroundColor Cyan
    Write-Host "Admin URL: $AdminUrl"
    npm run cap:sync
    if ($LASTEXITCODE -ne 0) { throw "cap sync failed" }

    Push-Location (Join-Path $root "android")
    try {
        & .\gradlew.bat assembleRelease
        if ($LASTEXITCODE -ne 0) { throw "Gradle assembleRelease failed" }
    } finally {
        Pop-Location
    }

    $src = Join-Path $root "android\app\build\outputs\apk\release\app-release.apk"
    if (-not (Test-Path $src)) {
        throw "Expected APK missing: $src"
    }
    $dist = Join-Path $root "dist"
    New-Item -ItemType Directory -Force -Path $dist | Out-Null
    $dest = Join-Path $dist ("rateb-erp-{0}.apk" -f $Slug)
    Copy-Item -Force $src $dest
    $sizeMb = [math]::Round((Get-Item $dest).Length / 1MB, 1)
    $sha = (Get-FileHash -Algorithm SHA256 $dest).Hash.ToLowerInvariant()
    Write-Host "OK -> $dest ($sizeMb MB)" -ForegroundColor Green
    Write-Host "SHA-256: $sha"
    Write-Host "Next: upload this file on the company's Mobile Apps page in ERP Admin."
} finally {
    [System.IO.File]::WriteAllBytes($configPath, $originalBytes)
    npx cap copy android | Out-Null
}
