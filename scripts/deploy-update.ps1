# Deploy Update — commit all changes (or empty deploy) and push to origin.
# Used by VS Code/Cursor task + Ctrl+Shift+U keybinding.
# Always pull --rebase first so push is not rejected when remote moved ahead.
$ErrorActionPreference = 'Stop'
$git = 'C:\Program Files\Git\\cmd\\git.exe'
if (-not (Test-Path $git)) {
    $git = (Get-Command git -ErrorAction SilentlyContinue).Source
}
if (-not $git) {
    Write-Host 'git.exe not found.'
    exit 1
}

Set-Location $PSScriptRoot\..
Write-Host ("Repo: " + (Get-Location))

Write-Host 'Fetching / pulling latest main...'
& $git fetch origin
if ($LASTEXITCODE -ne 0) {
    Write-Host 'git fetch failed.'
    exit 1
}
& $git pull --rebase origin HEAD
if ($LASTEXITCODE -ne 0) {
    Write-Host 'git pull --rebase failed. Resolve conflicts, then retry Ctrl+Shift+U.'
    exit 1
}

$pending = & $git status --porcelain
if ([string]::IsNullOrWhiteSpace($pending)) {
    $msg = 'deploy-' + (Get-Date -Format 'yyyyMMdd-HHmmss')
    & $git commit --allow-empty -m $msg
    if ($LASTEXITCODE -ne 0) {
        Write-Host 'Empty deploy commit failed.'
        exit 1
    }
    Write-Host 'No file changes — created empty deploy commit.'
} else {
    Write-Host $pending
    $msg = 'update-' + (Get-Date -Format 'yyyyMMdd-HHmmss')
    & $git add -A
    & $git commit -m $msg
    if ($LASTEXITCODE -ne 0) {
        Write-Host 'Commit failed.'
        exit 1
    }
}

& $git push origin HEAD
if ($LASTEXITCODE -eq 0) {
    Write-Host 'Pushed — deploy will start on GitHub Actions (~1-2 min).'
    & $git log -1 --oneline
    exit 0
}

Write-Host 'Push failed.'
exit 1
