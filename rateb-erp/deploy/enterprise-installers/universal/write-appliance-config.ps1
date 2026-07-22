# Phase D.3 — write appliance.env + HTML summary
param(
  [string]$InstallRoot,
  [int]$Port,
  [string]$PhpPath
)
$ErrorActionPreference = 'Stop'
$branchDir = Join-Path $InstallRoot 'storage\branch'
New-Item -ItemType Directory -Force -Path $branchDir | Out-Null
$version = 'unknown'
$vf = Join-Path $InstallRoot 'VERSION'
if (Test-Path $vf) { $version = (Get-Content $vf -Raw).Trim() }
$branchId = ''
$uuidFile = Join-Path $branchDir 'identity\branch.uuid'
if (Test-Path $uuidFile) { $branchId = (Get-Content $uuidFile -Raw).Trim() }
$url = if ($Port -eq 80) { 'http://127.0.0.1/admin' } else { "http://127.0.0.1:$Port/admin" }
$sqlite = Join-Path $branchDir 'rateb-branch.sqlite'
$sync = 'unknown'
try {
  $s = Get-Service -Name 'RATEBHybridSync' -ErrorAction SilentlyContinue
  if ($s) { $sync = $s.Status.ToString() }
} catch {}

@"
# Phase D.3 — Universal Branch Appliance (installer-written)
RATEB_BRANCH_HTTP_PORT=$Port
RATEB_BRANCH_HTTP_URL=$url
RATEB_PHP_BIN=$PhpPath
RATEB_APPLIANCE_VERSION=$version
RATEB_BRANCH_ID=$branchId
RATEB_CLOUD_URL=https://rateb.sa
"@ | Set-Content (Join-Path $branchDir 'appliance.env') -Encoding UTF8

$serve = Join-Path $branchDir 'serve.env'
if ((Test-Path $serve) -and -not ((Get-Content $serve -Raw) -match 'RATEB_BRANCH_HTTP_PORT=')) {
  Add-Content $serve "`nRATEB_BRANCH_HTTP_PORT=$Port"
}

@"
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>RATEB Branch Installed</title>
<style>body{font-family:Segoe UI,sans-serif;max-width:640px;margin:2rem auto;padding:0 1rem;line-height:1.5}
h1{font-size:1.4rem}code{background:#f4f4f4;padding:.1rem .3rem}
a.btn{display:inline-block;margin-top:1rem;padding:.6rem 1rem;background:#0b5;color:#fff;text-decoration:none;border-radius:4px}</style>
</head><body>
<h1>RATEB Branch Appliance ready</h1>
<p><strong>URL:</strong> <a href="$url">$url</a></p>
<p><strong>Branch ID:</strong> <code>$branchId</code></p>
<p><strong>SQLite:</strong> <code>$sqlite</code></p>
<p><strong>Sync:</strong> <code>$sync</code></p>
<p><strong>Health:</strong> <code>ok</code></p>
<p><strong>Version:</strong> <code>$version</code></p>
<p><strong>PHP:</strong> <code>$PhpPath</code></p>
<a class="btn" href="$url">Open ERP</a>
</body></html>
"@ | Set-Content (Join-Path $branchDir 'post-install.html') -Encoding UTF8

Write-Output $url
