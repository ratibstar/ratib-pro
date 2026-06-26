# Safe QA v2 — Regression for Issues 14–17 (password, restricted user, monitoring pages)
param([string]$Site = 'https://rateb.sa')

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

function Test-CompanyPortalLogin {
    param([string]$Email, [string]$Password)
    $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $lp = Invoke-WebRequest -Uri "$base/login" -WebSession $s -UseBasicParsing
    $lr = Invoke-WebRequest -Uri "$base/login" -Method POST -WebSession $s -Body @{
        email = $Email; password = $Password; _csrf = (Get-Csrf $lp.Content)
    } -UseBasicParsing -MaximumRedirection 10
    $uri = [string]$lr.BaseResponse.ResponseUri
    $ok = ($uri -match '/site/portal') -and ($lr.Content -notmatch 'password-form')
    return @{ ok = $ok; uri = $uri; status = $lr.StatusCode }
}

function Invoke-Resolve {
    param([string]$Type, [string]$Slug = '', [string]$Email = '', [int]$CompanyId = 0)
    $r = Invoke-QaManifestResolve -Site $Site -WebSession $sess -Type $Type -Slug $Slug -Email $Email -CompanyId $CompanyId
    if (-not $r.ok) { Stop-SafeQaSession -Manifest $manifest -Reason "Resolver failed type=$Type error=$($r.error)" }
    return [int]$r.id
}

function Save-ManifestNow { Save-SafeQaManifest -Manifest $manifest -Path $manifestPath }

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
    } catch { $gone = $true }
    if (-not $gone) {
        Stop-SafeQaSession -Manifest $manifest -Reason "Delete verification failed: $Type id=$Id"
    }
    foreach ($obj in $manifest.objects) {
        if ([string]$obj.type -eq $Type -and [int]$obj.id -eq $Id) {
            $obj.deletedAt = (Get-Date).ToString('o')
        }
    }
    Register-SafeQaWrite -Manifest $manifest -Type $Type -Id $Id -Action 'delete_verified'
    Save-ManifestNow
}

$report = [ordered]@{
    startedAt = (Get-Date).ToString('o')
    issues = @{}
    regression = @{}
}

# --- Super-admin login (single attempt — avoid rate limit) ---
$sess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$lp = Invoke-WebRequest -Uri "$base/login" -WebSession $sess -UseBasicParsing
$lr = Invoke-WebRequest -Uri "$base/login" -Method POST -WebSession $sess -Body @{
    email = $adminEmail; password = $adminPw; _csrf = (Get-Csrf $lp.Content)
} -UseBasicParsing -MaximumRedirection 10
$adminLoginOk = ($lr.BaseResponse.ResponseUri -match '/admin') -or ($lr.Content -match 'rateb-widget')
if (-not $adminLoginOk) {
    if ($lr.Content -match 'too_many|محاولات') {
        throw 'SAFE QA STOP: super-admin rate-limited. Wait 15 minutes and retry.'
    }
    throw 'SAFE QA STOP: super-admin login failed.'
}
$report.regression['super_admin_login'] = @{ ok = $true; uri = [string]$lr.BaseResponse.ResponseUri }

# --- Issue 1: monitoring pages ---
$monPages = @(
    @{ key = 'login_activity'; url = "$base/admin/login-activity" }
    @{ key = 'queue_monitor'; url = "$base/admin/queue-monitor" }
    @{ key = 'automation_health'; url = "$base/admin/automation-health" }
)
$issue1 = @{}
foreach ($p in $monPages) {
    try {
        $resp = Invoke-WebRequest -Uri $p.url -WebSession $sess -UseBasicParsing
        $missingClass = [regex]::Match($resp.Content, 'Class &quot;([^&]+)&quot; not found')
        $issue1[$p.key] = @{
            status = $resp.StatusCode
            pass = ($resp.StatusCode -eq 200) -and (-not $missingClass.Success)
            missingClass = if ($missingClass.Success) { $missingClass.Groups[1].Value } else { $null }
        }
    } catch {
        $code = if ($_.Exception.Response) { [int]$_.Exception.Response.StatusCode } else { 0 }
        $issue1[$p.key] = @{ status = $code; pass = $false; error = $_.Exception.Message }
    }
}
$report.issues['1_monitoring'] = $issue1

