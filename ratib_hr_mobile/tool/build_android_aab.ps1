#Requires -Version 5.1
<#
.SYNOPSIS
  Phase K — build production Android App Bundle (AAB) for Play Console.
#>
param(
  [string]$FlutterRoot = $(if ($env:FLUTTER_ROOT) { $env:FLUTTER_ROOT } else { "C:\flutter-sdk" })
)

$ErrorActionPreference = "Stop"
Set-Location (Split-Path $PSScriptRoot -Parent)

$flutter = Join-Path $FlutterRoot "bin\flutter.bat"
if (-not (Test-Path $flutter)) {
  throw "Flutter not found at $flutter"
}

$keyProps = Join-Path (Get-Location) "android\key.properties"
if (-not (Test-Path $keyProps)) {
  Write-Warning "android/key.properties missing — AAB will be debug-signed (NOT Play-uploadable). See android/key.properties.example"
}

& $flutter pub get
& $flutter build appbundle `
  --flavor production `
  --release `
  --dart-define=APP_FLAVOR=production

$aab = "build\app\outputs\bundle\productionRelease\app-production-release.aab"
if (Test-Path $aab) {
  Get-Item $aab | Format-List FullName, Length, LastWriteTime
} else {
  throw "AAB not found at $aab"
}
