# Build the RATEB Customer APK for ONE company server.
# The ERP Admin page (Oversight -> Mobile Apps -> Customer app -> company) shows the exact command.
# Usage:
#   .\tool\build_company_apk.ps1 -ApiBaseUrl "https://admin.rateb.sa/api" -Slug "admin-rateb"
#   .\tool\build_company_apk.ps1 -ApiBaseUrl "https://rateb.sa/api" -Slug "platform" -Universal
# Output: dist\android\rateb-customer-<slug>.apk  -> upload it on the company's Mobile Apps page.

param(
    [Parameter(Mandatory = $true)][string]$ApiBaseUrl,
    [Parameter(Mandatory = $true)][string]$Slug,
    # ERP that publishes offers/content; default = same host + /rateb-erp/public
    [string]$ErpBaseUrl = "",
    [switch]$Universal
)

$ErrorActionPreference = "Stop"

$ApiBaseUrl = $ApiBaseUrl.Trim().TrimEnd("/")
if ($ApiBaseUrl -notmatch '^https://[^/\s]+(/[^\s]*)?$') {
    throw "ApiBaseUrl must be an https URL, e.g. https://rateb.sa/api"
}
$Slug = ($Slug.Trim().ToLowerInvariant() -replace '[^a-z0-9]+', '-').Trim('-')
if (-not $Slug) {
    throw "Slug is empty after sanitizing"
}

$flutter = $null
foreach ($candidate in @(
    "C:\flutter_sdk\bin\flutter.bat",
    "C:\flutter-sdk\bin\flutter.bat",
    (Join-Path $env:LOCALAPPDATA "flutter\bin\flutter.bat")
)) {
    if (Test-Path $candidate) {
        $flutter = $candidate
        break
    }
}
if (-not $flutter) {
    throw "Flutter SDK not found (checked C:\flutter_sdk, C:\flutter-sdk, %LOCALAPPDATA%\flutter)"
}

$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

if (-not (Test-Path (Join-Path $root "android\key.properties"))) {
    Write-Warning "android\key.properties is missing - this build will be DEBUG-signed (fine for testing, not for Play or long-term distribution)."
}

$dist = Join-Path $root "dist\android"
New-Item -ItemType Directory -Force -Path $dist | Out-Null

$buildArgs = @(
    "build", "apk", "--release",
    "--dart-define=RATEB_API_BASE_URL=$ApiBaseUrl"
)
# "platform" = shared build: shows offers/content published to all companies.
if ($Slug -ne "platform") {
    $buildArgs += "--dart-define=RATEB_COMPANY_SLUG=$Slug"
}
$ErpBaseUrl = $ErpBaseUrl.Trim().TrimEnd("/")
if ($ErpBaseUrl) {
    if ($ErpBaseUrl -notmatch '^https://[^/\s]+(/[^\s]*)?$') {
        throw "ErpBaseUrl must be an https URL, e.g. https://rateb.sa/rateb-erp/public"
    }
    $buildArgs += "--dart-define=RATEB_ERP_BASE_URL=$ErpBaseUrl"
}
if (-not $Universal) {
    $buildArgs += @("--target-platform", "android-arm64")
}

Write-Host "=== RATEB Customer for $Slug ===" -ForegroundColor Cyan
Write-Host "API: $ApiBaseUrl"
& $flutter @buildArgs
if ($LASTEXITCODE -ne 0) {
    throw "Build failed for $Slug"
}

$src = Join-Path $root "build\app\outputs\flutter-apk\app-release.apk"
if (-not (Test-Path $src)) {
    throw "Expected APK missing: $src"
}
$dest = Join-Path $dist ("rateb-customer-{0}.apk" -f $Slug)
Copy-Item -Force $src $dest
$sizeMb = [math]::Round((Get-Item $dest).Length / 1MB, 1)
$sha = (Get-FileHash -Algorithm SHA256 $dest).Hash.ToLowerInvariant()
Write-Host "OK -> $dest ($sizeMb MB)" -ForegroundColor Green
Write-Host "SHA-256: $sha"
Write-Host "Next: upload this file on the company's Mobile Apps page in ERP Admin."
