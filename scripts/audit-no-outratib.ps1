# Audit: fail if out.ratib.sa / outratib appear in active repo paths.
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$patterns = @('out\.ratib\.sa', 'outratib', 'www\.out\.ratib')
$excludeDir = @(
    [regex]::Escape('archive'),
    [regex]::Escape('Designed'),
    [regex]::Escape('.git'),
    [regex]::Escape('node_modules'),
    [regex]::Escape('agent-transcripts'),
    [regex]::Escape('logs'),
    [regex]::Escape('rateb_mobile\.dart_tool'),
    [regex]::Escape('rateb_mobile\build'),
    [regex]::Escape('__pycache__')
)
$excludeFile = @(
    [regex]::Escape('scripts/audit-no-outratib.ps1')
)
$hits = @()

Get-ChildItem -Path $root -Recurse -File -ErrorAction SilentlyContinue | ForEach-Object {
    $rel = $_.FullName.Substring($root.Length + 1) -replace '\\', '/'
    foreach ($ex in $excludeDir) {
        if ($rel -match "(^|/)$ex(/|$)") { return }
    }
    foreach ($ex in $excludeFile) {
        if ($rel -match $ex) { return }
    }
    if ($_.Extension -match '\.(png|jpg|jpeg|gif|webp|ico|pdf|zip|gz|woff2?|ttf|eot|dill|pyc)$') { return }
    try {
        $text = [System.IO.File]::ReadAllText($_.FullName)
    } catch { return }
    foreach ($p in $patterns) {
        if ($text -match $p) {
            $hits += "$rel ($p)"
            break
        }
    }
}

if ($hits.Count -eq 0) {
    Write-Host "PASS: no out.ratib / outratib in active paths (archive/Designed/logs/build excluded)."
    exit 0
}

Write-Host "FAIL: found $($hits.Count) file(s) in active paths:"
$hits | ForEach-Object { Write-Host "  $_" }
exit 1
