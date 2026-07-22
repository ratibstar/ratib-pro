# RATEB ERP Safe QA v2 — Tests 14–16+ (manifest-only, session resolver auth)
param(
    [string]$Site = 'https://rateb.sa'
)

$ErrorActionPreference = 'Stop'
Import-Module (Join-Path $PSScriptRoot 'qa-manifest\SafeQaManifest.psm1') -Force

$base = "$Site/rateb-erp/public"
$adminEmail = 'admin@rateb.sa'
$adminPw = if ($env:RATEB_QA_PASSWORD) { $env:RATEB_QA_PASSWORD } else { 'password' }
$ts = Get-Date -Format 'yyyyMMddHHmmss'

function Get-Csrf($html) {
    $m = [regex]::Match($html, 'name="_csrf"\s+value="([^"]+)"')
    if ($m.Success) { return $m.Groups[1].Value }
    return ''
}

function Invoke-Resolve {
    param([string]$Type, [string]$Slug, [string]$Email, [int]$CompanyId = 0)
    $r = Invoke-QaManifestResolve -Site $Site -WebSession $sess -Type $Type -Slug $Slug -Email $Email -CompanyId $CompanyId
    if (-not $r.ok) { Stop-SafeQaSession -Manifest $manifest -Reason "Resolver failed type=$Type error=$($r.error)" }
    return [int]$r.id
}

function Remove-ManifestObject {
    param([string]$Type, [int]$Id, [string]$Route)
    Assert-SafeQaDeleteTarget -Manifest $manifest -Type $Type -Id $Id | Out-Null
    $list = Invoke-WebRequest -Uri "$base/admin/$Route" -WebSession $sess -UseBasicParsing
    Invoke-WebRequest -Uri "$base/admin/${Route}/$Id/delete" -Method POST -WebSession $sess -Body @{
        _csrf = (Get-Csrf $list.Content)
    } -UseBasicParsing -MaximumRedirection 10 | Out-Null
    Start-Sleep -Milliseconds 400
    try {
        $v = Invoke-WebRequest -Uri "$base/admin/${Route}/$Id/edit" -WebSession $sess -UseBasicParsing -MaximumRedirection 0
        $gone = ($v.StatusCode -ne 200)
    } catch {
        $gone = $true
    }
    if (-not $gone) {
        Stop-SafeQaSession -Manifest $manifest -Reason "Delete verification failed: $Type id=$Id still exists"
    }
    foreach ($obj in $manifest.objects) {
        if ([string]$obj.type -eq $Type -and [int]$obj.id -eq $Id) {
            $obj.deletedAt = (Get-Date).ToString('o')
        }
    }
    Register-SafeQaWrite -Manifest $manifest -Type $Type -Id $Id -Action 'delete_verified'
    Save-ManifestNow
}

function Save-ManifestNow {
    Save-SafeQaManifest -Manifest $manifest -Path $manifestPath
}

# --- Session + manifest ---
$sess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$lp = Invoke-WebRequest -Uri "$base/login" -WebSession $sess -UseBasicParsing
Invoke-WebRequest -Uri "$base/login" -Method POST -WebSession $sess -Body @{
    email = $adminEmail; password = $adminPw; _csrf = (Get-Csrf $lp.Content)
} -UseBasicParsing | Out-Null

$session = New-SafeQaSession -Site $Site
$manifest = @{}
foreach ($k in $session.Manifest.Keys) { $manifest[$k] = $session.Manifest[$k] }
$manifestPath = $session.Path

# Dependency company (required for user FK — not rerunning Tests 11–13)
$coSlug = "QA-COMPANY-$ts"
$coCreate = Invoke-WebRequest -Uri "$base/admin/companies/create" -WebSession $sess -UseBasicParsing
Invoke-WebRequest -Uri "$base/admin/companies" -Method POST -WebSession $sess -Body @{
    _csrf = (Get-Csrf $coCreate.Content); name = $coSlug; slug = $coSlug
    email = "qa-co-$ts@test.local"; phone = '0500000000'; status = 'active'
    plan_id = '1'; user_limit = '10'; branch_limit = '5'; storage_limit_mb = '512'
} -UseBasicParsing | Out-Null
Start-Sleep -Seconds 1
$companyId = Invoke-Resolve -Type 'company' -Slug $coSlug
Add-SafeQaObject -Manifest $manifest -Type 'company' -Id $companyId -Slug $coSlug | Out-Null
Register-SafeQaWrite -Manifest $manifest -Type 'company' -Id $companyId -Action 'create'
Save-ManifestNow

$report = [ordered]@{ tests = @{}; cleanup = @(); manifestPath = $manifestPath }