# --- Safe QA manifest session for Issues 2 & 3 ---
$session = New-SafeQaSession -Site $Site
$manifest = @{}
foreach ($k in $session.Manifest.Keys) { $manifest[$k] = $session.Manifest[$k] }
$manifestPath = $session.Path

$coSlug = "QA-COMPANY-$ts"
$coCreate = Invoke-WebRequest -Uri "$base/admin/companies/create" -WebSession $sess -UseBasicParsing
Invoke-WebRequest -Uri "$base/admin/companies" -Method POST -WebSession $sess -Body @{
    _csrf = (Get-Csrf $coCreate.Content); name = $coSlug; slug = $coSlug
    email = "qa-co-$ts@test.local"; phone = '0500000000'; status = 'active'
    plan_id = '1'; user_limit = '10'; branch_limit = '5'; storage_limit_mb = '512'
} -UseBasicParsing -MaximumRedirection 10 | Out-Null
Start-Sleep -Seconds 1
$companyId = Invoke-Resolve -Type 'company' -Slug $coSlug
Add-SafeQaObject -Manifest $manifest -Type 'company' -Id $companyId -Slug $coSlug | Out-Null
Register-SafeQaWrite -Manifest $manifest -Type 'company' -Id $companyId -Action 'create'
Save-ManifestNow

# Ensure subscription auto-provisioned (Blocker B — must exist without manual create)
Start-Sleep -Seconds 1
$subResolve = Invoke-QaManifestResolve -Site $Site -WebSession $sess -Type 'subscription' -CompanyId $companyId
if (-not $subResolve.ok) {
    Stop-SafeQaSession -Manifest $manifest -Reason "Blocker B FAIL: no subscription for QA company id=$companyId error=$($subResolve.error)"
}
$subId = [int]$subResolve.id
Add-SafeQaObject -Manifest $manifest -Type 'subscription' -Id $subId -ParentCompanyId $companyId | Out-Null
Register-SafeQaWrite -Manifest $manifest -Type 'subscription' -Id $subId -Action 'create_auto_provision'
Save-ManifestNow
$report.regression['subscription_auto_provision'] = @{ ok = $true; subscriptionId = $subId; status = $subResolve.meta.status }

# --- Issue 2: password reset ---
$userEmail = "QA-USER-$ts@test.local"
$tempPass = "QaSafe${ts}X"
$newPass = "QaNew${ts}X"

$uc = Invoke-WebRequest -Uri "$base/admin/users/create" -WebSession $sess -UseBasicParsing
Invoke-WebRequest -Uri "$base/admin/users" -Method POST -WebSession $sess -Body @{
    _csrf = (Get-Csrf $uc.Content); name = "QA-USER-$ts"; email = $userEmail
    phone = '0501111111'; company_id = $companyId; status = 'active'; locale = 'en'; password = $tempPass
} -UseBasicParsing -MaximumRedirection 10 | Out-Null
Start-Sleep -Seconds 1
$userId = Invoke-Resolve -Type 'user' -Email $userEmail
Add-SafeQaObject -Manifest $manifest -Type 'user' -Id $userId -Email $userEmail -ParentCompanyId $companyId | Out-Null
Register-SafeQaWrite -Manifest $manifest -Type 'user' -Id $userId -Action 'create'
Save-ManifestNow

$loginBefore = Test-CompanyPortalLogin -Email $userEmail -Password $tempPass
$uEdit = Invoke-WebRequest -Uri "$base/admin/users/$userId/edit" -WebSession $sess -UseBasicParsing
Invoke-WebRequest -Uri "$base/admin/users/$userId" -Method POST -WebSession $sess -Body @{
    _csrf = (Get-Csrf $uEdit.Content); name = "QA-USER-$ts"; email = $userEmail
    company_id = $companyId; status = 'active'; locale = 'en'; password = $newPass
} -UseBasicParsing -MaximumRedirection 10 | Out-Null
Start-Sleep -Milliseconds 500
$loginAfter = Test-CompanyPortalLogin -Email $userEmail -Password $newPass

