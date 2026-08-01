# Install a built APK via USB (adb). Phone must have USB debugging enabled.
# Usage:
#   .\tool\install_android.ps1
#   .\tool\install_android.ps1 -ApkPath "dist\android\rateb-hr-agency-admin.rateb.sa-arm64.apk"

param(
    [string]$ApkPath = ""
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot

if ($ApkPath -eq "") {
    $ApkPath = Join-Path $root "dist\android\rateb-hr-agency-admin.rateb.sa-arm64.apk"
}
if (-not [System.IO.Path]::IsPathRooted($ApkPath)) {
    $ApkPath = Join-Path $root $ApkPath
}
if (-not (Test-Path $ApkPath)) {
    throw "APK not found: $ApkPath`nRun .\tool\build_android_release_pack.ps1 first."
}

$adbCandidates = @(
    (Join-Path $env:LOCALAPPDATA "Android\Sdk\platform-tools\adb.exe"),
    "C:\Android\Sdk\platform-tools\adb.exe"
)
$adb = $adbCandidates | Where-Object { Test-Path $_ } | Select-Object -First 1
if (-not $adb) {
    throw "adb not found. Install Android SDK platform-tools or copy APK to phone manually."
}

$devices = & $adb devices | Select-Object -Skip 1 | Where-Object { $_ -match "device$" }
if (-not $devices) {
    throw @"
No Android device detected.
1. Enable Developer options + USB debugging on the phone
2. Connect USB and accept the debugging prompt
3. Run: & '$adb' devices
"@
}

Write-Host "Installing: $ApkPath"
& $adb install -r $ApkPath
if ($LASTEXITCODE -ne 0) {
    throw "adb install failed (exit $LASTEXITCODE)"
}
Write-Host "Installed OK." -ForegroundColor Green