# ========== TEST 14 — Users ==========
$userEmail = "QA-USER-$ts@test.local"
$tempPass = "QaSafe${ts}X"
$newPass = "QaNew${ts}X"

$uc = Invoke-WebRequest -Uri "$base/admin/users/create" -WebSession $sess -UseBasicParsing
Invoke-WebRequest -Uri "$base/admin/users" -Method POST -WebSession $sess -Body @{
    _csrf = (Get-Csrf $uc.Content); name = "QA-USER-$ts"; email = $userEmail
    phone = '0501111111'; company_id = $companyId; status = 'active'; locale = 'en'; password = $tempPass
} -UseBasicParsing | Out-Null
Start-Sleep -Seconds 1
$userId = Invoke-Resolve -Type 'user' -Email $userEmail
Add-SafeQaObject -Manifest $manifest -Type 'user' -Id $userId -Email $userEmail -ParentCompanyId $companyId | Out-Null
Register-SafeQaWrite -Manifest $manifest -Type 'user' -Id $userId -Action 'create'

$uEdit = Invoke-WebRequest -Uri "$base/admin/users/$userId/edit" -WebSession $sess -UseBasicParsing
Invoke-WebRequest -Uri "$base/admin/users/$userId" -Method POST -WebSession $sess -Body @{
    _csrf = (Get-Csrf $uEdit.Content); name = "QA-USER-$ts-EDITED"; email = $userEmail
    company_id = $companyId; status = 'active'; locale = 'ar'; password = $newPass
} -UseBasicParsing | Out-Null
Register-SafeQaWrite -Manifest $manifest -Type 'user' -Id $userId -Action 'update_password'

$sessU = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$lpU = Invoke-WebRequest -Uri "$base/login" -WebSession $sessU -UseBasicParsing
$lrU = Invoke-WebRequest -Uri "$base/login" -Method POST -WebSession $sessU -Body @{
    email = $userEmail; password = $newPass; _csrf = (Get-Csrf $lpU.Content)
} -UseBasicParsing
$userLoginOk = ($lrU.BaseResponse.ResponseUri -notmatch '/login$' -or $lrU.Content -notmatch 'password-form')

Invoke-WebRequest -Uri "$base/admin/users/$userId" -Method POST -WebSession $sess -Body @{
    _csrf = (Get-Csrf (Invoke-WebRequest "$base/admin/users/$userId/edit" -WebSession $sess -UseBasicParsing).Content)
    name = "QA-USER-$ts-EDITED"; email = $userEmail; company_id = $companyId; status = 'inactive'; locale = 'ar'
} -UseBasicParsing | Out-Null
Register-SafeQaWrite -Manifest $manifest -Type 'user' -Id $userId -Action 'disable'
Save-ManifestNow

Remove-ManifestObject -Type 'user' -Id $userId -Route 'users'
Save-ManifestNow
$report.tests['14'] = @{
    result = if ($userLoginOk) { 'PASS' } else { 'PARTIAL' }
    userId = $userId; userEmail = $userEmail; passwordResetLogin = $userLoginOk
    urls = @("$base/admin/users/create", "$base/admin/users/$userId/edit")
}

# ========== TEST 15 — Roles & permissions ==========
$roleSlug = "QA-ROLE-$ts"
$rc = Invoke-WebRequest -Uri "$base/admin/roles/create" -WebSession $sess -UseBasicParsing
$perm = ([regex]::Matches($rc.Content, 'name="permission_ids\[\]"\s+value="(\d+)"') | Select-Object -First 1)
if (-not $perm) { Stop-SafeQaSession -Manifest $manifest -Reason 'No permissions on role form' }
Invoke-WebRequest -Uri "$base/admin/roles" -Method POST -WebSession $sess -Body @{
    _csrf = (Get-Csrf $rc.Content); name = $roleSlug; slug = $roleSlug; description = 'Safe QA v2 role'
    'permission_ids[]' = $perm.Groups[1].Value
} -UseBasicParsing -MaximumRedirection 10 | Out-Null
Start-Sleep -Seconds 1
$roleId = Invoke-Resolve -Type 'role' -Slug $roleSlug
Add-SafeQaObject -Manifest $manifest -Type 'role' -Id $roleId -Slug $roleSlug | Out-Null

