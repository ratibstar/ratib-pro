# RATIB ERP Safe QA v2 runner — manifest-based ID capture via qa-manifest-resolve.php
param(
    [string]$Site = 'https://rateb.sa',
    [string]$MigrateToken = $env:RATEB_ERP_MIGRATE_TOKEN
)

$ErrorActionPreference = 'Stop'
Import-Module (Join-Path $PSScriptRoot 'qa-manifest\SafeQaManifest.psm1') -Force

if ([string]::IsNullOrWhiteSpace($MigrateToken)) {
    $MigrateToken = $env:CPANEL_API_TOKEN
}
if ([string]::IsNullOrWhiteSpace($MigrateToken)) {
    Stop-SafeQaSession -Manifest @{ sessionTag = 'UNINITIALIZED' } -Reason 'RATEB_ERP_MIGRATE_TOKEN required for manifest ID resolution'
}

$session = New-SafeQaSession -Site $Site
$manifest = [hashtable]$session.Manifest
$path = $session.Path
$ts = (Get-Date -Format 'yyyyMMddHHmmss')
$base = "$Site/rateb-erp/public"

function Get-Csrf($html) {
    $m = [regex]::Match($html, 'name="_csrf"\s+value="([^"]+)"')
    if ($m.Success) { return $m.Groups[1].Value }
    return ''
}

function Resolve-AndRegister {
    param(
        [string]$Type,
        [string]$Slug,
        [string]$Email,
        [string]$Code,
        [int]$CompanyId = 0
    )
    $resolved = Invoke-QaManifestResolve -Site $Site -Token $MigrateToken -Type $Type -Slug $Slug -Email $Email -Code $Code -CompanyId $CompanyId
    if (-not $resolved.ok) {
        Stop-SafeQaSession -Manifest $manifest -Reason "Resolver failed type=$Type error=$($resolved.error)"
    }
    return [int]$resolved.id
}

# Login
$email = 'admin@rateb.sa'
$pw = if ($env:RATEB_QA_PASSWORD) { $env:RATEB_QA_PASSWORD } else { 'password' }
$sess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$lp = Invoke-WebRequest -Uri "$base/login" -WebSession $sess -UseBasicParsing
Invoke-WebRequest -Uri "$base/login" -Method POST -WebSession $sess -Body @{
    email = $email; password = $pw; _csrf = (Get-Csrf $lp.Content)
} -UseBasicParsing | Out-Null

# --- Test 12: Company (QA prefix) ---
$slug = "QA-COMPANY-$ts"
$name = $slug
$coCreate = Invoke-WebRequest -Uri "$base/admin/companies/create" -WebSession $sess -UseBasicParsing
Invoke-WebRequest -Uri "$base/admin/companies" -Method POST -WebSession $sess -Body @{
    _csrf = (Get-Csrf $coCreate.Content); name = $name; slug = $slug
    email = "qa-company-$ts@test.local"; phone = '0500000000'; status = 'active'
    plan_id = '1'; user_limit = '10'; branch_limit = '5'; storage_limit_mb = '512'
} -UseBasicParsing | Out-Null
Start-Sleep -Seconds 1
$companyId = Resolve-AndRegister -Type 'company' -Slug $slug
Add-SafeQaObject -Manifest $manifest -Type 'company' -Id $companyId -Slug $slug | Out-Null
Register-SafeQaWrite -Manifest $manifest -Type 'company' -Id $companyId -Action 'create'

# --- Test 14: User (QA prefix) ---
$userEmail = "QA-USER-$ts@test.local"
$uc = Invoke-WebRequest -Uri "$base/admin/users/create" -WebSession $sess -UseBasicParsing
Invoke-WebRequest -Uri "$base/admin/users" -Method POST -WebSession $sess -Body @{
    _csrf = (Get-Csrf $uc.Content); name = "QA-USER-$ts"; email = $userEmail
    phone = '0501111111'; company_id = $companyId; status = 'active'; locale = 'en'; password = "QaSafe${ts}X"
} -UseBasicParsing | Out-Null
Start-Sleep -Seconds 1
$userId = Resolve-AndRegister -Type 'user' -Email $userEmail
Add-SafeQaObject -Manifest $manifest -Type 'user' -Id $userId -Email $userEmail -ParentCompanyId $companyId | Out-Null
Register-SafeQaWrite -Manifest $manifest -Type 'user' -Id $userId -Action 'create'

# --- Test 15: Role (QA prefix) ---
$roleSlug = "QA-ROLE-$ts"
$rc = Invoke-WebRequest -Uri "$base/admin/roles/create" -WebSession $sess -UseBasicParsing
$perm = ([regex]::Matches($rc.Content, 'name="permission_ids\[\]"\s+value="(\d+)"') | Select-Object -First 1)
if (-not $perm) { Stop-SafeQaSession -Manifest $manifest -Reason 'No permission checkbox on role form' }
Invoke-WebRequest -Uri "$base/admin/roles" -Method POST -WebSession $sess -Body @{
    _csrf = (Get-Csrf $rc.Content); name = $roleSlug; slug = $roleSlug; description = 'Safe QA v2'
    'permission_ids[0]' = $perm.Groups[1].Value
} -UseBasicParsing | Out-Null
Start-Sleep -Seconds 1
$roleId = Resolve-AndRegister -Type 'role' -Slug $roleSlug
Add-SafeQaObject -Manifest $manifest -Type 'role' -Id $roleId -Slug $roleSlug | Out-Null
Register-SafeQaWrite -Manifest $manifest -Type 'role' -Id $roleId -Action 'create'

$manifest.finishedAt = (Get-Date).ToString('o')
Save-SafeQaManifest -Manifest $manifest -Path $path
Write-Host "Manifest written: $path"
Write-Host "Objects: company=$companyId user=$userId role=$roleId"
$manifest | ConvertTo-Json -Depth 10
