#Requires -Version 5.1
<#
.SYNOPSIS
  Phase K1 — build Play-uploadable production AAB (upload-key signed).
#>
param(
  [string]$FlutterRoot = $(if ($env:FLUTTER_ROOT) { $env:FLUTTER_ROOT } else { "C:\flutter-sdk" }),
  [string]$ErpBaseUrl = "https://rateb.sa/rateb-erp/public",
  [string]$AndroidSdk = $(if ($env:ANDROID_SDK_ROOT) { $env:ANDROID_SDK_ROOT } elseif ($env:ANDROID_HOME) { $env:ANDROID_HOME } elseif (Test-Path "C:\android-sdk") { "C:\android-sdk" } else { "$env:LOCALAPPDATA\Android\sdk" })
)

$ErrorActionPreference = "Stop"
$root = Split-Path $PSScriptRoot -Parent
Set-Location $root

# Toolchain for Flutter AAB strip verification (apkanalyzer / cmdline-tools)
if (-not $env:JAVA_HOME -or -not (Test-Path "$env:JAVA_HOME\bin\java.exe")) {
  $jbr = "C:\Program Files\Android\Android Studio\jbr"
  if (Test-Path "$jbr\bin\java.exe") { $env:JAVA_HOME = $jbr }
}
$env:ANDROID_HOME = $AndroidSdk
$env:ANDROID_SDK_ROOT = $AndroidSdk
if (Test-Path "$AndroidSdk\ndk\28.2.13676358") {
  $env:ANDROID_NDK_HOME = "$AndroidSdk\ndk\28.2.13676358"
}
$env:Path = "$env:JAVA_HOME\bin;$AndroidSdk\cmdline-tools\latest\bin;$AndroidSdk\platform-tools;$env:Path"

$flutter = Join-Path $FlutterRoot "bin\flutter.bat"
if (-not (Test-Path $flutter)) {
  throw "Flutter not found at $flutter"
}

$keyProps = Join-Path $root "android\key.properties"
$jks = Join-Path $root "android\keystore\ratib-hr-upload-key.jks"
if (-not (Test-Path $keyProps)) {
  throw "Missing android/key.properties (gitignored). Copy from key.properties.example and fill secrets locally."
}
if (-not (Test-Path $jks)) {
  throw "Missing android/keystore/ratib-hr-upload-key.jks — generate upload keystore before building."
}

Write-Host "=== flutter clean ==="
& $flutter clean
Write-Host "=== flutter pub get ==="
& $flutter pub get
Write-Host "=== flutter build appbundle (production release) ==="
& $flutter build appbundle `
  --flavor production `
  --release `
  --dart-define=APP_FLAVOR=production `
  --dart-define=ERP_BASE_URL=$ErpBaseUrl

if ($LASTEXITCODE -ne 0) {
  throw "flutter build appbundle failed with exit $LASTEXITCODE"
}

$aab = Join-Path $root "build\app\outputs\bundle\productionRelease\app-production-release.aab"
if (-not (Test-Path $aab)) {
  throw "AAB not found at $aab"
}

Get-Item $aab | Format-List FullName, Length, LastWriteTime
Write-Host "OK: productionRelease AAB built (upload-key signed when key.properties present)."
