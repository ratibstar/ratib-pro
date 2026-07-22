# Stage payload + compile RATEB-Branch-Setup.exe (requires Inno Setup 6).
$ErrorActionPreference = 'Stop'
$Root = Resolve-Path (Join-Path $PSScriptRoot '..\..\..')
$Out = Join-Path $Root 'storage\branch\enterprise-installers'
$Payload = Join-Path $Out 'payload\windows'
$Ei = Join-Path $Root 'deploy\enterprise-installers'

New-Item -ItemType Directory -Force -Path $Payload | Out-Null
if (Test-Path $Payload) { Remove-Item $Payload -Recurse -Force }
New-Item -ItemType Directory -Force -Path $Payload | Out-Null

$manifest = Get-Content (Join-Path $Ei 'payload\include-manifest.txt') | Where-Object {
    $_ -and -not $_.Trim().StartsWith('#')
}
foreach ($line in $manifest) {
    $rel = $line.Trim()
    if (-not $rel) { continue }
    $src = Join-Path $Root $rel
    if (-not (Test-Path $src)) { Write-Warning "missing $rel"; continue }
    $dst = Join-Path $Payload $rel
    $dstParent = Split-Path $dst -Parent
    New-Item -ItemType Directory -Force -Path $dstParent | Out-Null
    Copy-Item $src $dst -Recurse -Force
}

@(
    'storage\branch\logs',
    'storage\branch\backups',
    'storage\branch\tmp',
    'storage\sessions'
) | ForEach-Object {
    New-Item -ItemType Directory -Force -Path (Join-Path $Payload $_) | Out-Null
}

# Copy WinSW placeholders note
$winBin = Join-Path $Payload 'bin\windows'
New-Item -ItemType Directory -Force -Path $winBin | Out-Null
Copy-Item (Join-Path $Root 'bin\windows\*') $winBin -Force -ErrorAction SilentlyContinue
# Enterprise WinSW names expected by install scripts
$note = Join-Path $winBin 'WINSW-README.txt'
@"
Place WinSW binaries here (or beside installer scripts):
  RATEBHybridSync.exe  — rename from WinSW x64
  RATEBBranchWeb.exe   — second copy of WinSW x64
Download: https://github.com/winsw/winsw/releases
"@ | Set-Content $note -Encoding UTF8

# Optional: copy pre-downloaded WinSW if present in enterprise windows folder
foreach ($n in @('RATEBHybridSync.exe','RATEBBranchWeb.exe')) {
    $src = Join-Path $PSScriptRoot $n
    if (Test-Path $src) {
        Copy-Item $src (Join-Path $winBin $n) -Force
        Copy-Item $src (Join-Path $PSScriptRoot $n) -Force
    }
}

$iscc = @(
    "${env:ProgramFiles(x86)}\Inno Setup 6\ISCC.exe",
    "${env:ProgramFiles}\Inno Setup 6\ISCC.exe"
) | Where-Object { Test-Path $_ } | Select-Object -First 1

if (-not $iscc) {
    Write-Host "Payload staged: $Payload"
    Write-Host "Inno Setup 6 not found — install from https://jrsoftware.org/isinfo.php then re-run."
    Write-Host "Expected output: $Out\RATEB-Branch-Setup.exe"
    exit 0
}

$iss = Join-Path $PSScriptRoot 'RATEB-Branch-Setup.iss'
& $iscc "/DPayloadDir=$Payload" $iss
if ($LASTEXITCODE -ne 0) { throw "ISCC failed: $LASTEXITCODE" }
Write-Host "OK $Out\RATEB-Branch-Setup.exe"
