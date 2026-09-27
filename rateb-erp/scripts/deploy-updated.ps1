$ErrorActionPreference = "Stop"

$project = "C:\Users\انا\Documents\ratibprogram"
$key = "C:\Users\انا\.ssh\ratebdeploy2"
$hostName = "admin@167.233.71.107"
$remote = "/home/admin/domains/rateb.sa/public_html/rateb-erp"

Set-Location $project

Write-Host "=== RATEB DEPLOY UPDATED ==="

$files = git diff --name-only HEAD
$untracked = git ls-files --others --exclude-standard

$allFiles = @($files) + @($untracked)
$allFiles = $allFiles | Where-Object {
    $_ -and
    $_ -notlike ".git/*" -and
    $_ -notlike ".vscode/*" -and
    $_ -notlike "node_modules/*" -and
    $_ -ne ".env"
} | Sort-Object -Unique

if ($allFiles.Count -eq 0) {
    Write-Host "No changed files to deploy."
    exit 0
}

Write-Host "Files to deploy:"
$allFiles | ForEach-Object { Write-Host " - $_" }

foreach ($file in $allFiles) {
    $local = Join-Path $project $file
    $remoteFile = "$remote/$($file -replace '\\','/')"
    $remoteDir = Split-Path $remoteFile -Parent

    Write-Host "Uploading: $file"

    & ssh -i $key $hostName "mkdir -p '$remoteDir'"
    if ($LASTEXITCODE -ne 0) {
        throw "Failed to create remote directory for $file"
    }

    & scp -i $key "$local" "$($hostName):$remoteFile"
    if ($LASTEXITCODE -ne 0) {
        throw "Failed to upload $file"
    }
}

Write-Host ""
Write-Host "=== DEPLOY UPDATED RATEB: COMPLETE ==="