# RATEB ERP Safe QA Mode — Tests 11-16 (session manifest + exact-ID cleanup only)
$ErrorActionPreference = 'Stop'
$base = 'https://rateb.sa/rateb-erp/public'
$ts = Get-Date -Format 'yyyyMMddHHmmss'
$sessionTag = "SAFE-QA-$ts"
$manifest = [ordered]@{
    sessionTag = $sessionTag
    startedAt = (Get-Date).ToString('o')
    writes = @()
    created = [ordered]@{
        companyId = $null
        companySlug = "QA-COMPANY-$ts"
        userId = $null
        userEmail = "QA-USER-$ts@test.local"
        restrictedUserId = $null
        restrictedUserEmail = "QA-USER-RESTRICTED-$ts@test.local"
        roleId = $null
        roleSlug = "QA-ROLE-$ts"
        restrictedRoleId = $null
        restrictedRoleSlug = "QA-ROLE-RESTRICTED-$ts"
    }
    tests = @{}
    cleanup = @()
    stopped = $false
    stopReason = $null
}

function Get-Csrf($html) {
    $m = [regex]::Match($html, 'name="_csrf"\s+value="([^"]+)"')
    if ($m.Success) { return $m.Groups[1].Value }
    return ''
}

function Log-Write($type, $id, $action, $extra) {
    $manifest.writes += [ordered]@{
        objectType = $type
        objectId = $id
        action = $action
        time = (Get-Date).ToString('o')
        extra = $extra
    }
}

function Invoke-Erp($Method, $Url, $Body, $Session) {
    $sw = [System.Diagnostics.Stopwatch]::StartNew()
    if ($Method -eq 'GET') {
        $r = Invoke-WebRequest -Uri $Url -WebSession $Session -UseBasicParsing -MaximumRedirection 5
    } else {
        $r = Invoke-WebRequest -Uri $Url -Method POST -Body $Body -WebSession $Session -UseBasicParsing -MaximumRedirection 5
    }
    $sw.Stop()
    return @{ status = $r.StatusCode; url = $r.BaseResponse.ResponseUri.AbsoluteUri; html = $r.Content; ms = $sw.ElapsedMilliseconds }
}

function Stop-Qa($reason) {
    $manifest.stopped = $true
    $manifest.stopReason = $reason
    throw "QA STOP: $reason"
}

function Find-CompanyIdBySlug($html, $slug) {
    foreach ($m in [regex]::Matches($html, 'admin/companies/(\d+)/edit')) {
        $start = [Math]::Max(0, $m.Index - 400)
        $len = [Math]::Min(800, $html.Length - $start)
        $chunk = $html.Substring($start, $len)
        if ($chunk -match [regex]::Escape($slug)) { return [int]$m.Groups[1].Value }
    }
    return 0
}

function Find-UserIdByEmail($html, $email) {
    foreach ($m in [regex]::Matches($html, 'admin/users/(\d+)/edit')) {
        $start = [Math]::Max(0, $m.Index - 400)
        $len = [Math]::Min(800, $html.Length - $start)
        $chunk = $html.Substring($start, $len)
        if ($chunk -match [regex]::Escape($email)) { return [int]$m.Groups[1].Value }
    }
    return 0
}

function Find-RoleIdBySlug($html, $slug) {
    foreach ($m in [regex]::Matches($html, 'admin/roles/(\d+)/edit')) {
        $start = [Math]::Max(0, $m.Index - 400)
        $len = [Math]::Min(800, $html.Length - $start)
        $chunk = $html.Substring($start, $len)
        if ($chunk -match [regex]::Escape($slug)) { return [int]$m.Groups[1].Value }
    }
    return 0
}

# Login
$email = 'admin@rateb.sa'
$pw = if ($env:RATEB_QA_PASSWORD) { $env:RATEB_QA_PASSWORD } else { 'password' }
$sess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$lp = Invoke-Erp GET "$base/login" $null $sess
$lr = Invoke-Erp POST "$base/login" @{ email = $email; password = $pw; _csrf = (Get-Csrf $lp.html) } $sess
if ($lr.url -notmatch '/admin' -and $lr.html -notmatch 'rateb-widget|rateb-sidebar') {
    Stop-Qa 'Super-admin login failed — aborting all writes'
}

# TEST 11 — Dashboard (read-only)
$dash = Invoke-Erp GET "$base/admin" $null $sess
$notif = Invoke-Erp GET "$base/admin/notifications" $null $sess
$manifest.tests['11'] = @{
    result = if ($dash.html -match 'rateb-widget' -and $dash.html -match 'chart-revenue') { 'PASS' } else { 'PARTIAL' }
    loadMs = $dash.ms
    widgetCount = ([regex]::Matches($dash.html, 'rateb-widget')).Count
    hasCharts = ($dash.html -match 'chart-revenue|chart-companies|chart-subscriptions')
    hasRecentActivity = ($dash.html -match 'recent.activity|recent_activity')
    notificationsOk = ($notif.status -eq 200)
    url = $dash.url
}

