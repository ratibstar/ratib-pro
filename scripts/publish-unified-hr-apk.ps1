<#
.SYNOPSIS
  Publish the unified HR APK (sa.rateb.hr.mobile) to rateb-erp/public/downloads after verifying
  it carries no branded company's activation code or server.

.EXAMPLE
  .\scripts\publish-unified-hr-apk.ps1
#>
param(
    [string] $Apk = ''
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
if ($Apk -eq '') {
    $Apk = Join-Path $root 'ratib_hr_mobile\build\app\outputs\flutter-apk\app-production-release.apk'
}
if (-not (Test-Path $Apk)) { throw "APK missing: $Apk" }

$needles = New-Object System.Collections.Generic.List[string]
Get-ChildItem (Join-Path $root 'mobile-branding') -Filter '*.json' -ErrorAction SilentlyContinue | ForEach-Object {
    $spec = [IO.File]::ReadAllText($_.FullName, [Text.Encoding]::UTF8) | ConvertFrom-Json
    if ($spec.code) { $needles.Add([string]$spec.code) }
    if ($spec.server) {
        $h = ([Uri][string]$spec.server).Host
        if ($h -and $h -notmatch '^(www\.)?rateb\.sa$|^admin\.rateb\.sa$') { $needles.Add($h) }
    }
    if ($spec.key) { $needles.Add([string]$spec.key) }
}

Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [IO.Compression.ZipFile]::OpenRead($Apk)
try {
    $libs = @($zip.Entries | Where-Object { $_.FullName -like 'lib/*/libapp.so' })
    if ($libs.Count -eq 0) { throw 'libapp.so not found in APK' }
    foreach ($entry in $libs) {
        $ms = New-Object IO.MemoryStream
        $s = $entry.Open(); $s.CopyTo($ms); $s.Dispose()
        $text = [Text.Encoding]::ASCII.GetString($ms.ToArray())
        foreach ($n in $needles) {
            if ($text.Contains($n)) { throw "Contaminated APK: $($entry.FullName) embeds branded value '$n'. Run 'flutter clean' and rebuild." }
        }
    }
} finally { $zip.Dispose() }

$aapt2 = Get-ChildItem 'C:\android_sdk\build-tools' -Recurse -Filter 'aapt2.exe' -ErrorAction SilentlyContinue | Sort-Object FullName -Descending | Select-Object -First 1
if (-not $aapt2) { throw 'aapt2 not found (C:\android_sdk\build-tools)' }
$badging = (& $aapt2.FullName dump badging $Apk | Select-Object -First 1)
if ($badging -notmatch "name='sa\.rateb\.hr\.mobile'") { throw "Not the unified package: $badging" }
$versionCode = if ($badging -match "versionCode='(\d+)'") { [int]$Matches[1] } else { 0 }
$versionName = if ($badging -match "versionName='([^']*)'") { $Matches[1] } else { '' }

$downloads = Join-Path $root 'rateb-erp\public\downloads'
$metaPath = Join-Path $downloads 'mobile-apps-latest.json'
$meta = [IO.File]::ReadAllText($metaPath, [Text.Encoding]::UTF8) | ConvertFrom-Json
if ($versionCode -le [int]$meta.hr.version_code) {
    throw "versionCode $versionCode must be greater than published $($meta.hr.version_code) (bump pubspec.yaml)"
}

Copy-Item -Force $Apk (Join-Path $downloads 'rateb-hr-mobile-latest.apk')
$meta.hr.version = $versionName
$meta.hr.version_code = $versionCode
[IO.File]::WriteAllText($metaPath, ($meta | ConvertTo-Json -Depth 5), (New-Object Text.UTF8Encoding($false)))

Write-Host ("OK -> rateb-hr-mobile-latest.apk ({0} / {1}, checked {2} branded values)" -f $versionName, $versionCode, $needles.Count) -ForegroundColor Green
