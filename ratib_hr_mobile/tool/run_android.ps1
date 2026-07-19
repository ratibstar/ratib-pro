# Phase A0 — run ratib_hr_mobile on Android with a flavor.
# Usage:
#   .\tool\run_android.ps1 -Flavor dev
#   .\tool\run_android.ps1 -Flavor staging -ErpBaseUrl "https://example.com/rateb-erp/public"
#   .\tool\run_android.ps1 -Flavor production -DeviceId emulator-5554

param(
    [ValidateSet("dev", "staging", "production")]
    [string]$Flavor = "dev",
    [string]$ErpBaseUrl = "",
    [string]$DeviceId = ""
)

$ErrorActionPreference = "Stop"
$flutter = Join-Path $env:LOCALAPPDATA "flutter\bin\flutter.bat"
if (-not (Test-Path $flutter)) {
    throw "Flutter not found at $flutter"
}

$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

$dartFlavor = if ($Flavor -eq "dev") { "development" } else { $Flavor }
$defines = @(
    "--dart-define=APP_FLAVOR=$dartFlavor"
)
if ($ErpBaseUrl -ne "") {
    $defines += "--dart-define=ERP_BASE_URL=$ErpBaseUrl"
}

$args = @("run", "--flavor", $Flavor) + $defines
if ($DeviceId -ne "") {
    $args += @("-d", $DeviceId)
}

Write-Host "Running: flutter $($args -join ' ')"
& $flutter @args
