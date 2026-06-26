# RATIB ERP Safe QA v2 — Tests 18+ read-only (no writes, no cleanup needed)
param([string]$Site = 'https://rateb.sa')

$ErrorActionPreference = 'Stop'
$base = "$Site/rateb-erp/public"
$adminEmail = 'admin@rateb.sa'
$adminPw = if ($env:RATEB_QA_PASSWORD) { $env:RATEB_QA_PASSWORD } else { 'password' }

function Get-Csrf($html) {
    $m = [regex]::Match($html, 'name="_csrf"\s+value="([^"]+)"')
    if ($m.Success) { return $m.Groups[1].Value }
    return ''
}

$sess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$lp = Invoke-WebRequest -Uri "$base/login" -WebSession $sess -UseBasicParsing
$lr = Invoke-WebRequest -Uri "$base/login" -Method POST -WebSession $sess -Body @{
    email = $adminEmail; password = $adminPw; _csrf = (Get-Csrf $lp.Content)
} -UseBasicParsing -MaximumRedirection 10

if ($lr.Content -match 'password-form' -and $lr.BaseResponse.ResponseUri -match '/login') {
    throw 'SAFE QA STOP: super-admin login failed (credentials or rate limit). Wait 15 min and retry.'
}

$routes = [ordered]@{
    '18' = @{ name = 'queue-monitor'; url = "$base/admin/queue-monitor" }
    '19' = @{ name = 'automation-health'; url = "$base/admin/automation-health" }
    '20' = @{ name = 'reports'; url = "$base/admin/reports" }
    '21' = @{ name = 'executive-dashboard'; url = "$base/admin/executive-dashboard" }
    '22' = @{ name = 'settings-read'; url = "$base/admin/settings" }
}

$report = [ordered]@{ tests = @{} }
foreach ($num in $routes.Keys) {
    $r = $routes[$num]
    try {
        $resp = Invoke-WebRequest -Uri $r.url -WebSession $sess -UseBasicParsing
        $isLogin = ($resp.Content -match 'password-form')
        $report.tests[$num] = @{
            result = if ($resp.StatusCode -eq 200 -and -not $isLogin) { 'PASS' } else { 'PARTIAL' }
            url = $r.url
            status = $resp.StatusCode
            redirectedToLogin = $isLogin
            name = $r.name
        }
    } catch {
        $code = if ($_.Exception.Response) { [int]$_.Exception.Response.StatusCode } else { 0 }
        $report.tests[$num] = @{
            result = 'FAIL'
            url = $r.url
            status = $code
            error = $_.Exception.Message
            name = $r.name
        }
    }
}

$report | ConvertTo-Json -Depth 6
