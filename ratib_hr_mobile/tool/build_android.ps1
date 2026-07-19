# Phase A0 — build Android APK / App Bundle by flavor (debug signing if no key.properties).
# Usage:
#   .\tool\build_android.ps1 -Flavor production
#   .\tool\build_android.ps1 -Flavor staging -Bundle

param(
    [ValidateSet("dev", "staging", "production")]
    [string]$Flavor = "production",
    [switch]$Bundle
)

$ErrorActionPreference = "Stop"
$flutter = Join-Path $env:LOCALAPPDATA "flutter\bin\flutter.bat"
if (-not (Test-Path $flutter)) {
    throw "Flutter not found at $flutter"
}

$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

$dartFlavor = if ($Flavor -eq "dev") { "development" } else { $Flavor }
$target = if ($Bundle) { "appbundle" } else { "apk" }

$args = @(
    "build", $target,
    "--flavor", $Flavor,
    "--dart-define=APP_FLAVOR=$dartFlavor"
)

Write-Host "Building: flutter $($args -join ' ')"
& $flutter @args
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
Write-Host "OK — flavor=$Flavor target=$target"
