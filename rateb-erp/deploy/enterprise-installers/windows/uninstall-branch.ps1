#Requires -RunAsAdministrator
param(
    [string]$InstallRoot = 'C:\Program Files\RATIB Branch',
    [ValidateSet('ask','yes','no')]
    [string]$KeepDatabase = 'ask'
)
$ErrorActionPreference = 'Stop'

foreach ($name in @('RATIBBranchWeb','RATIBHybridSync')) {
    $exeCandidates = @(
        (Join-Path $InstallRoot "bin\windows\$name.exe"),
        (Join-Path $PSScriptRoot "$name.exe")
    )
    foreach ($exe in $exeCandidates) {
        if (Test-Path $exe) {
            & $exe stop 2>$null
            & $exe uninstall 2>$null
            break
        }
    }
    Get-Service -Name $name -ErrorAction SilentlyContinue | ForEach-Object {
        Stop-Service $_ -Force -ErrorAction SilentlyContinue
    }
}

if ($KeepDatabase -eq 'ask') {
    $r = Read-Host 'Keep Branch Database? [Y/n]'
    if ($r -match '^[nN]') { $KeepDatabase = 'no' } else { $KeepDatabase = 'yes' }
}

if ($KeepDatabase -eq 'yes') {
    Write-Host 'Keeping storage (SQLite, backups, logs). Removing binaries only.'
    Get-ChildItem $InstallRoot -Force | Where-Object { $_.Name -ne 'storage' } | Remove-Item -Recurse -Force
} else {
    Remove-Item -Recurse -Force $InstallRoot -ErrorAction SilentlyContinue
}
Write-Host "OK uninstalled (KeepDatabase=$KeepDatabase)"