$report.issues['2_password_reset'] = @{
    loginWithInitialPassword = $loginBefore
    loginWithResetPassword = $loginAfter
    pass = ($loginBefore.ok -and $loginAfter.ok)
    rootCauseNote = 'Company portal login requires rateb_subscriptions row (PlanLimitService::companyAccessAllowed → hasValidSubscription).'
}

# --- Issue 3: restricted user ---
$roleSlug = "QA-ROLE-RESTRICTED-$ts"
$rc = Invoke-WebRequest -Uri "$base/admin/roles/create" -WebSession $sess -UseBasicParsing
$perm = ([regex]::Matches($rc.Content, 'name="permission_ids\[\]"\s+value="(\d+)"') | Select-Object -First 1)
if (-not $perm) { Stop-SafeQaSession -Manifest $manifest -Reason 'No permissions on role form' }
Invoke-WebRequest -Uri "$base/admin/roles" -Method POST -WebSession $sess -Body @{
    _csrf = (Get-Csrf $rc.Content); name = $roleSlug; slug = $roleSlug; description = 'restricted QA'
    'permission_ids[]' = $perm.Groups[1].Value
} -UseBasicParsing -MaximumRedirection 10 | Out-Null
Start-Sleep -Seconds 1
$roleId = Invoke-Resolve -Type 'role' -Slug $roleSlug
Add-SafeQaObject -Manifest $manifest -Type 'role' -Id $roleId -Slug $roleSlug | Out-Null

$restEmail = "QA-USER-RESTRICTED-$ts@test.local"
$uc2 = Invoke-WebRequest -Uri "$base/admin/users/create" -WebSession $sess -UseBasicParsing
Invoke-WebRequest -Uri "$base/admin/users" -Method POST -WebSession $sess -Body @{
    _csrf = (Get-Csrf $uc2.Content); name = "QA-USER-RESTRICTED-$ts"; email = $restEmail
    company_id = $companyId; status = 'active'; locale = 'en'; password = $tempPass
    'role_ids[]' = $roleId
} -UseBasicParsing -MaximumRedirection 10 | Out-Null
Start-Sleep -Seconds 1
$restUserId = Invoke-Resolve -Type 'user' -Email $restEmail
Add-SafeQaObject -Manifest $manifest -Type 'user' -Id $restUserId -Email $restEmail -ParentCompanyId $companyId | Out-Null

$sessR = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$lpR = Invoke-WebRequest -Uri "$base/login" -WebSession $sessR -UseBasicParsing
$lrR = Invoke-WebRequest -Uri "$base/login" -Method POST -WebSession $sessR -Body @{
    email = $restEmail; password = $tempPass; remember = '1'; _csrf = (Get-Csrf $lpR.Content)
} -UseBasicParsing -MaximumRedirection 10
$rmCookie = $sessR.Cookies.GetCookies([Uri]"$base/") | Where-Object { $_.Name -match 'remember|rateb' }
$dashR = Invoke-WebRequest -Uri "$base/site/portal" -WebSession $sessR -UseBasicParsing -ErrorAction SilentlyContinue
$restLogin = @{ ok = ([string]$lrR.BaseResponse.ResponseUri -match '/site/portal'); uri = [string]$lrR.BaseResponse.ResponseUri; status = $lrR.StatusCode }
$portalOk = ($dashR.StatusCode -eq 200) -and ($dashR.Content -notmatch 'password-form')
$denied = Invoke-WebRequest -Uri "$base/admin/companies/create" -WebSession $sessR -UseBasicParsing -ErrorAction SilentlyContinue
$accessDenied = ($denied.StatusCode -eq 403) -or ($denied.Content -match '403|Forbidden|access|permission|unauthorized|غير مصرح')

$report.issues['3_restricted_user'] = @{
    login = $restLogin
    portalDashboard = @{ ok = $portalOk; status = $dashR.StatusCode; uri = [string]$dashR.BaseResponse.ResponseUri }
    adminCompaniesCreateDenied = $accessDenied
    pass = ($restLogin.ok -and $portalOk -and $accessDenied)
}

