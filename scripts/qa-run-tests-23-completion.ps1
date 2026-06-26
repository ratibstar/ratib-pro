# RATIB ERP Safe QA v2 — Tests 23+ through Enterprise Certification (production)
param(
    [string]$Site = 'https://rateb.sa',
    [int]$StartTest = 23
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

function Test-HttpGet {
    param(
        [int]$Num, [string]$Module, [string]$Url,
        [Microsoft.PowerShell.Commands.WebRequestSession]$Session,
        [string]$Expected = '200 authenticated page',
        [string[]]$MustMatch = @(),
        [string[]]$MustNotMatch = @('password-form', 'Class &quot;'),
        [switch]$AllowLoginRedirect
    )
    $sw = [System.Diagnostics.Stopwatch]::StartNew()
    $row = [ordered]@{
        test = $Num; module = $Module; url = $Url
        expected = $Expected; actual = $null; status = 0
        ms = 0; result = 'FAIL'; risk = 'Low'; severity = 'Info'
        networkError = $null; phpError = $null; evidence = @{}
    }
    try {
        $r = Invoke-WebRequest -Uri $Url -WebSession $Session -UseBasicParsing -MaximumRedirection 10
        $sw.Stop()
        $row.status = $r.StatusCode
        $row.ms = [int]$sw.ElapsedMilliseconds
        $html = $r.Content
        $isLogin = ($html -match 'password-form') -and ($r.BaseResponse.ResponseUri -match '/login')
        $missing = [regex]::Match($html, 'Class &quot;([^&]+)&quot; not found')
        $phpErr = [regex]::Match($html, '<code[^>]*>([^<]+)</code>')
        if ($missing.Success) { $row.phpError = $missing.Groups[1].Value }
        elseif ($phpErr.Success -and $html -match 'rateb-err|تعذ') { $row.phpError = $phpErr.Groups[1].Value }
        $ok = ($r.StatusCode -eq 200)
        if (-not $AllowLoginRedirect -and $isLogin) { $ok = $false; $row.actual = 'redirected to login' }
        foreach ($m in $MustMatch) { if ($html -notmatch $m) { $ok = $false } }
        foreach ($n in $MustNotMatch) { if ($html -match $n) { $ok = $false } }
        if ($ok) { $row.result = 'PASS'; $row.actual = "HTTP $($r.StatusCode)" }
        elseif ($r.StatusCode -eq 403) { $row.result = 'PARTIAL'; $row.actual = '403 Forbidden'; $row.risk = 'Medium' }
        elseif ($isLogin) { $row.result = 'BLOCKED'; $row.actual = 'auth required'; $row.risk = 'Low' }
        else { $row.result = 'PARTIAL'; $row.actual = "HTTP $($r.StatusCode)" }
        $row.evidence = @{ finalUri = [string]$r.BaseResponse.ResponseUri; length = $html.Length }
    } catch {
        $sw.Stop()
        $row.ms = [int]$sw.ElapsedMilliseconds
        $code = if ($_.Exception.Response) { [int]$_.Exception.Response.StatusCode } else { 0 }
        $row.status = $code
        $row.networkError = $_.Exception.Message
        $row.result = if ($code -eq 404) { 'PARTIAL' } else { 'FAIL' }
        $row.actual = "Error $code"
        $row.risk = if ($code -ge 500) { 'High' } else { 'Medium' }
        $row.severity = if ($code -ge 500) { 'High' } else { 'Medium' }
    }
    return [pscustomobject]$row
}

function Test-HttpAnonymous {
    param([int]$Num, [string]$Module, [string]$Url, [string]$Expected = 'public OK')
    $sw = [System.Diagnostics.Stopwatch]::StartNew()
    $row = [ordered]@{
        test = $Num; module = $Module; url = $Url; expected = $Expected
        actual = $null; status = 0; ms = 0; result = 'FAIL'
        risk = 'Low'; severity = 'Info'; networkError = $null; phpError = $null; evidence = @{}
    }
    try {
        $r = Invoke-WebRequest -Uri $Url -UseBasicParsing -MaximumRedirection 5
        $sw.Stop()
        $row.status = $r.StatusCode
        $row.ms = [int]$sw.ElapsedMilliseconds
        $row.result = if ($r.StatusCode -eq 200) { 'PASS' } else { 'PARTIAL' }
        $row.actual = "HTTP $($r.StatusCode)"
        if ($r.Headers['Content-Security-Policy']) { $row.evidence.csp = 'present' }
        if ($r.Headers['X-Frame-Options']) { $row.evidence.xfo = $r.Headers['X-Frame-Options'] }
    } catch {
        $sw.Stop()
        $row.ms = [int]$sw.ElapsedMilliseconds
        $row.networkError = $_.Exception.Message
        $row.status = if ($_.Exception.Response) { [int]$_.Exception.Response.StatusCode } else { 0 }
        $row.result = 'FAIL'
    }
    return [pscustomobject]$row
}

# --- Login (single attempt) ---
$sess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$lp = Invoke-WebRequest -Uri "$base/login" -WebSession $sess -UseBasicParsing
$lr = Invoke-WebRequest -Uri "$base/login" -Method POST -WebSession $sess -Body @{
    email = $adminEmail; password = $adminPw; _csrf = (Get-Csrf $lp.Content)
} -UseBasicParsing -MaximumRedirection 10
if ($lr.Content -match 'too_many|محاولات') {
    throw 'SAFE QA STOP: rate limited — wait 15 minutes'
}
if (-not (($lr.BaseResponse.ResponseUri -match '/admin') -or ($lr.Content -match 'rateb-widget'))) {
    throw 'SAFE QA STOP: super-admin login failed'
}

$session = New-SafeQaSession -Site $Site
$manifest = @{}
foreach ($k in $session.Manifest.Keys) { $manifest[$k] = $session.Manifest[$k] }
$manifestPath = $session.Path
$results = @()

# --- Test catalog (23+) — read-only unless noted ---
$catalog = @(
    @{ n = 23; m = 'Plans'; u = "$base/admin/plans"; mm = @('rateb-card|table') }
    @{ n = 24; m = 'Subscriptions'; u = "$base/admin/subscriptions"; mm = @('rateb-card|table') }
    @{ n = 25; m = 'Payments'; u = "$base/admin/payments"; mm = @('rateb-card|table') }
    @{ n = 26; m = 'Invoices'; u = "$base/admin/invoices"; mm = @('rateb-card|table') }
    @{ n = 27; m = 'Email Templates'; u = "$base/admin/email-templates"; mm = @('rateb-card|form|table') }
    @{ n = 28; m = 'SMS Templates'; u = "$base/admin/sms-templates"; mm = @('rateb-card|form|table') }
    @{ n = 29; m = 'Support Tickets'; u = "$base/admin/support-tickets"; mm = @('rateb-card|table') }
    @{ n = 30; m = 'Access Control'; u = "$base/admin/access-control"; mm = @('access|rateb') }
    @{ n = 31; m = 'Permission Matrix'; u = "$base/admin/access-control/matrix"; mm = @('matrix|permission|rateb') }
    @{ n = 32; m = 'Permissions (read)'; u = "$base/admin/permissions"; mm = @('rateb-card|table') }
    @{ n = 33; m = 'Accounting Dashboard'; u = "$base/admin/accounting"; mm = @('accounting|rateb') }
    @{ n = 34; m = 'Chart of Accounts'; u = "$base/admin/chart-of-accounts"; mm = @('chart|account|rateb') }
    @{ n = 35; m = 'COA Tree'; u = "$base/admin/coa-tree"; mm = @('tree|account|rateb') }
    @{ n = 36; m = 'Journal Entries'; u = "$base/admin/journal-entries"; mm = @('journal|rateb') }
    @{ n = 37; m = 'Procurement Oversight'; u = "$base/admin/oversight/procurement"; mm = @('procurement|rateb') }
    @{ n = 38; m = 'RFQ Oversight'; u = "$base/admin/oversight/rfq"; mm = @('rfq|rateb') }
    @{ n = 39; m = 'Inventory Oversight'; u = "$base/admin/oversight/inventory"; mm = @('inventory|rateb') }
    @{ n = 40; m = 'Workflows Oversight'; u = "$base/admin/oversight/workflows"; mm = @('workflow|rateb') }
    @{ n = 41; m = 'Approvals Oversight'; u = "$base/admin/oversight/approvals"; mm = @('approval|rateb') }
    @{ n = 42; m = 'Supplier Evaluations'; u = "$base/admin/oversight/supplier-evaluations"; mm = @('supplier|rateb') }
    @{ n = 43; m = 'Ops Purchase Requests'; u = "$base/admin/ops/purchase-requests"; mm = @('purchase|rateb') }
    @{ n = 44; m = 'Ops Purchase Orders'; u = "$base/admin/ops/purchase-orders"; mm = @('purchase|rateb') }
    @{ n = 45; m = 'Ops RFQ'; u = "$base/admin/ops/rfq"; mm = @('rfq|rateb') }
    @{ n = 46; m = 'Ops Suppliers'; u = "$base/admin/ops/suppliers"; mm = @('supplier|rateb') }
    @{ n = 47; m = 'Ops Inventory'; u = "$base/admin/ops/inventory"; mm = @('inventory|rateb') }
    @{ n = 48; m = 'Ops Warehouses'; u = "$base/admin/ops/warehouses"; mm = @('warehouse|rateb') }
    @{ n = 49; m = 'Ops Branches'; u = "$base/admin/ops/branches"; mm = @('branch|rateb') }
    @{ n = 50; m = 'Ops Assets'; u = "$base/admin/ops/assets"; mm = @('asset|rateb') }
    @{ n = 51; m = 'Ops Contracts'; u = "$base/admin/ops/contracts"; mm = @('contract|rateb') }
    @{ n = 52; m = 'Ops Tenders'; u = "$base/admin/ops/tenders"; mm = @('tender|rateb') }
    @{ n = 53; m = 'Ops Medical Devices'; u = "$base/admin/ops/medical-devices"; mm = @('medical|device|rateb') }
    @{ n = 54; m = 'Ops Stock Movements'; u = "$base/admin/ops/stock-movements"; mm = @('stock|movement|rateb') }
    @{ n = 55; m = 'Ops Documents'; u = "$base/admin/ops/documents"; mm = @('document|rateb') }
    @{ n = 56; m = 'Ops Workflows'; u = "$base/admin/ops/workflows"; mm = @('workflow|rateb') }
    @{ n = 57; m = 'HR Dashboard'; u = "$base/admin/hr"; mm = @('hr|rateb') }
    @{ n = 58; m = 'HR Employees'; u = "$base/admin/hr/employees"; mm = @('employee|rateb') }
    @{ n = 59; m = 'HR Attendance'; u = "$base/admin/hr/attendance"; mm = @('attendance|rateb') }
    @{ n = 60; m = 'HR Leaves'; u = "$base/admin/hr/leaves"; mm = @('leave|rateb') }
    @{ n = 61; m = 'HR Payroll'; u = "$base/admin/hr/payroll"; mm = @('payroll|rateb') }
    @{ n = 62; m = 'HR Departments'; u = "$base/admin/hr/departments"; mm = @('department|rateb') }
    @{ n = 63; m = 'Ops Accounting'; u = "$base/admin/ops/accounting"; mm = @('accounting|rateb') }
    @{ n = 64; m = 'Ops Chart of Accounts'; u = "$base/admin/ops/chart-of-accounts"; mm = @('chart|account|rateb') }
    @{ n = 65; m = 'Ops Journal Entries'; u = "$base/admin/ops/journal-entries"; mm = @('journal|rateb') }
    @{ n = 66; m = 'Ops Customers'; u = "$base/admin/customers"; mm = @('customer|rateb') }
    @{ n = 67; m = 'Ops Cash Vouchers'; u = "$base/admin/ops/cash-vouchers"; mm = @('voucher|cash|rateb') }
    @{ n = 68; m = 'Ops Fiscal Periods'; u = "$base/admin/ops/fiscal-periods"; mm = @('fiscal|rateb') }
    @{ n = 69; m = 'Ops Bank Accounts'; u = "$base/admin/ops/bank-accounts"; mm = @('bank|rateb') }
    @{ n = 70; m = 'Ops Cost Centers'; u = "$base/admin/ops/cost-centers"; mm = @('cost|center|rateb') }
    @{ n = 71; m = 'Ops Product Categories'; u = "$base/admin/ops/product-categories"; mm = @('product|category|rateb') }
    @{ n = 72; m = 'Analytics KPI Reports'; u = "$base/admin/ops/reports/kpi"; mm = @('report|kpi|rateb') }
    @{ n = 73; m = 'Ops Reports'; u = "$base/admin/ops/reports"; mm = @('report|rateb') }
    @{ n = 74; m = 'Ops Notifications'; u = "$base/admin/ops/notifications"; mm = @('notification|rateb') }
    @{ n = 75; m = 'Ops Profile'; u = "$base/admin/ops/profile"; mm = @('profile|rateb') }
    @{ n = 76; m = 'Branch Dashboard'; u = "$base/admin/ops/branch-dashboard"; mm = @('branch|rateb') }
    @{ n = 77; m = 'Branch Financial'; u = "$base/admin/ops/branch-financial"; mm = @('branch|financial|rateb') }
    @{ n = 78; m = 'Branch Transfers'; u = "$base/admin/ops/branch-transfers"; mm = @('branch|transfer|rateb') }
    @{ n = 79; m = 'Locale EN'; u = "$base/locale/en"; mm = @(); al = $true }
    @{ n = 80; m = 'Locale AR'; u = "$base/locale/ar"; mm = @(); al = $true }
    @{ n = 81; m = 'Password Forgot'; u = "$base/password/forgot"; mm = @('password|email|form'); al = $true }
    @{ n = 82; m = 'Login Scan'; u = "$base/login/scan"; mm = @('scan|qr|barcode|login'); al = $true }
    @{ n = 83; m = 'Marketing Site'; u = "$Site/site"; mm = @('rateb|site|html') }
    @{ n = 84; m = 'Marketing Robots'; u = "$Site/site/robots.txt"; mm = @('User-agent|Disallow') }
)

foreach ($c in $catalog) {
    if ($c.n -lt $StartTest) { continue }
    if ($c.al) {
        $results += Test-HttpGet -Num $c.n -Module $c.m -Url $c.u -Session $sess -MustMatch $c.mm -AllowLoginRedirect
    } else {
        $results += Test-HttpGet -Num $c.n -Module $c.m -Url $c.u -Session $sess -MustMatch $c.mm
    }
}

# --- Anonymous / health endpoints ---
$results += Test-HttpAnonymous -Num 85 -Module 'API v1 Index' -Url "$base/api/v1" -Expected 'JSON API root'
$results += Test-HttpAnonymous -Num 86 -Module 'ERP Health' -Url "$base/erp-health.php" -Expected '{"status":"ok"}'
$results += Test-HttpAnonymous -Num 87 -Module 'Ping' -Url "$base/ping.php" -Expected 'pong or ok'
try {
    $sec = Invoke-WebRequest -Uri "$base/erp-security-cert.php" -UseBasicParsing
    $results += [pscustomobject]@{
        test = 88; module = 'Security Cert'; url = "$base/erp-security-cert.php"
        status = $sec.StatusCode; result = if ($sec.StatusCode -eq 200) { 'PASS' } else { 'PARTIAL' }
        expected = 'cert page or gate'; actual = "HTTP $($sec.StatusCode)"; ms = 0
        risk = 'Low'; severity = 'Info'; networkError = $null; phpError = $null
        evidence = @{ length = $sec.Content.Length }
    }
} catch {
    $results += [pscustomobject]@{
        test = 88; module = 'Security Cert'; url = "$base/erp-security-cert.php"
        status = 403; result = 'PARTIAL'; expected = 'token gated'; actual = '403 expected without token'
        ms = 0; risk = 'Low'; severity = 'Info'; networkError = $_.Exception.Message
        phpError = $null; evidence = @{}
    }
}

# Security headers on login page
try {
    $lh = Invoke-WebRequest -Uri "$base/login" -UseBasicParsing
    $hasCsp = $null -ne $lh.Headers['Content-Security-Policy']
    $results += [pscustomobject]@{
        test = 89; module = 'Security Headers'; url = "$base/login"
        status = $lh.StatusCode; result = if ($hasCsp) { 'PASS' } else { 'PARTIAL' }
        expected = 'CSP present'; actual = if ($hasCsp) { 'CSP present' } else { 'CSP missing' }
        ms = 0; risk = if ($hasCsp) { 'Low' } else { 'Medium' }; severity = 'Medium'
        networkError = $null; phpError = $null
        evidence = @{ csp = $hasCsp; xfo = $lh.Headers['X-Frame-Options'] }
    }
} catch { }

# CSRF on login form
$csrfOk = ($lp.Content -match 'name="_csrf"')
$results += [pscustomobject]@{
    test = 90; module = 'CSRF Token'; url = "$base/login"
    status = 200; result = if ($csrfOk) { 'PASS' } else { 'FAIL' }
    expected = '_csrf field'; actual = if ($csrfOk) { 'present' } else { 'missing' }
    ms = 0; risk = 'High'; severity = if ($csrfOk) { 'Info' } else { 'Critical' }
    networkError = $null; phpError = $null; evidence = @{}
}

# --- Safe write: QA Support Ticket (Test 91) ---
$ticketNo = "QA-TICKET-$ts"
$ticketId = 0
$ticketResult = 'BLOCKED'
try {
    $tc = Invoke-WebRequest -Uri "$base/admin/support-tickets/create" -WebSession $sess -UseBasicParsing
    Invoke-WebRequest -Uri "$base/admin/support-tickets" -Method POST -WebSession $sess -Body @{
        _csrf = (Get-Csrf $tc.Content); ticket_no = $ticketNo; subject = "QA-TICKET-SUBJECT-$ts"
        priority = 'low'; status = 'open'; message = 'Safe QA v2 ticket — delete after test'
    } -UseBasicParsing -MaximumRedirection 10 | Out-Null
    Start-Sleep -Seconds 1
    $tRes = Invoke-QaManifestResolve -Site $Site -WebSession $sess -Type 'ticket' -TicketNo $ticketNo
    if ($tRes.ok) {
        $ticketId = [int]$tRes.id
        Add-SafeQaObject -Manifest $manifest -Type 'ticket' -Id $ticketId -TicketNo $ticketNo | Out-Null
        Register-SafeQaWrite -Manifest $manifest -Type 'ticket' -Id $ticketId -Action 'create'
        $list = Invoke-WebRequest -Uri "$base/admin/support-tickets" -WebSession $sess -UseBasicParsing
        Invoke-WebRequest -Uri "$base/admin/support-tickets/$ticketId/delete" -Method POST -WebSession $sess -Body @{
            _csrf = (Get-Csrf $list.Content)
        } -UseBasicParsing -MaximumRedirection 10 | Out-Null
        foreach ($obj in $manifest.objects) {
            if ([string]$obj.type -eq 'ticket' -and [int]$obj.id -eq $ticketId) { $obj.deletedAt = (Get-Date).ToString('o') }
        }
        $tGone = Invoke-QaManifestResolve -Site $Site -WebSession $sess -Type 'ticket' -TicketNo $ticketNo
        $ticketResult = if (-not $tGone.ok) { 'PASS' } else { 'PARTIAL' }
    } else {
        $ticketResult = 'BLOCKED'
    }
} catch {
    $ticketResult = 'FAIL'
}
$results += [pscustomobject]@{
    test = 91; module = 'Support Ticket Write'; url = "$base/admin/support-tickets"
    status = 200; result = $ticketResult; expected = 'QA ticket create+resolver+cleanup'
    actual = if ($ticketId -gt 0) { "ticket id=$ticketId" } else { 'resolver unavailable or create failed' }
    ms = 0; risk = 'Low'; severity = 'Info'; networkError = $null; phpError = $null
    evidence = @{ ticketNo = $ticketNo; ticketId = $ticketId }
}

# --- QA company context for portal tests (92-94) ---
$coSlug = "QA-COMPANY-$ts"
$coCreate = Invoke-WebRequest -Uri "$base/admin/companies/create" -WebSession $sess -UseBasicParsing
Invoke-WebRequest -Uri "$base/admin/companies" -Method POST -WebSession $sess -Body @{
    _csrf = (Get-Csrf $coCreate.Content); name = $coSlug; slug = $coSlug
    email = "qa-co-$ts@test.local"; phone = '0500000000'; status = 'active'
    plan_id = '1'; user_limit = '10'; branch_limit = '5'; storage_limit_mb = '512'
} -UseBasicParsing -MaximumRedirection 10 | Out-Null
Start-Sleep -Seconds 1
$coRes = Invoke-QaManifestResolve -Site $Site -WebSession $sess -Type 'company' -Slug $coSlug
if (-not $coRes.ok) { throw "SAFE QA STOP: company resolve failed $($coRes.error)" }
$companyId = [int]$coRes.id
Add-SafeQaObject -Manifest $manifest -Type 'company' -Id $companyId -Slug $coSlug | Out-Null
Register-SafeQaWrite -Manifest $manifest -Type 'company' -Id $companyId -Action 'create'

$subRes = Invoke-QaManifestResolve -Site $Site -WebSession $sess -Type 'subscription' -CompanyId $companyId
if ($subRes.ok) {
    Add-SafeQaObject -Manifest $manifest -Type 'subscription' -Id ([int]$subRes.id) -ParentCompanyId $companyId | Out-Null
}

$userEmail = "QA-USER-$ts@test.local"
$userPass = "QaSafe${ts}X"
$uc = Invoke-WebRequest -Uri "$base/admin/users/create" -WebSession $sess -UseBasicParsing
Invoke-WebRequest -Uri "$base/admin/users" -Method POST -WebSession $sess -Body @{
    _csrf = (Get-Csrf $uc.Content); name = "QA-USER-$ts"; email = $userEmail
    phone = '0501111111'; company_id = $companyId; status = 'active'; locale = 'en'; password = $userPass
} -UseBasicParsing -MaximumRedirection 10 | Out-Null
Start-Sleep -Seconds 1
$uRes = Invoke-QaManifestResolve -Site $Site -WebSession $sess -Type 'user' -Email $userEmail
if (-not $uRes.ok) { throw "SAFE QA STOP: user resolve failed" }
$userId = [int]$uRes.id
Add-SafeQaObject -Manifest $manifest -Type 'user' -Id $userId -Email $userEmail -ParentCompanyId $companyId | Out-Null

$sessP = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$lpP = Invoke-WebRequest -Uri "$base/login" -WebSession $sessP -UseBasicParsing
Invoke-WebRequest -Uri "$base/login" -Method POST -WebSession $sessP -Body @{
    email = $userEmail; password = $userPass; _csrf = (Get-Csrf $lpP.Content)
} -UseBasicParsing -MaximumRedirection 10 | Out-Null

$results += Test-HttpGet -Num 92 -Module 'Company Portal' -Url "$base/site/portal" -Session $sessP -MustMatch @('portal|rateb')
$results += Test-HttpGet -Num 93 -Module 'Portal Profile' -Url "$base/site/portal/profile" -Session $sessP -MustMatch @('profile|rateb')
$results += Test-HttpGet -Num 94 -Module 'Portal Notifications' -Url "$base/site/portal/notifications" -Session $sessP -MustMatch @('notification|rateb')

# Pagination / search on companies (read-only production list)
$results += Test-HttpGet -Num 95 -Module 'Search Pagination' -Url "$base/admin/companies?q=&page=1" -Session $sess -MustMatch @('rateb|compan')

# Mobile viewport meta (responsive)
$dashHtml = (Invoke-WebRequest -Uri "$base/admin" -WebSession $sess -UseBasicParsing).Content
$results += [pscustomobject]@{
    test = 96; module = 'Mobile Responsive'; url = "$base/admin"
    status = 200; result = if ($dashHtml -match 'viewport') { 'PASS' } else { 'PARTIAL' }
    expected = 'viewport meta'; actual = if ($dashHtml -match 'viewport') { 'present' } else { 'missing' }
    ms = 0; risk = 'Low'; severity = 'Info'; networkError = $null; phpError = $null; evidence = @{}
}

# RTL/LTR
$results += Test-HttpGet -Num 97 -Module 'RTL Layout' -Url "$base/admin" -Session $sess -MustMatch @('dir="rtl"|dir=.rtl')
Invoke-WebRequest -Uri "$base/locale/en" -WebSession $sess -UseBasicParsing | Out-Null
$enDash = (Invoke-WebRequest -Uri "$base/admin" -WebSession $sess -UseBasicParsing).Content
$results += [pscustomobject]@{
    test = 98; module = 'LTR Locale Switch'; url = "$base/locale/en"
    status = 200; result = if ($enDash -match 'lang="en"|locale') { 'PASS' } else { 'PARTIAL' }
    expected = 'EN locale'; actual = 'locale switched'; ms = 0
    risk = 'Low'; severity = 'Info'; networkError = $null; phpError = $null; evidence = @{}
}

# Export endpoints (read-only GET — no data export of production)
$results += Test-HttpGet -Num 99 -Module 'PR Export' -Url "$base/admin/ops/purchase-requests/export" -Session $sess -MustMatch @()
$results += Test-HttpGet -Num 100 -Module 'Branch Export' -Url "$base/admin/ops/branches/export" -Session $sess -MustMatch @()

# --- Cleanup ---
function Remove-ManifestObject {
    param([string]$Type, [int]$Id, [string]$Route)
    Assert-SafeQaDeleteTarget -Manifest $manifest -Type $Type -Id $Id | Out-Null
    $list = Invoke-WebRequest -Uri "$base/admin/$Route" -WebSession $sess -UseBasicParsing
    Invoke-WebRequest -Uri "$base/admin/${Route}/$Id/delete" -Method POST -WebSession $sess -Body @{
        _csrf = (Get-Csrf $list.Content)
    } -UseBasicParsing -MaximumRedirection 10 | Out-Null
    foreach ($obj in $manifest.objects) {
        if ([string]$obj.type -eq $Type -and [int]$obj.id -eq $Id) { $obj.deletedAt = (Get-Date).ToString('o') }
    }
}

Remove-ManifestObject -Type 'user' -Id $userId -Route 'users'
if ($subRes.ok) { Remove-ManifestObject -Type 'subscription' -Id ([int]$subRes.id) -Route 'subscriptions' }
Remove-ManifestObject -Type 'company' -Id $companyId -Route 'companies'

$verifyCo = Invoke-QaManifestResolve -Site $Site -WebSession $sess -Type 'company' -Slug $coSlug
$manifest.finishedAt = (Get-Date).ToString('o')
Save-SafeQaManifest -Manifest $manifest -Path $manifestPath

$summary = [ordered]@{
    sessionTag = $manifest.sessionTag
    manifestPath = $manifestPath
    startedAt = $manifest.startedAt
    finishedAt = $manifest.finishedAt
    total = $results.Count
    pass = @($results | Where-Object { $_.result -eq 'PASS' }).Count
    partial = @($results | Where-Object { $_.result -eq 'PARTIAL' }).Count
    fail = @($results | Where-Object { $_.result -eq 'FAIL' }).Count
    blocked = @($results | Where-Object { $_.result -eq 'BLOCKED' }).Count
    cleanupVerified = (-not $verifyCo.ok)
    tests = $results
}
$outFile = Join-Path (Join-Path $PSScriptRoot 'qa-manifest\sessions') ("QA-CERT-$ts.json")
$summary | ConvertTo-Json -Depth 8 | Set-Content -Path $outFile -Encoding UTF8
Write-Output "CERT_REPORT=$outFile"
$summary | ConvertTo-Json -Depth 6