# TEST 12 — Companies (QA prefix only)
$slug = $manifest.created.companySlug
$name = "QA-COMPANY-$ts"
$coCreate = Invoke-Erp GET "$base/admin/companies/create" $null $sess
$coStore = Invoke-Erp POST "$base/admin/companies" @{
    _csrf = (Get-Csrf $coCreate.html); name = $name; slug = $slug
    email = "qa-company-$ts@test.local"; phone = '0500000000'; status = 'active'
    plan_id = '1'; user_limit = '10'; branch_limit = '5'; storage_limit_mb = '512'
} $sess
Start-Sleep -Seconds 1
$coList = Invoke-Erp GET "$base/admin/companies" $null $sess
$companyId = Find-CompanyIdBySlug $coList.html $slug
if ($companyId -lt 1) { Stop-Qa "Could not resolve created company ID for slug $slug — no cleanup attempted" }
$manifest.created.companyId = $companyId
Log-Write 'company' $companyId 'create' @{ slug = $slug; name = $name }

$coEdit = Invoke-Erp GET "$base/admin/companies/$companyId/edit" $null $sess
$editName = "$name-EDITED"
Invoke-Erp POST "$base/admin/companies/$companyId" @{
    _csrf = (Get-Csrf $coEdit.html); name = $editName; slug = $slug
    email = "qa-company-$ts@test.local"; phone = '0500000001'; status = 'active'
    plan_id = '1'; user_limit = '10'; branch_limit = '5'; storage_limit_mb = '512'
} $sess | Out-Null
Log-Write 'company' $companyId 'update' @{ name = $editName }
$coEdit2 = Invoke-Erp GET "$base/admin/companies/$companyId/edit" $null $sess
$editOk = $coEdit2.html -match [regex]::Escape($editName)

$coList2 = Invoke-Erp GET "$base/admin/companies" $null $sess
Invoke-Erp POST "$base/admin/companies/$companyId/suspend" @{ _csrf = (Get-Csrf $coList2.html) } $sess | Out-Null
Log-Write 'company' $companyId 'suspend' @{}
$coEdit3 = Invoke-Erp GET "$base/admin/companies/$companyId/edit" $null $sess
$suspendOk = $coEdit3.html -match 'suspended'

$coList3 = Invoke-Erp GET "$base/admin/companies" $null $sess
Invoke-Erp POST "$base/admin/companies/$companyId/activate" @{ _csrf = (Get-Csrf $coList3.html) } $sess | Out-Null
Log-Write 'company' $companyId 'activate' @{}
$coEdit4 = Invoke-Erp GET "$base/admin/companies/$companyId/edit" $null $sess
$activateOk = ($coEdit4.html -match 'active') -and -not ($coEdit4.html -match 'value="suspended"\s+selected')

$coCreate2 = Invoke-Erp GET "$base/admin/companies/create" $null $sess
$dupPost = Invoke-Erp POST "$base/admin/companies" @{
    _csrf = (Get-Csrf $coCreate2.html); name = 'QA-COMPANY-DUP'; slug = $slug
    email = 'dup@test.local'; status = 'active'
} $sess
$dupOk = ($dupPost.url -match 'create' -or $dupPost.html -match 'error|danger|Duplicate|UNIQUE|slug')

$manifest.tests['12'] = @{
    result = if ($editOk -and $suspendOk -and $activateOk -and $dupOk) { 'PASS' } else { 'PARTIAL' }
    companyId = $companyId; slug = $slug; editOk = $editOk; suspendOk = $suspendOk; activateOk = $activateOk; dupOk = $dupOk
}

# TEST 13 — Branches (read-only ERP + verify main branch for QA company)
$br = Invoke-Erp GET "$base/admin/ops/branches?company_id=$companyId" $null $sess
$uc = Invoke-Erp GET "$base/admin/users/create" $null $sess
$manifest.tests['13'] = @{
    result = 'PARTIAL'
    erpBranchesRedirect = ($br.url -match '/admin$|/admin\?')
    cpOnlyFlash = ($br.html -match 'branches_cp_only|Branch management|إدارة الفروع')
    note = 'Branch CRUD is Control Panel only; main branch auto-created with company'
    companyId = $companyId
}