# --- Additional regression probes (read-only) ---
$audit = Invoke-WebRequest -Uri "$base/admin/audit-logs" -WebSession $sess -UseBasicParsing
$notif = Invoke-WebRequest -Uri "$base/admin/notifications" -WebSession $sess -UseBasicParsing
$dash = Invoke-WebRequest -Uri "$base/admin" -WebSession $sess -UseBasicParsing
$report.regression['audit_logs'] = @{ ok = ($audit.StatusCode -eq 200); status = $audit.StatusCode }
$report.regression['notifications'] = @{ ok = ($notif.StatusCode -eq 200); status = $notif.StatusCode }
$report.regression['dashboard'] = @{ ok = ($dash.Content -match 'rateb-widget'); status = $dash.StatusCode }

# Remember Me cookie from restricted-user login (remember=1 on POST above)
$report.regression['remember_me'] = @{
    ok = ($null -ne $rmCookie -and @($rmCookie).Count -gt 0)
    cookies = @($rmCookie | ForEach-Object { $_.Name })
}

# Logout (company portal session — does not burn admin rate limit)
$portalLo = Invoke-WebRequest -Uri "$base/site/portal/logout" -WebSession $sessR -UseBasicParsing -MaximumRedirection 10
$report.regression['logout'] = @{
    ok = ($portalLo.BaseResponse.ResponseUri -match '/login' -or $portalLo.Content -match 'password-form')
    uri = [string]$portalLo.BaseResponse.ResponseUri
}

# --- Tests 18–22 (read-only, same admin session) ---
$testRoutes = [ordered]@{
    '18' = @{ name = 'queue-monitor'; url = "$base/admin/queue-monitor" }
    '19' = @{ name = 'automation-health'; url = "$base/admin/automation-health" }
    '20' = @{ name = 'reports'; url = "$base/admin/reports" }
    '21' = @{ name = 'executive-dashboard'; url = "$base/admin/executive-dashboard" }
    '22' = @{ name = 'settings-read'; url = "$base/admin/settings" }
}
$report.tests = @{}
foreach ($num in $testRoutes.Keys) {
    $tr = $testRoutes[$num]
    try {
        $resp = Invoke-WebRequest -Uri $tr.url -WebSession $sess -UseBasicParsing
        $isLogin = ($resp.Content -match 'password-form')
        $missing = [regex]::Match($resp.Content, 'Class &quot;([^&]+)&quot; not found')
        $report.tests[$num] = @{
            result = if ($resp.StatusCode -eq 200 -and -not $isLogin -and -not $missing.Success) { 'PASS' } else { 'PARTIAL' }
            url = $tr.url; status = $resp.StatusCode; name = $tr.name
            redirectedToLogin = $isLogin; missingClass = if ($missing.Success) { $missing.Groups[1].Value } else { $null }
        }
    } catch {
        $code = if ($_.Exception.Response) { [int]$_.Exception.Response.StatusCode } else { 0 }
        $report.tests[$num] = @{ result = 'FAIL'; url = $tr.url; status = $code; error = $_.Exception.Message; name = $tr.name }
    }
}

# --- Cleanup: users → roles → subscription → company ---
Remove-ManifestObject -Type 'user' -Id $restUserId -Route 'users'
Remove-ManifestObject -Type 'user' -Id $userId -Route 'users'
Remove-ManifestObject -Type 'role' -Id $roleId -Route 'roles'
if ($subId -gt 0) { Remove-ManifestObject -Type 'subscription' -Id $subId -Route 'subscriptions' }
Remove-ManifestObject -Type 'company' -Id $companyId -Route 'companies'

$verifyCo = Invoke-QaManifestResolve -Site $Site -WebSession $sess -Type 'company' -Slug $coSlug
$report.cleanup = @{
    companyGone = (-not $verifyCo.ok)
    manifestPath = $manifestPath
    objectsDeleted = ($manifest.objects | Where-Object { $null -ne $_.deletedAt }).Count
}

$manifest.finishedAt = (Get-Date).ToString('o')
Save-ManifestNow
$report.finishedAt = (Get-Date).ToString('o')
$report | ConvertTo-Json -Depth 10
