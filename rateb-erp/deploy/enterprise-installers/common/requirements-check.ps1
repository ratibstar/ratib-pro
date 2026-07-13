# Step 1 — Windows requirements (64-bit, Win10+ / Server 2019+).
param(
    [string]$PhpPath = '',
    [string]$BundledPhpDir = ''
)
$ErrorActionPreference = 'Stop'

function Test-OsOk {
    $os = Get-CimInstance Win32_OperatingSystem
    $arch = $env:PROCESSOR_ARCHITECTURE
    if ($arch -ne 'AMD64' -and $arch -ne 'ARM64') {
        throw "64-bit Windows required (got $arch)"
    }
    $ver = [version]$os.Version
    # Windows 10 = 10.0; Server 2019 = 10.0.17763+
    if ($ver.Major -lt 10) {
        throw "Windows 10+ or Windows Server 2019+ required"
    }
    Write-Host "OK OS $($os.Caption) $($os.Version) $arch"
}

function Resolve-Php {
    if ($PhpPath -and (Test-Path $PhpPath)) { return (Resolve-Path $PhpPath).Path }
    $cmd = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }
    if ($BundledPhpDir) {
        $p = Join-Path $BundledPhpDir 'php.exe'
        if (Test-Path $p) { return (Resolve-Path $p).Path }
    }
    return $null
}

Test-OsOk
$php = Resolve-Php
if (-not $php) {
    Write-Warning "php.exe not found. Installer must unpack bundled PHP into runtime\php."
    exit 2
}
$verOut = & $php -r "echo PHP_VERSION;"
if (-not $verOut -or [version]($verOut.Split('-')[0]) -lt [version]'8.2.0') {
    throw "PHP 8.2+ required (got $verOut)"
}
$mods = & $php -m
foreach ($e in @('pdo_sqlite','sqlite3','openssl','gd','curl','zip','json')) {
    if ($mods -notcontains $e) {
        throw "PHP extension missing: $e"
    }
}
Write-Host "OK PHP $verOut at $php"
$php
