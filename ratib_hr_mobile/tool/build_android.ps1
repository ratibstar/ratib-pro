# Build Android APK / App Bundle by flavor (debug signing if no key.properties).
# Usage:
#   .\tool\build_android.ps1 -Flavor production
#   .\tool\build_android.ps1 -Flavor staging -ErpBaseUrl "https://stg.example/rateb-erp/public" -Bundle
#   .\tool\build_android.ps1 -Flavor production -SplitPerAbi

param(
    [ValidateSet("dev", "staging", "production")]
    [string]$Flavor = "production",
    [string]$ErpBaseUrl = "",
    [switch]$Bundle,
    [switch]$SplitPerAbi
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
    throw "Flutter SDK not found (tried C:\flutter-sdk and %LOCALAPPDATA%\flutter)"
}

$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

if ($ErpBaseUrl -eq "") {
    if ($Flavor -eq "production") {
        $ErpBaseUrl = "https://rateb.sa/rateb-erp/public"
    } else {
        throw "ErpBaseUrl is required for flavor=$Flavor (production defaults to https://rateb.sa/rateb-erp/public)"
    }
}

$dartFlavor = if ($Flavor -eq "dev") { "development" } else { $Flavor }
$target = if ($Bundle) { "appbundle" } else { "apk" }

$args = @(
    "build", $target,
    "--flavor", $Flavor,
    "--dart-define=APP_FLAVOR=$dartFlavor",
    "--dart-define=ERP_BASE_URL=$ErpBaseUrl"
)
if ($SplitPerAbi -and -not $Bundle) {
    $args += "--split-per-abi"
}

Write-Host "Building: flutter $($args -join ' ')"
& $flutter @args
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
Write-Host "OK — flavor=$Flavor erp=$ErpBaseUrl target=$target"
if (-not $Bundle) {
    Write-Host "APK folder: $root\build\app\outputs\flutter-apk\"
}
