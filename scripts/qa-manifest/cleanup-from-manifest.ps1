# Delete ONLY objects listed in a Safe QA manifest (exact IDs, manifest-only).
param(
    [Parameter(Mandatory)][string]$ManifestPath,
    [switch]$WhatIf
)

$ErrorActionPreference = 'Stop'
Import-Module (Join-Path $PSScriptRoot 'SafeQaManifest.psm1') -Force

if (-not (Test-Path $ManifestPath)) {
    throw "Manifest not found: $ManifestPath"
}

$manifestJson = Get-Content -Path $ManifestPath -Raw -Encoding UTF8 | ConvertFrom-Json
$site = [string]$manifestJson.site
if ($site -eq '') { $site = 'https://rateb.sa' }

$email = 'admin@rateb.sa'
$pw = if ($env:RATEB_QA_PASSWORD) { $env:RATEB_QA_PASSWORD } else { 'password' }

function Get-Csrf($html) {
    $m = [regex]::Match($html, 'name="_csrf"\s+value="([^"]+)"')
    if ($m.Success) { return $m.Groups[1].Value }
    return ''
}

function Invoke-ErpDelete {
    param([string]$Route, [int]$Id, $Session)
    $list = Invoke-WebRequest -Uri "$site/rateb-erp/public/admin/$Route" -WebSession $Session -UseBasicParsing
    $csrf = Get-Csrf $list.Content
    $null = Invoke-WebRequest -Uri "$site/rateb-erp/public/admin/${Route}/$Id/delete" -Method POST -WebSession $Session -Body @{ _csrf = $csrf } -UseBasicParsing -MaximumRedirection 10
}

# Login
$sess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$lp = Invoke-WebRequest -Uri "$site/rateb-erp/public/login" -WebSession $sess -UseBasicParsing
Invoke-WebRequest -Uri "$site/rateb-erp/public/login" -Method POST -WebSession $sess -Body @{
    email = $email; password = $pw; _csrf = (Get-Csrf $lp.Content)
} -UseBasicParsing | Out-Null

$order = @('user', 'role', 'subscription', 'branch', 'company')
$remaining = @()

foreach ($type in $order) {
    foreach ($obj in @($manifestJson.objects | Where-Object { $_.type -eq $type -and $null -eq $_.deletedAt })) {
        $id = [int]$obj.id
        $route = switch ($type) {
            'user' { 'users' }
            'role' { 'roles' }
            'subscription' { 'subscriptions' }
            'company' { 'companies' }
            'branch' { throw 'branch delete not implemented in ERP admin — skip or CP manual' }
        }
        Write-Host "Manifest delete: $type id=$id"
        if ($WhatIf) { continue }
        try {
            Invoke-ErpDelete -Route $route -Id $id -Session $sess
            $verify = Invoke-WebRequest -Uri "$site/rateb-erp/public/admin/${route}/$id/edit" -WebSession $sess -UseBasicParsing -ErrorAction SilentlyContinue
            $gone = ($verify.StatusCode -ne 200) -or ($verify.Content -match '404')
            if (-not $gone) {
                $remaining += "${type}:${id} still exists after delete"
            }
        } catch {
            $remaining += "${type}:${id} delete error: $($_.Exception.Message)"
        }
    }
}

Write-Host '--- Cleanup summary ---'
if ($remaining.Count -eq 0) {
    Write-Host 'All manifest objects removed (or WhatIf skipped deletes).'
} else {
    $remaining | ForEach-Object { Write-Host $_ }
}
