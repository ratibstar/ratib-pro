# Build installable Android APKs into dist/android/ with clear names.
# Usage:
#   .\tool\build_android_release_pack.ps1
#   .\tool\build_android_release_pack.ps1 -AgencyOnly
#   .\tool\build_android_release_pack.ps1 -PlatformOnly

param(
    [switch]$AgencyOnly,
    [switch]$PlatformOnly,
    [string]$AgencyErpUrl = "https://admin.rateb.sa/rateb-erp/public",
    [string]$PlatformErpUrl = "https://rateb.sa/rateb-erp/public"
)

$ErrorActionPreference = "Stop"

$flutter = $null
foreach ($candidate in @(
    "C:\flutter-sdk\bin\flutter.bat",
    (Join-Path $env:LOCALAPPDATA "flutter\bin\flutter.bat")
)) {
    if (Test-Path $candidate) {
        $flutter = $candidate
        break
    }
}
if (-not $flutter) {
    throw "Flutter SDK not found"
}

$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

$dist = Join-Path $root "dist\android"
New-Item -ItemType Directory -Force -Path $dist | Out-Null

function Build-Apk {
    param(
        [string]$Label,
        [string]$ErpBaseUrl,
        [string]$OutName,
        [switch]$Arm64Only
    )

    $args = @(
        "build", "apk",
        "--flavor", "production",
        "--dart-define=APP_FLAVOR=production",
        "--dart-define=ERP_BASE_URL=$ErpBaseUrl"
    )
    if ($Arm64Only) {
        $args += @("--target-platform", "android-arm64")
    }

    Write-Host ""
    Write-Host "=== $Label ===" -ForegroundColor Cyan
    Write-Host "ERP: $ErpBaseUrl"
    & $flutter @args
    if ($LASTEXITCODE -ne 0) {
        throw "Build failed: $Label"
    }

    $src = Join-Path $root "build\app\outputs\flutter-apk\app-production-release.apk"
    if (-not (Test-Path $src)) {
        throw "Expected APK missing: $src"
    }
    $dest = Join-Path $dist $OutName
    Copy-Item -Force $src $dest
    $sizeMb = [math]::Round((Get-Item $dest).Length / 1MB, 1)
    Write-Host "OK -> $dest ($sizeMb MB)" -ForegroundColor Green
}

$buildAgency = -not $PlatformOnly
$buildPlatform = -not $AgencyOnly

if ($buildAgency) {
    Build-Apk `
        -Label "Agency ESS (admin.rateb.sa) — arm64" `
        -ErpBaseUrl $AgencyErpUrl `
        -OutName "rateb-hr-agency-admin.rateb.sa-arm64.apk" `
        -Arm64Only
}

if ($buildPlatform) {
    Build-Apk `
        -Label "Platform ESS (rateb.sa) — universal" `
        -ErpBaseUrl $PlatformErpUrl `
        -OutName "rateb-hr-platform-rateb.sa-universal.apk"
}

Write-Host ""
Write-Host "Release pack ready:" -ForegroundColor Cyan
Write-Host "  $dist"
Get-ChildItem $dist -Filter "*.apk" | ForEach-Object {
    Write-Host ("  - {0} ({1:N1} MB)" -f $_.Name, ($_.Length / 1MB))
}