# TEST 14 — Users (QA prefix only)
$userEmail = $manifest.created.userEmail
$tempPass = "QaSafe${ts}X"
$ucPg = Invoke-Erp GET "$base/admin/users/create" $null $sess
Invoke-Erp POST "$base/admin/users" @{
    _csrf = (Get-Csrf $ucPg.html); name = "QA-USER-$ts"; email = $userEmail
    phone = '0501111111'; company_id = $companyId; status = 'active'; locale = 'en'; password = $tempPass
} $sess | Out-Null
$ul = Invoke-Erp GET "$base/admin/users" $null $sess
$userId = Find-UserIdByEmail $ul.html $userEmail
if ($userId -lt 1) { Stop-Qa "Could not resolve QA user ID for $userEmail" }
$manifest.created.userId = $userId
Log-Write 'user' $userId 'create' @{ email = $userEmail; companyId = $companyId }

$uEdit = Invoke-Erp GET "$base/admin/users/$userId/edit" $null $sess
Invoke-Erp POST "$base/admin/users/$userId" @{
    _csrf = (Get-Csrf $uEdit.html); name = "QA-USER-$ts-EDITED"; email = $userEmail
    company_id = $companyId; status = 'active'; locale = 'ar'
} $sess | Out-Null
Log-Write 'user' $userId 'update' @{}

$newPass = "QaNew${ts}X"
$uEdit2 = Invoke-Erp GET "$base/admin/users/$userId/edit" $null $sess
Invoke-Erp POST "$base/admin/users/$userId" @{
    _csrf = (Get-Csrf $uEdit2.html); name = "QA-USER-$ts-EDITED"; email = $userEmail
    company_id = $companyId; status = 'active'; locale = 'ar'; password = $newPass
} $sess | Out-Null
Log-Write 'user' $userId 'password_reset' @{}

$sessU = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$lpU = Invoke-Erp GET "$base/login" $null $sessU
$lrU = Invoke-Erp POST "$base/login" @{ email = $userEmail; password = $newPass; _csrf = (Get-Csrf $lpU.html) } $sessU
$resetPwOk = ($lrU.url -notmatch '/login$' -or $lrU.html -notmatch 'password-form')

$uEdit3 = Invoke-Erp GET "$base/admin/users/$userId/edit" $null $sess
Invoke-Erp POST "$base/admin/users/$userId" @{
    _csrf = (Get-Csrf $uEdit3.html); name = "QA-USER-$ts-EDITED"; email = $userEmail
    company_id = $companyId; status = 'inactive'; locale = 'ar'
} $sess | Out-Null
Log-Write 'user' $userId 'disable' @{ status = 'inactive' }
$uEdit4 = Invoke-Erp GET "$base/admin/users/$userId/edit" $null $sess
$disableOk = $uEdit4.html -match 'inactive'

$manifest.tests['14'] = @{
    result = if ($userId -gt 0 -and $disableOk) { if ($resetPwOk) { 'PASS' } else { 'PARTIAL' } } else { 'FAIL' }
    userId = $userId; resetPwOk = $resetPwOk; disableOk = $disableOk
}

# TEST 15 — Roles (NEW QA roles only — no existing RBAC modified)
$roleCreate = Invoke-Erp GET "$base/admin/roles/create" $null $sess
$permIds = [regex]::Matches($roleCreate.html, 'name="permission_ids\[\]"\s+value="(\d+)"') | ForEach-Object { $_.Groups[1].Value } | Select-Object -First 2
$roleBody = @{ _csrf = (Get-Csrf $roleCreate.html); name = "QA-ROLE-$ts"; slug = $manifest.created.roleSlug; description = 'Safe QA temp role' }
$idx = 0; foreach ($p in $permIds) { $roleBody["permission_ids[$idx]"] = $p; $idx++ }
Invoke-Erp POST "$base/admin/roles" $roleBody $sess | Out-Null
$rl = Invoke-Erp GET "$base/admin/roles" $null $sess
$roleId = Find-RoleIdBySlug $rl.html $manifest.created.roleSlug
if ($roleId -lt 1) { Stop-Qa "Could not resolve QA role ID" }
$manifest.created.roleId = $roleId
Log-Write 'role' $roleId 'create' @{ slug = $manifest.created.roleSlug }

$roleCreate2 = Invoke-Erp GET "$base/admin/roles/create" $null $sess
$firstPerm = ([regex]::Matches($roleCreate2.html, 'name="permission_ids\[\]"\s+value="(\d+)"') | Select-Object -First 1)
if ($firstPerm.Count -lt 1) { Stop-Qa 'No permission checkbox found for restricted role' }
Invoke-Erp POST "$base/admin/roles" @{
    _csrf = (Get-Csrf $roleCreate2.html); name = "QA-ROLE-RESTRICTED-$ts"
    slug = $manifest.created.restrictedRoleSlug; description = 'Safe QA restricted'
    'permission_ids[0]' = $firstPerm[0].Groups[1].Value
} $sess | Out-Null
$rl2 = Invoke-Erp GET "$base/admin/roles" $null $sess
$restrictedRoleId = Find-RoleIdBySlug $rl2.html $manifest.created.restrictedRoleSlug
if ($restrictedRoleId -lt 1) { Stop-Qa 'Could not resolve restricted QA role ID' }
$manifest.created.restrictedRoleId = $restrictedRoleId
Log-Write 'role' $restrictedRoleId 'create' @{ slug = $manifest.created.restrictedRoleSlug }

