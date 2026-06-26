# RATIB ERP Safe QA v2 — manifest module (single source of truth for created object IDs)

Set-StrictMode -Version Latest

$script:QaPrefixRules = @{
    company = '^QA-COMPANY-'
    user    = '^QA-USER-'
    role    = '^QA-ROLE-'
    branch  = '^QA-BRANCH-'
}

function New-SafeQaSession {
    param(
        [string]$Site = 'https://rateb.sa',
        [string]$ManifestDir = (Join-Path $PSScriptRoot 'sessions')
    )
    if (-not (Test-Path $ManifestDir)) {
        New-Item -ItemType Directory -Path $ManifestDir -Force | Out-Null
    }
    $tag = 'SAFE-QA-' + (Get-Date -Format 'yyyyMMdd-HHmmss')
    $manifest = [ordered]@{
        sessionTag     = $tag
        site           = $Site.TrimEnd('/')
        startedAt      = (Get-Date).ToString('o')
        finishedAt     = $null
        objects        = @()
        writes         = @()
        cleanup        = @()
        stopped        = $false
        stopReason     = $null
        productionTouched = $false
    }
    $path = Join-Path $ManifestDir ($tag + '.json')
    $manifest | ConvertTo-Json -Depth 12 | Set-Content -Path $path -Encoding UTF8
    return [pscustomobject]@{
        Tag      = $tag
        Path     = $path
        Manifest = $manifest
    }
}

function Save-SafeQaManifest {
    param(
        [Parameter(Mandatory)][hashtable]$Manifest,
        [Parameter(Mandatory)][string]$Path
    )
    $Manifest | ConvertTo-Json -Depth 12 | Set-Content -Path $Path -Encoding UTF8
}

function Test-QaPrefix {
    param(
        [Parameter(Mandatory)][string]$Type,
        [Parameter(Mandatory)][string]$Value
    )
    if (-not $script:QaPrefixRules.ContainsKey($Type)) {
        throw "Unknown QA object type: $Type"
    }
    return [bool]($Value -match $script:QaPrefixRules[$Type])
}

function Add-SafeQaObject {
    param(
        [Parameter(Mandatory)][hashtable]$Manifest,
        [Parameter(Mandatory)][string]$Type,
        [Parameter(Mandatory)][int]$Id,
        [string]$Slug = $null,
        [string]$Email = $null,
        [string]$Code = $null,
        [int]$CompanyId = 0,
        [int]$BranchId = 0,
        [int]$ParentCompanyId = 0,
        [int]$ParentRoleId = 0,
        [string]$Uuid = $null,
        [hashtable]$Extra = @{}
    )
    if ($Id -lt 1) {
        throw 'Add-SafeQaObject: invalid id'
    }
    $key = switch ($Type) {
        'company' { if (-not (Test-QaPrefix company $Slug)) { throw 'company slug missing QA-COMPANY- prefix' }; $Slug }
        'user'    { if (-not (Test-QaPrefix user $Email)) { throw 'user email missing QA-USER- prefix' }; $Email }
        'role'    { if (-not (Test-QaPrefix role $Slug)) { throw 'role slug missing QA-ROLE- prefix' }; $Slug }
        'branch'  { if (-not (Test-QaPrefix branch $Code)) { throw 'branch code missing QA-BRANCH- prefix' }; $Code }
        default   { throw "unsupported type $Type" }
    }
    foreach ($existing in $Manifest.objects) {
        if ([int]$existing.id -eq $Id -and [string]$existing.type -eq $Type) {
            return $existing
        }
    }
    $row = [ordered]@{
        type             = $Type
        id               = $Id
        uuid             = $Uuid
        slug             = $Slug
        email            = $Email
        code             = $Code
        companyId        = $(if ($CompanyId -gt 0) { $CompanyId } else { $null })
        branchId         = $(if ($BranchId -gt 0) { $BranchId } else { $null })
        parentCompanyId  = $(if ($ParentCompanyId -gt 0) { $ParentCompanyId } else { $null })
        parentRoleId     = $(if ($ParentRoleId -gt 0) { $ParentRoleId } else { $null })
        sessionTag       = $Manifest.sessionTag
        createdAt        = (Get-Date).ToString('o')
        deletedAt        = $null
        extra            = $Extra
    }
    $Manifest.objects += $row
    return $row
}

function Invoke-QaManifestResolve {
    param(
        [Parameter(Mandatory)][string]$Site,
        [string]$Token = '',
        [Microsoft.PowerShell.Commands.WebRequestSession]$WebSession = $null,
        [Parameter(Mandatory)][string]$Type,
        [string]$Slug,
        [string]$Email,
        [string]$Code,
        [int]$CompanyId = 0
    )
    $body = @{ type = $Type }
    switch ($Type) {
        'company' { $body.slug = $Slug }
        'user'    { $body.email = $Email }
        'role'    { $body.slug = $Slug }
        'branch'  { $body.code = $Code; $body.company_id = $CompanyId }
    }
    $json = $body | ConvertTo-Json -Compress
    $headers = @{}
    if ($Token -ne '') { $headers['X-Rateb-Migrate-Token'] = $Token }
    $params = @{
        Uri             = "$Site/rateb-erp/public/qa-manifest-resolve.php"
        Method          = 'POST'
        Body            = $json
        ContentType     = 'application/json; charset=utf-8'
        UseBasicParsing = $true
    }
    if ($headers.Count -gt 0) { $params.Headers = $headers }
    if ($null -ne $WebSession) { $params.WebSession = $WebSession }
    $resp = Invoke-WebRequest @params
    return ($resp.Content | ConvertFrom-Json)
}

function Register-SafeQaWrite {
    param(
        [Parameter(Mandatory)][hashtable]$Manifest,
        [Parameter(Mandatory)][string]$Type,
        [Parameter(Mandatory)][int]$Id,
        [Parameter(Mandatory)][string]$Action,
        [hashtable]$Extra = @{}
    )
    $Manifest.writes += [ordered]@{
        objectType = $Type
        objectId   = $Id
        action     = $Action
        time       = (Get-Date).ToString('o')
        extra      = $Extra
    }
}

function Stop-SafeQaSession {
    param(
        [Parameter(Mandatory)][hashtable]$Manifest,
        [Parameter(Mandatory)][string]$Reason
    )
    if ($Manifest.ContainsKey('stopped')) {
        $Manifest.stopped = $true
    }
    $Manifest.stopReason = $Reason
    throw "SAFE QA STOP: $Reason"
}

function Assert-SafeQaDeleteTarget {
    param(
        [Parameter(Mandatory)][hashtable]$Manifest,
        [Parameter(Mandatory)][string]$Type,
        [Parameter(Mandatory)][int]$Id
    )
    $match = $Manifest.objects | Where-Object {
        [string]$_.type -eq $Type -and [int]$_.id -eq $Id -and $null -eq $_.deletedAt
    } | Select-Object -First 1
    if (-not $match) {
        throw "Delete blocked: id $Id type $Type not in manifest or already deleted"
    }
    return $match
}

Export-ModuleMember -Function *