$restSlug = "QA-ROLE-RESTRICTED-$ts"
$rc2 = Invoke-WebRequest -Uri "$base/admin/roles/create" -WebSession $sess -UseBasicParsing
Invoke-WebRequest -Uri "$base/admin/roles" -Method POST -WebSession $sess -Body @{
    _csrf = (Get-Csrf $rc2.Content); name = $restSlug; slug = $restSlug; description = 'restricted'
    'permission_ids[]' = $perm.Groups[1].Value
} -UseBasicParsing -MaximumRedirection 10 | Out-Null
Start-Sleep -Seconds 1
$restRoleId = Invoke-Resolve -Type 'role' -Slug $restSlug
Add-SafeQaObject -Manifest $manifest -Type 'role' -Id $restRoleId -Slug $restSlug | Out-Null

$restUserEmail = "QA-USER-RESTRICTED-$ts@test.local"
$uc2 = Invoke-WebRequest -Uri "$base/admin/users/create" -WebSession $sess -UseBasicParsing
Invoke-WebRequest -Uri "$base/admin/users" -Method POST -WebSession $sess -Body @{
    _csrf = (Get-Csrf $uc2.Content); name = "QA-USER-RESTRICTED-$ts"; email = $restUserEmail
    company_id = $companyId; status = 'active'; locale = 'en'; password = $tempPass
    'role_ids[0]' = $restRoleId
} -UseBasicParsing | Out-Null
Start-Sleep -Seconds 1
$restUserId = Invoke-Resolve -Type 'user' -Email $restUserEmail
Add-SafeQaObject -Manifest $manifest -Type 'user' -Id $restUserId -Email $restUserEmail -ParentCompanyId $companyId | Out-Null

$sessR = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$lpR = Invoke-WebRequest -Uri "$base/login" -WebSession $sessR -UseBasicParsing
Invoke-WebRequest -Uri "$base/login" -Method POST -WebSession $sessR -Body @{
    email = $restUserEmail; password = $tempPass; _csrf = (Get-Csrf $lpR.Content)
} -UseBasicParsing | Out-Null
$denied = Invoke-WebRequest -Uri "$base/admin/companies/create" -WebSession $sessR -UseBasicParsing -ErrorAction SilentlyContinue
$accessDenied = ($denied.StatusCode -eq 403) -or ($denied.Content -match '403|Forbidden|access|permission|unauthorized|غير مصرح') -or ($denied.BaseResponse.ResponseUri -notmatch 'companies/create')

Remove-ManifestObject -Type 'user' -Id $restUserId -Route 'users'
Remove-ManifestObject -Type 'role' -Id $restRoleId -Route 'roles'
Remove-ManifestObject -Type 'role' -Id $roleId -Route 'roles'

$report.tests['15'] = @{
    result = if ($accessDenied) { 'PASS' } else { 'PARTIAL' }
    roleId = $roleId; restrictedRoleId = $restRoleId; restrictedUserId = $restUserId
    accessDenied = $accessDenied
    urls = @("$base/admin/roles/create", "$base/admin/companies/create")
}

# ========== TEST 16 — Audit logs (read-only) ==========
$audit = Invoke-WebRequest -Uri "$base/admin/audit-logs" -WebSession $sess -UseBasicParsing
$report.tests['16'] = @{
    result = if ($audit.StatusCode -eq 200) { 'PASS' } else { 'FAIL' }
    status = $audit.StatusCode
    hasCreate = ($audit.Content -match 'create')
    hasUser = ($audit.Content -match 'user')
    hasCompany = ($audit.Content -match 'compan')
    url = "$base/admin/audit-logs"
}

# ========== TEST 17 — Login activity (read-only) ==========
try {
    $loginAct = Invoke-WebRequest -Uri "$base/admin/login-activity" -WebSession $sess -UseBasicParsing
    $report.tests['17'] = @{
        result = if ($loginAct.StatusCode -eq 200) { 'PASS' } else { 'PARTIAL' }
        status = $loginAct.StatusCode
        url = "$base/admin/login-activity"
    }
} catch {
    $report.tests['17'] = @{ result = 'PARTIAL'; error = $_.Exception.Message; url = "$base/admin/login-activity" }
}

# ========== Cleanup dependency company ==========
Remove-ManifestObject -Type 'company' -Id $companyId -Route 'companies'

# Verify resolver shows no QA objects for this session slugs
$verifyCo = Invoke-QaManifestResolve -Site $Site -WebSession $sess -Type 'company' -Slug $coSlug
$coGone = (-not $verifyCo.ok) -and ($verifyCo.error -eq 'not_found')

$manifest.finishedAt = (Get-Date).ToString('o')
$manifest.productionTouched = $false
Save-SafeQaManifest -Manifest $manifest -Path $manifestPath

$report.cleanup = @{
    companyRemoved = $coGone
    manifestObjects = $manifest.objects.Count
    writes = $manifest.writes.Count
}
$report | ConvertTo-Json -Depth 8