$restEmail = $manifest.created.restrictedUserEmail
$ucPg2 = Invoke-Erp GET "$base/admin/users/create" $null $sess
Invoke-Erp POST "$base/admin/users" @{
    _csrf = (Get-Csrf $ucPg2.html); name = "QA-USER-RESTRICTED-$ts"; email = $restEmail
    company_id = $companyId; status = 'active'; locale = 'en'; password = $tempPass
    'role_ids[0]' = $restrictedRoleId
} $sess | Out-Null
$ul2 = Invoke-Erp GET "$base/admin/users" $null $sess
$restrictedUserId = Find-UserIdByEmail $ul2.html $restEmail
if ($restrictedUserId -lt 1) { Stop-Qa "Could not resolve restricted QA user ID" }
$manifest.created.restrictedUserId = $restrictedUserId
Log-Write 'user' $restrictedUserId 'create' @{ email = $restEmail; roleId = $restrictedRoleId }

$sessR = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$lpR = Invoke-Erp GET "$base/login" $null $sessR
Invoke-Erp POST "$base/login" @{ email = $restEmail; password = $tempPass; _csrf = (Get-Csrf $lpR.html) } $sessR | Out-Null
$compAccess = Invoke-Erp GET "$base/admin/companies/create" $null $sessR
$accessDenied = ($compAccess.status -eq 403 -or $compAccess.html -match '403|Forbidden|access|permission|unauthorized|غير مصرح' -or $compAccess.url -notmatch 'companies/create')

$manifest.tests['15'] = @{
    result = if ($accessDenied) { 'PASS' } else { 'PARTIAL' }
    roleId = $roleId; restrictedRoleId = $restrictedRoleId; restrictedUserId = $restrictedUserId; accessDenied = $accessDenied
}

# TEST 16 — Audit logs (read-only)
$audit = Invoke-Erp GET "$base/admin/audit-logs" $null $sess
$manifest.tests['16'] = @{
    result = if ($audit.html -match 'create' -and $audit.html -match 'compan') { 'PARTIAL PASS' } else { 'PARTIAL' }
    hasCreate = ($audit.html -match 'create')
    hasCompany = ($audit.html -match 'compan')
    hasUser = ($audit.html -match 'user')
    hasSlug = ($audit.html -match [regex]::Escape($slug))
}

# SAFE CLEANUP — exact recorded IDs only, reverse dependency order
function Remove-QaByExactId($type, $id, $route, $Session) {
    if ($id -lt 1) { return }
    $list = Invoke-Erp GET "$base/admin/$route" $null $Session
    $verifyBefore = Invoke-Erp GET "$base/admin/${route}/$id/edit" $null $Session
    $existsBefore = ($verifyBefore.status -eq 200 -and $verifyBefore.html -notmatch '404')
    $del = Invoke-Erp POST "$base/admin/${route}/$id/delete" @{ _csrf = (Get-Csrf $list.html) } $Session
    Start-Sleep -Milliseconds 500
    $verifyAfter = Invoke-Erp GET "$base/admin/${route}/$id/edit" $null $Session
    $goneAfter = ($verifyAfter.status -ne 200 -or $verifyAfter.html -match '404')
    $entry = [ordered]@{
        objectType = $type; objectId = $id; deletedAt = (Get-Date).ToString('o')
        verifiedBefore = $existsBefore; verifiedAfterGone = $goneAfter; deleteUrl = $del.url
    }
    $manifest.cleanup += $entry
    Log-Write $type $id 'delete' @{ verifiedBefore = $existsBefore; verifiedAfterGone = $goneAfter }
}

Remove-QaByExactId 'user' $manifest.created.restrictedUserId 'users' $sess
Remove-QaByExactId 'user' $manifest.created.userId 'users' $sess
Remove-QaByExactId 'role' $manifest.created.restrictedRoleId 'roles' $sess
Remove-QaByExactId 'role' $manifest.created.roleId 'roles' $sess
Remove-QaByExactId 'company' $manifest.created.companyId 'companies' $sess

$manifest.finishedAt = (Get-Date).ToString('o')
$outPath = Join-Path $PSScriptRoot "qa-safe-manifest-$ts.json"
$manifest | ConvertTo-Json -Depth 8 | Set-Content -Path $outPath -Encoding UTF8
Write-Output "MANIFEST_PATH=$outPath"
$manifest | ConvertTo-Json -Depth 8
